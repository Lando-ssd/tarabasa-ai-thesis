{{--
  Practice Games: free play. No points, no streak, no level. Four games end with the child reading out loud: Balloon Pop (a
  word), Word Train (a row of words, or letters for a child still on the letters step), Sentence Stars (a sentence) and Syllable
  Steps (a longer word in parts, then in a sentence). The fifth, Sound Hunt, has no microphone: the child listens and taps to find
  the word that rhymes, starts or ends like another one. Expects $recommendedGame
  (['game' => 'balloon-pop'|'word-train'|'sentence-stars'|'syllable-steps'|'sound-hunt',
  'reason' => 'letters'|'sounds'|'own-words'|'first-words'|'sentences'|'long-words'] or null), $showSyllableSteps (false for a child
  still on the letters step, who has no word level yet), $soundHuntHasClips (Tara's voice clips exist, so the game can make a sound
  without the device's own voice) and $goodForYou (the child is on the Letters or first words step). The recommendation is real
  (Learner::recommendedGameFocus(), from the child's step on the reading path, the words they miss and their main kind of
  mistake) and never a gate: every game stays playable either way. Sound Hunt's card is hidden by a small script on a device that
  has no way to make a sound at all (no clips and no read-aloud voice).
--}}
@extends('layouts.learner-shell')

@section('title', 'Practice Games | TaraBasa AI')
@section('page', 'games')

@php
  $pick = $recommendedGame['game'] ?? null;
  $tips = [
    'letters' => 'Your reading path starts with letters. Word Train has letters on its cars, just for you!',
    'sounds' => 'Your reading path starts with sounds and letters. Sound Hunt helps you hear the sounds in words. No microphone, just listen and tap!',
    'own-words' => 'You have some words that are still tricky. Balloon Pop uses your own tricky words!',
    'first-words' => 'You are reading your first words. Word Train is a great place to practise them!',
    'sentences' => 'You are reading sentences. Try Sentence Stars!',
    'long-words' => 'Longer words are easier in parts. Syllable Steps cuts them up, then puts them back together!',
  ];
  $tip = $tips[$recommendedGame['reason'] ?? ''] ?? null;
@endphp

@section('content')
<div class="subpage" id="page-games">
  <h1 class="pg-title">Practice Games</h1>
  <p class="pg-sub">Free play. No points, just for fun!</p>

  @if ($tip)
    <div class="gm-note gm-tip">{{ $tip }}</div>
  @endif

  <div class="gm-grid">
    <div class="gm-card gm-mint" id="soundHuntCard">
      <div class="gm-head">
        <div class="gm-icon">@include('learner._badge-icon', ['icon' => 'magnifying-glass', 'class' => 'badge-svg'])</div>
        @if ($pick === 'sound-hunt')<div class="gm-ribbon">@include('learner._badge-icon', ['icon' => 'star', 'class' => 'badge-svg']) Recommended</div>@elseif ($goodForYou ?? false)<div class="gm-ribbon new">Good for you</div>@else<div class="gm-ribbon new">New</div>@endif
      </div>
      <h3>Sound Hunt</h3>
      <p>Listen to the sounds in words. Tap the word that matches!</p>
      <div class="gm-meta"><span class="gm-chip">Listen</span><span class="gm-chip">Tap</span><span class="gm-chip">3 levels</span></div>
      <a class="clay-btn orange" href="{{ route('learner.games.sound-hunt') }}">Play</a>
    </div>
    <div class="gm-card gm-sun">
      <div class="gm-head">
        <div class="gm-icon">@include('learner._badge-icon', ['icon' => 'balloon', 'class' => 'badge-svg'])</div>
        @if ($pick === 'balloon-pop')<div class="gm-ribbon">@include('learner._badge-icon', ['icon' => 'star', 'class' => 'badge-svg']) Recommended</div>@endif
      </div>
      <h3>Balloon Pop</h3>
      <p>A balloon shows a word. Say the word out loud to pop it!</p>
      <div class="gm-meta"><span class="gm-chip">Listen</span><span class="gm-chip">Read aloud</span><span class="gm-chip">3 levels</span></div>
      <a class="clay-btn orange" href="{{ route('learner.games.balloon-pop') }}">Play</a>
    </div>
    <div class="gm-card gm-sky">
      <div class="gm-head">
        <div class="gm-icon">@include('learner._badge-icon', ['icon' => 'train', 'class' => 'badge-svg'])</div>
        @if ($pick === 'word-train')<div class="gm-ribbon">@include('learner._badge-icon', ['icon' => 'star', 'class' => 'badge-svg']) Recommended</div>@else<div class="gm-ribbon new">New</div>@endif
      </div>
      <h3>Word Train</h3>
      <p>Read the words on the train cars out loud to make the train roll. Letter Explorers read letters.</p>
      <div class="gm-meta"><span class="gm-chip">Listen</span><span class="gm-chip">Read aloud</span><span class="gm-chip">3 levels</span></div>
      <a class="clay-btn blue" href="{{ route('learner.games.word-train') }}">Play</a>
    </div>
    <div class="gm-card gm-aqua">
      <div class="gm-head">
        <div class="gm-icon">@include('learner._badge-icon', ['icon' => 'star', 'class' => 'badge-svg'])</div>
        @if ($pick === 'sentence-stars')<div class="gm-ribbon">@include('learner._badge-icon', ['icon' => 'star', 'class' => 'badge-svg']) Recommended</div>@else<div class="gm-ribbon new">New</div>@endif
      </div>
      <h3>Sentence Stars</h3>
      <p>Read a sentence out loud to catch a star. Catch three!</p>
      <div class="gm-meta"><span class="gm-chip">Listen</span><span class="gm-chip">Read aloud</span><span class="gm-chip">3 levels</span></div>
      <a class="clay-btn orange" href="{{ route('learner.games.sentence-stars') }}">Play</a>
    </div>
    @if ($showSyllableSteps ?? false)
    <div class="gm-card gm-lilac">
      <div class="gm-head">
        <div class="gm-icon">@include('learner._badge-icon', ['icon' => 'stairs', 'class' => 'badge-svg'])</div>
        @if ($pick === 'syllable-steps')<div class="gm-ribbon">@include('learner._badge-icon', ['icon' => 'star', 'class' => 'badge-svg']) Recommended</div>@else<div class="gm-ribbon new">New</div>@endif
      </div>
      <h3>Syllable Steps</h3>
      <p>Clap the parts of a longer word, tap them together, then read it in a sentence.</p>
      <div class="gm-meta"><span class="gm-chip">Listen</span><span class="gm-chip">Clap and read aloud</span><span class="gm-chip">3 levels</span></div>
      <a class="clay-btn orange" href="{{ route('learner.games.syllable-steps') }}">Play</a>
    </div>
    @endif
  </div>
  <div class="gm-note">In most games you read out loud. What you read comes first from the words you missed in your readings, then from the list for your reading step.</div>
  <div class="gm-note">Games are practice. They never change your points, your streak or your level, and what you read or tap in a game is not graded.</div>
</div>
@endsection

{{-- A pushed script runs after the layout has loaded the read-aloud script (window.tbCanSpeak); one inside the content would run before it. --}}
@push('scripts')
<script>
  /* Sound Hunt needs to make a sound. Hide its card on a device that cannot: no voice clips from Tara and no read-aloud voice. */
  (function () {
    var card = document.getElementById('soundHuntCard');
    var hasClips = {{ ($soundHuntHasClips ?? false) ? 'true' : 'false' }};
    if (card && !hasClips && !window.tbCanSpeak) { card.style.display = 'none'; }
  })();
</script>
@endpush
