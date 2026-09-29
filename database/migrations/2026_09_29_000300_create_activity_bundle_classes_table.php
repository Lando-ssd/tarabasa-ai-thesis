<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which classes a bundle has been assigned to. A bundle can be assigned to more than one
     * class, and a class can carry more than one bundle — more flexible than the single
     * `classes.group_tag` string, and left completely separate from it (group tags keep working
     * exactly as before). Once a bundle is assigned here, any Approved activity later added to it
     * reaches these classes immediately — resolved live in LearnerAuthService, never a snapshot.
     */
    public function up(): void
    {
        Schema::create('activity_bundle_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_bundle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->unique(['activity_bundle_id', 'class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_bundle_classes');
    }
};
