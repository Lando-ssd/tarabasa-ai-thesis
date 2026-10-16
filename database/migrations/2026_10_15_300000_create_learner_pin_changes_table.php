<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A record of every time a guardian set a new PIN for a child: who, how (made by the system or chosen by them) and when. It
     * never holds a PIN. It is what limits how often a PIN can be changed, and the trail a parent or the team can read if a child
     * says their PIN stopped working.
     */
    public function up(): void
    {
        if (Schema::hasTable('learner_pin_changes')) {
            return;
        }

        Schema::create('learner_pin_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained('learners')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('method', 16); // generated | chosen
            $table->timestamp('changed_at')->useCurrent();
            $table->index(['learner_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_pin_changes');
    }
};
