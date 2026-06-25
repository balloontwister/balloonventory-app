<?php

namespace App\Services\Distributors;

use App\Models\Distributor;

/**
 * Judges a distributor's extraction health from a crawl's results: what fraction
 * of the fetched product pages still parsed against the recipe. A healthy crawl
 * extracts almost every page; a sudden collapse means the site changed its
 * template and the recipe needs updating (otherwise we'd silently stage garbage).
 */
class DistributorHealthEvaluator
{
    /** Below this many parsed pages we can't judge — leave health unchanged. */
    public const MIN_SAMPLE = 10;

    private const HEALTHY_RATE = 0.8;

    private const DEGRADED_RATE = 0.2;

    /**
     * @return array{status: string, detail: string}|null null when the sample is too small to judge
     */
    public function evaluate(int $extractedOk, int $pagesParsed): ?array
    {
        if ($pagesParsed < self::MIN_SAMPLE) {
            return null;
        }

        $rate = $extractedOk / $pagesParsed;
        $pct = (int) round($rate * 100);
        $ratio = "{$extractedOk}/{$pagesParsed} pages ({$pct}%)";

        if ($rate >= self::HEALTHY_RATE) {
            return ['status' => Distributor::HEALTH_HEALTHY, 'detail' => "Extracted {$ratio}."];
        }

        if ($rate >= self::DEGRADED_RATE) {
            return ['status' => Distributor::HEALTH_DEGRADED, 'detail' => "Only extracted {$ratio} — the recipe may be partially off."];
        }

        return ['status' => Distributor::HEALTH_BROKEN, 'detail' => "Extracted just {$ratio} — the site template likely changed; update the extraction recipe."];
    }
}
