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
    <style>
        .landing-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 24px;
            border-bottom: 1px solid hsl(hsl(var(--border)));
        }

        .landing-nav .logo {
            font-size: 1.125rem;
            font-weight: 700;
            letter-spacing: -0.025em;
            color: hsl(var(--foreground));
            text-decoration: none;
        }

        .landing-nav .links {
            display: flex;
            gap: 24px;
            align-items: center;
        }

        .landing-nav .links a {
            font-size: 0.875rem;
            color: hsl(var(--muted-foreground));
            text-decoration: none;
            transition: color 150ms ease;
        }

        .landing-nav .links a:hover {
            color: hsl(var(--foreground));
        }

        .hero {
            text-align: center;
            padding: 96px 24px 64px;
            max-width: 720px;
            margin: 0 auto;
        }

        .hero h1 {
            font-size: 3rem;
            font-weight: 700;
            letter-spacing: -0.025em;
            line-height: 1.1;
            margin-bottom: 16px;
        }

        .hero p {
            font-size: 1.125rem;
            color: hsl(var(--muted-foreground));
            max-width: 520px;
            margin: 0 auto 32px;
            line-height: 1.6;
        }

        .hero-ctas {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .hero-ctas .btn { min-width: 140px; }

        .features {
            max-width: 960px;
            margin: 0 auto;
            padding: 0 24px 96px;
        }

        .features h2 {
            text-align: center;
            margin-bottom: 8px;
        }

        .features .subtitle {
            text-align: center;
            color: hsl(var(--muted-foreground));
            margin-bottom: 48px;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
        }

        .feature-card {
            padding: 24px;
            border: 1px solid hsl(hsl(var(--border)));
            border-radius: var(--radius);
        }

        .feature-card h3 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .feature-card p {
            font-size: 0.875rem;
            color: hsl(var(--muted-foreground));
            line-height: 1.5;
        }

        .divider {
            max-width: 960px;
            margin: 0 auto;
            padding: 0 24px;
        }

        .divider hr {
            border: none;
            border-top: 1px solid hsl(hsl(var(--border)));
        }

        .footer {
            text-align: center;
            padding: 32px 24px;
            border-top: 1px solid hsl(hsl(var(--border)));
            font-size: 0.8125rem;
            color: hsl(var(--muted-foreground));
        }

        .footer a {
            color: hsl(var(--foreground));
            text-decoration: none;
        }

        .footer a:hover { text-decoration: underline; }

        @media (max-width: 640px) {
            .hero h1 { font-size: 2rem; }
            .hero { padding: 64px 24px 48px; }
        }
    </style>
</head>
<body>
    @auth
        <script>window.location.href = '{{ route('workspaces.index') }}';</script>
    @endauth

    <nav class="landing-nav">
        <a href="{{ route('home') }}" class="logo">Obscura</a>
        <div class="links">
            <a href="{{ route('about') }}">About</a>
            <a href="{{ route('enter') }}">Enter Code</a>
            <a href="{{ route('login') }}">Sign In</a>
            <a href="{{ route('register') }}" class="btn btn-primary btn-sm">Get Started</a>
        </div>
    </nav>

    <section class="hero">
        <h1>Private, encrypted<br>image galleries.</h1>
        <p>
            Obscura is an end-to-end encrypted gallery workspace. Your images are encrypted
            in your browser before they reach the server. Upload, organize, share, and
            revoke — all with zero-knowledge architecture.
        </p>
        <div class="hero-ctas">
            <a href="{{ route('register') }}" class="btn btn-primary btn-lg">Create Account</a>
            <a href="{{ route('enter') }}" class="btn btn-outline btn-lg">Enter Access Code</a>
        </div>
    </section>

    <section class="features">
        <h2>Everything encrypted. Nothing exposed.</h2>
        <p class="subtitle">Every name, title, caption, and image is encrypted client-side. The server stores only ciphertext.</p>

        <div class="feature-grid">
            <div class="feature-card">
                <h3>End-to-End Encryption</h3>
                <p>AES-256-GCM encryption in the browser. Workspace keys never leave your device unencrypted. The server stores only sealed keys and ciphertext blobs.</p>
            </div>
            <div class="feature-card">
                <h3>Access Codes</h3>
                <p>Generate scoped, time-boxed codes for sharing. Viewers don't need accounts. Revoke any code instantly — or re-key the workspace to invalidate all of them.</p>
            </div>
            <div class="feature-card">
                <h3>Collections &amp; Galleries</h3>
                <p>Organize galleries inside collections. Choose private, shared, or joint galleries with per-member roles — owner, editor, or viewer.</p>
            </div>
            <div class="feature-card">
                <h3>Hard Revocation</h3>
                <p>Re-key rotates the workspace encryption key, re-wraps all media keys, and invalidates every outstanding access code in one atomic operation.</p>
            </div>
            <div class="feature-card">
                <h3>Zero-Knowledge Server</h3>
                <p>Names, descriptions, titles, captions, and image content are all encrypted. Even the Super Admin cannot decrypt your media.</p>
            </div>
            <div class="feature-card">
                <h3>Self-Hostable</h3>
                <p>Runs on standard PHP hosting. No Node.js required at runtime. All encryption happens in the browser — your server just stores blobs.</p>
            </div>
        </div>
    </section>

    <div class="divider"><hr></div>

    <footer class="footer">
        <p>
            Obscura &middot;
            <a href="{{ route('about') }}">About</a> &middot;
            <a href="{{ route('enter') }}">Enter Code</a> &middot;
            <a href="{{ route('login') }}">Sign In</a>
        </p>
    </footer>
</body>
</html>
