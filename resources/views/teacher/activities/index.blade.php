{{--
  Activities: two tabs. To review is where new drafts wait to be placed in the level they belong
  to (Easy, Medium or Hard); My activities holds everything already approved, with suggestions
  for the teacher's classes on top, filters and paging. The AI can only guess how hard a text is
  (from how familiar the words are and how long it is), so the teacher decides.

  The open tab is #actMain, rendered by _main and refreshed in place when filters change or
  a card is placed. Generate lives here, in a window. Everything else (read, edit, reject,
  assign, share) is the activity's own window, fetched when a card opens.
--}}
@extends('layouts.teacher-shell')

@section('title', 'Activities | TaraBasa AI')

@section('content')
<div class="head">
  <div>
    <h1>Activities</h1>
    <p class="sub">New drafts wait in To review. Place each one in the level it belongs to. Everything approved is in My activities.</p>
  </div>
  <div class="head-actions">
    <span class="credit">@include('learner._badge-icon', ['icon' => 'sparkle', 'class' => 'ico']) {{ $teacher->free_generation_credits_remaining }} free {{ $teacher->free_generation_credits_remaining === 1 ? 'credit' : 'credits' }}</span>
    <button type="button" class="btn" data-open="genDlg">@include('learner._badge-icon', ['icon' => 'sparkle', 'class' => 'ico']) Generate activities</button>
  </div>
</div>

@if ($generation)
  @include('teacher.activities._generation')
@endif

@if ($locked)
  <div class="alert amber" style="padding:10px 16px">
    <span class="alert-ico" style="width:36px;height:36px;font-size:20px">@include('learner._badge-icon', ['icon' => 'lock-simple', 'class' => 'ico'])</span>
    <div><b style="font-size:19px">Sorting, editing, rejecting and assigning are locked</b><span class="d">They unlock when your school verification is approved. You can still generate and read your drafts.</span></div>
  </div>
@endif

<div class="toolbar">
  <div class="search">
    @include('learner._badge-icon', ['icon' => 'magnifying-glass', 'class' => 'ico'])
    <label class="sr" for="actQ">Search activities</label>
    <input type="search" id="actQ" placeholder="Search by title, skill or topic" autocomplete="off" value="{{ $q }}">
  </div>
  <select id="actGrade" class="mini wide" aria-label="Grade">
    @foreach (['All' => 'All grades', 'Grade 1' => 'Grade 1', 'Grade 2' => 'Grade 2', 'Grade 3' => 'Grade 3'] as $value => $label)
      <option value="{{ $value }}" @selected($grade === $value)>{{ $label }}</option>
    @endforeach
  </select>
</div>

<div id="actMain" data-url="{{ route('teacher.activities.index') }}">@include('teacher.activities._main')</div>
<script type="application/json" id="actState">@json($state)</script>
@endsection

@push('dialogs')
  @include('teacher.activities._generate')
@endpush
