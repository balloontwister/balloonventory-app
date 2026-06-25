<?php

namespace App\Services\Distributors;

use App\Models\Sku;
use Illuminate\Support\Collection;

/**
 * Finds catalog SKUs that share a proposal's resolved identity, split into:
 *
 *  - `exact`    — same brand + size + colour + print state + pack count + packaging:
 *                 the SAME product variant. If it exists without a barcode,
 *                 approving would create a duplicate, so the review UI offers to
 *                 map to it instead.
 *  - `siblings` — same brand + size + colour + print state but differing in pack
 *                 count and/or packaging: the *identical product in other
 *                 packaging* (a brand sells the same balloon in 100/50/12-count
 *                 and as Nozzle Up / Loose / Retail for different markets). These
 *                 are legitimately separate SKUs that should be linked via
 *                 {@see Sku::linkIdentical()} so the user can switch between them.
 *
 * Print state (`is_printed`) is part of the identity: a printed "Happy Birthday"
 * and a solid balloon of the same brand/size/colour are different products and
 * must never be treated as the same or linked as identical.
 */
class IdenticalSkuFinder
{
    /**
     * @return array{exact: ?Sku, siblings: Collection<int, Sku>}
     */
    public function find(string $brandId, string $balloonSizeId, string $colorId, bool $isPrinted, ?int $count, ?string $packagingId = null): array
    {
        $skus = Sku::query()
            ->where('brand_id', $brandId)
            ->where('balloon_size_id', $balloonSizeId)
            ->where('color_id', $colorId)
            ->where('is_printed', $isPrinted)
            ->get();

        $isExact = fn (Sku $sku) => $sku->default_count_per_bag === $count && $sku->packaging_id === $packagingId;

        return [
            'exact' => $skus->first($isExact),
            'siblings' => $skus->reject($isExact)->values(),
        ];
    }
}
