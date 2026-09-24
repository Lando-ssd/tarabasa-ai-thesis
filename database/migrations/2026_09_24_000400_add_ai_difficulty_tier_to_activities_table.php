<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The generator can only guess how hard a text is (it goes by how familiar the words are and
     * how long the text is), so the Teacher decides. `difficulty_tier` stays the level the
     * Teacher placed the activity in (it is what the Learner side matches on); this new column
     * keeps the level the AI suggested, so a move is visible and can be told to the AI on the
     * next generation. Existing rows start with the two equal.
     */
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->string('ai_difficulty_tier')->nullable()->after('difficulty_tier');
        });

        DB::table('activities')->whereNull('ai_difficulty_tier')->update(['ai_difficulty_tier' => DB::raw('difficulty_tier')]);
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn('ai_difficulty_tier');
        });
    }
};
