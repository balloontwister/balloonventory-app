<?php

namespace App\Services;

use App\Models\Distributor;
use App\Models\DistributorProduct;
use App\Services\DistributorPlatforms\BigCommerceProductPageParser;
use App\Services\DistributorPlatforms\Concerns\InspectsHttpResponses;
use App\Services\DistributorPlatforms\PlatformFactory;
use App\Services\Distributors\DistributorProductClassifier;
use App\Services\Distributors\ProductAttributeTableExtractor;
use App\Support\Gtin;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Writes raw distributor listings into the distributor_products staging table.
 *
 * Two paths, one service:
 *  - Shopify: bulk JSON API via the existing ShopifyAdapter, fast (~15k in ~30s).
 *  - BigCommerce single-page fetch: HTTP GET → BigCommerceProductPageParser →
 *    upsert. Used by the batch/resume crawl command, NOT for bulk sitemap loops
 *    (those are orchestrated by the command itself).
 *
 * The staging table is invisible to users — it feeds the cluster engine, which
 * groups by UPC and creates proposals for genuinely new products.
 */
class DistributorProductIngestor
{
    use InspectsHttpResponses;

    private const CHUNK_SIZE = 500;

    private const TIMEOUT = 30;

    private const USER_AGENT = 'Balloonventory/1.0 (+https://balloonventory.com)';

    public function __construct(
        private DistributorSkuNormalizer $normalizer,
        private PlatformFactory $platformFactory,
        private ProductAttributeTableExtractor $attributeExtractor,
        private DistributorProductClassifier $classifier,
    ) {}

    /**
     * Shopify: fetch from the bulk JSON API and upsert everything into staging.
     *
     * Returns stats: fetched, staged, fetch_report, complete.
     *
     * @return array{fetched: int, staged: int, report: array, complete: bool}
     */
    public function ingestShopify(Distributor $distributor, bool $execute, ?int $limit = null): array
    {
        $adapter = $this->platformFactory->make($distributor);
        $products = $adapter->fetchProducts($distributor);
        $report = $adapter->lastFetchReport();

        if ($limit !== null && $limit < count($products)) {
            $products = array_slice($products, 0, $limit);
        }

        $staged = 0;
        $complete = ! $report->stoppedEarly;

        if ($execute && $products !== []) {
            $config = $distributor->config ?? [];
            $distributorId = $distributor->id;

            foreach (array_chunk($products, self::CHUNK_SIZE) as $chunk) {
                $staged += $this->upsertShopifyChunk($distributorId, $chunk, $config);
            }
        } else {
            $staged = count($products);
        }

        // Advance last_synced_at on any complete pass — even when zero products
        // are returned (empty store or all filtered out). A complete pass is
        // a successful sync regardless of count.
        if ($execute && $complete) {
            $distributor->update(['last_synced_at' => now()]);
        }

        return [
            'fetched' => count($products),
            'staged' => $staged,
            'report' => $report->toArray(),
            'complete' => $complete,
        ];
    }

    /**
     * Fetch a single BigCommerce product page, parse it, and upsert to staging.
     *
     * Returns the parsed fields on success, or null when the page couldn't be
     * fetched or parsed (caller handles retry / failure accounting).
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>|null
     */
    public function crawlBigCommercePage(
        Distributor $distributor,
        string $url,
        string $externalId,
        array $config,
        bool $execute,
    ): ?array {
        $parser = app(BigCommerceProductPageParser::class);

        $response = Http::timeout(self::TIMEOUT)
            ->withUserAgent(self::USER_AGENT)
            ->get($url);

        $reason = $this->classifyResponse($response);

        if ($reason !== null) {
            return null;
        }

        $parsed = $parser->parse($response->body(), $config);

        if ($parsed === null || ($parsed['raw_sku'] ?? null) === null) {
            return null;
        }

        // Read the distributor's own structured attribute table (recipe-driven)
        // and classify the product type, so non-latex types can be parked with
        // their attributes rather than guessed from the title later.
        $extraction = $this->attributeExtractor->extract($response->body(), $config);
        $parsed['product_type'] = $this->classifier->classify($extraction);
        $parsed['attributes'] = $extraction['attributes'];
        $parsed['extraction'] = [
            'ok' => $extraction['ok'],
            'row_count' => $extraction['row_count'],
            'missing_required' => $extraction['missing_required'],
        ];

        // Drift guard: if the recipe didn't match this page (e.g. the site changed
        // its template) but we already hold good attributes for this product, keep
        // the good data rather than overwriting it with an empty extraction.
        if ($execute && ! ($parsed['extraction']['ok'] ?? false) && $this->hasStagedAttributes($distributor->id, $externalId)) {
            return $parsed;
        }

        if ($execute) {
            $this->upsertPage($distributor->id, $externalId, $url, $parsed);
        }

        return $parsed;
    }

