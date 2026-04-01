<x-ai-trace::card :cols="$this->cols" wire:poll.10s="">
    <x-ai-trace::card-header
        name="Error Rate"
        :details="'Past '.$metrics['period_label']"
    />

    <p class="text-3xl font-semibold tabular-nums text-gray-800 sm:text-4xl dark:text-gray-100">{{ number_format($metrics['rate'], 2) }}%</p>
    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ number_format($metrics['errors']) }} of {{ number_format($metrics['total']) }} traces</p>
</x-ai-trace::card>
