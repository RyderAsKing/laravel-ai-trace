<?php

namespace RyderAsKing\LaravelAiTrace\Livewire;

use Livewire\Attributes\Url;
use Livewire\Component;

class PeriodSelector extends Component
{
    #[Url(as: 'period', except: 30)]
    public int $minutes = 30;

    public array $options = [
        30 => '30m',
        60 => '1h',
        360 => '6h',
        1440 => '24h',
        10080 => '7d',
    ];

    public function mount(): void
    {
        $this->minutes = $this->normalizePeriod($this->minutes);
    }

    public function updatedMinutes(): void
    {
        $this->minutes = $this->normalizePeriod($this->minutes);
        $this->dispatch('ai-trace-period-changed', minutes: $this->minutes);
    }

    public function render()
    {
        return view('ai-trace::livewire.period-selector');
    }

    protected function normalizePeriod(int $minutes): int
    {
        $allowed = array_keys($this->options);

        if (in_array($minutes, $allowed, true)) {
            return $minutes;
        }

        return 30;
    }
}
