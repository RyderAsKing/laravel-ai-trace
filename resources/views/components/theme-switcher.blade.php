<div x-data="{ dark: document.documentElement.classList.contains('dark') }" class="flex">
    <button
        type="button"
        class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-gray-200 bg-gray-100 text-gray-500 transition hover:bg-gray-200 hover:text-gray-700 dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-300 dark:hover:bg-gray-700/70"
        x-on:click="
            dark = !dark;
            document.documentElement.classList.toggle('dark', dark);
            localStorage.setItem('ai-trace-theme', dark ? 'dark' : 'light');
        "
        x-bind:aria-label="dark ? 'Switch to light mode' : 'Switch to dark mode'"
        x-bind:title="dark ? 'Switch to light mode' : 'Switch to dark mode'"
    >
        <svg x-show="!dark" x-cloak class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.5M12 18.5V21M4.93 4.93l1.77 1.77M17.3 17.3l1.77 1.77M3 12h2.5M18.5 12H21M4.93 19.07l1.77-1.77M17.3 6.7l1.77-1.77M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
        </svg>
        <svg x-show="dark" x-cloak class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.8A9 9 0 1111.2 3 7 7 0 0021 12.8z"/>
        </svg>
    </button>
</div>
