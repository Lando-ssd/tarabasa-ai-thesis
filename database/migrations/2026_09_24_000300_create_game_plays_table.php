<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per finished Practice Games session (Word Builder or Letter Match).
 *
 * Practice Games stay free play: no points, no streak, no level, no ReadingSession
 * (see CLAUDE.md). This table exists only so the ten Games badges can be earned; nothing
 * in the reading achievement system reads it. The game itself runs in the browser, so
 * the numbers here are what the browser reports when a session ends. They can only ever
 * earn a child a badge, never points or a level, so nothing is gained by inflating them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_plays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained('learners')->cascadeOnDelete();
            $table->string('game', 20); // 'word-builder' or 'letter-match'
            $table->unsignedTinyInteger('level_reached'); // 1 to 3, the level the session ended on
            $table->boolean('top_level_cleared')->default(false); // finished a whole round at level 3
            $table->boolean('had_perfect_round')->default(false); // a round with no mistakes
            $table->unsignedTinyInteger('rounds')->default(1);
            $table->timestamp('played_at')->useCurrent();

            $table->index(['learner_id', 'game']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_plays');
    }
};
