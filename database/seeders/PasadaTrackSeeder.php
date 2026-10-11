<?php

namespace Database\Seeders;

use App\Models\Attendance\BiometricDevice;
use App\Models\Fleet\Driver;
use App\Models\Fleet\Jeepney;
use App\Models\Fleet\Route;
use App\Models\Team;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Opt-in, repeatable master data only; no users, memberships, scans or ledger rows. */
class PasadaTrackSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('PasadaTrack demo data is restricted to local and testing environments.');
        }

        DB::transaction(function (): void {
            $team = Team::query()->firstOrCreate(['slug' => 'pasadatrack-demo'], [
                'name' => 'PasadaTrack Demo Cooperative',
                'is_personal' => false,
            ]);

            foreach (['DEMO-001' => 'Juan Dela Cruz', 'DEMO-002' => 'Mario Santos', 'DEMO-003' => 'Ramon Reyes'] as $code => $name) {
                Driver::query()->firstOrCreate(['team_id' => $team->id, 'code' => $code], [
                    'name' => $name,
                    'is_active' => true,
                ]);
            }

            foreach (['DEMO-01', 'DEMO-02'] as $plate) {
                Jeepney::query()->firstOrCreate(['team_id' => $team->id, 'plate_number' => $plate], [
                    'name' => 'Demo jeepney '.$plate,
                    'capacity' => 20,
                    'is_active' => true,
                ]);
            }

            foreach (['CUB-QPO' => ['Cubao', 'Quiapo', '1200.00'], 'CUB-MRK' => ['Cubao', 'Marikina', '1000.00']] as $code => [$origin, $destination, $amount]) {
                Route::query()->firstOrCreate(['team_id' => $team->id, 'code' => $code], [
                    'name' => $origin.' - '.$destination,
                    'origin' => $origin,
                    'destination' => $destination,
                    'default_boundary_amount' => $amount,
                    'is_active' => true,
                ]);
            }

            BiometricDevice::query()->firstOrCreate(['team_id' => $team->id, 'code' => 'DEMO-BIO-01'], [
                'name' => 'Demo terminal attendance device',
                'metadata' => ['location' => 'Demo terminal', 'demo' => true],
                'is_active' => true,
            ]);
        });
    }
}
