<?php

namespace App\Services;

use App\Models\DistributorCatalogProposal;
use App\Models\DistributorProduct;
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
     * Read all staged products, cluster them, and (when executing) upsert a
     * catalog proposal for every cluster whose UPC isn't already in the catalog.
     *
     * @return array{clusters: int, matched_existing: int, proposals: int, unclustered: int}
     */
    public function run(bool $execute): array
    {
        $staged = DistributorProduct::query()->get();
        $clusters = $this->buildClusters($staged);
        $existingUpcs = $this->existingCatalogUpcs();

        $matchedExisting = 0;
        $proposals = 0;
        $clusteredMembers = 0;

        foreach ($clusters as $cluster) {
            $clusteredMembers += count($cluster['members']);

            if ($existingUpcs->has($cluster['upc'])) {
                $matchedExisting++;

                continue; // already a catalog product — not a new-product proposal
            }

            $proposals++;

            if ($execute) {
                $this->persistProposal($cluster);
            }
        }

        return [
            'clusters' => $clusters->count(),
            'matched_existing' => $matchedExisting,
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
     * Canonical GTIN-14s already represented in the catalog, so the engine only
     * proposes genuinely new products. Grouped where() keeps the SoftDeletes
     * scope from being short-circuited by OR precedence.
     */
    private function existingCatalogUpcs(): Collection
    {
        $set = collect();

        Sku::where(fn ($query) => $query->whereNotNull('upc')->orWhereNotNull('ean'))
            ->get(['upc', 'ean'])
            ->each(function (Sku $sku) use ($set) {
                foreach ([$sku->upc, $sku->ean] as $code) {
                    if ($code && ($canonical = Gtin::canonicalize($code)) !== null) {
                        $set->put($canonical, true);
                    }
                }
            });

        return $set;
    }
}
