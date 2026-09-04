<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * parent_learners — Table 5 of the Data Dictionary (schema.sql).
     * is_creator=true for the Parent who ran the Add-a-Learner wizard;
     * a second guardian linking via learner_code (Parent Actor Prompt,
     * Step 5) gets is_creator=false. The Learner record itself never
     * changes when a second guardian links — only who can see it.
     */
    public function up(): void
    {
        Schema::create('parent_learners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->constrained('parents')->cascadeOnDelete();
            $table->foreignId('learner_id')->constrained('learners')->cascadeOnDelete();
            $table->string('relationship')->nullable();
            $table->boolean('is_creator')->default(false);
            $table->timestamp('linked_at')->useCurrent();

            $table->unique(['parent_id', 'learner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_learners');
    }
};
