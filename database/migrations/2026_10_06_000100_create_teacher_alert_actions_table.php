<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a Teacher has done about a computed alert (App\Services\TeacherAlerts).
     *
     * The alerts themselves are worked out from the readings each time the page opens, so there is
     * nothing to store for them. This table only remembers "handled": one row per Teacher, learner and
     * kind of alert (support, up, quiet). An alert is hidden while handled_at is later than the
     * reading it is about, and comes back by itself when the learner reads again and the evidence
     * changes.
     */
    public function up(): void
    {
        Schema::create('teacher_alert_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('learner_id')->constrained('learners')->cascadeOnDelete();
            $table->string('kind', 12);
            $table->timestamp('handled_at')->useCurrent();

            $table->unique(['teacher_id', 'learner_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_alert_actions');
    }
};
