<?php

namespace App\Services;

use App\Models\Bin;
use App\Models\Business;
use App\Models\Location;
use App\Models\StockLevel;
use App\Scopes\BusinessScope;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BinResolver
{
    /**
     * Resolve the business's Default bin, creating the Default location and bin
     * on the fly if the business somehow has none yet. Every business seeds
     * these on registration, so the create path is a defensive fallback for
     * legacy rows.
     *
     * This was previously duplicated verbatim in ScanController and
     * InventoryController; it now lives here so bin resolution has a single home
     * to grow per-SKU inference and explicit-bin selection onto.
     */
    public function resolveDefault(Business $business): Bin
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

    /**
     * Resolve the bin a movement should target. When the client supplies an
     * explicit bin (the user picked one, or accepted the inferred default), it
     * must belong to the business. With no selection we fall back to the
     * Default bin, preserving the pre-bin-selection behavior.
     */
    public function resolveSelectedBin(Business $business, ?string $binId): Bin
    {
        if ($binId === null || $binId === '') {
            return $this->resolveDefault($business);
        }

        $bin = Bin::where('id', $binId)
            ->where('business_id', $business->id)
            ->first();

        if ($bin === null) {
            throw ValidationException::withMessages([
                'bin_id' => 'That bin is not available for this business.',
            ]);
        }

        return $bin;
    }

    /**
     * Stock rows for a SKU that currently hold something, most-recently-moved
     * first, with their bin + location eager-loaded. Drives the scan page's
     * "where does this item live" hint and multi-bin disambiguation.
     *
     * @return Collection<int,StockLevel>
     */
    public function binsHoldingSku(string $businessId, string $skuId): Collection
    {
        return StockLevel::where('business_id', $businessId)
            ->where('sku_id', $skuId)
            ->where(fn ($q) => $q->where('full_bags', '>', 0)->orWhere('open_bags', '>', 0))
            ->with(['bin:id,name,number,location_id', 'bin.location:id,name'])
            ->orderByDesc('last_movement_at')
            ->get();
    }
}
