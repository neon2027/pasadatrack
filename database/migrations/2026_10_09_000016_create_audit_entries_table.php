<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->unique(['team_id', 'id'], 'audit_entries_team_id_uq');
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action', 100);
            $table->string('subject_type', 100);
            $table->unsignedBigInteger('subject_id');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('reason')->nullable();
            $table->dateTime('occurred_at');
            $table->timestamps();
            $table->index(['team_id', 'subject_type', 'subject_id'], 'audit_subject_idx');
            $table->index(['team_id', 'occurred_at'], 'audit_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_entries');
    }
};
