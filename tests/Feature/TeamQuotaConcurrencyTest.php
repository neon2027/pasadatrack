<?php

namespace Tests\Feature;

use App\Actions\Teams\CreateTeam;
use App\Models\PrivilegeLevel;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TeamQuotaConcurrencyTest extends TestCase
{
    public function test_concurrent_team_creations_cannot_exceed_the_owner_limit(): void
    {
        $databasePath = tempnam(sys_get_temp_dir(), 'pasadatrack-quota-');
        self::assertIsString($databasePath);
        $originalConnection = config('database.default');
        config(['database.connections.quota' => ['driver' => 'sqlite', 'database' => $databasePath, 'foreign_key_constraints' => true, 'busy_timeout' => 10000], 'database.default' => 'quota']);
        try {
            Artisan::call('migrate', ['--database' => 'quota', '--force' => true, '--no-interaction' => true]);
            PrivilegeLevel::query()->findOrFail(2)->update(['team_limit' => 2]);
            $user = User::factory()->create(['privilege_level_id' => 2]);
            $userId = $user->id;
            $worker = static function () use ($databasePath, $userId): string {
                config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $databasePath, 'database.connections.sqlite.url' => null, 'database.connections.sqlite.busy_timeout' => 10000]);
                DB::purge('sqlite');
                try {
                    app(CreateTeam::class)->handle(User::query()->findOrFail($userId), 'Concurrent Team');

                    return 'created';
                } catch (ValidationException) {
                    return 'quota-denied';
                }
            };
            $results = Concurrency::driver('process')->run([$worker, $worker], 30);
            sort($results);
            self::assertSame(['created', 'quota-denied'], $results);
            self::assertSame(2, $user->ownedTeams()->count());
        } finally {
            DB::purge('quota');
            config(['database.default' => $originalConnection]);
            unlink($databasePath);
        }
    }
}
