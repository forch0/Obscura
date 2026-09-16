# Obscura — End-to-End Workflows & Sequence Diagrams

**Companion to:** `PRD.md`
**Notation:** ASCII sequence diagrams. Participants across the top; arrows are requests/data flow; `->>` = synchronous request; `-->>` = response; notes in `[#]`.

---

## Participants

| Alias | Who |
|---|---|
| **U** | User (browser, runs WebCrypto) |
| **S** | Server (Laravel app on cPanel) |
| **DB** | Database (SQLite local / MySQL host) |
| **FS** | Filesystem (`storage/app/private/`) |

Note: Per DECISIONS.md D5, all human-readable names (workspace, collection, gallery names, media titles/captions) are encrypted with the workspace DEK. Flows that display names include a decrypt step in the browser. The server only handles ciphertext for these fields.

---

## 1. Registration & User Keypair Generation

First-time account user (future workspace owner or member) creates an account. The browser generates an asymmetric keypair; the private key is sealed with a key derived from their password so the server never sees it.

```
U                S                DB
|                |                |
|-- register (email, password) ->>|                |
|                |                |
|                |  [#] Server validates, hashes password (Argon2id) for auth only.
|                |  [#] Server does NOT derive any key from password (that's client-side).
|                |                |
|                |  insert user (password_hash) ->>|                |
|                |<-- user id -------------------|
|                |                |
|<-- 200 {user_id, needs_keygen} --|                |
|                |                |
| [#] Browser (WebCrypto):
|     1. generateKey(RSA-OAEP, 2048)  -> keypair
|     2. deriveKey(PBKDF2, password, salt) -> wrappingKey
|     3. wrapKey(privateKey, wrappingKey) -> sealedPrivateKey
|                |                |
|-- store_keypair (public_key, sealedPrivateKey, salt) ->>|                |
|                |  update users ->>|                |
|                |<-- ok -----------|                |
|                |                |
|<-- 200 (keypair stored) --------|                |
|                |                |
| [#] Browser keeps privateKey in session (unsealed) for this session.
|     On future logins, re-derive wrappingKey from password, unwrap privateKey.
|     Browser generates recovery code and seals private key with recovery-derived key (see §13a). Recovery code shown once with print button.
```

**Notes:**
- Password change = re-wrap private key with new wrapping key (browser does it after verifying old password).
- Password forgotten = private key unrecoverable (E2EE tradeoff). See PRD §10 Q2.

---

## 2. Login & Private Key Unsealing

```
U                S                DB
|                |                |
|-- login (email, password) ----->>|                |
|                |  verify password hash ->>|        |
|                |<-- user row (incl. public_key, sealedPrivateKey, salt) --|
|                |                |
|                |  [#] Server issues session (Laravel default auth).
|                |  [#] Server returns public_key, sealedPrivateKey, salt to browser.
|                |                |
|<-- 200 {session, public_key, sealedPrivateKey, salt} --|        |
|                |                |
| [#] Browser:
|     deriveKey(PBKDF2, password, salt) -> wrappingKey
|     unwrapKey(sealedPrivateKey, wrappingKey) -> privateKey  (in memory only)
|                |                |
| [#] Browser now holds privateKey for session. Stored in JS memory / sessionStorage, never disk.
```

---

## 3. Create Workspace & Generate DEK

Owner creates a new workspace. Browser generates the workspace DEK and seals it for the owner.

```
U                S                DB
|                |                |
|-- create_workspace (name) ------->>|                |
|                |  insert workspaces (owner_id, name, wrapped_dek_for_owner=NULL) ->>|
|                |<-- workspace_id --|                |
|                |                |
|<-- 200 {workspace_id} -----------|                |
|                |                |
| [#] Browser (WebCrypto):
|     1. generateKey(AES-GCM, 256) -> DEK
|     2. encryptKey(DEK, owner_publicKey) -> wrapped_dek_for_owner
|     3. keep DEK in session memory
|                |                |
|-- store_workspace_dek (workspace_id, wrapped_dek_for_owner) ->>|
|                |  update workspaces ->>|            |
|                |<-- ok -----------|                |
|                |                |
|<-- 200 (workspace ready) --------|                |
|                |                |
| [#] Browser uses DEK for all subsequent encrypt/decrypt in this workspace.
```

---

## 4. Generate Access Code (scoped, time-boxed)

