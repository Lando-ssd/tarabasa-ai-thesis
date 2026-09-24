<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adaptive_Recommendator v2 is a breaking change: state is keyed by exact
     * MATATAG SUBDOMAIN instead of the three grouped competencies, and its
     * own README says not to silently copy the old three broad scores into
     * the new shape. So the v1 columns (competency_states,
     * next_recommended_competency) are left exactly as they are (history,
     * never read again) and v2 gets its own columns; existing learners simply
     * start with null and are initialized from their diagnostic on their
     * next scored reading.
     *
     * reading_sessions.adaptive_subdomain marks an attempt the v2 engine
     * accepted and scored, so the recent-history it is sent never mixes in
     * older v1-era scores (a different scale and a different meaning).
     */
    public function up(): void
    {
        Schema::table('learners', function (Blueprint $table) {
            $table->json('subdomain_states')->nullable();
            $table->string('next_recommended_subdomain')->nullable();
        });

        Schema::table('reading_sessions', function (Blueprint $table) {
            $table->string('adaptive_subdomain')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('learners', function (Blueprint $table) {
            $table->dropColumn(['subdomain_states', 'next_recommended_subdomain']);
        });

        Schema::table('reading_sessions', function (Blueprint $table) {
            $table->dropColumn('adaptive_subdomain');
        });
    }
};
