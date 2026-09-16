# Obscura — UI/UX Design Specification

**Companion to:** `PRD.md`, `WORKFLOWS.md`, `CAPABILITIES.md`, `DECISIONS.md`
**Principles:** Sleek. Simple. Content-first. Mobile-first.

---

## 1. Design Philosophy

Obscura is a private encrypted gallery. The UI must feel like a quiet, well-lit room where images are the subject — not the interface. Three rules, in priority order:

1. **Content first** — the gallery is about images. Chrome (nav, buttons, controls) recedes; images breathe.
2. **Quiet confidence** — no gratuitous animation, no visual noise. Transitions are fast (150–200ms) and purposeful. Whitespace is a feature.
3. **Thumb-friendly** — every primary action is reachable with one thumb on a phone. No hover-dependent UI on mobile.

### What "sleek and simple" means here
- Single accent color (one). Everything else is neutral grays/white/dark.
- Type scale of 4 sizes max: caption, body, heading, display.
- Cards have subtle shadows or 1px borders — not both.
- Rounded corners (8–12px) on cards, buttons, inputs. Pill buttons (full radius) for primary CTAs.
- No more than 2 font families: one for display/headings, one for body (or a single variable font).

---

## 2. Visual Language

### Color system

**Light theme (default)**
| Token | Value | Use |
|---|---|---|
| `--bg` | `#ffffff` | App background |
| `--bg-subtle` | `#f8f9fa` | Cards, sidebar, hover states |
| `--bg-muted` | `#f1f3f5` | Inset areas, table headers |
| `--border` | `#e9ecef` | 1px borders, dividers |
| `--text` | `#212529` | Primary text |
| `--text-secondary` | `#6c757d` | Captions, metadata, labels |
| `--text-muted` | `#adb5bd` | Placeholders, disabled |
| `--accent` | `#4f46e5` (indigo-600) | Primary actions, links, focus rings |
| `--accent-hover` | `#4338ca` (indigo-700) | Hover/active on primary |
| `--accent-subtle` | `#eef2ff` (indigo-50) | Selected states, soft backgrounds |
| `--danger` | `#dc2626` (red-600) | Delete, revoke, destructive |
| `--success` | `#16a34a` (green-600) | Success toasts, active states |
| `--warning` | `#d97706` (amber-600) | Expiring codes, warnings |

**Dark theme** (auto via `prefers-color-scheme`, or user toggle)
| Token | Value |
|---|---|
| `--bg` | `#0f1115` |
| `--bg-subtle` | `#1a1d24` |
| `--bg-muted` | `#22262e` |
| `--border` | `#2a2e37` |
| `--text` | `#e9ecef` |
| `--text-secondary` | `#8b95a5` |
| `--text-muted` | `#5c6573` |
| `--accent` | `#818cf8` (indigo-400) |
| `--accent-hover` | `#a5b4fc` |
| `--accent-subtle` | `#1e1b3a` |
| `--danger` | `#f87171` |
| `--success` | `#4ade80` |
| `--warning` | `#fbbf24` |

> Colors are defined as CSS custom properties on `:root` / `[data-theme="dark"]` so the entire app switches with one attribute. Tailwind config maps these tokens to utility classes.

### Typography

| Role | Font | Size | Weight | Line-height |
|---|---|---|---|---|
| Display (workspace name, hero) | Inter / system-ui | 2rem → 1.5rem (responsive) | 700 | 1.2 |
| Heading (section titles) | Inter | 1.25rem | 600 | 1.3 |
| Body | Inter | 1rem (16px) | 400 | 1.5 |
| Caption / metadata | Inter | 0.8125rem (13px) | 400 | 1.4 |
| Mono (codes, IDs) | JetBrains Mono / ui-monospace | 0.875rem | 500 | 1.4 |

- **Font loading:** Inter via `@font-face` (self-hosted, woff2) or Google Fonts with `font-display: swap`. Avoid FOIT.
- **No more than 2 weights loaded for body** (400, 600) + 1 for display (700) = 3 files max.

### Spacing scale

8px base: `0, 4, 8, 12, 16, 24, 32, 48, 64, 96`. Use Tailwind defaults (`gap-2`, `p-4`, etc.).

### Border radius

