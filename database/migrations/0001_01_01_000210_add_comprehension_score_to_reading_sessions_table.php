<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The one real, closable gap found while investigating a PM question:
     * reading_comprehension-competency Activities already carry real
     * Gemini-generated follow_up_questions (2-3 MC questions with a real
     * answer), but nothing in this app ever asked a Learner them or
     * scored the result — so comprehension_score was always null,
     * causing Adaptive_Recommendator's /recommend to 422 on every real
     * reading_comprehension attempt (confirmed against real production
     * data: a real Learner's real session on "Blue Crab at the Beach"
     * already has a null adaptive_attempt_score because of this). This
     * column is the one new piece of schema needed to close it.
     */
    public function up(): void
    {
        Schema::table('reading_sessions', function (Blueprint $table) {
            $table->float('comprehension_score')->nullable()->after('prosody_score');
        });
    }

    public function down(): void
    {
        Schema::table('reading_sessions', function (Blueprint $table) {
            $table->dropColumn('comprehension_score');
        });
    }
};
