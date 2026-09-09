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
  .wrap{ width:100%; max-width:460px; }
  @media (min-width:700px){ .wrap{ max-width:600px; } }

  .progress-wrap{ margin-bottom:16px; }
  .progress-label{ text-align:center; font:800 12.5px/1 'Baloo 2',sans-serif; color:var(--slate-600); margin-bottom:8px; letter-spacing:.02em; }
  .progress-dots{ display:flex; gap:8px; justify-content:center; }
  .pdot{ width:10px;height:10px;border-radius:50%; background:var(--line); }
  .pdot.done{ background:var(--teal); }
  .pdot.current{ background:var(--owl-orange-500); transform:scale(1.3); }

  .card{
    position:relative; overflow:hidden;
    background:var(--surface); border-radius:30px; padding:32px 28px; text-align:center;
    box-shadow:0 30px 60px -28px rgba(15,60,110,0.25);
  }
  .clay-blob{ position:absolute; border-radius:50%; pointer-events:none; z-index:0; }
  .clay-blob.b1{ width:150px;height:150px; top:-60px; right:-50px; background:radial-gradient(circle, rgba(255,207,110,0.35), transparent 70%); }
  .clay-blob.b2{ width:120px;height:120px; bottom:-40px; left:-40px; background:radial-gradient(circle, rgba(28,126,214,0.12), transparent 70%); }
  .card > *{ position:relative; z-index:1; }
  .mascot{
    width:76px;height:76px;border-radius:24px; margin:0 auto 12px; padding:9px;
    background:linear-gradient(155deg, var(--clay-yellow), var(--owl-orange-600));
    display:flex;align-items:center;justify-content:center; box-shadow:0 16px 28px -12px rgba(221,112,20,0.5);
    animation:bob 2.4s ease-in-out infinite;
  }
  @keyframes bob{ 0%,100%{transform:translateY(0);} 50%{transform:translateY(-8px);} }
  h1{ font-family:'Baloo 2',sans-serif; font-size:23px; font-weight:700; margin:0 0 18px; }

  .slots{ display:flex; gap:8px; justify-content:center; flex-wrap:wrap; margin-bottom:26px; }
  .slot{
    width:44px; height:52px; border-radius:12px; background:var(--bg-0);
    box-shadow:inset 0 3px 6px rgba(19,31,43,0.12), inset 0 -1px 0 rgba(255,255,255,0.6);
    display:flex; align-items:center; justify-content:center;
    font-family:'Baloo 2',sans-serif; font-size:24px; font-weight:800; color:var(--success);
    text-transform:uppercase; transition:transform .2s cubic-bezier(.34,1.56,.64,1), background .15s ease;
  }
  .slot.filled{
    background:linear-gradient(155deg, var(--success-bg), #cdeee2);
    box-shadow:inset 0 2px 4px rgba(31,158,131,0.15), 0 4px 8px -4px rgba(31,158,131,0.35);
    animation:slotPop .3s cubic-bezier(.34,1.56,.64,1);
  }
  @keyframes slotPop{ 0%{ transform:scale(0.7); } 60%{ transform:scale(1.12); } 100%{ transform:scale(1); } }

  .tiles{ display:flex; gap:10px; justify-content:center; flex-wrap:wrap; margin-bottom:8px; }
  .tile{
    width:52px; height:56px; border-radius:16px; border:none;
    background:linear-gradient(155deg, #ffffff, #eaf1fb);
    font-family:'Baloo 2',sans-serif; font-size:26px; font-weight:800; color:var(--navy-900); text-transform:uppercase;
    cursor:pointer; box-shadow:0 10px 20px -12px rgba(19,31,43,0.3), inset 0 -3px 0 rgba(19,31,43,0.07), inset 0 2px 0 rgba(255,255,255,0.8);
    transition:transform .15s ease, box-shadow .15s ease, opacity .2s ease;
  }
  .tile:hover:not(:disabled){ transform:translateY(-2px); box-shadow:0 14px 22px -12px rgba(239,141,42,0.45), inset 0 -3px 0 rgba(19,31,43,0.07), inset 0 2px 0 rgba(255,255,255,0.8); }
  .tile:active:not(:disabled){ transform:scale(0.94); }
  .tile:disabled{ opacity:0; pointer-events:none; }
  .tile.correct-tap{ animation:tileBounce .35s cubic-bezier(.34,1.56,.64,1); }
  @keyframes tileBounce{ 0%{ transform:scale(1); } 40%{ transform:scale(1.2) translateY(-6px); } 100%{ transform:scale(1); } }
  .tile.wrong-tap{ animation:tileShake .35s ease; box-shadow:0 10px 20px -12px rgba(214,69,69,0.5), inset 0 0 0 2.5px var(--danger); }
  @keyframes tileShake{ 0%,100%{ transform:translateX(0); } 25%{ transform:translateX(-6px); } 75%{ transform:translateX(6px); } }

  .celebrate{ display:none; }
  .celebrate.show{ display:block; animation:pop .5s cubic-bezier(.34,1.56,.64,1); }
  @keyframes pop{ 0%{ transform:scale(0); } 70%{ transform:scale(1.15); } 100%{ transform:scale(1); } }
  .celebrate-mascot{
    width:76px;height:76px;border-radius:24px; margin:0 auto 8px; padding:9px;
    background:linear-gradient(155deg, var(--clay-yellow), var(--owl-orange-600));
    display:flex;align-items:center;justify-content:center; box-shadow:0 16px 28px -12px rgba(221,112,20,0.5);
  }
  .celebrate-text{ font-family:'Baloo 2',sans-serif; font-size:20px; font-weight:700; color:var(--success); }

  .big-btn{
    display:block; width:100%; padding:16px; border:none; border-radius:18px; font:800 15px/1 'Baloo 2',sans-serif;
    cursor:pointer; background:linear-gradient(155deg, var(--blue-500), var(--blue-700)); color:#fff;
    text-decoration:none; box-sizing:border-box; box-shadow:0 16px 26px -12px rgba(15,95,174,0.5);
    transition:transform .2s cubic-bezier(.34,1.56,.64,1); margin-top:8px;
  }
  .big-btn:hover{ transform:translateY(-2px) scale(1.02); }
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
  <div class="progress-wrap">
    <div class="progress-label" id="progressLabel">Word 1 of {{ count($words) }}</div>
    <div class="progress-dots" id="progressDots"></div>
  </div>

  <div class="card">
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
    </div>

    <div class="celebrate" id="roundComplete">
      <div class="celebrate-mascot">@include('learner.games._owl-mascot', ['id' => 'wbOwlRoundComplete'])</div>
      <h1>All done!</h1>
      <p style="font-size:16px;font-weight:600;color:var(--slate-600);margin-bottom:20px;">You spelled all {{ count($words) }} words. Nice work!</p>
      <a href="{{ route('learner.games.word-builder') }}" class="big-btn">Play Again</a>
    </div>

    <a href="{{ route('learner.games.index') }}" class="back-link" id="backLink">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      Back to Games
    </a>
  </div>
</div>

<script>
  const WORDS = @json($words);
  let wordIndex = 0;
  let letters = [];
  let used = [];
  let spelled = '';

  const progressLabel = document.getElementById('progressLabel');
  const progressDots = document.getElementById('progressDots');
  const slotsEl = document.getElementById('slots');
  const tilesEl = document.getElementById('tiles');
  const playArea = document.getElementById('playArea');
  const celebrate = document.getElementById('celebrate');
  const celebrateText = document.getElementById('celebrateText');
  const roundComplete = document.getElementById('roundComplete');
  const backLink = document.getElementById('backLink');

  function renderDots() {
    progressDots.innerHTML = '';
    for (let i = 0; i < WORDS.length; i++) {
      const dot = document.createElement('div');
      dot.className = 'pdot' + (i < wordIndex ? ' done' : (i === wordIndex ? ' current' : ''));
      progressDots.appendChild(dot);
    }
  }

  function shuffle(arr) {
    const a = arr.slice();
    for (let i = a.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [a[i], a[j]] = [a[j], a[i]];
    }
    return a;
  }

  function startWord() {
    const word = WORDS[wordIndex];
    letters = shuffle(word.split(''));
    // A shuffle can coincidentally land back in the original order for a
    // short word — reshuffle once more if so, purely cosmetic (the game
    // logic works fine either way, but a "scrambled" word that isn't
    // actually scrambled looks like a bug).
    if (letters.join('') === word && word.length > 1) {
      letters = shuffle(letters);
    }
    used = new Array(letters.length).fill(false);
    spelled = '';
    progressLabel.textContent = 'Word ' + (wordIndex + 1) + ' of ' + WORDS.length;
    renderDots();
    renderSlots();
    renderTiles();
  }

  function renderSlots() {
    const word = WORDS[wordIndex];
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
    const word = WORDS[wordIndex];
    const expected = word[spelled.length];

    if (letters[i].toLowerCase() === expected) {
      used[i] = true;
      spelled += letters[i];
      btn.classList.add('correct-tap');
      renderSlots();
      btn.disabled = true;

      if (spelled.length === word.length) {
        setTimeout(wordComplete, 350);
      }
    } else {
      btn.classList.remove('wrong-tap');
      void btn.offsetWidth;
      btn.classList.add('wrong-tap');
      nudgeOwl();
    }
  }

  // A brief, warm "try again" tilt on the main owl — never a sad
  // expression, just a gentle reassuring nudge, retriggerable even if
  // the previous nudge's animation hasn't finished yet.
  function nudgeOwl() {
    const owl = document.getElementById('wbOwlMain');
    owl.classList.remove('is-encouraging');
    void owl.offsetWidth;
    owl.classList.add('is-encouraging');
  }

  function wordComplete() {
    playArea.style.display = 'none';
    celebrateText.textContent = '"' + WORDS[wordIndex].toUpperCase() + '" — you got it!';
    document.getElementById('wbOwlCelebrate').classList.add('is-celebrating');
    celebrate.classList.add('show');

    setTimeout(() => {
      celebrate.classList.remove('show');
      document.getElementById('wbOwlCelebrate').classList.remove('is-celebrating');
      playArea.style.display = '';
      wordIndex++;
      if (wordIndex >= WORDS.length) {
        finishRound();
      } else {
        startWord();
      }
    }, 1300);
  }

  function finishRound() {
    renderDots();
    progressLabel.textContent = 'Word ' + WORDS.length + ' of ' + WORDS.length;
    playArea.style.display = 'none';
    document.getElementById('wbOwlRoundComplete').classList.add('is-celebrating');
    roundComplete.classList.add('show');
    backLink.style.display = 'none';
  }

  startWord();
</script>
</body>
</html>
