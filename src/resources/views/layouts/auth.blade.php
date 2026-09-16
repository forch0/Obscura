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
</head>
<body style="display:flex;align-items:center;justify-content:center;min-height:100vh;padding:16px">
    <div style="width:100%;max-width:380px">
        <div style="text-align:center;margin-bottom:24px">
            <a href="{{ route('home') }}" style="font-size:1.5rem;font-weight:700;letter-spacing:-0.025em;color:hsl(var(--foreground));text-decoration:none">Obscura</a>
        </div>
        <div class="card">
            @hasSection('card-title')
                <h2 style="margin-bottom:4px">@yield('card-title')</h2>
            @endif
            @hasSection('card-subtitle')
                <p class="text-caption text-secondary" style="margin-bottom:20px">@yield('card-subtitle')</p>
            @endif
            @yield('content')
        </div>
    </div>
    @stack('scripts')
</body>
</html>
