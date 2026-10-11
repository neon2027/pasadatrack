<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SQLite3;
use Throwable;

#[Signature('operations:backup')]
#[Description('Create and integrity-check a private snapshot of the configured SQLite database')]
class BackupOperationsDatabase extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $connection = config('database.connections.'.config('database.default'));
        $sourcePath = $connection['database'] ?? '';
        if (($connection['driver'] ?? null) !== 'sqlite' || ! is_file($sourcePath)) {
            $this->error('A file-backed SQLite database is required. Configure platform backups for other database engines.');

            return self::FAILURE;
        }
        $disk = Storage::disk('local');
        $disk->makeDirectory('backups');
        $filename = 'backups/pasadatrack-'.now('UTC')->format('Ymd-His').'-'.Str::uuid().'.sqlite';
        $source = null;
        $destination = null;
        $verified = false;
        try {
            $source = new SQLite3($sourcePath, SQLITE3_OPEN_READONLY);
            $source->enableExceptions(true);
            $destination = new SQLite3($disk->path($filename));
            $destination->enableExceptions(true);
            if (! $source->backup($destination) || $destination->querySingle('PRAGMA integrity_check') !== 'ok') {
                throw new \RuntimeException('The database snapshot failed its integrity check.');
            }
            $verified = true;
        } catch (Throwable $error) {
            report($error);
            $this->error('Backup failed. Check the application log.');

            return self::FAILURE;
        } finally {
            $destination?->close();
            $source?->close();
            if (! $verified) {
                $disk->delete($filename);
            }
        }
        $this->info('Verified private database backup: '.$filename);

        return self::SUCCESS;
    }
}
