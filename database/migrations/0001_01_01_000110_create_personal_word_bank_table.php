<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Translates schema.sql's personal_word_bank table (Table 15)
     * directly. skill_type stays nullable and unpopulated for now —
     * Reading-api's word_feedback doesn't categorize missed words by
     * skill, so there's nothing real to put there yet.
     */
    public function up(): void
    {
        Schema::create('personal_word_bank', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained('learners')->cascadeOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('reading_sessions')->nullOnDelete();
            $table->string('word');
            $table->string('skill_type')->nullable();
            $table->enum('mastery_status', ['Struggling', 'Improving', 'Mastered']);
            $table->unsignedInteger('times_drilled')->default(0);
            $table->timestamp('last_reviewed')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_word_bank');
    }
};
