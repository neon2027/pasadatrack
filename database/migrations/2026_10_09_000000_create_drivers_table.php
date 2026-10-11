<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * All instants are UTC; operational/driving dates are local business dates.
     * Restrictive keys preserve history. Actors reference users, but membership and
     * authorization must be checked by services. No workflow runs from this schema.
     */
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->unique(['team_id', 'id'], 'drivers_team_id_uq');
            $table->string('code', 50);
            $table->string('name');
            $table->string('license_number', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['team_id', 'code'], 'drivers_team_code_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
