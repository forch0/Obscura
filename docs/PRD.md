# Obscura — PRD

**Status:** Draft for review
**Last updated:** 2026-09-16
**Owner:** Workspace Owner (you)

---

## 1. Overview

**Obscura** is a private, encrypted gallery platform where a **Workspace Owner** organizes images into **Collections** and **Galleries**, and selectively shares access with others using **time-boxed, revocable access codes** (minutes/hours). Content is end-to-end encrypted so the server (cPanel shared host) is blind to image bytes; only metadata is stored in plaintext. The name draws from *camera obscura* (the photographic precursor) and *obscured* (encrypted, hidden from the server).

### Goals
- True content privacy on an untrusted shared host (cPanel).
- Frictionless sharing via access codes — no account required to view.
- Granular, revocable, time-limited access.
- Hierarchical organization: Workspace → Collection → Gallery → Media.
- Collaborative galleries ("joint") where multiple members can upload/edit.
- Recoverable access via printed recovery codes (password loss does not mean data loss).

### Non-goals (v1)
- Public galleries (everything is private by default).
- Mobile native apps (web only; responsive).
- Real-time co-editing / live cursors (async collaboration only).
- Video/streaming media (images only in v1).

---

## 2. Roles & Identities

| Role | Identity type | How they authenticate | Scope of power |
|---|---|---|---|
| **Super Admin** | Account user | Email + password (Laravel session) | Platform-wide; bypasses all policies |
| **Workspace Owner** | Account user | Email + password | Full CRUD on their workspace + all descendants; manages members & codes |
| **Workspace Member** | Account user | Email + password | Per workspace role + per-gallery grants |
| **Access-code holder** | No account required (upgradeable) | Possession of valid code | Per code scope + permissions + expiry |

### Identity model: "code first, upgrade later"
- A shared user enters a code → gets a scoped session token, no account needed.
- They may later register → their browser re-wraps the workspace DEK to their new account keypair → access becomes account-based and independent of the code.
- Owner can then revoke the code without kicking them out.

---

## 3. Trust & Encryption Model

**Hybrid End-to-End Encryption (E2EE)** — chosen because cPanel is a less-trusted, shared environment and E2EE requires no special server-side crypto (all crypto in browser via WebCrypto).

- **Media bytes**: encrypted client-side; server stores only ciphertext blobs.
- **Metadata** (titles, timestamps, uploader, sizes): plaintext in DB, enabling listing/sort/search without decryption.
- **Keys**: never leave the client unencrypted. Server stores only wrapped (sealed) keys.

In addition to media bytes, all human-readable names (workspace, collection, gallery names, and media titles/captions) are also encrypted with the workspace DEK. The server is blind to all content names. See DECISIONS.md D5.

### Key hierarchy (envelope encryption)
```
Workspace DEK  (AES-GCM 256, one per workspace)
   ├── wraps each file's CEK (Content Encryption Key, unique per file)
   │      └── file blob = AES-GCM(CEK, IV, plaintext)
   └── DEK is sealed for each authorized principal:
         ├── account user  → sealed with user's public key (RSA-OAEP / ECDH)
         ├── access code   → sealed with key derived from code (Argon2id + salt)
         └── revocation re-key: new DEK, re-wrap all CEKs (file blobs untouched)
```

### Revocation semantics (important)
- **Soft revoke** (`revoked_at` set): blocks *new* access immediately. Someone who already unwrapped the DEK still has it in their browser.
- **Hard revoke (re-key)**: new workspace DEK, re-wrap all CEKs, re-seal DEK for all still-valid principals. Blocks everyone except those re-sealed. File blobs are NOT re-encrypted (only small CEKs are).
- v1 ships soft revoke; re-key is a manual owner action (one click) for true revocation.

---

## 4. Access Codes

An access code is a **time-boxed, revocable capability token**, not a user.

| Field | Purpose |
|---|---|
| `code_hash` | Argon2id hash of the raw code (never store raw code) |
| `code_salt` | Per-code salt for key derivation |
| `wrapped_dek` | Workspace DEK sealed with key derived from `code + salt` |
| `scope` | `workspace` \| `collection` \| `gallery` |
| `scope_id` | ID of the scoped resource (nullable if workspace-wide) |
| `permissions` | bitmask: `view` \| `upload` \| `comment` |
| `expires_at` | Duration in minutes/hours from creation |
| `revoked_at` | NULL until revoked |
| `max_uses` / `use_count` | Optional one-time vs reusable |
| `created_by` | Owner who generated it |

