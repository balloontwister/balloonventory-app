<?php

namespace App\Services\DistributorPlatforms;

use App\Contracts\DistributorPlatformAdapter;
use App\Models\Distributor;
use App\Services\DistributorPlatforms\Concerns\InspectsHttpResponses;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyAdapter implements DistributorPlatformAdapter
{
    use InspectsHttpResponses;

    private const PER_PAGE = 250;

    private const TIMEOUT = 30;

    private const USER_AGENT = 'Balloonventory/1.0 (+https://balloonventory.com)';

    private const DEFAULT_DELAY_MS = 500; // between requests to be a good citizen

    private const DEFAULT_MAX_RETRIES = 3;

    private ?FetchReport $report = null;

    public function lastFetchReport(): FetchReport
    {
        return $this->report ??= new FetchReport;
    }

    public function fetchProducts(Distributor $distributor): array
    {
        $this->report = new FetchReport;
        $config = $distributor->config ?? [];
        $collectionHandle = $config['collection_handle'] ?? 'all';
        $hasJsonApi = $config['has_json_api'] ?? true;

        if ($hasJsonApi) {
            $products = $this->fetchFromJsonApi($distributor, $collectionHandle, $config);

            // If the JSON API returned products, use them. Otherwise fall back
            // to the sitemap (rate limiting, API disabled, etc.) — but flag it,
            // since the sitemap has no barcodes/price/stock and matches far worse.
            if ($products !== []) {
                return $products;
            }

            $this->report->usedFallback = true;
            Log::warning('Distributor JSON API yielded nothing; using sitemap fallback', [
                'distributor' => $distributor->slug,
                'platform' => 'shopify',
                'last_failure_reason' => $this->report->lastFailureReason,
            ]);
        }

        return $this->fetchFromSitemap($distributor, $config);
    }

    /**
     * Fetch products from Shopify's public collection JSON endpoint.
     * Paginated — follows ?page=N until an empty page is returned.
     *
     * @param  array<string, mixed>  $config
     */
    private function fetchFromJsonApi(Distributor $distributor, string $collectionHandle, array $config): array
    {
        $delayMs = $this->configInt($config, 'request_delay_ms', self::DEFAULT_DELAY_MS);
        $jitterMs = $this->configInt($config, 'request_jitter_ms', 0);
        $maxRetries = $this->configInt($config, 'max_retries', self::DEFAULT_MAX_RETRIES);

        $products = [];
        $page = 1;
        $retries = 0;
        $batch = [];

        do {
            $url = rtrim($distributor->base_url, '/')
                ."/collections/{$collectionHandle}/products.json"
                .'?limit='.self::PER_PAGE
                .'&page='.$page;

            $this->throttle($delayMs, $jitterMs);
            $response = Http::timeout(self::TIMEOUT)
                ->withUserAgent(self::USER_AGENT)
                ->get($url);

            $reason = $this->classifyResponse($response);

            // Back off and retry on rate-limit / block / challenge.
            if ($this->isRetryable($reason)) {
                $retries++;
                if ($retries > $maxRetries) {
                    $this->report->recordFailure($response->status(), $reason);
                    $this->logStop($distributor, $url, $response->status(), $reason);

                    break; // Give up, fall back to sitemap
                }
                $this->report->retriesUsed++;
                sleep($this->retryDelaySeconds($response, $retries, 2));
                $batch = []; // Reset so the loop condition doesn't break on stale data

                continue;
            }

            if ($reason !== null) {
                $this->report->recordFailure($response->status(), $reason);
                $this->logStop($distributor, $url, $response->status(), $reason);

                break;
            }

            $json = $response->json();
            $batch = $json['products'] ?? [];

            if ($batch === []) {
                break; // natural end
            }

            foreach ($batch as $product) {
                $extracted = $this->extractProductVariants($product, $distributor);

                if ($extracted !== []) {
                    array_push($products, ...$extracted);
                }
            }

            $this->report->pagesFetched = $page;
            $page++;
        } while (count($batch) === self::PER_PAGE);

        return $products;
    }

    /**
     * Fallback: fetch product URLs from Shopify's sitemap XML.
     * Returns less data (no barcode/price/stock) but works when JSON API is restricted.
     */
    /**
     * @param  array<string, mixed>  $config
     */
    private function fetchFromSitemap(Distributor $distributor, array $config = []): array
    {
        $products = [];
        $delayMs = $this->configInt($config, 'request_delay_ms', self::DEFAULT_DELAY_MS);
        $jitterMs = $this->configInt($config, 'request_jitter_ms', 0);
        $maxRetries = $this->configInt($config, 'max_retries', self::DEFAULT_MAX_RETRIES);

        $sitemapUrl = $distributor->sitemap_url
            ?: rtrim($distributor->base_url, '/').'/sitemap.xml';

        $response = $this->httpGetWithRetry($sitemapUrl, $maxRetries);
        $reason = $this->classifyResponse($response);

        if ($reason !== null) {
            $this->report->recordFailure($response->status(), $reason);
            $this->logStop($distributor, $sitemapUrl, $response->status(), $reason);

            return $products;
        }

        $xml = simplexml_load_string($response->body());

        if ($xml === false) {
            $this->report->recordFailure($response->status(), 'unparseable');
            $this->logStop($distributor, $sitemapUrl, $response->status(), 'unparseable');

            return $products;
        }

        // Check if this is a sitemap index or a direct urlset
        if ($xml->getName() === 'sitemapindex') {
            $productSitemaps = [];
            foreach ($xml->sitemap as $sitemap) {
                $loc = (string) $sitemap->loc;
                if (str_contains($loc, 'sitemap_products')) {
                    $productSitemaps[] = $loc;
                }
            }

            foreach ($productSitemaps as $sitemapLoc) {
                $this->throttle($delayMs, $jitterMs);
                $batch = $this->parseProductSitemap($sitemapLoc, $maxRetries);
                array_push($products, ...$batch);
            }
        } elseif ($xml->getName() === 'urlset') {
            $products = $this->parseProductUrls($xml);
            $this->report->pagesFetched = 1;
        }

        return $products;
    }

    /**
     * Fetch and parse a single product sitemap file.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseProductSitemap(string $url, int $maxRetries): array
    {
        $response = $this->httpGetWithRetry($url, $maxRetries);

        if ($this->classifyResponse($response) !== null) {
            return [];
        }

        $xml = simplexml_load_string($response->body());

        if ($xml === false || $xml->getName() !== 'urlset') {
            return [];
        }

        $this->report->pagesFetched++;

        return $this->parseProductUrls($xml);
    }

    /**
     * Parse <url> entries from a urlset XML element.
     *
     * @return array<int, array{identifier: string, name: string, url: string, barcode: null, price: null, currency: null, in_stock: null, vendor: null, tags: array<int, string>, updated_at: null}>
     */
    private function parseProductUrls(\SimpleXMLElement $urlset): array
    {
        $products = [];

        foreach ($urlset->url as $entry) {
            $loc = (string) $entry->loc;

            // Extract an identifier from the URL slug (the trailing code before the last segment)
            $path = parse_url($loc, \PHP_URL_PATH) ?: '';
            $segments = array_values(array_filter(explode('/', $path)));
            $lastSegment = end($segments) ?: '';

            // The Shopify URL format is /products/{slug}-{identifier}
            // where {identifier} is often a numeric SKU/vendor code.
            $name = str_replace(['-', '/'], ' ', urldecode($lastSegment));

            $products[] = [
                'identifier' => $this->extractIdentifierFromSlug($lastSegment),
                'name' => $name,
                'url' => $loc,
                'barcode' => null,
                'price' => null,
                'currency' => null,
                'in_stock' => null,
                'vendor' => null,
                'tags' => [],
                'updated_at' => null,
                'product_type' => null,
            ];
        }

        return $products;
    }

    /**
     * Extract a usable identifier from a Shopify product handle.
     * Falls back to the full handle if no numeric suffix is found.
     */
    private function extractIdentifierFromSlug(string $handle): string
    {
        // Try to find a trailing numeric/alphanumeric identifier
        // Examples: "10523532-5-inches-kalisan..." → "10523532"
        //           "tt-15089-5-inch-tuftex..." → "tt-15089"
        $parts = explode('-', $handle);

        // If the first part looks like an SKU (all digits or vendor-prefixed), use it
        $first = $parts[0] ?? '';
        if (preg_match('/^[a-z]*\d+$/', $first)) {
            return $first;
        }

        // Look for a numeric suffix at the end
        $last = end($parts);
        if ($last !== false && preg_match('/^\d+[a-z]*$/', $last)) {
            return $last;
        }

        return $handle;
    }

    /**
     * Extract product variants from a Shopify product JSON object.
     *
     * @param  array<string, mixed>  $product
     * @return array<int, array<string, mixed>>
     */
    private function extractProductVariants(array $product, Distributor $distributor): array
    {
        $results = [];
        $handle = $product['handle'] ?? '';
        $productUrl = rtrim($distributor->base_url, '/').'/products/'.$handle;

        // Product-level fields the page-enrichment pass needs: the vendor is the
        // brand (Shopify omits it from the page table), and tags/title drive the
        // cheap solid-latex pre-filter. updated_at lets enrichment skip pages that
        // haven't changed since they were last fetched.
        $vendor = $product['vendor'] ?? null;
        $tags = $this->normalizeTags($product['tags'] ?? null);
        $updatedAt = $product['updated_at'] ?? null;
        $productType = $product['product_type'] ?? null;

        foreach ($product['variants'] ?? [] as $variant) {
            $sku = $variant['sku'] ?? '';
            $barcode = $variant['barcode'] ?? null;

            // Use SKU as identifier; skip variants without one
            if ($sku === '' && $barcode === null) {
                continue;
            }

            $price = isset($variant['price']) ? (float) $variant['price'] : null;
            $inventoryQty = $variant['inventory_quantity'] ?? null;

            $results[] = [
                'identifier' => $sku,
                'name' => $product['title'] ?? $variant['title'] ?? '',
                'url' => $productUrl,
                'barcode' => $barcode ? (string) $barcode : null,
                'price' => $price,
                'currency' => $variant['currency'] ?? 'USD',
                'in_stock' => $inventoryQty !== null ? $inventoryQty > 0 : null,
                'vendor' => $vendor !== null && $vendor !== '' ? (string) $vendor : null,
                'tags' => $tags,
                'updated_at' => $updatedAt,
                'product_type' => $productType !== null && $productType !== '' ? (string) $productType : null,
            ];
        }

        return $results;
    }

    /**
     * Shopify's products.json reports tags as an array; some endpoints emit a
     * comma-separated string. Normalise to a clean list either way.
     *
     * @param  mixed  $tags
     * @return array<int, string>
     */
    private function normalizeTags($tags): array
    {
        if (is_array($tags)) {
            return array_values(array_filter(array_map(fn ($t) => trim((string) $t), $tags)));
        }

        if (is_string($tags) && $tags !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $tags))));
        }

        return [];
    }

    /**
     * HTTP GET that retries on rate-limit / block / challenge responses with a
     * Retry-After-aware back-off.
     */
    private function httpGetWithRetry(string $url, int $maxRetries): Response
    {
        $attempts = 0;

        do {
            $response = Http::timeout(self::TIMEOUT)
                ->withUserAgent(self::USER_AGENT)
                ->get($url);

            if (! $this->isRetryable($this->classifyResponse($response))) {
                return $response;
            }

            $attempts++;
            if ($attempts <= $maxRetries) {
                $this->lastFetchReport()->retriesUsed++;
                sleep($this->retryDelaySeconds($response, $attempts, 5));
            }
        } while ($attempts <= $maxRetries);

        return $response;
    }

    private function logStop(Distributor $distributor, string $url, int $status, string $reason): void
    {
        Log::warning('Distributor fetch stopped early', [
            'distributor' => $distributor->slug,
            'platform' => 'shopify',
            'url' => $url,
            'status' => $status,
            'reason' => $reason,
        ]);
    }
}
