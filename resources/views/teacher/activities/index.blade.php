{{--
  Activities: a review board where new drafts wait in "To review" and are dragged into the level
  they belong to (Easy, Medium or Hard), and a list of every activity. The AI can only guess how
  hard a text is (from how familiar the words are and how long it is), so the teacher decides.

  The board or list is #actMain, rendered by _main and refreshed in place when filters change or
  a card is dropped. Generate lives here, in a window. Everything else (read, edit, reject,
  assign, share) is the activity's own window, fetched when a card opens.
--}}
@extends('layouts.teacher-shell')

@section('title', 'Activities | TaraBasa AI')

@section('content')
<div class="head">
  <div>
    <h1>Activities</h1>
    <p class="sub">Drag each new draft into the level it belongs to, or use the list.</p>
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
    <input type="search" id="actQ" placeholder="Search by title, grade, skill or topic" autocomplete="off" value="{{ $q }}">
  </div>
  <select id="actGrade" class="mini wide" aria-label="Grade">
    @foreach (['All' => 'All grades', 'Grade 1' => 'Grade 1', 'Grade 2' => 'Grade 2', 'Grade 3' => 'Grade 3'] as $value => $label)
      <option value="{{ $value }}" @selected($grade === $value)>{{ $label }}</option>
    @endforeach
  </select>
  <div class="chips viewchips" role="group" aria-label="View">
    <button type="button" class="chip plain" data-load='{"view":"board","page":0,"tray":0}' aria-pressed="{{ $view === 'board' ? 'true' : 'false' }}">@include('learner._badge-icon', ['icon' => 'squares-four', 'class' => 'ico']) Board</button>
    <button type="button" class="chip plain" data-load='{"view":"list","page":0,"tray":0}' aria-pressed="{{ $view === 'list' ? 'true' : 'false' }}">@include('learner._badge-icon', ['icon' => 'list-bullets', 'class' => 'ico']) List</button>
  </div>
</div>

<div id="actMain" data-url="{{ route('teacher.activities.index') }}">@include('teacher.activities._main')</div>
<script type="application/json" id="actState">@json($state)</script>
@endsection

@push('dialogs')
  @include('teacher.activities._generate')
@endpush
