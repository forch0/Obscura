@extends('layouts.public')

@section('title', 'Case Study — Obscura')

@section('content')
<style>
    .cs-hero { text-align: center; padding: 80px 24px 48px; max-width: 680px; margin: 0 auto; }
    .cs-hero .eyebrow { font-size: 0.8125rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: hsl(var(--muted-foreground)); margin-bottom: 16px; }
    .cs-hero h1 { font-size: 2.5rem; letter-spacing: -0.025em; line-height: 1.1; margin-bottom: 16px; }
    .cs-hero p { font-size: 1.125rem; color: hsl(var(--muted-foreground)); line-height: 1.6; }

    .cs-wrap { max-width: 880px; margin: 0 auto; padding: 0 24px 96px; }
    .cs-wrap > section { margin-bottom: 56px; }
    .cs-wrap h2 { font-size: 1.375rem; margin-bottom: 16px; letter-spacing: -0.015em; }
    .cs-wrap p, .cs-wrap li { font-size: 0.9375rem; color: hsl(var(--muted-foreground)); line-height: 1.7; }
    .cs-wrap ul { padding-left: 20px; list-style: disc; }
    .cs-wrap li { margin-bottom: 8px; }
    .cs-wrap strong { color: hsl(var(--foreground)); font-weight: 600; }

    .cs-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1px; background: hsl(var(--border)); border: 1px solid hsl(var(--border)); border-radius: var(--radius); overflow: hidden; margin: 24px 0; }
    .cs-stat { background: hsl(var(--card)); padding: 24px 16px; text-align: center; }
    .cs-stat .num { font-size: 1.75rem; font-weight: 700; letter-spacing: -0.02em; }
    .cs-stat .lbl { font-size: 0.75rem; color: hsl(var(--muted-foreground)); margin-top: 4px; text-transform: uppercase; letter-spacing: 0.05em; }

    .cs-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; }
    .cs-card { border: 1px solid hsl(var(--border)); border-radius: var(--radius); padding: 24px; }
    .cs-card h3 { font-size: 0.9375rem; font-weight: 600; margin-bottom: 8px; }
    .cs-card p { font-size: 0.875rem; color: hsl(var(--muted-foreground)); line-height: 1.6; }

    .cs-quote {
        border-left: 3px solid hsl(var(--foreground));
        padding: 16px 20px;
        font-size: 1rem;
        color: hsl(var(--foreground));
        margin: 24px 0;
        font-weight: 500;
        line-height: 1.6;
    }
    .cs-quote span { display: block; font-size: 0.8125rem; font-weight: 400; color: hsl(var(--muted-foreground)); margin-top: 8px; }

    .cs-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
    .cs-table th { text-align: left; padding: 10px 12px; font-weight: 600; border-bottom: 1px solid hsl(var(--border)); }
    .cs-table td { padding: 10px 12px; border-bottom: 1px solid hsl(var(--border)); vertical-align: top; color: hsl(var(--muted-foreground)); }
    .cs-table td:first-child { font-weight: 500; color: hsl(var(--foreground)); white-space: nowrap; }
    .cs-table-wrap { overflow-x: auto; }
    .cs-table { min-width: 560px; }

    @media (max-width: 640px) {
        .cs-hero { padding: 56px 20px 32px; }
        .cs-hero h1 { font-size: 1.875rem; }
        .cs-wrap { padding: 0 16px 64px; }
    }
</style>

<section class="cs-hero">
    <p class="eyebrow">Case Study</p>
    <h1>Obscura — zero-trust content storage, engineered end to end</h1>
    <p>
        A private content platform where the server is provably blind to user content.
        Designed, built, and tested as a demonstration of security-first product engineering.
    </p>
</section>

