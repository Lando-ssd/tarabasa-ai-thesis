<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * learners — Table 4 of the Data Dictionary (schema.sql).
     * A Learner never has a password — pin is the only credential, and
     * it's hashed just like a User's password (see Learner model).
     * classId starts null (not enrolled) — a Teacher joins them to a
     * class later via learner_code, a separate future slice.
     */
    public function up(): void
    {
        Schema::create('learners', function (Blueprint $table) {
            $table->id();
            $table->string('learner_code')->unique();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->enum('grade_level', ['Grade 1', 'Grade 2', 'Grade 3']);
            $table->string('pin');
            $table->string('avatar_id');
            $table->enum('mastery_level', ['Beginning', 'Developing', 'Proficient'])->nullable();
            $table->enum('learning_style', ['Visual', 'Listening', 'Hands-on'])->nullable();
            $table->unsignedInteger('points')->default(0);
            $table->unsignedInteger('streak')->default(0);
            $table->string('status')->default('Active');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learners');
    }
};
