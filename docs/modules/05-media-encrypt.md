# Module 5 — Media Upload & Decrypt

**Phase:** Content
**Depends on:** Modules 2 (Workspaces & DEK), 4 (Collections & Galleries)
**Status:** Not started

---

## 1. Objective

Enable authorized users (owners, editor members, or access-code holders with upload permission) to upload images that are **encrypted client-side** before they ever reach the server. The server stores only ciphertext blobs and sealed keys. On view, the server streams ciphertext to the browser, which decrypts and renders. The server is blind to image content at all times.

## 2. What gets built

- Media upload (client-side encrypt → upload ciphertext + sealed CEK)
- Media listing (plaintext metadata for grid rendering)
- Media viewing (stream ciphertext → browser decrypts → render)
- Media deletion (owner / joint-gallery editor)
- Media rename (metadata only — no re-encryption needed)
- Thumbnail generation (client-side, encrypted, stored alongside original)
- File blob storage in `storage/app/private/` (outside web root)
- Streamed download via PHP response (after authz check)
- Optional: in-browser download-to-disk (decrypt → save), gated by viewer download permission

## 3. Database schema

### Migration: `create_media_table`

```php
Schema::create('media', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('gallery_id')->constrained('galleries')->cascadeOnDelete();
    $table->foreignUuid('uploaded_by')->constrained('users')->cascadeOnDelete();
    $table->string('blob_path');              // relative path in storage/app/private/
    $table->string('thumb_path')->nullable(); // encrypted thumbnail path
    $table->text('cek_wrapped');              // CEK sealed with workspace DEK (base64)
    $table->string('iv');                     // AES-GCM IV for the blob (base64)
    $table->string('thumb_iv')->nullable();   // IV for thumbnail
    $table->string('mime_type');              // plaintext for Content-Type on stream
    $table->unsignedBigInteger('size');       // ciphertext size (bytes)
    $table->text('encrypted_title')->nullable(); // title encrypted with workspace DEK
    $table->text('title_iv')->nullable();        // IV for encrypted title
    $table->text('encrypted_caption')->nullable(); // caption encrypted with workspace DEK
    $table->text('caption_iv')->nullable();       // IV for encrypted caption
    $table->timestamps();
    $table->index('gallery_id');
    $table->index('uploaded_by');
});
```

### Final `media` table

| Column | Type | Notes |
|---|---|---|
| `id` | uuid (PK) | |
| `gallery_id` | uuid FK → galleries | cascade |
| `uploaded_by` | uuid FK → users | cascade |
| `blob_path` | string | path in `storage/app/private/` |
| `thumb_path` | string, nullable | encrypted thumbnail path |
| `cek_wrapped` | text | CEK sealed with workspace DEK |
| `iv` | string | AES-GCM IV for blob |
| `thumb_iv` | string, nullable | IV for thumbnail |
| `mime_type` | string | plaintext (needed for streaming) |
| `size` | bigint | ciphertext size |
| `encrypted_title` | text, nullable | title encrypted with workspace DEK |
| `title_iv` | text, nullable | IV for encrypted title |
| `encrypted_caption` | text, nullable | caption encrypted with workspace DEK |
| `caption_iv` | text, nullable | IV for encrypted caption |
| timestamps | | |

## 4. Files to create

### Backend
```
app/
  Models/
    Media.php                       (uses App\Traits\UsesUuid)
  Traits/
    UsesUuid.php                    (UUID primary key trait)
  Http/
    Controllers/
      MediaController.php          (store, index, show, update, destroy, blob)
  Policies/
    MediaPolicy.php
  Services/
    Storage/
      EncryptedBlobStorage.php     (write/read ciphertext to private disk)
database/
  migrations/
    yyyy_mm_dd_HHMMSS_create_media_table.php
  factories/
    MediaFactory.php
```

### Frontend
```
resources/
  views/
    galleries/
      show.blade.php               (update: render grid with thumbnails)
      media/
        show.blade.php             (full-size view)
  js/
    crypto/
      media-encrypt.js             (encrypt file → ciphertext + CEK + IV)
      media-decrypt.js             (decrypt blob → object URL)
      thumbnails.js                (generate encrypted thumbnail)
```

