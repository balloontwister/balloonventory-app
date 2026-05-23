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

    // ── Rename ────────────────────────────────────────────────────────────────

    public function test_super_admin_can_rename_backup(): void
    {
        $backupDir = storage_path('app/backups');

        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $oldFilename = 'balloonventory_2026-01-01_02-00-00.sql.gz';
        $newFilename = 'my-pre-launch-backup.sql.gz';
        $oldPath = $backupDir.'/'.$oldFilename;
        $newPath = $backupDir.'/'.$newFilename;
        file_put_contents($oldPath, 'fake backup content');

        try {
            $this->actingAs($this->superAdmin())
                ->patch(route('super-admin.backups.rename', $oldFilename), [
                    'new_filename' => $newFilename,
                ])
                ->assertRedirect();

            $this->assertFileExists($newPath);
            $this->assertFileDoesNotExist($oldPath);
        } finally {
            @unlink($oldPath);
            @unlink($newPath);
        }
    }

    public function test_rename_with_same_filename_is_a_no_op(): void
    {
        $backupDir = storage_path('app/backups');

        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $filename = 'balloonventory_2026-01-01_02-00-00.sql.gz';
        $path = $backupDir.'/'.$filename;
        file_put_contents($path, 'fake backup content');

        try {
            $this->actingAs($this->superAdmin())
                ->patch(route('super-admin.backups.rename', $filename), [
                    'new_filename' => $filename,
                ])
                ->assertRedirect();

            $this->assertFileExists($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_rename_returns_error_when_target_filename_already_exists(): void
    {
        $backupDir = storage_path('app/backups');

        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $filename1 = 'balloonventory_2026-01-01_02-00-00.sql.gz';
        $filename2 = 'balloonventory_2026-01-02_02-00-00.sql.gz';
        $path1 = $backupDir.'/'.$filename1;
        $path2 = $backupDir.'/'.$filename2;
        file_put_contents($path1, 'fake backup 1');
        file_put_contents($path2, 'fake backup 2');

        try {
            $this->actingAs($this->superAdmin())
                ->patch(route('super-admin.backups.rename', $filename1), [
                    'new_filename' => $filename2,
                ])
                ->assertSessionHasErrors(['new_filename']);

            $this->assertFileExists($path1);
        } finally {
            @unlink($path1);
            @unlink($path2);
        }
    }

    public function test_rename_returns_error_for_invalid_filename(): void
    {
        $backupDir = storage_path('app/backups');

        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $filename = 'balloonventory_2026-01-01_02-00-00.sql.gz';
        $path = $backupDir.'/'.$filename;
        file_put_contents($path, 'fake backup content');

        try {
            $this->actingAs($this->superAdmin())
                ->patch(route('super-admin.backups.rename', $filename), [
                    'new_filename' => 'invalid name with spaces.sql.gz',
                ])
                ->assertSessionHasErrors(['new_filename']);

            $this->assertFileExists($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_rename_rejects_path_traversal(): void
    {
        $this->actingAs($this->superAdmin())
            ->patch(route('super-admin.backups.rename', '../.env'), [
                'new_filename' => 'safe-name.sql.gz',
            ])
            ->assertNotFound();
    }

    public function test_regular_user_cannot_rename_backup(): void
    {
        $this->actingAs($this->regularUser())
            ->patch(route('super-admin.backups.rename', 'balloonventory_2026-01-01_02-00-00.sql.gz'), [
                'new_filename' => 'other.sql.gz',
            ])
            ->assertForbidden();
    }
}