| Element | Radius |
|---|---|
| Cards, panels | 12px (`rounded-xl`) |
| Buttons, inputs | 8px (`rounded-lg`) |
| Primary CTA (pill) | 9999px (`rounded-full`) |
| Thumbnails | 8px |
| Modals | 16px (`rounded-2xl`) |

### Shadows

| Level | Use |
|---|---|
| `shadow-sm` | Cards at rest |
| `shadow-md` | Cards on hover, dropdowns |
| `shadow-lg` | Modals, popovers |
| `shadow-none` | Flat sections (use border instead) |

> Rule: a card uses either a shadow OR a border, never both. Default: 1px border at rest, shadow on hover.

### Motion

| Transition | Duration | Easing |
|---|---|---|
| Hover states | 150ms | `ease-out` |
| Page enter/exit | 200ms | `ease-out` |
| Modal open/close | 200ms | `ease-out` |
| Toast slide-in | 200ms | `ease-out` |
| Image fade-in (on decrypt) | 300ms | `ease-out` |
| Skeleton shimmer | 1.5s | `ease-in-out` (loop) |

> No bounce, no spring, no parallax. Motion is invisible unless it conveys state change.

---

## 3. Layout System

### Breakpoints (mobile-first)

| Name | Width | Layout |
|---|---|---|
| `sm` | ≥ 640px | Single column. Bottom nav on mobile. |
| `md` | ≥ 768px | Two-column (sidebar + content) on tablets. |
| `lg` | ≥ 1024px | Three-column (sidebar + content + optional detail panel) on desktop. |
| `xl` | ≥ 1280px | Wider galleries, more columns in grid. |
| `2xl` | ≥ 1536px | Max content width capped at 1400px (centered). |

### Navigation patterns

**Mobile (≤ 768px):**
- **Bottom tab bar** with 4–5 items max: Workspaces, Shared, Codes, Settings (or Admin for super admin).
- **Hamburger** opens a slide-over drawer for secondary nav (collections within a workspace).
- **No sidebar** — full width for content.
- **Sticky top bar** (56px): workspace name (decrypted), back button, overflow menu.

**Tablet (768–1024px):**
- **Collapsible left sidebar** (240px, can be toggled to icon-only at 64px).
- Content area takes remaining width.

**Desktop (≥ 1024px):**
- **Persistent left sidebar** (256px): workspace switcher, collection tree, settings.
- **Main content area**: gallery grid, forms, dashboards.
- **Optional right panel** (320px): media detail, metadata, comments — slides in when a media item is selected; overlays on tablet/mobile.

### Container widths

| Context | Max width | Padding |
|---|---|---|
| Auth pages (login/register) | 400px | centered, `px-6` |
| Forms (create workspace, etc.) | 560px | `px-6` |
| Gallery grid | 100% of content area | `px-4 md:px-6` |
| Settings / admin | 800px | `px-6` |
| Full-screen image view | 100vw / 100vh | none (immersive) |

---

## 4. Gallery Views

The gallery is the heart of the app. Three view modes, user-selectable (persisted in localStorage):

### 4.1 Grid view (default)

```
┌─────────────────────────────────────────────┐
│  [Grid] [Masonry] [List]     🔍  Sort ▾  ☰   │  ← toolbar
├─────────────────────────────────────────────┤
│                                              │
│   ┌───────┐  ┌───────┐  ┌───────┐           │
│   │ thumb │  │ thumb │  │ thumb │           │
│   │       │  │       │  │       │           │
│   ├───────┤  ├───────┤  ├───────┤           │
│   │ title │  │ title │  │ title │           │
│   │ meta  │  │ meta  │  │ meta  │           │
│   └───────┘  └───────┘  └───────┘           │
│                                              │
│   ┌───────┐  ┌───────┐  ┌───────┐           │
│   │ ...   │  │ ...   │  │ ...   │           │
│   └───────┘  └───────┘  └───────┘           │
│                                              │
└─────────────────────────────────────────────┘
```

