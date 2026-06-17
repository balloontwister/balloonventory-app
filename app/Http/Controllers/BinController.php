<?php

namespace App\Http\Controllers;

use App\Models\Bin;
use App\Models\Business;
use App\Models\Location;
use App\Models\StockLevel;
use App\Scopes\BusinessScope;
use App\Services\BinResolver;
use App\Support\BusinessContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
                    ->orderByDesc('is_default')
                    ->orderBy('sort_order')
                    ->orderByRaw('`number` is null')
                    ->orderBy('number')
                    ->orderBy('name')
                    ->withSum('stockLevels as full_bags_total', 'full_bags')
                    ->withSum('stockLevels as open_bags_total', 'open_bags')
                    ->withCount(['stockLevels as sku_count' => fn (Builder $q) => $q
                        ->where(fn (Builder $inner) => $inner
                            ->where('full_bags', '>', 0)
                            ->orWhere('open_bags', '>', 0)),
                    ]);
            }])
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('Inventory/Bins', [
            'locations' => $locations,
        ]);
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

        $items = $levels->map(fn (StockLevel $level) => [
            'sku_id' => $level->sku_id,
            'name' => $level->sku?->computed_name ?? $level->sku?->name,
            'brand' => $level->sku?->brand?->abbreviation,
            'size' => $level->sku?->balloonSize?->name,
            'color_hex' => $level->sku?->color?->color_hex,
            'full_bags' => $level->full_bags,
            'open_bags' => $level->open_bags,
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
            'description' => $data['description'] ?? null,
        ]);

        return back()->with('success', __('bins.flash.bin_updated'));
    }

    public function destroy(Bin $bin): RedirectResponse
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

        return back()->with('success', __('bins.flash.bin_deleted'));
    }

    /**
     * @return array{location_id:string,name:string,number:?int,description:?string}
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
}
