# Obscura — User Guide

Every flow, end to end. Each section covers **everything** about one part of the
app before moving to the next: Account → Workspace → Collections → Galleries →
Media → Guests → Security. A complete worked example is at the bottom (§12).

**One rule to remember:** all encryption happens in *your browser*. The server
stores only ciphertext. Nobody — including us — can recover your content without
your keys.

---

## 1. Account

### 1.1 Register
1. Go to `/register` (or **Get Started**).
2. Enter name, email, password → submit.
3. You're logged in and sent to **keypair generation** automatically.

### 1.2 Generate your keypair
1. On **Secure your account**, re-enter your password.
2. The browser generates an RSA-OAEP 2048 keypair, seals the private key with a
   key derived from your password, and uploads: public key + sealed private key +
   a recovery-code-sealed copy. Takes a few seconds — wait for it.

### 1.3 Save your recovery code
- Shown **once** — format `XXXX-XXXX-XXXX-XXXX-XXXX-XXXX`.
- **Print it / store it safely.** It's the only way back if you lose your password.
- Lose both password and recovery code → content is permanently gone. Intentional:
  a recovery backdoor would break zero-trust.

### 1.4 Verify your email
- Sent automatically at registration — click the link.
- Missing it? Use **Resend** on the verification notice page.

### 1.5 Sign in / sign out
- `/login` → on success the browser unseals your private key into memory.
- **Multi-device:** sign in separately on each device; the sealed key is fetched
  and unsealed locally each time.
- Keys live in sessionStorage — **closing the tab clears them**.
- **Sign out** from the header — also clears the in-memory key.

### 1.6 Password recovery (recovery code)
At `/recover` (link on the sign-in page):
1. Email + recovery code → browser unseals your private key with the code.
2. Set a new password → key is re-sealed to it.
3. The used code is invalidated; a security-alert email is sent.

---

## 2. Workspaces — everything workspace-related

A workspace is an encrypted container: its own AES-256 key (DEK), generated in
your browser, sealed to your public key. Everything inside is encrypted with it.

### 2.1 Create a workspace
**Dashboard → New Workspace** → name → browser generates the DEK, encrypts the
name, seals the DEK, posts only ciphertext.

### 2.2 The workspace dashboard
Open a workspace to see its decrypted name, DEK version, and all management
actions. The workspace list shows workspaces you **own** and ones you're a
**member** of.

### 2.3 Rename
**Rename** → browser re-encrypts the name with the DEK. Owner only.

### 2.4 Members

Members get their own sealed copy of the workspace DEK — they can decrypt
everything their role allows.

- **Add** — enter the person's **email** (the one they registered with). The
  browser verifies the account exists + has a keypair, wraps the DEK with their
  public key, stores it. It just works on their next sign-in — no key exchange
  needed.
- **Roles** — `editor` (upload/edit in joint galleries) or `viewer` (read-only).
- **Remove** — removes them from the workspace **and** every gallery in it.
  This is *soft* revocation — for hard revocation, re-key (§2.6).

Privacy: there is no user directory — you must already know their email.

### 2.5 Access codes — sharing without accounts

**Workspace → Access Codes → New Access Code:**

| Field | Options |
|---|---|
| **Scope** | Entire workspace / a collection / a single gallery |
| **Permissions** | View · Upload · Comment |
| **Duration** | 1 h / 6 h / 24 h / 7 days / 30 days |
| **Max uses** | 0 = unlimited |
| **Recipient email** | optional — mails the raw code once |
| **Label** | optional — e.g. "Sent to Alice" |

- Raw code `XXXX-XXXX-XXXX` shown **once** — copy it. Server stores only an
  Argon2id hash.
- List, inspect, **revoke** codes from the codes page — instant effect.
- Re-key revokes **all** outstanding codes.

### 2.6 Re-key (hard revocation)

**Workspace → Re-key** rotates the workspace DEK:

- New DEK generated in-browser → every media key re-wrapped, every
  name/title/caption re-encrypted, new DEK sealed for you + all members.
- **All outstanding access codes revoked.**
- Atomic — a crash mid-way leaves the old key intact; members re-unwrap
  transparently on next visit.
- Removed members and code-holders are locked out **for real**.
- Uploads are blocked during rotation (423). Use it when someone leaves, a code
  leaks, or as periodic hygiene.

### 2.7 Delete
**Delete** → type the workspace name to confirm → cascades to everything inside.

---

## 3. Collections

Collections group galleries inside a workspace.

- **Create / rename / delete** from the workspace's **Collections** view.
- Names and descriptions are encrypted with the workspace DEK.
- Deleting a collection cascades to its galleries and their media.

---

## 4. Galleries

