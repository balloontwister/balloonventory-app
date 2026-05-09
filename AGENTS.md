# AGENTS.md — Balloonventory

The entry point for any AI coding agent (Claude Code, Cursor, Copilot, Aider, etc.) working in this repo. Read this first. It routes you to detail files when you need them.

Keep this file lean. Detail belongs in DESIGN.md, DATA.md, and PERMISSIONS.md. If you find yourself wanting to copy content from those files into here, link instead.

---

## Project

Balloonventory is a multi-tenant SaaS inventory system for professional balloon artists. Bags of balloons are tracked by color, size, brand, and finish. Stock movement happens primarily through UPC barcode scanning at the artist's home base, office, or warehouse — never at the gig. A single user account can belong to multiple businesses with separate per-business permissions.

The product is intentionally narrow: it tracks inventory and supports planning. It does not track money, gig profitability, consumption against estimates, or any reconciliation between planned and actual usage. These are explicit non-features.

---

## Tech stack

- **Runtime**: PHP 8.3.30
- **Framework**: Laravel 12 with Breeze starter kit (Inertia + Vue 3 + Tailwind)
- **Frontend**: Vue 3, Inertia.js, Tailwind, Reka UI for accessible primitives
- **Database**: MariaDB 10.11.16 (LTS)
- **ORM**: Eloquent
- **Auth**: Laravel built-in via Breeze
- **Authorization**: `spatie/laravel-permission`
- **Cache & queue driver**: `database` (Redis is unavailable on the current host)
- **Object storage**: local cPanel filesystem at `storage/app/public`
- **Hosting**: cPanel/LiteSpeed/CloudLinux; app at `app.balloonventory.com`, server path `/home/balloonventory/balloonventory-app/`, document root `/home/balloonventory/balloonventory-app/public`
- **Edge**: Cloudflare for DNS, WAF, DDoS, static asset caching, SSL
- **Local dev path**: `/Users/todd/Documents/VS Code Projects/Balloonventory App`

---

## Companion files — consult these when relevant

| Touching... | Read |
|---|---|
| Visual design, components, layout, color, typography | `DESIGN.md` |
| Database schema, queries, migrations, multi-tenancy | `DATA.md` |
| Roles, permissions, policies, notifications | `PERMISSIONS.md` |
| Anything in this list at the same time | All three; cross-references between them are intentional |

If a request seems to require changes across two or more of these files, update them in the same change set. A schema migration without a DATA.md update, or a policy change without a PERMISSIONS.md update, is incomplete.

---

## Critical rules — non-negotiable

These are the rules that must hold in every change. If a request asks you to violate one, surface the conflict before writing code.

1. **Multi-tenancy is enforced by Eloquent global scope.** Every tenant-scoped model uses the `BelongsToBusiness` trait, which registers a global scope filtering on `business_id = currentBusinessId()`. Never call `withoutGlobalScope` without a comment explaining why and a request for code review. See DATA.md "Multi-tenancy contract" — the loud non-negotiable section.

2. **No money in the UI.** Dollar amounts, prices, costs, and any monetary values appear only in the LocalPricesTable in Settings → Pricing. Even there they are reference-only data, never used in calculations. Don't add cost fields to entities. Don't compute totals. Don't display estimates.

3. **Permission-gated UI is disabled, never hidden.** The `PermissionGate` component renders a disabled state with an explanatory tooltip. Hidden UI is confusing across roles and businesses.

4. **Server-side authorization is the boundary.** Client-side `PermissionGate` is UX courtesy. Every server action re-checks permissions in a Laravel policy. Never trust the client.

5. **SuperAdmin doesn't bypass tenant scope.** The `is_super_admin` flag grants platform powers (shared catalog, business deletion). It does not grant access to a business's tenant-scoped data. SuperAdmin still needs a `membership` row to act inside a specific business.

6. **Soft delete uses `deleted_at`-in-unique-composite.** MariaDB-specific pattern. See DATA.md Conventions for examples.

7. **The product does not track actual usage.** No reconciliation between Job line items and stock movements. Returned bags Check In as plain stock with no link to the originating job.

---

## Build, run, test

