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
        <div class="bcard" @unless ($locked) draggable="true" @endunless data-place-url="{{ route('teacher.activities.place', $a) }}" data-status="Draft" data-activity-id="{{ $a->id }}">
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
          <div class="bcard ok" @unless ($locked) draggable="true" @endunless data-place-url="{{ route('teacher.activities.place', $a) }}" data-status="Approved" data-activity-id="{{ $a->id }}">
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

{{--
  Bundles: a Teacher's own named folders of approved activities. Drop an approved card here (or
  use its "Add to bundle" menu), then open a class's Bundles tab to assign one — anything dropped
  in afterward reaches that class right away, no separate step.
--}}
<div class="bundles">
  <header class="col-h"><h3>Bundles</h3><span class="pill">{{ $bundles->count() }}</span></header>
  <p class="hint">Drop an approved activity here, then assign the bundle to a class from that class's own window. New activities dropped in later reach the same class right away.</p>
  <div class="bundles-row">
    @foreach ($bundles as $bundle)
      <button type="button" class="bundle-tray" data-drop="bundle-{{ $bundle->id }}" data-add-url="{{ route('teacher.bundles.activities.add', $bundle) }}" data-window-url="{{ route('teacher.bundles.window', $bundle) }}">
        <span class="bundle-name">@include('learner._badge-icon', ['icon' => 'folders', 'class' => 'ico']) {{ $bundle->name }}</span>
        <span class="bundle-meta">{{ $bundle->activities_count }} {{ $bundle->activities_count === 1 ? 'activity' : 'activities' }}</span>
        @if ($bundle->classes->isNotEmpty())
          <span class="bundle-classes">{{ $bundle->classes->pluck('name')->join(', ') }}</span>
        @else
          <span class="bundle-classes none">Not assigned yet</span>
        @endif
      </button>
    @endforeach
    @unless ($locked)
      <button type="button" class="bundle-tray new" data-open="newBundleDlg">@include('learner._badge-icon', ['icon' => 'plus', 'class' => 'ico']) New bundle</button>
    @endunless
  </div>
</div>
