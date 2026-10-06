{{--
  What a practice try (stage 2 of a real activity) looks like. The same happy star and the same word by
  word look as a real reading, minus everything that would suggest it counted: no points, no streak, no
  level (see LearnerReadingService::recordPractice). The child chooses: try once more (while tries are
  left) or read for real. Never a sad face, whatever the result.
--}}
@php
    $stats = [];
    if (! empty($wordCounts)) {
        $stats[] = ['value' => $wordCounts['right'].' of '.$wordCounts['total'], 'label' => 'Words read right', 'icon' => 'target', 'tone' => '#1f9e83'];
        if ($wordCounts['notSure'] > 0) {
            $stats[] = ['value' => $wordCounts['notSure'], 'label' => 'Not sure', 'icon' => 'info', 'tone' => '#5b6b7a'];
        }
    } else {
        $stats[] = ['value' => round($accuracy).'%', 'label' => 'Accuracy', 'icon' => 'target', 'tone' => '#1f9e83'];
    }
@endphp
@include('learner._feedback-scene', [
    'mood' => 'happy',
    'pageTitle' => 'Nice try | TaraBasa AI',
    'heading' => 'Nice practice, '.$learner->first_name.'!',
    'subline' => 'You practiced "'.$activity->title.'".',
    'pill' => 'Practice. This one did not count.',
    'stats' => $stats,
    'buttonLabel' => 'Read for real',
    'buttonHref' => route('learner.activity.show', [$activity, 'stage' => 'real']),
    'secondLabel' => $triesLeft > 0 ? 'Try again ('.$triesLeft.' left)' : null,
    'secondHref' => route('learner.activity.show', [$activity, 'stage' => 'try']),
    'details' => 'learner._reading-review',
    'detailsData' => [
        'wordBreakdown' => $wordBreakdown ?? [],
        'extraWordsSaid' => $extraWordsSaid ?? [],
    ],
])
