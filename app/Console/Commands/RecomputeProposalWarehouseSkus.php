<?php

namespace App\Console\Commands;

use App\Models\DistributorCatalogProposal;
use App\Services\DistributorClusterEngine;
use Illuminate\Console\Command;

class RecomputeProposalWarehouseSkus extends Command
{
    protected $signature = 'catalog:recompute-proposal-warehouse-skus
                            {--execute : Write the corrected warehouse SKUs (omit for dry-run)}';

    protected $description = 'Re-derive each open proposal\'s warehouse SKU as the consensus of its evidence (the item number the most distributors agree on), fixing rows that took a single outlier\'s internal/UPC-derived id. Skips proposals whose warehouse SKU was manually edited.';

    public function handle(DistributorClusterEngine $engine): int
    {
        $dryRun = ! $this->option('execute');

        // Only proposals that haven't yet become a catalog SKU — once promoted, the
        // SKU carries its own warehouse_sku and editing the proposal is moot.
        $proposals = DistributorCatalogProposal::query()
            ->whereNull('resulting_sku_id')
            ->where('status', '!=', DistributorCatalogProposal::STATUS_REJECTED)
            ->get();

        $changes = [];
        $skippedManual = 0;

        foreach ($proposals as $proposal) {
            $consensus = $engine->consensusWarehouseSku($proposal->evidence ?? []);

            // Nothing to derive, or the stored value already is the consensus.
            if ($consensus === null || $consensus === $proposal->normalized_sku) {
                continue;
            }

            // A warehouse SKU the admin typed in by hand (it no longer equals the
            // auto-stamped normalized_sku) is left untouched — report it only.
            $manuallyEdited = $proposal->proposed_warehouse_sku !== $proposal->normalized_sku;

            $changes[] = [
                'upc' => $proposal->upc,
                'from' => (string) $proposal->normalized_sku,
                'to' => $consensus,
                'sources' => collect($proposal->evidence ?? [])->pluck('distributor_id')->filter()->unique()->count(),
                'manual' => $manuallyEdited,
            ];

            if ($manuallyEdited) {
                $skippedManual++;

                continue;
            }

            if (! $dryRun) {
                $proposal->forceFill([
                    'normalized_sku' => $consensus,
                    'proposed_warehouse_sku' => $consensus,
                ])->save();
            }
        }

        return $this->report($changes, $skippedManual, $dryRun);
    }

    /**
     * @param  array<int, array{upc: string, from: string, to: string, sources: int, manual: bool}>  $changes
     */
    private function report(array $changes, int $skippedManual, bool $dryRun): int
    {
        if ($changes === []) {
            $this->info('All open proposals already carry the consensus warehouse SKU. Nothing to do.');

            return Command::SUCCESS;
        }

        $this->table(
            ['UPC', 'Current', 'Consensus', 'Sources', 'Manual?'],
            collect($changes)->map(fn (array $c) => [
                $c['upc'],
                $c['from'],
                $c['to'],
                $c['sources'],
                $c['manual'] ? 'kept (edited)' : '',
            ])->all(),
        );

        $applied = count($changes) - $skippedManual;

        $this->newLine();
        $this->info(($dryRun ? 'Would correct' : 'Corrected').": {$applied} proposal(s).");

        if ($skippedManual > 0) {
            $this->warn("Left {$skippedManual} manually-edited warehouse SKU(s) untouched — review the rows marked \"kept (edited)\" above.");
        }

        if ($dryRun) {
            $this->newLine();
            $this->line('Dry run — re-run with --execute to apply.');
        }

        return Command::SUCCESS;
    }
}
