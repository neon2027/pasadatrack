<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->unique(['team_id', 'id'], 'biometric_devices_team_id_uq');
            $table->string('code', 50);
            $table->string('name');
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['team_id', 'code'], 'devices_team_code_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_devices');
    }
};
