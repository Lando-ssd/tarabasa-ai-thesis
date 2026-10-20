<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A teacher who is already approved cannot give themselves another grade: they ask, say how the school can confirm it, and the Admin
 * approves or declines. `grades_handled` (what they may open classes for) only changes when the Admin approves, so these three columns
 * hold the open request: the grades asked for, when, and the teacher's note about who at the school can confirm it.
 * All nullable: no request open means all three are empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            if (! Schema::hasColumn('teachers', 'grades_requested')) {
                $table->json('grades_requested')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'grades_requested_at')) {
                $table->timestamp('grades_requested_at')->nullable();
            }
            if (! Schema::hasColumn('teachers', 'grades_request_note')) {
                $table->string('grades_request_note', 300)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            foreach (['grades_requested', 'grades_requested_at', 'grades_request_note'] as $column) {
                if (Schema::hasColumn('teachers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
