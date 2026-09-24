{{--
  A recording that genuinely could not be scored (silent, too short, no speech).
  The sad star means "we could not hear you", never "you did badly". After three
  in a row ($final) a grown up is asked to help. Used by both the first-login check
  and ordinary practice readings. The design lives in _feedback-scene.
--}}
@if ($final)
  @include('learner._feedback-scene', [
      'mood' => 'sad',
      'pageTitle' => 'Let us try later | TaraBasa AI',
      'heading' => 'Please ask a grown up for help',
      'message' => "Tara couldn't quite hear that a few times in a row. A grown up can help you try again later!",
      // For the diagnostic specifically, this link naturally restarts a fresh attempt
      // (the standing EnsureDiagnosticComplete guard sends an incomplete Learner right
      // back to it) rather than being a true dead end.
      'buttonLabel' => 'Back to My Dashboard',
      'buttonHref' => route('learner.dashboard'),
  ])
@else
  @include('learner._feedback-scene', [
      'mood' => 'sad',
      'pageTitle' => 'Try again | TaraBasa AI',
      'heading' => "Didn't quite catch that!",
      'message' => 'No worries! Tap the mic and give it another go.',
      'buttonLabel' => 'Try Again',
      'buttonHref' => ($isDiagnostic ?? false) ? route('learner.diagnostic.passage') : route('learner.activity.show', $activity),
  ])
@endif
