<?php

namespace App\Console\Commands;

use App\Models\Distributor;
use App\Models\DistributorProduct;
use App\Services\DistributorSkuNormalizer;
use Illuminate\Console\Command;

class RenormalizeDistributorSkus extends Command
{
    protected $signature = 'catalog:renormalize-distributor-skus
                            {slug : Distributor slug}
                            {--execute : Write the corrected normalized SKUs (omit for dry-run)}';

    protected $description = 'Re-derive normalized_sku for every staged product of ONE distributor from its raw_sku, using the CURRENT normalizer + that distributor\'s config — overwriting existing values, not just filling nulls (unlike renormalize-staged-skus). Run after fixing a distributor\'s sku_strip_prefixes/suffixes. Only correct for a distributor whose normalized_sku is always derived from raw_sku (not another field like mpn) — check before running on a distributor you have not verified.';

    public function handle(DistributorSkuNormalizer $normalizer): int
    {
        $dryRun = ! $this->option('execute');
        $slug = $this->argument('slug');

        $distributor = Distributor::where('slug', $slug)->first();

        if ($distributor === null) {
            $this->error("No distributor found with slug '{$slug}'.");

            return Command::FAILURE;
        }

        $config = $distributor->config ?? [];
        $changed = 0;
        $samples = [];

        DistributorProduct::where('distributor_id', $distributor->id)
            ->chunkById(500, function ($products) use ($normalizer, $config, $dryRun, &$changed, &$samples) {
                foreach ($products as $product) {
                    $fresh = $normalizer->normalize((string) $product->raw_sku, $config);

                    if ($fresh === $product->normalized_sku) {
                        continue;
                    }

                    $changed++;

                    if (count($samples) < 20) {
                        $samples[] = [$product->raw_sku, $product->normalized_sku ?? '∅', $fresh ?? '∅'];
                    }

                    if (! $dryRun) {
                        $product->forceFill(['normalized_sku' => $fresh])->save();
                    }
                }
            });

        if ($changed === 0) {
            $this->info("All {$distributor->name} staged products already match the current normalizer. Nothing to do.");

            return Command::SUCCESS;
        }

        $this->table(['Raw SKU', 'Was', 'Now'], $samples);

        if ($changed > count($samples)) {
            $this->line('… and '.($changed - count($samples)).' more.');
        }

        $this->newLine();
        $this->info(($dryRun ? 'Would update' : 'Updated').": {$changed} staged product(s).");

        if ($dryRun) {
            $this->newLine();
            $this->line('Dry run — re-run with --execute to apply, then re-cluster (catalog:cluster-distributors --execute) so open proposals pick up the correction.');
        }

        return Command::SUCCESS;
    }
}
