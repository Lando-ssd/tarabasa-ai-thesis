<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\SerializesLearner;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\LearnerBadge;
use App\Services\LearnerAuthService;
use App\Services\LearnerReadingService;
use App\Support\LearnerHome;
use App\Support\ReadingLevel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile Learner API — login/dashboard/activity-picker. Thin JSON
 * wrappers over LearnerAuthService, the exact same logic
 * LearnerAuthController (web) calls — see CLAUDE.md's "Mobile API layer"
 * entry. Learner-only: Teacher/Parent/Admin stay web-only, matching the
 * mobile app's own scope.
 */
class LearnerApiController extends Controller
{
    use SerializesLearner;

    /**
     * Issues a real Sanctum bearer token — no session cookie involved, so
     * the mobile app just stores this (in expo-secure-store) and sends it
     * as an Authorization header on every subsequent call. Same
     * credential-check/throttle rules as the web login, byte-for-byte
     * (LearnerAuthService::authenticate()) — a 4-digit PIN gets the same
     * 5-attempts/15-minute lockout here as it does on the website.
     */
    public function login(Request $request, LearnerAuthService $service): JsonResponse
    {
        $credentials = $request->validate([
            'learner_code' => ['required', 'string', 'max:20'],
            'pin' => ['required', 'digits:4'],
        ]);

        $learner = $service->authenticate($credentials['learner_code'], $credentials['pin'], $request->ip());

        // One token per login call — a Learner switching devices or
        // re-logging in just accumulates additional tokens (each device
        // keeps working); this app doesn't yet have a "log out other
        // devices" concept, matching the web app's own single-session-
        // per-guard simplicity for now.
        $token = $learner->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'needsDiagnostic' => ! $service->hasCompletedDiagnostic($learner),
            'learner' => $this->learnerPayload($learner),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['ok' => true]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $learner = $request->user();
        $home = LearnerHome::for($learner);
        $currentStep = ReadingLevel::step($learner);

        return response()->json([
            'learner' => $this->learnerPayload($learner),
            // The four step path, with the child's own step marked: names a child can read, never a grade.
            'readingPath' => collect(ReadingLevel::STEPS)->map(fn (array $step, int $number) => [
                'step' => $number,
                'name' => $step['name'],
                'skill' => $step['skill'],
                'blurb' => $step['blurb'],
                'isCurrent' => $number === $currentStep,
            ])->values(),
            'competencyProgress' => $learner->subdomainProgressSummary(),
            // Same numbers as the website's Home (App\Support\LearnerHome).
            'streak' => [
                'days' => $home['dayStreak'],
                'week' => $home['streakWeek']->values(),
            ],
            'weeklyGoal' => [
                'done' => $home['weeklyCount'],
                'target' => $home['weeklyTarget'],
                'met' => $home['weeklyMet'],
            ],
            'growth' => [
                'days' => $home['growthDays']->values(),
                'scale' => $home['growthScale'],
            ],
            'badges' => LearnerBadge::summaryFor($learner),
        ]);
    }

    /**
     * Same picker resolution as the web ("What should I read?"), returned
     * as a flat JSON array instead of a Blade view — the mobile app
     * decides for itself whether to skip straight to the one option or
     * show a picker screen, same "exactly one vs. more than one" rule the
     * web app already applies, just moved client-side since there's no
     * server-rendered branch to pick between here.
     */
    public function activities(Request $request, LearnerAuthService $service): JsonResponse
    {
        $options = $service->findActivityOptions($request->user());

        return response()->json([
            'options' => $options->map(fn (array $option) => $this->activitySummaryPayload($option['activity'], $option['source'], $option['practice'] ?? null))->values(),
        ]);
    }

    /**
     * Full detail for one specific option — re-verifies real access
     * server-side (never trusts that the client only shows valid options),
     * same as the web's showActivity().
     */
    public function showActivity(Request $request, Activity $activity): JsonResponse
    {
        $learner = $request->user();

        abort_unless($activity->isAccessibleByLearner($learner), 403);

        $practice = app(LearnerReadingService::class)->practiceStage($learner, $activity, $request->query('stage'));

        return response()->json($this->activityDetailPayload($activity) + ['practice' => $practice]);
    }

    public function updateReadingFontStep(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'step' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        $request->user()->update(['reading_font_step' => $validated['step']]);

        return response()->json(['ok' => true]);
    }

    private function activitySummaryPayload(Activity $activity, string $source, ?string $practice = null): array
    {
        return [
            'id' => $activity->id,
            'title' => $activity->title,
            'competency' => $activity->competency,
            'difficultyTier' => $activity->difficulty_tier,
            'source' => $source,
            'isRecommended' => str_contains($source, 'Picked just for you'),
            // What this story practises for this child, when their own readings show a clear pattern.
            'practice' => $practice,
        ];
    }

    private function activityDetailPayload(Activity $activity): array
    {
        // The comprehension quiz is sent as the question and its choices ONLY. The correct answer (and the
        // explanation that gives it away) stays on the server and is checked there when the recording is
        // submitted, exactly as the website does (activity-found.blade.php never sends 'answer' to the browser).
        $questions = ($activity->competency === 'reading_comprehension') ? ($activity->follow_up_questions ?: []) : [];

        return [
            'id' => $activity->id,
            'title' => $activity->title,
            'instructions' => $activity->instructions,
            'passageText' => $activity->passage_text,
            'wordCount' => $activity->word_count,
            'competency' => $activity->competency,
            'difficultyTier' => $activity->difficulty_tier,
            'targetSkills' => $activity->target_skills,
            'quizQuestions' => collect($questions)->map(fn (array $q) => [
                'question' => $q['question'] ?? '',
                'choices' => array_values($q['choices'] ?? []),
            ])->values(),
        ];
    }
}
