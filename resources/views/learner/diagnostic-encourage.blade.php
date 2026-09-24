{{--
  The "Great job" screen between items of the first-login reading check. A warm
  interstitial, never a score or pass/fail signal (Part 4.2): every child sees the
  same happy star whatever the last item was. The dots show how many items are done
  without saying how many more there are, since the real length isn't known in
  advance. The design lives in _feedback-scene.
--}}
@include('learner._feedback-scene', [
    'mood' => 'happy',
    'pageTitle' => 'Great job | TaraBasa AI',
    'heading' => 'Great job!',
    'message' => $message,
    'buttonLabel' => 'Keep Going',
    'buttonHref' => route('learner.diagnostic.passage'),
    'dotsDone' => $passagesDone,
    'dotsTotal' => $maxPassages,
])
