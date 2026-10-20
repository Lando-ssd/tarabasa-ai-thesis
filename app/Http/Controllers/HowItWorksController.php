<?php

namespace App\Http\Controllers;

use App\Services\ClassActivityOutcomes;
use App\Services\TeacherAlerts;
use App\Support\ClassLevel;
use App\Support\ErrorPatterns;
use App\Support\GenerationBasis;
use App\Support\ReadingLevel;
use Illuminate\View\View;

/**
 * "How TaraBasa decides": the public page that lists every rule the app uses to decide something about a child, a
 * class or an activity, with its numbers and where they come from. For the people who have to judge the app (the
 * adviser and the panel), for teachers who want to know why an alert appeared, and for parents.
 *
 * The numbers are read from the same constants and config files the rules run on (config/progression.php,
 * config/activity_fit.php, config/reading.php, TeacherAlerts, ErrorPatterns, ClassLevel, GenerationBasis,
 * ClassActivityOutcomes), so this page cannot say one thing while the app does another. A test pins that.
 *
 * It says plainly that none of this is a trained model: the project has no dataset of children's readings to train
 * one on, so the "expert" is a set of written rules that a teacher can read, argue with and change in one place.
 */
class HowItWorksController extends Controller
{
    public function show(): View
    {
        $progression = config('progression');
        $fit = config('activity_fit');
        $reading = config('reading');

        // The words a child at each step can read in one activity (config/activity_fit.php), with the step's plain name.
        $limits = collect($fit['limits'])->map(fn (array $l, int $rung) => ['step' => ReadingLevel::RUNG_LABELS[$rung] ?? "Step {$rung}", 'comfortable' => $l[0], 'stretch' => $l[1]])->values();

        // The words a reading must have before it counts as evidence about the step the child is on.
        $minWords = collect($progression['min_words'])->map(fn (int $words, int $rung) => ['step' => ReadingLevel::RUNG_LABELS[$rung] ?? "Step {$rung}", 'words' => $words])->values();

        $patterns = collect(ErrorPatterns::CATEGORIES)->map(fn (array $c) => ['label' => $c['label'], 'teacher' => $c['teacher'], 'code' => $c['code']])->values();

        return view('how-it-works', [
            'progression' => $progression,
            'minWords' => $minWords,
            'limits' => $limits,
            'blockShare' => (int) round($fit['block_share'] * 100),
            'connectedWeight' => $fit['connected_text_weight'],
            'unsureConfidence' => $reading['unsure_confidence'],
            'unsureShare' => (int) round($reading['unsure_max_share'] * 100),
            'patterns' => $patterns,
            'patternMinReadings' => ErrorPatterns::MIN_READINGS,
            'patternSettled' => ErrorPatterns::SETTLED_READINGS,
            'patternMinMisses' => ErrorPatterns::MIN_MISSES,
            'alerts' => [
                'below' => TeacherAlerts::SUPPORT_BELOW,
                'up' => TeacherAlerts::UP_AT,
                'inARow' => TeacherAlerts::IN_A_ROW,
                'fresh' => TeacherAlerts::FRESH_DAYS,
                'quiet' => TeacherAlerts::QUIET_DAYS,
            ],
            'classLevelMin' => ClassLevel::MIN_LEARNERS,
            'classLevelDefaults' => ClassLevel::GRADE_DEFAULT,
            'basisShared' => GenerationBasis::MIN_SHARED,
            'outcomePaired' => ClassActivityOutcomes::MIN_PAIRED,
            'outcomeSame' => ClassActivityOutcomes::SAME_WITHIN,
            'tiers' => ReadingLevel::TIER_RUNGS,
        ]);
    }
}
