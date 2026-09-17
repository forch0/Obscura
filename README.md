# Obscura

A zero-trust content storage platform built on Laravel. Store anything — photos, videos, documents — organized into workspaces, collections, and galleries, and share it with scoped, time-boxed, revocable access codes — no account needed for viewers.

Everything is encrypted in the browser before it uploads. The server stores only ciphertext it cannot read — even the platform admin is locked out.

## Features

- **End-to-end encryption** — files are encrypted in your browser before upload. Names, titles, and captions too. The server never sees plaintext.
- **Any content type** — photos and videos are first-class (encrypted thumbnails, lightbox viewing, inline playback); documents and PDFs get the same treatment.
- **Simple organization** — Workspace → Collection → Gallery → Media.
- **Gallery types** — `private` (you only), `shared` (members view), `joint` (members can upload too).
- **Workspace members** — add people you know by email; they get their own sealed copy of the workspace key. Editor or viewer roles.
- **Access codes** — share without accounts. Scoped (workspace / collection / gallery), time-boxed, revocable, optionally emailed. Recipients get a guest viewer.
- **Hard revocation** — re-key rotates the workspace key, re-locks every file, and kills every outstanding access code in one atomic step.
- **Recovery code** — shown once at signup; losing your password doesn't mean losing your content.
- **Rate limiting & audit logging** — sensitive endpoints throttled; sensitive actions recorded.
- **UUIDs everywhere** — no guessable sequential IDs in URLs.
- **Mobile-responsive** — monochrome, content-first UI.

## How it works (the short version)

```
Your browser                                  Server
──────────────                                ──────────────────
 Your keypair                                  Stores only:
   └─ private key sealed                        • encrypted file blobs
      with your password                        • sealed (wrapped) keys
                                                • encrypted names/titles
 Workspace key (AES-256)                          • file sizes, types,
   └─ locks each file's own key                     timestamps, IDs
   └─ sealed separately for:
        • each member's public key             Can never see:
        • each access code                      • your content
                                                • your keys
                                                • names or captions
```

Full detail: [docs/USER_GUIDE.md](docs/USER_GUIDE.md) (plain-language, every flow) and [docs/architecture](docs/modules/README.md) module specs.

## Using Obscura

The complete walkthrough — registration, key generation, recovery codes, workspaces, members, collections, galleries, uploads, sharing codes, guest access, re-key, and a worked "Family Vault" example — lives in **[docs/USER_GUIDE.md](docs/USER_GUIDE.md)**.

Quick version:

1. Register → generate your keypair → **save the recovery code**.
2. Create a workspace → collections → galleries → upload.
3. Share by adding a member (email) or generating an access code.
4. Invitees open `/enter`, type the code, browse — no account needed.

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 11, PHP 8.2+ |
| Frontend | Blade + vanilla JS (dynamic crypto imports) |
| Styling | Custom CSS design system (CSS custom properties, monochrome) |
| Crypto | WebCrypto API — AES-256-GCM, RSA-OAEP 2048, PBKDF2, Argon2id |
| Database | SQLite (local dev), MySQL (cPanel production) |
| Deployment | cPanel shared hosting — no Node.js on the server |

## Documentation

| Document | Description |
|---|---|
| [USER_GUIDE.md](docs/USER_GUIDE.md) | **Start here** — every user flow in plain language + worked example |
| [CPANEL_DEPLOYMENT.md](docs/CPANEL_DEPLOYMENT.md) | Full shared-hosting deploy runbook + troubleshooting |
| [PRD.md](docs/PRD.md) | Product requirements — goals, roles, encryption model |
| [WORKFLOWS.md](docs/WORKFLOWS.md) | End-to-end sequence diagrams |
| [CAPABILITIES.md](docs/CAPABILITIES.md) | What each role can and cannot do |
| [DECISIONS.md](docs/DECISIONS.md) | Resolved design decisions and why |
| [UI-UX.md](docs/UI-UX.md) | Visual language and component library |
| [AUDIT_LOG.md](docs/AUDIT_LOG.md) | File-by-file build log with verification status |
| [modules/README.md](docs/modules/README.md) | Implementation module index (6 build phases) |

## Local Development

The Laravel app lives in `src/`.

**Prerequisites:** PHP 8.2+, Composer, Node.js 18+ (for building assets), SQLite.

```bash
cd src

composer install
npm install

cp .env.example .env
php artisan key:generate

# .env — use SQLite locally:
#   DB_CONNECTION=sqlite
#   DB_DATABASE=database/database.sqlite

php artisan migrate
npm run dev
php artisan serve
```

### Promote a Super Admin

```bash
php artisan admin:promote user@example.com
```

## Deployment (cPanel / shared hosting)

Short version: build assets locally (`npm run build`), upload the app, point the
document root at `public/`, import the SQL schema, run the artisan setup commands.

**The full runbook — upload rules, `.env`, database import, update workflow, and
a troubleshooting table — is in [docs/CPANEL_DEPLOYMENT.md](docs/CPANEL_DEPLOYMENT.md).**

Key facts:

- No Node.js on the server — build `public/build/` locally and upload it whole.
- HTTPS required — browser crypto only runs in secure contexts.
- Encrypted blobs live in `storage/app/private/` (outside web root), streamed via authorization-gated routes.

## Security Model

- **Hybrid E2EE:** file bytes and all human-readable names are encrypted client-side with AES-GCM. The workspace key is sealed separately for each member (RSA-OAEP) and each access code (a key derived from the code itself).
- **Key hierarchy:** workspace key → wraps each file's own key → encrypts the file. Your private key is sealed with your password, plus a second copy sealed with your recovery code.
- **Guests:** verified codes get encrypted, scope-pinned session cookies — re-checked against revocation, expiry, and use-count on every request, including file streams.
- **Revocation:** soft (access denied instantly) and hard (re-key: new workspace key, everything re-locked, all codes invalidated).
- **Recovery:** losing your password is recoverable with the printed recovery code. Losing both is not — that's the point.
- **Super Admin:** manages metadata only. No workspace key is ever sealed for them — content stays unreadable by design.

See [DECISIONS.md](docs/DECISIONS.md) for the full rationale.

## License

MIT. See the [Laravel license](https://opensource.org/licenses/MIT).
