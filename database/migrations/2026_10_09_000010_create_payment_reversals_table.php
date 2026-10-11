<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_reversals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->unique(['team_id', 'id'], 'payment_reversals_team_id_uq');
            $table->unsignedBigInteger('payment_id')->unique('reversals_payment_uq');
            $table->text('reason');
            $table->foreignId('reversed_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('reversed_at');
            $table->timestamps();
            $table->foreign(['team_id', 'payment_id'], 'payment_reversals_payment_id_fk')
                ->references(['team_id', 'id'])->on('payments')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reversals');
    }
};
