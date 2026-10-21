{{--
  Syllable Steps: the syllable method (config/syllable_method.php, App\Support\SyllableMethod) as a Practice Game. A longer word is
  taught in four steps, in the order of the method (hear and echo, split, join, read in a sentence). It is free play like the other
  games: no points, no streak, no level, and what the child says is never graded or kept.

    1. Listen    tap a part, hear Tara say it, four dots fill (the method's drill: repetition for automaticity)
    2. Split     tap the clap once for each part (RL1PA-I-2, a listening skill: it needs no letters)
    3. Join      tap the first part, then the next, in order; the tiles glide together by themselves and Tara says the word
                 (RL1PWS-I-5, blending the parts back into the word)
    4. Read      read a short sentence with the word in it: the only step that uses the microphone

  Only step 4 is ever scored. The parts (pic, nic, bas, ket) are not words, and the speech checker is unreliable on isolated
  non-words, so a part is never sent to it: steps 1 to 3 are listening, tapping and joining, and the checker hears a whole word
  inside a sentence. The answer is only "heard" or "again", never "wrong", the same as every other game.

  Built from what is known about children this age (see CLAUDE.md, "Syllable Steps"): one finger taps instead of dragging or
  sliding, because small children manage a tap far more reliably; Tara SAYS every instruction (the line of text is only a backup,
  and a speaker button says it again); the next thing to tap glows; every tap answers at once. The browser only lets a page speak
  after the child has tapped something, so the very first instruction waits for the speaker button, which glows.

  Rounds work as in the other games: three words a round, two or more read right the first time (step 4) climb a level, up to three
  rounds or one round at level 3. The three levels are the method's word levels. A child on the Letters step never sees this page
  (GameController::syllableSteps).

  Expects $levels (PracticeGameService::syllableLevels), $startLevel (1 to 3) and $repeat (the drill count, 4).
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Syllable Steps | TaraBasa AI</title>
<link rel="stylesheet" href="{{ asset('css/fonts.css') }}?v={{ substr(md5_file(public_path('css/fonts.css')), 0, 10) }}">
@include('learner.games._game-look')
<style>
  .level-badge{ --lv-bg:#e8defa; --lv-ink:#4b2a8c; --lv-lip:#c3a9ee; }
  .progress-wrap{ margin-bottom:10px; }
  .owl-lottie{ width:124px; margin-bottom:0; }
  h1{ font-size:30px; margin:0 0 10px; }

  /* the four steps */
  .stepbar{ list-style:none; margin:0 0 14px; padding:0; display:grid; grid-template-columns:repeat(4,1fr); gap:6px; }
  .stepbar li{ text-align:center; }
  .stepbar i{ display:grid; place-items:center; width:34px; height:34px; margin:0 auto 5px; border-radius:50%; background:var(--line); color:#fff; font:700 18px/1 var(--font-game); font-style:normal; }
  .stepbar i .badge-svg{ width:19px; height:19px; display:block; }
  .stepbar span{ display:block; font:600 14px/1 var(--font-game); letter-spacing:.06em; text-transform:uppercase; color:var(--slate-600); }
  .stepbar small{ display:block; margin-top:3px; font:600 11.5px/1 var(--font-game); letter-spacing:.06em; text-transform:uppercase; color:#1f7a63; }
  .stepbar li.done i{ background:var(--teal); }
  .stepbar li.cur i{ background:var(--owl-orange-500); transform:scale(1.15); box-shadow:0 3px 0 #b4560b; }
  .stepbar li.cur span{ color:var(--owl-orange-600); }

  /* what Tara says: one short line, a speaker to hear it again */
  .instr{ display:flex; align-items:center; justify-content:center; gap:12px; margin:0 auto 10px; max-width:460px; }
  .instr span{ font:600 24px/1.25 var(--font-game); color:#2f455b; text-align:left; min-height:1.25em; }
  .spk{ flex:none; width:56px; height:56px; border-radius:50%; border:2px solid var(--panel-line); background:#fff; color:var(--blue-500); display:grid; place-items:center; cursor:pointer; box-shadow:0 4px 0 var(--lip); transition:transform .08s ease, box-shadow .08s ease; }
  .spk .badge-svg{ width:30px; height:30px; display:block; }
  .spk:active{ transform:translateY(3px); box-shadow:0 1px 0 var(--lip); }
  .spk[hidden]{ display:none; }
  .spk:focus-visible, .part:focus-visible, .jt:focus-visible, .clap-btn:focus-visible{ outline:4px solid var(--blue-500); outline-offset:4px; }

  /* the glow on whatever the child should tap next (with reduced motion it is a still outline instead) */
  .cue{ animation:cueGlow 1.4s ease-in-out infinite; }
  @keyframes cueGlow{ 0%,100%{ filter:drop-shadow(0 0 0 rgba(249,165,68,0)); } 50%{ filter:drop-shadow(0 0 12px rgba(249,165,68,.95)); } }
  @media (prefers-reduced-motion:reduce){ .cue{ outline:4px solid #f6b867; outline-offset:4px; } }

  #stage{ min-height:176px; }
  .big-btn:disabled{ background:#d9e3ee; color:#8a9aaa; text-shadow:none; box-shadow:0 6px 0 #bccbdb; cursor:default; transform:none; }
  .big-btn[hidden], #micZone[hidden]{ display:none; }

  /* step 1: the parts and their four dots */
  .parts{ display:flex; justify-content:center; gap:14px; margin:6px 0 8px; flex-wrap:wrap; }
  .part{ flex:1 1 130px; max-width:210px; min-height:96px; cursor:pointer; padding:14px 10px 12px; border-radius:22px; background:#fff; border:2px solid var(--panel-line); box-shadow:0 6px 0 var(--lip); transition:transform .08s ease, box-shadow .08s ease; }
  .part:active{ transform:translateY(4px); box-shadow:0 2px 0 var(--lip); }
  .part.bump{ animation:bump .3s ease; }
  @keyframes bump{ 0%{ transform:scale(1); } 50%{ transform:scale(1.07); } 100%{ transform:scale(1); } }
  .part b{ display:block; font:700 clamp(34px, 9vw, 46px)/1 var(--font-game); color:#2f455b; letter-spacing:.02em; }
  .part .reps{ display:flex; justify-content:center; gap:7px; margin-top:12px; }
  .part .reps i{ width:13px; height:13px; border-radius:50%; background:var(--line); }
  .part .reps i.on{ background:var(--teal); }
  .part.full{ background:#e9f7f3; border-color:#bfe8dc; box-shadow:0 6px 0 #9ed6c6; }

  /* step 2: clap */
  .wordbar{ display:flex; justify-content:center; align-items:center; margin:8px 0 4px; transition:gap .35s cubic-bezier(.34,1.56,.64,1); }
  .wordbar.split{ gap:16px; }
  .seg2{ font:700 clamp(34px, 9vw, 46px)/1 var(--font-game); color:#2f455b; background:#fff; border:2px solid var(--panel-line); padding:12px 14px 10px; box-shadow:0 6px 0 var(--lip); transition:background .15s, color .15s, border-radius .3s; }
  .seg2:first-child{ border-radius:20px 0 0 20px; } .seg2:last-child{ border-radius:0 20px 20px 0; }
  .wordbar.split .seg2{ border-radius:20px; }
  .seg2.on{ background:var(--owl-orange-500); color:#fff; border-color:#d77a1a; box-shadow:0 6px 0 #b4560b; }
  .seg2 sup{ display:block; font:600 13px/1 var(--font-game); letter-spacing:.1em; color:inherit; opacity:0; margin-bottom:2px; }
  .wordbar.split .seg2 sup{ opacity:.75; }
  .clap-btn{ width:96px; height:96px; margin:10px auto 6px; border:none; border-radius:50%; cursor:pointer; display:grid; place-items:center; color:#fff; background:linear-gradient(180deg,#f9a544,#ee8a26); box-shadow:0 7px 0 #b4560b, 0 16px 24px -10px rgba(180,86,11,.6), inset 0 3px 0 rgba(255,255,255,.45); transition:transform .08s ease, box-shadow .08s ease; }
  .clap-btn .badge-svg{ width:48px; height:48px; display:block; }
  .clap-btn:active:not(:disabled){ transform:translateY(5px); box-shadow:0 2px 0 #b4560b, inset 0 3px 0 rgba(255,255,255,.4); }
  .clap-btn:disabled{ background:linear-gradient(180deg,#c9d6e3,#b3c3d3); box-shadow:0 7px 0 #93a7bb; cursor:default; }
  .count{ display:flex; justify-content:center; gap:9px; margin-top:2px; }
  .count i{ width:15px; height:15px; border-radius:50%; background:var(--line); }
  .count i.on{ background:var(--owl-orange-500); }

  /* step 3: tap the parts in order and they glide together */
  .join{ margin:10px 0 6px; --j:0; }
  .join-row{ display:flex; justify-content:center; align-items:center; gap:calc((1 - var(--j)) * 26px); }
  .jt{ cursor:pointer; font:700 clamp(34px, 9vw, 46px)/1 var(--font-game); color:#2f455b; background:#fff; border:2px solid var(--panel-line); padding:12px 14px 10px; box-shadow:0 6px 0 var(--lip); border-radius:20px; min-width:96px; min-height:88px; transition:border-radius .2s; }
  .jt sup{ display:block; font:600 13px/1 var(--font-game); letter-spacing:.1em; opacity:.75; margin-bottom:2px; }
  .jt:active{ transform:translateY(4px); }
  .jt.on{ background:#fff0d9; border-color:#f6b867; box-shadow:0 6px 0 #e3b53a; }
  .join.merged .jt:first-child{ border-radius:20px 0 0 20px; } .join.merged .jt:last-child{ border-radius:0 20px 20px 0; }
  .join.merged .jt, .join.merged .jt.on{ background:#e9f7f3; border-color:#bfe8dc; box-shadow:0 6px 0 #9ed6c6; }
  .join.merged .jt sup{ display:none; }
  .join.merged .join-row{ animation:pop .5s cubic-bezier(.34,1.56,.64,1); }

  /* step 4: the sentence, as in Sentence Stars, with the word of the game picked out */
  .sentence{ margin:6px auto 4px; padding:18px 16px 20px; background:#fff; border-radius:22px; border:2px solid #d6def0; box-shadow:0 6px 0 #c9d3e6; font:500 clamp(26px, 6.4vw, 36px)/1.55 'Lexend',sans-serif; color:#17324d; text-align:center; transition:box-shadow .3s ease, transform .3s ease; }
  .sentence .w{ display:inline-block; margin:0 .14em; padding:0 .12em; border-radius:10px; transition:background .25s ease, color .25s ease; }
  .sentence .w.hl{ background:#ffe3b8; color:#8a4300; box-shadow:inset 0 -4px 0 #f6b867; }
  .sentence .w.ok{ color:#1f7a50; background:#dff5e9; box-shadow:none; }
  .sentence.caught{ box-shadow:0 0 0 6px rgba(246,185,59,.45), 0 6px 0 #c9d3e6; transform:scale(1.02); }
  #stage .listen-btn{ margin:0 auto 12px; }
  .only-mic{ display:inline-flex; align-items:center; gap:6px; margin:6px auto 0; font:600 14px/1 var(--font-game); letter-spacing:.07em; text-transform:uppercase; color:#1f7a63; background:#e9f7f3; padding:6px 11px 5px; border-radius:999px; }
  .mic-zone{ margin:6px 0 0; }
  .say-note{ margin:8px 0 0; min-height:1.3em; }
  .say-note.small{ font:500 17px/1.4 'Inter',sans-serif; }

  @media (max-width:520px){
    .owl-lottie{ width:104px; }
    .star-lottie{ width:120px; height:120px; }
    h1{ font-size:27px; }
    .instr span{ font-size:21px; }
    .stepbar span{ font-size:12.5px; }
    .mic-btn{ width:84px; height:84px; }
    .mic-btn .badge-svg{ width:40px; height:40px; }
    .clap-btn{ width:88px; height:88px; }
    .sentence{ padding:14px 10px 16px; }
  }
  /* A short phone: the main thing to tap (the microphone in step 4) has to stay on the screen, so Tara's picture steps aside. */
  @media (max-height:700px){
    body{ padding-top:12px; padding-bottom:24px; }
    .top-bar{ margin-bottom:8px; }
    .progress-wrap{ margin-bottom:6px; }
    .progress-label{ margin-bottom:6px; }
    .stepbar{ margin-bottom:8px; }
    .card{ padding-top:16px; }
    .owl-lottie{ display:none; }
    h1{ font-size:24px; margin-bottom:6px; }
    .instr{ margin-bottom:6px; }
    #stage{ min-height:0; }
    #stage .listen-btn{ margin-bottom:8px; padding:8px 18px 7px; }
    .mic-btn{ width:76px; height:76px; margin-top:2px; }
    .big-btn{ padding:14px 20px 12px; }
    .part{ min-height:80px; padding:10px 8px; }
    .part .reps{ margin-top:8px; }
  }
</style>
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
  <ol class="stepbar" id="stepbar" aria-label="The four steps"></ol>

  <div class="card">
    <div id="playArea">
      <div class="owl-lottie" id="gameOwl" role="img" aria-label="Tara the owl, listening"></div>
      <h1 id="stepTitle"></h1>
      <div class="instr">
        <button type="button" class="spk" id="spkBtn" aria-label="Hear Tara say it again">@include('learner._badge-icon', ['icon' => 'speaker-high', 'class' => 'badge-svg'])</button>
        <span id="instrText" aria-live="polite"></span>
      </div>

      <div id="stage"></div>

      <button type="button" class="big-btn" id="nextBtn" disabled>Next</button>
      <div class="mic-zone" id="micZone" hidden>
        <button type="button" class="mic-btn" id="micBtn" aria-label="Tap, then read the sentence out loud">@include('learner._badge-icon', ['icon' => 'microphone', 'class' => 'badge-svg'])</button>
        <span class="only-mic">@include('learner._badge-icon', ['icon' => 'microphone', 'class' => 'badge-svg']) Only this step listens</span>
      </div>
      <button type="button" class="big-btn" id="tapBtn" hidden>I read it!</button>
      <p class="say-note" id="sayNote" aria-live="polite"></p>
      <p class="say-note small" id="footNote" hidden>Tara listens to you read the sentence. If she is not sure, she asks you to try once more. There is no wrong answer here.</p>
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
@include('learner.games._game-finish', ['game' => 'syllable-steps'])

<script>
  (function () {
    var LEVELS = @json($levels);
    var REPEAT = {{ (int) $repeat }};
    var MAX_ATTEMPTS = {{ (int) config('practice_games.rounds') }};       // rounds in one sitting (config/practice_games.php)
    var WORDS = {{ (int) config('practice_games.per_round') }};              // words in a round
    var CLEAN_HITS = {{ (int) config('practice_games.clean_hits') }};         // words read right the first time (step 4) that make a round clean and move up a level
    var MAX_TRIES = {{ (int) config('practice_games.tries') }};          // tries for the sentence: read it, then once more, then move on

    var STEP_NAMES = ['Listen', 'Split', 'Join', 'Read'];
    var TITLES = { 1: 'Say it with Tara', 2: 'Clap the parts', 3: 'Join the parts', 4: 'Read it in a sentence' };
    var NEXT_LABELS = { 1: 'Next: split it', 2: 'Next: join it', 3: 'Next: read it' };

    var level = {{ (int) $startLevel }};
    var attempt = 0;
    var leveledUpDuringSession = false;
    var topLevelCleared = false;
    var hadPerfectRound = false;
    var used = {};              // words already given this sitting, so a round is never the same words twice while there are others

    var targets = [];
    var pos = 0;                // which word of the round
    var step = 1;               // which of the four steps
    var dots = [];              // step 1: how many times each part was tapped
    var claps = 0;              // step 2
    var joinTaps = 0;           // step 3: parts tapped so far, in order
    var joined = false;
    var gliding = false;
    var tries = 0;
    var firstTryHits = 0;
    var userActive = false;     // the child has tapped something, so the browser lets Tara speak on her own
    var tapMode = !window.tarabasaCanListen;   // no microphone: the child says "I read it!" instead
    var busy = false;
    var listening = null;       // the controller of a recording that is running
    var instrTimer = null;

    function $(id) { return document.getElementById(id); }
    var levelBadge = $('levelBadge'), attemptLabel = $('attemptLabel'), progressLabel = $('progressLabel'), progressDots = $('progressDots');
    var stepbar = $('stepbar'), stepTitle = $('stepTitle'), spkBtn = $('spkBtn'), instrText = $('instrText'), stage = $('stage');
    var nextBtn = $('nextBtn'), micZone = $('micZone'), micBtn = $('micBtn'), tapBtn = $('tapBtn'), sayNote = $('sayNote'), footNote = $('footNote');
    var playArea = $('playArea'), celebrate = $('celebrate'), levelUpText = $('levelUpText'), roundComplete = $('roundComplete');
    var roundCompleteSummary = $('roundCompleteSummary'), backLink = $('backLink');

    function current() { return targets[pos]; }
    function esc(t) { return String(t).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    function shuffle(arr) {
      var a = arr.slice();
      for (var i = a.length - 1; i > 0; i--) {
        var j = Math.floor(Math.random() * (i + 1));
        var t = a[i]; a[i] = a[j]; a[j] = t;
      }
      return a;
    }
    // The words a sentence asks for, one for each word on the screen: the same cleaning the server does.
    function wordsOf(sentence) {
      return sentence.split(/\s+/).map(function (tok) { return tok.toLowerCase().replace(/[^a-z']/g, ''); }).filter(function (w) { return w; });
    }

    // Three words for a round: the child's own missed words first, then the level's words, then easier levels' words for review. A word is
    // not given twice in one sitting while there are others; the lists are short at level 2, so when they run out they start again.
    function pickWords(lv) {
      var data = LEVELS[lv];
      var out = [];
      function take(list) {
        for (var i = 0; i < list.length && out.length < WORDS; i++) {
          var e = list[i];
          if (!used[e.word] && !out.some(function (o) { return o.word === e.word; })) out.push(e);
        }
      }
      take(data.own);
      take(shuffle(data.level));
      take(shuffle(data.review));
      if (out.length < WORDS) { used = {}; take(shuffle(data.level)); take(shuffle(data.review)); }
      out.forEach(function (e) { used[e.word] = true; });
      return out;
    }

    // ----- the top of the page
    function setLevelBadge() {
      levelBadge.innerHTML = window.tarabasaBadgeSvg({ 1: 'plant', 2: 'leaf', 3: 'tree' }[level] || 'plant') + '<span>Level ' + level + '</span>';
    }
    function updateTopBar() {
      setLevelBadge();
      attemptLabel.textContent = 'Round ' + (attempt + 1) + ' of ' + MAX_ATTEMPTS;
    }
    function renderDots() {
      progressDots.innerHTML = '';
      for (var i = 0; i < WORDS; i++) {
        var dot = document.createElement('div');
        dot.className = 'pdot' + (i < pos ? ' done' : (i === pos ? ' current' : ''));
        progressDots.appendChild(dot);
      }
    }
    function renderStepbar() {
      var h = '';
      for (var i = 1; i <= 4; i++) {
        var cls = i < step ? 'done' : (i === step ? 'cur' : '');
        h += '<li class="' + cls + '"' + (i === step ? ' aria-current="step"' : '') + '><i>' + (i < step ? window.tarabasaBadgeSvg('check') : i) + '</i><span>' + STEP_NAMES[i - 1] + '</span>' + (i === 4 ? '<small>Uses the mic</small>' : '') + '</li>';
      }
      stepbar.innerHTML = h;
    }
    function nudgeOwl() {
      var owl = $('gameOwl');
      owl.classList.remove('is-encouraging');
      void owl.offsetWidth;
      owl.classList.add('is-encouraging');
    }

    // ----- what Tara says: the instruction of the step, said aloud as well as written
    function instruction() {
      if (!current()) return '';
      var n = current().parts.length;
      if (step === 1) return 'Tap a part. Say it with Tara, ' + REPEAT + ' times.';
      if (step === 2) return 'Clap once for each part.';
      if (step === 3) return n > 2 ? 'Tap the parts in order.' : 'Tap the first part. Then tap the next part.';
      return tapMode ? 'Read the sentence out loud. Then press the button.' : 'Tap the microphone. Read the sentence out loud.';
    }
    function speakInstruction() { if (window.tbCanSpeak) window.tarabasaSayText(instruction()); }
    function say(text) { if (window.tbCanSpeak) window.tarabasaSayText(text); }

    // ----- the glow on whatever the child should tap next
    function updateCues() {
      Array.prototype.forEach.call(document.querySelectorAll('.cue'), function (el) { el.classList.remove('cue'); });
      if (!current()) return;
      var c = null;
      var n = current().parts.length;
      if (!userActive && window.tbCanSpeak) c = spkBtn;
      else if (step === 1) {
        var firstOpen = -1;
        for (var i = 0; i < n; i++) { if (dots[i] < REPEAT) { firstOpen = i; break; } }
        c = firstOpen >= 0 ? stage.querySelectorAll('.part')[firstOpen] : nextBtn;
      } else if (step === 2) c = claps < n ? $('clapBtn') : nextBtn;
      else if (step === 3) c = joined ? nextBtn : (gliding ? null : stage.querySelectorAll('.jt')[joinTaps]);
      else if (step === 4 && !busy) c = tapMode ? tapBtn : micBtn;
      if (c) c.classList.add('cue');
    }

    // ----- one word, step by step
    function startRound() {
      targets = pickWords(level);
      pos = 0;
      firstTryHits = 0;
      updateTopBar();
      startWord();
    }

    function startWord() {
      var n = current().parts.length;
      tries = 0;
      busy = false;
      dots = [];
      for (var i = 0; i < n; i++) dots.push(0);
      claps = 0; joinTaps = 0; joined = false; gliding = false;
      progressLabel.textContent = 'Word ' + (pos + 1) + ' of ' + WORDS;
      renderDots();
      openStep(1);
    }

    function openStep(n) {
      step = n;
      clearTimeout(instrTimer);
      sayNote.textContent = '';
      renderStepbar();
      stepTitle.textContent = TITLES[n];
      instrText.textContent = instruction();
      spkBtn.hidden = !window.tbCanSpeak;

      nextBtn.hidden = n === 4;
      nextBtn.disabled = true;
      nextBtn.textContent = NEXT_LABELS[n] || 'Next';
      applyMode();
      micBtn.disabled = false;
      micBtn.classList.remove('listening');

      renderStage();
      // After the first tap the browser lets Tara speak on her own; the very first instruction waits for the speaker, which glows.
      if (userActive) instrTimer = setTimeout(speakInstruction, 350);
      updateCues();
    }

    function applyMode() {
      var four = step === 4;
      micZone.hidden = !four || tapMode;
      tapBtn.hidden = !four || !tapMode;
      footNote.hidden = !four || tapMode;
    }

    function dotsHtml(count, total, cls) {
      var h = '';
      for (var k = 0; k < total; k++) h += '<i class="' + (k < count ? 'on' : '') + '"></i>';
      return h;
    }

    function renderStage() {
      var cur = current(), parts = cur.parts, n = parts.length, h = '';

      if (step === 1) {
        h += '<button type="button" class="listen-btn" id="hearWord">' + window.tarabasaBadgeSvg('speaker-high') + ' <span>Hear the word</span></button><div class="parts">';
        parts.forEach(function (p, i) {
          h += '<button type="button" class="part" data-act="part" data-i="' + i + '" aria-label="Part ' + (i + 1) + ': ' + esc(p) + '"><b>' + esc(p) + '</b><span class="reps">' + dotsHtml(dots[i], REPEAT) + '</span></button>';
        });
        h += '</div>';
      }

      if (step === 2) {
        h += '<div class="wordbar" id="wordbar">';
        parts.forEach(function (p, i) { h += '<span class="seg2"><sup>' + (i + 1) + '</sup>' + esc(p) + '</span>'; });
        h += '</div><button type="button" class="clap-btn" id="clapBtn" data-act="clap" aria-label="Clap">' + window.tarabasaBadgeSvg('hand-palm') + '</button><div class="count" id="count" aria-hidden="true">' + dotsHtml(0, n) + '</div>';
      }

      if (step === 3) {
        h += '<div class="join" id="join" style="--j:0"><div class="join-row">';
        parts.forEach(function (p, i) {
          h += '<button type="button" class="jt" data-act="jtap" data-i="' + i + '" aria-label="Part ' + (i + 1) + ': ' + esc(p) + '"><sup>' + (i + 1) + '</sup>' + esc(p) + '</button>';
        });
        h += '</div></div><button type="button" class="listen-btn" id="hearJoined" hidden>' + window.tarabasaBadgeSvg('speaker-high') + ' <span>Hear the word</span></button>';
      }

      if (step === 4) {
        var html = '';
        cur.sentence.split(/\s+/).forEach(function (tok) {
          var clean = tok.toLowerCase().replace(/[^a-z']/g, '');
          if (clean) html += '<span class="w' + (clean === cur.word ? ' hl' : '') + '">' + esc(tok) + '</span> ';
        });
        h += '<button type="button" class="listen-btn" id="hearSentence">' + window.tarabasaBadgeSvg('speaker-high') + ' <span>Hear the sentence</span></button><div class="sentence" id="sentence" aria-live="polite">' + html + '</div>';
      }

      stage.innerHTML = h;
    }

    // Every tap on the stage counts as the child having tapped something (so Tara may speak on her own from now on).
    playArea.addEventListener('click', function () { userActive = true; clearTimeout(instrTimer); }, true);

    spkBtn.addEventListener('click', function () { speakInstruction(); updateCues(); });

    stage.addEventListener('click', function (e) {
      var hear = e.target.closest('#hearWord, #hearJoined, #hearSentence');
      if (hear) {
        if (hear.id === 'hearSentence') window.tarabasaSayText(current().sentence);
        else window.tarabasaSayWord(current().word);
        updateCues();
        return;
      }
      var el = e.target.closest('[data-act]');
      if (!el || el.disabled || busy) return;
      var act = el.getAttribute('data-act');
      if (act === 'part') tapPart(+el.getAttribute('data-i'), el);
      else if (act === 'clap') clap();
      else if (act === 'jtap') tapJoin(+el.getAttribute('data-i'));
    });

    // Step 1: tap a part, hear Tara say it, a dot fills. Four dots on every part opens the next step.
    function tapPart(i, btn) {
      var cur = current();
      window.tarabasaSayWord(cur.parts[i]);
      var wasFull = dots[i] >= REPEAT;
      if (dots[i] < REPEAT) dots[i]++;
      var reps = btn.querySelectorAll('.reps i');
      for (var k = 0; k < reps.length; k++) reps[k].className = k < dots[i] ? 'on' : '';
      btn.classList.remove('bump'); void btn.offsetWidth; btn.classList.add('bump');
      if (dots[i] >= REPEAT) {
        btn.classList.add('full');
        if (!wasFull) window.tarabasaPlaySfx('correct', 0.4);
      }
      var all = dots.every(function (d) { return d >= REPEAT; });
      nextBtn.disabled = !all;
      sayNote.textContent = all ? (cur.parts.length > 1 ? 'Every part, ' + REPEAT + ' times each.' : '') : '';
      updateCues();
    }

    // Step 2: one clap for each part. The word cuts apart where the parts are.
    function clap() {
      var cur = current(), n = cur.parts.length;
      if (claps >= n) return;
      window.tarabasaSayWord(cur.parts[claps]);
      var segs = stage.querySelectorAll('.seg2');
      segs[claps].classList.add('on');
      claps++;
      var cnt = $('count'); if (cnt) cnt.innerHTML = dotsHtml(claps, n);
      if (claps >= n) {
        $('wordbar').classList.add('split');
        $('clapBtn').disabled = true;
        nextBtn.disabled = false;
        sayNote.textContent = n + ' parts: ' + cur.parts.join(' and ') + '.';
        window.tarabasaPlaySfx('correct', 0.5);
      }
      updateCues();
    }

    // Step 3: tap the parts in order. A part tapped out of order is never "wrong": Tara says which comes first and that tile glows.
    function tapJoin(i) {
      var cur = current(), n = cur.parts.length;
      if (joined) { window.tarabasaSayWord(cur.word); return; }
      if (gliding) return;
      if (i !== joinTaps) {
        sayNote.textContent = joinTaps === 0 ? 'Start with the first part.' : 'Now tap the next part.';
        say(sayNote.textContent);
        updateCues();
        return;
      }
      window.tarabasaSayWord(cur.parts[i]);
      stage.querySelectorAll('.jt')[i].classList.add('on');
      joinTaps++;
      sayNote.textContent = '';
      if (joinTaps >= n) { gliding = true; updateCues(); setTimeout(glide, 450); }
      else updateCues();
    }

    // The tiles glide together by themselves once the last part is tapped, then Tara says the whole word.
    function glide() {
      var j = $('join');
      var reduce = false;
      try { reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches; } catch (e) {}
      function done() {
        if (joined) return;
        gliding = false;
        joined = true;
        j.style.setProperty('--j', '1');
        j.classList.add('merged');
        window.tarabasaSayWord(current().word);
        window.tarabasaPlaySfx('correct', 0.5);
        sayNote.textContent = 'You made the word!';
        var hj = $('hearJoined'); if (hj) hj.hidden = !window.tbCanSpeak;
        nextBtn.disabled = false;
        updateCues();
      }
      if (!j || reduce) { done(); return; }
      var t0 = null, dur = 650;
      // A page that is not being drawn (a background tab) pauses animation frames: the word is still made, a moment later, by this timer.
      setTimeout(done, dur + 500);
      (function frame(ts) {
        if (t0 === null) t0 = ts;
        var k = Math.min(1, (ts - t0) / dur);
        j.style.setProperty('--j', k.toFixed(3));
        if (k < 1) requestAnimationFrame(frame); else done();
      })(performance.now());
    }

    nextBtn.addEventListener('click', function () {
      if (nextBtn.disabled || step >= 4) return;
      openStep(step + 1);
    });

    // ----- step 4: read the sentence, as in Sentence Stars (the only step that listens)
    function markHeard(matched) {
      var spans = document.querySelectorAll('#sentence .w');
      Array.prototype.forEach.call(spans, function (el, i) { el.classList.toggle('ok', matched ? !!matched[i] : true); });
    }

    // The sentence was read (or the child said so): celebrate, then the next word.
    function finishWord(firstTry) {
      busy = true;
      if (firstTry) firstTryHits++;
      window.tarabasaPlaySfx('correct', 0.55);
      var s = $('sentence'); if (s) s.classList.add('caught');
      sayNote.textContent = firstTry ? 'Great reading!' : 'Good try! On we go.';
      say(sayNote.textContent);
      updateCues();
      pos++;
      setTimeout(function () {
        if (pos >= WORDS) finishAttempt(); else startWord();
      }, 1500);
    }

    micBtn.addEventListener('click', function () {
      if (listening) { listening.stop(); return; }   // pressed again while reading: that is "I am done"
      if (busy) return;
      busy = true;
      window.tbStopSpeaking();
      updateCues();
      var cur = current();
      window.tarabasaListenForText(wordsOf(cur.sentence).join(' '), 'sentence', {
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
            instrText.textContent = instruction();
            sayNote.textContent = '';
            updateCues();
            return;
          }
          if (status === 'heard') {
            markHeard(matched && matched.length ? matched : null);
            finishWord(tries === 0);
            return;
          }
          // Not sure what was said: show what went well, ask once more, then move on. There is no wrong answer here.
          var some = matched && matched.some(function (m) { return m; });
          if (some) markHeard(matched);
          tries++;
          if (tries >= MAX_TRIES) {
            sayNote.textContent = 'Good try! On we go.';
            say(sayNote.textContent);
            setTimeout(function () { busy = true; finishWord(false); }, 900);
            return;
          }
          nudgeOwl();
          sayNote.textContent = some ? 'Tara heard some of it. Try once more!' : 'Tara is not sure. Try once more!';
          busy = false;
          micBtn.disabled = false;
          updateCues();
        }
      });
    });

    tapBtn.addEventListener('click', function () {
      if (busy) return;
      finishWord(tries === 0);
    });

    // ----- rounds and the end, the same as the other games
    function finishAttempt() {
      var wasAtCeiling = level >= 3;
      if (wasAtCeiling) topLevelCleared = true;
      if (firstTryHits === WORDS) hadPerfectRound = true;
      var leveledUp = firstTryHits >= CLEAN_HITS && level < 3;
      if (leveledUp) {
        level++;
        leveledUpDuringSession = true;
      }
      attempt++;

      // A set number of rounds, or straight away once a round is finished at the top level, so a child who starts there plays one round, not three.
      if (attempt >= MAX_ATTEMPTS || wasAtCeiling) finishSession();
      else {
        updateTopBar();
        showRoundThenContinue(leveledUp);
      }
    }

    function showRoundThenContinue(leveledUp) {
      playArea.style.display = 'none';
      stepbar.style.display = 'none';
      levelUpText.style.display = leveledUp ? 'block' : 'none';
      window.tarabasaPlaySfx('levelComplete', 0.6);
      celebrate.classList.add('show');
      window.tarabasaAnimate($('celebrateStar'), 'star-happy');

      setTimeout(function () {
        celebrate.classList.remove('show');
        playArea.style.display = '';
        stepbar.style.display = '';
        startRound();
      }, 1700);
    }

    function finishSession() {
      setLevelBadge();
      attemptLabel.textContent = 'Complete!';
      playArea.style.display = 'none';
      stepbar.style.display = 'none';
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
