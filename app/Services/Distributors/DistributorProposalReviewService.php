<?php

namespace App\Services\Distributors;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Color;
use App\Models\Distributor;
use App\Models\DistributorCatalogProposal;
use App\Models\PackagingType;
use App\Models\Sku;
use App\Services\BarcodeLinker;
use App\Services\CatalogAttributeResolver;
use App\Services\DistributorCatalogPromoter;
use App\Support\Gtin;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

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
    /** Brand-filter sentinel for "proposals with no resolved brand". */
    public const NO_BRAND_FILTER = '__none__';

    public function __construct(
        private DistributorCatalogPromoter $promoter,
        private DistributorAttributeMatcher $matcher,
        private IdenticalSkuFinder $identicalFinder,
        private BarcodeLinker $barcodeLinker,
        private CatalogAttributeResolver $resolver,
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
                // Exact match on the resolved brand (the facet supplies the value);
                // the sentinel groups the proposals we couldn't resolve a brand for.
                fn ($q, $brand) => $brand === self::NO_BRAND_FILTER
                    ? $q->whereNull('resolved_brand_name')
                    : $q->where('resolved_brand_name', $brand),
            )
            // Pending first (they need action); within that, fully-resolved
            // "one-click" proposals first, then grouped by brand and by product
            // number so pack-size variants sit together.
            ->orderByRaw($this->statusPriorityOrder())
            ->orderByRaw($this->resolutionStateOrder())
            ->orderBy('resolved_brand_name')
            ->orderBy('normalized_sku')
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
            // Packaging is global (not brand-scoped).
            'packagingTypes' => PackagingType::orderBy('sort_order')->get(['id', 'name'])
                ->map(fn (PackagingType $p) => ['id' => $p->id, 'name' => $p->name])->all(),
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
        $proposals = DistributorCatalogProposal::pending()->get(['resolved_brand_name', 'resolution']);

        $brands = [];
        $sizes = [];
        $colors = [];

        foreach ($proposals as $proposal) {
            $detail = $proposal->resolution ?? [];
            $brand = $detail['brand'] ?? null;

            if ($brand === null) {
                continue;
            }

            if (array_key_exists('value', $brand)) {
                // Brand itself unresolved — the missing brand is what to add first;
                // its (brand-scoped) size/colour gaps aren't meaningful yet.
                if ($brand['value'] !== null) {
                    $brands[$brand['value']] = ($brands[$brand['value']] ?? 0) + 1;
                }

                continue;
            }

            $this->tallyDetailGap($sizes, $detail['size'] ?? null, $proposal->resolved_brand_name);
            $this->tallyDetailGap($colors, $detail['color'] ?? null, $proposal->resolved_brand_name);
        }

        return [
            'brands' => $this->sortGaps(array_map(fn ($value, $count) => ['value' => $value, 'count' => $count], array_keys($brands), $brands)),
            'sizes' => $this->sortGaps(array_values($sizes)),
            'colors' => $this->sortGaps(array_values($colors)),
        ];
    }

    /**
     * Pending-proposal facets for the queue header: how many proposals resolve to
     * each brand, and the full/partial/no_brand split. Cheap GROUP BYs over the
     * stored resolution columns (no matcher).
     *
     * @return array{brands: array<int, array{name: ?string, count: int}>, states: array<string, int>}
     */
    public function facets(): array
    {
        $brands = DistributorCatalogProposal::pending()
            ->selectRaw('resolved_brand_name as name, count(*) as total')
            ->groupBy('resolved_brand_name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'count' => (int) $row->total])
            ->all();

        $states = DistributorCatalogProposal::pending()
            ->selectRaw('resolution_state as state, count(*) as total')
            ->groupBy('resolution_state')
            ->pluck('total', 'state')
            ->map(fn ($total) => (int) $total)
            ->all();

        return ['brands' => $brands, 'states' => $states];
    }

    /**
     * Tally a stored resolution attribute that's still an unresolved distributor
     * value (missing reference data) against its brand.
     *
     * @param  array<string, array{value: string, brand: string, count: int}>  $bucket
     * @param  array<string, mixed>|null  $attr  a resolution detail entry ({id,name} resolved, or {value} not)
     */
    private function tallyDetailGap(array &$bucket, ?array $attr, ?string $brandName): void
    {
        if ($attr === null || ! array_key_exists('value', $attr) || $attr['value'] === null) {
            return;
        }

        $key = $brandName.'|'.$attr['value'];
        $bucket[$key] = [
            'value' => $attr['value'],
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

    /**
     * Map a proposal to an existing catalog SKU instead of creating a new one:
     * backfill the distributor's barcode onto that SKU (audited, as an admin
     * action on the shared catalog — null business), attach the distributor
     * purchase URLs, and resolve the proposal to it. Used when we already carry
     * the product but the existing SKU had no barcode (so it wasn't matched at
     * cluster time and surfaced as a proposal).
     */
    public function mapToExisting(DistributorCatalogProposal $proposal, Sku $target, string $reviewerId): void
    {
        $barcode = $this->distributorBarcode($proposal);

        if ($barcode === null) {
            throw ValidationException::withMessages([
                'barcode' => __('flash.distributor_proposals.map_no_barcode'),
            ]);
        }

        $this->barcodeLinker->link($target, $barcode, null, $reviewerId, BarcodeLinker::SOURCE_ADMIN);
        $this->promoter->attachDistributorUrls($target, $proposal);

        $proposal->forceFill([
            'status' => DistributorCatalogProposal::STATUS_APPROVED,
            'resulting_sku_id' => $target->id,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ])->save();
    }

    /**
     * The distributor's original reported barcode (12-digit UPC-A / 13-digit
     * EAN-13), not the padded canonical GTIN-14 stored on the proposal — so it
     * lands in the right column when backfilled.
     */
    private function distributorBarcode(DistributorCatalogProposal $proposal): ?string
    {
        foreach ($proposal->evidence ?? [] as $member) {
            if (! empty($member['raw_upc'])) {
                return Gtin::digitsOnly($member['raw_upc']);
            }
        }

        return null;
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
            'proposed_packaging_id' => $attributes['proposed_packaging_id'] ?? null,
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
            'packagingTypes' => PackagingType::whereIn('id', $proposals->pluck('proposed_packaging_id')->filter()->unique())
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
            'resolved_brand_name' => $proposal->resolved_brand_name,
            'resolution_state' => $proposal->resolution_state,
            'proposed_name' => $proposal->proposed_name,
            'proposed_count' => $proposal->proposed_count,
            'proposed_warehouse_sku' => $proposal->proposed_warehouse_sku,
            'proposed_brand_id' => $proposal->proposed_brand_id,
            'proposed_balloon_size_id' => $proposal->proposed_balloon_size_id,
            'proposed_color_id' => $proposal->proposed_color_id,
            'proposed_packaging_id' => $proposal->proposed_packaging_id,
            'brand_name' => $references['brands']->get($proposal->proposed_brand_id)?->name,
            'balloon_size_name' => $references['balloonSizes']->get($proposal->proposed_balloon_size_id)?->name,
            'color_name' => $references['colors']->get($proposal->proposed_color_id)?->name,
            'packaging_name' => $references['packagingTypes']->get($proposal->proposed_packaging_id)?->name,
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
        $packagingId = $proposal->proposed_packaging_id ?? data_get($guess, 'packaging.selected.id');
        $count = $proposal->proposed_count ?? ($guess['count'] ?? null);

        if ($brandId === null || $sizeId === null || $colorId === null) {
            return ['available' => false];
        }

        $match = $this->identicalFinder->find($brandId, $sizeId, $colorId, false, $count, $packagingId);
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
        $brand = $match['brand']['model'];

        return [
            'available' => true,
            'brand' => $this->presentMatch($match['brand']),
            'balloon_size' => $this->presentMatch($match['balloon_size']),
            'color' => $this->presentColor($match['color'], $brand instanceof Brand ? $brand : null, $proposal),
            'packaging' => $this->presentMatch($match['packaging']),
            'count' => $match['count'],
        ];
    }

    /**
     * Present the colour guess, mirroring {@see DistributorCatalogPromoter::resolveColor}
     * so the queue shows exactly what approving would create: an exact structured
     * match stands; otherwise the shade named in the product title is preferred
     * over a fuzzy structured family match, with the structured options kept as
     * alternates. A `source` flag marks where the suggestion came from.
     *
     * @param  array{model: ?Model, quality: string, candidates: array<int, array{id: string, name: string, quality: string}>}  $match
     * @return array<string, mixed>
     */
    private function presentColor(array $match, ?Brand $brand, DistributorCatalogProposal $proposal): array
    {
        $base = $this->presentMatch($match) + ['source' => 'structured'];

        if ($match['quality'] === 'exact' || $brand === null) {
            return $base;
        }

        $titleColor = $this->resolver->colorInText($this->proposalTitleText($proposal), $brand);

        if ($titleColor === null) {
            return $base;
        }

        return [
            'selected' => ['id' => $titleColor->id, 'name' => $titleColor->name],
            'quality' => 'exact',
            'source' => 'title',
            // Keep the structured family guesses as alternates the admin can pick.
            'candidates' => $base['candidates'],
        ];
    }

    /**
     * Every title in the cluster plus the proposed name, joined — the text the
     * title-colour resolver reads.
     */
    private function proposalTitleText(DistributorCatalogProposal $proposal): string
    {
        return collect($proposal->evidence ?? [])
            ->pluck('title')
            ->push($proposal->proposed_name)
            ->filter()
            ->implode(' ');
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

    /**
     * Fully-resolved (one-click) proposals first, then brand-only partials, then
     * the no-brand ones. NULLs (un-clustered legacy rows) sort last.
     */
    private function resolutionStateOrder(): string
    {
        return "CASE resolution_state
            WHEN 'full' THEN 0
            WHEN 'partial' THEN 1
            WHEN 'no_brand' THEN 2
            ELSE 3 END";
    }
}
