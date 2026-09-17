@extends('layouts.public')

@section('title', 'Obscura — Zero-Trust Content Storage')

@section('content')
<style>
    .hero {
        text-align: center;
        padding: 96px 24px 64px;
        max-width: 720px;
        margin: 0 auto;
    }
    .hero h1 { font-size: 3rem; font-weight: 700; letter-spacing: -0.025em; line-height: 1.1; margin-bottom: 16px; }
    .hero p { font-size: 1.125rem; color: hsl(var(--muted-foreground)); max-width: 520px; margin: 0 auto 32px; line-height: 1.6; }
    .hero-ctas { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
    .hero-ctas .btn { min-width: 140px; }
    .features { max-width: 960px; margin: 0 auto; padding: 0 24px 96px; }
    .features h2 { text-align: center; margin-bottom: 8px; }
    .features .subtitle { text-align: center; color: hsl(var(--muted-foreground)); margin-bottom: 48px; }
    .feature-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; }
    .feature-card { padding: 24px; border: 1px solid hsl(var(--border)); border-radius: var(--radius); }
    .feature-card h3 { font-size: 1rem; font-weight: 600; margin-bottom: 8px; }
    .feature-card p { font-size: 0.875rem; color: hsl(var(--muted-foreground)); line-height: 1.5; }
    @media (max-width: 640px) {
        .hero h1 { font-size: 2rem; }
        .hero { padding: 64px 24px 48px; }
    }
</style>

@auth
    <script>window.location.href = '{{ route('workspaces.index') }}';</script>
@endauth

<section class="hero">
    <h1>Zero-trust storage<br>for your private content.</h1>
    <p>
        Obscura is a zero-trust content vault — not just a gallery app. Photos,
        videos, documents, files: everything is encrypted in your browser before
        it reaches the server. Upload, organize, share, and revoke — on storage
        the server itself cannot read.
    </p>
    <div class="hero-ctas">
        <a href="{{ route('register') }}" class="btn btn-primary btn-lg">Create Account</a>
        <a href="{{ route('enter') }}" class="btn btn-outline btn-lg">Enter Access Code</a>
    </div>
</section>

<section class="features">
    <h2>Everything encrypted. Nothing exposed.</h2>
    <p class="subtitle">Every name, title, caption, and byte of content is encrypted client-side. The server stores only ciphertext it cannot read.</p>

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
            <h3>Organized &amp; Scoped</h3>
            <p>Structure content into collections and galleries. Choose private, shared, or joint galleries with per-member roles — owner, editor, or viewer.</p>
        </div>
        <div class="feature-card">
            <h3>Hard Revocation</h3>
            <p>Re-key rotates the workspace encryption key, re-wraps all media keys, and invalidates every outstanding access code in one atomic operation.</p>
        </div>
        <div class="feature-card">
            <h3>Zero-Trust Server</h3>
            <p>The server is a ciphertext store and an authorization gate — nothing more. Names, descriptions, and content are all encrypted. Even the Super Admin is cryptographically locked out.</p>
        </div>
        <div class="feature-card">
            <h3>Any Content Type</h3>
            <p>Photos and videos are first-class — encrypted thumbnails, lightbox viewing, inline playback. Documents and PDFs get the same zero-knowledge treatment.</p>
        </div>
        <div class="feature-card">
            <h3>Self-Hostable</h3>
            <p>Runs on standard PHP hosting. No Node.js required at runtime. All encryption happens in the browser — your server just stores blobs.</p>
        </div>
    </div>
</section>
@endsection
