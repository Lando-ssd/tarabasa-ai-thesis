{{--
  The end of the first-login reading check. Part 4.2: no visible score or
  pass/fail framing, ever. The child should feel exactly as celebrated whichever
  level they landed on, so $resultLabel is a warm, non-numeric phrase (never a raw
  accuracy percentage or grade) and the star is always the happy one. Badges shown
  here are only ones genuinely earned by this check (BadgeService). The design lives
  in _feedback-scene.
--}}
@include('learner._feedback-scene', [
    'mood' => 'happy',
    'pageTitle' => 'Great reading | TaraBasa AI',
    'heading' => 'Great reading, '.$learner->first_name.'!',
    'level' => $resultLabel,
    'newBadges' => $newBadges,
    'message' => "You're ready to start your first activity.",
    'buttonLabel' => 'Start Exploring',
    'buttonHref' => route('learner.dashboard'),
])
