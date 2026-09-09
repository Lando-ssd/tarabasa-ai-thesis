<?php

namespace App\Http\Controllers;

use App\Models\PersonalWordBank;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Practice Games — standalone free-play practice, deliberately outside the
 * manuscript's "gameType attached to a Teacher-approved Activity" model
 * (see the Activity Generation provisional-conflict note in CLAUDE.md —
 * same root cause: gemini_activity_gen's real API has no game_type/content
 * JSON concept to build that model against). No points, no scoring, no
 * ReadingSession row — a clean separation from the real reading-achievement
 * system, confirmed with the user before building.
 *
 * Both games now have 3 internal difficulty levels, deliberately built from
 * data this app already owns (grade_level, PersonalWordBank) rather than a
 * Curriculum Guide integration — confirmed with the user as the simpler,
 * in-scope approach. `grade_level` only picks the STARTING level; real
 * in-session performance (see each game's own wrong-tap/wrong-pair
 * threshold) can move a Learner up from there within one sitting, but never
 * back down — a Learner who's struggling just holds at their current level
 * instead of being pushed backward.
 */
class GameController extends Controller
{
    /**
     * Word Builder's 3 levels are length-based, and reuse the exact word
     * lists this feature already shipped with (grade 1/2/3's lists were
     * already, coincidentally, uniform 3/4/5-6-letter sets) — no new
     * content needed, just re-framed as levels instead of grades so a
     * Learner isn't locked to their grade's word length if their real
     * performance says otherwise.
     */
    private const WORD_LEVEL_LISTS = [
        1 => ['cat', 'dog', 'sun', 'hat', 'pig', 'bed', 'cup', 'run', 'big', 'red', 'hen', 'bus', 'box', 'fox', 'mud', 'six', 'wet', 'fan', 'jam', 'log'],
        2 => ['frog', 'star', 'milk', 'nest', 'lamp', 'swim', 'gift', 'sock', 'drum', 'fish', 'wind', 'hand', 'jump', 'camp', 'desk', 'flag', 'spin', 'tent', 'stop', 'pond'],
        3 => ['apple', 'table', 'happy', 'forest', 'purple', 'animal', 'garden', 'yellow', 'wonder', 'basket', 'monkey', 'sudden', 'castle', 'jungle', 'pencil', 'rocket', 'magnet', 'dragon', 'planet', 'silver'],
    ];

    /**
     * Letter Match's 3 levels are pair-count/grid-size based. Level 3 is
     * exactly the pre-leveling full-alphabet/3-round behavior, byte-for-byte
     * unchanged (still split into 3 rounds of ~8-9 pairs — one 52-card grid
     * is unplayable on a real phone screen, a decision already made and
     * tested in the original build). Levels 1-2 are new, genuinely easier
     * tiers below what Grade 1 used to get by default — a real answer to
     * "a weak Grade 1 reader still needs this to be adaptive," not just a
     * relabeling of the old grade defaults.
     */
    private const LETTER_LEVELS = [
        1 => [['A', 'B', 'C', 'D', 'E', 'F']],
        2 => [['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J']],
        3 => [
            ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'],
            ['J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R'],
            ['S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'],
        ],
    ];

    public function index(Request $request): View
    {
        $learner = $request->user('learner');

        return view('learner.games.index', [
            'hasCompetencyData' => $learner->competency_states !== null,
            'recommendedGame' => $learner->recommendedGameFocus(),
        ]);
    }

    /**
     * All 3 levels' word data is sent up front (real Struggling words for
     * that level's length, plus that level's full static list) so leveling
     * up or retrying mid-session is an instant client-side pick, never a
     * server round-trip. The client re-runs the same "real struggling
     * words first, top up from the static list" logic fresh for every
     * round it plays (see word-builder.blade.php's buildRoundWords()),
     * not just once — so a Learner who replays the same level again (held
     * there after too many mistakes) gets a freshly-shuffled 5, not an
     * identical repeat, and real struggling words get a real chance to
     * reappear across attempts instead of being used up after one try.
     */
    public function wordBuilder(Request $request): View
    {
        $learner = $request->user('learner');
        $grade = (int) substr($learner->grade_level, 6);
        $startLevel = max(1, min(3, $grade));

        $strugglingWords = PersonalWordBank::where('learner_id', $learner->id)
            ->where('mastery_status', 'Struggling')
            ->pluck('word')
            ->map(fn ($word) => strtolower(trim($word)))
            ->unique()
            ->values();

        $levels = [];
        foreach ([1, 2, 3] as $level) {
            $levels[$level] = [
                'struggling' => $strugglingWords
                    ->filter(fn ($word) => $this->wordLengthMatchesLevel(strlen($word), $level))
                    ->values()
                    ->all(),
                'fallback' => self::WORD_LEVEL_LISTS[$level],
            ];
        }

        return view('learner.games.word-builder', ['levels' => $levels, 'startLevel' => $startLevel]);
    }

    private function wordLengthMatchesLevel(int $length, int $level): bool
    {
        return match ($level) {
            1 => $length === 3,
            2 => $length === 4,
            default => $length >= 5,
        };
    }

    /**
     * Each level is an array of one-or-more rounds — levels 1 and 2 are a
     * single round each, level 3 is the existing 3-round full-alphabet
     * sequence played straight through as one "attempt" at that level.
     * No PersonalWordBank equivalent exists for individual letters (that
     * table stores whole words, never single characters), so unlike Word
     * Builder, Letter Match's adaptivity is honestly grade-plus-in-session-
     * performance only — not pretending to know which specific letters a
     * Learner struggles with.
     */
    public function letterMatch(Request $request): View
    {
        $learner = $request->user('learner');
        $grade = (int) substr($learner->grade_level, 6);
        $startLevel = max(1, min(3, $grade));

        return view('learner.games.letter-match', [
            'levelDefs' => self::LETTER_LEVELS,
            'startLevel' => $startLevel,
        ]);
    }
}
