# User Dashboard — Build Instructions

Paste this into a fresh Claude Code session (Sonnet). It is a complete spec for
replacing the placeholder business-tenant dashboard with a useful operational hub.

---

## Context / intent
The business-tenant dashboard (`/dashboard`) is still the stock Breeze placeholder
("You're logged in!"). Replace it with a genuinely useful at-a-glance operational hub for
balloon businesses, answering the daily questions: *what do I need to buy, what's happening
with my stock, let me quickly scan, and what needs setting up.* It's a **launchpad** — each
card summarizes and links to its full page; it is not where features live.

Data is plentiful: Inventory (stock levels, bins), Scan (stock movements / audit trail),
Favorites (reorder thresholds), and Bins/Locations are all live. **Jobs and Reorder are still
stubs** (`JobsController`/`ReorderController` just render an empty page) — so the dashboard
reserves a slot for "Upcoming jobs" but does not build it yet.

## Decisions (locked — implement as-is)
- **One dashboard for owner / manager / staff.** Not role-specific between them.
- **Guests get a simpler page automatically** — not via a separate layout, but by **gating
  action buttons and management nudges behind the user's abilities** (`$user->can(...)`). A
  view-only guest naturally sees only the read-only cards. This is the core design choice.
- **On hand = sealed bags only.** Low-stock and the reorder comparison use `full_bags` and
  **ignore `open_bags` entirely.** (KPI "total bags" may still show full+open — see below.)
- **v1 cards:** Quick-action launchpad · Low-stock/reorder alert · Inventory KPIs · Recent
  activity · Setup nudges. Reserve a top slot for **Upcoming jobs** (deferred until Jobs ships).
- No vanity metrics, no dense charts, no pricing-dependent figures in v1.
- Mobile-first; everything links out to its full page; reuse the existing `AdminCard` visual
  language so both dashboards feel like one product.

## Roles & abilities (already in the codebase)
Membership roles: `owner`, `manager`, `staff`, `guest`. Abilities are checked with
`$user->can('scope.action', $business)` — relevant gates (defined in `AppServiceProvider`):
`inventory.check_in`, `inventory.check_out`, `inventory.manual_adjust`,
`inventory.override_count`, `inventory.view_counts`, `inventory.view_audit_log`,
`sku.create_private`, `sku.edit_override`, `list.view`, `favorites.edit`, `job.view`,
`business.edit_settings`. Confirm guest's actual abilities in the permission seeder.

---

## Backend

### Route + controller
- Replace the closure route with a real controller (closures can't be route-cached; the app
  already moved admin pages to controllers for this reason):
  - `Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');`
    (stays in the `auth, verified, ensure.business` group).
- New `App\Http\Controllers\DashboardController@index`. All stock/movement queries are already
  tenant-scoped by `BusinessScope`; the current business is `BusinessContext::currentId()`.

