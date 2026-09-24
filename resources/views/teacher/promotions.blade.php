{{--
  Grade Promotions (Teacher Actor Prompt Step 9). Release: a learner who is ready for the next
  grade leaves your class and waits, still fully active, until a teacher with a matching class
  claims them. Claim: the platform-wide queue of released learners (never only the ones you
  released); your own matching classes are the only valid targets. Both ask first in a small
  window. Grade 3 has no next grade, enforced on the server too.
--}}
@extends('layouts.teacher-shell')

@section('title', 'Promotions | TaraBasa AI')

@php
    $isActive = $teacher->status === 'Active';
    $all = $releasableLearners->flatten(1);
@endphp

@section('content')
<div class="head">
  <div>
    <h1>Promotions</h1>
    <p class="sub">Release learners who are ready for the next grade, or claim ones released to you.</p>
  </div>
  <div class="chips" role="group" aria-label="Tab">
    <a class="chip plain" href="{{ route('teacher.promotions.index', ['tab' => 'release']) }}" aria-pressed="{{ $tab === 'release' ? 'true' : 'false' }}">Release</a>
    <a class="chip plain" href="{{ route('teacher.promotions.index', ['tab' => 'claim']) }}" aria-pressed="{{ $tab === 'claim' ? 'true' : 'false' }}">Claim incoming @if ($actionableCount > 0)<span class="n">{{ $actionableCount }}</span>@endif</a>
  </div>
</div>

@if (! $isActive)
  <div class="alert amber">
    <span class="alert-ico">@include('learner._badge-icon', ['icon' => 'lock-simple', 'class' => 'ico'])</span>
    <div><b>Releasing and claiming is locked</b><span class="d">You can see what is here, but releasing a learner or claiming one into your class needs an Active account.</span></div>
  </div>
@endif

@if ($tab === 'release')
  @if ($myCurrentClasses->isEmpty() || $all->isEmpty())
    <div class="card empty-hero">
      <div class="empty-ico">@include('learner._badge-icon', ['icon' => 'graduation-cap', 'class' => 'ico'])</div>
      <h2>Nothing to release yet</h2>
      <p>Learners in your classes for this school year show up here, ready to move up a grade.</p>
    </div>
  @else
    <div data-class-picker="#promoList" data-default="{{ $myCurrentClasses->first(fn ($c) => $releasableLearners->get($c->id, collect())->isNotEmpty())->id ?? $myCurrentClasses->first()->id }}">
      <div class="toolbar">
        <div class="search">
          @include('learner._badge-icon', ['icon' => 'magnifying-glass', 'class' => 'ico'])
          <label class="sr" for="promoQ">Find a learner</label>
          <input type="search" id="promoQ" placeholder="Find a learner" autocomplete="off">
        </div>
      </div>
      <div class="chips" style="margin-bottom:16px">
        @foreach ($myCurrentClasses as $c)
          <button type="button" class="chip plain" data-class-chip="{{ $c->id }}" aria-pressed="false">{{ $c->name }}<span class="n">{{ $releasableLearners->get($c->id, collect())->count() }}</span></button>
        @endforeach
        <span class="note" data-searching hidden style="align-self:center">Searching all your classes</span>
      </div>
      <div class="lgrid" id="promoList">
        @foreach ($myCurrentClasses as $c)
          @foreach ($releasableLearners->get($c->id, collect()) as $l)
            @php $gradeNumber = (int) substr($l->grade_level, 6); @endphp
            <div class="lrow static" data-class="{{ $c->id }}" data-search="{{ strtolower($l->first_name.' '.$l->last_name.' '.$l->learner_code) }}" hidden>
              @include('teacher._avatar', ['learner' => $l])
              <span class="lname"><b>{{ $l->first_name }} {{ $l->last_name }}</b><small>{{ $l->grade_level }} · {{ $c->name }} · {{ $l->mastery_level ?? 'New' }}</small></span>
              @if ($gradeNumber >= 3)
                <span class="pill" style="margin-left:auto">Final year</span>
              @elseif ($isActive)
                <button type="button" class="btn small blue" data-open="releaseDlg" data-fill="release" data-name="{{ $l->first_name }} {{ $l->last_name }}" data-class="{{ $c->name }}" data-next="Grade {{ $gradeNumber + 1 }}" data-action="{{ route('teacher.promotions.release', $l) }}">@include('learner._badge-icon', ['icon' => 'graduation-cap', 'class' => 'ico']) To Grade {{ $gradeNumber + 1 }}</button>
              @else
                <button type="button" class="btn small ghost" disabled title="Locked until your account is Active">@include('learner._badge-icon', ['icon' => 'lock-simple', 'class' => 'ico']) To Grade {{ $gradeNumber + 1 }}</button>
              @endif
            </div>
          @endforeach
        @endforeach
      </div>
      <p class="note" data-empty hidden>No learners in your current classes match.</p>
    </div>
  @endif
