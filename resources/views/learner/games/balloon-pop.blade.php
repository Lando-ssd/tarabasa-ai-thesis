{{--
  Balloon Pop: a balloon shows a word, the child says the marked word out loud to pop it. Free play:
  no points, no streak, no level (see GameController), and what the child says is never graded.
  The look is shared with the other games in games/_game-look; only the balloons are styled here.

  Speaking (games/_game-voice): one press of the microphone, one word, checked by the same service
  that scores readings. The answer is only "heard" or "again", so Tara never says "wrong": if she is
  not sure she asks once more, then moves on and pops the balloon anyway. A microphone that is
  blocked or missing turns the game into tap-to-pop, so it can always be played.

  A round is 5 balloons with 3 of them marked in turn. Popping all three is a round. Two or more
  popped on the first try is a clean round (the next level), the same simple idea the other two
  games use. Up to 3 rounds, or until a round is finished at level 3.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Balloon Pop | TaraBasa AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Semi+Condensed:wght@500;600;700&family=Inter:wght@400;500;600;700;800&family=Lexend:wght@600;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
<noscript><link href="https://fonts.googleapis.com/css2?family=Barlow+Semi+Condensed:wght@500;600;700&family=Inter:wght@400;500;600;700;800&family=Lexend:wght@600;700&display=swap" rel="stylesheet"></noscript>
@include('learner.games._game-look')
<style>
  .level-badge{ --lv-bg:#dcecfb; --lv-ink:#1c5d9e; --lv-lip:#abcdee; }
  .card.sky{ background:linear-gradient(180deg,#bfe3ff 0%,#e6f4ff 60%,#f4fbf2 100%); }

  .ask{ display:flex; align-items:center; justify-content:center; gap:14px; flex-wrap:wrap; margin:0 0 6px; font:600 34px/1.1 var(--font-game); color:#1b3a5c; }
  .ask b{ color:var(--owl-orange-600); font:700 56px/1 'Lexend',var(--font-game); letter-spacing:.03em; }
  .ask .listen-btn{ margin:0; padding:10px 16px 9px; }

  .bl-row{ display:flex; gap:clamp(10px, 3vw, 26px); flex-wrap:wrap; justify-content:center; align-items:flex-end; margin:14px 0 8px; padding-bottom:34px; }
  .bl{
    position:relative; width:clamp(84px, 24vw, 150px); height:clamp(106px, 30vw, 190px); border:none; padding:0; cursor:default;
    border-radius:50% 50% 48% 48%; display:flex; align-items:center; justify-content:center;
    font:700 clamp(19px, 5.4vw, 40px)/1 'Lexend',sans-serif; color:#fff; text-shadow:0 2px 0 rgba(0,0,0,.2);
    box-shadow:inset -14px -18px 28px rgba(0,0,0,.16), inset 10px 10px 20px rgba(255,255,255,.35);
    transition:transform .3s ease, opacity .3s ease; animation:balloonFloat 3.2s ease-in-out infinite;
  }
  .bl:nth-child(2n){ animation-delay:-1.1s; } .bl:nth-child(3n){ animation-delay:-2.1s; }
  @keyframes balloonFloat{ 0%,100%{ transform:translateY(0); } 50%{ transform:translateY(-8px); } }
  /* the string */
  .bl::after{ content:''; position:absolute; left:50%; bottom:-30px; width:2px; height:30px; background:#8aa0b4; }
  .bl.c1{ background:#ef5b4a; } .bl.c2{ background:#2f8de0; } .bl.c3{ background:#2bb89c; } .bl.c4{ background:#f2b134; } .bl.c5{ background:#9b6bd6; }
  .bl.target{ outline:5px solid #fff; outline-offset:4px; box-shadow:0 0 0 10px rgba(255,255,255,.4), inset -14px -18px 28px rgba(0,0,0,.16), inset 10px 10px 20px rgba(255,255,255,.35); }
  .bl.tap-ready{ cursor:pointer; }
  .bl.tap-ready:focus-visible{ outline:5px solid var(--blue-500); }
  .bl.popping{ animation:none; transform:scale(1.25); opacity:.6; }
  .bl.pop{ animation:none; opacity:.22; transform:scale(.62); pointer-events:none; }
  .bl.pop::after{ display:none; }

  .mic-zone{ margin:6px 0 0; }
  .mic-zone[hidden]{ display:none; }
  .progress-wrap{ margin-bottom:12px; }

  /* On a phone three balloons fit in a row, and the owl is smaller, so the microphone stays on screen. */
  @media (max-width:520px){
    .ask b{ font-size:46px; } .ask{ font-size:28px; gap:10px; }
    .mascot{ width:70px; height:70px; margin-bottom:0; }
    .bl-row{ gap:8px 12px; padding-bottom:30px; margin-top:6px; }
    .bl{ width:calc((100% - 24px) / 3); height:auto; aspect-ratio:4 / 5; }
    .mic-btn{ width:84px; height:84px; }
    .mic-btn .badge-svg{ width:40px; height:40px; }
  }
  @media (prefers-reduced-motion:reduce){ .bl{ animation:none !important; } }
</style>
</head>
<body>
<div class="wrap">
  <div class="top-bar">
    <span class="level-badge" id="levelBadge"></span>
    <span class="attempt-label" id="attemptLabel">Round 1 of 3</span>
  </div>
  <div class="progress-wrap">
    <div class="progress-label" id="progressLabel">Balloon 1 of 3</div>
    <div class="progress-dots" id="progressDots"></div>
  </div>

  <div class="card sky">
    <div id="playArea">
      <div class="mascot">@include('learner.games._owl-mascot', ['id' => 'bpOwlMain'])</div>
      <div class="ask">
        <span>Say</span><b id="targetWord">sun</b>
        <button type="button" class="listen-btn" id="listenBtn" aria-label="Hear the word">@include('learner._badge-icon', ['icon' => 'speaker-high', 'class' => 'badge-svg'])</button>
      </div>
      <div class="bl-row" id="balloons"></div>
      <div class="mic-zone" id="micZone">
        <button type="button" class="mic-btn" id="micBtn" aria-label="Tap, then say the word">@include('learner._badge-icon', ['icon' => 'microphone', 'class' => 'badge-svg'])</button>
      </div>
      <p class="say-note" id="sayNote">Tap the microphone and say the word.</p>
      <p class="say-note" style="font:500 17px/1.4 'Inter',sans-serif; margin-top:6px;" id="footNote">Tara checks the word in a few seconds. If she is not sure, she asks you to try once more. There is no wrong answer here.</p>
    </div>

    <div class="celebrate" id="celebrate">
      <div class="celebrate-mascot">@include('learner.games._owl-mascot', ['id' => 'bpOwlCelebrate'])</div>
      <p class="celebrate-text">Round complete!</p>
      <p class="level-up-text" id="levelUpText" style="display:none;">@include('learner._badge-icon', ['icon' => 'star']) Level up!</p>
    </div>

    <div class="celebrate" id="roundComplete">
      <div class="celebrate-mascot">@include('learner.games._owl-mascot', ['id' => 'bpOwlRoundComplete'])</div>
      <h1>All done!</h1>
      <p class="round-summary" id="roundCompleteSummary">Nice work!</p>
      <div class="gm-newbadges" id="newBadges"></div>
      <a href="{{ route('learner.games.balloon-pop') }}" class="big-btn">Play Again</a>
    </div>

    <a href="{{ route('learner.dashboard') }}" class="back-link" id="backLink">Back to My Dashboard</a>
  </div>
</div>

@include('learner.games._game-sounds')
@include('learner.games._game-voice')
@include('learner.games._game-finish', ['game' => 'balloon-pop'])

<script>
  const LEVELS = @json($levels);
  const MAX_ATTEMPTS = 3;          // rounds in one sitting
  const TARGETS_PER_ROUND = 3;     // marked balloons to pop in a round
  const BALLOONS = 5;
  const CLEAN_HITS = 2;            // first-try pops (of 3) that make a round clean and move up a level
  const MAX_TRIES = 2;             // tries for one word: say it, then once more, then move on

  let currentLevel = {{ (int) $startLevel }};
  let attemptCount = 0;
  let leveledUpDuringSession = false;
  let topLevelCleared = false;
  let hadPerfectRound = false;

  let words = [];
  let order = [];            // balloon indexes, in the order they are marked
  let targetPos = 0;
  let tries = 0;
  let firstTryHits = 0;
  let userActive = false;    // the child has tapped something, so the browser lets a word be spoken on its own
  let tapMode = !window.tarabasaCanListen;   // no microphone: tap the marked balloon instead
  let busy = false;

  const levelBadge = document.getElementById('levelBadge');
  const attemptLabel = document.getElementById('attemptLabel');
  const progressLabel = document.getElementById('progressLabel');
  const progressDots = document.getElementById('progressDots');
  const balloonsEl = document.getElementById('balloons');
  const targetWordEl = document.getElementById('targetWord');
  const listenBtn = document.getElementById('listenBtn');
  const micZone = document.getElementById('micZone');
  const micBtn = document.getElementById('micBtn');
  const sayNote = document.getElementById('sayNote');
  const footNote = document.getElementById('footNote');
  const playArea = document.getElementById('playArea');
  const celebrate = document.getElementById('celebrate');
  const levelUpText = document.getElementById('levelUpText');
  const roundComplete = document.getElementById('roundComplete');
  const roundCompleteSummary = document.getElementById('roundCompleteSummary');
  const backLink = document.getElementById('backLink');

  const LEVEL_ICON = { 1: 'balloon', 2: 'balloon', 3: 'trophy' };

  function shuffle(arr) {
    const a = arr.slice();
    for (let i = a.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [a[i], a[j]] = [a[j], a[i]];
    }
    return a;
  }

  // Words to top a round up with: the ones that practise this child's own kind of mistake first
  // (when there is one), then the rest of the level's list, each group in a fresh random order.
  function fillFrom(data, picked) {
    const free = (w) => picked.indexOf(w) === -1;
    const favoured = shuffle((data.favoured || []).filter(free));
    const rest = shuffle(data.fallback.filter((w) => free(w) && favoured.indexOf(w) === -1));
    return favoured.concat(rest);
  }

  // The child's own tricky words for this level first, topped up from the level's word list.
  function buildRoundWords(level) {
    const data = LEVELS[level];
    let picked = shuffle(data.struggling).slice(0, BALLOONS);
    if (picked.length < BALLOONS) {
      picked = picked.concat(fillFrom(data, picked).slice(0, BALLOONS - picked.length));
    }
    return shuffle(picked);
  }

  function setLevelBadge() {
    levelBadge.innerHTML = window.tarabasaBadgeSvg(LEVEL_ICON[currentLevel] || 'balloon') + '<span>Level ' + currentLevel + '</span>';
  }

  function updateTopBar() {
    setLevelBadge();
    attemptLabel.textContent = 'Round ' + (attemptCount + 1) + ' of ' + MAX_ATTEMPTS;
  }

  function renderDots() {
    progressDots.innerHTML = '';
    for (let i = 0; i < TARGETS_PER_ROUND; i++) {
      const dot = document.createElement('div');
      dot.className = 'pdot' + (i < targetPos ? ' done' : (i === targetPos ? ' current' : ''));
      progressDots.appendChild(dot);
    }
  }

  function startRound() {
    words = buildRoundWords(currentLevel);
    order = shuffle(words.map((_, i) => i)).slice(0, TARGETS_PER_ROUND);
    targetPos = 0;
    firstTryHits = 0;
    updateTopBar();
    renderBalloons();
    startTarget();
  }

  function renderBalloons() {
    balloonsEl.innerHTML = '';
    words.forEach((w, i) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'bl c' + ((i % 5) + 1);
      b.dataset.i = i;
      b.textContent = w;
      b.setAttribute('aria-label', w);
      b.addEventListener('click', () => { if (tapMode && !busy && i === order[targetPos]) popTarget(true); });
      balloonsEl.appendChild(b);
    });
  }

  function currentWord() { return words[order[targetPos]]; }

  function startTarget() {
    tries = 0;
    busy = false;
    const word = currentWord();
    targetWordEl.textContent = word;
    progressLabel.textContent = 'Balloon ' + (targetPos + 1) + ' of ' + TARGETS_PER_ROUND;
    renderDots();

    balloonsEl.querySelectorAll('.bl').forEach((el) => {
      el.classList.toggle('target', Number(el.dataset.i) === order[targetPos]);
      el.classList.toggle('tap-ready', tapMode && !el.classList.contains('pop'));
    });

    applyMode();
    micBtn.disabled = false;
    micBtn.classList.remove('listening');
    // After the first tap the browser lets the word be said on its own; the first one waits for Listen.
    if (userActive) window.tarabasaSayWord(word);
  }

  function applyMode() {
    micZone.hidden = tapMode;
    sayNote.textContent = tapMode ? 'Tap the marked balloon to pop it.' : 'Tap the microphone and say the word.';
    footNote.hidden = tapMode;
    balloonsEl.querySelectorAll('.bl:not(.pop)').forEach((el) => el.classList.toggle('tap-ready', tapMode));
  }

  function nudgeOwl() {
    const owl = document.getElementById('bpOwlMain');
    owl.classList.remove('is-encouraging');
    void owl.offsetWidth;
    owl.classList.add('is-encouraging');
  }

  function popTarget(firstTry) {
    busy = true;
    if (firstTry) firstTryHits++;
    const el = balloonsEl.querySelector('.bl[data-i="' + order[targetPos] + '"]');
    el.classList.remove('target');
    el.classList.add('popping');
    window.tarabasaPlaySfx('correct', 0.55);
    setTimeout(() => { el.classList.remove('popping'); el.classList.add('pop'); }, 220);

    targetPos++;
    setTimeout(() => {
      if (targetPos >= TARGETS_PER_ROUND) finishAttempt(); else startTarget();
    }, 650);
  }

  micBtn.addEventListener('click', () => {
    if (busy) return;
    userActive = true;
    busy = true;
    micBtn.disabled = true;
    window.tbStopSpeaking();
    window.tarabasaListenForWord(currentWord(), {
      onListening: () => { micBtn.classList.add('listening'); sayNote.textContent = 'Listening...'; },
      onChecking: () => { micBtn.classList.remove('listening'); sayNote.textContent = 'Tara is checking...'; },
      onDone: (status) => {
        micBtn.classList.remove('listening');
        if (status === 'unavailable') {
          // No microphone after all (blocked, missing, or the service cannot be reached): tap to pop.
          tapMode = true;
          busy = false;
          applyMode();
          return;
        }
        if (status === 'heard') { popTarget(tries === 0); return; }
        // Not sure what was said: ask once more, then pop it anyway. There is no wrong answer here.
        tries++;
        if (tries >= MAX_TRIES) {
          sayNote.textContent = 'Good try! On we go.';
          setTimeout(() => popTarget(false), 700);
          return;
        }
        nudgeOwl();
        sayNote.textContent = 'Tara is not sure. Try once more!';
        busy = false;
        micBtn.disabled = false;
      }
    });
  });

  listenBtn.addEventListener('click', () => {
    userActive = true;
    window.tarabasaSayWord(currentWord());
  });
  listenBtn.hidden = !window.tbCanSpeak;

  function finishAttempt() {
    const wasAtCeiling = currentLevel >= 3;
    if (wasAtCeiling) topLevelCleared = true;
    if (firstTryHits === TARGETS_PER_ROUND) hadPerfectRound = true;
    const leveledUp = firstTryHits >= CLEAN_HITS && currentLevel < 3;
    if (leveledUp) {
      currentLevel++;
      leveledUpDuringSession = true;
    }
    attemptCount++;

    // Same ending as the other games: a set number of rounds, or straight away once a round is
    // finished at the top level, so a child who starts there plays one round, not three.
    if (attemptCount >= MAX_ATTEMPTS || wasAtCeiling) {
      finishSession();
    } else {
      updateTopBar();
      showRoundThenContinue(leveledUp);
    }
  }

  function showRoundThenContinue(leveledUp) {
    playArea.style.display = 'none';
    levelUpText.style.display = leveledUp ? 'block' : 'none';
    document.getElementById('bpOwlCelebrate').classList.add('is-celebrating');
    window.tarabasaPlaySfx('levelComplete', 0.6);
    celebrate.classList.add('show');

    setTimeout(() => {
      celebrate.classList.remove('show');
      document.getElementById('bpOwlCelebrate').classList.remove('is-celebrating');
      playArea.style.display = '';
      startRound();
    }, 1700);
  }

  function finishSession() {
    setLevelBadge();
    attemptLabel.textContent = 'Complete!';
    playArea.style.display = 'none';
    document.getElementById('bpOwlRoundComplete').classList.add('is-celebrating');
    window.tarabasaPlaySfx('levelComplete', 0.6);
    roundCompleteSummary.textContent = 'You finished at Level ' + currentLevel + (leveledUpDuringSession ? '. Great climbing!' : '. Nice work!');
    roundComplete.classList.add('show');
    backLink.style.display = 'none';

    window.tarabasaReportGame({
      level_reached: currentLevel,
      top_level_cleared: topLevelCleared,
      had_perfect_round: hadPerfectRound,
      rounds: Math.max(1, Math.min(3, attemptCount))
    }, document.getElementById('newBadges'));
  }

  startRound();
</script>
</body>
</html>
