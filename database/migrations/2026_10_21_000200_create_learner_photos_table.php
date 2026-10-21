<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * A child's photo now lives in the database, not on the server's disk.
 *
 * On the free hosting plan the disk is wiped every time the website sleeps or is redeployed, so every uploaded photo vanished and
 * the app fell back to the child's initial. The database is the one thing that survives, so the photo is kept there, in its own
 * table (so the small `learners` row that every page loads does not carry the picture around). The browser already crops each
 * photo to 480 by 480 pixels before it is sent, so a photo is tens of kilobytes. It is stored as base64 text, which every database
 * handles the same way.
 *
 * `learners.photo_version` is a cheap "this child has a photo" marker (null = none) that also changes whenever the photo does, so
 * the address of the picture changes and a browser never shows an old one. It saves a query per child on every page that lists children.
 *
 * Old photo files that are still on a disk when this runs are moved into the table and deleted; a path whose file is already gone
 * is just cleared. `learners.avatar_photo_path` is no longer written (the column stays: a shipped migration is never edited).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->unique()->constrained('learners')->cascadeOnDelete();
            $table->string('mime', 20);
            $table->longText('data');
            $table->timestamp('updated_at')->nullable();
        });

        Schema::table('learners', function (Blueprint $table) {
            $table->unsignedBigInteger('photo_version')->nullable()->after('avatar_photo_path');
        });

        $this->moveOldPhotoFiles();
    }

    public function down(): void
    {
        Schema::table('learners', function (Blueprint $table) {
            $table->dropColumn('photo_version');
        });

        Schema::dropIfExists('learner_photos');
    }

    private function moveOldPhotoFiles(): void
    {
        $disk = Storage::disk('public');
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];

        foreach (DB::table('learners')->whereNotNull('avatar_photo_path')->get(['id', 'avatar_photo_path']) as $row) {
            $path = $row->avatar_photo_path;
            $finished = false;

            try {
                if (! $disk->exists($path)) {
                    $finished = true; // the file is already gone: nothing to keep, and the pointer is useless
                } elseif ($disk->size($path) <= 1024 * 1024 && in_array($mime = $disk->mimeType($path), $allowed, true)) {
                    DB::table('learner_photos')->insert([
                        'learner_id' => $row->id,
                        'mime' => $mime,
                        'data' => base64_encode($disk->get($path)),
                        'updated_at' => now(),
                    ]);
                    DB::table('learners')->where('id', $row->id)->update(['photo_version' => time()]);
                    $disk->delete($path);
                    $finished = true;
                }
            } catch (\Throwable) {
                // An unreadable file is left where it is, with its pointer, rather than lost.
            }

            if ($finished) {
                DB::table('learners')->where('id', $row->id)->update(['avatar_photo_path' => null]);
            }
        }
    }
};
