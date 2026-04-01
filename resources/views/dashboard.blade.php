<x-ai-trace::layout>
    <x-slot:controls>
        <livewire:ai-trace.period-selector />
        <x-ai-trace::theme-switcher />
    </x-slot:controls>

    <livewire:ai-trace.trace-volume-card />
    <livewire:ai-trace.span-events-chart-card />
    <livewire:ai-trace.total-tokens-card />
    <livewire:ai-trace.trace-explorer-card />
</x-ai-trace::layout>
