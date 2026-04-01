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

    #[Url(as: 'trace_error_only', except: false)]
    public bool $errorOnly = false;

    #[Url(as: 'trace_min_duration', except: 0)]
    public int $minDurationMs = 0;

    #[Url(as: 'trace_min_tokens', except: 0)]
    public int $minTokens = 0;

    #[Url(as: 'trace_sort_by', except: 'started_at')]
    public string $sortBy = 'started_at';

    #[Url(as: 'trace_sort_direction', except: 'desc')]
    public string $sortDirection = 'desc';

    public int $minutes = 30;

    public int $limit = 20;

    public function setSort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';

            return;
        }

        $this->sortBy = $column;
        $this->sortDirection = in_array($column, ['name', 'status'], true) ? 'asc' : 'desc';
    }

    public function clearQuickFilters(): void
    {
        $this->errorOnly = false;
        $this->minDurationMs = 0;
        $this->minTokens = 0;
    }

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
            'error_only' => $this->errorOnly,
            'min_duration_ms' => $this->minDurationMs,
            'min_tokens' => $this->minTokens,
            'sort_by' => $this->sortBy,
            'sort_direction' => $this->sortDirection,
            'minutes' => $this->minutes,
        ];

        return view('ai-trace::livewire.trace-explorer-card', [
            'filters' => $queryService->traceFilters($this->minutes),
            'traces' => $queryService->filteredTraces($filters, $this->limit),
            'periodLabel' => $queryService->periodLabel($this->minutes),
        ]);
    }
}
