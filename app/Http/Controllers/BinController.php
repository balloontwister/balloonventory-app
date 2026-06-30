<?php

namespace App\Http\Controllers;

use App\Models\Bin;
use App\Models\Business;
use App\Models\Location;
use App\Models\Sku;
use App\Models\StockLevel;
use App\Scopes\BusinessScope;
use App\Services\BinResolver;
use App\Support\BusinessContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BinController extends Controller
{
    public function __construct(private readonly BinResolver $binResolver) {}

    /**
     * The "By Bin" wall: locations, each with their bins and a lightweight
     * stock summary. Bin contents are loaded lazily per-card via contents().
     */
    public function index(): Response
    {
        // Businesses created before this feature (or that have only ever used
        // the catalog) may have no Default location/bin yet — they were seeded
        // lazily on first scan. Ensure one exists so the wall is never empty
        // and "Add bin" always has a location to attach to.
        $this->binResolver->resolveDefault(Business::findOrFail(BusinessContext::currentId()));

        $locations = Location::query()
            ->with(['bins' => function ($query) {
                $query
                    ->orderedForDisplay()
                    ->withSum('stockLevels as full_bags_total', 'full_bags')
                    ->withSum('stockLevels as open_bags_total', 'open_bags')
                    ->withCount(['stockLevels as sku_count' => fn (Builder $q) => $q
                        ->where(fn (Builder $inner) => $inner
                            ->where('full_bags', '>', 0)
                            ->orWhere('open_bags', '>', 0)),
                    ]);
            }])
            ->orderedForDisplay()
            ->get();

        return Inertia::render('Inventory/Bins', [
            'locations' => $locations,
        ]);
    }

    /**
     * The "Manage storage" view: a condensed, structure-only list of locations
     * and their bins (no contents/stock) for the occasional setup actions —
     * add/edit/delete locations & bins, print labels, and auto-number. Kept off
     * the daily "By Bin" wall so that view stays focused on contents.
     */
    public function manage(): Response
    {
        $business = Business::findOrFail(BusinessContext::currentId());
        $this->binResolver->resolveDefault($business);

        $locations = Location::query()
            ->with(['bins' => fn ($query) => $query->orderedForDisplay()])
            ->orderedForDisplay()
            ->get(['id', 'name', 'description', 'is_default', 'sort_order', 'position_locked']);

        return Inertia::render('Inventory/ManageStorage', [
            'locations' => $locations,
        ]);
    }

    /**
     * Assign bin numbers automatically. Two modes:
     *   - fill: only number bins that have none yet; existing numbers untouched.
     *   - renumber: reassign sequential numbers to every bin, EXCEPT bins whose
     *     number is locked — those keep their number, and it's reserved so no
     *     other bin reuses it.
     * Numbering is continuous business-wide, ordered location → bin sort order,
     * and written in one transaction (bin.number has no DB unique index, so the
     * rewrite is safe; uniqueness is maintained by the reserved-set bookkeeping).
     */
    public function autoNumber(Request $request): RedirectResponse
    {
        $business = Business::findOrFail(BusinessContext::currentId());
        Gate::authorize('inventory.manual_adjust', $business);

        $data = $request->validate([
            'mode' => ['required', 'in:fill,renumber'],
        ]);

        $fill = $data['mode'] === 'fill';

        // Ordered the same way the views present bins, so numbers track layout.
        $orderedBins = Location::query()
            ->with(['bins' => fn ($q) => $q->orderedForDisplay()])
            ->orderedForDisplay()
            ->get()
            ->flatMap->bins;

        // Numbers that must be preserved: locked bins always; in fill mode, every
        // already-numbered bin too.
        $used = [];
        foreach ($orderedBins as $bin) {
            $keep = $bin->number !== null && ($bin->number_locked || $fill);
            if ($keep) {
                $used[$bin->number] = true;
            }
        }

        $assignments = [];
        $next = 1;
        foreach ($orderedBins as $bin) {
            // Skip bins whose number is kept as-is.
            if ($bin->number_locked && $bin->number !== null) {
                continue;
            }
            if ($fill && $bin->number !== null) {
                continue;
            }

            while (! empty($used[$next])) {
                $next++;
            }
            $assignments[$bin->id] = $next;
            $used[$next] = true;
        }

        if ($assignments !== []) {
            DB::transaction(function () use ($assignments) {
                foreach ($assignments as $id => $number) {
                    Bin::where('id', $id)->update(['number' => $number]);
                }
            });
        }

        return back()->with('success', __('bins.flash.auto_numbered', [
            'count' => count($assignments),
        ]));
    }

    /**
     * Persist a new visual order for one location's bins (drag-reorder on Manage
     * storage). The bins are sent in their new order; sort_order is written by
     * index. Only ids that belong to the given location are honored. Position
     * locks are enforced on the client (locked bins can't be dragged or crossed),
     * so the submitted order already keeps them in place.
     */
    public function reorder(Request $request): RedirectResponse
    {
        $businessId = BusinessContext::currentId();
        Gate::authorize('inventory.manual_adjust', Business::findOrFail($businessId));

        $data = $request->validate([
            'location_id' => [
                'required',
                'uuid',
                Rule::exists('locations', 'id')
                    ->where('business_id', $businessId)
                    ->whereNull('deleted_at'),
            ],
            'bin_ids' => ['required', 'array'],
            'bin_ids.*' => ['uuid'],
        ]);

        // Restrict to bins that actually live in this location (the global
        // BusinessScope already restricts to this business).
        $validIds = Bin::where('location_id', $data['location_id'])
            ->whereIn('id', $data['bin_ids'])
            ->pluck('id')
            ->all();

        $ordered = array_values(array_filter(
            $data['bin_ids'],
            fn ($id) => in_array($id, $validIds, true),
        ));

        DB::transaction(function () use ($ordered) {
            foreach ($ordered as $index => $binId) {
                Bin::where('id', $binId)->update(['sort_order' => $index]);
            }
        });

        return back()->with('success', __('bins.flash.reordered'));
    }

    /**
     * Lazy-loaded contents for a single bin — the SKUs it holds with their
     * per-bin bag counts. Implicit binding + the global BusinessScope keep this
     * scoped to the current business (404 for foreign bins).
     */
    public function contents(Bin $bin): JsonResponse
    {
        $levels = StockLevel::where('bin_id', $bin->id)
            ->where(fn (Builder $q) => $q->where('full_bags', '>', 0)->orWhere('open_bags', '>', 0))
            ->with([
                'sku' => fn ($q) => $q->with([
                    'brand:id,abbreviation',
                    'balloonSize:id,name',
                    'color:id,color_hex',
                ]),
            ])
            ->get();

        $items = $levels->map(fn (StockLevel $level) => $this->stockItemPayload($level))->values();

        return response()->json(['items' => $items]);
    }

    /**
     * Bulk bin contents for the wall's "show all / show this location" toggles —
     * the SKUs every requested bin holds, keyed by bin id, in ONE query instead
     * of N per-card fetches. Optionally narrowed to a single location. The
     * BelongsToBusiness scope on StockLevel keeps it tenant-scoped, so an id from
     * another business simply returns no rows.
     */
    public function bulkContents(Request $request): JsonResponse
    {
        Gate::authorize('inventory.view_counts', Business::findOrFail(BusinessContext::currentId()));

        $data = $request->validate([
            'location' => ['nullable', 'uuid'],
        ]);

        $query = StockLevel::where(fn (Builder $q) => $q->where('full_bags', '>', 0)->orWhere('open_bags', '>', 0))
            ->with([
                'sku' => fn ($q) => $q->with([
                    'brand:id,abbreviation',
                    'balloonSize:id,name',
                    'color:id,color_hex',
                ]),
            ]);

        if (! empty($data['location'])) {
            $query->whereHas('bin', fn (Builder $q) => $q->where('location_id', $data['location']));
        }

        $contents = $query->get()
            ->groupBy('bin_id')
            ->map(fn ($levels) => $levels
                ->map(fn (StockLevel $level) => $this->stockItemPayload($level))
                ->values());

        return response()->json(['contents' => $contents]);
    }

    /**
     * The bin detail page: the bin's identity plus the SKUs it currently holds,
     * each with its per-bin bag counts. Backs the per-item adjust / move and the
     * "add item to this bin" flows. Implicit binding + the global BusinessScope
     * keep this scoped to the current business (404 for foreign bins).
     */
    public function show(Request $request, Bin $bin): Response
    {
        Gate::authorize('inventory.view_counts', Business::findOrFail(BusinessContext::currentId()));

        $bin->load('location:id,name');

        $levels = StockLevel::where('bin_id', $bin->id)
            ->where(fn (Builder $q) => $q->where('full_bags', '>', 0)->orWhere('open_bags', '>', 0))
            ->with([
                'sku' => fn ($q) => $q->with([
                    'brand:id,abbreviation',
                    'balloonSize:id,name',
                    'color:id,color_hex',
                ]),
            ])
            ->get();

        $items = $levels->map(fn (StockLevel $level) => $this->stockItemPayload($level))->values();

        return Inertia::render('Inventory/BinShow', [
            'bin' => [
                'id' => $bin->id,
                'name' => $bin->name,
                'number' => $bin->number,
                'description' => $bin->description,
                'scan_code' => $bin->scan_code,
                'is_default' => $bin->is_default,
                'number_locked' => $bin->number_locked,
                'position_locked' => $bin->position_locked,
                'location_id' => $bin->location_id,
                'location_name' => $bin->location?->name,
            ],
            'items' => $items,
            'bins' => $this->binsForSelector(),
            // Locations for the "edit bin" form's location picker.
            'locations' => Location::orderedForDisplay()->get(['id', 'name']),
            'fullBagsTotal' => (int) $levels->sum('full_bags'),
            'openBagsTotal' => (int) $levels->sum('open_bags'),
            // Where the user arrived from, so the back link can return there
            // (e.g. Manage storage) instead of always the By-Bin wall.
            'from' => $request->query('from', ''),
        ]);
    }

    /**
     * Typeahead for the "add item to this bin" picker. Searches the catalog
     * visible to the current business (shared OR owned), flagging SKUs already
     * stocked in this bin so the picker can disable them.
     */
    public function searchItems(Request $request, Bin $bin): JsonResponse
    {
        Gate::authorize('inventory.view_counts', Business::findOrFail(BusinessContext::currentId()));

        $data = $request->validate([
            'q' => ['required', 'string', 'max:255'],
        ]);

        $inBinSkuIds = StockLevel::where('bin_id', $bin->id)->pluck('sku_id')->all();

        $skus = Sku::visibleTo(BusinessContext::currentId())
            ->matchesSearch($data['q'])
            ->with([
                'brand:id,abbreviation',
                'balloonSize:id,name',
                'color:id,color_hex',
            ])
            ->limit(20)
            ->get();

        $items = $skus->map(fn (Sku $sku) => [
            'sku_id' => $sku->id,
            'name' => $sku->computed_name ?? $sku->name,
            'brand' => $sku->brand?->abbreviation,
            'size' => $sku->balloonSize?->name,
            'color_hex' => $sku->color?->color_hex,
            'in_bin' => in_array($sku->id, $inBinSkuIds, true),
        ])->values();

        return response()->json(['items' => $items]);
    }

    public function store(Request $request): RedirectResponse
    {
        $businessId = BusinessContext::currentId();

        $data = $this->validateBin($request, $businessId);

        Bin::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $businessId,
            'location_id' => $data['location_id'],
            'name' => $data['name'],
            'number' => $data['number'] ?? null,
            'description' => $data['description'] ?? null,
            'sort_order' => $this->nextSortOrder($businessId, $data['location_id']),
        ]);

        return back()->with('success', __('bins.flash.bin_created'));
    }

    public function update(Request $request, Bin $bin): RedirectResponse
    {
        $businessId = BusinessContext::currentId();

        $data = $this->validateBin($request, $businessId, $bin);

        $bin->update([
            'location_id' => $data['location_id'],
            'name' => $data['name'],
            'number' => $data['number'] ?? null,
            'number_locked' => $data['number_locked'] ?? false,
            'position_locked' => $data['position_locked'] ?? false,
            'description' => $data['description'] ?? null,
        ]);

        return back()->with('success', __('bins.flash.bin_updated'));
    }

    public function destroy(Request $request, Bin $bin): RedirectResponse
    {
        if ($bin->is_default) {
            return back()->with('error', __('bins.flash.bin_default_protected'));
        }

        // Block deletion while real stock remains — the user must check out or
        // transfer it first so totals can never strand in a removed bin.
        $hasStock = StockLevel::where('bin_id', $bin->id)
            ->where(fn (Builder $q) => $q->where('full_bags', '>', 0)->orWhere('open_bags', '>', 0))
            ->exists();

        if ($hasStock) {
            return back()->with('error', __('bins.flash.bin_has_stock'));
        }

        // Empty (0/0) assignment rows can ride along so they don't dangle.
        StockLevel::where('bin_id', $bin->id)->get()->each->delete();

        $bin->delete();

        // Can't back() — the caller's URL (the bin detail page, or implicitly the
        // deleted row) is gone. Return to where the action came from: Manage
        // storage when triggered there, otherwise the By-Bin wall.
        $target = $request->input('from') === 'manage'
            ? 'inventory.storage'
            : 'inventory.bins.index';

        return redirect()->route($target)
            ->with('success', __('bins.flash.bin_deleted'));
    }

    /**
     * @return array{location_id:string,name:string,number:?int,number_locked?:bool,position_locked?:bool,description:?string}
     */
    private function validateBin(Request $request, ?string $businessId, ?Bin $bin = null): array
    {
        return $request->validate([
            'location_id' => [
                'required',
                'uuid',
                Rule::exists('locations', 'id')
                    ->where('business_id', $businessId)
                    ->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'number' => [
                'nullable',
                'integer',
                'min:1',
                'max:32767',
                Rule::unique('bins', 'number')
                    ->where(fn ($q) => $q->where('business_id', $businessId)->whereNull('deleted_at'))
                    ->ignore($bin?->id),
            ],
            'number_locked' => ['boolean'],
            'position_locked' => ['boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function nextSortOrder(string $businessId, string $locationId): int
    {
        return (int) Bin::withoutGlobalScope(BusinessScope::class)
            ->where('business_id', $businessId)
            ->where('location_id', $locationId)
            ->max('sort_order') + 1;
    }

    /**
     * The compact per-bin item shape shared by the single-bin, bulk, and detail
     * payloads — keeps the three contents readers from drifting.
     *
     * @return array<string,mixed>
     */
    private function stockItemPayload(StockLevel $level): array
    {
        return [
            'sku_id' => $level->sku_id,
            'name' => $level->sku?->computed_name ?? $level->sku?->name,
            'brand' => $level->sku?->brand?->abbreviation,
            'size' => $level->sku?->balloonSize?->name,
            'color_hex' => $level->sku?->color?->color_hex,
            'full_bags' => $level->full_bags,
            'open_bags' => $level->open_bags,
        ];
    }

    /**
     * The business's bins for the move/transfer-destination picker, Default
     * first. Mirrors the selector shape used on the SKU detail page.
     *
     * @return array<int,array<string,mixed>>
     */
    private function binsForSelector(): array
    {
        return Bin::with('location:id,name')
            ->orderedForDisplay()
            ->get()
            ->map(fn (Bin $bin) => [
                'id' => $bin->id,
                'name' => $bin->name,
                'number' => $bin->number,
                'location_name' => $bin->location?->name,
            ])
            ->all();
    }
}
