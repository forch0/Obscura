# Module 3 — Access Codes

**Phase:** Sharing
**Depends on:** Module 2 (Workspaces & DEK)
**Status:** Not started

---

## 1. Objective

Enable a Workspace Owner to generate scoped, time-boxed, revocable access codes that let invitees (with no account) access a workspace, collection, or gallery. The code itself is the cryptographic credential: the browser derives a key from the code and uses it to unwrap the workspace DEK.

## 2. What gets built

- Access code generation (scoped, time-boxed, permissioned)
- Access code entry (no account required → scoped session token)
- Access code revocation (soft revoke)
- Access code listing & management (owner view)
- "Code first, upgrade later" flow (invitee registers → access becomes account-based)
- Scoped session middleware (validates token + scope + permissions + expiry)
- Extension of `WorkspacePolicy` to accept access-code sessions

## 3. Database schema

### Migration: `create_workspace_access_codes_table`

```php
Schema::create('workspace_access_codes', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
    $table->string('scope');                  // workspace | collection | gallery
    $table->uuid('scope_id')->nullable();     // null when scope=workspace
    $table->unsignedSmallInteger('permissions'); // bitmask: view=1, upload=2, comment=4
    $table->string('code_hash');              // Argon2id hash of raw code
    $table->string('code_salt');              // base64, used for both hash + key derivation
    $table->text('wrapped_dek');              // DEK sealed with key derived from code
    $table->timestamp('expires_at');
    $table->timestamp('revoked_at')->nullable();
    $table->unsignedInteger('max_uses')->default(0); // 0 = unlimited
    $table->unsignedInteger('use_count')->default(0);
    $table->foreignUuid('created_by')->constrained('users');
    $table->string('label')->nullable();     // owner's note: "Sent to Alice"
    $table->timestamps();
    $table->index(['scope', 'scope_id']);
    $table->index(['workspace_id', 'revoked_at']);
    $table->index('expires_at');
});
```

### Migration: `create_workspace_members_table`

```php
Schema::create('workspace_members', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
    $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
    $table->string('role');                   // editor | viewer
    $table->text('wrapped_dek')->nullable();   // DEK sealed with member's public key
    $table->timestamp('joined_at')->useCurrent();
    $table->timestamps();
    $table->unique(['workspace_id', 'user_id']);
});
```

### Final tables

**`workspace_access_codes`**

| Column | Type | Notes |
|---|---|---|
| `id` | uuid (PK) | |
| `workspace_id` | uuid FK → workspaces | cascade |
| `scope` | string | `workspace` \| `collection` \| `gallery` |
| `scope_id` | uuid, nullable | null when scope=workspace; polymorphic (no hard FK) |
| `permissions` | ushort | bitmask: view=1, upload=2, comment=4 |
| `code_hash` | string | Argon2id(raw_code, code_salt) |
| `code_salt` | string | base64 |
| `wrapped_dek` | text | DEK sealed with key derived from code |
| `expires_at` | timestamp | |
| `revoked_at` | timestamp, nullable | null = active |
| `max_uses` | uint | 0 = unlimited |
| `use_count` | uint | |
| `created_by` | uuid FK → users | |
| `label` | string, nullable | owner's note |
| timestamps | | |

**`workspace_members`**

| Column | Type | Notes |
|---|---|---|
| `id` | uuid (PK) | |
| `workspace_id` | uuid FK | |
| `user_id` | uuid FK | |
| `role` | string | `editor` \| `viewer` |
| `wrapped_dek` | text, nullable | DEK sealed with member public key |
| `joined_at` | timestamp | |
| timestamps | | |

## 4. Files to create

