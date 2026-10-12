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
 * GameController uses, so the website and the phone can never disagree about a level, the words a game offers, whether a
 * spoken word was heard, or how a finished game is reported. Free play: no points, streak or level.
 */
class LearnerGamesApiController extends Controller
{
    /** What the Games screen and Letter Match need: the recommendation, the starting level and the letter sets. */
    public function index(Request $request, PracticeGameService $games): JsonResponse
    {
        $learner = $request->user();

        return response()->json([
            // 'word-builder' or 'letter-match' when the child's own reading shows one would help, else null. Never a gate.
            'recommended' => $learner->recommendedGameFocus()['game'] ?? null,
            'hasCompetencyData' => $learner->subdomain_states !== null,
            'startLevel' => $games->startLevel($learner),
            'letterLevels' => PracticeGameService::LETTER_LEVELS,
        ]);
    }

    /** The words Word Builder and Balloon Pop can use at each of the three levels, the child's own missed words first. */
    public function words(Request $request, PracticeGameService $games): JsonResponse
    {
        $learner = $request->user();

        return response()->json([
            'startLevel' => $games->startLevel($learner),
            'levels' => $games->wordLevels($learner),
        ]);
    }

    /** One spoken word. Only ever "heard", "again" or "unavailable": never "wrong", and nothing is kept. */
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
