<?php

namespace App\Providers;

use App\Models\Attendance\AttendanceSession;
use App\Models\Attendance\BiometricDevice;
use App\Models\Attendance\BiometricDeviceUser;
use App\Models\Boundary\BoundaryObligation;
use App\Models\Boundary\Payment;
use App\Models\Fleet\Driver;
use App\Models\Fleet\Jeepney;
use App\Models\Fleet\TeamScopedModel;
use App\Models\Scheduling\Assignment;
use App\Models\Scheduling\VehicleUnavailability;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([
            'driver' => Driver::class,
            'jeepney' => Jeepney::class,
            'route' => \App\Models\Fleet\Route::class,
            'assignment' => Assignment::class,
            'unavailability' => VehicleUnavailability::class,
            'payment' => Payment::class,
            'obligation' => BoundaryObligation::class,
            'attendance' => AttendanceSession::class,
            'device' => BiometricDevice::class,
            'enrollment' => BiometricDeviceUser::class,
        ] as $parameter => $model) {
            Route::bind($parameter, function (string $value, \Illuminate\Routing\Route $route) use ($model): TeamScopedModel {
                $team = $route->parameter('current_team');
                $team = $team instanceof Team ? $team : Team::query()->where('slug', $team)->firstOrFail();

                return $model::forTeam($team)->findOrFail($value);
            });
        }

        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
