<?php

namespace App\Services;

use App\Models\DistributorCatalogProposal;
use App\Models\DistributorProduct;
use App\Models\DistributorSkuUrl;
use App\Models\Sku;
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
        $withoutUpc = [];     // members lacking a UPC

        foreach ($products as $product) {
            $member = $this->toMember($product);
            $canonical = $product->upc ? Gtin::canonicalize($product->upc) : null;

            if ($canonical !== null) {
                $member['upc'] = $canonical;
                $member['inherited_upc'] = false;
                $byUpc[$canonical][] = $member;

                if ($member['normalized_sku'] !== null && $member['normalized_sku'] !== '') {
                    $normalizedToUpcs[$member['normalized_sku']][$canonical] = true;
                }
            } else {
                $withoutUpc[] = $member;
            }
        }

        // A barcode-less listing joins a cluster only when its normalized SKU
        // points at exactly one UPC — otherwise we can't safely tell which
        // variant it is, so it stays unclustered.
        foreach ($withoutUpc as $member) {
            $normalized = $member['normalized_sku'];

            if ($normalized === null || $normalized === '' || ! isset($normalizedToUpcs[$normalized])) {
                continue;
            }

            if (count($normalizedToUpcs[$normalized]) !== 1) {
                continue;
            }

            $canonical = array_key_first($normalizedToUpcs[$normalized]);
            $member['upc'] = $canonical;
            $member['inherited_upc'] = true;
            $byUpc[$canonical][] = $member;
        }

        return collect($byUpc)->map(fn (array $members, string $upc) => [
            'upc' => $upc,
            'normalized_sku' => $this->representativeNormalizedSku($members),
            'members' => $members,
        ])->values();
    }

    /**
     * Read all staged products, cluster them, and (when executing) either attach
     * distributor URLs to existing catalog SKUs or create proposals for new ones.
     *
     * @return array{clusters: int, matched_existing: int, urls_attached: int, proposals: int, unclustered: int}
     */
    public function run(bool $execute): array
    {
        $staged = DistributorProduct::query()->get();
        $clusters = $this->buildClusters($staged);
        $upcToSkuId = $this->existingCatalogUpcMap();

        $matchedExisting = 0;
        $urlsAttached = 0;
        $proposals = 0;
        $clusteredMembers = 0;

        foreach ($clusters as $cluster) {
            $clusteredMembers += count($cluster['members']);

            $skuId = $upcToSkuId->get($cluster['upc']);

            if ($skuId !== null) {
                $matchedExisting++;

                // Attach distributor purchase URLs so the Reorder page shows
                // links for products we already catalog.
                if ($execute) {
                    $urlsAttached += $this->attachUrls($skuId, $cluster['members']);
                }

                continue;
            }

            $proposals++;

            if ($execute) {
                $this->persistProposal($cluster);
            }
        }

        return [
            'clusters' => $clusters->count(),
            'matched_existing' => $matchedExisting,
            'urls_attached' => $urlsAttached,
            'proposals' => $proposals,
            'unclustered' => $staged->count() - $clusteredMembers,
        ];
    }

    /**
     * @param  array{upc: string, normalized_sku: ?string, members: array<int, array<string, mixed>>}  $cluster
     */
    private function persistProposal(array $cluster): void
    {
        $title = $this->representativeTitle($cluster['members']);
        $count = $title !== null ? ProductText::packCount($title) : null;
        $distributorCount = collect($cluster['members'])->pluck('distributor_id')->unique()->count();

        $attributes = [
            'normalized_sku' => $cluster['normalized_sku'],
            'proposed_name' => $title,
            'proposed_count' => $count,
            'proposed_warehouse_sku' => $cluster['normalized_sku'],
            'evidence' => $this->buildEvidence($cluster['members']),
            'confidence' => ($distributorCount >= 2 && $count !== null) ? 'high' : 'low',
        ];

        $existing = DistributorCatalogProposal::where('upc', $cluster['upc'])->first();

        if ($existing !== null) {
            // Refresh the evidence/attributes but never clobber a human's
            // review decision (approved/rejected/auto_approved).
            $existing->fill($attributes)->save();

            return;
        }

        DistributorCatalogProposal::create($attributes + [
            'upc' => $cluster['upc'],
            'status' => DistributorCatalogProposal::STATUS_PENDING,
        ]);
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
            'distributor_id' => $product->distributor_id,
            'raw_sku' => $product->raw_sku,
            'normalized_sku' => $product->normalized_sku,
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
