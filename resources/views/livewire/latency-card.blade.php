<x-ai-trace::card :cols="$this->cols" wire:poll.10s="">
    <x-ai-trace::card-header
        name="Latency"
        :details="'Past '.$metrics['period_label']"
    />

    <x-ai-trace::table>
        <tbody>
            <tr class="h-2 first:h-0"></tr>
            <tr>
                <x-ai-trace::td>P50</x-ai-trace::td>
                <x-ai-trace::td numeric>{{ number_format($metrics['p50']) }} ms</x-ai-trace::td>
            </tr>
            <tr class="h-2"></tr>
            <tr>
                <x-ai-trace::td>P95</x-ai-trace::td>
                <x-ai-trace::td numeric>{{ number_format($metrics['p95']) }} ms</x-ai-trace::td>
            </tr>
            <tr class="h-2"></tr>
            <tr>
                <x-ai-trace::td>Average</x-ai-trace::td>
                <x-ai-trace::td numeric>{{ number_format($metrics['avg'], 2) }} ms</x-ai-trace::td>
            </tr>
            <tr class="h-2"></tr>
            <tr>
                <x-ai-trace::td>Max</x-ai-trace::td>
                <x-ai-trace::td numeric>{{ number_format($metrics['max']) }} ms</x-ai-trace::td>
            </tr>
        </tbody>
    </x-ai-trace::table>
</x-ai-trace::card>
