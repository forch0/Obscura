@extends('layouts.public')

@section('title', 'Architecture — Obscura')

@section('content')
<style>
    .arch-section { max-width: 800px; margin: 0 auto; padding: 0 24px; }
    .arch-section > section { margin-bottom: 48px; }
    .arch-section h2 { font-size: 1.5rem; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 1px solid hsl(var(--border)); }
    .arch-section h3 { font-size: 1rem; margin-bottom: 8px; }
    .arch-section p, .arch-section li { font-size: 0.9375rem; color: hsl(var(--muted-foreground)); line-height: 1.7; }
    .arch-section ul { padding-left: 20px; list-style: disc; }
    .arch-section li { margin-bottom: 6px; }
    .arch-section strong { color: hsl(var(--foreground)); font-weight: 600; }
    .arch-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
    .arch-table th { text-align: left; padding: 10px 12px; font-weight: 600; border-bottom: 1px solid hsl(var(--border)); color: hsl(var(--foreground)); }
    .arch-table td { padding: 10px 12px; border-bottom: 1px solid hsl(var(--border)); vertical-align: top; }
    .arch-table td:first-child { font-weight: 500; color: hsl(var(--foreground)); }
    .arch-diagram {
        font-family: 'JetBrains Mono', ui-monospace, monospace;
        font-size: 0.8125rem;
        line-height: 1.6;
        background: hsl(var(--muted));
        border: 1px solid hsl(var(--border));
        border-radius: var(--radius);
        padding: 20px 24px;
        overflow-x: auto;
        white-space: pre;
        color: hsl(var(--foreground));
        margin: 16px 0;
    }
    .arch-note {
        border-left: 3px solid hsl(var(--border));
        padding: 12px 16px;
        font-size: 0.875rem;
        color: hsl(var(--muted-foreground));
        margin: 16px 0;
    }
    .arch-hero { text-align: center; padding: 80px 24px 48px; max-width: 640px; margin: 0 auto; }
    .arch-hero h1 { font-size: 2.5rem; margin-bottom: 12px; }
    .arch-hero p { font-size: 1.125rem; color: hsl(var(--muted-foreground)); line-height: 1.6; }
    .arch-table-wrap { overflow-x: auto; margin: 0 -4px; }
    .arch-table { min-width: 560px; }
    @media (max-width: 640px) {
        .arch-hero { padding: 56px 20px 32px; }
        .arch-hero h1 { font-size: 1.875rem; }
        .arch-section { padding: 0 16px; }
        .arch-diagram { font-size: 0.75rem; padding: 16px; }
    }
    .arch-toc { border: 1px solid hsl(var(--border)); border-radius: var(--radius); padding: 20px 24px; margin-bottom: 48px; }
    .arch-toc a { color: hsl(var(--foreground)); text-decoration: none; font-size: 0.875rem; display: block; padding: 4px 0; }
    .arch-toc a:hover { text-decoration: underline; }
    .arch-toc .toc-label { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: hsl(var(--muted-foreground)); margin-bottom: 8px; }
</style>

<section class="arch-hero">
    <h1>Architecture</h1>
    <p>How Obscura achieves zero-knowledge encryption end-to-end — key hierarchy, data flows, and the design decisions behind them.</p>
</section>

<div class="arch-section">

    <nav class="arch-toc">
        <p class="toc-label">Contents</p>
        <a href="#overview">1. Overview</a>
        <a href="#key-hierarchy">2. Key Hierarchy</a>
        <a href="#registration">3. Registration &amp; Key Generation</a>
        <a href="#upload">4. Upload Flow</a>
        <a href="#viewing">5. Viewing &amp; Decryption</a>
        <a href="#access-codes">6. Access Codes</a>
        <a href="#rekey">7. Re-key &amp; Hard Revocation</a>
        <a href="#data-model">8. Data Model</a>
        <a href="#authorization">9. Authorization</a>
        <a href="#threat-model">10. Threat Model</a>
        <a href="#tradeoffs">11. Design Tradeoffs</a>
    </nav>

    {{-- 1. Overview --}}
    <section id="overview">
        <h2>1. Overview</h2>
        <p>
            Obscura is a <strong>zero-knowledge encrypted gallery platform</strong>. The guiding constraint:
            <strong>the server must never be able to read user content</strong> — not names, not captions, not image bytes.
            All cryptography happens in the browser via the Web Crypto API. The server is a ciphertext store
            and an authorization gate, nothing more.
        </p>
        <p style="margin-top:12px">
            This shapes every decision: keys are generated client-side, sealed to asymmetric keys before transit,
            and stored only in wrapped form. A full database dump — or a malicious operator — reveals
            metadata (sizes, timestamps, relationships) but zero content.
        </p>
        <div class="arch-note">
            The stack is deliberately boring: Laravel + Blade + a small set of vanilla-JS crypto modules.
            No heavy JS framework, no custom crypto. Everything is standard WebCrypto and standard Laravel —
            auditable, portable, and deployable to commodity PHP hosting.
        </div>
    </section>

    {{-- 2. Key Hierarchy --}}
    <section id="key-hierarchy">
        <h2>2. Key Hierarchy</h2>
        <p>Three tiers of keys, each protecting the layer below it:</p>

        <div class="arch-diagram">
