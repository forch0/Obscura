<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Obscura')</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .auth-card {
            width: 100%;
            max-width: 400px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 40px 32px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.06);
        }
        .auth-logo {
            text-align: center;
            margin-bottom: 32px;
        }
        .auth-logo h1 {
            font-size: 1.75rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--text);
        }
        .auth-logo p {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-top: 4px;
        }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 6px;
            color: var(--text);
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
        .form-input::placeholder { color: var(--text-muted); }
        .btn-primary {
            width: 100%;
            height: 48px;
            border: none;
            border-radius: 9999px;
            background: var(--accent);
            color: #fff;
            font-family: inherit;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 150ms ease-out;
        }
        .btn-primary:hover { background: var(--accent-hover); }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-secondary {
            width: 100%;
            height: 48px;
            border: 1px solid var(--border);
            border-radius: 9999px;
            background: var(--bg);
            color: var(--text);
            font-family: inherit;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 150ms ease-out;
        }
        .btn-secondary:hover { background: var(--bg-subtle); }
        .auth-links {
            text-align: center;
            margin-top: 24px;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        .auth-links a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 500;
        }
        .auth-links a:hover { text-decoration: underline; }
        .error-text {
            color: var(--danger);
            font-size: 0.8125rem;
            margin-top: 4px;
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
        @yield('extra-styles')
    </style>
    @stack('scripts')
</head>
<body>
    <div class="auth-card">
        <div class="auth-logo">
            <h1>Obscura</h1>
            <p>@yield('subtitle', 'Private encrypted gallery')</p>
        </div>

        @if ($errors->any())
            <div class="alert-error">
                @foreach ($errors->all() as $error)
                    {{ $error }}<br>
                @endforeach
            </div>
        @endif

        @yield('content')
    </div>
</body>
</html>
