{{--
  Tab 2: My activities. Suggestions for the teacher's classes first (so nobody scrolls a long
  list to find something to assign), then filters, then a paged list. Rejected activities are one
  chip away (and can be restored from there).
--}}
@php $from = $rowTotal === 0 ? 0 : $page * $perPage + 1; @endphp

@if ($weekly->isNotEmpty())
  <div class="sugg-h" style="margin-top:0">@include('learner._badge-icon', ['icon' => 'lightbulb', 'class' => 'ico']) Suggested for your classes this week</div>
  <div class="sugg-strip">
    @foreach ($weekly as $w)
      @include('teacher._suggestion', ['s' => $w['card'], 'class' => $w['class'], 'band' => $w['band'], 'return' => 'activities', 'view' => 'acts', 'style' => 'card', 'for' => $w['for']])
    @endforeach
  </div>
@endif

<div class="filters">
  <select class="mini wide" aria-label="Skill" data-load-select="skill">
    <option value="all">All skills</option>
    @foreach ($skills as $key => $label)<option value="{{ $key }}" @selected($skill === $key)>{{ $label }}</option>@endforeach
  </select>
  <div class="chips" role="group" aria-label="Level">
    @foreach (['all' => 'All levels'] + array_combine($tiers, $tiers) as $key => $label)
      <button type="button" class="chip plain" data-load='{"tier":"{{ $key }}","page":0}' aria-pressed="{{ $tier === $key ? 'true' : 'false' }}">{{ $label }}</button>
    @endforeach
  </div>
  <button type="button" class="chip plain" data-load-toggle="unassigned" aria-pressed="{{ $unassigned ? 'true' : 'false' }}">Not assigned yet</button>
  <button type="button" class="chip plain" data-load='{"status":"{{ $status === 'Rejected' ? 'Approved' : 'Rejected' }}","page":0}' aria-pressed="{{ $status === 'Rejected' ? 'true' : 'false' }}">Rejected<span class="n">{{ $counts['Rejected'] }}</span></button>
</div>

@if ($rows->isEmpty())
  <div class="card empty-hero">
    <div class="empty-ico">@include('learner._badge-icon', ['icon' => 'books', 'class' => 'ico'])</div>
    <h2>{{ $filtering ? 'No activities match' : ($status === 'Rejected' ? 'Nothing rejected' : 'Nothing approved yet') }}</h2>
    <p>{{ $filtering ? 'Try a different word, or clear the filters.' : ($status === 'Rejected' ? 'Rejected drafts would appear here, and can be restored.' : 'Place a draft in a level on the To review tab, and it appears here.') }}</p>
  </div>
@else
  <div class="alist">
    @foreach ($rows as $a)
      @php
          $state = $a->status === 'Rejected' ? 'Rejected'
              : ($a->assignments->isNotEmpty() ? 'Assigned '.$a->assignments->count().($a->assignments->count() === 1 ? ' time' : ' times') : 'Not assigned yet').($a->shared_to_repository ? ' · Shared to Repository' : '');
      @endphp
      <div class="arow" data-window-url="{{ route('teacher.activities.window', $a) }}">
        <div class="atitle"><b>{{ $a->title }}</b><span>{{ $a->grade_level }} · {{ $a->typeLabel() }} · {{ $a->word_count }} words</span></div>
        <span class="pill t-{{ strtolower($a->difficulty_tier) }}">{{ $a->difficulty_tier }}</span>
        <span class="astate">{{ $state }}</span>
        <span class="acts">
          @if ($a->status === 'Rejected' && ! $locked)
            <button type="button" class="btn small ghost" data-post="{{ route('teacher.activities.restore', $a) }}">@include('learner._badge-icon', ['icon' => 'arrow-counter-clockwise', 'class' => 'ico']) Restore</button>
          @endif
          <button type="button" class="btn small ghost" data-window-url="{{ route('teacher.activities.window', $a) }}">{{ $a->status === 'Approved' && ! $locked ? 'Assign' : 'Open' }}</button>
        </span>
      </div>
    @endforeach
  </div>
  <div class="pager">
    <span>Showing {{ $from }} to {{ $from + $rows->count() - 1 }} of {{ $rowTotal }} · {{ $perPage }} per page</span>
    @if ($pages > 1)
      <span class="pb">
        <button type="button" class="btn small ghost" data-load='{"page":{{ $page - 1 }}}' @disabled($page === 0) aria-label="Previous page">@include('learner._badge-icon', ['icon' => 'caret-left', 'class' => 'ico'])</button>
        <button type="button" class="btn small ghost" data-load='{"page":{{ $page + 1 }}}' @disabled($page >= $pages - 1) aria-label="Next page">@include('learner._badge-icon', ['icon' => 'caret-right', 'class' => 'ico'])</button>
      </span>
    @endif
  </div>
@endif
