<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if(auth()->check() && auth()->user()->public_key)
        <meta name="user-public-key" content="{{ auth()->user()->public_key }}">
    @endif
    <title>@yield('title', 'Obscura — Workspaces')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --bg: #ffffff;
            --bg-subtle: #f8f9fa;
            --bg-muted: #f1f3f5;
            --border: #e9ecef;
            --text: #212529;
            --text-secondary: #6c757d;
            --text-muted: #adb5bd;
            --accent: #4f46e5;
            --accent-hover: #4338ca;
            --accent-subtle: #eef2ff;
            --danger: #dc2626;
            --success: #16a34a;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg-subtle);
            color: var(--text);
            min-height: 100vh;
        }
        .app-header {
            background: var(--bg);
            border-bottom: 1px solid var(--border);
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .app-logo h1 {
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        .app-logo h1 a { color: var(--text); text-decoration: none; }
        .app-nav { display: flex; gap: 16px; align-items: center; }
        .app-nav a {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .app-nav a:hover { color: var(--accent); }
        .app-main { max-width: 960px; margin: 0 auto; padding: 32px 24px; }
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }
        .page-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        .btn-primary {
            display: inline-flex;
            align-items: center;
            height: 40px;
            padding: 0 20px;
            border: none;
            border-radius: 9999px;
            background: var(--accent);
            color: #fff;
            font-family: inherit;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: background 150ms ease-out;
        }
        .btn-primary:hover { background: var(--accent-hover); }
        .btn-secondary {
            display: inline-flex;
            align-items: center;
            height: 40px;
            padding: 0 20px;
            border: 1px solid var(--border);
            border-radius: 9999px;
            background: var(--bg);
            color: var(--text);
            font-family: inherit;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: background 150ms ease-out;
        }
        .btn-secondary:hover { background: var(--bg-subtle); }
        .btn-danger {
            display: inline-flex;
            align-items: center;
            height: 40px;
            padding: 0 20px;
            border: 1px solid #fecaca;
            border-radius: 9999px;
            background: #fff;
            color: var(--danger);
            font-family: inherit;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-danger:hover { background: #fef2f2; }
        .workspace-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
        }
        .workspace-card {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            transition: border-color 150ms ease-out;
        }
        .workspace-card:hover { border-color: var(--accent); }
        .workspace-card h3 {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .workspace-card p {
            font-size: 0.8125rem;
            color: var(--text-secondary);
        }
        .workspace-card .actions {
            margin-top: 16px;
            display: flex;
            gap: 8px;
        }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 6px;
        }
        .form-input {
            width: 100%;
            height: 48px;
            padding: 0 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-family: inherit;
            font-size: 1rem;
            color: var(--text);
            background: var(--bg);
            transition: border-color 150ms ease-out, box-shadow 150ms ease-out;
        }
        .form-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 2px var(--accent-subtle);
        }
        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: var(--danger);
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 0.875rem;
            margin-bottom: 20px;
        }
        .empty-state {
            text-align: center;
            padding: 64px 24px;
            color: var(--text-secondary);
        }
        .empty-state h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 8px;
        }
        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid var(--border);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .decrypt-status {
            font-size: 0.8125rem;
            color: var(--text-secondary);
            margin-top: 4px;
        }
    </style>
    @stack('scripts')
</head>
<body>
    <header class="app-header">
        <div class="app-logo">
            <h1><a href="{{ route('workspaces.index') }}">Obscura</a></h1>
        </div>
        <nav class="app-nav">
            <a href="{{ route('workspaces.index') }}">Workspaces</a>
            <form method="POST" action="{{ route('logout') }}" style="display:inline">
                @csrf
                <button type="submit" style="background:none;border:none;color:inherit;cursor:pointer;font:inherit">Logout</button>
            </form>
        </nav>
    </header>

    <main class="app-main">
        @if ($errors->any())
            <div class="alert-error">
                @foreach ($errors->all() as $error)
                    {{ $error }}<br>
                @endforeach
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
