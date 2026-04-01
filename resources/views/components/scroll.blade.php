<div
    x-data="{
        init() {
            $nextTick(() => this.scroll())
        },
        scroll() {
            const { content, fade } = this.$refs

            if (! fade) {
                return
            }

            const distanceToBottom = content.scrollHeight - (content.scrollTop + content.clientHeight)

            if (distanceToBottom >= 24) {
                fade.style.transform = 'scaleY(1)'
            } else {
                fade.style.transform = `scaleY(${distanceToBottom / 24})`
            }
        }
    }"
    {{ $attributes->merge(['class' => '@container/scroll-wrapper flex w-full flex-grow overflow-hidden basis-56', ':class' => "loading && 'animate-pulse opacity-25'"]) }}
>
    <div x-ref="content" class="supports-scrollbars basis-full flex-grow space-y-1 overflow-y-auto scrollbar:h-1.5 scrollbar:w-1.5 scrollbar:bg-transparent scrollbar-track:rounded scrollbar-track:bg-gray-100 scrollbar-thumb:rounded scrollbar-thumb:bg-gray-300 dark:scrollbar-track:bg-gray-500/10 dark:scrollbar-thumb:bg-gray-500/50" @scroll.debounce.5ms="scroll">
        {{ $slot }}
        <div x-ref="fade" class="pointer-events-none fixed bottom-0 left-0 right-0 h-6 origin-bottom bg-white/90 dark:bg-gray-900/90" wire:ignore></div>
    </div>
</div>
