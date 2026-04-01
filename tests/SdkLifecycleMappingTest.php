<?php

namespace RyderAsKing\LaravelAiTrace\Tests;

use RyderAsKing\LaravelAiTrace\Models\Span;
use RyderAsKing\LaravelAiTrace\Models\SpanEvent;
use RyderAsKing\LaravelAiTrace\Models\Trace;
use RyderAsKing\LaravelAiTrace\Support\SdkCorrelationStore;
use RyderAsKing\LaravelAiTrace\Support\SdkDeduplicator;
use RyderAsKing\LaravelAiTrace\Support\SdkEventBuffer;

class SdkLifecycleMappingTest extends TestCase
{
    public function test_agent_and_tool_events_create_and_complete_correlated_spans(): void
    {
        $this->app->make(SdkEventBuffer::class)->flush();
        $this->app->make(SdkCorrelationStore::class)->flush();
        $this->app->make(SdkDeduplicator::class)->flush();

        $invocationId = '00000000-0000-7000-8000-000000000001';
        $toolInvocationId = '00000000-0000-7000-8000-000000000002';

        $startEvent = new \stdClass;
        $startEvent->invocationId = $invocationId;
        $startEvent->provider = 'openai';
        $startEvent->model = 'gpt-4o-mini';

        $toolStartEvent = new \stdClass;
        $toolStartEvent->invocationId = $invocationId;
        $toolStartEvent->toolInvocationId = $toolInvocationId;
        $toolStartEvent->tool = new \stdClass;
        $toolStartEvent->tool->name = 'lookup';

        $toolEndEvent = new \stdClass;
        $toolEndEvent->invocationId = $invocationId;
        $toolEndEvent->toolInvocationId = $toolInvocationId;

        $endEvent = new \stdClass;
        $endEvent->invocationId = $invocationId;

        $this->app['events']->dispatch('Laravel\\Ai\\Events\\PromptingAgent', [$startEvent]);
        $this->app['events']->dispatch('Laravel\\Ai\\Events\\InvokingTool', [$toolStartEvent]);
        $this->app['events']->dispatch('Laravel\\Ai\\Events\\ToolInvoked', [$toolEndEvent]);
        $this->app['events']->dispatch('Laravel\\Ai\\Events\\AgentPrompted', [$endEvent]);

        $trace = Trace::query()->where('trace_id', $invocationId)->first();

        $this->assertNotNull($trace);
        $this->assertNotNull($trace->started_at);
        $this->assertNotNull($trace->ended_at);

        $rootSpan = Span::query()->where('span_id', $invocationId)->first();
        $toolSpan = Span::query()->where('span_id', $toolInvocationId)->first();

        $this->assertNotNull($rootSpan);
        $this->assertSame('agent', $rootSpan->span_type);
        $this->assertNotNull($rootSpan->started_at);
        $this->assertNotNull($rootSpan->ended_at);

        $this->assertNotNull($toolSpan);
        $this->assertSame('tool', $toolSpan->span_type);
        $this->assertSame($invocationId, $toolSpan->parent_span_id);
        $this->assertSame('tool.lookup', $toolSpan->name);
        $this->assertNotNull($toolSpan->started_at);
        $this->assertNotNull($toolSpan->ended_at);

        $eventTypes = SpanEvent::query()
            ->where('trace_id', $trace->id)
            ->orderBy('id')
            ->pluck('event_type')
            ->all();

        $this->assertContains('sdk_prompting_agent', $eventTypes);
        $this->assertContains('sdk_invoking_tool', $eventTypes);
        $this->assertContains('sdk_tool_invoked', $eventTypes);
        $this->assertContains('sdk_agent_prompted', $eventTypes);
    }

    public function test_tool_start_without_agent_start_creates_context_fallback(): void
    {
        $this->app->make(SdkCorrelationStore::class)->flush();
        $this->app->make(SdkDeduplicator::class)->flush();

        $invocationId = '00000000-0000-7000-8000-000000000003';
        $toolInvocationId = '00000000-0000-7000-8000-000000000004';

        $toolStartEvent = new \stdClass;
        $toolStartEvent->invocationId = $invocationId;
        $toolStartEvent->toolInvocationId = $toolInvocationId;

        $this->app['events']->dispatch('Laravel\\Ai\\Events\\InvokingTool', [$toolStartEvent]);

        $this->assertNotNull(Trace::query()->where('trace_id', $invocationId)->first());
        $this->assertNotNull(Span::query()->where('span_id', $invocationId)->first());
        $this->assertNotNull(Span::query()->where('span_id', $toolInvocationId)->first());
    }

