<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_device_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->unique(['team_id', 'id'], 'biometric_device_users_team_id_uq');
            $table->unsignedBigInteger('biometric_device_id');
            $table->string('external_user_id', 100);
            $table->unsignedBigInteger('driver_id');
            $table->timestamps();
            $table->foreign(['team_id', 'biometric_device_id'], 'biometric_device_users_biometric_device_id_fk')
                ->references(['team_id', 'id'])->on('biometric_devices')->restrictOnDelete();
            $table->foreign(['team_id', 'driver_id'], 'biometric_device_users_driver_id_fk')
                ->references(['team_id', 'id'])->on('drivers')->restrictOnDelete();
            $table->unique(['biometric_device_id', 'external_user_id'], 'device_users_external_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_device_users');
    }
};
