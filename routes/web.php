<?php

use App\Http\Controllers\Attendance\AttendanceController;
use App\Http\Controllers\Attendance\BiometricDeviceController;
use App\Http\Controllers\Audit\AuditController;
use App\Http\Controllers\Boundary\CollectionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Fleet\DriverController;
use App\Http\Controllers\Fleet\JeepneyController;
use App\Http\Controllers\Fleet\RouteController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Scheduling\AssignmentController;
use App\Http\Controllers\Scheduling\VehicleUnavailabilityController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');
Route::inertia('/terms-and-conditions', 'terms-and-conditions')->name('terms');
Route::inertia('/privacy-policy', 'privacy-policy')->name('privacy');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::middleware(EnsureTeamMembership::class.':owner')->group(function () {
            Route::resource('drivers', DriverController::class)->only(['index', 'store', 'update']);
            Route::resource('jeepneys', JeepneyController::class)->only(['index', 'store', 'update']);
            Route::resource('routes', RouteController::class)->only(['index', 'store', 'update']);
            Route::resource('assignments', AssignmentController::class)->only(['index', 'store', 'update']);
            Route::post('assignments/{assignment}/confirm', [AssignmentController::class, 'confirm'])->name('assignments.confirm');
            Route::post('assignments/{assignment}/cancel', [AssignmentController::class, 'cancel'])->name('assignments.cancel');
            Route::post('unavailabilities', [VehicleUnavailabilityController::class, 'store'])->name('unavailabilities.store');
            Route::delete('unavailabilities/{unavailability}', [VehicleUnavailabilityController::class, 'destroy'])->name('unavailabilities.destroy');
            Route::get('collections', [CollectionController::class, 'index'])->name('collections.index');
            Route::post('collections', [CollectionController::class, 'store'])->name('collections.store');
            Route::post('payments/{payment}/allocations', [CollectionController::class, 'allocate'])->name('collections.allocate');
            Route::post('payments/{payment}/reversal', [CollectionController::class, 'reverse'])->name('collections.reverse');
            Route::post('obligations/{obligation}/adjustments', [CollectionController::class, 'adjust'])->name('collections.adjust');
            Route::resource('attendance', AttendanceController::class)->only(['index', 'store', 'update']);
            Route::get('attendance-devices', [BiometricDeviceController::class, 'index'])->name('devices.index');
            Route::post('attendance-devices', [BiometricDeviceController::class, 'store'])->name('devices.store');
            Route::patch('attendance-devices/{device}', [BiometricDeviceController::class, 'update'])->name('devices.update');
            Route::post('attendance-devices/{device}/enrollment', [BiometricDeviceController::class, 'enroll'])->name('devices.enroll');
            Route::post('attendance-devices/{device}/enrollments/{enrollment}/revoke', [BiometricDeviceController::class, 'revoke'])->name('devices.revoke');
            Route::post('attendance-devices/{device}/import', [BiometricDeviceController::class, 'import'])->middleware('throttle:30,1')->name('devices.import');
            Route::post('attendance-devices/{device}/process', [BiometricDeviceController::class, 'process'])->middleware('throttle:30,1')->name('devices.process');
            Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('reports/export', [ReportController::class, 'export'])->middleware('throttle:10,1')->name('reports.export');
            Route::get('audit', [AuditController::class, 'index'])->name('audit.index');
        });
    });

Route::middleware(['auth'])->group(function () {
    Route::post('onboarding/dismiss', [OnboardingController::class, 'store'])->name('onboarding.dismiss');
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