    public function test_agent_streamed_event_persists_stream_timeline_events_with_source_timestamps(): void
    {
        $this->app->make(SdkDeduplicator::class)->flush();

        $invocationId = '00000000-0000-7000-8000-000000000005';

        $startEvent = new \stdClass;
        $startEvent->invocationId = $invocationId;

        $streamed = new \stdClass;
        $streamed->invocationId = $invocationId;
        $streamed->response = new \stdClass;
        $streamed->response->events = [
            new class
            {
                public function toArray(): array
                {
                    return ['type' => 'stream_start', 'timestamp' => 1710000000, 'id' => 's1'];
                }
            },
            new class
            {
                public function toArray(): array
                {
                    return ['type' => 'text_delta', 'timestamp' => 1710000001, 'delta' => 'hello'];
                }
            },
            new class
            {
                public function toArray(): array
                {
                    return ['type' => 'reasoning_delta', 'timestamp' => 1710000002, 'delta' => 'plan'];
                }
            },
        ];

        $this->app['events']->dispatch('Laravel\\Ai\\Events\\PromptingAgent', [$startEvent]);
        $this->app['events']->dispatch('Laravel\\Ai\\Events\\AgentStreamed', [$streamed]);

        $trace = Trace::query()->where('trace_id', $invocationId)->first();
        $this->assertNotNull($trace);

        $events = SpanEvent::query()
            ->where('trace_id', $trace->id)
            ->orderBy('id')
            ->get();

        $this->assertContains('stream_start', $events->pluck('event_type')->all());
        $this->assertContains('text_delta', $events->pluck('event_type')->all());
        $this->assertContains('reasoning_delta', $events->pluck('event_type')->all());
        $this->assertContains('sdk_agent_streamed', $events->pluck('event_type')->all());

        $streamStart = $events->firstWhere('event_type', 'stream_start');
        $this->assertNotNull($streamStart);
        $this->assertSame('2024-03-09 16:00:00', $streamStart->recorded_at?->setTimezone('UTC')->format('Y-m-d H:i:s'));
    }

    public function test_stream_events_are_truncated_when_over_configured_limit(): void
    {
        $this->app->make(SdkDeduplicator::class)->flush();

        config()->set('ai-trace.stream.max_events_per_invocation', 2);

        $invocationId = '00000000-0000-7000-8000-000000000006';

        $startEvent = new \stdClass;
        $startEvent->invocationId = $invocationId;

        $streamed = new \stdClass;
        $streamed->invocationId = $invocationId;
        $streamed->response = new \stdClass;
        $streamed->response->events = [
            new class
            {
                public function toArray(): array
                {
                    return ['type' => 'stream_start', 'timestamp' => 1710000100];
                }
            },
            new class
            {
                public function toArray(): array
                {
                    return ['type' => 'text_start', 'timestamp' => 1710000101];
                }
            },
            new class
            {
                public function toArray(): array
                {
                    return ['type' => 'text_delta', 'timestamp' => 1710000102];
                }
            },
        ];

        $this->app['events']->dispatch('Laravel\\Ai\\Events\\PromptingAgent', [$startEvent]);
        $this->app['events']->dispatch('Laravel\\Ai\\Events\\AgentStreamed', [$streamed]);

        $trace = Trace::query()->where('trace_id', $invocationId)->first();
        $this->assertNotNull($trace);

        $eventTypes = SpanEvent::query()
            ->where('trace_id', $trace->id)
            ->pluck('event_type')
            ->all();

        $this->assertContains('stream_start', $eventTypes);
        $this->assertContains('text_start', $eventTypes);
        $this->assertNotContains('text_delta', $eventTypes);
        $this->assertContains('stream_truncated', $eventTypes);
    }

