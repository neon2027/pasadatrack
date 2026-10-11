<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boundary_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->unique(['team_id', 'id'], 'boundary_adjustments_team_id_uq');
            $table->unsignedBigInteger('boundary_obligation_id');
            $table->decimal('amount', 12, 2);
            $table->text('reason');
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('approved_at');
            $table->timestamps();
            $table->foreign(['team_id', 'boundary_obligation_id'], 'boundary_adjustments_boundary_obligation_id_fk')
                ->references(['team_id', 'id'])->on('boundary_obligations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boundary_adjustments');
    }
};
