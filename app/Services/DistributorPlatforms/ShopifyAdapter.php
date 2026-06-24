<?php

namespace App\Services\DistributorPlatforms;

use App\Contracts\DistributorPlatformAdapter;
use App\Models\Distributor;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ShopifyAdapter implements DistributorPlatformAdapter
{
    private const PER_PAGE = 250;

    private const TIMEOUT = 30;

    private const USER_AGENT = 'Balloonventory/1.0 (+https://balloonventory.com)';

    private const DELAY_MS = 500_000; // 500ms between requests to be a good citizen

    public function fetchProducts(Distributor $distributor): array
    {
        $config = $distributor->config ?? [];
        $collectionHandle = $config['collection_handle'] ?? 'all';
        $hasJsonApi = $config['has_json_api'] ?? true;

        if ($hasJsonApi) {
            $products = $this->fetchFromJsonApi($distributor, $collectionHandle);

            // If the JSON API returned products, use them. Otherwise fall back
            // to the sitemap (rate limiting, API disabled, etc.).
            if ($products !== []) {
                return $products;
            }
        }

        return $this->fetchFromSitemap($distributor);
    }

    /**
     * Fetch products from Shopify's public collection JSON endpoint.
     * Paginated — follows ?page=N until an empty page is returned.
     */
    private function fetchFromJsonApi(Distributor $distributor, string $collectionHandle): array
    {
        $products = [];
        $page = 1;
        $retries = 0;
        $batch = [];

        do {
            $url = rtrim($distributor->base_url, '/')
                ."/collections/{$collectionHandle}/products.json"
                .'?limit='.self::PER_PAGE
                .'&page='.$page;

            $this->delay();
            $response = Http::timeout(self::TIMEOUT)
                ->withUserAgent(self::USER_AGENT)
                ->get($url);

            // Back off on rate limiting — Shopify returns 429
            if ($response->status() === 429 || $response->status() === 403) {
                $retries++;
                if ($retries > 3) {
                    break; // Give up, fall back to sitemap
                }
                sleep($retries * 2); // 2s, 4s, 6s
                $batch = []; // Reset so the loop condition doesn't break on stale data

                continue;
            }

            if (! $response->successful()) {
                break;
            }

            $json = $response->json();
            $batch = $json['products'] ?? [];

            if ($batch === []) {
                break;
            }

            foreach ($batch as $product) {
                $extracted = $this->extractProductVariants($product, $distributor);

                if ($extracted !== []) {
                    array_push($products, ...$extracted);
                }
            }

            $page++;
        } while (count($batch) === self::PER_PAGE);

        return $products;
    }

    /**
     * Fallback: fetch product URLs from Shopify's sitemap XML.
     * Returns less data (no barcode/price/stock) but works when JSON API is restricted.
     */
    private function fetchFromSitemap(Distributor $distributor): array
    {
        $products = [];

        $sitemapUrl = $distributor->sitemap_url
            ?: rtrim($distributor->base_url, '/').'/sitemap.xml';

        $response = $this->httpGetWithRetry($sitemapUrl);

        if (! $response->successful()) {
            return $products;
        }

        $xml = simplexml_load_string($response->body());

        if ($xml === false) {
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
                $this->delay();
                $batch = $this->parseProductSitemap($sitemapLoc);
                array_push($products, ...$batch);
            }
        } elseif ($xml->getName() === 'urlset') {
            $products = $this->parseProductUrls($xml);
        }

        return $products;
    }

    /**
     * Fetch and parse a single product sitemap file.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseProductSitemap(string $url): array
    {
        $response = $this->httpGetWithRetry($url);

        if (! $response->successful()) {
            return [];
        }

        $xml = simplexml_load_string($response->body());

        if ($xml === false || $xml->getName() !== 'urlset') {
            return [];
        }

        return $this->parseProductUrls($xml);
    }

    /**
     * Parse <url> entries from a urlset XML element.
     *
     * @return array<int, array{identifier: string, name: string, url: string, barcode: null, price: null, currency: null, in_stock: null}>
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
            ];
        }

        return $results;
    }

    /**
     * HTTP GET with retry + backoff for rate-limited endpoints (Shopify returns 429).
     */
    private function httpGetWithRetry(string $url, int $maxRetries = 3): Response
    {
        $attempts = 0;

        do {
            if ($attempts > 0) {
                sleep($attempts * 5); // 5s, 10s, 15s backoff
            }

            $response = Http::timeout(self::TIMEOUT)
                ->withUserAgent(self::USER_AGENT)
                ->get($url);

            if ($response->status() !== 429 && $response->status() !== 403) {
                return $response;
            }

            $attempts++;
        } while ($attempts <= $maxRetries);

        return $response;
    }

    private function delay(): void
    {
        usleep(self::DELAY_MS);
    }
}
