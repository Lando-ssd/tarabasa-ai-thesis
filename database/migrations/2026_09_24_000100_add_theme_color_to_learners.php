<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The learner's favorite color ('blue' or 'pink') tints their profile
     * chip in the Learner app. Chosen by the Parent when creating the child;
     * nullable so every existing learner keeps working (they get the blue
     * default until a Parent picks one).
     */
    public function up(): void
    {
        Schema::table('learners', function (Blueprint $table) {
            $table->string('theme_color', 10)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('learners', function (Blueprint $table) {
            $table->dropColumn('theme_color');
        });
    }
};
