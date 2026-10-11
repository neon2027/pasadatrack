<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->unique(['team_id', 'id'], 'attendance_sessions_team_id_uq');
            $table->unsignedBigInteger('driver_id');
            $table->enum('source', ['manual', 'biometric']);
            $table->unsignedBigInteger('in_event_id')->nullable();
            $table->unsignedBigInteger('out_event_id')->nullable();
            $table->dateTime('in_at');
            $table->dateTime('out_at')->nullable();
            $table->timestamps();
            $table->foreign(['team_id', 'driver_id'], 'attendance_sessions_driver_id_fk')
                ->references(['team_id', 'id'])->on('drivers')->restrictOnDelete();
            $table->foreign(['team_id', 'in_event_id'], 'attendance_sessions_in_event_id_fk')
                ->references(['team_id', 'id'])->on('biometric_events')->restrictOnDelete();
            $table->foreign(['team_id', 'out_event_id'], 'attendance_sessions_out_event_id_fk')
                ->references(['team_id', 'id'])->on('biometric_events')->restrictOnDelete();
            $table->index(['team_id', 'driver_id', 'in_at'], 'sessions_driver_in_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');
    }
};
