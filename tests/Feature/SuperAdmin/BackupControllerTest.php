<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupControllerTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    private function regularUser(): User
    {
        return User::factory()->create();
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_super_admin_can_view_backups_index(): void
    {
        $response = $this->actingAs($this->superAdmin())
            ->get(route('super-admin.backups.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SuperAdmin/Backups/Index')
                ->has('backups')
            );
    }

    public function test_regular_user_cannot_view_backups_index(): void
    {
        $this->actingAs($this->regularUser())
            ->get(route('super-admin.backups.index'))
            ->assertForbidden();
    }

    // ── Download ──────────────────────────────────────────────────────────────

    public function test_super_admin_can_download_backup(): void
    {
        $backupDir = storage_path('app/backups');

        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $filename = 'balloonventory_2026-01-01_02-00-00.sql.gz';
        $path = $backupDir.'/'.$filename;
        file_put_contents($path, 'fake backup content');

        try {
            $response = $this->actingAs($this->superAdmin())
                ->get(route('super-admin.backups.download', $filename));

            $response->assertOk()
                ->assertDownload($filename);
        } finally {
            @unlink($path);
        }
    }

    public function test_download_rejects_path_traversal(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('super-admin.backups.download', '../.env'))
            ->assertNotFound();
    }

    public function test_download_returns_404_for_missing_file(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('super-admin.backups.download', 'balloonventory_2020-01-01_00-00-00.sql.gz'))
            ->assertNotFound();
    }

    public function test_regular_user_cannot_download_backup(): void
    {
        $this->actingAs($this->regularUser())
            ->get(route('super-admin.backups.download', 'balloonventory_2026-01-01_02-00-00.sql.gz'))
            ->assertForbidden();
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_super_admin_can_delete_backup(): void
    {
        $backupDir = storage_path('app/backups');

        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $filename = 'balloonventory_2026-01-01_02-00-00.sql.gz';
        $path = $backupDir.'/'.$filename;
        file_put_contents($path, 'fake backup content');

        $this->actingAs($this->superAdmin())
            ->delete(route('super-admin.backups.destroy', $filename))
            ->assertRedirect();

        $this->assertFileDoesNotExist($path);
    }

    public function test_delete_rejects_path_traversal(): void
    {
        $this->actingAs($this->superAdmin())
            ->delete(route('super-admin.backups.destroy', '../.env'))
            ->assertNotFound();
    }

    public function test_regular_user_cannot_delete_backup(): void
    {
        $this->actingAs($this->regularUser())
            ->delete(route('super-admin.backups.destroy', 'balloonventory_2026-01-01_02-00-00.sql.gz'))
            ->assertForbidden();
    }
}
