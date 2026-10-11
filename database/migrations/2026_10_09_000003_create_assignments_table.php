<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->unique(['team_id', 'id'], 'assignments_team_id_uq');
            $table->unsignedBigInteger('planned_driver_id');
            $table->unsignedBigInteger('jeepney_id');
            $table->unsignedBigInteger('route_id');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->date('operational_date');
            $table->enum('status', ['planned', 'cancelled', 'confirmed'])->default('planned');
            $table->timestamps();
            $table->foreign(['team_id', 'planned_driver_id'], 'assignments_planned_driver_id_fk')
                ->references(['team_id', 'id'])->on('drivers')->restrictOnDelete();
            $table->foreign(['team_id', 'jeepney_id'], 'assignments_jeepney_id_fk')
                ->references(['team_id', 'id'])->on('jeepneys')->restrictOnDelete();
            $table->foreign(['team_id', 'route_id'], 'assignments_route_id_fk')
                ->references(['team_id', 'id'])->on('routes')->restrictOnDelete();
            $table->index(['team_id', 'operational_date', 'status'], 'assignments_day_status_idx');
            $table->index(['team_id', 'jeepney_id', 'starts_at'], 'assignments_vehicle_start_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
