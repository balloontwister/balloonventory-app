<?php

namespace App\Http\Controllers;

use App\Enums\StockDirection;
use App\Models\Bin;
use App\Models\Business;
use App\Models\Location;
use App\Models\Sku;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Scopes\BusinessScope;
use App\Support\BusinessContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ScanController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Scan/Index');
    }

    public function lookup(Request $request): JsonResponse
    {
        $request->validate(['upc' => ['required', 'string', 'max:50']]);

        $upc = trim($request->input('upc'));

        $businessId = BusinessContext::currentId();

        $sku = Sku::with([
            'brand:id,name,abbreviation',
            'balloonSize' => fn ($q) => $q->with(['shape:id,name', 'size:id,name']),
            'color' => fn ($q) => $q->with(['colorFamily:id,name', 'texture:id,name']),
            'material:id,name',
        ])
            ->where('upc', $upc)
            ->where(fn ($q) => $q
                ->whereNull('owned_by_business_id')
                ->orWhere('owned_by_business_id', $businessId)
            )
            ->first();

        if (! $sku) {
            return response()->json(['found' => false]);
        }

        $stockLevels = StockLevel::where('sku_id', $sku->id)
            ->select('full_bags', 'open_bags')
            ->get();

        return response()->json([
            'found' => true,
            'sku' => [
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
            ],
        ]);
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

            // Scope the stock-level lookup to the SAME bin the original
            // movement targeted. The legacy unique key on (business_id, sku_id)
            // makes this a no-op today, but the bin_id column is already in
            // the schema so we get this right before stock spans multiple bins.
            $stockLevel = StockLevel::where('business_id', $businessId)
                ->where('sku_id', $stockMovement->sku_id)
                ->where('bin_id', $stockMovement->bin_id)
                ->lockForUpdate()
                ->first();

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

        // Resolve bin — use default, creating it if the business has none yet.
        $bin = $this->resolveBin($business);

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
        $sku = Sku::where('id', $skuId)
            ->where(fn ($q) => $q
                ->whereNull('owned_by_business_id')
                ->orWhere('owned_by_business_id', $businessId)
            )
            ->first();

        if (! $sku) {
            throw ValidationException::withMessages([
                'sku_id' => 'That SKU is not available for this business.',
            ]);
        }

        return $sku;
    }

    private function resolveBin(Business $business): Bin
    {
        $bin = $business->defaultBin();

        if ($bin !== null) {
            return $bin;
        }

        $location = $business->defaultLocation();

        if ($location === null) {
            $location = Location::withoutGlobalScope(BusinessScope::class)->create([
                'business_id' => $business->id,
                'name' => 'Default',
                'is_default' => true,
            ]);
        }

        return Bin::withoutGlobalScope(BusinessScope::class)->create([
            'business_id' => $business->id,
            'location_id' => $location->id,
            'name' => 'Default',
            'is_default' => true,
        ]);
    }
}
