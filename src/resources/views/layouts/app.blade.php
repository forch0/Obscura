<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-public-key" content="{{ auth()->user()?->public_key ?? '' }}">
    <title>@yield('title', 'Obscura')</title>

    {{-- No-flash theme script --}}
    <script>
        (function() {
            const stored = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const theme = stored || (prefersDark ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="app-shell">
        {{-- Sidebar (desktop) --}}
        <nav class="app-sidebar" id="sidebar">
            <a href="/" style="font-size:1.25rem;font-weight:700;color:var(--accent);text-decoration:none;margin-bottom:16px">Obscura</a>
            <a href="{{ route('workspaces.index') }}" style="color:var(--text);text-decoration:none;padding:8px 12px;border-radius:8px;display:block" class="sidebar-link">Workspaces</a>
            <a href="{{ route('enter') }}" style="color:var(--text-secondary);text-decoration:none;padding:8px 12px;border-radius:8px;display:block" class="sidebar-link">Enter Code</a>
        </nav>

        {{-- Main content --}}
        <main class="app-main">
            <div class="top-bar">
                <button class="btn btn-ghost btn-sm" style="display:none" id="sidebar-toggle" aria-label="Menu">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <a href="{{ route('workspaces.index') }}" style="font-weight:600;font-size:1rem;color:var(--accent);text-decoration:none">Obscura</a>
                <div style="flex:1"></div>
                @auth
                    <form method="POST" action="{{ route('logout') }}" style="display:inline">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-sm">Logout</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="btn btn-ghost btn-sm">Login</a>
                    <a href="{{ route('register') }}" class="btn btn-primary btn-sm btn-pill">Register</a>
                @endauth
            </div>

            <div class="app-content">
                @yield('content')
            </div>
        </main>
    </div>

    {{-- Bottom nav (mobile) --}}
    <nav class="bottom-nav">
        <a href="{{ route('workspaces.index') }}">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
            Spaces
        </a>
        <a href="{{ route('enter') }}">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/></svg>
            Codes
        </a>
    </nav>

    {{-- Toast container --}}
    <div class="toast-container" id="toasts"></div>

    @stack('scripts')
</body>
</html>
