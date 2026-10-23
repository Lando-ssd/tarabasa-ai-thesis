<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Teacher's or a Parent's own profile photo, kept in the database exactly the way a child's photo is (see the learner_photos migration for
 * why: the free hosting disk is wiped on every sleep or redeploy, the database is not).
 *
 * One row per person. `users.photo_version` is the cheap "has a photo" marker (null = none) that also changes whenever the photo does, so the
 * picture's address changes and a browser never shows an old one. The picture is shown only to the person it belongs to
 * (App\Http\Controllers\UserPhotoController); there is no public address for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('mime', 20);
            $table->longText('data');
            $table->timestamp('updated_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('photo_version')->nullable()->after('contact_number');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('photo_version');
        });

        Schema::dropIfExists('user_photos');
    }
};
