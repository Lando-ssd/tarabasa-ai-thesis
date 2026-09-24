<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per "Generate activities" request. The AI service writes one text at a time (13 to
     * 27 seconds each) and always writes all three levels, so a request takes from about a minute
     * to several minutes. That is too long to hold a web request open, so the request
     * is saved here, written in the background, and the Activities page follows its status.
     * Credits are charged only when it finishes (`Done`), never for a failed or timed out one.
     */
    public function up(): void
    {
        Schema::create('activity_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();

            // Queued -> Running -> Done, or Failed.
            $table->string('status')->default('Queued');

            $table->string('grade_level');
            $table->string('competency');
            $table->string('activity_type');
            $table->string('topic')->nullable();
            $table->text('teacher_notes')->nullable();

            // How many of each level the teacher asked for: {"Easy":0,"Medium":3,"Hard":0}.
            $table->json('levels');

            $table->unsignedSmallInteger('created_count')->default(0);
            // What to tell the teacher: what was added, or why nothing was.
            $table->text('message')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            // Set when the teacher has seen how it ended, so the notice on Activities goes away.
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['teacher_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_generations');
    }
};
