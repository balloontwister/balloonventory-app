# List History / Audit Log — Session Prompt

## Goal
Add a history/audit log to the Lists feature so business owners can see who created a list and when, and who added or removed items from it over time.

## Context
- The app is a Laravel 12 / Inertia.js / Vue 3 application.
- Read `CLAUDE.md` (project conventions) and `memory/project_lists_state.md` before starting.
- The existing inventory audit-log pattern (stock_movements table + StockMovement model) is the closest analogue. The scan page's Recent Scans list is the closest UI analogue.

## What Exists Today
- `lists` table: id, business_id, name, notes, is_business_favorites, created_by_user_id, visibility, archived_at, timestamps, deleted_at
- `list_items` table: id, list_id, sku_id, planned_quantity, sort_order, notes, timestamps, deleted_at
- `App\Models\BalloonList` — has `createdBy()` belongs-to User relation
- `App\Models\ListItem`
- `App\Http\Controllers\ListsController` — handles index, show, create/store, edit/update, destroy, itemsStore, itemsUpdate, itemsDestroy
- No `list_events` table or model exists yet

## What to Build

### 1. Migration: `create_list_events_table`
```
id (uuid, PK)
business_id (uuid, FK → businesses)
list_id (uuid, FK → lists)
user_id (uuid, FK → users, nullable — future-proof for system events)
event_type (string) — values: 'created', 'item_added', 'item_removed', 'item_qty_changed', 'renamed', 'archived', 'unarchived'
data (json, nullable) — e.g. { "sku_name": "Qualatex 11\" Red", "sku_id": "...", "old_qty": 5, "new_qty": 10 }
created_at (timestamp) — no updated_at (append-only log)
```
No soft deletes — this is an append-only audit trail.

### 2. Model: `App\Models\ListEvent`
- `$table = 'list_events'`
- UUID primary key, `$timestamps = false`, `$fillable` for all columns
- `casts()`: `data` as `array`
- `list()` → belongsTo BalloonList; `user()` → belongsTo User; `business()` → belongsTo Business

### 3. Record Events in ListsController

| Method | Event type | Data |
|---|---|---|
| `store()` | `created` | `['name' => $list->name]` |
| `update()` | `renamed` | `['old' => $old, 'new' => $new]` only when name changed |
| `itemsStore()` | `item_added` | `['sku_id' => ..., 'sku_name' => ...]` |
| `itemsDestroy()` | `item_removed` | `['sku_id' => ..., 'sku_name' => ...]` |
| `itemsUpdate()` | `item_qty_changed` | `['sku_id' => ..., 'sku_name' => ..., 'old_qty' => ..., 'new_qty' => ...]` (only when planned_quantity changes) |
| `update()` (archive toggle) | `archived` / `unarchived` | `[]` |

Use a private helper `recordListEvent(BalloonList $list, string $type, array $data = [])` to avoid repetition.

### 4. Show Events in `Lists/Show.vue`

Pass events from `ListsController@show()` as a `listPayload()` key:
```php
'events' => ListEvent::where('list_id', $list->id)
    ->with('user:id,name')
    ->orderByDesc('created_at')
    ->limit(50)
    ->get()
    ->map(fn ($e) => [
        'id' => $e->id,
        'type' => $e->event_type,
        'data' => $e->data ?? [],
        'user_name' => $e->user?->name ?? 'System',
        'created_at' => $e->created_at,
    ])->all(),
```

In `Lists/Show.vue`, add a "History" section below `<ListContents>` (similar to how Scan shows recent scans). Each row shows:
- An icon or pill for event type (colored by type: green=added, red=removed, neutral=others)
- A human-readable description: "Alice added Qualatex 11" Red", "Bob removed TufTex 5" Blue", "Carol renamed the list", etc.
- Timestamp (relative: "2 hours ago" or date if older)

Use i18n keys in `lang/en/lists.php` and `lang/es/lists.php` under a `history` section.

### 5. Tests
- Add tests to `tests/Feature/ListsControllerTest.php` or a new `tests/Feature/ListHistoryTest.php`
- Verify: creating a list records `created` event; adding an item records `item_added`; removing records `item_removed`; qty change records `item_qty_changed`; events appear in the `show()` payload

## Notes / Gotchas
- The `authorizeListEdit()` helper already handles favorites vs custom list authorization — call `recordListEvent()` after it.
- `listPayload()` is shared between `show()` and `inventoryView()`. Consider only loading events in `show()` (not in the inventory tab) to avoid overhead.
- The `visibility` field and `archived_at` field already exist on the `lists` table by the time this session runs.
- Run `php artisan test --compact tests/Feature/ListsControllerTest.php` (or equivalent) when done. Ask the user if they want to run the full suite.
- Run `vendor/bin/pint --dirty --format agent` after all PHP changes.
- Deploy via `git push origin main` (triggers `npm run build` pre-push hook) then `ssh myvps "cd /home/balloonventory/balloonventory-app && bash bin/deploy.sh"`.
