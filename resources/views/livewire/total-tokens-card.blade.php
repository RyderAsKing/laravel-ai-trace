<x-ai-trace::card :cols="$this->cols" wire:poll.10s="">
    <x-ai-trace::card-header
        name="Total Tokens"
        :details="'Past '.$metrics['period_label']"
    />

    <p class="text-3xl font-semibold tabular-nums text-gray-800 sm:text-4xl dark:text-gray-100">{{ number_format($metrics['total']) }}</p>
    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">tokens processed (input + output)</p>

    <div class="mt-3 grid grid-cols-2 gap-3 text-xs font-medium text-gray-600 dark:text-gray-400">
        <div class="rounded-md bg-gray-50 px-2 py-1 ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-gray-100/10">
            <span class="inline-block h-2 w-2 rounded-sm bg-cyan-500"></span>
            <span class="ml-1">Input</span>
            <span class="ml-2 tabular-nums text-gray-800 dark:text-gray-100">{{ number_format($metrics['input_total']) }}</span>
        </div>
        <div class="rounded-md bg-gray-50 px-2 py-1 ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-gray-100/10">
            <span class="inline-block h-2 w-2 rounded-sm bg-emerald-500"></span>
            <span class="ml-1">Output</span>
            <span class="ml-2 tabular-nums text-gray-800 dark:text-gray-100">{{ number_format($metrics['output_total']) }}</span>
        </div>
    </div>

    <x-ai-trace::multi-line-chart
        event-name="ai-trace-token-usage-chart-update"
        :series="$series"
        :palette="['#06b6d4', '#10b981']"
        chart-type="bar"
        :stacked="true"
        height-class="h-20"
    />
</x-ai-trace::card>
