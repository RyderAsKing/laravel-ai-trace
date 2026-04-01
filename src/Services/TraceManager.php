<?php

namespace RyderAsKing\LaravelAiTrace\Services;

use RyderAsKing\LaravelAiTrace\Models\Span;
use RyderAsKing\LaravelAiTrace\Models\SpanEvent;
use RyderAsKing\LaravelAiTrace\Models\Trace;
use Illuminate\Support\Str;
use DateTimeInterface;

class TraceManager
{
    public function __construct(protected array $config = [])
    {
    }

    public function startTrace(array $attributes = []): Trace
    {
        return Trace::query()->create(array_merge([
            'trace_id' => (string) Str::uuid(),
            'status' => 'ok',
            'started_at' => now(),
            'meta' => [],
        ], $attributes));
    }

    public function endTrace(Trace $trace, array $attributes = []): Trace
    {
        $endedAt = $attributes['ended_at'] ?? now();

        $trace->fill(array_merge([
            'ended_at' => $endedAt,
            'duration_ms' => (int) max(0, ($endedAt->getTimestampMs() - $trace->started_at?->getTimestampMs())),
            'status' => $attributes['status'] ?? 'ok',
        ], $attributes));

        $trace->save();

        return $trace->refresh();
    }

    public function startSpan(Trace $trace, array $attributes = []): Span
    {
        return Span::query()->create(array_merge([
            'trace_id' => $trace->id,
            'span_id' => (string) Str::uuid(),
            'source' => 'ai_sdk',
            'span_type' => 'llm',
            'status' => 'ok',
            'started_at' => now(),
            'meta' => [],
        ], $attributes));
    }

    public function endSpan(Span $span, array $attributes = []): Span
    {
        $endedAt = $attributes['ended_at'] ?? now();

        $span->fill(array_merge([
            'ended_at' => $endedAt,
            'duration_ms' => (int) max(0, ($endedAt->getTimestampMs() - $span->started_at?->getTimestampMs())),
            'status' => $attributes['status'] ?? 'ok',
        ], $attributes));

        $span->save();

        return $span->refresh();
    }

    public function recordEvent(
        Trace $trace,
        Span $span,
        string $eventType,
        array $payload = [],
        ?DateTimeInterface $recordedAt = null,
    ): SpanEvent
    {
        return SpanEvent::query()->create([
            'trace_id' => $trace->id,
            'span_id' => $span->id,
            'event_type' => $eventType,
            'payload' => $payload,
            'recorded_at' => $recordedAt ?? now(),
        ]);
    }
}
