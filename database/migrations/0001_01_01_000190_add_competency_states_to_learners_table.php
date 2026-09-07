<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adaptive_Recommendator (github.com/BldZeuz/Adaptive_Recommendator)
     * is completely stateless — it computes a decision on each call but
     * stores nothing itself (confirmed by reading its real source: no DB,
     * no persistence layer, its own README says so explicitly). This
     * repo is the "main backend" it expects to hold state, so a Learner's
     * per-competency state (proficiency/difficulty/confidence/
     * attempt_count for each of foundational_reading/reading_fluency/
     * reading_comprehension) has to be stored here, verbatim, and passed
     * back on every future call. Stored as one JSON blob matching their
     * CurrentState shape exactly, rather than 12 separate columns, since
     * it's always read/written as one whole object, never queried by an
     * individual sub-field.
     *
     * next_recommended_competency/next_recommended_difficulty are the
     * most recent next_recommendation the service returned — kept as
     * plain columns (not folded into the JSON above) since
     * LearnerAuthController::findActivity() needs to match against them
     * directly in a query-friendly way. difficulty is nullable because
     * the service itself can return a null difficulty for a competency
     * that's never been assessed yet.
     */
    public function up(): void
    {
        Schema::table('learners', function (Blueprint $table) {
            $table->json('competency_states')->nullable()->after('mastery_level');
            $table->string('next_recommended_competency')->nullable()->after('competency_states');
            $table->string('next_recommended_difficulty')->nullable()->after('next_recommended_competency');
        });
    }

    public function down(): void
    {
        Schema::table('learners', function (Blueprint $table) {
            $table->dropColumn(['competency_states', 'next_recommended_competency', 'next_recommended_difficulty']);
        });
    }
};
