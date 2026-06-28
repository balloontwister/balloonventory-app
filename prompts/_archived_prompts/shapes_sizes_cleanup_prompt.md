# Cleanup: shapes table (material labels + duplicates) and a duplicate balloon_size

You are cleaning up reference data in the **Balloonventory** Laravel app. The catalog's
`shapes` table has accumulated wrong `material_id` values and duplicate/orphan rows
(some from a seeder, some from hand-editing in the production UI), and there is one
duplicate `balloon_size`. This is **data cleanup**, not a feature. Be careful and
verify against production before and after every change.

## Background / data model

- `shapes` — `id`, `name`, **`material_id`** (nullable FK → `materials`), `sort_order`,
  `description`, soft-deletes (`deleted_at`). A shape is a balloon form (Round, Heart,
  Round Foil, Star Foil, …). Material is meaningful: a latex Round and a foil Round are
  different shapes/products.
- `sizes` — diameter reference (`11-inch`, `24-inch`, `160`, `660`, …). **No material FK**
  (correct — `11-inch` exists in both latex and foil). `name` is unique.
- `balloon_sizes` — the per-brand variant: `brand_id`, `material_id`, `size_id`,
  `shape_id`, display `name` (e.g. `R-24`, `LOL-12`, `18-inch Foil Round`). This is what
  SKUs reference. Soft-deletes.
- `materials` — `Chloroprene, Foil, Latex, Plastic, Stretchy`.

## What's wrong (verified on prod 2026-06-25 — RE-VERIFY, it may have changed)

`shapes` rows and their `material` + how many `balloon_sizes` reference each:

| name | material | #balloon_sizes | problem |
|---|---|---|---|
| Round | Latex | 47 | OK |
| Heart | Latex | 12 | OK |
| Link | Latex | 15 | OK |
| Non-round | Latex | 26 | OK |
| Round Foil | Foil | 5 | OK |
| Square Foil | Foil | 1 | OK |
| Shaped | Foil | 5 | OK-ish (confirm "Shaped" should be Foil; ShapeSeeder created it as Latex) |
| Star Foil (dup) | Foil | 1 | the **correct** foil star — but mis-named "(dup)" |
| Star Foil | Latex | 0 | WRONG material, orphan (0 refs) |
| Circle Foil | Latex | 0 | WRONG material, orphan |
| Shaped Foil (dup) | Latex | 0 | WRONG material, orphan |
| SuperShape (foil) | Latex | 0 | WRONG material, orphan |
| Multi-shape | NULL | 0 | material unset, orphan |
| Other | Latex | 0 | OK (generic catch-all) |

So there are two "Star Foil" rows (the used one is named `Star Foil (dup)` and is correct
Foil; the unused `Star Foil` is wrongly Latex), a `Shaped Foil (dup)` orphan, and several
foil shapes mislabeled Latex with **0** references.

Note the id-prefix tell: the wrong-material/orphan rows share an id batch with the original
latex seeder run (they were renamed in the UI to add "Foil"/"(dup)" but their material was
never updated); the correctly-Foil rows are a later batch.

**Also:** one duplicate `balloon_size` — Britetex `12-inch (B)` exists **twice** (same brand,
same name). Dedup to one (keep whichever is referenced by SKUs; re-point any SKU refs first).

## The seeder

`database/seeders/ShapeSeeder.php` early-returns if `Shape::withTrashed()->exists()` (catalog
is curated by hand in production), and it creates a base set **all as Latex** with
material-agnostic names (Round, Link, Non-round, Heart, Circle, Star, Shaped, SuperShape,
Other). The foil shapes were added later by hand. Decide and document the canonical shape
set + correct material per shape, and update `ShapeSeeder` so a fresh install / test DB
reproduces the *intended* state (fixing the all-Latex assumption for foil shapes).

## Tasks

1. **Re-verify current state on prod** (it may differ from the table above). Dump every
   shape with id, name, material, soft-delete state, and reference counts from BOTH
   `balloon_sizes` AND `skus` (check whether SKUs reference `shape_id` directly or only via
   `balloon_size_id` — re-point before deleting anything).
2. **Fix mislabeled materials**: foil shapes → `Foil`. Confirm `Shaped`, `Multi-shape`,
   `SuperShape` intended materials with the catalog (don't guess silently — if unsure,
   leave a note and pick the conservative option).
3. **Merge/remove duplicates**: collapse `Star Foil` / `Star Foil (dup)` into one correctly-
   named, correctly-Foil row; remove `Shaped Foil (dup)`; remove genuinely-orphan
   wrong-material rows (0 references) or fix+keep them if they should exist. Re-point any
   `balloon_sizes`/`skus` references onto the surviving row **before** deleting. Prefer the
   model's delete (soft delete) unless a hard delete is clearly right; mind FKs.
4. **Dedup the `12-inch (B)` Britetex `balloon_size`** the same way (re-point SKU refs, then
   remove the extra). Investigate how it got duplicated (no `BritetexBalloonSizeSeeder`
   exists — likely hand-created or a re-run); note the cause so it doesn't recur.
5. **Update `ShapeSeeder`** to reflect the corrected canonical set + per-shape material, and
   add/adjust a test so it reproduces the intended state on a fresh DB.
6. **Tests + Pint**: write/adjust a focused test, run it, `vendor/bin/pint --dirty --format agent`.

## Constraints & runbook

- **Production access** (see the project's `project_server` memory): SSH alias `myvps`, app
  at `/home/balloonventory/balloonventory-app`. **Use the full PHP 8.4 path**
  `/opt/cpanel/ea-php84/root/usr/bin/php` for manual artisan/tinker (`php` is 8.2 and breaks
  on the lock file).
- The **deploy script does NOT run seeders** — any seeder change must be run manually on prod
  with `db:seed --class=… --force` after deploy. But this cleanup is mostly **data surgery on
  existing prod rows**, so prefer a small idempotent artisan command or a carefully-reviewed
  tinker script over re-seeding.
- **Never** run mutating artisan with `--env=testing` (no `.env.testing` → it hits the dev DB).
- Verify reference counts **before and after**; a shape/size with live SKU or balloon_size
  references must not be deleted until those are re-pointed. Take a quick count snapshot first.
- Don't touch unrelated working-tree changes; commit only the cleanup files (stage by name).

## Acceptance criteria

- Every foil shape has `material = Foil`; every latex shape `material = Latex`; no NULL
  material on a shape that's in use.
- No duplicate or "(dup)"-named shape rows; the surviving star-foil row is correctly named
  and Foil, with the one `balloon_size` re-pointed to it.
- Britetex `12-inch (B)` exists exactly once; SKU references intact.
- `ShapeSeeder` reproduces the corrected set on a fresh DB; its test passes; Pint clean.
- A short note recording what was changed and the suspected cause of the `12-inch (B)` dup.
