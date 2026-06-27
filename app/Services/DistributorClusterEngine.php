<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Distributor;
use App\Models\DistributorCatalogProposal;
use App\Models\DistributorProduct;
use App\Models\DistributorSkuUrl;
use App\Models\Sku;
use App\Services\Distributors\DistributorProductClassifier;
use App\Services\Distributors\ProposalResolver;
use App\Support\Gtin;
use App\Support\ProductText;
use Illuminate\Support\Collection;

/**
 * Groups staged distributor listings into cross-distributor product clusters and
 * turns the new-to-us ones into catalog proposals.
 *
 * Clustering is UPC-gated (Todd's decision): a cluster's identity is a canonical
 * GTIN-14, so it only forms when ≥1 distributor exposes a barcode. A barcode-less
 * listing (e.g. havinaparty) joins a cluster by its normalized SKU — but only
 * when that normalized SKU maps to exactly ONE UPC, so the 100-ct (53012 →
 * 030625530125) and 10-ct (53012 → a different UPC) never get merged.
 *
 * Materialising an approved proposal into a real Sku (attribute resolution +
 * creation) is a separate promoter step; this engine only produces/refreshes
 * the proposals that feed the review queue.
 */
class DistributorClusterEngine
{
    /** @var Collection<string, Distributor> distributor configs, keyed by id, for resolution */
    private Collection $configs;

    /** @var Collection<string, array<int, array{sku_id: string, brand_id: ?string}>>|null memoised warehouse_sku index */
    private ?Collection $warehouseSkuIndex = null;

    /** @var Collection<string, string>|null memoised lowercased-brand-name => brand_id */
    private ?Collection $brandNameIndex = null;

    public function __construct(private ProposalResolver $proposalResolver) {}

    /**
     * Build clusters from staged products without touching the database.
     *
     * @param  iterable<DistributorProduct>  $products
     * @return Collection<int, array{upc: string, normalized_sku: ?string, members: array<int, array<string, mixed>>}>
     */
    public function buildClusters(iterable $products): Collection
    {
        $byUpc = [];          // canonical UPC => member[]
        $normalizedToUpcs = []; // normalized_sku => [canonicalUpc => true]
        $upcBrands = [];      // canonical UPC => [lowercased brand => true] of its barcoded members
        $withoutUpc = [];     // members lacking a UPC

        foreach ($products as $product) {
            $member = $this->toMember($product);
            $canonical = $product->upc ? Gtin::canonicalize($product->upc) : null;

            if ($canonical !== null) {
                $member['upc'] = $canonical;
                $member['inherited_upc'] = false;
                $byUpc[$canonical][] = $member;

                if (($brand = $this->memberBrand($member)) !== null) {
                    $upcBrands[$canonical][$brand] = true;
                }

                if ($member['normalized_sku'] !== null && $member['normalized_sku'] !== '') {
                    $normalizedToUpcs[$member['normalized_sku']][$canonical] = true;
                }
            } else {
                $withoutUpc[] = $member;
            }
        }

        // A barcode-less listing joins a cluster only when its normalized SKU
        // points at exactly one UPC AND its brand matches that product's brand.
        // The brand gate is essential: bare item numbers aren't namespaced across
        // manufacturers (Elitex latex #36683 vs an Anagram foil #36683), so without
        // it a no-/wrong-brand row inherits an unrelated UPC, contaminating the
        // cluster, hijacking its name, and faking multi-distributor confidence.
        foreach ($withoutUpc as $member) {
            $normalized = $member['normalized_sku'];

            if ($normalized === null || $normalized === '' || ! isset($normalizedToUpcs[$normalized])) {
                continue;
            }

            if (count($normalizedToUpcs[$normalized]) !== 1) {
                continue;
            }

            $canonical = array_key_first($normalizedToUpcs[$normalized]);

            // Must carry a brand that matches the barcoded product's brand. A
            // member with no brand can't be confirmed as the same product.
            $brand = $this->memberBrand($member);
            if ($brand === null || ! isset($upcBrands[$canonical][$brand])) {
                continue;
            }

            $member['upc'] = $canonical;
            $member['inherited_upc'] = true;
            $byUpc[$canonical][] = $member;
        }

        return collect($byUpc)->map(fn (array $members, string $upc) => [
            'upc' => $upc,
            'normalized_sku' => $this->representativeNormalizedSku($members),
            'product_type' => $this->representativeProductType($members),
            'members' => $members,
        ])->values();
    }

