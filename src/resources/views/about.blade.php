@extends('layouts.public')

@section('title', 'About — Obscura')

@section('content')
<style>
    .about-wrap { max-width: 720px; margin: 0 auto; padding: 0 24px; }
    @media (max-width: 640px) {
        .about-wrap { padding: 0 16px; }
        .about-wrap > section { margin-bottom: 32px !important; }
        .about-hero { padding: 32px 0 20px !important; margin-bottom: 24px !important; }
        .about-hero h1 { font-size: 1.625rem !important; }
        .about-hero p { font-size: 1rem !important; }
        .about-wrap h2 { font-size: 1.25rem !important; }
        .about-wrap .card { padding: 16px !important; }
        .gallery-types { grid-template-columns: 1fr !important; }
        .about-wrap table { font-size: 0.8125rem !important; min-width: 0 !important; }
        .about-wrap table th, .about-wrap table td { padding: 8px 6px 8px 0 !important; }
        .about-footer { padding: 24px 0 !important; }
    }
</style>
<div class="about-wrap">

    {{-- Hero --}}
    <div class="about-hero" style="padding:48px 0 32px;border-bottom:1px solid hsl(var(--border));margin-bottom:32px">
        <h1 style="margin-bottom:12px">About Obscura</h1>
        <p style="font-size:1.125rem;color:hsl(var(--muted-foreground));line-height:1.6">
            A zero-trust content storage platform — not just a gallery. Photos, videos,
            documents, and files are encrypted in the browser; the server stores only ciphertext.
        </p>
    </div>

    {{-- Overview --}}
    <section style="margin-bottom:40px">
        <h2 style="margin-bottom:16px">What is Obscura?</h2>
        <p class="text-secondary" style="line-height:1.7;margin-bottom:16px">
            Obscura is a self-hosted, zero-trust content storage workspace.
            Upload any content — photos, videos, documents — organize it into collections
            and galleries, and share it with scoped access codes — all without the
            server ever seeing your content.
        </p>
        <p class="text-secondary" style="line-height:1.7">
            Every workspace has its own AES-256 encryption key, generated in your browser.
            That key is sealed to your RSA public key before it touches the server. Every file
            gets its own per-file key, which is wrapped by the workspace key. Names, titles,
            captions — all encrypted before storage.
        </p>
    </section>

    {{-- Core Features --}}
    <section style="margin-bottom:40px">
        <h2 style="margin-bottom:24px">Core Features</h2>
        <div style="display:grid;gap:12px">
            <div class="card">
                <h3 style="margin-bottom:8px">End-to-End Encrypted Content</h3>
                <p class="text-caption">Each file — photo, video, PDF, or document — is encrypted with a unique AES-256-GCM key before upload. The file key is sealed with the workspace key. The server stores only ciphertext blobs — it never sees plaintext content. Thumbnails are generated and encrypted in the browser. On view, ciphertext streams to the browser and decrypts locally into a lightbox — photos full-screen, video with inline playback, PDFs in an embedded viewer.</p>
            </div>
            <div class="card">
                <h3 style="margin-bottom:8px">Workspace Encryption Keys</h3>
                <p class="text-caption">Every workspace has its own AES-256 key (DEK) generated in the browser via Web Crypto API. The DEK is sealed to the owner's RSA-OAEP public key. The server never sees the raw DEK. Key rotation (re-key) generates a new DEK, re-wraps all file keys, and re-seals for all members — atomically.</p>
            </div>
            <div class="card">
                <h3 style="margin-bottom:8px">Access Codes</h3>
                <p class="text-caption">Generate 12-character codes scoped to a workspace, collection, or gallery. Set permissions (view, upload, comment), duration (minutes to days), and max uses. Codes are hashed with Argon2id server-side. Invitees don't need accounts — just the code. Revoke any code instantly.</p>
            </div>
            <div class="card">
                <h3 style="margin-bottom:8px">Collections &amp; Galleries</h3>
                <p class="text-caption">Workspaces contain collections, which contain galleries. Three gallery types: private (owner only), shared (members view), joint (editors can upload). Gallery names and descriptions are encrypted with the workspace key. Members are assigned editor or viewer roles per gallery.</p>
            </div>
            <div class="card">
                <h3 style="margin-bottom:8px">Hard Revocation (Re-key)</h3>
                <p class="text-caption">Rotating the workspace key re-wraps all file keys, re-encrypts all names and descriptions, re-seals the new key for all members, and revokes every outstanding access code — in a single atomic transaction. Members re-unwrap transparently on next visit.</p>
            </div>
            <div class="card">
                <h3 style="margin-bottom:8px">Keypair &amp; Recovery</h3>
                <p class="text-caption">On registration, the browser generates an RSA-OAEP 2048 keypair. The public key is stored on the server; the private key stays in the browser's IndexedDB. A recovery code seals a copy of the private key — print it and store it safely. Lose it, and your workspaces are permanently inaccessible.</p>
            </div>
        </div>
    </section>

    {{-- Security Model --}}
    <section style="margin-bottom:40px">
        <h2 style="margin-bottom:24px">Security Model</h2>
        <div class="card" style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:0.875rem;min-width:480px">
                <thead>
                    <tr style="border-bottom:1px solid hsl(var(--border))">
                        <th style="text-align:left;padding:10px 0;font-weight:600">Layer</th>
                        <th style="text-align:left;padding:10px 0;font-weight:600">Algorithm</th>
                        <th style="text-align:left;padding:10px 0;font-weight:600">Where</th>
                    </tr>
                </thead>
                <tbody class="text-secondary">
                    <tr style="border-bottom:1px solid hsl(var(--border))">
                        <td style="padding:10px 0">Media files</td>
                        <td>AES-256-GCM (per-file CEK)</td>
                        <td>Browser</td>
                    </tr>
                    <tr style="border-bottom:1px solid hsl(var(--border))">
                        <td style="padding:10px 0">Thumbnails</td>
                        <td>AES-256-GCM</td>
                        <td>Browser</td>
                    </tr>
                    <tr style="border-bottom:1px solid hsl(var(--border))">
                        <td style="padding:10px 0">Titles / Captions / Names</td>
                        <td>AES-256-GCM (workspace DEK)</td>
                        <td>Browser</td>
                    </tr>
                    <tr style="border-bottom:1px solid hsl(var(--border))">
                        <td style="padding:10px 0">Key wrapping</td>
                        <td>RSA-OAEP 2048 / SHA-256</td>
                        <td>Browser</td>
                    </tr>
                    <tr style="border-bottom:1px solid hsl(var(--border))">
                        <td style="padding:10px 0">Access code hashing</td>
                        <td>Argon2id</td>
                        <td>Server</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 0">Code key derivation</td>
                        <td>PBKDF2-SHA256 250k</td>
                        <td>Browser</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    {{-- What the server sees --}}
    <section style="margin-bottom:40px">
        <h2 style="margin-bottom:24px">What the Server Can and Cannot See</h2>
        <div style="display:grid;gap:12px">
            <div class="card" style="border-left:3px solid hsl(var(--foreground))">
                <h3 style="margin-bottom:12px">Cannot see</h3>
                <ul class="text-secondary" style="padding-left:20px;font-size:0.875rem;list-style:disc;line-height:1.8">
                    <li>Workspace, collection, and gallery names</li>
                    <li>All content — photos, videos, documents, titles and captions</li>
                    <li>Workspace encryption keys (DEKs) and file keys (CEKs)</li>
                    <li>Raw access codes (only Argon2id hashes stored)</li>
                    <li>Private keys (never leave the browser)</li>
                </ul>
            </div>
            <div class="card" style="border-left:3px solid hsl(var(--muted-foreground))">
                <h3 style="margin-bottom:12px">Can see (metadata only)</h3>
                <ul class="text-secondary" style="padding-left:20px;font-size:0.875rem;list-style:disc;line-height:1.8">
                    <li>File sizes and MIME types (needed for streaming)</li>
                    <li>User accounts and workspace membership</li>
                    <li>Access code metadata (expiry, scope, permissions, usage count)</li>
                    <li>Audit logs and timestamps</li>
                </ul>
            </div>
        </div>
    </section>

    {{-- Gallery types --}}
    <section style="margin-bottom:40px">
        <h2 style="margin-bottom:24px">Gallery Types</h2>
        <div class="gallery-types" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px">
            <div class="card" style="text-align:center">
                <p class="font-semibold" style="margin-bottom:4px">Private</p>
                <p class="text-caption">Owner only. No members, no access codes.</p>
            </div>
            <div class="card" style="text-align:center">
                <p class="font-semibold" style="margin-bottom:4px">Shared</p>
                <p class="text-caption">Workspace members can view. Only owner can upload or edit.</p>
            </div>
            <div class="card" style="text-align:center">
                <p class="font-semibold" style="margin-bottom:4px">Joint</p>
                <p class="text-caption">Editor members can upload, edit, and delete media.</p>
            </div>
        </div>
    </section>

    {{-- Roles --}}
    <section style="margin-bottom:40px">
        <h2 style="margin-bottom:24px">Roles &amp; Permissions</h2>
        <div class="card" style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:0.875rem;min-width:560px">
                <thead>
                    <tr style="border-bottom:1px solid hsl(var(--border))">
                        <th style="text-align:left;padding:10px 0;font-weight:600">Role</th>
                        <th style="text-align:left;padding:10px 0;font-weight:600">View</th>
                        <th style="text-align:left;padding:10px 0;font-weight:600">Upload</th>
                        <th style="text-align:left;padding:10px 0;font-weight:600">Edit</th>
                        <th style="text-align:left;padding:10px 0;font-weight:600">Delete</th>
                        <th style="text-align:left;padding:10px 0;font-weight:600">Manage</th>
                    </tr>
                </thead>
                <tbody class="text-secondary">
                    <tr style="border-bottom:1px solid hsl(var(--border))">
                        <td style="padding:10px 0;font-weight:500;color:hsl(var(--foreground))">Owner</td>
                        <td style="padding:10px 0">&#10003;</td>
                        <td style="padding:10px 0">&#10003;</td>
                        <td style="padding:10px 0">&#10003;</td>
                        <td style="padding:10px 0">&#10003;</td>
                        <td style="padding:10px 0">&#10003;</td>
                    </tr>
                    <tr style="border-bottom:1px solid hsl(var(--border))">
                        <td style="padding:10px 0;font-weight:500;color:hsl(var(--foreground))">Editor</td>
                        <td style="padding:10px 0">&#10003;</td>
                        <td style="padding:10px 0">&#10003;</td>
                        <td style="padding:10px 0">&#10003;</td>
                        <td style="padding:10px 0">&mdash;</td>
                        <td style="padding:10px 0">&mdash;</td>
                    </tr>
                    <tr style="border-bottom:1px solid hsl(var(--border))">
                        <td style="padding:10px 0;font-weight:500;color:hsl(var(--foreground))">Viewer</td>
                        <td style="padding:10px 0">&#10003;</td>
                        <td style="padding:10px 0">&mdash;</td>
                        <td style="padding:10px 0">&mdash;</td>
                        <td style="padding:10px 0">&mdash;</td>
                        <td style="padding:10px 0">&mdash;</td>
                    </tr>
                    <tr style="border-bottom:1px solid hsl(var(--border))">
                        <td style="padding:10px 0;font-weight:500;color:hsl(var(--foreground))">Code Holder</td>
                        <td style="padding:10px 0">&#10003;</td>
                        <td style="padding:10px 0">if granted</td>
                        <td style="padding:10px 0">&mdash;</td>
                        <td style="padding:10px 0">&mdash;</td>
                        <td style="padding:10px 0">&mdash;</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 0;font-weight:500;color:hsl(var(--foreground))">Super Admin</td>
                        <td style="padding:10px 0">metadata</td>
                        <td style="padding:10px 0">&mdash;</td>
                        <td style="padding:10px 0">&mdash;</td>
                        <td style="padding:10px 0">&mdash;</td>
                        <td style="padding:10px 0">platform</td>
                    </tr>
                </tbody>
            </table>
            <p class="text-caption" style="margin-top:16px;padding-top:12px;border-top:1px solid hsl(var(--border))">
                The Super Admin cannot decrypt content. They see only encrypted metadata — no DEK, no plaintext.
            </p>
        </div>
    </section>

    {{-- Access codes detail --}}
    <section style="margin-bottom:40px">
        <h2 style="margin-bottom:24px">Access Codes</h2>
        <div class="card">
            <p class="text-secondary" style="line-height:1.7;margin-bottom:16px">
                Access codes allow sharing without accounts. Each code is scoped to a workspace,
                collection, or gallery, with configurable permissions and expiration.
            </p>
            <div style="display:grid;gap:8px;font-size:0.875rem">
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid hsl(var(--border))">
                    <span class="font-medium">Format</span>
                    <span class="mono">XXXX-XXXX-XXXX</span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid hsl(var(--border))">
                    <span class="font-medium">Length</span>
                    <span>12 characters (Base32)</span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid hsl(var(--border))">
                    <span class="font-medium">Hashing</span>
                    <span>Argon2id</span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid hsl(var(--border))">
                    <span class="font-medium">Permissions</span>
                    <span>View, Upload, Comment</span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid hsl(var(--border))">
                    <span class="font-medium">Duration</span>
                    <span>Minutes to days</span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:8px 0">
                    <span class="font-medium">Max uses</span>
                    <span>Configurable or unlimited</span>
                </div>
            </div>
        </div>
    </section>

    {{-- Tech stack --}}
    <section style="margin-bottom:40px">
        <h2 style="margin-bottom:24px">Built With</h2>
        <div class="card">
            <div class="flex gap-2" style="flex-wrap:wrap">
                <span class="badge badge-secondary">Laravel 11</span>
                <span class="badge badge-secondary">PHP 8.5</span>
                <span class="badge badge-secondary">Blade</span>
                <span class="badge badge-secondary">Tailwind CSS</span>
                <span class="badge badge-secondary">Web Crypto API</span>
                <span class="badge badge-secondary">AES-256-GCM</span>
                <span class="badge badge-secondary">RSA-OAEP 2048</span>
                <span class="badge badge-secondary">Argon2id</span>
                <span class="badge badge-secondary">PBKDF2</span>
                <span class="badge badge-secondary">SQLite</span>
                <span class="badge badge-secondary">UUID PKs</span>
                <span class="badge badge-secondary">IndexedDB</span>
            </div>
        </div>
    </section>

    {{-- Footer --}}
    <div class="about-footer" style="text-align:center;padding:32px 0;border-top:1px solid hsl(var(--border))">
        <p class="text-caption">
            <a href="{{ route('home') }}" style="color:hsl(var(--foreground));text-decoration:none">Obscura</a> &middot;
            <a href="{{ route('workspaces.index') }}" style="color:hsl(var(--muted-foreground));text-decoration:none">Dashboard</a> &middot;
            <a href="{{ route('enter') }}" style="color:hsl(var(--muted-foreground));text-decoration:none">Enter Code</a>
        </p>
    </div>

</div>
@endsection
