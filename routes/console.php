<?php

use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;

if (config('database.connections.'.config('database.default').'.driver') === 'sqlite') {
    Schedule::command('operations:backup')->dailyAt('02:00')->timezone('Asia/Manila')->withoutOverlapping();
}

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');
