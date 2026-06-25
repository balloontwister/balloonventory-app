<?php

namespace App\Services\Distributors;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Color;
use App\Models\Distributor;
use App\Models\DistributorCatalogProposal;
use App\Models\Sku;
use App\Services\DistributorCatalogPromoter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Backs the distributor proposal review queue.
 *
 * The load-bearing reason this is a service and not inline controller code:
 * {@see DistributorCatalogProposal} lives on the relocatable `distributors`
 * connection and carries NO database foreign keys — its proposed_*_id columns
 * and the distributor_ids inside the evidence JSON point at rows on the primary
 * connection. So we deliberately avoid Eloquent relations (which assume one
 * connection / real FKs) and instead hydrate reference data with a second batch
 * query against the primary connection, stitched in PHP. This keeps working when
 * the `distributors` connection is later pointed at a separate database.
 */
class DistributorProposalReviewService
{
    public function __construct(
        private DistributorCatalogPromoter $promoter,
        private DistributorAttributeMatcher $matcher,
        private IdenticalSkuFinder $identicalFinder,
    ) {}

    /**
     * Paginated, hydrated proposals for the review queue.
     *
     * @param  array{status?: ?string, brand?: ?string, confidence?: ?string}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $paginator = DistributorCatalogProposal::query()
            ->when(
                ($filters['status'] ?? null),
                fn ($q, $status) => $q->where('status', $status),
            )
            ->when(
                ($filters['confidence'] ?? null),
                fn ($q, $confidence) => $q->where('confidence', $confidence),
            )
            ->when(
                ($filters['brand'] ?? null),
                // Text search across the cluster's titles. Evidence is stored as
                // JSON text, so a LIKE catches the brand name wherever it appears
                // (proposed_name or any member title).
                fn ($q, $brand) => $q->where(fn ($inner) => $inner
                    ->where('proposed_name', 'like', "%{$brand}%")
                    ->orWhere('evidence', 'like', "%{$brand}%")),
            )
            ->orderByRaw($this->statusPriorityOrder())
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        $references = $this->hydrateReferences($paginator->getCollection());

        $paginator->setCollection(
            $paginator->getCollection()->map(
                fn (DistributorCatalogProposal $p) => $this->present($p, $references),
            ),
        );

        return $paginator;
    }

    /**
     * Reference dropdown data for the Edit modal. Sizes and colours carry their
     * brand_id so the client can scope them to the chosen brand.
     *
     * @return array{brands: array<int, array<string, mixed>>, balloonSizes: array<int, array<string, mixed>>, colors: array<int, array<string, mixed>>}
     */
    public function referenceOptions(): array
    {
        return [
            'brands' => Brand::orderBy('name')->get(['id', 'name'])
                ->map(fn (Brand $b) => ['id' => $b->id, 'name' => $b->name])->all(),
            'balloonSizes' => BalloonSize::orderBy('name')->get(['id', 'name', 'brand_id'])
                ->map(fn (BalloonSize $bs) => ['id' => $bs->id, 'name' => $bs->name, 'brand_id' => $bs->brand_id])->all(),
            'colors' => Color::orderBy('name')->get(['id', 'name', 'brand_id'])
                ->map(fn (Color $c) => ['id' => $c->id, 'name' => $c->name, 'brand_id' => $c->brand_id])->all(),
        ];
    }

    /**
     * The reference rows the matcher couldn't resolve across all pending
     * proposals — the actionable "add these to the catalog" list. A present-but-
     * unmatched Brand is a missing brand; an unmatched Size/Colour under a matched
     * brand is a missing size/colour for that brand. Sorted by how many products
     * each gap blocks, so the highest-impact additions come first.
     *
     * @return array{brands: array<int, array{value: string, count: int}>, sizes: array<int, array{value: string, brand: string, count: int}>, colors: array<int, array{value: string, brand: string, count: int}>}
     */
    public function referenceGaps(): array
    {
        $proposals = DistributorCatalogProposal::pending()->get(['id', 'evidence']);

        $distributorIds = $proposals
            ->flatMap(fn (DistributorCatalogProposal $p) => collect($p->evidence ?? [])->pluck('distributor_id'))
            ->filter()->unique();
        $configs = Distributor::whereIn('id', $distributorIds)->get(['id', 'config'])->keyBy('id');

        $brands = [];
        $sizes = [];
        $colors = [];

        foreach ($proposals as $proposal) {
            $member = collect($proposal->evidence ?? [])->first(fn (array $m) => ! empty($m['attributes']));

            if ($member === null) {
                continue;
            }

            $match = $this->matcher->match($member['attributes'], $configs->get($member['distributor_id'])?->config ?? []);

            if ($match['brand']['model'] === null) {
                if ($match['brand']['value'] !== null) {
                    $brands[$match['brand']['value']] = ($brands[$match['brand']['value']] ?? 0) + 1;
                }

                // Without a brand, size/colour gaps aren't meaningful (they're
                // brand-scoped) — the missing brand is the thing to add first.
                continue;
            }

            $brandName = $match['brand']['model']->name;
            $this->tallyGap($sizes, $match['balloon_size'], $brandName);
            $this->tallyGap($colors, $match['color'], $brandName);
        }

        return [
            'brands' => $this->sortGaps(array_map(fn ($value, $count) => ['value' => $value, 'count' => $count], array_keys($brands), $brands)),
            'sizes' => $this->sortGaps(array_values($sizes)),
            'colors' => $this->sortGaps(array_values($colors)),
        ];
    }

