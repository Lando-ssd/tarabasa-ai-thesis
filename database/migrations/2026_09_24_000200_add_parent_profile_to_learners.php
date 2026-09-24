<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the Parent said about the child's reading when they created the
     * account. The wizard has always asked for both, but only the score
     * computed from the three yes/no answers was kept, so the first-login
     * reading check could not start where the Parent's own description said
     * the child is (a child described as "just starting" got the same
     * starting point as one who "reads independently").
     *
     * Both are nullable: learners created before this migration have no
     * stored answers, and the reading check falls back to their stored
     * mastery_level for them.
     */
    public function up(): void
    {
        Schema::table('learners', function (Blueprint $table) {
            // starting | letters | blending | sentences | independent | unsure
            $table->string('reading_stage', 20)->nullable();
            // {"q1":"yes","q2":"no","q3":"no"} for the grade specific questions
            $table->json('placement_answers')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('learners', function (Blueprint $table) {
            $table->dropColumn(['reading_stage', 'placement_answers']);
        });
    }
};
