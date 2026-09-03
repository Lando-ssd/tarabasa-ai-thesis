<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Learner's photo is optional and separate from the required
     * avatar_id (preset emoji character) — avatar_id stays NOT NULL and
     * keeps whatever preset was selected as a fallback, this column is
     * only set when a Parent actually uploads and crops a real photo.
     * Kept as its own migration rather than editing the shipped learners
     * table migration, same pattern as every other add-a-column change.
     */
    public function up(): void
    {
        Schema::table('learners', function (Blueprint $table) {
            $table->string('avatar_photo_path')->nullable()->after('avatar_id');
        });
    }

    public function down(): void
    {
        Schema::table('learners', function (Blueprint $table) {
            $table->dropColumn('avatar_photo_path');
        });
    }
};
