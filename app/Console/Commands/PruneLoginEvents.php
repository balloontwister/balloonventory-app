<?php

namespace App\Console\Commands;

use App\Models\LoginEvent;
use Illuminate\Console\Command;

/**
 * Deletes login-history rows older than the retention window (18 months), so the
 * append-only table stays bounded and old IP/user-agent data doesn't linger.
 */
class PruneLoginEvents extends Command
{
    protected $signature = 'app:prune-login-events';

    protected $description = 'Delete login history older than the retention window (18 months)';

    private const RETENTION_MONTHS = 18;

    public function handle(): int
    {
        $cutoff = now()->subMonths(self::RETENTION_MONTHS);

        $deleted = LoginEvent::where('created_at', '<', $cutoff)->delete();

        $this->info("Pruned {$deleted} login events older than ".$cutoff->toDateString().'.');

        return self::SUCCESS;
    }
}
