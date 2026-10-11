<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driving_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->unique(['team_id', 'id'], 'driving_records_team_id_uq');
            $table->unsignedBigInteger('assignment_id')->unique('driving_records_assignment_uq');
            $table->unsignedBigInteger('actual_driver_id');
            $table->foreignId('confirmed_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('confirmed_at');
            $table->decimal('boundary_amount', 12, 2);
            $table->date('driving_date');
            $table->timestamps();
            $table->foreign(['team_id', 'assignment_id'], 'driving_records_assignment_id_fk')
                ->references(['team_id', 'id'])->on('assignments')->restrictOnDelete();
            $table->foreign(['team_id', 'actual_driver_id'], 'driving_records_actual_driver_id_fk')
                ->references(['team_id', 'id'])->on('drivers')->restrictOnDelete();
            $table->unique(['team_id', 'id', 'actual_driver_id'], 'driving_records_driver_uq');
            $table->index(['team_id', 'actual_driver_id', 'driving_date'], 'driving_records_driver_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driving_records');
    }
};
