# Obscura — Capabilities Matrix

**Companion to:** `PRD.md`, `WORKFLOWS.md`
**Purpose:** A single-page reference listing exactly what each role is permitted to do. Nothing implied — if it's not listed here, the role cannot do it.

> All design decisions are resolved — see DECISIONS.md. Key decisions affecting capabilities: D2 (recovery codes enable password recovery without data loss), D5 (all names encrypted — Super Admin cannot read names), D6 (UUIDs prevent ID enumeration).

---

## Roles at a glance

| Role | Who they are | How they authenticate |
|---|---|---|
| **Super Admin** | Platform operator | Account (email + password) |
| **Workspace Owner** | Creator/owner of a workspace | Account (email + password) |
| **Workspace Member (Editor)** | Invited long-term collaborator | Account (email + password) |
| **Workspace Member (Viewer)** | Invited long-term observer | Account (email + password) |
| **Access-Code Holder** | Temporary invitee (no account) | Possession of a valid code |

---

## Capability Matrix

Legend: **Y** = yes, **N** = no, **C** = conditional (see notes).

### A. Account & Identity

| Capability | Super Admin | Owner | Member (Editor) | Member (Viewer) | Code Holder |
|---|---|---|---|---|---|
| Register an account | Y | Y | Y | Y | Y (upgrade) |
| Log in / log out | Y | Y | Y | Y | N (no account) |
| Change own password | Y | Y | Y | Y | N |
| Reset own keypair (re-seal private key) | Y | Y | Y | Y | N |
| Delete own account | Y | Y | Y | Y | N |
| Recover account via recovery code | N | Y | Y | Y | N |

> **Recover account via recovery code:** Enter recovery code to unseal private key and set new password (DECISIONS.md D2).

### B. Platform Administration

| Capability | Super Admin | Owner | Member (Editor) | Member (Viewer) | Code Holder |
|---|---|---|---|---|---|
| List all workspaces on platform | Y | N (own only) | N | N | N |
| Disable / ban a user | Y | N | N | N | N |
| Delete any workspace | Y | N | N | N | N |
| View platform-wide metadata (counts, users) | Y | N | N | N | N |
| View encrypted media content | **N** (no DEK) | N | N | N | N |

> **Super Admin cannot see image content.** E2EE means no DEK is ever sealed for Super Admin. They manage accounts and workspace metadata only.

> Per DECISIONS.md D5, all human-readable names (workspace, collection, gallery, media titles/captions) are encrypted with the workspace DEK. Super Admin sees only ciphertext for these fields.

### C. Workspace Management

| Capability | Super Admin | Owner | Member (Editor) | Member (Viewer) | Code Holder |
|---|---|---|---|---|---|
| Create a workspace | N (1) | Y | N | N | N |
| Rename own workspace | N | Y | N | N | N |
| Delete own workspace | N | Y | N | N | N |
| View workspace metadata (name, counts) | Y (any) | Y (own) | Y (member of) | Y (member of) | Y (scoped) |
| Re-key workspace (hard revoke) | N | Y | N | N | N |

> (1) Super Admin does not create workspaces — users do. Super Admin can only *delete* or *disable*.

### D. Workspace Membership

| Capability | Super Admin | Owner | Member (Editor) | Member (Viewer) | Code Holder |
|---|---|---|---|---|---|
| Invite a member to workspace | N | Y | N | N | N |
| Set member role (editor/viewer) | N | Y | N | N | N |
| Change a member's role | N | Y | N | N | N |
| Remove a member | N | Y | N | N | N |
| Leave a workspace (self) | N | N (2) | Y | Y | N |
| List workspace members | N | Y | Y | Y | N |

> (2) Owner cannot leave their own workspace; they can only delete it or transfer ownership (v2).

### E. Access Codes (the temporary share mechanism)

| Capability | Super Admin | Owner | Member (Editor) | Member (Viewer) | Code Holder |
|---|---|---|---|---|---|
| Generate an access code | N | Y | N | N | N |
| Choose code scope (workspace/collection/gallery) | N | Y | N | N | N |
| Set code duration (minutes/hours) | N | Y | N | N | N |
| Set code permissions (view/upload/comment) | N | Y | N | N | N |
| Set max uses (one-time vs reusable) | N | Y | N | N | N |
| View list of own codes | N | Y | N | N | N |
| Soft-revoke a code (block new access) | N | Y | N | N | N |
| Enter/use a code to gain access | N | Y | Y | Y | Y |
| Regenerate a revoked code | N | Y | N | N | N |

### F. Collections

| Capability | Super Admin | Owner | Member (Editor) | Member (Viewer) | Code Holder |
|---|---|---|---|---|---|
| Create a collection | N | Y | N | N | N |
| Rename a collection | N | Y | N | N | N |
| Delete a collection | N | Y | N | N | N |
| List collections in workspace | Y (meta only) | Y | Y | Y | C (if scope ≥ collection) |
| View collection metadata | Y (meta only) | Y | Y | Y | C (if scope ≥ collection) |

### G. Galleries