    public function test_duplicate_callbacks_are_deduplicated_by_ttl_keying(): void
    {
        $this->app->make(SdkDeduplicator::class)->flush();

        $invocationId = '00000000-0000-7000-8000-000000000007';

        $startEvent = new \stdClass;
        $startEvent->invocationId = $invocationId;

        $endEvent = new \stdClass;
        $endEvent->invocationId = $invocationId;

        $this->app['events']->dispatch('Laravel\\Ai\\Events\\PromptingAgent', [$startEvent]);
        $this->app['events']->dispatch('Laravel\\Ai\\Events\\PromptingAgent', [$startEvent]);
        $this->app['events']->dispatch('Laravel\\Ai\\Events\\AgentPrompted', [$endEvent]);
        $this->app['events']->dispatch('Laravel\\Ai\\Events\\AgentPrompted', [$endEvent]);

        $trace = Trace::query()->where('trace_id', $invocationId)->first();
        $this->assertNotNull($trace);

        $this->assertSame(1, SpanEvent::query()->where('trace_id', $trace->id)->where('event_type', 'sdk_prompting_agent')->count());
        $this->assertSame(1, SpanEvent::query()->where('trace_id', $trace->id)->where('event_type', 'sdk_agent_prompted')->count());
    }

    public function test_out_of_order_tool_events_still_create_and_complete_tool_span(): void
    {
        $this->app->make(SdkDeduplicator::class)->flush();

        $invocationId = '00000000-0000-7000-8000-000000000008';
        $toolInvocationId = '00000000-0000-7000-8000-000000000009';

        $toolEnd = new \stdClass;
        $toolEnd->invocationId = $invocationId;
        $toolEnd->toolInvocationId = $toolInvocationId;

        $this->app['events']->dispatch('Laravel\\Ai\\Events\\ToolInvoked', [$toolEnd]);

        $toolSpan = Span::query()->where('span_id', $toolInvocationId)->first();
        $this->assertNotNull($toolSpan);
        $this->assertNotNull($toolSpan->ended_at);
    }

