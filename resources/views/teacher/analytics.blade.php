{{--
  Teacher Analytics (Actor Prompt Step 10): one learner at a time, or one group. Teacher-assigned
  and parent-started readings are always two separate numbers, never combined. A one-time
  placement test is not practice, so diagnostic sessions are left out everywhere here.

  Fits one laptop window: the learner card, three summary cards, the trend and the four newest
  sessions. "Change learner" opens a search window (class chips, then type), "See all" opens the
  rest. The days in a row and this week's readings are the same numbers the child sees.
--}}
@extends('layouts.teacher-shell')

@section('title', 'Analytics | TaraBasa AI')

@php
    $noLearners = $learners->isEmpty() && $groupTags->isEmpty();
    $pct = fn ($v) => $v === null ? 'not yet' : $v.'%';
    $scoreClass = fn ($v) => $v >= 80 ? 'good' : ($v >= 65 ? 'mid' : 'low');
    $mastery = fn ($l) => $l->mastery_level ?? 'New';
    $pill = fn ($l) => '<span class="pill '.($l->mastery_level ? 'lv-'.strtolower($l->mastery_level) : '').'">'.e($mastery($l)).'</span>';
@endphp

@section('content')
<div class="head">
  <div>
    <h1>Analytics</h1>
    <p class="sub">How your learners are reading, one learner or one group at a time.</p>
  </div>
  <div class="chips" role="group" aria-label="View">
    <a class="chip plain" href="{{ route('teacher.analytics.index', ['mode' => 'learner']) }}" aria-pressed="{{ $mode === 'learner' ? 'true' : 'false' }}">By learner</a>
    <a class="chip plain" href="{{ route('teacher.analytics.index', ['mode' => 'group']) }}" aria-pressed="{{ $mode === 'group' ? 'true' : 'false' }}">By group</a>
  </div>
</div>

@if ($noLearners)
  <div class="card empty-hero">
    <div class="empty-ico">@include('learner._badge-icon', ['icon' => 'chart-line-up', 'class' => 'ico'])</div>
    <h2>No learners yet</h2>
    <p>Once you have joined learners into your classes, their reading progress shows up here.</p>
  </div>

