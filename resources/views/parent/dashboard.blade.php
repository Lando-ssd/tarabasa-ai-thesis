{{--
  Parent Home. One child at a time (pick from the chips); what matters first: an alert if the
  latest reading was flagged, then the child's summary (the real day streak, points, words a
  minute), this week's goal, the badges, the sessions by who started them, and the recent readings.
  Numbers come from the same sources the child's own Home uses (see App\Support\ChildSummary).
--}}
@extends('layouts.parent-shell')

@section('title', 'Home | TaraBasa AI')

@php
    $clock = \App\Support\LearnerClock::now();
    $hour = $clock->hour;
    $timeGreeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
@endphp

@section('content')
<div class="eyebrow">{{ $clock->format('l') }}</div>
<h1>{{ $timeGreeting }}, {{ $user->first_name }}</h1>
<p class="sub">Here is how your {{ $learners->count() === 1 ? 'child is' : 'children are' }} doing today.</p>

@if ($learners->isEmpty())
  {{-- Parent Actor Prompt, Validation & Edge Cases: zero Learners linked gets its own friendly empty state. --}}
  <div class="card empty-hero">
    <div class="empty-ico">@include('learner._badge-icon', ['icon' => 'users-three', 'class' => 'ico'])</div>
    <h2>No children added yet</h2>
    <p>Add your first child to start following their reading journey with TaraBasa AI.</p>
    <div class="actions"><a href="{{ route('parent.children.create') }}" class="btn">Add your child</a></div>
  </div>
@else
  @include('parent._child-chips', ['route' => 'parent.dashboard', 'flagged' => $flaggedByLearner])

  @if ($childData['is_flagged'])
    <div class="alert" style="margin-top:18px">
      <div class="alert-ico">@include('learner._badge-icon', ['icon' => 'warning-circle', 'class' => 'ico'])</div>
      <div>
        <b>{{ $selectedLearner->first_name }} needs attention</b>
        <span>Their most recent reading was flagged for extra support. A quick look could help.</span>
      </div>
      <a href="{{ route('parent.progress', ['learner_id' => $selectedLearner->id]) }}" class="btn small">View session</a>
    </div>
  @endif

  <div class="card focus {{ $childData['is_flagged'] ? 'flagged' : '' }}" style="margin-top:18px">
    @include('parent._avatar', ['learner' => $selectedLearner, 'class' => 'focus-av'])
    <div>
      <h2>
        {{ $selectedLearner->first_name }}
        @if ($selectedLearner->mastery_level)<span class="pill lv-{{ strtolower($selectedLearner->mastery_level) }}">{{ $selectedLearner->mastery_level }}</span>@endif
        @if ($childData['is_flagged'])<span class="pill warn">@include('learner._badge-icon', ['icon' => 'warning-circle', 'class' => 'ico']) Flagged</span>@endif
      </h2>
      <div class="meta">{{ $selectedLearner->grade_level }} &middot; {{ $selectedLearner->schoolClass->name ?? 'Not in a class yet' }} &middot; Code {{ $selectedLearner->learner_code }}</div>
      <div class="stats">
        <div class="stat"><b>@include('learner._badge-icon', ['icon' => 'fire', 'class' => 'ico']){{ $summary['dayStreak'] }}</b><span>Days in a row</span></div>
        <div class="stat"><b>@include('learner._badge-icon', ['icon' => 'star', 'class' => 'ico']){{ $selectedLearner->points }}</b><span>Points</span></div>
        <div class="stat"><b>@include('learner._badge-icon', ['icon' => 'timer', 'class' => 'ico']){{ $childData['wcpm'] !== null ? round($childData['wcpm']) : 'N/A' }}</b><span>Words a minute</span></div>
      </div>
    </div>
    {{-- A parent never reads for the child directly: the Learner always needs their own PIN
         (Learner Actor Prompt, Step 1). So this goes to the Repository, where a parent unlocks
         something that the child then reads through their own login. --}}
    <a href="{{ route('parent.repository.index', ['learner_id' => $selectedLearner->id]) }}" class="btn">Browse extra activities</a>
  </div>

  <div class="two">
    <div class="card">@include('parent._goal-card', ['learner' => $selectedLearner])</div>
    <div class="card">@include('parent._badges-card')</div>
  </div>

  @include('parent._source-cards', ['sourceSummary' => $childData['source_summary']])

  <div class="section-head">
    <h2>Recent sessions</h2>
    <a href="{{ route('parent.progress', ['learner_id' => $selectedLearner->id]) }}" class="link">See all</a>
  </div>
  @if ($childData['recent_sessions']->isEmpty())
    <div class="card hint">No reading sessions yet for {{ $selectedLearner->first_name }}. Once they read something, it will show up here.</div>
  @else
    <div class="list">
      @foreach ($childData['recent_sessions'] as $session)
        <div class="item">
          <div class="item-ico {{ $session->flagged_needs_attention ? 'warn' : 'ok' }}">@include('learner._badge-icon', ['icon' => $session->flagged_needs_attention ? 'warning-circle' : 'check-circle', 'class' => 'ico'])</div>
          <div class="item-body">
            <div class="item-title">{{ $session->activity->title ?? 'Reading activity' }} <span class="tag {{ $session->initiated_by === 'Parent' ? 'parent' : '' }}">{{ $session->initiated_by }}</span></div>
            <div class="item-sub">
              @if ($session->flagged_needs_attention)
                Flagged for extra support
              @elseif ($session->level_before !== $session->level_after)
                {{ round($session->accuracy_percent) }}% accuracy, moved to {{ $session->level_after }}
              @else
                {{ round($session->accuracy_percent) }}% accuracy
              @endif
            </div>
          </div>
          <div class="item-time">{{ $session->timestamp->diffForHumans() }}</div>
        </div>
      @endforeach
    </div>
  @endif
@endif

<div class="section-head"><h2>Add or link a child</h2></div>
<div class="actions">
  <a href="{{ route('parent.children.create') }}" class="btn ghost">@include('learner._badge-icon', ['icon' => 'plus-circle', 'class' => 'ico']) Add another child</a>
  <a href="{{ route('parent.children.link') }}" class="btn ghost">@include('learner._badge-icon', ['icon' => 'link-simple', 'class' => 'ico']) Link an existing child</a>
</div>
@endsection
