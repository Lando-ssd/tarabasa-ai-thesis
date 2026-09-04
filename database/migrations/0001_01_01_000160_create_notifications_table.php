<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Translates schema.sql's notifications table directly. Recipients
     * are real Users (Teacher or Parent), not the Teacher/Parent profile
     * row — a Teacher/Parent can have exactly one User, so this matches
     * how every other notification-adjacent table in this project
     * already resolves identity. `type` is app-validated, not a DB
     * CHECK, matching this project's existing pattern for third-party-
     * ish open vocabularies (see activities.competency).
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipient_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('learner_id')->nullable()->constrained('learners')->cascadeOnDelete();
            $table->string('type');
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->timestamp('timestamp')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
