<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * teachers — Table 8 of the Data Dictionary.
     * One row per Teacher, linked 1:1 to a users row.
     * status starts 'Pending' until Admin verifies Employee ID
     * (Module 1.1.5/1.1.6 — see Admin Actor Prompt).
     */
    public function up(): void
    {
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('school_name');
            $table->string('employee_id');
            $table->enum('status', ['Pending', 'Active', 'Rejected'])->default('Pending');
            $table->unsignedInteger('free_generation_credits_remaining')->default(2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teachers');
    }
};
