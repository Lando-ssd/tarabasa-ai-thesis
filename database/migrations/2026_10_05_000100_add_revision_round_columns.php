<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The October 2026 revision round (the screens reviewed with the adviser):
     *
     * - teachers.grades_handled: the grades a Teacher says they handle at sign up (one grade, or
     *   several for a multigrade class). Null for Teachers who registered earlier, who keep
     *   access to every grade until they set it in Profile.
     * - learners.home_language, supports, interests: the Parent's answers that replace the old
     *   "learning style" question (a learning style is not used to pick a level).
     * - learners.reading_rung: where on the first-login ladder (0 letters ... 6 long passage) the
     *   child is. Null for children checked earlier; the step shown is then worked out from
     *   mastery_level. See App\Support\ReadingLevel.
     * - activity_assignments.reading_band: an assignment to a whole class can be limited to the
     *   learners at one reading level in it (a "reading group"), worked out live each time.
     */
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->json('grades_handled')->nullable();
        });

        Schema::table('learners', function (Blueprint $table) {
            $table->string('home_language', 40)->nullable();
            $table->json('supports')->nullable();
            $table->json('interests')->nullable();
            $table->unsignedTinyInteger('reading_rung')->nullable();
        });

        Schema::table('activity_assignments', function (Blueprint $table) {
            $table->string('reading_band', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('activity_assignments', function (Blueprint $table) {
            $table->dropColumn('reading_band');
        });

        Schema::table('learners', function (Blueprint $table) {
            $table->dropColumn(['home_language', 'supports', 'interests', 'reading_rung']);
        });

        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn('grades_handled');
        });
    }
};
