<?php

namespace RyderAsKing\LaravelAiTrace\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;
use RyderAsKing\LaravelAiTrace\Services\TraceQueryService;

class LatencyCard extends Component
{
    public int $minutes = 30;

    public int|string $cols = 6;

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
        return view('ai-trace::livewire.latency-card', [
            'metrics' => $queryService->latency($this->minutes),
        ]);
    }
}
