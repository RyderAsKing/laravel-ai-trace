<?php

namespace RyderAsKing\LaravelAiTrace\Tests;

use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use RyderAsKing\LaravelAiTrace\Livewire\TraceExplorerCard;
use RyderAsKing\LaravelAiTrace\Livewire\TraceVolumeCard;
use RyderAsKing\LaravelAiTrace\Models\Span;
use RyderAsKing\LaravelAiTrace\Models\Trace;

class DashboardCardsTest extends TestCase
{
    public function test_dashboard_route_renders_livewire_cards(): void
    {
        Gate::define('viewAiTrace', fn ($user = null) => true);

        $response = $this->get(route('ai-trace.dashboard'));

        $response
            ->assertOk()
            ->assertSeeText('Trace Volume')
            ->assertSeeText('Total Tokens')
            ->assertSeeText('Span Events')
            ->assertSeeText('Trace Explorer');
    }

    public function test_trace_volume_and_trace_explorer_cards_render_data(): void
    {
        $trace = Trace::query()->create([
            'trace_id' => (string) fake()->uuid(),
            'name' => 'test trace card row',
            'status' => 'ok',
            'duration_ms' => 88,
            'started_at' => now()->subMinute(),
            'total_tokens' => 42,
        ]);

        Span::query()->create([
            'trace_id' => $trace->id,
            'span_id' => (string) fake()->uuid(),
            'span_type' => 'llm',
            'name' => 'llm.generate',
            'provider' => 'openrouter',
            'model_normalized' => 'gpt-4o-mini',
            'status' => 'ok',
            'started_at' => now()->subMinute(),
        ]);

        Livewire::test(TraceVolumeCard::class)
            ->assertSee('Trace Volume')
            ->assertSee('1');

        Livewire::test(TraceExplorerCard::class)
            ->assertSee('Trace Explorer')
            ->assertSee('test trace card row');
    }
}
