<x-ai-trace::layout>
    <x-slot:controls>
        <livewire:ai-trace.period-selector />
        <x-ai-trace::theme-switcher />
    </x-slot:controls>

    <livewire:ai-trace.trace-volume-card />
    <livewire:ai-trace.span-events-chart-card />
    <livewire:ai-trace.latency-card />
    <livewire:ai-trace.trace-explorer-card />
    <livewire:ai-trace.waterfall-preview-card />
</x-ai-trace::layout>