    /**
     * Read all staged products, cluster them, and (when executing) either attach
     * distributor URLs to existing catalog SKUs or create proposals for new ones.
     *
     * @return array{clusters: int, matched_existing: int, urls_attached: int, proposals: int, deferred: int, deferred_by_type: array<string, int>, unclustered: int}
     */
    public function run(bool $execute): array
    {
        $staged = DistributorProduct::active()->get();
        $clusters = $this->buildClusters($staged);
        $upcToSkuId = $this->existingCatalogUpcMap();
        $this->configs = Distributor::all(['id', 'config'])->keyBy('id');

        $matchedExisting = 0;
        $urlsAttached = 0;
        $proposals = 0;
        $deferredByType = [];
        $clusteredMembers = 0;

        foreach ($clusters as $cluster) {
            $clusteredMembers += count($cluster['members']);

            $skuId = $upcToSkuId->get($cluster['upc']);

            if ($skuId !== null) {
                $matchedExisting++;

                // Attach distributor purchase URLs so the Reorder page shows
                // links for products we already catalog — regardless of type,
                // since a matched SKU is one we've already decided to carry.
                if ($execute) {
                    $urlsAttached += $this->attachUrls($skuId, $cluster['members']);
                }

                continue;
            }

            // We only create proposals for product types we can currently add to
            // the catalog. Everything else stays staged (with its attributes) and
            // is counted here so the admin can see what's parked, ready to enable
            // when we support that type — no re-crawl needed.
            if (! $this->isProposalEligible($cluster['product_type'])) {
                $type = $cluster['product_type'] ?? DistributorProductClassifier::UNKNOWN;
                $deferredByType[$type] = ($deferredByType[$type] ?? 0) + 1;

                continue;
            }

            $proposals++;

            if ($execute) {
                $this->persistProposal($cluster);
            }
        }

        // Warehouse-SKU rescue: a barcode-less listing that never clustered (no
        // sibling UPC to inherit) can still match a catalog SKU directly when the
        // distributor's on-page SKU equals our warehouse_sku — the only way to
        // reach a catalog SKU we hold WITHOUT a barcode. Attach a purchase link;
        // never create a SKU (no UPC = no identity to create from).
        $clusteredIds = $clusters
            ->flatMap(fn (array $cluster) => collect($cluster['members'])->pluck('id'))
            ->filter()
            ->flip();

        $matchedByWarehouseSku = 0;

        foreach ($staged as $product) {
            if ($product->upc !== null || $clusteredIds->has($product->id)) {
                continue;
            }

            $config = $this->configs->get($product->distributor_id)?->config ?? [];

            if (! ($config['match_by_warehouse_sku'] ?? false)) {
                continue;
            }

            $skuId = $this->matchWarehouseSku($product);

            if ($skuId === null) {
                continue;
            }

            $matchedByWarehouseSku++;

            if ($execute) {
                $urlsAttached += $this->attachUrls($skuId, [$this->toMember($product)]);
            }
        }

        return [
            'clusters' => $clusters->count(),
            'matched_existing' => $matchedExisting,
            'urls_attached' => $urlsAttached,
            'proposals' => $proposals,
            'deferred' => array_sum($deferredByType),
            'deferred_by_type' => $deferredByType,
            'matched_by_warehouse_sku' => $matchedByWarehouseSku,
            'unclustered' => $staged->count() - $clusteredMembers - $matchedByWarehouseSku,
        ];
    }

