<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->unique(['team_id', 'id'], 'payments_team_id_uq');
            $table->unsignedBigInteger('driver_id');
            $table->decimal('amount', 12, 2);
            $table->dateTime('collected_at');
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->string('receipt_number', 100);
            $table->timestamps();
            $table->foreign(['team_id', 'driver_id'], 'payments_driver_id_fk')
                ->references(['team_id', 'id'])->on('drivers')->restrictOnDelete();
            $table->unique(['team_id', 'receipt_number'], 'payments_team_receipt_uq');
            $table->unique(['team_id', 'id', 'driver_id'], 'payments_driver_uq');
            $table->index(['team_id', 'driver_id', 'collected_at'], 'payments_driver_collected_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
