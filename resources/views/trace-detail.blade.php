<x-ai-trace::layout>
    <x-ai-trace::card cols="12">
        <x-ai-trace::card-header
            :name="'Trace: '.($detail['trace']->name ?: $detail['trace']->trace_id)"
            :details="'Status: '.$detail['trace']->status.' | Content mode: '.$detail['content_mode']"
        />

        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
            <span class="rounded-full bg-sky-100 px-2 py-1 font-medium text-sky-700 dark:bg-sky-500/20 dark:text-sky-300">Trace ID: {{ $detail['trace']->trace_id }}</span>
            <span class="rounded-full bg-gray-100 px-2 py-1 font-medium text-gray-700 dark:bg-gray-700/50 dark:text-gray-300">Started: {{ optional($detail['trace']->started_at)->toDateTimeString() }}</span>
            <span class="rounded-full bg-gray-100 px-2 py-1 font-medium text-gray-700 dark:bg-gray-700/50 dark:text-gray-300">Duration: {{ number_format((int) ($detail['trace']->duration_ms ?? 0)) }} ms</span>
            <span class="rounded-full bg-gray-100 px-2 py-1 font-medium text-gray-700 dark:bg-gray-700/50 dark:text-gray-300">Tokens: {{ number_format((int) ($detail['trace']->total_tokens ?? 0)) }}</span>
        </div>
    </x-ai-trace::card>

    <x-ai-trace::card cols="12">
        <x-ai-trace::card-header name="Trace Inspector" details="Select a span to inspect input, output, events, and attributes" />

        @if ($detail['spans']->isEmpty())
            <p class="flex h-72 items-center justify-center p-4 text-sm text-gray-400 dark:text-gray-600">No spans recorded for this trace.</p>
        @else
            <div
                class="mt-2 grid gap-4 default:grid-cols-12"
                x-data="{ selectedSpanId: @js($detail['spans']->first()['span_id']), activeTab: 'input' }"
            >
                <div class="default:col-span-4">
                    <div class="h-[34rem] overflow-y-auto rounded-lg border border-gray-200 bg-gray-50/70 p-2 dark:border-gray-700 dark:bg-gray-800/40">
                        @foreach ($detail['spans'] as $span)
                            <button
                                type="button"
                                @click="selectedSpanId = @js($span['span_id'])"
                                class="mb-2 block w-full rounded-md border border-transparent px-3 py-2 text-left transition hover:border-gray-300 hover:bg-white dark:hover:border-gray-600 dark:hover:bg-gray-800"
                                :class="selectedSpanId === @js($span['span_id']) ? 'border-sky-300 bg-white dark:border-sky-500/60 dark:bg-gray-800' : ''"
                            >
                                <p class="text-sm font-medium text-gray-800 dark:text-gray-100">{{ str_repeat('· ', (int) $span['depth']) }}{{ $span['name'] }}</p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $span['provider'] ?: '-' }} / {{ $span['model'] ?: '-' }}</p>
                                <div class="mt-2 flex items-center gap-2">
                                    <span class="relative inline-block h-1.5 w-24 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                        <span
                                            class="absolute inset-y-0 left-0 rounded-full bg-sky-500"
                                            style="width: {{ max(0, min(100, (float) $span['bar_percent'])) }}%;"
                                        ></span>
                                    </span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ number_format((int) $span['duration_ms']) }} ms</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ number_format((int) $span['total_tokens']) }} tok</span>
                                </div>
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="default:col-span-8">
                    <div class="mb-3 flex flex-wrap gap-2 border-b border-gray-200 pb-2 dark:border-gray-700">
                        <button type="button" @click="activeTab = 'input'" class="rounded-md px-2 py-1 text-sm" :class="activeTab === 'input' ? 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-300' : 'text-gray-600 dark:text-gray-300'">Input</button>
                        <button type="button" @click="activeTab = 'output'" class="rounded-md px-2 py-1 text-sm" :class="activeTab === 'output' ? 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-300' : 'text-gray-600 dark:text-gray-300'">Output</button>
                        <button type="button" @click="activeTab = 'events'" class="rounded-md px-2 py-1 text-sm" :class="activeTab === 'events' ? 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-300' : 'text-gray-600 dark:text-gray-300'">Events</button>
                        <button type="button" @click="activeTab = 'attributes'" class="rounded-md px-2 py-1 text-sm" :class="activeTab === 'attributes' ? 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-300' : 'text-gray-600 dark:text-gray-300'">Attributes</button>
                        <button type="button" @click="activeTab = 'raw'" class="rounded-md px-2 py-1 text-sm" :class="activeTab === 'raw' ? 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-300' : 'text-gray-600 dark:text-gray-300'">Raw JSON</button>
                    </div>

                    @foreach ($detail['spans'] as $span)
                        <div x-show="selectedSpanId === @js($span['span_id'])" x-cloak>
                            <div class="mb-3 flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                <span class="rounded-md bg-gray-100 px-2 py-1 dark:bg-gray-800">{{ $span['span_type'] }}</span>
                                <span class="rounded-md bg-gray-100 px-2 py-1 dark:bg-gray-800">{{ $span['status'] ?: 'unknown' }}</span>
                                <span class="rounded-md bg-gray-100 px-2 py-1 dark:bg-gray-800">{{ number_format((int) $span['input_tokens']) }} in</span>
                                <span class="rounded-md bg-gray-100 px-2 py-1 dark:bg-gray-800">{{ number_format((int) $span['output_tokens']) }} out</span>
                            </div>

                            <div x-show="activeTab === 'input'" x-cloak class="rounded-md border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800/40 dark:text-gray-300">{{ $span['input_preview'] ?: '-' }}</div>
                            <div x-show="activeTab === 'output'" x-cloak class="rounded-md border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800/40 dark:text-gray-300">{{ $span['output_preview'] ?: '-' }}</div>

                            <div x-show="activeTab === 'events'" x-cloak>
                                <div class="h-[28rem] overflow-y-auto rounded-md border border-gray-200 bg-gray-50 p-2 dark:border-gray-700 dark:bg-gray-800/40">
                                    @php
                                        $spanEvents = $detail['events']->filter(fn ($event) => (int) $event['span_id'] === (int) $span['id']);
                                    @endphp
                                    @if ($spanEvents->isEmpty())
                                        <p class="p-3 text-sm text-gray-500 dark:text-gray-400">No events for this span.</p>
                                    @else
                                        @foreach ($spanEvents as $event)
                                            <div class="mb-2 rounded-md border border-gray-200 bg-white p-2 text-xs dark:border-gray-700 dark:bg-gray-900">
                                                <p class="font-medium text-gray-700 dark:text-gray-300">{{ $event['event_type'] }}</p>
                                                <p class="text-gray-500 dark:text-gray-400">{{ optional($event['recorded_at'])->toDateTimeString() }}</p>
                                                <pre class="mt-1 overflow-x-auto whitespace-pre-wrap text-gray-600 dark:text-gray-300">{{ is_array($event['payload']) ? json_encode($event['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : ($event['payload'] ?? '-') }}</pre>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                            </div>

                            <div x-show="activeTab === 'attributes'" x-cloak class="rounded-md border border-gray-200 bg-gray-50 p-3 text-xs text-gray-700 dark:border-gray-700 dark:bg-gray-800/40 dark:text-gray-300">
                                <pre class="overflow-x-auto whitespace-pre-wrap">{{ json_encode($span['meta'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                            </div>

                            <div x-show="activeTab === 'raw'" x-cloak class="rounded-md border border-gray-200 bg-gray-50 p-3 text-xs text-gray-700 dark:border-gray-700 dark:bg-gray-800/40 dark:text-gray-300">
                                <pre class="overflow-x-auto whitespace-pre-wrap">{{ json_encode($span, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </x-ai-trace::card>
</x-ai-trace::layout>
