# Module 1 — Auth & User Keypair & Super Admin

**Phase:** Foundation
**Depends on:** —
**Status:** Not started

---

## 1. Objective

Establish user authentication and the cryptographic identity layer that all later modules depend on. After this module, users can register, log in, and each user has an asymmetric keypair (generated in-browser) whose private key is sealed with a password-derived key. A Super Admin role exists for platform management.

## 2. What gets built

- Registration flow (email + password)
- Login / logout flow
- User keypair generation (WebCrypto, browser-side)
- Private key sealing (PBKDF2 + AES-GCM) and storage
- Private key unsealing on login
- Super Admin flag + policy bypass
- Password change (re-seal private key)

## 3. Database schema

### Migration: `create_users_table` (replace default with UUID)

The default Laravel `0001_01_01_000000_create_users_table` migration should be modified to use a UUID primary key instead of bigint, so that all tables in the application use UUID consistently per DECISIONS.md D6. Replace the default migration with the version below, then add an extension migration for the crypto columns.

```php
// 0001_01_01_000000_create_users_table.php  (replaced default)

Schema::create('users', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password');
    $table->boolean('is_super_admin')->default(false);
    $table->rememberToken();
    $table->timestamps();
});
```

```php
// yyyy_mm_dd_HHMMSS_extend_users_table_for_crypto.php

Schema::table('users', function (Blueprint $table) {
    $table->text('public_key')->nullable()->after('is_super_admin');           // RSA-OAEP public key (SPKI DER, base64)
    $table->text('encrypted_private_key')->nullable()->after('public_key');    // AES-GCM sealed private key (base64)
    $table->text('encrypted_private_key_recovery')->nullable()->after('encrypted_private_key'); // private key sealed with recovery-code-derived key
    $table->string('recovery_code_hash')->nullable()->after('encrypted_private_key_recovery'); // hash of recovery code
    $table->string('recovery_code_salt')->nullable()->after('recovery_code_hash'); // PBKDF2 salt for recovery code (base64)
    $table->timestamp('recovery_code_used_at')->nullable()->after('recovery_code_salt');
    $table->string('keypair_salt')->nullable()->after('recovery_code_used_at'); // PBKDF2 salt (base64)
    $table->timestamp('keypair_created_at')->nullable()->after('keypair_salt');
});

Schema::table('users', function (Blueprint $table) {
    $table->index('is_super_admin');
});
```

### Final `users` table columns

| Column | Type | Notes |
|---|---|---|
| `id` | uuid (PK) | UUID per DECISIONS.md D6 |
| `name` | string | |
| `email` | string (unique) | |
| `email_verified_at` | timestamp, nullable | |
| `password` | string | Argon2id hash (Laravel default) — auth only |
| `is_super_admin` | boolean | default false |
| `public_key` | text, nullable | RSA-OAEP 2048, SPKI DER, base64 |
| `encrypted_private_key` | text, nullable | PKCS#8 private key sealed with AES-GCM (password-derived) |
| `encrypted_private_key_recovery` | text, nullable | PKCS#8 private key sealed with AES-GCM (recovery-code-derived) |
| `recovery_code_hash` | string, nullable | hash of recovery code |
| `recovery_code_salt` | string, nullable | PBKDF2 salt for recovery code, base64 |
| `recovery_code_used_at` | timestamp, nullable | set when recovery code is consumed |
| `keypair_salt` | string, nullable | PBKDF2 salt, base64 |
| `keypair_created_at` | timestamp, nullable | |
| `remember_token` | string | |
| `created_at` / `updated_at` | timestamps | |

## 4. Files to create

### Backend
```
app/
  Models/
    User.php                      (modify: add fills, casts, isSuperAdmin(), use UsesUuid trait)
  Traits/
    UsesUuid.php                  (UUID primary key trait: override getKeyType, getIncrementing, generate UUID on creating)
  Http/
    Controllers/
      Auth/
        RegisteredUserController.php   (modify: return keygen flag)
        AuthenticatedSessionController.php
        RecoveryCodeController.php      (show recovery code once, password recovery form + submit)
    Middleware/
      EnsureUserHasKeypair.php    (redirect to keygen if missing)
  Services/
    Crypto/
      KeyDerivationService.php    (PBKDF2 params config — server only stores params)
database/
  migrations/
    0001_01_01_000000_create_users_table.php  (replace default: UUID primary key)
    yyyy_mm_dd_HHMMSS_extend_users_table_for_crypto.php
  factories/
    UserFactory.php               (modify: add crypto fields)
```

