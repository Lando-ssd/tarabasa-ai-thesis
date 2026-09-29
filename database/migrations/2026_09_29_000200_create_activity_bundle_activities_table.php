<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which Approved activities are "in" a bundle — dragged there, or added from the card's
     * "Add to bundle" menu. Only Approved activities can be added (BundleController enforces
     * this); a rejected or draft activity is never linked here.
     */
    public function up(): void
    {
        Schema::create('activity_bundle_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_bundle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained('activities')->cascadeOnDelete();
            $table->timestamp('added_at')->useCurrent();
            $table->unique(['activity_bundle_id', 'activity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_bundle_activities');
    }
};
