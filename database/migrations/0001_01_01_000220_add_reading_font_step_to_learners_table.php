<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Learner's own chosen passage-text size, one of 5 fixed steps
     * (1=Small ... 5=Extra Large). Nullable, not defaulted here — the
     * EFFECTIVE default depends on grade_level (see
     * Learner::effectiveReadingFontStep()), which the schema layer
     * shouldn't hardcode. Null means "never explicitly chosen," not
     * "step 0" — the app computes a sensible grade-based default for
     * that case instead of trusting a fixed column default.
     */
    public function up(): void
    {
        Schema::table('learners', function (Blueprint $table) {
            $table->unsignedTinyInteger('reading_font_step')->nullable()->after('learning_style');
        });
    }

    public function down(): void
    {
        Schema::table('learners', function (Blueprint $table) {
            $table->dropColumn('reading_font_step');
        });
    }
};
