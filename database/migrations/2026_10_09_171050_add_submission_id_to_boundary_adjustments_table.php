<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('boundary_adjustments', function (Blueprint $table) {
            $table->uuid('submission_id')->nullable();
            $table->unique(['team_id', 'submission_id'], 'boundary_adjustments_submission_uq');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('boundary_adjustments', function (Blueprint $table) {
            $table->dropUnique('boundary_adjustments_submission_uq');
            $table->dropColumn('submission_id');
        });
    }
};