    /**
     * @param  array<string, array{value: string, brand: string, count: int}>  $bucket
     * @param  array{model: ?Model, value: ?string}  $match
     */
    private function tallyGap(array &$bucket, array $match, string $brandName): void
    {
        if ($match['model'] !== null || $match['value'] === null) {
            return;
        }

        $key = $brandName.'|'.$match['value'];
        $bucket[$key] = [
            'value' => $match['value'],
            'brand' => $brandName,
            'count' => ($bucket[$key]['count'] ?? 0) + 1,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $gaps
     * @return array<int, array<string, mixed>>
     */
    private function sortGaps(array $gaps): array
    {
        usort($gaps, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $gaps;
    }

    /**
     * Approve a proposal and attempt to materialise it into a Sku. The proposal
     * is stamped approved regardless; the returned result says whether a Sku was
     * created or the admin still needs to map attributes.
     */
    public function approve(DistributorCatalogProposal $proposal, string $reviewerId): ProposalPromotionResult
    {
        $proposal->forceFill([
            'status' => DistributorCatalogProposal::STATUS_APPROVED,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ])->save();

        // promoteForReview reads the (now approved) proposal; the promoter keeps
        // the approved status when it creates the Sku.
        return $this->promoter->promoteForReview($proposal->refresh());
    }

    public function reject(DistributorCatalogProposal $proposal, string $reviewerId): void
    {
        $proposal->forceFill([
            'status' => DistributorCatalogProposal::STATUS_REJECTED,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ])->save();
    }

    /**
     * Apply a manual attribute mapping to a still-open proposal. Setting any
     * proposed_*_id marks the proposal human-touched, so a later re-cluster won't
     * overwrite it (see DistributorClusterEngine::isHumanTouched).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function edit(DistributorCatalogProposal $proposal, array $attributes, string $reviewerId): void
    {
        $proposal->forceFill([
            'proposed_brand_id' => $attributes['proposed_brand_id'] ?? null,
            'proposed_balloon_size_id' => $attributes['proposed_balloon_size_id'] ?? null,
            'proposed_color_id' => $attributes['proposed_color_id'] ?? null,
            'proposed_count' => $attributes['proposed_count'] ?? null,
            'proposed_warehouse_sku' => $attributes['proposed_warehouse_sku'] ?? null,
            'reviewed_by' => $reviewerId,
        ])->save();
    }

    /**
     * Batch-load every reference + distributor row referenced by a page of
     * proposals, from the primary connection, keyed by id.
     *
     * @param  Collection<int, DistributorCatalogProposal>  $proposals
     * @return array{brands: Collection, balloonSizes: Collection, colors: Collection, skus: Collection, distributors: Collection}
     */
    private function hydrateReferences(Collection $proposals): array
    {
        $distributorIds = $proposals
            ->flatMap(fn (DistributorCatalogProposal $p) => collect($p->evidence ?? [])->pluck('distributor_id'))
            ->filter()->unique()->values();

        return [
            'brands' => Brand::whereIn('id', $proposals->pluck('proposed_brand_id')->filter()->unique())
                ->get(['id', 'name'])->keyBy('id'),
            'balloonSizes' => BalloonSize::whereIn('id', $proposals->pluck('proposed_balloon_size_id')->filter()->unique())
                ->get(['id', 'name'])->keyBy('id'),
            'colors' => Color::whereIn('id', $proposals->pluck('proposed_color_id')->filter()->unique())
                ->get(['id', 'name'])->keyBy('id'),
            'skus' => Sku::whereIn('id', $proposals->pluck('resulting_sku_id')->filter()->unique())
                ->get(['id', 'name'])->keyBy('id'),
            'distributors' => Distributor::whereIn('id', $distributorIds)
                ->get(['id', 'name', 'config'])->keyBy('id'),
        ];
    }

    /**
     * Flatten one proposal into the array shape the Vue page consumes, resolving
     * ids to names from the pre-loaded reference maps.
     *
     * @param  array{brands: Collection, balloonSizes: Collection, colors: Collection, skus: Collection, distributors: Collection}  $references
     * @return array<string, mixed>
     */
    private function present(DistributorCatalogProposal $proposal, array $references): array
    {
        $evidence = collect($proposal->evidence ?? [])->map(fn (array $member) => [
            'distributor_id' => $member['distributor_id'] ?? null,
            'distributor_name' => $references['distributors']->get($member['distributor_id'] ?? '')?->name,
            'raw_sku' => $member['raw_sku'] ?? null,
            'title' => $member['title'] ?? null,
            'url' => $member['url'] ?? null,
            'price' => $member['price'] ?? null,
            'stock' => $member['stock'] ?? null,
            'in_stock' => $member['in_stock'] ?? null,
            'inherited_upc' => $member['inherited_upc'] ?? false,
        ])->values();

        $guess = $this->guessFor($proposal, $references['distributors']);

        return [
            'id' => $proposal->id,
            'upc' => $proposal->upc,
            'normalized_sku' => $proposal->normalized_sku,
            'status' => $proposal->status,
            'confidence' => $proposal->confidence,
            'proposed_name' => $proposal->proposed_name,
            'proposed_count' => $proposal->proposed_count,
            'proposed_warehouse_sku' => $proposal->proposed_warehouse_sku,
            'proposed_brand_id' => $proposal->proposed_brand_id,
            'proposed_balloon_size_id' => $proposal->proposed_balloon_size_id,
            'proposed_color_id' => $proposal->proposed_color_id,
            'brand_name' => $references['brands']->get($proposal->proposed_brand_id)?->name,
            'balloon_size_name' => $references['balloonSizes']->get($proposal->proposed_balloon_size_id)?->name,
            'color_name' => $references['colors']->get($proposal->proposed_color_id)?->name,
            'resulting_sku_id' => $proposal->resulting_sku_id,
            'resulting_sku_name' => $references['skus']->get($proposal->resulting_sku_id)?->name,
            'reviewed_at' => $proposal->reviewed_at,
            'distributor_count' => $evidence->pluck('distributor_id')->filter()->unique()->count(),
            'guess' => $guess,
            'catalog_match' => $this->catalogMatchFor($proposal, $guess),
            'evidence' => $evidence,
        ];
    }

    /**
     * What approving this proposal would do against the existing catalog, using
     * the effective attributes (manual mapping, else the matcher guess):
     *  - `exact`    — a same-count product already exists (approving would create
     *                 a duplicate; `has_barcode` says whether it needs the barcode).
     *  - `siblings` — the same product in other pack counts, which the new SKU
     *                 will be linked to as identical.
     * Solid latex only for now, so the lookup is scoped to is_printed = false.
     *
     * @param  array<string, mixed>  $guess
     * @return array<string, mixed>
     */
    private function catalogMatchFor(DistributorCatalogProposal $proposal, array $guess): array
    {
        $brandId = $proposal->proposed_brand_id ?? data_get($guess, 'brand.selected.id');
        $sizeId = $proposal->proposed_balloon_size_id ?? data_get($guess, 'balloon_size.selected.id');
        $colorId = $proposal->proposed_color_id ?? data_get($guess, 'color.selected.id');
        $count = $proposal->proposed_count ?? ($guess['count'] ?? null);

        if ($brandId === null || $sizeId === null || $colorId === null) {
            return ['available' => false];
        }

        $match = $this->identicalFinder->find($brandId, $sizeId, $colorId, false, $count);
        $exact = $match['exact'];

        return [
            'available' => true,
            'exact' => $exact !== null
                ? ['id' => $exact->id, 'name' => $exact->name, 'has_barcode' => $exact->upc !== null || $exact->ean !== null]
                : null,
            'siblings' => $match['siblings']
                ->map(fn ($sku) => ['id' => $sku->id, 'name' => $sku->name, 'count' => $sku->default_count_per_bag])
                ->all(),
        ];
    }

    /**
     * The matcher's read of the distributor's structured attribute table —
     * the suggested brand/size/colour/count plus alternates — so the reviewer
     * sees what we'd map this to (and what's still ambiguous) without approving.
     *
     * @param  Collection<string, Distributor>  $distributors
     * @return array<string, mixed>
     */
    private function guessFor(DistributorCatalogProposal $proposal, Collection $distributors): array
    {
        $member = collect($proposal->evidence ?? [])->first(fn (array $m) => ! empty($m['attributes']));

        if ($member === null) {
            return ['available' => false];
        }

        $config = $distributors->get($member['distributor_id'])?->config ?? [];
        $match = $this->matcher->match($member['attributes'], $config);

        return [
            'available' => true,
            'brand' => $this->presentMatch($match['brand']),
            'balloon_size' => $this->presentMatch($match['balloon_size']),
            'color' => $this->presentMatch($match['color']),
            'count' => $match['count'],
        ];
    }

    /**
     * @param  array{model: ?Model, quality: string, candidates: array<int, array{id: string, name: string, quality: string}>}  $match
     * @return array<string, mixed>
     */
    private function presentMatch(array $match): array
    {
        return [
            'selected' => $match['model'] !== null
                ? ['id' => $match['model']->id, 'name' => $match['model']->name]
                : null,
            'quality' => $match['quality'],
            'candidates' => $match['candidates'],
        ];
    }

    /**
     * Pending proposals first (they need action), then auto-approved, then the
     * resolved ones. SQLite + MySQL both accept this CASE expression.
     */
    private function statusPriorityOrder(): string
    {
        return "CASE status
            WHEN 'pending' THEN 0
            WHEN 'auto_approved' THEN 1
            WHEN 'approved' THEN 2
            WHEN 'rejected' THEN 3
            ELSE 4 END";
    }
}
