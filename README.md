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
