{{--
  One item of the first-login reading check: either a row of six letters the
  child names aloud (children whose Parent said they are just starting) or a
  short passage to read. The screen itself is the shared reading scene.

  $present comes from LearnerDiagnosticService::presentation(): the kind
  ('letters' or 'passage'), the direction to show, the mic and done wording and,
  for letters, the letters themselves.

  Part 4.5: simple visual progress so the child knows there's a clear end in
  sight. Three dots are always shown (the check may stop after 1 or 2) rather
  than a false "of N" count, since the real total isn't known ahead of time.
--}}
@include('learner._reading-scene', [
    'pageTitle' => "Let's Read Together | TaraBasa AI",
    'eyebrow' => $present['kind'] === 'letters' ? 'Letters' : 'Passage '.$passageNumber,
    'dotsTotal' => $maxPassages,
    'dotsCurrent' => $passageNumber,
    'prompt' => $present['prompt'],
    'letters' => $present['kind'] === 'letters' ? $present['letters'] : null,
    'passageText' => $present['kind'] === 'letters' ? null : $activity->passage_text,
    'recordAction' => route('learner.diagnostic.record'),
    'micLabel' => $present['micLabel'],
    'doneLabel' => $present['doneLabel'],
    'learner' => $learner,
])
