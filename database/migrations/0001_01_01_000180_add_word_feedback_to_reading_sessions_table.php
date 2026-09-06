<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Reading-api's real word_feedback array ({reference, spoken,
        // status} per word — status is correct|substitution|deletion|
        // insertion) was already being computed and returned on every
        // scored reading, but only ever used transiently to pluck up to 2
        // struggling words for PersonalWordBank, then thrown away. Stored
        // here now so the real color-coded word breakdown on the results
        // screen can render from real data instead of nothing.
        Schema::table('reading_sessions', function (Blueprint $table) {
            $table->json('word_feedback')->nullable()->after('insertion_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reading_sessions', function (Blueprint $table) {
            $table->dropColumn('word_feedback');
        });
    }
};
