<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jeepneys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->unique(['team_id', 'id'], 'jeepneys_team_id_uq');
            $table->string('plate_number', 30);
            $table->string('name', 100)->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['team_id', 'plate_number'], 'jeepneys_team_plate_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jeepneys');
    }
};
