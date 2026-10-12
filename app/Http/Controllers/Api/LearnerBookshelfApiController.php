<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Services\LearnerReadingService;
use App\Services\ReadingAiClient;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile Learner API: My Shelf. The same three things the website's BookshelfController does: the list of stories a
 * child has really read, the story for a free re-read, and the free re-read itself (the exact same logic, see
 * LearnerReadingService::recordFreeReattempt: scored for feedback only, nothing is saved, no points, streak or level).
 *
 * A story may be re-read only if the child has a finished real reading of it, checked on the server every time
 * (Activity::hasCompletedPracticeReadingFor), never just because the app only lists those stories. A child sees how many
 * times they read a story and when, never a percentage (those stay on the teacher's and the parent's screens).
 */
class LearnerBookshelfApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $books = $request->user()->bookshelfBooks()->map(fn (array $book) => [
            'id' => $book['activity']->id,
            'title' => $book['activity']->title,
            'wordCount' => $book['activity']->word_count,
            'timesRead' => $book['timesRead'],
            'lastRead' => $book['mostRecentDate'] ? Carbon::parse($book['mostRecentDate'])->toIso8601String() : null,
        ])->values();

        return response()->json(['books' => $books]);
    }

    public function show(Request $request, Activity $activity): JsonResponse
    {
        abort_unless($activity->hasCompletedPracticeReadingFor($request->user()), 403);

        return response()->json([
            'id' => $activity->id,
            'title' => $activity->title,
            'instructions' => $activity->instructions,
            'passageText' => $activity->passage_text,
            'wordCount' => $activity->word_count,
        ]);
    }

    public function reread(Request $request, Activity $activity, ReadingAiClient $readingAi, LearnerReadingService $service): JsonResponse
    {
        abort_unless($activity->hasCompletedPracticeReadingFor($request->user()), 403);

        $validated = $request->validate([
            'audio' => ['required', 'file', 'max:15360'],
        ]);

        $outcome = $service->recordFreeReattempt($activity, $validated['audio'], $readingAi);

        // No cap on tries: nothing is at stake, so a recording that could not be heard just says "try again".
        if ($outcome['status'] === 'unclear') {
            return response()->json(['status' => 'unclear']);
        }

        return response()->json([
            'status' => 'scored',
            'activityId' => $activity->id,
            'accuracy' => $outcome['accuracy'],
            'wordBreakdown' => $outcome['wordBreakdown'],
            'extraWordsSaid' => $outcome['extraWordsSaid'],
            'wordsToPractice' => $outcome['wordsToPractice'],
            'wordCounts' => $outcome['wordCounts'] ?? null,
        ]);
    }
}