Owner shares access by generating a code. The browser derives a key from the code and seals the DEK with it.

```
U (owner)       S                DB
|                |                |
|-- request: "generate code for scope=X, perms=view, duration=2h" ->>|
|                |                |
|                |  [#] Server generates:
|                |       raw_code  = 12-char base32 (e.g. K7QX-9P2M-4F8R)
|                |       code_salt  = random 16 bytes
|                |       expires_at = now + duration
|                |       code_hash  = Argon2id(raw_code, code_salt)
|                |  insert workspace_access_codes (..., wrapped_dek=NULL, code_hash, salt, expires_at, scope, perms, revoked_at=NULL)
|                |<-- code_id --|                |
|                |                |
|<-- 200 {raw_code, code_salt, code_id} (returned ONCE to owner) --|        |
|                |                |
| [#] Browser (WebCrypto):
|     1. deriveKey(Argon2id, raw_code, code_salt) -> codeKey
|     2. encryptKey(DEK, codeKey) -> wrapped_dek
|                |                |
|-- store_wrapped_dek (code_id, wrapped_dek) ->>|                |
|                |  update workspace_access_codes set wrapped_dek ->>|
|                |<-- ok -----------|                |
|                |                |
| [#] Owner sees the raw_code ONCE to share it. Server never stores raw_code.
```

**Notes:**
- `code_hash` lets the server verify a submitted code without storing it.
- `wrapped_dek` + `code_salt` are returned to any valid code holder so they can derive the codeKey client-side and unwrap the DEK. The code itself is the secret; the salt is not secret.

---

## 5. Access Code Entry (invitee gets in, no account)

```
U (invitee)     S                DB
|                |                |
|-- enter_code (raw_code) ------->>|                |
|                |                |
|                |  [#] Server: find rows where Argon2id(raw_code, salt) == code_hash
|                |     (try each salt, or index by code prefix if needed)
|                |  [#] Check: revoked_at IS NULL AND expires_at > now() AND use_count < max_uses
|                |  if invalid -> 403 (revoked/expired/used)
|                |                |
|                |  increment use_count ->>|        |
|                |                |                |
|                |  issue scoped session token (tied to code_id, scope, perms, expiry) ->>|
|                |<-- token -------|                |
|                |                |
|<-- 200 {token, scope, perms, wrapped_dek, code_salt} --|        |
|                |                |
| [#] Browser (WebCrypto):
|     deriveKey(Argon2id, raw_code, code_salt) -> codeKey
|     unwrapKey(wrapped_dek, codeKey) -> DEK  (in memory)
|                |                |
| [#] Browser now holds DEK + scoped token. Can now view/decrypt within scope.
```

---

## 6. Upgrade: Code-holder Registers an Account

Invitee decides to register. Their browser re-seals the DEK to their new account keypair so access becomes account-based and survives code revocation.

```
U (invitee, holding DEK in memory)     S                DB
|                |                |
|-- register (email, password) ------->>|                |
|                |  insert user (password_hash) ->>|    |
|                |<-- user_id -----|                |
|                |                |
|<-- 200 {user_id, needs_keygen} -----|                |
|                |                |
| [#] Browser:
|     1. generateKey(RSA-OAEP) -> keypair
|     2. deriveKey(PBKDF2, password, salt) -> wrappingKey
|     3. wrapKey(privateKey, wrappingKey) -> sealedPrivateKey
|     4. encryptKey(DEK, publicKey) -> wrapped_dek_for_member   [#] re-seal DEK to their account
|                |                |
|-- store_keypair + claim_workspace (public_key, sealedPrivateKey, workspace_id, wrapped_dek_for_member, role) ->>|
|                |  update users (keypair) ->>|    |
|                |  insert workspace_members (workspace_id, user_id, role) ->>|
|                |<-- ok -----------|                |
|                |                |
|<-- 200 (account ready, member linked) --|        |
|                |                |
| [#] Now their access is account-based. The code can be revoked without affecting them.
```

---

## 7. Upload Media (client-side encrypt)

A user with `upload` permission (owner, member-editor, or code with upload perm) adds an image to a gallery.

