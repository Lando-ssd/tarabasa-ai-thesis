<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Translates schema.sql's `activities` table (Table 9) into what the
     * real, deployed gemini_activity_gen API (v3.1.0) actually returns —
     * NOT a 1:1 translation. schema.sql models a single game_type +
     * single difficulty_tier per row with curriculum_guide_id grounding;
     * the real API returns 3 difficulty tiers per call, has no gameType
     * concept, and has no curriculum-guide grounding at all. See
     * CLAUDE.md's "PROVISIONAL, NOT FINAL" section for the full conflict
     * and why this is a stopgap, not a settled decision.
     *
     * One row is saved per (difficulty tier × variant) the API returns
     * from a single generation call, all sharing the same generation_id.
     */
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by_teacher_id')->nullable()->constrained('teachers')->nullOnDelete();

            // Ties every row from one "Generate" click together so they can
            // be reviewed/displayed as a batch.
            $table->string('generation_id')->nullable()->index();
            $table->string('bundle_title')->nullable();

            $table->enum('grade_level', ['Grade 1', 'Grade 2', 'Grade 3']);

            // The API's own vocabulary (foundational_reading, word_reading,
            // etc.) — deliberately NOT constrained to a fixed DB enum since
            // this is a third-party registry we don't own; validated against
            // config('services.activity_ai.competencies') in the controller.
            $table->string('competency');
            $table->string('competency_label');
            $table->string('activity_type');

            $table->enum('difficulty_tier', ['Easy', 'Medium', 'Hard']);
            $table->string('variant_label')->nullable();

            $table->string('topic')->nullable();
            $table->text('teacher_notes')->nullable();

            $table->string('title');
            $table->text('instructions');
            // The text the Learner actually reads aloud (API's display_text).
            $table->text('passage_text');
            // Whitespace-normalized version for future AI-scoring alignment
            // (Sprint 4's read-aloud scoring, not used yet).
            $table->text('reference_text')->nullable();
            $table->unsignedInteger('word_count')->nullable();

            $table->json('target_skills')->nullable();
            $table->json('reading_features')->nullable();
            $table->json('follow_up_questions')->nullable();

            $table->string('status')->default('Draft');
            $table->boolean('shared_to_repository')->default(false);

            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
