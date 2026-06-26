<?php

namespace App\Console\Commands;

use App\Services\DistributorClusterEngine;
use Illuminate\Console\Command;

class ClusterDistributorProducts extends Command
{
    protected $signature = 'catalog:cluster-distributors
                            {--execute : Write catalog proposals (omit for dry-run)}';

    protected $description = 'Cluster staged distributor products by UPC and propose new catalog products.';

    public function handle(DistributorClusterEngine $engine): int
    {
        $dryRun = ! $this->option('execute');

        $stats = $engine->run(! $dryRun);

        $this->newLine();
        $this->line("  Clusters:             {$stats['clusters']}");
        $this->line("  Already in catalog:   {$stats['matched_existing']}");
        $this->line("    ↳ URLs attached:    {$stats['urls_attached']}");
        $this->line("  New-product proposals: {$stats['proposals']} (solid latex)");
        $this->line("  Deferred (parked):     {$stats['deferred']}");

        foreach ($stats['deferred_by_type'] as $type => $count) {
            $this->line('    ↳ '.str_pad($type, 14).$count);
        }

        $this->line("  Matched by warehouse SKU: {$stats['matched_by_warehouse_sku']} (barcode-less → existing catalog)");
        $this->line("  Unclustered listings:  {$stats['unclustered']}");

        $this->newLine();
        $mode = $dryRun ? '<comment>[DRY RUN]</comment>' : '<info>[EXECUTED]</info>';
        $this->line("{$mode} ".($dryRun ? 'Run with --execute to write proposals and attach URLs.' : 'Proposals and URLs would be written.'));

        return Command::SUCCESS;
    }
}
