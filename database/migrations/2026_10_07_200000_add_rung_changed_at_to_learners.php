<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the child last moved on the reading ladder (the first check, or a step up or down after that). The readings
     * that count toward the NEXT move are only those after it, so a child cannot be moved again by the evidence that
     * already moved them. Null means "never moved yet": every reading counts.
     */
    public function up(): void
    {
        Schema::table('learners', function (Blueprint $table) {
            $table->timestamp('rung_changed_at')->nullable()->after('reading_rung');
        });
    }

    public function down(): void
    {
        Schema::table('learners', function (Blueprint $table) {
            $table->dropColumn('rung_changed_at');
        });
    }
};
