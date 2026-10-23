<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * A Teacher's or Parent's own profile photo, kept in the database (see the migration). One row per person; the picture is base64 text in
 * `data`. It is shown only through App\Http\Controllers\UserPhotoController, which lets nobody but the person it belongs to see it.
 */
class UserPhoto extends Model
{
    public $timestamps = false;

    /** The only kinds of picture that are kept (the upload rule already allows nothing else). */
    public const TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    protected $fillable = ['user_id', 'mime', 'data', 'updated_at'];

    protected function casts(): array
    {
        return ['updated_at' => 'datetime'];
    }

    /**
     * Keeps (or replaces) the person's photo and gives it a new version, so the address of the picture changes with it.
     *
     * @throws \InvalidArgumentException when the file is not one of the allowed kinds of picture
     */
    public static function put(User $user, UploadedFile $file): void
    {
        $mime = $file->getMimeType();

        if (! in_array($mime, self::TYPES, true)) {
            throw new \InvalidArgumentException('That is not a photo this app keeps.');
        }

        $data = base64_encode($file->get());

        DB::transaction(function () use ($user, $mime, $data) {
            self::updateOrCreate(['user_id' => $user->id], ['mime' => $mime, 'data' => $data, 'updated_at' => now()]);
            // A whole number that only ever grows, so a replaced photo always has a new address.
            $user->forceFill(['photo_version' => max(time(), (int) $user->photo_version + 1)])->save();
        });
    }

    /** Takes the photo away. Nothing is left in the database, and the person goes back to their initial. */
    public static function remove(User $user): void
    {
        DB::transaction(function () use ($user) {
            self::where('user_id', $user->id)->delete();
            $user->forceFill(['photo_version' => null])->save();
        });
    }
}