### Routes
```php
Route::middleware(['auth', 'keypair'])->group(function () {
    Route::post('galleries/{gallery}/media', [MediaController::class, 'store']);
    Route::get('galleries/{gallery}/media', [MediaController::class, 'index']);
    Route::get('media/{medium}', [MediaController::class, 'show']);
    Route::put('media/{medium}', [MediaController::class, 'update']);
    Route::delete('media/{medium}', [MediaController::class, 'destroy']);
    Route::get('media/{medium}/blob', [MediaController::class, 'blob']);
    Route::get('media/{medium}/thumbnail', [MediaController::class, 'thumbnail']);
});
```

## 5. Crypto logic (browser-side)

### 5.1 Encrypt a file for upload

```javascript
// resources/js/crypto/media-encrypt.js

async function encryptFile(file, dekHandle) {
  // 1. Generate unique CEK + IV
  const cek = await crypto.subtle.generateKey(
    { name: 'AES-GCM', length: 256 }, true, ['encrypt', 'decrypt']
  );
  const iv = crypto.getRandomValues(new Uint8Array(12));

  // 2. Read file bytes
  const plaintext = await file.arrayBuffer();

  // 3. Encrypt with CEK
  const ciphertext = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, cek, plaintext);

  // 4. Wrap CEK with workspace DEK
  const rawCek = await crypto.subtle.exportKey('raw', cek);
  const cekIv = crypto.getRandomValues(new Uint8Array(12));
  const cekWrapped = await crypto.subtle.encrypt(
    { name: 'AES-GCM', iv: cekIv }, dekHandle, rawCek
  );
  // Prepend cekIv to cekWrapped for storage
  const cekCombined = concat(cekIv, new Uint8Array(cekWrapped));

  // 5. Generate encrypted thumbnail (resize → encrypt with same CEK + new IV)
  const thumbCiphertext = await generateEncryptedThumbnail(file, cek);

  // 6. Encrypt title and caption with the DEK
  const titleEnc = await encryptTextField(file.name, dekHandle);
  const captionEnc = await encryptTextField('', dekHandle);

  return {
    ciphertext: new Blob([ciphertext]),
    cekWrapped: base64(cekCombined),
    iv: base64(iv),
    thumbCiphertext: thumbCiphertext.blob,
    thumbIv: thumbCiphertext.iv,
    mimeType: file.type,
    size: ciphertext.byteLength,
    encryptedTitle: titleEnc.ciphertext,
    titleIv: titleEnc.iv,
    encryptedCaption: captionEnc.ciphertext,
    captionIv: captionEnc.iv,
  };
}

async function encryptTextField(plaintext, dekHandle) {
  const iv = crypto.getRandomValues(new Uint8Array(12));
  const encoded = new TextEncoder().encode(plaintext);
  const ciphertext = await crypto.subtle.encrypt(
    { name: 'AES-GCM', iv }, dekHandle, encoded
  );
  return { ciphertext: base64(ciphertext), iv: base64(iv) };
}

async function generateEncryptedThumbnail(file, cek) {
  // Use Canvas to resize to max 200x200
  const bitmap = await createImageBitmap(file);
  const canvas = new OffscreenCanvas(200, 200);
  const ctx = canvas.getContext('2d');
  // ... draw scaled ...
  const thumbBlob = await canvas.convertToBlob({ type: 'image/jpeg', quality: 0.7 });
  const thumbBytes = await thumbBlob.arrayBuffer();
  const thumbIv = crypto.getRandomValues(new Uint8Array(12));
  const thumbCiphertext = await crypto.subtle.encrypt(
    { name: 'AES-GCM', iv: thumbIv }, cek, thumbBytes
  );
  return { blob: new Blob([thumbCiphertext]), iv: base64(thumbIv) };
}
```

### 5.2 Decrypt a blob for viewing

```javascript
// resources/js/crypto/media-decrypt.js

async function decryptMedia(blob, cekWrappedB64, ivB64, dekHandle) {
  // 1. Unwrap CEK with workspace DEK
  const cekCombined = fromBase64(cekWrappedB64);
  const cekIv = cekCombined.slice(0, 12);
  const sealedCek = cekCombined.slice(12);
  const rawCek = await crypto.subtle.decrypt(
    { name: 'AES-GCM', iv: cekIv }, dekHandle, sealedCek
  );
  const cek = await crypto.subtle.importKey(
    'raw', rawCek, { name: 'AES-GCM', length: 256 }, false, ['decrypt']
  );

  // 2. Decrypt blob
  const ciphertext = await blob.arrayBuffer();
  const iv = fromBase64(ivB64);
  const plaintext = await crypto.subtle.decrypt({ name: 'AES-GCM', iv }, cek, ciphertext);

  // 3. Create object URL for rendering
  return URL.createObjectURL(new Blob([plaintext]));
}
```

