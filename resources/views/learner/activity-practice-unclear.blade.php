{{--
  A practice try that could not be heard. Nothing is at stake and it does not use up a try, so the
  child simply goes back and tries again. The design lives in _feedback-scene.
--}}
@include('learner._feedback-scene', [
    'mood' => 'sad',
    'pageTitle' => 'Try again | TaraBasa AI',
    'heading' => "Didn't quite catch that!",
    'message' => 'No worries! That try did not count. Tap the mic and give it another go.',
    'buttonLabel' => 'Try Again',
    'buttonHref' => route('learner.activity.show', [$activity, 'stage' => 'try']),
])
