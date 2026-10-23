<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A parent now reads the child's own four steps (Letter Explorer, Word Builder, Sentence Reader, Story Reader), never the system's
 * stored level (Beginning, Developing, Proficient) or the school's Phil-IRI words (Instructional, Independent ...), which are for
 * teachers. New alerts are written that way. This rewords the parents' OLD alerts so they do not still show those words:
 *
 *   "Rosa's starting reading level has been confirmed: Developing."  ->  "Rosa's first reading check is done."
 *   "... moved up to Sentences (Sentence Reader), now at the Instructional level."  ->  "... moved up to Sentences (Sentence Reader)."
 *
 * Only rows addressed to a Parent account are touched, so a teacher keeps the school's words. The old text does not say which step a
 * child started on, so the first rewrite says only that the check is done. Nothing is deleted and no row is added.
 */
return new class extends Migration
{
    private const BANDS = 'Non-reader|Frustration|Instructional|Independent';

    public function up(): void
    {
        $parentUserIds = DB::table('users')->where('user_type', 'Parent')->pluck('id')->all();
        if ($parentUserIds === []) {
            return;
        }

        foreach (array_chunk($parentUserIds, 500) as $ids) {
            DB::table('notifications')
                ->whereIn('recipient_user_id', $ids)
                ->whereIn('type', ['Level Confirmed', 'Session Summary'])
                ->where(function ($q) {
                    $q->where('message', 'like', '%starting reading level has been confirmed%')
                        ->orWhere('message', 'like', '%, now at the %');
                })
                ->chunkById(200, function ($rows) {
                    foreach ($rows as $row) {
                        $text = preg_replace(
                            "/^(.+?)'s starting reading level has been confirmed: (Beginning|Developing|Proficient)\\.$/u",
                            "$1's first reading check is done.",
                            (string) $row->message
                        );
                        $text = preg_replace('/, now at the ('.self::BANDS.') level\\./u', '.', (string) $text);

                        if ($text !== $row->message) {
                            DB::table('notifications')->where('id', $row->id)->update(['message' => $text]);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        // The old wording cannot be restored, and nobody needs it back.
    }
};
