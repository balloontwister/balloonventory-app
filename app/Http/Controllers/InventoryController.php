<?php

namespace App\Http\Controllers;

use App\Enums\StockDirection;
use App\Models\BalloonList;
use App\Models\Bin;
use App\Models\Brand;
use App\Models\Business;
use App\Models\BusinessSkuOverride;
use App\Models\ColorFamily;
use App\Models\ListItem;
use App\Models\Material;
use App\Models\Shape;
use App\Models\Size;
use App\Models\Sku;
use App\Models\SkuFeedback;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\TextureFamily;
use App\Scopes\BusinessScope;
use App\Services\BinResolver;
use App\Services\ImageAttachmentService;
use App\Support\BusinessContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    /**
     * Attributes a user may flag in an item-feedback report. Mirrors the fields
     * shown on the SKU detail card, plus image/other catch-alls. Used to
     * constrain the `field` value submitted to {@see self::submitFeedback()}.
     *
     * @var list<string>
     */
    public const FEEDBACK_FIELDS = [
        'name',
        'brand',
        'size',
        'shape',
        'color',
        'texture',
        'material',
        'count_per_bag',
        'packaging',
        'barcode',
        'image',
        'other',
    ];

    public function __construct(
        private readonly BinResolver $binResolver,
        private readonly ImageAttachmentService $images,
    ) {}

    public function index(Request $request): Response
    {
        $request->validate([
            'brand' => ['nullable', 'uuid'],
            'size' => ['nullable', 'uuid'],
            'shape' => ['nullable', 'uuid'],
            'texture_family' => ['nullable', 'uuid'],
            'color_family' => ['nullable', 'uuid'],
            'material' => ['nullable', 'uuid'],
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'in:recent,name,color_family,shape,size'],
        ]);

        // StockLevel uses BelongsToBusiness, so this subquery is already scoped to the current business.
        $inventorySubquery = StockLevel::select('sku_id');

        $query = Sku::with([
            'brand',
            'balloonSize' => fn ($q) => $q->with(['size', 'shape']),
            'color' => fn ($q) => $q->with('colorFamily'),
            'material',
        ])
            ->withSum('stockLevels as full_bags_total', 'full_bags')
            ->withSum('stockLevels as open_bags_total', 'open_bags')
            ->withMax('stockLevels as last_movement_at', 'last_movement_at')
            ->whereIn('skus.id', $inventorySubquery);

        $this->applyCatalogFilters($query, $request);

        $sort = $request->input('sort', 'recent');
        if ($sort === 'name') {
            $query->orderBy('skus.name');
        } elseif ($sort === 'color_family') {
            $query->orderByRaw('(SELECT cf.sort_order FROM color_families cf JOIN colors c ON c.color_family_id = cf.id WHERE c.id = skus.color_id LIMIT 1)');
        } elseif ($sort === 'shape') {
            $query->orderByRaw('(SELECT sh.sort_order FROM shapes sh JOIN balloon_sizes bs ON bs.shape_id = sh.id WHERE bs.id = skus.balloon_size_id LIMIT 1)');
        } elseif ($sort === 'size') {
            $query->orderByRaw('(SELECT si.sort_order FROM sizes si JOIN balloon_sizes bs ON bs.size_id = si.id WHERE bs.id = skus.balloon_size_id LIMIT 1)');
        } else {
            $query->orderByDesc('last_movement_at');
        }

        $skus = $query->paginate(50)->withQueryString();

        $pageSkuIds = $skus->pluck('id');

        if ($pageSkuIds->isNotEmpty()) {
            $listItems = ListItem::whereIn('sku_id', $pageSkuIds)
                ->whereHas('list') // BalloonList global scope scopes to current business
                ->with('list:id,name,is_business_favorites')
                ->get();

            $listsBySkuId = $listItems->groupBy('sku_id')->map(
                fn ($items) => $items->map(fn ($item) => [
                    'id' => $item->list->id,
                    'name' => $item->list->name,
                    'is_favorites' => (bool) $item->list->is_business_favorites,
                ])->values()
            );

            $skus->getCollection()->each(function (Sku $sku) use ($listsBySkuId) {
                $sku->lists = $listsBySkuId->get($sku->id, collect())->values()->all();
            });
        } else {
            $skus->getCollection()->each(fn (Sku $sku) => $sku->lists = []);
        }

        // Catalog fallback: shared SKUs not already in this business's inventory,
        // shown whenever any search or filter is active so the master catalog can
        // be browsed/added from the same view. Honors the SAME filters as the
        // inventory listing above (via applyCatalogFilters) — selecting e.g.
        // Brand + Size + Color Family narrows the catalog results too.
        $catalogSkus = collect();
        if ($this->hasActiveFilters($request)) {
            $catalogQuery = Sku::with([
                'brand',
                'balloonSize' => fn ($q) => $q->with(['size', 'shape']),
                'color',
                'material',
            ])
                ->whereNull('owned_by_business_id')
                ->whereNotIn('id', StockLevel::select('sku_id'));

            $this->applyCatalogFilters($catalogQuery, $request);

            $catalogSkus = $catalogQuery->limit(20)->get();
        }

        return Inertia::render('Inventory/Index', [
            'skus' => $skus,
            'catalogSkus' => $catalogSkus->values(),
            'filters' => $request->only(['brand', 'size', 'shape', 'texture_family', 'color_family', 'material', 'search', 'sort']),
            'brands' => Brand::orderBy('sort_order')->get(['id', 'name', 'abbreviation']),
            'sizes' => Size::orderBy('sort_order')->get(['id', 'name']),
            'shapes' => Shape::orderBy('sort_order')->get(['id', 'name']),
            'textureFamilies' => TextureFamily::orderBy('sort_order')->get(['id', 'name']),
            'colorFamilies' => ColorFamily::orderBy('sort_order')->get(['id', 'name']),
            'materials' => Material::orderBy('sort_order')->get(['id', 'name']),
            'lists' => BalloonList::get(['id', 'name', 'is_business_favorites']),
            'favoritesListId' => BalloonList::where('is_business_favorites', true)->value('id'),
            'hasSampleStock' => StockLevel::where('is_sample', true)->exists(),
        ]);
    }

    /**
     * Whether the request carries any search term or filter — used to decide
     * whether the master-catalog fallback should be queried.
     */
    private function hasActiveFilters(Request $request): bool
    {
        return $request->filled('search')
            || $request->filled('brand')
            || $request->filled('size')
            || $request->filled('shape')
            || $request->filled('texture_family')
            || $request->filled('color_family')
            || $request->filled('material');
    }

    /**
     * Apply the shared catalog filters (brand, size, shape, texture family,
     * color family, material, and free-text search) to a SKU query. Used by BOTH
     * the in-inventory listing and the master-catalog fallback so the two never
     * drift. Free-text search matches the SKU name/computed name/warehouse SKU as
     * well as the related color and brand names, so typing "Red" or "Kalisan"
     * works without needing the matching dropdown.
     */
    private function applyCatalogFilters(Builder $query, Request $request): void
    {
        if ($request->filled('brand')) {
            $query->where('brand_id', $request->brand);
        }

        if ($request->filled('size')) {
            $query->whereHas('balloonSize', fn ($q) => $q->where('size_id', $request->size));
        }

        if ($request->filled('shape')) {
            $query->whereHas('balloonSize', fn ($q) => $q->where('shape_id', $request->shape));
        }

        if ($request->filled('texture_family')) {
            $query->whereHas('color.texture', fn ($q) => $q->where('texture_family_id', $request->texture_family));
        }

        if ($request->filled('color_family')) {
            $query->whereHas('color', fn ($q) => $q->where('color_family_id', $request->color_family));
        }

        if ($request->filled('material')) {
            $query->where('material_id', $request->material);
        }

        if ($request->filled('search')) {
            // Shared tokenized search (name/brand/size/shape/color/texture/SKU).
            $query->matchesSearch($request->search);
        }
    }

    public function show(Request $request, Sku $sku): Response
    {
        // Visible SKUs (shared OR owned by this business) can be viewed even when
        // not yet stocked — e.g. a catalog item added to a list. The page shows
        // an "Add to inventory" CTA in that case (see $inInventory below).
        abort_unless($sku->isVisibleTo(BusinessContext::currentId()), 404);

        $inInventory = StockLevel::where('sku_id', $sku->id)->exists();

        $sku->load([
            'brand',
            'balloonSize' => fn ($q) => $q->with(['size', 'shape']),
            'color' => fn ($q) => $q->with(['colorFamily', 'texture']),
            'material',
            'packagingType',
        ]);

        // Resolve display images the same way the master catalog does. Most latex
        // SKUs carry no image of their own, so fall back to the color's image.
        $images = $this->images->urls($sku);
        if (empty($images['single']) && empty($images['cluster']) && $sku->color) {
            $images = $this->images->urls($sku->color);
        }
        $sku->images = $images;

        $override = BusinessSkuOverride::where('sku_id', $sku->id)->first();

        $stockLevels = StockLevel::where('sku_id', $sku->id)
            ->with('bin.location')
            ->get();

        // Identical SKUs (packaging/size variants) that are ALSO in this
        // business's inventory — surfaced as quick links with their on-hand bags.
        $identicalSkus = $sku->identicalSkus()
            ->whereIn('skus.id', StockLevel::select('sku_id'))
            ->with([
                'brand:id,name,abbreviation',
                'balloonSize' => fn ($q) => $q->with('size:id,name'),
                'color:id,name,color_hex',
            ])
            ->withSum('stockLevels as full_bags_total', 'full_bags')
            ->withSum('stockLevels as open_bags_total', 'open_bags')
            ->get();

        $recentMovements = StockMovement::where('sku_id', $sku->id)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $favoritesListId = BalloonList::where('is_business_favorites', true)->value('id');

        $isFavorite = false;
        $reorderQuantity = null;

        if ($favoritesListId) {
            $favItem = ListItem::where('list_id', $favoritesListId)
                ->where('sku_id', $sku->id)
                ->first(['planned_quantity']);

            $isFavorite = $favItem !== null;
            $reorderQuantity = $favItem?->planned_quantity;
        }

        // Every list this SKU appears on, for this business — Favorites first.
        // Favorites gets a chip too (membership), distinct from the header star
        // (which toggles membership). The BalloonList global scope keeps the
        // whereHas tenant-scoped.
        $onLists = ListItem::where('sku_id', $sku->id)
            ->whereHas('list')
            ->with('list:id,name,is_business_favorites')
            ->get()
            ->sortByDesc(fn (ListItem $item) => $item->list->is_business_favorites)
            ->map(fn (ListItem $item) => ['id' => $item->list->id, 'name' => $item->list->name])
            ->values();

        return Inertia::render('Inventory/Show', [
            'sku' => $sku,
            'override' => $override,
            'stockLevels' => $stockLevels,
            'identicalSkus' => $identicalSkus,
            'bins' => $this->binsForSelector(),
            'recentMovements' => $recentMovements,
            'favoritesListId' => $favoritesListId,
            'isFavorite' => $isFavorite,
            'reorderQuantity' => $reorderQuantity,
            'onLists' => $onLists,
            'inInventory' => $inInventory,
            'returnQuery' => $request->query('return', ''),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sku_id' => ['required', 'uuid'],
        ]);

        $businessId = BusinessContext::currentId();

        // Resolve under the catalog visibility rule (shared OR owned by this
        // business). MUST NOT use `exists:skus,id`, which would let a foreign
        // business's private SKU — or a soft-deleted one — into inventory.
        $sku = Sku::visibleTo($businessId)->find($data['sku_id']);

        if ($sku === null) {
            throw ValidationException::withMessages([
                'sku_id' => 'That SKU is not available for this business.',
            ]);
        }

        $business = Business::findOrFail($businessId);
        $bin = $this->binResolver->resolveDefault($business);

        StockLevel::firstOrCreate(
            [
                'business_id' => $businessId,
                'sku_id' => $sku->id,
                'bin_id' => $bin->id,
            ],
            ['full_bags' => 0, 'open_bags' => 0]
        );

        return back()->with('success', __('flash.inventory.sku_added'));
    }

    public function destroy(Request $request, Sku $sku): RedirectResponse
    {
        $businessId = BusinessContext::currentId();

        $levels = StockLevel::where('sku_id', $sku->id)->get();

        abort_if($levels->isEmpty(), 404);

        $levels->each(fn (StockLevel $l) => $l->delete());

        StockMovement::create([
            'business_id' => $businessId,
            'sku_id' => $sku->id,
            'user_id' => $request->user()->id,
            'direction' => StockDirection::Removed,
            'full_bags_change' => 0,
            'open_bags_change' => 0,
        ]);

        return redirect()->route('inventory.index')
            ->with('success', __('flash.inventory.sku_removed'));
    }

    /**
     * Move stock from one bin to another. Recorded as two paired `adjusted`
     * movements sharing a transfer_id — the source leg carries negative bag
     * changes, the destination leg positive — so the two halves can be shown
     * and reversed together.
     */
    public function transfer(Request $request, Sku $sku): RedirectResponse
    {
        abort_unless(StockLevel::where('sku_id', $sku->id)->exists(), 404);

        $businessId = BusinessContext::currentId();

        $binExistsForBusiness = Rule::exists('bins', 'id')
            ->where('business_id', $businessId)
            ->whereNull('deleted_at');

        $data = $request->validate([
            'from_bin_id' => ['required', 'uuid', $binExistsForBusiness],
            'to_bin_id' => ['required', 'uuid', 'different:from_bin_id', $binExistsForBusiness],
            'full_bags_change' => ['integer', 'min:0'],
            'open_bags_change' => ['integer', 'min:0'],
        ]);

        $fullBagsChange = (int) ($data['full_bags_change'] ?? 0);
        $openBagsChange = (int) ($data['open_bags_change'] ?? 0);

        if ($fullBagsChange === 0 && $openBagsChange === 0) {
            throw ValidationException::withMessages([
                'full_bags_change' => __('flash.inventory.transfer_nothing'),
            ]);
        }

        $business = Business::findOrFail($businessId);
        $fromBin = $this->binResolver->resolveSelectedBin($business, $data['from_bin_id']);
        $toBin = $this->binResolver->resolveSelectedBin($business, $data['to_bin_id']);

        DB::transaction(function () use (
            $businessId, $sku, $fromBin, $toBin, $fullBagsChange, $openBagsChange, $request
        ) {
            $source = StockLevel::where('business_id', $businessId)
                ->where('sku_id', $sku->id)
                ->where('bin_id', $fromBin->id)
                ->lockForUpdate()
                ->first();

            if ($source === null
                || $source->full_bags < $fullBagsChange
                || $source->open_bags < $openBagsChange) {
                throw ValidationException::withMessages([
                    'full_bags_change' => __('flash.inventory.transfer_insufficient', [
                        'full' => $source->full_bags ?? 0,
                        'open' => $source->open_bags ?? 0,
                    ]),
                ]);
            }

            $destination = StockLevel::where('business_id', $businessId)
                ->where('sku_id', $sku->id)
                ->where('bin_id', $toBin->id)
                ->lockForUpdate()
                ->first()
                ?? StockLevel::create([
                    'business_id' => $businessId,
                    'sku_id' => $sku->id,
                    'bin_id' => $toBin->id,
                    'full_bags' => 0,
                    'open_bags' => 0,
                ]);

            $source->decrement('full_bags', $fullBagsChange);
            $source->decrement('open_bags', $openBagsChange);
            $source->update(['last_movement_at' => now()]);

            $destination->increment('full_bags', $fullBagsChange);
            $destination->increment('open_bags', $openBagsChange);
            $destination->update(['last_movement_at' => now()]);

            $transferId = (string) Str::uuid7();
            $userId = $request->user()->id;

            StockMovement::create([
                'business_id' => $businessId,
                'sku_id' => $sku->id,
                'bin_id' => $fromBin->id,
                'transfer_id' => $transferId,
                'user_id' => $userId,
                'direction' => StockDirection::Adjusted,
                'full_bags_change' => -$fullBagsChange,
                'open_bags_change' => -$openBagsChange,
                'notes' => 'Transfer to '.$toBin->name,
            ]);

            StockMovement::create([
                'business_id' => $businessId,
                'sku_id' => $sku->id,
                'bin_id' => $toBin->id,
                'transfer_id' => $transferId,
                'user_id' => $userId,
                'direction' => StockDirection::Adjusted,
                'full_bags_change' => $fullBagsChange,
                'open_bags_change' => $openBagsChange,
                'notes' => 'Transfer from '.$fromBin->name,
            ]);
        });

        return back()->with('success', __('flash.inventory.transfer_done'));
    }

    /**
     * Set the on-hand bag counts for a single bin to the supplied values,
     * recording the net change as one `adjusted` StockMovement. Backs the per-bin
     * steppers on the SKU detail page (manual recount/correction) and seeding
     * stock into a bin that didn't previously hold this SKU. The non-sample
     * movement it writes also promotes any onboarding sample stock for this SKU to
     * real on the next sample cleanup.
     */
    public function adjust(Request $request, Sku $sku): RedirectResponse
    {
        abort_unless(StockLevel::where('sku_id', $sku->id)->exists(), 404);

        $businessId = BusinessContext::currentId();

        $binExistsForBusiness = Rule::exists('bins', 'id')
            ->where('business_id', $businessId)
            ->whereNull('deleted_at');

        $data = $request->validate([
            'bin_id' => ['required', 'uuid', $binExistsForBusiness],
            'full_bags' => ['required', 'integer', 'min:0', 'max:1000000'],
            'open_bags' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);

        $business = Business::findOrFail($businessId);
        $bin = $this->binResolver->resolveSelectedBin($business, $data['bin_id']);

        $targetFull = (int) $data['full_bags'];
        $targetOpen = (int) $data['open_bags'];

        DB::transaction(function () use ($businessId, $sku, $bin, $targetFull, $targetOpen, $request) {
            $level = StockLevel::where('business_id', $businessId)
                ->where('sku_id', $sku->id)
                ->where('bin_id', $bin->id)
                ->lockForUpdate()
                ->first()
                ?? StockLevel::create([
                    'business_id' => $businessId,
                    'sku_id' => $sku->id,
                    'bin_id' => $bin->id,
                    'full_bags' => 0,
                    'open_bags' => 0,
                ]);

            $fullChange = $targetFull - $level->full_bags;
            $openChange = $targetOpen - $level->open_bags;

            // No net change — nothing to record.
            if ($fullChange === 0 && $openChange === 0) {
                return;
            }

            $level->update([
                'full_bags' => $targetFull,
                'open_bags' => $targetOpen,
                'last_movement_at' => now(),
            ]);

            StockMovement::create([
                'business_id' => $businessId,
                'sku_id' => $sku->id,
                'bin_id' => $bin->id,
                'user_id' => $request->user()->id,
                'direction' => StockDirection::Adjusted,
                'full_bags_change' => $fullChange,
                'open_bags_change' => $openChange,
            ]);
        });

        return back()->with('success', __('flash.inventory.stock_adjusted'));
    }

    /**
     * Dismiss an EMPTY stock-level row for this SKU in a specific bin, so bins
     * the user no longer uses for this item stop cluttering the detail page. Only
     * a row with zero full and zero open bags can be removed. Removing the last
     * remaining bin drops the SKU from inventory entirely (same effect as
     * "Remove from inventory").
     */
    public function removeStockBin(Sku $sku, Bin $bin): RedirectResponse
    {
        $businessId = BusinessContext::currentId();

        $level = StockLevel::where('business_id', $businessId)
            ->where('sku_id', $sku->id)
            ->where('bin_id', $bin->id)
            ->first();

        abort_if($level === null, 404);

        if ($level->full_bags > 0 || $level->open_bags > 0) {
            throw ValidationException::withMessages([
                'bin_id' => __('flash.inventory.bin_not_empty'),
            ]);
        }

        $level->delete();

        if (! StockLevel::where('sku_id', $sku->id)->exists()) {
            return redirect()->route('inventory.index')
                ->with('success', __('flash.inventory.sku_removed'));
        }

        return back()->with('success', __('flash.inventory.bin_removed'));
    }

    /**
     * The business's bins for transfer destination pickers, Default first.
     *
     * @return array<int,array<string,mixed>>
     */
    private function binsForSelector(): array
    {
        return Bin::with('location:id,name')
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Bin $bin) => [
                'id' => $bin->id,
                'name' => $bin->name,
                'number' => $bin->number,
                'location_name' => $bin->location?->name,
            ])
            ->all();
    }

    public function updateOverride(Request $request, Sku $sku): RedirectResponse
    {
        abort_unless(StockLevel::where('sku_id', $sku->id)->exists(), 404);

        $data = $request->validate([
            'custom_name' => ['nullable', 'string', 'max:255'],
            'custom_color_hex' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $businessId = BusinessContext::currentId();

        BusinessSkuOverride::withoutGlobalScope(BusinessScope::class)->updateOrCreate(
            ['business_id' => $businessId, 'sku_id' => $sku->id],
            $data
        );

        return back()->with('success', __('flash.inventory.override_saved'));
    }

    /**
     * Record a user's "feedback on this item" report — a field-targeted edit or
     * error report flagging a discrepancy between the physical product and our
     * catalog data. Written to the shared (non-tenant) feedback log for admin
     * review; the report only describes the problem and never mutates the catalog
     * itself. A snapshot of the product name and the reporter's business/user are
     * stored for context.
     */
    public function submitFeedback(Request $request, Sku $sku): RedirectResponse
    {
        abort_unless(StockLevel::where('sku_id', $sku->id)->exists(), 404);

        $data = $request->validate([
            'field' => ['required', Rule::in(self::FEEDBACK_FIELDS)],
            'current_value' => ['nullable', 'string', 'max:255'],
            // A report must carry content: the corrected value, a note, or both.
            'suggested_value' => ['nullable', 'required_without:note', 'string', 'max:255'],
            'note' => ['nullable', 'required_without:suggested_value', 'string', 'max:2000'],
        ]);

        SkuFeedback::create([
            'business_id' => BusinessContext::currentId(),
            'user_id' => $request->user()->id,
            'sku_id' => $sku->id,
            'sku_name' => $sku->name,
            'field' => $data['field'],
            'current_value' => $data['current_value'] ?? null,
            'suggested_value' => $data['suggested_value'] ?? null,
            'note' => $data['note'] ?? null,
        ]);

        return back()->with('success', __('flash.inventory.feedback_submitted'));
    }

    public function addToList(Request $request, Sku $sku): RedirectResponse
    {
        abort_unless(StockLevel::where('sku_id', $sku->id)->exists(), 404);

        $data = $request->validate([
            'list_id' => ['required', 'uuid'],
        ]);

        // BalloonList global scope ensures the list belongs to the current business.
        $list = BalloonList::findOrFail($data['list_id']);

        ListItem::firstOrCreate(
            ['list_id' => $list->id, 'sku_id' => $sku->id],
            ['planned_quantity' => null, 'sort_order' => 0]
        );

        return back()->with('success', __('flash.inventory.added_to_list', ['list' => $list->name]));
    }

    public function addFavorite(Sku $sku): RedirectResponse
    {
        // A business may only favorite SKUs it can see (shared OR its own).
        abort_unless($sku->isVisibleTo(BusinessContext::currentId()), 404);

        $list = BalloonList::where('is_business_favorites', true)->firstOrFail();

        ListItem::firstOrCreate(
            ['list_id' => $list->id, 'sku_id' => $sku->id],
            ['planned_quantity' => null, 'sort_order' => 0]
        );

        return back();
    }

    public function removeFavorite(Sku $sku): RedirectResponse
    {
        $list = BalloonList::where('is_business_favorites', true)->firstOrFail();

        ListItem::where('list_id', $list->id)
            ->where('sku_id', $sku->id)
            ->forceDelete();

        return back();
    }
}
