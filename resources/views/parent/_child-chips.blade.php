{{--
  The row of children to pick from (only shown when there is more than one). Each chip links to the
  same page for that child. Expects: $learners, $selectedLearner, $route (the page's route name).
  Optional: $flagged (a map of learner id to needs attention), $extra (more query values to keep).
--}}
@if ($learners->count() > 1)
  <div class="chips" role="group" aria-label="Choose a child">
    @foreach ($learners as $learner)
      <a class="chip {{ $learner->id === $selectedLearner->id ? 'on' : '' }}" href="{{ route($route, ($extra ?? []) + ['learner_id' => $learner->id]) }}" @if ($learner->id === $selectedLearner->id) aria-current="true" @endif>
        @include('parent._avatar', ['learner' => $learner])
        {{ $learner->first_name }}
        @if (! empty($flagged) && ($flagged[$learner->id] ?? false))<span class="flag" title="Needs attention"></span>@endif
      </a>
    @endforeach
  </div>
@endif
