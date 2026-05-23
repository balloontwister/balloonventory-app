<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class BackupDatabase extends Command
{
    protected $signature = 'app:backup-database';

    protected $description = 'Dump the database to storage/app/backups as a gzipped SQL file';

    public function handle(): int
    {
        $backupDir = storage_path('app/backups');

        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $connection = config('database.default');
        $host = config("database.connections.{$connection}.host");
        $database = config("database.connections.{$connection}.database");
        $username = config("database.connections.{$connection}.username");
        $password = config("database.connections.{$connection}.password");

        $filename = 'balloonventory_'.now()->format('Y-m-d_H-i-s').'.sql.gz';
        $backupPath = $backupDir.'/'.$filename;

        // Write credentials to a temp file so the password never appears in
        // the process argument list or shell history.
        $cnfPath = tempnam(sys_get_temp_dir(), 'bv_backup_');
        file_put_contents($cnfPath, "[mysqldump]\nuser={$username}\npassword={$password}\nhost={$host}\n");
        chmod($cnfPath, 0600);

        try {
            $process = Process::fromShellCommandline(
                sprintf(
                    'mysqldump --defaults-extra-file=%s %s | gzip > %s',
                    escapeshellarg($cnfPath),
                    escapeshellarg($database),
                    escapeshellarg($backupPath),
                )
            );

            $process->setTimeout(300);
            $process->run();

            if (! $process->isSuccessful() || ! file_exists($backupPath)) {
                $this->error('Backup failed: '.$process->getErrorOutput());

                return self::FAILURE;
            }
        } finally {
            @unlink($cnfPath);
        }

        $size = round(filesize($backupPath) / 1024, 1);
        $this->info("Backup created: {$filename} ({$size} KB)");

        return self::SUCCESS;
    }
}
