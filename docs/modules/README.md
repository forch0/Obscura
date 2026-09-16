# Obscura — Implementation Modules Index

**Companion to:** `PRD.md`, `WORKFLOWS.md`, `CAPABILITIES.md`, `DECISIONS.md`, `UI-UX.md`
**Location:** `docs/modules/`

Each module is a self-contained implementation guide for one build phase: files to create, schemas, crypto logic, routes, views, tests, and acceptance criteria. Modules are sequential — each builds on the previous.

---

## Module List (build order)

| # | Module | Phase | Depends on | Status |
|---|---|---|---|---|
| 1 | [Auth & User Keypair](./01-auth-keypair.md) | Foundation | — | Not started |
| 2 | [Workspaces & DEK](./02-workspaces-dek.md) | Core | Module 1 | Not started |
| 3 | [Access Codes](./03-access-codes.md) | Sharing | Module 2 | Not started |
| 4 | [Collections & Galleries](./04-collections-galleries.md) | Organization | Module 2 | Not started |
| 5 | [Media Upload & Decrypt](./05-media-encrypt.md) | Content | Modules 2, 4 | Not started |
| 6 | [Re-key on Revoke](./06-rekey-revoke.md) | Security | Modules 2, 3, 5 | Not started |

---

## Conventions (apply to all modules)

### Tech stack
- **Backend:** Laravel 11 (PHP 8.2+)
- **Frontend:** Blade + Livewire + Alpine.js (confirmed — see DECISIONS.md D1)
- **Crypto:** WebCrypto API (browser) for all key generation / encryption / decryption
- **DB:** SQLite (local dev), MySQL (cPanel production) — schema must be portable

### Naming
- Models: singular PascalCase (`Workspace`, `AccessCode`)
- Tables: snake_case plural (`workspaces`, `workspace_access_codes`)
- Migrations: `yyyy_mm_dd_HHMMSS_create_<table>_table.php`
- Routes: kebab-case (`/workspaces/{workspace}/access-codes`)

### File layout (per module)
```
app/
  Models/            Eloquent models
  Http/Controllers/  Controllers (or Livewire components)
  Policies/          Authorization policies
  Services/          Domain services (crypto helpers, etc.)
database/
  migrations/        Schema migrations
  factories/         Test factories
tests/
  Feature/           HTTP-level tests
  Unit/              Service-level tests
resources/
  views/             Blade templates
  js/                Crypto modules (WebCrypto wrappers)
routes/
  web.php            Route definitions
```

### Crypto conventions (all modules)
- **DEK:** AES-GCM 256, one per workspace, generated client-side
- **User keypair:** RSA-OAEP 2048 (for sealing/unsealing DEKs)
- **Private key seal:** AES-GCM with key derived via PBKDF2(password, salt)
- **Recovery key seal:** AES-GCM with key derived via PBKDF2(recovery_code, salt) — see DECISIONS.md D2
- **Access code key:** Argon2id(code, salt) — server stores hash, browser derives key
- **File encryption:** AES-GCM with unique CEK + IV per file
- **Name/title encryption:** AES-GCM with workspace DEK — all human-readable names encrypted (see DECISIONS.md D5)
- **All crypto in the browser** — server only stores ciphertext + wrapped keys

### ID conventions (all modules)
- **All primary keys are UUIDs** (RFC 4122 v4) — see DECISIONS.md D6
- Migrations: `$table->uuid('id')->primary()` instead of `$table->id()`
- Foreign keys: `$table->foreignUuid('...')` instead of `$table->foreignId('...')`
- Models use the `UsesUuid` trait (`app/Traits/UsesUuid.php`)
- No sequential ID enumeration possible

### Testing approach
- Each module ships with **feature tests** (HTTP flow) and **unit tests** (services/crypto).
- Crypto logic tested via a small JS test harness (Vitest or browser-based) where WebCrypto is available.
- Server-side tests use SQLite in-memory (`:memory:`) for speed.

### Acceptance criteria format
Each module ends with a checklist of verifiable outcomes. A module is "done" when all boxes pass.

---

## Cross-cutting concerns (handled incrementally)

| Concern | Introduced in | Finalized in |
|---|---|---|
| User authentication (login/register) | Module 1 | Module 1 |
| Super Admin flag & policy | Module 1 | Module 1 |
| User keypair (WebCrypto) | Module 1 | Module 1 |
| Recovery code (password recovery) | Module 1 | Module 1 |
| UUID primary keys (all tables) | Module 1 | Module 6 |
| Workspace DEK generation | Module 2 | Module 2 |
| Encrypted workspace name | Module 2 | Module 2 |
| Authorization policies (first-match-wins) | Module 2 | Module 4 |
| Access code crypto flow | Module 3 | Module 3 |
| Scoped session tokens | Module 3 | Module 3 |
| Encrypted collection/gallery names | Module 4 | Module 4 |
| File blob storage & streaming | Module 5 | Module 5 |
| Encrypted media titles/captions | Module 5 | Module 5 |
| Re-key / hard revocation | Module 6 | Module 6 |
| Re-encryption of all text fields on re-key | Module 6 | Module 6 |
| Audit logging | Module 2 (basic) | Module 6 (full) |

---

## How to use these modules

1. Read the PRD, WORKFLOWS, and CAPABILITIES docs first.
2. Implement modules **in order** — each assumes the previous is complete.
3. Run the acceptance checklist at the end of each module before moving on.
4. If a module reveals a design gap, update the PRD/WORKFLOWS first, then adjust the module doc.
