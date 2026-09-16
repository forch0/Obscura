# Module 6 — Re-key on Revoke (Hard Revocation)

**Phase:** Security
**Depends on:** Modules 2 (Workspaces & DEK), 3 (Access Codes), 5 (Media)
**Status:** Not started

---

## 1. Objective

Implement **hard revocation** — true revocation that blocks even someone who already unwrapped the workspace DEK. Soft revoke (Module 3) only stops *new* access. Hard revoke generates a new DEK, re-wraps all CEKs (file blobs are untouched), and re-seals the DEK for all still-valid principals. All outstanding access codes are invalidated (the owner doesn't know the raw codes to re-seal them).

This is the E2EE revocation completeness step.

## 2. What gets built

- Re-key workspace action (owner-initiated)
- New DEK generation (browser)
- Re-wrap all media CEKs with new DEK (browser → server batch)
- Re-encrypt all workspace/collection/gallery names and media titles/captions with new DEK (browser → server)
- Re-seal new DEK for owner + all workspace members (browser → server)
- Invalidate all outstanding access codes (server-side, automatic)
- `dek_version` increment + `rekeyed_at` timestamp
- Locking during re-key (prevent concurrent modifications)
- Re-key progress UI (batched operation for large galleries)
- Audit log: `workspace.rekeyed`

## 3. No new migrations

Uses existing columns from Modules 2 and 3:
- `workspaces.dek_version` (added in Module 2)
- `workspaces.rekeyed_at` (added in Module 2)
- `workspaces.wrapped_dek_for_owner` (updated)
- `workspace_members.wrapped_dek` (updated per member)
- `media.cek_wrapped` (updated per file)
- `media.encrypted_title`, `media.title_iv`, `media.encrypted_caption`, `media.caption_iv` (re-encrypted per file)
- `collections.encrypted_name`, `collections.name_iv`, `collections.encrypted_description`, `collections.description_iv` (re-encrypted)
- `galleries.encrypted_name`, `galleries.name_iv`, `galleries.encrypted_description`, `galleries.description_iv` (re-encrypted)
- `workspace_access_codes.revoked_at` (set to now() for all outstanding)

### Optional migration: `create_rekey_jobs_table` (for batched re-key tracking)

```php
Schema::create('rekey_jobs', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
    $table->foreignUuid('initiated_by')->constrained('users');
    $table->unsignedInteger('total_media')->default(0);
    $table->unsignedInteger('processed_media')->default(0);
    $table->string('status')->default('pending'); // pending | in_progress | completed | failed
    $table->text('new_wrapped_dek_for_owner')->nullable();
    $table->json('member_wraps')->nullable();      // [{member_id, wrapped_dek}] (member_id is a UUID string)
    $table->json('media_wraps')->nullable();       // [{media_id, cek_wrapped, encrypted_title, title_iv, encrypted_caption, caption_iv}] (batched; media_id is a UUID string)
    $table->json('collection_wraps')->nullable();  // [{collection_id, encrypted_name, name_iv, encrypted_description, description_iv}]
    $table->json('gallery_wraps')->nullable();    // [{gallery_id, encrypted_name, name_iv, encrypted_description, description_iv}]
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();
});
```

The `RekeyJob` model uses the `UsesUuid` trait (UUID primary key).

## 4. Files to create

### Backend
```
app/
  Http/
    Controllers/
      RekeyController.php           (initiate, status, complete)
  Services/
    Rekey/
      RekeyOrchestrator.php         (coordinates the re-key operation)
      RekeyLockService.php          (prevents concurrent re-keys)
  Jobs/
      FinalizeRekeyJob.php          (applies all wraps in a transaction)
  Console/
    Commands/
      StaleRekeyCleanupCommand.php  (cleans up failed/abandoned re-keys)
database/
  migrations/
    yyyy_mm_dd_HHMMSS_create_rekey_jobs_table.php
```

### Frontend
```
resources/
  views/
    workspaces/
      rekey.blade.php               (confirm + progress UI)
  js/
    crypto/
      rekey.js                       (generate new DEK, re-wrap CEKs, re-seal for members)
```

### Routes
```php
Route::middleware(['auth', 'keypair'])->group(function () {
    Route::post('workspaces/{workspace}/rekey', [RekeyController::class, 'initiate']);
    Route::get('workspaces/{workspace}/rekey/status', [RekeyController::class, 'status']);
    Route::post('workspaces/{workspace}/rekey/{job}/complete', [RekeyController::class, 'complete']);
});
```

## 5. Crypto logic (browser-side)

### 5.1 Generate new DEK and re-wrap all CEKs

```javascript
// resources/js/crypto/rekey.js

async function startRekey(oldDekHandle, mediaItems, collectionItems, galleryItems, ownerPublicKeyB64, members) {
  // 1. Generate new DEK
  const newDek = await crypto.subtle.generateKey(
    { name: 'AES-GCM', length: 256 }, true, ['encrypt', 'decrypt']
  );

  // 2. Re-wrap all CEKs with new DEK
  //    CEKs are currently sealed with OLD DEK. We unwrap with old, re-wrap with new.
  //    File blobs are NOT touched.
  const mediaWraps = [];
  for (const media of mediaItems) {
    const cekCombined = fromBase64(media.cek_wrapped);
    const cekIv = cekCombined.slice(0, 12);
    const sealedCek = cekCombined.slice(12);

    // Unwrap CEK with old DEK
    const rawCek = await crypto.subtle.decrypt(
      { name: 'AES-GCM', iv: cekIv }, oldDekHandle, sealedCek
    );

    // Re-wrap CEK with new DEK
    const newCekIv = crypto.getRandomValues(new Uint8Array(12));
    const newSealedCek = await crypto.subtle.encrypt(
      { name: 'AES-GCM', iv: newCekIv }, newDek, rawCek
    );
    const newCekCombined = concat(newCekIv, new Uint8Array(newSealedCek));

    mediaWraps.push({ media_id: media.id, cek_wrapped: base64(newCekCombined) });
  }

  // 2b. Re-encrypt all media text fields (titles, captions) with new DEK
  //     Names/titles/captions are encrypted with the workspace DEK (DECISIONS.md D5).
  //     When the DEK changes, these must be re-encrypted too.
  //     These are small strings so this is fast.
  for (let i = 0; i < mediaItems.length; i++) {
    const media = mediaItems[i];

    // Decrypt title with old DEK, re-encrypt with new DEK
    if (media.encrypted_title) {
      const titleCombined = fromBase64(media.encrypted_title);
      const titleIvOld = titleCombined.slice(0, 12);
      const sealedTitle = titleCombined.slice(12);
      const rawTitle = await crypto.subtle.decrypt(
        { name: 'AES-GCM', iv: titleIvOld }, oldDekHandle, sealedTitle
      );
      const titleIvNew = crypto.getRandomValues(new Uint8Array(12));
      const newSealedTitle = await crypto.subtle.encrypt(
        { name: 'AES-GCM', iv: titleIvNew }, newDek, rawTitle
      );
      mediaWraps[i].encrypted_title = base64(concat(titleIvNew, new Uint8Array(newSealedTitle)));
      mediaWraps[i].title_iv = base64(titleIvNew);
    }

    // Decrypt caption with old DEK, re-encrypt with new DEK
    if (media.encrypted_caption) {
      const captionCombined = fromBase64(media.encrypted_caption);
      const captionIvOld = captionCombined.slice(0, 12);
      const sealedCaption = captionCombined.slice(12);
      const rawCaption = await crypto.subtle.decrypt(
        { name: 'AES-GCM', iv: captionIvOld }, oldDekHandle, sealedCaption
      );
      const captionIvNew = crypto.getRandomValues(new Uint8Array(12));
      const newSealedCaption = await crypto.subtle.encrypt(
        { name: 'AES-GCM', iv: captionIvNew }, newDek, rawCaption
      );
      mediaWraps[i].encrypted_caption = base64(concat(captionIvNew, new Uint8Array(newSealedCaption)));
      mediaWraps[i].caption_iv = base64(captionIvNew);
    }
  }

  // 2c. Re-encrypt all collection names/descriptions with new DEK
  const collectionWraps = [];
  for (const collection of collectionItems) {
    const wrap = { collection_id: collection.id };

    if (collection.encrypted_name) {
      const nameCombined = fromBase64(collection.encrypted_name);
      const nameIvOld = nameCombined.slice(0, 12);
      const sealedName = nameCombined.slice(12);
      const rawName = await crypto.subtle.decrypt(
        { name: 'AES-GCM', iv: nameIvOld }, oldDekHandle, sealedName
      );
      const nameIvNew = crypto.getRandomValues(new Uint8Array(12));
      const newSealedName = await crypto.subtle.encrypt(
        { name: 'AES-GCM', iv: nameIvNew }, newDek, rawName
      );
      wrap.encrypted_name = base64(concat(nameIvNew, new Uint8Array(newSealedName)));
      wrap.name_iv = base64(nameIvNew);
    }

    if (collection.encrypted_description) {
      const descCombined = fromBase64(collection.encrypted_description);
      const descIvOld = descCombined.slice(0, 12);
      const sealedDesc = descCombined.slice(12);
      const rawDesc = await crypto.subtle.decrypt(
        { name: 'AES-GCM', iv: descIvOld }, oldDekHandle, sealedDesc
      );
      const descIvNew = crypto.getRandomValues(new Uint8Array(12));
      const newSealedDesc = await crypto.subtle.encrypt(
        { name: 'AES-GCM', iv: descIvNew }, newDek, rawDesc
      );
      wrap.encrypted_description = base64(concat(descIvNew, new Uint8Array(newSealedDesc)));
      wrap.description_iv = base64(descIvNew);
    }

    collectionWraps.push(wrap);
  }

  // 2d. Re-encrypt all gallery names/descriptions with new DEK
  const galleryWraps = [];
  for (const gallery of galleryItems) {
    const wrap = { gallery_id: gallery.id };

    if (gallery.encrypted_name) {
      const nameCombined = fromBase64(gallery.encrypted_name);
      const nameIvOld = nameCombined.slice(0, 12);
      const sealedName = nameCombined.slice(12);
      const rawName = await crypto.subtle.decrypt(
        { name: 'AES-GCM', iv: nameIvOld }, oldDekHandle, sealedName
      );
      const nameIvNew = crypto.getRandomValues(new Uint8Array(12));
      const newSealedName = await crypto.subtle.encrypt(
        { name: 'AES-GCM', iv: nameIvNew }, newDek, rawName
      );
      wrap.encrypted_name = base64(concat(nameIvNew, new Uint8Array(newSealedName)));
      wrap.name_iv = base64(nameIvNew);
    }

    if (gallery.encrypted_description) {
      const descCombined = fromBase64(gallery.encrypted_description);
      const descIvOld = descCombined.slice(0, 12);
      const sealedDesc = descCombined.slice(12);
      const rawDesc = await crypto.subtle.decrypt(
        { name: 'AES-GCM', iv: descIvOld }, oldDekHandle, sealedDesc
      );
      const descIvNew = crypto.getRandomValues(new Uint8Array(12));
      const newSealedDesc = await crypto.subtle.encrypt(
        { name: 'AES-GCM', iv: descIvNew }, newDek, rawDesc
      );
      wrap.encrypted_description = base64(concat(descIvNew, new Uint8Array(newSealedDesc)));
      wrap.description_iv = base64(descIvNew);
    }

    galleryWraps.push(wrap);
  }

  // 3. Seal new DEK for owner
  const ownerPubKey = await importPublicKey(ownerPublicKeyB64);
  const rawNewDek = await crypto.subtle.exportKey('raw', newDek);
  const wrappedDekForOwner = await crypto.subtle.encrypt(
    { name: 'RSA-OAEP' }, ownerPubKey, rawNewDek
  );

  // 4. Seal new DEK for each member
  const memberWraps = [];
  for (const member of members) {
    const memberPubKey = await importPublicKey(member.public_key);
    const wrappedDek = await crypto.subtle.encrypt(
      { name: 'RSA-OAEP' }, memberPubKey, rawNewDek
    );
    memberWraps.push({ member_id: member.id, wrapped_dek: base64(wrappedDek) });
  }

  return {
    newDekHandle: newDek,                       // keep in memory
    new_wrapped_dek_for_owner: base64(wrappedDekForOwner),
    member_wraps: memberWraps,
    media_wraps: mediaWraps,
    collection_wraps: collectionWraps,
    gallery_wraps: galleryWraps,
  };
}
```

### 5.2 Batched re-wrap (for large galleries)

For workspaces with thousands of media items, re-wrapping all CEKs in one browser pass may be slow or memory-heavy. Use batching:

```javascript
async function startRekeyBatched(oldDekHandle, mediaItems, collectionItems, galleryItems, ownerPublicKeyB64, members, batchCallback, batchSize = 50) {
  const newDek = await generateNewDek();
  const allMediaWraps = [];

  for (let i = 0; i < mediaItems.length; i += batchSize) {
    const batch = mediaItems.slice(i, i + batchSize);
    // rewrapBatch also re-encrypts titles/captions in this batch
    const batchWraps = await rewrapBatch(oldDekHandle, newDek, batch);
    allMediaWraps.push(...batchWraps);

    // Upload this batch to server (server stores but doesn't apply yet)
    await batchCallback(batchWraps, i / batchSize + 1, Math.ceil(mediaItems.length / batchSize));
  }

  // Re-encrypt collection/gallery names/descriptions (small, done in one pass)
  const collectionWraps = await reencryptCollections(oldDekHandle, newDek, collectionItems);
  const galleryWraps = await reencryptGalleries(oldDekHandle, newDek, galleryItems);

  // Seal new DEK for owner + members (same as startRekey)
  const { new_wrapped_dek_for_owner, member_wraps } = await sealNewDek(newDek, ownerPublicKeyB64, members);

  return {
    newDekHandle: newDek,
    new_wrapped_dek_for_owner,
    member_wraps,
    media_wraps: allMediaWraps,
    collection_wraps: collectionWraps,
    gallery_wraps: galleryWraps,
  };
}
```

## 6. Key flows

### 6.1 Initiate re-key

1. Owner navigates to workspace → "Re-key Workspace" (with a clear warning: "This will invalidate all access codes. Members will keep access. This may take a few minutes.").
2. Owner confirms.
3. Server: `RekeyLockService::acquire(workspace_id)` — prevents concurrent re-keys. Returns 409 if already in progress.
4. Server creates `rekey_jobs` row with `status = pending`, `total_media = count`.
5. Server returns to browser:
   - All `media` rows (`id`, `cek_wrapped`, `encrypted_title`, `title_iv`, `encrypted_caption`, `caption_iv` — the CEKs to re-wrap and text fields to re-encrypt)
   - All `collections` rows (`id`, `encrypted_name`, `name_iv`, `encrypted_description`, `description_iv` — names/descriptions to re-encrypt)
   - All `galleries` rows (`id`, `encrypted_name`, `name_iv`, `encrypted_description`, `description_iv` — names/descriptions to re-encrypt)
   - All `workspace_members` with their `public_key`s (to re-seal DEK)
   - Owner's public key
6. Browser (holding `oldDekHandle`) runs `startRekey(...)` or `startRekeyBatched(...)`.
7. Browser POSTs the result to `/workspaces/{ws}/rekey/{job}/complete`.

### 6.2 Complete re-key (server-side, transactional)

```php
class RekeyController
{
    public function complete(Request $request, Workspace $ws, RekeyJob $job)
    {
        // Verify ownership
        abort_unless($request->user()->id === $ws->owner_id, 403);

        DB::transaction(function () use ($ws, $job, $request) {
            // 1. Update workspace DEK + version
            $ws->update([
                'wrapped_dek_for_owner' => $request->input('new_wrapped_dek_for_owner'),
                'dek_version' => $ws->dek_version + 1,
                'rekeyed_at' => now(),
            ]);

            // 2. Update each member's wrapped DEK
            foreach ($request->input('member_wraps') as $wrap) {
                WorkspaceMember::where('id', $wrap['member_id'])
                    ->update(['wrapped_dek' => $wrap['wrapped_dek']]);
            }

            // 3. Update each media's wrapped CEK + re-encrypted text fields
            foreach ($request->input('media_wraps') as $wrap) {
                Media::where('id', $wrap['media_id'])
                    ->update([
                        'cek_wrapped' => $wrap['cek_wrapped'],
                        'encrypted_title' => $wrap['encrypted_title'] ?? null,
                        'title_iv' => $wrap['title_iv'] ?? null,
                        'encrypted_caption' => $wrap['encrypted_caption'] ?? null,
                        'caption_iv' => $wrap['caption_iv'] ?? null,
                    ]);
            }

            // 3b. Update each collection's re-encrypted name/description
            foreach ($request->input('collection_wraps') as $wrap) {
                Collection::where('id', $wrap['collection_id'])
                    ->update([
                        'encrypted_name' => $wrap['encrypted_name'] ?? null,
                        'name_iv' => $wrap['name_iv'] ?? null,
                        'encrypted_description' => $wrap['encrypted_description'] ?? null,
                        'description_iv' => $wrap['description_iv'] ?? null,
                    ]);
            }

            // 3c. Update each gallery's re-encrypted name/description
            foreach ($request->input('gallery_wraps') as $wrap) {
                Gallery::where('id', $wrap['gallery_id'])
                    ->update([
                        'encrypted_name' => $wrap['encrypted_name'] ?? null,
                        'name_iv' => $wrap['name_iv'] ?? null,
                        'encrypted_description' => $wrap['encrypted_description'] ?? null,
                        'description_iv' => $wrap['description_iv'] ?? null,
                    ]);
            }

            // 4. Invalidate ALL outstanding access codes
            WorkspaceAccessCode::where('workspace_id', $ws->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            // 5. Mark job complete
            $job->update(['status' => 'completed', 'completed_at' => now()]);
        });

        // 6. Release lock
        app(RekeyLockService::class)->release($ws->id);

        // 7. Audit log
        AuditLogger::log($request->user(), $ws, 'workspace.rekeyed', [
            'dek_version' => $ws->fresh()->dek_version,
            'codes_revoked' => $job->codes_revoked_count,
        ]);

        return response()->json(['status' => 'completed']);
    }
}
```

### 6.3 What happens to existing sessions

| Principal | Effect of re-key |
|---|---|
| **Owner** | Browser has `newDekHandle` from the re-key operation. Seamless. |
| **Members (online)** | Their browser still holds the OLD DEK. Next decrypt attempt fails (stale CEK wrap). Browser detects mismatch, re-fetches their `wrapped_dek`, re-unwraps with their private key → gets new DEK. |
| **Members (offline)** | On next login/workspace-open, they unwrap the new `wrapped_dek` with their private key. No issue. |
| **Access code holders** | All codes revoked. Their scoped session middleware returns 403 on next request. They must get a new code from the owner. |
| **Super Admin** | No effect (no DEK anyway). |

### 6.4 Failure recovery

If the browser crashes mid-re-key (after some batches uploaded but before `complete`):
- The `rekey_jobs` row stays `in_progress`.
- A cron job (`StaleRekeyCleanupCommand`) runs every 10 min: marks jobs older than 30 min as `failed`, releases the lock.
- The owner can retry; the old DEK is still valid (nothing was applied yet — `complete` is atomic).
- Already-uploaded batch wraps are discarded (the job is deleted on retry).

## 7. Locking

```php
class RekeyLockService
{
    public function acquire(int $workspaceId): bool
    {
        return Cache::lock("rekey:ws:{$workspaceId}", 1800) // 30 min TTL
            ->block(5, fn () => true); // wait 5s to acquire
    }

    public function release(int $workspaceId): void
    {
        Cache::lock("rekey:ws:{$workspaceId}")->forceRelease();
    }
}
```

During the lock:
- New media uploads are rejected (423 Locked) — they'd be encrypted with the old DEK and need re-keying.
- New access code generation is rejected.
- Members can still view (read-only) using their cached DEK.

## 8. Tests

### Feature tests
```
tests/Feature/
  Rekey/
    InitiateRekeyTest.php          — owner can initiate; non-owner 403; double-init 409
    CompleteRekeyTest.php          — DEK rotated; CEKs re-wrapped; members re-sealed
    CodesInvalidatedTest.php       — all outstanding codes revoked_at set
    DekVersionIncrementTest.php    — dek_version increments
    ConcurrentRekeyTest.php        — second re-key during first returns 409
    StaleJobCleanupTest.php        — cron marks old jobs failed; lock released
    MemberReunwrapTest.php         — member with stale DEK re-unwraps successfully
    UploadDuringRekeyTest.php      — upload returns 423 while re-key in progress
    FailureRecoveryTest.php        — browser crash mid-rekey; retry succeeds; old DEK intact
    AuditLogRekeyTest.php          — workspace.rekeyed logged with dek_version + codes_revoked
```

### Unit tests
```
tests/Unit/
  Services/
    RekeyOrchestratorTest.php
    RekeyLockServiceTest.php
```

### JS crypto tests
```
resources/js/crypto/__tests__/
  rekey.test.js — re-wrap CEKs round-trip; new DEK decrypts re-wrapped CEKs; old DEK can't
```

## 9. Acceptance criteria

- [ ] Owner can initiate a re-key from the workspace settings
- [ ] A new DEK is generated in-browser
- [ ] All media CEKs are re-wrapped with the new DEK (file blobs untouched)
- [ ] The new DEK is sealed for the owner and all workspace members
- [ ] `dek_version` is incremented and `rekeyed_at` is set
- [ ] All outstanding access codes are revoked (revoked_at set)
- [ ] Owner's browser seamlessly uses the new DEK after re-key
- [ ] Online members with stale DEK can re-fetch and unwrap the new DEK
- [ ] Offline members get the new DEK on next workspace open
- [ ] Access code holders get 403 on their next request
- [ ] Concurrent re-key attempts return 409
- [ ] Uploads during re-key return 423
- [ ] A browser crash mid-re-key is recoverable (retry works; old DEK intact)
- [ ] Stale re-key jobs are cleaned up by cron
- [ ] Audit log records the re-key with dek_version and codes_revoked count
- [ ] All encrypted text fields (names, descriptions, titles, captions) are re-encrypted with the new DEK during re-key
- [ ] After re-key, all names/titles decrypt correctly with the new DEK
- [ ] Recovery codes and recovery-sealed private keys are unaffected by re-key
- [ ] All tests pass

## 10. Notes & decisions

- **Why file blobs are untouched:** each file's CEK is unchanged — only the wrapping (seal) of the CEK changes. The CEK is re-wrapped with the new DEK instead of the old. This makes re-key fast (small CEKs vs large media files).
- **Why access codes are invalidated:** the owner doesn't know the raw codes (only the hash is stored), so they can't re-seal the new DEK for those codes. The owner must regenerate any codes they want to keep. This is an inherent E2EE tradeoff — documented in PRD §9b and WORKFLOWS §9b.
- **Re-key does not affect recovery codes or recovery-sealed private keys.** The private key is per-user and unchanged by workspace re-keying. Only the workspace DEK and CEK wraps change. The recovery-sealed copy of the private key (DECISIONS.md D2) is sealed with the user's recovery code, not the workspace DEK, so it is unaffected.
- **Encrypted text fields re-encrypted:** per DECISIONS.md D5, workspace/collection/gallery names and media titles/captions are encrypted with the workspace DEK. When the DEK changes during re-key, these fields must also be re-encrypted with the new DEK. The browser decrypts each field with the old DEK and re-encrypts with the new DEK.
- **Atomicity:** the `complete` endpoint applies all changes in a single DB transaction. Either everything updates or nothing does. The old DEK remains valid until `complete` succeeds.
- **Performance:** re-wrapping CEKs is CPU-bound browser work. For 1,000 images, each CEK is ~32 bytes — the unwrap+rewrap is sub-millisecond per item. Total browser time: a few seconds. Network: ~1,000 small JSON updates. Batched at 50/request = 20 requests. Acceptable. Re-encrypting text fields is fast (small strings, AES-GCM). For 1,000 media items + 50 collections + 100 galleries, total text re-encryption is <100ms. The dominant cost is still CEK re-wrapping (also fast). Network upload is the bottleneck — batched as before.
- **Locking mechanism:** cache-based lock (Redis or file cache on cPanel). TTL 30 min prevents permanent locks. Cron cleans up stale locks.
- **cPanel consideration:** `QUEUE_CONNECTION=sync` means `FinalizeRekeyJob` runs inline. For large workspaces, this could hit PHP `max_execution_time`. Mitigation: the `complete` endpoint does pure DB updates (no heavy compute), so it's fast even for thousands of rows. The heavy crypto is done in the browser.

## 11. Open items

- [x] All decisions resolved — see DECISIONS.md. Re-key invalidates all access codes (confirmed). Two-step confirmation required (type workspace name). Batch size: 50 (configurable). No member notification on re-key (transparent re-unwrap).
