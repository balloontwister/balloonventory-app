# DATA.md — Balloonventory

The data model and tenancy rules for the Balloonventory inventory system. This file is the source of truth for entities, relationships, and the multi-tenancy contract. It is intentionally stack-agnostic. Concrete database choice, ORM, and migration tooling are documented separately in `AGENTS.md` once chosen.

If anything in code conflicts with this file, this file wins. Update this file before changing the schema, not after.

---

## Status & placeholders

The stack is locked in. Concrete versions to update as the project upgrades:

- **Language & runtime**: PHP 8.3.30 on cPanel/LiteSpeed (PHP 8.4 available; deferred until there's a reason to upgrade)
- **Framework**: Laravel 12 with Breeze starter kit (Inertia + Vue 3 + Tailwind)
- **Database**: MariaDB 10.11.16 (LTS, supported through 2028) on CloudLinux/LVE
- **Query layer**: Eloquent ORM with Laravel migrations
- **Auth**: Laravel's built-in auth via Breeze. The `user` table in this document is the local Laravel `users` table. There is no external auth provider in v1.
- **Object storage** (for SKU images): local cPanel storage via Laravel's `local` filesystem driver, writing to `storage/app/public`. Cloudflare R2 is the planned migration path when growth warrants it.
- **Cache & queue driver**: Laravel's `database` driver for both cache and queue. Diagnostic on 2026-05-08 confirmed that while phpredis 5.3.7 and memcached 3.4.0 PHP extensions are installed, no Redis or Memcached daemons are running on the cPanel host. The database driver is well-suited to Balloonventory's expected scale (small-to-medium per-business SaaS workloads). Migration path: ask the host to provision a Redis daemon (or upgrade hosting tier) and swap two lines in `config/cache.php` and `config/queue.php`.
- **Tenancy enforcement**: app-layer query scoping via Eloquent global scopes. The `BelongsToBusiness` trait will be added to every tenant-scoped model and apply a global scope filtering on the current business context. MariaDB has no row-level security equivalent to Postgres RLS, so app-layer scoping is the only line of defense. This makes the rules in the Multi-tenancy contract section non-negotiable.
- **Edge layer**: Cloudflare for DNS, WAF, DDoS protection, static asset caching, and free SSL. LiteSpeed LSCache at the origin for dynamic page caching once we install `litespeedtech/lscache-laravel`.
- **Catalog curation model** (who can add to and edit shared catalog SKUs): for v1, admin-only, seeded from manufacturer catalogs. Crowdsourced contribution and moderation are deferred.
- **UPC conflict resolution** (when two businesses claim the same UPC for different SKUs): for v1, last-write-wins on `sku.upc` with audit trail via `stock_movement`. UPC is a direct column on `sku`, not a separate table. Moderation flow deferred.

---

## Multi-tenancy contract

This is the single most important section in the file. Read it twice.

Balloonventory is multi-tenant. A `business` is the tenant. Most data is scoped to exactly one business and must never be visible to or modifiable by users from other businesses, even if those users belong to multiple businesses themselves.

### The rule

**Every query, mutation, API endpoint, cache key, background job, and exported file must include `business_id` in its scope.** A query without a `business_id` filter on a tenant-scoped table is a bug. Treat it as a security incident in code review.

### Good vs bad

```
-- GOOD: business-scoped read
SELECT * FROM stock_level WHERE business_id = $1 AND sku_id = $2;

-- BAD: leaks across tenants
SELECT * FROM stock_level WHERE sku_id = $2;
```

```
-- GOOD: business-scoped mutation
UPDATE stock_level
SET quantity = quantity + $3
WHERE business_id = $1 AND sku_id = $2;

-- BAD: would mutate every business's stock for that SKU
UPDATE stock_level SET quantity = quantity + $3 WHERE sku_id = $2;
```

### Which tables are tenant-scoped

Tenant-scoped (every row belongs to exactly one business; every query MUST filter by `business_id`):

- `business_sku_override`
- `stock_level`
- `stock_movement`
- `job`
- `job_line_item` (reach `business_id` via `job`)
- `list`
- `list_item` (reach `business_id` via `list`)
- `local_price`
- `pending_upc_scan`
- `membership` (when querying a single business's membership list)

Note: `upc` columns on `sku` are direct attributes, not a separate table. UPC visibility is inherited from the SKU's visibility rule.

Mixed-scope (rows are visible based on context — see entity definition):

- `sku_error_report` (a row is visible to: the reporter, the owning business of a private SKU report, and SuperAdmin always)

Global / shared (rows are visible to all businesses; no `business_id` filter required):

- `user`
- `business` (a user can only see businesses they have a `membership` row for)
- `brand`
- `size`, `shape`, `texture`, `color_family`, `color`, `theme`, `material` (catalog reference data)
- `sku` where `owned_by_business_id IS NULL` (the shared catalog)

Hybrid (the row itself is global, but visibility may be filtered):

- `sku` where `owned_by_business_id IS NOT NULL` (private SKU; only the owning business sees it)

### Required helper

Every server-side request handler must, before any DB call, resolve the **current business context** from the session — typically via a `requireCurrentBusiness(req): { businessId, userId, role }` helper. All DB access goes through repository functions that take `businessId` as a required first argument. Repository functions for tenant-scoped tables must refuse to run if `businessId` is missing or null.

### Canonical query helper signatures

DB access goes through Eloquent models with global scopes, plus a small set of service classes for multi-write operations. Service methods always take `Business $business` (not `$businessId`) as the first argument — a fully resolved tenant, not just an ID — so any leakage from a stale or wrong context fails type checks before it fails at runtime.

```
StockService::listStockLevels(Business $b, ?Sku $sku = null, bool $lowStockOnly = false): Collection
StockService::recordCheckIn(Business $b, User $u, Sku $sku, float $qty, ?string $upc = null, ?Job $job = null, ?string $notes = null): StockMovement
StockService::recordCheckOut(Business $b, User $u, Sku $sku, float $qty, ?string $upc = null, ?Job $job = null, ?string $notes = null): StockMovement
StockService::queueUnknownUpc(Business $b, User $u, string $upc, StockDirection $direction, float $qty = 1.0): PendingUpcScan
JobService::listJobs(Business $b, ?JobStatus $status = null): Collection
SkuService::upsertOverride(Business $b, Sku $sku, array $overrides): BusinessSkuOverride
ListService::addSku(Business $b, BalloonList $list, Sku $sku, ?float $plannedQty = null): ListItem
FavoritesService::add(Business $b, Sku $sku): ListItem
FavoritesService::remove(Business $b, Sku $sku): void
PendingUpcScanService::resolveAssign(PendingUpcScan $pending, User $resolver, Sku $sku): StockMovement
PendingUpcScanService::resolveCreate(PendingUpcScan $pending, User $resolver, array $newSkuAttributes): StockMovement
PendingUpcScanService::reject(PendingUpcScan $pending, User $resolver, string $notes): void
SkuErrorReportService::file(User $reporter, Sku $sku, string $description, ?Business $context = null): SkuErrorReport
```

Notice `Business` is always the first positional argument. This is enforced by convention; new service methods must follow it. Eloquent global scopes provide the second line of defense — even a query that forgot to filter by business will get filtered automatically by the `BelongsToBusiness` trait.

Note: `BalloonList` rather than `List` because PHP reserves `list` as a language construct.

### Cross-business operations

There are no cross-business operations in v1. A user with memberships in business A and business B cannot transfer stock from A to B; they would have to Check Out from A and Check In to B as separate actions, manually. This is intentional. Cross-tenant writes are the most dangerous code path in any SaaS and are out of scope until there's a clear product need.

---

## Conventions

These apply to every table unless explicitly noted.

- **Storage engine**: every table is **InnoDB** with `utf8mb4_unicode_ci` collation. `config/database.php` pins `engine => InnoDB` on the mysql + mariadb connections. Foreign keys declared via `$table->foreign(...)` are enforced at the DB level. **Do not** declare a multi-column index that includes a `varchar(255)` plus two or more `char(36)` columns — utf8mb4 in InnoDB caps keys at 3072 bytes globally and at 1000 bytes on this host's older defaults. Use single-column indexes plus app-level checks instead. If you must compose, narrow the string column (e.g. `string('code', 50)`).
- **Primary keys**: UUIDv7 stored as `CHAR(36)` in MariaDB. Generated by Laravel via the `HasUuids` trait on each model with the `newUniqueId()` method overridden to return `Str::uuid7()` (Laravel 11+ provides this; if not available in the chosen Laravel version, use `ramsey/uuid` v4.7+ with `Uuid::uuid7()`). Column is named `id`. The `CHAR(36)` choice trades storage efficiency for readability during development and compatibility with Laravel's tooling. MariaDB 10.7+ has a native `UUID` data type (16-byte storage, 36-char display) that we could migrate to later; revisit if row counts pass 10M.
- **Soft deletes**: every entity uses Laravel's `SoftDeletes` trait, which adds a nullable `deleted_at` timestamp. Hard deletes are forbidden from application code. Background cleanup of long-tombstoned rows is a future concern.
- **Soft delete + unique constraints — app-level only**: the `unique(col_a, col_b, deleted_at)` trick does **not** enforce uniqueness among active rows. MariaDB treats NULL as distinct from other NULLs in unique indexes, so two *active* rows (`deleted_at = NULL` for both) with the same `col_a, col_b` are both accepted. The trick *only* allows tombstoned rows to coexist with active ones. Uniqueness on lookup tables is enforced in seeders (`firstOrCreate`) and in FormRequest validation (`Rule::unique(...)->where(fn($q) => $q->whereNull('deleted_at'))->ignore($id)`), not at the DB layer. The rework migration drops the DB-level `unique(name)` from `textures`, `color_families`, `shapes`, and the `(name, brand_id, deleted_at)` composite from `colors` for exactly this reason.
- **Timestamps**: every table has `created_at` and `updated_at` in UTC. Laravel sets these automatically when models use `$timestamps = true` (the default). Do not rely on MariaDB triggers.
- **Foreign keys**: name the column `<entity>_id` (e.g. `business_id`, `sku_id`). Always add an explicit index. Use `foreignUuid('business_id')->constrained()` in migrations to add both the column and the FK constraint.
- **Eloquent global scopes**: every tenant-scoped model uses a `BelongsToBusiness` trait that registers a global scope filtering on `business_id = currentBusinessId()`. Removing or bypassing this scope (`withoutGlobalScope`) requires a comment explaining why and a code review.
- **No JSON columns for queryable data**. MariaDB supports JSON (technically a virtual mapping over LONGTEXT with constraints) but it's awkward to index and query. JSON is acceptable only for opaque blobs (e.g. cached external API responses). If you'd ever want to query inside it, model it as columns.
- **No nullable text fields where empty string would do**, except where null carries semantic meaning ("no override set" vs "override is empty string").
- **Naming**: `snake_case` for all columns and table names, matching Laravel/Eloquent conventions. Pluralize table names (`skus`, `businesses`, `list_items`).

---

## Entities

Field lists below use `name (type, modifier)` notation. `pk` = primary key. `fk` = foreign key. `unique` = column-level uniqueness. `idx` = should be indexed.

### `user`

A person. The standard Laravel `users` table extended with our display field. Auth is Laravel's built-in (Breeze) — passwords, sessions, and tokens are handled by the framework, not modeled here.

- `id` (uuid, pk) — UUIDv7 generated by Laravel
- `name` (text) — Laravel default field; used for display
- `email` (text, unique, idx) — Laravel default field
- `email_verified_at` (timestamp, nullable) — Laravel default field
- `password` (text) — Laravel default field; hashed by the framework
- `remember_token` (text, nullable) — Laravel default field
- `admin_level` (enum: `site_admin`, `super_admin`, nullable, default NULL) — platform-level admin tier. `NULL` means regular user; `site_admin` means Site Admin; `super_admin` means Super Admin. Grants edit access to the shared catalog, the `pending_upc_scan` queue, and platform actions like business deletion. Does NOT grant access to any business's tenant-scoped data; any admin-level user still needs a `membership` row to act inside a specific business. See PERMISSIONS.md for the full tier description. Only a `super_admin` can promote a regular user or `site_admin` to `site_admin`.
- `locale` (string(8), default `'en'`) — BCP-47 short tag selecting the UI language for this user. `'en'` and `'es'` are the supported values; the column is wide enough for future region tags like `'pt-BR'`. Honored by `SetUserLocale` middleware on every request and by `TemplatedMailable::forKey()` when picking an email template variant.
- `timezone` (string(64), nullable) — IANA tz database identifier (e.g. `'America/Chicago'`). Captured from the browser via `Intl.DateTimeFormat().resolvedOptions().timeZone` on first login if NULL. Used for client-side date/time formatting; the server keeps storing UTC.
- `created_at`, `updated_at`, `deleted_at`

`locale` and `timezone` are user-level (not membership-level) because a person's language preference and physical location follow them across businesses. UI preferences that genuinely vary per business context still belong on `membership`.

### `business`

A tenant. The unit that owns inventory.

- `id` (uuid, pk)
- `name` (text)
- `slug` (text, unique, idx) — URL-safe identifier, e.g. `acme-balloons`
- `plan` (enum: `solo`, `store`, `enterprise`, default `solo`) — membership tier for this business. In v1 there are no functional differences between tiers; the field is present so future subscription logic has a home. All existing and new businesses default to `solo`. See PERMISSIONS.md for planned future tier distinctions.
- `created_at`, `updated_at`, `deleted_at`

No currency field, no billing fields, no money fields. Add when needed.

### `membership`

The user × business relationship with a role and per-membership UI preferences.

- `id` (uuid, pk)
- `user_id` (uuid, fk → user.id, idx)
- `business_id` (uuid, fk → business.id, idx)
- `role` (enum: `owner`, `manager`, `staff`, `viewer`)
- `business_badge_color` (text, hex like `#FF5722`) — the BusinessBadge color this user picked for this business; see DESIGN.md
- `joined_at` (timestamp)
- `created_at`, `updated_at`, `deleted_at`
- `unique(user_id, business_id, deleted_at)` (one active membership per user per business; deleted_at in the composite allows re-invitation after removal — see Conventions)

Roles are documented in `PERMISSIONS.md`. The role enum lives here, but the action × role matrix lives there.

### `brand`

A balloon manufacturer. Shared globally, not tenant-scoped.

- `id` (uuid, pk)
- `name` (text, unique) — e.g. `Qualatex`, `Sempertex`, `TufTex`, `Betallic`, `Kalisan`, `Decomex`, `Funsational`
- `abbreviation` (text, unique) — short tag shown in BrandTag, e.g. `QTX`, `STX`
- `description` (text, nullable) — free-text brand description
- `url_1` (text, nullable) — primary manufacturer URL
- `url_2` (text, nullable) — secondary manufacturer URL
- `logo_url` (text, nullable) — URL to brand logo image
- `primary_color_hex` (varchar 7, nullable) — primary brand color
- `secondary_color_hex` (varchar 7, nullable) — secondary brand color
- `is_active` (boolean, default true) — soft toggle for brand visibility
- `brand_color_hex` (text, nullable) — the dot color in BrandTag; optional
- `logo_path` (text, nullable) — path inside the `public` disk (resolves to `storage/app/public/<path>`). Use `Storage::disk('public')->url($logo_path)` to render. New uploads land in `brand-logos/`.
- `sort_order` (integer, default 0) — display order in dropdowns and tables
- `created_at`, `updated_at`, `deleted_at`

### `size`

A balloon size. Shared globally. All lookup tables follow this pattern: `id`, `name` (unique), `sort_order`, `description` (nullable), `deleted_at`, timestamps.

- `id` (uuid, pk)
- `name` (text, unique) — primary imperial label, e.g. `5-inch`, `11-inch`, `16-inch`, `260`, `646`
- `alt_imperial_name` (text, nullable) — secondary imperial label for the same physical balloon. Used where manufacturers ship one product under two names in different regions. Examples: `5-inch` has alt `6-inch`; `11-inch` has alt `12-inch`. Larger and modeling sizes leave this NULL.
- `diameter_cm` (unsigned smallint, nullable) — canonical metric diameter for round-latex sizes. Rendered alongside the imperial label as a hybrid display (e.g. `5" / 6" (13 cm)`). Modeling sizes (260, 350, etc.) leave this NULL because the name itself is the spec.
- `size_category` (enum: `small`, `medium`, `large`, `giant`, `small_modeling`, `large_modeling`) — groups similar sizes for browsing and filtering
- `sort_order` (integer, default 0)
- `description` (text, nullable)
- `single_image_file_path` (text, nullable) — single balloon image for this size
- `cluster_image_file_path` (text, nullable) — cluster/bag image for this size
- `created_at`, `updated_at`, `deleted_at`

**Display rule:** the UI renders sizes as `"{name} / {alt_imperial_name} ({diameter_cm} cm)"` when all three are set, `"{name} ({diameter_cm} cm)"` when only diameter is set, and just `"{name}"` otherwise. The hybrid label is intentional — balloon supply is a cross-region market and the same SKU is referenced by either notation depending on supplier paperwork.

**Why 11" and 12" are one row:** Industry quirk. Both ship as the same physical 30 cm balloon under different historical naming. Sempertex's `R-12` product code is the same product, sold as 11" in the US and 12" in Europe. The 5"/6" pair has the same story. Sort order falls out naturally from `diameter_cm` for round sizes; modeling sizes follow their own numeric series.

### `shape`

A balloon shape. Shared globally. Values: Round, Link, Non-round, Heart, Circle, Star, Shaped, SuperShape, Other.

- `id` (uuid, pk)
- `name` (text, unique)
- `material_id` (uuid, nullable, fk → material.id, idx) — shapes are material-specific (e.g. a latex "Round" is different from a foil "Round")
- `image_path` (text, nullable) — representative shape image
- (plus standard lookup columns: `sort_order`, `description`, timestamps, soft deletes)

### `texture`

A balloon surface finish. Shared globally. Unique too each brand and material. Similar texture across brands are in the same texture-family.

- `id` (uuid, pk)
- `name` (text, unique) — seeded values: `Crystal`, `Standard`, `Matte`, `Glow-in-the-dark`, `Metallic`, `Pearl`, `Neon`, `Chrome`, `Satin`
- `material_id` (uuid, nullable, fk → material.id, idx) — textures are material-specific (e.g. chrome latex vs chrome foil)
- `brand_id` (uuid, nullable, fk → brand.id, idx) — some textures are brand-specific
- `texture_family_id` (uuid, nullable, fk → texture_family.id, idx) — groups textures for filtering. Current families: `Crystal`, `Standard`, `Metallic`, `Neon`, `Chrome` (the `Chrome` family also covers `Satin` for now)
- `image_path` (text, nullable) — representative texture image
- (plus standard lookup columns: `sort_order`, `description`, timestamps, soft deletes)

### `color_family`

A cross-brand color grouping. Examples: `Blacks`, `Reds`, `Pinks`, `Blues`, `Golds`.

- `id` (uuid, pk)
- `name` (text, unique)
- `material_id` (uuid, nullable, fk → material.id, idx) — color families are material-specific
- `color_hex` (text, nullable) — canonical representative hex for the family swatch
- `hex_color_start` (varchar 7, nullable) — gradient swatch start hex
- `hex_color_end` (varchar 7, nullable) — gradient swatch end hex
- `single_image_file_path` (text, nullable) — single balloon image for this color family
- `cluster_image_file_path` (text, nullable) — cluster/bag image for this color family
- (plus standard lookup columns: `sort_order`, `description`, timestamps, soft deletes)

### `color`

A brand-specific color name. Each brand names their colors independently; `color_family` links them cross-brand.

- `id` (uuid, pk)
- `name` (text) — e.g. `Deluxe Black` (STX), `Onyx Black` (QTX), `Black` (TTX)
- `color_family_id` (uuid, fk → color_family.id, idx)
- `brand_id` (uuid, nullable, fk → brand.id, idx) — null for generic/unbranded colors
- `material_id` (uuid, nullable, fk → material.id, idx) — colors are material-specific
- `color_hex` (text, nullable) — specific hex for this color variant; used by BalloonSwatch
- `color_code` (text, nullable) — internal color code from the manufacturer
- `pms_value` (text, nullable) — Pantone Matching System value
- `texture_id` (uuid, nullable, fk → texture.id, idx) — some colors have a default texture association
- `single_image_file_path` (text, nullable) — single balloon image for this color
- `cluster_image_file_path` (text, nullable) — cluster/bag image for this color
- `sort_order`, `description`
- `unique(name, brand_id, deleted_at)` — a brand cannot have two active colors with the same name

### `theme`

A printed-balloon theme tag. Seeded values: `Holiday`, `Christmas`, `Halloween`, `Stars`, `Animal`, `Star Wars`, `Princess`, `Cartoon`, `Jungle`. Same structure as `shape`. Applied to SKUs via the `sku_themes` many-to-many pivot.

The Themes panel on the SKU form is hidden unless `sku.is_printed = true` (UX nudge — themes are mostly meaningful for printed balloons). The server-side does not enforce this rule; if a non-printed SKU has themes attached, they're stored but not surfaced.

### `material`

The balloon substrate. Values: Latex, Foil, Plastic, Chloroprene, Stretchy.

- `id` (uuid, pk)
- `name` (text, unique)
- `url` (text, nullable) — external URL for this material
- `image_path` (text, nullable) — representative material image
- (plus standard lookup columns: `sort_order`, `description`, timestamps, soft deletes)

### `sku_themes` (pivot)

Many-to-many between `sku` and `theme`. A SKU can belong to multiple themes (e.g. a Halloween Star Wars balloon).

- `sku_id` (uuid, fk → sku.id, cascade delete)
- `theme_id` (uuid, fk → theme.id, cascade delete)
- `primary(sku_id, theme_id)`

No timestamps, no soft delete — themes are tags; removing a theme from a SKU hard-deletes the pivot row.

### `sku`

A balloon SKU. The hybrid catalog lives here. A row is either **shared** (visible to all businesses) or **private** (visible only to one).

- `id` (uuid, pk)
- `name` (text) — canonical product name, e.g. `Wild Berry`
- `description` (text, nullable) — free-text product description
- `brand_id` (uuid, fk → brand.id, idx)
- `material_id` (uuid, nullable, fk → material.id, idx)
- `balloon_size_id` (uuid, nullable, fk → balloon_size.id, idx) — brand+material-specific balloon size (e.g. Sempertex R-12), replaces the direct `size_id` FK
- `shape_id` (uuid, nullable, fk → shape.id, idx)
- `texture_id` (uuid, nullable, fk → texture.id, idx)
- `color_id` (uuid, nullable, fk → color.id, idx)
- `is_printed` (boolean, default false) — true for printed/themed balloons; glow-in-the-dark and satin are textures, not a separate flag
- `default_count_per_bag` (integer, nullable) — typical bag size, e.g. 100 for 11" latex
- `warehouse_sku` (text, nullable, idx) — official product number from the brand, e.g. `43734`. Renamed from `manufacturer_sku`. Enforced unique per `(brand_id, warehouse_sku, deleted_at)` at the validation layer when non-null.
- `upc` (text, nullable, idx) — UPC barcode string (12-digit GTIN-12). Stored directly on the SKU; no separate `upcs` table.
- `ean` (text, nullable) — European Article Number (13-digit GTIN-13)
- `asin` (text, nullable) — Amazon Standard Identification Number
- `mfg_no` (text, nullable) — manufacturer's part/item number distinct from the warehouse SKU
- `packaging_id` (uuid, nullable, fk → packaging_type.id, idx) — how the bag is packaged (Individual, Loose, Nozzle Up, Retail)
- `single_image_file_path` (text, nullable) — single balloon image for this SKU
- `cluster_image_file_path` (text, nullable) — cluster/bag image for this SKU
- `computed_name` (text, nullable, idx) — auto-generated display name from cascading attributes. Format: `"{balloon_size.name} {color.name} {brand.abbreviation} {shape.name} {count}ct"` (e.g. "12-inch Fashion Red STX Round 100ct"). Regenerated via Eloquent `saving` observer when brand, color, shape, count, or balloon_size change.
- `price_code_id` (uuid, nullable, fk → price_code.id, idx) — FK to the `price_codes` table; replaces the free-text `price_code` string column
- `gs1_prefix` (text, nullable) — GS1 company prefix (derivable from UPC but stored for lookup perf)
- `is_active` (boolean, default true) — soft toggle for SKU visibility in catalogs
- `discontinued_at` (timestamp, nullable) — when the manufacturer discontinued this SKU
- `product_version` (text, nullable) — version/revision of the product
- `owned_by_business_id` (uuid, nullable, fk → business.id) — `NULL` means shared catalog. Set means private to that business.
- `created_at`, `updated_at`, `deleted_at`

All attribute FK columns are nullable. Conditional validation (e.g. foil SKUs have no `balloon_size_id`) is deferred to a later phase.

Themes are many-to-many via the `sku_themes` pivot (a SKU can belong to multiple themes). Print colors and print sides are also many-to-many (via `sku_print_colors` and `sku_print_sides` pivots) and are only surfaced when `is_printed = true`. Identical SKUs (same physical balloon, different bag variant) are modeled as a self-referential M2M via the `identical_skus` pivot.

**Image fallback chain (runtime):** display logic cascades from SKU image → SKU color image → hex-generated placeholder. This is an accessor / Vue computed, not a denormalized copy.

Visibility rule for any user in business X:

```
WHERE sku.deleted_at IS NULL
  AND (sku.owned_by_business_id IS NULL OR sku.owned_by_business_id = X)
```

### `texture_family`

A grouping of related textures for filtering. Shared globally.

- `id` (uuid, pk)
- `name` (text, unique) — seeded values: `Crystal`, `Standard`, `Metallic`, `Neon`, `Chrome`
- `description` (text, nullable)
- `image_path` (text, nullable)
- (plus standard lookup columns: `sort_order`, timestamps, soft deletes)

### `packaging_type`

How a bag of balloons is physically packaged. Shared globally.

- `id` (uuid, pk)
- `name` (text, unique) — seeded values: `Individual`, `Loose`, `Nozzle Up`, `Retail`
- (plus standard lookup columns: `sort_order`, timestamps, soft deletes)

### `price_code`

A per-brand pricing category. Each brand defines its own set of price codes; SKUs reference them via `price_code_id`.

- `id` (uuid, pk)
- `brand_id` (uuid, fk → brand.id, idx)
- `code` (text) — pricing code string (e.g. `std`, `premium`)
- `unique(brand_id, code, deleted_at)`
- (plus standard lookup columns: `sort_order`, timestamps, soft deletes)

This table replaces the free-text `price_code` column that was previously on `skus`. The dictionary governance prevents typos and allows per-brand pricing tiers.

### `balloon_size`

A brand+material-specific balloon size definition. This bridges the abstract `size` family (e.g. "11-inch") with how a specific brand+material combination names it (e.g. Sempertex latex calls it "R-12", TufTex latex calls it "11-inch"). Shared globally.

- `id` (uuid, pk)
- `brand_id` (uuid, fk → brand.id, idx)
- `material_id` (uuid, fk → material.id, idx)
- `size_id` (uuid, fk → size.id, idx) — the size family this belongs to (e.g. the "11-inch" size row)
- `name` (text) — brand's name for this size (e.g. `R-12`, `C-12`, `11-inch`)
- `description` (text, nullable)
- `single_image_path` (text, nullable)
- `cluster_image_path` (text, nullable)
- `unique(brand_id, material_id, name, deleted_at)`
- (plus standard lookup columns: `sort_order`, timestamps, soft deletes)

The `size_id` FK groups balloon sizes into families for cross-brand filtering (e.g. "show me all 11-inch balloons from all brands"). The `balloon_size_id` FK on `sku` pins a SKU to a specific brand+material size.

### `print_color`

An ink color used for printed balloons. M2M with SKUs.

- `id` (uuid, pk)
- `name` (text, unique) — e.g. `Black`, `White`, `Red`, `Gold`, `Silver`
- (plus standard lookup columns: `sort_order`, timestamps, soft deletes)

### `print_side`

Which side(s) of a balloon are printed. M2M with SKUs.

- `id` (uuid, pk)
- `name` (text, unique) — e.g. `Top`, `Side`, `Two-Sides`, `Four-Sides`, `Five-Sides`
- (plus standard lookup columns: `sort_order`, timestamps, soft deletes)

### `brand_gs1_prefix`

A GS1 manufacturer prefix associated with a brand. Used to validate UPC/EAN identifiers.

- `id` (uuid, pk)
- `brand_id` (uuid, fk → brand.id, idx)
- `prefix` (text) — GS1 company prefix digits
- `unique(brand_id, prefix)`
- `created_at`, `updated_at` (no soft deletes — prefix assignments are durable)

### `sku_print_colors` (pivot)

Many-to-many between `sku` and `print_color`. Only meaningful when `sku.is_printed = true`.

- `sku_id` (uuid, fk → sku.id, cascade delete)
- `print_color_id` (uuid, fk → print_color.id, cascade delete)
- `primary(sku_id, print_color_id)`

No timestamps, no soft delete — print colors are attributes; removing one hard-deletes the pivot row.

### `sku_print_sides` (pivot)

Many-to-many between `sku` and `print_side`. Only meaningful when `sku.is_printed = true`.

- `sku_id` (uuid, fk → sku.id, cascade delete)
- `print_side_id` (uuid, fk → print_side.id, cascade delete)
- `primary(sku_id, print_side_id)`

No timestamps, no soft delete.

### `identical_skus` (pivot)

Self-referential many-to-many linking different SKU rows that represent the same physical balloon in different bag variants. For example, a 25ct bag and a 100ct bag of the same balloon.

- `sku_id` (uuid, fk → sku.id, cascade delete)
- `identical_sku_id` (uuid, fk → sku.id, cascade delete, idx)
- `primary(sku_id, identical_sku_id)`

Both directions are stored. The application layer prevents self-referencing rows (`sku_id = identical_sku_id`). When a SKU is deleted, all its identical_skus pivot rows are cascade-deleted.

### `business_sku_override`

A business's per-SKU customization layer over a shared catalog SKU. Only meaningful for shared SKUs (`sku.owned_by_business_id IS NULL`). For private SKUs, edits go directly on the `sku` row since the business owns it.

- `id` (uuid, pk)
- `business_id` (uuid, fk → business.id, idx)
- `sku_id` (uuid, fk → sku.id, idx)
- `custom_name` (text, nullable) — `NULL` means use catalog name
- `custom_color_hex` (text, nullable) — `NULL` means use catalog hex
- `reorder_threshold` (decimal, nullable) — `NULL` means no low-stock alert
- `notes` (text, nullable) — free-text notes for this business's users
- `is_hidden` (boolean, default false) — business doesn't use this SKU; hide from search
- `created_at`, `updated_at`, `deleted_at`
- `unique(business_id, sku_id, deleted_at)`

### `stock_level`

Aggregate stock count per business per SKU. One row per (business, SKU) pair. No per-bag instances.

- `id` (uuid, pk)
- `business_id` (uuid, fk → business.id, idx)
- `sku_id` (uuid, fk → sku.id, idx)
- `quantity` (decimal, default 0) — number of bags. Decimal supports partial bags (e.g. 12.4).
- `last_movement_at` (timestamp, nullable) — denormalized from latest stock_movement for fast sort
- `created_at`, `updated_at` (no `deleted_at` — quantity 0 is the empty state)
- `unique(business_id, sku_id)`

### `stock_movement`

The audit log. Every Check In and Check Out writes one row. Immutable: no updates, no deletes from application code. Tombstoning a Job or SKU does not delete its movement history.

- `id` (uuid, pk)
- `business_id` (uuid, fk → business.id, idx)
- `sku_id` (uuid, fk → sku.id, idx)
- `user_id` (uuid, fk → user.id, idx) — who performed the scan
- `direction` (enum: `in`, `out`)
- `quantity_change` (decimal, positive) — always positive; direction tells you sign
- `upc_scanned` (text, nullable) — the raw UPC if entered via scanner; null if manual count adjustment
- `job_id` (uuid, nullable, fk → job.id, idx) — set when this Check Out is associated with a planned job; informational only, no reconciliation
- `notes` (text, nullable)
- `created_at` (no `updated_at`, no `deleted_at` — append-only)
- `idx(business_id, sku_id, created_at)` for fast per-SKU history reads

### `job`

A planned work assignment with a date and a list of SKUs needed. Used for proposals and Check Out preparation. Not reconciled.

- `id` (uuid, pk)
- `business_id` (uuid, fk → business.id, idx)
- `name` (text) — e.g. `Smith Wedding`
- `client_name` (text, nullable)
- `event_date` (date, nullable)
- `status` (enum: `draft`, `planned`, `in_progress`, `archived`) — purely organizational; nothing system-driven flips a job to `in_progress`
- `notes` (text, nullable)
- `created_by_user_id` (uuid, fk → user.id)
- `created_at`, `updated_at`, `deleted_at`

No fields for cost, price, deposit, invoice, payment, or any monetary concept. If a future feature needs them, that's a separate design conversation; do not add them ad-hoc.

### `job_line_item`

A single planned SKU on a job.

- `id` (uuid, pk)
- `job_id` (uuid, fk → job.id, idx)
- `sku_id` (uuid, fk → sku.id, idx)
- `planned_quantity` (decimal) — bags planned for this job
- `notes` (text, nullable)
- `created_at`, `updated_at`, `deleted_at`
- `unique(job_id, sku_id, deleted_at)` (one active line per SKU per job; merge instead of duplicating)

`business_id` is not on this table because it's reachable via `job.business_id`. Tenant-scoped queries must JOIN `job` and filter on `job.business_id`. If join cost becomes a problem, denormalize `business_id` onto this table later.

### `list`

A user-named, themed collection of SKUs. Per business. Reusable, no date or client (those distinguish Lists from Jobs).

A single canonical "Favorites" list (one row per business with `is_business_favorites = true`) holds the SKUs the business always stocks. It's seeded automatically at business creation, cannot be renamed or deleted, and is permission-gated differently from custom Lists in PERMISSIONS.md. All other rows in this table are user-named custom Lists.

- `id` (uuid, pk)
- `business_id` (uuid, fk → business.id, idx)
- `name` (text) — for the seeded Favorites row this is `"Favorites"`; for custom Lists, user-chosen
- `is_business_favorites` (boolean, default false, idx) — true for exactly one row per business
- `notes` (text, nullable)
- `created_by_user_id` (uuid, fk → user.id) — for the seeded Favorites row, this is the user who created the business
- `created_at`, `updated_at`, `deleted_at`
- `unique(business_id, is_business_favorites)` where `is_business_favorites = true` — enforces one Favorites row per business. In MariaDB this is implemented as a regular unique constraint on `(business_id, is_business_favorites)` since `false` is a duplicable value, or as application-layer enforcement in the `Business` model's `creating` observer. Application enforcement is simpler.

When a new business is created, the application observer creates the Favorites list automatically. Application code must refuse to soft-delete or rename a list where `is_business_favorites = true`. This rule lives in the `BalloonList` model's `deleting` and `saving` observers.

Lists are shared across all members of a business in v1. There is no per-user list visibility, no private lists, no shared-with-specific-users. If a member creates a list, every member of that business sees it. PERMISSIONS.md governs who can edit and delete.

No date field, no client field, no status enum on this table. If those become product needs, the entity in question is probably a Job, not a List.

### `list_item`

A single SKU on a list with optional planned quantity and a manual sort order.

- `id` (uuid, pk)
- `list_id` (uuid, fk → list.id, idx)
- `sku_id` (uuid, fk → sku.id, idx)
- `planned_quantity` (decimal, nullable) — Amazon-wishlist style; null means "want this on the list, no specific count"
- `sort_order` (integer, default 0) — for manual drag-reorder; ties broken by `created_at` ascending
- `notes` (text, nullable)
- `created_at`, `updated_at`, `deleted_at`
- `unique(list_id, sku_id, deleted_at)` — same SKU can't appear twice in the same active list; merge with `ON DUPLICATE KEY UPDATE` (see Conventions for soft-delete unique handling)

`business_id` is not on this table; reach it via `list.business_id`. Tenant-scoped queries must JOIN `list` and filter on `list.business_id`.

### `local_price`

A per-business dollar value for a price code. Reference data only. Never used in any calculation in v1.

- `id` (uuid, pk)
- `business_id` (uuid, fk → business.id, idx)
- `price_code` (text) — should match a `price_codes.code` value to be useful, but no FK enforcement. Orphan rows are allowed and harmless.
- `amount_cents` (integer) — stored as cents to avoid floating point. USD assumed in v1; per-business currency override is a future setting.
- `created_at`, `updated_at`, `deleted_at`
- `unique(business_id, price_code, deleted_at)`

This table powers the LocalPricesTable in Settings → Pricing. No other UI surface reads it in v1. If a future feature wants to compute estimates, totals, or pricing from these values, that's a product decision that requires updates to PERMISSIONS.md, DESIGN.md, and the no-money rule before any code changes.

### `pending_upc_scan`

A queue row for a UPC scan that didn't resolve to any SKU visible to the scanning business. Created automatically when an Artist (or any scanner) hits an unknown barcode. Held in `pending` status until a Manager+ resolves it. See PERMISSIONS.md for the workflow.

Critically, **no `stock_movement` is recorded when a pending row is created**. The count is not changed until the pending row resolves. This avoids posting bad data based on a guessed mapping.

- `id` (uuid, pk)
- `business_id` (uuid, fk → business.id, idx)
- `upc_string` (text, idx) — raw scanned digits, no separators
- `direction` (enum: `in`, `out`) — which workflow the user was in when they scanned
- `quantity_scanned` (decimal, default 1) — usually 1; partial-bag override applies if the user adjusted before the resolution
- `scanned_by_user_id` (uuid, fk → user.id, idx) — the Artist or whoever scanned
- `scanned_at` (timestamp) — captured at scan time, not at resolution
- `status` (enum: `pending`, `resolved_assigned`, `resolved_created`, `rejected`)
- `resolved_by_user_id` (uuid, fk → user.id, nullable) — Manager, Owner, or SuperAdmin who acted
- `resolved_to_sku_id` (uuid, fk → sku.id, nullable) — the SKU the UPC was assigned to (for `resolved_assigned` and `resolved_created`)
- `resolved_at` (timestamp, nullable)
- `resolution_notes` (text, nullable)
- `created_at`, `updated_at`, `deleted_at`

When a pending row resolves to `resolved_assigned` or `resolved_created`, the resolution flow creates a `stock_movement` row using the original `scanned_at` as `created_at` and the original `scanned_by_user_id` as `user_id`, plus a marker (in `notes`) indicating the delayed apply and the `resolved_by_user_id`. This preserves the audit trail: the Artist who scanned and the Manager who resolved are both visible in the audit log.

### `sku_error_report`

A user-submitted report of a problem with a SKU (wrong color hex, wrong size, wrong manufacturer SKU, anything). Any user can file one for any SKU they can see. Routing depends on whether the SKU is shared or private; see PERMISSIONS.md for the notification flow.

- `id` (uuid, pk)
- `sku_id` (uuid, fk → sku.id, idx)
- `reported_by_user_id` (uuid, fk → user.id, idx)
- `reported_from_business_id` (uuid, fk → business.id, nullable, idx) — the business context where the user spotted the issue. Nullable for SuperAdmin reports made outside any business context.
- `description` (text) — free-text explanation of the problem
- `status` (enum: `open`, `acknowledged`, `fixed`, `rejected`)
- `resolved_by_user_id` (uuid, fk → user.id, nullable)
- `resolved_at` (timestamp, nullable)
- `resolution_notes` (text, nullable)
- `created_at`, `updated_at`, `deleted_at`

This table is global-ish — a report against a shared SKU has no single business owner — but reads should still be tenant-scoped where possible. A business should only see reports it filed or that pertain to its private SKUs. SuperAdmin sees all reports.

### `email_template`

A database-stored email template editable via the super-admin UI. One row per named template key. See EMAIL.md for the full design, variable system, and authoring guidelines.

- `id` (uuid, pk)
- `key` (text, unique, idx) — machine name, e.g. `welcome`, `subscription_upgrade`. Set at seed time; never changes.
- `label` (text) — human-readable name shown in the super-admin UI, e.g. `Welcome to Balloonventory`
- `trigger_description` (text) — plain-English description of when the email fires, shown in the UI
- `subject` (text) — email subject line; supports `{{variable}}` tokens
- `body_html` (longtext) — HTML body fragment rendered inside the Blade chrome layout; supports `{{variable}}` tokens
- `body_text` (text) — plain-text fallback; supports `{{variable}}` tokens
- `is_active` (boolean, default false) — when `false` the trigger is a no-op; allows drafting before activating
- `last_edited_by_user_id` (uuid, nullable, fk → user.id) — audit trail for super-admin edits
- `created_at`, `updated_at` (no `deleted_at` — deactivate via `is_active = false`, never delete)

Not tenant-scoped — templates are global platform configuration, not per-business data.

Seeded at install time with one row per template key, all with `is_active = false` and empty body fields. The super-admin UI shows an "empty / not yet written" state when `body_html` is blank.

### `email_log`

A write-once observability log of every outbound email the application sends. Populated by the `App\Listeners\LogSentEmail` listener on Laravel's `MessageSent` event. Read by the Super Admin dashboard for the "Emails by day / month" panels.

- `id` (bigint auto-increment, pk) — intentionally not UUID because this table is high-volume, append-only, and never referenced from elsewhere
- `to` (string) — recipient email address (from the Symfony `Address`, not the array key)
- `subject` (string) — Symfony message subject
- `mailable` (string) — `class_basename()` of the `Mailable` class, or `unknown` for `Mail::raw()` / framework notifications that don't set `__laravel_mailable`
- `user_id` (uuid, nullable, fk → user.id, null on delete) — best-effort lookup of the recipient by email at send time
- `sent_at` (timestamp, default current, idx) — when the send completed

No `created_at` / `updated_at`. Not tenant-scoped — platform-level observability data.

### `support_ticket`

A user-submitted support request from the in-app contact form (`Get help` button). One row per submission; replies live in `support_ticket_reply`. See EMAIL.md "Support ticket system" for the full flow.

- `id` (uuid, pk)
- `user_id` (uuid, nullable, fk → user.id, null on delete) — who submitted; null after the user is hard-deleted
- `user_name` (string) — snapshotted at submission time so the ticket survives the user's account being deleted
- `user_email` (string) — snapshotted at submission time
- `subject` (string, max 150)
- `body` (text, max 5000 at validation)
- `archived_at` (timestamp, nullable) — null = open ticket awaiting reply; non-null = replied-to or dismissed
- `created_at`, `updated_at`

The ticket row is created *before* the notification email is attempted in `SupportController`, so a Resend outage does not lose the user's submission. Submission is throttled to 3 per 60 minutes per user.

Not tenant-scoped — support is platform-level. SuperAdmin sees all tickets.

### `support_ticket_reply`

The admin's outbound reply to a `support_ticket`. One row per reply; the corresponding email is sent at the same moment via `SupportReplyMail`.

- `id` (uuid, pk)
- `support_ticket_id` (uuid, fk → support_ticket.id, cascade on delete)
- `body` (text, max 10000 at validation)
- `created_at`, `updated_at`

Creating a reply auto-archives the parent ticket (`archived_at = now()`).

---

## Relationships at a glance

```
user ──< membership >── business
                          │
                          ├──< stock_level >── sku ──> brand ──< brand_gs1_prefix
                          │                    │   ──> balloon_size ──> size (→ size_category)
                          │                    │   │               ──> brand
                          │                    │   │               ──> material
                          │                    │   ──> shape (→ material)
                          │                    │   ──> texture (→ texture_family, material, brand)
                          │                    │   ──> color (→ color_family, material, texture)
                          │                    │   ──> material
                          │                    │   ──> packaging_type
                          │                    │   ──> price_code (→ brand)
                          │                    │   ──< sku_themes >── theme
                          │                    │   ──< sku_print_colors >── print_color
                          │                    │   ──< sku_print_sides >── print_side
                          │                    │   ──< identical_skus >── sku (self)
                          │                    │
                          │                    └── upc (direct string column on sku)
                          │
                          ├──< stock_movement >── sku
                          │            │
                          │            └── (optional) job
                          │
                          ├──< business_sku_override >── sku
                          │
                          ├──< list (incl. is_business_favorites=true seed) >── list_item ── sku
                          │
                          ├──< local_price (loose match to price_code by string)
                          │
                          ├──< pending_upc_scan ── (resolves to) ── sku
                          │
                          └──< job >── job_line_item ── sku

sku_error_report ── reporter:user, scope:business?, sku  (mixed-scope, see entity)
```

`sku` is in the middle because every other inventory concept references it. The shared-vs-private distinction lives in `sku.owned_by_business_id` and is honored everywhere through the visibility rule. `local_price` loosely matches `price_code.code` by string; orphan rows in `local_price` are allowed.

---

## Common query patterns

These are the canonical patterns. New code should match the shape of these examples. If you find yourself writing a query that doesn't fit these shapes for a tenant-scoped table, stop and ask why.

### List all visible SKUs for a business (with overrides applied)

```
SELECT
  sku.id,
  COALESCE(override.custom_name, sku.computed_name, sku.name) AS display_name,
  COALESCE(override.custom_color_hex, sku.color_hex) AS display_color_hex,
  sku.brand_id,
  sku.balloon_size_id,
  sku.warehouse_sku,
  sku.upc,
  override.reorder_threshold,
  override.is_hidden
FROM sku
LEFT JOIN business_sku_override AS override
  ON override.sku_id = sku.id AND override.business_id = $1 AND override.deleted_at IS NULL
WHERE sku.deleted_at IS NULL
  AND (sku.owned_by_business_id IS NULL OR sku.owned_by_business_id = $1)
  AND (override.is_hidden IS NULL OR override.is_hidden = false);
```

### Resolve a scanned UPC to an SKU

UPC is now a direct column on `sku` — no separate `upcs` table. Query against `sku.upc` directly:

```
SELECT *
FROM sku
WHERE upc = $1
  AND deleted_at IS NULL
  AND (owned_by_business_id IS NULL OR owned_by_business_id = $2);
```

If no row returns, the UPC is unknown to this business: trigger the "Unknown UPC — assign or create" flow described in DESIGN.md.

### Record a Check In

Two writes in a transaction. MariaDB-compatible upsert using `VALUES()`:

```
INSERT INTO stock_movement (id, business_id, sku_id, user_id, direction, quantity_change, upc_scanned, notes, created_at)
VALUES (?, ?, ?, ?, 'in', ?, ?, ?, NOW());

INSERT INTO stock_level (id, business_id, sku_id, quantity, last_movement_at, created_at, updated_at)
VALUES (?, ?, ?, ?, NOW(), NOW(), NOW())
ON DUPLICATE KEY UPDATE
  quantity = stock_level.quantity + VALUES(quantity),
  last_movement_at = NOW(),
  updated_at = NOW();
```

Check Out is identical except `direction = 'out'` and the upsert sets `quantity = stock_level.quantity - VALUES(quantity)`.

In Eloquent, both writes happen inside `DB::transaction()`. Use `StockLevel::updateOrCreate()` for the upsert (Laravel handles the underlying syntax internally).

Note: the `VALUES()` function is the MariaDB-compatible way to refer to the row being inserted within an `ON DUPLICATE KEY UPDATE` clause. MySQL 8.0.19+ added a row-alias syntax (`AS new ON DUPLICATE KEY UPDATE col = new.col`), but MariaDB has not adopted it. Stick with `VALUES()`.

### List low-stock SKUs for a business

```
SELECT sku.id, sl.quantity, override.reorder_threshold
FROM stock_level AS sl
JOIN sku ON sku.id = sl.sku_id
LEFT JOIN business_sku_override AS override
  ON override.sku_id = sl.sku_id AND override.business_id = $1
WHERE sl.business_id = $1
  AND override.reorder_threshold IS NOT NULL
  AND sl.quantity <= override.reorder_threshold;
```

### Job stock readiness

For each line item, compare `planned_quantity` against current `stock_level.quantity`:

```
SELECT
  jli.sku_id,
  jli.planned_quantity,
  COALESCE(sl.quantity, 0) AS on_hand,
  CASE WHEN COALESCE(sl.quantity, 0) >= jli.planned_quantity THEN true ELSE false END AS ready
FROM job_line_item AS jli
JOIN job ON job.id = jli.job_id
LEFT JOIN stock_level AS sl
  ON sl.sku_id = jli.sku_id AND sl.business_id = job.business_id
WHERE jli.job_id = $1
  AND job.business_id = $2
  AND jli.deleted_at IS NULL;
```

Note the redundant `job.business_id = $2` filter even though we're already filtering by `job_id`. This is defense-in-depth: it makes a leaked or guessed `job_id` from another business return no rows instead of leaking data.

### Add and remove a Favorites SKU

Favorites are now a `list_item` row on the seeded Favorites list (`is_business_favorites = true`). Look up the Favorites list_id once per session and cache it; it never changes for a given business.

```
-- Resolve the Favorites list_id (once per session)
SELECT id FROM list
WHERE business_id = ? AND is_business_favorites = TRUE AND deleted_at IS NULL;
```

After that, add and remove operations are list_item operations:

```
-- Add (no-op if already on the Favorites list)
INSERT INTO list_item (id, list_id, sku_id, planned_quantity, sort_order, created_at, updated_at)
VALUES (?, ?, ?, NULL, 0, NOW(), NOW())
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Remove (soft delete)
UPDATE list_item
SET deleted_at = NOW()
WHERE list_id = ? AND sku_id = ? AND deleted_at IS NULL;
```

Note the change from the previous `favorite` table:

- Soft delete (not hard delete), since `list_item` uniformly soft-deletes
- `planned_quantity` is `NULL` on Favorites entries (Favorites tracks "we stock this," not "we plan to use N")
- The unique constraint on `(list_id, sku_id, deleted_at)` lets a SKU be removed and re-added without conflict

In Eloquent, this becomes `BalloonList::find($favoritesListId)->items()->updateOrCreate(['sku_id' => $sku->id], [...])`. A `FavoritesService` wrapping the Favorites list_id resolution is cleaner than doing it inline everywhere.

### List business Favorites

```
SELECT
  sku.id,
  COALESCE(override.custom_name, sku.computed_name, sku.name) AS display_name,
  COALESCE(override.custom_color_hex, sku.color_hex) AS display_color_hex,
  sku.balloon_size_id,
  sku.brand_id,
  sku.texture_id,
  sku.warehouse_sku,
  COALESCE(sl.quantity, 0) AS on_hand
FROM list_item AS li
JOIN list ON list.id = li.list_id
JOIN sku ON sku.id = li.sku_id
LEFT JOIN business_sku_override AS override
  ON override.sku_id = sku.id AND override.business_id = ? AND override.deleted_at IS NULL
LEFT JOIN stock_level AS sl
  ON sl.sku_id = sku.id AND sl.business_id = ?
WHERE list.business_id = ?
  AND list.is_business_favorites = TRUE
  AND list.deleted_at IS NULL
  AND li.deleted_at IS NULL
  AND sku.deleted_at IS NULL
  AND (sku.owned_by_business_id IS NULL OR sku.owned_by_business_id = ?)
ORDER BY li.created_at DESC;
```

The structure mirrors the standard "list items" query (with overrides and on-hand counts), distinguished only by the `list.is_business_favorites = TRUE` filter. This is the point of unifying Favorites under `list`: one query shape works for any list view.

### Add a SKU to a list

Three steps: verify list ownership, compute next sort_order, then insert. The verification protects against a leaked or guessed `list_id` from another business. The sort_order is computed in a separate query because MariaDB doesn't allow subselecting from a target table inside `INSERT...VALUES`.

```
-- Step 1: confirm the list belongs to the calling business
SELECT 1 FROM list
WHERE id = ? AND business_id = ? AND deleted_at IS NULL;
-- If no row, refuse the insert.

-- Step 2: compute next sort order
SELECT COALESCE(MAX(sort_order) + 1, 0) AS next_sort
FROM list_item
WHERE list_id = ? AND deleted_at IS NULL;

-- Step 3: insert or update
INSERT INTO list_item (id, list_id, sku_id, planned_quantity, sort_order, created_at, updated_at)
VALUES (?, ?, ?, ?, ?, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  planned_quantity = VALUES(planned_quantity),
  updated_at = NOW();
```

In Eloquent, this is a `DB::transaction()` block: query the list with `business_id` scope, fetch `MAX('sort_order')`, then `ListItem::updateOrCreate()`. The unique constraint includes `deleted_at`, so a soft-deleted row with the same `(list_id, sku_id)` doesn't block the insert.

### List items in a list (with overrides and on-hand)

```
SELECT
  li.id,
  li.sku_id,
  li.planned_quantity,
  li.sort_order,
  li.notes,
  COALESCE(override.custom_name, sku.computed_name, sku.name) AS display_name,
  COALESCE(override.custom_color_hex, sku.color_hex) AS display_color_hex,
  sku.balloon_size_id,
  sku.brand_id,
  sku.warehouse_sku,
  COALESCE(sl.quantity, 0) AS on_hand
FROM list_item AS li
JOIN list ON list.id = li.list_id
JOIN sku ON sku.id = li.sku_id
LEFT JOIN business_sku_override AS override
  ON override.sku_id = sku.id AND override.business_id = $2 AND override.deleted_at IS NULL
LEFT JOIN stock_level AS sl
  ON sl.sku_id = sku.id AND sl.business_id = $2
WHERE li.list_id = $1
  AND list.business_id = $2
  AND li.deleted_at IS NULL
  AND sku.deleted_at IS NULL
  AND (sku.owned_by_business_id IS NULL OR sku.owned_by_business_id = $2)
ORDER BY li.sort_order ASC, li.created_at ASC;
```

Same defense-in-depth pattern as the Job query: the redundant `list.business_id = $2` filter prevents data leak from leaked or guessed list IDs.

### Fetch local prices map for a business

```
SELECT price_code, amount_cents
FROM local_price
WHERE business_id = $1
  AND deleted_at IS NULL;
```

Caller materializes this as a `Map<priceCode, amountCents>`. Used only by the LocalPricesTable in Settings → Pricing. If you find this query running outside that surface, stop and check why — there's no other authorized consumer in v1.

`local_price.price_code` loosely matches `price_codes.code` by string (no FK enforcement). Orphan rows are allowed.

---

## Decisions deferred

These are real product calls that will need to be made before related code is written. Listed here so they don't get lost.

- **Catalog curation model**: who can add and edit shared SKUs, what moderation looks like, how brands get added to the `brand` table. v1 assumption: admin-only, seeded from manufacturer catalogs.
- **UPC conflict resolution**: what happens when business A assigns UPC `012345678901` to SKU X, then business B insists it's actually SKU Y. v1: last-write-wins on `sku.upc` with `stock_movement` providing the audit trail. UPC is a direct column on `sku`, not a separate table.
- **Image storage and processing**: thumbnail sizes, max upload, compression, CDN strategy. Single and cluster image paths exist on SKU, color, color_family, balloon_size, size, and texture_family. Upload UI deferred.
- **Brand color seeding**: where the initial `brand_color_hex` values come from. Manual curation for v1.
- **Soft-delete cleanup**: when do tombstoned rows get hard-deleted, if ever. v1: never.
- **Audit log retention**: `stock_movement` is append-only; if it grows unboundedly, partitioning or archival becomes a concern. v1: don't worry about it until row count crosses a few million.
- **Cross-business stock transfer**: deferred to v2. Today, a user with memberships in A and B does this manually as Check Out + Check In.
- **Bulk operations**: CSV import for SKUs, CSV export for inventory snapshots, bulk stock adjustments. Not modeled in this file yet.
- **List visibility and sharing**: lists are shared across all members of a business in v1. Per-user private lists, sharing with specific users, sharing across businesses, and list-import-from-template are all v2 concerns.
- **Default Local Prices for shared SKUs**: a new business starts with an empty `local_price` table. v2 might suggest defaults from a community-curated catalog or import from CSV.
- **Business plan enforcement**: the `business.plan` column exists and defaults to `solo`. In v2, each plan tier will gate different maximum user counts (artists + guests + owners) and be charged a different subscription fee. No enforcement code is written yet — the field is structural scaffolding only.
- **Per-business currency**: USD assumed in v1. Storing `amount_cents` as integer future-proofs the schema. Adding a `currency_code` column to `business` and `local_price` is straightforward when the need arrives.
- **Price code dictionary**: implemented as the `price_codes` table with FK from `sku.price_code_id`. `local_price.price_code` still loosely matches by string (no FK to `price_codes`). If governance becomes needed for `local_price`, add a `price_code_id` FK there as well.
- **Favorites schema history note**: in pre-PERMISSIONS.md drafts, Favorites was a separate `favorite` junction table. It was unified under the `list` table with an `is_business_favorites` flag during the PERMISSIONS.md design pass. This note exists so a future reader doesn't reintroduce a separate table by mistake.
- **Per-Guest custom permissions**: PERMISSIONS.md notes the v1 limitation that all Guests get the same default permissions. v2 will introduce a `membership_permission_override` table allowing Owners to grant individual Guests broader access (Job visibility, Local Prices visibility, etc.) on a case-by-case basis. Schema sketch: `id`, `membership_id`, `permission_key` (text matching Spatie permission names), `granted` (boolean), timestamps. Read-time logic merges role-default permissions with per-membership overrides.
- **SuperAdmin support view**: PERMISSIONS.md notes that v1 SuperAdmins cannot read a business's tenant-scoped data without being a member. v2 will add a "support view" mode where a SuperAdmin can opt into read-only access to any business, with the action audit-logged. Implementation likely needs a separate `super_admin_audit_log` table to track every tenant-scoped read by a SuperAdmin, plus an explicit "enter support view" UI step that requires a reason string.
- **Converting a List to a Job**: a List is a reusable template; a Job is a dated work assignment. v2 might let users instantiate a Job from a List ("use my Halloween List as the basis for the Pumpkin Patch Party Job on October 31"). Not modeled in v1.
- **LSCache integration**: the `litespeedtech/lscache-laravel` package can cache dynamic Laravel pages at the LiteSpeed origin. Plan to install during stabilization, not initial development; cache invalidation rules need to be designed once the routes settle.
- **Cloudflare R2 migration**: object storage is `local` (cPanel disk) for v1. R2 migration becomes interesting when total stored image size passes ~5GB or egress becomes a cost concern. Laravel's filesystem abstraction makes the swap a config change.
- **PHP 8.4 upgrade**: 8.3 is the v1 baseline. 8.4 brings property hooks and asymmetric visibility, neither critical. Defer until 8.3 hits the start of its security-only window (late 2026 by current schedule).
- **UUID storage migration**: stored as `CHAR(36)` for readability and Laravel compatibility. Two future optimization paths if row counts cross 10M and storage/index size becomes meaningful: migrate to `BINARY(16)` with a Laravel cast, or migrate to MariaDB's native `UUID` column type (16-byte storage, 36-char display, available in MariaDB 10.7+). The native type is simpler if we stay on MariaDB; `BINARY(16)` is more portable. Not a v1 concern.

---

## Changing this file

When you add an entity, change a relationship, or relax the multi-tenancy contract: update this file in the same change set as the schema migration. A schema migration without a corresponding DATA.md update is incomplete.

When the multi-tenancy contract changes, get a second pair of eyes. That section is load-bearing for the entire product.
