# Obscura — Design Decisions

**Date resolved:** 2026-09-16
**Supersedes:** Open items in `PRD.md §10` and per-module "Open items" sections.

---

## Resolved decisions

### D1. Frontend stack → Blade + Livewire + Alpine.js

**Decision:** Blade + Livewire + Alpine.js with WebCrypto via small inline JS modules.

**Rationale:** Lightest stack; ships with Laravel; minimal build tooling; ideal for cPanel (no SPA routing/CORS complexity). Livewire handles server-driven UI; Alpine + WebCrypto handle client-side crypto.

**Affected modules:** All (1–6)

---

### D2. Account recovery → Printed recovery code

**Decision:** At registration, generate a one-time recovery code. The private key is sealed twice: once with the password-derived key, once with the recovery-code-derived key. The recovery code is shown once and the user is instructed to print it. If the password is forgotten, the user enters the recovery code to unseal the private key, then sets a new password and re-seals.

**Mechanism:**
- `users.encrypted_private_key` — sealed with PBKDF2(password) key (existing)
- `users.encrypted_private_key_recovery` — sealed with PBKDF2(recovery_code) key (new)
- `users.recovery_code_hash` — Argon2id hash for server-side verification (new)
- `users.recovery_code_salt` — for both hash + key derivation (new)
- `users.recovery_code_used_at` — nullable; null = unused/available (new)

**Recovery flow:**
1. User enters email + recovery code.
2. Server verifies `recovery_code_hash` matches.
3. Server returns `encrypted_private_key_recovery` + `recovery_code_salt` to browser.
4. Browser derives key from recovery code + salt, unseals private key.
5. User sets a new password.
6. Browser re-seals private key with new password-derived key.
7. Server updates `encrypted_private_key` + password hash.
8. Recovery code remains valid (reusable) unless explicitly invalidated.

**Affected modules:** Module 1 (primary), Module 6 (re-key must also re-seal recovery copy)

---

### D3. Comments → Plaintext

**Decision:** Comments are stored in plaintext. Not encrypted.

**Rationale:** Comments are conversational metadata; encrypting them adds complexity with minimal privacy gain (the media itself is the sensitive content). v2 can revisit if needed.

**Affected modules:** Module 3 (access code `comment` permission), Module 5 (if comments added)

---

### D4. Re-key invalidates all access codes → Acceptable

**Decision:** Hard revocation (re-key) invalidates all outstanding access codes. The owner must regenerate any codes they want to keep. This is an inherent E2EE tradeoff (the owner doesn't know raw codes to re-seal them).

**Affected modules:** Module 6

---

### D5. Encrypted names → All names/titles encrypted with workspace DEK

**Decision:** The following fields are encrypted client-side with the workspace DEK (AES-GCM) and stored as ciphertext + IV:

| Table | Encrypted fields | Plaintext fields |
|---|---|---|
| `workspaces` | `name` → `encrypted_name` + `name_iv` | `owner_id`, `wrapped_dek_for_owner`, `dek_version`, timestamps |
| `collections` | `name` → `encrypted_name` + `name_iv`, `description` → `encrypted_description` + `description_iv` | `workspace_id`, timestamps |
| `galleries` | `name` → `encrypted_name` + `name_iv`, `description` → `encrypted_description` + `description_iv` | `collection_id`, `type`, timestamps |
| `media` | `title` → `encrypted_title` + `title_iv`, `caption` → `encrypted_caption` + `caption_iv` | `gallery_id`, `uploaded_by`, `blob_path`, `cek_wrapped`, `iv`, `mime_type`, `size`, timestamps |

**Consequences:**
- The server is blind to all human-readable content names. A breach exposes only ciphertext names + structural metadata (IDs, types, timestamps, sizes).
- Super Admin sees only encrypted blobs for names — cannot browse by name.
- Listing views (workspace list, collection list, gallery grid) require the browser to decrypt names/titles before rendering. The DEK must be unsealed first.
- Server-side search/sort by name is not possible (ciphertext is not searchable). v1: client-side search after decryption. v2: searchable encryption if needed.
- Workspace list (landing page after login): browser unseals each workspace's DEK (RSA-OAEP decrypt with private key) then decrypts the name. For a handful of workspaces this is <10ms total.

**What stays plaintext:** `mime_type`, `size`, `type` (gallery type), timestamps, IDs, foreign keys, permission bitmasks, roles, email (for auth), blob paths, IVs, wrapped keys. These are structural/operational metadata, not content.

**Affected modules:** Module 2 (workspace name), Module 4 (collection/gallery names+descriptions), Module 5 (media title+caption)

---

### D6. UUIDs for all primary keys

**Decision:** All tables use UUID (RFC 4122 v4) as primary keys instead of auto-incrementing bigints.

**Implementation:**
- Migrations: `$table->uuid('id')->primary()` instead of `$table->id()`
- Foreign keys: `$table->foreignUuid('workspace_id')->constrained()` instead of `$table->foreignId(...)`
- Models: add `protected $keyType = 'string'; public $incrementing = false;`
- Use `Str::uuid()` to generate IDs, or a model `creating` event / trait

**Trait approach (recommended):**
```php
// app/Traits/UsesUuid.php
trait UsesUuid
{
    protected static function bootUsesUuid(): void
    {
        static::creating(function (Model $model) {
            if (!$model->getKey()) {
                $model->{$model->getKeyName()} = Str::uuid()->toString();
            }
        });
    }
    public function getIncrementing(): bool { return false; }
    public function getKeyType(): string { return 'string'; }
}
```

**Rationale:**
- No ID enumeration (can't guess `/workspaces/1`, `/workspaces/2`)
- Globally unique — safe to sync across environments
- cPanel MySQL: `uuid` column type or `char(36)` with index
- SQLite: stored as `TEXT` (portable)

**Affected modules:** All (1–6) — every migration and model

---

## Summary of schema changes from these decisions

### New columns on `users` (Module 1)
- `encrypted_private_key_recovery` (text, nullable)
- `recovery_code_hash` (string, nullable)
- `recovery_code_salt` (string, nullable)
- `recovery_code_used_at` (timestamp, nullable)

### Renamed columns (encrypted names)
- `workspaces.name` → `workspaces.encrypted_name` + `workspaces.name_iv`
- `collections.name` → `collections.encrypted_name` + `collections.name_iv`
- `collections.description` → `collections.encrypted_description` + `collections.description_iv`
- `galleries.name` → `galleries.encrypted_name` + `galleries.name_iv`
- `galleries.description` → `galleries.encrypted_description` + `galleries.description_iv`
- `media.title` → `media.encrypted_title` + `media.title_iv`
- `media.caption` → `media.encrypted_caption` + `media.caption_iv`

### All primary keys → UUID
- Every `id` column changes from `bigint` to `uuid` (`char(36)` / `TEXT`)
- Every foreign key changes from `foreignId` to `foreignUuid`

---

## Still open (v2 considerations)

| Item | Status |
|---|---|
| Searchable encrypted names (blind index) | Deferred to v2 |
| Encrypted comments | Deferred to v2 |
| Workspace transfer (change owner) | Deferred to v2 |
| Soft-delete workspaces (undo) | Deferred to v2 |
| Chunked/resumable uploads | Deferred to v2 |
| Video media | Deferred to v2 |
