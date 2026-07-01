<?php

namespace App\Console\Commands;

use App\Models\Distributor;
use App\Models\DistributorProduct;
use App\Services\DistributorSkuNormalizer;
use Illuminate\Console\Command;

class RenormalizeStagedSkus extends Command
{
    protected $signature = 'catalog:renormalize-staged-skus
                            {--execute : Write the refreshed normalized SKUs (omit for dry-run)}';

    protected $description = 'RECOVER a normalized_sku for staged distributor products the old normalizer left null (e.g. alphanumeric codes like 56360P2), re-normalizing the stored raw_sku with the current normalizer + config. Only fills nulls — never overwrites an existing value (which for some distributors came from a field other than raw_sku) — and drops barcodes. Run after a normalizer fix / new strip rule so the next re-cluster produces correct warehouse SKUs without a re-crawl.';

    public function handle(DistributorSkuNormalizer $normalizer): int
    {
        $dryRun = ! $this->option('execute');

        $configs = Distributor::all(['id', 'config'])
            ->mapWithKeys(fn (Distributor $d) => [$d->id => $d->config ?? []])
            ->all();

        $changed = 0;
        $samples = [];

        // Only products with no normalized_sku yet — an existing value is
        // authoritative and must not be re-derived from raw_sku.
        DistributorProduct::query()
            ->where(fn ($q) => $q->whereNull('normalized_sku')->orWhere('normalized_sku', ''))
            ->chunkById(500, function ($products) use ($normalizer, $configs, $dryRun, &$changed, &$samples) {
                foreach ($products as $product) {
                    // Only recover the case the old normalizer actually dropped: an
                    // alphanumeric item code (e.g. 56360P2). See
                    // RecomputeProposalWarehouseSkus::recoverItemNumber.
                    $fresh = RecomputeProposalWarehouseSkus::recoverItemNumber(
                        (string) $product->raw_sku,
                        $normalizer,
                        $configs[$product->distributor_id] ?? [],
                    );

                    if ($fresh === null) {
                        continue;
                    }

                    $changed++;

                    if (count($samples) < 20) {
                        $samples[] = [$product->raw_sku, '∅', $fresh];
                    }

                    if (! $dryRun) {
                        $product->forceFill(['normalized_sku' => $fresh])->save();
                    }
                }
            });

        if ($changed === 0) {
            $this->info('All staged products already carry the current normalization. Nothing to do.');

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
            $this->line('Dry run — re-run with --execute to apply, then re-cluster.');
        }

        return Command::SUCCESS;
    }
}
