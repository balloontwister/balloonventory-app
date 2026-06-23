# List History / Audit Log — Session Prompt

## Goal
Add a history/audit log to the Lists feature so business owners can see who created a list and when, and who added, removed, or changed items over time.

## Context
- Laravel 12 / Inertia.js v2 / Vue 3 application. Read `CLAUDE.md` before starting.
- Read `memory/MEMORY.md` to load relevant project memories.
- The closest existing analogue: `stock_movements` table + `StockMovement` model (inventory audit log). The scan page's "Recent Scans" list is the closest UI analogue.

## What Exists Today

### Database
- `lists` table: `id`, `business_id`, `name`, `notes`, `is_business_favorites`, `created_by_user_id`, `visibility` (standard/owner_editable/private), `archived_at`, timestamps, `deleted_at`
- `list_items` table: `id`, `list_id`, `sku_id`, `planned_quantity`, `sort_order`, timestamps, `deleted_at`
- No `list_events` table or model yet

### Models
- `App\Models\BalloonList` — has `createdBy()` belongsTo User relation
- `App\Models\ListItem`

### Controller
- `App\Http\Controllers\ListsController` — methods: `index`, `show`, `create`, `store`, `edit`, `update`, `destroy`, `itemsStore`, `itemsUpdate`, `itemsDestroy`
- Uses `Gate::authorize('update', $list)` for edit authorization (backed by `BalloonListPolicy`)
- `listPayload(BalloonList $list, bool $isOwner): array` — builds the props array for `show()` and `inventoryView()`; add events here for the show page only
- `currentUserIsOwner(Business $business): bool` — queries Membership directly (owners-only logic)

### Frontend
- `resources/js/Pages/Lists/Show.vue` — main list detail page; has notes card, archived notice, and `<ListContents>` component; add History section below ListContents
- `resources/js/Pages/Lists/Index.vue`, `Edit.vue`, `Create.vue` — no changes needed for history

### Lang files
- `lang/en/lists.php` and `lang/es/lists.php` — add a `history` section

## What to Build

### 1. Migration: `create_list_events_table`

```
id            uuid, PK
business_id   uuid, FK → businesses
list_id       uuid, FK → lists (no cascade delete — preserve history)
user_id       uuid, FK → users, nullable
event_type    string
payload       json, nullable
created_at    timestamp  ← no updated_at; this is an append-only log
```

No soft deletes. `event_type` values:
`created`, `renamed`, `archived`, `unarchived`, `visibility_changed`,
`item_added`, `item_removed`, `item_qty_changed`

### 2. Model: `App\Models\ListEvent`

```php
protected $table = 'list_events';
public $timestamps = false;
public $incrementing = false;
protected $keyType = 'string';
// fillable: all columns
// casts(): payload => 'array', created_at => 'datetime'
// relations: list(), user(), business()
```

Use `php artisan make:model ListEvent` then fill in.

### 3. Record Events in ListsController

Add a private helper:

```php
private function recordListEvent(BalloonList $list, string $type, array $payload = []): void
{
    ListEvent::create([
        'business_id' => $list->business_id,
        'list_id'     => $list->id,
        'user_id'     => auth()->id(),
        'event_type'  => $type,
        'payload'     => $payload ?: null,
        'created_at'  => now(),
    ]);
}
```

Call it **after** the DB write succeeds in each method:

| Method | Condition | Event type | Payload |
|--------|-----------|------------|---------|
| `store()` | always | `created` | `['name' => $list->name]` |
| `update()` | name changed | `renamed` | `['old' => $oldName, 'new' => $list->name]` |
| `update()` | `archived_at` toggled on | `archived` | `[]` |
| `update()` | `archived_at` toggled off | `unarchived` | `[]` |
| `update()` | visibility changed | `visibility_changed` | `['old' => $oldVisibility, 'new' => $list->visibility]` |
| `itemsStore()` | always | `item_added` | `['sku_id' => $sku->id, 'sku_name' => $sku->name]` |
| `itemsDestroy()` | always | `item_removed` | `['sku_id' => $item->sku_id, 'sku_name' => $item->sku->name]` |
| `itemsUpdate()` | `planned_quantity` changed | `item_qty_changed` | `['sku_id' => ..., 'sku_name' => ..., 'old_qty' => ..., 'new_qty' => ...]` |

