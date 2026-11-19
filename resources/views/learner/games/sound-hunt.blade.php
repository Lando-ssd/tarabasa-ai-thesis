{{--
  Sound Hunt: a Practice Game where the child listens and taps. No microphone (config/sound_hunt.php, App\Support\SoundHunt). Free play like
  the other games: no points, no streak, no level, and nothing the child taps is graded or kept.

  A turn: Tara asks "Which one rhymes with cat?" (level 1), "starts like" (level 2) or "ends like" (level 3). Three cards each say their
  word when tapped; the child picks one and confirms with "That one!". A right answer turns the card green and shows what the words share.
  A first miss is never "wrong": Tara says "Let's listen again". After a second miss she says the answer and moves on.

  A child on the Letters step (no written word yet) only HEARS the cards, which have a colour each; every other child hears and reads them.
  After an answer the written words always appear, with the part that matches picked out, so the sound is linked to the letters.

  Rounds work as in the other games (config/practice_games.php): three questions a round, two or more right the first time climb a level, up to
  three rounds or one round at level 3.

  Every word is a whole word: a voice can say "sun" but not the single sound /s/. Tara's voice is a set of clips made once and shipped with the game
  (public/audio/sound-hunt, tools/make-voice-clips.py). A line is played from clips only when every clip it needs exists; otherwise the device's
  own read-aloud voice reads the whole line, so a line never mixes two voices. The browser only lets a page make a sound after the child has tapped
  something, so the very first question waits for the glowing Listen button.

  Expects $levels (PracticeGameService::soundHunt: three lists of questions), $startLevel (1 to 3), $readsWords (show the written words),
  $clips (clip key => address, may be empty) and $levelNames.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Sound Hunt | TaraBasa AI</title>
<link rel="stylesheet" href="{{ asset('css/fonts.css') }}?v={{ substr(md5_file(public_path('css/fonts.css')), 0, 10) }}">
@include('learner.games._game-look')
<style>
  .progress-wrap{ margin-bottom:10px; }
  .owl-lottie{ width:132px; margin-bottom:0; }
  h1{ font-size:29px; margin:2px 0 10px; text-wrap:balance; }

  /* the word Tara asks about: a pill that says it again */
  .target{ display:inline-flex; align-items:center; gap:10px; margin:0 auto 14px; padding:8px 20px 8px 10px; border-radius:999px; background:#fff; border:2px solid var(--panel-line);
    box-shadow:0 4px 0 var(--lip); cursor:pointer; font:700 32px/1 var(--font-game); color:#0f4f8f; transition:transform .08s ease, box-shadow .08s ease; }
  .target:active{ transform:translateY(3px); box-shadow:0 1px 0 var(--lip); }
  .target.only-ear{ font-size:22px; letter-spacing:.06em; text-transform:uppercase; }
  .target.only-ear.show{ font-size:32px; letter-spacing:0; text-transform:none; }   /* once the word is shown, it is a word again, not a label */
  .target:focus-visible, .opt:focus-visible{ outline:4px solid var(--blue-500); outline-offset:4px; }
  .spk{ width:40px; height:40px; border-radius:50%; background:linear-gradient(180deg,#f9a544,#ee8a26); display:grid; place-items:center; box-shadow:0 3px 0 #b4560b, inset 0 2px 0 rgba(255,255,255,.45); flex:none; color:#fff; }
  .spk .badge-svg{ width:23px; height:23px; display:block; }
  .target .hl, .opt.ok .hl{ color:#d2561f; }
  .target.show .hl{ text-decoration:underline; text-decoration-thickness:4px; text-underline-offset:5px; }

  /* the glow on whatever the child should tap next (with reduced motion it is a still outline instead) */
  .cue{ animation:cueGlow 1.5s ease-in-out infinite; }
  @keyframes cueGlow{ 0%,100%{ filter:drop-shadow(0 0 0 rgba(249,165,68,0)); } 50%{ filter:drop-shadow(0 0 12px rgba(249,165,68,.95)); } }
  @media (prefers-reduced-motion:reduce){ .cue{ outline:4px solid #f6b867; outline-offset:4px; } }

  /* the three cards */
  .opts{ display:grid; grid-template-columns:1fr; gap:12px; margin:2px 0 14px; }
  .opt{ appearance:none; width:100%; min-height:86px; border:none; border-radius:22px; cursor:pointer; display:flex; align-items:center; gap:14px; padding:12px 18px; text-align:left;
    background:linear-gradient(180deg,var(--t),var(--b)); box-shadow:0 7px 0 var(--lp), inset 0 2px 0 rgba(255,255,255,.65); color:var(--ink2); transition:transform .08s ease, box-shadow .08s ease, opacity .2s; }
  .opt:hover{ transform:translateY(-1px); }
  .opt:active{ transform:translateY(4px); box-shadow:0 3px 0 var(--lp), inset 0 2px 0 rgba(255,255,255,.65); }
  .opt.c1{ --t:#fff3c8; --b:#ffdf8a; --lp:#d9a93f; --ink2:#6b4700; }
  .opt.c2{ --t:#d6f6ea; --b:#a6e3c9; --lp:#48a983; --ink2:#0d5a45; }
  .opt.c3{ --t:#efe6ff; --b:#d2c0f5; --lp:#a98cdc; --ink2:#42237b; }
  .opt .spk{ width:48px; height:48px; } .opt .spk .badge-svg{ width:28px; height:28px; }
  .opt .w{ font:700 40px/1 var(--font-game); letter-spacing:.02em; }
  .opt .ear{ font:700 23px/1 var(--font-game); letter-spacing:.06em; text-transform:uppercase; }
  .opt.sel{ outline:4px solid var(--blue-500); outline-offset:3px; }
  .opt.ok{ --t:#d9f6e6; --b:#8fdcb4; --lp:#2f9a6b; --ink2:#0b5a3a; outline:none; }
  .opt.ok .hl{ color:#0a6a3f; text-decoration:underline; text-decoration-thickness:4px; text-underline-offset:5px; }
  .opt.dim{ opacity:.42; pointer-events:none; }
  .opt.miss{ opacity:.45; pointer-events:none; filter:saturate(.5); }

  .say{ min-height:58px; margin:0 0 12px; font:600 23px/1.25 var(--font-game); color:#3f5568; text-wrap:balance; }
  .say b{ color:var(--success); }
  .big-btn:disabled{ background:#d9e3ee; color:#8a9aaa; text-shadow:none; box-shadow:0 6px 0 #bccbdb; cursor:default; transform:none; }
  .big-btn[hidden]{ display:none; }
  #stage{ min-height:0; }
  .nosound{ display:none; }
  .nosound.show{ display:block; }
  .h2{ font:700 30px/1.15 var(--font-game); color:#2f455b; margin:6px 0 8px; }

  @media (max-width:520px){
    .owl-lottie{ width:112px; }
    .star-lottie{ width:120px; height:120px; }
    h1{ font-size:26px; }
    .opt{ min-height:80px; padding:10px 14px; } .opt .w{ font-size:36px; }
  }
  /* A short phone: the cards and the confirm button have to stay on the screen, so Tara's picture steps aside. */
  @media (max-height:700px){
    body{ padding-top:12px; padding-bottom:24px; }
    .top-bar{ margin-bottom:8px; } .progress-label{ margin-bottom:6px; }
    .card{ padding-top:14px; padding-bottom:16px; }
    .owl-lottie{ display:none; }
    h1{ font-size:24px; margin-bottom:6px; }
    .target{ margin-bottom:8px; padding:6px 16px 6px 8px; }
    .opts{ gap:9px; margin-bottom:8px; } .opt{ min-height:66px; padding:8px 14px; box-shadow:0 5px 0 var(--lp), inset 0 2px 0 rgba(255,255,255,.65); }
    .say{ min-height:44px; margin-bottom:8px; font-size:20px; }
    .big-btn{ padding:14px 20px 12px; }
  }
</style>
</head>
<body>
<div class="wrap">
  <div class="top-bar" id="topBar">
    <span class="level-badge" id="levelBadge"></span>
    <span class="attempt-label" id="attemptLabel">Round 1 of {{ (int) config('practice_games.rounds') }}</span>
  </div>
  <div class="progress-wrap" id="progressWrap">
    <div class="progress-label" id="progressLabel"></div>
    <div class="progress-dots" id="progressDots"></div>
  </div>

  <div class="card">
    <div id="playArea">
      <div class="owl-lottie" id="gameOwl" role="img" aria-label="Tara the owl, listening"></div>
      <h1 id="askText"></h1>
      <div id="targetBox"></div>

      <div id="stage"><div class="opts" id="opts" role="group" aria-label="The three words"></div></div>

      <p class="say" id="sayNote" aria-live="polite"></p>
      <button type="button" class="big-btn" id="confirmBtn" disabled>That one!</button>
      <button type="button" class="big-btn" id="nextBtn" hidden>Next</button>
    </div>

    <div class="nosound" id="noSound">
      <div class="owl-lottie" id="noSoundOwl" role="img" aria-label="Tara the owl"></div>
      <div class="h2">I need my voice</div>
      <p class="round-summary">This game needs sound. Ask a grown up to check the volume and the voice settings, then try again.</p>
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
@include('partials.speak')
@include('learner.games._game-finish', ['game' => 'sound-hunt'])

<script>
  (function () {
    var LEVELS = @json($levels);
    var NAMES = @json($levelNames);
    var CLIPS = @json($clips);
    var READS = @json((bool) $readsWords);
    var MAX_ATTEMPTS = {{ (int) config('practice_games.rounds') }};     // rounds in one sitting (config/practice_games.php)
    var QUESTIONS = {{ (int) config('practice_games.per_round') }};        // questions in a round
    var CLEAN_HITS = {{ (int) config('practice_games.clean_hits') }};     // questions right the first time that make a round clean and move up a level
    var MAX_TRIES = {{ (int) config('practice_games.tries') }};            // tries for one question: pick, pick again, then Tara says the answer

    var level = {{ (int) $startLevel }};
    var attempt = 0;
    var leveledUpDuringSession = false, topLevelCleared = false, hadPerfectRound = false;
    var taken = { 1: 0, 2: 0, 3: 0 };       // how far into each level's list we are, so no question comes twice in a sitting

    var round = [], pos = 0, firstTryHits = 0;
    var sel = null, tries = 0, missed = [], phase = 'ask';   // phase: 'ask' while choosing, 'done' once the question is finished
    var userActive = false;                  // the child has tapped something, so the browser lets Tara speak on her own
    var queueToken = 0, audio = null;
    var haveClips = Object.keys(CLIPS).length > 0;

    function $(id) { return document.getElementById(id); }
    function esc(t) { return String(t).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    function cap(w) { return w.charAt(0).toUpperCase() + w.slice(1); }
    function current() { return round[pos]; }

    // ----- Tara's voice: the clips when every one a line needs exists, otherwise the device's own voice for the whole line
    function stopAll() {
      queueToken++;
      try { if (audio) { audio.pause(); audio = null; } } catch (e) {}
      try { if (window.tbStopSpeaking) window.tbStopSpeaking(); } catch (e) {}
    }
    function playLine(line, then) {
      stopAll();
      var token = queueToken;
      function fallback() {
        if (token !== queueToken) return;
        if (window.tbCanSpeak) window.tbSpeak(line.text, { rate: 0.8, onend: then });
        else if (then) then();
      }
      if (haveClips && line.parts.every(function (k) { return CLIPS[k]; })) {
        var i = 0;
        (function next() {
          if (token !== queueToken) return;
          if (i >= line.parts.length) { if (then) then(); return; }
          audio = new Audio(CLIPS[line.parts[i++]]);
          audio.onended = function () { setTimeout(next, 90); };
          audio.onerror = fallback;
          var p = audio.play();
          if (p && p.catch) p.catch(fallback);
        })();
        return;
      }
      fallback();
    }
    function say(word) { playLine({ parts: ['w-' + word], text: word }); }

    // ----- the top of the page
    function setLevelBadge() {
      $('levelBadge').innerHTML = window.tarabasaBadgeSvg({ 1: 'plant', 2: 'leaf', 3: 'tree' }[level] || 'plant') + '<span>Level ' + level + '</span>';
    }
    function updateTopBar() {
      setLevelBadge();
      $('attemptLabel').textContent = 'Round ' + (attempt + 1) + ' of ' + MAX_ATTEMPTS + ' · ' + NAMES[level];
    }
    function renderDots() {
      var box = $('progressDots'); box.innerHTML = '';
      for (var i = 0; i < QUESTIONS; i++) {
        var d = document.createElement('div');
        d.className = 'pdot' + (i < pos ? ' done' : (i === pos ? ' current' : ''));
        box.appendChild(d);
      }
    }
    function nudgeOwl() {
      var owl = $('gameOwl'); owl.classList.remove('is-encouraging'); void owl.offsetWidth; owl.classList.add('is-encouraging');
    }

    // ----- one question
    function takeRound() {
      var list = LEVELS[level] || [];
      var out = [];
      for (var i = 0; i < QUESTIONS && list.length; i++) { out.push(list[taken[level] % list.length]); taken[level]++; }
      return out;
    }
    function startRound() {
      round = takeRound(); pos = 0; firstTryHits = 0;
      updateTopBar();
      if (!round.length) { finishSession(); return; }
      startQuestion();
    }
    function startQuestion() {
      sel = null; tries = 0; missed = []; phase = 'ask';
      $('progressLabel').textContent = 'Question ' + (pos + 1) + ' of ' + QUESTIONS;
      renderDots();
      render('');
      // Tara asks on her own once the child has tapped something; only if this question is still the one on the screen a moment later.
      var asked = current();
      if (userActive) setTimeout(function () { if (current() === asked && phase === 'ask') playLine(asked.say.ask); }, 350);
    }

    function marked(word, q, plain) {
      var w = esc(word);
      if (plain) return w;
      if (q.kind === 'rhyme') return w.slice(0, w.length - q.mark) + '<span class="hl">' + w.slice(w.length - q.mark) + '</span>';
      if (q.kind === 'first') return '<span class="hl">' + w.charAt(0) + '</span>' + w.slice(1);
      return w.slice(0, -1) + '<span class="hl">' + w.slice(-1) + '</span>';
    }

    function render(note) {
      var q = current(), done = phase === 'done', words = READS || done;
      // A child who reads sees the word Tara asks about; a child on the Letters step hears it (the Listen pill) and sees a short question.
      var askRead = { rhyme: 'Which one rhymes with', first: 'Which one starts like', last: 'Which one ends like' }[q.kind];
      var askHear = { rhyme: 'Which word rhymes?', first: 'Which word starts the same?', last: 'Which word ends the same?' }[q.kind];
      $('askText').textContent = READS ? askRead + ' ' + q.target + '?' : askHear;

      var cue = !userActive ? ' cue' : '';
      var tgt = READS
        ? '<div class="target' + (done ? ' show' : '') + cue + '" id="targetPill" role="button" tabindex="0" aria-label="Hear the question again"><span class="spk">' + window.tarabasaBadgeSvg('speaker-high') + '</span>' + marked(q.target, q, !done) + '</div>'
        : '<div class="target only-ear' + (done ? ' show' : '') + cue + '" id="targetPill" role="button" tabindex="0" aria-label="Hear the question again"><span class="spk">' + window.tarabasaBadgeSvg('speaker-high') + '</span>' + (done ? marked(q.target, q, false) : 'Listen') + '</div>';
      $('targetBox').innerHTML = tgt;

      var h = '';
      q.choices.forEach(function (w, i) {
        var cls = 'opt c' + (i + 1);
        if (done) cls += (w === q.answer ? ' ok' : ' dim');
        else if (missed.indexOf(w) > -1) cls += ' miss';
        else if (sel === w) cls += ' sel';
        var inner = '<span class="spk">' + window.tarabasaBadgeSvg('speaker-high') + '</span>' +
          (words ? '<span class="w">' + marked(w, q, !(done && w === q.answer)) + '</span>' : '<span class="ear">Word ' + (i + 1) + '</span>');
        h += '<button type="button" class="' + cls + '" data-w="' + esc(w) + '" aria-pressed="' + (sel === w ? 'true' : 'false') + '" aria-label="' + (words ? esc(w) : 'Word ' + (i + 1)) + '">' + inner + '</button>';
      });
      $('opts').innerHTML = h;

      $('sayNote').innerHTML = note || (phase === 'ask' ? 'Tap a card to hear it.' : '');
      var confirmBtn = $('confirmBtn'), nextBtn = $('nextBtn');
      confirmBtn.hidden = done; nextBtn.hidden = !done;
      confirmBtn.disabled = !sel;
      nextBtn.textContent = pos >= QUESTIONS - 1 ? 'Finish round' : 'Next';
      // The glow: the Listen pill until the child has tapped something, then the button that comes next.
      confirmBtn.classList.remove('cue'); nextBtn.classList.remove('cue');
      if (userActive) { if (done) nextBtn.classList.add('cue'); else if (sel) confirmBtn.classList.add('cue'); }
    }

    // Every tap counts as the child having tapped something (so Tara may speak on her own from now on).
    $('playArea').addEventListener('click', function () { userActive = true; }, true);

    $('targetBox').addEventListener('click', function (e) {
      if (!e.target.closest('#targetPill')) return;
      playLine(current().say.ask);
      render($('sayNote').innerHTML);
    });
    $('targetBox').addEventListener('keydown', function (e) {
      if ((e.key === 'Enter' || e.key === ' ') && e.target.closest('#targetPill')) { e.preventDefault(); e.target.click(); }
    });

    $('opts').addEventListener('click', function (e) {
      var b = e.target.closest('.opt'); if (!b || phase !== 'ask') return;
      sel = b.getAttribute('data-w');
      say(sel);
      render($('sayNote').innerHTML);
    });

    $('confirmBtn').addEventListener('click', function () {
      if (!sel || phase !== 'ask') return;
      var q = current();
      if (sel === q.answer) {
        phase = 'done';
        if (tries === 0) firstTryHits++;
        window.tarabasaPlaySfx('correct', 0.55);
        render('<b>' + esc(q.screen.right) + '</b>');
        playLine(q.say.right);
        return;
      }
      tries++; missed.push(sel); sel = null; nudgeOwl();
      if (tries >= MAX_TRIES) {
        phase = 'done';
        render(esc(q.screen.reveal));
        playLine(q.say.reveal);
        return;
      }
      render(esc(q.screen.again));
      playLine(q.say.again);
    });

    $('nextBtn').addEventListener('click', function () {
      stopAll();
      pos++;
      if (pos >= QUESTIONS) finishAttempt(); else startQuestion();
    });

    // ----- rounds and the end, the same as the other games
    function finishAttempt() {
      stopAll();
      var wasAtCeiling = level >= 3;
      if (wasAtCeiling) topLevelCleared = true;
      if (firstTryHits === QUESTIONS) hadPerfectRound = true;
      var leveledUp = firstTryHits >= CLEAN_HITS && level < 3;
      if (leveledUp) { level++; leveledUpDuringSession = true; }
      attempt++;

      // A set number of rounds, or straight away once a round is finished at the top level, so a child who starts there plays one round, not three.
      if (attempt >= MAX_ATTEMPTS || wasAtCeiling) finishSession();
      else { updateTopBar(); showRoundThenContinue(leveledUp); }
    }

    function showRoundThenContinue(leveledUp) {
      $('playArea').style.display = 'none';
      $('progressWrap').style.display = 'none';
      $('levelUpText').style.display = leveledUp ? 'block' : 'none';
      window.tarabasaPlaySfx('levelComplete', 0.6);
      $('celebrate').classList.add('show');
      window.tarabasaAnimate($('celebrateStar'), 'star-happy');
      setTimeout(function () {
        $('celebrate').classList.remove('show');
        $('playArea').style.display = '';
        $('progressWrap').style.display = '';
        startRound();
      }, 1700);
    }

    function finishSession() {
      setLevelBadge();
      $('attemptLabel').textContent = 'Complete!';
      $('playArea').style.display = 'none';
      $('progressWrap').style.display = 'none';
      window.tarabasaPlaySfx('levelComplete', 0.6);
      window.tarabasaAnimate($('doneStar'), 'star-happy');
      $('roundCompleteSummary').textContent = 'You finished at Level ' + level + (leveledUpDuringSession ? '. Great climbing!' : '. Nice work!');
      $('roundComplete').classList.add('show');
      $('backLink').style.display = 'none';

      window.tarabasaReportGame({
        level_reached: level,
        top_level_cleared: topLevelCleared,
        had_perfect_round: hadPerfectRound,
        rounds: Math.max(1, Math.min(3, attempt))
      }, $('newBadges'));
    }

    // Tara's head looks around at the top of the game.
    window.tarabasaAnimate($('gameOwl'), 'tara-owl-head');

    // A device with no clips and no read-aloud voice cannot play this game: say so kindly instead of showing cards that make no sound.
    if (!haveClips && !window.tbCanSpeak) {
      $('playArea').style.display = 'none';
      $('progressWrap').style.display = 'none';
      $('topBar').style.display = 'none';
      $('noSound').classList.add('show');
      window.tarabasaAnimate($('noSoundOwl'), 'tara-owl-head');
    } else {
      startRound();
    }
  })();
</script>
</body>
</html>
