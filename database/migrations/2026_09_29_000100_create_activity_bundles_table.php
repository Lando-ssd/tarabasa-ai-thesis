<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Teacher's own named folder of Approved activities ("Bundle 1", "Bundle 2"), per the
     * instructor's relayed request: create a bundle, drop activities into it, then assign the
     * whole bundle to a class. Not the same "bundle" gemini_activity_gen means by
     * `activities.bundle_title` (one generation's own batch of levels) — this is a Teacher
     * organizing already-Approved content, unrelated to how it was generated.
     */
    public function up(): void
    {
        Schema::create('activity_bundles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_bundles');
    }
};
