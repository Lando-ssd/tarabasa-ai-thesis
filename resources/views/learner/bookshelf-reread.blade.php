{{--
  Reading a finished book again, just for fun. Free and unscored: no points, no
  streak, no level change (see LearnerReadingService::recordFreeReattempt()). The
  screen itself is the shared reading scene, with a note saying so.
--}}
@include('learner._reading-scene', [
    'pageTitle' => $activity->title.' | TaraBasa AI',
    'eyebrow' => $activity->title,
    'pill' => 'Free practice. No points, just for fun.',
    'prompt' => 'Read it again out loud, then tap the mic!',
    'passageText' => $activity->passage_text,
    'recordAction' => route('learner.bookshelf.reread.submit', $activity),
    'backHref' => route('learner.dashboard'),
    'backLabel' => 'Back to My Dashboard',
    'learner' => $learner,
])
