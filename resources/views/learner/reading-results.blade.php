{{--
  The result of a real, scored reading. Never a sad face or a discouraging word,
  even on a low score: the star is always the happy one, and the word by word look
  underneath shows what to practise without grading the child. The design lives in
  _feedback-scene (the numbers and badges) and _reading-review (the words).

  A level that moved says so plainly. A level that stayed the same says nothing.
--}}
@php
    // A step moves one at a time and only on repeated strong readings (ReadingProgression). A move up is celebrated by its
    // name; a move back is never announced to a child (the activities simply fit better); between moves, a child sees how
    // many strong readings they have toward growing, never a percentage.
    $levelLine = null;
    $p = $progress ?? null;
    if ($p && $p['moved'] === 'up') {
        $levelLine = $p['stepChanged'] ? 'Level up! You are now a '.$p['stepAfter'] : 'You got stronger as a '.$p['stepAfter'].'!';
    } elseif ($p && $p['moved'] === null && $p['have'] > 0 && $p['nextStep'] !== null) {
        $levelLine = 'Strong readings toward growing: '.$p['have'].' of '.$p['need'];
    } elseif (! $p && $levelChanged) {
        $levelLine = $levelWentUp ? 'Level up! You are now '.$levelAfter : 'Your level is now '.$levelAfter;
    }

    // What a child needs to see: how many words went right, how many the app was not sure about (those
    // are never counted against them), and the points. Percentages and speed are for the teacher and
    // the parent, who see them on their own screens.
    $stats = [];
    if (! empty($wordCounts)) {
        $stats[] = ['value' => $wordCounts['right'].' of '.$wordCounts['total'], 'label' => 'Words read right', 'icon' => 'target', 'tone' => '#1f9e83'];
        if ($wordCounts['notSure'] > 0) {
            $stats[] = ['value' => $wordCounts['notSure'], 'label' => 'Not sure', 'icon' => 'info', 'tone' => '#5b6b7a'];
        }
    } else {
        $stats[] = ['value' => round($accuracy).'%', 'label' => 'Accuracy', 'icon' => 'target', 'tone' => '#1f9e83'];
    }
    $stats[] = ['value' => '+'.$pointsEarned, 'label' => 'Points', 'icon' => 'star', 'tone' => '#c9820b'];
@endphp
@include('learner._feedback-scene', [
    'mood' => 'happy',
    'pageTitle' => 'Great reading | TaraBasa AI',
    'heading' => 'Great reading, '.$learner->first_name.'!',
    'subline' => 'You read "'.$activity->title.'".',
    'level' => $levelLine,
    'newBadges' => $newBadges,
    'stats' => $stats,
    'buttonLabel' => 'Done',
    'buttonHref' => route('learner.dashboard'),
    'details' => 'learner._reading-review',
    'detailsData' => [
        'wordBreakdown' => $wordBreakdown ?? [],
        'extraWordsSaid' => $extraWordsSaid ?? [],
        'comprehension' => $comprehension ?? null,
    ],
])
