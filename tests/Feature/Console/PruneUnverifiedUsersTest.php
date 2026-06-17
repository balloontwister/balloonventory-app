<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneUnverifiedUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_prunes_an_unverified_non_admin_past_the_grace_period(): void
    {
        $user = User::factory()->create([
            'email' => 'stale@example.com',
            'email_verified_at' => null,
            'admin_level' => null,
            'created_at' => now()->subHours(25),
        ]);

        $this->artisan('app:prune-unverified-users')->assertExitCode(0);

        $this->assertSoftDeleted('users', ['id' => $user->id]);

        // Email is scrambled for immediate re-registration; original preserved.
        $pruned = User::withTrashed()->find($user->id);
        $this->assertSame($user->id.'@pruned.invalid', $pruned->email);
        $this->assertSame('stale@example.com', $pruned->original_email);
    }

    public function test_does_not_prune_admins(): void
    {
        $superAdmin = User::factory()->superAdmin()->create([
            'email_verified_at' => null,
            'created_at' => now()->subHours(48),
        ]);
        $siteAdmin = User::factory()->siteAdmin()->create([
            'email_verified_at' => null,
            'created_at' => now()->subHours(48),
        ]);

        $this->artisan('app:prune-unverified-users')->assertExitCode(0);

        $this->assertNotSoftDeleted('users', ['id' => $superAdmin->id]);
        $this->assertNotSoftDeleted('users', ['id' => $siteAdmin->id]);
    }

    public function test_does_not_prune_recently_created_unverified_users(): void
    {
        $recent = User::factory()->create([
            'email_verified_at' => null,
            'admin_level' => null,
            'created_at' => now()->subHour(),
        ]);

        $this->artisan('app:prune-unverified-users')->assertExitCode(0);

        $this->assertNotSoftDeleted('users', ['id' => $recent->id]);
    }

    public function test_does_not_prune_verified_users(): void
    {
        $verified = User::factory()->create([
            'email_verified_at' => now(),
            'admin_level' => null,
            'created_at' => now()->subHours(72),
        ]);

        $this->artisan('app:prune-unverified-users')->assertExitCode(0);

        $this->assertNotSoftDeleted('users', ['id' => $verified->id]);
    }

    public function test_dry_run_reports_without_deleting(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
            'admin_level' => null,
            'created_at' => now()->subHours(25),
        ]);

        $this->artisan('app:prune-unverified-users', ['--dry-run' => true])
            ->assertExitCode(0);

        $this->assertNotSoftDeleted('users', ['id' => $user->id]);
        $this->assertSame($user->email, $user->fresh()->email);
    }
}
