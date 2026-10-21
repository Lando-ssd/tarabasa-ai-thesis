{{--
  The page and the play loop that Word Train and Sentence Stars share. Both are read-aloud games: the child is shown something
  (a row of words or letters, a sentence), reads it out loud, and the same service that scores readings listens. Free play: no
  points, no streak, no level (see GameController), and what the child says is never graded or kept.

  Each game extends this and fills in:
    @section('title')    the page title
    @section('css')      what is its own (the train, the sentence)
    @section('ask')      the line above the stage, and the Hear button (id="listenBtn")
    @section('stage')    where the thing to read is drawn
    @section('script')   defines window.READ_GAME (see the contract below)
  and passes: $gameKey ('word-train' | 'sentence-stars'), $startLevel (1 to 3).

  Speaking (games/_game-voice): one press of the microphone, one recording, and it stops by itself when the child pauses (or when
  they press the microphone again). The answer is only "heard" or "again", with which words were heard, so Tara never says "wrong":
  if she is not sure she asks once more, then moves on. A microphone that is blocked or missing turns the game into "I read it!",
  so it can always be played.

  A round is three things to read. Reading two or more of them right the first time is a clean round and climbs a level, the same
  simple idea Balloon Pop uses. Up to 3 rounds, or until a round is finished at level 3.

  window.READ_GAME = {
    startLevel,                 // 1 to 3
    unit: 'Train',              // "Train 1 of 3"
    levelIcons: {1:'plant',...} // an icon name per level (the badge sprite)
    micNote, tapNote,           // what the note under the microphone says
    kind(level),                // 'row' | 'letters' | 'sentence'
    makeTargets(level),         // three targets: { text: 'cat dog pig' (lowercase words, spaces), ...anything the page needs }
    show(target, index, total), // draw it
    hear(target),               // read it aloud
    markHeard(matched),         // matched: for each word asked, was it heard? (show the ones that were)
    celebrate(target, firstTry),// the roll or the star; returns how long it takes in ms
  }
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title') | TaraBasa AI</title>
<link rel="stylesheet" href="{{ asset('css/fonts.css') }}?v={{ substr(md5_file(public_path('css/fonts.css')), 0, 10) }}">
@include('learner.games._game-look')
<style>
  .level-badge{ --lv-bg:#dcecfb; --lv-ink:#1c5d9e; --lv-lip:#abcdee; }
  .ask{ display:flex; align-items:center; justify-content:center; gap:14px; flex-wrap:wrap; margin:0 0 10px; font:600 30px/1.15 var(--font-game); color:#1b3a5c; }
  .ask .listen-btn{ margin:0; padding:10px 16px 9px; }
  .mic-zone{ margin:10px 0 0; }
  .mic-zone[hidden]{ display:none; }
  .progress-wrap{ margin-bottom:12px; }
  .say-note.small{ font:500 17px/1.4 'Inter',sans-serif; margin-top:6px; }
  #tapBtn[hidden]{ display:none; }
  @media (max-width:520px){
    .ask{ font-size:26px; gap:10px; }
    .owl-lottie{ width:132px; }
    .star-lottie{ width:120px; height:120px; }
    .mic-btn{ width:84px; height:84px; }
    .mic-btn .badge-svg{ width:40px; height:40px; }
  }
</style>
@yield('css')
</head>
<body>
<div class="wrap">
  <div class="top-bar">
    <span class="level-badge" id="levelBadge"></span>
    <span class="attempt-label" id="attemptLabel">Round 1 of 3</span>
  </div>
  <div class="progress-wrap">
    <div class="progress-label" id="progressLabel"></div>
    <div class="progress-dots" id="progressDots"></div>
  </div>

  <div class="card @yield('card-class')">
    <div id="playArea">
      <div class="owl-lottie" id="gameOwl" role="img" aria-label="Tara the owl, listening"></div>
      <div class="ask">@yield('ask')</div>
      @yield('stage')
      <div class="mic-zone" id="micZone">
        <button type="button" class="mic-btn" id="micBtn" aria-label="Tap, then read it out loud">@include('learner._badge-icon', ['icon' => 'microphone', 'class' => 'badge-svg'])</button>
      </div>
      <button type="button" class="big-btn" id="tapBtn" hidden>I read it!</button>
      <p class="say-note" id="sayNote"></p>
      <p class="say-note small" id="footNote">Tara listens to you read. If she is not sure, she asks you to try once more. There is no wrong answer here.</p>
    </div>

    <div class="celebrate" id="celebrate">
      <div class="star-lottie" id="celebrateStar" role="img" aria-label="A happy star"></div>
      <p class="celebrate-text">Round complete!</p>
      <p class="level-up-text" id="levelUpText" style="display:none;">@include('learner._badge-icon', ['icon' => 'star']) Level up!</p>
    </div>

    <div class="celebrate" id="roundComplete">
      <div class="star-lottie" id="doneStar" role="img" aria-label="A happy star"></div>
      <h1>All done!</h1>
      <p class="round-summary" id="roundCompleteSummary">Nice work!</p>
      <div class="gm-newbadges" id="newBadges"></div>
      <a href="{{ url()->current() }}" class="big-btn">Play Again</a>
    </div>

    <a href="{{ route('learner.games.index') }}" class="back-link" id="backLink">Back to Games</a>
  </div>
</div>

@include('learner.games._game-animations')
@include('learner.games._game-sounds')
@include('learner.games._game-voice')
@include('learner.games._game-finish', ['game' => $gameKey])

@yield('script')

<script>
  (function () {
    var G = window.READ_GAME;
    var MAX_ATTEMPTS = {{ (int) config('practice_games.rounds') }};       // rounds in one sitting (config/practice_games.php)
    var TARGETS = {{ (int) config('practice_games.per_round') }};            // things to read in a round
    var CLEAN_HITS = {{ (int) config('practice_games.clean_hits') }};         // first-try reads that make a round clean and move up a level
    var MAX_TRIES = {{ (int) config('practice_games.tries') }};          // tries for one thing: read it, then once more, then move on

    var level = G.startLevel;
    var attempt = 0;
    var leveledUpDuringSession = false;
    var topLevelCleared = false;
    var hadPerfectRound = false;

    var targets = [];
    var pos = 0;
    var tries = 0;
    var firstTryHits = 0;
    var userActive = false;     // the child has tapped something, so the browser lets it be spoken on its own
    var tapMode = !window.tarabasaCanListen;   // no microphone: the child says "I read it!" instead
    var busy = false;
    var listening = null;       // the controller of a recording that is running

    function $(id) { return document.getElementById(id); }
    var levelBadge = $('levelBadge'), attemptLabel = $('attemptLabel'), progressLabel = $('progressLabel'), progressDots = $('progressDots');
    var micZone = $('micZone'), micBtn = $('micBtn'), tapBtn = $('tapBtn'), sayNote = $('sayNote'), footNote = $('footNote');
    var playArea = $('playArea'), celebrate = $('celebrate'), levelUpText = $('levelUpText'), roundComplete = $('roundComplete');
    var roundCompleteSummary = $('roundCompleteSummary'), backLink = $('backLink'), listenBtn = $('listenBtn');

    function current() { return targets[pos]; }

    function setLevelBadge() {
      levelBadge.innerHTML = window.tarabasaBadgeSvg((G.levelIcons || {})[level] || 'plant') + '<span>Level ' + level + '</span>';
    }
    function updateTopBar() {
      setLevelBadge();
      attemptLabel.textContent = 'Round ' + (attempt + 1) + ' of ' + MAX_ATTEMPTS;
    }
    function renderDots() {
      progressDots.innerHTML = '';
      for (var i = 0; i < TARGETS; i++) {
        var dot = document.createElement('div');
        dot.className = 'pdot' + (i < pos ? ' done' : (i === pos ? ' current' : ''));
        progressDots.appendChild(dot);
      }
    }
    function nudgeOwl() {
      var owl = $('gameOwl');
      owl.classList.remove('is-encouraging');
      void owl.offsetWidth;
      owl.classList.add('is-encouraging');
    }
    function applyMode() {
      micZone.hidden = tapMode;
      tapBtn.hidden = !tapMode;
      footNote.hidden = tapMode;
      sayNote.textContent = tapMode ? G.tapNote : G.micNote;
    }

    function startRound() {
      targets = G.makeTargets(level);
      pos = 0;
      firstTryHits = 0;
      updateTopBar();
      startTarget();
    }

    function startTarget() {
      tries = 0;
      busy = false;
      var target = current();
      G.show(target, pos, TARGETS);
      progressLabel.textContent = G.unit + ' ' + (pos + 1) + ' of ' + TARGETS;
      renderDots();
      applyMode();
      micBtn.disabled = false;
      micBtn.classList.remove('listening');
      // After the first tap the browser lets it be said on its own; the first one waits for Hear.
      if (userActive) G.hear(target);
    }

    // The thing was read (or the child said so): celebrate, then the next one.
    function finishTarget(firstTry) {
      busy = true;
      if (firstTry) firstTryHits++;
      window.tarabasaPlaySfx('correct', 0.55);
      var ms = G.celebrate(current(), firstTry) || 900;
      pos++;
      setTimeout(function () {
        if (pos >= TARGETS) finishAttempt(); else startTarget();
      }, ms);
    }

    micBtn.addEventListener('click', function () {
      if (listening) { listening.stop(); return; }   // pressed again while reading: that is "I am done"
      if (busy) return;
      userActive = true;
      busy = true;
      window.tbStopSpeaking();
      var target = current();
      window.tarabasaListenForText(target.text, G.kind(level), {
        onListening: function (control) {
          listening = control;
          micBtn.classList.add('listening');
          sayNote.textContent = 'Listening... press the microphone again when you finish.';
        },
        onChecking: function () {
          listening = null;
          micBtn.classList.remove('listening');
          micBtn.disabled = true;
          sayNote.textContent = 'Tara is checking...';
        },
        onDone: function (status, matched) {
          listening = null;
          micBtn.classList.remove('listening');
          if (status === 'unavailable') {
            // No microphone after all (blocked, missing, or the service cannot be reached): "I read it!".
            tapMode = true;
            busy = false;
            applyMode();
            return;
          }
          if (status === 'heard') {
            G.markHeard(matched.length ? matched : null);
            finishTarget(tries === 0);
            return;
          }
          // Not sure what was said: show what went well, ask once more, then move on. There is no wrong answer here.
          var some = matched && matched.some(function (m) { return m; });
          if (some) G.markHeard(matched);
          tries++;
          if (tries >= MAX_TRIES) {
            sayNote.textContent = 'Good try! On we go.';
            setTimeout(function () { finishTarget(false); }, 900);
            return;
          }
          nudgeOwl();
          sayNote.textContent = some ? 'Tara heard some of it. Try once more!' : 'Tara is not sure. Try once more!';
          busy = false;
          micBtn.disabled = false;
        }
      });
    });

    tapBtn.addEventListener('click', function () {
      if (busy) return;
      userActive = true;
      finishTarget(tries === 0);
    });

    listenBtn.addEventListener('click', function () { userActive = true; G.hear(current()); });
    listenBtn.hidden = !window.tbCanSpeak;

    function finishAttempt() {
      var wasAtCeiling = level >= 3;
      if (wasAtCeiling) topLevelCleared = true;
      if (firstTryHits === TARGETS) hadPerfectRound = true;
      var leveledUp = firstTryHits >= CLEAN_HITS && level < 3;
      if (leveledUp) {
        level++;
        leveledUpDuringSession = true;
      }
      attempt++;

      // A set number of rounds, or straight away once a round is finished at the top level, so a child who starts
      // there plays one round, not three.
      if (attempt >= MAX_ATTEMPTS || wasAtCeiling) finishSession();
      else {
        updateTopBar();
        showRoundThenContinue(leveledUp);
      }
    }

    function showRoundThenContinue(leveledUp) {
      playArea.style.display = 'none';
      levelUpText.style.display = leveledUp ? 'block' : 'none';
      window.tarabasaPlaySfx('levelComplete', 0.6);
      celebrate.classList.add('show');
      window.tarabasaAnimate($('celebrateStar'), 'star-happy');

      setTimeout(function () {
        celebrate.classList.remove('show');
        playArea.style.display = '';
        startRound();
      }, 1700);
    }

    function finishSession() {
      setLevelBadge();
      attemptLabel.textContent = 'Complete!';
      playArea.style.display = 'none';
      window.tarabasaPlaySfx('levelComplete', 0.6);
      window.tarabasaAnimate($('doneStar'), 'star-happy');
      roundCompleteSummary.textContent = 'You finished at Level ' + level + (leveledUpDuringSession ? '. Great climbing!' : '. Nice work!');
      roundComplete.classList.add('show');
      backLink.style.display = 'none';

      window.tarabasaReportGame({
        level_reached: level,
        top_level_cleared: topLevelCleared,
        had_perfect_round: hadPerfectRound,
        rounds: Math.max(1, Math.min(3, attempt))
      }, $('newBadges'));
    }

    // Tara's head looks around at the top of the game.
    window.tarabasaAnimate($('gameOwl'), 'tara-owl-head');

    startRound();
  })();
</script>
</body>
</html>