- **Columns:** auto-fill with `minmax(180px, 1fr)` on desktop, `minmax(140px, 1fr)` on tablet, `minmax(120px, 1fr)` on mobile. CSS grid, no JS masonry needed.
- **Thumbnail aspect:** 1:1 (square crop) by default. Toggle for "original aspect" available in settings.
- **Card:** 1px border at rest, `shadow-md` + `border-accent` on hover. Title + date below thumbnail in `--text-secondary`.
- **Hover (desktop only):** quick-action buttons appear top-right (view, download, delete if permitted). Fade in 150ms.
- **Tap (mobile):** tap opens the image in full-screen view. Long-press opens context menu (view, download, delete).
- **Empty state:** centered illustration + "No images yet. Upload your first image." with a primary CTA button.
- **Loading:** skeleton cards (gray pulsing placeholders) in the grid shape. Show 6–8 skeletons while fetching.

### 4.2 Masonry view

```
┌─────────────────────────────────────────────┐
│  [Grid] [Masonry] [List]     🔍  Sort ▾  ☰   │
├─────────────────────────────────────────────┤
│                                              │
│   ┌───────┐  ┌───┐  ┌────────┐              │
│   │       │  │   │  │        │              │
│   │ thumb │  │   │  │ thumb  │              │
│   │       │  │   │  │        │              │
│   │       │  └───┘  │        │              │
│   └───────┘  ┌───┐ └────────┘              │
│   ┌───────┐  │   │  ┌───────┐              │
│   │ thumb │  │   │  │ thumb │              │
│   └───────┘  └───┘  └───────┘              │
│                                              │
└─────────────────────────────────────────────┘
```

- CSS `columns` property: `columns: 3` (desktop), `2` (tablet), `2` (mobile). `break-inside: avoid` on items.
- Thumbnails preserve original aspect ratio (no cropping).
- Same card styling as grid, minus the fixed height.

### 4.3 List view

```
┌─────────────────────────────────────────────┐
│  [Grid] [Masonry] [List]     🔍  Sort ▾  ☰   │
├─────────────────────────────────────────────┤
│                                              │
│   ┌───┬──────────────────────────────────┐  │
│   │   │ Title                    Jan 12  │  │
│   │ 📸│ caption text...                   │  │
│   │   │ image.jpg · 2.4 MB · by Alice    │  │
│   └───┴──────────────────────────────────┘  │
│   ┌───┬──────────────────────────────────┐  │
│   │   │ Title                    Jan 10  │  │
│   │ 📸│ ...                               │  │
│   └───┴──────────────────────────────────┘  │
│                                              │
└─────────────────────────────────────────────┘
```

- Row height: 72px. Thumbnail 48x48 (square). Title + caption + metadata in a flex row.
- Useful for scanning metadata-heavy galleries. Default sort: newest first.

### 4.4 Full-screen image view (lightbox)

```
┌─────────────────────────────────────────────┐
│  ✕                              [info] [⋯]  │  ← top bar (auto-hides)
│                                              │
│                                              │
│              ┌────────────────┐              │
│              │                │              │
│              │    IMAGE       │              │
│              │   (decrypted)  │              │
│              │                │              │
│              └────────────────┘              │
│                                              │
│                                              │
│  ◀  3 / 24                        ▶          │  ← prev/next (auto-hides)
│  Title of this image                         │  ← caption bar
│  Jan 12, 2026 · 2.4 MB · uploaded by Alice   │
└─────────────────────────────────────────────┘
```

- **Background:** `--bg` (solid, not blurred — keeps it simple).
- **Image:** max 90vw / 85vh, centered, `object-fit: contain`. Fades in over 300ms after decrypt.
- **Top bar:** close (✕), info toggle, overflow menu (download, delete if permitted). Auto-hides after 3s of no mouse movement; reappears on move/tap.
- **Bottom bar:** prev/next arrows (large, 48px tap targets), position indicator "3 / 24", title + metadata. Auto-hides with top bar.
- **Keyboard:** `←` `→` to navigate, `Esc` to close, `i` to toggle info.
- **Swipe:** left/right swipe on mobile to navigate. Pinch to zoom (optional, v2).
- **Info panel:** slides in from right (320px). Shows decrypted title, caption, upload date, file size, MIME type, uploader. Editable if user has permission (inline edit, no modal).

### 4.5 Decryption loading state

Since images decrypt client-side, there's a brief delay between the ciphertext arriving and the image rendering:

