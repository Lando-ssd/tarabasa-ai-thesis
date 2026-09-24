{{--
  Letter Match: a memory match game. Find every capital letter and its small letter twin.
  Free play: no points, no streak, no level (see GameController). The look is shared with
  Word Builder in games/_game-look; only the cards are styled here.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Letter Match | TaraBasa AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Semi+Condensed:wght@500;600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
@include('learner.games._game-look')
<style>
  :root{
    --confetti-1:#ff9ec4; --confetti-2:#ffcf6e; --confetti-3:#7ecbe8; --confetti-4:#b9e28c;
  }
  @media (min-width:700px){ .wrap{ max-width:660px; } }
  .level-badge{ --lv-bg:#efe8fb; --lv-ink:#4a2f8f; --lv-lip:#d3c4f2; }
  .pdot.done{ background:#6b4bc7; }

  /* "Colorful pattern" accents: small scattered confetti shapes in the corners of the panel. */
  .confetti{ position:absolute; border-radius:6px; pointer-events:none; z-index:0; opacity:.5; }
  .confetti.c1{ top:16px; left:20px; width:14px; height:14px; background:var(--confetti-1); transform:rotate(18deg); border-radius:50%; }
  .confetti.c2{ top:36px; right:34px; width:12px; height:12px; background:var(--confetti-3); transform:rotate(-12deg); }
  .confetti.c3{ bottom:26px; left:36px; width:16px; height:16px; background:var(--confetti-2); border-radius:50%; }
  .confetti.c4{ bottom:50px; right:22px; width:13px; height:13px; background:var(--confetti-4); transform:rotate(30deg); }
  .confetti.c5{ top:80px; left:8px; width:10px; height:10px; background:var(--confetti-2); border-radius:50%; }
  .confetti.c6{ top:100px; right:10px; width:11px; height:11px; background:var(--confetti-1); transform:rotate(-20deg); }

  /* Column count is set inline via JS (see pickColumns()), not auto-fit: auto-fit packs as many
     columns as fit per row, which for an odd card count (e.g. 12) gave a ragged split (7 cards in
     row one, 5 in row two). pickColumns() always chooses a count the card count divides evenly. */
  .grid{ display:grid; gap:12px; margin-bottom:8px; justify-items:stretch; }
  .mcard{
    aspect-ratio:1; border-radius:18px; border:none; cursor:pointer; position:relative;
    background:linear-gradient(180deg,#3d97ea,#1c7ed6); color:#ffffff;
    font:700 40px/1 var(--font-game);
    display:flex; align-items:center; justify-content:center;
    box-shadow:0 6px 0 #0f5fae, inset 0 2px 0 rgba(255,255,255,.3);
    transition:transform .08s ease, box-shadow .08s ease;
  }
  .mcard:hover:not(:disabled){ transform:translateY(-2px); }
  .mcard:active:not(:disabled){ transform:translateY(4px); box-shadow:0 2px 0 #0f5fae, inset 0 2px 0 rgba(255,255,255,.3); }
  .mcard:disabled{ cursor:default; }
  .mcard:focus-visible{ outline:4px solid var(--owl-orange-500); outline-offset:3px; }
  .mcard.flipped{ background:#ffffff; color:var(--navy-900); box-shadow:0 6px 0 var(--lip), inset 0 0 0 2px var(--panel-line); }
  .mcard.matched{
    background:var(--success-bg); color:var(--success); box-shadow:0 6px 0 #b9e3d6, inset 0 0 0 2px #a9dccd;
    animation:matchPop .35s cubic-bezier(.34,1.56,.64,1);
  }
  .mcard.matched .mcard-spark{ position:absolute; top:-7px; right:-7px; width:18px; height:18px; }
  @keyframes matchPop{ 0%{ transform:scale(1); } 50%{ transform:scale(1.18); } 100%{ transform:scale(1); } }
  .mcard.wrong{ animation:cardShake .35s ease; box-shadow:0 6px 0 #efb1b1, inset 0 0 0 3px var(--danger); }
  @keyframes cardShake{ 0%,100%{ transform:translateX(0); } 25%{ transform:translateX(-6px); } 75%{ transform:translateX(6px); } }
  @media (min-width:700px){ .mcard{ font-size:46px; } }
</style>
</head>
<body>
<div class="wrap">
  <div class="top-bar">
    <span class="level-badge" id="levelBadge"></span>
    <span class="attempt-label" id="attemptLabel">Round 1 of 3</span>
  </div>
  <div class="progress-wrap">
    <div class="progress-label" id="progressLabel">Set 1 of 1</div>
    <div class="progress-dots" id="progressDots"></div>
  </div>

  <div class="card">
    <div class="confetti c1"></div>
    <div class="confetti c2"></div>
    <div class="confetti c3"></div>
    <div class="confetti c4"></div>
    <div class="confetti c5"></div>
    <div class="confetti c6"></div>

    <div id="playArea">
      <div class="mascot">@include('learner.games._owl-mascot', ['id' => 'lmOwlMain'])</div>
      <h1>Find the matching pairs!</h1>
      <div class="grid" id="grid"></div>
    </div>

    <div class="celebrate" id="subRoundComplete">
      <div class="celebrate-mascot">@include('learner.games._owl-mascot', ['id' => 'lmOwlSubComplete'])</div>
      <p class="celebrate-text">Nice matching!</p>
    </div>

    <div class="celebrate" id="roundComplete">
      <div class="celebrate-mascot">@include('learner.games._owl-mascot', ['id' => 'lmOwlRoundComplete'])</div>
      <h1>All done!</h1>
      <p class="round-summary" id="roundCompleteSummary">Great job!</p>
      <div class="gm-newbadges" id="newBadges"></div>
      <a href="{{ route('learner.games.letter-match') }}" class="big-btn">Play Again</a>
    </div>

    <a href="{{ route('learner.dashboard') }}" class="back-link" id="backLink" onclick="saveProgress()">Back to My Dashboard</a>
  </div>
</div>

@include('learner.games._game-sounds')
@include('learner.games._game-finish', ['game' => 'letter-match'])

<script>
  const LEVEL_DEFS = @json($levelDefs);
  const MAX_ATTEMPTS = 3;
  const WRONG_THRESHOLD = 3;

  let currentLevel = {{ (int) $startLevel }};
  let attemptCount = 0;
  let leveledUpDuringSession = false;
  let levelRounds = [];
  let subRoundIndex = 0;
  let wrongAttemptsThisAttempt = 0;
  let topLevelCleared = false;   // a whole round finished at level 3 (the whole alphabet)
  let hadPerfectRound = false;   // a round with no wrong pairs
  let cards = [];
  let flipped = [];
  let matchedCount = 0;
  let locked = false;

  const levelBadge = document.getElementById('levelBadge');
  const attemptLabel = document.getElementById('attemptLabel');
  const progressLabel = document.getElementById('progressLabel');
  const progressDots = document.getElementById('progressDots');
  const grid = document.getElementById('grid');
  const playArea = document.getElementById('playArea');
  const subRoundComplete = document.getElementById('subRoundComplete');
  const roundComplete = document.getElementById('roundComplete');
  const roundCompleteSummary = document.getElementById('roundCompleteSummary');
  const backLink = document.getElementById('backLink');

  const LEVEL_ICON = { 1: 'puzzle-piece', 2: 'puzzle-piece', 3: 'trophy' };

  // Resume-in-progress support — same technique and same reasoning as
  // Word Builder's (see that file's own comment): localStorage, not a new
  // DB column, keyed by the Learner's real learner_code so a shared
  // family device can't leak one child's progress into a sibling's.
  const STORAGE_KEY = 'tarabasa_lm_progress_{{ $learnerCode }}';

  function saveProgress() {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify({
        currentLevel, attemptCount, leveledUpDuringSession, topLevelCleared, hadPerfectRound,
        subRoundIndex, cards, wrongAttemptsThisAttempt,
      }));
    } catch (e) { /* resume is a convenience, never allowed to break play */ }
  }

  function clearProgress() {
    try { localStorage.removeItem(STORAGE_KEY); } catch (e) {}
  }

  function loadProgress() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return null;
      const data = JSON.parse(raw);
      if (!data || !Array.isArray(data.cards) || typeof data.subRoundIndex !== 'number') return null;
      if (!LEVEL_DEFS[data.currentLevel]) return null;
      return data;
    } catch (e) {
      return null;
    }
  }

  function resumeOrStart() {
    const saved = loadProgress();
    if (!saved) {
      startAttempt();
      return;
    }
    currentLevel = saved.currentLevel;
    attemptCount = saved.attemptCount;
    leveledUpDuringSession = saved.leveledUpDuringSession;
    topLevelCleared = !!saved.topLevelCleared;
    hadPerfectRound = !!saved.hadPerfectRound;
    levelRounds = LEVEL_DEFS[currentLevel];
    subRoundIndex = saved.subRoundIndex;
    wrongAttemptsThisAttempt = saved.wrongAttemptsThisAttempt;
    cards = saved.cards;
    flipped = [];
    matchedCount = cards.filter((c) => c.matched).length;
    locked = false;

    updateTopBar();
    progressLabel.textContent = 'Set ' + (subRoundIndex + 1) + ' of ' + levelRounds.length;
    renderDots();
    renderGrid();
  }

  function shuffle(arr) {
    const a = arr.slice();
    for (let i = a.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [a[i], a[j]] = [a[j], a[i]];
    }
    return a;
  }

  const CARD_BACK_SVG = '<svg viewBox="0 0 24 24" width="50%" height="50%" xmlns="http://www.w3.org/2000/svg"><path d="M12 3 L14 9 L20 10 L15.5 14 L17 20 L12 16.5 L7 20 L8.5 14 L4 10 L10 9 Z" fill="rgba(255,255,255,0.9)"/></svg>';
  const MATCH_SPARK_SVG = '<svg class="mcard-spark" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 2 L14 9 L21 11 L14 13 L12 20 L10 13 L3 11 L10 9 Z" fill="#ffcf6e" stroke="#f0b03e" stroke-width="1"/></svg>';

  function setLevelBadge() {
    levelBadge.innerHTML = window.tarabasaBadgeSvg(LEVEL_ICON[currentLevel] || 'puzzle-piece') + '<span>Level ' + currentLevel + '</span>';
  }

  function updateTopBar() {
    setLevelBadge();
    attemptLabel.textContent = 'Round ' + (attemptCount + 1) + ' of ' + MAX_ATTEMPTS;
  }

  function renderDots() {
    progressDots.innerHTML = '';
    for (let i = 0; i < levelRounds.length; i++) {
      const dot = document.createElement('div');
      dot.className = 'pdot' + (i < subRoundIndex ? ' done' : (i === subRoundIndex ? ' current' : ''));
      progressDots.appendChild(dot);
    }
  }

  function startAttempt() {
    levelRounds = LEVEL_DEFS[currentLevel];
    subRoundIndex = 0;
    wrongAttemptsThisAttempt = 0;
    updateTopBar();
    startSubRound();
  }

  function startSubRound() {
    const letters = levelRounds[subRoundIndex];
    let deck = [];
    letters.forEach((letter) => {
      deck.push({ pairId: letter, display: letter, matched: false });
      deck.push({ pairId: letter, display: letter.toLowerCase(), matched: false });
    });
    cards = shuffle(deck);
    flipped = [];
    matchedCount = 0;
    locked = false;

    progressLabel.textContent = 'Set ' + (subRoundIndex + 1) + ' of ' + levelRounds.length;
    renderDots();
    renderGrid();
    saveProgress();
  }

  // Always picks a column count the current card count divides evenly by,
  // so every row has the same number of cards — never a ragged last row.
  // Checked in an order that favors a wider, shorter grid (nicer on a
  // phone than a tall, narrow one) before falling back to narrower ones.
  function pickColumns(cardCount) {
    // On a tablet or bigger there is room for more columns, so the board stays short
    // enough to see whole without scrolling (18 cards is 3 rows of 6, not 6 rows of 3).
    const wide = window.matchMedia('(min-width: 700px)').matches;
    const candidates = wide ? [6, 5, 4, 3, 2] : [4, 3, 5, 6, 2];
    for (const c of candidates) {
      if (cardCount % c === 0) return c;
    }
    return 4;
  }

  function renderGrid() {
    grid.style.gridTemplateColumns = 'repeat(' + pickColumns(cards.length) + ', 1fr)';
    grid.innerHTML = '';
    cards.forEach((c, i) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'mcard' + (c.matched ? ' matched' : (flipped.includes(i) ? ' flipped' : ''));
      if (c.matched) {
        btn.innerHTML = c.display + MATCH_SPARK_SVG;
      } else if (flipped.includes(i)) {
        btn.textContent = c.display;
      } else {
        btn.innerHTML = CARD_BACK_SVG;
      }
      btn.disabled = c.matched || locked;
      btn.addEventListener('click', () => tapCard(i, btn));
      grid.appendChild(btn);
    });
  }

  function tapCard(i, btn) {
    if (locked || cards[i].matched || flipped.includes(i)) return;

    flipped.push(i);
    renderGrid();

    if (flipped.length === 2) {
      locked = true;
      const [a, b] = flipped;

      if (cards[a].pairId === cards[b].pairId) {
        cards[a].matched = true;
        cards[b].matched = true;
        matchedCount++;
        flipped = [];
        locked = false;
        window.tarabasaPlaySfx('correct', 0.55);
        renderGrid();

        if (matchedCount === levelRounds[subRoundIndex].length) {
          // Not saved here — transient, about to celebrate and move on
          // (same reasoning as Word Builder's completed-word case).
          setTimeout(subRoundDone, 400);
        } else {
          saveProgress();
        }
      } else {
        window.tarabasaPlaySfx('wrong', 0.3);
        nudgeOwl();
        wrongAttemptsThisAttempt++;

        setTimeout(() => {
          const cardEls = grid.querySelectorAll('.mcard');
          cardEls[a]?.classList.add('wrong');
          cardEls[b]?.classList.add('wrong');
        }, 20);

        setTimeout(() => {
          flipped = [];
          locked = false;
          renderGrid();
          saveProgress();
        }, 700);
      }
    }
  }

  function nudgeOwl() {
    const owl = document.getElementById('lmOwlMain');
    owl.classList.remove('is-encouraging');
    void owl.offsetWidth;
    owl.classList.add('is-encouraging');
  }

  function subRoundDone() {
    subRoundIndex++;
    if (subRoundIndex >= levelRounds.length) {
      finishAttempt();
      return;
    }

    // Only shown between internal sets within a multi-set level (level 3's
    // 3 alphabet chunks) — not a full attempt/level completion yet.
    playArea.style.display = 'none';
    document.getElementById('lmOwlSubComplete').classList.add('is-celebrating');
    window.tarabasaPlaySfx('levelComplete', 0.45);
    subRoundComplete.classList.add('show');

    setTimeout(() => {
      subRoundComplete.classList.remove('show');
      document.getElementById('lmOwlSubComplete').classList.remove('is-celebrating');
      playArea.style.display = '';
      startSubRound();
    }, 1100);
  }

  function finishAttempt() {
    const wasAtCeiling = currentLevel >= 3;
    if (wasAtCeiling) topLevelCleared = true;
    if (wrongAttemptsThisAttempt === 0) hadPerfectRound = true;
    const leveledUp = wrongAttemptsThisAttempt <= WRONG_THRESHOLD && currentLevel < 3;
    if (leveledUp) {
      currentLevel++;
      leveledUpDuringSession = true;
    }
    attemptCount++;

    if (attemptCount >= MAX_ATTEMPTS || wasAtCeiling) {
      finishSession(leveledUpDuringSession);
    } else {
      updateTopBar();
      if (leveledUp) {
        showLevelUpThenContinue();
      } else {
        startAttempt();
      }
    }
  }

  function showLevelUpThenContinue() {
    playArea.style.display = 'none';
    document.getElementById('lmOwlSubComplete').classList.add('is-celebrating');
    window.tarabasaPlaySfx('levelComplete', 0.6);
    subRoundComplete.querySelector('p').innerHTML = window.tarabasaBadgeSvg('star') + ' Level up!';
    subRoundComplete.classList.add('show');

    setTimeout(() => {
      subRoundComplete.classList.remove('show');
      subRoundComplete.querySelector('p').textContent = 'Nice matching!';
      document.getElementById('lmOwlSubComplete').classList.remove('is-celebrating');
      playArea.style.display = '';
      startAttempt();
    }, 1400);
  }

  function finishSession(justLeveledUp) {
    clearProgress();
    setLevelBadge();
    attemptLabel.textContent = 'Complete!';
    playArea.style.display = 'none';
    document.getElementById('lmOwlRoundComplete').classList.add('is-celebrating');
    window.tarabasaPlaySfx('levelComplete', 0.6);
    roundCompleteSummary.textContent = 'You finished at Level ' + currentLevel + (justLeveledUp ? '. Great climbing!' : '. Great job!');
    roundComplete.classList.add('show');
    backLink.style.display = 'none';

    // Tell the server the session ended so the Games badges can be earned (fire and forget).
    window.tarabasaReportGame({
      level_reached: currentLevel,
      top_level_cleared: topLevelCleared,
      had_perfect_round: hadPerfectRound,
      rounds: Math.max(1, Math.min(3, attemptCount))
    }, document.getElementById('newBadges'));
  }

  resumeOrStart();
</script>
</body>
</html>