### Backend
```
app/
  Traits/
    UsesUuid.php                       (UUID primary key boot logic)
  Models/
    WorkspaceAccessCode.php            (uses UsesUuid trait)
    WorkspaceMember.php                (uses UsesUuid trait)
  Http/
    Controllers/
      AccessCodeController.php       (generate, index, revoke, show)
      AccessCodeEntryController.php  (enter code → scoped token)
    Middleware/
      ValidateAccessCodeSession.php  (check token + scope + perms + expiry)
  Policies/
    AccessCodePolicy.php
  Services/
    AccessCode/
      CodeGeneratorService.php       (generate raw code, hash, salt)
      CodeSessionService.php         (issue/validate scoped tokens)
    Crypto/
      Argon2Config.php               (server-side Argon2id params for hashing)
database/
  migrations/
    yyyy_mm_dd_HHMMSS_create_workspace_access_codes_table.php
    yyyy_mm_dd_HHMMSS_create_workspace_members_table.php
  factories/
    WorkspaceAccessCodeFactory.php
    WorkspaceMemberFactory.php
```

### Frontend
```
resources/
  views/
    access-codes/
      index.blade.php                (owner: list codes, revoke button)
      create.blade.php                (scope, perms, duration picker)
      show.blade.php                  (display raw code ONCE)
      enter.blade.php                 (invitee: enter code form)
  js/
    crypto/
      code-key.js                     (derive key from code via Argon2id)
```

### Routes
```php
// Owner routes (auth + owns workspace)
Route::middleware(['auth', 'keypair'])->prefix('workspaces/{workspace}')->group(function () {
    Route::get('access-codes', [AccessCodeController::class, 'index']);
    Route::post('access-codes', [AccessCodeController::class, 'store']);
    Route::delete('access-codes/{code}', [AccessCodeController::class, 'revoke']);
});

// Invitee routes (guest)
Route::middleware('guest')->group(function () {
    Route::get('enter', [AccessCodeEntryController::class, 'create']);
    Route::post('enter', [AccessCodeEntryController::class, 'store']);
});
```

## 5. Crypto logic (browser-side)

### 5.1 Derive key from access code

```javascript
// resources/js/crypto/code-key.js

async function deriveCodeKey(rawCode, saltB64) {
  const salt = fromBase64(saltB64);
  const enc = new TextEncoder();

  // Import code as base key
  const baseKey = await crypto.subtle.importKey(
    'raw', enc.encode(rawCode), 'PBKDF2', false, ['deriveKey']
  );

  // Derive AES-GCM key from code (mirrors server's Argon2id for hashing,
  // but for key derivation we use PBKDF2 since WebCrypto doesn't expose Argon2id)
  // IMPORTANT: server hashes with Argon2id (PHP) for verification;
  // browser derives key with PBKDF2 (WebCrypto) for DEK unwrapping.
  // These are TWO DIFFERENT operations on the same code+salt.
  return crypto.subtle.deriveKey(
    { name: 'PBKDF2', salt, iterations: 250000, hash: 'SHA-256' },
    baseKey,
    { name: 'AES-GCM', length: 256 },
    false,
    ['encrypt', 'decrypt']
  );
}

async function unsealDekWithCode(wrappedDekB64, codeKey) {
  const wrapped = fromBase64(wrappedDekB64);
  const iv = wrapped.slice(0, 12);          // IV prepended
  const sealed = wrapped.slice(12);
  const rawDek = await crypto.subtle.decrypt({ name: 'AES-GCM', iv }, codeKey, sealed);
  return crypto.subtle.importKey('raw', rawDek, { name: 'AES-GCM', length: 256 }, false, ['encrypt', 'decrypt']);
}
```

### 5.2 Owner seals DEK for a new code

```javascript
async function sealDekForCode(dekHandle, rawCode, saltB64) {
  const salt = fromBase64(saltB64);
  const codeKey = await deriveCodeKey(rawCode, salt);

  const rawDek = await crypto.subtle.exportKey('raw', dekHandle);
  const iv = crypto.getRandomValues(new Uint8Array(12));
  const sealed = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, codeKey, rawDek);

  // Prepend IV
  const combined = new Uint8Array(iv.length + sealed.byteLength);
  combined.set(iv, 0);
  combined.set(new Uint8Array(sealed), iv.length);
  return base64(combined);
}
```

## 6. Key flows

### 6.1 Generate access code (owner)

