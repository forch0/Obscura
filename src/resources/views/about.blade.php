@extends('layouts.app')

@section('title', 'About — Obscura')

@section('content')
    <div style="max-width:720px;margin:0 auto">

        {{-- Hero --}}
        <div style="text-align:center;padding:48px 0 32px">
            <h1 style="font-size:2.5rem;margin-bottom:12px">Obscura</h1>
            <p style="font-size:1.125rem;color:var(--text-secondary);max-width:480px;margin:0 auto">
                A private, encrypted gallery workspace. Your images are encrypted in the browser before they ever reach the server.
            </p>
        </div>

        {{-- What it is --}}
        <div class="card" style="margin-bottom:24px">
            <h3>What is Obscura?</h3>
            <p class="text-secondary" style="margin-top:8px;line-height:1.6">
                Obscura is an end-to-end encrypted image gallery platform. Upload images, organize them into collections and galleries,
                share them with scoped access codes, and revoke access at any time — all without the server ever seeing your content.
            </p>
        </div>

        {{-- How it works --}}
        <h3 style="margin-bottom:16px">How It Works</h3>
        <div style="display:grid;gap:12px;margin-bottom:32px">
            <div class="card" style="display:flex;gap:16px;align-items:flex-start">
                <div style="width:32px;height:32px;border-radius:50%;background:var(--accent-subtle);color:var(--accent);display:flex;align-items:center;justify-content:center;font-weight:600;flex-shrink:0">1</div>
                <div>
                    <p class="font-semibold">Create a workspace</p>
                    <p class="text-caption text-secondary">A workspace gets its own AES-256 encryption key, generated in your browser and never sent to the server.</p>
                </div>
            </div>
            <div class="card" style="display:flex;gap:16px;align-items:flex-start">
                <div style="width:32px;height:32px;border-radius:50%;background:var(--accent-subtle);color:var(--accent);display:flex;align-items:center;justify-content:center;font-weight:600;flex-shrink:0">2</div>
                <div>
                    <p class="font-semibold">Organize collections &amp; galleries</p>
                    <p class="text-caption text-secondary">Group galleries into collections. Choose private, shared, or joint galleries for collaboration.</p>
                </div>
            </div>
            <div class="card" style="display:flex;gap:16px;align-items:flex-start">
                <div style="width:32px;height:32px;border-radius:50%;background:var(--accent-subtle);color:var(--accent);display:flex;align-items:center;justify-content:center;font-weight:600;flex-shrink:0">3</div>
                <div>
                    <p class="font-semibold">Upload encrypted media</p>
                    <p class="text-caption text-secondary">Images are encrypted in your browser with per-file keys. Thumbnails are generated and encrypted client-side.</p>
                </div>
            </div>
            <div class="card" style="display:flex;gap:16px;align-items:flex-start">
                <div style="width:32px;height:32px;border-radius:50%;background:var(--accent-subtle);color:var(--accent);display:flex;align-items:center;justify-content:center;font-weight:600;flex-shrink:0">4</div>
                <div>
                    <p class="font-semibold">Share with access codes</p>
                    <p class="text-caption text-secondary">Generate time-boxed, revocable codes. Invitees don't need accounts — just the code.</p>
                </div>
            </div>
        </div>

        {{-- Security model --}}
        <h3 style="margin-bottom:16px">Security Model</h3>
        <div class="card" style="margin-bottom:24px">
            <div style="display:grid;gap:12px">
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border)">
                    <span class="font-medium">Encryption</span>
                    <span class="badge badge-accent">AES-256-GCM</span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border)">
                    <span class="font-medium">Key wrapping</span>
                    <span class="badge badge-accent">RSA-OAEP 2048</span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border)">
                    <span class="font-medium">Code hashing</span>
                    <span class="badge badge-accent">Argon2id</span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border)">
                    <span class="font-medium">Key derivation</span>
                    <span class="badge badge-accent">PBKDF2 250k</span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0">
                    <span class="font-medium">Server sees</span>
                    <span class="badge">Ciphertext only</span>
                </div>
            </div>
        </div>

        {{-- What's encrypted --}}
        <h3 style="margin-bottom:16px">What the Server Can and Cannot See</h3>
        <div style="display:grid;gap:12px;margin-bottom:32px">
            <div class="card" style="border-left:3px solid var(--success)">
                <p class="font-semibold" style="color:var(--success)">Cannot see</p>
                <ul class="text-secondary" style="margin-top:8px;padding-left:20px;font-size:0.875rem;list-style:disc">
                    <li>Workspace, collection, and gallery names</li>
                    <li>Image content, titles, and captions</li>
                    <li>Encryption keys (DEKs and CEKs)</li>
                    <li>Raw access codes</li>
                </ul>
            </div>
            <div class="card" style="border-left:3px solid var(--warning)">
                <p class="font-semibold" style="color:var(--warning)">Can see (metadata only)</p>
                <ul class="text-secondary" style="margin-top:8px;padding-left:20px;font-size:0.875rem;list-style:disc">
                    <li>File sizes and MIME types (needed for streaming)</li>
                    <li>User accounts and workspace membership</li>
                    <li>Access code metadata (expiry, scope, permissions)</li>
                    <li>Timestamps and audit logs</li>
                </ul>
            </div>
        </div>

        {{-- Gallery types --}}
        <h3 style="margin-bottom:16px">Gallery Types</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:32px">
            <div class="card" style="text-align:center">
                <p style="font-size:1.5rem;margin-bottom:8px">🔒</p>
                <p class="font-semibold">Private</p>
                <p class="text-caption text-secondary" style="margin-top:4px">Owner only. No members allowed.</p>
            </div>
            <div class="card" style="text-align:center">
                <p style="font-size:1.5rem;margin-bottom:8px">👥</p>
                <p class="font-semibold">Shared</p>
                <p class="text-caption text-secondary" style="margin-top:4px">Workspace members can view. Only owner edits.</p>
            </div>
            <div class="card" style="text-align:center">
                <p style="font-size:1.5rem;margin-bottom:8px">🤝</p>
                <p class="font-semibold">Joint</p>
                <p class="text-caption text-secondary" style="margin-top:4px">Editor members can upload, edit, and delete.</p>
            </div>
        </div>

        {{-- Access codes --}}
        <h3 style="margin-bottom:16px">Access Codes</h3>
        <div class="card" style="margin-bottom:24px">
            <p class="text-secondary" style="line-height:1.6">
                Generate codes scoped to a workspace, collection, or gallery. Set permissions (view, upload, comment),
                duration (minutes to days), and max uses. Revoke any code instantly. Re-key a workspace to invalidate all
                outstanding codes at once.
            </p>
            <div class="code-display" style="margin-top:16px">K7QX-9P2M-4F8R</div>
            <p class="text-caption text-muted" style="margin-top:8px;text-align:center">Example code format — 12 chars, grouped for readability</p>
        </div>

        {{-- Roles --}}
        <h3 style="margin-bottom:16px">Roles &amp; Permissions</h3>
        <div class="card" style="margin-bottom:32px;overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:0.875rem">
                <thead>
                    <tr style="border-bottom:1px solid var(--border)">
                        <th style="text-align:left;padding:8px;font-weight:600">Role</th>
                        <th style="text-align:left;padding:8px;font-weight:600">View</th>
                        <th style="text-align:left;padding:8px;font-weight:600">Upload</th>
                        <th style="text-align:left;padding:8px;font-weight:600">Edit</th>
                        <th style="text-align:left;padding:8px;font-weight:600">Delete</th>
                        <th style="text-align:left;padding:8px;font-weight:600">Manage</th>
                    </tr>
                </thead>
                <tbody class="text-secondary">
                    <tr style="border-bottom:1px solid var(--border)">
                        <td style="padding:8px;font-weight:500">Owner</td>
                        <td style="padding:8px">✓</td>
                        <td style="padding:8px">✓</td>
                        <td style="padding:8px">✓</td>
                        <td style="padding:8px">✓</td>
                        <td style="padding:8px">✓</td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--border)">
                        <td style="padding:8px;font-weight:500">Editor</td>
                        <td style="padding:8px">✓</td>
                        <td style="padding:8px">✓</td>
                        <td style="padding:8px">✓</td>
                        <td style="padding:8px">—</td>
                        <td style="padding:8px">—</td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--border)">
                        <td style="padding:8px;font-weight:500">Viewer</td>
                        <td style="padding:8px">✓</td>
                        <td style="padding:8px">—</td>
                        <td style="padding:8px">—</td>
                        <td style="padding:8px">—</td>
                        <td style="padding:8px">—</td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--border)">
                        <td style="padding:8px;font-weight:500">Code (view)</td>
                        <td style="padding:8px">✓</td>
                        <td style="padding:8px">—</td>
                        <td style="padding:8px">—</td>
                        <td style="padding:8px">—</td>
                        <td style="padding:8px">—</td>
                    </tr>
                    <tr>
                        <td style="padding:8px;font-weight:500">Super Admin</td>
                        <td style="padding:8px">✓ <span class="text-caption text-muted">(metadata)</span></td>
                        <td style="padding:8px">—</td>
                        <td style="padding:8px">—</td>
                        <td style="padding:8px">—</td>
                        <td style="padding:8px">✓ <span class="text-caption text-muted">(platform)</span></td>
                    </tr>
                </tbody>
            </table>
            <p class="text-caption text-muted" style="margin-top:12px">
                Super Admin cannot decrypt content — they have no DEK. They see only encrypted metadata.
            </p>
        </div>

        {{-- Tech stack --}}
        <h3 style="margin-bottom:16px">Built With</h3>
        <div class="card" style="margin-bottom:32px">
            <div class="flex gap-2" style="flex-wrap:wrap">
                <span class="badge">Laravel 11</span>
                <span class="badge">PHP 8.5</span>
                <span class="badge">Blade</span>
                <span class="badge">Alpine.js</span>
                <span class="badge">Tailwind CSS</span>
                <span class="badge">Web Crypto API</span>
                <span class="badge">AES-256-GCM</span>
                <span class="badge">RSA-OAEP</span>
                <span class="badge">Argon2id</span>
                <span class="badge">PBKDF2</span>
                <span class="badge">SQLite</span>
                <span class="badge">UUID</span>
            </div>
        </div>

        {{-- Footer --}}
        <div style="text-align:center;padding:32px 0;border-top:1px solid var(--border)">
            <p class="text-caption text-muted">Obscura — Private encrypted galleries. Your keys, your content, your rules.</p>
            <p class="text-caption text-muted" style="margin-top:4px">
                <a href="{{ route('workspaces.index') }}" style="color:var(--accent);text-decoration:none">Dashboard</a> ·
                <a href="{{ route('enter') }}" style="color:var(--accent);text-decoration:none">Enter Code</a>
            </p>
        </div>

    </div>
@endsection
