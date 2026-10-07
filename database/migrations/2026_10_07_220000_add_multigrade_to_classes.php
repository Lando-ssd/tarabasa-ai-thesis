<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A class holds ONE grade (its grade_level), so a Grade 2 learner cannot be added to a Grade 1 class. The one
     * exception is a multigrade class (a single teacher teaching two or more grades together, as in the registration
     * question "A multigrade class has two or more grades"): only a teacher who handles several grades can mark a class
     * as multigrade, and it then accepts learners from any grade that teacher handles. False for every existing class.
     */
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->boolean('multigrade')->default(false)->after('grade_level');
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn('multigrade');
        });
    }
};
