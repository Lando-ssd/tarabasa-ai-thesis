<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Translates schema.sql's activity_assignments table (Table 10)
     * directly — no shape conflict here, unlike activities. "Exactly one
     * of learner_id/class_id/group_tag set" is enforced at the app layer
     * (ActivityController::assign()), per schema.sql's own comment, not
     * as a DB CHECK constraint.
     */
    public function up(): void
    {
        Schema::create('activity_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('activities')->cascadeOnDelete();
            $table->foreignId('learner_id')->nullable()->constrained('learners')->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->cascadeOnDelete();
            $table->string('group_tag')->nullable();
            $table->foreignId('assigned_by_teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_assignments');
    }
};
