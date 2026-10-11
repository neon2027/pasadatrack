<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->unique(['team_id', 'id'], 'biometric_events_team_id_uq');
            $table->unsignedBigInteger('biometric_device_id');
            $table->string('event_key', 100);
            // No mapping FK: unknown users must not prevent raw scan persistence.
            $table->string('external_user_id', 100);
            $table->json('raw_metadata');
            $table->dateTime('occurred_at');
            $table->timestamps();
            $table->foreign(['team_id', 'biometric_device_id'], 'biometric_events_biometric_device_id_fk')
                ->references(['team_id', 'id'])->on('biometric_devices')->restrictOnDelete();
            $table->unique(['biometric_device_id', 'event_key'], 'events_device_key_uq');
            $table->index(['team_id', 'occurred_at'], 'events_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_events');
    }
};
