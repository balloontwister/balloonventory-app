<?php

namespace App\Console\Commands;

use App\Models\Distributor;
use App\Services\DistributorProductIngestor;
use Illuminate\Console\Command;

class CatalogIngestDistributor extends Command
{
    protected $signature = 'catalog:ingest-distributor
                            {slug : Distributor slug (bargain-balloons, etc.)}
                            {--execute : Write staged products to the database (omit for dry-run)}
                            {--limit= : Cap the number of products (for testing; Shopify only)}';

    protected $description = 'Ingest products from a Shopify distributor into the staging table. For BigCommerce, use catalog:crawl-distributor instead.';

    public function handle(DistributorProductIngestor $ingestor): int
    {
        $slug = $this->argument('slug');
        $execute = (bool) $this->option('execute');
        $limit = $this->option('limit');

        if ($limit !== null) {
            $limit = (int) $limit;
        }

        $distributor = Distributor::where('slug', $slug)->first();

        if ($distributor === null) {
            $this->error("No distributor found with slug '{$slug}'.");

            return Command::FAILURE;
        }

        if ($distributor->platform_type === 'bigcommerce') {
            $this->newLine();
            $this->warn("{$distributor->name} is a BigCommerce store.");
            $this->warn('BigCommerce requires per-product page crawling — use catalog:crawl-distributor instead.');
            $this->newLine();
            $this->line("  php artisan catalog:crawl-distributor {$slug} --execute --limit=100");

            return Command::FAILURE;
        }

        $this->newLine();
        $this->info("{$distributor->name} ({$distributor->platform_type})");

        $result = $ingestor->ingestShopify($distributor, $execute, $limit);

        $this->line("  Fetched: {$result['fetched']} products");
        $this->line("  Staged:  {$result['staged']}");

        // ── Diagnostics ────────────────────────────────────────────────
        $report = $result['report'];

        if ($report['used_fallback'] ?? false) {
            $this->warn('  ⚠ Richer source unavailable — used the sitemap fallback (URLs only: no barcodes/price/stock).');
        }

        if ($report['stopped_early'] ?? false) {
            $detail = ($report['last_failure_reason'] ?? 'unknown').' (HTTP '.($report['last_failure_status'] ?? '?').')';

            if (in_array($report['last_failure_reason'] ?? null, ['rate_limited', 'blocked', 'challenge'], true)) {
                $this->error("  ⚠ Looks blocked/rate-limited: {$detail}. Stopped after {$report['pages_fetched']} page(s) — results are PARTIAL; last_synced_at will NOT be updated. Back off and retry later.");
            } else {
                $this->warn("  ⚠ Fetch stopped early: {$detail} after {$report['pages_fetched']} page(s) — results may be partial.");
            }
        }

        if ($result['fetched'] === 0) {
            $this->line('  No products found.');

            return Command::SUCCESS;
        }

        // ── Summary ────────────────────────────────────────────────────
        $this->newLine();
        $mode = $execute
            ? '<info>[EXECUTED]</info>'
            : '<comment>[DRY RUN]</comment>';
        $this->line("{$mode} Fetched: {$result['fetched']} | Staged: {$result['staged']}");

        if (! $execute) {
            $this->line('         Run with --execute to write to staging.');
        }

        return Command::SUCCESS;
    }
}
