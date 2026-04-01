<x-ai-trace::card :cols="$this->cols" wire:poll.15s="">
    <x-ai-trace::card-header
        name="Trace Explorer"
        :details="'Filter by status, provider, and model · Past '.$periodLabel"
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

    <x-ai-trace::scroll>
        @if ($traces->isEmpty())
            <p class="flex h-full items-center justify-center p-4 text-sm text-gray-400 dark:text-gray-600">No traces match the current filters.</p>
        @else
            <x-ai-trace::table>
                <x-ai-trace::thead>
                    <tr>
                        <x-ai-trace::th>Trace</x-ai-trace::th>
                        <x-ai-trace::th>Status</x-ai-trace::th>
                        <x-ai-trace::th>Provider</x-ai-trace::th>
                        <x-ai-trace::th>Model</x-ai-trace::th>
                        <x-ai-trace::th>Duration</x-ai-trace::th>
                        <x-ai-trace::th>Tokens</x-ai-trace::th>
                    </tr>
                </x-ai-trace::thead>
                <tbody>
                    @foreach ($traces as $trace)
                        <tr class="h-2 first:h-0"></tr>
                        <tr>
                            <x-ai-trace::td>
                                <a class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300" href="{{ route('ai-trace.dashboard.trace', ['traceId' => $trace['trace_id']]) }}">
                                    {{ $trace['name'] ?: $trace['trace_id'] }}
                                </a>
                            </x-ai-trace::td>
                            <x-ai-trace::td>{{ $trace['status'] }}</x-ai-trace::td>
                            <x-ai-trace::td>{{ $trace['provider'] ?: '-' }}</x-ai-trace::td>
                            <x-ai-trace::td>{{ $trace['model'] ?: '-' }}</x-ai-trace::td>
                            <x-ai-trace::td numeric>{{ number_format((int) $trace['duration_ms']) }} ms</x-ai-trace::td>
                            <x-ai-trace::td numeric>{{ number_format((int) $trace['total_tokens']) }}</x-ai-trace::td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ai-trace::table>
        @endif
    </x-ai-trace::scroll>
</x-ai-trace::card>
