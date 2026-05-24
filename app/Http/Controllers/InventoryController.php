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
use App\Models\Location;
use App\Models\Material;
use App\Models\Shape;
use App\Models\Size;
use App\Models\Sku;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\TextureFamily;
use App\Scopes\BusinessScope;
use App\Support\BusinessContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
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
            $term = $request->search;
            $query->where(fn ($q) => $q
                ->where('skus.name', 'like', "%{$term}%")
                ->orWhere('computed_name', 'like', "%{$term}%")
                ->orWhere('warehouse_sku', 'like', "%{$term}%"));
        }

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

        // Catalog fallback: shared SKUs not in this business's inventory, shown when searching.
        $catalogSkus = collect();
        if ($request->filled('search')) {
            $term = $request->search;
            $catalogSkus = Sku::with([
                'brand',
                'balloonSize' => fn ($q) => $q->with(['size', 'shape']),
                'color',
                'material',
            ])
                ->whereNull('owned_by_business_id')
                ->whereNotIn('id', StockLevel::select('sku_id'))
                ->where(fn ($q) => $q
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('computed_name', 'like', "%{$term}%")
                    ->orWhere('warehouse_sku', 'like', "%{$term}%"))
                ->limit(20)
                ->get();
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
        ]);
    }

    public function show(Sku $sku): Response
    {
        abort_unless(StockLevel::where('sku_id', $sku->id)->exists(), 404);

        $sku->load([
            'brand',
            'balloonSize' => fn ($q) => $q->with(['size', 'shape']),
            'color' => fn ($q) => $q->with(['colorFamily', 'texture']),
            'material',
            'packagingType',
        ]);

        $override = BusinessSkuOverride::where('sku_id', $sku->id)->first();

        $stockLevels = StockLevel::where('sku_id', $sku->id)
            ->with('bin.location')
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

        return Inertia::render('Inventory/Show', [
            'sku' => $sku,
            'override' => $override,
            'stockLevels' => $stockLevels,
            'recentMovements' => $recentMovements,
            'favoritesListId' => $favoritesListId,
            'isFavorite' => $isFavorite,
            'reorderQuantity' => $reorderQuantity,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sku_id' => ['required', 'uuid', 'exists:skus,id'],
        ]);

        $businessId = BusinessContext::currentId();
        $business = Business::findOrFail($businessId);
        $bin = $business->defaultBin();

        if ($bin === null) {
            $location = $business->defaultLocation();

            if ($location === null) {
                $location = Location::withoutGlobalScope(BusinessScope::class)->create([
                    'business_id' => $businessId,
                    'name' => 'Default',
                    'is_default' => true,
                ]);
            }

            $bin = Bin::withoutGlobalScope(BusinessScope::class)->create([
                'business_id' => $businessId,
                'location_id' => $location->id,
                'name' => 'Default',
                'is_default' => true,
            ]);
        }

        StockLevel::firstOrCreate(
            [
                'business_id' => $businessId,
                'sku_id' => $data['sku_id'],
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
