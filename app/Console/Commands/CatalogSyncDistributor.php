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
                DB::transaction(function () use ($distributor, $matched, $gaps) {
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

                    // Update last synced timestamp
                    $distributor->update(['last_synced_at' => now()]);
                });

                $this->info('  ✓ Synced.');
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
