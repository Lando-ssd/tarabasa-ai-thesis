{{--
  Reading a real activity (from the teacher or the repository) aloud. The screen
  itself is the shared reading scene; this view only says what to put in it.

  Lexend is used ONLY for the passage text a child actually reads aloud (studied
  for reading fluency); the scene keeps everything else in the app's game face.

  A reading_comprehension activity that carries real follow_up_questions gets the
  quiz step, asked between "done reading" and the real form submit (Reading-api
  needs comprehension_score in the same /analyze call as the audio). Only the
  choices reach the browser; the answers stay on the server for scoring.
--}}
@php
    $comprehensionQuestions = ($activity->competency === 'reading_comprehension') ? ($activity->follow_up_questions ?: []) : [];
@endphp
@include('learner._reading-scene', [
    'pageTitle' => $activity->title.' | TaraBasa AI',
    'eyebrow' => $activity->title,
    'prompt' => 'Read the words below out loud, then tap the mic!',
    'passageText' => $activity->passage_text,
    'recordAction' => route('learner.activity.record', $activity),
    'quizQuestions' => $comprehensionQuestions,
    'backHref' => route('learner.dashboard'),
    'backLabel' => 'Back to My Dashboard',
    'learner' => $learner,
])