### Data the controller assembles
1. **KPIs** (aggregate queries on `StockLevel`, business-scoped):
   - `distinctSkus` = count of distinct `sku_id`.
   - `totalBags` = `sum(full_bags + open_bags)` (KPI display may include open bags).
   - `binCount` = count of the business's bins.
   - `lowStockCount` (see #2).
   - Include `is_sample` rows; the samples banner (#5) covers the "not real yet" case.
2. **Low-stock list** — the centerpiece:
   - Favorites list: `BalloonList::where('is_business_favorites', true)->value('id')`.
   - For its `ListItem`s with a non-null `planned_quantity`, compute on-hand = **sum of
     `full_bags` across bins for that SKU (sealed bags only — ignore `open_bags`)** and flag
     where on-hand `<=` `planned_quantity`.
   - Return the top ~5 most-depleted for the card **plus** the total count for the KPI.
     Resolve display name via the `business_sku_override` custom name when present (mirror
     `InventoryController@show`). One query with eager-loaded SKU + override; avoid N+1.
3. **Recent activity** — latest ~6 `StockMovement` rows (business-scoped), eager-loading `sku`
   (+ override) and `user`. Return direction, `full_bags_change`/`open_bags_change`, sku
   display name, user name, `created_at`.
4. **Nudge flags** (booleans; visibility gating in the frontend #5):
   - `hasSampleStock` = any `StockLevel`/movement with `is_sample = true`.
   - `emailVerified` (from the user).
   - `onboardingComplete` = `business.onboarding_completed_at` non-null.
   - `contactInfoIncomplete` = user (and, for managers/owners, business) contact fields blank.
     **This depends on the separate User/Business contact-info feature.** Ship it only after
     those columns exist (guard with `Schema::hasColumn`) or add this nudge in a follow-up.
5. **Abilities map** — pass a `can` object computed server-side via `Gate::allows(ability, $business)`
   for at least: `checkIn`, `checkOut`, `adjust` (`inventory.manual_adjust`), `addInventory`
   (`sku.create_private`/`sku.edit_override`), `manageBusiness` (`business.edit_settings`),
   `viewCounts` (`inventory.view_counts`). The Vue gates cards/buttons off this map (cleaner
   than wiring Ziggy `can` on the client).

Keep it lean — ~4 aggregate queries + 2 small list queries. No heavy joins.

---

## Frontend

- Rewrite `resources/js/Pages/Dashboard.vue` as a responsive card grid inside
  `AuthenticatedLayout`, with a personalized header ("Good morning, {name}").
- Small presentational components (reuse/extend `AdminCard` styling):
  - **`QuickActions`** — prominent buttons, each shown only if its ability is true:
    **Scan in** → `scan.index` (Add mode), **Scan out** → `scan.index` (Remove mode),
    **Add inventory** → `inventory.index`, **View reorder list** → `reorder.index`. For a guest
    with no mutate abilities, this card collapses to read-only links (or hides).
  - **`LowStockCard`** — top depleted items (name + on-hand vs threshold), "N items need
    restocking", links to `reorder.index` (or filtered inventory). Empty: "You're well stocked."
    Shown only if `can.viewCounts`.
  - **`KpiRow`** — the four KPI tiles, each linking to its filtered list. Gated by `can.viewCounts`.
  - **`RecentActivityCard`** — movement feed ("Sarah checked out 5 bags · 11″ White · 2h ago");
    relative timestamps; empty state for new shops. Gate by `can.viewCounts` (or
    `inventory.view_audit_log` if that's the better match — pick the right ability).
  - **`SetupNudges`** — conditional banners: clear-samples (only if `hasSampleStock` **and**
    `can.manageBusiness`), verify-email (anyone, if unverified), complete-contact-info (personal →
    anyone; business → `can.manageBusiness`), finish-onboarding (if `!onboardingComplete` and
    `can.manageBusiness`).
- **Reserved slot:** an "Upcoming jobs" card position at the top, rendered only behind a
  `can('job.view')` + "feature available" flag — leave a commented placeholder so it's a small
  drop-in once Jobs ships. Do **not** build job logic now.
- **Empty-state-first:** a brand-new account (no inventory) should see a "Get started — add your
  first inventory / scan your first bag" panel instead of a wall of zeros.

## Guest simplification (how it falls out)
A guest typically has only view abilities. With the gating above, their dashboard renders KPIs +
low-stock + recent activity (if they can view counts), no mutate quick-actions, and no management
nudges — the "simpler" page, with zero special-casing. Confirm guest abilities from the permission
seeder and ensure the read-only cards still render for them (or show a friendly "ask an owner for
access" note if they can't even view counts).

---

## i18n (en + es, kept in sync by hand)
New `lang/{en,es}/dashboard.php` (replace the three placeholder keys): greeting, card
headings/subheadings, KPI labels, low-stock strings (incl. a `:count items need restocking`
plural), recent-activity verbs (checked in / out / adjusted / transferred), quick-action labels,
each nudge's copy, and empty states.

## Tests (PHPUnit)
- `DashboardController` returns the expected props for an owner (KPIs, lowStock, recentActivity,
  nudge flags, `can` map).
- KPI math: seed stock and assert `distinctSkus` / `totalBags` / `binCount`.
- Low-stock detection: a SKU whose **sealed** `full_bags` ≤ its favorites `planned_quantity`
  appears; one above it doesn't; **open bags don't affect the result** (assert a SKU with low
  `full_bags` but high `open_bags` still flags as low).
- Recent activity: latest movements newest-first with resolved names; no N+1 (eager-load).
- **Ability gating:** a guest gets `can.checkIn === false` and mutate actions absent; an owner
  gets them true.
- Nudges: samples flag true only when sample stock exists; onboarding flag reflects
  `onboarding_completed_at`.
- Run the full suite (`php artisan test --compact`).

## Verification & ship
1. `vendor/bin/pint --dirty --format agent`; `npm run build`.
2. Manual on dev: log in as owner (full dashboard), then as a guest member of the same business
   (simplified, read-only) — confirm the difference is purely ability-driven. Check the
   empty-state on a fresh business, and that the samples/verify/contact nudges appear and clear
   correctly.
3. **Branch → PR → merge → deploy** via `bin/deploy.sh` (no migration needed unless the
   contact-info nudge ships together). `git add` only feature files — exclude the untracked
   `index.html`.
4. Update `project_status.md` + `MEMORY.md`.

## Sequencing note
The complete-contact-info nudge references the **User/Business contact fields** from the separate
contact-info plan. Cleanest order: ship the contact-info feature first, then this dashboard — or
ship the dashboard now and add that one nudge in a follow-up. Everything else here is buildable
from current data immediately.

## Conventions reminder for the session
Read `CLAUDE.md` + the project memory files first; use Boost `search-docs` before framework code;
follow Laravel 12 structure (config/middleware in `bootstrap/app.php`, casts via a `casts()`
method); reuse `Sku::scopeVisibleTo`, the `business_sku_override` name-resolution pattern,
`BalloonList`/`ListItem`, and `AdminCard`; keep en/es in sync; every change gets a test.
