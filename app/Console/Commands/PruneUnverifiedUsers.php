<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PruneUnverifiedUsers extends Command
{
    protected $signature = 'app:prune-unverified-users
                            {--hours=24 : Delete accounts unverified for longer than this many hours}
                            {--dry-run : Report how many would be deleted without deleting}';

    protected $description = 'Delete user accounts that were never email-verified within the grace period';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $cutoff = Carbon::now()->subHours($hours);

        // Never prune admin accounts. admin_level is NULL for regular users and
        // set (site_admin/super_admin) for staff; the old is_super_admin column
        // was dropped in favor of it. (The User model also hard-blocks deleting
        // a super admin, so a stray admin here would crash the prune.)
        $query = User::whereNull('email_verified_at')
            ->whereNull('admin_level')
            ->where('created_at', '<', $cutoff);

        $count = $query->count();

        if ($this->option('dry-run')) {
            $this->info("Dry run: {$count} unverified account(s) would be deleted (older than {$hours}h).");

            return self::SUCCESS;
        }

        // Scramble email before soft-deleting so the address is immediately
        // available for re-registration. original_email preserves it for the admin dashboard.
        $query->each(function ($user) {
            $user->original_email = $user->email;
            $user->email = $user->id.'@pruned.invalid';
            $user->save();
            $user->delete();
        });

        $this->info("Pruned {$count} unverified account(s) older than {$hours} hours.");

        return self::SUCCESS;
    }
}
