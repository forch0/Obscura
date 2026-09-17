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
    @stack('styles')
    @yield('extra-styles')
</head>
<body>
    <div class="app-shell">
        <nav class="app-sidebar">
            <a href="{{ route('home') }}" class="sidebar-logo">Obscura</a>
            <a href="{{ route('workspaces.index') }}" class="sidebar-link {{ request()->routeIs('workspaces.*') ? 'active' : '' }}">Workspaces</a>
            <a href="{{ route('enter') }}" class="sidebar-link {{ request()->routeIs('enter*') ? 'active' : '' }}">Enter Code</a>
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
                <button class="menu-toggle" id="app-menu-toggle" aria-label="Menu" aria-expanded="false">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="18" height="18">
                        <line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/>
                    </svg>
                </button>
            </div>

            <div class="app-mobile-menu" id="app-mobile-menu">
                <a href="{{ route('workspaces.index') }}" class="{{ request()->routeIs('workspaces.*') ? 'active' : '' }}">Workspaces</a>
                <a href="{{ route('enter') }}" class="{{ request()->routeIs('enter*') ? 'active' : '' }}">Enter Code</a>
            </div>

            <div class="app-content">
                @yield('content')
            </div>

            <x-footer />
        </main>
    </div>

    <div class="toast-container" id="toasts"></div>

    <script>
        const t = document.getElementById('app-menu-toggle');
        const m = document.getElementById('app-mobile-menu');
        t?.addEventListener('click', () => {
            const open = m.classList.toggle('open');
            t.setAttribute('aria-expanded', open);
        });
    </script>

    @stack('scripts')
</body>
</html>
