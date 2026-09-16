# Obscura

A private, end-to-end encrypted gallery platform built on Laravel 11. Workspace owners organize images into collections and galleries, and selectively share access with others using time-boxed, revocable access codes. All media and content names are encrypted client-side (WebCrypto) — the server is blind to content.

## Features

- **End-to-end encryption** — images are encrypted in the browser before upload; the server stores only ciphertext. Workspace, collection, and gallery names, plus media titles/captions, are also encrypted with the workspace DEK.
- **Hierarchical organization** — Workspace → Collection → Gallery → Media.
- **Gallery types** — `private` (owner only), `shared` (view access), `joint` (co-editors can upload/edit).
- **Access codes** — scoped (workspace / collection / gallery), time-boxed (minutes/hours), revocable, no account required for invitees.
- **Recovery codes** — printed at registration; password loss does not mean data loss.
- **UUID primary keys** — no sequential ID enumeration.
- **Super Admin** — platform management (metadata only; cannot view encrypted content by design).
- **Mobile-responsive** — sleek, simple, content-first UI with grid/masonry/list gallery views.

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 11, PHP 8.2+ |
| Frontend | Blade + Livewire + Alpine.js |
| Styling | Tailwind CSS |
| Crypto | WebCrypto API (browser-side) |
| Database | SQLite (local dev), MySQL (cPanel production) |
| Deployment | cPanel shared hosting |

## Documentation

All design and implementation docs live in `../docs/`:

| Document | Description |
|---|---|
| [PRD.md](../docs/PRD.md) | Product requirements — goals, roles, encryption model, data model |
| [WORKFLOWS.md](../docs/WORKFLOWS.md) | 15 end-to-end sequence diagrams (registration, sharing, upload, re-key, recovery) |
| [CAPABILITIES.md](../docs/CAPABILITIES.md) | Capabilities matrix — exactly what each role can and cannot do |
| [DECISIONS.md](../docs/DECISIONS.md) | 6 resolved design decisions (frontend, recovery, encryption, UUIDs) |
| [UI-UX.md](../docs/UI-UX.md) | Visual language, gallery views, responsive layout, component library |
| [modules/README.md](../docs/modules/README.md) | Implementation module index (6 build phases) |

### Implementation modules

| # | Module | Phase |
|---|---|---|
| 1 | [Auth & User Keypair](../docs/modules/01-auth-keypair.md) | Foundation |
| 2 | [Workspaces & DEK](../docs/modules/02-workspaces-dek.md) | Core |
| 3 | [Access Codes](../docs/modules/03-access-codes.md) | Sharing |
| 4 | [Collections & Galleries](../docs/modules/04-collections-galleries.md) | Organization |
| 5 | [Media Upload & Decrypt](../docs/modules/05-media-encrypt.md) | Content |
| 6 | [Re-key on Revoke](../docs/modules/06-rekey-revoke.md) | Security |

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
    └─ unwraps workspace DEK
```

The server never sees: plaintext images, DEKs, CEKs, private keys, raw access codes, or plaintext names/titles.

## Local Development

### Prerequisites

- PHP 8.2+
- Composer
- Node.js 18+ (for Vite asset building)
- SQLite (default local DB)

### Setup

```bash
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

Local development uses SQLite (`database/database.sqlite`). The production deployment on cPanel uses MySQL — see `.env.bak` for the production DB config. Switch back to MySQL before deploying.

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
- **Revocation:** Soft revoke (blocks new access immediately) and hard revoke / re-key (new DEK, re-wrap all CEKs + encrypted names, invalidates all access codes).
- **Recovery:** Printed recovery code seals a second copy of the private key. Password loss is recoverable without data loss.
- **Super Admin:** Cannot view encrypted content — no DEK is ever sealed for them. Metadata management only.

See [DECISIONS.md](../docs/DECISIONS.md) for the full security rationale.

## License

MIT. See the [Laravel license](https://opensource.org/licenses/MIT).
