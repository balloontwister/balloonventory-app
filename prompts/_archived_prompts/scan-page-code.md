This was used for a code review and now just here for archival purposes so we have a trail of how the scanning page was built

# Scan Page — Code Review Document

## Overview

The `/scan` page is the primary input surface for end users. It lets balloon decorators scan barcodes (UPC-A, EAN-13, etc.) to check balloons in and out of inventory. The page handles three input methods — USB barcode scanner, phone camera, and manual keyboard entry — all funneling into the same UPC → SKU → stock movement pipeline.

## Review Handoff Prompt

```
Review the /scan page implementation for Balloonventory.

—— CONTEXT ——
Target users scan a UPC to add or remove balloons from inventory. They use:
- A laptop with a USB barcode scanner
- An iOS/Android phone with the built-in camera
- Rarely, typing a UPC by hand

The UI has a large Add button, a large Remove button, smaller controls
to change quantity and toggle open bags, and a scan input field. Each
scan auto-commits immediately with an undo toast.

—— WHAT WAS BUILT ——
- POST /scan/lookup  — UPC → SKU resolution
- POST /scan/check-in / check-out — record stock movements
- POST /scan/undo/{movementId} — compensating reversal
- Scan/Index.vue — full page (mode toggle, quantity stepper w/ presets,
  open-bag toggle, scan field, camera modal, manual entry modal,
  unknown-UPC banner, recent scans list, toast notifications)
- CameraScanner.vue — live viewfinder (BarcodeDetector API + quagga2 fallback)
- QuantityStepper.vue — presets 3/5/10 + −/+ stepper
- useCameraScan.js — hybrid camera composable
- ScanField.vue — updated with camera button, external status, error state
- ScanToast.vue — updated for new backend response shape
- 14 PHPUnit tests (ScanControllerTest)
- 32 translation keys in lang/en/scan.php + lang/es/scan.php
- @ericblade/quagga2 added to package.json

—— WHAT TO LOOK FOR ——
- Tenant isolation: every query scoped to BusinessContext::currentId()
- Undo creates compensating movement, never deletes (append-only table)
- Auto-commit + undo pattern (no preview-then-confirm step)
- Camera: BarcodeDetector on Chrome, quagga2 lazy-loaded on Safari
- Quantity: presets 3/5/10 for common batch sizes, stepper for edge cases
- Open bags toggle: mutually exclusive with full bags
- Zero-quantity scans rejected server-side
- Default bin/location auto-created if missing
- All tests pass: 280 tests, 1500 assertions
- Pint formatting passes, Vite build is clean

—— DEFERRED ——
- Bin scan codes (BIN-XXXXXXXX) — schema ready, handler deferred
- Pending UPC resolution flow — schema ready, UI not integrated
- Job association during check-out — endpoint accepts job_id, no UI yet
- Offline mode / PWA
```

---

## User Flow

1. User arrives at `/scan`. Mode defaults to **Add** with quantity 1, open bags off.
2. User can toggle between Add and Remove via large mode buttons.
3. User adjusts quantity (preset chips 3/5/10 or stepper −/+) and optionally toggles "open bag."
4. User scans a barcode (USB scanner, phone camera, or typed manually).
5. The UPC is looked up server-side. If found, a stock movement is recorded immediately. If not, an "Unknown UPC" banner appears with an "Assign to SKU" button that navigates to the inventory catalog pre-filled with the scanned code.
6. Each successful scan adds a row to the "Recent scans" list and shows a toast (auto-dismiss 4s) with an **Undo** button.
7. Undo creates a compensating stock movement (reversed direction) and adjusts stock levels back.

---

## Files

### Backend — `app/Http/Controllers/ScanController.php`

#### `index()` — GET /scan
Renders `Scan/Index.vue` via Inertia. No server props needed; the page is client-driven.

