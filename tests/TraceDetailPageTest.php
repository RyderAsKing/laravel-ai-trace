<?php

namespace RyderAsKing\LaravelAiTrace\Tests;

use Illuminate\Support\Facades\Gate;
use RyderAsKing\LaravelAiTrace\Models\Span;
use RyderAsKing\LaravelAiTrace\Models\SpanEvent;
use RyderAsKing\LaravelAiTrace\Models\Trace;

class TraceDetailPageTest extends TestCase
{
    public function test_trace_detail_route_renders_waterfall_and_timeline(): void
    {
        Gate::define('viewAiTrace', fn ($user = null) => true);
        config()->set('ai-trace.record_content_mode', 'none');

        $trace = Trace::query()->create([
            'trace_id' => 'trace-detail-page-1',
            'name' => 'detail test trace',
            'status' => 'ok',
            'started_at' => now()->subMinute(),
            'duration_ms' => 200,
        ]);

        $span = Span::query()->create([
            'trace_id' => $trace->id,
            'span_id' => 'span-detail-page-1',
            'span_type' => 'agent',
            'name' => 'agent.prompt',
            'status' => 'ok',
            'duration_ms' => 200,
            'input_text' => 'sensitive input',
            'output_text' => 'sensitive output',
            'started_at' => now()->subMinute(),
        ]);

        SpanEvent::query()->create([
            'trace_id' => $trace->id,
            'span_id' => $span->id,
            'event_type' => 'annotation',
            'payload' => ['note' => 'secret key_abcdefghi'],
            'recorded_at' => now()->subSeconds(20),
        ]);

        $this->get(route('ai-trace.dashboard.trace', ['traceId' => 'trace-detail-page-1']))
            ->assertOk()
            ->assertSee('Span Waterfall')
            ->assertSee('Event Timeline')
            ->assertSee('[hidden]');
    }
}