```bash
# First-time setup
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed

# Development
npm run dev                    # Vite dev server with HMR
php artisan serve              # Laravel dev server (or use Valet/Herd)

# Test and lint (run all of these green before declaring done)
php artisan test               # PHPUnit / Pest test suite
./vendor/bin/pint              # PHP code style (Laravel's Pint)
npm run lint                   # ESLint for Vue/JS
npm run format                 # Prettier for Vue/JS
npm run type-check             # TypeScript / Vue type check if configured

# Production build
npm run build                  # Compiled assets to public/build/
```

**Run tests, linters, and formatters automatically before declaring any task done.** If any check fails, fix it before reporting. Do not surface a "task complete" message with red tests.

If a fix would touch files outside the working set of the current task, ask first (see Working scope below).

---

## Repository layout

Standard Laravel layout with these project-specific callouts:

```
app/
├── Models/                    # Eloquent models, all use HasUuids + SoftDeletes
├── Models/Concerns/
│   └── BelongsToBusiness.php  # Tenancy trait with global scope
├── Services/                  # Domain services (StockService, JobService, etc.)
├── Policies/                  # Laravel policies, one per model
├── Notifications/             # Laravel notifications for unknown UPC, errors
├── Observers/                 # Model observers (Favorites seed, last-Owner guard)
└── Scopes/
    └── BusinessScope.php      # The global scope used by BelongsToBusiness

database/
├── migrations/                # Schema migrations; reflect DATA.md
└── seeders/
    ├── PermissionSeeder.php   # Seeds Spatie roles and permissions from PERMISSIONS.md
    ├── BrandSeeder.php        # Seeds the brand table
    └── SharedSkuSeeder.php    # Seeds the shared SKU catalog (SuperAdmin-curated)

resources/js/
├── Pages/                     # Inertia page components (one per route)
├── Components/                # Design-system components from DESIGN.md
│   ├── BalloonSwatch.vue
│   ├── ScanField.vue
│   ├── BusinessSwitcher.vue
│   └── ...
└── Composables/               # Vue composables (useBusiness, usePermissions, etc.)

routes/
├── web.php                    # Inertia routes
└── api.php                    # JSON API routes (if any)

tests/
├── Feature/                   # End-to-end Inertia tests
└── Unit/                      # Pure unit tests
```

Anything not listed is standard Laravel; you already know where it goes.

---

## Conventions

Detail lives in DATA.md (schema) and DESIGN.md (visual). High-level rules:

- **PHP**: Laravel Pint with default config. Strict types where reasonable. Constructor property promotion preferred.
- **Vue**: Composition API with `<script setup>`. TypeScript optional in v1, recommended once a component grows past trivial.
- **Naming**: `snake_case` in DB and Eloquent attributes. `camelCase` in JS. `PascalCase` for Vue components and PHP classes.
- **Service classes**: see DATA.md "Canonical query helper signatures" — `Business $b` is always the first positional argument on tenant-scoped service methods.
- **No JSON columns for queryable data.** See DATA.md.
- **No cost or price fields on any entity.** See Critical rules.

---

## Workflows for common tasks

### Adding a new entity

1. Update DATA.md (entity definition, relationships diagram, query patterns)
2. Write the migration in `database/migrations/`
3. Write the Eloquent model with `HasUuids`, `SoftDeletes`, and (if tenant-scoped) `BelongsToBusiness` traits
4. Write the factory in `database/factories/`
5. Write the policy in `app/Policies/`
6. Register the policy in `AuthServiceProvider`
7. Add tests in `tests/Feature/` and/or `tests/Unit/`
8. Run migrations, tests, and linters

### Adding a new permission

1. Update PERMISSIONS.md with the new permission key and which roles get it
2. Add the permission to `database/seeders/PermissionSeeder.php`
3. Add a policy method on the relevant model's policy
4. Run `php artisan db:seed --class=PermissionSeeder`
5. Add or update tests

### Adding a new component

1. Read DESIGN.md for the relevant section: tokens, component patterns, do's and don'ts
2. Place the component in `resources/js/Components/`
3. Match naming and prop conventions of existing components
4. Use `PermissionGate` to wrap any action that needs role-based visibility
5. Use Reka UI primitives for any popover, dialog, or focus-trapping interaction

### Adding a new page

1. Place the Inertia page in `resources/js/Pages/`
2. Add the route in `routes/web.php`
3. Ensure the controller method passes a `permissions` prop on the Inertia response so client-side `PermissionGate` works correctly. The Inertia middleware should compute this scoped to the current business.

---

## Anti-patterns — never do these

