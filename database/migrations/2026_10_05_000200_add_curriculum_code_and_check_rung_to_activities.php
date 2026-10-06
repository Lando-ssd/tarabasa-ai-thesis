<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The first-login reading check now uses a curated bank (config/diagnostic_bank.php), so an
     * activity can carry its own curriculum information instead of asking the generator for it:
     *
     * - curriculum_code: the MATATAG competency code the item practises (for example
     *   RL1PWS-I-5). When set, it is what is sent to the scoring service and shown to teachers;
     *   when null (every generated activity) the code is looked up from the generator as before.
     * - check_rung: which rung of the first-login ladder (letters, phonics_easy ... passage_hard)
     *   a bank item belongs to. Null for every other activity.
     */
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->string('curriculum_code', 40)->nullable();
            $table->string('check_rung', 30)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropIndex(['check_rung']);
            $table->dropColumn(['curriculum_code', 'check_rung']);
        });
    }
};
