<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Letter Match — TaraBasa AI</title>
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
    --purple:#6b4bc7; --purple-bg:#efe8fb;
    --danger:#d64545;
    --confetti-1:#ff9ec4; --confetti-2:#ffcf6e; --confetti-3:#7ecbe8; --confetti-4:#b9e28c;
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
  @media (min-width:700px){ .wrap{ max-width:660px; } }

  .top-bar{ display:flex; align-items:center; justify-content:center; gap:10px; margin-bottom:14px; flex-wrap:wrap; }
  .level-badge{
    display:inline-flex; align-items:center; gap:5px; font:800 12.5px/1 'Baloo 2',sans-serif; color:#4a2f8f;
    background:var(--purple-bg); border:1.5px solid var(--purple); border-radius:999px; padding:5px 12px;
  }
  .attempt-label{ font:700 12px/1 'Inter',sans-serif; color:var(--slate-600); }

  .progress-wrap{ margin-bottom:16px; }
  .progress-label{ text-align:center; font:800 12.5px/1 'Baloo 2',sans-serif; color:var(--slate-600); margin-bottom:8px; letter-spacing:.02em; }
  .progress-dots{ display:flex; gap:8px; justify-content:center; }
  .pdot{ width:10px;height:10px;border-radius:50%; background:var(--line); }
  .pdot.done{ background:var(--purple); }
  .pdot.current{ background:var(--owl-orange-500); transform:scale(1.3); }

  .card{
    position:relative; overflow:hidden;
    background:var(--surface); border-radius:30px; padding:28px 24px; text-align:center;
    box-shadow:0 30px 60px -28px rgba(15,60,110,0.25);
  }
  /* "Colorful pattern" theme accents — small scattered claymorphism
     confetti shapes (same soft-blob technique as elsewhere, just tinted
     and shaped playfully), not a new heavy background image. */
  .confetti{ position:absolute; border-radius:6px; pointer-events:none; z-index:0; opacity:.5; }
  .confetti.c1{ top:16px; left:20px; width:14px; height:14px; background:var(--confetti-1); transform:rotate(18deg); border-radius:50%; }
  .confetti.c2{ top:36px; right:34px; width:12px; height:12px; background:var(--confetti-3); transform:rotate(-12deg); }
  .confetti.c3{ bottom:26px; left:36px; width:16px; height:16px; background:var(--confetti-2); border-radius:50%; }
  .confetti.c4{ bottom:50px; right:22px; width:13px; height:13px; background:var(--confetti-4); transform:rotate(30deg); }
  .confetti.c5{ top:80px; left:8px; width:10px; height:10px; background:var(--confetti-2); border-radius:50%; }
  .confetti.c6{ top:100px; right:10px; width:11px; height:11px; background:var(--confetti-1); transform:rotate(-20deg); }
  .clay-blob{ position:absolute; border-radius:50%; pointer-events:none; z-index:0; }
  .clay-blob.b1{ width:150px;height:150px; top:-60px; right:-50px; background:radial-gradient(circle, rgba(255,207,110,0.28), transparent 70%); }
  .clay-blob.b2{ width:120px;height:120px; bottom:-40px; left:-40px; background:radial-gradient(circle, rgba(28,126,214,0.1), transparent 70%); }
  .card > *{ position:relative; z-index:1; }
  .mascot{
    width:72px;height:72px;border-radius:22px; margin:0 auto 10px; padding:9px;
    background:linear-gradient(155deg, var(--clay-yellow), var(--owl-orange-600));
    display:flex;align-items:center;justify-content:center; box-shadow:0 16px 28px -12px rgba(221,112,20,0.5);
    animation:bob 2.4s ease-in-out infinite;
  }
  @keyframes bob{ 0%,100%{transform:translateY(0);} 50%{transform:translateY(-8px);} }
  h1{ font-family:'Baloo 2',sans-serif; font-size:22px; font-weight:700; margin:0 0 16px; }

  .grid{
    display:grid; grid-template-columns:repeat(auto-fit, minmax(68px, 1fr)); gap:10px; margin-bottom:8px;
  }
  .mcard{
    aspect-ratio:1; border-radius:16px; border:none; cursor:pointer; position:relative;
    background:linear-gradient(155deg, var(--blue-500), var(--blue-700));
    box-shadow:0 8px 16px -10px rgba(15,95,174,0.5), inset 0 -3px 0 rgba(10,61,115,0.4), inset 0 2px 0 rgba(255,255,255,0.25);
    font-family:'Baloo 2',sans-serif; font-size:28px; font-weight:800; color:#fff;
    display:flex; align-items:center; justify-content:center;
    transition:transform .18s ease;
  }
  .mcard:hover:not(:disabled){ transform:scale(1.06) translateY(-2px); }
  .mcard:disabled{ cursor:default; }
  .mcard.flipped{
    background:linear-gradient(155deg, #ffffff, #fff3e2); color:var(--navy-900);
    box-shadow:0 8px 16px -10px rgba(19,31,43,0.2), inset 0 -2px 0 rgba(240,176,62,0.4), inset 0 2px 0 rgba(255,255,255,0.9);
  }
  .mcard.matched{
    background:linear-gradient(155deg, var(--success-bg), #cdeee2); color:var(--success);
    box-shadow:0 8px 16px -10px rgba(31,158,131,0.35), inset 0 -2px 0 rgba(31,158,131,0.2);
    animation:matchPop .35s cubic-bezier(.34,1.56,.64,1);
  }
  .mcard.matched .mcard-spark{ position:absolute; top:-7px; right:-7px; width:18px; height:18px; }
  @keyframes matchPop{ 0%{ transform:scale(1); } 50%{ transform:scale(1.18); } 100%{ transform:scale(1); } }
  .mcard.wrong{ animation:cardShake .35s ease; box-shadow:0 8px 16px -10px rgba(214,69,69,0.5), inset 0 0 0 2.5px var(--danger); }
  @keyframes cardShake{ 0%,100%{ transform:translateX(0); } 25%{ transform:translateX(-6px); } 75%{ transform:translateX(6px); } }
  @media (prefers-reduced-motion: reduce){ *{ animation:none !important; } }

  .celebrate{ display:none; }
  .celebrate.show{ display:block; animation:pop .5s cubic-bezier(.34,1.56,.64,1); }
  @keyframes pop{ 0%{ transform:scale(0); } 70%{ transform:scale(1.15); } 100%{ transform:scale(1); } }
  .celebrate-mascot{
    width:72px;height:72px;border-radius:22px; margin:0 auto 8px; padding:9px;
    background:linear-gradient(155deg, var(--clay-yellow), var(--owl-orange-600));
    display:flex;align-items:center;justify-content:center; box-shadow:0 16px 28px -12px rgba(221,112,20,0.5);
  }
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
</style>
</head>
<body>
<div class="wrap">
  <div class="top-bar">
    <span class="level-badge" id="levelBadge">🧩 Level 1</span>
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
    <div class="clay-blob b1"></div>
    <div class="clay-blob b2"></div>

    <div id="playArea">
      <div class="mascot">@include('learner.games._owl-mascot', ['id' => 'lmOwlMain'])</div>
      <h1>Find the matching pairs!</h1>
      <div class="grid" id="grid"></div>
    </div>

    <div class="celebrate" id="subRoundComplete">
      <div class="celebrate-mascot">@include('learner.games._owl-mascot', ['id' => 'lmOwlSubComplete'])</div>
      <p style="font-family:'Baloo 2',sans-serif;font-size:18px;font-weight:700;color:var(--success);">Nice matching!</p>
    </div>

    <div class="celebrate" id="roundComplete">
      <div class="celebrate-mascot">@include('learner.games._owl-mascot', ['id' => 'lmOwlRoundComplete'])</div>
      <h1>All done!</h1>
      <p id="roundCompleteSummary" style="font-size:16px;font-weight:600;color:var(--slate-600);margin-bottom:20px;">Great job!</p>
      <a href="{{ route('learner.games.letter-match') }}" class="big-btn">Play Again</a>
    </div>

    <a href="{{ route('learner.games.index') }}" class="back-link" id="backLink">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      Back to Games
    </a>
  </div>
</div>

@include('learner.games._game-sounds')

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

  const LEVEL_EMOJI = { 1: '🧩', 2: '🧩', 3: '🏆' };

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

  function updateTopBar() {
    levelBadge.textContent = (LEVEL_EMOJI[currentLevel] || '🧩') + ' Level ' + currentLevel;
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
  }

  function renderGrid() {
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
          setTimeout(subRoundDone, 400);
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
    subRoundComplete.querySelector('p').textContent = '⭐ Level up!';
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
    levelBadge.textContent = (LEVEL_EMOJI[currentLevel] || '🧩') + ' Level ' + currentLevel;
    attemptLabel.textContent = 'Complete!';
    playArea.style.display = 'none';
    document.getElementById('lmOwlRoundComplete').classList.add('is-celebrating');
    window.tarabasaPlaySfx('levelComplete', 0.6);
    roundCompleteSummary.textContent = 'You finished at Level ' + currentLevel + (justLeveledUp ? ' — great climbing!' : '. Great job!');
    roundComplete.classList.add('show');
    backLink.style.display = 'none';
  }

  startAttempt();
</script>
</body>
</html>
