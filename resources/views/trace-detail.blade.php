<x-ai-trace::layout>
    <x-ai-trace::card cols="12">
        <x-ai-trace::card-header
            :name="'Trace: '.($detail['trace']->name ?: $detail['trace']->trace_id)"
            :details="'Status: '.$detail['trace']->status.' | Content mode: '.$detail['content_mode']"
        />

        <p class="text-xs text-gray-500 dark:text-gray-400">
            Started: {{ optional($detail['trace']->started_at)->toDateTimeString() }}
            @if ($detail['trace']->duration_ms !== null)
                | Duration: {{ number_format((int) $detail['trace']->duration_ms) }} ms
            @endif
            | Trace ID: {{ $detail['trace']->trace_id }}
        </p>
    </x-ai-trace::card>

    <x-ai-trace::card cols="7">
        <x-ai-trace::card-header name="Span Waterfall" details="Hierarchical spans with relative duration bars" />

        <x-ai-trace::scroll class="basis-80">
            @if ($detail['spans']->isEmpty())
                <p class="flex h-full items-center justify-center p-4 text-sm text-gray-400 dark:text-gray-600">No spans recorded for this trace.</p>
            @else
                <x-ai-trace::table>
                    <x-ai-trace::thead>
                        <tr>
                            <x-ai-trace::th>Span</x-ai-trace::th>
                            <x-ai-trace::th>Type</x-ai-trace::th>
                            <x-ai-trace::th>Status</x-ai-trace::th>
                            <x-ai-trace::th>Waterfall</x-ai-trace::th>
                        </tr>
                    </x-ai-trace::thead>
                    <tbody>
                        @foreach ($detail['spans'] as $span)
                            <tr class="h-2 first:h-0"></tr>
                            <tr>
                                <x-ai-trace::td>
                                    <div>
                                        {{ str_repeat('· ', (int) $span['depth']) }}{{ $span['name'] }}
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $span['provider'] ?: '-' }} / {{ $span['model'] ?: '-' }}</div>
                                    </div>
                                </x-ai-trace::td>
                                <x-ai-trace::td>{{ $span['span_type'] }}</x-ai-trace::td>
                                <x-ai-trace::td>{{ $span['status'] }}</x-ai-trace::td>
                                <x-ai-trace::td>
                                    <div class="flex items-center gap-2">
                                        <span class="relative inline-block h-2 w-24 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                            <span
                                                class="absolute inset-y-0 left-0 rounded-full"
                                                style="width: {{ max(0, min(100, (float) $span['bar_percent'])) }}%; background-color: #475569;"
                                            ></span>
                                        </span>
                                        <span class="whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">{{ number_format((int) $span['duration_ms']) }} ms ({{ number_format((float) $span['bar_percent'], 1) }}%)</span>
                                    </div>
                                </x-ai-trace::td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ai-trace::table>
            @endif
        </x-ai-trace::scroll>
    </x-ai-trace::card>

    <x-ai-trace::card cols="5">
        <x-ai-trace::card-header name="Span Content" :details="'Privacy-aware previews based on '.$detail['content_mode'].' mode'" />

        <x-ai-trace::scroll class="basis-80">
            @if ($detail['spans']->isEmpty())
                <p class="flex h-full items-center justify-center p-4 text-sm text-gray-400 dark:text-gray-600">No span content available.</p>
            @else
                @foreach ($detail['spans'] as $span)
                    <div class="rounded-md bg-gray-50 p-3 dark:bg-gray-800/50">
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $span['name'] }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Input: {{ $span['input_preview'] ?: '-' }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Output: {{ $span['output_preview'] ?: '-' }}</p>
                    </div>
                @endforeach
            @endif
        </x-ai-trace::scroll>
    </x-ai-trace::card>

    <x-ai-trace::card cols="12">
        <x-ai-trace::card-header name="Event Timeline" details="Chronological event stream" />

        <x-ai-trace::scroll class="basis-80">
            @if ($detail['events']->isEmpty())
                <p class="flex h-full items-center justify-center p-4 text-sm text-gray-400 dark:text-gray-600">No events recorded for this trace.</p>
            @else
                <x-ai-trace::table>
                    <x-ai-trace::thead>
                        <tr>
                            <x-ai-trace::th>Time</x-ai-trace::th>
                            <x-ai-trace::th>Span</x-ai-trace::th>
                            <x-ai-trace::th>Event</x-ai-trace::th>
                            <x-ai-trace::th>Payload</x-ai-trace::th>
                        </tr>
                    </x-ai-trace::thead>
                    <tbody>
                        @foreach ($detail['events'] as $event)
                            <tr class="h-2 first:h-0"></tr>
                            <tr>
                                <x-ai-trace::td>{{ optional($event['recorded_at'])->toDateTimeString() }}</x-ai-trace::td>
                                <x-ai-trace::td>{{ $event['span_name'] }}</x-ai-trace::td>
                                <x-ai-trace::td>{{ $event['event_type'] }}</x-ai-trace::td>
                                <x-ai-trace::td>{{ is_array($event['payload']) ? json_encode($event['payload']) : ($event['payload'] ?? '-') }}</x-ai-trace::td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ai-trace::table>
            @endif
        </x-ai-trace::scroll>
    </x-ai-trace::card>
</x-ai-trace::layout>
