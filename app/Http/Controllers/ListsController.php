<?php

namespace App\Http\Controllers;

use App\Models\BalloonList;
use App\Models\Business;
use App\Models\BusinessSkuOverride;
use App\Models\ListItem;
use App\Models\Membership;
use App\Models\Sku;
use App\Models\StockLevel;
use App\Scopes\BusinessScope;
use App\Support\BusinessContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ListsController extends Controller
{
    /**
     * The "Lists & Jobs" hub: every list for the business, Favorites pinned
     * first, each with a SKU count and a small swatch preview for its card.
     */
    public function index(Request $request): Response
    {
        $business = $this->currentBusiness();
        Gate::authorize('list.view', $business);

        $showArchived = $request->boolean('archived');
        $isOwner = $this->currentUserIsOwner($business);

        return Inertia::render('Lists/Index', [
            'lists' => $this->listsSummary(withPreview: true, archived: $showArchived, isOwner: $isOwner),
            'archivedCount' => BalloonList::where('business_id', $business->id)->whereNotNull('archived_at')->count(),
            'showArchived' => $showArchived,
            'can' => [
                'create' => Gate::allows('list.create', $business),
            ],
        ]);
    }

    /**
     * The "By List" tab on the Inventory page: a list switcher plus the active
     * list's contents shown with live stock, so a user can review what they
     * just added without leaving the inventory context. Defaults to Favorites.
     */
    public function inventoryView(Request $request): Response
    {
        $business = $this->currentBusiness();
        Gate::authorize('list.view', $business);

        $isOwner = $this->currentUserIsOwner($business);

        $active = $request->filled('list')
            ? BalloonList::find($request->query('list'))
            : null;

        $active ??= BalloonList::orderByDesc('is_business_favorites')
            ->orderBy('name')
            ->first();

        return Inertia::render('Inventory/Lists', [
            'lists' => $this->listsSummary(isOwner: $isOwner),
            'activeList' => $active ? $this->listPayload($active, $isOwner) : null,
        ]);
    }

    public function show(BalloonList $list): Response
    {
        $business = $this->currentBusiness();
        Gate::authorize('list.view', $business);

        $isOwner = $this->currentUserIsOwner($business);

        // Private lists are invisible to non-owners.
        abort_if(($list->visibility ?? 'standard') === 'private' && ! $isOwner, 403);

        return Inertia::render('Lists/Show', [
            'list' => $this->listPayload($list, $isOwner),
        ]);
    }

    public function create(): Response
    {
        $business = $this->currentBusiness();
        Gate::authorize('list.create', $business);

        return Inertia::render('Lists/Create', [
            'canManageVisibility' => $this->currentUserIsOwner($business),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->currentBusiness();
        Gate::authorize('list.create', $business);

        $isOwner = $this->currentUserIsOwner($business);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'visibility' => ['nullable', 'string', 'in:standard,owner_editable,private'],
        ]);

        $list = BalloonList::create([
            'business_id' => $business->id,
            'name' => $data['name'],
            'notes' => $data['notes'] ?? null,
            'is_business_favorites' => false,
            'created_by_user_id' => $request->user()->id,
            'visibility' => $isOwner ? ($data['visibility'] ?? 'standard') : 'standard',
        ]);

        return redirect()
            ->route('lists.show', $list)
            ->with('success', __('flash.lists.created', ['list' => $list->name]));
    }

    public function edit(BalloonList $list): Response
    {
        Gate::authorize('update', $list);

        $business = $this->currentBusiness();
        $isOwner = $this->currentUserIsOwner($business);

        return Inertia::render('Lists/Edit', [
            'list' => [
                'id' => $list->id,
                'name' => $list->name,
                'notes' => $list->notes,
                'archived' => $list->archived_at !== null,
                'visibility' => $list->visibility ?? 'standard',
            ],
            'canManageVisibility' => $isOwner,
        ]);
    }

    public function update(Request $request, BalloonList $list): RedirectResponse
    {
        Gate::authorize('update', $list);

        $isOwner = $this->currentUserIsOwner($this->currentBusiness());

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'archived' => ['nullable', 'boolean'],
            'visibility' => ['nullable', 'string', 'in:standard,owner_editable,private'],
        ]);

        $updates = [
            'name' => $data['name'],
            'notes' => $data['notes'] ?? null,
        ];

        if ($isOwner) {
            $updates['archived_at'] = ($data['archived'] ?? false) ? now() : null;
            $updates['visibility'] = $data['visibility'] ?? $list->visibility ?? 'standard';
        }

        $list->update($updates);

        return redirect()
            ->route('lists.show', $list)
            ->with('success', __('flash.lists.updated', ['list' => $list->name]));
    }

    public function destroy(BalloonList $list): RedirectResponse
    {
        Gate::authorize('delete', $list);

        $name = $list->name;
        $list->delete();

        return redirect()
            ->route('lists.index')
            ->with('success', __('flash.lists.deleted', ['list' => $name]));
    }

    public function itemsStore(Request $request, BalloonList $list): RedirectResponse
    {
        $this->authorizeListEdit($list);

        $data = $request->validate([
            'sku_id' => ['required', 'uuid'],
        ]);

        // A business may only add SKUs it can see (shared OR its own).
        $sku = Sku::findOrFail($data['sku_id']);
        abort_unless($sku->isVisibleTo(BusinessContext::currentId()), 404);

        ListItem::firstOrCreate(
            ['list_id' => $list->id, 'sku_id' => $sku->id],
            ['planned_quantity' => null, 'sort_order' => 0],
        );

        return back()->with('success', __('flash.lists.item_added', ['list' => $list->name]));
    }

    public function itemsUpdate(Request $request, BalloonList $list, string $item): RedirectResponse
    {
        $this->authorizeListEdit($list);

        $listItem = $list->items()->findOrFail($item);

        $data = $request->validate([
            'planned_quantity' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $listItem->update($data);

        return back();
    }

    public function itemsDestroy(BalloonList $list, string $item): RedirectResponse
    {
        $this->authorizeListEdit($list);

        $listItem = $list->items()->findOrFail($item);
        $listItem->forceDelete();

        return back()->with('success', __('flash.lists.item_removed'));
    }

    /**
     * Authorize editing a list's items: Favorites uses the dedicated
     * `favorites.edit` gate; custom lists use the standard list update policy.
     */
    private function authorizeListEdit(BalloonList $list): void
    {
        if ($list->is_business_favorites) {
            Gate::authorize('favorites.edit', $this->currentBusiness());

            return;
        }

        Gate::authorize('update', $list);
    }

    /**
     * Summary rows for every list (Favorites first). Used by both the hub cards
     * and the Inventory "By List" switcher; `withPreview` adds swatch previews.
     * Non-owners never see private lists. The hub can toggle between active and
     * archived lists via the `archived` flag.
     *
     * @return list<array<string,mixed>>
     */
    private function listsSummary(bool $withPreview = false, bool $archived = false, bool $isOwner = false): array
    {
        $query = BalloonList::query()
            ->withCount('items')
            ->orderByDesc('is_business_favorites')
            ->orderBy('name');

        // Show archived OR active lists — never both at once.
        if ($archived) {
            $query->whereNotNull('archived_at');
        } else {
            $query->whereNull('archived_at');
        }

        // Non-owners cannot see private lists.
        if (! $isOwner) {
            $query->where(fn ($q) => $q
                ->whereNull('visibility')
                ->orWhereIn('visibility', ['standard', 'owner_editable'])
            );
        }

        if ($withPreview) {
            $query->with(['items' => fn ($q) => $q
                ->orderBy('sort_order')
                ->orderBy('created_at')
                ->limit(6)
                ->with(['sku' => fn ($q) => $q->select('id', 'color_id')->with('color:id,color_hex')]),
            ]);
        }

        return $query->get()->map(function (BalloonList $list) use ($withPreview) {
            $row = [
                'id' => $list->id,
                'name' => $list->name,
                'is_business_favorites' => (bool) $list->is_business_favorites,
                'notes' => $list->notes,
                'visibility' => $list->visibility ?? 'standard',
                'archived_at' => $list->archived_at,
                'sku_count' => (int) $list->items_count,
                'updated_at' => $list->updated_at,
            ];

            if ($withPreview) {
                $row['preview_skus'] = $list->items
                    ->map(fn (ListItem $item) => [
                        'id' => $item->sku_id,
                        'hex' => $item->sku?->color?->color_hex ?? '#CBD5E1',
                        'finish' => 'standard',
                    ])
                    ->all();
            }

            return $row;
        })->all();
    }

    /**
     * The full payload for one list: its items with current stock plus the
     * caller's edit/rename/delete abilities. Shared by the hub detail page and
     * the Inventory "By List" tab so the two never drift.
     *
     * @return array<string,mixed>
     */
    private function listPayload(BalloonList $list, bool $isOwner = false): array
    {
        $business = $this->currentBusiness();

        return [
            'id' => $list->id,
            'name' => $list->name,
            'is_business_favorites' => (bool) $list->is_business_favorites,
            'notes' => $list->notes,
            'visibility' => $list->visibility ?? 'standard',
            'archived_at' => $list->archived_at?->toISOString(),
            'items' => $this->itemsWithStock($list),
            'can' => [
                'editItems' => $list->is_business_favorites
                    ? Gate::allows('favorites.edit', $business)
                    : Gate::allows('update', $list),
                'edit' => Gate::allows('update', $list),
                'delete' => Gate::allows('delete', $list),
                'manage_visibility' => $isOwner,
            ],
        ];
    }

    /**
     * A list's items resolved with display name/colour (honoring per-business
     * overrides) and summed on-hand bags across all bins.
     *
     * @return list<array<string,mixed>>
     */
    private function itemsWithStock(BalloonList $list): array
    {
        $list->loadMissing(['items' => fn ($q) => $q
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->with(['sku' => fn ($q) => $q->with([
                'brand:id,abbreviation',
                'balloonSize:id,name',
                'color:id,color_hex',
            ])]),
        ]);

        $skuIds = $list->items->pluck('sku_id');

        if ($skuIds->isEmpty()) {
            return [];
        }

        // Sealed + open on-hand per SKU across all bins (BusinessScope applies).
        $stock = StockLevel::whereIn('sku_id', $skuIds)
            ->selectRaw('sku_id, SUM(full_bags) as full_bags, SUM(open_bags) as open_bags')
            ->groupBy('sku_id')
            ->get()
            ->keyBy('sku_id');

        // Per-business name/colour overrides (BusinessScope applies).
        $overrides = BusinessSkuOverride::whereIn('sku_id', $skuIds)
            ->get(['sku_id', 'custom_name', 'custom_color_hex'])
            ->keyBy('sku_id');

        return $list->items->map(function (ListItem $item) use ($stock, $overrides) {
            $sku = $item->sku;
            $override = $overrides->get($item->sku_id);
            $row = $stock->get($item->sku_id);

            return [
                'id' => $item->id,
                'sku_id' => $item->sku_id,
                'name' => $override?->custom_name ?? $sku?->computed_name ?? $sku?->name ?? '',
                'color_hex' => $override?->custom_color_hex ?? $sku?->color?->color_hex,
                'brand' => $sku?->brand?->abbreviation,
                'size' => $sku?->balloonSize?->name,
                'full_bags' => (int) ($row->full_bags ?? 0),
                'open_bags' => (int) ($row->open_bags ?? 0),
                'planned_quantity' => $item->planned_quantity !== null ? (float) $item->planned_quantity : null,
                'notes' => $item->notes,
            ];
        })->all();
    }

    private function currentBusiness(): Business
    {
        return Business::findOrFail(BusinessContext::currentId());
    }

    private function currentUserIsOwner(Business $business): bool
    {
        return Membership::withoutGlobalScope(BusinessScope::class)
            ->where('user_id', auth()->id())
            ->where('business_id', $business->id)
            ->whereNull('deleted_at')
            ->value('role') === 'owner';
    }
}