### Frontend
```
resources/
  views/
    auth/
      register.blade.php          (email + password form)
      login.blade.php
      keygen.blade.php            (post-registration keypair generation page)
      recovery-code.blade.php     (display recovery code once, with print button)
      recover.blade.php           (password recovery form: email + recovery code + new password)
  js/
    crypto/
      keypair.js                  (WebCrypto: generate keypair, seal private key)
      session.js                  (hold unwrapped private key in memory)
      pbkdf2.js                   (PBKDF2 wrapper)
      recovery.js                 (WebCrypto: derive recovery-code key, seal/unseal private key)
```

### Routes
```php
// routes/web.php additions
Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create']);
    Route::post('register', [RegisteredUserController::class, 'store']);
    Route::get('login', [AuthenticatedSessionController::class, 'create']);
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
    Route::get('recover', [RecoveryCodeController::class, 'showRecoverForm']);
    Route::post('recover', [RecoveryCodeController::class, 'recover']);
});
Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy']);
    Route::get('/keygen', fn () => view('auth.keygen'));
    Route::post('/api/keypair', [KeypairController::class, 'store']);
    Route::get('/recovery-code', [RecoveryCodeController::class, 'show']);
});
```

## 5. Crypto logic (browser-side)

### 5.1 Registration → keypair generation

```javascript
// resources/js/crypto/keypair.js

async function generateAndSealKeypair(password) {
  // 1. Generate RSA-OAEP keypair (2048-bit)
  const keypair = await crypto.subtle.generateKey(
    { name: 'RSA-OAEP', modulusLength: 2048, publicExponent: new Uint8Array([1,0,1]), hash: 'SHA-256' },
    true,                                    // extractable (we need to export & store)
    ['encrypt', 'decrypt']                   // usages: seal/unseal DEKs
  );

  // 2. Export public key as SPKI DER → base64
  const pubSpki = await crypto.subtle.exportKey('spki', keypair.publicKey);
  const publicKeyB64 = base64(pubSpki);

  // 3. Export private key as PKCS#8 DER
  const privPkcs8 = await crypto.subtle.exportKey('pkcs8', keypair.privateKey);

  // 4. Derive wrapping key from password
  const salt = crypto.getRandomValues(new Uint8Array(16));
  const wrappingKey = await deriveWrappingKey(password, salt);

  // 5. Seal private key with AES-GCM
  const iv = crypto.getRandomValues(new Uint8Array(12));
  const sealedPriv = await crypto.subtle.encrypt(
    { name: 'AES-GCM', iv }, wrappingKey, privPkcs8
  );

  return {
    publicKey: publicKeyB64,
    encryptedPrivateKey: base64(sealedPriv),
    salt: base64(salt),
    iv: base64(iv),                          // store alongside sealed key
    // Keep privateKey handle in memory for this session
    privateKeyHandle: keypair.privateKey,
  };
}

async function deriveWrappingKey(password, salt) {
  const enc = new TextEncoder();
  const baseKey = await crypto.subtle.importKey('raw', enc.encode(password), 'PBKDF2', false, ['deriveKey']);
  return crypto.subtle.deriveKey(
    { name: 'PBKDF2', salt, iterations: 250000, hash: 'SHA-256' },
    baseKey,
    { name: 'AES-GCM', length: 256 },
    false,                                   // not extractable
    ['encrypt', 'decrypt']
  );
}
```

### 5.2 Login → unseal private key

```javascript
// resources/js/crypto/session.js

async function unsealPrivateKey(password, sealedPrivB64, saltB64, ivB64) {
  const salt = fromBase64(saltB64);
  const iv = fromBase64(ivB64);
  const sealedPriv = fromBase64(sealedPrivB64);

  const wrappingKey = await deriveWrappingKey(password, salt);
  const privPkcs8 = await crypto.subtle.decrypt(
    { name: 'AES-GCM', iv }, wrappingKey, sealedPriv
  );

  // Import as usable key handle (kept in memory only, never persisted)
  return crypto.subtle.importKey(
    'pkcs8', privPkcs8,
    { name: 'RSA-OAEP', hash: 'SHA-256' },
    false,                                   // not extractable — can't be re-exported
    ['decrypt']                              // only need decrypt to unseal DEKs
  );
}
```