#### `lookup()` — POST /scan/lookup
Accepts `{ upc: string }`. Queries the `skus` table by UPC, respecting the SKU visibility rule (shared or owned by current business). Eager-loads brand, balloonSize→shape, color, material.

**Returns:**
```json
{ "found": false }
// or
{
  "found": true,
  "sku": {
    "id", "name", "computed_name", "warehouse_sku", "upc",
    "default_count_per_bag",
    "color": { "id", "name", "color_hex" },
    "brand": { "id", "name", "abbreviation" },
    "balloon_size": { "id", "name",
      "shape": { "id", "name" },
      "size": { "id", "name" }
    },
    "full_bags_total": <sum across bins>,
    "open_bags_total": <sum across bins>
  }
}
```

#### `checkIn()` — POST /scan/check-in
#### `checkOut()` — POST /scan/check-out

Both delegate to a shared `recordMovement()` method. Accept:
```json
{
  "sku_id": "uuid",
  "full_bags_change": 0,
  "open_bags_change": 0,
  "job_id": "uuid|null"
}
```

**Flow:**
1. Validate input (at least one quantity > 0 required)
2. Resolve current business + user
3. `resolveBin()` — finds or auto-creates the Default bin and location for this business
4. Inside a transaction: create `StockMovement` + upsert `StockLevel`
5. Reload SKU with fresh stock totals, return movement_id + updated SKU data

The response shape matches what `ScanToast.vue` and the recent-scans list expect.

#### `undo()` — POST /scan/undo/{stockMovement}
- Verifies the movement belongs to the current business (404 otherwise)
- Only reverses `in`/`out` movements (422 for `removed`/`restored`/`adjusted`)
- Inside a transaction: creates a compensating movement (reversed direction) and reverses the stock-level increments/decrements
- Preserves the audit trail — the original movement stays, the undo is a new row

#### `resolveBin()` helper
Mirrors the pattern from `InventoryController::store()`. Falls back through:
1. `$business->defaultBin()` — returns existing default bin
2. `$business->defaultLocation()` — returns existing default location, or creates one
3. Creates a new Default bin in that location

### Routes — `routes/web.php`

Five routes under the `auth, verified, ensure.business` middleware group:
```
GET   /scan                        scan.index
POST  /scan/lookup                 scan.lookup
POST  /scan/check-in               scan.check-in
POST  /scan/check-out              scan.check-out
POST  /scan/undo/{stockMovement}   scan.undo
```

---

### Frontend — Page: `resources/js/Pages/Scan/Index.vue`

The page is mode-driven: the user sets their intent (Add/Remove, quantity, open-bag toggle) and then scans rapidly. Each scan auto-commits.

**State variables:**

| State | Type | Default | Purpose |
|---|---|---|---|
| `mode` | `'add' \| 'remove'` | `'add'` | Which mode button is active |
| `quantity` | `number` | `1` | Bags per scan |
| `isOpenBag` | `boolean` | `false` | Whether scan affects `open_bags` |
| `scanStatus` | `null \| 'looking_up' \| 'error'` | `null` | External status for ScanField |
| `unknownUpc` | `null \| { upc }` | `null` | Shows unknown-UPC banner |
| `recentScans` | `array` | `[]` | Last 30 scan results |
| `showCamera` | `boolean` | `false` | Camera modal open |
| `showManualEntry` | `boolean` | `false` | Manual entry modal open |
| `manualUpc` | `string` | `''` | Typed UPC value |

**Layout sections:**

1. **Mode toggle** — Two large buttons (Add / Remove). Active button is filled with brand color + shadow; inactive is outlined. Swapping mode does not reset quantity or open-bag toggle (intentional — user sets mode once, scans many).

2. **Quantity controls** — Card containing `QuantityStepper` + open-bag checkbox + context hint ("Adding 3 bags to inventory." / "Removing 1 open bag from inventory."). Context hint hidden on mobile (<md).

