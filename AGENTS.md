# AGENTS.md — Balloonventory

The entry point for any AI coding agent (Claude Code, Cursor, Copilot, Aider, etc.) working in this repo. Read this first. It routes you to detail files when you need them.

Keep this file lean. Detail belongs in DESIGN.md, DATA.md, and PERMISSIONS.md. If you find yourself wanting to copy content from those files into here, link instead.

---

## Project

Balloonventory is a multi-tenant SaaS inventory system for professional balloon artists. Bags of balloons are tracked by color, size, brand, and finish. Stock movement happens primarily through UPC barcode scanning at the artist's home base, office, or warehouse — never at the gig. A single user account can belong to multiple businesses with separate per-business permissions.

The product is intentionally narrow: it tracks inventory and supports planning. It does not track money, gig profitability, consumption against estimates, or any reconciliation between planned and actual usage. These are explicit non-features.

---

## Tech stack

- **Runtime**: PHP 8.5.5 (local), PHP 8.4 via EasyApache 4 on the server
- **Framework**: Laravel 12 with Breeze starter kit (Inertia + Vue 3 + Tailwind)
- **Frontend**: Vue 3, Inertia.js, Tailwind, Reka UI for accessible primitives
- **Database**: MariaDB 10.11.16 (LTS). All tables are **InnoDB** with `utf8mb4_unicode_ci`. `config/database.php` pins `engine => InnoDB` on the mysql + mariadb connections so future migrations follow suit. Foreign keys declared in migrations are enforced at the DB level — verify with `SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'FOREIGN KEY'` (~64 currently). The host's default engine was MyISAM until 2026-05-15; see `database/migrations/2026_05_15_154811_convert_tables_to_innodb_and_restore_fks.php` for the conversion and FK restoration.
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
| Email templates, TemplatedMailable, Tallie persona, email chrome layout | `EMAIL.md` |
| Anything in this list at the same time | All relevant files; cross-references between them are intentional |

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
├── Http/Controllers/
│   ├── SuperAdmin/
│   │   ├── CatalogController.php          # Shared SKU CRUD — cascading filters, computed_name, pivot sync
│   │   ├── CatalogColorController.php     # Color CRUD
│   │   ├── CatalogBrandController.php     # Brand list + update
│   │   ├── CatalogReferenceController.php # Generic reference data (sizes/shapes/etc.)
│   │   └── SupportTicketController.php
│   ├── LocaleController.php               # POST /locale/switch — auth + guest locale changes
│   └── ...
├── Models/                    # Eloquent models, all use HasUuids + SoftDeletes
│   ├── Concerns/
│   │   ├── BelongsToBusiness.php  # Tenancy trait with global scope
│   │   └── HasTranslations.php    # i18n trait — translated(), withTranslations(), loadTranslations()
│   ├── Brand.php, Sku.php              # Core catalog models
│   ├── Size.php, Shape.php, Texture.php, Material.php  # Reference lookup tables
│   ├── Color.php, ColorFamily.php      # Two-level color taxonomy
│   ├── Theme.php                       # Many-to-many with Sku via sku_themes pivot
│   ├── BalloonSize.php                 # Brand+material-specific balloon size definitions
│   ├── PackagingType.php               # Bag packaging types (Individual, Loose, etc.)
│   ├── PriceCode.php                   # Per-brand pricing codes
│   ├── PrintColor.php, PrintSide.php   # Printed balloon detail lookups
│   ├── TextureFamily.php               # Groups textures into families
│   ├── BrandGs1Prefix.php              # Brand-specific GS1 manufacturer prefixes
│   └── *Translation.php               # 6 translation models (ShapeTranslation, MaterialTranslation, etc.)
├── Services/                  # Domain services (StockService, JobService, etc.)
├── Policies/                  # Laravel policies, one per model
├── Notifications/             # Laravel notifications for unknown UPC, errors
├── Observers/                 # Model observers (Favorites seed, last-Owner guard)
└── Scopes/
    └── BusinessScope.php      # The global scope used by BelongsToBusiness

