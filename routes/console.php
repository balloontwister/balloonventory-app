<?php

use Illuminate\Support\Facades\Schedule;

// Delete user accounts that were registered but never verified within 24 hours.
// Runs once per day at 3:00 AM server time.
Schedule::command('app:prune-unverified-users')->dailyAt('03:00');

// Process the queued email worker every minute. Uses --stop-when-empty so the
// process exits cleanly after draining pending jobs (daemon mode is not
// available on the cPanel/shared host). withoutOverlapping() prevents a second
// worker starting if a batch runs long.
Schedule::command('queue:work --stop-when-empty --tries=3 --queue=default')
    ->everyMinute()
    ->withoutOverlapping();