<div class="cs-wrap">

    {{-- The problem --}}
    <section>
        <h2>The problem</h2>
        <p>
            Consumer storage platforms force a bad bargain: convenience for exposure. Storage providers
            hold decryption keys, files leak through public URLs, and "private" usually means
            "private until someone asks for the key."
        </p>
        <p style="margin-top:12px">
            Obscura rejects the bargain. <strong>The server is a ciphertext store and an authorization
            gate — nothing more.</strong> Every name, caption, and image byte is encrypted in the
            browser before transit. A full database dump yields metadata, not content.
        </p>
        <div class="cs-quote">
            "If the operator can read your content, it's not private. I designed the system so
            that even the platform super-admin is cryptographically locked out."
            <span>— Design constraint #1, recorded in docs/DECISIONS.md</span>
        </div>
    </section>

    {{-- What it demonstrates --}}
    <section>
        <h2>What this project demonstrates</h2>

        <div class="cs-stats">
            <div class="cs-stat"><div class="num">3</div><div class="lbl">key tiers</div></div>
            <div class="cs-stat"><div class="num">0</div><div class="lbl">plaintext at rest</div></div>
            <div class="cs-stat"><div class="num">6</div><div class="lbl">authz levels</div></div>
            <div class="cs-stat"><div class="num">200+</div><div class="lbl">feature tests</div></div>
        </div>

        <div class="cs-grid">
            <div class="cs-card">
                <h3>Applied cryptography</h3>
                <p>Web Crypto API in production: RSA-OAEP 2048 keypairs, AES-256-GCM DEKs and per-file CEKs, PBKDF2 key derivation (250k iterations), Argon2id code hashing. No custom crypto — every primitive is native and auditable.</p>
            </div>
            <div class="cs-card">
                <h3>Zero-knowledge key management</h3>
                <p>Three-tier hierarchy (user → workspace → file) with envelope encryption. Sealed private keys enable multi-device login without the server ever seeing key material. Recovery codes re-seal, never reset.</p>
            </div>
            <div class="cs-card">
                <h3>Real revocation</h3>
                <p>Two-tier revocation: soft (codes/members denied at the gate) and hard (full DEK rotation re-wraps every CEK in one atomic transaction). A stolen key stops working for real, not just politely.</p>
            </div>
            <div class="cs-card">
                <h3>Scoped guest access</h3>
                <p>Access codes grant view/upload rights to a workspace, collection, or single gallery — no account required. Encrypted, scope-pinned session cookies are re-validated server-side on every request.</p>
            </div>
            <div class="cs-card">
                <h3>Authorization as architecture</h3>
                <p>Six-level precedence chain (super-admin → owner → member → gallery member → code session → deny) resolved through a dedicated AuthorizationResolver, not scattered checks. Super-admin is explicitly denied content.</p>
            </div>
            <div class="cs-card">
                <h3>Audit trail</h3>
                <p>Every sensitive action — code generation, revocation, re-key, member changes, deletion — writes an audit record with actor, subject, context, and IP. Metadata only, by design.</p>
            </div>
        </div>
    </section>

    {{-- Product engineering --}}
    <section>
        <h2>Product engineering</h2>
        <p>Security that fights the user doesn't get used. The hard part was making zero-knowledge feel effortless:</p>
        <ul>
            <li><strong>Invisible crypto.</strong> Uploads show per-file progress, not key jargon. The user picks photos; the browser handles CEKs, thumbnails, and sealed titles.</li>
            <li><strong>Batch uploads with guardrails.</strong> Multi-file selection with limits stated up front (20 files / 100 MB each / 500 MB per batch) — enforced before work begins, not after it fails.</li>
            <li><strong>Lightbox viewing.</strong> Decrypted media opens in a fullscreen viewer with keyboard navigation and PDF/video handling. Blob URLs are revoked between items to bound memory.</li>
            <li><strong>GitHub-style destructive confirms.</strong> Deleting a workspace, collection, gallery, or file requires typing its decrypted name. Custom dialogs replace native <code class="mono">confirm()</code>/<code class="mono">alert()</code> throughout.</li>
            <li><strong>Recipient-aware sharing.</strong> Codes can carry a recipient email — the raw code is mailed once at creation, the only moment it exists server-side. Delivery failure never blocks creation.</li>
            <li><strong>Custom component system.</strong> Reusable dropdowns, form selects, permission chips, toasts, empty states — all vanilla JS with delegated handlers, no framework overhead.</li>
            <li><strong>Mobile-first throughout.</strong> Responsive grids, touch-friendly controls, icon-only secondary actions on small screens, no horizontal overflow anywhere.</li>
        </ul>
    </section>

    {{-- Engineering decisions --}}
    <section>
        <h2>Decisions worth defending</h2>
        <div class="cs-table-wrap"><table class="cs-table">
            <tr><th>Decision</th><th>Reasoning</th></tr>
            <tr>
                <td>Re-key invalidates access codes</td>
                <td>The server holds only Argon2id hashes and code-key-wrapped DEKs — it can't re-wrap what it can't read. Regenerating codes is the honest price of real revocation.</td>
            </tr>
            <tr>
                <td>No password-based recovery</td>
                <td>Lost password + lost recovery code = lost data. Any reset path that could recover content would be a backdoor — this is a feature, not a gap.</td>
            </tr>
            <tr>
                <td>Keys in sessionStorage</td>
                <td>Private keys clear on tab close — convenience traded for not persisting key material to disk.</td>
            </tr>
            <tr>
                <td>Synchronous queue + browser-heavy re-key</td>
                <td>The expensive crypto runs where the keys already are (the owner's browser). The server does one atomic transaction — deployable on commodity cPanel hosting.</td>
            </tr>
            <tr>
                <td>Emailed codes travel plaintext</td>
                <td>Documented, opt-in convenience. Codes stay revocable and time-boxed; the alternative (secure channel setup) defeats the "no account needed" purpose.</td>
            </tr>
        </table></div>
    </section>

    {{-- Security practice --}}
    <section>
        <h2>Security practice, not just security claims</h2>
        <ul>
            <li><strong>Defense in depth on blobs:</strong> ciphertext stored outside the web root AND authorization-gated at the route level — the file being unreadable doesn't make it public.</li>
            <li><strong>Constant-time code verification</strong> via Argon2id; lookup by hash, never by plaintext comparison.</li>
            <li><strong>Per-request session validation:</strong> access-code cookies re-checked against revocation, expiry, use-count, and permission bits on every hit — including media streams.</li>
            <li><strong>Locked writes during rotation:</strong> uploads during a re-key return 423 — they would be sealed to a soon-stale DEK.</li>
            <li><strong>Crash-safe rotation:</strong> re-key commits in a single transaction; a dead browser mid-rotation leaves the workspace untouched and retryable. Stale-job sweeper prevents wedged locks.</li>
            <li><strong>UUID keys everywhere:</strong> no enumerable IDs, no identifier guessing in URLs.</li>
            <li><strong>Honest threat model:</strong> the docs enumerate what a DB dump, a live server compromise, a malicious admin, and a stolen code each expose — and what they can't touch.</li>
        </ul>
    </section>

    {{-- Stack --}}
    <section>
        <h2>The stack</h2>
        <p>
            Deliberately boring where it counts: <strong>Laravel</strong> (policies, middleware, transactions),
            <strong>Blade</strong> templates, <strong>vanilla JS</strong> crypto modules with delegated event handling,
            and a monochrome design system built on CSS custom properties. Deployable to shared PHP hosting —
            no Node runtime, no Redis requirement, no infrastructure tax.
        </p>
        <p style="margin-top:12px">
            The full design reasoning lives in the repo: <code class="mono">docs/DECISIONS.md</code>,
            <code class="mono">docs/AUDIT_LOG.md</code>, and six module-by-module build documents.
        </p>
        <div class="hero-ctas" style="display:flex;gap:12px;flex-wrap:wrap;margin-top:24px">
            <a href="{{ route('architecture') }}" class="btn btn-outline">Read the architecture</a>
            <a href="{{ route('about') }}" class="btn btn-ghost">About the product</a>
        </div>
    </section>

</div>
@endsection
