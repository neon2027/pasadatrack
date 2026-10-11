<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use SQLite3;
use Tests\TestCase;

class OperationsBackupTest extends TestCase
{
    public function test_backup_produces_a_readable_snapshot_of_committed_data(): void
    {
        Storage::fake('local');
        $path = Storage::disk('local')->path('source.sqlite');
        $database = new SQLite3($path);
        $database->exec('CREATE TABLE backup_fixture (name TEXT)');
        $database->exec("INSERT INTO backup_fixture VALUES ('Preserved record')");
        $database->close();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $path]);

        $this->artisan('operations:backup')->assertSuccessful();

        $files = Storage::disk('local')->allFiles('backups');
        $this->assertCount(1, $files);
        $snapshot = new SQLite3(Storage::disk('local')->path($files[0]), SQLITE3_OPEN_READONLY);
        $this->assertSame('Preserved record', $snapshot->querySingle('SELECT name FROM backup_fixture'));
        $this->assertSame('ok', $snapshot->querySingle('PRAGMA integrity_check'));
        $snapshot->close();
    }

    public function test_backup_rejects_an_in_memory_database(): void
    {
        Storage::fake('local');
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);

        $this->artisan('operations:backup')->assertFailed();

        $this->assertSame([], Storage::disk('local')->allFiles('backups'));
    }

    public function test_backup_requires_platform_configuration_for_other_engines(): void
    {
        Storage::fake('local');
        config(['database.default' => 'mysql']);

        $this->artisan('operations:backup')->assertFailed();

        $this->assertSame([], Storage::disk('local')->allFiles('backups'));
    }
}
