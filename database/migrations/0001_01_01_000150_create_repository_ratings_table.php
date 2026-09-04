<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Translates RepositoryRatings_Addition.txt's new table directly.
     * UNIQUE(listing_id, parent_id) is exactly as specified there — one
     * rating per Parent per listing, re-rating UPDATEs rather than
     * duplicating. Confirmed with the user this stays exactly as given,
     * even though a Parent with two children who both use the same
     * listing can only ever leave one combined rating for it, not one
     * per child — that's the schema's real behavior, not a bug to work
     * around.
     */
    public function up(): void
    {
        Schema::create('repository_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('open_repository_listings')->cascadeOnDelete();
            $table->foreignId('parent_id')->constrained('parents')->cascadeOnDelete();
            $table->foreignId('learner_id')->constrained('learners')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamp('rated_at')->useCurrent();
            $table->unique(['listing_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repository_ratings');
    }
};
