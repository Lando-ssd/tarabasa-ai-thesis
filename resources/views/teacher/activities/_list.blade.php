{{-- Every activity as compact rows: status tabs, level chips, seven to a page. --}}
@php
    $statusLabels = ['Draft' => 'To review', 'Approved' => 'Approved', 'Rejected' => 'Rejected'];
    $from = $rowTotal === 0 ? 0 : $page * $perPage + 1;
@endphp
<div class="toolbar" style="margin-bottom:12px">
  <div class="chips" role="group" aria-label="Status">
    @foreach ($statusLabels as $key => $label)
      <button type="button" class="chip plain" data-load='{"status":"{{ $key }}","page":0}' aria-pressed="{{ $status === $key ? 'true' : 'false' }}">{{ $label }}<span class="n">{{ $counts[$key] }}</span></button>
    @endforeach
  </div>
  <div class="chips" style="margin-left:auto" role="group" aria-label="Level">
    @foreach (['all' => 'All levels'] + array_combine($tiers, $tiers) as $key => $label)
      <button type="button" class="chip plain" data-load='{"tier":"{{ $key }}","page":0}' aria-pressed="{{ $tier === $key ? 'true' : 'false' }}">{{ $label }}</button>
    @endforeach
  </div>
</div>

@if ($rows->isEmpty())
  <div class="card empty-hero">
    <div class="empty-ico">@include('learner._badge-icon', ['icon' => 'books', 'class' => 'ico'])</div>
    <h2>{{ $q !== '' ? 'No activities match' : ['Draft' => 'No drafts waiting', 'Approved' => 'Nothing approved yet', 'Rejected' => 'Nothing rejected'][$status] }}</h2>
    <p>{{ $q !== '' ? 'Try a different word, or clear the filters.' : 'Generate activities to see them here.' }}</p>
  </div>
@else
  <div class="alist">
    @foreach ($rows as $a)
      @php
          $state = $a->status === 'Draft' ? 'AI suggested '.($a->ai_difficulty_tier ?? $a->difficulty_tier)
              : ($a->status === 'Rejected' ? 'Rejected' : ($a->assignments->isNotEmpty() ? 'Assigned to '.$a->assignments->count() : 'Not assigned yet').($a->shared_to_repository ? ' · Shared' : ''));
      @endphp
      <div class="arow" data-window-url="{{ route('teacher.activities.window', $a) }}">
        <div class="atitle"><b>{{ $a->title }}</b><span>{{ $a->grade_level }} · {{ $a->typeLabel() }} · {{ $a->word_count }} words</span></div>
        <span class="pill t-{{ strtolower($a->difficulty_tier) }}">{{ $a->difficulty_tier }}</span>
        <span class="astate">{{ $state }}</span>
        <span class="acts">
          @if ($a->status === 'Draft')
            @if ($locked)
              <button type="button" class="btn small ghost" disabled title="Locked until your account is Active">@include('learner._badge-icon', ['icon' => 'lock-simple', 'class' => 'ico']) Approve</button>
            @else
              <button type="button" class="btn small green" data-post="{{ route('teacher.activities.approve', $a) }}">@include('learner._badge-icon', ['icon' => 'check', 'class' => 'ico']) Approve</button>
            @endif
          @elseif ($a->status === 'Rejected' && ! $locked)
            <button type="button" class="btn small ghost" data-post="{{ route('teacher.activities.restore', $a) }}">@include('learner._badge-icon', ['icon' => 'arrow-counter-clockwise', 'class' => 'ico']) Restore</button>
          @endif
          <button type="button" class="btn small ghost" data-window-url="{{ route('teacher.activities.window', $a) }}">Open</button>
        </span>
      </div>
    @endforeach
  </div>
  <div class="pager">
    <span>Showing {{ $from }} to {{ $from + $rows->count() - 1 }} of {{ $rowTotal }}</span>
    @if ($pages > 1)
      <span class="pb">
        <button type="button" class="btn small ghost" data-load='{"page":{{ $page - 1 }}}' @disabled($page === 0)>@include('learner._badge-icon', ['icon' => 'caret-left', 'class' => 'ico']) Previous</button>
        <button type="button" class="btn small ghost" data-load='{"page":{{ $page + 1 }}}' @disabled($page >= $pages - 1)>Next @include('learner._badge-icon', ['icon' => 'caret-right', 'class' => 'ico'])</button>
      </span>
    @endif
  </div>
@endif
