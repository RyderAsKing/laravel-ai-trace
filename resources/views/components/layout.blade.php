<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Laravel AI Trace</title>
        <script>
            (() => {
                const storedTheme = localStorage.getItem('ai-trace-theme');
                const preferDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                const useDark = storedTheme ? storedTheme === 'dark' : preferDark;

                document.documentElement.classList.toggle('dark', useDark);
            })();
        </script>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:300,400,500,600,700" rel="stylesheet">
        {!! app('ai-trace-dashboard-assets')->css() !!}
        @livewireStyles
    </head>
    <body class="bg-gray-50 font-sans antialiased text-gray-900 dark:bg-gray-950 dark:text-gray-100">
        <div class="ai-trace-shell">
            <header class="px-5">
                <div class="container mx-auto border-b border-gray-200 py-3 sm:py-5 dark:border-gray-900">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-lg font-medium text-gray-700 sm:text-2xl dark:text-gray-300"><b class="font-bold">Laravel</b> AI Trace</h1>
                            <p class="text-xs text-gray-500 sm:text-sm dark:text-gray-500">Trace and span observability dashboard</p>
                        </div>

                        @if (isset($controls))
                            <div class="flex items-center gap-3 sm:gap-6">
                                {{ $controls }}
                            </div>
                        @endif
                    </div>
                </div>
            </header>

            <main class="px-6 pb-12 pt-6">
                <div class="container mx-auto grid default:grid-cols-12 default:gap-6">
                    {{ $slot }}
                </div>
            </main>
        </div>
        @livewireScripts
        {!! app('ai-trace-dashboard-assets')->js() !!}
    </body>
</html>
