@props(['name' => '', 'details' => null])
<header class="mb-3 flex items-center justify-between gap-4 @md:mb-6">
    <h2 class="truncate text-base font-bold text-gray-600 dark:text-gray-300">{{ $name }}</h2>
    @if ($details)
        <p class="truncate text-xs font-medium text-gray-400 dark:text-gray-600">{{ $details }}</p>
    @endif
</header>