    /**
     * Does a staged row for this product already hold a non-empty attribute table?
     */
    private function hasStagedAttributes(string $distributorId, string $externalId): bool
    {
        $existing = DistributorProduct::where('distributor_id', $distributorId)
            ->where('external_id', $externalId)
            ->first(['raw_data']);

        return ! empty($existing?->raw_data['attributes'] ?? []);
    }

    /**
     * Upsert a batch of Shopify products into staging.
     *
     * @param  array<int, array>  $products  One-per-variant from ShopifyAdapter
     * @param  array<string, mixed>  $config
     */
    private function upsertShopifyChunk(string $distributorId, array $products, array $config): int
    {
        $externalIds = array_map(fn (array $p) => $p['identifier'], $products);
        $existing = DistributorProduct::where('distributor_id', $distributorId)
            ->whereIn('external_id', $externalIds)
            ->pluck('id', 'external_id');

        $rows = [];

        foreach ($products as $product) {
            $externalId = $product['identifier'];
            $upc = null;

            if (! empty($product['barcode'])) {
                $digits = Gtin::digitsOnly((string) $product['barcode']);
                if ($digits !== '') {
                    $upc = $digits;
                }
            }

            $rows[] = [
                'id' => $existing[$externalId] ?? (string) Str::uuid7(),
                'distributor_id' => $distributorId,
                'external_id' => $externalId,
                'raw_sku' => $product['identifier'],
                'normalized_sku' => $this->normalizer->normalize($product['identifier'], $config),
                'upc' => $upc,
                'title' => $product['name'],
                'url' => $product['url'],
                'price' => $product['price'],
                'currency' => $product['currency'] ?? 'USD',
                'stock' => null,
                'in_stock' => $product['in_stock'],
                'raw_data' => null,
                'fetched_at' => now(),
            ];
        }

        DistributorProduct::upsert(
            $rows,
            ['distributor_id', 'external_id'],
            ['raw_sku', 'normalized_sku', 'upc', 'title', 'url', 'price', 'currency', 'in_stock', 'fetched_at'],
        );

        return count($rows);
    }

    /**
     * Upsert a single BigCommerce-parsed product page into staging.
     *
     * @param  array<string, mixed>  $parsed  From BigCommerceProductPageParser::parse()
     */
    private function upsertPage(string $distributorId, string $externalId, string $url, array $parsed): void
    {
        $existing = DistributorProduct::where('distributor_id', $distributorId)
            ->where('external_id', $externalId)
            ->value('id');

        $rawData = [
            'attributes' => $parsed['attributes'] ?? [],
            'extraction' => $parsed['extraction'] ?? [],
        ];

        DistributorProduct::upsert(
            [[
                'id' => $existing ?? (string) Str::uuid7(),
                'distributor_id' => $distributorId,
                'external_id' => $externalId,
                'raw_sku' => $parsed['raw_sku'] ?? '',
                'normalized_sku' => $parsed['normalized_sku'],
                'upc' => $parsed['upc'] ?? null,
                'title' => $parsed['title'] ?? '',
                'product_type' => $parsed['product_type'] ?? null,
                'url' => $url,
                'price' => $parsed['price'] ?? null,
                'currency' => 'USD',
                'stock' => $parsed['stock'] ?? null,
                'in_stock' => $parsed['in_stock'] ?? null,
                'raw_data' => json_encode($rawData),
                'fetched_at' => now(),
            ]],
            ['distributor_id', 'external_id'],
            ['raw_sku', 'normalized_sku', 'upc', 'title', 'product_type', 'url', 'price', 'currency', 'stock', 'in_stock', 'raw_data', 'fetched_at'],
        );
    }
}
