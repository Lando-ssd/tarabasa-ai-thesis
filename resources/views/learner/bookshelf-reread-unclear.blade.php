{{--
  A free re-reading that could not be heard. Free re-reading has no capped-attempts
  flow (unlike a real scored reading): nothing is at stake, so a Learner can simply
  keep trying as many times as they want. The design lives in _feedback-scene.
--}}
@include('learner._feedback-scene', [
    'mood' => 'sad',
    'pageTitle' => 'Try again | TaraBasa AI',
    'heading' => "Didn't quite catch that!",
    'message' => 'No worries! Tap the mic and give it another go.',
    'buttonLabel' => 'Try Again',
    'buttonHref' => route('learner.bookshelf.reread', $activity),
])
