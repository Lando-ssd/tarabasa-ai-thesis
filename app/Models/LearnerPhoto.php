<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * A child's photo, kept in the database so it survives a restart of the website (see the migration for why).
 *
 * One row per child. The picture is base64 text in `data`. It is shown only through App\Http\Controllers\LearnerPhotoController,
 * which checks who is asking; there is no public address for it.
 */
class LearnerPhoto extends Model
{
    public $timestamps = false;

    /** The only kinds of picture that are kept (the upload rule already allows nothing else). */
    public const TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    protected $fillable = ['learner_id', 'mime', 'data', 'updated_at'];

    protected function casts(): array
    {
        return ['updated_at' => 'datetime'];
    }

    /**
     * Keeps (or replaces) the child's photo and gives it a new version, so the address of the picture changes with it.
     *
     * @throws \InvalidArgumentException when the file is not one of the allowed kinds of picture
     */
    public static function put(Learner $learner, UploadedFile $file): void
    {
        $mime = $file->getMimeType();

        if (! in_array($mime, self::TYPES, true)) {
            throw new \InvalidArgumentException('That is not a photo this app keeps.');
        }

        $data = base64_encode($file->get());

        DB::transaction(function () use ($learner, $mime, $data) {
            self::updateOrCreate(['learner_id' => $learner->id], ['mime' => $mime, 'data' => $data, 'updated_at' => now()]);
            // The version is a whole number that only ever grows, so a replaced photo always has a new address.
            $learner->update(['photo_version' => max(time(), (int) $learner->photo_version + 1)]);
        });
    }
}
