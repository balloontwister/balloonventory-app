<?php

use Illuminate\Support\Facades\Schedule;

// Delete user accounts that were registered but never verified within 24 hours.
// Runs once per day at 3:00 AM server time.
Schedule::command('app:prune-unverified-users')->dailyAt('03:00');

// Create a daily database backup at 2:00 AM server time.
Schedule::command('app:backup-database')->dailyAt('02:00');

// Thin out old backups after the nightly dump: daily for 30 days, then monthly,
// quarterly, and yearly. Keeps the backup list from growing without bound.
Schedule::command('app:prune-backups')->dailyAt('02:30');

// Prune login history past its 18-month retention window.
Schedule::command('app:prune-login-events')->dailyAt('03:15');

// Process the queued email worker every minute. Uses --stop-when-empty so the
// process exits cleanly after draining pending jobs (daemon mode is not
// available on the cPanel/shared host). withoutOverlapping() prevents a second
// worker starting if a batch runs long.
Schedule::command('queue:work --stop-when-empty --tries=3 --queue=default')
    ->everyMinute()
    ->withoutOverlapping();
