<x-ai-trace::card :cols="$this->cols" wire:poll.10s="">
    <x-ai-trace::card-header
        name="Trace Volume"
        :details="'Past '.$metrics['period_label']"
    />

    <p class="text-3xl font-semibold tabular-nums text-gray-800 sm:text-4xl dark:text-gray-100">{{ number_format($metrics['total']) }}</p>
    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">traces captured</p>

    <div class="mt-3 flex flex-wrap gap-4 text-xs font-medium text-gray-600 dark:text-gray-400">
        @php
            $legendColorClasses = ['bg-gray-500/50', 'bg-purple-500/60', 'bg-purple-600', 'bg-yellow-500', 'bg-rose-600', 'bg-teal-500'];
            $chartPalette = ['rgba(107,114,128,0.5)', 'rgba(147,51,234,0.5)', '#9333ea', '#eab308', '#e11d48', '#14b8a6'];
        @endphp
        @foreach ($series['datasets'] as $index => $dataset)
            <div class="flex items-center gap-2">
                <div class="h-0.5 w-3 rounded-full {{ $legendColorClasses[$index % count($legendColorClasses)] }}"></div>
                {{ ucfirst($dataset['label']) }}
            </div>
        @endforeach
    </div>

    <x-ai-trace::multi-line-chart
        event-name="ai-trace-trace-status-chart-update"
        :series="$series"
        :palette="$chartPalette"
    />
</x-ai-trace::card>