## 6. Key flows

### 6.1 Upload media

1. User selects a file in a gallery where they have `upload` permission.
2. Browser runs `encryptFile(file, dekHandle)` → produces ciphertext + sealed CEK + IV + thumbnail.
3. Browser POSTs (multipart or raw body):
   - `ciphertext` (file blob)
   - `thumbCiphertext` (file blob)
   - `cekWrapped`, `iv`, `thumbIv` (form fields)
   - `mimeType` (form field)
   - `encrypted_title`, `title_iv`, `encrypted_caption`, `caption_iv` (form fields — ciphertext, browser encrypts title/caption with the DEK)
4. Server: `MediaPolicy::create()` checks upload permission (owner / joint-editor / code with upload perm in scope).
5. Server: `EncryptedBlobStorage::write()` stores ciphertext + thumbnail to `storage/app/private/{workspace_id}/{gallery_id}/{media_id}.enc` and `..._thumb.enc`.
6. Server inserts `media` row with `blob_path`, `thumb_path`, `cek_wrapped`, IVs, and ciphertext columns (`encrypted_title`, `title_iv`, `encrypted_caption`, `caption_iv`). Server stores only ciphertext — never plaintext title/caption.
7. Audit log: `media.uploaded`.
8. Server returns `{ media_id }`.

### 6.2 List media (gallery view)

1. User opens a gallery.
2. `MediaController::index()` checks view permission.
3. Server returns media rows: `{ id, encrypted_title, title_iv, encrypted_caption, caption_iv, mime_type, size, uploaded_by, created_at }` — **ciphertext metadata (title/caption) + plaintext operational metadata (`mime_type`, `size`); no media blob**. `mime_type` and `size` remain plaintext (needed for display/streaming).
4. Browser decrypts `encrypted_title` / `encrypted_caption` with the DEK before rendering the grid. Browser renders the grid. For thumbnails, browser requests `/media/{id}/thumbnail` per item (or batched).

### 6.3 View thumbnail / full media

