<?php

namespace App\Services\DistributorPlatforms;

use App\Contracts\DistributorPlatformAdapter;
use App\Models\Distributor;
use App\Services\DistributorPlatforms\Concerns\InspectsHttpResponses;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BigCommerceAdapter implements DistributorPlatformAdapter
{
    use InspectsHttpResponses;

    private const TIMEOUT = 30;

    private const USER_AGENT = 'Balloonventory/1.0 (+https://balloonventory.com)';

    private const DEFAULT_DELAY_MS = 500; // between paginated requests

    private const DEFAULT_MAX_RETRIES = 3;

    /** Hard ceiling so a sitemap that never returns an empty page can't loop forever. */
    private const DEFAULT_MAX_PAGES = 500;

    private ?FetchReport $report = null;

    public function lastFetchReport(): FetchReport
    {
        return $this->report ??= new FetchReport;
    }

    public function fetchProducts(Distributor $distributor): array
    {
        $this->report = new FetchReport;
        $config = $distributor->config ?? [];
        $delayMs = $this->configInt($config, 'request_delay_ms', self::DEFAULT_DELAY_MS);
        $jitterMs = $this->configInt($config, 'request_jitter_ms', 0);
        $maxRetries = $this->configInt($config, 'max_retries', self::DEFAULT_MAX_RETRIES);
        $maxPages = $this->configInt($config, 'max_pages', self::DEFAULT_MAX_PAGES);

        $products = [];
        $page = 1;

        do {
            $url = rtrim($distributor->base_url, '/')
                .'/xmlsitemap.php'
                .'?type=products'
                .'&page='.$page;

            if ($page > 1) {
                $this->throttle($delayMs, $jitterMs);
            }

            $response = $this->httpGetWithRetry($url, $maxRetries);
            $reason = $this->classifyResponse($response);

            if ($reason !== null) {
                $this->report->recordFailure($response->status(), $reason);
                $this->logStop($distributor, $url, $response->status(), $reason);
                break;
            }

            $xml = simplexml_load_string($response->body());

            if ($xml === false) {
                $this->report->recordFailure($response->status(), 'unparseable');
                $this->logStop($distributor, $url, $response->status(), 'unparseable');
                break;
            }

            $batch = $this->parseUrlset($xml);
            $this->report->pagesFetched = $page;

            if ($batch === []) {
                break; // natural end of the catalog
            }

            array_push($products, ...$batch);
            $page++;
        } while ($page <= $maxPages);

        return $products;
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
                sleep($this->retryDelaySeconds($response, $attempts, 3));
            }
        } while ($attempts <= $maxRetries);

        return $response;
    }

    private function logStop(Distributor $distributor, string $url, int $status, string $reason): void
    {
        Log::warning('Distributor fetch stopped early', [
            'distributor' => $distributor->slug,
            'platform' => 'bigcommerce',
            'url' => $url,
            'status' => $status,
            'reason' => $reason,
        ]);
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
                // Sitemap last-modified, when the store populates it — drives the
                // incremental refresh (fetch only changed/new pages).
                'lastmod' => trim((string) ($entry->lastmod ?? '')) ?: null,
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