3. **ScanField** — The scan input. Receives `externalStatus`, `showCameraButton`, `cameraSupported` props. Emits `scan` (UPC detected), `camera` (camera icon tapped), `manual-entry` (keyboard icon tapped).

4. **Unknown UPC banner** — Warning-colored card with the raw UPC in monospace and an "Assign to SKU" button. Button navigates to `inventory.index?search=<UPC>` for manual resolution.

5. **Recent scans list** — Sectioned card with "Recent scans" header and "Clear all" action. Each row: direction badge (+/− circle), color swatch, SKU name (truncated), open-bag badge if applicable, quantity delta, undo button.

6. **Toasts** — Fixed at bottom-center on mobile, static in flow on desktop. Shows latest 3 scan results with auto-dismiss + undo.

7. **Camera modal** — Full `Modal` with `CameraScanner` inside.

8. **Manual entry modal** — Simple form with a numeric-input field and Scan/Cancel buttons. Enforces min 4 characters before allowing submit.

**Scan processing flow (`processScan`):**
```
UPC received → status='looking_up' → POST /scan/lookup
  ├─ found → POST /scan/check-in (or check-out) → push to recentScans → status=null
  └─ not found → status=null → show unknownUpc banner
Any error → status='error' → auto-clear after 4s
```

Uses `window.axios` (bootstrapped in `app.js`) for all JSON calls. Routes are generated by Ziggy's `route()` helper.

**Undo flow (`undoScan`):**
```
POST /scan/undo/{movementId} → filter movement from recentScans array
```

---

### Frontend — Component: `resources/js/Components/ScanField.vue`

The scan input widget. Designed to work with USB HID scanners (keyboard buffer) and provide affordances for camera and manual entry.

**Props:**
- `workflow` — `'check_in'` / `'check_out'` — for the context label
- `recentScans` — array of `{ upc }` objects for duplicate detection
- `externalStatus` — `null | 'looking_up' | 'error'` — overrides internal status when controlled by parent
- `showCameraButton` — boolean
- `cameraSupported` — boolean

**Internal behavior:**
- Uses `useScanField` composable for keyboard HID capture (global keydown listener, 80ms buffer timeout)
- Duplicate detection against `recentScans` array (checks `s.upc === value`)
- When `externalStatus` is provided, the component becomes a controlled display (no internal status transitions)
- When `externalStatus` is null, internal status cycles armed → success/duplicate → armed

**Status styling:**

| State | Border | Background | Status dot |
|---|---|---|---|
| armed | accent | surface | green |
| looking_up | accent | surface | yellow spinner |
| success | accent | accent-soft | green |
| error | danger | danger-soft | red |
| duplicate | warning | warning-soft | yellow |

**Icon layout:** Status dot at `right-4`, camera icon at `right-9`, keyboard icon at `right-14`. Icons shift left when camera is hidden.

---

### Frontend — Component: `resources/js/Components/ScanToast.vue`

Auto-dismissing toast notification. Updated from the original (which expected `scan.sku.hex`, `scan.sku.finish`, etc.) to match the new backend response shape.

**Props:**
- `scan` — object with `movement_id`, `direction`, `full_bags_change`, `open_bags_change`, `sku`

**Display:**
- Color swatch from `sku.color.color_hex`
- SKU name from `sku.computed_name` (falls back to `sku.name`)
- Open-bag badge when `open_bags_change > 0`
- Delta (`+3` / `−1`) colored green/yellow
- Undo button (undo-arrow icon) emitting `undo` event with `movement_id`
- Green left border for check-in, yellow for check-out
- Auto-dismiss after 4s via `setTimeout` + `visible` ref

---

### Frontend — Component: `resources/js/Components/CameraScanner.vue`

Live camera viewfinder for mobile barcode scanning.

**Internal:** Uses `useCameraScan` composable. On mount, calls `start()` with a `<video>` ref. Emits `detected` (UPC string), `error`, and `close` events.

