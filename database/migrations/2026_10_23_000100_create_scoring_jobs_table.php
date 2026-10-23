<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per recording that is being scored in the background.
 *
 * Scoring a recording waits on the speech service for 5 to 45 seconds. Done inside the web request, that holds
 * one of the server's few web workers the whole time, and a class of children pressing "done" together used up
 * all of them (the host's health check then went unanswered and every visitor got a 502). Now the web request
 * only saves the recording and one row here, and a background worker scores it while the child's page asks
 * "is it done?" every couple of seconds (see App\Services\BackgroundScoring).
 *
 * The recording itself waits in a temporary file (audio_path) and is deleted as soon as the job ends, whatever
 * the outcome. The result (what the results page needs) is kept in `outcome` so the result page can be opened
 * again with a refresh; old rows are removed after a day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scoring_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('learner_id')->constrained('learners')->cascadeOnDelete();
            // reading (a scored practice reading), practice (a free try), diagnostic (the first check), reread (Bookshelf).
            $table->string('kind', 20);
            $table->unsignedBigInteger('activity_id')->nullable();
            // Made by the child's page for each recording and sent again with "Send Again", so sending the same
            // recording twice (a lost answer, a double tap) can never be scored, and paid in points, twice.
            $table->string('client_key', 64);
            $table->string('status', 12)->default('queued'); // queued, running, done, failed
            $table->string('audio_path')->nullable();
            $table->string('audio_mime', 80)->nullable();
            $table->string('audio_name', 120)->nullable();
            $table->json('params')->nullable();
            $table->longText('outcome')->nullable();
            $table->string('error', 300)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['learner_id', 'client_key']);
            $table->index(['learner_id', 'kind', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scoring_jobs');
    }
};
