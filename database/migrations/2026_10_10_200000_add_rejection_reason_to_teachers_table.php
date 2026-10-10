<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Why a teacher was rejected, written by the Admin and shown to the teacher the next time they try to sign in.
 * Nullable: teachers rejected before this existed simply have no reason on record.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('teachers', 'rejection_reason')) {
            Schema::table('teachers', function (Blueprint $table) {
                $table->string('rejection_reason', 200)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('teachers', 'rejection_reason')) {
            Schema::table('teachers', function (Blueprint $table) {
                $table->dropColumn('rejection_reason');
            });
        }
    }
};
