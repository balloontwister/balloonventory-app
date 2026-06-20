<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Carbon;
use Tests\TestCase;

class PruneBackupsTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        // Isolate from any real backups by pointing the command at a temp dir.
        $this->dir = sys_get_temp_dir().'/bv-prune-test-'.uniqid();
        mkdir($this->dir, 0755, true);
        config(['backups.directory' => $this->dir]);

        // Freeze "now" so the age-based tiers are deterministic.
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*') as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);

        parent::tearDown();
    }

    private function backup(string $timestamp): string
    {
        $name = "balloonventory_{$timestamp}.sql.gz";
        file_put_contents($this->dir.'/'.$name, 'x');

        return $name;
    }

    private function exists(string $name): bool
    {
        return file_exists($this->dir.'/'.$name);
    }

    public function test_it_keeps_all_recent_dailies_and_thins_older_tiers(): void
    {
        // Daily tier (<= 30 days): all kept.
        $d1 = $this->backup('2026-06-15_02-00-00');
        $d2 = $this->backup('2026-06-10_02-00-00');
        $d3 = $this->backup('2026-05-20_02-00-00'); // 26 days old

        // Monthly tier (30d–1y): keep newest per calendar month.
        $aprNew = $this->backup('2026-04-28_02-00-00');
        $aprOld = $this->backup('2026-04-05_02-00-00'); // same month → pruned
        $mar = $this->backup('2026-03-15_02-00-00');

        // Quarterly tier (1y–3y): keep newest per calendar quarter.
        $q4New = $this->backup('2024-11-20_02-00-00');
        $q4Old = $this->backup('2024-10-05_02-00-00'); // same quarter → pruned

        // Yearly tier (> 3y): keep newest per calendar year.
        $yNew = $this->backup('2022-08-01_02-00-00');
        $yOld = $this->backup('2022-02-01_02-00-00'); // same year → pruned

        // A renamed / pinned backup never matches the pattern → always kept.
        $pinned = 'pre-migration-snapshot.sql.gz';
        file_put_contents($this->dir.'/'.$pinned, 'x');

        $this->artisan('app:prune-backups')->assertSuccessful();

        // Kept
        foreach ([$d1, $d2, $d3, $aprNew, $mar, $q4New, $yNew] as $keep) {
            $this->assertTrue($this->exists($keep), "expected {$keep} to be kept");
        }
        $this->assertTrue($this->exists($pinned), 'renamed backup must be pinned');

        // Pruned
        foreach ([$aprOld, $q4Old, $yOld] as $gone) {
            $this->assertFalse($this->exists($gone), "expected {$gone} to be pruned");
        }
    }

    public function test_dry_run_deletes_nothing(): void
    {
        $keep = $this->backup('2026-04-28_02-00-00');
        $would = $this->backup('2026-04-05_02-00-00');

        $this->artisan('app:prune-backups --dry-run')->assertSuccessful();

        $this->assertTrue($this->exists($keep));
        $this->assertTrue($this->exists($would), 'dry-run must not delete anything');
    }
}
