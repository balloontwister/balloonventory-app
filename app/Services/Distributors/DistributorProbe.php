<?php

namespace App\Services\Distributors;

use App\Models\Distributor;
use App\Services\DistributorPlatforms\BigCommerceProductPageParser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;

/**
 * Onboarding/diagnostic tool: fetch one product URL and show how that
 * distributor's page maps to our catalog — WITHOUT writing anything to staging.
 *
 * Runs the exact same per-distributor pipeline a real crawl uses (the config
 * extraction recipe → attribute table → classifier → attribute matcher), so when
 * adding a new brand you point this at a sample URL, confirm the recipe/label_map/
 * aliases resolve correctly, and only then run the bulk crawl. It's also the
 * read-only basis the health check uses to judge whether a live site still parses.
 */
class DistributorProbe
{
    private const TIMEOUT = 30;

    private const USER_AGENT = 'Balloonventory/1.0 (+https://balloonventory.com)';

    public function __construct(
        private BigCommerceProductPageParser $parser,
        private ProductAttributeTableExtractor $extractor,
        private TitleAttributeExtractor $titleExtractor,
        private DistributorProductClassifier $classifier,
        private DistributorAttributeMatcher $matcher,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function probe(Distributor $distributor, string $url): array
    {
        try {
            $response = Http::timeout(self::TIMEOUT)->withUserAgent(self::USER_AGENT)->get($url);
        } catch (\Throwable $e) {
            return ['url' => $url, 'fetched' => false, 'error' => $e->getMessage()];
        }

        if (! $response->ok()) {
            return ['url' => $url, 'fetched' => false, 'http_status' => $response->status()];
        }

        $html = $response->body();
        $config = $distributor->config ?? [];

        $parsed = $this->parser->parse($html, $config) ?? [];
        // Stores with no attribute table (havinaparty) read attributes from the
        // title + breadcrumb, exactly as the crawl path does.
        $extraction = isset($config['extraction']['title_attributes'])
            ? $this->titleExtractor->extract($parsed, $config)
            : $this->extractor->extract($html, $config);
        $match = $this->matcher->match($extraction['attributes'], $config, $distributor->id);

        return [
            'url' => $url,
            'fetched' => true,
            'title' => $parsed['title'] ?? null,
            'raw_sku' => $parsed['raw_sku'] ?? null,
            'upc' => $parsed['upc'] ?? null,
            'product_type' => $this->classifier->classify($extraction),
            'extraction' => [
                'ok' => $extraction['ok'],
                'row_count' => $extraction['row_count'],
                'missing_required' => $extraction['missing_required'],
                'has_recipe' => $extraction['has_recipe'],
            ],
            // The distributor's raw label → value(s) table, flattened for display.
            'attributes' => collect($extraction['attributes'])
                ->map(fn (array $values, string $label) => ['label' => $label, 'value' => implode(' · ', $values)])
                ->values()->all(),
            'match' => [
                'brand' => $this->presentMatch($match['brand']),
                'balloon_size' => $this->presentMatch($match['balloon_size']),
                'color' => $this->presentMatch($match['color']),
                'packaging' => $this->presentMatch($match['packaging']),
                'count' => $match['count'],
            ],
        ];
    }

    /**
     * @param  array{model: Model|null, value: ?string, quality: string, candidates: array<int, array{id: string, name: string, quality: string}>}  $match
     * @return array<string, mixed>
     */
    private function presentMatch(array $match): array
    {
        return [
            'value' => $match['value'],
            'matched' => $match['model']?->name,
            'quality' => $match['quality'],
            'candidates' => collect($match['candidates'])->pluck('name')->all(),
        ];
    }
}
