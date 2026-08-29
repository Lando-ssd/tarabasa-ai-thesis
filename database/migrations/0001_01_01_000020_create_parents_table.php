<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * parents — Table 9 of the Data Dictionary.
     * One row per Parent, linked 1:1 to a users row. Deliberately
     * minimal — Parent-owned data (their Learners) lives in
     * parent_learners, built in a later slice.
     */
    public function up(): void
    {
        Schema::create('parents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parents');
    }
};
