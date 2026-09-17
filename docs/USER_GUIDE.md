# Obscura — User Guide

Every flow, end to end: registration → keys → workspaces → members → content → sharing → recovery.

**One rule to remember:** all encryption happens in *your browser*. The server stores
only ciphertext. That means nobody — including us — can recover your content without
your keys.

---

## 1. Registration & Keys

### Create an account
1. Go to `/register` (or **Get Started** in the nav).
2. Enter name, email, password. Submit.
3. You're logged in immediately and redirected to **keypair generation**.

### Generate your keypair
1. On the **Secure your account** page, re-enter your password.
2. The browser derives a wrapping key (PBKDF2), generates an RSA-OAEP 2048 keypair,
   seals the private key with your password, and uploads: public key + sealed
   private key + a recovery-code-sealed copy.
3. Wait for the spinner — takes a few seconds.

### Save your recovery code
- Shown **once** on the next screen — a code like `XXXX-XXXX-XXXX-XXXX-XXXX-XXXX`.
- **Print it or store it somewhere safe.** It's the only way back in if you lose
  your password.
- If you lose both password and recovery code, your content is permanently
  unrecoverable — this is intentional (a backdoor would break zero-trust).

### Verify your email
- A verification email is sent at registration. Click the link.
- Didn't get it? Use **Resend** on the verification notice page.

---

## 2. Signing In / Out

**Sign in** at `/login`. On success the browser unseals your private key into
memory for the session. If your account has no keypair yet, you're sent to
keygen first.

**Multi-device:** the sealed private key is fetched per-device — sign in on each
device separately. The key lives in sessionStorage; **closing the tab clears it**.

**Sign out** from the header — clears the in-memory key too.

---

## 3. Password Recovery (recovery code)

At `/recover` ("Forgot" link on sign-in):
1. Enter email + your recovery code.
2. The browser unseals your private key with the code.
3. Set a new password — the key is re-sealed to it and saved.
4. The recovery code is **marked used** — you get a new one / it can't be reused.
5. A security-alert email is sent to your address.

---

## 4. Workspaces

A workspace is an encrypted container: its own AES-256 key (the DEK), generated
in your browser and sealed to your public key. Everything inside is encrypted
with it.

### Create
**Dashboard → New Workspace** → enter a name → the browser generates the DEK,
encrypts the name, seals the DEK, and posts only ciphertext.

### Dashboard (open a workspace)
Shows decrypted name, DEK version, and actions:

| Action | Who | What happens |
|---|---|---|
| **Rename** | Owner | Re-encrypts the name with the DEK |
| **Members** | Owner | Member management (§5) |
| **Access Codes** | Owner | Code management (§8) |
| **Re-key** | Owner | Full key rotation (§10) |
| **Delete** | Owner | Type the workspace name to confirm — cascades to all contents |

You see workspaces you **own** and ones you're a **member** of in the same list.

---

## 5. Members (workspace-level)

Members get their own sealed copy of the workspace DEK — they can decrypt
everything their role allows.

**Workspace → Members:**

- **Add** — enter the person's **email** (the address they registered with).
  The browser verifies they exist and have a keypair, wraps the DEK with *their*
  public key, and stores it. They never see a password or key — it just works
  when they sign in.
- **Roles** — `editor` (can upload/edit in joint galleries) or `viewer`
  (read-only).
- **Remove** — removes them from the workspace **and** from every gallery in it.
  *Note:* this is soft revocation — for hard revocation, re-key (§10).

Privacy: there is no user directory — you must already know the email of the
person you're adding.

---

## 6. Collections & Galleries

Structure: **Workspace → Collections → Galleries → Media**

### Collections
Create/rename/delete from the workspace's **Collections** view. Names and
descriptions are encrypted with the workspace DEK.

### Galleries
Created inside a collection. Three types:

| Type | Who can see it |
|---|---|
| **Private** | Owner only — no members, no code access to its media |
| **Shared** | Workspace members can view; only owner uploads |
| **Joint** | Members can view; **editors** can upload/edit/delete media |

Gallery names + descriptions are encrypted. Rename/change type from the gallery
page; delete cascades to its media.

---

## 7. Media

### Upload
On a gallery page → **Upload** → pick files (multi-select supported).
- Per-file AES-256 key (CEK), encrypted client-side; thumbnail generated and
  encrypted in-browser too.
- Limits: **100 MB per file**, 2 MB thumbnail, ~20 files / 500 MB per batch.
- Server receives only ciphertext.

### View
Click a thumbnail → decrypted in-browser → full view (images full-size, video
inline playback, PDF embedded). Click a media item's title to open its page —
rename title/caption, delete (type the name to confirm).

Everything streams as ciphertext and decrypts locally — the plaintext never
exists on the server.

---

## 8. Access Codes — sharing without accounts

For people who shouldn't need an account.

**Workspace → Access Codes → New Access Code:**

| Field | Options |
|---|---|
| **Scope** | Entire workspace / a collection / a single gallery |
| **Permissions** | View · Upload · Comment (checkboxes) |
| **Duration** | 1 hour / 6 h / 24 h / 7 days / 30 days |
| **Max uses** | 0 = unlimited, or a number |
| **Recipient email** | optional — mails the raw code once |
| **Label** | optional — e.g. "Sent to Alice" |

The raw code (`XXXX-XXXX-XXXX`) is shown **once** — copy it. The server keeps
only an Argon2id hash.

**Revoke** any code instantly from the codes list. Re-key revokes *all* codes.

---

## 9. Guest Access (what invitees see)

1. Invitee opens `/enter`, types the code.
2. Browser derives a key from the code → unseals the workspace DEK → decrypts
   names client-side.
3. They browse the scoped galleries, open media in the lightbox — all decrypted
   in-browser.
4. Expired/revoked/exhausted code → 403, immediately (checked every request).

---

## 10. Re-key (hard revocation)

**Workspace → Re-key.** Rotates the workspace DEK:

- New DEK generated in your browser
- Every media CEK re-wrapped, every name/title/caption re-encrypted
- New DEK sealed for you + all current members
- **All outstanding access codes are revoked**
- `dek_version` increments; atomic — a crash mid-way leaves the old key intact
- Members re-unwrap transparently on next visit; removed members/code-holders
  are locked out for real

Use it when someone leaves, a code leaks, or as periodic hygiene. Don't run it
during uploads (they're locked with 423 while it runs).

---

## 11. Security notes

- The server sees **metadata only**: file sizes, MIME types, memberships, code
  expiry/usage, audit timestamps. Never content, names, or keys.
- Super Admin is cryptographically locked out of all content.
- Password strength matters — your private key is sealed with it. Use a strong
  one.
- Private keys live in sessionStorage — they vanish when the tab closes. Sign in
  again on a new device/session.
- Lost password **+** lost recovery code = lost content. No exceptions — that's
  the price of real zero-trust.

---

## Roles at a glance

| | View | Upload | Edit | Delete | Manage |
|---|---|---|---|---|---|
| **Owner** | ✓ | ✓ | ✓ | ✓ | ✓ |
| **Editor** | ✓ | ✓ (joint) | ✓ (joint) | ✓ (joint media) | — |
| **Viewer** | ✓ | — | — | — | — |
| **Code holder** | ✓ | if granted | — | — | — |
| **Super Admin** | metadata only | — | — | — | platform |
