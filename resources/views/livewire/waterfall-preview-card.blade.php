<x-ai-trace::card :cols="$this->cols" wire:poll.10s="">
    <x-ai-trace::card-header
        name="Waterfall Preview"
        details="Latest trace span sequence"
    />

    <x-ai-trace::scroll>
        @if ($items->isEmpty())
            <p class="flex h-full items-center justify-center p-4 text-sm text-gray-400 dark:text-gray-600">No spans to preview yet.</p>
        @else
            @foreach ($items as $item)
                <p class="mt-2 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $item['name'] ?: $item['trace_id'] }} ({{ $item['span_count'] }} spans)</p>
                <x-ai-trace::table>
                    <x-ai-trace::thead>
                        <tr>
                            <x-ai-trace::th>Span</x-ai-trace::th>
                            <x-ai-trace::th>Type</x-ai-trace::th>
                            <x-ai-trace::th>Duration</x-ai-trace::th>
                        </tr>
                    </x-ai-trace::thead>
                    <tbody>
                        @foreach ($item['spans'] as $span)
                            <tr class="h-2 first:h-0"></tr>
                            <tr>
                                <x-ai-trace::td>{{ $span->name ?: $span->span_id }}</x-ai-trace::td>
                                <x-ai-trace::td>{{ $span->span_type }}</x-ai-trace::td>
                                <x-ai-trace::td numeric>{{ number_format((int) $span->duration_ms) }} ms</x-ai-trace::td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ai-trace::table>
            @endforeach
        @endif
    </x-ai-trace::scroll>
</x-ai-trace::card>
