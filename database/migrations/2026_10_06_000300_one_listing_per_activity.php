<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An activity is shared to the Repository once (and earns its two credits once). Nothing in the
     * database enforced that, so two clicks at the same moment could list it twice. This keeps the
     * first listing of each activity (moving over any unlocks and ratings the later ones collected, so
     * no parent loses what they unlocked) and then makes a second listing impossible.
     */
    public function up(): void
    {
        $groups = DB::table('open_repository_listings')->orderBy('id')->get()->groupBy('activity_id');

        foreach ($groups as $listings) {
            $keep = $listings->first();

            foreach ($listings->skip(1) as $duplicate) {
                foreach (DB::table('repository_unlocks')->where('listing_id', $duplicate->id)->get() as $unlock) {
                    $already = DB::table('repository_unlocks')->where('listing_id', $keep->id)->where('learner_id', $unlock->learner_id)->exists();
                    $already
                        ? DB::table('repository_unlocks')->where('id', $unlock->id)->delete()
                        : DB::table('repository_unlocks')->where('id', $unlock->id)->update(['listing_id' => $keep->id]);
                }
                foreach (DB::table('repository_ratings')->where('listing_id', $duplicate->id)->get() as $rating) {
                    $already = DB::table('repository_ratings')->where('listing_id', $keep->id)->where('parent_id', $rating->parent_id)->exists();
                    $already
                        ? DB::table('repository_ratings')->where('id', $rating->id)->delete()
                        : DB::table('repository_ratings')->where('id', $rating->id)->update(['listing_id' => $keep->id]);
                }
                DB::table('open_repository_listings')->where('id', $duplicate->id)->delete();
            }
        }

        Schema::table('open_repository_listings', function (Blueprint $table) {
            $table->unique('activity_id');
        });
    }

    public function down(): void
    {
        Schema::table('open_repository_listings', function (Blueprint $table) {
            $table->dropUnique(['activity_id']);
        });
    }
};
