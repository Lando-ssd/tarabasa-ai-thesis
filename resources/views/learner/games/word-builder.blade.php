<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Word Builder — TaraBasa AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --sky-100:#dcedff;
    --blue-500:#1c7ed6; --blue-700:#0a3d73;
    --navy-900:#131f2b; --slate-600:#5b6b7a;
    --owl-orange-500:#ef8d2a; --owl-orange-600:#dd7014; --clay-yellow:#ffcf6e;
    --teal:#2bb89c; --success:#1f9e83; --success-bg:#e9f7f3;
    --danger:#d64545;
    --leaf-green:#4caf7d; --leaf-green-light:#c9ecd9; --petal-pink:#ff9ec4;
    --line:#e3ebf2; --surface:#ffffff; --bg-0:#f6faff;
  }
  *{box-sizing:border-box;} html,body{margin:0;padding:0;}
  body{
    min-height:100vh; font-family:'Inter',sans-serif; color:var(--navy-900);
    background: radial-gradient(1200px 700px at 90% -10%, var(--sky-100), transparent 55%),
                radial-gradient(900px 600px at 0% 100%, #ffe9c9, transparent 50%), var(--bg-0);
    background-attachment:fixed;
    display:flex; align-items:safe center; justify-content:center; padding:24px;
  }
  .wrap{ width:100%; max-width:480px; }
  @media (min-width:700px){ .wrap{ max-width:640px; } }

  .top-bar{ display:flex; align-items:center; justify-content:center; gap:10px; margin-bottom:14px; flex-wrap:wrap; }
  .level-badge{
    display:inline-flex; align-items:center; gap:5px; font:800 12.5px/1 'Baloo 2',sans-serif; color:#1a6e4f;
    background:var(--leaf-green-light); border:1.5px solid var(--leaf-green); border-radius:999px; padding:5px 12px;
  }
  .attempt-label{ font:700 12px/1 'Inter',sans-serif; color:var(--slate-600); }

  .progress-wrap{ margin-bottom:16px; }
  .progress-label{ text-align:center; font:800 12.5px/1 'Baloo 2',sans-serif; color:var(--slate-600); margin-bottom:8px; letter-spacing:.02em; }
  .progress-dots{ display:flex; gap:8px; justify-content:center; }
  .pdot{ width:10px;height:10px;border-radius:50%; background:var(--line); }
  .pdot.done{ background:var(--teal); }
  .pdot.current{ background:var(--owl-orange-500); transform:scale(1.3); }

  .card{
    position:relative; overflow:hidden;
    background:linear-gradient(180deg, #fbfff9, var(--surface) 40%); border-radius:30px; padding:32px 28px; text-align:center;
    box-shadow:0 30px 60px -28px rgba(15,60,110,0.25);
  }
  /* "Garden" theme accents — simple claymorphism leaf/flower shapes, the
     same soft-gradient-blob technique already used everywhere else in
     this app, just shaped like garden motifs instead of plain circles. */
  .garden-accent{ position:absolute; pointer-events:none; z-index:0; opacity:.85; }
  .garden-accent.leaf-tl{ top:-10px; left:-14px; width:86px; transform:rotate(-18deg); }
  .garden-accent.leaf-br{ bottom:-16px; right:-18px; width:100px; transform:rotate(160deg); }
  .garden-accent.petal-tr{ top:14px; right:22px; width:34px; }
  .garden-accent.petal-bl{ bottom:60px; left:16px; width:26px; }
  .card > *{ position:relative; z-index:1; }
  .clay-blob{ position:absolute; border-radius:50%; pointer-events:none; z-index:0; }
  .clay-blob.b1{ width:150px;height:150px; top:-60px; right:-50px; background:radial-gradient(circle, rgba(255,207,110,0.28), transparent 70%); }
  .clay-blob.b2{ width:120px;height:120px; bottom:-40px; left:-40px; background:radial-gradient(circle, rgba(28,126,214,0.1), transparent 70%); }

  .mascot{
    width:88px;height:88px;border-radius:26px; margin:0 auto 12px; padding:11px;
    background:linear-gradient(155deg, var(--clay-yellow), var(--owl-orange-600));
    display:flex;align-items:center;justify-content:center; box-shadow:0 16px 28px -12px rgba(221,112,20,0.5);
    animation:bob 2.4s ease-in-out infinite;
  }
  @keyframes bob{ 0%,100%{transform:translateY(0);} 50%{transform:translateY(-8px);} }
  h1{ font-family:'Baloo 2',sans-serif; font-size:24px; font-weight:700; margin:0 0 18px; }

  .slots{ display:flex; gap:9px; justify-content:center; flex-wrap:wrap; margin-bottom:28px; }
  .slot{
    width:52px; height:60px; border-radius:14px; background:var(--bg-0);
    box-shadow:inset 0 3px 6px rgba(19,31,43,0.12), inset 0 -1px 0 rgba(255,255,255,0.6);
    display:flex; align-items:center; justify-content:center;
    font-family:'Baloo 2',sans-serif; font-size:27px; font-weight:800; color:var(--success);
    text-transform:uppercase; transition:transform .2s cubic-bezier(.34,1.56,.64,1), background .15s ease;
  }
  .slot.filled{
    background:linear-gradient(155deg, var(--success-bg), #cdeee2);
    box-shadow:inset 0 2px 4px rgba(31,158,131,0.15), 0 4px 8px -4px rgba(31,158,131,0.35);
    animation:slotPop .3s cubic-bezier(.34,1.56,.64,1);
  }
  @keyframes slotPop{ 0%{ transform:scale(0.7); } 60%{ transform:scale(1.12); } 100%{ transform:scale(1); } }

  .tiles{ display:flex; gap:12px; justify-content:center; flex-wrap:wrap; margin-bottom:8px; }
  .tile{
    width:62px; height:66px; border-radius:18px; border:none;
    background:linear-gradient(155deg, #ffffff, #eaf1fb);
    font-family:'Baloo 2',sans-serif; font-size:29px; font-weight:800; color:var(--navy-900); text-transform:uppercase;
    cursor:pointer; box-shadow:0 10px 20px -12px rgba(19,31,43,0.3), inset 0 -3px 0 rgba(19,31,43,0.07), inset 0 2px 0 rgba(255,255,255,0.8);
    transition:transform .15s ease, box-shadow .15s ease, opacity .2s ease;
  }
  .tile:hover:not(:disabled){ transform:translateY(-3px) scale(1.04); box-shadow:0 16px 24px -12px rgba(239,141,42,0.5), inset 0 -3px 0 rgba(19,31,43,0.07), inset 0 2px 0 rgba(255,255,255,0.8); }
  .tile:active:not(:disabled){ transform:scale(0.92); }
  .tile:disabled{ opacity:0; pointer-events:none; }
  .tile.correct-tap{ animation:tileBounce .4s cubic-bezier(.34,1.56,.64,1); }
  @keyframes tileBounce{ 0%{ transform:scale(1); } 40%{ transform:scale(1.25) translateY(-8px) rotate(-4deg); } 100%{ transform:scale(1); } }
  .tile.wrong-tap{ animation:tileShake .35s ease; box-shadow:0 10px 20px -12px rgba(214,69,69,0.5), inset 0 0 0 2.5px var(--danger); }
  @keyframes tileShake{ 0%,100%{ transform:translateX(0); } 25%{ transform:translateX(-7px); } 75%{ transform:translateX(7px); } }

  .celebrate{ display:none; }
  .celebrate.show{ display:block; animation:pop .5s cubic-bezier(.34,1.56,.64,1); }
  @keyframes pop{ 0%{ transform:scale(0); } 70%{ transform:scale(1.15); } 100%{ transform:scale(1); } }
  .celebrate-mascot{
    width:88px;height:88px;border-radius:26px; margin:0 auto 8px; padding:11px;
    background:linear-gradient(155deg, var(--clay-yellow), var(--owl-orange-600));
    display:flex;align-items:center;justify-content:center; box-shadow:0 16px 28px -12px rgba(221,112,20,0.5);
  }
  .celebrate-text{ font-family:'Baloo 2',sans-serif; font-size:20px; font-weight:700; color:var(--success); }
  .level-up-text{ font-family:'Baloo 2',sans-serif; font-size:15px; font-weight:700; color:var(--owl-orange-600); margin-top:6px; }

  .big-btn{
    display:block; width:100%; padding:17px; border:none; border-radius:18px; font:800 15px/1 'Baloo 2',sans-serif;
    cursor:pointer; background:linear-gradient(155deg, var(--blue-500), var(--blue-700)); color:#fff;
    text-decoration:none; box-sizing:border-box; box-shadow:0 16px 26px -12px rgba(15,95,174,0.5);
    transition:transform .2s cubic-bezier(.34,1.56,.64,1); margin-top:8px;
  }
  .big-btn:hover{ transform:translateY(-2px) scale(1.02); }
  .big-btn:active{ transform:scale(0.97); }
  .back-link{
    display:inline-flex; align-items:center; gap:6px; font-size:14px; font-weight:700; color:var(--slate-600);
    text-decoration:none; margin-top:16px;
  }
  .back-link:hover{ color:var(--blue-600); }
  @media (prefers-reduced-motion: reduce){ *{ animation:none !important; } }
</style>
</head>
<body>
<div class="wrap">
  <div class="top-bar">
    <span class="level-badge" id="levelBadge">🌱 Level 1</span>
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
    <div class="clay-blob b1"></div>
    <div class="clay-blob b2"></div>

    <div id="playArea">
      <div class="mascot">@include('learner.games._owl-mascot', ['id' => 'wbOwlMain'])</div>
      <h1 id="promptText">Spell the word!</h1>
      <div class="slots" id="slots"></div>
      <div class="tiles" id="tiles"></div>
    </div>

    <div class="celebrate" id="celebrate">
      <div class="celebrate-mascot">@include('learner.games._owl-mascot', ['id' => 'wbOwlCelebrate'])</div>
      <p class="celebrate-text" id="celebrateText">Great job!</p>
      <p class="level-up-text" id="levelUpText" style="display:none;">⭐ Level up!</p>
    </div>

    <div class="celebrate" id="roundComplete">
      <div class="celebrate-mascot">@include('learner.games._owl-mascot', ['id' => 'wbOwlRoundComplete'])</div>
      <h1>All done!</h1>
      <p id="roundCompleteSummary" style="font-size:16px;font-weight:600;color:var(--slate-600);margin-bottom:20px;">Nice work!</p>
      <a href="{{ route('learner.games.word-builder') }}" class="big-btn">Play Again</a>
    </div>

    <a href="{{ route('learner.dashboard') }}" class="back-link" id="backLink" onclick="saveProgress()">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      Back to My Dashboard
    </a>
  </div>
</div>

@include('learner.games._game-sounds')

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

  const LEVEL_EMOJI = { 1: '🌱', 2: '🌿', 3: '🌳' };

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
        currentLevel, attemptCount, leveledUpDuringSession,
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

  function updateTopBar() {
    levelBadge.textContent = (LEVEL_EMOJI[currentLevel] || '🌱') + ' Level ' + currentLevel;
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
    celebrateText.textContent = '"' + roundWords[wordIndex].toUpperCase() + '" — you got it!';
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
    levelBadge.textContent = (LEVEL_EMOJI[currentLevel] || '🌱') + ' Level ' + currentLevel;
    attemptLabel.textContent = 'Complete!';
    playArea.style.display = 'none';
    document.getElementById('wbOwlRoundComplete').classList.add('is-celebrating');
    window.tarabasaPlaySfx('levelComplete', 0.6);
    roundCompleteSummary.textContent = 'You finished at Level ' + currentLevel + (justLeveledUp ? ' — great climbing!' : '. Nice work!');
    roundComplete.classList.add('show');
    backLink.style.display = 'none';
  }

  resumeOrStart();
</script>
</body>
</html>
