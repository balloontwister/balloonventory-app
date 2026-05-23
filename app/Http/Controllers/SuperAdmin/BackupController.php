<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function index(): Response
    {
        $backupDir = storage_path('app/backups');
        $backups = [];

        if (is_dir($backupDir)) {
            foreach (glob($backupDir.'/*.sql.gz') as $path) {
                $backups[] = [
                    'filename' => basename($path),
                    'size_kb' => round(filesize($path) / 1024, 1),
                    'created_at' => date('Y-m-d H:i:s', filemtime($path)),
                ];
            }

            usort($backups, fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));
        }

        return Inertia::render('SuperAdmin/Backups/Index', [
            'backups' => $backups,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        Artisan::call('app:backup-database');

        return back()->with('success', 'Backup created successfully.');
    }

    public function download(Request $request, string $filename): BinaryFileResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $path = $this->resolveBackupPath($filename);

        return response()->download($path);
    }

    public function rename(Request $request, string $filename): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $oldPath = $this->resolveBackupPath($filename);

        $request->validate([
            'new_filename' => [
                'required',
                'string',
                'regex:/^[a-zA-Z0-9][a-zA-Z0-9_-]*\.sql\.gz$/',
            ],
        ]);

        $newFilename = $request->input('new_filename');
        $newPath = storage_path('app/backups/'.$newFilename);

        if ($newFilename !== $filename) {
            if (file_exists($newPath)) {
                throw ValidationException::withMessages([
                    'new_filename' => __('super_admin.backups.rename_error_exists'),
                ]);
            }

            rename($oldPath, $newPath);
        }

        return back()->with('success', 'Backup renamed.');
    }

    public function destroy(Request $request, string $filename): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $path = $this->resolveBackupPath($filename);
        unlink($path);

        return back()->with('success', 'Backup deleted.');
    }

    private function resolveBackupPath(string $filename): string
    {
        abort_unless(preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]*\.sql\.gz$/', $filename), 404);

        $path = storage_path('app/backups/'.$filename);
        abort_unless(file_exists($path), 404);

        return $path;
    }
}
