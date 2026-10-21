<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a person agreed to, and when. One row each time someone ticks a consent box:
 *   terms_privacy  an adult accepted the Terms of Use and read the Privacy Policy (sign up, Teacher or Parent);
 *   child_data     a parent or guardian consented to a child's information being kept and used (adding a child);
 *   child_photo    the same parent also consented to the child's photo (a separate, optional box);
 *   child_link     a second guardian confirmed they are the child's parent or guardian (linking to a child).
 * `policy_version` is config('legal.version') at that moment, so we can say WHICH wording was agreed to. No address
 * or browser details are kept here. Rows are deleted with the person or the child (DataErasureService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('learner_id')->nullable();
            $table->string('kind', 30);
            $table->string('policy_version', 20);
            $table->string('channel', 10)->default('web');
            $table->timestamp('accepted_at');
            $table->timestamp('withdrawn_at')->nullable();

            $table->index(['user_id', 'kind']);
            $table->index('learner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_records');
    }
};
