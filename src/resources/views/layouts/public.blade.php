<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Obscura')</title>

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
            .public-nav .links a:not(.btn):not(.nav-primary) { display: none; }
            .public-nav { padding: 0 16px; }
        }

        .public-footer {
            text-align: center;
            padding: 32px 24px;
            border-top: 1px solid hsl(var(--border));
            font-size: 0.8125rem;
            color: hsl(var(--muted-foreground));
        }

        .public-footer a {
            color: hsl(var(--foreground));
            text-decoration: none;
        }

        .public-footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <nav class="public-nav">
        <a href="{{ route('home') }}" class="logo">Obscura</a>
        <div class="links">
            <a href="{{ route('about') }}">About</a>
            <a href="{{ route('architecture') }}">Architecture</a>
            <a href="{{ route('use-cases') }}">Use Cases</a>
            <a href="{{ route('enter') }}">Enter Code</a>
            @auth
                <a href="{{ route('workspaces.index') }}" class="btn btn-primary btn-sm">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="nav-primary">Sign In</a>
                <a href="{{ route('register') }}" class="btn btn-primary btn-sm">Get Started</a>
            @endauth
        </div>
    </nav>

    @yield('content')

    <footer class="public-footer">
        <p>
            <a href="{{ route('home') }}">Obscura</a> &middot;
            <a href="{{ route('about') }}">About</a> &middot;
            <a href="{{ route('architecture') }}">Architecture</a> &middot;
            <a href="{{ route('use-cases') }}">Use Cases</a> &middot;
            <a href="{{ route('enter') }}">Enter Code</a>
        </p>
    </footer>
</body>
</html>
