<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->unique(['team_id', 'id'], 'payment_allocations_team_id_uq');
            $table->unsignedBigInteger('payment_id');
            $table->unsignedBigInteger('boundary_obligation_id');
            $table->unsignedBigInteger('driver_id');
            $table->decimal('amount', 12, 2);
            $table->timestamps();
            $table->foreign(['team_id', 'payment_id', 'driver_id'], 'allocations_payment_driver_fk')
                ->references(['team_id', 'id', 'driver_id'])->on('payments')->restrictOnDelete();
            $table->foreign(['team_id', 'boundary_obligation_id', 'driver_id'], 'allocations_obligation_driver_fk')
                ->references(['team_id', 'id', 'driver_id'])->on('boundary_obligations')->restrictOnDelete();
            $table->unique(['payment_id', 'boundary_obligation_id'], 'allocations_payment_obligation_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
