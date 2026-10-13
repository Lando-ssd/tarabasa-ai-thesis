<?php

namespace App\Http\Controllers;

use App\Models\GamePlay;
use App\Services\PracticeGameService;
use App\Services\ReadingAiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Practice Games: standalone free-play reading practice, deliberately outside the manuscript's "gameType attached to a
 * Teacher-approved Activity" model (see the Activity Generation provisional-conflict note in CLAUDE.md: same root cause,
 * gemini_activity_gen's real API has no game_type/content JSON concept to build that model against). No points, no scoring,
 * no ReadingSession row: a clean separation from the real reading-achievement system, confirmed with the user before
 * building. The one thing a finished session reports to the server is a `game_plays` row (see finish()), which exists only
 * so the ten Games badges can be earned.
 *
 * Every game is a reading game: the child reads something out loud and the same speech checker that scores readings listens.
 * Balloon Pop is one word, Word Train a row of words (or letters, for a child still on the letters step), Sentence Stars a
 * sentence. They were three different things before (Word Builder spelled with taps, Letter Match matched cards); the team
 * decided Practice Games should only ever be reading.
 *
 * The rules (the three levels of each game, what a game offers, whether something read aloud was heard, how a finished game is
 * reported) live in App\Services\PracticeGameService, shared with the phone app's API
 * (App\Http\Controllers\Api\LearnerGamesApiController), so the two can never disagree. This controller only renders the
 * website's pages from them.
 *
 * Each game has 3 difficulty levels built from data this app already owns (the child's step on the reading path, their own
 * missed words and their main kind of mistake). The reading step only picks the STARTING level; real in-session performance
 * can move a child up from there within one sitting, but never back down: a child who is struggling just holds at their level.
 */
class GameController extends Controller
{
    public function index(Request $request): View
    {
        $learner = $request->user('learner');

        return view('learner.games.index', [
            'recommendedGame' => $learner->recommendedGameFocus(),
        ]);
    }

    /**
     * Balloon Pop: a balloon shows a word and the child says it out loud to pop it. All 3 levels' words are sent up front (the
     * child's real Struggling words for that level's length, the words that practise their kind of mistake, and that level's
     * full list) so levelling up or retrying mid-session is an instant client-side pick, never a server round-trip.
     */
    public function balloonPop(Request $request, PracticeGameService $games): View
    {
        $learner = $request->user('learner');

        return view('learner.games.balloon-pop', [
            'levels' => $games->wordLevels($learner),
            'startLevel' => $games->startLevel($learner),
        ]);
    }

    /** Word Train: a row of cars, each one a word (or a letter), and the child reads the whole row to make the train roll. */
    public function wordTrain(Request $request, PracticeGameService $games): View
    {
        $learner = $request->user('learner');

        return view('learner.games.word-train', $games->trainLevels($learner) + [
            'startLevel' => $games->startLevel($learner),
        ]);
    }

    /** Sentence Stars: the child reads a sentence out loud to catch a star. */
    public function sentenceStars(Request $request, PracticeGameService $games): View
    {
        $learner = $request->user('learner');

        return view('learner.games.sentence-stars', [
            'levels' => $games->sentenceLevels($learner),
            'startLevel' => $games->startLevel($learner),
        ]);
    }

    /**
     * Something the child read aloud into a game: one word (Balloon Pop), a row of words or letters (Word Train) or a sentence
     * (Sentence Stars). The answer is only ever "heard" or "again", never "wrong", plus which of the words asked were heard, and
     * nothing is kept (see PracticeGameService::checkText for why).
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

        return response()->json($games->checkText($request->user('learner'), $words, $data['kind'], $data['audio'], $readingAi));
    }

    /** One word, the same check Balloon Pop has always used (kept so an older page still works). */
    public function checkWord(Request $request, ReadingAiClient $readingAi, PracticeGameService $games): JsonResponse
    {
        $data = $request->validate([
            'word' => ['required', 'string', 'regex:/^[a-z]{2,12}$/'],
            'audio' => ['required', 'file', 'max:2048'],
        ]);

        return response()->json(['status' => $games->checkWord($request->user('learner'), $data['word'], $data['audio'], $readingAi)]);
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
