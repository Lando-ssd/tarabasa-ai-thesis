<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Translates schema.sql's open_repository_listings table (Table 11)
     * directly — no shape conflict, unlike activities. "Is this Activity
     * shared" is also mirrored onto activities.shared_to_repository (a
     * denormalized flag that already existed in that table) for a fast
     * check on the Teacher's My Activities cards without a join.
     */
    public function up(): void
    {
        Schema::create('open_repository_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('activities')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->enum('price_type', ['Free', 'Paid']);
            $table->float('price')->default(0);
            $table->timestamp('listed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('open_repository_listings');
    }
};
