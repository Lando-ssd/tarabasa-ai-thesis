{{--
  One item of the first-login reading check: either a row of six letters the
  child names aloud (children whose Parent said they are just starting) or a
  short passage to read. The screen itself is the shared reading scene.

  $present comes from LearnerDiagnosticService::presentation(): the kind
  ('letters' or 'passage'), the direction to show, the mic and done wording and,
  for letters, the letters themselves.

  Part 4.5: simple visual progress. The check is adaptive and stops as soon as it knows the child's
  level (it can end after 1, 2 or 3 items), so the row of dots never promises a total: the first item
  shows none, and from the second item on there is one dot per item reached so far (the earlier ones
  done, the present one orange). It used to show three dots from the start, and a child who finished
  after one item felt something had gone wrong.
--}}
@include('learner._reading-scene', [
    'pageTitle' => "Let's Read Together | TaraBasa AI",
    'eyebrow' => $present['kind'] === 'letters' ? 'Letters' : 'Passage '.$passageNumber,
    'dotsTotal' => $passageNumber > 1 ? $passageNumber : null,
    'dotsCurrent' => $passageNumber,
    'prompt' => $present['prompt'],
    'letters' => $present['kind'] === 'letters' ? $present['letters'] : null,
    'passageText' => $present['kind'] === 'letters' ? null : $activity->passage_text,
    'recordAction' => route('learner.diagnostic.record'),
    'micLabel' => $present['micLabel'],
    'doneLabel' => $present['doneLabel'],
    'learner' => $learner,
    // The very first item starts with a one word warm-up (a microphone check; nothing is scored).
    'warmup' => $passageNumber === 1,
    'warmupLetters' => $present['kind'] === 'letters',
])
