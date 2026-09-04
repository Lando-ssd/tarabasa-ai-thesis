<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Translates schema.sql's reading_sessions table (Table 14) directly
     * — no shape conflict, unlike activities. Nullable columns with no
     * default (not schema.sql's DEFAULT 0) for mispronunciation_count and
     * repetition_count specifically: Reading-api's real word-diff output
     * only distinguishes substitutions/deletions/insertions, so these two
     * genuinely can't be measured from this data source. NULL means "not
     * measured," never a fabricated "confirmed zero" — same for
     * pronunciation_score/fluency_score, which have no direct equivalent
     * in Reading-api's real response either.
     */
    public function up(): void
    {
        Schema::create('reading_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained('learners')->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained('activities')->cascadeOnDelete();
            $table->float('accuracy_percent')->nullable();
            $table->float('wcpm')->nullable();
            $table->float('pronunciation_score')->nullable();
            $table->float('fluency_score')->nullable();
            $table->unsignedInteger('mispronunciation_count')->nullable();
            $table->unsignedInteger('skipped_word_count')->nullable();
            $table->unsignedInteger('substitution_count')->nullable();
            $table->unsignedInteger('repetition_count')->nullable();
            $table->unsignedInteger('insertion_count')->nullable();
            $table->enum('level_before', ['Beginning', 'Developing', 'Proficient'])->nullable();
            $table->enum('level_after', ['Beginning', 'Developing', 'Proficient'])->nullable();
            $table->boolean('flagged_needs_attention')->default(false);
            $table->enum('session_type', ['Diagnostic', 'Practice', 'Assessment']);
            $table->enum('initiated_by', ['Teacher', 'Parent']);
            $table->timestamp('timestamp')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_sessions');
    }
};
