<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A service check now also writes what it found as data (which service, asleep or working or broken, how long it
 * took), so the Admin's System health page can show a card per service instead of a block of text. The text in
 * "body" is unchanged; this column is extra and nullable, so older rows simply have no details.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('service_failures', 'details')) {
            Schema::table('service_failures', function (Blueprint $table) {
                $table->text('details')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('service_failures', 'details')) {
            Schema::table('service_failures', function (Blueprint $table) {
                $table->dropColumn('details');
            });
        }
    }
};