User RSA-OAEP 2048 keypair          (per user — generated in browser)
        │
        │  seals/unwraps
        ▼
Workspace DEK — AES-256-GCM       (per workspace — generated in browser)
        │
        │  wraps/unwraps
        ▼
Media CEK — AES-256-GCM           (per file — generated in browser)
        │
        │  encrypts/decrypts
        ▼
Image blob / thumbnail            (ciphertext at rest, always)
        </div>

        <div class="arch-table-wrap"><table class="arch-table">
            <tr><th>Key</th><th>Type</th><th>Generated</th><th>Stored</th></tr>
            <tr>
                <td>User private key</td>
                <td>RSA-OAEP 2048</td>
                <td>Browser (registration)</td>
                <td>Never leaves the browser. A sealed copy (AES-GCM, key derived from password via PBKDF2) is stored server-side for multi-device login; a second sealed copy is locked to the recovery code.</td>
            </tr>
            <tr>
                <td>User public key</td>
                <td>RSA-OAEP 2048</td>
                <td>Browser (registration)</td>
                <td>Server (plaintext — public keys are safe to store).</td>
            </tr>
            <tr>
                <td>Workspace DEK</td>
                <td>AES-256-GCM</td>
                <td>Browser (workspace creation)</td>
                <td>Server, wrapped: sealed with owner's RSA public key (<code class="mono">wrapped_dek_for_owner</code>). Members get their own copies sealed to <em>their</em> public keys (<code class="mono">workspace_members.wrapped_dek</code>).</td>
            </tr>
            <tr>
                <td>Media CEK</td>
                <td>AES-256-GCM</td>
                <td>Browser (per upload)</td>
                <td>Server, wrapped by the workspace DEK (<code class="mono">media.cek_wrapped</code>).</td>
            </tr>
        </table></div>

        <div class="arch-note">
            <strong>Why three tiers?</strong> Rotation cost. Re-keying the workspace DEK only requires
            re-wrapping small CEKs (~32 bytes each) — the multi-megabyte file blobs are untouched.
            Revoking a member means rotating the DEK, not re-encrypting every image.
        </div>
    </section>

    {{-- 3. Registration --}}
    <section id="registration">
        <h2>3. Registration &amp; Key Generation</h2>
        <div class="arch-diagram">
