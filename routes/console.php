<?php

use Illuminate\Support\Facades\Schedule;

// Delete user accounts that were registered but never verified within 24 hours.
// Runs once per day at 3:00 AM server time.
Schedule::command('app:prune-unverified-users')->dailyAt('03:00');