    /**
     * Match a barcode-less staged product to an existing catalog SKU by
     * warehouse_sku, scoped to its brand so a bare item number can't collide
     * across manufacturers. Returns the sku_id only on a single unambiguous
     * match, else null.
     */
    private function matchWarehouseSku(DistributorProduct $product): ?string
    {
        $sku = $product->normalized_sku ?: $product->raw_sku;

        if ($sku === null || $sku === '') {
            return null;
        }

        $candidates = $this->warehouseSkuIndex()->get($sku)
            ?? $this->warehouseSkuIndex()->get($product->raw_sku);

        if (empty($candidates)) {
            return null;
        }

        $brandName = $product->raw_data['attributes']['Brand'][0] ?? null;
        $brandId = $brandName !== null
            ? $this->brandNameIndex()->get(mb_strtolower(trim((string) $brandName)))
            : null;

        if ($brandId !== null) {
            $candidates = array_values(array_filter(
                $candidates,
                fn (array $c) => $c['brand_id'] === $brandId,
            ));
        }

        // Attach only on a single unambiguous match.
        return count($candidates) === 1 ? $candidates[0]['sku_id'] : null;
    }

    /**
     * warehouse_sku => list of {sku_id, brand_id} for every catalog SKU that has
     * one. Memoised for the run.
     *
     * @return Collection<string, array<int, array{sku_id: string, brand_id: ?string}>>
     */
    private function warehouseSkuIndex(): Collection
    {
        return $this->warehouseSkuIndex ??= Sku::whereNotNull('warehouse_sku')
            ->get(['id', 'warehouse_sku', 'brand_id'])
            ->groupBy('warehouse_sku')
            ->map(fn (Collection $group) => $group
                ->map(fn (Sku $sku) => ['sku_id' => $sku->id, 'brand_id' => $sku->brand_id])
                ->all());
    }

    /**
     * Lowercased brand name => brand_id. Memoised for the run.
     *
     * @return Collection<string, string>
     */
    private function brandNameIndex(): Collection
    {
        return $this->brandNameIndex ??= Brand::get(['id', 'name'])
            ->mapWithKeys(fn (Brand $brand) => [mb_strtolower($brand->name) => $brand->id]);
    }

    /**
     * Solid latex is the only type we materialise into the catalog today. Every
     * other type — and anything we couldn't classify (null: e.g. a fetch failure,
     * or a platform whose pages we don't yet read a structured table from) — is
     * parked in staging rather than proposed, so the queue is strictly the
     * products we can actually add. Nothing is lost; parked items keep their
     * attributes and surface in the deferred-by-type counts.
     */
    private function isProposalEligible(?string $productType): bool
    {
        return $productType === DistributorProductClassifier::SOLID_LATEX;
    }

    /**
     * The product type for a cluster. Cross-distributor members of one UPC are the
     * same product, so a *confident* classification (anything other than unknown /
     * non_balloon) is authoritative and wins over a member that merely couldn't be
     * classified — otherwise a distributor whose page we read weakly (havinaparty,
     * no attribute table) could, by appearing first, demote a real solid-latex
     * cluster another distributor classified correctly. Falls back to the first
     * weak type, then null.
     *
     * @param  array<int, array<string, mixed>>  $members
     */
    private function representativeProductType(array $members): ?string
    {
        $weak = [DistributorProductClassifier::UNKNOWN, DistributorProductClassifier::NON_BALLOON];
        $fallback = null;

        foreach ($members as $member) {
            $type = $member['product_type'] ?? null;

            if (empty($type)) {
                continue;
            }

            if (! in_array($type, $weak, true)) {
                return $type;
            }

            $fallback ??= $type;
        }

        return $fallback;
    }

