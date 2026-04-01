<?php

namespace RyderAsKing\LaravelAiTrace\Services;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use RyderAsKing\LaravelAiTrace\Models\Span;
use RyderAsKing\LaravelAiTrace\Models\Trace;
use RyderAsKing\LaravelAiTrace\Support\PrivacyRedactor;
use RyderAsKing\LaravelAiTrace\Support\SdkCorrelationStore;
use RyderAsKing\LaravelAiTrace\Support\SdkDeduplicator;
use Traversable;

class SdkLifecycleManager
{
    public function __construct(
        protected TraceManager $traceManager,
        protected SdkCorrelationStore $correlationStore,
        protected SdkDeduplicator $deduplicator,
        protected PrivacyRedactor $redactor,
    ) {
    }

    public function handle(string $eventName, mixed $event): void
    {
        $shortName = $this->shortName($eventName);

        match ($shortName) {
            'PromptingAgent', 'StreamingAgent' => $this->startInvocation($event, $shortName),
            'InvokingTool' => $this->startToolSpan($event),
            'ToolInvoked' => $this->completeToolSpan($event, $shortName),
            'AgentPrompted', 'AgentStreamed' => $this->completeInvocation($event, $shortName),
            'GeneratingImage', 'GeneratingAudio', 'GeneratingTranscription', 'GeneratingEmbeddings',
            'Reranking', 'StoringFile', 'CreatingStore', 'AddingFileToStore', 'RemovingFileFromStore'
                => $this->startNonAgentOperation($event, $shortName),
            'ImageGenerated', 'AudioGenerated', 'TranscriptionGenerated', 'EmbeddingsGenerated',
            'Reranked', 'FileStored', 'StoreCreated', 'FileAddedToStore', 'FileRemovedFromStore'
                => $this->completeNonAgentOperation($event, $shortName),
            'FileDeleted', 'StoreDeleted' => $this->completeStandaloneOperation($event, $shortName),
            'AgentFailedOver', 'ProviderFailedOver' => $this->recordFailover($event, $shortName),
            default => $this->recordSdkMilestone($shortName, $event),
        };
    }

    protected function startInvocation(mixed $event, string $eventName): void
    {
        $invocationId = $this->stringField($event, 'invocationId');

        if ($invocationId === null) {
            return;
        }

        $trace = Trace::query()->firstOrCreate(
            ['trace_id' => $invocationId],
            [
                'name' => 'agent:'.$eventName,
                'status' => 'ok',
                'started_at' => now(),
                'meta' => ['invocation_id' => $invocationId, 'event' => $eventName],
            ]
        );

        $rootSpan = Span::query()->firstOrCreate(
            ['span_id' => $invocationId],
            [
                'trace_id' => $trace->id,
                'parent_span_id' => null,
                'span_type' => 'agent',
                'name' => 'agent.prompt',
                'input_text' => $this->extractPromptText($event),
                'provider' => $this->stringField($event, 'provider'),
                'model_raw' => $this->stringField($event, 'model'),
                'model_normalized' => $this->stringField($event, 'model'),
                'status' => 'ok',
                'started_at' => now(),
                'meta' => ['invocation_id' => $invocationId],
            ]
        );

        if (! $rootSpan->started_at) {
            $rootSpan->started_at = now();
            $rootSpan->save();
        }

        if (! $rootSpan->input_text) {
            $promptText = $this->extractPromptText($event);

            if ($promptText !== null) {
                $rootSpan->input_text = $promptText;
                $rootSpan->save();
            }
        }

        $this->correlationStore->putInvocation($invocationId, [
            'trace_id' => $trace->id,
            'root_span_id' => $rootSpan->id,
            'root_span_key' => $rootSpan->span_id,
        ]);

        $this->recordEventDeduped($trace, $rootSpan, $this->sdkEventType($eventName), $this->sdkPayload($event), [
            'scope' => 'invocation-start',
            'invocation_id' => $invocationId,
        ]);
    }