```
U                S                DB               FS
|                |                |                |
|-- select file (gallery_id) ----- (browser)        |                |
|                |                |                |
| [#] Browser (WebCrypto):
|     1. generateKey(AES-GCM, 256) -> CEK
|     2. generateIV() -> IV
|     3. encrypt(fileBytes, CEK, IV) -> ciphertext
|     4. wrapKey(CEK, DEK) -> cek_wrapped   [#] seal CEK with workspace DEK
|                |                |                |
|-- POST /media (gallery_id, ciphertext, cek_wrapped, IV, metadata) ->>|
|                |                |                |
|                |  [#] Authz policy: can this principal upload to this gallery?
|                |       (owner | member-editor | code with upload perm in scope)
|                |                |                |
|                |  write ciphertext to FS ->>|    |                |
|                |                |                |-- blob stored --|
|                |                |                |
|                |  insert media (gallery_id, blob_path, cek_wrapped, IV, metadata) ->>|
|                |<-- media_id ----|                |
|                |                |                |
|<-- 200 {media_id} --------------|                |
```

**Notes:**
- Server never sees plaintext or CEK or DEK.
- Metadata (title, mime, size) is plaintext for listing.

---

## 8. View Gallery / Decrypt Media

```
U                S                DB               FS
|                |                |                |
|-- GET /gallery/{id} ------------->>|                |
|                |  [#] Authz: can view gallery?
|                |  select media rows (metadata only, plaintext) ->>|
|                |<-- rows ---------|                |
|                |                |                |
|<-- 200 {gallery, media[]: {id, title, size, mime, ...}} --|        |
|                |                |                |
| [#] Browser renders the grid using plaintext metadata (no decryption needed yet).
|                |                |                |
|-- GET /media/{id}/blob ----------->>|                |
|                |  [#] Authz: can view this media?
|                |  read blob from FS ->>|            |                |
|                |                |                |-- ciphertext ---|
|                |  select cek_wrapped, IV ->>|      |                |
|                |<-- row -----------|                |
|                |                |                |
|<-- 200 {ciphertext, cek_wrapped, IV} --|        |                |
|                |                |                |
| [#] Browser (WebCrypto):
|     unwrapKey(cek_wrapped, DEK) -> CEK
|     decrypt(ciphertext, CEK, IV) -> plaintext bytes
|     createObjectURL(plaintext) -> <img src>
|                |                |                |
| [#] Image displays. Plaintext never persisted to disk.
```

---

## 9. Revoke Access Code

### 9a. Soft revoke (blocks new access)

```
U (owner)       S                DB
|                |                |
|-- revoke_code (code_id) --------->>|
|                |  update workspace_access_codes set revoked_at = now() ->>|
|                |<-- ok -----------|                |
|<-- 200 (revoked) ---------------|                |
|                |                |
| [#] Any future enter_code for this id returns 403. Existing browser sessions with the DEK still work until they close/expire.
```

### 9b. Hard revoke (re-key workspace — true revocation)

```
U (owner, holding DEK)     S                DB               FS
|                |                |                |
|-- rekey_workspace (workspace_id) ->>|        |                |
|                |                |                |
|                |  [#] Server locks workspace during re-key (sync queue).
|                |  select all still-valid principals:
|                |     - workspace_members (with their public_keys)
|                |     - access_codes where revoked_at IS NULL AND expires_at > now() (with salts)
|                |<-- principals list --|        |
|                |                |                |
|<-- 200 {principals: [{id, type, public_key|salt}]} --|        |
|                |                |                |
| [#] Browser (WebCrypto):
|     1. generateKey(AES-GCM, 256) -> newDEK
|     2. for each media file:
|          unwrapKey(old_cek_wrapped, oldDEK) -> CEK   [#] CEK unchanged
|          wrapKey(CEK, newDEK) -> new_cek_wrapped
|     3. for each principal:
|          if account user: encryptKey(newDEK, public_key) -> wrapped_dek
|          if access code: deriveKey(Argon2id, ?, salt) ... [#] owner doesn't know raw codes!
|                |                |                |
| [#] PROBLEM: owner cannot re-seal for access codes because they don't know the raw codes.
|     Resolution: re-key INVALIDATES all outstanding access codes (they must be regenerated).
|     Only account members are re-sealed. Codes are revoked as a side effect.
|                |                |                |
| [#] Revised browser step 3:
|     for each workspace_member: encryptKey(newDEK, public_key) -> wrapped_dek_for_member
|                |                |                |
|-- POST rekey_result (new_wrapped_dek_for_owner, [{member_id, wrapped_dek}], [{media_id, new_cek_wrapped}]) ->>|
|                |  transaction:
|                |    update workspaces.wrapped_dek_for_owner
|                |    update workspace_members.wrapped_dek (per member)
|                |    update media.cek_wrapped (per file)
|                |    update workspace_access_codes set revoked_at = now() (all outstanding)
|                |    update workspaces.dek_version ++
|                |<-- ok -----------|                |
|<-- 200 (re-key complete) ---------|                |
```