- Don't reintroduce a `favorite` table. Favorites is a flag on the `list` table (`is_business_favorites = true`).
- Don't add cost, price, dollar, or monetary fields to `job`, `job_line_item`, `sku`, or any other entity. Money lives only in `local_price`, only in Settings, only as reference data.
- Don't compute against `local_price.amount_cents` anywhere outside the LocalPricesTable. No estimates, no totals, no projections.
- Don't hide permission-gated UI. Disable it with a tooltip.
- Don't bypass `BelongsToBusiness` global scope without an explanatory comment and a code review request.
- Don't add bottom-nav items on mobile. The mobile bottom nav is locked at 5 items with the center Scan button.
- Don't use DaisyUI, shadcn, or any pre-styled component library. Use Reka UI for primitives and Tailwind utilities for everything else.
- Don't add a date or client field to the `list` table. That makes it a Job. The distinction is intentional.
- Don't track consumption, fulfillment, or estimate-vs-actual reconciliation anywhere.
- Don't display full UPC strings in primary inventory rows. Last 6 digits in detail views only.

---

## Working scope and chattiness

Default behavior: **pragmatic**. Free to read, write, and run commands inside the working set of the current task. The "working set" is the files directly relevant to the request — controllers, models, components, tests, migrations for the entity in question.

**Ask before:**
- Touching files outside the working set (refactoring, renaming across the codebase, dependency updates)
- Installing new Composer or NPM packages
- Modifying any of the four spec files (AGENTS.md, DESIGN.md, DATA.md, PERMISSIONS.md) — these are user-owned and changes need a clear rationale
- Modifying CI configuration, deployment configuration, or `.env` defaults

**Surface (don't ask, but mention in the response):**
- Decisions made about ambiguous requirements
- Patterns established for the first time that future code should follow
- Trade-offs taken (e.g., "I added an index on `last_movement_at` because the query in the dashboard would otherwise sort the whole table")

---

## Git workflow

Commit messages follow [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>(<scope>): <short description>

<optional body>

<optional footer>
```

Types: `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`, `perf`, `build`, `ci`.

Scopes for this project: `inventory`, `catalog`, `lists`, `jobs`, `auth`, `permissions`, `ui`, `db`, `infra`.

Examples:

```
feat(inventory): add ScanField focus reclamation on rapid keystroke input
fix(catalog): prevent unknown UPC scans from creating duplicate pending records
docs(permissions): clarify last-Owner guard error message
refactor(db): extract Favorites query into FavoritesService
```

The agent writes commits scoped tightly to a single change. If a task spans two scopes, commit them separately. The agent does not push, branch, or open PRs — those are user actions.

---

## Environment

| Item | Value |
|---|---|
| Local dev path | `/Users/todd/Documents/VS Code Projects/Balloonventory App` |
| Production URL | https://app.balloonventory.com |
| Marketing URL | https://balloonventory.com (placeholder; marketing site TBD) |
| Server path | `/home/balloonventory/balloonventory-app/` |
| Document root | `/home/balloonventory/balloonventory-app/public` |
| SSH user | `balloonventory` |
| SSH alias | `myvps` (configured in `~/.ssh/config`) |
| Repository | https://github.com/balloontwister/balloonventory-app |
| Hosting | cPanel/LiteSpeed/CloudLinux |
| Edge | Cloudflare (DNS, WAF, caching, SSL) |
| PHP version | 8.3.30 |
| Database | MariaDB 10.11.16 |
| Cache/queue driver | `database` |

---

## Open decisions and known limits

See the "Decisions deferred" sections of DATA.md and PERMISSIONS.md for the running list of v2 work and known v1 gaps. Don't implement these without an explicit user request — they're deferred on purpose.

A short list of the most relevant ones a coding agent might bump into:

- **No SuperAdmin support view in v1.** SuperAdmin needs a `membership` to act in a business.
- **No per-Guest custom permissions in v1.** All Guests get the default permission set.
- **Cross-business stock transfer is manual.** A user with memberships in A and B does Check Out from A then Check In to B.
- **No bulk operations.** No CSV import, no bulk stock adjustments. Will come, but not in v1.

---

## Changing this file

When the stack, conventions, or workflows change, update AGENTS.md in the same change set. A subtle stack drift between AGENTS.md and the actual codebase is the single most common reason agents start producing low-quality code.
