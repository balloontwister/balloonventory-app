<?php

namespace App\Services;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Color;
use App\Models\Distributor;
use App\Models\PackagingType;
use App\Models\Sku;
use App\Support\Gtin;
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
        $attributeData = $this->loadAttributeData();

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
                $attrMatch = $this->matchByAttributes($product, $attributeData);
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
     * Load all attribute reference data needed for Tier 3 matching.
     */
    private function loadAttributeData(): array
    {
        // Each lookup is ordered longest-name-first so the most specific token
        // wins: "15-inch" is tried before "5-inch", "Light Blue" before "Blue".
        return [
            'brands' => Brand::all()
                ->sortByDesc(fn (Brand $b) => strlen($b->name))
                ->keyBy(fn (Brand $b) => strtolower($b->name)),
            'balloonSizes' => BalloonSize::with(['size', 'shape'])
                ->get()
                ->sortByDesc(fn (BalloonSize $bs) => strlen($bs->name))
                ->keyBy(fn (BalloonSize $bs) => strtolower($bs->name)),
            'colors' => Color::with('brand')->get()
                ->groupBy(fn (Color $c) => $c->brand_id)
                ->map(fn (Collection $group) => $group
                    ->sortByDesc(fn (Color $c) => strlen($c->name))
                    ->keyBy(fn (Color $c) => strtolower($c->name))),
            'packagingTypes' => PackagingType::all()->keyBy(fn (PackagingType $pt) => strtolower($pt->name)),
        ];
    }

    /**
     * Attempt fuzzy attribute-based matching from the product name/URL.
     *
     * @param  array  $data  Pre-loaded attribute reference data
     * @return array{sku_id: ?string, reason: ?string}
     */
    private function matchByAttributes(array $product, array $data): array
    {
        $name = strtolower($product['name']);

        // Try to find a brand mention in the product name
        $brand = null;
        foreach ($data['brands'] as $brandName => $brandModel) {
            if ($this->mentions($name, $brandName)) {
                $brand = $brandModel;
                break;
            }
        }

        if ($brand === null) {
            return ['sku_id' => null, 'reason' => null];
        }

        // Try to find a balloon size mention
        $balloonSize = null;
        foreach ($data['balloonSizes'] as $sizeName => $sizeModel) {
            if ($sizeModel->brand_id === $brand->id && $this->mentions($name, $sizeName)) {
                $balloonSize = $sizeModel;
                break;
            }
        }

        // Try to find a color mention
        $brandColors = $data['colors']->get($brand->id, collect());
        $color = null;
        foreach ($brandColors as $colorName => $colorModel) {
            if ($this->mentions($name, $colorName)) {
                $color = $colorModel;
                break;
            }
        }

        // Need brand + size + color to even consider a match. A looser match
        // would pin a distributor URL to an arbitrary color/variant of that
        // size — i.e. send the user to buy the wrong balloon — so we'd rather
        // record a gap than guess.
        if ($balloonSize === null || $color === null) {
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
            $count = $this->parsePackCount($name);

            if ($count !== null) {
                $byCount = $candidates->where('default_count_per_bag', $count)->values();

                if ($byCount->count() === 1) {
                    return ['sku_id' => $byCount->first()->id, 'reason' => 'attribute'];
                }
            }
        }

        return ['sku_id' => null, 'reason' => null];
    }

    /**
     * Case-insensitive "name appears as a distinct token" test. Requires a
     * non-alphanumeric boundary (or string start) immediately before the
     * needle so "5-inch" doesn't match inside "15-inch" and "blue" doesn't
     * match inside a longer word. A trailing boundary is intentionally NOT
     * required so "5-inch" still matches "5-inches".
     */
    private function mentions(string $haystack, string $needle): bool
    {
        $needle = trim($needle);

        if ($needle === '') {
            return false;
        }

        return (bool) preg_match('/(?<![a-z0-9])'.preg_quote($needle, '/').'/i', $haystack);
    }

    /**
     * Parse a pack/bag count from a product name — "50ct", "50 per bag",
     * "bag of 100", etc. Returns null when no count is present.
     */
    private function parsePackCount(string $name): ?int
    {
        if (preg_match('/(\d{1,4})\s*(?:ct|count|pcs|pc|pk)\b/i', $name, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/(\d{1,4})[\s-]*per[\s-]*(?:bag|pack)/i', $name, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/(?:bag|pack)\s*of\s*(\d{1,4})/i', $name, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
