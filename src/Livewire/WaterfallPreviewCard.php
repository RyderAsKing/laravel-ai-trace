<?php

namespace RyderAsKing\LaravelAiTrace\Livewire;

use Livewire\Component;
use RyderAsKing\LaravelAiTrace\Services\TraceQueryService;

class WaterfallPreviewCard extends Component
{
    public int|string $cols = 4;

    public int $traceLimit = 1;

    public int $spanLimit = 25;

    public function render(TraceQueryService $queryService)
    {
        return view('ai-trace::livewire.waterfall-preview-card', [
            'items' => $queryService->waterfallPreview($this->traceLimit, $this->spanLimit),
        ]);
    }
}
