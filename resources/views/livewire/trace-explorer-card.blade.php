<x-ai-trace::card :cols="$this->cols" wire:poll.15s="">
    <x-ai-trace::card-header
        name="Trace Explorer"
        :details="'Filter and sort traces · Past '.$periodLabel"
    />

    <div class="mb-3 grid gap-3 md:grid-cols-3 xl:grid-cols-3">
        <label class="flex overflow-hidden rounded-md border border-gray-200 focus-within:ring dark:border-gray-700">
            <span class="flex items-center border-r border-gray-200 bg-gray-100 px-3 text-xs text-gray-600 dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-300">Status</span>
            <select wire:model.live="status" class="w-full border-0 bg-gray-50 py-1 pl-3 pr-8 text-xs text-gray-700 shadow-none focus:ring-0 sm:text-sm dark:bg-gray-800 dark:text-gray-300">
                <option value="all">all</option>
                @foreach ($filters['statuses'] as $status)
                    <option value="{{ $status }}">{{ $status }}</option>
                @endforeach
            </select>
        </label>

        <label class="flex overflow-hidden rounded-md border border-gray-200 focus-within:ring dark:border-gray-700">
            <span class="flex items-center border-r border-gray-200 bg-gray-100 px-3 text-xs text-gray-600 dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-300">Provider</span>
            <select wire:model.live="provider" class="w-full border-0 bg-gray-50 py-1 pl-3 pr-8 text-xs text-gray-700 shadow-none focus:ring-0 sm:text-sm dark:bg-gray-800 dark:text-gray-300">
                <option value="all">all</option>
                @foreach ($filters['providers'] as $provider)
                    <option value="{{ $provider }}">{{ $provider }}</option>
                @endforeach
            </select>
        </label>

        <label class="flex overflow-hidden rounded-md border border-gray-200 focus-within:ring dark:border-gray-700">
            <span class="flex items-center border-r border-gray-200 bg-gray-100 px-3 text-xs text-gray-600 dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-300">Model</span>
            <select wire:model.live="model" class="w-full border-0 bg-gray-50 py-1 pl-3 pr-8 text-xs text-gray-700 shadow-none focus:ring-0 sm:text-sm dark:bg-gray-800 dark:text-gray-300">
                <option value="all">all</option>
                @foreach ($filters['models'] as $model)
                    <option value="{{ $model }}">{{ $model }}</option>
                @endforeach
            </select>
        </label>

    </div>

    <div class="mb-3 flex flex-wrap items-center gap-2 text-xs">
        <label class="inline-flex items-center gap-2 rounded-md border border-gray-200 bg-gray-50 px-2 py-1 text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
            <input wire:model.live="errorOnly" type="checkbox" class="h-3.5 w-3.5 rounded border-gray-300 text-gray-700 focus:ring-gray-400 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300">
            Errors only
        </label>

        <label class="inline-flex items-center gap-2 rounded-md border border-gray-200 bg-gray-50 px-2 py-1 text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
            Min duration
            <select wire:model.live="minDurationMs" class="rounded border-0 bg-transparent py-0 pl-1 pr-5 text-xs text-gray-700 shadow-none focus:ring-0 dark:text-gray-300">
                <option value="0">any</option>
                <option value="250">250ms+</option>
                <option value="500">500ms+</option>
                <option value="1000">1s+</option>
                <option value="3000">3s+</option>
            </select>
        </label>

        <label class="inline-flex items-center gap-2 rounded-md border border-gray-200 bg-gray-50 px-2 py-1 text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
            Min tokens
            <select wire:model.live="minTokens" class="rounded border-0 bg-transparent py-0 pl-1 pr-5 text-xs text-gray-700 shadow-none focus:ring-0 dark:text-gray-300">
                <option value="0">any</option>
                <option value="500">500+</option>
                <option value="1000">1k+</option>
                <option value="2500">2.5k+</option>
                <option value="5000">5k+</option>
            </select>
        </label>

        <button type="button" wire:click="clearQuickFilters" class="rounded-md border border-gray-200 px-2 py-1 text-gray-600 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
            Clear quick filters
        </button>
    </div>

    <x-ai-trace::scroll>
        @if ($traces->isEmpty())
            <p class="flex h-full items-center justify-center p-4 text-sm text-gray-400 dark:text-gray-600">No traces match the current filters.</p>
        @else
            <x-ai-trace::table>
                <x-ai-trace::thead>
                    <tr>
                        @php
                            $sortArrow = $this->sortDirection === 'asc' ? '↑' : '↓';
                            $sortLabel = fn (string $column, string $label): string => $this->sortBy === $column ? $label.' '.$sortArrow : $label;
                        @endphp
                        <x-ai-trace::th>
                            <button type="button" wire:click="setSort('name')" class="font-semibold text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-gray-100">{{ $sortLabel('name', 'Trace') }}</button>
                        </x-ai-trace::th>
                        <x-ai-trace::th>
                            <button type="button" wire:click="setSort('status')" class="font-semibold text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-gray-100">{{ $sortLabel('status', 'Status') }}</button>
                        </x-ai-trace::th>
                        <x-ai-trace::th>Provider / Model</x-ai-trace::th>
                        <x-ai-trace::th>
                            <button type="button" wire:click="setSort('started_at')" class="font-semibold text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-gray-100">{{ $sortLabel('started_at', 'Started') }}</button>
                        </x-ai-trace::th>
                        <x-ai-trace::th>
                            <button type="button" wire:click="setSort('duration_ms')" class="font-semibold text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-gray-100">{{ $sortLabel('duration_ms', 'Duration') }}</button>
                        </x-ai-trace::th>
                        <x-ai-trace::th>
                            <button type="button" wire:click="setSort('total_input_tokens')" class="font-semibold text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-gray-100">{{ $sortLabel('total_input_tokens', 'Input') }}</button>
                        </x-ai-trace::th>
                        <x-ai-trace::th>
                            <button type="button" wire:click="setSort('total_output_tokens')" class="font-semibold text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-gray-100">{{ $sortLabel('total_output_tokens', 'Output') }}</button>
                        </x-ai-trace::th>
                        <x-ai-trace::th>
                            <button type="button" wire:click="setSort('total_tokens')" class="font-semibold text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-gray-100">{{ $sortLabel('total_tokens', 'Total') }}</button>
                        </x-ai-trace::th>
                    </tr>
                </x-ai-trace::thead>
                <tbody>
                    @foreach ($traces as $trace)
                        @php
                            $statusClasses = match ($trace['status']) {
                                'ok' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300',
                                'error', 'failed' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300',
                                'cancelled' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300',
                                default => 'bg-gray-100 text-gray-700 dark:bg-gray-700/50 dark:text-gray-300',
                            };
                        @endphp
                        <tr class="h-2 first:h-0"></tr>
                        <tr class="rounded-md hover:bg-gray-50 dark:hover:bg-gray-800/40">
                            <x-ai-trace::td>
                                <a class="font-medium text-sky-700 hover:text-sky-600 dark:text-sky-300 dark:hover:text-sky-200" href="{{ route('ai-trace.dashboard.trace', ['traceId' => $trace['trace_id']]) }}">
                                    {{ $trace['name'] ?: str($trace['trace_id'])->limit(20) }}
                                </a>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $trace['trace_id'] }}</div>
                            </x-ai-trace::td>
                            <x-ai-trace::td>
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $statusClasses }}">{{ $trace['status'] ?: 'unknown' }}</span>
                            </x-ai-trace::td>
                            <x-ai-trace::td>
                                <div class="text-gray-700 dark:text-gray-200">{{ $trace['provider'] ?: '-' }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $trace['model'] ?: '-' }}</div>
                            </x-ai-trace::td>
                            <x-ai-trace::td>{{ optional($trace['started_at'])->toDateTimeString() }}</x-ai-trace::td>
                            <x-ai-trace::td numeric>{{ number_format((int) $trace['duration_ms']) }} ms</x-ai-trace::td>
                            <x-ai-trace::td numeric>{{ number_format((int) $trace['input_tokens']) }}</x-ai-trace::td>
                            <x-ai-trace::td numeric>{{ number_format((int) $trace['output_tokens']) }}</x-ai-trace::td>
                            <x-ai-trace::td numeric>{{ number_format((int) $trace['total_tokens']) }}</x-ai-trace::td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ai-trace::table>
        @endif
    </x-ai-trace::scroll>
</x-ai-trace::card>