**Notes:**
- Re-key invalidates all access codes (owner must regenerate any they want to keep).
- File blobs are NOT re-encrypted — only the small CEKs are re-wrapped. Fast even with thousands of images.
- This is the E2EE revocation completeness step. v1 may ship with soft-revoke only and add re-key as a follow-up.

---

## 10. Joint Gallery — Co-editor Upload

Two members of a joint gallery both use the same workspace DEK (they each have their own sealed copy of it). Upload flow is identical to §7 for each editor; the only difference is authorization.

```
U_a (editor A)  U_b (editor B)     S                DB
|                |                |                |
|  [#] Both have unwrapped the workspace DEK in their sessions.
|                |                |                |
|-- upload file_a (gallery_id) ----->>|                |
|                |  authz: gallery.type=joint AND gallery_members(user=A, role=editor) -> allow
|                |  store ciphertext + cek_wrapped (sealed with workspace DEK) ->>|
|<-- 200 {media_id_a} -------------|                |
|                |                |                |
|                |-- upload file_b (gallery_id) ->>|                |
|                |  authz: gallery.type=joint AND gallery_members(user=B, role=editor) -> allow
|                |  store ciphertext + cek_wrapped ->>|
|                |<-- 200 {media_id_b} --|                |
|                |                |                |
| [#] Both files decrypt with the same workspace DEK. Either editor can also delete either file (per policy).
```

---

## 11. Super Admin — Metadata Management

Super Admin manages accounts/workspaces but **cannot view encrypted content** (no DEK).

```
U (super admin) S                DB
|                |                |
|-- GET /admin/workspaces --------->>|
|                |  select workspaces (owner_id, name, counts) ->>|
|                |<-- rows ---------|                |
|<-- 200 {workspaces[]} -----------|                |
|                |                |
| [#] Super admin sees metadata only. No wrapped_dek for them. No DEK = no decryption possible.
| [#] Super admin can: disable users, delete workspaces, reset passwords (which seals keys, not decrypts content).
```

---

## 12. End-to-End: Owner Shares a Collection with a Friend for 3 Hours

Combined flow tying the pieces together.

```
Owner (O)                    Friend (F)            Server (S)
|                            |                     |
| 1. O creates workspace (§3) |                     |
| 2. O creates collection     |                     |
| 3. O generates code:       |                     |
|       scope=collection, perms=view, duration=3h (§4)            |
|       -> receives K7QX-9P2M-4F8R                                 |
|                            |                     |
| 4. O sends code to F out-of-band (chat/email)                  |
|                ----------> K7QX-9P2M-4F8R ----->|              |
|                            |                     |
|                            | 5. F enters code (§5)             |
|                            |    -> scoped session + DEK in browser|
|                            |                     |
|                            | 6. F browses collection (§8)       |
|                            |    -> sees thumbnails (metadata)    |
|                            |    -> clicks image -> decrypts blob |
|                            |                     |
| ... 3 hours pass ...        |                     |
|                            |                     |
|                            | 7. F's code expires (expires_at < now)
|                            |    -> next request returns 403     |
|                            |    -> F's browser still has DEK in memory
|                            |       but no new requests succeed; session dead|
|                            |                     |
| 8. (Optional) O soft-revokes early (§9a) if F misbehaves       |
|    -> F's next request 403 immediately                         |
|                            |                     |
| 9. (Optional) O hard-revokes (§9b) if F might have copied DEK  |
|    -> re-key; even a stolen DEK is now useless                  |
```

---

## 13. Recovery Code — Generation and Password Recovery

### 13a. Recovery code generation (at registration)

