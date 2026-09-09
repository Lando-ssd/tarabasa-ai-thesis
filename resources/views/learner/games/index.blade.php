<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Practice Games — TaraBasa AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --sky-100:#dcedff;
    --blue-500:#1c7ed6; --blue-700:#0a3d73;
    --navy-900:#131f2b; --slate-600:#5b6b7a;
    --owl-orange-500:#ef8d2a; --owl-orange-600:#dd7014; --clay-yellow:#ffcf6e;
    --teal:#2bb89c; --purple:#6b4bc7; --purple-bg:#efe8fb;
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
  @media (min-width:700px){ .wrap{ max-width:620px; } }
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
    width:84px;height:84px;border-radius:26px; margin:0 auto 14px; padding:10px;
    background:linear-gradient(155deg, var(--clay-yellow), var(--owl-orange-600));
    display:flex;align-items:center;justify-content:center; box-shadow:0 16px 28px -12px rgba(221,112,20,0.5);
    animation:bob 2.4s ease-in-out infinite;
  }
  @keyframes bob{ 0%,100%{transform:translateY(0);} 50%{transform:translateY(-8px);} }
  h1{ font-family:'Baloo 2',sans-serif; font-size:25px; font-weight:700; margin:0 0 8px; }
  .sub{ font-size:18px; color:var(--slate-600); font-weight:600; margin:0 0 18px; line-height:1.5; }

  /* Adaptive connection — a real, disclosed signal (Learner::
     recommendedGameFocus()), never a fabricated one. Only rendered when
     there's something honest to say. */
  .adaptive-note{
    display:flex; align-items:center; gap:10px; text-align:left; background:#fff8ef;
    border:1.5px solid var(--owl-orange-500); border-radius:16px; padding:12px 14px; margin-bottom:18px;
  }
  .adaptive-note .dot{ width:8px;height:8px;border-radius:50%; background:var(--owl-orange-500); flex-shrink:0; }
  .adaptive-note p{ margin:0; font-size:13.5px; font-weight:700; color:#8a4e0a; line-height:1.4; }
  .steady-note{
    text-align:left; background:var(--success-bg,#e9f7f3); border:1.5px solid var(--success,#1f9e83); border-radius:16px;
    padding:12px 14px; margin-bottom:18px;
  }
  .steady-note p{ margin:0; font-size:13.5px; font-weight:700; color:#0d5c4b; line-height:1.4; }

  .game-list{ display:flex; flex-direction:column; gap:14px; margin-bottom:8px; }
  @media (min-width:700px){ .game-list{ flex-direction:row; } }
  .game-card{
    position:relative; display:block; flex:1; text-align:left; background:var(--bg-0); border:2px solid var(--line);
    border-radius:22px; padding:20px; text-decoration:none; color:var(--navy-900);
    transition:border-color .15s ease, transform .15s ease, box-shadow .15s ease;
  }
  .game-card:hover{ transform:translateY(-2px); }
  .game-card.builder:hover{ border-color:var(--owl-orange-500); }
  .game-card.matcher:hover{ border-color:var(--purple); }
  .game-card.is-recommended{ border-color:var(--owl-orange-500); box-shadow:0 14px 28px -18px rgba(221,112,20,0.5); }
  .recommended-badge{
    position:absolute; top:-11px; right:14px; display:inline-flex; align-items:center; gap:4px;
    font:800 11px/1 'Baloo 2',sans-serif; color:#8a4e0a; background:#fff0da; border:1.5px solid var(--owl-orange-500);
    border-radius:999px; padding:4px 10px;
  }
  .game-icon{ width:56px; height:56px; margin-bottom:12px; }
  .game-title{ font-family:'Baloo 2',sans-serif; font-size:19px; font-weight:700; margin:0 0 4px; }
  .game-desc{ font-size:14px; font-weight:600; color:var(--slate-600); line-height:1.4; margin:0; }

  .back-link{
    display:inline-flex; align-items:center; gap:6px; font-size:14.5px; font-weight:700; color:var(--slate-600);
    text-decoration:none; margin-top:18px;
  }
  .back-link:hover{ color:var(--blue-600); }
  a:focus-visible{ outline:2px solid var(--blue-500); outline-offset:2px; }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="clay-blob b1"></div>
    <div class="clay-blob b2"></div>
    <div class="mascot">@include('learner.games._owl-mascot', ['id' => 'hubOwl'])</div>
    <h1>Practice Games</h1>
    <p class="sub">No points, just fun practice! Pick a game to play.</p>

    @if ($recommendedGame)
      <div class="adaptive-note">
        <span class="dot"></span>
        <p>
          @if ($recommendedGame['game'] === 'word-builder')
            Your reading check shows spelling practice would help right now — Word Builder uses your own tricky words!
          @else
            Your reading check shows letter practice would help right now — try Letter Match!
          @endif
        </p>
      </div>
    @elseif ($hasCompetencyData)
      <div class="steady-note">
        <p>Your foundational skills are looking strong — keep sharpening them here anytime you want!</p>
      </div>
    @endif

    <div class="game-list">
      <a href="{{ route('learner.games.word-builder') }}" class="game-card builder {{ ($recommendedGame['game'] ?? null) === 'word-builder' ? 'is-recommended' : '' }}">
        @if (($recommendedGame['game'] ?? null) === 'word-builder')
          <span class="recommended-badge">⭐ Recommended</span>
        @endif
        <svg class="game-icon" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg">
          <defs>
            <linearGradient id="tileGrad" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0%" stop-color="#ffcf6e"/>
              <stop offset="100%" stop-color="#dd7014"/>
            </linearGradient>
          </defs>
          <rect x="6" y="24" width="18" height="20" rx="5" fill="#ffe4c2" transform="rotate(-8 15 34)"/>
          <rect x="21" y="18" width="20" height="24" rx="5" fill="url(#tileGrad)"/>
          <text x="31" y="35" font-family="Baloo 2, sans-serif" font-weight="800" font-size="16" fill="#fff" text-anchor="middle">A</text>
          <rect x="36" y="26" width="18" height="20" rx="5" fill="#fff0da" stroke="#f0b03e" stroke-width="1.5" transform="rotate(7 45 36)"/>
        </svg>
        <p class="game-title">Word Builder</p>
        <p class="game-desc">Tap the letters in order to spell each word.</p>
      </a>
      <a href="{{ route('learner.games.letter-match') }}" class="game-card matcher {{ ($recommendedGame['game'] ?? null) === 'letter-match' ? 'is-recommended' : '' }}">
        @if (($recommendedGame['game'] ?? null) === 'letter-match')
          <span class="recommended-badge">⭐ Recommended</span>
        @endif
        <svg class="game-icon" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg">
          <rect x="6" y="14" width="24" height="30" rx="7" fill="var(--purple)" transform="rotate(-6 18 29)"/>
          <text x="18" y="34" font-family="Baloo 2, sans-serif" font-weight="800" font-size="15" fill="#fff" text-anchor="middle" transform="rotate(-6 18 29)">A</text>
          <rect x="30" y="16" width="24" height="30" rx="7" fill="#efe8fb" stroke="var(--purple)" stroke-width="2" transform="rotate(6 42 31)"/>
          <text x="42" y="36" font-family="Baloo 2, sans-serif" font-weight="800" font-size="15" fill="var(--purple)" text-anchor="middle" transform="rotate(6 42 31)">a</text>
        </svg>
        <p class="game-title">Letter Match</p>
        <p class="game-desc">Find the matching pairs — big letter, small letter!</p>
      </a>
    </div>

    <a href="{{ route('learner.dashboard') }}" class="back-link">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      Back to My Dashboard
    </a>
  </div>
</div>
</body>
</html>
