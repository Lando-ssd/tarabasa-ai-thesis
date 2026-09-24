{{--
  Practice Games: free play. No points, no streak, no level. Expects $learner,
  $recommendedGame (['game' => 'word-builder'|'letter-match'] or null) and
  $hasCompetencyData. The recommendation is real (Learner::recommendedGameFocus())
  and never a gate: both games stay playable either way.
--}}
@extends('layouts.learner-shell')

@section('title', 'Practice Games — TaraBasa AI')
@section('page', 'games')

@php $pick = $recommendedGame['game'] ?? null; @endphp

@section('content')
<div class="subpage" id="page-games">
  <h1 class="pg-title">Practice Games 🎮</h1>
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
        <div class="gm-icon">🔤</div>
        @if ($pick === 'word-builder')<div class="gm-ribbon">⭐ Recommended</div>@endif
      </div>
      <h3>Word Builder</h3>
      <p>Put the scrambled letters in the right order to spell real words. It even uses words you are practicing.</p>
      <div class="gm-meta"><span class="gm-chip">3 levels</span></div>
      <a class="clay-btn orange" href="{{ route('learner.games.word-builder') }}">Play</a>
    </div>
    <div class="gm-card gm-aqua">
      <div class="gm-head">
        <div class="gm-icon">🧩</div>
        @if ($pick === 'letter-match')<div class="gm-ribbon">⭐ Recommended</div>@endif
      </div>
      <h3>Letter Match</h3>
      <p>A memory match game. Find every capital letter and its small letter twin.</p>
      <div class="gm-meta"><span class="gm-chip">3 levels</span></div>
      <a class="clay-btn blue" href="{{ route('learner.games.letter-match') }}">Play</a>
    </div>
  </div>
  <div class="gm-note">Games never change your points, your streak or your level. Just play and enjoy!</div>
</div>
@endsection
