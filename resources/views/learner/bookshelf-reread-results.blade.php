{{--
  The result of a free re-reading of a finished book. The same design as a real
  reading's result, minus everything that would suggest it counted: no points, no
  streak, no level, no badges (see LearnerReadingService::recordFreeReattempt()).
--}}
@php
    $stats = [
        ['value' => round($accuracy).'%', 'label' => 'Accuracy', 'icon' => 'target', 'tone' => '#1f9e83'],
        ['value' => $wcpm !== null ? round($wcpm) : 'N/A', 'label' => 'Words a minute', 'icon' => 'timer', 'tone' => '#1c7ed6'],
        ['value' => $wordsToPractice ?? 'N/A', 'label' => 'Words to practice', 'icon' => 'book-open', 'tone' => '#dd7014'],
    ];
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