### 5.3 Recovery code → seal/unseal private key

```javascript
// resources/js/crypto/recovery.js

async function deriveRecoveryWrappingKey(recoveryCode, salt) {
  const enc = new TextEncoder();
  const baseKey = await crypto.subtle.importKey('raw', enc.encode(recoveryCode), 'PBKDF2', false, ['deriveKey']);
  return crypto.subtle.deriveKey(
    { name: 'PBKDF2', salt, iterations: 250000, hash: 'SHA-256' },
    baseKey,
    { name: 'AES-GCM', length: 256 },
    false,                                   // not extractable
    ['encrypt', 'decrypt']
  );
}

async function sealPrivateKeyWithRecoveryCode(privPkcs8, recoveryCode) {
  const salt = crypto.getRandomValues(new Uint8Array(16));
  const iv = crypto.getRandomValues(new Uint8Array(12));
  const wrappingKey = await deriveRecoveryWrappingKey(recoveryCode, salt);
  const sealedPriv = await crypto.subtle.encrypt(
    { name: 'AES-GCM', iv }, wrappingKey, privPkcs8
  );

  return {
    encryptedPrivateKeyRecovery: base64(sealedPriv),
    recoveryCodeSalt: base64(salt),
    recoveryIv: base64(iv),
  };
}

async function unsealPrivateKeyWithRecoveryCode(recoveryCode, sealedPrivB64, saltB64, ivB64) {
  const salt = fromBase64(saltB64);
  const iv = fromBase64(ivB64);
  const sealedPriv = fromBase64(sealedPrivB64);

  const wrappingKey = await deriveRecoveryWrappingKey(recoveryCode, salt);
  return crypto.subtle.decrypt(
    { name: 'AES-GCM', iv }, wrappingKey, sealedPriv
  );
}
```

## 6. Key flows

### 6.1 Registration
1. User submits email + password.
2. Server creates `users` row (password hashed via Argon2id). Keypair fields are NULL.
3. Server returns `{ user_id, needs_keygen: true }`.
4. Browser navigates to `/keygen`.
5. Browser runs `generateAndSealKeypair(password)`.
6. Browser POSTs `{ publicKey, encryptedPrivateKey, salt, iv }` to `/api/keypair`.
7. Server stores them, sets `keypair_created_at`.
8. Browser keeps `privateKeyHandle` in memory (sessionStorage holds a flag that keypair is ready; the actual key handle lives in JS memory).

### 6.2 Login
1. User submits email + password.
2. Server verifies password hash, issues Laravel session.
3. Server returns `{ public_key, encrypted_private_key, keypair_salt, iv }` to browser.
4. Browser runs `unsealPrivateKey(password, ...)`.
5. Browser stores `privateKeyHandle` in JS memory for the session.

### 6.3 Password change
1. User submits old + new password (server verifies old).
2. Browser (still holding `privateKeyHandle`) re-exports private key as PKCS#8.
3. Browser derives new wrapping key from new password + new salt.
4. Browser seals private key with new wrapping key.
5. Browser POSTs new `{ encryptedPrivateKey, salt, iv }` to server.
6. Server updates `users` row + password hash.

### 6.4 Recovery code generation (at registration)
1. During registration, after the keypair is generated and sealed with the password-derived key, the browser also seals the private key a second time using a recovery-code-derived key.
2. The browser generates a random recovery code (e.g. 24 alphanumeric characters, split into groups for readability).
3. The browser derives a wrapping key from the recovery code + a new salt (PBKDF2).
4. The browser seals the PKCS#8 private key with the recovery-code-derived key (AES-GCM).
5. The browser POSTs `{ encryptedPrivateKeyRecovery, recoveryCodeSalt, recoveryIv, recoveryCodeHash }` to `/api/keypair` alongside the password-sealed key. The server stores `recovery_code_hash` (a hash of the recovery code) and `recovery_code_salt`; the plaintext recovery code is never sent to or stored on the server.
6. The server returns a one-time URL `/recovery-code` to display the recovery code.
7. The browser navigates to `/recovery-code`, which shows the plaintext recovery code ONCE with a print button. The user is instructed to store it securely. The code is not shown again.

