# Prompt: Build the Distributor Admin UI (Review Queue + Controls)

> **Status:** The backend foundation is already built, tested, and on `main`'s working tree
> (Opus, 2026-06-24). **Your job (Sonnet) is the Vue/UI layer + the remaining mechanical
> backend, then tests.** Do NOT rebuild the controllers/services/jobs/routes listed under
> "Already built" — wire your UI to them. This is the first iteration of what becomes a full
> internet balloon search engine + catalog-ingestion workflow, so favor clean, extensible
> components over one-off markup.

Start by reading these for context:
1. `CLAUDE.md` (project rules, Laravel Boost conventions — Pint, PHPUnit, Inertia/Vue, i18n)
2. `~/.claude/projects/-Users-todd-Documents-VS-Code-Projects-Balloonventory-App/memory/project_distributors_state.md` (full system state + the "Admin UI roadmap" section)
3. The pattern files you'll copy: `resources/js/Pages/SuperAdmin/SkuFeedback/Index.vue` (filtered table + status badges + slide-down + action buttons) and `resources/js/Components/AdminMenu.vue` (nav)
4. The existing distributor Vue pages you'll extend: `resources/js/Pages/SuperAdmin/Distributors/{Index,Show,Form}.vue`

---

## Already built — wire to these, do NOT rebuild

**Backend, with passing tests (`tests/Feature/SuperAdmin/DistributorProposalControllerTest.php`, 6 tests; full distributor suite 92 green):**

- **Routes** (`routes/web.php`, in the admin group; `proposals` registered before the `{distributor}` wildcard):
  - `admin.distributors.proposals.index` — `GET /admin/distributors/proposals`
  - `admin.distributors.proposals.approve` — `POST .../proposals/{proposal}/approve`
  - `admin.distributors.proposals.reject` — `POST .../proposals/{proposal}/reject`
  - `admin.distributors.proposals.update` — `PATCH .../proposals/{proposal}` (manual attribute mapping)
  - `admin.distributors.sync` — `POST /admin/distributors/{distributor}/sync` (body: optional `limit`)

- **`DistributorProposalController`** (`app/Http/Controllers/SuperAdmin/`) — `index` renders Inertia page
  `SuperAdmin/Distributors/Proposals` with these props (**this is the data contract for your Vue page**):
  - `proposals` — a **paginated** object (`.data`, `.current_page`, `.last_page`, `.prev_page_url`, `.next_page_url`). Each `data` row is already flattened + hydrated:
    `id, upc, normalized_sku, status, confidence, proposed_name, proposed_count, proposed_warehouse_sku, proposed_brand_id, proposed_balloon_size_id, proposed_color_id, brand_name, balloon_size_name, color_name, resulting_sku_id, resulting_sku_name, reviewed_at, distributor_count, evidence[]`.
    Each `evidence[]` member: `distributor_id, distributor_name, raw_sku, title, url, price, stock, in_stock, inherited_upc`.
  - `filters` — `{ status?, brand?, confidence? }` (echo back into your filter controls)
  - `references` — `{ brands:[{id,name}], balloonSizes:[{id,name,brand_id}], colors:[{id,name,brand_id}] }` (Edit-modal dropdowns; filter sizes/colors by selected brand client-side)
  - `pendingCount` — integer, for the heading badge
  - `approve`/`reject`/`update` redirect back with a flash (`success`/`warning`). Approve auto-creates a SKU when attributes resolve; otherwise flashes a warning telling the admin which attributes to map.

