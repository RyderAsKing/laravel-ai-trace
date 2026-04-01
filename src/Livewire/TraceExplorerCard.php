<?php

namespace RyderAsKing\LaravelAiTrace\Livewire;

use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use RyderAsKing\LaravelAiTrace\Services\TraceQueryService;

class TraceExplorerCard extends Component
{
    public int|string $cols = 12;

    #[Url(as: 'trace_status', except: 'all')]
    public string $status = 'all';

    #[Url(as: 'trace_provider', except: 'all')]
    public string $provider = 'all';

    #[Url(as: 'trace_model', except: 'all')]
    public string $model = 'all';

    public int $minutes = 30;

    public int $limit = 20;

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
        $filters = [
            'status' => $this->status,
            'provider' => $this->provider,
            'model' => $this->model,
            'minutes' => $this->minutes,
        ];

        return view('ai-trace::livewire.trace-explorer-card', [
            'filters' => $queryService->traceFilters($this->minutes),
            'traces' => $queryService->filteredTraces($filters, $this->limit),
            'periodLabel' => $queryService->periodLabel($this->minutes),
        ]);
    }
}
