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
 */
class GameController extends Controller
{
    /**
     * Small, hardcoded per-grade CVC-ish word lists — the fallback source
     * when a Learner has no real PersonalWordBank "Struggling" words yet
     * (a brand-new Learner, or one who has never scored below 80% on a
     * real reading). Real words, grade-appropriate, not fabricated data —
     * same category as the Learner avatar preset list: static reference
     * content, not something read/scored from a live source.
     */
    private const WORD_LISTS = [
        1 => ['cat', 'dog', 'sun', 'hat', 'pig', 'bed', 'cup', 'run', 'big', 'red', 'hen', 'bus', 'box', 'fox', 'mud', 'six', 'wet', 'fan', 'jam', 'log'],
        2 => ['frog', 'star', 'milk', 'nest', 'lamp', 'swim', 'gift', 'sock', 'drum', 'fish', 'wind', 'hand', 'jump', 'camp', 'desk', 'flag', 'spin', 'tent', 'stop', 'pond'],
        3 => ['apple', 'table', 'happy', 'forest', 'purple', 'animal', 'garden', 'yellow', 'wonder', 'basket', 'monkey', 'sudden', 'castle', 'jungle', 'pencil', 'rocket', 'magnet', 'dragon', 'planet', 'silver'],
    ];

    /**
     * Grade 1 gets a smaller subset (the first 10 letters) — Grade 2/3 get
     * the full alphabet, but split into rounds of ~8-9 pairs each rather
     * than one 52-card grid, which would be unplayably small/tedious on a
     * real phone screen. This round-chunking is an implementation detail
     * the original scope didn't spell out; disclosed here rather than
     * silently decided.
     */
    private const LETTER_ROUNDS = [
        1 => [['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J']],
        2 => [
            ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'],
            ['J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R'],
            ['S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'],
        ],
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
     * Real PersonalWordBank "Struggling" words first, topped up with the
     * static per-grade list when there are fewer than 5 (rather than an
     * all-or-nothing swap) — uses every real personalized word available
     * before falling back, instead of discarding 1-4 real struggling
     * words just because there weren't a full 5.
     */
    public function wordBuilder(Request $request): View
    {
        $learner = $request->user('learner');
        $grade = (int) substr($learner->grade_level, 6);
        $fallback = self::WORD_LISTS[$grade] ?? self::WORD_LISTS[1];

        $strugglingWords = PersonalWordBank::where('learner_id', $learner->id)
            ->where('mastery_status', 'Struggling')
            ->pluck('word')
            ->map(fn ($word) => strtolower(trim($word)))
            ->unique()
            ->values();

        $picked = $strugglingWords->shuffle()->take(5)->all();

        if (count($picked) < 5) {
            $filler = collect($fallback)
                ->reject(fn ($word) => in_array($word, $picked, true))
                ->shuffle()
                ->take(5 - count($picked))
                ->all();

            $picked = array_merge($picked, $filler);
        }

        shuffle($picked);

        return view('learner.games.word-builder', ['words' => $picked]);
    }

    public function letterMatch(Request $request): View
    {
        $learner = $request->user('learner');
        $grade = (int) substr($learner->grade_level, 6);
        $rounds = self::LETTER_ROUNDS[$grade] ?? self::LETTER_ROUNDS[1];

        return view('learner.games.letter-match', ['rounds' => $rounds]);
    }
}
