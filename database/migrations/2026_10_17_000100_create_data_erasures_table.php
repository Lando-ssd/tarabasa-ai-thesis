<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A record that something was deleted on request, kept AFTER the person's data is gone, so the team can show the Data Privacy
     * Act request was honoured. It holds no name, email, code or id of the person: only what kind of thing was deleted, whose role it
     * was, who asked (the person or an Admin), how many children went with an account, and when.
     */
    public function up(): void
    {
        if (Schema::hasTable('data_erasures')) {
            return;
        }

        Schema::create('data_erasures', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 16); // child | account | unlinked
            $table->string('role', 16); // Learner | Parent | Teacher
            $table->string('requested_by', 16); // self | admin
            $table->unsignedSmallInteger('children_erased')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_erasures');
    }
};
