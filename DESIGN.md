---
name: Balloonventory
description: Multi-tenant inventory system for professional balloon artists tracking bags of balloons across colors, sizes, brands, and jobs. Used at the artist's home base, office, or warehouse to check balloons in (whether arriving from a distributor or returning from a job) and check balloons out in preparation for a job. Both movements happen primarily via UPC barcode scanner. A separate Jobs workflow lets the user assemble a planned SKU list per job for use in proposals and Check Out preparation, with no reconciliation against actual consumption and no cost or dollar tracking. A single user account can belong to multiple businesses with per-business permissions. Bold and modern visual identity with monochrome chrome so balloon-color data stays the loudest thing on screen.
colors:
  ink-primary: "#0A0A0A"
  ink-secondary: "#52525B"
  ink-tertiary: "#A1A1AA"
  surface: "#FFFFFF"
  background: "#F4F4F5"
  border: "#E4E4E7"
  border-strong: "#D4D4D8"
  accent: "#6D28D9"
  accent-hover: "#5B21B6"
  accent-soft: "#EDE9FE"
  accent-on: "#FFFFFF"
  success: "#16A34A"
  success-soft: "#DCFCE7"
  warning: "#EA580C"
  warning-soft: "#FFEDD5"
  danger: "#DC2626"
  danger-soft: "#FEE2E2"
typography:
  display:
    fontFamily: "Inter Tight, Inter, system-ui, sans-serif"
    weight: 600
    tracking: "-0.02em"
  body:
    fontFamily: "Inter, system-ui, sans-serif"
    weight: 400
    tracking: "0"
  mono:
    fontFamily: "JetBrains Mono, ui-monospace, SFMono-Regular, monospace"
    weight: 500
    tracking: "0"
spacing:
  xs: 4
  sm: 8
  md: 16
  lg: 24
  xl: 40
  2xl: 64
rounded:
  sm: 6
  md: 10
  lg: 14
  pill: 9999
---

# DESIGN.md — Balloonventory

A design system for a multi-tenant inventory platform built for professional balloon artists. The product tracks bags of balloons by color, size, brand, and finish, and supports two core scan-driven workflows: **Check In** (balloons arrive from a distributor or return from a job) and **Check Out** (the artist pulls inventory ahead of a job). Both happen primarily by scanning a UPC barcode with a USB or Bluetooth scanner.

A separate planning workflow — **Jobs** — lets the user assemble a planned SKU list per job for use in client proposals and Check Out preparation. Jobs are plans only, not reconciled against actual consumption. Returned bags Check In as plain stock with no link to the originating job. The system does not track unit costs or generate dollar estimates; users handle pricing in their own accounting tools.

Inventory is organized through three lenses: the full catalog, a per-business **Favorites** set (always-stocked items the user keeps top of mind), and user-named **Lists** (reusable themed collections like Halloween, Corporate Q4, holiday décor — modeled loosely on Amazon wishlists). Each SKU also carries a **price code** identifier (a variable shared across SKUs that price together: white, red, and orange 5" standard balloons share one price code; the 11" and metallic variants have different codes). Price codes are captured for future use but are not applied to any calculation in v1. A per-business **Local Prices** table in Settings lets the user record a dollar value per price code as reference data only.

The product is used at the artist's home base, office, or warehouse — never at the job itself. A single user account can belong to multiple businesses with separate per-business permissions, so the current business context must be persistent and unambiguous in every screen. The UI works equally well on a desktop at the stockroom desk and on a phone walking through warehouse aisles.

## 1. Visual Theme & Atmosphere

Bold, modern, confident. Closer to a logistics dashboard or a developer tool than a craft app. Think Linear's precision and Vercel's monochrome restraint, with a single saturated accent for interaction.

The defining principle: chrome stays neutral so saturated color in the UI always means data. A red dot is a low-stock state, a hot-pink chip is a Qualatex Wild Berry, a deep emerald block is a 16" Rich Cranberry. The interface itself never competes with that information.

This is software for working pros, not a hobbyist scrapbook. No balloon emoji in product chrome, no rainbow gradients, no rounded mascots, no party fonts. The product respects that the user runs a business.

Because users may belong to multiple businesses, the current business context is treated as a primary navigation element — always visible, never ambiguous. Mistakenly scanning into the wrong business's inventory is the worst possible failure mode for this product, so the design has to make that mistake visually difficult.

## 2. Color Palette & Roles

Every saturated color in chrome is reserved for state or interaction. Brand-style decoration uses ink and surface tokens only.

### Light theme (default)

| Token | Hex | Role |
|---|---|---|
| `ink-primary` | `#0A0A0A` | Headlines, primary text, icon defaults |
| `ink-secondary` | `#52525B` | Metadata, captions, secondary labels |
| `ink-tertiary` | `#A1A1AA` | Hints, placeholders, disabled text |
| `surface` | `#FFFFFF` | Cards, modals, table rows |
| `background` | `#F4F4F5` | App background behind surfaces |
| `border` | `#E4E4E7` | Default hairline borders, dividers |
| `border-strong` | `#D4D4D8` | Input borders, focused dividers |
| `accent` | `#6D28D9` | Primary buttons, links, active nav, focus ring |
| `accent-hover` | `#5B21B6` | Hover state for accent surfaces |
| `accent-soft` | `#EDE9FE` | Selected row backgrounds, badge fills |
| `accent-on` | `#FFFFFF` | Text/icons on accent surfaces |
| `success` | `#16A34A` | In-stock indicator, confirmations |
| `success-soft` | `#DCFCE7` | Success badge background |
| `warning` | `#EA580C` | Low-stock indicator, reorder prompts |
| `warning-soft` | `#FFEDD5` | Warning badge background |
| `danger` | `#DC2626` | Out-of-stock, destructive actions |
| `danger-soft` | `#FEE2E2` | Danger badge background |

