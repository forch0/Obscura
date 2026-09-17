# Obscura

A private, end-to-end encrypted gallery workspace built on Laravel. Owners organize photos and videos (with PDF support) into collections and galleries, and selectively share access using scoped, time-boxed, revocable access codes — no account needed for viewers. All media and content names are encrypted client-side (WebCrypto) — the server is blind to content.

## Features

- **End-to-end encryption** — media is encrypted in the browser before upload; the server stores only ciphertext. Workspace, collection, and gallery names, plus media titles/captions, are also encrypted with the workspace DEK.
- **Photo, video & PDF support** — encrypted thumbnails, lightbox viewing, inline video playback, in-browser PDF rendering.
- **Hierarchical organization** — Workspace → Collection → Gallery → Media.
- **Gallery types** — `private` (owner only), `shared` (view access), `joint` (co-editors can upload/edit).
- **Access codes** — scoped (workspace / collection / gallery), time-boxed, revocable, optionally emailed to a recipient. Code-holders browse a dedicated guest viewer — no account required.
- **Hard revocation** — re-key rotates the workspace DEK, re-wraps every file key, and invalidates all access codes in one atomic transaction.
- **Recovery codes** — printed at registration; password loss does not mean data loss.
- **Rate limiting** — named throttles on login, registration, recovery, code entry, uploads, and every sensitive endpoint.
- **Audit logging** — every sensitive action (code use, revocation, re-key, deletion) writes an audit record.
- **UUID primary keys** — no sequential ID enumeration.
- **Super Admin** — platform management (metadata only; cannot view encrypted content by design).
- **Mobile-responsive** — monochrome, content-first UI with a custom component system (dropdowns, dialogs, toasts).

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel, PHP 8.2+ |
| Frontend | Blade + vanilla JS (delegated event handling, dynamic crypto imports) |
| Styling | Custom CSS design system (CSS custom properties, shadcn-inspired monochrome) |
| Crypto | WebCrypto API — AES-256-GCM, RSA-OAEP 2048, PBKDF2, Argon2id |
| Database | SQLite (local dev), MySQL (cPanel production) |
| Deployment | cPanel shared hosting |

## Documentation

All design and implementation docs live in `docs/`:

| Document | Description |
|---|---|
| [PRD.md](docs/PRD.md) | Product requirements — goals, roles, encryption model, data model |
| [WORKFLOWS.md](docs/WORKFLOWS.md) | End-to-end sequence diagrams (registration, sharing, upload, re-key, recovery) |
| [CAPABILITIES.md](docs/CAPABILITIES.md) | Capabilities matrix — exactly what each role can and cannot do |
| [DECISIONS.md](docs/DECISIONS.md) | Resolved design decisions (frontend, recovery, encryption, UUIDs) |
| [UI-UX.md](docs/UI-UX.md) | Visual language, gallery views, responsive layout, component library |
| [AUDIT_LOG.md](docs/AUDIT_LOG.md) | File-by-file build log with verification status and rate-limiting rationale |
| [modules/README.md](docs/modules/README.md) | Implementation module index (6 build phases) |

### Implementation modules

| # | Module | Phase |
|---|---|---|
| 1 | [Auth & User Keypair](docs/modules/01-auth-keypair.md) | Foundation |
| 2 | [Workspaces & DEK](docs/modules/02-workspaces-dek.md) | Core |
| 3 | [Access Codes](docs/modules/03-access-codes.md) | Sharing |
| 4 | [Collections & Galleries](docs/modules/04-collections-galleries.md) | Organization |
| 5 | [Media Upload & Decrypt](docs/modules/05-media-encrypt.md) | Content |
| 6 | [Re-key on Revoke](docs/modules/06-rekey-revoke.md) | Security |

## Architecture at a Glance

```
Browser (WebCrypto)                    Server (Laravel)
─────────────────────                  ─────────────────
  User keypair (RSA-OAEP)               Stores only:
    └─ private key sealed                 • ciphertext blobs
       with password-derived key          • wrapped (sealed) keys
                                          • encrypted names/titles
  Workspace DEK (AES-GCM 256)            • plaintext operational metadata
    └─ wraps each file's CEK               (mime_type, size, timestamps, IDs)
    └─ sealed per principal:
         • user public key
         • access-code-derived key

  Access code (PBKDF2-derived key)       Argon2id hash for verification
    └─ unwraps workspace DEK             → guest /access viewer, scope-checked
```

The server never sees: plaintext media, DEKs, CEKs, private keys, raw access codes, or plaintext names/titles.

## Local Development

The Laravel application lives in `src/`.

### Prerequisites

- PHP 8.2+
- Composer
- Node.js 18+ (for Vite asset building)
- SQLite (default local DB)

### Setup

```bash
cd src

# Install PHP dependencies
composer install

# Install JS dependencies
npm install

# Configure environment
cp .env.example .env
php artisan key:generate

# Set database to SQLite for local dev
# In .env:
#   DB_CONNECTION=sqlite
#   DB_DATABASE=database/database.sqlite

# Run migrations
php artisan migrate

# Build frontend assets
npm run dev

# Start the dev server
php artisan serve
```

### Default database

Local development uses SQLite (`src/database/database.sqlite`). The production deployment on cPanel uses MySQL — switch `DB_CONNECTION` before deploying.

### Promote a Super Admin

```bash
php artisan admin:promote user@example.com
```

## Deployment (cPanel)

1. Set `.env` to MySQL (production DB credentials).
2. Build assets locally: `npm run build` — commit `public/build/`.
3. Upload all files to the server (excluding `node_modules/`).
4. Run `php artisan migrate` on the server.
5. Ensure `storage/app/private/` is writable but not web-accessible.
6. HTTPS is required (WebCrypto needs a secure context) — use cPanel AutoSSL.

### cPanel-specific notes

- `QUEUE_CONNECTION=sync` — no persistent queue workers; re-key runs inline or via cron.
- No Node.js on the server — build assets locally and deploy the built `public/build/` directory.
- Encrypted blobs stored in `storage/app/private/` (outside web root), served via authz-gated PHP streaming.
- Root `.htaccess` rewrites requests into `public/` for shared-host document root compatibility.

## Security Model

- **Encryption:** Hybrid E2EE. Media bytes + all human-readable names are encrypted client-side with AES-GCM. The workspace DEK is sealed per-principal via RSA-OAEP (account users) or PBKDF2-derived keys (access codes).
- **Key hierarchy:** Workspace DEK → wraps per-file CEK → encrypts file blob. DEK is sealed for each authorized principal.
- **Guest access:** Verified codes issue encrypted, scope-pinned session cookies — re-validated against revocation, expiry, and use-count on every request, including media streams.
- **Rate limiting:** Credential endpoints, code entry, uploads, and re-key are all throttled — see `docs/AUDIT_LOG.md` for the limiter table.
- **Revocation:** Soft revoke (blocks new access immediately) and hard revoke / re-key (new DEK, re-wrap all CEKs + encrypted names, invalidates all access codes).
- **Recovery:** Printed recovery code seals a second copy of the private key. Password loss is recoverable without data loss.
- **Super Admin:** Cannot view encrypted content — no DEK is ever sealed for them. Metadata management only.

See [DECISIONS.md](docs/DECISIONS.md) for the full security rationale.

## License

MIT. See the [Laravel license](https://opensource.org/licenses/MIT).