1. **Skeleton placeholder** in the card (gray, shimmering) while ciphertext is fetched.
2. **Spinner** (small, `--accent` colored, 24px) centered in the card while decrypting.
3. **Fade-in** of the decrypted image over 300ms once ready.
4. **Error state** if decryption fails (wrong DEK / corrupted): muted icon + "Unable to decrypt" in `--text-muted`.

> This loading state is important UX — without it, the gallery looks broken while crypto runs. The skeleton + spinner makes the wait feel intentional.

---

## 5. Key Screens

### 5.1 Auth screens (login, register, recover)

```
         ┌───────────────────────────┐
         │                           │
         │     [Logo / App Name]     │
         │                           │
         │   ┌───────────────────┐   │
         │   │ email             │   │
         │   └───────────────────┘   │
         │   ┌───────────────────┐   │
         │   │ password          │   │
         │   └───────────────────┘   │
         │                           │
         │   ┌───────────────────┐   │
         │   │   Sign In (pill)  │   │
         │   └───────────────────┘   │
         │                           │
         │   Forgot password?        │
         │   Don't have an account?  │
         │                           │
         └───────────────────────────┘
```

- Centered card, max 400px, `--bg-subtle` background with `shadow-lg`.
- No illustration on mobile (saves space). Optional subtle illustration on desktop left half (split-screen).
- Inputs: 48px height, `rounded-lg`, 1px border, focus ring `--accent` (2px).
- Primary button: full width, pill, `--accent` bg, white text, 48px height.
- Links below: `--text-secondary`, hover `--accent`.
- **Recovery code screen:** monospace font, large, grouped in 4s (`K7QX-9P2M-4F8R-...`), with a prominent "Print" button and a "I've stored it safely" checkbox before continuing.

### 5.2 Workspace dashboard

```
┌─────────┬───────────────────────────────────────────┐
│         │  Workspace Name (decrypted)     [+ Code]  │
│ Worksp   │  3 collections · 24 galleries · 1.2k img │
│ spaces  │                                            │
│         │  ┌──────────┐ ┌──────────┐ ┌──────────┐   │
│ ─────── │  │ Coll 1   │ │ Coll 2   │ │ Coll 3   │   │
│ Coll 1   │  │ 8 gal    │ │ 4 gal    │ │ 12 gal   │   │
│ Coll 2   │  │ 240 img  │ │ 80 img   │ │ 900 img  │   │
│ Coll 3   │  └──────────┘ └──────────┘ └──────────┘   │
│         │                                            │
│ ─────── │  Recent Activity                           │
│ Members │  • You uploaded 3 images to "Summer"  2h   │
│ Codes   │  • Alice viewed "Travel"           5h      │
│ Settings│  • Code "K7QX..." expired         1d      │
│         │                                            │
└─────────┴───────────────────────────────────────────┘
```

- Sidebar: workspace switcher (dropdown at top), collection tree, members, codes, settings.
- Main: workspace name (decrypted client-side), stats summary, collection cards, recent activity.
- Collection cards: 1:1.5 aspect, `--bg-subtle`, collection name (decrypted), gallery count, image count. Click → collection view.
- "+ Code" button: opens access code creation modal.

### 5.3 Collection view

```
┌─────────┬───────────────────────────────────────────┐
│         │  ← Collection Name (decrypted)             │
│ sidebar │                                            │
│         │  ┌──────────┐ ┌──────────┐ ┌──────────┐   │
│         │  │ Gallery  │ │ Gallery  │ │ + New    │   │
│         │  │ (thumb) │ │ (thumb) │ │ Gallery  │   │
│         │  │ 12 img   │ │ 8 img   │ │          │   │
│         │  └──────────┘ └──────────┘ └──────────┘   │
│         │                                            │
└─────────┴───────────────────────────────────────────┘
```

- Gallery cards show a thumbnail of the most recent image (decrypted). If no images, a dashed-border placeholder.
- Gallery type badge: `private` (lock icon, `--text-muted`), `shared` (people icon, `--accent`), `joint` (collaborate icon, `--success`).
- "+ New Gallery" card: dashed border, `--text-secondary`, plus icon. Click → create modal.

### 5.4 Gallery view (grid)

