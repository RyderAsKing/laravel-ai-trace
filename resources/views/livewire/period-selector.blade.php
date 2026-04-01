<div class="flex overflow-hidden rounded-md border border-gray-200 focus-within:ring dark:border-gray-700">
    <label for="ai-trace-period" class="flex items-center border-r border-gray-200 bg-gray-100 px-3 text-xs text-gray-600 dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-300">Period</label>
    <select id="ai-trace-period" wire:model.live="minutes" class="w-full border-0 bg-gray-50 py-1 pl-3 pr-8 text-xs text-gray-700 shadow-none focus:ring-0 sm:text-sm dark:bg-gray-800 dark:text-gray-300">
        @foreach ($options as $value => $label)
            <option value="{{ $value }}">{{ $label }}</option>
        @endforeach
    </select>
</div>
