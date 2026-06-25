<?php

namespace App\Services;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Color;
use App\Models\Distributor;
use App\Models\DistributorCatalogProposal;
use App\Models\DistributorSkuUrl;
use App\Models\Sku;
use App\Services\Distributors\ProposalPromotionResult;
use App\Support\Gtin;
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
    ) {}

    /**
     * True when the proposal can be safely auto-created right now (no writes).
     */
    public function canPromote(DistributorCatalogProposal $proposal): bool
    {
        if ($proposal->resulting_sku_id !== null || $this->upcAlreadyInCatalog($proposal)) {
            return false;
        }

        $resolved = $this->resolveAttributes($proposal);

        return $resolved['brand'] !== null
            && $resolved['balloonSize'] !== null
            && $resolved['color'] !== null;
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
                'warehouse_sku' => $proposal->proposed_warehouse_sku,
                'upc' => $this->originalUpc($proposal) ?? $proposal->upc,
                'is_active' => true,
            ]);

            $this->attachDistributorUrls($sku, $proposal);

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
     * @return array{brand: ?Brand, balloonSize: ?BalloonSize, color: ?Color}
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

        foreach (['brand' => 'brand', 'balloonSize' => 'balloon_size', 'color' => 'color'] as $key => $matchKey) {
            if ($structured[$matchKey]['model'] !== null) {
                $resolved[$key] = $structured[$matchKey]['model'];
            }
        }

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

        return $resolved;
    }

    /**
     * Run the structured attribute matcher over the proposal's evidence (the
     * distributor's own attribute table), using that distributor's alias map.
     *
     * @return array{brand: array<string, mixed>, balloon_size: array<string, mixed>, color: array<string, mixed>, count: int|null}
     */
    private function structuredMatch(DistributorCatalogProposal $proposal): array
    {
        $member = collect($proposal->evidence ?? [])->first(fn (array $m) => ! empty($m['attributes']));

        if ($member === null) {
            return $this->matcher->match([]);
        }

        $aliases = Distributor::find($member['distributor_id'])?->config['attribute_aliases'] ?? [];

        return $this->matcher->match($member['attributes'], $aliases);
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

    private function attachDistributorUrls(Sku $sku, DistributorCatalogProposal $proposal): void
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
