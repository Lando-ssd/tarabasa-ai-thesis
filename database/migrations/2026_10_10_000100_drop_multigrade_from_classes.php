<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every class holds ONE grade, with no exception (a Grade 1 class is only for Grade 1 learners, and so on). A teacher
     * who handles several grades opens one class per grade, so the "multigrade class" flag added on 2026-10-07 is gone.
     * Guarded, so it is safe on a database that never had the column.
     */
    public function up(): void
    {
        if (Schema::hasColumn('classes', 'multigrade')) {
            Schema::table('classes', function (Blueprint $table) {
                $table->dropColumn('multigrade');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('classes', 'multigrade')) {
            Schema::table('classes', function (Blueprint $table) {
                $table->boolean('multigrade')->default(false)->after('grade_level');
            });
        }
    }
};