Warning is orange rather than amber so it doesn't get lost next to typical balloon yellows in inventory tables.

### Dark theme

Mirror structure with inverted surfaces. Accent shifts lighter for contrast.

| Token | Hex |
|---|---|
| `ink-primary` | `#FAFAFA` |
| `ink-secondary` | `#A1A1AA` |
| `ink-tertiary` | `#71717A` |
| `surface` | `#18181B` |
| `background` | `#09090B` |
| `border` | `#27272A` |
| `border-strong` | `#3F3F46` |
| `accent` | `#8B5CF6` |
| `accent-hover` | `#A78BFA` |
| `accent-soft` | `#2E1065` |

### Balloon-color rendering (data, not chrome)

Balloon SKU swatches are user-entered hex values. Render as a 16px square with `rounded.sm` and a 1px inner border at `rgba(0,0,0,0.08)` (light) or `rgba(255,255,255,0.12)` (dark) so light balloon colors remain visible on white surfaces.

For metallic and chrome finishes, overlay a 45° linear gradient at 12% opacity. For matte finishes, no overlay. For confetti or print balloons, render the swatch with a single base color and append a small `print` glyph beside it.

## 3. Typography Rules

Three families, no more. Inter Tight handles display, Inter handles body, JetBrains Mono handles all numeric data (counts, SKUs, prices, sizes).

| Role | Family | Size | Weight | Line height | Tracking |
|---|---|---|---|---|---|
| Display XL | Inter Tight | 40px | 600 | 1.1 | -0.025em |
| H1 | Inter Tight | 32px | 600 | 1.15 | -0.02em |
| H2 | Inter Tight | 24px | 600 | 1.2 | -0.015em |
| H3 | Inter | 18px | 600 | 1.3 | -0.01em |
| Body | Inter | 15px | 400 | 1.5 | 0 |
| Body Small | Inter | 13px | 400 | 1.45 | 0 |
| Label | Inter | 12px | 500 | 1.3 | 0.02em |
| Eyebrow | Inter | 11px | 600 | 1.2 | 0.08em (uppercase) |
| Numeric (counts, SKUs) | JetBrains Mono | 14px | 500 | 1.4 | 0 |
| Numeric large (stock totals) | JetBrains Mono | 22px | 600 | 1.2 | -0.01em |

Use mono for any number the user will scan or compare. A "12 bags" stock count in Inter looks soft; in JetBrains Mono it reads like data.

Body sits at 15px, not 16px, because dense inventory tables benefit from a slightly tighter base. Bump to 16px on mobile for tap legibility.

## 4. Component Stylings

The primary interaction in Balloonventory is scanning, not tapping. Components are designed around a scan-first workflow with tap and type as the fallback. ScanField is the central component on the two scan-driven views — **Check In** and **Check Out** — and it should hold focus aggressively across the session.

A persistent **BusinessSwitcher** sits at the top of the sidebar (desktop) or as a sticky header element (mobile). The current business name and the user's role within it are visible on every screen.

### Buttons

| Variant | Background | Text | Border | Radius | Padding |
|---|---|---|---|---|---|
| Primary | `accent` | `accent-on` | none | `rounded.md` | 10px 16px |
| Primary hover | `accent-hover` | `accent-on` | none | — | — |
| Secondary | `surface` | `ink-primary` | 1px `border-strong` | `rounded.md` | 10px 16px |
| Ghost | transparent | `ink-primary` | none | `rounded.md` | 10px 12px |
| Danger | `danger` | `#FFFFFF` | none | `rounded.md` | 10px 16px |

Button text is Inter 14px / weight 500. No drop shadows on buttons. Focus state is a 2px `accent` ring offset by 2px from the button edge.

### Inputs

Surface white, 1px `border` default, 1px `accent` on focus with a 3px `accent-soft` halo. Radius `rounded.md`. Padding 10px 12px. Label sits above the input as Eyebrow text in `ink-secondary`. No floating labels.

### Cards

Surface white, 1px `border`, `rounded.lg`, no shadow by default. Padding 20px. Card titles use H3. A card containing a stock summary uses a `border-l-4` accent stripe in the relevant state color (success/warning/danger).

### BalloonSwatch (domain component)

A color chip representing one balloon SKU.

- Default size: 24px square, `rounded.sm`
- 1px inner border at low-opacity ink for visibility on any surface
- Optional finish overlay (metallic/chrome/pearl) as a 12% linear gradient
- When stacked in a list, swatches sit flush with no gap; when used as a chip, swatch + name + size pair on a single 32px row with 8px between elements

### StockBadge

A pill showing current count and stock state.

- Pill radius (`rounded.pill`), 4px 10px padding, JetBrains Mono 13px
- In stock: `success-soft` bg, `success` text
- Low stock: `warning-soft` bg, `warning` text
- Out of stock: `danger-soft` bg, `danger` text

