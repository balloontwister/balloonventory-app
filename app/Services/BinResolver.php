<?php

namespace App\Services;

use App\Models\Bin;
use App\Models\Business;
use App\Models\Location;
use App\Scopes\BusinessScope;

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
}
