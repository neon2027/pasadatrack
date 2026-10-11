<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->unique(['team_id', 'id'], 'routes_team_id_uq');
            $table->string('code', 50);
            $table->string('name');
            $table->string('origin');
            $table->string('destination');
            $table->decimal('default_boundary_amount', 12, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['team_id', 'code'], 'routes_team_code_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routes');
    }
};