Format: `{count} bags` for ≥1, `0 bags` for empty (never "out" alone, which obscures the unit).

### FavoriteStar

A star toggle showing whether an SKU is on the current business's Favorites list. Single binary state, instant toggle, no confirmation dialog.

- 20×20px star icon: outline when unfavorited, filled when favorited
- Touch target ≥ 32×32px (padding around the icon, not the icon itself, to preserve scan-row tap precision)
- Unfavorited: `ink-tertiary` outline
- Favorited: `accent` filled
- One-tap toggle with optimistic UI update — the icon flips immediately; the network request resolves in the background and reverts on failure
- Hover (desktop): tooltip "Add to favorites" / "Remove from favorites"
- Lives on every SKU row in interactive list contexts: Inventory views (All, Favorites, Lists), search results, picker dialogs, SKU detail sheets
- Does NOT appear in read-only contexts: stock movement history, JobCard line items, ScanToast rows. Those are records, not management surfaces.

### SizeChip

Inert chip showing balloon size: `5"`, `11"`, `16"`, `260`, `350`, `646`, `Heart`, `Geo Blossom`, etc.

- 1px `border`, `rounded.sm`, 2px 8px padding
- JetBrains Mono 12px for numeric sizes, Inter 12px for named shapes
- No background fill — these stay quiet, since size is one of several attributes

### BrandTag

Tiny attribution showing manufacturer: Qualatex, Sempertex, Tuftex, Betallic, Kalisan.

- Inter 11px / weight 500, `ink-secondary`
- No background, no border, just a 6px square brand-color dot to the left of the name
- Sits in the meta row below the SKU name

### PriceCodeField

A small labeled metadata field showing an SKU's price code (e.g., `STD-5`, `MET-11`, `CHRM-16`). The price code is shared across SKUs that price together — every standard 5" latex regardless of color carries the same code.

- Lives in the SKU detail metadata block alongside manufacturer SKU and brand. NOT in inventory rows.
- Label: "Price code" in Eyebrow type, `ink-secondary`
- Value: JetBrains Mono 14px, `ink-primary`
- Inline-editable when the user has permission (PermissionGate applies)
- No surrounding chrome — no pill, no border, no chip styling. Just label-plus-value.
- Captured but never used for any calculation, sort, group, or filter in v1. The data exists for future use only.

### JobCard

A card representing one upcoming job: a planned SKU list the artist has built for a client proposal, plus a stock-readiness check ahead of Check Out day.

- Standard card chrome plus a left-aligned date block (day/month) in JetBrains Mono
- Body lists planned SKUs as `{swatch} {name} {size} × {count}` rows
- Footer shows stock readiness: `12 / 15 SKUs in stock` with a thin progress bar in `accent`
- If any planned SKU is short of the requested count in current inventory, the readiness flips to `warning` color and lists the shortfall items below the footer
- Readiness is informational only. Nothing reconciles after Check Out, and returned bags Check In as plain stock with no link back to this job
- No dollar amounts, no cost figures, no pricing fields anywhere on the card. Pricing happens in the user's accounting tool, not here.

### ListChip

Pill showing membership in a custom user-named list. Surfaced in SKU detail views to indicate which Lists this SKU belongs to. NOT shown on main inventory rows — would clutter scan-heavy contexts.

- `rounded.pill`, 2px 8px padding, Body Small 12px / weight 500
- `accent-soft` background, `accent` text
- Shows the list name, truncated at 24 characters with ellipsis
- Multiple chips stack horizontally with 4px gap
- Beyond 3 visible chips, collapse the rest into a "+N more" chip in the same style; tapping it expands inline
- Each chip is tappable: jumps to that List's view with the SKU pre-highlighted

### ListCard

Card representing one custom list in the Lists overview view (the result of choosing "Lists" in ScopeTabs without picking a specific one).

- Standard card chrome: `surface` bg, 1px `border`, `rounded.lg`, 20px padding, no shadow
- Header: list name (H3) on the left + item count in JetBrains Mono `ink-secondary` on the right (e.g., `24 SKUs`)
- Body: horizontal row of up to 6 BalloonSwatch previews from the list. If the list contains more, append a small `ink-tertiary` "+N" indicator in the same row.
- Footer: optional notes in Body Small `ink-secondary`, plus a "Last edited [timestamp]" eyebrow tag on the right
- Tap to open the list view: a full SKU list with planned quantities per item
- No date field, no client field — that distinguishes Lists from Jobs. If the user wants those, they should create a Job, not a List.
- No "delete" affordance on the card. Deletion lives inside the list editor surface.

### ReorderAlert

Inline banner above an inventory table or on the dashboard.

- `warning-soft` background, 1px `warning` border at 30% opacity, `rounded.md`
- Icon + headline + secondary line + `Reorder` button on the right
- Never modal, never dismissible by default — these are persistent until resolved

### ScanField (domain component)

The primary inventory input. A single editable input that accepts USB scanner output, phone-camera-detected codes, and human typing through one path. The field is the default-focused element on the Scan view and reclaims focus after every committed scan.

