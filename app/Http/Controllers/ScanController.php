<?php

namespace App\Http\Controllers;

use App\Enums\StockDirection;
use App\Models\Bin;
use App\Models\Business;
use App\Models\Sku;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Services\BarcodeMatcher;
use App\Services\BinResolver;
use App\Support\BusinessContext;
use App\Support\Gtin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ScanController extends Controller
{
    public function __construct(
        private readonly BarcodeMatcher $barcodeMatcher,
        private readonly BinResolver $binResolver,
    ) {}

    public function index(): Response
    {
        $businessId = BusinessContext::currentId();

        // Guarantee a Default bin so the working-bin selector is never empty
        // (mirrors the bins page; businesses predating bins were seeded lazily).
        $business = Business::findOrFail($businessId);
        $defaultBin = $this->binResolver->resolveDefault($business);

        return Inertia::render('Scan/Index', [
            'bins' => $this->binsForSelector($businessId),
            'defaultBinId' => $defaultBin->id,
        ]);
    }

    /**
     * Flat, location-grouped list of the business's bins for the scan page's
     * working-bin selector.
     *
     * @return array<int,array<string,mixed>>
     */
    private function binsForSelector(string $businessId): array
    {
        return Bin::where('business_id', $businessId)
            ->with(['location:id,name,sort_order'])
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Bin $bin) => [
                'id' => $bin->id,
                'name' => $bin->name,
                'number' => $bin->number,
                'is_default' => $bin->is_default,
                'location_name' => $bin->location?->name,
            ])
            ->all();
    }

    public function lookup(Request $request): JsonResponse
    {
        $request->validate(['upc' => ['required', 'string', 'max:50']]);

        $businessId = BusinessContext::currentId();

        // A scanned bin label resolves a working bin rather than a product. The
        // global BusinessScope on Bin keeps this to the current business, so
        // another business's BIN code reads as "not found".
        $raw = trim($request->input('upc'));
        if (str_starts_with(strtoupper($raw), 'BIN-')) {
            return $this->lookupBin(strtoupper($raw));
        }

        $result = $this->barcodeMatcher->match($request->input('upc'), $businessId);

        // Common metadata returned in every response so the frontend can
        // decide what to do with the scan (auto-record, prompt to confirm,
        // route to the "add new UPC" flow) without an extra round-trip.
        $meta = [
            'gtin14' => $result['gtin14'],
            'is_valid_gtin' => $result['is_valid_gtin'],
        ];

        if ($result['candidates'] === []) {
            return response()->json($meta + [
                'found' => false,
                'auto_apply' => false,
            ]);
        }

        // Batch-load relations + stock levels for every candidate so the
        // confirm-match UI can render any of them without a second request.
        $candidateIds = array_map(static fn (array $c) => $c['sku']->id, $result['candidates']);

        $skus = Sku::with([
            'brand:id,name,abbreviation',
            'balloonSize' => fn ($q) => $q->with(['shape:id,name', 'size:id,name']),
            'color' => fn ($q) => $q->with(['colorFamily:id,name', 'texture:id,name']),
            'material:id,name',
        ])
            ->whereIn('id', $candidateIds)
            ->get()
            ->keyBy('id');

        $stockBySkuId = StockLevel::whereIn('sku_id', $candidateIds)
            ->select('id', 'sku_id', 'bin_id', 'full_bags', 'open_bags', 'last_movement_at')
            ->with(['bin:id,name,number,location_id', 'bin.location:id,name'])
            ->orderByDesc('last_movement_at')
            ->get()
            ->groupBy('sku_id');

        $candidates = array_map(function (array $c) use ($skus, $stockBySkuId) {
            $sku = $skus[$c['sku']->id];

            return [
                'match' => $c['match'],
                'score' => $c['score'],
                'sku' => $this->serializeSku($sku, $stockBySkuId->get($sku->id, collect())),
            ];
        }, $result['candidates']);

        $top = $candidates[0];
        $autoApply = count($candidates) === 1 && $top['score'] >= 100;

        return response()->json($meta + [
            'found' => true,
            'auto_apply' => $autoApply,
            'match_type' => $top['match'],
            'sku' => $top['sku'],
            'candidates' => $candidates,
        ]);
    }

    /**
     * Search the visible catalog (shared rows OR owned by this business) for the
     * "link this barcode to a product" picker. Lightweight JSON — name, brand,
     * size, colour, and whether the SKU already carries a barcode.
     */
    public function searchSkus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $businessId = BusinessContext::currentId();
        $term = $data['q'];

        $skus = Sku::visibleTo($businessId)
            ->with([
                'brand:id,name,abbreviation',
                'balloonSize' => fn ($q) => $q->with('size:id,name'),
                'color:id,name,color_hex',
            ])
            ->where(fn ($q) => $q
                ->where('skus.name', 'like', "%{$term}%")
                ->orWhere('skus.computed_name', 'like', "%{$term}%")
                ->orWhere('skus.warehouse_sku', 'like', "%{$term}%")
                ->orWhereHas('color', fn ($c) => $c->where('name', 'like', "%{$term}%"))
                ->orWhereHas('brand', fn ($b) => $b
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('abbreviation', 'like', "%{$term}%")))
            ->orderBy('skus.name')
            ->limit(15)
            ->get();

        return response()->json([
            'skus' => $skus->map(fn (Sku $sku) => [
                'id' => $sku->id,
                'name' => $sku->name,
                'warehouse_sku' => $sku->warehouse_sku,
                'has_barcode' => $sku->upc !== null || $sku->ean !== null,
                'brand' => $sku->brand ? ['abbreviation' => $sku->brand->abbreviation] : null,
                'balloon_size' => $sku->balloonSize?->size ? ['name' => $sku->balloonSize->size->name] : null,
                'color' => $sku->color ? ['name' => $sku->color->name, 'color_hex' => $sku->color->color_hex] : null,
            ])->all(),
        ]);
    }

    /**
     * Attach a scanned barcode to a catalog SKU so future scans resolve it. The
     * code is routed by length — 12 digits to `upc` (UPC-A), anything else to
     * `ean` — and written to the shared catalog row so every business benefits.
     * Rejects an invalid check digit, a code already on another SKU, or
     * overwriting a different code already on the target SKU.
     */
    public function linkBarcode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'barcode' => ['required', 'string', 'max:50'],
            'sku_id' => ['required', 'uuid'],
        ]);

        $businessId = BusinessContext::currentId();
        $sku = $this->resolveVisibleSku($data['sku_id'], $businessId);

        $digits = Gtin::digitsOnly($data['barcode']);

        if (strlen($digits) < 8 || ! Gtin::isValidCheckDigit($digits)) {
            throw ValidationException::withMessages([
                'barcode' => __('scan.link.invalid_barcode'),
            ]);
        }

        $column = strlen($digits) === 12 ? 'upc' : 'ean';

        // Already on another SKU (either barcode column)? Refuse — a barcode must
        // map to exactly one product.
        $clash = Sku::where('id', '!=', $sku->id)
            ->where(fn ($q) => $q->where('upc', $digits)->orWhere('ean', $digits))
            ->first();

        if ($clash !== null) {
            throw ValidationException::withMessages([
                'barcode' => __('scan.link.already_used', ['name' => $clash->name]),
            ]);
        }

        // Don't clobber a different existing code on this SKU/column.
        if ($sku->{$column} !== null && $sku->{$column} !== $digits) {
            throw ValidationException::withMessages([
                'barcode' => __('scan.link.has_other_code'),
            ]);
        }

        $sku->{$column} = $digits;
        $sku->save();

        return response()->json([
            'linked' => true,
            'sku_id' => $sku->id,
            'sku_name' => $sku->name,
        ]);
    }

    /**
     * Resolve a scanned bin label to the working bin it represents. Returns a
     * `type: bin` payload the scan page uses to set the active working bin
     * instead of recording a movement.
     */
    private function lookupBin(string $scanCode): JsonResponse
    {
        $bin = Bin::where('scan_code', $scanCode)
            ->with('location:id,name')
            ->first();

        if ($bin === null) {
            return response()->json(['type' => 'bin', 'found' => false]);
        }

        return response()->json([
            'type' => 'bin',
            'found' => true,
            'bin' => [
                'id' => $bin->id,
                'name' => $bin->name,
                'number' => $bin->number,
                'location_name' => $bin->location?->name,
            ],
        ]);
    }

    /**
     * Shape a Sku model into the array the frontend expects. Stock levels are
     * pre-grouped by sku_id and passed in to avoid an N+1 across candidates.
     *
     * @param  Collection<int,StockLevel>  $stockLevels
     * @return array<string,mixed>
     */
    private function serializeSku(Sku $sku, $stockLevels): array
    {
        // Bins that actually hold this SKU, most-recently-moved first. The top
        // entry is the suggested bin: "known location wins", so a scan defaults
        // to where the item already lives. Empty when the SKU is new to stock —
        // the frontend then falls back to the working bin (or Default).
        $holdingBins = $stockLevels
            ->filter(fn (StockLevel $level) => $level->full_bags > 0 || $level->open_bags > 0)
            ->map(fn (StockLevel $level) => [
                'bin_id' => $level->bin_id,
                'bin_name' => $level->bin?->name,
                'bin_number' => $level->bin?->number,
                'location_name' => $level->bin?->location?->name,
                'full_bags' => $level->full_bags,
                'open_bags' => $level->open_bags,
            ])
            ->values();

        return [
            'id' => $sku->id,
            'name' => $sku->name,
            'computed_name' => $sku->computed_name,
            'warehouse_sku' => $sku->warehouse_sku,
            'upc' => $sku->upc,
            'default_count_per_bag' => $sku->default_count_per_bag,
            'color' => $sku->color ? [
                'id' => $sku->color->id,
                'name' => $sku->color->name,
                'color_hex' => $sku->color->color_hex,
            ] : null,
            'brand' => $sku->brand ? [
                'id' => $sku->brand->id,
                'name' => $sku->brand->name,
                'abbreviation' => $sku->brand->abbreviation,
            ] : null,
            'balloon_size' => $sku->balloonSize ? [
                'id' => $sku->balloonSize->id,
                'name' => $sku->balloonSize->name,
                'shape' => $sku->balloonSize->shape ? [
                    'id' => $sku->balloonSize->shape->id,
                    'name' => $sku->balloonSize->shape->name,
                ] : null,
                'size' => $sku->balloonSize->size ? [
                    'id' => $sku->balloonSize->size->id,
                    'name' => $sku->balloonSize->size->name,
                ] : null,
            ] : null,
            'full_bags_total' => (int) $stockLevels->sum('full_bags'),
            'open_bags_total' => (int) $stockLevels->sum('open_bags'),
            'bins' => $holdingBins->all(),
            'suggested_bin_id' => $holdingBins->first()['bin_id'] ?? null,
        ];
    }

    public function checkIn(Request $request): JsonResponse
    {
        return $this->recordMovement($request, StockDirection::In);
    }

    public function checkOut(Request $request): JsonResponse
    {
        return $this->recordMovement($request, StockDirection::Out);
    }

    public function undo(Request $request, StockMovement $stockMovement): JsonResponse
    {
        $businessId = BusinessContext::currentId();

        // Implicit binding already enforces business_id via the global scope on
        // StockMovement; the explicit check is defense-in-depth in case the
        // scope is ever bypassed.
        abort_if($stockMovement->business_id !== $businessId, 404);
        abort_unless(in_array($stockMovement->direction, [StockDirection::In, StockDirection::Out]), 422);

        $reverseDirection = $stockMovement->direction === StockDirection::In
            ? StockDirection::Out
            : StockDirection::In;

        DB::transaction(function () use ($stockMovement, $reverseDirection, $request, $businessId) {
            // Scope the stock-level lookup to the SAME bin the original
            // movement targeted. The legacy unique key on (business_id, sku_id)
            // makes this a no-op today, but the bin_id column is already in
            // the schema so we get this right before stock spans multiple bins.
            $stockLevel = StockLevel::where('business_id', $businessId)
                ->where('sku_id', $stockMovement->sku_id)
                ->where('bin_id', $stockMovement->bin_id)
                ->lockForUpdate()
                ->first();

            // Undoing a check-IN removes those bags from stock again. Reject if
            // the current on-hand level can't cover the reversal — exactly as a
            // check-OUT would — otherwise the decrement drives the level
            // negative and silently corrupts totals.
            if ($stockMovement->direction === StockDirection::In) {
                $availableFull = $stockLevel->full_bags ?? 0;
                $availableOpen = $stockLevel->open_bags ?? 0;

                if ($availableFull < $stockMovement->full_bags_change
                    || $availableOpen < $stockMovement->open_bags_change) {
                    throw ValidationException::withMessages([
                        'stock' => sprintf(
                            'Cannot undo: only %d full / %d open bag(s) on hand to reverse.',
                            $availableFull,
                            $availableOpen,
                        ),
                    ]);
                }
            }

            StockMovement::create([
                'business_id' => $businessId,
                'sku_id' => $stockMovement->sku_id,
                'bin_id' => $stockMovement->bin_id,
                'user_id' => $request->user()->id,
                'direction' => $reverseDirection,
                'full_bags_change' => $stockMovement->full_bags_change,
                'open_bags_change' => $stockMovement->open_bags_change,
                'upc_scanned' => $stockMovement->upc_scanned,
                'notes' => 'Undo of movement '.$stockMovement->id,
            ]);

            if ($stockLevel) {
                if ($stockMovement->direction === StockDirection::In) {
                    $stockLevel->decrement('full_bags', $stockMovement->full_bags_change);
                    $stockLevel->decrement('open_bags', $stockMovement->open_bags_change);
                } else {
                    $stockLevel->increment('full_bags', $stockMovement->full_bags_change);
                    $stockLevel->increment('open_bags', $stockMovement->open_bags_change);
                }
                $stockLevel->update(['last_movement_at' => now()]);
            }
        });

        return response()->json(['undone' => true]);
    }

    private function recordMovement(Request $request, StockDirection $direction): JsonResponse
    {
        $businessId = BusinessContext::currentId();

        $data = $request->validate([
            'sku_id' => ['required', 'uuid'],
            'upc' => ['nullable', 'string', 'max:50'],
            'bin_id' => ['nullable', 'uuid'],
            'full_bags_change' => ['integer', 'min:0'],
            'open_bags_change' => ['integer', 'min:0'],
            'job_id' => [
                'nullable',
                'uuid',
                Rule::exists('jobs', 'id')->where('business_id', $businessId),
            ],
        ]);

        $fullBagsChange = (int) ($data['full_bags_change'] ?? 0);
        $openBagsChange = (int) ($data['open_bags_change'] ?? 0);

        if ($fullBagsChange === 0 && $openBagsChange === 0) {
            throw ValidationException::withMessages([
                'full_bags_change' => 'At least one of full_bags_change or open_bags_change must be > 0.',
            ]);
        }

        // Resolve the SKU under the catalog visibility rule (shared OR owned
        // by current business). Soft-deleted rows are excluded by the default
        // scope. This is the only place sku_id is trusted — it MUST be filtered
        // here, not via `exists:skus,id` (which would let foreign-owned UUIDs
        // through).
        $sku = $this->resolveVisibleSku($data['sku_id'], $businessId);

        $business = Business::findOrFail($businessId);
        $userId = $request->user()->id;

        // Resolve the target bin: the one the client chose (validated to this
        // business), falling back to Default when no bin was supplied.
        $bin = $this->binResolver->resolveSelectedBin($business, $data['bin_id'] ?? null);

        $upcScanned = isset($data['upc']) ? trim($data['upc']) : null;
        if ($upcScanned === '') {
            $upcScanned = null;
        }

        $movementId = DB::transaction(function () use (
            $businessId,
            $sku,
            $data,
            $direction,
            $fullBagsChange,
            $openBagsChange,
            $bin,
            $userId,
            $upcScanned,
        ) {
            $stockLevel = StockLevel::where('business_id', $businessId)
                ->where('sku_id', $sku->id)
                ->where('bin_id', $bin->id)
                ->lockForUpdate()
                ->first();

            if ($stockLevel === null) {
                $stockLevel = StockLevel::create([
                    'business_id' => $businessId,
                    'sku_id' => $sku->id,
                    'bin_id' => $bin->id,
                    'full_bags' => 0,
                    'open_bags' => 0,
                ]);
            }

            // Reject under-removal BEFORE writing anything. Decrement-to-negative
            // would silently corrupt totals and downstream reports.
            if ($direction === StockDirection::Out) {
                if ($stockLevel->full_bags < $fullBagsChange || $stockLevel->open_bags < $openBagsChange) {
                    throw ValidationException::withMessages([
                        'full_bags_change' => sprintf(
                            'Not enough stock on hand. Available: %d full / %d open bags.',
                            $stockLevel->full_bags,
                            $stockLevel->open_bags,
                        ),
                    ]);
                }
            }

            $movement = StockMovement::create([
                'business_id' => $businessId,
                'sku_id' => $sku->id,
                'bin_id' => $bin->id,
                'user_id' => $userId,
                'direction' => $direction,
                'full_bags_change' => $fullBagsChange,
                'open_bags_change' => $openBagsChange,
                'upc_scanned' => $upcScanned,
                'job_id' => $data['job_id'] ?? null,
            ]);

            if ($direction === StockDirection::In) {
                $stockLevel->increment('full_bags', $fullBagsChange);
                $stockLevel->increment('open_bags', $openBagsChange);
            } else {
                $stockLevel->decrement('full_bags', $fullBagsChange);
                $stockLevel->decrement('open_bags', $openBagsChange);
            }

            $stockLevel->update(['last_movement_at' => now()]);

            return $movement->id;
        });

        // Reload with fresh totals for the response. resolveVisibleSku above
        // already proved the SKU is in this business's catalog, so a plain
        // find() (with eager-loaded relations) is safe here.
        $sku = Sku::with([
            'brand:id,name,abbreviation',
            'balloonSize' => fn ($q) => $q->with(['shape:id,name']),
            'color:id,name,color_hex',
        ])->findOrFail($sku->id);

        $stockLevels = StockLevel::where('sku_id', $sku->id)
            ->select('full_bags', 'open_bags')
            ->get();

        return response()->json([
            'recorded' => true,
            'direction' => $direction->value,
            'full_bags_change' => $fullBagsChange,
            'open_bags_change' => $openBagsChange,
            'movement_id' => $movementId,
            'bin' => [
                'id' => $bin->id,
                'name' => $bin->name,
                'number' => $bin->number,
            ],
            'sku' => [
                'id' => $sku->id,
                'name' => $sku->name,
                'computed_name' => $sku->computed_name,
                'color' => $sku->color ? [
                    'color_hex' => $sku->color->color_hex,
                ] : null,
                'brand' => $sku->brand ? [
                    'abbreviation' => $sku->brand->abbreviation,
                ] : null,
                'balloon_size' => $sku->balloonSize ? [
                    'name' => $sku->balloonSize->name,
                    'shape' => $sku->balloonSize->shape ? ['name' => $sku->balloonSize->shape->name] : null,
                ] : null,
                'full_bags_total' => (int) $stockLevels->sum('full_bags'),
                'open_bags_total' => (int) $stockLevels->sum('open_bags'),
            ],
        ]);
    }

    /**
     * Fetch a SKU that this business is allowed to act on (shared catalog row
     * OR owned by the current business). Throws 422 otherwise — matching the
     * shape of normal input validation rather than 404 so the frontend's
     * existing error path renders.
     */
    private function resolveVisibleSku(string $skuId, ?string $businessId): Sku
    {
        $sku = Sku::visibleTo($businessId)->find($skuId);

        if (! $sku) {
            throw ValidationException::withMessages([
                'sku_id' => 'That SKU is not available for this business.',
            ]);
        }

        return $sku;
    }
}