@elseif ($mode === 'learner')
  @if ($learners->isEmpty())
    <div class="card empty-hero"><div class="empty-ico">@include('learner._badge-icon', ['icon' => 'chart-line-up', 'class' => 'ico'])</div><h2>You don't have any learners yet</h2><p>Add a learner to a class and their progress appears here.</p></div>
  @else
    @php
        $l = $selectedLearner;
        $history = $learnerStats['history'];
        $recent = $history->take(4);
        $points = $history->reverse()->take(-12)->map(fn ($s) => (int) round($s->accuracy_percent ?? 0))->values();
        $focus = $l->subdomainProgressSummary();
        $upNext = collect($focus)->firstWhere('isUpNext', true);
        $src = $learnerStats['source_summary'];
    @endphp

    <div class="card who">
      @include('teacher._avatar', ['learner' => $l, 'class' => 'av big'])
      <div class="info">
        <h2>{{ $l->first_name }} {{ $l->last_name }}</h2>
        <div class="meta">{{ $l->grade_level }} · {{ $l->schoolClass->name ?? 'No class' }} · {{ $l->learner_code }}</div>
        <div style="margin-top:8px">{!! $pill($l) !!}</div>
      </div>
      <div class="nums">
        <div><b>{{ $summary['dayStreak'] }}</b><span>Days in a row</span></div>
        <div><b>{{ $summary['weeklyCount'] }} of {{ $summary['weeklyTarget'] }}</b><span>This week</span></div>
      </div>
      <button type="button" class="btn small ghost" data-open="pickDlg">@include('learner._badge-icon', ['icon' => 'users-three', 'class' => 'ico']) Change learner</button>
    </div>

    <div class="g3">
      @foreach ([['Assigned by you', 'Teacher'], ['Started by a parent', 'Parent']] as [$label, $key])
        <div class="card src">
          <span class="label">{{ $label }}</span>
          <div class="value">{{ $src[$key]['count'] }} {{ $src[$key]['count'] === 1 ? 'session' : 'sessions' }}</div>
          <div class="avg">Average score: {{ $pct($src[$key]['avg_accuracy'] ?? null) }}</div>
        </div>
      @endforeach
      <div class="card adaptive">
        <div class="eyebrow">@include('learner._badge-icon', ['icon' => 'target', 'class' => 'ico']) Adaptive focus</div>
        @if (empty($focus))
          <p class="headline">{{ $l->first_name }} has not finished the first reading check yet.</p>
        @else
          <p class="headline">@if ($upNext)Now practicing: <b>{{ $upNext['label'] }}</b>@if ($upNext['difficultyWord']) ({{ $upNext['difficultyWord'] }})@endif @else No specific focus right now.@endif</p>
          <div class="skill-chips">@foreach ($focus as $item)<span class="skill {{ $item['isUpNext'] ? 'on' : '' }}">{{ $item['label'] }}</span>@endforeach</div>
        @endif
      </div>
    </div>

    @if ($history->isEmpty())
      <div class="card empty-hero" style="margin-top:14px">
        <div class="empty-ico">@include('learner._badge-icon', ['icon' => 'chart-line-up', 'class' => 'ico'])</div>
        <h2>No reading sessions yet</h2>
        <p>{{ $l->first_name }} has not finished a practice reading. Their accuracy trend appears here after the first one.</p>
      </div>
    @else
      @php
          $W = 560; $H = 176; $Lm = 40; $Rm = 14; $Tm = 14; $Bm = 30; $iw = $W - $Lm - $Rm; $ih = $H - $Tm - $Bm; $n = $points->count();
          $x = fn ($i) => $Lm + ($n === 1 ? $iw / 2 : ($iw * $i) / ($n - 1));
          $y = fn ($v) => $Tm + $ih - ($ih * $v) / 100;
          $line = $points->map(fn ($v, $i) => round($x($i), 1).','.round($y($v), 1))->implode(' ');
      @endphp
      <div class="split">
        <div class="card">
          <p class="card-title">Accuracy trend</p>
          <p class="card-sub">Each dot is one reading, oldest to newest.</p>
          <div class="chart-wrap">
            <svg viewBox="0 0 {{ $W }} {{ $H }}" role="img" aria-label="Accuracy trend, {{ $n }} readings, latest {{ $points->last() }} percent">
              @foreach ([0, 25, 50, 75, 100] as $g)
                <line x1="{{ $Lm }}" x2="{{ $W - $Rm }}" y1="{{ $y($g) }}" y2="{{ $y($g) }}" stroke="#e6eef6" stroke-width="1.5"/>
                <text x="{{ $Lm - 8 }}" y="{{ $y($g) + 4 }}" text-anchor="end">{{ $g }}</text>
              @endforeach
              @if ($n > 1)
                <path d="M{{ round($x(0), 1) }},{{ $y(0) }} L{{ str_replace(' ', ' L', $line) }} L{{ round($x($n - 1), 1) }},{{ $y(0) }} Z" fill="#1f9e83" opacity=".1"/>
                <polyline points="{{ $line }}" fill="none" stroke="#1f9e83" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"/>
              @endif
              @foreach ($points as $i => $v)
                <circle cx="{{ round($x($i), 1) }}" cy="{{ round($y($v), 1) }}" r="{{ $i === $n - 1 ? 6 : 4 }}" fill="#fff" stroke="#1f9e83" stroke-width="3"/>
                @if ($i % 2 === 0 || $i === $n - 1)<text x="{{ round($x($i), 1) }}" y="{{ $H - 8 }}" text-anchor="middle">#{{ $i + 1 }}</text>@endif
              @endforeach
            </svg>
          </div>
        </div>
        <div class="card">
          <p class="card-title">Recent sessions</p>
          <p class="card-sub">{{ $history->count() }} {{ $history->count() === 1 ? 'reading' : 'readings' }} in total.</p>
          @foreach ($recent as $s)
            @include('teacher._session-row', ['s' => $s])
          @endforeach
          @if ($history->count() > 4)
            <div style="margin-top:10px"><button type="button" class="link" data-open="sessDlg">See all {{ $history->count() }}</button></div>
          @endif
        </div>
      </div>
    @endif
  @endif

@else
  {{-- ---------- by group ---------- --}}
  @if ($groupTags->isEmpty())
    <div class="card empty-hero"><div class="empty-ico">@include('learner._badge-icon', ['icon' => 'users-three', 'class' => 'ico'])</div><h2>No groups yet</h2><p>Give a class a group tag (edit the class) and its readings can be looked at together here.</p></div>
  @else
    @php $rows = $groupStats['learner_rows']; $gsrc = $groupStats['source_summary']; @endphp
    <div class="chips" style="margin-bottom:16px" role="group" aria-label="Group">
      @foreach ($groupTags as $tag)
        <a class="chip plain" href="{{ route('teacher.analytics.index', ['mode' => 'group', 'group_tag' => $tag]) }}" aria-pressed="{{ $tag === $selectedGroupTag ? 'true' : 'false' }}">{{ $tag }}</a>
      @endforeach
    </div>
    <div class="g2">
      @foreach ([['Assigned by you', 'Teacher'], ['Started by a parent', 'Parent']] as [$label, $key])
        <div class="card src">
          <span class="label">{{ $label }}</span>
          <div class="value">{{ $gsrc[$key]['count'] }} {{ $gsrc[$key]['count'] === 1 ? 'session' : 'sessions' }}</div>
          <div class="avg">Across the group · Average score: {{ $pct($gsrc[$key]['avg_accuracy']) }}</div>
        </div>
      @endforeach
    </div>
    @if ($rows->isEmpty())
      <div class="card empty-hero"><div class="empty-ico">@include('learner._badge-icon', ['icon' => 'users-three', 'class' => 'ico'])</div><h2>No learners in this group</h2><p>Learners appear here once they are in a class that carries this tag.</p></div>
    @else
      <div class="head" style="margin-bottom:10px">
        <div><h3>Who needs help first</h3><p class="note" style="margin:0">{{ $rows->count() }} {{ $rows->count() === 1 ? 'learner' : 'learners' }} in this group, lowest level first.</p></div>
        @if ($rows->count() > 8)<button type="button" class="btn small ghost" data-open="grpDlg">See all {{ $rows->count() }}</button>@endif
      </div>
      <div class="lgrid">
        @foreach ($rows->take(8) as $row)
          @include('teacher._group-row', ['row' => $row])
        @endforeach
      </div>
    @endif
  @endif
