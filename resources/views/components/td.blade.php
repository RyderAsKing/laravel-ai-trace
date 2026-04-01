@props(['numeric' => false])
<td {{ $attributes->merge(['class' => 'bg-gray-50 dark:bg-gray-800/50 text-sm py-3 first:pl-3 last:pr-3 px-1 @sm:px-3 first:rounded-l-md last:rounded-r-md'.($numeric ? ' text-right tabular-nums whitespace-nowrap' : '')]) }}>
    {{ $slot }}
</td>
