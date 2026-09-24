{{--
  The review board. Drafts wait in "To review" (four at a time). Dragging a card into Easy, Medium
  or Hard approves it in that level; dropping it on Reject rejects it; an approved card can be
  dragged to another level. Every card also has a "Move to" menu (touch and keyboard).
  $locked: a teacher who is still Pending can look but not sort.
--}}
@php $filtered = $q !== '' || $grade !== 'All'; @endphp
<div class="board">
  <section class="col tray" data-drop="tray">
    <header class="col-h"><h3>To review</h3><span class="pill amber">{{ $draftTotal }}</span></header>
    <p class="hint">Drag a card into the level it belongs to, or use the menu on the card.</p>
    <div class="bcards">
      @forelse ($drafts as $a)
        @php $ai = $a->ai_difficulty_tier ?? $a->difficulty_tier; @endphp
        <div class="bcard" @unless ($locked) draggable="true" @endunless data-place-url="{{ route('teacher.activities.place', $a) }}" data-status="Draft">
          <div class="b-top">
            <span class="grip">@include('learner._badge-icon', ['icon' => 'dots-six-vertical', 'class' => 'ico'])</span>
            <button type="button" class="b-title" data-window-url="{{ route('teacher.activities.window', $a) }}">{{ $a->title }}</button>
          </div>
          <div class="b-meta">{{ $a->grade_level }} · {{ $a->word_count }} words</div>
          <div class="b-row">
            <span class="pill t-{{ strtolower($ai) }}">AI: {{ $ai }}</span>
            @if ($locked)
              <span class="lock">@include('learner._badge-icon', ['icon' => 'lock-simple', 'class' => 'ico']) Locked</span>
            @else
              <select class="mini" data-place-url="{{ route('teacher.activities.place', $a) }}" aria-label="Move {{ $a->title }} to a level">
                <option value="">Move to</option>
                @foreach ($tiers as $tier)<option>{{ $tier }}</option>@endforeach
                <option value="reject">Reject</option>
              </select>
            @endif
          </div>
        </div>
      @empty
        <div class="empty-mini">{{ $filtered ? 'No drafts match.' : 'Nothing waiting. Generate activities to add drafts.' }}</div>
      @endforelse
    </div>
    @if ($trayPages > 1)
      <div class="tpager">
        <button type="button" class="btn small ghost" data-load='{"tray":{{ $tray - 1 }}}' @disabled($tray === 0) aria-label="Previous cards">@include('learner._badge-icon', ['icon' => 'caret-left', 'class' => 'ico'])</button>
        <span>{{ $tray + 1 }} of {{ $trayPages }}</span>
        <button type="button" class="btn small ghost" data-load='{"tray":{{ $tray + 1 }}}' @disabled($tray >= $trayPages - 1) aria-label="Next cards">@include('learner._badge-icon', ['icon' => 'caret-right', 'class' => 'ico'])</button>
      </div>
    @endif
    <div class="dropzone" data-drop="reject">@include('learner._badge-icon', ['icon' => 'x-circle', 'class' => 'ico']) Drop here to reject</div>
  </section>

  @foreach ($tiers as $tier)
    @php $col = $columns[$tier]; @endphp
    <section class="col lvl" data-drop="{{ $tier }}">
      <header class="col-h"><h3>{{ $tier }}</h3><span class="pill t-{{ strtolower($tier) }}">{{ $col['total'] }}</span></header>
      <p class="hint">{{ $levelInfo[$tier]['short'] }}</p>
      <div class="bcards">
        @forelse ($col['cards'] as $a)
          <div class="bcard ok" @unless ($locked) draggable="true" @endunless data-place-url="{{ route('teacher.activities.place', $a) }}" data-status="Approved">
            <button type="button" class="b-title" data-window-url="{{ route('teacher.activities.window', $a) }}">{{ $a->title }}</button>
            <div class="b-meta" style="margin-bottom:0">{{ $a->grade_level }} · {{ $a->word_count }} words @if ($a->assignments->isNotEmpty()) · {{ $a->assignments->count() }} assigned @endif</div>
            @if ($a->movedByTeacher())<div class="b-moved">Moved from {{ $a->ai_difficulty_tier }}</div>@endif
          </div>
        @empty
          <div class="empty-mini">{{ $col['total'] ? 'None match the filter.' : "Drop a card here to place it in {$tier}." }}</div>
        @endforelse
      </div>
      @if ($col['matching'] > count($col['cards']))
        <button type="button" class="link" style="margin-top:8px" data-load='{"view":"list","status":"Approved","tier":"{{ $tier }}","page":0}'>See all {{ $col['matching'] }}</button>
      @endif
    </section>
  @endforeach
</div>