database/
├── migrations/                # Schema migrations; reflect DATA.md
└── seeders/
    ├── PermissionSeeder.php       # Seeds Spatie roles and permissions from PERMISSIONS.md
    ├── BrandSeeder.php            # 7 brands (QTX, STX, BET, KAL, TTX, DCX, FSN)
    ├── SizeSeeder.php             # 14 sizes with size_category groupings
    ├── ShapeSeeder.php            # 9 shapes
    ├── TextureFamilySeeder.php    # 5 texture families (Crystal, Standard, etc.)
    ├── TextureSeeder.php          # 9 textures with FK refs to material + texture_family
    ├── ColorFamilySeeder.php      # 13 color families with material FK
    ├── ColorSeeder.php            # ~150 brand-specific colors
    ├── ThemeSeeder.php            # 9 themes (Holiday, Christmas, etc.)
    ├── MaterialSeeder.php         # 5 materials (Latex, Foil, etc.)
    ├── PackagingTypeSeeder.php    # 4 packaging types (Individual, Loose, etc.)
    ├── PrintColorSeeder.php       # 11 print ink colors
    ├── PrintSideSeeder.php        # 5 print side options
    ├── PriceCodeSeeder.php        # Per-brand price codes
    ├── BalloonSizeSeeder.php      # ~40 brand+material balloon sizes
    └── CatalogTranslationSeeder.php  # Spanish translations for all reference data

resources/js/
├── Pages/
│   ├── SuperAdmin/
│   │   ├── Dashboard.vue
│   │   └── Catalog/
│   │       ├── Index.vue      # SKU list with filters + pagination
│   │       ├── SkuForm.vue    # Create/edit SKU — brand-driven color filter, theme pills
│   │       ├── Colors.vue     # Colors grouped by family, inline edit
│   │       ├── Brands.vue     # Brand list, inline edit
│   │       └── Reference.vue  # Sub-tabbed reference data (sizes/shapes/textures/etc.)
│   └── ...
├── Components/                # Design-system components from DESIGN.md
│   ├── BalloonSwatch.vue
│   ├── ScanField.vue
│   ├── BusinessSwitcher.vue
│   ├── LocaleSwitcher.vue       # Globe icon dropdown — server-driven locale list
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

## i18n & locale system

The app supports multiple UI locales. The current system:

- **Source of truth**: `config('app.supported_locales')` — an associative map of locale code → display label (e.g. `'es' => 'Español'`). Adding a language means adding it here and creating `lang/{code}/` — zero JS changes.
- **Backend**: `SetUserLocale` middleware (global web) reads `users.locale` for auth users, falls back to `session('locale')` for guests. Every page render gets `app()->getLocale()`.
- **Frontend i18n**: `laravel-vue-i18n` compiles `lang/*.php` to JSON bundles at build time. `$t()` is available globally in Vue templates. The `LocaleController` + `POST /locale/switch` persists locale changes; the frontend forces a full page reload after switching because the i18n plugin initializes once at app boot.
- **Shared prop**: `HandleInertiaRequests` shares `supportedLocales` (structured `[{code, label}]`) and `locale` (current code) to every Inertia page.
- **Catalog translations**: 6 `*_translations` tables store per-locale names/descriptions for reference data (shapes, materials, textures, color_families, colors, themes). Models use the `HasTranslations` trait. Controllers resolve translated values before passing to Inertia.
- **LocaleSwitcher.vue**: Globe icon dropdown usable in any layout. Reads `supportedLocales` and `locale` from `$page.props`. Posts to `/locale/switch` and reloads.
- **Guest users**: Locale stored in session, not DB. Lost on login (acceptable v1 limitation).

---

## Image uploads

