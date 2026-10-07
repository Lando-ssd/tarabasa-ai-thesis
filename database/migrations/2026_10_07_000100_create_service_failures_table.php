<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A short diary of what the teammate services (activity generator, reading checker, adaptive
     * recommender) answered when something went wrong.
     *
     * Why: the hosting log is the only other place this is written, it is hard for the team to open, and it is
     * cut off after a day or so. One row per failed call (after any retries), so a person with the Admin
     * account can see "the reading checker answered 429 with a web page" without opening anything else. Only the
     * service's own answer is kept; no child name, recording or password is ever written here.
     */
    public function up(): void
    {
        Schema::create('service_failures', function (Blueprint $table) {
            $table->id();
            $table->string('service', 20); // generator | reader | recommender
            $table->unsignedSmallInteger('status')->nullable(); // null = no answer at all (could not connect)
            $table->string('trail', 60)->nullable(); // every status seen while retrying, e.g. "429,429,429"
            $table->string('content_type', 80)->nullable();
            $table->string('what', 160)->nullable(); // the sentence the person saw on screen
            $table->text('body')->nullable(); // what the service sent back, cut to 1500 characters
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_failures');
    }
};