- **`DistributorProposalReviewService`** (`app/Services/Distributors/`) — owns the **cross-connection hydration** (proposals live on the `distributors` connection with no FKs; reference rows are batch-loaded from the primary connection and stitched in PHP — that's why there are no Eloquent relations here). Also `approve()`, `reject()`, `edit()`, `referenceOptions()`.
- **`ProposalPromotionResult`** (`app/Services/Distributors/`) — the promote-outcome value object (`created` / `already_promoted` / `needs_mapping` + `missingAttributes[]` / `upc_conflict`).
- **`DistributorCatalogPromoter`** — extended: `resolveAttributes()` now honors manually-set `proposed_brand_id/size/color` (the Edit→Approve flow actually works), and `promoteForReview()` returns the structured result.
- **`DistributorClusterEngine`** — `persistProposal()` now guards human-touched proposals: a re-cluster refreshes only `evidence`, never clobbering an admin's edits/decision (`isHumanTouched()`).
- **`RunDistributorSyncJob`** (`app/Jobs/`) — queued (DB driver); dispatches `catalog:ingest-distributor` (Shopify) or `catalog:crawl-distributor --limit=N` (BigCommerce) via `Artisan::call`. Dispatched by `DistributorController@sync`.
- **Flash strings** added to `lang/en/flash.php` under `distributors.sync_started` and `distributor_proposals.*`.

---

## Your tasks (Sonnet)

### 1. Proposal review queue page — `resources/js/Pages/SuperAdmin/Distributors/Proposals.vue`

Copy the structure of `SkuFeedback/Index.vue` (filtered table, debounced search, status badges, slide-down row, action buttons, pagination). Consume the `proposals`/`filters`/`references`/`pendingCount` props above.

- **Filters** (top bar, drive via `router.get(route('admin.distributors.proposals.index'), {...}, {preserveState:true, replace:true})`, debounce the brand text input like SkuFeedback debounces `search`): Status (All/Pending/Auto-approved/Approved/Rejected), Brand text filter, Confidence (All/High/Low).
- **Columns:** UPC (link to catalog search / `resulting_sku_name` link when `resulting_sku_id` is set), Proposed name, Brand (`brand_name` or "Unknown"), Count, Confidence badge, Status badge, Distributors (`distributor_count`), Evidence expander, Actions.
- **Evidence expander:** slide-down row (SkuFeedback pattern) showing each `evidence[]` member — `distributor_name`, `raw_sku`, `title`, external `url` link, `price`, stock/`in_stock`, and an "inherited UPC" tag when `inherited_upc`.
- **Actions** (show Approve/Reject/Edit for `pending` + `auto_approved`): Approve → `POST` approve route; Reject → `POST` reject route; Edit → opens the modal below. Use `router.post/patch` with `preserveScroll: true`.
- **Edit modal:** dropdowns from `references` for brand → (sizes/colors filtered to that brand) → color, plus number `proposed_count` and text `proposed_warehouse_sku`. Submit `PATCH` update route. After save the admin re-approves to attempt promotion.

### 2. Sync/Crawl controls + detail enrichment — `Distributors/Show.vue` (and/or `Form.vue`)

- Add a **"Sync now"** (Shopify) / **"Crawl more"** (BigCommerce) button that `POST`s `admin.distributors.sync` (label by `platform_type`). Flash already handled.
- Surface staging stats on the detail page. **Backend bit you must add** (mechanical): in `DistributorController@show`, add staged counts to the payload — `DistributorProduct::where('distributor_id', $distributor->id)->count()` (total) and `->whereNotNull('upc')->count()` (with barcodes) — plus the existing `last_synced_at`. Render them in the Details card. Note `DistributorProduct` is on the `distributors` connection (the model handles it).
- Optionally show a "Review Proposals" link/button from `Distributors/Index.vue` to the queue.

### 3. Config form fields — `Distributors/Form.vue` + `DistributorController` store/edit

Replace the raw `config` JSON textarea with structured fields that serialize to the `config` JSON column, keeping a collapsible **"Advanced: raw config"** for anything unstructured. Update `DistributorController::rules()`/`attributes()` to accept + merge them:
- **Product Matching:** `sku_strip_prefixes`, `sku_strip_suffixes` (comma-separated → arrays).
- **Fetch Settings:** `request_delay_ms` (500), `request_jitter_ms` (0), `max_retries` (3), `max_pages` (500).
- **API (Shopify only, toggle by `platform_type`):** `has_json_api` (checkbox, default true), `collection_handle` (default "all").

### 4. Navigation — `resources/js/Components/AdminMenu.vue`

Add a `proposals` entry under `distributors` (`route: 'admin.distributors.proposals.index'`, `match: 'admin.distributors.proposals.*'`) with a **pending-count badge**. The count needs to be available app-wide — share `DistributorCatalogProposal::pending()->count()` via the existing Inertia shared-props mechanism (check `app/Http/Middleware/HandleInertiaRequests.php` for where admin counts are shared; follow that pattern). Add the `super_admin.dashboard.nav.proposals` i18n key.

### 5. i18n

Add keys under `lang/en/super_admin.php` (`dashboard.distributors.proposals.*` for the page: heading, column labels, status/confidence badges, filter labels, action labels, evidence labels, empty state) and the nav key in #4. Follow existing `dashboard.feedback.*` conventions.

### 6. Tests

- **Extend** `tests/Feature/SuperAdmin/DistributorProposalControllerTest.php` only where you add behavior (e.g. the `show` staged-count payload, config-field serialization). Do not duplicate the 6 existing tests.
- Add a feature test for `DistributorController@sync` asserting `RunDistributorSyncJob` is dispatched (`Queue::fake()`), gated to super-admins.
- Add a `Form.vue` config-serialization test (store/update writes the structured fields into `config`).
- Run with `php artisan test --compact --filter=Distributor`. Then run Pint: `vendor/bin/pint --dirty --format agent`. Ask Todd before running the full suite.

### Gotchas (already handled in the foundation, keep in mind if you touch backend)
- Proposals/products are on the `distributors` connection — no Eloquent relations across it; batch-load + stitch (the service shows the pattern).
- Never call instance `save()`/`update()` on the composite-key pivots (`BusinessDistributor`, `DistributorSkuUrl`) — upsert only.
- SQLite (tests) hides MySQL-only breaks; keep queries portable (the service's status-ordering CASE is written to work on both).
- `npm run build` (or `composer run dev`) after Vue changes so Todd sees them.