All catalog image uploads go through `App\Services\Catalog\CatalogImageService` — a single chokepoint that resizes oversized source files down to `MAX_WIDTH` (currently 1200px, preserving aspect, stripping EXIF), stores them under `storage/app/public/<entity-folder>/<hashed-filename>.<ext>`, deletes the previously stored file when replacing, and provides URL helpers for Inertia responses. The driver is Imagick on prod (auto-falls back to GD when the extension isn't available — e.g. local dev / CI).

Per-entity folder + slot configuration lives in a `CONFIG` map at the top of the service. Controllers never know paths or column names — they call `$service->set($model, $slot, $file)`, `$service->clear($model, $slot)`, or `$service->urls($model)`. To add image upload support to a new entity (e.g. `User::class` for profile pictures or `Business::class` for business logos), append an entry to that map with its folder + slot map and call the service from the controller — no other changes needed.

The user-facing form layer uses two reusable components: `<ImageUpload>` for the input (file picker + preview + clear toggle, with `v-model:file` and `v-model:clear`) and `<ImageGallery>` for display (accepts an array of URLs, filters falsy entries, so today's 1–2-slot entities and a future multi-image gallery use the same prop shape). Forms that include an `<ImageUpload>` must submit with `forceFormData: true` so multipart reaches PHP, and use `_method` spoofing on PATCH routes (Inertia v2's `useForm` does this automatically when forceFormData is set).

If we later migrate to `spatie/laravel-medialibrary` for gallery-style multi-image, the service is the only file that changes; controllers and Vue components keep their current API.

**Future entities** — when adding profile pictures (`users.avatar_path`) or business logos (`businesses.logo_path`), follow the same pattern: column on the model, slot in `CatalogImageService` config (rename the service if it stops being catalog-only), `<ImageUpload>` on the form, `<ImageGallery>` for display. Do not create a parallel upload path.

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
| PHP (local) | 8.5.5 (Homebrew/Herd) |
| PHP (server) | 8.4 via EasyApache 4 (`ea-php84`); declared in `public/.htaccess` with `AddHandler application/x-httpd-ea-php84 .php` |
| Database | MariaDB 10.11.16 |
| Cache/queue driver | `database` |

---

## Transactional email

See **EMAIL.md** for the full email system design — Tallie persona, hybrid template model, database-driven templates, support ticket flow, queuing policy, and the chrome layout. The summary below is for orientation; EMAIL.md is source of truth.

**Provider**: [Resend](https://resend.com) HTTP API via `resend/resend-laravel`. SMTP is not an option — the host blocks outbound ports 25/465/587.

**From / Reply-To**: `tallie@balloonventory.com` ("Tallie at Balloonventory") with Reply-To routed per Mailable (see EMAIL.md "Reply-To strategy"). Inbound for `@balloonventory.com` is handled by Cloudflare Email Routing.

### Server `.env`

```
MAIL_MAILER=resend
RESEND_API_KEY=<Resend API key>
MAIL_FROM_ADDRESS=tallie@balloonventory.com
MAIL_FROM_NAME="Tallie at Balloonventory"
MAIL_SUPPORT_ADDRESS=support@balloonventory.com
```

### Local development

Set `MAIL_MAILER=log` to write outgoing emails to `storage/logs/laravel.log`, or run Mailpit on `localhost:1025`.

### Adding email types

Database-driven (admin-editable): add an `email_templates` row + register the trigger via `TemplatedMailable::forKey()`. See EMAIL.md "Adding a new email type."

Standalone (developer-owned, never user-editable): create a new `Mailable` class in `app/Mail/` and matching Blade views under `resources/views/mail/` extending `mail.layout`.

### Current Mailables

| Class | View | Trigger | Queued |
|---|---|---|---|
| `App\Mail\EmailVerificationCode` | `mail.verification-code` | Registration + resend on verify page | No (time-critical) |
| `App\Mail\SupportRequestMail` | `mail.support-request` | User submits contact form → sent to `support@` | No |
| `App\Mail\SupportReplyMail` | `mail.support-reply` | Super-admin replies to a ticket → sent to user | No |
| `App\Mail\TemplatedMailable` | `mail.templated` | Database-driven templates (e.g. `welcome`) | Yes (`ShouldQueue`) |

### Email observability

Every successful send is recorded in `email_logs` via the `App\Listeners\LogSentEmail` listener on `Illuminate\Mail\Events\MessageSent`. Listeners in `app/Listeners/` are auto-discovered — do not register them with `Event::listen()` in `AppServiceProvider` or they will fire twice. The Super Admin dashboard reads from `email_logs` for the "Emails by day / month" charts.

---

## Scheduled tasks

Laravel's task scheduler runs all scheduled commands. The server has a single cron entry that fires every minute and hands off to Laravel:

```
* * * * * cd /home/balloonventory/balloonventory-app && /opt/cpanel/ea-php84/root/usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

**Never add individual cron entries for application tasks.** Register everything in `routes/console.php` using the `Schedule` facade instead. This keeps the full schedule visible in one place and version-controlled.

### Adding a new scheduled task

1. Create the command: `php artisan make:command YourCommandName`
2. Implement `handle()` in `app/Console/Commands/YourCommandName.php`
3. Register the schedule in `routes/console.php`:
   ```php
   Schedule::command('app:your-command')->dailyAt('03:00');
   ```
4. Test locally: `php artisan app:your-command --dry-run` (add a `--dry-run` option when destructive)
5. Commit — the server picks it up automatically on next `git pull`

### Current scheduled tasks

| Command | Schedule | Purpose |
|---|---|---|
| `app:prune-unverified-users` | Daily at 03:00 | Delete accounts unverified for more than 24 hours |
| `queue:work --stop-when-empty` | Every minute | Drain queued email jobs (TemplatedMailable). Exits when empty; withoutOverlapping() prevents stacking. |

### Useful artisan schedule commands

```bash
php artisan schedule:list          # Show all registered tasks and next run time
php artisan schedule:run           # Run tasks due right now (what cron calls)
php artisan schedule:work          # Run scheduler in foreground (local dev only)
```

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
