{{--
  The result of a free re-reading of a finished book. The same design as a real
  reading's result, minus everything that would suggest it counted: no points, no
  streak, no level, no badges (see LearnerReadingService::recordFreeReattempt()).
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
    'pageTitle' => 'Nice reading | TaraBasa AI',
    'heading' => 'Nice re-reading practice!',
    'subline' => 'You read "'.$activity->title.'" again.',
    'pill' => 'Free practice. This does not change your level.',
    'stats' => $stats,
    'buttonLabel' => 'Back to My Dashboard',
    'buttonHref' => route('learner.dashboard'),
    'details' => 'learner._reading-review',
    'detailsData' => [
        'wordBreakdown' => $wordBreakdown ?? [],
        'extraWordsSaid' => $extraWordsSaid ?? [],
    ],
])