As described in §4.1. The toolbar includes:
- View mode toggle (grid/masonry/list) — icon buttons, segmented control.
- Search input (client-side filter on decrypted titles). 200px on desktop, full-width expandable on mobile.
- Sort dropdown: Newest, Oldest, Title A–Z, Title Z–A. (Title sort is client-side after decryption.)
- Overflow menu: rename gallery, manage members (if shared/joint), delete gallery.

### 5.5 Access code management

```
┌─────────────────────────────────────────────┐
│  Access Codes                    [+ Generate]│
├─────────────────────────────────────────────┤
│                                              │
│  ┌───────────────────────────────────────┐  │
│  │ K7QX-9P2M-4F8R          [Copy]        │  │
│  │ Scope: Collection "Summer"             │  │
│  │ Perms: View · Comment                  │  │
│  │ Expires: in 2h 14m  (progress bar)     │  │
│  │ Uses: 3 / unlimited                    │  │
│  │ Label: "Sent to Alice"                 │  │
│  │                        [Revoke]         │  │
│  └───────────────────────────────────────┘  │
│                                              │
│  ┌───────────────────────────────────────┐  │
│  │ P3XK-7M2N-8Q4R         [Expired]      │  │
│  │ Scope: Gallery "Travel"               │  │
│  │ Perms: View                            │  │
│  │ Expired 3 hours ago                    │  │
│  └───────────────────────────────────────┘  │
│                                              │
└─────────────────────────────────────────────┘
```

- Active codes: full opacity, expiry countdown (live-updating, `--text-secondary`), progress bar showing time elapsed.
- Expiring soon (< 15 min): `--warning` text on countdown.
- Expired/revoked codes: 50% opacity, greyed out, "Expired" or "Revoked" badge.
- Code display: monospace, grouped, with copy button (copies to clipboard, toast confirmation).
- "Revoke" button: `--danger` outline, confirms with inline "Are you sure? [Revoke] [Cancel]".

### 5.6 Code entry screen (invitee, no account)

```
         ┌───────────────────────────┐
         │                           │
         │     [Logo / App Name]     │
         │                           │
         │   You've been invited to   │
         │   view a private gallery   │
         │                           │
         │   ┌───────────────────┐   │
         │   │ Enter access code  │   │
         │   └───────────────────┘   │
         │                           │
         │   ┌───────────────────┐   │
         │   │   Unlock (pill)   │   │
         │   └───────────────────┘   │
         │                           │
         │   Don't have a code?       │
         │   Create an account       │
         │                           │
         └───────────────────────────┘
```

- Same card style as auth screens.
- Input: monospace, letter-spacing for code readability, auto-formats with dashes as user types.
- "Unlock" button: disabled until 12 chars entered. On submit: spinner → redirect to scoped content or error toast.
- "Create an account" link: for the upgrade flow (Module 3 §6.4).

### 5.7 Mobile-specific layouts

**Bottom navigation (mobile):**
```
┌──────────────────────────────┐
│                              │
│       (content area)         │
│                              │
│                              │
├──────────────────────────────┤
│  📁      👥      🔑     ⚙️  │
│ Spaces  Shared  Codes  Set  │
└──────────────────────────────┘
```

- 56px height, `--bg` with top border, safe-area padding for notched devices.
- Active tab: `--accent` icon + label. Inactive: `--text-muted`.
- 4 items max. If super admin, "Settings" becomes "Admin".

**Mobile gallery grid:**
- 2 columns on phones (`minmax(150px, 1fr)`), 3 on large phones (≥ 414px).
- No hover actions — tap opens image, long-press for context menu.
- Toolbar collapses: view toggle becomes a single icon (cycles grid→masonry→list), search becomes icon that expands to full-width overlay.

