<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <link rel="icon" href="/assets/image/favicon.png" type="image/png">
        <link rel="apple-touch-icon" href="/assets/image/favicon.png">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            @php($meta = $page['props']['meta'] ?? null)

            @if (is_array($meta))
                <title>{{ $meta['title'] ?? config('app.name', 'Laravel') }}</title>
                <meta name="description" content="{{ $meta['description'] ?? '' }}">
                <link rel="canonical" href="{{ $meta['canonicalUrl'] ?? url()->current() }}">
                <meta property="og:title" content="{{ data_get($meta, 'openGraph.title', $meta['title'] ?? config('app.name', 'Laravel')) }}">
                <meta property="og:description" content="{{ data_get($meta, 'openGraph.description', $meta['description'] ?? '') }}">
                <meta property="og:url" content="{{ data_get($meta, 'openGraph.url', $meta['canonicalUrl'] ?? url()->current()) }}">
                <meta property="og:type" content="website">
                @if (filled(data_get($meta, 'openGraph.imageUrl')))
                    <meta property="og:image" content="{{ data_get($meta, 'openGraph.imageUrl') }}">
                @endif
                <meta name="robots" content="{{ $meta['robots'] ?? 'index,follow' }}">
            @else
                <title>{{ config('app.name', 'Laravel') }}</title>
            @endif
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