    /**
     * @param  array{upc: string, normalized_sku: ?string, members: array<int, array<string, mixed>>}  $cluster
     */
    private function persistProposal(array $cluster): void
    {
        $title = $this->representativeTitle($cluster['members']);
        // Confidence counts only distributors that contributed ATTRIBUTES — a bare
        // barcoded row (no brand) corroborates nothing, so it must not lift a
        // single-source cluster to "high".
        $distributorCount = collect($cluster['members'])
            ->filter(fn (array $m) => $this->memberBrand($m) !== null)
            ->pluck('distributor_id')->unique()->count();

        $config = $this->configFor($cluster['members']);
        $resolution = $this->proposalResolver->resolve($cluster['members'], $config);

        // Prefer the distributor's structured "Quantity" (clean) over a count parsed
        // from the title — it sets the SKU's pack size and keys identical-sibling
        // linking, so a wrong count is costly once a SKU exists.
        $count = $resolution['count'] ?? ($title !== null ? ProductText::packCount($title) : null);

        $attributes = [
            'normalized_sku' => $cluster['normalized_sku'],
            'proposed_name' => $title,
            'proposed_count' => $count,
            'proposed_warehouse_sku' => $cluster['normalized_sku'],
            'evidence' => $this->buildEvidence($cluster['members']),
            'confidence' => ($distributorCount >= 2 && $count !== null) ? 'high' : 'low',
            'resolved_brand_id' => $resolution['brand_id'],
            'resolved_brand_name' => $resolution['brand_name'],
            'resolution_state' => $resolution['state'],
            'resolution' => $resolution['detail'],
        ];

        $existing = DistributorCatalogProposal::where('upc', $cluster['upc'])->first();

        if ($existing !== null) {
            if ($this->isHumanTouched($existing)) {
                // A human has reviewed or edited this proposal. Refresh the
                // provenance (evidence) and the derived resolution so grouping
                // stays current, but preserve their proposed attributes and
                // decision.
                $existing->fill([
                    'evidence' => $attributes['evidence'],
                    'resolved_brand_id' => $attributes['resolved_brand_id'],
                    'resolved_brand_name' => $attributes['resolved_brand_name'],
                    'resolution_state' => $attributes['resolution_state'],
                    'resolution' => $attributes['resolution'],
                ])->save();

                return;
            }

            // Untouched, still-pending proposal: safe to refresh everything.
            $existing->fill($attributes)->save();

            return;
        }

        DistributorCatalogProposal::create($attributes + [
            'upc' => $cluster['upc'],
            'status' => DistributorCatalogProposal::STATUS_PENDING,
        ]);
    }

    /**
     * The distributor config of the cluster's representative (first attributed)
     * member — the alias/label/quirk map the matcher needs to resolve attributes.
     *
     * @param  array<int, array<string, mixed>>  $members
     * @return array<string, mixed>
     */
    private function configFor(array $members): array
    {
        $member = collect($members)->first(fn (array $m) => ! empty($m['attributes']));

        if ($member === null) {
            return [];
        }

        return $this->configs->get($member['distributor_id'])?->config ?? [];
    }