    protected function completeInvocation(mixed $event, string $eventName): void
    {
        $invocationId = $this->stringField($event, 'invocationId');

        if ($invocationId === null) {
            return;
        }

        [$trace, $rootSpan] = $this->resolveContext($invocationId);

        if (! $trace || ! $rootSpan) {
            $this->startInvocation($event, $eventName);
            [$trace, $rootSpan] = $this->resolveContext($invocationId);
        }

        if (! $trace || ! $rootSpan) {
            return;
        }

        if ($eventName === 'AgentStreamed') {
            $this->ingestStreamEvents($trace, $rootSpan, $event);
        }

        $this->hydrateCompletionMetrics($trace, $rootSpan, $event);

        $this->recordEventDeduped($trace, $rootSpan, $this->sdkEventType($eventName), $this->sdkPayload($event), [
            'scope' => 'invocation-complete',
            'invocation_id' => $invocationId,
        ]);

        if (! $rootSpan->ended_at) {
            $this->traceManager->endSpan($rootSpan, ['status' => 'ok']);
        }

        if (! $trace->ended_at) {
            $this->traceManager->endTrace($trace, ['status' => 'ok']);
        }

        $this->correlationStore->forgetInvocation($invocationId);
    }

    protected function startToolSpan(mixed $event): void
    {
        $invocationId = $this->stringField($event, 'invocationId');
        $toolInvocationId = $this->stringField($event, 'toolInvocationId');

        if ($invocationId === null || $toolInvocationId === null) {
            return;
        }

        $context = $this->correlationStore->getInvocation($invocationId);

        if ($context === null) {
            $this->startInvocation($event, 'PromptingAgent');
            $context = $this->correlationStore->getInvocation($invocationId);
        }

        if ($context === null) {
            return;
        }

        $trace = Trace::query()->find($context['trace_id']);

        if (! $trace instanceof Trace) {
            return;
        }

        $span = Span::query()->firstOrCreate(
            ['span_id' => $toolInvocationId],
            [
                'trace_id' => $trace->id,
                'parent_span_id' => $context['root_span_key'],
                'span_type' => 'tool',
                'name' => $this->toolName($event),
                'input_text' => $this->stringifySanitized($this->field($event, 'arguments')),
                'status' => 'ok',
                'started_at' => now(),
                'meta' => [
                    'invocation_id' => $invocationId,
                    'tool_invocation_id' => $toolInvocationId,
                    'arguments' => $this->redactor->sanitizeValue($this->field($event, 'arguments')),
                ],
            ]
        );

        $this->correlationStore->putTool($toolInvocationId, $span->id);

        $this->recordEventDeduped($trace, $span, $this->sdkEventType('InvokingTool'), $this->sdkPayload($event), [
            'scope' => 'tool-start',
            'tool_invocation_id' => $toolInvocationId,
            'invocation_id' => $invocationId,
        ]);
    }

    protected function completeToolSpan(mixed $event, string $eventName): void
    {
        $invocationId = $this->stringField($event, 'invocationId');
        $toolInvocationId = $this->stringField($event, 'toolInvocationId');

        if ($toolInvocationId === null) {
            return;
        }

        [$trace] = $this->resolveContext($invocationId);

        $spanId = $this->correlationStore->getTool($toolInvocationId);
        $span = $spanId ? Span::query()->find($spanId) : Span::query()->where('span_id', $toolInvocationId)->first();

        if (! $span instanceof Span) {
            $this->startToolSpan($event);
            $spanId = $this->correlationStore->getTool($toolInvocationId);
            $span = $spanId ? Span::query()->find($spanId) : Span::query()->where('span_id', $toolInvocationId)->first();
        }

        if (! $span instanceof Span) {
            return;
        }

        if (! $trace instanceof Trace) {
            $trace = Trace::query()->find($span->trace_id);
        }

        if ($trace instanceof Trace) {
            $sanitizedResult = $this->redactor->sanitizeValue($this->field($event, 'result'));
            $span->output_text = $this->stringifySanitized($sanitizedResult) ?? $span->output_text;
            $spanMeta = is_array($span->meta) ? $span->meta : [];
            $spanMeta['result'] = $sanitizedResult;
            $span->meta = $spanMeta;
            $span->save();

            $this->recordEventDeduped($trace, $span, $this->sdkEventType($eventName), $this->sdkPayload($event), [
                'scope' => 'tool-complete',
                'tool_invocation_id' => $toolInvocationId,
                'invocation_id' => $invocationId,
            ]);
        }

        if (! $span->ended_at) {
            $this->traceManager->endSpan($span, ['status' => 'ok']);
        }

        $this->correlationStore->forgetTool($toolInvocationId);
    }

