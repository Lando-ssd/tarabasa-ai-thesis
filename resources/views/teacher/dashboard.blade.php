{{--
  Teacher Home: what needs the teacher today. Viewable while Pending (each linked screen handles
  its own locked state). Learners flagged for attention first, then the four numbers (each one
  opens its screen), then the next things to do and a way straight into any class.
--}}
@extends('layouts.teacher-shell')

@section('title', 'Home | TaraBasa AI')

@php $isPending = $teacher->status !== 'Active'; @endphp

@section('content')
<h1>Welcome back, {{ $user->first_name }}</h1>
<p class="sub">Here is what needs you today.</p>

@if ($isPending)
  <div class="alert amber">
    <span class="alert-ico">@include('learner._badge-icon', ['icon' => 'lock-simple', 'class' => 'ico'])</span>
    <div><b>Your account is waiting for Admin approval</b><span class="d">You can try Generate Activities now with {{ $teacher->free_generation_credits_remaining }} free {{ $teacher->free_generation_credits_remaining === 1 ? 'credit' : 'credits' }}. Nothing you create is visible to any student. Classes and everything else unlock once your school verifies you.</span></div>
  </div>
@elseif ($needsAttentionCount > 0)
  <div class="alert">
    <span class="alert-ico">@include('learner._badge-icon', ['icon' => 'warning-circle', 'class' => 'ico'])</span>
    <div><b>{{ $needsAttentionCount }} {{ $needsAttentionCount === 1 ? 'learner needs' : 'learners need' }} attention</b><span class="d">{{ $latestNeedsAttention->message }}</span></div>
    <a class="btn small" href="{{ route('teacher.notifications.index', ['filter' => 'attention']) }}">View</a>
  </div>
@endif

<div class="tiles">
  <a class="tile" href="{{ route('teacher.classes.index') }}"><b>{{ $classes->count() }}</b><span>Classes</span></a>
  <a class="tile" href="{{ route('teacher.activities.index') }}"><b>{{ $activitiesCount }}</b><span>Activities</span></a>
  <a class="tile" href="{{ route('teacher.activities.index', ['view' => 'list', 'status' => 'Approved']) }}"><b>{{ $approvedCount }}</b><span>Approved</span></a>
  <a class="tile" href="{{ route('teacher.activities.index', ['generate' => 1]) }}"><b>{{ $teacher->free_generation_credits_remaining }}</b><span>Free credits</span></a>
</div>

<div class="two">
  <div class="card">
    <p class="card-title">Waiting for you</p>
    <p class="card-sub">The next things to do, one tap each.</p>
    <div class="list">
      <a class="item" href="{{ route('teacher.activities.index', ['view' => 'board']) }}">
        <span class="item-ico o">@include('learner._badge-icon', ['icon' => 'note-pencil', 'class' => 'ico'])</span>
        <span class="item-body"><span class="item-title">{{ $draftCount }} {{ $draftCount === 1 ? 'draft' : 'drafts' }} to review</span><span class="item-sub">Drag each one into the level it belongs to.</span></span>
        @include('learner._badge-icon', ['icon' => 'caret-right', 'class' => 'ico go'])
      </a>
      @unless ($isPending)
        <a class="item" href="{{ route('teacher.promotions.index', ['tab' => 'claim']) }}">
          <span class="item-ico">@include('learner._badge-icon', ['icon' => 'graduation-cap', 'class' => 'ico'])</span>
          <span class="item-body"><span class="item-title">{{ $claimCount }} {{ $claimCount === 1 ? 'learner' : 'learners' }} to claim</span><span class="item-sub">Released by another teacher and waiting for a class.</span></span>
          @include('learner._badge-icon', ['icon' => 'caret-right', 'class' => 'ico go'])
        </a>
        <a class="item" href="{{ route('teacher.notifications.index') }}">
          <span class="item-ico g">@include('learner._badge-icon', ['icon' => 'bell', 'class' => 'ico'])</span>
          <span class="item-body"><span class="item-title">{{ $unreadNotifications }} unread {{ $unreadNotifications === 1 ? 'alert' : 'alerts' }}</span><span class="item-sub">Session results for your learners.</span></span>
          @include('learner._badge-icon', ['icon' => 'caret-right', 'class' => 'ico go'])
        </a>
      @endunless
      <a class="item" href="{{ route('teacher.activities.index', ['generate' => 1]) }}">
        <span class="item-ico">@include('learner._badge-icon', ['icon' => 'sparkle', 'class' => 'ico'])</span>
        <span class="item-body"><span class="item-title">Generate activities</span><span class="item-sub">{{ $teacher->free_generation_credits_remaining }} free {{ $teacher->free_generation_credits_remaining === 1 ? 'credit' : 'credits' }} left.</span></span>
        @include('learner._badge-icon', ['icon' => 'caret-right', 'class' => 'ico go'])
      </a>
    </div>
  </div>

  <div class="card">
    <p class="card-title">Your classes</p>
    <p class="card-sub">{{ $isPending ? 'Your classes appear here after approval.' : 'Open one to see who is in it and what it has been given.' }}</p>
    <div class="list">
      @forelse ($classes->take(4) as $class)
        <a class="item" href="{{ route('teacher.classes.index', ['school_year' => $class->school_year, 'open' => $class->id]) }}">
          <span class="item-body"><span class="item-title">{{ $class->name }}</span><span class="item-sub">{{ $class->grade_level }} · {{ $class->section }}</span></span>
          <span class="pill">{{ $class->learners_count }} {{ $class->learners_count === 1 ? 'learner' : 'learners' }}</span>
        </a>
      @empty
        @if ($isPending)
          <p class="note" style="margin:0"><span class="lock">@include('learner._badge-icon', ['icon' => 'lock-simple', 'class' => 'ico']) Class Management unlocks with your Admin approval</span></p>
        @else
          <p class="note" style="margin:0">No classes yet for this school year. <a class="link" style="font-size:16px" href="{{ route('teacher.classes.index') }}">Create your first class</a></p>
        @endif
      @endforelse
      @if ($classes->count() > 4)
        <a class="link" href="{{ route('teacher.classes.index') }}">See all {{ $classes->count() }} classes</a>
      @endif
    </div>
  </div>
</div>
@endsection
