<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Obscura')</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">

    <script>
        (function() {
            const stored = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.setAttribute('data-theme', stored || (prefersDark ? 'dark' : 'light'));
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @yield('extra-styles')
</head>
<body>
    <div class="top-bar" style="justify-content:space-between">
        <a href="{{ route('home') }}" class="logo">Obscura</a>
        <span class="text-caption text-secondary">Shared access</span>
    </div>

    <main class="app-content" style="max-width:960px">
        @yield('content')
    </main>

    <x-footer />
    <div class="toast-container" id="toasts"></div>
    @stack('scripts')
</body>
</html>
