<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds an optional middle_initial column to users — kept as its own
     * migration rather than editing the original users table migration,
     * same pattern already used when teachers/parents were added.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('middle_initial', 5)->nullable()->after('last_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('middle_initial');
        });
    }
};