    protected function startNonAgentOperation(mixed $event, string $eventName): void
    {
        $invocationId = $this->stringField($event, 'invocationId');
        $blueprint = $this->operationBlueprint($eventName);

        if ($invocationId === null || $blueprint === null) {
            return;
        }

        $operation = $blueprint['operation'];
        $spanKey = $this->operationSpanKey($invocationId, $operation);

        $trace = Trace::query()->firstOrCreate(
            ['trace_id' => $invocationId],
            [
                'name' => $blueprint['name'],
                'status' => 'ok',
                'started_at' => now(),
                'meta' => ['invocation_id' => $invocationId, 'operation' => $operation],
            ]
        );

        $span = Span::query()->firstOrCreate(
            ['span_id' => $spanKey],
            [
                'trace_id' => $trace->id,
                'parent_span_id' => null,
                'span_type' => $blueprint['span_type'],
                'name' => $blueprint['name'],
                'input_text' => $this->extractOperationInput($event),
                'provider' => $this->stringField($event, 'provider'),
                'model_raw' => $this->stringField($event, 'model'),
                'model_normalized' => $this->stringField($event, 'model'),
                'status' => 'ok',
                'started_at' => now(),
                'meta' => ['invocation_id' => $invocationId, 'operation' => $operation],
            ]
        );

        $this->correlationStore->putOperation($invocationId, $operation, [
            'trace_id' => $trace->id,
            'root_span_id' => $span->id,
            'root_span_key' => $span->span_id,
        ]);

        $this->recordEventDeduped($trace, $span, $this->sdkEventType($eventName), $this->sdkPayload($event), [
            'scope' => 'non-agent-start',
            'operation' => $operation,
            'invocation_id' => $invocationId,
        ]);
    }

    protected function completeNonAgentOperation(mixed $event, string $eventName): void
    {
        $invocationId = $this->stringField($event, 'invocationId');
        $blueprint = $this->operationBlueprint($eventName);

        if ($invocationId === null || $blueprint === null) {
            return;
        }

        $operation = $blueprint['operation'];
        $context = $this->correlationStore->getOperation($invocationId, $operation);

        if ($context === null) {
            $this->startNonAgentOperation($event, $blueprint['start_event']);
            $context = $this->correlationStore->getOperation($invocationId, $operation);
        }

        if ($context === null) {
            return;
        }

        $trace = Trace::query()->find($context['trace_id']);
        $span = Span::query()->find($context['root_span_id']);

        if (! $trace instanceof Trace || ! $span instanceof Span) {
            return;
        }

        $this->hydrateOperationCompletionMetrics($trace, $span, $event, $operation);

        $this->recordEventDeduped($trace, $span, $this->sdkEventType($eventName), $this->sdkPayload($event), [
            'scope' => 'non-agent-complete',
            'operation' => $operation,
            'invocation_id' => $invocationId,
        ]);

        if (! $span->ended_at) {
            $this->traceManager->endSpan($span, ['status' => 'ok']);
        }

        if (! $trace->ended_at) {
            $this->traceManager->endTrace($trace, ['status' => 'ok']);
        }

        $this->correlationStore->forgetOperation($invocationId, $operation);
    }

    protected function completeStandaloneOperation(mixed $event, string $eventName): void
    {
        $this->startNonAgentOperation($event, $eventName);
        $this->completeNonAgentOperation($event, $eventName);
    }

    /**
     * @return array{operation:string,span_type:string,name:string,start_event:string}|null
     */
    protected function operationBlueprint(string $eventName): ?array
    {
        return match ($eventName) {
            'GeneratingImage', 'ImageGenerated' => ['operation' => 'image_generation', 'span_type' => 'image', 'name' => 'image.generate', 'start_event' => 'GeneratingImage'],
            'GeneratingAudio', 'AudioGenerated' => ['operation' => 'audio_generation', 'span_type' => 'audio', 'name' => 'audio.generate', 'start_event' => 'GeneratingAudio'],
            'GeneratingTranscription', 'TranscriptionGenerated' => ['operation' => 'transcription', 'span_type' => 'transcription', 'name' => 'audio.transcribe', 'start_event' => 'GeneratingTranscription'],
            'GeneratingEmbeddings', 'EmbeddingsGenerated' => ['operation' => 'embeddings', 'span_type' => 'embedding', 'name' => 'embedding.generate', 'start_event' => 'GeneratingEmbeddings'],
            'Reranking', 'Reranked' => ['operation' => 'reranking', 'span_type' => 'reranking', 'name' => 'reranking.score', 'start_event' => 'Reranking'],
            'StoringFile', 'FileStored' => ['operation' => 'file_store', 'span_type' => 'file', 'name' => 'file.store', 'start_event' => 'StoringFile'],
            'FileDeleted' => ['operation' => 'file_delete', 'span_type' => 'file', 'name' => 'file.delete', 'start_event' => 'FileDeleted'],
            'CreatingStore', 'StoreCreated' => ['operation' => 'store_create', 'span_type' => 'vector_store', 'name' => 'store.create', 'start_event' => 'CreatingStore'],
            'AddingFileToStore', 'FileAddedToStore' => ['operation' => 'store_add_file', 'span_type' => 'vector_store', 'name' => 'store.file.add', 'start_event' => 'AddingFileToStore'],
            'RemovingFileFromStore', 'FileRemovedFromStore' => ['operation' => 'store_remove_file', 'span_type' => 'vector_store', 'name' => 'store.file.remove', 'start_event' => 'RemovingFileFromStore'],
            'StoreDeleted' => ['operation' => 'store_delete', 'span_type' => 'vector_store', 'name' => 'store.delete', 'start_event' => 'StoreDeleted'],
            default => null,
        };
    }

