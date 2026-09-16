# Module 2 — Workspaces & DEK

**Phase:** Core
**Depends on:** Module 1 (Auth & User Keypair)
**Status:** Not started

---

## 1. Objective

Enable a logged-in user to create a Workspace, generate its Data Encryption Key (DEK) in the browser, and seal the DEK to their own public key. This module also introduces the authorization policy framework (first-match-wins) that later modules extend.

## 2. What gets built

- Workspace CRUD (create, list, show, rename, delete)
- DEK generation (browser, AES-GCM 256)
- DEK sealing to owner's public key (RSA-OAEP)
- DEK unsealing on workspace access (using owner's private key)
- `WorkspacePolicy` (owner-only, first policy in the chain)
- Basic audit log (workspace created / renamed / deleted)
- Workspace dashboard view

## 3. Database schema

### Migration: `create_workspaces_table`

```php
Schema::create('workspaces', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('owner_id')->constrained('users')->cascadeOnDelete();
    $table->text('encrypted_name');
    $table->text('name_iv');
    $table->text('wrapped_dek_for_owner')->nullable();   // DEK sealed with owner's public key (base64)
    $table->text('wrapped_dek_iv')->nullable();          // IV for the seal (or prepend to wrapped_dek)
    $table->unsignedInteger('dek_version')->default(1);  // incremented on re-key (Module 6)
    $table->timestamp('rekeyed_at')->nullable();
    $table->timestamps();
    $table->index('owner_id');
});
```

### Migration: `create_audit_logs_table`

```php
Schema::create('audit_logs', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuidMorphs('actor');           // actor_type, actor_id (User or AccessCode)
    $table->uuidMorphs('subject');          // subject_type, subject_id
    $table->string('action');                // created, renamed, deleted, etc.
    $table->json('context')->nullable();
    $table->string('ip_address')->nullable();
    $table->timestamps();
    $table->index(['subject_type', 'subject_id']);
    $table->index(['actor_type', 'actor_id']);
});
```

### Final `workspaces` table

| Column | Type | Notes |
|---|---|---|
| `id` | uuid (PK) | |
| `owner_id` | uuid FK → users | cascade on delete |
| `encrypted_name` | text | workspace name encrypted with DEK (AES-GCM) |
| `name_iv` | text | AES-GCM IV for the name |
| `wrapped_dek_for_owner` | text, nullable | DEK sealed with owner public key |
| `wrapped_dek_iv` | text, nullable | IV for the seal |
| `dek_version` | uint | default 1; bumped on re-key |
| `rekeyed_at` | timestamp, nullable | |
| `created_at` / `updated_at` | timestamps | |

## 4. Files to create

### Backend
```
app/
  Models/
    Workspace.php
    AuditLog.php
  Traits/
    UsesUuid.php                    (UUID primary key trait; used by Workspace and AuditLog models)
  Http/
    Controllers/
      WorkspaceController.php       (index, create, store, show, update, destroy)
    Middleware/
      EnsureUserHasKeypair.php      (from Module 1 — enforce here)
  Policies/
    WorkspacePolicy.php
  Services/
    Audit/
      AuditLogger.php               (write to audit_logs)
  Observers/
    WorkspaceObserver.php           (auto-log create/delete)
database/
  migrations/
    yyyy_mm_dd_HHMMSS_create_workspaces_table.php
    yyyy_mm_dd_HHMMSS_create_audit_logs_table.php
  factories/
    WorkspaceFactory.php
```

### Frontend
```
resources/
  views/
    workspaces/
      index.blade.php               (list own workspaces)
      create.blade.php
      show.blade.php                 (dashboard: name, stats, actions)
      edit.blade.php
  js/
    crypto/
      dek.js                        (generate DEK, seal to public key, unseal)
      workspace-session.js          (hold unwrapped DEK in memory per workspace)
```

### Routes
```php
Route::middleware(['auth', 'keypair'])->group(function () {
    Route::resource('workspaces', WorkspaceController::class);
    Route::post('/api/workspaces/{workspace}/dek', [WorkspaceController::class, 'storeDek']);
});
```

## 5. Crypto logic (browser-side)

### 5.1 Generate DEK and seal for owner

```javascript
// resources/js/crypto/dek.js

async function generateAndSealDek(ownerPublicKeyB64) {
  // 1. Generate AES-GCM 256 DEK
  const dek = await crypto.subtle.generateKey(
    { name: 'AES-GCM', length: 256 },
    true,                          // extractable — we need to wrap it
    ['encrypt', 'decrypt']         // used to wrap CEKs (Module 5)
  );

  // 2. Import owner's public key
  const pubKey = await importPublicKey(ownerPublicKeyB64);

  // 3. Seal DEK with owner's public key (RSA-OAEP)
  const rawDek = await crypto.subtle.exportKey('raw', dek);
  const iv = crypto.getRandomValues(new Uint8Array(12));
  const sealed = await crypto.subtle.encrypt(
    { name: 'RSA-OAEP', iv },      // RSA-OAEP doesn't use IV; use label or just raw RSA-OAEP
    pubKey,
    rawDek
  );
  // Note: RSA-OAEP uses no IV. The 'iv' above is illustrative; for RSA-OAEP use:
  //   crypto.subtle.encrypt({ name: 'RSA-OAEP' }, pubKey, rawDek)

  return {
    wrappedDek: base64(sealed),
    dekHandle: dek,                 // keep in memory
  };
}
```

### 5.2 Unseal DEK with owner's private key

```javascript
async function unsealDek(sealedDekB64, ownerPrivateKeyHandle) {
  const sealed = fromBase64(sealedDekB64);
  const rawDek = await crypto.subtle.decrypt(
    { name: 'RSA-OAEP' },
    ownerPrivateKeyHandle,
    sealed
  );
  return crypto.subtle.importKey(
    'raw', rawDek,
    { name: 'AES-GCM', length: 256 },
    false,                          // not extractable
    ['encrypt', 'decrypt']          // wrap/unwrap CEKs (Module 5)
  );
}
```

### 5.3 Encrypt workspace name with DEK

```javascript
async function encryptName(name, dekHandle) {
  const iv = crypto.getRandomValues(new Uint8Array(12));
  const enc = new TextEncoder();
  const ciphertext = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, dekHandle, enc.encode(name));
  return { encryptedName: base64(concat(iv, new Uint8Array(ciphertext))), nameIv: base64(iv) };
}

async function decryptName(encryptedNameB64, dekHandle) {
  const combined = fromBase64(encryptedNameB64);
  const iv = combined.slice(0, 12);
  const ciphertext = combined.slice(12);
  const plaintext = await crypto.subtle.decrypt({ name: 'AES-GCM', iv }, dekHandle, ciphertext);
  return new TextDecoder().decode(plaintext);
}
```

## 6. Key flows

### 6.1 Create workspace
1. User submits workspace name.
2. Browser fetches owner's public key (already in session from Module 1).
3. Browser runs `generateAndSealDek(publicKey)` to generate the DEK.
4. Browser encrypts the workspace name with the DEK via `encryptName(name, dekHandle)`, producing `encrypted_name` + `name_iv`.
5. Browser POSTs `{ encrypted_name, name_iv, wrapped_dek }` to create the workspace.
6. Server creates `workspaces` row with `encrypted_name`, `name_iv`, and `wrapped_dek_for_owner`.
7. Browser keeps `dekHandle` in memory (workspace session).

### 6.2 Open existing workspace
1. User clicks a workspace in the list.
2. Server returns workspace row incl. `wrapped_dek_for_owner` and `encrypted_name`.
3. Browser runs `unsealDek(wrappedDek, privateKeyHandle)` to recover the DEK.
4. Browser decrypts the workspace name via `decryptName(encrypted_name, dekHandle)`.
5. Browser stores `dekHandle` in the workspace session (JS memory, keyed by workspace_id).
6. Dashboard renders with the decrypted workspace name.

### 6.3 Delete workspace
1. Owner confirms deletion.
2. `WorkspacePolicy::delete()` checks `user->id === workspace->owner_id` (or Super Admin).
3. Server cascades: deletes workspace + all descendants (collections, galleries, media, members, codes) via FK cascades or observer cleanup.
4. Encrypted blobs on disk are deleted (scheduled or immediate).
5. Audit log entry written.

## 7. Authorization

### WorkspacePolicy (first in the chain)

```php
class WorkspacePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->is_super_admin) {
            // Super Admin can delete/disable but we still return true here;
            // they cannot decrypt (no DEK) — that's enforced client-side.
            return true;
        }
        return null; // fall through to specific checks
    }

    public function view(User $user, Workspace $ws): bool
    {
        return $user->id === $ws->owner_id
            || $ws->members()->where('user_id', $user->id)->exists();
        // Module 3 adds access-code session check
    }

    public function update(User $user, Workspace $ws): bool
    {
        return $user->id === $ws->owner_id;
    }

    public function delete(User $user, Workspace $ws): bool
    {
        return $user->id === $ws->owner_id;
    }
}
```

> Note: `view` is intentionally permissive here — members can see the workspace dashboard. Module 3 extends this with access-code sessions. The `before()` Super Admin hook is the first-match-wins entry point.

## 8. Audit logging

```php
class AuditLogger
{
    public function log(Model $actor, Model $subject, string $action, array $context = []): void
    {
        AuditLog::create([
            'actor_type' => get_class($actor),
            'actor_id'   => $actor->id,
            'subject_type' => get_class($subject),
            'subject_id' => $subject->id,
            'action' => $action,
            'context' => $context,
            'ip_address' => request()->ip(),
        ]);
    }
}
```

Actions logged in this module: `workspace.created`, `workspace.renamed`, `workspace.deleted`, `workspace.dek_stored`.

## 9. Tests

### Feature tests
```
tests/Feature/
  Workspaces/
    CreateWorkspaceTest.php      — create, DEK stored, audit logged
    ListWorkspacesTest.php        — owner sees own; others don't
    ViewWorkspaceTest.php        — owner + member can view; stranger 403
    UpdateWorkspaceTest.php      — owner can rename; others 403
    DeleteWorkspaceTest.php      — owner + super admin; others 403; cascades
  Audit/
    AuditLogTest.php              — actions are logged with actor + subject
```

### Unit tests
```
tests/Unit/
  Policies/
    WorkspacePolicyTest.php
```

### JS crypto tests
```
resources/js/crypto/__tests__/
  dek.test.js    — generate → seal → unseal round-trip with a keypair
  name.test.js   — encryptName → decryptName round-trip with a DEK
  privacy.test.js — server never sees plaintext workspace name (assert request body contains encrypted_name, not name)
```

## 10. Acceptance criteria

- [ ] User can create a workspace; DEK is generated and sealed to their public key
- [ ] User can list only their own workspaces (Super Admin sees all)
- [ ] User can open a workspace and unseal the DEK in-browser
- [ ] Owner can rename a workspace
- [ ] Owner can delete a workspace (cascades to descendants — verified once later modules add them)
- [ ] Non-owners get 403 on update/delete
- [ ] Super Admin bypasses policy (but cannot unseal DEK — no private key match)
- [ ] Audit log records create/rename/delete with actor + IP
- [ ] DEK never leaves the browser unencrypted (verify: no plaintext DEK in any request/response)
- [ ] Workspace name is encrypted at rest; server never sees plaintext name
- [ ] Workspace name decrypts correctly in browser after DEK unsealing
- [ ] All tests pass

## 11. Notes & decisions

- **RSA-OAEP vs ECDH for DEK sealing:** RSA-OAEP 2048 is simpler and sufficient for a gallery. ECDH (X25519) is smaller/faster but less commonly understood. Stick with RSA-OAEP unless performance demands otherwise.
- **DEK in memory:** keyed by `workspace_id` in a JS module-scoped map. Cleared on logout or tab close.
- **Cascade delete:** use DB foreign key cascades where possible; for file blobs, a queued job or inline cleanup deletes from `storage/app/private/`.
- **Re-key readiness:** `dek_version` column is added now (default 1) so Module 6 doesn't need a migration.
- **Encrypted workspace name (DECISIONS.md D5):** The workspace name is encrypted with the workspace DEK, so the workspace list view requires the browser to unseal each workspace's DEK to decrypt its name. For a handful of workspaces this is <10ms.

## 12. Open items

- All decisions resolved — see DECISIONS.md