**UI:**
- `<video>` fills the container (aspect-ratio 3:4 mobile, 4:3 desktop)
- Scan region overlay: white-bordered rectangle with red laser-line animation crossing horizontally
- Green flash overlay on detection (75ms enter, 200ms leave transition)
- Error state: dark overlay with message text
- Close button (X) in top-right corner

---

### Frontend — Component: `resources/js/Components/QuantityStepper.vue`

Reusable quantity control with preset chips and −/+ stepper.

**Props:**
- `modelValue` (v-model, default 1)
- `presets` (array, default `[3, 5, 10]`)
- `min` (default 1)

**Behavior:**
- Tapping a preset chip jumps to that value; chip is filled when active
- −/+ buttons have 44px touch targets
- − is disabled when value equals min
- Value displayed in monospace tabular-nums at 20px

---

### Frontend — Composable: `resources/js/Composables/useCameraScan.js`

Camera barcode detection with a two-tier backend strategy:

1. **Native `BarcodeDetector` API** (Chrome/Edge Android, desktop Chrome) — instant, no library overhead. Formats: `ean_13`, `ean_8`, `upc_a`, `upc_e`, `code_128`, `code_39`.
2. **`@ericblade/quagga2` fallback** (iOS Safari, Firefox) — lazy-loaded via dynamic `import()` so the 156KB payload only loads when needed. Cached by Vite/service worker on first load.

**API:**
```
useCameraScan({ onDetected, onError })
  → { start(videoElement), stop(), isScanning, isSupported, error }
```

**Key behaviors:**
- Feature-detection on construction (`checkSupport()`)
- `start()`: requests `getUserMedia({ video: { facingMode: 'environment' } })`, attaches stream to `<video>`, begins detection loop
- Native loop: fires `BarcodeDetector.detect()` every 200ms
- Quagga loop: `Quagga.init()` with LiveStream, `Quagga.onDetected()` callback
- 1500ms cooldown after each detection to prevent double-scans
- `navigator.vibrate(50)` on detection for haptic feedback
- `stop()`: releases camera stream, stops detection, stops Quagga
- Cleanup on `onUnmounted`

---

### Frontend — Composable: `resources/js/Composables/useScanField.js` (UNCHANGED)

Existing keyboard HID composable. No modifications needed — it already captures global keydown events, buffers characters at 80ms intervals (scanner-speed heuristic), and flushes on Enter or timeout. Minimum scan length 4 characters.

---

### Translations — `lang/en/scan.php` & `lang/es/scan.php`

Expanded from 2 keys to 32 keys each. English and Spanish are full mirrors.

**Key groups:**
- Mode: `mode_add`, `mode_remove`
- Quantity: `qty_label`, `qty_preset_3/5/10`
- Open bag: `open_bag_label`
- Scan field: `scan_placeholder`, `scanning`, `looking_up`
- Camera: `camera_button`, `camera_start`, `camera_stop`, `camera_unsupported`, `camera_error`
- Manual entry: `manual_entry_title`, `manual_entry_label`, `manual_entry_scan`
- Unknown UPC: `unknown_upc`, `unknown_upc_body`, `unknown_assign`
- Recent scans: `recent_heading`, `recent_empty`, `clear_recent`
- Actions: `undo`, `undone`
- Status: `error_network`, `error_lookup`, `duplicate`
- Context hints: `adding_to_inventory`, `removing_from_inventory`, `stock_label`

---

### Tests — `tests/Feature/ScanControllerTest.php` (14 tests, all passing)

Follows the exact pattern from `InventoryControllerTest`: `RefreshDatabase`, manual `Location`/`Bin`/`BalloonList` setup in `setUp()`, `BusinessContext::set()` + `BusinessContext::clear()` lifecycle.

**Coverage:**

