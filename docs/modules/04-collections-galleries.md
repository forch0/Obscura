# Module 4 — Collections & Galleries

**Phase:** Organization
**Depends on:** Module 2 (Workspaces & DEK)
**Status:** Not started

---

## 1. Objective

Add the organizational hierarchy below workspaces: Collections (groupings within a workspace) and Galleries (image containers within collections). Galleries have a `type` — `private`, `shared`, or `joint` — that controls collaboration. This module also introduces `gallery_members` for shared/joint gallery grants and extends the authorization policy chain.

## 2. What gets built

- Collection CRUD (create, list, show, rename, delete) — owner only
- Gallery CRUD (create, list, show, rename, delete) — owner only
- Gallery types: `private` | `shared` | `joint`
- Gallery members management (for shared/joint galleries)
- Policy chain extension: `CollectionPolicy`, `GalleryPolicy`
- Access-code scope enforcement for collections and galleries (from Module 3)
- Nested navigation: Workspace → Collection → Gallery

## 3. Database schema

### Migration: `create_collections_table`

```php
Schema::create('collections', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
    $table->text('encrypted_name');
    $table->text('name_iv');
    $table->text('encrypted_description')->nullable();
    $table->text('description_iv')->nullable();
    $table->timestamps();
    $table->index('workspace_id');
});
```

### Migration: `create_galleries_table`

```php
Schema::create('galleries', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('collection_id')->constrained('collections')->cascadeOnDelete();
    $table->text('encrypted_name');
    $table->text('name_iv');
    $table->enum('type', ['private', 'shared', 'joint'])->default('private');
    $table->text('encrypted_description')->nullable();
    $table->text('description_iv')->nullable();
    $table->timestamps();
    $table->index(['collection_id', 'type']);
});
```

### Migration: `create_gallery_members_table`

```php
Schema::create('gallery_members', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('gallery_id')->constrained('galleries')->cascadeOnDelete();
    $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
    $table->string('role');                   // editor | viewer
    $table->timestamps();
    $table->unique(['gallery_id', 'user_id']);
});
```

### Final tables

**`collections`**

| Column | Type | Notes |
|---|---|---|
| `id` | uuid (PK) | |
| `workspace_id` | uuid FK → workspaces | cascade |
| `encrypted_name` | text | ciphertext (AES-GCM) |
| `name_iv` | text | initialization vector |
| `encrypted_description` | text, nullable | ciphertext (AES-GCM) |
| `description_iv` | text, nullable | initialization vector |
| timestamps | | |

**`galleries`**

| Column | Type | Notes |
|---|---|---|
| `id` | uuid (PK) | |
| `collection_id` | uuid FK → collections | cascade |
| `encrypted_name` | text | ciphertext (AES-GCM) |
| `name_iv` | text | initialization vector |
| `type` | enum | `private` \| `shared` \| `joint` |
| `encrypted_description` | text, nullable | ciphertext (AES-GCM) |
| `description_iv` | text, nullable | initialization vector |
| timestamps | | |

**`gallery_members`**

| Column | Type | Notes |
|---|---|---|
| `id` | uuid (PK) | |
| `gallery_id` | uuid FK → galleries | cascade |
| `user_id` | uuid FK → users | cascade |
| `role` | string | `editor` \| `viewer` |
| timestamps | | |
| unique | `(gallery_id, user_id)` | |

## 4. Files to create

### Backend
```
app/
  Models/
    Collection.php                 (uses UsesUuid trait)
    Gallery.php                    (uses UsesUuid trait)
    GalleryMember.php              (uses UsesUuid trait)
  Traits/
    UsesUuid.php                   (shared UUID primary-key trait)
  Http/
    Controllers/
      CollectionController.php
      GalleryController.php
      GalleryMemberController.php
  Policies/
    CollectionPolicy.php
    GalleryPolicy.php
  Services/
    Authorization/
      AuthorizationResolver.php   (centralizes first-match-wins logic)
database/
  migrations/
    yyyy_mm_dd_HHMMSS_create_collections_table.php
    yyyy_mm_dd_HHMMSS_create_galleries_table.php
    yyyy_mm_dd_HHMMSS_create_gallery_members_table.php
  factories/
    CollectionFactory.php
    GalleryFactory.php
    GalleryMemberFactory.php
```

### Frontend
```
resources/
  js/
    crypto/
      name-encrypt.js              (encrypt/decrypt collection & gallery names/descriptions)
  views/
    collections/
      index.blade.php             (list collections in a workspace)
      show.blade.php               (collection detail + nested galleries)
      create.blade.php
      edit.blade.php
    galleries/
      show.blade.php               (gallery grid — placeholder until Module 5)
      create.blade.php
      edit.blade.php
      members.blade.php            (manage gallery members for shared/joint)
```

### Routes
```php
Route::middleware(['auth', 'keypair'])->group(function () {
    // Collections
    Route::resource('workspaces.{workspace}.collections', CollectionController::class);

    // Galleries (nested under collections)
    Route::resource('collections.{collection}.galleries', GalleryController::class);

    // Gallery members (shared/joint only)
    Route::post('galleries/{gallery}/members', [GalleryMemberController::class, 'store']);
    Route::put('galleries/{gallery}/members/{member}', [GalleryMemberController::class, 'update']);
    Route::delete('galleries/{gallery}/members/{member}', [GalleryMemberController::class, 'destroy']);
});
```