- Full-width input, 56px tall (taller than standard so status is readable from arm's length)
- `surface` background, 2px `border-strong`, `rounded.md`
- Armed state (default): 2px `accent` border, label "Ready to scan" in eyebrow type, plus the current workflow ("Checking in to [Business Name]" or "Checking out for [Job Name] · [Business Name]") in body text below the field
- Status dot in the top-right corner: `success` when armed, `warning` when looking up or duplicate, `danger` on error
- Camera icon to the immediate left of the status dot opens the live viewfinder modal. There is intentionally no separate keyboard icon — the field itself accepts typing, so a parallel modal would be redundant
- On successful scan: 200ms `accent-soft` flash across the field, then it clears and re-arms automatically
- On unknown UPC: a `warning-soft` banner appears below the field with the raw UPC in monospace and an "Assign to SKU" button that routes to the inventory catalog pre-filled with the scanned code
- On duplicate scan (UPC already in recent scans): 600ms `warning-soft` flash with "Already scanned" eyebrow text. The duplicate is NOT recommitted to the server — the field flashes and clears
- Mobile: same field behavior. Tapping the field brings up the on-screen numeric keypad (via `inputmode=numeric`, `enterkeyhint=done`). The camera icon opens the viewfinder
- USB barcode scanners type their characters into whichever input is focused and press Enter on completion. With the field default-focused and auto-refocused after each commit, scanning works without any document-level keydown shim

The input commits on Enter when the trimmed value is ≥ 4 characters. After commit, the field clears and (if `externalStatus` returns to null, meaning the parent finished processing) re-focuses automatically.

**What this replaces:** an earlier iteration used a `readonly` field with a document-level keydown HID buffer and a separate "Type a barcode" modal triggered by a keyboard icon. That pattern conflicted with the modal on iOS Safari + `<dialog>` and on desktop Chromium. Direct typing is strictly better — the modal added a focus trap, an extra tap, and a buffer-timing failure mode. See the memory note `scan-field-input-pattern` for the full history.

For partial-bag check-ins, the user controls quantity + open-bag state via the `QuantityStepper` and the open-bag checkbox above the field — set once and scan many. Tapping a recent toast undoes its movement; partial bags are entered before the scan, not after.

### ScanToast

Confirmation pulse that appears below the ScanField after each successful scan. Stacks vertically with the newest on top, max 5 visible, oldest fades after 4 seconds.

- 48px row, `surface` bg, 1px `border`, `rounded.md`, padding 8px 12px
- 4px left border indicates stock direction:
  - `success` for the Check In workflow — count goes up
  - `warning` for the Check Out workflow — count goes down
- Layout: swatch + name + size chip + brand tag + mono count change (`+1` or `−1`) + small action menu
- The count change is the loudest element, JetBrains Mono 16px in `accent`
- Tap a toast to undo (removes the entry and reverses the count change)
- Toasts never block input. Continuous scanning fills the stack; older toasts age out

### BusinessSwitcher (domain component)

The persistent business context selector. Visible on every screen. The single most important piece of chrome in the product because it determines which business's inventory is being modified.

- Desktop: top of the sidebar, full sidebar width minus 16px gutter on each side. Two stacked rows of text: business name in Inter 15px / weight 600 ink-primary, user's role in that business below in Eyebrow type / `ink-secondary`. A small chevron sits on the right.
- Mobile: pinned header element above the page title, full bleed, 56px tall, `surface` bg with `border` bottom edge. Same two-line layout, chevron on the right. The wrapping flex container must use `min-w-0` so the business name truncates instead of overflowing into adjacent top-bar elements (avatar, admin shield).
- The trigger row is **two distinct click targets**, not one. Tapping the logo + name + role region navigates to `/dashboard` — the primary one-tap path to the dashboard from anywhere in the app. Tapping the chevron (44×44 tap target, rotates 180° when open) opens the switcher dropdown. This split lets the most common action (return to dashboard) be one tap while keeping the switcher discoverable.
- The dropdown (desktop) or full-screen sheet (mobile) lists all businesses the user belongs to. Each entry shows business name + user's role + small business-color indicator. The current business has an `accent` checkmark.
- A 4px-wide vertical bar in the business's chosen accent color sits on the left edge of the BusinessSwitcher at all times. This is the **business-color cue** — see BusinessBadge.
- A "Manage businesses" link at the bottom of the dropdown opens settings.

### AccountHub (page pattern)

The Account hub at `/account` is an iOS-Settings-style index page that consolidates rare-use account, business, and support controls behind a single discoverable entry point. The user reaches it by tapping their avatar in the mobile top bar, the Account tab in the mobile bottom nav, or the user-name row in the desktop sidebar footer.

- Layout: a vertical column of cards/rows on `background`, max content width inherits from the page main column
- Top: **identity card** — avatar (or person-icon fallback) on the left, user name (display 17px) + email (body small `ink-secondary`) stacked on the right, chevron trailing. Tapping navigates to `route('profile.edit')`
- Body: a single `surface` card containing rows separated by 1px `border-t`. Each row is a flex container: 32×32 icon tile (`accent-soft` bg, `accent` icon) + label + optional subtext + trailing chevron. Rows in order: Profile · Business · Preferences · Help & Support · Super Admin (only when `auth.isAnyAdmin`)
- The Business row is gated by `business.edit_settings` or `business.manage_logo` and is omitted entirely when the user has neither permission (unlike PermissionGate's disabled-state rule, which applies inside business workflows — the Account hub treats permission-irrelevant entries as not-applicable to this user, not as gated)
- Help & Support opens the `ContactSupportModal` (mounted locally on the page, not in the layout)
- **Log out** is the final row, visually separated by sitting in its own card with `bg-surface`, `danger-soft` icon tile, and `danger` text. POSTs to `/logout` directly — no confirmation modal (logging out is reversible by logging back in; a confirm dialog would create more friction than it prevents)
- This pattern replaces the older approach of stacking Help, Language, Logout buttons in the top bar. Rare-use chrome belongs inside the hub; the top bar carries only business context (BusinessSwitcher) and an avatar entry point.

### BusinessBadge

A small persistent visual cue tying every screen to its business context. The user picks a color per business at setup (default palette of 12 distinct colors, none of which are `accent` violet). That color appears as:

- A 4px vertical bar on the left edge of the BusinessSwitcher
- A 2px top border on the application frame (above any sticky headers, full viewport width)
- A 6px square dot next to the business name anywhere it appears in chrome

The BusinessBadge color is decorative chrome, not state. It never doubles as a status indicator. Its only job is peripheral-vision confirmation that the user is in the business they think they're in.

### RoleBadge

Compact label showing a user's role in a given business: Owner, Manager, Staff, Viewer.

- Pill style (`rounded.pill`), 2px 8px padding, Eyebrow type
- Owner: `ink-primary` bg, `surface` text
- Manager: `accent-soft` bg, `accent` text
- Staff: `surface` bg with 1px `border`, `ink-secondary` text
- Viewer: transparent bg, `ink-tertiary` text, no border
- Used inside the BusinessSwitcher dropdown, on the user profile menu, and beside member names in the Team management view

### PermissionGate

The visual treatment for actions the current user can't perform in the current business. Permission-gated actions are **never hidden** — hiding actions makes the product feel inconsistent across businesses and confuses users about what's possible.

- Disabled state: `ink-tertiary` text and icon, no hover effect, cursor `not-allowed`
- On hover (desktop) or long-press (mobile): tooltip shows "Requires [Role] role in [Business Name]" in Body Small on a `ink-primary` surface with `surface` text, `rounded.sm`, `shadow-pop`
- For destructive or critical actions (delete SKU, edit settings), the gated state is rendered with a small lock glyph to the left of the label

### ScopeTabs

Segmented control sitting at the top of the Inventory view, below the BusinessSwitcher and ScanField. Filters the inventory display to a subset of SKUs.

- Three positions: **All**, **Favorites**, **Lists ▾** (the chevron indicates a dropdown)
- Pill style overall (`rounded.pill`), with the active position rendered as an inner pill with `accent-soft` background and `accent` text in weight 600
- Inactive positions: `ink-secondary` text, transparent background, weight 500
- Active indicator slides smoothly between positions on change (200ms ease)
- Tapping "Lists ▾" reveals a dropdown menu of all the business's lists by name, plus a "+ New list" item at the bottom. Selecting a list scopes the inventory to that list's items.
- When "Lists" is the active scope and a specific list is selected, an H2 list name appears below the tabs, with a small "× SKUs" mono count beside it and an inline "Edit list" action on the right
- Mobile: full-width segmented control, 40px tall, sits in the sticky header area below the BusinessSwitcher
- Desktop: inline at the top of the inventory main column with 24px gap to the SKU table; sort and filter controls sit on the right of the same row

### LocalPricesTable

A two-column table living in Settings → Pricing. The business sets a dollar value for each known price code as reference data only.

- Standard table chrome (per the Tables section)
- Two columns: Price Code (JetBrains Mono, `ink-primary`) | Local Price (JetBrains Mono, `ink-primary`)
- Header row: Eyebrow type, `ink-secondary`
- Local Price cells are inline-editable on click; on blur, value is saved
- "+ Add row" action below the table for adding new price codes
- No row-delete affordance. Clearing a Local Price cell removes that price code from active reference but preserves audit history.
- Persistent banner above the table (`accent-soft` bg, `rounded.md`, 12px padding, Body Small `accent` text): "Local Prices are reference data. They are not currently applied to job estimates, proposals, or any other UI."
- This is the only place dollar amounts appear in the product. Everywhere else, the no-money rule applies.

### Tables

Used only on desktop and tablet landscape. On phones, tables collapse to stacked cards.

- Row height 48px, header row 40px
- Header row: `background` fill, Eyebrow type, `ink-secondary`
- Row hover: `accent-soft` at 40% opacity
- Selected row: `accent-soft` full opacity with 2px `accent` left border
- Cell padding: 12px horizontal, 0 vertical (height comes from row)
- Numeric columns right-aligned with mono type
- Sticky first column on horizontal scroll

### Navigation

- Desktop: 240px fixed left sidebar, `surface` bg, `border` right edge. **BusinessSwitcher pinned at the top, then a 24px gap, then nav sections separated by 24px and Eyebrow-style headers.** Inventory is an expandable section with sub-items: All Stock, Favorites, and a nested Lists tree (each list by name, plus "+ New list" at the bottom of the tree). Clicking a sub-item navigates and sets the matching ScopeTabs state on the main view. A 2px BusinessBadge color bar runs across the top of the entire viewport above any chrome.
- Mobile: bottom tab bar, 5 items, 56px tall, `surface` bg with `border` top edge, active item in `accent`. Items: Inventory, **Jobs**, **Scan** (center), Reorder, Account. The center Scan item is visually elevated as a circular button (see Mobile-specific rules in Section 8). The Account tab uses the user's avatar (or a person icon fallback) and opens the Account hub (Profile / Business / Preferences / Help & Support / Log out). The ScopeTabs (All / Favorites / Lists) live at the top of the Inventory view, not in the bottom nav. **A sticky BusinessSwitcher header sits above the page title on every screen, with the BusinessBadge color bar above it.**
- Active nav item: `accent-soft` background, `accent` text, `rounded.md`

## 5. Layout Principles

8px spacing scale with 4px half-step. Never use values outside the scale.

- Mobile: single column, 16px horizontal padding, full-bleed cards with internal padding
- Tablet: 2-column where useful, 24px gutters
- Desktop: max content width 1280px, sidebar 240px, main content padded 32px

Generous whitespace at section level (xl/2xl gaps), tight whitespace inside dense data (sm gaps in tables and chip groups).

Never nest cards. If you find yourself wanting a card inside a card, the inner thing should be a row, a divider section, or a list item.

## 6. Depth & Elevation

This is a flat system. Hierarchy comes from borders, surface contrast (background vs surface), and typography weight — not shadows.

Two shadows allowed, used sparingly:

- `shadow-pop`: `0 4px 12px rgba(0,0,0,0.08)` — for menus, popovers, and dropdowns only
- `shadow-modal`: `0 20px 50px rgba(0,0,0,0.18)` — for modals and full-screen sheets only

Cards, inputs, buttons, and nav have zero shadow.

## 7. Do's and Don'ts

**Do:**
- Use `accent` (violet) sparingly. One primary action per screen. Multiple violet elements signal nothing because nothing stands out.
- Use mono type for every number a user will compare or scan.
- Render balloon colors as data with consistent swatch chrome (size, border, finish overlay).
- Treat low-stock and out-of-stock as visual priorities. These are why the product exists.
- Right-align numeric table columns.
- Default focus to ScanField on Check In and Check Out views. Reclaim focus when scan input is detected anywhere on the page.
- Make unknown UPCs a one-tap path to "assign to existing SKU" or "create new SKU." Never a dead end.
- Use brief 200ms visual flashes for scan feedback. Scanners beep on their own; the UI shouldn't compete.
- Write empty-state copy as scan-first ("Scan a barcode to add your first bag") rather than tap-first.
- Show the BusinessSwitcher and BusinessBadge color bar on every screen without exception, including modals and full-screen scan views.
- Echo the current business name inside the ScanField label ("Checking in to [Business Name]") so the user can confirm context without looking away from the bag.
- Render permission-gated actions in a disabled state with a tooltip explaining the missing role. Never hide them.
- Show the FavoriteStar on every SKU row in interactive list contexts (Inventory views, search results, picker dialogs). One-tap toggle, optimistic UI.
- Treat Favorites and Lists as inventory views, not separate features. Both live behind the ScopeTabs at the top of the Inventory view; both are filters over the same underlying SKU set.
- Capture price codes when entering or editing SKUs. Surface them quietly in SKU detail metadata. Don't compute anything against them.
- Allow users to add a SKU to a List from the SKU detail sheet via an "Add to list" action that opens a menu of existing lists plus "+ New list."

**Don't:**
- Don't introduce additional accent colors. Violet is the only interaction color. (BusinessBadge colors are decorative chrome, not interaction state — they don't count.)
- Don't use balloon or party emoji in chrome (🎈🎉). Acceptable inside user-generated content (notes, job titles) but never in nav, buttons, or empty states.
- Don't use rainbow gradients, ever. Gradients are reserved for individual balloon swatches with metallic/chrome finishes.
- Don't use rounded radii larger than `rounded.lg` (14px). No fully-pill cards or buttons (pill is for badges only).
- Don't put text on top of balloon swatches. Swatches are read visually; the name lives next to them.
- Don't default to "modern" sans-serifs with quirky character (Comfortaa, Quicksand, Fredoka). Stay with Inter.
- Don't shrink touch targets below 44×44px on mobile, even for inert chips.
- Don't use shadow on cards or buttons. The mobile Scan button is the only exception.
- Don't require a confirmation modal after each scan. Continuous scanning must never be blocked.
- Don't show full UPC strings in primary inventory rows. Display last 6 digits in detail views only, in JetBrains Mono.
- Don't use the same color treatment for stock-in and stock-out scan toasts. Success for Check In, warning for Check Out.
- Don't display dollar amounts, prices, costs, or any monetary value in inventory, scan, job, list, or reorder views. The LocalPricesTable in Settings is the only surface where dollar values appear, and even there they are captured-but-not-applied reference data, never used in calculations.
- Don't apply Local Prices to job estimates, list totals, reorder calculations, or anywhere else. They sit in the database as future raw material; nothing computes against them in v1.
- Don't display price codes prominently. They live in SKU detail metadata only — never as a column in inventory tables, never in scan toasts, never in JobCard or ListCard rows.
- Don't conflate Lists with Jobs. Lists are reusable themed collections (Halloween, Corporate Q4) with no date or client. Jobs are specific work assignments with a date, client, and Check Out workflow. A List's UI must not have a date field or a client field.
- Don't show the FavoriteStar in read-only contexts (audit log, JobCard line items, ScanToast rows). Stars belong on management surfaces, not on records.
- Don't show consumption tracking, fulfillment confirmation, or estimate-vs-actual reconciliation anywhere in the UI. The product does not track actual usage; pretending it does will mislead users.
- Don't allow a destructive action (delete SKU, delete business, transfer inventory) without showing the current business name in the confirmation dialog.
- Don't reuse a BusinessBadge color across two businesses for the same user. Color collisions defeat the entire purpose of the badge.
- Don't hide permission-gated UI. The user has to be able to see what their role can't do, with the reason.

## 8. Responsive Behavior

Three breakpoints:

| Name | Min width | Layout |
|---|---|---|
| Mobile | 0 | Single column, bottom nav, cards instead of tables |
| Tablet | 768px | Two-column where useful, top nav, tables allowed |
| Desktop | 1024px | Sidebar nav, full table layouts, max content 1280px |

### Mobile-specific rules

- Touch targets ≥ 44×44px
- Bottom nav with 5 items: Inventory, **Jobs**, **Scan** (center), Reorder, Account. The mobile top bar carries only the BusinessSwitcher (left), an optional admin shield, and the user's avatar (right, links to the Account hub). Rare-use controls (help, language, logout) live inside the Account hub, not in the top bar.
- The Scan item in the center slot is visually elevated: 56px diameter circle, `accent` background, barcode glyph in `accent-on`, `shadow-pop`, sits 12px above the bottom nav baseline. This is the only exception to "no shadows." Tapping it opens the camera scanner overlay; long-pressing offers manual entry.
- Inventory list shows: swatch, name, size, brand, stock badge, FavoriteStar (right-anchored) — in a single 64px row
- Tap a row → sheet from bottom with full SKU detail (UPC visible in detail view as last 6 digits in JetBrains Mono)

### Desktop-specific rules

- Sidebar fixed at 240px
- The persistent ScanField sits at the top of Check In and Check Out views — pinned, not scrollable away
- Tables show: FavoriteStar (left-anchored, fixed 40px width), swatch, name, brand, finish, size, count, last restocked, actions. UPC is searchable but not a default column (visible on row expand)
- Hover states use `accent-soft`
- Bulk select via checkbox column on the left

### Table-to-card collapse

Below 768px, every table becomes a vertical list of cards. Each card mirrors the row content stacked: swatch + name on top row, brand + size + finish on a meta row, stock badge on the right.

## 9. Icons

Icons are inline SVGs copied directly into Vue components — no icon library package, no CDN dependency.

**Source:** Heroicons v2 (MIT license, no attribution required). All icons come from this set. Do not introduce icons from other sets.

**Grid:** Use `viewBox="0 0 20 20"` as the default. `24×24` is acceptable for larger contexts (page headers, empty states). `16×16` for very tight inline uses (badges, table cells). Do not mix grids within the same component.

**Style:** Prefer `fill="currentColor"` (solid style) for UI chrome. Use `stroke="currentColor"` (outline style) sparingly — only where a lighter visual weight is clearly better (e.g., the unfavorited star, the camera outline in ScanField).

**Color:** Always use `currentColor` so the icon inherits its color from the surrounding Tailwind text class. Never hardcode a hex value in an SVG `fill` or `stroke` attribute.

**Sizing:** Control size via Tailwind (`h-4 w-4`, `h-5 w-5`, `h-6 w-6`). Do not set `width` or `height` attributes directly on the `<svg>` element.

**Naming convention:** Add `<!-- icon: {heroicons-slug} -->` as an HTML comment immediately before every `<svg>` tag, using the exact Heroicons v2 slug (e.g. `chevron-down`, `magnifying-glass`, `star`). This makes icons grep-able: `grep -r "icon: star" resources/js` finds every usage instantly. Comments are stripped by the Vite build and never reach the browser.

**Accessibility:** Add `aria-hidden="true"` to decorative icons (icons paired with visible text, or inside buttons that already carry an `aria-label`). For interactive icon-only buttons (no visible label), add `aria-label` to the button element, not the SVG itself.

**Do not** use emoji as icon substitutes in chrome. **Do not** pull icons from a CDN or add an icon package dependency — inline SVG is intentional to keep the bundle self-contained and avoid layout flash.

### Icons currently in use

These are the Heroicons v2 slugs used across the codebase. Check this list before adding a new icon — reuse what's already here first.

| Icon | Used for |
|---|---|
| `archive-box` | Archive actions |
| `arrow-left` | Back navigation |
| `arrow-left-on-rectangle` | Log out |
| `arrow-path` | Refresh / sync |
| `arrow-uturn-left` | Undo scan |
| `bars-3` | Mobile menu / hamburger |
| `bell` | Notifications |
| `book-open` | Documentation / guides |
| `building-office` | Business |
| `calendar` | Date fields, jobs |
| `camera` | Camera scanner trigger |
| `chart-bar` / `circle-stack` | Stats, database |
| `check` | Confirmation, success state |
| `chevron-down` | Dropdowns, accordions |
| `chevron-left` | Back, previous |
| `chevron-right` | Forward, next, list rows |
| `clipboard-document-list` | Lists, copied content |
| `cog-6-tooth` | Settings |
| `credit-card` | Billing |
| `cube` | SKU / product |
| `document` | Files, records |
| `document-duplicate` | Duplicate action |
| `ellipsis-vertical` | Action menus (⋮) |
| `envelope` | Email |
| `exclamation-circle` | Inline error |
| `exclamation-triangle` | Warning banner |
| `globe-alt` | Locale / language |
| `inbox` | Inbox, messages |
| `lock-closed` | Permissions, locked state |
| `magnifying-glass` | Search |
| `minus` | Decrement, remove |
| `plus` | Add, increment |
| `qr-code` | Barcode / scan |
| `question-mark-circle` | Help, unknown state |
| `shield-check` | Super admin, security |
| `shopping-bag` | Check out workflow |
| `squares-2x2` | Grid view |
| `star` | Favorites |
| `table-cells` | Distributor / data table |
| `trash` | Delete |
| `user` | Profile, single user |
| `user-plus` | Invite user |
| `users` | Team / multiple users |
| `x-mark` | Close, dismiss |

## 10. Accessibility

Accessibility is a build-toward standard, not a retroactive audit. Apply these rules to all new components and when editing existing ones.

### Icons

- All decorative SVGs (icons paired with visible text, or inside labeled buttons) must have `aria-hidden="true"`.
- Interactive icon-only buttons must have `aria-label` on the `<button>` element. Use i18n keys (`$t(...)`) for all user-visible strings so labels are translatable.
- Always use the `<!-- icon: slug -->` comment convention so icons are grep-able.

### Buttons and interactions

- `aria-label` is required on any button or control with no visible text label.
- `aria-expanded` is required on any toggle that shows/hides content (dropdowns, accordions, collapsible panels).
- `aria-pressed` is required on binary toggle buttons (e.g., a theme toggle).

### Forms

- Every input must have an associated `<label>` (or `aria-label` if the label is visually hidden). Placeholder text is not a substitute for a label.
- Error messages must be associated with their input via `aria-describedby`.

### General

- Minimum touch target size: 44×44px on mobile (apply padding, not size, to preserve layout).
- Do not rely on color alone to convey state — pair color with text, icon, or pattern.
- Do not use `tabindex` values greater than 0.

## 11. Agent Prompt Guide

### Quick reference

- Single accent: `#6D28D9` violet. Use for one primary action per screen.
- Three font families: Inter Tight (display), Inter (body), JetBrains Mono (numbers).
- Spacing scale: 4, 8, 16, 24, 40, 64. Nothing in between.
- Radii: 6, 10, 14, 9999. Pill only for badges.
- No shadows except `shadow-pop` (menus, mobile Scan button) and `shadow-modal` (modals).
- Balloon colors are data. Render as swatches with consistent chrome, never as backgrounds.
- Numbers use JetBrains Mono. Always.
- Mobile gets a bottom nav with elevated center Scan button. Desktop gets a sidebar. Tables collapse to cards below 768px.
- **Scan-first**: ScanField is the default focus on Check In and Check Out views. Empty states say "scan to begin." Unknown UPCs are a one-tap path to assignment, never a dead end. Continuous scanning is never blocked by modals.
- **Multi-tenant**: BusinessSwitcher is visible on every screen (sidebar top on desktop, sticky header on mobile). A 2px BusinessBadge color bar runs across the top of the viewport at all times. The current business name is echoed inside the ScanField label and inside every destructive confirmation dialog. Permission-gated actions are disabled with a tooltip, never hidden.
- **Inventory views**: ScopeTabs at the top of Inventory toggle between All, Favorites, and Lists. Favorites is a single per-business pinned set; Lists are user-named collections (Halloween, Corporate Q4) modeled on Amazon wishlists. FavoriteStar appears on every SKU row in interactive contexts. ListChips appear only in SKU detail.
- **Price codes & money**: Each SKU has a price code (small metadata, never prominent). The LocalPricesTable in Settings is the only place dollar values appear, and it's pure reference data — no calculations against it anywhere in v1.

### Ready-to-use prompt

> Build [component] for the Balloonventory inventory system. Follow DESIGN.md exactly. Use the violet accent (`#6D28D9`) only for the primary action. Numbers in JetBrains Mono. Balloon colors are user data — render them as 24px square swatches with a 1px inner border, never as background fills. No shadows on cards or buttons (the mobile Scan button is the only exception). Markup must work equally well at mobile and desktop widths up to 1280px. No balloon emoji. No additional accent colors. Treat scanning as the primary interaction: ScanField holds focus, scan feedback is brief and visual, continuous scanning is never blocked. The product is multi-tenant: assume a current business context exists, show the BusinessSwitcher and BusinessBadge color bar in chrome, echo the current business name in the ScanField label and any destructive confirmation, and render permission-gated actions as disabled with a tooltip rather than hidden. Show the FavoriteStar on every SKU row in interactive contexts. Treat Favorites and Lists as ScopeTabs filters on the Inventory view, not separate top-level features. Do not display dollar amounts, costs, prices, or any money anywhere except inside the LocalPricesTable in Settings, and even there present them as captured-but-unused reference data with the standard "not applied to any UI" banner.

### Component prompt example

> Build a JobCard component. Show the job date in a left-aligned block (day in large mono, month abbreviated in eyebrow type). Body lists planned balloon SKUs as rows of swatch + name + size chip + mono count. Footer shows stock readiness as `{in-stock} / {total} SKUs in stock` with a thin violet progress bar. If any planned SKU is short of the requested count in current inventory, the readiness flips to warning (`#EA580C`) and lists the shortfall items. The card is scoped to the current business — do not show jobs from other businesses the user belongs to, and include the business name as a small `ink-secondary` tag in the card header. Do not show any post-job consumption data, dollar amounts, or cost figures anywhere: the product does not track usage or money.
