<?php

namespace RyderAsKing\LaravelAiTrace\Tests;

use RyderAsKing\LaravelAiTrace\Models\Span;
use RyderAsKing\LaravelAiTrace\Models\SpanEvent;
use RyderAsKing\LaravelAiTrace\Models\Trace;
use RyderAsKing\LaravelAiTrace\Services\TraceQueryService;

class TraceQueryServiceTest extends TestCase
{
    public function test_it_calculates_volume_error_rate_and_latency_metrics(): void
    {
        $queryService = $this->app->make(TraceQueryService::class);

        $okTrace = Trace::query()->create([
            'trace_id' => (string) fake()->uuid(),
            'name' => 'ok trace',
            'status' => 'ok',
            'duration_ms' => 120,
            'started_at' => now()->subMinutes(10),
            'ended_at' => now()->subMinutes(10)->addMilliseconds(120),
            'total_tokens' => 100,
        ]);

        $errorTrace = Trace::query()->create([
            'trace_id' => (string) fake()->uuid(),
            'name' => 'error trace',
            'status' => 'error',
            'duration_ms' => 320,
            'started_at' => now()->subMinutes(5),
            'ended_at' => now()->subMinutes(5)->addMilliseconds(320),
            'total_tokens' => 220,
        ]);

        Trace::query()->create([
            'trace_id' => (string) fake()->uuid(),
            'name' => 'outside window',
            'status' => 'error',
            'duration_ms' => 999,
            'started_at' => now()->subHours(3),
            'ended_at' => now()->subHours(3)->addMilliseconds(999),
        ]);

        Span::query()->create([
            'trace_id' => $okTrace->id,
            'span_id' => (string) fake()->uuid(),
            'name' => 'agent.prompt',
            'span_type' => 'agent',
            'status' => 'ok',
            'duration_ms' => 120,
            'started_at' => now()->subMinutes(10),
        ]);

        Span::query()->create([
            'trace_id' => $errorTrace->id,
            'span_id' => (string) fake()->uuid(),
            'name' => 'llm.generate',
            'span_type' => 'llm',
            'status' => 'error',
            'duration_ms' => 320,
            'started_at' => now()->subMinutes(5),
        ]);

        $volume = $queryService->traceVolume();
        $errorRate = $queryService->errorRate();
        $latency = $queryService->latency();
        $tokens = $queryService->totalTokens();
        $tokenSeries = $queryService->tokenUsageSeries(30, 6);
        $waterfall = $queryService->waterfallPreview();

        $this->assertSame(2, $volume['total']);
        $this->assertSame(2, $errorRate['total']);
        $this->assertSame(1, $errorRate['errors']);
        $this->assertSame(50.0, $errorRate['rate']);
        $this->assertSame(2, $latency['count']);
        $this->assertSame(120, $latency['p50']);
        $this->assertSame(320, $latency['p95']);
        $this->assertSame(320, $latency['max']);
        $this->assertSame(320, $tokens['total']);
        $this->assertSame(6, count($tokenSeries['labels']));
        $this->assertCount(2, $tokenSeries['datasets']);
        $this->assertSame('input', $tokenSeries['datasets'][0]['key']);
        $this->assertSame('output', $tokenSeries['datasets'][1]['key']);
        $this->assertSame(1, $waterfall->count());
        $this->assertSame(1, $waterfall->first()['span_count']);
    }

    public function test_it_filters_traces_and_builds_privacy_aware_trace_detail(): void
    {
        $queryService = $this->app->make(TraceQueryService::class);

        $trace = Trace::query()->create([
            'trace_id' => 'trace-drilldown-1',
            'name' => 'trace explorer row',
            'status' => 'ok',
            'duration_ms' => 200,
            'started_at' => now()->subMinutes(5),
            'total_tokens' => 33,
        ]);

        $root = Span::query()->create([
            'trace_id' => $trace->id,
            'span_id' => 'root-span',
            'span_type' => 'agent',
            'name' => 'agent.prompt',
            'provider' => 'openrouter',
            'model_normalized' => 'gpt-4o-mini',
            'status' => 'ok',
            'duration_ms' => 150,
            'started_at' => now()->subMinutes(5),
            'input_text' => 'email me at john@example.com',
            'output_text' => 'call +12025551234',
            'meta' => [
                'usage' => [
                    'cache_read_input_tokens' => 120,
                    'cache_write_input_tokens' => 7,
                ],
            ],
        ]);

        Span::query()->create([
            'trace_id' => $trace->id,
            'span_id' => 'child-span',
            'parent_span_id' => 'root-span',
            'span_type' => 'llm',
            'name' => 'llm.generate',
            'provider' => 'openrouter',
            'model_normalized' => 'gpt-4o-mini',
            'status' => 'ok',
            'duration_ms' => 50,
            'started_at' => now()->subMinutes(4),
        ]);

        SpanEvent::query()->create([
            'trace_id' => $trace->id,
            'span_id' => $root->id,
            'event_type' => 'annotation',
            'payload' => ['message' => 'token sk_live_1234567890'],
            'recorded_at' => now()->subMinutes(4),
        ]);

        $filtered = $queryService->filteredTraces([
            'status' => 'ok',
            'provider' => 'openrouter',
            'model' => 'gpt-4o-mini',
            'minutes' => 60,
        ]);

        config()->set('ai-trace.record_content_mode', 'redacted');
        $detail = $queryService->traceDetail('trace-drilldown-1');

        $this->assertCount(1, $filtered);
        $this->assertSame('trace-drilldown-1', $filtered->first()['trace_id']);
        $this->assertSame(120, $filtered->first()['cache_read_input_tokens']);
        $this->assertSame(7, $filtered->first()['cache_write_input_tokens']);
        $this->assertCount(2, $detail['spans']);
        $this->assertSame(0, $detail['spans']->first()['depth']);
        $this->assertSame(1, $detail['spans']->last()['depth']);
        $this->assertSame(120, $detail['trace_cache_read_input_tokens']);
        $this->assertSame(7, $detail['trace_cache_write_input_tokens']);
        $this->assertSame(120, $detail['spans']->first()['cache_read_input_tokens']);
        $this->assertSame(7, $detail['spans']->first()['cache_write_input_tokens']);
        $this->assertStringContainsString('[redacted-email]', (string) $detail['spans']->first()['input_preview']);
        $this->assertStringContainsString('[redacted-phone]', (string) $detail['spans']->first()['output_preview']);
        $this->assertStringContainsString('[redacted-token]', (string) ($detail['events']->first()['payload']['message'] ?? ''));
    }
}