Capture old values **before** calling `$list->update(...)` so you can diff them.

### 4. Pass Events from `listPayload()`

In `listPayload()`, add events **only when called from `show()`** — pass a `$withEvents` bool (default false) and set it to true in `show()`. This avoids loading events on the inventory "By List" tab where they're not shown.

```php
'events' => $withEvents
    ? ListEvent::where('list_id', $list->id)
        ->with('user:id,name')
        ->orderByDesc('created_at')
        ->limit(50)
        ->get()
        ->map(fn ($e) => [
            'id'         => $e->id,
            'type'       => $e->event_type,
            'payload'    => $e->payload ?? [],
            'user_name'  => $e->user?->name ?? 'System',
            'created_at' => $e->created_at,
        ])->all()
    : [],
```

### 5. UI: History Section in `Lists/Show.vue`

Add below `<ListContents>`:

```vue
<div v-if="list.events?.length" class="rounded-lg border border-border bg-surface">
  <div class="border-b border-border px-4 py-3">
    <h2 class="font-sans text-[13px] font-semibold text-ink-secondary uppercase tracking-eyebrow">
      {{ $t('lists.history.heading') }}
    </h2>
  </div>
  <ul class="divide-y divide-border">
    <li v-for="event in list.events" :key="event.id" class="flex items-start gap-3 px-4 py-3">
      <!-- type pill or icon -->
      <!-- human-readable description via $t() -->
      <!-- relative timestamp -->
    </li>
  </ul>
</div>
```

Design pattern: follow how the Scan page (`resources/js/Pages/Scan/Index.vue`) renders its "Recent Scans" list — same card structure, same relative-time approach.

Human-readable descriptions (use i18n):
- `created` → "Alice created this list"
- `renamed` → "Bob renamed from "Old Name" to "New Name""
- `archived` / `unarchived` → "Carol archived this list" / "Carol unarchived this list"
- `visibility_changed` → "Alice changed list type"
- `item_added` → "Alice added Qualatex 11" Red"
- `item_removed` → "Bob removed TufTex 5" Blue"
- `item_qty_changed` → "Carol changed planned qty for Qualatex 11" Red from 5 to 10"

Add keys under `lists.history` in both `lang/en/lists.php` and `lang/es/lists.php`.

### 6. Tests

Add to `tests/Feature/ListsControllerTest.php` (or a new `tests/Feature/ListHistoryTest.php`):

- Creating a list records a `created` event
- Renaming records `renamed` with old/new name in payload
- Archiving records `archived`; unarchiving records `unarchived`
- Adding an item records `item_added` with sku_name in payload
- Removing an item records `item_removed`
- Changing planned_quantity records `item_qty_changed`
- `show()` response includes events in the `list.events` prop
- Events do NOT appear in the `inventoryView()` response

## Notes / Gotchas

- The migration FK on `list_id` should NOT cascade delete — preserve audit history even after a list is deleted.
- `update()` handles name, notes, archived_at, and visibility all in one call. Capture `$list->name`, `$list->archived_at`, and `$list->visibility` **before** `$list->update(...)` to detect what changed.
- `itemsUpdate()` currently only sets `planned_quantity`. Only record `item_qty_changed` if that value actually changed.
- `listPayload()` is also called from `inventoryView()` — don't load events there (performance). The `$withEvents` flag approach above is the cleanest way to handle this.
- `visibility_changed` payload values are the raw strings (`standard`, `owner_editable`, `private`); the UI can map them to human labels.
- The history section should be **hidden entirely** (not just empty) if `list.events` is empty or the array has no items — don't render an empty card.
- Run `php artisan test --compact` on affected test files when done. Ask the user if they want to run the full suite.
- Run `vendor/bin/pint --dirty --format agent` after all PHP changes.
- Deploy: `git push origin main` (triggers `npm run build` pre-push hook) then `ssh myvps "cd /home/balloonventory/balloonventory-app && bash bin/deploy.sh"`.
