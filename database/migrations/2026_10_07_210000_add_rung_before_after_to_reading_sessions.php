<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The step of the reading ladder (0 to 6) a child was on before and after a reading. The stored level
     * (Beginning, Developing, Proficient) cannot tell a child who moved from Frustration to Instructional apart from one
     * who moved inside Frustration, or one who moved from Non-reader to Frustration, so a teacher could not be told
     * "Mary moved up a reading group". Null on readings made before this existed and on first-check readings.
     */
    public function up(): void
    {
        Schema::table('reading_sessions', function (Blueprint $table) {
            $table->unsignedTinyInteger('rung_before')->nullable()->after('level_after');
            $table->unsignedTinyInteger('rung_after')->nullable()->after('rung_before');
        });
    }

    public function down(): void
    {
        Schema::table('reading_sessions', function (Blueprint $table) {
            $table->dropColumn(['rung_before', 'rung_after']);
        });
    }
};
