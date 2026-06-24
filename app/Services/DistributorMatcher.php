<?php

namespace App\Services;

use App\Models\Distributor;
use App\Models\Sku;
use App\Support\Gtin;
use App\Support\ProductText;
use Illuminate\Support\Collection;

class DistributorMatcher
{
    /**
     * Match parsed distributor products against catalog SKUs.
     *
     * Matching cascade (ordered by confidence):
     *   1. Exact barcode match (UPC/EAN)
     *   2. Warehouse SKU match
     *   3. Attribute-based fallback (size + color + brand + count from parsed slug)
     *
     * @param  array<int, array{identifier: string, name: string, url: string, barcode: ?string, price: ?float, currency: ?string, in_stock: ?bool}>  $products
     * @return array{matched: Collection, gaps: Collection}
     */
    public function match(Distributor $distributor, array $products): array
    {
        if ($products === []) {
            return [
                'matched' => collect(),
                'gaps' => collect(),
            ];
        }

        // Pre-load all catalog data needed for matching
        $barcodeIndex = $this->buildBarcodeIndex();
        $warehouseSkuIndex = $this->buildWarehouseSkuIndex();
        $resolver = new CatalogAttributeResolver;

        $matched = collect();
        $gaps = collect();

        foreach ($products as $product) {
            $skuId = null;
            $matchReason = null;

            // Tier 1: Exact barcode match (canonicalized to GTIN-14 so that
            // a UPC-A stored as 12 digits collides with the same product
            // listed as a leading-zero EAN-13, and vice versa).
            if ($product['barcode'] !== null && $product['barcode'] !== '') {
                $canonical = Gtin::canonicalize($product['barcode']);

                if ($canonical !== null) {
                    $skuId = $barcodeIndex->get($canonical);
                    $matchReason = $skuId !== null ? 'barcode' : null;
                }
            }

            // Tier 2: Warehouse SKU match
            if ($skuId === null && $product['identifier'] !== '') {
                $skuId = $warehouseSkuIndex->get($product['identifier']);
                $matchReason = $skuId !== null ? 'warehouse_sku' : null;
            }

            // Tier 3: Attribute-based match (fuzzy — parse name/slug)
            if ($skuId === null) {
                $attrMatch = $this->matchByAttributes($product, $resolver);
                $skuId = $attrMatch['sku_id'];
                $matchReason = $attrMatch['reason'];
            }

            if ($skuId !== null) {
                $matched->push([
                    'distributor_id' => $distributor->id,
                    'sku_id' => $skuId,
                    'url' => $product['url'],
                    'price' => $product['price'],
                    'currency' => $product['currency'],
                    'in_stock' => $product['in_stock'],
                    'last_checked_at' => now(),
                    'match_reason' => $matchReason,
                ]);
            } else {
                $gaps->push([
                    'distributor_id' => $distributor->id,
                    'external_identifier' => $product['identifier'],
                    'product_name' => $product['name'],
                    'product_url' => $product['url'],
                    'reason' => 'no_matching_barcode_or_warehouse_sku',
                    'raw_data' => $product,
                ]);
            }
        }

        return [
            'matched' => $matched,
            'gaps' => $gaps,
        ];
    }

    /**
     * Build an index of canonical GTIN-14 → sku_id for fast lookup. Stores
     * both the UPC and EAN of each SKU under their canonical form so lookups
     * are format-agnostic (UPC-A vs leading-zero EAN-13 collide).
     *
     * The where() closure groups the OR so the SoftDeletes scope's
     * `deleted_at IS NULL` isn't short-circuited by operator precedence —
     * otherwise trashed SKUs would leak into the index and could shadow a
     * live SKU sharing the same barcode.
     */
    private function buildBarcodeIndex(): Collection
    {
        $index = collect();

        Sku::where(fn ($query) => $query->whereNotNull('upc')->orWhereNotNull('ean'))
            ->get(['id', 'upc', 'ean'])
            ->each(function (Sku $sku) use ($index) {
                foreach ([$sku->upc, $sku->ean] as $code) {
                    if ($code && ($canonical = Gtin::canonicalize($code)) !== null) {
                        $index->put($canonical, $sku->id);
                    }
                }
            });

        return $index;
    }

    /**
     * Build an index of warehouse_sku → sku_id for fast lookup.
     */
    private function buildWarehouseSkuIndex(): Collection
    {
        return Sku::whereNotNull('warehouse_sku')
            ->get(['id', 'warehouse_sku'])
            ->keyBy('warehouse_sku')
            ->map(fn (Sku $sku) => $sku->id);
    }

    /**
     * Attempt fuzzy attribute-based matching from the product name/URL.
     *
     * @return array{sku_id: ?string, reason: ?string}
     */
    private function matchByAttributes(array $product, CatalogAttributeResolver $resolver): array
    {
        ['brand' => $brand, 'balloonSize' => $balloonSize, 'color' => $color] = $resolver->resolve($product['name']);

        // Need brand + size + color to even consider a match. A looser match
        // would pin a distributor URL to an arbitrary color/variant of that
        // size — i.e. send the user to buy the wrong balloon — so we'd rather
        // record a gap than guess.
        if ($brand === null || $balloonSize === null || $color === null) {
            return ['sku_id' => null, 'reason' => null];
        }

        $candidates = Sku::where('brand_id', $brand->id)
            ->where('balloon_size_id', $balloonSize->id)
            ->where('color_id', $color->id)
            ->get(['id', 'default_count_per_bag']);

        // Exactly one variant for this brand/size/color — safe to match.
        if ($candidates->count() === 1) {
            return ['sku_id' => $candidates->first()->id, 'reason' => 'attribute'];
        }

        // Multiple variants (e.g. 50ct vs 100ct bags). Only commit if the pack
        // count parsed from the product name singles one out; otherwise refuse
        // to guess which variant the URL belongs to.
        if ($candidates->count() > 1) {
            $count = ProductText::packCount($product['name']);

            if ($count !== null) {
                $byCount = $candidates->where('default_count_per_bag', $count)->values();

                if ($byCount->count() === 1) {
                    return ['sku_id' => $byCount->first()->id, 'reason' => 'attribute'];
                }
            }
        }

        return ['sku_id' => null, 'reason' => null];
    }
}
