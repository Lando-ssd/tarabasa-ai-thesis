<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Learner;
use App\Models\ScoringJob;
use App\Services\BackgroundScoring;
use App\Services\LearnerReadingService;
use App\Services\ReadingAiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Sprint 4 Slice 3 — real ReadingSession persistence, mastery-level
 * adjustment, points/streak, PersonalWordBank, and the results screen.
 * Calls the real, deployed Reading-api — no mock.
 *
 * A thin Blade-rendering wrapper — the actual scoring/mastery/adaptive
 * logic lives in LearnerReadingService, shared verbatim with the mobile
 * API's Api\LearnerReadingApiController. See CLAUDE.md's "Mobile API
 * layer" entry for why this was extracted.
 */
class LearnerReadingController extends Controller
{
    /**
     * A scored reading. The child's page sends the recording in the background (it asks for JSON): the recording is then
     * saved and scored by a background worker, and the page opens renderRecording() through ScoringController once it is
     * done, so no web worker waits on the speech service. A plain form post (a browser that cannot send in the
     * background) is still scored right here, as before.
     */
    public function submitRecording(Request $request, Activity $activity, ReadingAiClient $readingAi, LearnerReadingService $service, BackgroundScoring $scoring): View|JsonResponse
    {
        $learner = $request->user('learner');

        abort_unless($activity->isAccessibleByLearner($learner), 403);

        // Reading-api streams and hard-rejects over 15MB itself (413), but
        // rejecting an oversized file here first is a cheap, fast pre-check
        // that saves the Learner's bandwidth/time on an upload we already
        // know will fail. 15360 KB = 15MB, matching Reading-api's own
        // MAX_UPLOAD_MB exactly (confirmed by reading its real source).
        $validated = $request->validate([
            'audio' => ['required', 'file', 'max:15360'],
            'answers' => ['nullable', 'array'],
            'client_key' => ['nullable', 'string', 'max:64'],
        ]);

        if ($request->expectsJson()) {
            return $scoring->submit($request, ScoringJob::READING, $learner, $activity, $validated['audio'], ['answers' => $validated['answers'] ?? null]);
        }

        $outcome = $service->recordAttempt($learner, $activity, $validated['audio'], $validated['answers'] ?? null, $readingAi);

        return $this->renderRecording($outcome, $activity);
    }

    /** The results (or "didn't quite catch that") screen for a scored reading. Drawn the same whether it came straight from the post or from the background. */
    public function renderRecording(array $outcome, Activity $activity): View
    {
        if ($outcome['status'] === 'unclear') {
            return view('learner.reading-unclear', ['activity' => $activity, 'final' => $outcome['final']]);
        }

        return view('learner.reading-results', [
            'activity' => $outcome['activity'] ?? $activity,
            'learner' => $outcome['learner'] ?? request()->user('learner'),
            'accuracy' => $outcome['accuracy'],
            'wcpm' => $outcome['wcpm'],
            'levelBefore' => $outcome['levelBefore'],
            'levelAfter' => $outcome['levelAfter'],
            'levelChanged' => $outcome['levelChanged'],
            'levelWentUp' => $outcome['levelWentUp'],
            'pointsEarned' => $outcome['pointsEarned'],
            'wordBreakdown' => $outcome['wordBreakdown'],
            'extraWordsSaid' => $outcome['extraWordsSaid'],
            'wordsToPractice' => $outcome['wordsToPractice'],
            'wordCounts' => $outcome['wordCounts'] ?? null,
            'comprehension' => $outcome['comprehension'],
            'newBadges' => $outcome['newBadges'],
            'progress' => $outcome['progress'] ?? null,
        ]);
    }

    /**
     * A practice try before the real reading (stage 2 of the practice stage). Free and unscored:
     * the child sees the same word by word look, but nothing is saved and no points, streak or level
     * move. Two tries at most; after that the child reads for real. Scored in the background like a
     * real reading (see submitRecording).
     */
    public function submitPractice(Request $request, Activity $activity, ReadingAiClient $readingAi, LearnerReadingService $service, BackgroundScoring $scoring): View|RedirectResponse|JsonResponse
    {
        $learner = $request->user('learner');

        abort_unless($activity->isAccessibleByLearner($learner), 403);

        if ($service->practiceTriesLeft($learner, $activity) === 0) {
            $realUrl = route('learner.activity.show', [$activity, 'stage' => 'real']);

            return $request->expectsJson() ? response()->json(['status' => 'redirect', 'url' => $realUrl]) : redirect($realUrl);
        }

        $validated = $request->validate([
            'audio' => ['required', 'file', 'max:15360'],
            'client_key' => ['nullable', 'string', 'max:64'],
        ]);

        if ($request->expectsJson()) {
            return $scoring->submit($request, ScoringJob::PRACTICE, $learner, $activity, $validated['audio']);
        }

        $outcome = $service->recordPractice($learner, $activity, $validated['audio'], $readingAi);

        return $this->renderPractice($outcome, $activity, $learner);
    }

    public function renderPractice(array $outcome, Activity $activity, Learner $learner): View
    {
        if ($outcome['status'] === 'unclear') {
            return view('learner.activity-practice-unclear', ['activity' => $activity]);
        }

        return view('learner.activity-practice-result', [
            'activity' => $activity,
            'learner' => $learner,
            'wordBreakdown' => $outcome['wordBreakdown'],
            'extraWordsSaid' => $outcome['extraWordsSaid'],
            'wordCounts' => $outcome['wordCounts'],
            'accuracy' => $outcome['accuracy'],
            'triesLeft' => $outcome['triesLeft'],
        ]);
    }

    /**
     * Called quietly by a screen where a child is about to read or speak, so the scoring service is
     * awake by the time the recording is sent. Always answers at once with nothing to show.
     */
    public function warm(ReadingAiClient $readingAi): \Illuminate\Http\Response
    {
        $readingAi->wake();

        // The adaptive recommender sleeps the same way and is asked right after a reading is scored.
        if (config('services.adaptive_recommender.url') && ! \App\Support\ServiceWake::isKnownAwake('recommender')
            && \Illuminate\Support\Facades\Cache::add('recommender-wake-requested', true, now()->addMinutes(2))) {
            \App\Jobs\WakeServiceJob::start('recommender');
        }

        return response()->noContent();
    }

    /**
     * Asked by the recording page, a few seconds apart, before it sends a recording: is the scoring service awake?
     * Always answers within a few seconds (see ReadingAiClient::isReady), so no web worker is held while it wakes.
     */
    public function ready(ReadingAiClient $readingAi): \Illuminate\Http\JsonResponse
    {
        return response()->json(['ready' => $readingAi->isReady()]);
    }
}
