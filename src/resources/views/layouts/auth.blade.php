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
    <div style="width:100%;max-width:400px">
        <div class="card" style="padding:32px">
            <h1 style="text-align:center;margin-bottom:24px;font-size:1.5rem;color:var(--accent)">Obscura</h1>
            @yield('content')
        </div>
    </div>
    @stack('scripts')
</body>
</html>
