<?php

namespace RyderAsKing\LaravelAiTrace\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Livewire;
use RyderAsKing\LaravelAiTrace\Services\TraceQueryService;

class TraceVolumeCard extends Component
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
        $series = $queryService->traceStatusSeries($this->minutes);

        if (Livewire::isLivewireRequest()) {
            $this->dispatch('ai-trace-trace-status-chart-update', series: $series);
        }

        return view('ai-trace::livewire.trace-volume-card', [
            'metrics' => $queryService->traceVolume($this->minutes),
            'series' => $series,
        ]);
    }
}