@endif
@endsection

@if (! $noLearners)
  @push('dialogs')
    @if ($mode === 'learner' && $learners->isNotEmpty())
      {{-- Change learner: class chips, then type to search every class. --}}
      <dialog id="pickDlg" class="win" aria-label="Choose a learner">
        <div class="win-in">
          <header class="win-head">
            <div class="win-titles"><h2>Choose a learner</h2><p class="win-meta">Pick a class, or type a name.</p></div>
            <button type="button" class="x" data-close aria-label="Close">@include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])</button>
          </header>
          <div class="win-body" data-class-picker="#pickList" data-default="{{ $selectedLearner->class_id }}">
            <div class="toolbar"><div class="search">@include('learner._badge-icon', ['icon' => 'magnifying-glass', 'class' => 'ico'])<label class="sr" for="pickQ">Search learners</label><input type="search" id="pickQ" placeholder="Find a learner" autocomplete="off"></div></div>
            <div class="chips" style="margin-bottom:12px">
              @foreach ($pickerClasses as $pc)
                <button type="button" class="chip plain" data-class-chip="{{ $pc->id }}" aria-pressed="false">{{ $pc->name }}@if ($pc->school_year !== \App\Models\SchoolClass::currentSchoolYear()) ({{ $pc->school_year }})@endif<span class="n">{{ $learners->where('class_id', $pc->id)->count() }}</span></button>
              @endforeach
              <span class="note" data-searching hidden style="align-self:center">Searching all your classes</span>
            </div>
            <div class="lgrid" id="pickList">
              @foreach ($learners as $pl)
                <a class="lrow" href="{{ route('teacher.analytics.index', ['mode' => 'learner', 'learner_id' => $pl->id]) }}" data-class="{{ $pl->class_id }}" data-search="{{ strtolower($pl->first_name.' '.$pl->last_name.' '.$pl->learner_code) }}" hidden>
                  @include('teacher._avatar', ['learner' => $pl])
                  <span class="lname"><b>{{ $pl->first_name }} {{ $pl->last_name }}</b><small>{{ $pl->schoolClass->name ?? '' }} · {{ $pl->learner_code }}</small></span>
                  {!! $pill($pl) !!}
                </a>
              @endforeach
            </div>
            <p class="note" data-empty hidden>No learner matches.</p>
          </div>
        </div>
      </dialog>

      @if (isset($history) && $history->count() > 4)
        <dialog id="sessDlg" class="win" aria-label="All sessions">
          <div class="win-in mid">
            <header class="win-head">
              <div class="win-titles"><h2>All sessions</h2><p class="win-meta">{{ $selectedLearner->first_name }} {{ $selectedLearner->last_name }} · {{ $history->count() }} readings</p></div>
              <button type="button" class="x" data-close aria-label="Close">@include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])</button>
            </header>
            <div class="win-body">@foreach ($history as $s)@include('teacher._session-row', ['s' => $s])@endforeach</div>
            <footer class="win-foot"><button type="button" class="btn ghost small" data-close>Close</button></footer>
          </div>
        </dialog>
      @endif
    @endif

    @if ($mode === 'group' && isset($rows) && $rows->count() > 8)
      <dialog id="grpDlg" class="win" aria-label="{{ $selectedGroupTag }}">
        <div class="win-in">
          <header class="win-head">
            <div class="win-titles"><h2>{{ $selectedGroupTag }}</h2><p class="win-meta">{{ $rows->count() }} learners in this group, lowest level first.</p></div>
            <button type="button" class="x" data-close aria-label="Close">@include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])</button>
          </header>
          <div class="win-body">
            <div class="toolbar"><div class="search">@include('learner._badge-icon', ['icon' => 'magnifying-glass', 'class' => 'ico'])<label class="sr" for="grpQ">Search this group</label><input type="search" id="grpQ" placeholder="Find a learner in this group" autocomplete="off" data-filter="#grpList"></div></div>
            <div class="lgrid" id="grpList">@foreach ($rows as $row)@include('teacher._group-row', ['row' => $row, 'searchable' => true])@endforeach</div>
            <p class="note" data-empty hidden>No learner matches.</p>
          </div>
        </div>
      </dialog>
    @endif
  @endpush
@endif
