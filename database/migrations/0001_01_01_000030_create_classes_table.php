<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * classes — Table 6 of the Data Dictionary (schema.sql), with the
     * school_year patch already merged in (SchoolYear_Addition.txt).
     * A class is never edited into "becoming" next year's class — a new
     * school year always means a new row; old ones stay as permanent,
     * read-only historical records.
     */
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->string('name');
            $table->enum('grade_level', ['Grade 1', 'Grade 2', 'Grade 3']);
            $table->string('section');
            $table->string('group_tag')->nullable();
            $table->string('school_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