**Code format**: 12-char base32, grouped `K7QX-9P2M-4F8R` for readability.

---

## 5. Data Model

```
users
  id (UUID), email, password, name, is_super_admin,
  public_key, encrypted_private_key   (priv key sealed with password-derived key)
  encrypted_private_key_recovery       (priv key sealed with recovery-code-derived key)
  recovery_code_hash, recovery_code_salt, recovery_code_used_at

workspaces
  id (UUID), owner_id → users (UUID),
  encrypted_name, name_iv,
  wrapped_dek_for_owner                (DEK sealed with owner's public key)

workspace_members                      (account-based long-term access)
  id (UUID), workspace_id (UUID), user_id (UUID), role (editor|viewer), joined_at

workspace_access_codes                 (temporary share mechanism)
  (fields from §4, all IDs are UUID)

collections
  id (UUID), workspace_id (UUID),
  encrypted_name, name_iv,
  encrypted_description, description_iv, ...

galleries
  id (UUID), collection_id (UUID),
  encrypted_name, name_iv,
  encrypted_description, description_iv,
  type (private|shared|joint), ...

gallery_members                         (shared/joint gallery grants)
  id (UUID), gallery_id (UUID), user_id (UUID), role (editor|viewer)

media
  id (UUID), gallery_id (UUID), uploaded_by (UUID),
  encrypted_blob_path, cek_wrapped, iv,   (ciphertext + sealed CEK)
  mime_type, size,
  encrypted_title, title_iv,
  encrypted_caption, caption_iv,
  created_at
```

### Gallery types
- **private** — owner only.
- **shared** — owner + granted members (view).
- **joint** — owner + co-editors (both can upload/edit/delete).

---

## 6. Authorization (Policy Layers)

Laravel Policies, evaluated first-match-wins:

1. `SuperAdmin` → allow.
2. `WorkspaceOwner` → full CRUD on workspace + all descendants.
3. `WorkspaceMember` → per workspace role + per-gallery grants.
4. `AccessCode session` → per code `scope` + `permissions` + `expires_at` + `revoked_at`.

---

## 7. Frontend Stack

**Blade + Livewire + Alpine.js**, with WebCrypto via small inline JS modules.

- Lightest stack; ships with Laravel; minimal build tooling.
- Ideal for cPanel (no SPA routing/CORS complexity).
- Livewire handles server-driven UI; Alpine + WebCrypto handle client-side crypto.

**Confirmed:** See DECISIONS.md D1.

*Note: An SPA feel via Inertia + Vue was considered but rejected; it requires more build setup and is harder on cPanel.*

---

## 8. cPanel Deployment Constraints (baked into design)

- `QUEUE_CONNECTION=sync` (no persistent queue workers; re-key runs inline or via cron).
- Build Vite assets locally, commit `public/build/`, deploy built assets (no Node on server).
- Encrypted blobs in `storage/app/private/` (outside web root); served via streamed PHP response after authz.
- DB: SQLite locally, MySQL on host (schema portable).
- HTTPS via cPanel AutoSSL (required for WebCrypto).

---

## 9. Build Order

1. **Auth + user keypair + super admin**
2. **Workspaces + DEK** (create, seal for owner)
3. **Access codes** (generate/revoke/scoped, crypto flow)
4. **Collections + galleries** (CRUD, shared/joint, gallery_members)
5. **Media upload/decrypt** (client-side encrypt, upload, stream+decrypt on view)
6. **Re-key on revoke** (true revocation completeness)

---

## 10. Open Questions for You

All decisions have been resolved and are documented in DECISIONS.md. Summary of the 6 resolved decisions:

- **D1**: Frontend = Blade + Livewire + Alpine
- **D2**: Recovery = printed recovery code
- **D3**: Comments = plaintext
- **D4**: Re-key invalidates all access codes = accepted
- **D5**: All names/titles encrypted with workspace DEK
- **D6**: UUIDs for all primary keys
