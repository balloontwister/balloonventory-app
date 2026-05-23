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

        // Pass the password via MYSQL_PWD so it never appears in the process
        // argument list or shell history, and avoids cnf-file special-char issues.
        $process = Process::fromShellCommandline(
            sprintf(
                'mysqldump -u %s -h %s %s | gzip > %s',
                escapeshellarg($username),
                escapeshellarg($host),
                escapeshellarg($database),
                escapeshellarg($backupPath),
            )
        );

        $process->setEnv(['MYSQL_PWD' => $password]);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful() || ! file_exists($backupPath) || filesize($backupPath) === 0) {
            $this->error('Backup failed: '.$process->getErrorOutput());

            @unlink($backupPath);

            return self::FAILURE;
        }

        $size = round(filesize($backupPath) / 1024, 1);
        $this->info("Backup created: {$filename} ({$size} KB)");

        return self::SUCCESS;
    }
}