| Test | What it verifies |
|---|---|
| `test_index_returns_ok_for_authenticated_owner` | GET /scan renders |
| `test_index_requires_authentication` | Auth gate |
| `test_lookup_finds_sku_by_upc` | Happy path — UPC → SKU |
| `test_lookup_returns_not_found_for_unknown_upc` | Unknown UPC returns `found: false` |
| `test_lookup_requires_upc` | Validation |
| `test_check_in_creates_stock_movement_and_updates_level` | Full check-in cycle |
| `test_check_in_with_open_bags` | Mixed full+open bags |
| `test_check_in_accumulates_on_existing_stock_level` | Upsert behavior |
| `test_check_in_rejects_zero_quantity` | Guard against zero-change scans |
| `test_check_out_decrements_stock_level` | Full check-out cycle |
| `test_undo_reverses_check_in` | Undo reverses stock level increment |
| `test_undo_reverses_check_out` | Undo reverses stock level decrement |
| `test_undo_rejects_movement_from_other_business` | Tenant isolation |
| `test_check_in_creates_default_bin_when_none_exists` | Auto-creates Default bin/location for fresh businesses (uses `withSession` to control business context when user has multiple memberships) |

---

## Design Decisions

### Auto-commit vs preview-then-confirm
**Chose auto-commit with undo.** Rationale: balloon decorators scan dozens of bags in rapid succession. A confirmation step would slow them down. The undo toast provides the safety net — if you accidentally scan the wrong item or wrong quantity, one tap reverses it.

### Compensating movement for undo (not deletion)
StockMovement is append-only per the data model (`no updated_at, no deleted_at`). Undo creates a new movement with reversed direction rather than deleting or mutating the original. This preserves the full audit trail — a manager can see exactly what was scanned and then reversed.

### Hybrid camera approach (BarcodeDetector + quagga2)
The native `BarcodeDetector` API is used where supported (Chrome Android, desktop Chrome) for its speed and zero dependency cost. `@ericblade/quagga2` is lazy-loaded via dynamic `import()` only on browsers that lack BarcodeDetector (iOS Safari). The lazy import means desktop users who never touch the camera button pay zero bytes for the library.

### Mode persistence across scans
The user sets Add/Remove, quantity, and open-bag toggle once, then scans many items. These controls persist across scans — swapping mode or adjusting quantity does not reset after a scan. This matches real warehouse workflows where you're checking in a whole shipment before switching to check-out for a job.

### Quantity presets (3/5/10)
Balloon bags typically come in standard counts. The presets let users jump to common quantities in one tap. The stepper handles edge cases (1 bag, 2 bags, etc.).

### No server-side scan page props
The scan page needs no initial server data (no filter dropdowns, no paginated list). It's entirely client-driven — all data flows through the JSON lookup and record endpoints. This keeps the initial page load fast.

---

## Things Not Included (Deferred)

- **Bin scan codes (`BIN-XXXXXXXX`)** — The `scan_code` column exists on `bins` and is auto-generated. Handling bin QR codes on the Scan page (to show bin contents) is deferred per DATA.md.
- **Pending UPC resolution** — Unknown UPCs currently redirect to the inventory catalog for manual SKU lookup. The full `pending_upc_scan` → resolve flow (Manager+ assigns or creates) is wired in the schema and model but the Scan page doesn't integrate it yet.
- **Job association during check-out** — The `job_id` parameter is accepted by the check-out endpoint but the Scan page has no UI for selecting a job. The field is ready for future UI.
- **Offline mode** — No offline support in v1. Network errors show a red state with retry prompt.
- **PWA / home screen install** — The app works in mobile browsers but has no service worker or manifest for "Add to Home Screen" behavior.

---

## Build Output

```
quagga.min-*.js    156 kB  (44 kB gzipped)   — lazy-loaded, cacheable
app-*.js           294 kB (104 kB gzipped)   — includes Scan page + components
```

---

## Test Results

```
Tests:    280 passed (1500 assertions)
Duration: 13.65s
```

- 266 tests → 280 tests (14 new scan tests)
- Pint formatting: passed
- Vite build: clean