    public function test_failover_events_are_tracked_with_attempt_context(): void
    {
        $this->app->make(SdkDeduplicator::class)->flush();

        $invocationId = '00000000-0000-7000-8000-000000000010';

        $startEvent = new \stdClass;
        $startEvent->invocationId = $invocationId;

        $failover = new \stdClass;
        $failover->provider = 'openai';
        $failover->model = 'gpt-4o';
        $failover->exception = new \RuntimeException('Provider timeout');

        $this->app['events']->dispatch('Laravel\\Ai\\Events\\PromptingAgent', [$startEvent]);
        $this->app['events']->dispatch('Laravel\\Ai\\Events\\AgentFailedOver', [$failover]);
        $this->app['events']->dispatch('Laravel\\Ai\\Events\\ProviderFailedOver', [$failover]);

        $trace = Trace::query()->where('trace_id', $invocationId)->first();
        $this->assertNotNull($trace);

        $events = SpanEvent::query()->where('trace_id', $trace->id)
            ->whereIn('event_type', ['sdk_agent_failed_over', 'sdk_provider_failed_over'])
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $events);
        $this->assertSame(1, $events[0]->payload['attempt'] ?? null);
        $this->assertSame(2, $events[1]->payload['attempt'] ?? null);
        $this->assertSame('Provider timeout', $events[0]->payload['exception_message'] ?? null);
    }

    public function test_agent_prompted_response_usage_and_meta_populate_span_and_trace_totals(): void
    {
        $this->app->make(SdkDeduplicator::class)->flush();

        $invocationId = '00000000-0000-7000-8000-000000000011';

        $startEvent = new \stdClass;
        $startEvent->invocationId = $invocationId;

        $response = new \stdClass;
        $response->usage = new \stdClass;
        $response->usage->promptTokens = 120;
        $response->usage->completionTokens = 45;
        $response->usage->cacheWriteInputTokens = 10;
        $response->usage->cacheReadInputTokens = 5;
        $response->usage->reasoningTokens = 8;
        $response->meta = new \stdClass;
        $response->meta->provider = 'openai';
        $response->meta->model = 'gpt-4.1-mini';
        $response->finishReason = new class
        {
            public string $value = 'stop';
        };
        $response->steps = [new \stdClass, new \stdClass];

        $endEvent = new \stdClass;
        $endEvent->invocationId = $invocationId;
        $endEvent->response = $response;

        $this->app['events']->dispatch('Laravel\\Ai\\Events\\PromptingAgent', [$startEvent]);
        $this->app['events']->dispatch('Laravel\\Ai\\Events\\AgentPrompted', [$endEvent]);

        $trace = Trace::query()->where('trace_id', $invocationId)->first();
        $this->assertNotNull($trace);
        $this->assertSame(120, (int) $trace->total_input_tokens);
        $this->assertSame(45, (int) $trace->total_output_tokens);
        $this->assertSame(165, (int) $trace->total_tokens);
        $this->assertSame('stop', $trace->meta['finish_reason'] ?? null);

        $rootSpan = Span::query()->where('span_id', $invocationId)->first();
        $this->assertNotNull($rootSpan);
        $this->assertSame(120, (int) $rootSpan->input_tokens);
        $this->assertSame(45, (int) $rootSpan->output_tokens);
        $this->assertSame(165, (int) $rootSpan->total_tokens);
        $this->assertSame('openai', $rootSpan->provider);
        $this->assertSame('gpt-4.1-mini', $rootSpan->model_normalized);
        $this->assertSame(2, $rootSpan->meta['step_count'] ?? null);
    }

    public function test_agent_streamed_uses_stream_end_usage_when_response_usage_missing(): void
    {
        $this->app->make(SdkDeduplicator::class)->flush();

        $invocationId = '00000000-0000-7000-8000-000000000012';

        $startEvent = new \stdClass;
        $startEvent->invocationId = $invocationId;

        $streamed = new \stdClass;
        $streamed->invocationId = $invocationId;
        $streamed->response = new \stdClass;
        $streamed->response->events = [
            new class
            {
                public function toArray(): array
                {
                    return [
                        'type' => 'stream_end',
                        'reason' => 'length',
                        'timestamp' => 1710000200,
                        'usage' => [
                            'prompt_tokens' => 55,
                            'completion_tokens' => 20,
                            'cache_write_input_tokens' => 3,
                            'cache_read_input_tokens' => 2,
                            'reasoning_tokens' => 4,
                        ],
                    ];
                }
            },
        ];

        $this->app['events']->dispatch('Laravel\\Ai\\Events\\PromptingAgent', [$startEvent]);
        $this->app['events']->dispatch('Laravel\\Ai\\Events\\AgentStreamed', [$streamed]);

        $trace = Trace::query()->where('trace_id', $invocationId)->first();
        $this->assertNotNull($trace);
        $this->assertSame(55, (int) $trace->total_input_tokens);
        $this->assertSame(20, (int) $trace->total_output_tokens);
        $this->assertSame(75, (int) $trace->total_tokens);
        $this->assertSame('length', $trace->meta['finish_reason'] ?? null);

        $rootSpan = Span::query()->where('span_id', $invocationId)->first();
        $this->assertNotNull($rootSpan);
        $this->assertSame(75, (int) $rootSpan->total_tokens);
        $this->assertSame('length', $rootSpan->meta['finish_reason'] ?? null);
        $this->assertSame(4, $rootSpan->meta['usage']['reasoning_tokens'] ?? null);
    }

    public function test_redacted_mode_sanitizes_prompt_response_tool_and_event_payloads_before_persisting(): void
    {
        $this->app->make(SdkDeduplicator::class)->flush();
        config()->set('ai-trace.record_content_mode', 'redacted');

        $invocationId = '00000000-0000-7000-8000-000000000013';
        $toolInvocationId = '00000000-0000-7000-8000-000000000014';

        $start = new \stdClass;
        $start->invocationId = $invocationId;
        $start->prompt = new \stdClass;
        $start->prompt->prompt = 'email john@example.com token sk_live_1234567890';

        $toolStart = new \stdClass;
        $toolStart->invocationId = $invocationId;
        $toolStart->toolInvocationId = $toolInvocationId;
        $toolStart->arguments = ['phone' => '+12025551234'];

        $toolEnd = new \stdClass;
        $toolEnd->invocationId = $invocationId;
        $toolEnd->toolInvocationId = $toolInvocationId;
        $toolEnd->arguments = ['token' => 'sk_live_abcdefghijk'];
        $toolEnd->result = ['contact' => 'jane@example.com'];

        $streamed = new \stdClass;
        $streamed->invocationId = $invocationId;
        $streamed->response = new \stdClass;
        $streamed->response->text = 'Call me at +12025551234';
        $streamed->response->events = [
            new class
            {
                public function toArray(): array
                {
                    return ['type' => 'text_delta', 'timestamp' => 1710000300, 'delta' => 'john@example.com'];
                }
            },
        ];

        $this->app['events']->dispatch('Laravel\\Ai\\Events\\PromptingAgent', [$start]);
        $this->app['events']->dispatch('Laravel\\Ai\\Events\\InvokingTool', [$toolStart]);
        $this->app['events']->dispatch('Laravel\\Ai\\Events\\ToolInvoked', [$toolEnd]);
        $this->app['events']->dispatch('Laravel\\Ai\\Events\\AgentStreamed', [$streamed]);

        $rootSpan = Span::query()->where('span_id', $invocationId)->first();
        $this->assertNotNull($rootSpan);
        $this->assertStringContainsString('[redacted-email]', (string) $rootSpan->input_text);
        $this->assertStringContainsString('[redacted-phone]', (string) $rootSpan->output_text);

        $toolSpan = Span::query()->where('span_id', $toolInvocationId)->first();
        $this->assertNotNull($toolSpan);
        $this->assertStringContainsString('[redacted-phone]', (string) $toolSpan->input_text);
        $this->assertStringContainsString('[redacted-email]', (string) $toolSpan->output_text);

        $streamEvent = SpanEvent::query()->where('event_type', 'text_delta')->first();
        $this->assertNotNull($streamEvent);
        $this->assertSame('[redacted-email]', $streamEvent->payload['delta'] ?? null);
    }

    public function test_none_mode_hides_content_and_hash_mode_hashes_content(): void
    {
        $this->app->make(SdkDeduplicator::class)->flush();

        config()->set('ai-trace.record_content_mode', 'none');

        $invocationNone = '00000000-0000-7000-8000-000000000015';
        $startNone = new \stdClass;
        $startNone->invocationId = $invocationNone;
        $startNone->prompt = new \stdClass;
        $startNone->prompt->prompt = 'secret@example.com';
        $endNone = new \stdClass;
        $endNone->invocationId = $invocationNone;
        $endNone->response = new \stdClass;
        $endNone->response->text = 'sk_live_12345678';

        $this->app['events']->dispatch('Laravel\\Ai\\Events\\PromptingAgent', [$startNone]);
        $this->app['events']->dispatch('Laravel\\Ai\\Events\\AgentPrompted', [$endNone]);

        $noneSpan = Span::query()->where('span_id', $invocationNone)->first();
        $this->assertNotNull($noneSpan);
        $this->assertSame('[hidden]', $noneSpan->input_text);
        $this->assertSame('[hidden]', $noneSpan->output_text);

        config()->set('ai-trace.record_content_mode', 'hash');

        $invocationHash = '00000000-0000-7000-8000-000000000016';
        $startHash = new \stdClass;
        $startHash->invocationId = $invocationHash;
        $startHash->prompt = new \stdClass;
        $startHash->prompt->prompt = 'hello@example.com';
        $endHash = new \stdClass;
        $endHash->invocationId = $invocationHash;
        $endHash->response = new \stdClass;
        $endHash->response->text = 'phone +12025551234';

        $this->app['events']->dispatch('Laravel\\Ai\\Events\\PromptingAgent', [$startHash]);
        $this->app['events']->dispatch('Laravel\\Ai\\Events\\AgentPrompted', [$endHash]);

        $hashSpan = Span::query()->where('span_id', $invocationHash)->first();
        $this->assertNotNull($hashSpan);
        $this->assertSame(40, strlen((string) $hashSpan->input_text));
        $this->assertSame(40, strlen((string) $hashSpan->output_text));
    }
}
