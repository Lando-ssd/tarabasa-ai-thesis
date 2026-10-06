{{--
  Tab 1: To review. New drafts wait here, four at a time. The level is the teacher's call: drag a
  card onto a level, or press Easy, Medium or Hard on the card (placing a draft in a level also
  approves it there). Nothing is approved without the teacher. $locked: a teacher who is still
  Pending can look but not place.
--}}
@php $filtered = $q !== '' || $grade !== 'All'; @endphp
<div class="slots" aria-label="Levels">
  @foreach ($tiers as $tier)
    <div class="slot" data-drop="{{ $tier }}">
      <b>{{ $tier }} <span class="pill t-{{ strtolower($tier) }}">drop here</span></b>
      <small>{{ $levelInfo[$tier]['short'] }}</small>
    </div>
  @endforeach
</div>

@if ($drafts->isEmpty())
  <div class="card empty-hero">
    <div class="empty-ico">@include('learner._badge-icon', ['icon' => 'check-circle', 'class' => 'ico'])</div>
    <h2>{{ $filtered ? 'No drafts match' : 'Nothing waiting for review' }}</h2>
    <p>{{ $filtered ? 'Try a different word, or clear the filters.' : 'New drafts from Generate activities wait here until you place them in a level.' }}</p>
  </div>
@else
  <div class="rv">
    @foreach ($drafts as $a)
      @php $ai = $a->ai_difficulty_tier ?? $a->difficulty_tier; @endphp
      <div class="rvcard" @unless ($locked) draggable="true" @endunless data-place-url="{{ route('teacher.activities.place', $a) }}" data-status="Draft">
        <div class="b-top">
          <span class="grip">@include('learner._badge-icon', ['icon' => 'dots-six-vertical', 'class' => 'ico'])</span>
          <button type="button" class="b-title" data-window-url="{{ route('teacher.activities.window', $a) }}">{{ $a->title }}</button>
        </div>
        <div class="b-meta">{{ $a->grade_level }} · {{ $a->word_count }} words · {{ $a->typeLabel() }}</div>
        <div class="why1">@include('learner._badge-icon', ['icon' => 'lightbulb', 'class' => 'ico'])<span>{{ $a->whyLevel() }}</span></div>
        <div class="b-row" style="margin-bottom:8px"><span class="pill t-{{ strtolower($ai) }}">AI suggests: {{ $ai }}</span></div>
        <div class="lvbtns">
          <span class="note" style="margin-right:2px">Place in</span>
          @foreach ($tiers as $tier)
            @if ($locked)
              <span class="lb {{ strtolower($tier) }}" aria-disabled="true">{{ $tier }}</span>
            @else
              <button type="button" class="lb {{ strtolower($tier) }}" data-place-url="{{ route('teacher.activities.place', $a) }}" data-level="{{ $tier }}">{{ $tier }}</button>
            @endif
          @endforeach
          @if ($locked)
            <span class="lock" style="margin-left:auto">@include('learner._badge-icon', ['icon' => 'lock-simple', 'class' => 'ico']) Locked</span>
          @else
            <button type="button" class="lb rej" data-place-url="{{ route('teacher.activities.place', $a) }}" data-level="reject">Reject</button>
          @endif
        </div>
      </div>
    @endforeach
  </div>
  <div class="pager">
    <span>Showing {{ $trayFrom }} to {{ $trayFrom + $drafts->count() - 1 }} of {{ $draftTotal }} {{ $draftTotal === 1 ? 'draft' : 'drafts' }}</span>
    @if ($trayPages > 1)
      <span class="pb">
        <button type="button" class="btn small ghost" data-load='{"tray":{{ $tray - 1 }}}' @disabled($tray === 0) aria-label="Previous drafts">@include('learner._badge-icon', ['icon' => 'caret-left', 'class' => 'ico'])</button>
        <button type="button" class="btn small ghost" data-load='{"tray":{{ $tray + 1 }}}' @disabled($tray >= $trayPages - 1) aria-label="Next drafts">@include('learner._badge-icon', ['icon' => 'caret-right', 'class' => 'ico'])</button>
      </span>
    @endif
  </div>
@endif
