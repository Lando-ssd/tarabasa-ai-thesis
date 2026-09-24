{{--
  Parent Progress (Parent Actor Prompt Step 7): for one child at a time, the sessions by who
  started them, what the adaptive engine has them practising now, this week's goal, their badges,
  the accuracy trend and every session. A Diagnostic session is never counted (see AnalyticsController).
--}}
@extends('layouts.parent-shell')

@section('title', 'Progress | TaraBasa AI')

@php
    // The accuracy trend, drawn to one scale: 0 to 100 up the side, one point per reading along the bottom.
    $chartAccuracies = $learnerStats ? $learnerStats['chart_points']->pluck('accuracy')->values() : collect();
    $cW = 640; $cH = 220; $cL = 40; $cR = 16; $cT = 14; $cB = 34;
    $iw = $cW - $cL - $cR; $ih = $cH - $cT - $cB; $n = $chartAccuracies->count();
    $cx = fn ($i) => $cL + ($n === 1 ? $iw / 2 : $iw * $i / max(1, $n - 1));
    $cy = fn ($v) => $cT + $ih - $ih * $v / 100;
    $focus = $selectedLearner ? $selectedLearner->subdomainProgressSummary() : [];
    $upNext = collect($focus)->firstWhere('isUpNext', true);
@endphp

@section('content')
<h1>Progress</h1>
<p class="sub">How {{ $learners->count() === 1 ? 'your child is' : 'your children are' }} reading, and what they are working on next.</p>

@if ($learners->isEmpty())
  <div class="card empty-hero">
    <div class="empty-ico">@include('learner._badge-icon', ['icon' => 'chart-line-up', 'class' => 'ico'])</div>
    <h2>No progress to show yet</h2>
    <p>Once you have added a child, their reading sessions and progress will show up here.</p>
    <div class="actions"><a href="{{ route('parent.children.create') }}" class="btn">Add your child</a></div>
  </div>
@else
  @include('parent._child-chips', ['route' => 'parent.progress'])

  @include('parent._source-cards', ['sourceSummary' => $learnerStats['source_summary']])

  <div class="card adaptive" style="margin-top:16px">
    <div class="eyebrow">@include('learner._badge-icon', ['icon' => 'target', 'class' => 'ico']) Adaptive focus</div>
    @if (empty($focus))
      <p class="headline" style="font-size:20px">{{ $selectedLearner->first_name }} has not finished the first reading check yet. This fills in once they do.</p>
    @else
      <p class="headline">
        @if ($upNext)
          Currently practicing <b>{{ $upNext['label'] }}</b>@if ($upNext['difficultyWord']) ({{ $upNext['difficultyWord'] }})@endif
        @else
          No specific focus is recommended right now.
        @endif
      </p>
      <div class="skill-chips">
        @foreach ($focus as $item)
          <span class="skill {{ $item['isUpNext'] ? 'on' : '' }}">{{ $item['label'] }}: {{ $item['difficultyWord'] ?? 'Not assessed' }}</span>
        @endforeach
      </div>
    @endif
  </div>

  <div class="two" style="margin-top:16px">
    <div class="card">@include('parent._goal-card', ['learner' => $selectedLearner])</div>
    <div class="card">@include('parent._badges-card')</div>
  </div>

  <div class="section-head"><h2>Accuracy trend</h2></div>
  <div class="card chart-wrap">
    @if ($n === 0)
      <p class="hint" style="margin:0">No reading sessions yet. Once {{ $selectedLearner->first_name }} reads something, the trend will appear here.</p>
    @else
      <svg viewBox="0 0 {{ $cW }} {{ $cH }}" role="img" aria-label="Accuracy for each reading">
        @foreach ([0, 25, 50, 75, 100] as $tick)
          <line x1="{{ $cL }}" x2="{{ $cW - $cR }}" y1="{{ $cy($tick) }}" y2="{{ $cy($tick) }}" stroke="#e6eef6" stroke-width="1.5"/>
          <text x="{{ $cL - 8 }}" y="{{ $cy($tick) + 4 }}" text-anchor="end">{{ $tick }}</text>
        @endforeach
        @if ($n > 1)
          <polygon points="{{ $cx(0) }},{{ $cy(0) }} @foreach ($chartAccuracies as $i => $v){{ $cx($i) }},{{ $cy($v) }} @endforeach{{ $cx($n - 1) }},{{ $cy(0) }}" fill="#1f9e83" fill-opacity=".10"/>
          <polyline points="@foreach ($chartAccuracies as $i => $v){{ $cx($i) }},{{ $cy($v) }} @endforeach" fill="none" stroke="#1f9e83" stroke-width="3.5" stroke-linejoin="round" stroke-linecap="round"/>
        @endif
        @foreach ($chartAccuracies as $i => $v)
          <circle cx="{{ $cx($i) }}" cy="{{ $cy($v) }}" r="{{ $i === $n - 1 ? 6 : 4 }}" fill="#fff" stroke="#1f9e83" stroke-width="3"/>
          @if ($i % 2 === 0 || $i === $n - 1)<text x="{{ $cx($i) }}" y="{{ $cH - 12 }}" text-anchor="middle">#{{ $i + 1 }}</text>@endif
        @endforeach
        <text x="{{ $cx($n - 1) }}" y="{{ $cy($chartAccuracies->last()) - 14 }}" text-anchor="middle" style="font-weight:700;fill:#137a63;font-size:13px">{{ $chartAccuracies->last() }}%</text>
      </svg>
    @endif
  </div>

  <div class="section-head"><h2>Session history</h2></div>
  @if ($learnerStats['history']->isEmpty())
    <div class="card hint">No reading sessions yet for {{ $selectedLearner->first_name }}.</div>
  @else
    <div class="list">
      @foreach ($learnerStats['history'] as $session)
        <div class="item">
          <div class="item-body">
            <div class="item-title">{{ $session->activity->title ?? 'Reading activity' }} <span class="tag {{ $session->initiated_by === 'Parent' ? 'parent' : '' }}">{{ $session->initiated_by }}</span></div>
            <div class="item-sub">{{ $session->level_before ?? 'New' }} to {{ $session->level_after ?? 'New' }} &middot; {{ $session->timestamp->format('M j, g:i A') }}</div>
          </div>
          <span class="pill {{ $session->accuracy_percent >= 80 ? 'ok' : 'amber' }}" style="font-size:20px;padding:7px 14px 6px">{{ round($session->accuracy_percent) }}%</span>
        </div>
      @endforeach
    </div>
  @endif
@endif
@endsection
