<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boundary_obligations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->unique(['team_id', 'id'], 'boundary_obligations_team_id_uq');
            $table->unsignedBigInteger('driving_record_id')->unique('obligations_record_uq');
            // Redundant driver keys allow allocations to enforce same-driver integrity.
            $table->unsignedBigInteger('driver_id');
            $table->decimal('original_amount', 12, 2);
            $table->timestamps();
            $table->foreign(['team_id', 'driver_id'], 'boundary_obligations_driver_id_fk')
                ->references(['team_id', 'id'])->on('drivers')->restrictOnDelete();
            $table->foreign(['team_id', 'driving_record_id', 'driver_id'], 'obligations_record_driver_fk')
                ->references(['team_id', 'id', 'actual_driver_id'])->on('driving_records')->restrictOnDelete();
            $table->unique(['team_id', 'id', 'driver_id'], 'obligations_driver_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boundary_obligations');
    }
};
