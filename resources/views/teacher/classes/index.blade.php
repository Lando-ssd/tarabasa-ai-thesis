{{--
  Class Management. Every class is a card that says how many activities it has been given; a
  search box finds any class or learner by typing. Opening a card shows the class in its own
  window: its learners, its activities (with assigning), adding a learner, editing the class, and
  one learner's history. Creating a class is a window too. Nothing needs scrolling to find.

  A Pending teacher sees this locked and explained (approval gates touching real learners, not
  visibility); a past school year is read only, enforced again on the server.
--}}
@extends('layouts.teacher-shell')

@section('title', 'Classes | TaraBasa AI')

@php
    $isActive = $teacher->status === 'Active';
    $canCreate = $isActive && ! $isPastYear;
    $gradeChips = ['All', 'Grade 1', 'Grade 2', 'Grade 3'];
@endphp

@section('content')
<div class="head">
  <div>
    <h1>Classes</h1>
    <p class="sub">Open a class to see its learners and activities, add a learner, or edit it.</p>
  </div>
  <div class="head-actions">
    @if ($canCreate)
      <button type="button" class="btn" data-open="newClassDlg">@include('learner._badge-icon', ['icon' => 'plus', 'class' => 'ico']) New class</button>
    @elseif (! $isActive)
      <span class="lock">@include('learner._badge-icon', ['icon' => 'lock-simple', 'class' => 'ico']) Locked until your account is Active</span>
    @endif
  </div>
</div>

@if (! $isActive)
  <div class="alert amber">
    <span class="alert-ico">@include('learner._badge-icon', ['icon' => 'lock-simple', 'class' => 'ico'])</span>
    <div>
      <b>Class Management is locked</b>
      <span class="d">
        @if ($teacher->status === 'Pending')
          Your school verification is still pending. Once approved, you can create classes and add learners. In the meantime, Generate Activities works with your free credits.
        @else
          Your teacher registration wasn't approved, so Class Management isn't available on this account. Contact your school's TaraBasa admin if you believe this is a mistake.
        @endif
      </span>
    </div>
  </div>
  <div class="card empty-hero">
    <div class="empty-ico">@include('learner._badge-icon', ['icon' => 'chalkboard-teacher', 'class' => 'ico'])</div>
    <h2>No classes yet</h2>
    <p>Your classes appear here as soon as your account is approved.</p>
  </div>
@else
  <div class="toolbar">
    <div class="find">
      @include('learner._badge-icon', ['icon' => 'magnifying-glass', 'class' => 'ico'])
      <label class="sr" for="findBox">Find a class or learner</label>
      <input type="search" id="findBox" placeholder="Find a class or learner" autocomplete="off">
      <div class="find-res" id="findRes" hidden></div>
    </div>
    <div class="chips" role="group" aria-label="School year">
      @foreach ($availableYears as $year)
        <a class="chip plain" href="{{ route('teacher.classes.index', ['school_year' => $year]) }}" aria-pressed="{{ $year === $selectedYear ? 'true' : 'false' }}">SY {{ $year }} · {{ $year === $currentSchoolYear ? 'Current' : (\App\Models\SchoolClass::isYearPast($year) ? 'Past' : 'Upcoming') }}</a>
      @endforeach
    </div>
  </div>

  <div class="chips" style="margin-bottom:16px" role="group" aria-label="Grade">
    @foreach ($gradeChips as $g)
      <button type="button" class="chip plain" data-grade-filter="{{ $g }}" aria-pressed="{{ $g === 'All' ? 'true' : 'false' }}">{{ $g }}</button>
    @endforeach
  </div>

  @if ($isPastYear)
    <div class="alert amber" style="padding:10px 16px">
      <span class="alert-ico" style="width:36px;height:36px;font-size:20px">@include('learner._badge-icon', ['icon' => 'lock-simple', 'class' => 'ico'])</span>
      <div><b style="font-size:19px">A past school year is read only</b><span class="d">You can look, but not change anything.</span></div>
    </div>
  @endif

  @if ($classes->isEmpty())
    <div class="card empty-hero">
      <div class="empty-ico">@include('learner._badge-icon', ['icon' => 'chalkboard-teacher', 'class' => 'ico'])</div>
      <h2>No classes here</h2>
      <p>{{ $isPastYear ? "No classes were recorded for SY {$selectedYear}." : "No classes yet for SY {$selectedYear}. Create your first class to get started." }}</p>
      @if ($canCreate)<div class="actions" style="justify-content:center"><button type="button" class="btn" data-open="newClassDlg">Create a class</button></div>@endif
    </div>
  @else
    <div class="ctiles">
      @foreach ($classes as $c)
        @php $na = $classActivities[$c->id]->count(); @endphp
        <button type="button" class="ctile" data-open="class-{{ $c->id }}" data-grade="{{ $c->grade_level }}">
          <span class="ctile-top">
            <span class="pill">{{ $c->grade_level }}</span>
            @if ($c->group_tag)<span class="pill blue">@include('learner._badge-icon', ['icon' => 'users-three', 'class' => 'ico']){{ $c->group_tag }}</span>@endif
            @if ($isPastYear)<span class="pill amber">Read only</span>@endif
          </span>
          <span class="ctile-name">{{ $c->name }}</span>
          <span class="ctile-meta">{{ $c->section }} · SY {{ $c->school_year }}</span>
          <span class="ctile-acts {{ $na ? '' : 'none' }}">@include('learner._badge-icon', ['icon' => 'books', 'class' => 'ico']){{ $na ? $na.' '.($na === 1 ? 'activity' : 'activities').' assigned' : 'No activities yet' }}</span>
          <span class="ctile-foot">
            <span class="stack">
              @foreach ($c->learners->take(4) as $l)@include('teacher._avatar', ['learner' => $l])@endforeach
              @if ($c->learners->count() > 4)<span class="more">+{{ $c->learners->count() - 4 }}</span>@endif
            </span>
            <span class="count">{{ $c->learners->count() }} {{ $c->learners->count() === 1 ? 'learner' : 'learners' }}</span>
            @include('learner._badge-icon', ['icon' => 'caret-right', 'class' => 'ico go'])
          </span>
        </button>
      @endforeach
    </div>
  @endif
@endif
@endsection

@if ($isActive)
  @push('dialogs')
    @include('teacher.classes._new')
    @foreach ($classes as $c)
      @include('teacher.classes._window', ['class' => $c])
    @endforeach
    <script type="application/json" id="classData">@json($clientData)</script>
  @endpush
@endif