### 6.5 Password recovery via recovery code
1. User navigates to `/recover` and submits email + recovery code + new password.
2. Server looks up the user by email, verifies the recovery code against `recovery_code_hash`.
3. If valid, server returns `{ encrypted_private_key_recovery, recovery_code_salt, recovery_iv }` to the browser.
4. Browser runs `unsealPrivateKeyWithRecoveryCode(recoveryCode, ...)` to recover the PKCS#8 private key.
5. Browser imports the private key as a usable handle, then re-exports it as PKCS#8.
6. Browser derives a new wrapping key from the new password + new salt.
7. Browser seals the private key with the new password-derived key (AES-GCM).
8. Browser POSTs `{ encryptedPrivateKey, salt, iv, newPassword }` to the server.
9. Server updates the `users` row: new password hash, new `encrypted_private_key`, new `keypair_salt`, and sets `recovery_code_used_at` to mark the recovery code as consumed.
10. Optionally, the server/browser generate a fresh recovery code and re-seal the private key with it (recovery code rotation).

## 7. Super Admin

- First Super Admin is created via a tinker command or a one-time setup route (protected by an env var `SUPER_ADMIN_EMAIL`).
- `User::isSuperAdmin()` returns the flag.
- A `SuperAdminPolicy` or a base `before()` hook in each policy returns `true` if `isSuperAdmin`.
- Super Admin has **no DEK sealed for them** — they cannot decrypt media (enforced by design, not by code that could be bypassed).

### Setup command
```php
// app/Console/Commands/PromoteSuperAdmin.php
Artisan::command('admin:promote {email}', function ($email) {
  $user = User::where('email', $email)->firstOrFail();
  $user->update(['is_super_admin' => true]);
  $this->info("{$email} is now a Super Admin.");
});
```

## 8. Tests

### Feature tests
```
tests/Feature/
  Auth/
    RegistrationTest.php       — register, keygen, keypair stored, recovery code hash stored
    LoginTest.php              — login, private key returned for unsealing
    LogoutTest.php             — session destroyed
    PasswordChangeTest.php     — old password verified, key re-sealed
    RecoveryCodeTest.php       — recovery code generated at registration, hash stored, code shown once
    PasswordRecoveryTest.php   — recover via recovery code, private key re-sealed with new password
  SuperAdmin/
    PromotionTest.php          — admin:promote command works
    PolicyBypassTest.php       — super admin bypasses a sample policy
```

### Unit tests
```
tests/Unit/
  Crypto/
    KeyDerivationServiceTest.php  — PBKDF2 params are correct
```

### JS crypto tests (browser or Vitest with WebCrypto polyfill)
```
resources/js/crypto/__tests__/
  keypair.test.js    — generate → seal → unseal round-trip
  pbkdf2.test.js     — derivation is deterministic with same salt
  recovery.test.js   — recovery code seal → unseal round-trip; differs from password seal
```

## 9. Acceptance criteria

- [ ] User can register with email + password
- [ ] After registration, browser generates keypair and stores it on server
- [ ] Recovery code is generated at registration; its hash is stored on the server (plaintext never sent)
- [ ] Recovery code is shown ONCE to the user with a print button
- [ ] User can log in; browser receives and unseals private key
- [ ] Private key never leaves the browser unencrypted (verify: no plaintext private key in any request/response/log)
- [ ] User can change password; private key is re-sealed successfully
- [ ] User can recover password via recovery code: private key is unsealed with recovery code and re-sealed with new password
- [ ] Recovery code is marked as used after a successful recovery
- [ ] Super Admin can be promoted via `php artisan admin:promote`
- [ ] Super Admin flag bypasses a test policy
- [ ] Logging out clears the in-memory private key handle
- [ ] All feature tests pass
- [ ] All JS crypto round-trip tests pass

## 10. Notes & decisions

- **PBKDF2 iterations:** 250,000 (OWASP 2023 recommendation for SHA-256). Configurable in `config/crypto.php`.
- **Private key extractability:** `false` after import on login — cannot be re-exported, reducing exfil risk.
- **Session storage of key handle:** JS memory only (`window`-scoped variable), not `localStorage`/`sessionStorage`/cookies. Lost on tab close → user must re-login.
- **Recovery code:** generated at registration alongside the keypair. The private key is sealed twice — once with the password-derived key, once with the recovery-code-derived key. The recovery code is shown once to the user and its hash is stored on the server. See section 6.4 and 6.5 for the full flow.
- **UUID primary keys:** all tables use UUID per DECISIONS.md D6. The default `users` migration is replaced to use `uuid('id')` as the primary key.

## 11. Open items for this module

All decisions resolved — see DECISIONS.md.
