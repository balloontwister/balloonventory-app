<?php

namespace App\Services;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Color;
use App\Models\Distributor;
use App\Models\DistributorCatalogProposal;
use App\Models\DistributorSkuUrl;
use App\Models\PackagingType;
use App\Models\Sku;
use App\Services\Distributors\ProposalPromotionResult;
use App\Support\Gtin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Materialises a catalog proposal into a real Sku (Todd's "auto-create" decision)
 * and attaches the distributor purchase URLs + latest stock.
 *
 * Deliberately conservative: a Sku is only created when brand + balloon size +
 * colour all resolve to existing reference rows AND no catalog Sku already owns
 * the UPC. Anything short of that is left `pending` for human review rather than
 * risk polluting the master catalog with a half-resolved product.
 */
class DistributorCatalogPromoter
{
    public function __construct(
        private CatalogAttributeResolver $resolver,
        private Distributors\DistributorAttributeMatcher $matcher,
        private Distributors\IdenticalSkuFinder $identicalFinder,
        private Distributors\Gs1BrandRegistry $gs1Registry,
    ) {}

    /**
     * True when the proposal can be safely AUTO-created right now (no writes).
     *
     * Beyond resolving every required attribute, an automatic (non-human) create
     * must clear the accuracy gate: identity ("high confidence") confirms a shared
     * UPC, but the attributes still need corroboration before we add a new product
     * to the master catalog. A human-vouched proposal (approved / manually mapped)
     * bypasses the gate — the reviewer is the corroboration.
     */
    public function canPromote(DistributorCatalogProposal $proposal): bool
    {
        if ($proposal->resulting_sku_id !== null || $this->upcAlreadyInCatalog($proposal)) {
            return false;
        }

        $resolved = $this->resolveAttributes($proposal);

        if ($resolved['brand'] === null || $resolved['balloonSize'] === null || $resolved['color'] === null) {
            return false;
        }

        return $this->isHumanVouched($proposal) || $this->passesAccuracyGate($proposal, $resolved);
    }

    /**
     * The auto-create accuracy gate (Todd's policy in distributor_operations_prompt.md).
     * Both checks must hold for an unattended create:
     *
     *  - Multi-source attribute agreement: ≥2 distributors expose attribute tables
     *    that resolve to the SAME brand/size/colour. A single attribute source is
     *    review material, not auto-create material — its mis-attribution would be
     *    copied faithfully.
     *  - GS1 prefix → brand sanity: the resolved brand must not contradict the
     *    barcode's known manufacturer prefix.
     *
     * @param  array{brand: ?Brand, balloonSize: ?BalloonSize, color: ?Color, packaging: ?PackagingType}  $resolved
     */
    private function passesAccuracyGate(DistributorCatalogProposal $proposal, array $resolved): bool
    {
        if (! $this->multiSourceAgrees($proposal)) {
            return false;
        }

        if ($resolved['brand'] !== null && $this->gs1Conflicts($proposal, $resolved['brand'])) {
            return false;
        }

        return true;
    }

    /**
     * Whether ≥2 distinct distributors expose an attribute table for this cluster
     * AND those tables resolve to the same brand + size + colour. Each member's
     * attributes are already stored in the proposal evidence, matched here with
     * that distributor's own alias map.
     */
    private function multiSourceAgrees(DistributorCatalogProposal $proposal): bool
    {
        $resolvedPerDistributor = [];
        $counts = [];

        foreach ($proposal->evidence ?? [] as $member) {
            if (empty($member['attributes']) || empty($member['distributor_id'])) {
                continue;
            }

            // One resolution per distributor (the first attributed listing).
            if (isset($resolvedPerDistributor[$member['distributor_id']])) {
                continue;
            }

            $config = Distributor::find($member['distributor_id'])?->config ?? [];
            $match = $this->matcher->match($member['attributes'], $config, $member['distributor_id']);

            $resolvedPerDistributor[$member['distributor_id']] = [
                'brand' => $match['brand']['model']?->id,
                'size' => $match['balloon_size']['model']?->id,
                'color' => $match['color']['model']?->id,
            ];

            if ($match['count'] !== null) {
                $counts[$member['distributor_id']] = $match['count'];
            }
        }

        if (count($resolvedPerDistributor) < 2) {
            return false;
        }

        $tuples = array_values($resolvedPerDistributor);
        $first = $tuples[0];

        // Every attribute must resolve and every source must agree.
        if ($first['brand'] === null || $first['size'] === null || $first['color'] === null) {
            return false;
        }

        foreach ($tuples as $tuple) {
            if ($tuple !== $first) {
                return false;
            }
        }

        // The pack count must not conflict either: it's part of a SKU's identity
        // (it keys identical-sibling linking), and distributors sometimes mislabel
        // it or carry a wrong barcode — e.g. a "3 per bag" listing stamped with the
        // 10-pack's UPC. Among the sources that report a count, all must agree;
        // otherwise route to review rather than auto-create a possibly-wrong pack.
        if (count(array_unique(array_values($counts))) > 1) {
            return false;
        }

        return true;
    }

    /**
     * Does the resolved brand contradict the barcode's known GS1 company prefix?
     */
    private function gs1Conflicts(DistributorCatalogProposal $proposal, Brand $brand): bool
    {
        $barcode = $this->originalUpc($proposal) ?? Gtin::digitsOnly((string) $proposal->upc);

        return $this->gs1Registry->conflictsWith($barcode, $brand->name);
    }

    /**
     * Has a human vouched for this proposal? An approved status, a recorded
     * reviewer, or any manually-pinned attribute means the accuracy gate is
     * already satisfied by the reviewer and should not block creation.
     */
    private function isHumanVouched(DistributorCatalogProposal $proposal): bool
    {
        return $proposal->status === DistributorCatalogProposal::STATUS_APPROVED
            || $proposal->reviewed_by !== null
            || $proposal->proposed_brand_id !== null
            || $proposal->proposed_balloon_size_id !== null
            || $proposal->proposed_color_id !== null
            || $proposal->proposed_packaging_id !== null;
    }

    /**
     * Evaluate + (when possible) promote a proposal for the review queue, always
     * returning a structured outcome the admin UI can act on. Unlike {@see promote()}
     * — which returns null for several distinct reasons — this distinguishes
     * "already has a Sku" / "UPC already in catalog" / "needs attribute mapping"
     * / "created", and lists exactly which attributes are still unresolved.
     */
    public function promoteForReview(DistributorCatalogProposal $proposal): ProposalPromotionResult
    {
        if ($proposal->resulting_sku_id !== null) {
            return ProposalPromotionResult::alreadyPromoted($proposal->resulting_sku_id);
        }

        if ($this->upcAlreadyInCatalog($proposal)) {
            return ProposalPromotionResult::upcConflict();
        }

        $missing = $this->missingAttributes($this->resolveAttributes($proposal));

        if ($missing !== []) {
            return ProposalPromotionResult::needsMapping($missing);
        }

        $sku = $this->promote($proposal);

        return $sku !== null
            ? ProposalPromotionResult::created($sku->id)
            : ProposalPromotionResult::needsMapping(['brand', 'balloon_size', 'color']);
    }

    /**
     * Create the Sku + distributor URLs for a proposal, or return null when it
     * isn't safely resolvable (left pending for review).
     */
    public function promote(DistributorCatalogProposal $proposal): ?Sku
    {
        if ($proposal->resulting_sku_id !== null) {
            return Sku::find($proposal->resulting_sku_id);
        }

        if ($this->upcAlreadyInCatalog($proposal)) {
            return null;
        }

        $resolved = $this->resolveAttributes($proposal);

        if ($resolved['brand'] === null || $resolved['balloonSize'] === null || $resolved['color'] === null) {
            return null;
        }

        return DB::transaction(function () use ($proposal, $resolved) {
            $sku = Sku::create([
                'name' => $proposal->proposed_name ?: $resolved['brand']->name.' '.$resolved['balloonSize']->name,
                'brand_id' => $resolved['brand']->id,
                'material_id' => $resolved['balloonSize']->material_id,
                'balloon_size_id' => $resolved['balloonSize']->id,
                'color_id' => $resolved['color']->id,
                'default_count_per_bag' => $proposal->proposed_count,
                'packaging_id' => $resolved['packaging']?->id,
                'warehouse_sku' => $proposal->proposed_warehouse_sku,
                'upc' => $this->originalUpc($proposal) ?? $proposal->upc,
                // The pipeline clusters solid latex only — a printed cluster is
                // deferred before it ever becomes a proposal — but an admin can
                // hand-classify a mixed-evidence cluster that slipped through
                // misclassified as solid. Print state is part of the identity used
                // for sibling linking (see linkIdenticalSiblings below).
                'is_printed' => (bool) $proposal->proposed_is_printed,
                'is_active' => true,
            ]);

            if ($proposal->proposed_is_printed) {
                $sku->themes()->sync($proposal->proposed_theme_ids ?? []);
                $sku->printColors()->sync($proposal->proposed_print_color_ids ?? []);
                $sku->printSides()->sync($proposal->proposed_print_side_ids ?? []);
            }

            $this->attachDistributorUrls($sku, $proposal);
            $this->linkIdenticalSiblings($sku);

            // A human-approved proposal keeps that status; an automatic
            // materialisation records auto_approved.
            $proposal->fill([
                'status' => $proposal->status === DistributorCatalogProposal::STATUS_APPROVED
                    ? DistributorCatalogProposal::STATUS_APPROVED
                    : DistributorCatalogProposal::STATUS_AUTO_APPROVED,
                'resulting_sku_id' => $sku->id,
                'reviewed_at' => $proposal->reviewed_at ?? now(),
            ])->save();

            return $sku;
        });
    }

    /**
     * Link the freshly-created SKU to any catalog SKU that is the same product in
     * a different pack count (same brand/size/colour/print state) — so the user
     * can switch between 100/50/12-count of the same balloon. Symmetric and
     * incremental, so approving each pack size builds the whole identical group.
     */
    private function linkIdenticalSiblings(Sku $sku): void
    {
        $siblings = $this->identicalFinder->find(
            $sku->brand_id,
            $sku->balloon_size_id,
            $sku->color_id,
            (bool) $sku->is_printed,
            $sku->default_count_per_bag,
            $sku->packaging_id,
        )['siblings'];

        foreach ($siblings as $sibling) {
            $sku->linkIdentical($sibling);
        }
    }

    /**
     * @return array{brand: ?Brand, balloonSize: ?BalloonSize, color: ?Color, packaging: ?PackagingType}
     */
    private function resolveAttributes(DistributorCatalogProposal $proposal): array
    {
        // Resolve from every title in the cluster, not just one — the brand
        // name often appears in only one distributor's title (e.g. "Sempertex"
        // at LA Balloons but "Betallatex" at BargainBalloons).
        $text = collect($proposal->evidence ?? [])
            ->pluck('title')
            ->push($proposal->proposed_name)
            ->filter()
            ->implode(' ');

        $resolved = $this->resolver->resolve($text);

        // The distributor's structured attribute table beats anything inferred
        // from the title — a clean "Brand: Kalisan / Size: 260 / Color: Clear"
        // resolves where prose-matching fails.
        $structured = $this->structuredMatch($proposal);

        foreach (['brand' => 'brand', 'balloonSize' => 'balloon_size'] as $key => $matchKey) {
            if ($structured[$matchKey]['model'] !== null) {
                $resolved[$key] = $structured[$matchKey]['model'];
            }
        }

        // Colour is special: a distributor's structured "Color" is often a coarse
        // family ("Green") while the real shade is in the title ("Mirror Green
        // Gold"). A non-exact structured guess always defers to the title; an
        // EXACT structured match is trusted too UNLESS the title names a more
        // specific colour that's a refinement of it (see resolveColor/
        // refineColorFromTitle) — a coarse label being technically a real,
        // separate catalog colour doesn't make it the right one for THIS product.
        $resolved['color'] = $this->resolveColor($structured['color'], $resolved['brand'], $text);

        // A human review (Edit modal) can pin any attribute to an explicit
        // reference row. Manual mappings always win over both the structured and
        // text resolution so an admin can rescue a proposal nothing else mapped.
        if ($proposal->proposed_brand_id !== null) {
            $resolved['brand'] = Brand::find($proposal->proposed_brand_id);
        }

        if ($proposal->proposed_balloon_size_id !== null) {
            $resolved['balloonSize'] = BalloonSize::find($proposal->proposed_balloon_size_id);
        }

        if ($proposal->proposed_color_id !== null) {
            $resolved['color'] = Color::find($proposal->proposed_color_id);
        }

        // Packaging (Nozzle Up / Loose / Retail …) is optional and never blocks
        // creation. It comes from the structured table, but a manual mapping in
        // the Edit modal wins like the other attributes.
        $resolved['packaging'] = $structured['packaging']['model'];

        if ($proposal->proposed_packaging_id !== null) {
            $resolved['packaging'] = PackagingType::find($proposal->proposed_packaging_id);
        }

        return $resolved;
    }

    /**
     * Re-derive the colour a promotion would assign TODAY, straight from the
     * proposal's own evidence — deliberately ignoring any stored
     * `proposed_color_id`. Used by the promoted-colour audit: a manual override on
     * an already-promoted proposal may itself be the corrupted value under
     * investigation (e.g. carried along by an edit to an unrelated field before the
     * touched-fields capture gate existed), so it can't be trusted as the baseline
     * to compare against.
     */
    public function recomputeColorFromEvidence(DistributorCatalogProposal $proposal): ?Color
    {
        $text = collect($proposal->evidence ?? [])
            ->pluck('title')
            ->push($proposal->proposed_name)
            ->filter()
            ->implode(' ');

        $structured = $this->structuredMatch($proposal);
        $brand = $structured['brand']['model'] ?? $this->resolver->resolve($text)['brand'];

        return $this->resolveColor($structured['color'], $brand, $text);
    }

    /**
     * Resolve the effective colour: a specific shade named in the title (scoped
     * to the resolved brand) is preferred over a fuzzy structured family match,
     * and even over a coarse-but-real EXACT structured match when the title
     * names a more specific refinement of it (see
     * {@see CatalogAttributeResolver::refineColorFromTitle}).
     *
     * @param  array{model: ?Model, quality: string}  $structuredColor
     */
    private function resolveColor(array $structuredColor, ?Brand $brand, string $text): ?Color
    {
        if ($brand === null) {
            return $structuredColor['model'];
        }

        return $this->resolver->refineColorFromTitle($structuredColor['model'], $structuredColor['quality'] ?? 'none', $text, $brand);
    }

    /**
     * Run the structured attribute matcher over the proposal's evidence (the
     * distributor's own attribute table), using that distributor's alias map.
     *
     * @return array{brand: array<string, mixed>, balloon_size: array<string, mixed>, color: array<string, mixed>, packaging: array<string, mixed>, count: int|null}
     */
    private function structuredMatch(DistributorCatalogProposal $proposal): array
    {
        $member = collect($proposal->evidence ?? [])->first(fn (array $m) => ! empty($m['attributes']));

        if ($member === null) {
            return $this->matcher->match([]);
        }

        $config = Distributor::find($member['distributor_id'])?->config ?? [];

        return $this->matcher->match($member['attributes'], $config, $member['distributor_id'] ?? null);
    }

    /**
     * Which of brand/size/colour are still unresolved for a proposal — drives the
     * "map these" hint in the review UI.
     *
     * @param  array{brand: ?Brand, balloonSize: ?BalloonSize, color: ?Color}  $resolved
     * @return array<int, string>
     */
    private function missingAttributes(array $resolved): array
    {
        return collect([
            'brand' => $resolved['brand'],
            'balloon_size' => $resolved['balloonSize'],
            'color' => $resolved['color'],
        ])->filter(fn ($value) => $value === null)->keys()->all();
    }

    public function attachDistributorUrls(Sku $sku, DistributorCatalogProposal $proposal): void
    {
        $rows = collect($proposal->evidence ?? [])
            ->filter(fn (array $m) => ! empty($m['distributor_id']) && ! empty($m['url']))
            ->groupBy('distributor_id')
            ->map(fn ($group) => $group->first())
            ->map(fn (array $m) => [
                'distributor_id' => $m['distributor_id'],
                'sku_id' => $sku->id,
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
    }

    private function memberInStock(array $member): ?bool
    {
        if (isset($member['stock']) && $member['stock'] !== null) {
            return $member['stock'] > 0;
        }

        return $member['in_stock'] ?? null;
    }

    /**
     * The distributor's original barcode (e.g. 12-digit UPC-A) so the new Sku's
     * upc matches catalog convention rather than the padded GTIN-14 identity.
     */
    private function originalUpc(DistributorCatalogProposal $proposal): ?string
    {
        foreach ($proposal->evidence ?? [] as $member) {
            if (! empty($member['raw_upc'])) {
                return Gtin::digitsOnly($member['raw_upc']);
            }
        }

        return null;
    }

    private function upcAlreadyInCatalog(DistributorCatalogProposal $proposal): bool
    {
        $canonical = Gtin::canonicalize($proposal->upc);

        if ($canonical === null) {
            return false;
        }

        return Sku::where(fn ($query) => $query->whereNotNull('upc')->orWhereNotNull('ean'))
            ->get(['upc', 'ean'])
            ->contains(fn (Sku $sku) => Gtin::canonicalize((string) $sku->upc) === $canonical
                || Gtin::canonicalize((string) $sku->ean) === $canonical);
    }
}
