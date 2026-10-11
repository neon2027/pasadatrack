<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->unique(['team_id', 'id'], 'attendance_corrections_team_id_uq');
            $table->unsignedBigInteger('attendance_session_id');
            $table->text('reason');
            $table->foreignId('corrected_by')->constrained('users')->restrictOnDelete();
            $table->json('before');
            $table->json('after');
            $table->dateTime('corrected_at');
            $table->timestamps();
            $table->foreign(['team_id', 'attendance_session_id'], 'attendance_corrections_attendance_session_id_fk')
                ->references(['team_id', 'id'])->on('attendance_sessions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_corrections');
    }
};
