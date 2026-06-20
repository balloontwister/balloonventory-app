<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Thins out old database backups on a grandfather-father-son schedule so the
 * list doesn't grow without bound:
 *   - every daily backup is kept for the last 30 days;
 *   - then one per calendar month up to 1 year old;
 *   - then one per calendar quarter up to 3 years old;
 *   - then one per calendar year, forever.
 * Within each older period the NEWEST backup is the one kept.
 *
 * Only auto-generated backups (balloonventory_<timestamp>.sql.gz) are touched.
 * Any backup an admin has renamed no longer matches that pattern and is left
 * alone — renaming is therefore an effective "pin this forever".
 */
class PruneBackups extends Command
{
    protected $signature = 'app:prune-backups {--dry-run : List what would be deleted without deleting it}';

    protected $description = 'Prune old database backups (daily → monthly → quarterly → yearly retention)';

    /** Keep every backup newer than this many days. */
    private const DAILY_DAYS = 30;

    /** Up to this age, keep one per calendar month. */
    private const MONTHLY_DAYS = 365;

    /** Up to this age, keep one per calendar quarter; beyond it, one per year. */
    private const QUARTERLY_DAYS = 1095; // ~3 years

    public function handle(): int
    {
        // Defaults to the real backups dir; overridable (config or tests) so
        // pruning can be exercised in isolation.
        $dir = config('backups.directory') ?: storage_path('app/backups');

        if (! is_dir($dir)) {
            $this->info('No backups directory — nothing to prune.');

            return self::SUCCESS;
        }

        // Parse only auto-generated backups; renamed/pinned files are ignored.
        $files = [];
        foreach (glob($dir.'/*.sql.gz') as $path) {
            $name = basename($path);
            if (! preg_match('/^balloonventory_(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})\.sql\.gz$/', $name, $m)) {
                continue;
            }
            $date = Carbon::createFromFormat('Y-m-d_H-i-s', $m[1]);
            if (! $date) {
                continue;
            }
            $files[] = ['path' => $path, 'name' => $name, 'date' => $date];
        }

        // Newest first, so the first file seen in each older bucket is the keeper.
        usort($files, fn ($a, $b) => $b['date'] <=> $a['date']);

        $now = now();
        $seenBuckets = [];
        $deleted = [];
        $keptCount = 0;
        $dryRun = (bool) $this->option('dry-run');

        foreach ($files as $f) {
            $bucket = $this->bucketFor($f['date'], $now);

            // Daily tier (null bucket) keeps everything; older tiers keep the
            // first (newest) file seen per bucket and drop the rest.
            if ($bucket === null || ! isset($seenBuckets[$bucket])) {
                if ($bucket !== null) {
                    $seenBuckets[$bucket] = true;
                }
                $keptCount++;

                continue;
            }

            $deleted[] = $f;
        }

        if (empty($deleted)) {
            $this->info("Nothing to prune — {$keptCount} backups kept.");

            return self::SUCCESS;
        }

        foreach ($deleted as $f) {
            if ($dryRun) {
                $this->line('[dry-run] would delete '.$f['name']);

                continue;
            }
            @unlink($f['path']);
            $this->line('Deleted '.$f['name']);
        }

        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info("{$prefix}Pruned ".count($deleted)." backups; {$keptCount} kept.");

        return self::SUCCESS;
    }

    /**
     * The retention bucket a backup belongs to, or null when it is recent enough
     * to keep unconditionally (daily tier). Older backups collapse to one keeper
     * per month, then quarter, then year.
     */
    private function bucketFor(Carbon $date, Carbon $now): ?string
    {
        $ageDays = $date->diffInDays($now);

        if ($ageDays <= self::DAILY_DAYS) {
            return null;
        }

        if ($ageDays <= self::MONTHLY_DAYS) {
            return 'M:'.$date->format('Y-m');
        }

        if ($ageDays <= self::QUARTERLY_DAYS) {
            return 'Q:'.$date->year.'-'.$date->quarter;
        }

        return 'Y:'.$date->year;
    }
}