1. Owner picks scope (workspace/collection/gallery), permissions (view/upload/comment), duration (minutes/hours), optional max_uses + label.
2. Server generates:
   - `raw_code` = 12-char base32, grouped `K7QX-9P2M-4F8R`
   - `code_salt` = 16 random bytes (base64)
   - `code_hash` = Argon2id(raw_code, code_salt) via PHP `password_hash` or sodium
   - `expires_at` = now + duration
3. Server inserts row with `wrapped_dek = NULL`, returns `{ code_id, raw_code, code_salt }` to owner **once**.
4. Owner's browser (holding `dekHandle`) runs `sealDekForCode(dek, raw_code, salt)`.
5. Browser POSTs `wrapped_dek` to `/workspaces/{ws}/access-codes/{code}`.
6. Server stores `wrapped_dek`.
7. Owner sees the raw code displayed once with a copy button.

### 6.2 Enter access code (invitee, no account)

1. Invitee visits `/enter`, submits `raw_code`.
2. Server iterates candidate rows: for each `code_salt`, computes `Argon2id(raw_code, salt)` and compares to `code_hash`.
   - Optimization: index by a non-secret prefix (first 4 chars of hash) to limit lookups.
3. On match, server checks: `revoked_at IS NULL AND expires_at > now() AND (max_uses = 0 OR use_count < max_uses)`.
4. If valid: increment `use_count`, issue a **scoped session token** (encrypted cookie or signed JWT) containing `{ code_id, scope, scope_id, permissions, expires_at }`.
5. Server returns `{ token, scope, permissions, wrapped_dek, code_salt }`.
6. Browser runs `deriveCodeKey(raw_code, salt)` → `unsealDekWithCode(wrapped_dek, codeKey)` → `dekHandle` in memory.
7. Browser redirects to the scoped resource.

### 6.3 Revoke access code (soft)

1. Owner clicks "Revoke" on a code in the list.
2. Server sets `revoked_at = now()`.
3. Future `/enter` attempts with this code return 403.
4. Existing scoped sessions: the `ValidateAccessCodeSession` middleware re-checks `revoked_at` on every request → next request 403.

### 6.4 Upgrade: code-holder registers

1. Invitee (holding `dekHandle` in memory from code entry) clicks "Create account".
2. Registration flow (Module 1) runs → new user, new keypair.
3. Browser re-seals the DEK to the new account's public key: `sealDekForUser(dek, publicKey)`.
4. Browser POSTs `{ workspace_id, wrapped_dek }` to `/api/workspaces/{ws}/claim`.
5. Server inserts `workspace_members` row with `wrapped_dek`, role = `viewer` (owner can upgrade later).
6. Now their access is account-based. Revoking the code doesn't affect them.

## 7. Scoped session tokens

### Token structure (signed cookie or JWT)
```json
{
  "code_id": 42,
  "scope": "collection",
  "scope_id": 7,
  "permissions": 5,           // view + comment
  "expires_at": "2026-09-16T21:00:00Z",
  "issued_at": "2026-09-16T18:00:00Z"
}
```

### Middleware: `ValidateAccessCodeSession`
```php
class ValidateAccessCodeSession
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->cookie('access_code_session');
        if (!$token) return $next($request); // not a code session; fall through

        $payload = $this->decode($token);
        $code = WorkspaceAccessCode::find($payload['code_id']);

        if (!$code || $code->revoked_at) return $this->deny('revoked');
        if ($code->expires_at < now())      return $this->deny('expired');
        if ($code->max_uses > 0 && $code->use_count >= $code->max_uses) return $this->deny('used');

        // Attach to request for policies
        $request->merge(['access_code' => $code, 'access_scope' => $payload]);
        return $next($request);
    }
}
```

## 8. Authorization extension

### Updated `WorkspacePolicy::view`

