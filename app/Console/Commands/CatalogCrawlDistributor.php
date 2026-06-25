<?php

namespace App\Console\Commands;

use App\Models\Distributor;
use App\Models\DistributorProduct;
use App\Services\DistributorPlatforms\Concerns\InspectsHttpResponses;
use App\Services\DistributorPlatforms\FetchReport;
use App\Services\DistributorPlatforms\PlatformFactory;
use App\Services\DistributorProductIngestor;
use Illuminate\Console\Command;
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

    public function handle(DistributorProductIngestor $ingestor, PlatformFactory $platformFactory): int
    {
        $slug = $this->argument('slug');
        $execute = (bool) $this->option('execute');
        $limit = (int) $this->option('limit');

        $distributor = Distributor::where('slug', $slug)->first();

        if ($distributor === null) {
            $this->error("No distributor found with slug '{$slug}'.");

            return Command::FAILURE;
        }

        if ($distributor->platform_type !== 'bigcommerce') {
            $this->error("{$distributor->name} is not a BigCommerce store. Use catalog:ingest-distributor for Shopify distributors.");

            return Command::FAILURE;
        }

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

        // ── Step 2: Skip already-fetched products (resume) ─────────────
        // --force re-fetches everything (used to backfill newly-added fields).
        $existingIds = $this->option('force')
            ? collect()
            : DistributorProduct::where('distributor_id', $distributor->id)
                ->where('fetched_at', '>', now()->subHours(24))
                ->pluck('external_id')
                ->flip();

        $config = $distributor->config ?? [];
        $delayMs = $this->configInt($config, 'request_delay_ms', 500);
        $jitterMs = $this->configInt($config, 'request_jitter_ms', 0);
        $maxRetries = $this->configInt($config, 'max_retries', 3);

        $queued = 0;
        $staged = 0;
        $failed = 0;
        $skipped = 0;

        // ── Step 3: Crawl product pages ────────────────────────────────
        foreach ($sitemapProducts as $product) {
            $url = $product['url'];
            $externalId = $this->resolveExternalId($url);

            if ($existingIds->has($externalId)) {
                $skipped++;

                continue;
            }

            if ($limit !== null && $queued >= $limit) {
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
                $parsed = $ingestor->crawlBigCommercePage($distributor, $url, $externalId, $config, $execute);

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
            } else {
                $failed++;
                Log::warning('BigCommerce crawl: failed to fetch/parse product page', [
                    'distributor' => $distributor->slug,
                    'url' => $url,
                ]);
            }

            // Progress dot every 10 products so long runs don't look frozen.
            if ($queued % 10 === 0) {
                $this->output->write('.');
            }
        }

        // ── Step 4: Mark complete ──────────────────────────────────────
        $allDone = ! $fetchReport->stoppedEarly && ($limit === null || $queued < $limit);
        // More precisely: all remaining URLs were queued (nothing left undone).
        $remainingCount = $totalUrls - $existingIds->count() - $skipped;
        $caughtUp = $remainingCount <= $queued;

        if ($execute && $allDone && $caughtUp) {
            $distributor->update(['last_synced_at' => now()]);
        }

        if ($queued > 0 && $this->output->isDecorated()) {
            $this->newLine(); // newline after the dots
        }

        // ── Summary ────────────────────────────────────────────────────
        $this->newLine();
        $this->line("  Sitemap URLs:  {$totalUrls}");
        $this->line("  Skipped (24h): {$skipped}");
        $this->line("  Queued:        {$queued}");
        $this->line("  Staged:        {$staged}");
        $this->line("  Failed:        {$failed}");

        $this->newLine();
        $mode = $execute
            ? '<info>[EXECUTED]</info>'
            : '<comment>[DRY RUN]</comment>';
        $status = $caughtUp ? 'All products up to date.' : ($remainingCount - $queued).' products remaining. Run again to continue.';
        $this->line("{$mode} {$status}");

        if (! $execute) {
            $this->line('         Run with --execute to write to staging.');
        }

        return Command::SUCCESS;
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
}
