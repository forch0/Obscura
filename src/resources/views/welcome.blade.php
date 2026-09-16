<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Obscura — Private Encrypted Galleries</title>

    <script>
        (function() {
            const stored = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.setAttribute('data-theme', stored || (prefersDark ? 'dark' : 'light'));
        })();
    </script>

    @vite(['resources/css/app.css'])
</head>
<body>
    @auth
        <script>window.location.href = '{{ route('workspaces.index') }}';</script>
    @endauth

    <div style="min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px;text-align:center">

        {{-- Logo --}}
        <h1 style="font-size:3rem;font-weight:700;color:var(--accent);margin-bottom:8px">Obscura</h1>
        <p style="font-size:1.125rem;color:var(--text-secondary);max-width:440px;margin-bottom:40px">
            Private encrypted galleries. Your images are encrypted in the browser — the server only sees ciphertext.
        </p>

        {{-- CTA buttons --}}
        <div class="flex gap-2" style="flex-wrap:wrap;justify-content:center">
            <a href="{{ route('register') }}" class="btn btn-primary btn-lg btn-pill" style="min-width:160px">Create Account</a>
            <a href="{{ route('login') }}" class="btn btn-secondary btn-lg" style="min-width:160px">Sign In</a>
        </div>

        <div style="margin-top:24px">
            <a href="{{ route('enter') }}" style="color:var(--accent);text-decoration:none;font-size:0.9375rem">
                Have an access code? Enter it →
            </a>
        </div>

        {{-- Features --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;max-width:720px;margin-top:64px;width:100%">
            <div class="card" style="text-align:center;padding:24px 16px">
                <p style="font-size:1.75rem;margin-bottom:8px">🔒</p>
                <p class="font-semibold">End-to-End Encrypted</p>
                <p class="text-caption text-secondary" style="margin-top:4px">AES-256-GCM in your browser. Server is blind.</p>
            </div>
            <div class="card" style="text-align:center;padding:24px 16px">
                <p style="font-size:1.75rem;margin-bottom:8px">📁</p>
                <p class="font-semibold">Collections &amp; Galleries</p>
                <p class="text-caption text-secondary" style="margin-top:4px">Organize with private, shared, or joint galleries.</p>
            </div>
            <div class="card" style="text-align:center;padding:24px 16px">
                <p style="font-size:1.75rem;margin-bottom:8px">🔑</p>
                <p class="font-semibold">Access Codes</p>
                <p class="text-caption text-secondary" style="margin-top:4px">Time-boxed, revocable, scoped to galleries.</p>
            </div>
            <div class="card" style="text-align:center;padding:24px 16px">
                <p style="font-size:1.75rem;margin-bottom:8px">🔄</p>
                <p class="font-semibold">Hard Revocation</p>
                <p class="text-caption text-secondary" style="margin-top:4px">Re-key to invalidate all codes instantly.</p>
            </div>
        </div>

        {{-- Footer --}}
        <p class="text-caption text-muted" style="margin-top:48px">
            <a href="{{ route('about') }}" style="color:var(--accent);text-decoration:none">Learn more</a>
        </p>
    </div>
</body>
</html>
