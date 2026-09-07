<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reading-api's real /analyze response already returns a 0-100
     * speed_score (grade-appropriate-WCPM-derived, distinct from the raw
     * wcpm already stored) and a 0-100 prosody_score (a real acoustic
     * heuristic) on every call — confirmed by reading its real main.py
     * directly. Neither has ever been persisted here; both are needed now
     * as real inputs to Adaptive_Recommendator's per-competency scoring
     * (foundational_reading needs accuracy+speed, reading_fluency needs
     * accuracy+speed+prosody). Capturing real data Reading-api already
     * computes, not deriving anything new.
     *
     * adaptive_attempt_score is the ONE score Adaptive_Recommendator
     * itself computed for this attempt (its own weighted blend of the
     * fields above, per whichever competency this activity belongs to)
     * and handed back in its response. Stored so a later /recommend call
     * can rebuild real recent_history from what the service actually
     * returned, rather than re-deriving an approximation of its own
     * formula in PHP.
     */
    public function up(): void
    {
        Schema::table('reading_sessions', function (Blueprint $table) {
            $table->float('speed_score')->nullable()->after('wcpm');
            $table->float('prosody_score')->nullable()->after('speed_score');
            $table->float('adaptive_attempt_score')->nullable()->after('word_feedback');
        });
    }

    public function down(): void
    {
        Schema::table('reading_sessions', function (Blueprint $table) {
            $table->dropColumn(['speed_score', 'prosody_score', 'adaptive_attempt_score']);
        });
    }
};
