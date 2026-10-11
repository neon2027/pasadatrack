<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Middleware\EnsurePlatformAdministrator;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified', EnsurePlatformAdministrator::class])->group(function (): void {
    Route::get('users', [AdminController::class, 'users'])->name('users.index');
    Route::get('roles', [AdminController::class, 'roles'])->name('roles.index');
    Route::get('levels', [AdminController::class, 'levels'])->name('levels.index');
    Route::get('audit', [AdminController::class, 'audit'])->name('audit.index');
    Route::middleware([RequirePassword::using('password.confirm', 300), 'throttle:30,1'])->group(function (): void {
        Route::post('users', [AdminController::class, 'provision'])->name('users.store');
        Route::patch('users/{user}', [AdminController::class, 'updateUser'])->name('users.update');
        Route::post('roles', [AdminController::class, 'storeRole'])->name('roles.store');
        Route::patch('roles/{role}', [AdminController::class, 'updateRole'])->name('roles.update');
        Route::patch('levels/{level}', [AdminController::class, 'updateLevel'])->name('levels.update');
    });
});