## 5. Crypto logic (browser-side)

Collection and gallery names and descriptions are encrypted client-side with the workspace DEK (see Module 2). The server only ever stores ciphertext and IV columns; it never sees plaintext.

```javascript
// resources/js/crypto/name-encrypt.js

async function encryptName(name, dekHandle) {
  const iv = crypto.getRandomValues(new Uint8Array(12));
  const enc = new TextEncoder();
  const ciphertext = await crypto.subtle.encrypt(
    { name: 'AES-GCM', iv }, dekHandle, enc.encode(name)
  );
  return { encryptedName: base64(concat(iv, new Uint8Array(ciphertext))), nameIv: base64(iv) };
}

async function decryptName(encryptedNameB64, dekHandle) {
  const combined = fromBase64(encryptedNameB64);
  const iv = combined.slice(0, 12);
  const ciphertext = combined.slice(12);
  const plaintext = await crypto.subtle.decrypt(
    { name: 'AES-GCM', iv }, dekHandle, ciphertext
  );
  return new TextDecoder().decode(plaintext);
}

// Same pattern for description (encryptDescription / decryptDescription)
```

The DEK handle is already in memory after the workspace is opened (Module 2), so encrypt/decrypt calls add no extra key-entry step. Listing/navigation views decrypt each name before rendering. Because names are encrypted at rest, the server cannot perform server-side search or sort by name; all search and filtering is done client-side after decryption.

## 6. Authorization (policy chain)

### AuthorizationResolver (centralizes first-match-wins)

```php
class AuthorizationResolver
{
    /**
     * Resolve access for a given subject. First match wins.
     * Order: SuperAdmin → Owner → Member → AccessCode → deny.
     */
    public function check(User $user, Model $subject, string $ability): bool
    {
        // 1. Super Admin
        if ($user->is_super_admin) return true;

        // 2. Workspace Owner (owns the workspace that the subject belongs to)
        $workspace = $this->workspaceOf($subject);
        if ($workspace && $user->id === $workspace->owner_id) return true;

        // 3. Workspace Member (per workspace role + gallery grants)
        if ($this->isMember($user, $workspace)) {
            return $this->checkMemberAbility($user, $subject, $ability);
        }

        // 4. Access Code session (injected by middleware)
        if (request()->has('access_code')) {
            return $this->checkCodeAbility(request()->input('access_code'), $subject, $ability);
        }

        return false;
    }

    private function checkMemberAbility(User $user, Model $subject, string $ability): bool
    {
        // For galleries: check gallery_members role
        if ($subject instanceof Gallery) {
            $member = $subject->members()->where('user_id', $user->id)->first();
            if (!$member) {
                // Not a gallery member → can they view? Only if gallery is not private.
                return $subject->type !== 'private' && $ability === 'view';
            }
            return match ($ability) {
                'view'   => true,
                'upload', 'edit', 'delete' => $member->role === 'editor',
                default  => false,
            };
        }
        // Collections: members can view; edit/delete is owner-only (handled above)
        return $ability === 'view';
    }

    private function checkCodeAbility(WorkspaceAccessCode $code, Model $subject, string $ability): bool
    {
        // Check scope matches subject
        if (!$this->codeCoversSubject($code, $subject)) return false;

        // Check permission bitmask
        return match ($ability) {
            'view'    => $code->hasPermission('view'),
            'upload'  => $code->hasPermission('upload'),
            'comment' => $code->hasPermission('comment'),
            default   => false,
        };
    }
}
```

### Gallery type semantics

| Type | Owner | Gallery Member (editor) | Gallery Member (viewer) | Workspace Member (not gallery member) | Access Code |
|---|---|---|---|---|---|
| `private` | full | — | — | no access | no access (unless scoped) |
| `shared` | full | — | view | view (if workspace member) | per code perms |
| `joint` | full | upload/edit/delete | view | view (if workspace member) | per code perms |

> `private` galleries are owner-only. Adding gallery_members to a private gallery is a no-op (or rejected). `shared` allows view-only members. `joint` allows editor members.
>
> Super Admin sees only encrypted blobs for collection and gallery names — they cannot browse by name. The server cannot perform server-side search or sort on encrypted names; all search and filtering is client-side after the browser decrypts names with the workspace DEK.

## 7. Key flows

### 7.1 Create collection
1. Owner navigates to a workspace → "New Collection".
2. Submits name + description.
3. Browser encrypts name + description with the workspace DEK (see section 5) before sending to the server.
4. `CollectionPolicy::create()` checks owner (or Super Admin).
5. Server stores ciphertext only (`encrypted_name`, `name_iv`, `encrypted_description`, `description_iv`). Audit log: `collection.created`.

