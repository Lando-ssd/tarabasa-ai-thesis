<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Translates schema.sql's repository_unlocks table (Table 12)
     * directly. UNIQUE(listing_id, learner_id) is the real enforcement
     * that unlocking is per-item, per-specific-Learner — never a flat
     * platform subscription, and never re-unlockable once done (Parent
     * Actor Prompt Step 6 + Validation & Edge Cases).
     */
    public function up(): void
    {
        Schema::create('repository_unlocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('open_repository_listings')->cascadeOnDelete();
            $table->foreignId('parent_id')->constrained('parents')->cascadeOnDelete();
            $table->foreignId('learner_id')->constrained('learners')->cascadeOnDelete();
            $table->float('amount_paid')->default(0);
            $table->timestamp('unlocked_at')->useCurrent();
            $table->unique(['listing_id', 'learner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repository_unlocks');
    }
};
