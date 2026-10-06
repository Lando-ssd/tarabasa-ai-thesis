<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\GamePlay;
use App\Models\Learner;
use App\Models\PersonalWordBank;
use App\Services\BadgeService;
use App\Services\ReadingAiClient;
use App\Support\NotSure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Practice Games — standalone free-play practice, deliberately outside the
 * manuscript's "gameType attached to a Teacher-approved Activity" model
 * (see the Activity Generation provisional-conflict note in CLAUDE.md —
 * same root cause: gemini_activity_gen's real API has no game_type/content
 * JSON concept to build that model against). No points, no scoring, no
 * ReadingSession row — a clean separation from the real reading-achievement
 * system, confirmed with the user before building. The one thing a finished
 * session does report to the server is a `game_plays` row (see finish()), which
 * exists only so the ten Games badges can be earned.
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
            'hasCompetencyData' => $learner->subdomain_states !== null,
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

        return view('learner.games.word-builder', [
            'levels' => $this->wordLevels($learner),
            'startLevel' => $startLevel,
            'learnerCode' => $learner->learner_code,
        ]);
    }

    /**
     * Balloon Pop: a balloon shows a word and the child says it out loud to pop it. The words
     * come from the same place as Word Builder's (the words this child missed in real readings,
     * then the word list for the level), so the game practices what that child needs.
     */
    public function balloonPop(Request $request): View
    {
        $learner = $request->user('learner');
        $grade = (int) substr($learner->grade_level, 6);

        return view('learner.games.balloon-pop', [
            'levels' => $this->wordLevels($learner),
            'startLevel' => max(1, min(3, $grade)),
            'checkUrl' => route('learner.games.check-word'),
        ]);
    }

    /**
     * The words a game can use at each of the three levels: the child's own Struggling words
     * (from their real readings) bucketed by length, and that level's fixed word list.
     *
     * @return array<int, array{struggling: list<string>, fallback: list<string>, favoured: list<string>}>
     */
    private function wordLevels(Learner $learner): array
    {
        // Words the child missed first, then ones they are improving on that are due another look.
        $strugglingWords = app(\App\Services\WordBank::class)->practiceWords($learner);

        // When the child's own readings show a clear kind of mistake (see ErrorPatterns), the words
        // from the level's list that practise it are offered first when a round needs filling up.
        $pattern = \App\Support\ErrorPatterns::mainPattern(\App\Support\ErrorPatterns::forLearner($learner));

        $levels = [];
        foreach ([1, 2, 3] as $level) {
            $levels[$level] = [
                'struggling' => $strugglingWords
                    ->filter(fn ($word) => $this->wordLengthMatchesLevel(strlen($word), $level))
                    ->values()
                    ->all(),
                'fallback' => self::WORD_LEVEL_LISTS[$level],
                'favoured' => array_values(array_filter(
                    self::WORD_LEVEL_LISTS[$level],
                    fn ($word) => \App\Support\ErrorPatterns::practisesPattern($word, $pattern)
                )),
            ];
        }

        return $levels;
    }

    /**
     * One word the child said into a game, checked by the same service that scores readings. This
     * is practice: nothing is saved (no reading session, no word bank, no points), and what the
     * child said is not kept. The answer is only ever "heard" or "again", never "wrong": speech
     * recognition is weaker for young children and for single words, so a game must not tell a child
     * they are wrong when the machine may be. If the service cannot be reached the answer is
     * "unavailable" and the game simply carries on without the speaking part.
     */
    public function checkWord(Request $request, ReadingAiClient $readingAi): JsonResponse
    {
        $data = $request->validate([
            'word' => ['required', 'string', 'regex:/^[a-z]{2,12}$/'],
            'audio' => ['required', 'file', 'max:2048'],
        ]);

        $learner = $request->user('learner');
        $grade = max(1, min(3, (int) substr($learner->grade_level, 6)));

        // A throwaway, unsaved activity that only tells the service what it is listening to: one
        // word, read aloud, at the child's own grade.
        $activity = new Activity([
            'grade_level' => 'Grade '.$grade,
            'competency' => 'foundational_reading',
            'activity_type' => 'word_reading',
            'difficulty_tier' => 'Easy',
            'curriculum_code' => [1 => 'RL1PWS-I-5', 2 => 'EN2PWS-I-3', 3 => 'EN3PWS-I-2'][$grade],
            'reference_text' => $data['word'],
            'passage_text' => $data['word'],
        ]);
        $activity->id = 0;

        try {
            $outcome = $readingAi->analyze($data['audio'], $activity);
        } catch (ValidationException) {
            return response()->json(['status' => 'unavailable']);
        }

        if ($outcome['unclear']) {
            return response()->json(['status' => 'again']);
        }

        $feedback = NotSure::apply($outcome['result'])['accuracy']['word_feedback'] ?? [];
        $heard = collect($feedback)->contains(
            fn (array $w) => ($w['reference'] ?? null) === $data['word'] && ($w['status'] ?? null) === 'correct'
        );

        return response()->json(['status' => $heard ? 'heard' : 'again']);
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
            'learnerCode' => $learner->learner_code,
        ]);
    }

    /**
     * A game session ended (the game runs in the browser and calls this once). Records a
     * game_plays row and returns any badges it just earned, for the "All done" screen.
     * The numbers come from the browser, but all they can ever earn is a badge: no
     * points, streak or level reads this table, so there is nothing to gain by faking them.
     */
    public function finish(Request $request, string $game): JsonResponse
    {
        abort_unless(in_array($game, GamePlay::GAMES, true), 404);

        $data = $request->validate([
            'level_reached' => ['required', 'integer', 'between:1,3'],
            'top_level_cleared' => ['required', 'boolean'],
            'had_perfect_round' => ['required', 'boolean'],
            'rounds' => ['required', 'integer', 'between:1,3'],
        ]);

        $learner = $request->user('learner');

        GamePlay::create($data + ['learner_id' => $learner->id, 'game' => $game, 'played_at' => now()]);

        $newBadges = collect(app(BadgeService::class)->sync($learner->fresh()))
            ->map(fn (array $badge) => [
                'code' => $badge['code'],
                'name' => $badge['name'],
                'icon' => config('badge_icons.icons.'.$badge['code'], config('badge_icons.fallback')),
            ])
            ->values();

        return response()->json(['newBadges' => $newBadges]);
    }
}
