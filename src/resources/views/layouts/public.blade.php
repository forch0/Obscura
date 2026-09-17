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

    @vite(['resources/css/app.css'])
    <style>
        .public-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            height: 56px;
            border-bottom: 1px solid hsl(var(--border));
            position: sticky;
            top: 0;
            background: hsl(var(--background));
            z-index: 20;
        }

        .public-nav .logo {
            font-size: 1.125rem;
            font-weight: 700;
            letter-spacing: -0.025em;
            color: hsl(var(--foreground));
            text-decoration: none;
        }

        .public-nav .links {
            display: flex;
            gap: 24px;
            align-items: center;
        }

        .public-nav .links a:not(.btn) {
            font-size: 0.875rem;
            color: hsl(var(--muted-foreground));
            text-decoration: none;
            transition: color 150ms ease;
        }

        .public-nav .links a:not(.btn):hover {
            color: hsl(var(--foreground));
        }

        @media (max-width: 720px) {
            .public-nav .links a:not(.btn) { display: none; }
            .public-nav { padding: 0 16px; }
        }

        /* Mobile menu */
        .menu-toggle {
            display: none;
            align-items: center;
            justify-content: center;
            width: 36px; height: 36px;
            background: none;
            border: 1px solid hsl(var(--border));
            border-radius: 6px;
            cursor: pointer;
            color: hsl(var(--foreground));
        }
        .menu-toggle svg { width: 18px; height: 18px; }

        .mobile-menu {
            display: none;
            border-bottom: 1px solid hsl(var(--border));
            background: hsl(var(--background));
            padding: 8px 16px 16px;
        }
        .mobile-menu.open { display: block; }
        .mobile-menu a {
            display: block;
            padding: 10px 8px;
            font-size: 0.9375rem;
            color: hsl(var(--foreground));
            text-decoration: none;
            border-radius: 6px;
        }
        .mobile-menu a:hover { background: hsl(var(--muted)); }

        @media (max-width: 720px) {
            .menu-toggle { display: flex; }
        }
    </style>
</head>
<body style="display:flex;flex-direction:column;min-height:100vh">
    <nav class="public-nav">
        <a href="{{ route('home') }}" class="logo">Obscura</a>
        <div class="links">
            <a href="{{ route('about') }}">About</a>
            <a href="{{ route('architecture') }}">Architecture</a>
            <a href="{{ route('use-cases') }}">Use Cases</a>
            <a href="{{ route('showcase') }}">Case Study</a>
            <a href="{{ route('enter') }}">Enter Code</a>
            @auth
                <a href="{{ route('workspaces.index') }}" class="btn btn-primary btn-sm">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="nav-primary">Sign In</a>
                <a href="{{ route('register') }}" class="btn btn-primary btn-sm">Get Started</a>
            @endauth
            <button class="menu-toggle" id="menu-toggle" aria-label="Menu" aria-expanded="false">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/>
                </svg>
            </button>
        </div>
    </nav>

    <div class="mobile-menu" id="mobile-menu">
        <a href="{{ route('about') }}">About</a>
        <a href="{{ route('architecture') }}">Architecture</a>
        <a href="{{ route('use-cases') }}">Use Cases</a>
        <a href="{{ route('showcase') }}">Case Study</a>
        <a href="{{ route('enter') }}">Enter Code</a>
        @guest
            <a href="{{ route('login') }}">Sign In</a>
        @endguest
    </div>

    <div style="flex:1">
        @yield('content')
    </div>

    <x-footer />

    <script>
        const toggle = document.getElementById('menu-toggle');
        const menu = document.getElementById('mobile-menu');
        toggle?.addEventListener('click', () => {
            const open = menu.classList.toggle('open');
            toggle.setAttribute('aria-expanded', open);
        });
    </script>
</body>
</html>