**Mobile full-screen image:**
- Same as §4.4 but:
  - Top/bottom bars are always visible (no auto-hide on mobile — touch doesn't have hover).
  - Swipe left/right to navigate.
  - Pinch to zoom (v2).
  - Info panel slides up from bottom (bottom sheet) instead of right side.

---

## 6. Component Library

### Core components (built with Blade + Alpine + Tailwind)

| Component | Description |
|---|---|
| `x-card` | Base card: `--bg`, 1px border, `rounded-xl`, `shadow-sm` → `shadow-md` on hover. Slot for content. |
| `x-button` | Variants: `primary` (pill, `--accent`), `secondary` (outline, `--border`), `ghost` (text only), `danger` (`--danger` outline). Sizes: `sm` (32px), `md` (40px), `lg` (48px). |
| `x-input` | Text input: 48px, `rounded-lg`, 1px border, focus ring. Label above, helper text below, error state (`--danger` border). |
| `x-modal` | Centered, `--bg`, `rounded-2xl`, `shadow-lg`, backdrop blur. Max 560px. Close on Esc + backdrop click. Alpine `x-show` transition. |
| `x-toast` | Top-right (desktop) / top-center (mobile). Auto-dismiss 4s. Variants: success (`--success`), error (`--danger`), info (`--accent`). |
| `x-skeleton` | Animated placeholder: `--bg-muted` with shimmer gradient. Used in grids, lists, cards while loading/decrypting. |
| `x-dropdown` | Menu: `--bg`, `shadow-md`, `rounded-lg`. Items: 40px height, hover `--bg-subtle`. |
| `x-tabs` | Segmented control: `--bg-muted` container, active item `--bg` + `shadow-sm`. Used for view mode toggle. |
| `x-badge` | Small pill: `--bg-subtle` + `--text-secondary`. Variants: `accent`, `success`, `warning`, `danger`. |
| `x-empty-state` | Centered: illustration (optional), message, CTA button. `--text-secondary` for message. |
| `x-code-display` | Monospace, large, grouped with dashes, copy button. Used for access codes + recovery codes. |
| `x-progress-bar` | Thin (4px), `--bg-muted` track, `--accent` fill. Used for code expiry countdown. |
| `x-confirm` | Inline confirmation: replaces element with "Are you sure? [Confirm] [Cancel]". No modal for simple confirmations. |
| `x-lightbox` | Full-screen image viewer (§4.4). Alpine-controlled. Keyboard + swipe support. |

### Blade component structure

```
resources/views/components/
  card.blade.php
  button.blade.php
  input.blade.php
  modal.blade.php
  toast.blade.php
  skeleton.blade.php
  dropdown.blade.php
  tabs.blade.php
  badge.blade.php
  empty-state.blade.php
  code-display.blade.php
  progress-bar.blade.php
  confirm.blade.php
  lightbox.blade.php
  gallery-grid.blade.php        (composes cards + skeleton + lightbox)
  gallery-masonry.blade.php
  gallery-list.blade.php
  sidebar.blade.php
  bottom-nav.blade.php
  top-bar.blade.php
```

---

## 7. Interaction Patterns

### 7.1 Decryption-aware loading

Every view that displays encrypted content follows this pattern:

```
1. Fetch ciphertext from server (Livewire/Alpine action)
2. Show skeleton placeholders in the UI
3. Decrypt in browser (WebCrypto)
4. Replace skeletons with decrypted content (fade-in, 300ms)
5. On error: show muted error state ("Unable to decrypt")
```

This is critical — without skeleton states, the app looks broken during the crypto delay.

### 7.2 Optimistic UI for non-crypto actions

Actions that don't involve encryption (revoke code, delete gallery, rename metadata) use optimistic updates:
1. User clicks action.
2. UI updates immediately (e.g., card fades out, code moves to "revoked" section).
3. Server request fires in background.
4. On error: revert UI + show error toast.

### 7.3 Crypto actions (upload, create workspace)

Actions involving encryption are NOT optimistic (the user needs to see progress):
1. User clicks action.
2. Button shows spinner, disabled.
3. Crypto runs (encrypt file, generate DEK, etc.).
4. Upload to server.
5. On success: toast + UI update.
6. On error: error toast, button re-enables.

### 7.4 Drag-and-drop upload

- Desktop: drag files onto the gallery grid. Drop zone highlights with `--accent-subtle` bg + dashed `--accent` border.
- Mobile: no drag-drop. Tap "+" button → file picker.
- Multi-file: supported. Shows per-file progress (small progress bar on each card skeleton).

### 7.5 Keyboard shortcuts (desktop)

| Shortcut | Action |
|---|---|
| `G` then `M` | Grid view |
| `G` then `A` | Masonry view |
| `G` then `L` | List view |
| `/` | Focus search |
| `←` `→` | Navigate images in lightbox |
| `Esc` | Close lightbox / modal |
| `N` | New gallery (in collection view) |
| `U` | Upload (in gallery view) |
| `?` | Show shortcuts help |

---

## 8. Responsive Behavior Summary

| Element | Mobile (< 768px) | Tablet (768–1024px) | Desktop (≥ 1024px) |
|---|---|---|---|
| Navigation | Bottom tab bar + drawer | Collapsible sidebar | Persistent sidebar |
| Gallery columns | 2 (3 on ≥ 414px) | 3 | 4–6 (auto-fill) |
| Card hover actions | None (tap + long-press) | Show on hover | Show on hover |
| Lightbox info panel | Bottom sheet (slides up) | Right panel (slides in) | Right panel (slides in) |
| Search | Icon → full-width overlay | Inline (200px) | Inline (240px) |
| View toggle | Single cycling icon | Segmented control | Segmented control |
| Modals | Full-screen | Centered (max 560px) | Centered (max 560px) |
| Forms | Single column, full width | Single column, 560px max | 2-column for short forms |
| Drag-drop upload | Disabled (tap to upload) | Enabled | Enabled |
| Keyboard shortcuts | Disabled | Enabled | Enabled |
| Top bar | 56px, workspace name + back | 56px, workspace name + actions | 56px, full breadcrumb + actions |

---

## 9. Accessibility

- **Color contrast:** all text combinations meet WCAG AA (4.5:1 for body text, 3:1 for large text).
- **Focus visible:** all interactive elements have a 2px `--accent` focus ring. Never remove focus styles.
- **Keyboard navigable:** every action reachable via keyboard. Tab order is logical (top-to-bottom, left-to-right).
- **ARIA labels:** icon-only buttons have `aria-label`. Modals have `role="dialog"` + `aria-modal`. Toasts have `role="status"`.
- **Screen readers:** encrypted content that's still decrypting has `aria-busy="true"`. Skeletons have `aria-hidden="true"`.
- **Reduced motion:** `@media (prefers-reduced-motion: reduce)` disables all transitions and animations. Images appear instantly.
- **Touch targets:** minimum 44x44px on mobile (Apple HIG) / 48x48px for primary actions.

---

## 10. Dark Mode

- **Auto-detection:** `@media (prefers-color-scheme: dark)` applies dark tokens by default.
- **Manual toggle:** in settings, user can override (light / dark / auto). Stored in `localStorage`, applied via `[data-theme]` attribute on `<html>`.
- **No flash:** inline script in `<head>` reads `localStorage` before paint to set the theme attribute.
- **Gallery in dark mode:** `--bg` is near-black, images pop. Thumbnails get a subtle 1px `--border` to separate from background.

---

## 11. Performance Budget

| Metric | Target |
|---|---|
| First contentful paint | < 1.5s |
| Time to interactive | < 2.5s |
| JS bundle (gzip) | < 80 KB (Alpine + crypto helpers + Livewire) |
| CSS (gzip) | < 30 KB (Tailwind, purged) |
| Image thumbnails | < 10 KB each (200x200, JPEG quality 70) |
| Gallery grid render (100 items) | < 500ms after data arrives |
| Decryption per image | < 50ms (AES-GCM, typical image) |

- **No heavy frameworks.** Alpine.js (~15KB gzip) + Livewire (server-rendered) + Tailwind (purged). No React/Vue/Angular.
- **Lazy-load images.** Thumbnails below the fold use `loading="lazy"`. Full images in lightbox load on demand.
- **Code-split crypto.** WebCrypto modules are dynamically imported (`import()`) only when needed, keeping initial bundle small.

---

## 12. Tailwind Configuration

```js
// tailwind.config.js
module.exports = {
  darkMode: ['class', '[data-theme="dark"]'],
  content: [
    './resources/**/*.blade.php',
    './resources/**/*.js',
    './app/Livewire/**/*.php',
  ],
  theme: {
    extend: {
      colors: {
        // Map CSS custom properties to Tailwind tokens
        // (or define directly here for build-time purging)
        accent: { DEFAULT: '#4f46e5', hover: '#4338ca', subtle: '#eef2ff' },
        danger: '#dc2626',
        success: '#16a34a',
        warning: '#d97706',
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
        mono: ['JetBrains Mono', 'ui-monospace', 'monospace'],
      },
      borderRadius: {
        xl: '12px',
        '2xl': '16px',
      },
      maxWidth: {
        content: '1400px',
        form: '560px',
        auth: '400px',
      },
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
  ],
};
```

---

## 13. CSS Custom Properties (theme tokens)

```css
/* resources/css/app.css */
:root {
  --bg: #ffffff;
  --bg-subtle: #f8f9fa;
  --bg-muted: #f1f3f5;
  --border: #e9ecef;
  --text: #212529;
  --text-secondary: #6c757d;
  --text-muted: #adb5bd;
  --accent: #4f46e5;
  --accent-hover: #4338ca;
  --accent-subtle: #eef2ff;
  --danger: #dc2626;
  --success: #16a34a;
  --warning: #d97706;
}

[data-theme="dark"] {
  --bg: #0f1115;
  --bg-subtle: #1a1d24;
  --bg-muted: #22262e;
  --border: #2a2e37;
  --text: #e9ecef;
  --text-secondary: #8b95a5;
  --text-muted: #5c6573;
  --accent: #818cf8;
  --accent-hover: #a5b4fc;
  --accent-subtle: #1e1b3a;
  --danger: #f87171;
  --success: #4ade80;
  --warning: #fbbf24;
}

@media (prefers-color-scheme: dark) {
  :root:not([data-theme="light"]) {
    /* dark tokens auto-apply unless user explicitly chose light */
  }
}
```

---

## 14. No-flash Theme Script

```html
<!-- in <head>, before CSS loads -->
<script>
  (function() {
    const stored = localStorage.getItem('theme'); // 'light' | 'dark' | null
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const theme = stored || (prefersDark ? 'dark' : 'light');
    document.documentElement.setAttribute('data-theme', theme);
  })();
</script>
```

---

## 15. Implementation Notes

- **Blade components** are the primary UI building block. Each component in §6 is a Blade anonymous component (`<x-card>`, `<x-button>`, etc.).
- **Alpine.js** handles client-side interactivity: dropdowns, modals, toasts, lightbox, view toggles, search filtering. Each component uses `x-data` for local state.
- **Livewire** handles server interactions: form submission, data fetching, code generation, revocation. Livewire dispatches browser events that Alpine listens to for UI updates.
- **WebCrypto** modules are plain JS files imported dynamically. They're not Blade components — they're utility functions called from Alpine components or inline scripts.
- **Tailwind** is purged to only include used classes. The `content` array in `tailwind.config.js` covers all Blade files, JS files, and Livewire PHP files.
- **No jQuery.** No Bootstrap. No Material UI. Tailwind + Alpine + Blade only.

---

## 16. Per-Module UI Summary

| Module | Key screens | Components used |
|---|---|---|
| 1. Auth | Login, register, keygen, recovery code display, password recovery | `x-input`, `x-button`, `x-code-display`, `x-modal`, `x-toast` |
| 2. Workspaces | Dashboard, create workspace, workspace list | `x-card`, `x-sidebar`, `x-empty-state`, `x-button`, `x-input` |
| 3. Access codes | Code management list, generate modal, code entry screen, code display | `x-code-display`, `x-progress-bar`, `x-badge`, `x-confirm`, `x-modal` |
| 4. Collections & galleries | Collection view, gallery cards, create forms, member management | `x-card`, `x-badge`, `x-dropdown`, `x-modal`, `x-tabs` |
| 5. Media | Gallery grid/masonry/list, lightbox, upload, skeleton states | `gallery-grid`, `gallery-masonry`, `gallery-list`, `x-lightbox`, `x-skeleton`, `x-toast` |
| 6. Re-key | Re-key confirmation modal, progress bar, re-key complete toast | `x-modal`, `x-progress-bar`, `x-confirm`, `x-toast` |

---

## 17. Open Items

- [ ] Confirm accent color: indigo (`#4f46e5`) — or prefer a different hue (e.g. teal, violet, blue)?
- [ ] Confirm font: Inter — or prefer another (e.g. system-ui stack only, no web font)?
- [ ] Decide: should the lightbox support zoom (pinch/double-click) in v1 or defer to v2?
- [ ] Decide: should there be a workspace cover image (shown in workspace list)? If yes, it would need to be encrypted too.
