@props([
    'eventName',
    'series' => ['labels' => [], 'datasets' => []],
    'palette' => [],
    'heightClass' => 'h-14',
    'chartType' => 'line',
    'stacked' => false,
])

<div
    wire:ignore
    class="relative mt-3 {{ $heightClass }}"
    x-data="aiTraceDashboard.multiLineChart({
        eventName: @js($eventName),
        series: @js($series),
        palette: @js($palette),
        chartType: @js($chartType),
        stacked: @js((bool) $stacked),
    })"
>
    <canvas x-ref="canvas" class="w-full rounded-md bg-gray-50 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-gray-100/10"></canvas>
</div>
