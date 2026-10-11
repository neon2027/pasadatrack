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
        Schema::table('biometric_device_users', function (Blueprint $table) {
            $table->dateTime('revoked_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('biometric_device_users', function (Blueprint $table) {
            $table->dropColumn('revoked_at');
        });
    }
};
