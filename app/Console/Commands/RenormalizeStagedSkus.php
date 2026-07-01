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

    protected $description = 'Re-derive normalized_sku for every staged distributor product from its stored raw_sku using the current normalizer + distributor config. Run after a normalizer fix or a new strip rule so the next re-cluster produces correct warehouse SKUs without a re-crawl.';

    public function handle(DistributorSkuNormalizer $normalizer): int
    {
        $dryRun = ! $this->option('execute');

        $configs = Distributor::all(['id', 'config'])
            ->mapWithKeys(fn (Distributor $d) => [$d->id => $d->config ?? []])
            ->all();

        $changed = 0;
        $samples = [];

        DistributorProduct::query()->chunkById(500, function ($products) use ($normalizer, $configs, $dryRun, &$changed, &$samples) {
            foreach ($products as $product) {
                $fresh = $normalizer->normalize((string) $product->raw_sku, $configs[$product->distributor_id] ?? []);

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
