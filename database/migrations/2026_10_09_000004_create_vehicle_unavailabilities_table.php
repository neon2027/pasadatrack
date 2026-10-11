<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_unavailabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->unique(['team_id', 'id'], 'vehicle_unavailabilities_team_id_uq');
            $table->unsignedBigInteger('jeepney_id');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->text('reason');
            $table->timestamps();
            $table->foreign(['team_id', 'jeepney_id'], 'vehicle_unavailabilities_jeepney_id_fk')
                ->references(['team_id', 'id'])->on('jeepneys')->restrictOnDelete();
            $table->index(['team_id', 'jeepney_id', 'starts_at'], 'vehicle_unavail_start_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_unavailabilities');
    }
};