    /**
     * Has an admin reviewed or manually mapped this proposal? Once true, a
     * re-cluster must not overwrite their proposed attributes or decision —
     * a non-pending status, a recorded reviewer, or any manually-set attribute
     * FK all count as a human touch.
     */
    private function isHumanTouched(DistributorCatalogProposal $proposal): bool
    {
        return $proposal->status !== DistributorCatalogProposal::STATUS_PENDING
            || $proposal->reviewed_by !== null
            || $proposal->proposed_brand_id !== null
            || $proposal->proposed_balloon_size_id !== null
            || $proposal->proposed_color_id !== null
            || $proposal->proposed_packaging_id !== null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $members
     * @return array<int, array<string, mixed>>
     */
    private function buildEvidence(array $members): array
    {
        return collect($members)->map(fn (array $m) => [
            'distributor_id' => $m['distributor_id'],
            'raw_sku' => $m['raw_sku'],
            'normalized_sku' => $m['normalized_sku'],
            'attributes' => $m['attributes'] ?? [],
            'raw_upc' => $m['raw_upc'],
            'title' => $m['title'],
            'url' => $m['url'],
            'price' => $m['price'],
            'stock' => $m['stock'],
            'in_stock' => $m['in_stock'],
            'inherited_upc' => $m['inherited_upc'],
        ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function toMember(DistributorProduct $product): array
    {
        return [
            'id' => $product->id,
            'distributor_id' => $product->distributor_id,
            'raw_sku' => $product->raw_sku,
            'normalized_sku' => $product->normalized_sku,
            'product_type' => $product->product_type,
            // The distributor's structured attribute table (Brand/Size/Colour/…),
            // carried into the proposal so the review queue can match + display it.
            'attributes' => $product->raw_data['attributes'] ?? [],
            'raw_upc' => $product->upc, // the distributor's original barcode, as reported
            'title' => $product->title,
            'url' => $product->url,
            'price' => $product->price,
            'stock' => $product->stock,
            'in_stock' => $product->in_stock,
            'upc' => null,
            'inherited_upc' => false,
        ];
    }

    /**
     * A member's brand, lowercased, from its extracted attributes (label "Brand",
     * matched case-insensitively). Null when the member carries no brand — which,
     * for UPC inheritance, means we can't confirm it's the same product.
     *
     * @param  array<string, mixed>  $member
     */
    private function memberBrand(array $member): ?string
    {
        foreach ($member['attributes'] ?? [] as $label => $values) {
            if (strcasecmp((string) $label, 'Brand') === 0) {
                $value = trim((string) ($values[0] ?? ''));

                return $value !== '' ? mb_strtolower($value) : null;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $members
     */
    private function representativeNormalizedSku(array $members): ?string
    {
        foreach ($members as $member) {
            if ($member['normalized_sku'] !== null && $member['normalized_sku'] !== '') {
                return $member['normalized_sku'];
            }
        }

        return null;
    }

    /**
     * Longest non-empty title across the members — distributors with richer
     * titles (BargainBalloons, LA Balloons) usually win over terse slugs.
     *
     * @param  array<int, array<string, mixed>>  $members
     */
    private function representativeTitle(array $members): ?string
    {
        return collect($members)
            ->pluck('title')
            ->filter(fn (?string $t) => $t !== null && $t !== '')
            ->sortByDesc(fn (string $t) => strlen($t))
            ->first();
    }

    /**
     * Canonical GTIN-14 → sku_id for every barcoded SKU already in the catalog.
     * Grouped where() keeps the SoftDeletes scope from being short-circuited by
     * OR precedence. When the same canonical UPC maps to multiple SKUs (rare —
     * duplicate barcode), the first one wins for URL attachment.
     */
    private function existingCatalogUpcMap(): Collection
    {
        $map = collect();

        Sku::where(fn ($query) => $query->whereNotNull('upc')->orWhereNotNull('ean'))
            ->get(['id', 'upc', 'ean'])
            ->each(function (Sku $sku) use ($map) {
                foreach ([$sku->upc, $sku->ean] as $code) {
                    if ($code && ($canonical = Gtin::canonicalize($code)) !== null) {
                        $map->put($canonical, $sku->id);
                    }
                }
            });

        return $map;
    }

    /**
     * Upsert distributor_sku_urls rows for every member of a cluster that
     * matched an existing catalog SKU — so the Reorder page shows purchase links.
     *
     * @param  array<int, array<string, mixed>>  $members
     * @return int Number of URL rows upserted
     */
    private function attachUrls(string $skuId, array $members): int
    {
        $rows = collect($members)
            ->filter(fn (array $m) => ! empty($m['distributor_id']) && ! empty($m['url']))
            ->groupBy('distributor_id')
            ->map(fn ($group) => $group->first())
            ->map(fn (array $m) => [
                'distributor_id' => $m['distributor_id'],
                'sku_id' => $skuId,
                'url' => $m['url'],
                'price' => $m['price'] ?? null,
                'currency' => 'USD',
                'in_stock' => $this->memberInStock($m),
                'last_checked_at' => now(),
            ])
            ->values()
            ->all();

        if ($rows !== []) {
            DistributorSkuUrl::upsert(
                $rows,
                ['distributor_id', 'sku_id'],
                ['url', 'price', 'currency', 'in_stock', 'last_checked_at'],
            );
        }

        return count($rows);
    }

    private function memberInStock(array $member): ?bool
    {
        if (isset($member['stock']) && $member['stock'] !== null) {
            return $member['stock'] > 0;
        }

        return $member['in_stock'] ?? null;
    }
}
