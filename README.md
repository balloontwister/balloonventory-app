# Balloonventory

Inventory management for balloon businesses. Track stock, plan jobs, manage your product catalog, and keep a full audit trail of every movement in or out.

## What it does

**Inventory** — Track balloon stock levels per business, log every movement (in/out) with notes and timestamps, scan UPCs to update counts, and set reorder thresholds so nothing runs out before an event.

**Jobs** — Create work orders tied to specific events. Attach line items (SKUs + planned quantities), then record actual stock usage against the job as you go. Jobs move through draft → planned → in progress → archived.

**Lists** — Build reusable collections of balloon combinations. Every business gets a protected Favorites list, plus unlimited custom lists with per-item quantity planning.

**Catalog** — A global SKU catalog organized by brand, size, color, shape, material, and texture. Businesses can override display names, colors, pricing, and visibility per SKU without touching the shared catalog.

**Multi-tenant** — Each business is fully isolated. Users can belong to multiple businesses and switch between them. Stock, jobs, lists, and pricing are all scoped per business.

## Tech stack

- PHP 8.5 / Laravel 12
- Inertia.js v2 + Vue 3
- Tailwind CSS v3
- PHPUnit 11

## Local setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
composer run dev
```

`composer run dev` starts the Laravel dev server, queue worker, and Vite in parallel.

## Testing

```bash
php artisan test --compact
```

Run a specific file or filter:

```bash
php artisan test --compact tests/Feature/InventoryTest.php
php artisan test --compact --filter=testStockMovement
```

Every change needs a passing test before it's done.

## Root-level files — leave these where they are

A handful of Markdown files live at the repository root on purpose. They look like loose docs, but moving them into a subfolder breaks tooling or established cross-references. Leave them at root.

**Agent-instruction files (discovered by convention):**

- `CLAUDE.md` — loaded automatically by Claude Code, which searches upward from the working directory. It only auto-loads from the root.
- `AGENTS.md` — the cross-tool agent spec, read from the repository root. It is also the hub of the spec system below.

**The spec system (`AGENTS.md` + the four spec files):**

`AGENTS.md`, `DESIGN.md`, `DATA.md`, `PERMISSIONS.md`, and `EMAIL.md` form one tightly cross-linked, user-owned set. `AGENTS.md` routes to the others by topic (design → `DESIGN.md`, schema → `DATA.md`, roles → `PERMISSIONS.md`, email → `EMAIL.md`), and they reference each other by bare filename throughout. Because `AGENTS.md` must stay at root, the others stay beside it so those references resolve and the hub-and-spokes set stays co-located.

- `DESIGN.md` — visual design, components, layout, color, typography.
- `DATA.md` — database schema, queries, migrations, multi-tenancy.
- `PERMISSIONS.md` — roles, permissions, policies.
- `EMAIL.md` — email templates and the email system.
- `GLOSSARY.md` — balloon-industry vocabulary; references `DATA.md` for canonical brand abbreviations.

**Other root files that stay by convention:** `README.md` (this file).

If root ever needs decluttering, the only safe move is to relocate `AGENTS.md` and all four spec files *together* into one folder and update the references in the same change set — never move them piecemeal.
