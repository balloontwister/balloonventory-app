<?php

namespace App\Services\DistributorPlatforms;

use App\Contracts\DistributorPlatformAdapter;
use App\Models\Distributor;
use App\Services\DistributorPlatforms\Concerns\InspectsHttpResponses;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Magento 2 store adapter. Unlike Shopify/BigCommerce there's no bulk product
 * feed, and the Magento sitemap mixes flat product URLs with categories
 * brand-blind — so it can't be path-filtered to the slice we support. Instead we
 * harvest product links from the configured CATEGORY listing pages (config
 * `category_urls`, e.g. the solid-latex-by-brand categories), following Magento's
 * `?p=N` pagination until a page yields no new product links. The per-page parse
 * (JSON-LD) happens later in the crawl command via {@see MagentoProductPageParser},
 * exactly like the BigCommerce crawl.
 *
 * Config:
 *   - category_urls: string[]  listing pages to harvest (required)
 *   - request_delay_ms / request_jitter_ms / max_retries / max_pages: throttle knobs
 */
class MagentoAdapter implements DistributorPlatformAdapter
{
    use InspectsHttpResponses;

    private const TIMEOUT = 30;

    private const USER_AGENT = 'Balloonventory/1.0 (+https://balloonventory.com)';

    private const DEFAULT_DELAY_MS = 1000; // Cloudflare-fronted → polite by default

    private const DEFAULT_MAX_RETRIES = 3;

    /** Per-category page ceiling so a listing that never empties can't loop forever. */
    private const DEFAULT_MAX_PAGES = 100;

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

        $categoryUrls = array_values(array_filter(
            (array) ($config['category_urls'] ?? []),
            fn ($url) => is_string($url) && $url !== '',
        ));

        $seen = [];       // absolute url => true (dedupe across categories + pages)
        $products = [];
        $requests = 0;

        foreach ($categoryUrls as $categoryUrl) {
            $previousSignature = null;

            for ($page = 1; $page <= $maxPages; $page++) {
                $url = $this->pageUrl($categoryUrl, $page);

                if ($requests > 0) {
                    $this->throttle($delayMs, $jitterMs);
                }
                $requests++;

                $response = $this->httpGetWithRetry($url, $maxRetries);
                $reason = $this->classifyResponse($response);

                if ($reason !== null) {
                    $this->report->recordFailure($response->status(), $reason);
                    $this->logStop($distributor, $url, $response->status(), $reason);
                    break; // stop this category; report->stoppedEarly guards removal reconciliation
                }

                $links = $this->extractProductLinks($response->body(), $distributor->base_url);
                $this->report->pagesFetched = $requests;

                // End of this category: an empty grid, or Magento clamping `?p`
                // beyond the last page back to a page we just read (same links).
                $signature = $links === [] ? '' : md5(implode('|', $links));

                if ($links === [] || $signature === $previousSignature) {
                    break;
                }

                $previousSignature = $signature;

                foreach ($links as $loc) {
                    if (isset($seen[$loc])) {
                        continue;
                    }

                    $seen[$loc] = true;
                    $products[] = [
                        'identifier' => $this->extractIdentifierFromUrl($loc),
                        'name' => $this->nameFromUrl($loc),
                        'url' => $loc,
                        'barcode' => null,
                        'price' => null,
                        'currency' => null,
                        'in_stock' => null,
                        'lastmod' => null, // Magento category listings carry no per-product lastmod
                    ];
                }
            }
        }

        return $products;
    }

    /**
     * HTTP GET that retries on rate-limit / block / challenge responses with a
     * Retry-After-aware back-off. Mirrors BigCommerceAdapter.
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

    private function pageUrl(string $categoryUrl, int $page): string
    {
        if ($page <= 1) {
            return $categoryUrl;
        }

        $separator = str_contains($categoryUrl, '?') ? '&' : '?';

        return $categoryUrl.$separator.'p='.$page;
    }

    /**
     * Pull product URLs from a Magento category listing: the `<a>` elements that
     * carry the `product-item-link` class (attribute order varies by theme, so we
     * match anchors containing that class then read the href). Only `.html`
     * product URLs are kept.
     *
     * @return array<int, string>
     */
    private function extractProductLinks(string $html, string $baseUrl): array
    {
        if (! preg_match_all('#<a\b[^>]*product-item-link[^>]*>#is', $html, $matches)) {
            return [];
        }

        $urls = [];

        foreach ($matches[0] as $tag) {
            if (! preg_match('#href="([^"]+)"#i', $tag, $href)) {
                continue;
            }

            $loc = $this->absolutize(html_entity_decode(trim($href[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'), $baseUrl);

            if ($loc !== null && str_ends_with((string) parse_url($loc, \PHP_URL_PATH), '.html')) {
                $urls[$loc] = true; // dedupe within the page
            }
        }

        return array_keys($urls);
    }

    private function absolutize(string $href, string $baseUrl): ?string
    {
        if ($href === '') {
            return null;
        }

        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }

        if (str_starts_with($href, '/')) {
            return rtrim($baseUrl, '/').$href;
        }

        return null; // ignore fragments / javascript: / other schemes
    }

    /**
     * The last path segment (the product's url-key, including .html) — a stable
     * per-product identifier that matches the crawl command's resolveExternalId.
     */
    private function extractIdentifierFromUrl(string $url): string
    {
        $path = parse_url($url, \PHP_URL_PATH) ?: '';
        $segments = array_values(array_filter(explode('/', $path)));
        $last = end($segments);

        return $last ?: $path;
    }

    private function nameFromUrl(string $url): string
    {
        $path = parse_url($url, \PHP_URL_PATH) ?: '';
        $slug = preg_replace('/\.html$/', '', basename($path)) ?? '';

        return str_replace(['-', '/'], ' ', urldecode($slug));
    }

    private function logStop(Distributor $distributor, string $url, int $status, string $reason): void
    {
        Log::warning('Distributor fetch stopped early', [
            'distributor' => $distributor->slug,
            'platform' => 'magento',
            'url' => $url,
            'status' => $status,
            'reason' => $reason,
        ]);
    }
}
