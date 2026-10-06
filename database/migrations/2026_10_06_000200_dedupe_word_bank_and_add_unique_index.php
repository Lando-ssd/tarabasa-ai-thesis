<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A child's word bank is one row per word. Until now every low-scoring reading added the same
     * missed word again, so one word could sit in the bank dozens of times (one real test child had
     * 24 rows for 4 words). That inflated every count built on it and meant a word could never be
     * "mastered". This merges the repeats into one row per learner and word, keeping the oldest
     * row and the strongest progress, then makes it impossible to repeat again.
     */
    public function up(): void
    {
        $order = ['Struggling' => 0, 'Improving' => 1, 'Mastered' => 2];

        $groups = DB::table('personal_word_bank')
            ->orderBy('id')
            ->get(['id', 'learner_id', 'word', 'mastery_status', 'times_drilled', 'last_reviewed'])
            ->groupBy(fn ($row) => $row->learner_id.'|'.mb_strtolower(trim($row->word)));

        foreach ($groups as $rows) {
            $keep = $rows->first();

            if ($rows->count() > 1) {
                $best = $rows->sortByDesc(fn ($r) => $order[$r->mastery_status] ?? 0)->first();

                DB::table('personal_word_bank')->where('id', $keep->id)->update([
                    'mastery_status' => $best->mastery_status,
                    'times_drilled' => $rows->sum('times_drilled'),
                    'last_reviewed' => $rows->pluck('last_reviewed')->filter()->max(),
                ]);

                DB::table('personal_word_bank')->whereIn('id', $rows->skip(1)->pluck('id'))->delete();
            }

            // One spelling for each word, so "Hen" and "hen" are the same row from now on.
            DB::table('personal_word_bank')->where('id', $keep->id)->update(['word' => mb_strtolower(trim($keep->word))]);
        }

        Schema::table('personal_word_bank', function (Blueprint $table) {
            $table->unique(['learner_id', 'word']);
        });
    }

    public function down(): void
    {
        Schema::table('personal_word_bank', function (Blueprint $table) {
            $table->dropUnique(['learner_id', 'word']);
        });
    }
};
