{{--
  Practice Games: free play. No points, no streak, no level. Three games (Word Builder,
  Letter Match and the speaking game Balloon Pop). Expects $learner,
  $recommendedGame (['game' => 'word-builder'|'letter-match'] or null) and
  $hasCompetencyData. The recommendation is real (Learner::recommendedGameFocus())
  and never a gate: both games stay playable either way.
--}}
@extends('layouts.learner-shell')

@section('title', 'Practice Games | TaraBasa AI')
@section('page', 'games')

@php $pick = $recommendedGame['game'] ?? null; @endphp

@section('content')
<div class="subpage" id="page-games">
  <h1 class="pg-title">Practice Games</h1>
  <p class="pg-sub">Free play. No points, just for fun!</p>

  @if ($pick === 'word-builder')
    <div class="gm-note gm-tip">Your reading shows spelling practice would help right now. Word Builder uses your own tricky words!</div>
  @elseif ($pick === 'letter-match')
    <div class="gm-note gm-tip">Your reading shows letter practice would help right now. Try Letter Match!</div>
  @elseif ($hasCompetencyData)
    <div class="gm-note gm-tip">Your word skills are looking strong. Keep sharpening them here anytime you want!</div>
  @endif

  <div class="gm-grid">
    <div class="gm-card gm-sky">
      <div class="gm-head">
        <div class="gm-icon">@include('learner._badge-icon', ['icon' => 'text-aa', 'class' => 'badge-svg'])</div>
        @if ($pick === 'word-builder')<div class="gm-ribbon">@include('learner._badge-icon', ['icon' => 'star', 'class' => 'badge-svg']) Recommended</div>@endif
      </div>
      <h3>Word Builder</h3>
      <p>Put the letters in order to spell a word. Now you can hear the word first, and say it when you finish.</p>
      <div class="gm-meta"><span class="gm-chip">Listen</span><span class="gm-chip">Speak</span><span class="gm-chip">3 levels</span></div>
      <a class="clay-btn orange" href="{{ route('learner.games.word-builder') }}">Play</a>
    </div>
    <div class="gm-card gm-aqua">
      <div class="gm-head">
        <div class="gm-icon">@include('learner._badge-icon', ['icon' => 'puzzle-piece', 'class' => 'badge-svg'])</div>
        @if ($pick === 'letter-match')<div class="gm-ribbon">@include('learner._badge-icon', ['icon' => 'star', 'class' => 'badge-svg']) Recommended</div>@endif
      </div>
      <h3>Letter Match</h3>
      <p>Hear a letter, then find it and its small twin.</p>
      <div class="gm-meta"><span class="gm-chip">Listen</span><span class="gm-chip">3 levels</span></div>
      <a class="clay-btn blue" href="{{ route('learner.games.letter-match') }}">Play</a>
    </div>
    <div class="gm-card gm-sun">
      <div class="gm-head">
        <div class="gm-icon">@include('learner._badge-icon', ['icon' => 'balloon', 'class' => 'badge-svg'])</div>
        <div class="gm-ribbon new">New</div>
      </div>
      <h3>Balloon Pop</h3>
      <p>A balloon shows a word. Say the word out loud to pop it!</p>
      <div class="gm-meta"><span class="gm-chip">Listen</span><span class="gm-chip">Speak</span><span class="gm-chip">3 levels</span></div>
      <a class="clay-btn orange" href="{{ route('learner.games.balloon-pop') }}">Play</a>
    </div>
  </div>
  <div class="gm-note">Where the words come from: words you missed in your readings, then the word list for your grade.</div>
  <div class="gm-note">Games are practice. They never change your points, your streak or your level, and what you say in a game is not graded.</div>
</div>
@endsection