| Capability | Super Admin | Owner | Member (Editor) | Member (Viewer) | Code Holder |
|---|---|---|---|---|---|
| Create a gallery | N | Y | N | N | N |
| Set gallery type (private/shared/joint) | N | Y | N | N | N |
| Rename a gallery | N | Y | N | N | N |
| Delete a gallery | N | Y | N | N | N |
| Add gallery members (for shared/joint) | N | Y | N | N | N |
| Set gallery member role (editor/viewer) | N | Y | N | N | N |
| Remove a gallery member | N | Y | N | N | N |
| List galleries in a collection | Y (meta only) | Y | Y | Y | C (if scope ≥ gallery) |
| View gallery metadata | Y (meta only) | Y | Y | Y | C (if scope ≥ gallery) |

### H. Media (images)

| Capability | Super Admin | Owner | Member (Editor) | Member (Viewer) | Code Holder |
|---|---|---|---|---|---|
| Upload media (encrypt + store) | N | Y | C (3) | N | C (4) |
| View media metadata (title, size, uploader) | Y (meta only) | Y | Y | Y | C (4) |
| View/decrypt media content | **N** (no DEK) | Y | Y | Y | C (4) |
| Rename a media item | N | Y | C (3) | N | N |
| Delete a media item | N | Y | C (3) | N | N |
| Download (decrypt to disk) | N | Y | Y | C (5) | C (4) |

> (3) **Joint gallery only** — Editor members can upload/rename/delete in galleries where `gallery_members.role = editor`.
> (4) **Per code permissions** — Code Holder can only do what the code's `permissions` bitmask allows (view / upload / comment), and only within the code's `scope`.
> (5) **Viewer download** — configurable per workspace (default: viewers can view in-browser but not download to disk).

### I. Comments (if enabled)

| Capability | Super Admin | Owner | Member (Editor) | Member (Viewer) | Code Holder |
|---|---|---|---|---|---|
| View comments | N (meta only) | Y | Y | Y | C (code has `comment` perm) |
| Add a comment | N | Y | Y | N | C (code has `comment` perm) |
| Edit own comment | N | Y | Y | N | N |
| Delete own comment | N | Y | Y | N | N |
| Delete any comment | N | Y | N | N | N |

> **Comment encryption:** TBD per PRD §10 Q4 — if enabled, comments are encrypted with the workspace DEK like media.

### J. Audit & History

| Capability | Super Admin | Owner | Member (Editor) | Member (Viewer) | Code Holder |
|---|---|---|---|---|---|
| View workspace audit log (who did what) | Y (any) | Y (own) | N | N | N |
| View access-code usage log | N | Y | N | N | N |
| View own activity history | N | Y | Y | Y | N |

---

## Authorization Decision Order (first match wins)

When a request comes in, the server checks in this order. The **first rule that matches decides** — later rules are not evaluated.

```
1. Is the user a Super Admin?
     YES → allow (but still cannot decrypt media — no DEK exists for them)
     NO  → continue

2. Is the user the Owner of this workspace?
     YES → allow everything on this workspace and all descendants
     NO  → continue

3. Is the user a Member of this workspace?
     YES → check their workspace role + any per-gallery grants (gallery_members)
           → allow if the specific capability is granted
     NO  → continue

4. Is the request via an Access-Code session token?
     YES → check the code's:
            - scope (workspace | collection | gallery)
            - permissions bitmask (view | upload | comment)
            - expires_at > now()
            - revoked_at IS NULL
            - use_count < max_uses
           → allow only the specific capability within scope
     NO  → deny (403)
```

---

## What Each Role Can Do — One-Line Summary

| Role | Can do | Cannot do |
|---|---|---|
| **Super Admin** | Manage accounts, disable users, delete workspaces, view all metadata | See encrypted content, create workspaces, share codes |
| **Workspace Owner** | Everything in their workspace: CRUD collections/galleries/media, invite members, generate/revoke codes, re-key | Touch other workspaces, platform admin |
| **Member (Editor)** | View all in workspace, upload/edit/delete in joint galleries they're an editor of | Manage workspace, invite members, generate codes, delete galleries |
| **Member (Viewer)** | View all in workspace (in-browser) | Upload, edit, delete, manage, download (configurable) |
| **Access-Code Holder** | Do exactly what the code allows (view/upload/comment), within the code's scope, until expiry or revocation | Anything outside the code's scope/permissions, anything after expiry/revocation, manage anything |

---

## Hard Rules (apply to everyone, no exceptions)

1. **No one can decrypt media without the workspace DEK** — not even Super Admin.
2. **No one can see the raw access code after generation** — not even the Owner (shown once).
3. **Expired or revoked codes cannot be used** — server returns 403 immediately.
4. **Hard revoke (re-key) invalidates all outstanding access codes** — Owner must regenerate any they want to keep.
5. **Password reset does not recover encrypted content** — E2EE tradeoff; a new keypair starts fresh.
6. **Code Holders cannot manage anything** — they are read/act-only within their scope.
7. **Super Admin cannot create workspaces or share codes** — those are Owner actions.
8. All human-readable content names (workspace, collection, gallery, media title/caption) are encrypted with the workspace DEK — the server stores only ciphertext. Listing views require client-side decryption.
9. All primary keys are UUIDs (RFC 4122 v4) — no sequential ID enumeration possible.
