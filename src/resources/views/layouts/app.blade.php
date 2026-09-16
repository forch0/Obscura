<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-public-key" content="{{ auth()->user()?->public_key ?? '' }}">
    <title>@yield('title', 'Obscura')</title>

    <script>
        (function() {
            const stored = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.setAttribute('data-theme', stored || (prefersDark ? 'dark' : 'light'));
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="app-shell">
        <nav class="app-sidebar">
            <a href="{{ route('home') }}" class="sidebar-logo">Obscura</a>
            <a href="{{ route('workspaces.index') }}" class="sidebar-link {{ request()->routeIs('workspaces.*') ? 'active' : '' }}">Workspaces</a>
            <a href="{{ route('enter') }}" class="sidebar-link {{ request()->routeIs('enter*') ? 'active' : '' }}">Enter Code</a>
            <a href="{{ route('about') }}" class="sidebar-link {{ request()->routeIs('about') ? 'active' : '' }}">About</a>
        </nav>

        <main class="app-main">
            <div class="top-bar">
                <a href="{{ route('home') }}" class="logo">Obscura</a>
                <div style="flex:1"></div>
                @auth
                    <form method="POST" action="{{ route('logout') }}" style="display:inline">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-sm">Log out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="btn btn-ghost btn-sm">Log in</a>
                    <a href="{{ route('register') }}" class="btn btn-primary btn-sm">Get Started</a>
                @endauth
            </div>

            <div class="app-content">
                @yield('content')
            </div>
        </main>
    </div>

    <nav class="bottom-nav">
        <a href="{{ route('workspaces.index') }}" class="{{ request()->routeIs('workspaces.*') ? 'active' : '' }}">Spaces</a>
        <a href="{{ route('enter') }}" class="{{ request()->routeIs('enter*') ? 'active' : '' }}">Codes</a>
        <a href="{{ route('about') }}" class="{{ request()->routeIs('about') ? 'active' : '' }}">About</a>
    </nav>

    <div class="toast-container" id="toasts"></div>

    @stack('scripts')
</body>
</html>