    protected function operationSpanKey(string $invocationId, string $operation): string
    {
        $hash = md5($invocationId.'::'.$operation);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12)
        );
    }

    protected function extractOperationInput(mixed $event): ?string
    {
        $prompt = $this->field($event, 'prompt');

        if ($prompt !== null) {
            $payload = [
                'prompt' => $this->field($prompt, 'prompt') ?? $this->field($prompt, 'text') ?? null,
                'instructions' => $this->field($prompt, 'instructions'),
                'query' => $this->field($prompt, 'query'),
                'documents' => $this->field($prompt, 'documents'),
                'inputs' => $this->field($prompt, 'inputs'),
                'language' => $this->field($prompt, 'language'),
            ];

            return $this->stringifySanitized(array_filter($payload, fn ($value): bool => $value !== null));
        }

        $payload = [
            'store_id' => $this->field($event, 'storeId'),
            'file_id' => $this->field($event, 'fileId'),
            'document_id' => $this->field($event, 'documentId'),
            'file_ids' => $this->field($event, 'fileIds'),
            'name' => $this->field($event, 'name'),
            'description' => $this->field($event, 'description'),
        ];

        $payload = array_filter($payload, fn ($value): bool => $value !== null);

        return $payload === [] ? null : $this->stringifySanitized($payload);
    }

    protected function hydrateOperationCompletionMetrics(Trace $trace, Span $span, mixed $event, string $operation): void
    {
        $response = is_object($event) ? $this->field($event, 'response') : null;

        if (is_object($response)) {
            $usage = $this->extractUsage($this->field($response, 'usage'));

            if ($usage === null && $operation === 'embeddings') {
                $tokens = $this->intField($response, 'tokens', 'tokens');
                if ($tokens > 0) {
                    $usage = [
                        'prompt_tokens' => $tokens,
                        'completion_tokens' => 0,
                        'total_tokens' => $tokens,
                        'cache_write_input_tokens' => 0,
                        'cache_read_input_tokens' => 0,
                        'reasoning_tokens' => 0,
                    ];
                }
            }

            if ($usage !== null) {
                $span->input_tokens = $usage['prompt_tokens'];
                $span->output_tokens = $usage['completion_tokens'];
                $span->total_tokens = $usage['total_tokens'];
            }

            $meta = $this->extractMeta($this->field($response, 'meta'));

            if (! $span->provider && isset($meta['provider'])) {
                $span->provider = (string) $meta['provider'];
            }

            if (! $span->model_normalized && isset($meta['model'])) {
                $span->model_raw = (string) $meta['model'];
                $span->model_normalized = (string) $meta['model'];
            }

            $outputText = $this->extractOperationOutput($response, $operation);

            if ($outputText !== null) {
                $span->output_text = $outputText;
            }

            $metaPayload = is_array($span->meta) ? $span->meta : [];
            $metaPayload['response_meta'] = $meta ? $this->redactor->sanitizeValue($meta) : null;
            $span->meta = array_filter($metaPayload, fn ($value): bool => $value !== null);
            $span->save();

            $this->refreshTraceUsageTotals($trace);

            return;
        }

        $span->output_text = $this->stringifySanitized([
            'file_id' => $this->field($event, 'fileId'),
            'store_id' => $this->field($event, 'storeId'),
            'document_id' => $this->field($event, 'documentId'),
        ]);
        $span->save();
    }

    protected function extractOperationOutput(object $response, string $operation): ?string
    {
        return match ($operation) {
            'image_generation' => $this->stringifySanitized(['image_count' => $this->countIterable($this->field($response, 'images'))]),
            'audio_generation' => $this->stringifySanitized(['audio' => $this->field($response, 'audio')]),
            'transcription' => $this->redactor->sanitizeText($this->stringField($response, 'text')),
            'embeddings' => $this->stringifySanitized(['embedding_count' => $this->countIterable($this->field($response, 'embeddings'))]),
            'reranking' => $this->stringifySanitized(['result_count' => $this->countIterable($this->field($response, 'results'))]),
            'file_store' => $this->stringifySanitized(['stored_file_id' => $this->field($response, 'id')]),
            'store_create' => $this->stringifySanitized(['store_id' => $this->field($this->field($response, 'store'), 'id')]),
            default => null,
        };
    }

    protected function countIterable(mixed $value): int
    {
        if (is_array($value)) {
            return count($value);
        }

        if ($value instanceof \Countable) {
            return count($value);
        }

        return 0;
    }

    protected function recordSdkMilestone(string $eventName, mixed $event): void
    {
        $invocationId = $this->stringField($event, 'invocationId');

        if ($invocationId === null) {
            return;
        }

        [$trace, $rootSpan] = $this->resolveContext($invocationId);

        if (! $trace || ! $rootSpan) {
            return;
        }

        $this->recordEventDeduped($trace, $rootSpan, $this->sdkEventType($eventName), $this->sdkPayload($event), [
            'scope' => 'sdk-milestone',
            'invocation_id' => $invocationId,
        ]);
    }

    protected function recordFailover(mixed $event, string $eventName): void
    {
        $invocationId = $this->stringField($event, 'invocationId') ?? $this->correlationStore->latestInvocationId();

        [$trace, $rootSpan] = $this->resolveContext($invocationId);

        if (! $trace || ! $rootSpan || ! $invocationId) {
            return;
        }

        $attempt = $this->correlationStore->nextFailoverAttempt($invocationId);

        $payload = array_merge($this->sdkPayload($event), [
            'attempt' => $attempt,
            'exception_class' => $this->exceptionField($event, 'class'),
            'exception_message' => $this->exceptionField($event, 'message'),
        ]);

        $this->recordEventDeduped($trace, $rootSpan, $this->sdkEventType($eventName), $payload, [
            'scope' => 'failover',
            'invocation_id' => $invocationId,
            'attempt' => $attempt,
        ]);
    }

    protected function ingestStreamEvents(Trace $trace, Span $rootSpan, mixed $event): void
    {
        $streamEvents = $this->extractStreamEvents($event);

        if ($streamEvents === null) {
            return;
        }

        $limit = max(1, (int) config('ai-trace.stream.max_events_per_invocation', 1000));
        $captured = 0;
        $total = 0;

        foreach ($streamEvents as $streamEvent) {
            $total++;

            if ($captured >= $limit) {
                continue;
            }

            $payload = $this->streamPayload($streamEvent);
            $eventType = (string) ($payload['type'] ?? 'stream_event');
            $recordedAt = $this->streamTimestamp($payload['timestamp'] ?? null);
            $sanitizedPayload = $this->sanitizeEventPayload($payload);

            $this->recordEventDeduped($trace, $rootSpan, $eventType, $sanitizedPayload, [
                'scope' => 'stream-event',
                'invocation_id' => $this->stringField($payload, 'invocation_id') ?? $trace->trace_id,
                'stream_type' => $eventType,
                'stream_id' => $this->stringField($payload, 'id'),
                'stream_timestamp' => $this->stringField($payload, 'timestamp'),
                'stream_class' => $this->stringField($payload, 'stream_class'),
            ], $recordedAt);
            $captured++;
        }

        if ($total > $captured) {
            $this->recordEventDeduped($trace, $rootSpan, 'stream_truncated', [
                'captured' => $captured,
                'dropped' => $total - $captured,
                'total' => $total,
                'limit' => $limit,
            ], [
                'scope' => 'stream-truncated',
                'invocation_id' => $trace->trace_id,
                'captured' => $captured,
                'dropped' => $total - $captured,
                'limit' => $limit,
            ]);
        }
    }

    protected function extractStreamEvents(mixed $event): iterable|null
    {
        if (! is_object($event) || ! isset($event->response) || ! is_object($event->response)) {
            return null;
        }

        if (! isset($event->response->events)) {
            return null;
        }

        $events = $event->response->events;

        if (is_array($events) || $events instanceof Traversable) {
            return $events;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function streamPayload(mixed $streamEvent): array
    {
        if (is_object($streamEvent) && method_exists($streamEvent, 'toArray')) {
            $payload = $streamEvent->toArray();

            if (is_array($payload)) {
                $payload['stream_class'] = $streamEvent::class;

                return $payload;
            }
        }

        return [
            'type' => 'stream_event',
            'stream_class' => is_object($streamEvent) ? $streamEvent::class : gettype($streamEvent),
        ];
    }

    protected function streamTimestamp(mixed $timestamp): ?DateTimeInterface
    {
        if (! is_numeric($timestamp)) {
            return null;
        }

        $seconds = (int) $timestamp;

        return (new DateTimeImmutable('@'.$seconds))->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * @return array{0: Trace|null, 1: Span|null}
     */
    protected function resolveContext(?string $invocationId): array
    {
        if ($invocationId === null) {
            return [null, null];
        }

        $context = $this->correlationStore->getInvocation($invocationId);

        if ($context !== null) {
            return [
                Trace::query()->find($context['trace_id']),
                Span::query()->find($context['root_span_id']),
            ];
        }

        return [
            Trace::query()->where('trace_id', $invocationId)->first(),
            Span::query()->where('span_id', $invocationId)->first(),
        ];
    }

    protected function sdkEventType(string $eventName): string
    {
        $snake = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $eventName));

        return 'sdk_'.$snake;
    }

    /**
     * @return array<string, mixed>
     */
    protected function sdkPayload(mixed $event): array
    {
        $exception = is_object($event) && isset($event->exception) && is_object($event->exception)
            ? [
                'class' => $event->exception::class,
                'message' => method_exists($event->exception, 'getMessage') ? $event->exception->getMessage() : null,
            ]
            : null;

        return array_filter([
            'invocation_id' => $this->stringField($event, 'invocationId'),
            'tool_invocation_id' => $this->stringField($event, 'toolInvocationId'),
            'provider' => $this->stringField($event, 'provider'),
            'model' => $this->stringField($event, 'model'),
            'arguments' => $this->redactor->sanitizeValue($this->field($event, 'arguments')),
            'result' => $this->redactor->sanitizeValue($this->field($event, 'result')),
            'exception' => $this->redactor->sanitizeValue($exception),
        ], fn ($value): bool => $value !== null && $value !== '');
    }

    protected function hydrateCompletionMetrics(Trace $trace, Span $rootSpan, mixed $event): void
    {
        if (! is_object($event) || ! isset($event->response) || ! is_object($event->response)) {
            return;
        }

        $response = $event->response;
        $usage = $this->extractUsage($response->usage ?? null) ?? $this->extractUsageFromStreamEvents($response->events ?? null);
        $meta = $this->extractMeta($response->meta ?? null);
        $finishReason = $this->extractFinishReason($response);
        $stepCount = $this->countSteps($response->steps ?? null);

        if ($usage !== null) {
            $rootSpan->input_tokens = $usage['prompt_tokens'];
            $rootSpan->output_tokens = $usage['completion_tokens'];
            $rootSpan->total_tokens = $usage['total_tokens'];
        }

        $responseText = $this->stringField($response, 'text');

        if ($responseText !== null && $responseText !== '') {
            $rootSpan->output_text = $this->redactor->sanitizeText($responseText);
        }

        if (! $rootSpan->provider && isset($meta['provider'])) {
            $rootSpan->provider = $meta['provider'];
        }

        if (! $rootSpan->model_normalized && isset($meta['model'])) {
            $rootSpan->model_raw = $meta['model'];
            $rootSpan->model_normalized = $meta['model'];
        }

        $existingMeta = is_array($rootSpan->meta) ? $rootSpan->meta : [];
        $existingMeta['usage'] = $usage;
        $existingMeta['response_meta'] = $meta ? $this->redactor->sanitizeValue($meta) : null;

        if ($finishReason !== null) {
            $existingMeta['finish_reason'] = $finishReason;
        }

        if ($stepCount !== null) {
            $existingMeta['step_count'] = $stepCount;
        }

        $rootSpan->meta = array_filter($existingMeta, fn ($value): bool => $value !== null);
        $rootSpan->save();

        $this->refreshTraceUsageTotals($trace);

        if ($finishReason !== null) {
            $traceMeta = is_array($trace->meta) ? $trace->meta : [];
            $traceMeta['finish_reason'] = $finishReason;
            $trace->meta = $traceMeta;
            $trace->save();
        }
    }

    protected function refreshTraceUsageTotals(Trace $trace): void
    {
        $totals = Span::query()
            ->where('trace_id', $trace->id)
            ->selectRaw('COALESCE(SUM(input_tokens), 0) as input_total, COALESCE(SUM(output_tokens), 0) as output_total, COALESCE(SUM(total_tokens), 0) as total')
            ->first();

        $input = (int) ($totals?->input_total ?? 0);
        $output = (int) ($totals?->output_total ?? 0);
        $total = (int) ($totals?->total ?? 0);

        $trace->total_input_tokens = $input;
        $trace->total_output_tokens = $output;
        $trace->total_tokens = $total;
        $trace->save();
    }

    /**
     * @return array{prompt_tokens:int,completion_tokens:int,total_tokens:int,cache_write_input_tokens:int,cache_read_input_tokens:int,reasoning_tokens:int}|null
     */
    protected function extractUsage(mixed $usage): ?array
    {
        if ($usage === null) {
            return null;
        }

        $prompt = $this->intField($usage, 'promptTokens', 'prompt_tokens');
        $completion = $this->intField($usage, 'completionTokens', 'completion_tokens');
        $cacheWrite = $this->intField($usage, 'cacheWriteInputTokens', 'cache_write_input_tokens');
        $cacheRead = $this->intField($usage, 'cacheReadInputTokens', 'cache_read_input_tokens');
        $reasoning = $this->intField($usage, 'reasoningTokens', 'reasoning_tokens');

        return [
            'prompt_tokens' => $prompt,
            'completion_tokens' => $completion,
            'total_tokens' => $prompt + $completion,
            'cache_write_input_tokens' => $cacheWrite,
            'cache_read_input_tokens' => $cacheRead,
            'reasoning_tokens' => $reasoning,
        ];
    }

    /**
     * @return array{provider?:string,model?:string,citations?:mixed}|null
     */
    protected function extractMeta(mixed $meta): ?array
    {
        if ($meta === null) {
            return null;
        }

        $provider = $this->stringField($meta, 'provider');
        $model = $this->stringField($meta, 'model');
        $citations = $this->field($meta, 'citations');

        $payload = array_filter([
            'provider' => $provider,
            'model' => $model,
            'citations' => $citations,
        ], fn ($value): bool => $value !== null && $value !== []);

        return $payload === [] ? null : $payload;
    }

    protected function extractFinishReason(object $response): ?string
    {
        $reason = $this->field($response, 'finishReason');

        if (is_string($reason) && $reason !== '') {
            return $reason;
        }

        if (is_object($reason) && isset($reason->value) && is_scalar($reason->value)) {
            return (string) $reason->value;
        }

        $events = $this->extractStreamEvents((object) ['response' => $response]);

        if ($events === null) {
            return null;
        }

        $streamEndReasons = [];

        foreach ($events as $streamEvent) {
            $payload = $this->streamPayload($streamEvent);

            if (($payload['type'] ?? null) === 'stream_end' && isset($payload['reason']) && is_scalar($payload['reason'])) {
                $streamEndReasons[] = (string) $payload['reason'];
            }
        }

        return $streamEndReasons === [] ? null : end($streamEndReasons);
    }

    protected function countSteps(mixed $steps): ?int
    {
        if (is_array($steps)) {
            return count($steps);
        }

        if ($steps instanceof Traversable) {
            $count = 0;

            foreach ($steps as $_step) {
                $count++;
            }

            return $count;
        }

        return null;
    }

    /**
     * @return array{prompt_tokens:int,completion_tokens:int,total_tokens:int,cache_write_input_tokens:int,cache_read_input_tokens:int,reasoning_tokens:int}|null
     */
    protected function extractUsageFromStreamEvents(mixed $events): ?array
    {
        if (! is_array($events) && ! $events instanceof Traversable) {
            return null;
        }

        $usage = [
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
            'cache_write_input_tokens' => 0,
            'cache_read_input_tokens' => 0,
            'reasoning_tokens' => 0,
        ];

        $hasUsage = false;

        foreach ($events as $streamEvent) {
            $payload = $this->streamPayload($streamEvent);

            if (($payload['type'] ?? null) !== 'stream_end' || ! isset($payload['usage']) || ! is_array($payload['usage'])) {
                continue;
            }

            $hasUsage = true;
            $usage['prompt_tokens'] += $this->intField($payload['usage'], 'prompt_tokens', 'promptTokens');
            $usage['completion_tokens'] += $this->intField($payload['usage'], 'completion_tokens', 'completionTokens');
            $usage['cache_write_input_tokens'] += $this->intField($payload['usage'], 'cache_write_input_tokens', 'cacheWriteInputTokens');
            $usage['cache_read_input_tokens'] += $this->intField($payload['usage'], 'cache_read_input_tokens', 'cacheReadInputTokens');
            $usage['reasoning_tokens'] += $this->intField($payload['usage'], 'reasoning_tokens', 'reasoningTokens');
        }

        if (! $hasUsage) {
            return null;
        }

        $usage['total_tokens'] = $usage['prompt_tokens'] + $usage['completion_tokens'];

        return $usage;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $dedupParts
     */
    protected function recordEventDeduped(
        Trace $trace,
        Span $span,
        string $eventType,
        array $payload,
        array $dedupParts,
        ?DateTimeInterface $recordedAt = null,
    ): void {
        if ($this->isDuplicate($eventType, $dedupParts)) {
            return;
        }

        $this->traceManager->recordEvent($trace, $span, $eventType, $payload, $recordedAt);
    }

    /**
     * @param  array<string, mixed>  $dedupParts
     */
    protected function isDuplicate(string $eventType, array $dedupParts): bool
    {
        $ttl = max(1, (int) config('ai-trace.dedup_ttl_seconds', 300));
        $payload = array_merge(['event_type' => $eventType], $dedupParts);
        ksort($payload);
        $key = 'sdk-event:'.sha1((string) json_encode($payload));

        return $this->deduplicator->isDuplicate($key, $ttl);
    }

    protected function exceptionField(mixed $event, string $field): ?string
    {
        if (! is_object($event) || ! isset($event->exception) || ! is_object($event->exception)) {
            return null;
        }

        if ($field === 'class') {
            return $event->exception::class;
        }

        if ($field === 'message' && method_exists($event->exception, 'getMessage')) {
            return (string) $event->exception->getMessage();
        }

        return null;
    }

    protected function shortName(string $eventName): string
    {
        $parts = explode('\\', $eventName);

        return (string) end($parts);
    }

    protected function stringField(mixed $event, string $field): ?string
    {
        if (is_array($event)) {
            $value = $event[$field] ?? null;

            return is_scalar($value) ? (string) $value : null;
        }

        if (! is_object($event) || ! isset($event->{$field})) {
            return null;
        }

        $value = $event->{$field};

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return null;
    }

    protected function intField(mixed $source, string $primary, string $secondary): int
    {
        $value = $this->field($source, $primary);

        if ($value === null) {
            $value = $this->field($source, $secondary);
        }

        return is_numeric($value) ? (int) $value : 0;
    }

    protected function field(mixed $source, string $field): mixed
    {
        if (is_array($source)) {
            return $source[$field] ?? null;
        }

        if (is_object($source) && isset($source->{$field})) {
            return $source->{$field};
        }

        return null;
    }

    protected function extractPromptText(mixed $event): ?string
    {
        $prompt = $this->field($event, 'prompt');

        if (is_string($prompt)) {
            return $this->redactor->sanitizeText($prompt);
        }

        $promptText = $this->stringField($prompt, 'prompt');

        return $promptText === null ? null : $this->redactor->sanitizeText($promptText);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function sanitizeEventPayload(array $payload): array
    {
        $safeKeys = [
            'type',
            'id',
            'invocation_id',
            'message_id',
            'reasoning_id',
            'item_id',
            'timestamp',
            'status',
            'stream_class',
            'reason',
        ];

        foreach ($payload as $key => $value) {
            if (in_array((string) $key, $safeKeys, true)) {
                continue;
            }

            $payload[$key] = $this->redactor->sanitizeValue($value);
        }

        return $payload;
    }

    protected function stringifySanitized(mixed $value): ?string
    {
        $sanitized = $this->redactor->sanitizeValue($value);

        if ($sanitized === null) {
            return null;
        }

        if (is_string($sanitized)) {
            return $sanitized;
        }

        if (is_scalar($sanitized)) {
            return (string) $sanitized;
        }

        return json_encode($sanitized);
    }

    protected function toolName(mixed $event): string
    {
        if (! is_object($event) || ! isset($event->tool) || ! is_object($event->tool)) {
            return 'tool.call';
        }

        if (isset($event->tool->name) && is_scalar($event->tool->name)) {
            return 'tool.'.(string) $event->tool->name;
        }

        return 'tool.'.$this->shortName($event->tool::class);
    }
}
