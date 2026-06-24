<?php

namespace App\Services\DistributorPlatforms;

use App\Contracts\DistributorPlatformAdapter;
use App\Models\Distributor;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class BigCommerceAdapter implements DistributorPlatformAdapter
{
    private const TIMEOUT = 30;

    private const DELAY_MS = 500_000; // 500ms between paginated requests

    /** Hard ceiling so a sitemap that never returns an empty page can't loop forever. */
    private const MAX_PAGES = 500;

    public function fetchProducts(Distributor $distributor): array
    {
        $products = [];
        $page = 1;

        do {
            $url = rtrim($distributor->base_url, '/')
                .'/xmlsitemap.php'
                .'?type=products'
                .'&page='.$page;

            if ($page > 1) {
                usleep(self::DELAY_MS);
            }

            $response = $this->httpGetWithRetry($url);

            if (! $response->successful()) {
                break;
            }

            $xml = simplexml_load_string($response->body());

            if ($xml === false) {
                break;
            }

            $batch = $this->parseUrlset($xml);

            if ($batch === []) {
                break;
            }

            array_push($products, ...$batch);
            $page++;
        } while ($page <= self::MAX_PAGES);

        return $products;
    }

    /**
     * HTTP GET with retry + backoff for rate-limited endpoints.
     */
    private function httpGetWithRetry(string $url, int $maxRetries = 3): Response
    {
        $attempts = 0;

        do {
            $response = Http::timeout(self::TIMEOUT)->get($url);

            if ($response->status() !== 429) {
                return $response;
            }

            $attempts++;
            if ($attempts <= $maxRetries) {
                sleep($attempts * 3);
            }
        } while ($attempts <= $maxRetries);

        return $response;
    }

    /**
     * Parse <url> entries from a BigCommerce sitemap urlset.
     *
     * @return array<int, array{identifier: string, name: string, url: string, barcode: null, price: null, currency: null, in_stock: null}>
     */
    private function parseUrlset(\SimpleXMLElement $xml): array
    {
        $products = [];

        foreach ($xml->url ?? [] as $entry) {
            $loc = (string) $entry->loc;

            // Skip non-balloon products when possible (heuristic: balloon-related URL slugs)
            // BigCommerce mixes balloons with clown supplies, makeup, etc.
            $path = parse_url($loc, \PHP_URL_PATH) ?: '';

            $lastSegment = basename($path);
            // Remove .htm extension if present
            $lastSegment = preg_replace('/\.htm$/', '', $lastSegment);

            $name = str_replace(['-', '/'], ' ', urldecode($lastSegment));

            $products[] = [
                'identifier' => $this->extractIdentifierFromUrl($loc),
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
     * Extract a usable identifier from a BigCommerce product URL.
     *
     * BigCommerce URLs come in two forms:
     *   /12-inch-round-standard-pink-balloon-elitex-50ct/  (clean slug)
     *   /.../product-name-p/7144499999.htm                  (old-style with product ID)
     */
    private function extractIdentifierFromUrl(string $url): string
    {
        $path = parse_url($url, \PHP_URL_PATH) ?: '';

        // Try old-style .htm with product ID: /.../p/123456.htm
        if (preg_match('#/p/(\d+)\.htm$#', $path, $matches)) {
            return $matches[1];
        }

        // For clean slugs, use the last path segment as the identifier
        $segments = array_values(array_filter(explode('/', $path)));
        $last = end($segments);

        return $last ?: $path;
    }
}
