<?php

namespace App\Console\Commands;

use App\Models\DistributorCatalogProposal;
use App\Services\DistributorCatalogPromoter;
use Illuminate\Console\Command;

class PromoteDistributorProposals extends Command
{
    protected $signature = 'catalog:promote-distributor-proposals
                            {--execute : Create catalog SKUs (omit for dry-run)}';

    protected $description = 'Auto-create catalog SKUs from high-confidence distributor proposals.';

    public function handle(DistributorCatalogPromoter $promoter): int
    {
        $dryRun = ! $this->option('execute');

        // Auto-create targets high-confidence pending proposals; human-approved
        // proposals are promoted regardless of confidence.
        $proposals = DistributorCatalogProposal::query()
            ->whereNull('resulting_sku_id')
            ->where(function ($query) {
                $query->where(fn ($q) => $q->where('status', DistributorCatalogProposal::STATUS_PENDING)->where('confidence', 'high'))
                    ->orWhere('status', DistributorCatalogProposal::STATUS_APPROVED);
            })
            ->get();

        $created = 0;
        $leftPending = 0;

        foreach ($proposals as $proposal) {
            if ($dryRun) {
                $promoter->canPromote($proposal) ? $created++ : $leftPending++;

                continue;
            }

            try {
                $sku = $promoter->promote($proposal);
            } catch (\Throwable $e) {
                $this->error("  Proposal {$proposal->upc}: {$e->getMessage()}");
                $leftPending++;

                continue;
            }

            if ($sku !== null) {
                $created++;
                $this->line("  ✓ Created SKU for {$proposal->upc} — {$sku->name}");
            } else {
                $leftPending++;
            }
        }

        $this->newLine();
        $verb = $dryRun ? 'would be created' : 'created';
        $this->line("  Eligible proposals: {$proposals->count()}");
        $this->line("  SKUs {$verb}: {$created}");
        $this->line('  Left pending (need review): '.$leftPending);

        $this->newLine();
        $mode = $dryRun ? '<comment>[DRY RUN]</comment>' : '<info>[EXECUTED]</info>';
        $this->line("{$mode} ".($dryRun ? 'Run with --execute to create SKUs.' : 'Done.'));

        return Command::SUCCESS;
    }
}