### 7.2 Create gallery
1. Owner navigates to a collection → "New Gallery".
2. Submits name, type (`private`/`shared`/`joint`), description.
3. Browser encrypts name + description with the workspace DEK before sending to the server.
4. `GalleryPolicy::create()` checks owner.
5. Server stores ciphertext only. Audit log: `gallery.created`.

### 7.3 Add gallery member (shared/joint only)
1. Owner navigates to a shared/joint gallery → "Manage Members".
2. Searches for a workspace member by email.
3. Selects role (`editor` for joint, `viewer` for shared).
4. Server creates `gallery_members` row. Audit log: `gallery.member_added`.

### 7.4 View gallery (member)
1. Workspace member navigates to a collection → gallery.
2. `AuthorizationResolver` checks: is the gallery private? If so, deny unless owner.
3. If shared/joint: member can view. If gallery_member with editor role + joint type: can upload/edit.
4. Browser decrypts collection and gallery names (and descriptions) with the workspace DEK before rendering navigation/listing views.
5. Gallery grid renders (placeholder thumbnails until Module 5).

### 7.5 View gallery (access code)
1. Code-holder navigates within their scope.
2. `checkCodeAbility` verifies the code's scope covers this gallery (or its collection/workspace).
3. Permission bitmask checked against the requested ability.
4. Code-holder has the DEK (obtained during access-code entry), so the browser decrypts collection and gallery names with the DEK before rendering.
5. If allowed, gallery renders.

## 8. Tests

### Feature tests
```
tests/Feature/
  Collections/
    CreateCollectionTest.php       — owner creates; non-owner 403
    ListCollectionsTest.php        — owner + members see; strangers don't
    UpdateCollectionTest.php       — owner only
    DeleteCollectionTest.php       — owner only; cascades to galleries
  Galleries/
    CreateGalleryTest.php          — all three types
    ViewGalleryTest.php            — private: owner only; shared/joint: members
    JointGalleryEditTest.php       — editor member can upload (mock); viewer can't
    GalleryMembersTest.php         — add/remove/role change
    DeleteGalleryTest.php          — owner only; cascades
  AccessCodeScoping/
    CollectionScopedCodeTest.php   — code sees only its collection's galleries
    GalleryScopedCodeTest.php      — code sees only one gallery
    WrongScopeCodeTest.php         — code scoped to gallery A can't see gallery B
  Encryption/
    NameEncryptionTest.php         — name/description encryption round-trip (encrypt then decrypt)
    ServerNeverSeesPlaintextTest.php — server stores only ciphertext; plaintext never in request/DB
    SuperAdminSeesEncryptedBlobsTest.php — Super Admin cannot read collection/gallery names
```

### Unit tests
```
tests/Unit/
  Policies/
    CollectionPolicyTest.php
    GalleryPolicyTest.php
  Services/
    AuthorizationResolverTest.php   — all first-match-wins paths
  Crypto/
    NameEncryptTest.php             — encryptName/decryptName round-trip; decryptDescription round-trip
```

## 9. Acceptance criteria

- [ ] Owner can create collections and galleries within a workspace
- [ ] Galleries can be private, shared, or joint
- [ ] Owner can add/remove gallery members for shared/joint galleries
- [ ] Private galleries are owner-only (members + code-holders get 403)
- [ ] Shared galleries: members can view; only owner can edit
- [ ] Joint galleries: editor members can upload/edit; viewer members can view
- [ ] Access codes scoped to a collection only cover that collection's galleries
- [ ] Access codes scoped to a gallery only cover that one gallery
- [ ] Deleting a collection cascades to its galleries
- [ ] Deleting a gallery cascades to its members (and media in Module 5)
- [ ] AuthorizationResolver enforces first-match-wins correctly (all paths tested)
- [ ] Collection and gallery names are encrypted at rest with the workspace DEK
- [ ] Server never sees plaintext names or descriptions
- [ ] Browser decrypts names before rendering navigation/listing views
- [ ] Super Admin sees only encrypted blobs for names (cannot read them)
- [ ] All tests pass

## 10. Notes & decisions

- **Names and descriptions are encrypted:** per DECISIONS.md D5, collection and gallery names and descriptions are encrypted at rest with the workspace DEK. The server stores only ciphertext (`encrypted_name`, `name_iv`, `encrypted_description`, `description_iv`) and never sees plaintext. Listing/navigation views require the browser to decrypt names with the DEK, which is already in memory after the workspace is opened (Module 2) — no extra key-entry step. Because names are encrypted at rest, server-side search by name is not possible; all search and filtering is client-side after decryption.
- **Gallery members vs workspace members:** gallery_members must be a subset of workspace_members. You can't grant gallery access to someone who isn't a workspace member. Enforced by a validation rule on `GalleryMemberController::store`.
- **Access codes and galleries:** a code scoped to `collection` implicitly covers all galleries in that collection. A code scoped to `gallery` covers only that gallery. `checkCodeAbility` walks up the hierarchy to verify coverage.

## 11. Open items

- All decisions resolved — see DECISIONS.md. Collection/gallery names and descriptions are encrypted with the workspace DEK. Shared galleries: explicit gallery_members only (not auto-shared to all workspace members).
