<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Badges — deferred at Sprint 4 for lack of defined content, built for
 * real now (see CLAUDE.md's "Real Badges" entry). Badge DEFINITIONS
 * (name/description/emoji/threshold) deliberately do NOT get their own
 * table — they're fixed content nobody edits through a UI, so they live
 * in config/badges.php instead (same pattern as MASTERY_TO_RESULT_LABEL).
 * This table only holds the one genuinely dynamic fact: which badges a
 * specific Learner has actually earned, and when.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained('learners')->cascadeOnDelete();
            $table->string('badge_code');
            $table->timestamp('earned_at')->useCurrent();

            // A Learner can only ever earn a given badge once — awarding
            // logic checks this table first, but the constraint is the
            // real backstop against a race or a future bug double-awarding.
            $table->unique(['learner_id', 'badge_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_badges');
    }
};