@else
  @if ($pendingRecords->isEmpty())
    <div class="card empty-hero">
      <div class="empty-ico">@include('learner._badge-icon', ['icon' => 'graduation-cap', 'class' => 'ico'])</div>
      <h2>Nothing to claim</h2>
      <p>Released learners waiting for a class will show up here.</p>
    </div>
  @else
    <div class="list">
      @foreach ($pendingRecords as $record)
        @php $matching = $matchingClassesByRecord->get($record->id, collect()); $learner = $record->learner; @endphp
        <div class="item" style="cursor:default">
          @include('teacher._avatar', ['learner' => $learner])
          <span class="item-body">
            <span class="item-title">{{ $learner->first_name }} {{ $learner->last_name }}</span>
            <span class="item-sub">Released from {{ $record->releasedFromGrade() }} by {{ $record->releasedByTeacher->user->first_name ?? 'a teacher' }} · needs {{ $record->next_grade }}</span>
          </span>
          @if ($matching->isEmpty())
            <span class="note">You have no {{ $record->next_grade }} class this year.</span>
          @elseif ($isActive)
            <button type="button" class="btn small green" data-open="claimDlg" data-fill="claim" data-name="{{ $learner->first_name }} {{ $learner->last_name }}" data-meta="Released from {{ $record->releasedFromGrade() }} · needs {{ $record->next_grade }}" data-action="{{ route('teacher.promotions.claim', $record) }}" data-classes="{{ $matching->map(fn ($c) => ['id' => $c->id, 'label' => $c->name.' · '.$c->grade_level.' · '.$c->section])->values()->toJson() }}">Claim</button>
          @else
            <span class="lock">@include('learner._badge-icon', ['icon' => 'lock-simple', 'class' => 'ico']) Locked</span>
          @endif
        </div>
      @endforeach
    </div>
  @endif
@endif
@endsection

@push('dialogs')
  {{-- Release: one confirmation window, filled from the learner's row. --}}
  <dialog id="releaseDlg" class="win" aria-label="Release a learner">
    <div class="win-in narrow">
      <header class="win-head">
        <div class="win-titles"><h2>Release this learner?</h2><p class="win-meta" data-f-name></p></div>
        <button type="button" class="x" data-close aria-label="Close">@include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])</button>
      </header>
      <form method="POST" action="" style="display:contents" data-busy="Working">
        @csrf
        <div class="win-body"><p style="margin:0">They leave <b data-f-from></b> now and wait to be claimed into a <b data-f-next></b> class by any teacher. Their account, level and history stay exactly as they are.</p></div>
        <footer class="win-foot">
          <button type="submit" class="btn small blue" data-f-go>Release</button>
          <button type="button" class="btn ghost small" data-close>Cancel</button>
        </footer>
      </form>
    </div>
  </dialog>

  {{-- Claim: pick which of your matching classes they go into. --}}
  <dialog id="claimDlg" class="win" aria-label="Claim a learner">
    <div class="win-in narrow">
      <header class="win-head">
        <div class="win-titles"><h2>Claim <span data-f-name></span></h2><p class="win-meta" data-f-meta></p></div>
        <button type="button" class="x" data-close aria-label="Close">@include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])</button>
      </header>
      <form method="POST" action="" style="display:contents" data-busy="Working">
        @csrf
        <div class="win-body">
          <div class="field"><label for="claimSel">Put them in</label><select id="claimSel" name="class_id" required></select><span class="fhint">Their grade updates to match the class.</span></div>
        </div>
        <footer class="win-foot">
          <button type="submit" class="btn small green">Claim into class</button>
          <button type="button" class="btn ghost small" data-close>Cancel</button>
        </footer>
      </form>
    </div>
  </dialog>
@endpush
