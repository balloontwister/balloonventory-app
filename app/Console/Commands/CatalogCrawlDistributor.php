<?php

namespace App\Console\Commands;

use App\Models\Distributor;
use App\Models\DistributorProduct;
use App\Services\DistributorPlatforms\Concerns\InspectsHttpResponses;
use App\Services\DistributorPlatforms\FetchReport;
use App\Services\DistributorPlatforms\PlatformFactory;
use App\Services\DistributorProductIngestor;
use App\Services\Distributors\DistributorHealthEvaluator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class CatalogCrawlDistributor extends Command
{
    use InspectsHttpResponses;

    protected $signature = 'catalog:crawl-distributor
                            {slug : Distributor slug (larocks, havinaparty, etc.)}
                            {--execute : Write staged products to the database (omit for dry-run)}
                            {--limit=100 : Max product pages to fetch this run}
                            {--force : Re-fetch pages even if crawled in the last 24h (e.g. to backfill new fields)}';

    protected $description = 'Crawl product pages from a BigCommerce distributor and stage them for clustering. Designed for repeated invocation — each run processes up to --limit pages, skipping products already fetched in the last 24h.';

    private const TIMEOUT = 30;

    private const USER_AGENT = 'Balloonventory/1.0 (+https://balloonventory.com)';

    public function handle(DistributorProductIngestor $ingestor, PlatformFactory $platformFactory, DistributorHealthEvaluator $healthEvaluator): int
    {
        $slug = $this->argument('slug');
        $execute = (bool) $this->option('execute');
        $limit = (int) $this->option('limit');

        $distributor = Distributor::where('slug', $slug)->first();

        if ($distributor === null) {
            $this->error("No distributor found with slug '{$slug}'.");

            return Command::FAILURE;
        }

        if (! in_array($distributor->platform_type, ['bigcommerce', 'magento'], true)) {
            $this->error("{$distributor->name} is not a crawlable store (BigCommerce or Magento). Use catalog:ingest-distributor for Shopify distributors.");

            return Command::FAILURE;
        }

        $isMagento = $distributor->platform_type === 'magento';

        $this->newLine();
        $this->info("{$distributor->name} ({$distributor->platform_type})");

        // ── Step 1: Get the URL list from the sitemap ──────────────────
        $fetchReport = new FetchReport;

        try {
            $adapter = $platformFactory->make($distributor);
            $sitemapProducts = $adapter->fetchProducts($distributor);
            $fetchReport = $adapter->lastFetchReport();
        } catch (\Exception $e) {
            $this->error("  Failed to fetch sitemap: {$e->getMessage()}");

            return Command::FAILURE;
        }

        $totalUrls = count($sitemapProducts);

        $this->line("  Sitemap URLs: {$totalUrls}");

        // ── Sitemap diagnostics ────────────────────────────────────────
        if ($fetchReport->usedFallback) {
            $this->warn('  ⚠ Sitemap fallback used.');
        }

        if ($fetchReport->stoppedEarly) {
            $detail = "{$fetchReport->lastFailureReason} (HTTP {$fetchReport->lastFailureStatus})";

            if ($fetchReport->looksBlocked()) {
                $this->error("  ⚠ Looks blocked: {$detail} — sitemap may be incomplete.");
            } else {
                $this->warn("  ⚠ Sitemap fetch truncated: {$detail}.");
            }
        }

        if ($totalUrls === 0) {
            $this->line('  No product URLs found.');

            return Command::SUCCESS;
        }

        // ── Step 2: Decide what to (re)fetch (incremental refresh) ─────
        // Skip a product we already fetched *after* its sitemap <lastmod> — it
        // hasn't changed. New and updated products fall through and get crawled.
        // Without a usable lastmod we fall back to a 24h freshness window so a big
        // backfill still resumes. --force re-fetches everything.
        $known = $this->option('force')
            ? collect()
            : DistributorProduct::where('distributor_id', $distributor->id)
                ->get(['external_id', 'fetched_at'])
                ->keyBy('external_id');

        $config = $distributor->config ?? [];
        $delayMs = $this->configInt($config, 'request_delay_ms', 500);
        $jitterMs = $this->configInt($config, 'request_jitter_ms', 0);
        $maxRetries = $this->configInt($config, 'max_retries', 3);

        $queued = 0;
        $staged = 0;
        $failed = 0;
        $skipped = 0;
        $filtered = 0;
        $hitLimit = false;
        $extractionOk = 0;   // pages whose attribute table parsed against the recipe
        $pagesParsed = 0;    // pages we successfully fetched + read product data from
        $extractionBroke = false;

        // ── Step 3: Crawl product pages ────────────────────────────────
        foreach ($sitemapProducts as $product) {
            $url = $product['url'];
            $externalId = $this->resolveExternalId($url);

            // Pre-fetch slug filter (config crawl_filter): skip URLs we can tell
            // from the slug are not what we're after, WITHOUT spending a fetch on
            // the ~1 MB page. They stay in the sitemap list, so removal
            // reconciliation is unaffected.
            if ($this->isFilteredOut($url, $config)) {
                $filtered++;

                continue;
            }

            if ($this->isFresh($known->get($externalId), $product['lastmod'] ?? null)) {
                $skipped++;

                continue;
            }

            if ($queued >= $limit) {
                $hitLimit = true;
                break;
            }

            $queued++;

            if ($queued > 1) {
                $this->throttle($delayMs, $jitterMs);
            }

            // Dry-run: just count what would be crawled.
            if (! $execute) {
                $staged++;

                continue;
            }

            // Fetch with retries.
            $parsed = null;
            $remaining = $maxRetries;

            do {
                $parsed = $isMagento
                    ? $ingestor->crawlMagentoPage($distributor, $url, $externalId, $config, $execute)
                    : $ingestor->crawlBigCommercePage($distributor, $url, $externalId, $config, $execute);

                if ($parsed !== null) {
                    break;
                }

                $remaining--;
                if ($remaining > 0) {
                    sleep(3);
                }
            } while ($remaining > 0);

            if ($parsed !== null) {
                $staged++;
                $pagesParsed++;
                if ($parsed['extraction']['ok'] ?? false) {
                    $extractionOk++;
                }
            } else {
                $failed++;
                Log::warning('BigCommerce crawl: failed to fetch/parse product page', [
                    'distributor' => $distributor->slug,
                    'url' => $url,
                ]);
            }

            // Drift guard: once we have a sample, if almost nothing is extracting
            // the site template has likely changed — stop rather than churn the
            // rest of the crawl writing garbage (the no-clobber guard in the
            // ingestor already protects existing good rows).
            if ($pagesParsed >= DistributorHealthEvaluator::MIN_SAMPLE
                && ($extractionOk / $pagesParsed) < 0.2) {
                $extractionBroke = true;
                break;
            }

            // Progress dot every 10 products so long runs don't look frozen.
            if ($queued % 10 === 0) {
                $this->output->write('.');
            }
        }

        // ── Extraction health (drift detection) ────────────────────────
        if ($execute && ($health = $healthEvaluator->evaluate($extractionOk, $pagesParsed)) !== null) {
            $distributor->update([
                'health_status' => $health['status'],
                'health_checked_at' => now(),
                'health_detail' => $health['detail'],
            ]);
        }

        // ── Step 4: Reconcile removals + mark complete ─────────────────
        // The sitemap is fully enumerated regardless of --limit, so we can retire
        // products that dropped out of it — but only when the fetch was COMPLETE; a
        // truncated/blocked sitemap would wrongly retire live products.
        $sitemapComplete = ! $fetchReport->stoppedEarly && ! $fetchReport->usedFallback;
        $removed = ($execute && $sitemapComplete)
            ? $this->reconcileRemovals($distributor, $sitemapProducts)
            : 0;

        // Caught up when we walked the whole sitemap this run without hitting --limit.
        $caughtUp = ! $hitLimit;

        if ($execute && $sitemapComplete && $caughtUp) {
            $distributor->update(['last_synced_at' => now()]);
        }

        if ($queued > 0 && $this->output->isDecorated()) {
            $this->newLine(); // newline after the dots
        }

        // ── Summary ────────────────────────────────────────────────────
        $this->newLine();
        $this->line("  Sitemap URLs:    {$totalUrls}");
        if ($filtered > 0) {
            $this->line("  Filtered (slug): {$filtered} (not latex — accessories/foils skipped pre-fetch)");
        }
        $this->line("  Skipped (fresh): {$skipped}");
        $this->line("  Queued:          {$queued}");
        $this->line("  Staged:          {$staged}");
        $this->line("  Failed:          {$failed}");

        if ($execute && $sitemapComplete) {
            $this->line("  Retired (gone):  {$removed}");
        }

        if ($execute && $pagesParsed > 0) {
            $this->line("  Extracted OK:  {$extractionOk}/{$pagesParsed}".($distributor->health_status ? "  [health: {$distributor->health_status}]" : ''));
            if ($extractionBroke) {
                $this->error('  ⚠ Stopped early — extraction looks broken (site template may have changed). Re-check the recipe with Probe.');
            }
        }

        $this->newLine();
        $mode = $execute
            ? '<info>[EXECUTED]</info>'
            : '<comment>[DRY RUN]</comment>';
        $remaining = max(0, $totalUrls - $filtered - $skipped - $queued);
        $status = $caughtUp ? 'All products up to date.' : $remaining.' products remaining. Run again to continue.';
        $this->line("{$mode} {$status}");

        if (! $execute) {
            $this->line('         Run with --execute to write to staging.');
        }

        return Command::SUCCESS;
    }

    /**
     * Pre-fetch slug filter, driven by config `crawl_filter`:
     *   'crawl_filter' => [
     *     // skip slugs that don't start with a digit — solid-latex slugs always
     *     // lead with a size (11s-…, 160k-…); letter-led slugs are accessories
     *     // (arch-kit-…, tassel-…) or foil letters/scripts (script-silver-…).
     *     'require_leading_digit' => true,
     *     // skip slugs containing any of these (high-confidence non-latex signals)
     *     'skip_keywords' => ['air-fill', 'foil', 'orbz', 'sphere', 'mylar', 'banner'],
     *   ]
     * Conservative by design: when in doubt the URL is crawled (no latex lost).
     *
     * @param  array<string, mixed>  $config
     */
    private function isFilteredOut(string $url, array $config): bool
    {
        $filter = $config['crawl_filter'] ?? null;

        if (! is_array($filter)) {
            return false;
        }

        $slug = strtolower(trim((string) parse_url($url, \PHP_URL_PATH), '/'));
        // Last path segment is the product slug.
        $slug = (string) (strrchr($slug, '/') ? ltrim(strrchr($slug, '/'), '/') : $slug);

        if (($filter['require_leading_digit'] ?? false) && preg_match('/^\d/', $slug) !== 1) {
            return true;
        }

        foreach ((array) ($filter['skip_keywords'] ?? []) as $keyword) {
            if ($keyword !== '' && str_contains($slug, strtolower((string) $keyword))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Derive a stable external_id from a BigCommerce product URL.
     *
     * Matches BigCommerceAdapter::extractIdentifierFromUrl so the same URL
     * always resolves to the same external_id.
     */
    private function resolveExternalId(string $url): string
    {
        $path = parse_url($url, \PHP_URL_PATH) ?: '';

        // Old-style .htm with product ID: /p/123456.htm
        if (preg_match('#/p/(\d+)\.htm$#', $path, $matches)) {
            return $matches[1];
        }

        // Clean slug: last path segment
        $segments = array_values(array_filter(explode('/', $path)));
        $last = end($segments);

        return $last ?: $path;
    }

    /**
     * Whether a staged product is still fresh and can be skipped this run: we
     * already fetched its page at or after the sitemap's last-modified time. With
     * no usable lastmod we fall back to a 24h window so a long backfill resumes
     * without re-pulling pages it just did.
     *
     * @param  DistributorProduct|null  $known  the staged row (external_id + fetched_at), or null if new
     */
    private function isFresh(?DistributorProduct $known, ?string $lastmod): bool
    {
        if ($known === null || $known->fetched_at === null) {
            return false; // never staged / never page-fetched → crawl it
        }

        if ($lastmod !== null && ($changedAt = $this->parseLastmod($lastmod)) !== null) {
            return $known->fetched_at->greaterThanOrEqualTo($changedAt);
        }

        return $known->fetched_at->greaterThan(now()->subHours(24));
    }

    private function parseLastmod(string $value): ?Carbon
    {
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Mark every product still listed in the sitemap as seen now (un-retiring any
     * that reappeared), then retire the staged products that are no longer listed.
     * Caller guarantees the sitemap fetch was complete. Returns the retired count.
     *
     * @param  array<int, array<string, mixed>>  $sitemapProducts
     */
    private function reconcileRemovals(Distributor $distributor, array $sitemapProducts): int
    {
        $runAt = now();

        $sitemapIds = collect($sitemapProducts)
            ->map(fn (array $product) => $this->resolveExternalId($product['url']))
            ->unique();

        foreach ($sitemapIds->chunk(500) as $chunk) {
            DistributorProduct::where('distributor_id', $distributor->id)
                ->whereIn('external_id', $chunk->all())
                ->update(['last_seen_at' => $runAt, 'removed_at' => null]);
        }

        return DistributorProduct::where('distributor_id', $distributor->id)
            ->whereNull('removed_at')
            ->where(fn ($q) => $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $runAt))
            ->update(['removed_at' => $runAt]);
    }
}
