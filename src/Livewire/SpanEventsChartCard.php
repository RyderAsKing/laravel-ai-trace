<?php

namespace RyderAsKing\LaravelAiTrace\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Livewire;
use RyderAsKing\LaravelAiTrace\Services\TraceQueryService;

class SpanEventsChartCard extends Component
{
    public int $minutes = 30;

    public int|string $cols = 3;

    public function mount(): void
    {
        $this->minutes = max(1, (int) request()->integer('period', $this->minutes));
    }

    #[On('ai-trace-period-changed')]
    public function updatePeriod(int $minutes): void
    {
        $this->minutes = max(1, $minutes);
    }

    public function render(TraceQueryService $queryService)
    {
        $series = $queryService->spanEventTypeSeries($this->minutes);

        if (Livewire::isLivewireRequest()) {
            $this->dispatch('ai-trace-span-events-chart-update', series: $series);
        }

        return view('ai-trace::livewire.span-events-chart-card', [
            'series' => $series,
            'periodLabel' => $queryService->periodLabel($this->minutes),
        ]);
    }
}
