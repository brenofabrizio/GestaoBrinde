<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;

class DatabaseBackupService
{
    public function backup(string $destinationDirectory, bool $force = false): string
    {
        $this->assertAllowed($force);
        $this->assertSqlite();

        $source = $this->sqlitePath();
        if ($source === ':memory:' || ! is_file($source)) {
            throw new RuntimeException('configured SQLite database file does not exist');
        }

        File::ensureDirectoryExists($destinationDirectory);
        $destination = rtrim($destinationDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'database-'.date('Ymd-His').'.sqlite';
        if (! copy($source, $destination)) {
            throw new RuntimeException('could not create database backup');
        }

        return $destination;
    }

    public function restore(string $backup, bool $force = false): void
    {
        $this->assertAllowed($force);
        $this->assertSqlite();

        $backup = realpath($backup) ?: '';
        $backupDirectory = realpath(storage_path('app/backups')) ?: '';
        if ($backup === '' || $backupDirectory === '' || ! str_starts_with($backup, $backupDirectory.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('backup must be an existing file inside storage/app/backups');
        }
        if (pathinfo($backup, PATHINFO_EXTENSION) !== 'sqlite') {
            throw new RuntimeException('backup must use the .sqlite extension');
        }

        $destination = $this->sqlitePath();
        File::ensureDirectoryExists(dirname($destination));
        $temporary = $destination.'.restore-'.bin2hex(random_bytes(6));
        if (! copy($backup, $temporary) || ! rename($temporary, $destination)) {
            @unlink($temporary);
            throw new RuntimeException('could not restore database backup');
        }
    }

    public function driver(): string
    {
        return (string) config('database.default');
    }

    private function assertAllowed(bool $force): void
    {
        if (app()->environment('production') && ! $force) {
            throw new RuntimeException('refused in production without --force');
        }
    }

    private function assertSqlite(): void
    {
        if ($this->driver() !== 'sqlite') {
            throw new RuntimeException('unsupported database driver: '.$this->driver());
        }
    }

    private function sqlitePath(): string
    {
        return (string) config('database.connections.sqlite.database');
    }
}
