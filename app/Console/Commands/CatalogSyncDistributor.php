<?php

namespace App\Console\Commands;

use App\Models\Distributor;
use App\Models\DistributorCatalogGap;
use App\Models\DistributorSkuUrl;
use App\Services\DistributorMatcher;
use App\Services\DistributorPlatforms\PlatformFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CatalogSyncDistributor extends Command
{
    protected $signature = 'catalog:sync-distributor
                            {distributor? : The distributor slug or "all"}
                            {--execute : Write matches to the database (omit for dry-run)}';

    protected $description = 'Fetch product URLs from a distributor and match them against the catalog.';

    public function handle(PlatformFactory $platformFactory, DistributorMatcher $matcher): int
    {
        $dryRun = ! $this->option('execute');
        $slug = $this->argument('distributor');

        if ($slug && $slug !== 'all') {
            $distributors = Distributor::where('slug', $slug)->get();

            if ($distributors->isEmpty()) {
                $this->error("No distributor found with slug '{$slug}'.");

                return Command::FAILURE;
            }
        } else {
            $distributors = Distributor::active()->orderBy('sort_order')->get();

            if ($distributors->isEmpty()) {
                $this->warn('No active distributors found.');

                return Command::SUCCESS;
            }
        }

        $totalFetched = 0;
        $totalMatched = 0;
        $totalNew = 0;
        $totalGaps = 0;

        foreach ($distributors as $distributor) {
            $this->newLine();
            $this->info("{$distributor->name} ({$distributor->platform_type})");

            // ── Fetch ─────────────────────────────────────────────────
            try {
                $adapter = $platformFactory->make($distributor);
                $products = $adapter->fetchProducts($distributor);
            } catch (\Exception $e) {
                $this->error("  Failed to fetch: {$e->getMessage()}");

                continue;
            }

            $fetched = count($products);
            $totalFetched += $fetched;
            $this->line("  Fetched: {$fetched} products");

            // ── Fetch diagnostics ─────────────────────────────────────
            $report = $adapter->lastFetchReport();

            if ($report->usedFallback) {
                $this->warn('  ⚠ Richer source unavailable — used the sitemap fallback (URLs only: no barcodes/price/stock, so far fewer matches).');
            }

            if ($report->stoppedEarly) {
                $detail = "{$report->lastFailureReason} (HTTP {$report->lastFailureStatus})";

                if ($report->looksBlocked()) {
                    $this->error("  ⚠ Looks blocked/rate-limited: {$detail}. Stopped after {$report->pagesFetched} page(s) — results are PARTIAL; last_synced_at will NOT be updated. Back off and retry later.");
                } else {
                    $this->warn("  ⚠ Fetch stopped early: {$detail} after {$report->pagesFetched} page(s) — results may be partial.");
                }
            }

            if ($fetched === 0) {
                continue;
            }

            // ── Match ─────────────────────────────────────────────────
            $result = $matcher->match($distributor, $products);
            $matched = $result['matched'];
            $gaps = $result['gaps'];

            // Count how many are new (not already in DB)
            $existingPairs = DistributorSkuUrl::where('distributor_id', $distributor->id)
                ->pluck('sku_id')
                ->flip();
            $newCount = $matched->filter(fn (array $m) => ! $existingPairs->has($m['sku_id']))->count();

            $totalMatched += $matched->count();
            $totalNew += $newCount;
            $totalGaps += $gaps->count();

            // Breakdown by match reason
            $byReason = $matched->groupBy('match_reason')
                ->map(fn (Collection $g) => $g->count())
                ->all();
            $reasonStr = implode(', ', array_map(fn (string $k, int $v) => "{$k}: {$v}", array_keys($byReason), $byReason));

            $this->line("  Matched: {$matched->count()} ({$reasonStr})");
            $this->line("  New URLs: {$newCount}");
            $this->line("  Gaps: {$gaps->count()}");

            // ── Execute ────────────────────────────────────────────────
            if (! $dryRun) {
                // A truncated fetch (block/rate-limit) still upserts what we got
                // — upsert never removes existing URLs — but last_synced_at must
                // only advance on a complete pass so it can't mask a bad sync.
                $fetchWasComplete = ! $report->stoppedEarly;

                DB::transaction(function () use ($distributor, $matched, $gaps, $fetchWasComplete) {
                    // Upsert matched URLs
                    if ($matched->isNotEmpty()) {
                        DistributorSkuUrl::upsert(
                            $matched->map(fn (array $m) => [
                                'distributor_id' => $m['distributor_id'],
                                'sku_id' => $m['sku_id'],
                                'url' => $m['url'],
                                'price' => $m['price'],
                                'currency' => $m['currency'],
                                'in_stock' => $m['in_stock'],
                                'last_checked_at' => $m['last_checked_at'],
                            ])->all(),
                            ['distributor_id', 'sku_id'],
                            ['url', 'price', 'currency', 'in_stock', 'last_checked_at'],
                        );
                    }

                    // Insert new gaps, de-duplicated by product_url. We can't key
                    // on external_identifier: Shopify variants that carry a
                    // barcode but no SKU all share an empty identifier, which
                    // would collapse every such gap into a single row. product_url
                    // is unique per product.
                    if ($gaps->isNotEmpty()) {
                        $existingGapUrls = DistributorCatalogGap::where('distributor_id', $distributor->id)
                            ->pluck('product_url')
                            ->flip();

                        $seenUrls = [];
                        $newGaps = $gaps->filter(function (array $g) use ($existingGapUrls, &$seenUrls) {
                            $url = $g['product_url'];
                            if ($existingGapUrls->has($url) || isset($seenUrls[$url])) {
                                return false;
                            }
                            $seenUrls[$url] = true;

                            return true;
                        });

                        foreach ($newGaps as $gap) {
                            DistributorCatalogGap::create($gap);
                        }
                    }

                    // Only mark a full, clean pass as "synced".
                    if ($fetchWasComplete) {
                        $distributor->update(['last_synced_at' => now()]);
                    }
                });

                $this->info($fetchWasComplete ? '  ✓ Synced.' : '  ✓ Wrote partial results (not marked fully synced).');
            }
        }

        // ── Summary ──────────────────────────────────────────────────
        $this->newLine();
        $mode = $dryRun
            ? '<comment>[DRY RUN]</comment>'
            : '<info>[EXECUTED]</info>';
        $this->line("{$mode} Total — Fetched: {$totalFetched} | Matched: {$totalMatched} | New URLs: {$totalNew} | Gaps: {$totalGaps}");

        if ($dryRun) {
            $this->line('         Run with --execute to write.');
        }

        return Command::SUCCESS;
    }
}