Galleries live inside collections. Names + descriptions encrypted.

### 4.1 Create
Inside a collection → **New Gallery** → name, type, optional description.

### 4.2 The three types

| Type | Who can see it |
|---|---|
| **Private** | Owner only — no members, no code access |
| **Shared** | Workspace members view; only owner uploads |
| **Joint** | Members view; **editors** upload/edit/delete media |

### 4.3 Gallery members
On shared/joint galleries, **Members** lets you pick from workspace members
(dropdown shows eligible members — they must already be in the workspace) and
assign editor/viewer roles. Removing someone from the workspace removes them
here automatically.

### 4.4 Rename / change type / delete
From the gallery page. Delete cascades to its media.

---

## 5. Media

### 5.1 Upload
Gallery page → **Upload** → multi-select files.
- Per-file AES-256 key (CEK), encrypted client-side; thumbnail generated and
  encrypted in-browser.
- Limits: **100 MB per file**, 2 MB thumbnail, ~20 files / 500 MB per batch.
- Server receives only ciphertext — never plaintext.

### 5.2 View
Thumbnail → decrypted in-browser → full view: images full-size, video inline
playback, PDF embedded. Blob URLs are revoked between items to bound memory.

### 5.3 Rename / delete
Open a media item → edit title/caption (re-encrypted with the DEK) or delete —
type the decrypted name to confirm.

---

## 6. Guest experience (code-holders)

1. Invitee opens `/enter`, types the code.
2. Browser derives a key from the code → unseals the workspace DEK → decrypts
   names client-side.
3. They browse the scoped galleries and open media in the lightbox — all
   decrypted in-browser, no account needed.
4. Expired / revoked / exhausted code → 403, checked on every request.

---

## 7. Security notes

- Server sees **metadata only**: file sizes, MIME types, memberships, code
  expiry/usage, audit timestamps. Never content, names, or keys.
- Super Admin is cryptographically locked out of all content.
- Password strength matters — it seals your private key.
- Lost password **+** lost recovery code = lost content. No exceptions.

### Roles at a glance

| | View | Upload | Edit | Delete | Manage |
|---|---|---|---|---|---|
| **Owner** | ✓ | ✓ | ✓ | ✓ | ✓ |
| **Editor** | ✓ | ✓ (joint) | ✓ (joint) | ✓ (joint media) | — |
| **Viewer** | ✓ | — | — | — | — |
| **Code holder** | ✓ | if granted | — | — | — |
| **Super Admin** | metadata | — | — | — | platform |

---

## 8. Complete example flow — "Family Vault"

Alice sets up a private vault and shares it two ways: her brother as a full
member, her grandmother via an access code.

**1 — Alice registers**
`/register` → name/email/password → redirected to keygen → re-enters password →
keypair generated → **writes down the recovery code** → clicks the verification
email.

**2 — Alice creates the workspace**
Dashboard → **New Workspace** → types `Family Vault` → done. The DEK is
generated and sealed in her browser; the server only got ciphertext.

**3 — Structure**
Inside *Family Vault*: **Collections → New** → `2026`. Inside it:
**New Gallery** → `Summer Trip`, type **joint** (her brother will upload too).

**4 — Upload**
Opens *Summer Trip* → **Upload** → picks 30 photos → each encrypted +
thumbnailed in her browser → they appear in the grid, decrypted.

**5 — Add her brother as a member**
Workspace → **Members** → enters `brother@example.com` → role `editor` → the
page verifies he exists, wraps the DEK to his public key. He signs in → *Family
Vault* is in his workspace list → he can view the joint gallery and upload.

**6 — Share with grandma (no account)**
Workspace → **Access Codes → New** → scope `Gallery` → picks `2026 / Summer
Trip` → permission **View only** → duration **7 days** → label "Grandma" →
copies the code and texts it to her.

Grandma opens `/enter` → types `XXXX-XXXX-XXXX` → browses the gallery → opens
photos in the lightbox. No account, no app.

**7 — Ongoing management**
Brother uploads his own photos to the joint gallery. Alice renames a photo's
caption. A week later the grandma code **expires automatically**.

**8 — Someone leaves / a code leaks**
Alice removes her brother from **Members** (he's also removed from all galleries).
To be thorough she runs **Re-key**: new DEK, everything re-wrapped, all codes
revoked — his cached copy of the old key is now useless, even offline.

**9 — Disaster recovery**
Alice forgets her password → `/recover` → enters her recovery code → sets a new
password → her private key is re-sealed and everything still decrypts. The old
recovery code is now invalid.

**10 — If she'd lost both**
Password *and* recovery code gone → the content is mathematically
unrecoverable. That's the guarantee working as designed.
