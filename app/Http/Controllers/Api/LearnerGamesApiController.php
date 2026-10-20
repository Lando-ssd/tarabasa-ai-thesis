<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GamePlay;
use App\Services\PracticeGameService;
use App\Services\ReadingAiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile Learner API: Practice Games. Thin JSON wrappers over PracticeGameService, the same rules the website's
 * GameController uses, so the website and the phone can never disagree about a level, what a game offers, whether something
 * read aloud was heard, or how a finished game is reported. Free play: no points, streak or level. Every game is a read-aloud
 * game (Balloon Pop, Word Train, Sentence Stars).
 */
class LearnerGamesApiController extends Controller
{
    /**
     * What the Games screen needs: which game leads (and why), the level a game starts at, and whether Syllable Steps is offered.
     *
     * A phone build that has Syllable Steps asks for it with `?syllables=1`. An older build never does, so it is never told to lead with
     * a game it does not have (and the extra field is ignored by it).
     */
    public function index(Request $request, PracticeGameService $games): JsonResponse
    {
        $learner = $request->user();
        $pick = $learner->recommendedGameFocus($request->boolean('syllables'));

        return response()->json([
            // 'balloon-pop', 'word-train', 'sentence-stars' (or 'syllable-steps' for a build that asked for it) when the child's own reading says one would help most. Never a gate.
            'recommended' => $pick['game'] ?? null,
            'reason' => $pick['reason'] ?? null,
            'startLevel' => $games->startLevel($learner),
            // False for a child still on the Letters step: they have no word level yet, so the card is not shown.
            'syllableSteps' => $games->syllableStartLevel($learner) !== null,
        ]);
    }

    /**
     * Syllable Steps: the method's words at each level, with their parts and the sentence read at the end (see
     * PracticeGameService::syllableLevels). Not for a child still on the Letters step, who has no word level (404, never shown a card).
     */
    public function syllables(Request $request, PracticeGameService $games): JsonResponse
    {
        $learner = $request->user();
        $startLevel = $games->syllableStartLevel($learner);
        abort_if($startLevel === null, 404);

        return response()->json([
            'startLevel' => $startLevel,
            'repeat' => (int) config('syllable_method.repeat', 4),
            'levels' => $games->syllableLevels($learner),
        ]);
    }

    /** The words Balloon Pop can use at each of the three levels, the child's own missed words first. */
    public function words(Request $request, PracticeGameService $games): JsonResponse
    {
        $learner = $request->user();

        return response()->json([
            'startLevel' => $games->startLevel($learner),
            'levels' => $games->wordLevels($learner),
        ]);
    }

    /** Word Train's rows: letters for a child still on the letters step, words for everyone else. */
    public function train(Request $request, PracticeGameService $games): JsonResponse
    {
        $learner = $request->user();

        return response()->json($games->trainLevels($learner) + ['startLevel' => $games->startLevel($learner)]);
    }

    /** Sentence Stars' sentences at each of the three levels, the ones that suit this child first. */
    public function sentences(Request $request, PracticeGameService $games): JsonResponse
    {
        $learner = $request->user();

        return response()->json([
            'startLevel' => $games->startLevel($learner),
            'levels' => $games->sentenceLevels($learner),
        ]);
    }

    /**
     * Something read aloud: a word, a row of words or letters, or a sentence. Only ever "heard", "again" or "unavailable", plus which
     * of the words asked were heard: never "wrong", and nothing is kept.
     */
    public function checkText(Request $request, ReadingAiClient $readingAi, PracticeGameService $games): JsonResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'regex:/^[a-z\' ]{1,160}$/'],
            'kind' => ['required', 'in:word,row,letters,sentence'],
            'audio' => ['required', 'file', 'max:4096'],
        ]);

        $words = PracticeGameService::wordsOf($data['text']);
        abort_if($words === [] || count($words) > 14, 422);

        return response()->json($games->checkText($request->user(), $words, $data['kind'], $data['audio'], $readingAi));
    }

    /** One spoken word (Balloon Pop). Only ever "heard", "again" or "unavailable": never "wrong", and nothing is kept. */
    public function checkWord(Request $request, ReadingAiClient $readingAi, PracticeGameService $games): JsonResponse
    {
        $data = $request->validate([
            'word' => ['required', 'string', 'regex:/^[a-z]{2,12}$/'],
            'audio' => ['required', 'file', 'max:2048'],
        ]);

        return response()->json(['status' => $games->checkWord($request->user(), $data['word'], $data['audio'], $readingAi)]);
    }

    /** A game session ended: one row so the Games badges can be earned, and any badges it just earned come back. */
    public function finish(Request $request, string $game, PracticeGameService $games): JsonResponse
    {
        abort_unless(in_array($game, GamePlay::GAMES, true), 404);

        $data = $request->validate([
            'level_reached' => ['required', 'integer', 'between:1,3'],
            'top_level_cleared' => ['required', 'boolean'],
            'had_perfect_round' => ['required', 'boolean'],
            'rounds' => ['required', 'integer', 'between:1,3'],
        ]);

        return response()->json(['newBadges' => $games->finish($request->user(), $game, $data)]);
    }
}
