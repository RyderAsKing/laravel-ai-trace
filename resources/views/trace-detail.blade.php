<x-ai-trace::layout>
    @php
        $traceInputTokens = (int) ($detail['trace']->total_input_tokens ?? 0);
        $traceOutputTokens = (int) ($detail['trace']->total_output_tokens ?? 0);
        $traceTotalTokens = (int) ($detail['trace']->total_tokens ?? ($traceInputTokens + $traceOutputTokens));
        $traceCacheReadTokens = (int) ($detail['trace_cache_read_input_tokens'] ?? 0);
        $traceCacheWriteTokens = (int) ($detail['trace_cache_write_input_tokens'] ?? 0);
        $hasTraceCachedUsage = $traceCacheReadTokens > 0 || $traceCacheWriteTokens > 0;
        $traceTokenBase = max($traceInputTokens + $traceOutputTokens, 0);
        $traceInputPercent = $traceTokenBase > 0 ? round(($traceInputTokens / $traceTokenBase) * 100, 2) : 0;
        $traceOutputPercent = $traceTokenBase > 0 ? round(($traceOutputTokens / $traceTokenBase) * 100, 2) : 0;
        $tokenDominant = $traceInputTokens === $traceOutputTokens
            ? 'Even split'
            : ($traceInputTokens > $traceOutputTokens ? 'Input heavy' : 'Output heavy');
    @endphp

    <div class="space-y-4 default:col-span-full default:lg:col-span-12" x-data="{ selectedSpanId: @js($detail['spans']->first()['span_id'] ?? null), activeTab: 'input', selectedNodeKey: @js(($detail['spans']->first()['span_id'] ?? 'none').':root'), selectedEventKey: null, leftLayout: 'waterfall' }">
    <x-ai-trace::card cols="12">
        <x-ai-trace::card-header
            :name="'Trace: '.($detail['trace']->name ?: $detail['trace']->trace_id)"
            :details="'Status: '.$detail['trace']->status.' | Content mode: '.$detail['content_mode']"
        />

        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
            <span class="rounded-full bg-sky-100 px-2 py-1 font-medium text-sky-700 dark:bg-sky-500/20 dark:text-sky-300">Trace ID: {{ $detail['trace']->trace_id }}</span>
            <span class="rounded-full bg-gray-100 px-2 py-1 font-medium text-gray-700 dark:bg-gray-700/50 dark:text-gray-300">Started: {{ optional($detail['trace']->started_at)->toDateTimeString() }}</span>
            <span class="rounded-full bg-gray-100 px-2 py-1 font-medium text-gray-700 dark:bg-gray-700/50 dark:text-gray-300">Duration: {{ number_format((int) ($detail['trace']->duration_ms ?? 0)) }} ms</span>
            <span class="rounded-full bg-gray-100 px-2 py-1 font-medium text-gray-700 dark:bg-gray-700/50 dark:text-gray-300">Tokens: {{ number_format($traceTotalTokens) }}</span>
            <span class="rounded-full bg-emerald-100 px-2 py-1 font-medium text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">In: {{ number_format($traceInputTokens) }}</span>
            <span class="rounded-full bg-indigo-100 px-2 py-1 font-medium text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300">Out: {{ number_format($traceOutputTokens) }}</span>
            @if ($hasTraceCachedUsage)
                <span class="rounded-full bg-gray-200 px-2 py-1 font-medium text-gray-700 dark:bg-gray-700/70 dark:text-gray-300">Cache read: {{ number_format($traceCacheReadTokens) }}</span>
                <span class="rounded-full bg-gray-200 px-2 py-1 font-medium text-gray-700 dark:bg-gray-700/70 dark:text-gray-300">Cache write: {{ number_format($traceCacheWriteTokens) }}</span>
            @endif
        </div>

        <div class="mt-3">
            <div class="mb-2 flex items-center justify-between gap-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Input vs Output Token Ratio</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $tokenDominant }}</p>
            </div>

            @if ($traceTokenBase > 0)
                <div class="group relative">
                    <div class="flex h-2.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700" aria-label="Trace input and output token usage">
                        <span class="h-full bg-emerald-500" style="width: {{ $traceInputPercent }}%"></span>
                        <span class="h-full bg-indigo-500" style="width: {{ $traceOutputPercent }}%"></span>
                    </div>

                    <div class="pointer-events-none absolute left-1/2 top-full z-10 mt-2 hidden -translate-x-1/2 rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-[11px] text-gray-700 shadow-sm group-hover:block dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                        Input {{ number_format($traceInputTokens) }} ({{ number_format($traceInputPercent, 1) }}%) • Output {{ number_format($traceOutputTokens) }} ({{ number_format($traceOutputPercent, 1) }}%)
                    </div>
                </div>
                @if ($hasTraceCachedUsage)
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Cached input tokens: read {{ number_format($traceCacheReadTokens) }}, write {{ number_format($traceCacheWriteTokens) }}.</p>
                @endif
            @else
                <div class="h-2.5 w-full rounded-full bg-gray-200 dark:bg-gray-700"></div>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">No token usage recorded for this trace yet.</p>
            @endif
        </div>
    </x-ai-trace::card>

    <x-ai-trace::card cols="12" class="bg-gradient-to-b from-white/90 to-slate-50/70 dark:from-gray-900/70 dark:to-gray-900/40">
        <x-ai-trace::card-header name="Trace Inspector" details="Select a span to inspect input, output, events, and attributes" />

        @if ($detail['spans']->isEmpty())
            <p class="flex h-72 items-center justify-center p-4 text-sm text-gray-400 dark:text-gray-600">No spans recorded for this trace.</p>
        @else
            @php
                $eventsBySpanId = $detail['events']->groupBy('span_id');
                $spansById = $detail['spans']->keyBy('id');
                $prettyPayload = static function (mixed $value): string {
                    if ($value === null || $value === '') {
                        return '-';
                    }

                    if (is_array($value) || is_object($value)) {
                        return (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    }

                    if (! is_string($value)) {
                        return (string) $value;
                    }

                    $decoded = json_decode($value, true);

                    if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {
                        return (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    }

                    return $value;
                };

                $waterfallItems = collect();

                foreach ($detail['spans'] as $span) {
                    $startedAt = $span['started_at'];
                    $startTimeMs = $startedAt ? $startedAt->getTimestampMs() : PHP_INT_MAX;
                    $spanType = strtolower((string) ($span['span_type'] ?? ''));
                    $isToolSpan = str_contains($spanType, 'tool');
                    $isAgentSpan = str_contains($spanType, 'agent');

                    $waterfallItems->push([
                        'key' => $span['span_id'].':wf:start',
                        'event_key' => null,
                        'span_id' => $span['span_id'],
                        'kind' => 'start',
                        'label' => $span['name'],
                        'kind_label' => $isToolSpan ? 'Tool started' : ($isAgentSpan ? 'Agent started' : 'Span started'),
                        'tab' => 'input',
                        'at' => $startedAt,
                        'sort_time' => $startTimeMs,
                        'sort_order' => 0,
                    ]);

                    if ($startedAt && (int) ($span['duration_ms'] ?? 0) > 0) {
                        $endedAt = $startedAt->copy()->addMilliseconds((int) $span['duration_ms']);

                        $waterfallItems->push([
                            'key' => $span['span_id'].':wf:end',
                            'event_key' => null,
                            'span_id' => $span['span_id'],
                            'kind' => 'end',
                            'label' => $span['name'],
                            'kind_label' => $isToolSpan ? 'Tool finished' : ($isAgentSpan ? 'Agent finished' : 'Span finished'),
                            'tab' => 'output',
                            'at' => $endedAt,
                            'sort_time' => $endedAt->getTimestampMs(),
                            'sort_order' => 3,
                        ]);
                    }
                }

                foreach ($detail['events'] as $event) {
                    $span = $spansById->get((int) $event['span_id']);

                    if (! $span) {
                        continue;
                    }

                    $eventType = strtolower((string) $event['event_type']);
                    $tab = 'events';

                    if (str_starts_with($eventType, 'sdk_')) {
                        $tab = 'events';
                    } elseif (str_contains($eventType, 'input') || str_contains($eventType, 'request')) {
                        $tab = 'input';
                    } elseif (str_contains($eventType, 'output') || str_contains($eventType, 'response') || str_contains($eventType, 'completion') || str_contains($eventType, 'stream')) {
                        $tab = 'output';
                    } elseif (str_contains($eventType, 'usage') || str_contains($eventType, 'attribute') || str_contains($eventType, 'meta')) {
                        $tab = 'attributes';
                    }

                    $recordedAt = $event['recorded_at'];

                    $waterfallItems->push([
                        'key' => $span['span_id'].':wf:event:'.$event['event_key'],
                        'event_key' => $event['event_key'],
                        'span_id' => $span['span_id'],
                        'kind' => 'event',
                        'label' => \Illuminate\Support\Str::headline((string) $event['event_type']),
                        'kind_label' => $span['name'],
                        'tab' => $tab,
                        'at' => $recordedAt,
                        'sort_time' => $recordedAt ? $recordedAt->getTimestampMs() : PHP_INT_MAX,
                        'sort_order' => 1,
                    ]);
                }

                $waterfallItems = $waterfallItems
                    ->sortBy([
                        ['sort_time', 'asc'],
                        ['sort_order', 'asc'],
                    ])
                    ->values();

                $stack = [];
                $waterfallItems = $waterfallItems->map(function (array $item) use (&$stack): array {
                    $depth = count($stack);

                    if ($item['kind'] === 'start') {
                        $depth = count($stack);
                        $stack[] = $item['span_id'];
                    } elseif ($item['kind'] === 'end') {
                        $depth = max(count($stack) - 1, 0);
                        $stackIndex = array_search($item['span_id'], $stack, true);

                        if ($stackIndex !== false) {
                            $depth = (int) $stackIndex;
                            array_splice($stack, $stackIndex, 1);
                        }
                    }

                    $item['depth'] = $depth;

                    return $item;
                })->values();
            @endphp
            <div
                class="mt-2 grid gap-4 default:grid-cols-12"
            >
                <div class="default:col-span-4">
                    <div class="mb-2 inline-flex rounded-lg border border-gray-200 bg-white/80 p-1 dark:border-gray-700 dark:bg-gray-800/50">
                        <button type="button" @click="leftLayout = 'waterfall'" class="rounded-md px-2.5 py-1 text-xs font-medium" :class="leftLayout === 'waterfall' ? 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-300' : 'text-gray-600 dark:text-gray-300'">Waterfall</button>
                        <button type="button" @click="leftLayout = 'spans'" class="rounded-md px-2.5 py-1 text-xs font-medium" :class="leftLayout === 'spans' ? 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-300' : 'text-gray-600 dark:text-gray-300'">Spans</button>
                    </div>

                    <div x-show="leftLayout === 'spans'" x-cloak class="h-[34rem] overflow-y-auto p-1 pr-2">
                        @foreach ($detail['spans'] as $span)
                            @php
                                $spanType = strtolower((string) $span['span_type']);
                                $isToolSpan = str_contains($spanType, 'tool');
                                $isAgentSpan = str_contains($spanType, 'agent');
                                $spanKindLabel = $isToolSpan ? 'Tool' : ($isAgentSpan ? 'Agent' : 'Span');
                                $spanKindClasses = $isToolSpan
                                    ? 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300'
                                    : ($isAgentSpan
                                        ? 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-300'
                                        : 'bg-gray-100 text-gray-700 dark:bg-gray-700/50 dark:text-gray-300');
                                $barClasses = $isToolSpan
                                    ? 'bg-amber-500'
                                    : ($isAgentSpan ? 'bg-sky-500' : 'bg-indigo-500');
                                $barPercent = (float) $span['bar_percent'];
                                $barPercentVisible = ((int) $span['duration_ms'] > 0 && $barPercent > 0 && $barPercent < 4)
                                    ? 4
                                    : $barPercent;
                            @endphp
                            <div class="mb-3 rounded-lg border border-slate-300 bg-white/80 px-3 py-2.5 shadow-sm transition last:mb-0 dark:border-gray-600 dark:bg-gray-800/40" :class="selectedSpanId === @js($span['span_id']) ? 'border-sky-400 hover:border-sky-500 dark:border-sky-500 dark:hover:border-sky-400' : 'hover:border-slate-400 dark:hover:border-gray-500'">
                                <button
                                    type="button"
                                    @click="selectedSpanId = @js($span['span_id']); activeTab = 'input'; selectedNodeKey = @js($span['span_id'].':root'); selectedEventKey = null"
                                    class="block w-full text-left"
                                >
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="text-xs font-medium text-gray-800 dark:text-gray-100">{{ str_repeat('· ', (int) $span['depth']) }}{{ $span['name'] }}</p>
                                        <span class="shrink-0 rounded px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide {{ $spanKindClasses }}">{{ $spanKindLabel }}</span>
                                    </div>
                                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">{{ $span['provider'] ?: '-' }} / {{ $span['model'] ?: '-' }}</p>
                                    <div class="mt-2 flex items-center gap-2">
                                        <span class="relative inline-block h-1.5 w-24 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                            <span
                                                class="absolute inset-y-0 left-0 rounded-full {{ $barClasses }}"
                                                style="width: {{ max(0, min(100, $barPercentVisible)) }}%;"
                                            ></span>
                                        </span>
                                        <span class="text-[11px] text-gray-500 dark:text-gray-400">{{ number_format((int) $span['duration_ms']) }} ms</span>
                                        <span class="text-[11px] text-gray-500 dark:text-gray-400">{{ number_format((int) $span['total_tokens']) }} tok</span>
                                    </div>
                                    @if (((int) ($span['cache_read_input_tokens'] ?? 0)) > 0 || ((int) ($span['cache_write_input_tokens'] ?? 0)) > 0)
                                        <div class="mt-1 text-[10px] text-gray-400 dark:text-gray-500">
                                            cache r {{ number_format((int) ($span['cache_read_input_tokens'] ?? 0)) }} · w {{ number_format((int) ($span['cache_write_input_tokens'] ?? 0)) }}
                                        </div>
                                    @endif
                                </button>

                                <div class="mt-2.5 space-y-1 border-l border-gray-300 pl-2 dark:border-gray-600">
                                    <button
                                        type="button"
                                        @click="selectedSpanId = @js($span['span_id']); activeTab = 'output'; selectedNodeKey = @js($span['span_id'].':sdk'); selectedEventKey = null"
                                        class="flex w-full items-center justify-between rounded px-2 py-1 text-left text-[11px] transition hover:bg-gray-100 dark:hover:bg-gray-700/60"
                                        :class="selectedNodeKey === @js($span['span_id'].':sdk') ? 'bg-sky-50 text-sky-800 dark:bg-sky-500/20 dark:text-sky-200' : 'text-gray-600 dark:text-gray-300'"
                                    >
                                        <span>AI SDK</span>
                                        <span class="truncate text-right">{{ $span['provider'] ?: '-' }} / {{ $span['model'] ?: '-' }}</span>
                                    </button>

                                    @foreach ($eventsBySpanId->get((int) $span['id'], collect()) as $event)
                                        @php
                                            $eventType = strtolower((string) $event['event_type']);
                                            $tab = 'events';

                                            if (str_starts_with($eventType, 'sdk_')) {
                                                $tab = 'events';
                                            } elseif (str_contains($eventType, 'input') || str_contains($eventType, 'request')) {
                                                $tab = 'input';
                                            } elseif (str_contains($eventType, 'output') || str_contains($eventType, 'response') || str_contains($eventType, 'completion') || str_contains($eventType, 'stream')) {
                                                $tab = 'output';
                                            } elseif (str_contains($eventType, 'usage') || str_contains($eventType, 'attribute') || str_contains($eventType, 'meta')) {
                                                $tab = 'attributes';
                                            }

                                            $eventNodeKey = $span['span_id'].':event:'.$event['event_key'];
                                        @endphp
                                        <button
                                            type="button"
                                            @click="selectedSpanId = @js($span['span_id']); activeTab = @js($tab); selectedNodeKey = @js($eventNodeKey); selectedEventKey = @js($event['event_key'])"
                                            class="flex w-full items-center justify-between rounded px-2 py-1 text-left text-[11px] transition hover:bg-gray-100 dark:hover:bg-gray-700/60"
                                            :class="selectedNodeKey === @js($eventNodeKey) ? 'bg-sky-50 text-sky-800 dark:bg-sky-500/20 dark:text-sky-200' : 'text-gray-600 dark:text-gray-300'"
                                        >
                                            <span class="truncate">{{ \Illuminate\Support\Str::headline((string) $event['event_type']) }}</span>
                                            <span class="ml-2 shrink-0 text-[11px] text-gray-500 dark:text-gray-400">{{ optional($event['recorded_at'])->format('H:i:s') ?: '-' }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div x-show="leftLayout === 'waterfall'" x-cloak class="h-[34rem] overflow-y-auto rounded-lg border border-gray-700/80 bg-[#0b1220] p-2.5">
                        @foreach ($waterfallItems as $item)
                            @php
                                $isStart = $item['kind'] === 'start';
                                $isEnd = $item['kind'] === 'end';
                                $isEvent = $item['kind'] === 'event';
                                $kindPillClasses = $isStart
                                    ? 'bg-emerald-500/20 text-emerald-300'
                                    : ($isEnd
                                        ? 'bg-rose-500/20 text-rose-300'
                                        : 'bg-sky-500/20 text-sky-300');
                                $rowBorderClasses = $isStart
                                    ? 'border-emerald-500/25'
                                    : ($isEnd ? 'border-rose-500/25' : 'border-slate-700');
                                $indentPx = min(220, ((int) ($item['depth'] ?? 0)) * 22);
                            @endphp
                            <button
                                type="button"
                                @click="selectedSpanId = @js($item['span_id']); activeTab = @js($item['tab']); selectedNodeKey = @js($item['key']); selectedEventKey = @js($item['event_key'])"
                                class="mb-1.5 flex w-full items-center gap-2 rounded-md border {{ $rowBorderClasses }} bg-[#0f192b] px-2.5 py-2 text-left transition"
                                style="margin-left: {{ $indentPx }}px; width: calc(100% - {{ $indentPx }}px);"
                                :class="selectedNodeKey === @js($item['key']) ? 'ring-1 ring-sky-400/70 border-sky-400/60 bg-[#14223a]' : 'hover:border-slate-500 hover:bg-[#13233c]'"
                            >
                                <span class="min-w-0 flex-1">
                                    <span class="flex items-center gap-2 text-[11px]">
                                        <span class="truncate font-semibold text-slate-100">{{ $item['label'] }}</span>
                                        <span class="shrink-0 rounded px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide {{ $kindPillClasses }}">{{ $isEvent ? 'Event' : ($isStart ? 'Start' : 'End') }}</span>
                                    </span>
                                    <span class="mt-0.5 block truncate text-[10px] text-slate-400">{{ $item['kind_label'] }}</span>
                                </span>

                                <span class="shrink-0 text-[11px] font-medium text-slate-300">{{ optional($item['at'])->format('H:i:s') ?: '-' }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="default:col-span-8">
                    <div class="mb-3 flex flex-wrap gap-2 border-b border-gray-200 pb-2 dark:border-gray-700">
                        <button type="button" @click="activeTab = 'input'" class="rounded-md px-2.5 py-1.5 text-xs font-medium" :class="activeTab === 'input' ? 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-300' : 'text-gray-600 dark:text-gray-300'">Input</button>
                        <button type="button" @click="activeTab = 'output'" class="rounded-md px-2.5 py-1.5 text-xs font-medium" :class="activeTab === 'output' ? 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-300' : 'text-gray-600 dark:text-gray-300'">Output</button>
                        <button type="button" @click="activeTab = 'events'" class="rounded-md px-2.5 py-1.5 text-xs font-medium" :class="activeTab === 'events' ? 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-300' : 'text-gray-600 dark:text-gray-300'">Events</button>
                        <button type="button" @click="activeTab = 'attributes'" class="rounded-md px-2.5 py-1.5 text-xs font-medium" :class="activeTab === 'attributes' ? 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-300' : 'text-gray-600 dark:text-gray-300'">Attributes</button>
                        <button type="button" @click="activeTab = 'raw'" class="rounded-md px-2.5 py-1.5 text-xs font-medium" :class="activeTab === 'raw' ? 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-300' : 'text-gray-600 dark:text-gray-300'">Raw JSON</button>
                    </div>

                    @foreach ($detail['spans'] as $span)
                        @php
                            $inputPreview = $prettyPayload($span['input_preview'] ?? null);
                            $outputPreview = $prettyPayload($span['output_preview'] ?? null);
                        @endphp
                        <div x-show="selectedSpanId === @js($span['span_id'])" x-cloak>
                            <div class="mb-3 flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                <span class="rounded-md bg-gray-100 px-2 py-1 dark:bg-gray-800">{{ $span['span_type'] }}</span>
                                <span class="rounded-md bg-gray-100 px-2 py-1 dark:bg-gray-800">{{ $span['status'] ?: 'unknown' }}</span>
                            </div>

                            <div x-show="activeTab === 'input'" x-cloak class="rounded-md border border-gray-200 bg-gray-50 p-3 text-xs text-gray-700 dark:border-gray-700 dark:bg-gray-800/40 dark:text-gray-300">
                                <pre class="overflow-x-auto whitespace-pre-wrap">{{ $inputPreview }}</pre>
                            </div>
                            <div x-show="activeTab === 'output'" x-cloak class="rounded-md border border-gray-200 bg-gray-50 p-3 text-xs text-gray-700 dark:border-gray-700 dark:bg-gray-800/40 dark:text-gray-300">
                                <pre class="overflow-x-auto whitespace-pre-wrap">{{ $outputPreview }}</pre>
                            </div>

                            <div x-show="activeTab === 'events'" x-cloak>
                                <div class="h-[28rem] overflow-y-auto rounded-md border border-gray-200 bg-gray-50 p-2 dark:border-gray-700 dark:bg-gray-800/40">
                                    @php
                                        $spanEvents = $eventsBySpanId->get((int) $span['id'], collect());
                                    @endphp
                                    @if ($spanEvents->isEmpty())
                                        <p class="p-3 text-sm text-gray-500 dark:text-gray-400">No events for this span.</p>
                                    @else
                                        @foreach ($spanEvents as $event)
                                            <div x-show="selectedEventKey === @js($event['event_key'])" x-cloak class="mb-3 rounded-md bg-sky-50/80 px-2 py-1.5 text-xs text-sky-800 dark:bg-sky-500/10 dark:text-sky-200">
                                                <p class="font-semibold">Selected Event: {{ \Illuminate\Support\Str::headline((string) $event['event_type']) }}</p>
                                                <p class="text-sky-700/80 dark:text-sky-300/80">{{ optional($event['recorded_at'])->format('Y-m-d H:i:s') ?: '-' }}</p>
                                            </div>
                                        @endforeach

                                        @foreach ($spanEvents as $event)
                                            @php
                                                $payload = $event['payload'];
                                                $inputValue = is_array($payload)
                                                    ? ($payload['input'] ?? $payload['prompt'] ?? $payload['request'] ?? data_get($payload, 'data.input'))
                                                    : null;
                                                $outputValue = is_array($payload)
                                                    ? ($payload['output'] ?? $payload['response'] ?? $payload['result'] ?? data_get($payload, 'data.output'))
                                                    : null;
                                            @endphp
                                            <div class="mb-2 border-b border-gray-200 pb-2 text-xs last:mb-0 last:border-b-0 last:pb-0 dark:border-gray-700">
                                                <p class="font-medium text-gray-700 dark:text-gray-300" :class="selectedEventKey === @js($event['event_key']) ? 'text-sky-700 dark:text-sky-300' : ''">{{ $event['event_type'] }}</p>
                                                <p class="text-gray-500 dark:text-gray-400">{{ optional($event['recorded_at'])->format('Y-m-d H:i:s') ?: '-' }}</p>

                                                @if ($inputValue !== null)
                                                    <div class="mt-2 rounded-md bg-emerald-50/70 p-2 dark:bg-emerald-500/10">
                                                        <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Event Input</p>
                                                        <pre class="overflow-x-auto whitespace-pre-wrap text-gray-700 dark:text-gray-200">{{ $prettyPayload($inputValue) }}</pre>
                                                    </div>
                                                @endif

                                                @if ($outputValue !== null)
                                                    <div class="mt-2 rounded-md bg-indigo-50/70 p-2 dark:bg-indigo-500/10">
                                                        <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-indigo-700 dark:text-indigo-300">Event Output</p>
                                                        <pre class="overflow-x-auto whitespace-pre-wrap text-gray-700 dark:text-gray-200">{{ $prettyPayload($outputValue) }}</pre>
                                                    </div>
                                                @endif

                                                <div class="mt-2 rounded-md bg-gray-100/70 p-2 dark:bg-gray-800/60">
                                                    <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">Full Event Payload</p>
                                                    <pre class="overflow-x-auto whitespace-pre-wrap text-gray-600 dark:text-gray-300">{{ $prettyPayload($payload) }}</pre>
                                                </div>
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
    </div>
</x-ai-trace::layout>