```
U                S                DB
|                |                |
| (after keypair generation in §1) |                |
|                |                |
| [#] Browser generates recovery code:
|     recovery_code = 24-char base32 (e.g. K7QX9P2M4F8R... grouped in 4s)
|                |                |
| [#] Browser derives recovery key:
|     deriveKey(PBKDF2, recovery_code, recovery_salt) -> recoveryKey
|     (recovery_salt generated by browser, 16 random bytes)
|                |                |
| [#] Browser seals private key with recovery key:
|     wrapKey(privateKey, recoveryKey) -> sealedPrivateKeyRecovery
|                |                |
|-- store_recovery (recovery_code_hash_input, recovery_salt, sealedPrivateKeyRecovery) ->>|
|                |  [#] Server hashes recovery_code with Argon2id -> recovery_code_hash
|                |  update users (recovery_code_hash, recovery_code_salt, encrypted_private_key_recovery) ->>|
|                |<-- ok -----------|                |
|                |                |
|<-- 200 (recovery code stored) --|                |
|                |                |
| [#] Browser displays recovery code ONCE with a print button.
|     User is instructed to print and store securely. Code is NOT shown again.
```

### 13b. Password recovery via recovery code

```
U                S                DB
|                |                |
|-- recover (email, recovery_code) ->>|                |
|                |  find user by email ->>|            |
|                |  verify Argon2id(recovery_code, recovery_salt) == recovery_code_hash ->>|
|                |<-- match? -------|                |
|                |                |
|                |  if no match -> 403 (invalid recovery code)
|                |                |
|                |  return encrypted_private_key_recovery + recovery_salt to browser
|                |<-- row ----------|                |
|                |                |
|<-- 200 {sealedPrivateKeyRecovery, recovery_salt} --|                |
|                |                |
| [#] Browser:
|     deriveKey(PBKDF2, recovery_code, recovery_salt) -> recoveryKey
|     unwrapKey(sealedPrivateKeyRecovery, recoveryKey) -> privateKey  (in memory)
|                |                |
| [#] User sets new password.
| [#] Browser re-seals private key with new password-derived key:
|     deriveKey(PBKDF2, newPassword, new_salt) -> newWrappingKey
|     wrapKey(privateKey, newWrappingKey) -> newSealedPrivateKey
|                |                |
|-- reset_password (new_password_hash, newSealedPrivateKey, new_salt) ->>|
|                |  update users (password, encrypted_private_key, keypair_salt) ->>|
|                |  set recovery_code_used_at = now() (optional tracking)
|                |<-- ok -----------|                |
|                |                |
|<-- 200 (password reset, private key re-sealed) --|                |
|                |                |
| [#] Browser now holds privateKey in memory. User can proceed to use workspaces.
|     Recovery code remains valid for future use (reusable) unless explicitly invalidated.
```

---

## 14. Failure & Edge Cases

| Scenario | Behavior |
|---|---|
| Wrong code entered | 403, no use_count increment (only valid codes increment) |
| Expired code | 403 with "expired" reason |
| Revoked code | 403 with "revoked" reason |
| Code used up (max_uses reached) | 403 with "uses exhausted" |
| User forgets password | User enters recovery code (see §13b). Private key is unsealed via recovery key. User sets new password and re-seals private key. No data loss. |
| Browser tab closed mid-session | DEK lost from memory; user must re-enter code or re-login |
| Owner re-keys while a member is online | Member's next decrypt fails (stale DEK); client re-fetches wrapped_dek, re-unwraps with their private key |
| cPanel disk full mid-upload | 500; partial blob cleaned up by a scheduled janitor command |
| Server breach (DB + FS stolen) | Attacker gets ciphertext + metadata + wrapped keys. No plaintext images. No DEK. Content safe. Metadata (titles, who/when) exposed. |

---

## 15. Sequence Summary (build order mapping)

| Workflow | PRD build step |
|---|---|
| §1 Registration + keypair | Step 1 |
| §2 Login + unseal | Step 1 |
| §3 Create workspace + DEK | Step 2 |
| §4 Generate access code | Step 3 |
| §5 Enter access code | Step 3 |
| §6 Upgrade code → account | Step 3 |
| §7 Upload media | Step 5 |
| §8 View/decrypt media | Step 5 |
| §9 Revoke (soft + hard) | Step 6 |
| §10 Joint gallery co-edit | Step 4 + 5 |
| §11 Super admin | Step 1 (auth) + cross-cutting |
| §12 End-to-end share | Integration of all |
| §13a Recovery code generation | Step 1 |
| §13b Password recovery | Step 1 |
