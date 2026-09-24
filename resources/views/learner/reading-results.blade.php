{{--
  The result of a real, scored reading. Never a sad face or a discouraging word,
  even on a low score: the star is always the happy one, and the word by word look
  underneath shows what to practise without grading the child. The design lives in
  _feedback-scene (the numbers and badges) and _reading-review (the words).

  A level that moved says so plainly. A level that stayed the same says nothing.
--}}
@php
    $levelLine = null;
    if ($levelChanged) {
        $levelLine = $levelWentUp ? 'Level up! You are now '.$levelAfter : 'Your level is now '.$levelAfter;
    }

    $stats = [
        ['value' => round($accuracy).'%', 'label' => 'Accuracy', 'icon' => 'target', 'tone' => '#1f9e83'],
        ['value' => $wcpm !== null ? round($wcpm) : 'N/A', 'label' => 'Words a minute', 'icon' => 'timer', 'tone' => '#1c7ed6'],
        ['value' => $wordsToPractice ?? 'N/A', 'label' => 'Words to practice', 'icon' => 'book-open', 'tone' => '#dd7014'],
        ['value' => '+'.$pointsEarned, 'label' => 'Points', 'icon' => 'star', 'tone' => '#c9820b'],
        ['value' => $learner->streak, 'label' => 'Streak', 'icon' => 'fire', 'tone' => '#dd7014'],
    ];
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