```php
public function view(User $user, Workspace $ws): bool
{
    if ($user->id === $ws->owner_id) return true;
    if ($ws->members()->where('user_id', $user->id)->exists()) return true;
    return false;
}

// New method for access-code sessions (called by middleware-injected context)
public function viewViaCode(WorkspaceAccessCode $code, Workspace $ws): bool
{
    return $code->workspace_id === $ws->id
        && $code->scope === 'workspace'
        && $code->hasPermission('view')
        && $code->revoked_at === null
        && $code->expires_at > now();
}
```

> The policy framework now has two entry points: account-based (User) and code-based (AccessCode). The first-match-wins order from CAPABILITIES.md §"Authorization Decision Order" applies.

## 9. Tests

### Feature tests
```
tests/Feature/
  AccessCodes/
    GenerateCodeTest.php        — create code, raw returned once, hash stored
    EnterCodeTest.php           — valid code → scoped token + DEK
    ExpiredCodeTest.php         — 403 after expires_at
    RevokedCodeTest.php         — 403 after revoked_at
    MaxUsesCodeTest.php         — 403 after use_count reaches max
    ScopedAccessTest.php        — code scoped to collection can't see other collections
    PermissionBitmaskTest.php   — view-only code can't upload
    UpgradeToAccountTest.php    — code-holder registers → member row created
    RevokeCodeTest.php          — owner revokes; existing session 403 on next request
  WorkspaceMembers/
    ClaimWorkspaceTest.php      — upgrade flow inserts member with wrapped_dek
```

### Unit tests
```
tests/Unit/
  Services/
    CodeGeneratorServiceTest.php — code format, uniqueness, hash verification
    CodeSessionServiceTest.php    — token issue/decode/expiry
```

### JS crypto tests
```
resources/js/crypto/__tests__/
  code-key.test.js — derive key from code, seal/unseal DEK round-trip
```

## 10. Acceptance criteria

- [ ] Owner can generate a code with scope, permissions, duration, max_uses, label
- [ ] Raw code is shown once to the owner; server stores only the hash
- [ ] Invitee can enter a valid code and receive a scoped session + unwrapped DEK
- [ ] Expired codes return 403
- [ ] Revoked codes return 403 (including existing sessions on next request)
- [ ] Max-uses enforcement works (one-time codes)
- [ ] Scope is enforced (collection-scoped code can't access other collections)
- [ ] Permission bitmask is enforced (view-only code can't upload)
- [ ] Code-holder can register → access becomes account-based (member row with wrapped_dek)
- [ ] Revoking the code after upgrade does NOT affect the upgraded member
- [ ] Owner can list and revoke codes
- [ ] All tests pass

## 11. Notes & decisions

- **Argon2id (server hash) vs PBKDF2 (browser key derivation):** WebCrypto doesn't expose Argon2id, so the browser uses PBKDF2 to derive the unwrapping key. The server uses Argon2id (via PHP `sodium` or `password_hash`) to hash the code for lookup/verification. These are two separate operations on the same `(code, salt)` pair — the hash is for the server to find the row; the derived key is for the browser to unwrap the DEK. Both must use the **same salt**.
- **Code format:** 12 chars base32 (A-Z2-7), grouped `XXXX-XXXX-XXXX` for readability. ~62 bits of entropy — sufficient for time-boxed codes.
- **Token storage:** signed cookie (HttpOnly, Secure, SameSite=Strict) is simplest. JWT is an alternative if we need stateless validation. Recommendation: cookie + server-side re-check of `revoked_at`/`expires_at` on every request (not pure JWT, which can't be revoked).
- **Lookup optimization:** store a non-secret `code_prefix` (first 4 chars of `code_hash`) and index it, so `/enter` only Argon2id-hashes against a handful of candidate rows instead of all codes.
- **Polymorphic `scope_id`:** `scope_id` is a UUID that references either a collection or a gallery (or is null when `scope=workspace`). Because the target table depends on the `scope` value, `scope_id` is not a hard FK constraint. A composite index on `(scope, scope_id)` is added instead to support efficient lookups.

## 12. Open items

All decisions resolved — see DECISIONS.md. Argon2id (server) + PBKDF2 (browser) dual-operation approach confirmed. Scoped session token: signed cookie. Upgraded members default to `viewer` role.
