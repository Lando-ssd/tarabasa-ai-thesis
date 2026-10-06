{{--
  One suggested activity with a one-click Assign. The suggestion comes from ActivitySuggestions
  (the teacher's own approved activities, picked by reading level and by the MATATAG skill that is
  next); nothing is assigned until the button is pressed.

  Expects: $s (the suggestion), $class (SchoolClass).
  Optional: $band (assign to one reading group only), $view ('groups' or 'acts', the tab to come
  back to), $return ('activities' to come back to the Activities page), $style ('sugg' or 'card'),
  $for (the small caption on a card).
--}}
@php
    $a = $s['activity'];
    $style = $style ?? 'sugg';
    $line = $s['code'] ? $s['code'].' '.$s['skill'] : $s['skill'];
@endphp
@if ($style === 'card')
  <div class="sugg-card">
    <div class="for">@include('learner._badge-icon', ['icon' => 'target', 'class' => 'ico']) {{ $for ?? $class->name }}</div>
    <b>{{ $a->title }}</b>
    <div class="why1">{{ $line }}. {{ $s['why'] }}</div>
    <div style="display:flex;justify-content:space-between;align-items:center">
      <span class="pill t-{{ strtolower($a->difficulty_tier) }}">{{ $a->difficulty_tier }}</span>
      <form method="POST" action="{{ route('teacher.classes.assign-activity', $class) }}" data-busy="Assigning">
        @csrf
        <input type="hidden" name="form" value="class"><input type="hidden" name="target_class_id" value="{{ $class->id }}"><input type="hidden" name="view" value="{{ $view ?? 'acts' }}">
        <input type="hidden" name="activity_id" value="{{ $a->id }}">
        @isset($band)<input type="hidden" name="reading_band" value="{{ $band }}">@endisset
        @isset($return)<input type="hidden" name="return" value="{{ $return }}">@endisset
        <button type="submit" class="btn tiny">Assign</button>
      </form>
    </div>
  </div>
@else
  <div class="sugg">
    <b>{{ $a->title }}</b>
    <span class="code">{{ $line }}</span>
    <div class="srow">
      <span class="pill t-{{ strtolower($a->difficulty_tier) }}">{{ $a->difficulty_tier }}</span>
      <form method="POST" action="{{ route('teacher.classes.assign-activity', $class) }}" data-busy="Assigning">
        @csrf
        <input type="hidden" name="form" value="class"><input type="hidden" name="target_class_id" value="{{ $class->id }}"><input type="hidden" name="view" value="{{ $view ?? 'groups' }}">
        <input type="hidden" name="activity_id" value="{{ $a->id }}">
        @isset($band)<input type="hidden" name="reading_band" value="{{ $band }}">@endisset
        <button type="submit" class="btn tiny">Assign</button>
      </form>
    </div>
  </div>
@endif
