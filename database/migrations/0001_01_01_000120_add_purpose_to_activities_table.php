<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tags system-generated diagnostic passages (Sprint 4 Slice 4) so
     * they never surface in a Teacher's My Activities/Assign lists.
     * created_by_teacher_id is already nullable — diagnostic Activities
     * use null there too, matching schema.sql's own comment ("diagnostic
     * passages... are system-generated with no authoring Teacher"),
     * which already excludes them from any
     * where('created_by_teacher_id', $teacher->id) query. This column
     * is for explicit, queryable tagging rather than relying on that
     * inference alone.
     */
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->string('purpose')->nullable()->after('activity_type');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn('purpose');
        });
    }
};
