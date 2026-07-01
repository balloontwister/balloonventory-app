<?php

namespace App\Console\Commands;

use App\Models\DistributorCatalogProposal;
use App\Models\Sku;
use App\Services\DistributorClusterEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class AuditPromotedWarehouseSkus extends Command
{
    protected $signature = 'catalog:audit-promoted-warehouse-skus
                            {--execute : Correct the affected catalog SKUs (omit for a read-only audit)}';

    protected $description = 'Audit catalog SKUs created from distributor proposals for a warehouse SKU that disagrees with the consensus of the proposal evidence (the pre-fix "first member wins" bug baked into the SKU at promotion). Read-only by default; --execute corrects SKUs that were not manually edited after promotion.';

    public function handle(DistributorClusterEngine $engine): int
    {
        $dryRun = ! $this->option('execute');

        // Proposals that materialised a catalog SKU and still carry their evidence.
        $proposals = DistributorCatalogProposal::query()
            ->whereNotNull('resulting_sku_id')
            ->get();

        // Cross-connection: SKUs live on the primary connection, proposals on the
        // relocatable distributors connection — batch-load and stitch, no join.
        $skus = Sku::whereIn('id', $proposals->pluck('resulting_sku_id')->filter()->unique())
            ->get(['id', 'name', 'warehouse_sku'])
            ->keyBy('id');

        $changes = [];
        $skippedManual = 0;

        foreach ($proposals as $proposal) {
            $sku = $skus->get($proposal->resulting_sku_id);

            if ($sku === null) {
                continue; // SKU deleted since promotion.
            }

            $consensus = $engine->consensusWarehouseSku($proposal->evidence ?? [], $proposal->upc);

            if ($consensus === null || $consensus === $sku->warehouse_sku) {
                continue;
            }

            // The SKU's warehouse_sku still equals what promotion stamped from the
            // proposal → it's the auto value, safe to correct. If it differs, an
            // admin edited the SKU by hand → report only, never overwrite.
            $manuallyEdited = $sku->warehouse_sku !== $proposal->proposed_warehouse_sku;

            $changes[] = [
                'sku_id' => $sku->id,
                'name' => $sku->name,
                'from' => (string) $sku->warehouse_sku,
                'to' => $consensus,
                'sources' => collect($proposal->evidence ?? [])->pluck('distributor_id')->filter()->unique()->count(),
                'manual' => $manuallyEdited,
            ];

            if ($manuallyEdited) {
                $skippedManual++;

                continue;
            }

            if (! $dryRun) {
                $sku->forceFill(['warehouse_sku' => $consensus])->save();
            }
        }

        return $this->report(collect($changes), $skippedManual, $dryRun);
    }

    /**
     * @param  Collection<int, array{sku_id: string, name: ?string, from: string, to: string, sources: int, manual: bool}>  $changes
     */
    private function report(Collection $changes, int $skippedManual, bool $dryRun): int
    {
        if ($changes->isEmpty()) {
            $this->info('No promoted SKUs disagree with their evidence consensus. Nothing to do.');

            return Command::SUCCESS;
        }

        $this->table(
            ['SKU', 'Name', 'Current', 'Consensus', 'Sources', 'Manual?'],
            $changes->map(fn (array $c) => [
                mb_strimwidth((string) $c['sku_id'], 0, 8, ''),
                mb_strimwidth((string) $c['name'], 0, 40, '…'),
                $c['from'],
                $c['to'],
                $c['sources'],
                $c['manual'] ? 'kept (edited)' : '',
            ])->all(),
        );

        $applied = $changes->count() - $skippedManual;

        $this->newLine();
        $this->info(($dryRun ? 'Would correct' : 'Corrected').": {$applied} SKU(s).");

        if ($skippedManual > 0) {
            $this->warn("Left {$skippedManual} manually-edited SKU(s) untouched — review the rows marked \"kept (edited)\" above.");
        }

        if ($dryRun) {
            $this->newLine();
            $this->line('Read-only audit — re-run with --execute to apply.');
        }

        return Command::SUCCESS;
    }
}
