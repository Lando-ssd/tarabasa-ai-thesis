<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Translates schema.sql's promotion_records table (Table 7) directly
     * — no shape conflict. released_from_class_id/claimed_into_class_id
     * are the schema's own patch specifically to support the rich
     * year-over-year roster history line from SchoolYear_Addition.txt
     * Part 4 ("Grade 1 - CCS (SY 2025-2026, Teacher: X) -> Grade 2 - CCS
     * (SY 2026-2027, Teacher: Y)") — without them, only grade-number and
     * date could be reconstructed, since a Learner's class_id gets
     * overwritten on every promotion.
     */
    public function up(): void
    {
        Schema::create('promotion_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained('learners')->cascadeOnDelete();
            $table->foreignId('released_by_teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('claimed_by_teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->enum('next_grade', ['Grade 2', 'Grade 3']);
            $table->enum('status', ['Pending', 'Claimed'])->default('Pending');
            $table->timestamp('released_at')->useCurrent();
            $table->timestamp('claimed_at')->nullable();
            $table->foreignId('released_from_class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignId('claimed_into_class_id')->nullable()->constrained('classes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_records');
    }
};