Browser                                    Server
───────                                    ──────
1. Generate RSA-OAEP 2048 keypair
2. Export private key (PKCS#8)
3. PBKDF2(password, salt, 250k)
       → wrapping key
4. AES-GCM encrypt private key
       → sealed_priv
5. Generate recovery code
6. Seal private key again under
   recovery-code-derived key
       → sealed_priv_recovery
7. POST ──────────────────────────────►  stores:
                                           public_key
                                           sealed_priv : iv
                                           sealed_priv_recovery : iv
                                           keypair_salt
                                           recovery_code_salt
                                           recovery_code_hash (Argon2id)
        </div>
        <p>
            The password itself is never used for key material on the server — it's only a PBKDF2 input
            in the browser. The sealed private key blob is useless to anyone without the password
            (or recovery code).
        </p>
    </section>

    {{-- 4. Upload --}}
    <section id="upload">
        <h2>4. Upload Flow</h2>
        <div class="arch-diagram">
Browser                                    Server
───────                                    ──────
1. Read file bytes
2. Generate CEK (AES-256-GCM)
3. Encrypt file  → ciphertext blob
4. Generate thumbnail via canvas
5. Encrypt thumbnail (separate IV)
6. Wrap CEK with workspace DEK
       → cek_wrapped
7. Encrypt title/caption with DEK
8. POST multipart ────────────────────►  stores:
                                           blob_path   (private disk)
                                           thumb_path
                                           cek_wrapped
                                           iv, thumb_iv
                                           encrypted_title/caption
                                           mime_type, size
        </div>
        <p>
            The upload is a standard multipart request, but every sensitive field is already ciphertext.
            The server writes the blob to <code class="mono">storage/app/private/</code> — outside the web root,
            only reachable through an authorization-gated streaming route.
        </p>
        <div class="arch-note">
            <strong>Per-file keys, per-file IVs.</strong> Each image and each thumbnail gets a fresh
            random 96-bit IV and its own CEK. A key compromise is scoped to a single file.
        </div>
    </section>

    {{-- 5. Viewing --}}
    <section id="viewing">
        <h2>5. Viewing &amp; Decryption</h2>
        <div class="arch-diagram">
Browser                                    Server
───────                                    ──────
1. GET /galleries/{id}/media ─────────►  authorize →
2.   ← metadata JSON (encrypted          return rows
       titles, wrapped CEKs, sizes)       (no plaintext)
3. Decrypt titles with DEK
4. GET /media/{id}/thumbnail ─────────►  authorize → stream
5.   ← ciphertext + X-Cek-Wrapped,       ciphertext bytes
       X-Blob-Iv headers
6. Unwrap CEK → decrypt → object URL
7. Full image: same flow via /blob
        </div>
        <p>
            Wrapped CEKs and IVs travel in response headers, not the body — keeping the ciphertext
            stream opaque. Decryption is a sub-50ms operation per image in a modern browser; the grid
            shows skeleton placeholders while crypto runs.
        </p>
        <p style="margin-top:12px">
            <strong>Session keys:</strong> the user's private key is restored from
            <code class="mono">sessionStorage</code> (PKCS8 bytes, cleared on tab close); workspace DEKs
            live in an in-memory map, re-unwrapped per page load via the private key.
        </p>
    </section>

    {{-- 6. Access codes --}}
    <section id="access-codes">
        <h2>6. Access Codes</h2>
        <p>
            Access codes let the owner share content with people who have no account — without ever
            transmitting the code to the server in a form it could use.
        </p>
        <div class="arch-diagram">
Owner browser                              Server
─────────────                              ──────
1. Generate code (12 chars, Base32)
2. Argon2id-ready copy ─────────────────► stores HASH only
3. PBKDF2(code, salt, 250k)
       → code key
4. Wrap workspace DEK with code key
       → wrapped_dek ──────────────────► stored on code row

Invitee browser                            Server
────────────────                           ──────
1. Enter code at /enter
2. PBKDF2(code, salt) → code key
3. POST code ──────────────────────────► verify Argon2id hash
4.   ← wrapped_dek (if valid)            create scoped session
5. Unwrap DEK locally with code key      (signed encrypted cookie)
6. Browse within scope until expiry
        </div>
        <p>
            The server verifies the code via its Argon2id hash but <strong>cannot derive the code key</strong> —
            it can hand out the wrapped DEK without ever being able to unwrap it. Scopes
            (workspace / collection / gallery) and a permission bitmask (view=1, upload=2, comment=4)
            are enforced server-side; expiry, max-uses, and revocation are checked on every request.
        </p>
    </section>

    {{-- 7. Re-key --}}
    <section id="rekey">
        <h2>7. Re-key &amp; Hard Revocation</h2>
        <p>
            Soft revocation (deleting a code or member) stops <em>new</em> access. But a revoked party may
            already hold a copy of the DEK. <strong>Hard revocation</strong> rotates the DEK entirely:
        </p>
        <div class="arch-diagram">
1. Owner clicks Re-key → server acquires a lock (409 if already running)
2. Server returns all wrapped CEKs, encrypted names/descriptions,
   and member public keys
3. Browser: generate new DEK
      for each media:  unwrap CEK w/ old DEK → re-wrap w/ new DEK
      for each field:  decrypt w/ old DEK → re-encrypt w/ new DEK
      for each member: seal new DEK to their public key
4. POST all wraps to /rekey/{job}/complete
5. Server applies everything in ONE transaction:
      - new wrapped_dek_for_owner, dek_version+1, rekeyed_at
      - new member wraps, media wraps, name/description re-encryptions
      - ALL access codes → revoked_at = now()
6. Lock released; audit log records dek_version + codes_revoked
        </div>
        <p>
            Atomicity matters: until <code class="mono">complete</code> commits, the old DEK remains valid —
            a crashed browser mid-rekey leaves the workspace untouched and retryable. A cache lock plus a
            stale-job sweeper (<code class="mono">rekey:cleanup</code>, 30-minute TTL) prevents concurrent or
            abandoned re-keys from wedging the workspace.
        </p>
        <div class="arch-note">
            <strong>Why access codes can't survive re-key:</strong> the server only holds each code's
            Argon2id hash and the code-key-wrapped DEK. To re-wrap for a code, you'd need the raw code —
            which no one has but the holder. Re-key therefore invalidates all codes; the owner re-issues them.
        </div>
    </section>

    {{-- 8. Data model --}}
    <section id="data-model">
        <h2>8. Data Model</h2>
        <div class="arch-table-wrap"><table class="arch-table">
            <tr><th>Table</th><th>Plaintext columns</th><th>Encrypted columns</th></tr>
            <tr>
                <td><code class="mono">users</code></td>
                <td>id (uuid pk), email, role, public_key, keypair_salt, recovery_code_salt, recovery_code_hash, timestamps</td>
                <td>encrypted_private_key, encrypted_private_key_recovery (sealed, not decryptable by server)</td>
            </tr>
            <tr>
                <td><code class="mono">workspaces</code></td>
                <td>id (uuid pk), owner_id, dek_version, rekeyed_at, timestamps</td>
                <td>encrypted_name, name_iv, wrapped_dek_for_owner</td>
            </tr>
            <tr>
                <td><code class="mono">workspace_members</code></td>
                <td>id (uuid pk), workspace_id, user_id, role</td>
                <td>wrapped_dek (per-member seal)</td>
            </tr>
            <tr>
                <td><code class="mono">collections</code></td>
                <td>id (uuid pk), workspace_id, timestamps</td>
                <td>encrypted_name, encrypted_description (+ IVs)</td>
            </tr>
            <tr>
                <td><code class="mono">galleries</code></td>
                <td>id (uuid pk), collection_id, type (private/shared/joint), timestamps</td>
                <td>encrypted_name, encrypted_description (+ IVs)</td>
            </tr>
            <tr>
                <td><code class="mono">gallery_members</code></td>
                <td>id (uuid pk), gallery_id, user_id, role (editor/viewer)</td>
                <td>&mdash;</td>
            </tr>
            <tr>
                <td><code class="mono">media</code></td>
                <td>id (uuid pk), gallery_id, uploaded_by, mime_type, size, blob_path, thumb_path, timestamps</td>
                <td>cek_wrapped, iv, thumb_iv, encrypted_title, encrypted_caption (+ IVs)</td>
            </tr>
            <tr>
                <td><code class="mono">workspace_access_codes</code></td>
                <td>id (uuid pk), workspace_id, scope, permissions, expires_at, max_uses, use_count, revoked_at, code_hash (Argon2id)</td>
                <td>wrapped_dek (sealed to code-derived key), label</td>
            </tr>
            <tr>
                <td><code class="mono">rekey_jobs</code></td>
                <td>id (uuid pk), workspace_id, initiated_by, status, totals, timestamps</td>
                <td>member/media/collection/gallery wrap payloads (all ciphertext)</td>
            </tr>
            <tr>
                <td><code class="mono">audit_logs</code></td>
                <td>id (uuid pk), actor, subject, action, context (JSON), ip_address, created_at</td>
                <td>&mdash; (metadata only by design)</td>
            </tr>
        </table></div>
        <p style="margin-top:12px">
            Every application table uses a UUID primary key — no sequential IDs to enumerate,
            and identifiers are safe to expose in URLs. (Laravel's internal <code class="mono">jobs</code>
            and <code class="mono">cache</code> tables are the only exceptions; they hold no user data.)
        </p>
    </section>

    {{-- 9. Authorization --}}
    <section id="authorization">
        <h2>9. Authorization</h2>
        <p>
            Authorization is content-aware: policy checks resolve identity through a fixed precedence chain,
            implemented in <code class="mono">AuthorizationResolver</code>:
        </p>
        <div class="arch-diagram">
Request ──► 1. Super Admin?  ──► metadata-level access only (never content)
         ──► 2. Workspace owner? ──► full access
         ──► 3. Workspace member? ──► role-gated (editor/viewer)
         ──► 4. Gallery member? ──► gallery role
         ──► 5. Access-code session? ──► scope + permission bitmask check
         ──► 6. Deny (403)
        </div>
        <ul>
            <li><strong>Super Admin is explicitly denied content access</strong> — the policy returns <code class="mono">false</code>, not just absence of grants. Platform administration must never imply decryption capability; the admin holds no DEK and the code enforces it.</li>
            <li>Blob and thumbnail routes authorize <em>before</em> streaming — ciphertext is still access-controlled even though it's opaque.</li>
            <li>Access-code sessions are signed, encrypted, scope-pinned cookies — validated on every request against expiry, revocation, use-count, and permission bits.</li>
            <li>Uploads during a re-key return <code class="mono">423 Locked</code> — they'd be encrypted with the soon-stale DEK.</li>
        </ul>
    </section>

    {{-- 10. Threat model --}}
    <section id="threat-model">
        <h2>10. Threat Model</h2>
        <div class="arch-table-wrap"><table class="arch-table">
            <tr><th>Scenario</th><th>What's exposed</th><th>What's safe</th></tr>
            <tr>
                <td>Database dump</td>
                <td>Emails, roles, structure, code hashes, sizes, timestamps, ciphertext blobs</td>
                <td>All content (names, titles, captions, images), all DEKs/CEKs, all private keys, raw codes</td>
            </tr>
            <tr>
                <td>Server compromise (live)</td>
                <td>Metadata + ciphertext streams as they're served</td>
                <td>Decrypted content — keys never transit the server</td>
            </tr>
            <tr>
                <td>Malicious Super Admin</td>
                <td>Full platform metadata</td>
                <td>All user content — policy-denied + cryptographically impossible</td>
            </tr>
            <tr>
                <td>Stolen access code</td>
                <td>Content within code's scope until expiry/revocation</td>
                <td>Everything outside scope; revocable; killed entirely by re-key</td>
            </tr>
            <tr>
                <td>Revoked member with old DEK</td>
                <td>Nothing after re-key — stale DEK can't unwrap re-wrapped CEKs</td>
                <td>&mdash;</td>
            </tr>
            <tr>
                <td>Lost password</td>
                <td>&mdash;</td>
                <td>Recovery code re-seals the private key under a new password</td>
            </tr>
            <tr>
                <td>Lost password AND recovery code</td>
                <td>&mdash;</td>
                <td>Nothing can recover the data — by design (no backdoor exists)</td>
            </tr>
        </table></div>
    </section>

    {{-- 11. Tradeoffs --}}
    <section id="tradeoffs">
        <h2>11. Design Tradeoffs</h2>
        <ul>
            <li><strong>No server-side search.</strong> Content is ciphertext; search/filter happens client-side on decrypted metadata. Accepted for privacy; viable because galleries are browsed, not searched.</li>
            <li><strong>Re-key invalidates access codes.</strong> The owner can't re-seal the DEK for codes it can't read. Regenerating codes is the honest cost of real revocation.</li>
            <li><strong>Password reset doesn't recover content.</strong> Resetting a password without the recovery code means the sealed private key is undecryptable. This is a feature — anything less would be a backdoor.</li>
            <li><strong>Comments are plaintext (for now).</strong> A documented exception — comments are low-sensitivity metadata and encrypting them adds per-viewer key-management complexity. Flagged for a future module.</li>
            <li><strong>Key persistence is sessionStorage, not IndexedDB.</strong> Keys clear on tab close — a deliberate trade of convenience for not persisting key material on disk.</li>
            <li><strong>Browser crypto, not libsodium/native.</strong> WebCrypto is universal and audit-friendly; AES-GCM + RSA-OAEP + PBKDF2 are all FIPS-recognized primitives with native browser support.</li>
            <li><strong>Synchronous jobs (QUEUE_CONNECTION=sync).</strong> cPanel-compatible; re-key's heavy work is in the browser anyway — the server's part is a fast transactional update.</li>
        </ul>
        <div class="arch-note">
            Every tradeoff above is recorded in <code class="mono">docs/DECISIONS.md</code> and
            <code class="mono">docs/AUDIT_LOG.md</code> alongside the six build modules — the reasoning
            is part of the codebase, not just the outcome.
        </div>
    </section>

</div>
@endsection
