<?php

namespace App\Console\Commands;

use App\Models\DistributorCatalogProposal;
use App\Services\DistributorCatalogPromoter;
use Illuminate\Console\Command;

class PromoteDistributorProposals extends Command
{
    protected $signature = 'catalog:promote-distributor-proposals
                            {--execute : Create catalog SKUs (omit for dry-run)}
                            {--brand= : Only promote proposals for this brand (case-insensitive match against evidence titles)}';

    protected $description = 'Auto-create catalog SKUs from high-confidence distributor proposals that clear the accuracy gate (multi-source attribute agreement + GS1 brand check). Human-approved proposals promote regardless.';

    public function handle(DistributorCatalogPromoter $promoter): int
    {
        $dryRun = ! $this->option('execute');
        $brand = $this->option('brand');

        // Auto-create targets high-confidence pending proposals; human-approved
        // proposals are promoted regardless of confidence.
        $query = DistributorCatalogProposal::query()
            ->whereNull('resulting_sku_id')
            ->where(function ($query) {
                $query->where(fn ($q) => $q->where('status', DistributorCatalogProposal::STATUS_PENDING)->where('confidence', 'high'))
                    ->orWhere('status', DistributorCatalogProposal::STATUS_APPROVED);
            });

        $proposals = $query->get();

        // ── Brand gate (optional) ──────────────────────────────────────
        if ($brand !== null) {
            $brandLower = strtolower($brand);

            $proposals = $proposals->filter(function (DistributorCatalogProposal $proposal) use ($brandLower): bool {
                $text = collect($proposal->evidence ?? [])
                    ->pluck('title')
                    ->push($proposal->proposed_name)
                    ->filter()
                    ->implode(' ');

                return str_contains(strtolower($text), $brandLower);
            });

            $this->info("Brand filter: \"{$brand}\" — {$proposals->count()} proposals matched.");
            $this->newLine();
        }

        $created = 0;
        $leftPending = 0;

        foreach ($proposals as $proposal) {
            if ($dryRun) {
                $promoter->canPromote($proposal) ? $created++ : $leftPending++;

                continue;
            }

            // Only auto-create what clears the accuracy gate; everything else is
            // left pending for the review queue. (Human-approved proposals bypass
            // the gate inside canPromote — the reviewer is the corroboration.)
            if (! $promoter->canPromote($proposal)) {
                $leftPending++;

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
