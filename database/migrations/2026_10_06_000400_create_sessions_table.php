<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The table Laravel's "database" session driver writes to.
 *
 * Railway kept sessions in files, so the original users migration never made this table. Render's free
 * disk is wiped whenever the service sleeps or redeploys, so on Render (SESSION_DRIVER=database) every
 * page failed with "Table 'sessions' doesn't exist". Found 2026-10-06 when the app was first run against
 * TiDB. Safe on Railway: it only adds a table that stays unused while SESSION_DRIVER=file. The guard
 * makes it harmless to run on a database that already has one.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sessions')) {
            return;
        }

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