1. Browser requests `/media/{id}/thumbnail` (or `/media/{id}/blob` for full).
2. Server: `MediaPolicy::view()` checks permission.
3. Server streams the ciphertext blob from `storage/app/private/` with a custom response (no `Content-Type: image/*` — it's ciphertext, so `application/octet-stream`).
4. Server also returns `cek_wrapped` + `iv` in response headers or a parallel JSON call.
5. Browser runs `decryptMedia(blob, cekWrapped, iv, dekHandle)` → object URL → `<img src>`.

### 6.4 Delete media

1. Owner or joint-gallery editor clicks delete.
2. `MediaPolicy::delete()` checks permission.
3. Server deletes blob files from disk + `media` row.
4. Audit log: `media.deleted`.

### 6.5 Rename media (metadata only)

1. Owner or editor submits new title/caption. Browser re-encrypts the new title/caption with the DEK before sending.
2. `MediaPolicy::update()` checks permission.
3. Browser sends `encrypted_title` + `title_iv` (and optionally `encrypted_caption` + `caption_iv`). Server updates the ciphertext columns — no re-encryption of the media blob needed (metadata is re-encrypted client-side, blob untouched).
4. Audit log: `media.renamed`.

## 7. Storage layout

```
storage/app/private/
  {workspace_id}/
    {gallery_id}/
      {media_id}.enc          (encrypted file blob)
      {media_id}_thumb.enc    (encrypted thumbnail)
```

- Files are outside the web root → not directly accessible via URL.
- Served only through `MediaController::blob()` after authz.
- `EncryptedBlobStorage` service abstracts read/write so we can swap backends later (S3, etc.).

## 8. Authorization (MediaPolicy)

```php
class MediaPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->is_super_admin) return false; // Super Admin CANNOT view media (no DEK)
        return null;
    }

    public function view(User $user, Media $media): bool
    {
        $gallery = $media->gallery;
        return app(AuthorizationResolver::class)->check($user, $gallery, 'view');
    }

    public function create(User $user, Gallery $gallery): bool
    {
        // Upload: owner, joint-editor, or code with upload perm
        return app(AuthorizationResolver::class)->check($user, $gallery, 'upload');
    }

    public function update(User $user, Media $media): bool
    {
        return app(AuthorizationResolver::class)->check($user, $media->gallery, 'upload');
    }

    public function delete(User $user, Media $media): bool
    {
        return app(AuthorizationResolver::class)->check($user, $media->gallery, 'upload');
    }
}
```

> **Super Admin `before()` returns `false`** for media — not `true`. This is the one place where Super Admin is explicitly denied. They have no DEK, so even if they could fetch the blob, they can't decrypt it. Returning `false` prevents them from seeing metadata too (defense in depth).

## 9. Tests

### Feature tests
```
tests/Feature/
  Media/
    UploadMediaTest.php            — encrypt + upload; ciphertext stored; metadata saved
    ListMediaTest.php              — metadata returned; no ciphertext in listing
    ViewMediaTest.php              — blob streamed; authz enforced
    DeleteMediaTest.php            — owner + editor; viewer 403
    RenameMediaTest.php            — metadata update; no re-encryption
    ThumbnailTest.php              — thumbnail generated + encrypted + served
    SuperAdminMediaTest.php        — Super Admin 403 on media (no DEK)
    AccessCodeUploadTest.php       — code with upload perm can upload; view-only can't
    AccessCodeViewTest.php         — code with view perm can view; no-upload can't upload
    StoragePathTest.php            — blobs stored outside web root
    PlaintextNeverOnServerTest.php — server never sees plaintext title/caption (only ciphertext columns stored)
```

### Unit tests
```
tests/Unit/
  Services/
    EncryptedBlobStorageTest.php   — write/read/delete; path safety
```

### JS crypto tests
```
resources/js/crypto/__tests__/
  media-encrypt.test.js — encrypt → decrypt round-trip; thumbnail generation; title/caption encryption/decryption round-trip
  media-decrypt.test.js — decrypt with correct DEK; wrong DEK fails
```

## 10. Acceptance criteria

- [ ] Authorized user can upload an image; it's encrypted client-side before upload
- [ ] Server stores only ciphertext + sealed CEK + IV (no plaintext image on server)
- [ ] Gallery grid renders using plaintext metadata (no decryption needed for listing)
- [ ] Media title and caption are encrypted at rest with the workspace DEK
- [ ] Server never sees plaintext title or caption
- [ ] Gallery grid decrypts titles/captions client-side before rendering
- [ ] Thumbnails are generated client-side, encrypted, and served via authz-gated endpoint
- [ ] Full image is streamed as ciphertext and decrypted in-browser for rendering
- [ ] Owner can delete any media in their workspace
- [ ] Joint-gallery editor can upload/delete/rename in their gallery
- [ ] Shared-gallery viewer can view but not upload/delete
- [ ] Access code with `upload` permission can upload within scope
- [ ] Access code with `view`-only cannot upload
- [ ] Super Admin cannot view media (403 — no DEK)
- [ ] Blobs are stored outside the web root; not directly URL-accessible
- [ ] Plaintext image content never appears in any request, response, log, or DB row
- [ ] All tests pass

## 11. Notes & decisions

- **Thumbnails:** generated client-side via Canvas/OffscreenCanvas, encrypted with the same CEK as the original (different IV). This avoids the server ever touching plaintext image data. Tradeoff: no server-side thumbnail generation (which would need the DEK). Acceptable for v1.
- **MIME type in plaintext:** `mime_type` and `size` remain plaintext (operational metadata needed for streaming/display). In contrast, `title` and `caption` are encrypted (content metadata) — the server never sees plaintext title or caption. The MIME type is needed so the browser knows how to render after decryption and so the server can set `Content-Type` on the ciphertext stream (though it's `application/octet-stream` for the ciphertext itself; the MIME type is metadata for the browser's post-decrypt handling).
- **Large files:** v1 uploads the entire file in one request. v2 should add chunked/resumable upload for large images. For now, rely on PHP `upload_max_filesize` / `post_max_size` limits.
- **Streaming:** `MediaController::blob()` uses Laravel's `StreamedResponse` to stream the ciphertext file without loading it all into memory.
- **Object URL lifecycle:** `URL.createObjectURL` URLs should be revoked (`URL.revokeObjectURL`) when the image is no longer displayed to avoid memory leaks.

## 12. Open items

- All decisions resolved — see DECISIONS.md. Client-side thumbnail generation confirmed. Max upload size: 25MB (configurable). Caption is encrypted with workspace DEK. Viewer download: configurable per workspace, default off.
