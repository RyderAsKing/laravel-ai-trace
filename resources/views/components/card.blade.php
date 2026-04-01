@props(['cols' => 6])
@php
    $cols = max(1, min(12, (int) $cols));
@endphp
<section
    {{ $attributes->merge(['class' => "@container flex flex-col rounded-xl bg-white p-3 text-gray-900 shadow-sm ring-1 ring-gray-900/5 sm:p-6 dark:bg-gray-900 dark:text-gray-100 default:col-span-full default:lg:col-span-{$cols}"]) }}
    x-data="{
        loading: false,
        init() {
            @if (isset($_instance))
                Livewire.hook('commit', ({ component, succeed }) => {
                    if (component.id === $wire.__instance.id) {
                        succeed(() => this.loading = false)
                    }
                })
            @endif
        }
    }"
>
    {{ $slot }}
</section>
