<?php

namespace App\Http\Controllers;

use App\Models\GamePlay;
use App\Models\Learner;
use App\Services\PracticeGameService;
use App\Services\ReadingAiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Practice Games: standalone free-play practice, deliberately outside the manuscript's "gameType attached to a
 * Teacher-approved Activity" model (see the Activity Generation provisional-conflict note in CLAUDE.md: same root cause,
 * gemini_activity_gen's real API has no game_type/content JSON concept to build that model against). No points, no scoring,
 * no ReadingSession row: a clean separation from the real reading-achievement system, confirmed with the user before
 * building. The one thing a finished session reports to the server is a `game_plays` row (see finish()), which exists only
 * so the ten Games badges can be earned.
 *
 * The rules (the three levels of each game, which words are offered, whether a spoken word was heard, how a finished game is
 * reported) live in App\Services\PracticeGameService, shared with the phone app's API
 * (App\Http\Controllers\Api\LearnerGamesApiController), so the two can never disagree. This controller only renders the
 * website's pages from them.
 *
 * Each game has 3 difficulty levels built from data this app already owns (grade_level, the child's own missed words)
 * rather than a Curriculum Guide integration. `grade_level` only picks the STARTING level; real in-session performance can
 * move a child up from there within one sitting, but never back down: a child who is struggling just holds at their level.
 */
class GameController extends Controller
{
    public function index(Request $request): View
    {
        $learner = $request->user('learner');

        return view('learner.games.index', [
            'hasCompetencyData' => $learner->subdomain_states !== null,
            'recommendedGame' => $learner->recommendedGameFocus(),
        ]);
    }

    /**
     * All 3 levels' word data is sent up front (the child's real Struggling words for that level's length, plus that level's
     * full static list) so levelling up or retrying mid-session is an instant client-side pick, never a server round-trip.
     * The client re-runs the same "real struggling words first, top up from the static list" logic fresh for every round it
     * plays, so a child held at a level gets a freshly shuffled 5, not an identical repeat.
     */
    public function wordBuilder(Request $request): View
    {
        $learner = $request->user('learner');

        return view('learner.games.word-builder', [
            'levels' => $this->wordLevels($learner),
            'startLevel' => app(PracticeGameService::class)->startLevel($learner),
            'learnerCode' => $learner->learner_code,
        ]);
    }

    /**
     * Balloon Pop: a balloon shows a word and the child says it out loud to pop it. The words come from the same place as
     * Word Builder's (the words this child missed in real readings, then the word list for the level), so the game
     * practises what that child needs.
     */
    public function balloonPop(Request $request): View
    {
        $learner = $request->user('learner');

        return view('learner.games.balloon-pop', [
            'levels' => $this->wordLevels($learner),
            'startLevel' => app(PracticeGameService::class)->startLevel($learner),
            'checkUrl' => route('learner.games.check-word'),
        ]);
    }

    /** The words a game can use at each of the three levels (see PracticeGameService::wordLevels). */
    private function wordLevels(Learner $learner): array
    {
        return app(PracticeGameService::class)->wordLevels($learner);
    }

    /**
     * One word the child said into a game. The answer is only ever "heard" or "again", never "wrong", and nothing is kept
     * (see PracticeGameService::checkWord for why).
     */
    public function checkWord(Request $request, ReadingAiClient $readingAi, PracticeGameService $games): JsonResponse
    {
        $data = $request->validate([
            'word' => ['required', 'string', 'regex:/^[a-z]{2,12}$/'],
            'audio' => ['required', 'file', 'max:2048'],
        ]);

        return response()->json(['status' => $games->checkWord($request->user('learner'), $data['word'], $data['audio'], $readingAi)]);
    }

    /**
     * Letter Match: each level is a list of one or more sets (levels 1 and 2 are one set, level 3 is the full alphabet in 3
     * sets). No word bank equivalent exists for individual letters, so unlike Word Builder its adaptivity is honestly
     * grade-plus-in-session-performance only, not pretending to know which letters a child struggles with.
     */
    public function letterMatch(Request $request): View
    {
        $learner = $request->user('learner');

        return view('learner.games.letter-match', [
            'levelDefs' => PracticeGameService::LETTER_LEVELS,
            'startLevel' => app(PracticeGameService::class)->startLevel($learner),
            'learnerCode' => $learner->learner_code,
        ]);
    }

    /**
     * A game session ended (the game runs in the browser and calls this once): records a game_plays row and returns any
     * badges it just earned, for the "All done" screen (see PracticeGameService::finish).
     */
    public function finish(Request $request, string $game, PracticeGameService $games): JsonResponse
    {
        abort_unless(in_array($game, GamePlay::GAMES, true), 404);

        $data = $request->validate([
            'level_reached' => ['required', 'integer', 'between:1,3'],
            'top_level_cleared' => ['required', 'boolean'],
            'had_perfect_round' => ['required', 'boolean'],
            'rounds' => ['required', 'integer', 'between:1,3'],
        ]);

        return response()->json(['newBadges' => $games->finish($request->user('learner'), $game, $data)]);
    }
}
