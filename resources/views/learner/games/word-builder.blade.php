{{--
  Word Builder: put scrambled letters in the right order to spell real words. Free play:
  no points, no streak, no level (see GameController). The look is shared with Letter
  Match in games/_game-look; only the tiles and slots are styled here.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Word Builder | TaraBasa AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Semi+Condensed:wght@500;600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
@include('learner.games._game-look')
<style>
  :root{ --leaf-green:#4caf7d; --petal-pink:#ff9ec4; }
  .level-badge{ --lv-bg:#d8f2e4; --lv-ink:#1a6e4f; --lv-lip:#a7dcc0; }

  /* "Garden" accents: simple leaf and flower shapes tucked into the corners of the panel. */
  .garden-accent{ position:absolute; pointer-events:none; z-index:0; opacity:.85; }
  .garden-accent.leaf-tl{ top:-10px; left:-14px; width:86px; transform:rotate(-18deg); }
  .garden-accent.leaf-br{ bottom:-16px; right:-18px; width:100px; transform:rotate(160deg); }
  .garden-accent.petal-tr{ top:14px; right:22px; width:34px; }
  .garden-accent.petal-bl{ bottom:60px; left:16px; width:26px; }

  .slots{ display:flex; gap:10px; justify-content:center; flex-wrap:wrap; margin-bottom:30px; }
  .slot{
    width:54px; height:64px; border-radius:16px; background:#ffffff; border:2px solid var(--panel-line);
    box-shadow:inset 0 4px 0 rgba(19,31,43,.06);
    display:flex; align-items:center; justify-content:center;
    font:700 38px/1 var(--font-game); color:var(--success); text-transform:uppercase;
    transition:transform .2s cubic-bezier(.34,1.56,.64,1), background .15s ease;
  }
  .slot.filled{ background:var(--success-bg); border-color:#a9dccd; animation:slotPop .3s cubic-bezier(.34,1.56,.64,1); }
  @keyframes slotPop{ 0%{ transform:scale(.7); } 60%{ transform:scale(1.12); } 100%{ transform:scale(1); } }

  .tiles{ display:flex; gap:12px; justify-content:center; flex-wrap:wrap; margin-bottom:8px; }
  .tile{
    width:64px; height:70px; border-radius:18px; cursor:pointer; background:#ffffff; border:2px solid var(--panel-line);
    font:700 40px/1 var(--font-game); color:var(--navy-900); text-transform:uppercase;
    box-shadow:0 6px 0 var(--lip); transition:transform .08s ease, box-shadow .08s ease, opacity .2s ease;
  }
  .tile:hover:not(:disabled){ transform:translateY(-2px); border-color:var(--owl-orange-500); }
  .tile:active:not(:disabled){ transform:translateY(4px); box-shadow:0 2px 0 var(--lip); }
  .tile:disabled{ opacity:0; pointer-events:none; }
  .tile:focus-visible{ outline:4px solid var(--blue-500); outline-offset:3px; }
  .tile.correct-tap{ animation:tileBounce .4s cubic-bezier(.34,1.56,.64,1); }
  @keyframes tileBounce{ 0%{ transform:scale(1); } 40%{ transform:scale(1.25) translateY(-8px) rotate(-4deg); } 100%{ transform:scale(1); } }
  .tile.wrong-tap{ animation:tileShake .35s ease; border-color:var(--danger); box-shadow:0 6px 0 #efb1b1; }
  @keyframes tileShake{ 0%,100%{ transform:translateX(0); } 25%{ transform:translateX(-7px); } 75%{ transform:translateX(7px); } }
  /* On a phone the answer slots shrink to keep a whole word (up to six letters) on one line. */
  @media (max-width:520px){
    .slots{ flex-wrap:nowrap; gap:8px; }
    .slot{ flex:1 1 0; min-width:0; max-width:54px; height:60px; font-size:32px; }
    .tiles{ gap:10px; }
  }
  @media (min-width:700px){
    .slot{ width:60px; height:70px; font-size:42px; }
    .tile{ width:72px; height:78px; font-size:44px; }
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
    <div class="progress-label" id="progressLabel">Word 1 of 5</div>
    <div class="progress-dots" id="progressDots"></div>
  </div>

  <div class="card">
    <svg class="garden-accent leaf-tl" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"><path d="M30 2C46 2 58 14 58 30C42 30 30 18 30 2Z" fill="var(--leaf-green)" opacity=".35"/></svg>
    <svg class="garden-accent leaf-br" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"><path d="M30 2C46 2 58 14 58 30C42 30 30 18 30 2Z" fill="var(--leaf-green)" opacity=".3"/></svg>
    <svg class="garden-accent petal-tr" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg"><circle cx="20" cy="12" r="8" fill="var(--petal-pink)" opacity=".55"/><circle cx="11" cy="24" r="8" fill="var(--petal-pink)" opacity=".55"/><circle cx="29" cy="24" r="8" fill="var(--petal-pink)" opacity=".55"/><circle cx="20" cy="22" r="6" fill="var(--clay-yellow)" opacity=".8"/></svg>
    <svg class="garden-accent petal-bl" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg"><circle cx="20" cy="12" r="8" fill="var(--clay-yellow)" opacity=".55"/><circle cx="11" cy="24" r="8" fill="var(--clay-yellow)" opacity=".55"/><circle cx="29" cy="24" r="8" fill="var(--clay-yellow)" opacity=".55"/><circle cx="20" cy="22" r="6" fill="var(--petal-pink)" opacity=".7"/></svg>

    <div id="playArea">
      <div class="mascot">@include('learner.games._owl-mascot', ['id' => 'wbOwlMain'])</div>
      <h1 id="promptText">Spell the word!</h1>
      <div class="slots" id="slots"></div>
      <div class="tiles" id="tiles"></div>
    </div>

    <div class="celebrate" id="celebrate">
      <div class="celebrate-mascot">@include('learner.games._owl-mascot', ['id' => 'wbOwlCelebrate'])</div>
      <p class="celebrate-text" id="celebrateText">Great job!</p>
      <p class="level-up-text" id="levelUpText" style="display:none;">@include('learner._badge-icon', ['icon' => 'star']) Level up!</p>
    </div>

    <div class="celebrate" id="roundComplete">
      <div class="celebrate-mascot">@include('learner.games._owl-mascot', ['id' => 'wbOwlRoundComplete'])</div>
      <h1>All done!</h1>
      <p class="round-summary" id="roundCompleteSummary">Nice work!</p>
      <div class="gm-newbadges" id="newBadges"></div>
      <a href="{{ route('learner.games.word-builder') }}" class="big-btn">Play Again</a>
    </div>

    <a href="{{ route('learner.dashboard') }}" class="back-link" id="backLink" onclick="saveProgress()">Back to My Dashboard</a>
  </div>
</div>

@include('learner.games._game-sounds')
@include('learner.games._game-finish', ['game' => 'word-builder'])

<script>
  const LEVELS = @json($levels);
  const MAX_ATTEMPTS = 3;
  const WRONG_THRESHOLD = 3; // at most this many wrong taps in a round to advance a level

  let currentLevel = {{ (int) $startLevel }};
  let attemptCount = 0; // attempts completed so far
  let leveledUpDuringSession = false; // tracks the WHOLE session, not just the final attempt
  let roundWords = [];
  let wordIndex = 0;
  let letters = [];
  let used = [];
  let spelled = '';
  let wrongTapsThisAttempt = 0;
  let topLevelCleared = false;   // a whole round finished at level 3
  let hadPerfectRound = false;   // a round with no wrong taps

  const levelBadge = document.getElementById('levelBadge');
  const attemptLabel = document.getElementById('attemptLabel');
  const progressLabel = document.getElementById('progressLabel');
  const progressDots = document.getElementById('progressDots');
  const slotsEl = document.getElementById('slots');
  const tilesEl = document.getElementById('tiles');
  const playArea = document.getElementById('playArea');
  const celebrate = document.getElementById('celebrate');
  const celebrateText = document.getElementById('celebrateText');
  const levelUpText = document.getElementById('levelUpText');
  const roundComplete = document.getElementById('roundComplete');
  const roundCompleteSummary = document.getElementById('roundCompleteSummary');
  const backLink = document.getElementById('backLink');

  const LEVEL_ICON = { 1: 'plant', 2: 'leaf', 3: 'tree' };

  // Resume-in-progress support — this game's entire state already lives
  // only in these JS variables (the server sends the word pools once, up
  // front, and never hears back), so localStorage is the natural, lowest-
  // friction place to keep it, not a new DB column: no migration, no save
  // endpoint, and it keeps the deliberate "Practice Games persist nothing
  // server-side" separation intact. Keyed by the Learner's own real
  // learner_code (safe to expose client-side — it's the public login code,
  // not the PIN) so a shared family device can't leak one child's
  // in-progress game into a sibling's session. Wrapped in try/catch
  // throughout — localStorage can legitimately throw (private browsing,
  // storage disabled, quota) and a resume convenience must never be able
  // to break the game itself.
  const STORAGE_KEY = 'tarabasa_wb_progress_{{ $learnerCode }}';

  function saveProgress() {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify({
        currentLevel, attemptCount, leveledUpDuringSession, topLevelCleared, hadPerfectRound,
        roundWords, wordIndex, letters, used, spelled, wrongTapsThisAttempt,
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
      // A basic shape check — never trust stored data blindly, especially
      // across a future code change to the level/word structure.
      if (!data || !Array.isArray(data.roundWords) || typeof data.wordIndex !== 'number') return null;
      if (data.wordIndex < 0 || data.wordIndex >= data.roundWords.length) return null;
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
    roundWords = saved.roundWords;
    wordIndex = saved.wordIndex;
    letters = saved.letters;
    used = saved.used;
    spelled = saved.spelled;
    wrongTapsThisAttempt = saved.wrongTapsThisAttempt;

    updateTopBar();
    progressLabel.textContent = 'Word ' + (wordIndex + 1) + ' of ' + roundWords.length;
    renderDots();
    renderSlots();
    renderTiles();
  }

  function shuffle(arr) {
    const a = arr.slice();
    for (let i = a.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [a[i], a[j]] = [a[j], a[i]];
    }
    return a;
  }

  // Real struggling words for the CURRENT level first, topped up from that
  // level's static list — re-run fresh every time a round starts (not just
  // once), so retrying a held level reshuffles instead of repeating the
  // identical 5 words, and real struggling words get a fresh chance to
  // reappear each attempt.
  function buildRoundWords(level) {
    const data = LEVELS[level];
    let picked = shuffle(data.struggling).slice(0, 5);
    if (picked.length < 5) {
      const filler = shuffle(data.fallback.filter((w) => picked.indexOf(w) === -1)).slice(0, 5 - picked.length);
      picked = picked.concat(filler);
    }
    return shuffle(picked);
  }

  function setLevelBadge() {
    levelBadge.innerHTML = window.tarabasaBadgeSvg(LEVEL_ICON[currentLevel] || 'plant') + '<span>Level ' + currentLevel + '</span>';
  }

  function updateTopBar() {
    setLevelBadge();
    attemptLabel.textContent = 'Round ' + (attemptCount + 1) + ' of ' + MAX_ATTEMPTS;
  }

  function renderDots() {
    progressDots.innerHTML = '';
    for (let i = 0; i < roundWords.length; i++) {
      const dot = document.createElement('div');
      dot.className = 'pdot' + (i < wordIndex ? ' done' : (i === wordIndex ? ' current' : ''));
      progressDots.appendChild(dot);
    }
  }

  function startAttempt() {
    roundWords = buildRoundWords(currentLevel);
    wordIndex = 0;
    wrongTapsThisAttempt = 0;
    updateTopBar();
    startWord();
  }

  function startWord() {
    const word = roundWords[wordIndex];
    letters = shuffle(word.split(''));
    if (letters.join('') === word && word.length > 1) {
      letters = shuffle(letters);
    }
    used = new Array(letters.length).fill(false);
    spelled = '';
    progressLabel.textContent = 'Word ' + (wordIndex + 1) + ' of ' + roundWords.length;
    renderDots();
    renderSlots();
    renderTiles();
    saveProgress();
  }

  function renderSlots() {
    const word = roundWords[wordIndex];
    slotsEl.innerHTML = '';
    for (let i = 0; i < word.length; i++) {
      const slot = document.createElement('div');
      slot.className = 'slot' + (i < spelled.length ? ' filled' : '');
      slot.textContent = i < spelled.length ? spelled[i] : '';
      slotsEl.appendChild(slot);
    }
  }

  function renderTiles() {
    tilesEl.innerHTML = '';
    letters.forEach((letter, i) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'tile';
      btn.textContent = letter;
      btn.disabled = used[i];
      btn.addEventListener('click', () => tapTile(i, btn));
      tilesEl.appendChild(btn);
    });
  }

  function tapTile(i, btn) {
    if (used[i]) return;
    const word = roundWords[wordIndex];
    const expected = word[spelled.length];

    if (letters[i].toLowerCase() === expected) {
      used[i] = true;
      spelled += letters[i];
      btn.classList.add('correct-tap');
      renderSlots();
      btn.disabled = true;
      window.tarabasaPlaySfx('correct', 0.55);

      if (spelled.length === word.length) {
        // Deliberately NOT saved here — this is a brief, transient
        // "just completed, about to celebrate and advance" moment. If a
        // Learner navigates away in the ~0.3-2.9s before the next word/
        // attempt actually starts, resuming replays the last tap instead
        // of risking a frozen "fully spelled, nothing left to do" state
        // that the game has no code path to advance out of on a fresh
        // page load.
        setTimeout(wordComplete, 350);
      } else {
        saveProgress();
      }
    } else {
      btn.classList.remove('wrong-tap');
      void btn.offsetWidth;
      btn.classList.add('wrong-tap');
      wrongTapsThisAttempt++;
      window.tarabasaPlaySfx('wrong', 0.3);
      nudgeOwl();
      saveProgress();
    }
  }

  function nudgeOwl() {
    const owl = document.getElementById('wbOwlMain');
    owl.classList.remove('is-encouraging');
    void owl.offsetWidth;
    owl.classList.add('is-encouraging');
  }

  function wordComplete() {
    playArea.style.display = 'none';
    celebrateText.textContent = 'You spelled "' + roundWords[wordIndex].toUpperCase() + '"!';
    levelUpText.style.display = 'none';
    document.getElementById('wbOwlCelebrate').classList.add('is-celebrating');
    window.tarabasaPlaySfx('levelComplete', 0.5);
    celebrate.classList.add('show');

    setTimeout(() => {
      celebrate.classList.remove('show');
      document.getElementById('wbOwlCelebrate').classList.remove('is-celebrating');
      playArea.style.display = '';
      wordIndex++;
      if (wordIndex >= roundWords.length) {
        finishAttempt();
      } else {
        startWord();
      }
    }, 1300);
  }

  function finishAttempt() {
    const wasAtCeiling = currentLevel >= 3;
    if (wasAtCeiling) topLevelCleared = true;
    if (wrongTapsThisAttempt === 0) hadPerfectRound = true;
    const leveledUp = wrongTapsThisAttempt <= WRONG_THRESHOLD && currentLevel < 3;
    if (leveledUp) {
      currentLevel++;
      leveledUpDuringSession = true;
    }
    attemptCount++;

    // Session ends after MAX_ATTEMPTS attempts, or immediately once an
    // attempt is completed while already at the level-3 ceiling — no
    // point replaying the hardest tier 2 more times just to hit a fixed
    // attempt count, and a Learner who starts at level 3 (an older grade)
    // still gets a single, appropriately-sized session, not 3x as long.
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
    celebrateText.textContent = 'Round complete!';
    levelUpText.style.display = 'block';
    document.getElementById('wbOwlCelebrate').classList.add('is-celebrating');
    window.tarabasaPlaySfx('levelComplete', 0.6);
    celebrate.classList.add('show');

    setTimeout(() => {
      celebrate.classList.remove('show');
      document.getElementById('wbOwlCelebrate').classList.remove('is-celebrating');
      playArea.style.display = '';
      startAttempt();
    }, 1600);
  }

  function finishSession(justLeveledUp) {
    clearProgress();
    setLevelBadge();
    attemptLabel.textContent = 'Complete!';
    playArea.style.display = 'none';
    document.getElementById('wbOwlRoundComplete').classList.add('is-celebrating');
    window.tarabasaPlaySfx('levelComplete', 0.6);
    roundCompleteSummary.textContent = 'You finished at Level ' + currentLevel + (justLeveledUp ? '. Great climbing!' : '. Nice work!');
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
