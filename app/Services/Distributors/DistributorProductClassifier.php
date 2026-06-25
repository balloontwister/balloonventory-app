<?php

namespace App\Services\Distributors;

/**
 * Classifies a staged distributor product into a catalog product type from its
 * extracted attribute table (see {@see ProductAttributeTableExtractor}).
 *
 * We currently only build catalog SKUs for solid latex. Everything else
 * (foil, printed, plastic, assortments, accessories) is captured in staging with
 * its full attributes but parked — it stays in the system, classified, ready to
 * activate when we add support for that type, without re-crawling. Pages with no
 * attribute table at all are non-products (Larocks also sells magic/novelty
 * items) and are marked so they never become proposals.
 */
class DistributorProductClassifier
{
    public const SOLID_LATEX = 'solid_latex';

    public const FOIL = 'foil';

    public const PRINTED = 'printed';

    public const PLASTIC = 'plastic';

    public const ASSORTMENT = 'assortment';

    public const ACCESSORY = 'accessory';

    public const NON_BALLOON = 'non_balloon';

    public const UNKNOWN = 'unknown';

    /**
     * @param  array{has_recipe: bool, attributes: array<string, array<int, string>>, row_count: int, ok: bool, missing_required: array<int, string>}  $extraction
     */
    public function classify(array $extraction): string
    {
        // No attribute table → not a catalog balloon product (novelty/magic).
        if (! ($extraction['has_recipe'] ?? false) || ($extraction['row_count'] ?? 0) === 0) {
            return self::NON_BALLOON;
        }

        $attributes = $extraction['attributes'] ?? [];
        $industry = strtolower($this->first($attributes, 'Industry') ?? '');
        $material = strtolower($this->first($attributes, 'Balloon Material') ?? '');
        $color = strtolower($this->first($attributes, 'Color') ?? '');
        $theme = $this->first($attributes, 'Occasion / Theme');

        // Explicitly outside the balloon line → an accessory/other we don't carry
        // yet (inflators, ribbons, …).
        if ($industry !== '' && ! str_contains($industry, 'balloon')) {
            return self::ACCESSORY;
        }

        if (str_contains($material, 'foil')) {
            return self::FOIL;
        }

        if (str_contains($material, 'plastic') || str_contains($material, 'bubble')) {
            return self::PLASTIC;
        }

        // Mixed-colour packs are a deferred special case in our catalog.
        if ($color === 'assortment') {
            return self::ASSORTMENT;
        }

        if (str_contains($material, 'latex')) {
            return ($theme !== null && $theme !== '') ? self::PRINTED : self::SOLID_LATEX;
        }

        return self::UNKNOWN;
    }

    /**
     * First value of a label, matched case-insensitively.
     *
     * @param  array<string, array<int, string>>  $attributes
     */
    private function first(array $attributes, string $label): ?string
    {
        foreach ($attributes as $key => $values) {
            if (strcasecmp($key, $label) === 0) {
                return $values[0] ?? null;
            }
        }

        return null;
    }
}
