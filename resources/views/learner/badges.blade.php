<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Badges — TaraBasa AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --sky-100:#dcedff;
    --blue-500:#1c7ed6; --blue-600:#0f5fae; --blue-700:#0a3d73;
    --navy-900:#131f2b; --slate-600:#5b6b7a;
    --owl-orange-600:#dd7014; --clay-yellow:#ffcf6e;
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
  @media (min-width:700px){ .wrap{ max-width:640px; } }
  .card{
    background:var(--surface); border-radius:30px; padding:32px 28px; text-align:center;
    box-shadow:0 30px 60px -28px rgba(15,60,110,0.25);
  }
  .mascot{
    width:72px;height:72px;border-radius:22px; margin:0 auto 14px; font-size:36px;
    background:linear-gradient(155deg, var(--clay-yellow), var(--owl-orange-600));
    display:flex;align-items:center;justify-content:center; box-shadow:0 16px 28px -12px rgba(221,112,20,0.5);
  }
  h1{ font-family:'Baloo 2',sans-serif; font-size:25px; font-weight:700; margin:0 0 8px; }
  .sub{ font-size:18px; color:var(--slate-600); font-weight:600; margin:0 0 22px; line-height:1.5; }

  .badge-grid{ display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:8px; }
  @media (min-width:700px){ .badge-grid{ grid-template-columns:1fr 1fr 1fr; } }
  .badge-tile{ border-radius:20px; padding:18px 12px; text-align:center; }
  .badge-tile.earned{
    background:linear-gradient(155deg,#fff3c4,#f0b03e); box-shadow:0 14px 24px -14px rgba(240,176,62,0.55);
  }
  .badge-tile.locked{ background:var(--bg-0); border:2px dashed var(--line); }
  .badge-tile-icon{ font-size:38px; line-height:1; margin-bottom:8px; }
  .badge-tile.locked .badge-tile-icon{ filter:grayscale(1); opacity:.45; }
  .badge-tile-name{ font-family:'Baloo 2',sans-serif; font-size:14.5px; font-weight:800; margin:0 0 4px; }
  .badge-tile.earned .badge-tile-name{ color:#5a3d00; }
  .badge-tile.locked .badge-tile-name{ color:var(--slate-600); }
  .badge-tile-desc{ font-size:12px; font-weight:600; line-height:1.35; margin:0 0 8px; }
  .badge-tile.earned .badge-tile-desc{ color:#7a5400; }
  .badge-tile.locked .badge-tile-desc{ color:var(--slate-600); opacity:.85; }
  .badge-tile-date{ font-size:11px; font-weight:800; color:#5a3d00; margin:0; }
  .badge-tile-locked-label{ font-size:11px; font-weight:800; color:var(--slate-600); margin:0; }

  .back-link{
    display:inline-flex; align-items:center; gap:6px; font-size:14.5px; font-weight:700; color:var(--slate-600);
    text-decoration:none; margin-top:22px;
  }
  .back-link:hover{ color:var(--blue-600); }
  a:focus-visible{ outline:2px solid var(--blue-500); outline-offset:2px; }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="mascot">🏆</div>
    <h1>My Badges</h1>
    <p class="sub">Keep reading to earn them all!</p>

    <div class="badge-grid">
      @foreach ($badges as $badge)
        <div class="badge-tile {{ $badge['earned'] ? 'earned' : 'locked' }}">
          <div class="badge-tile-icon">{{ $badge['emoji'] }}</div>
          <p class="badge-tile-name">{{ $badge['name'] }}</p>
          <p class="badge-tile-desc">{{ $badge['description'] }}</p>
          @if ($badge['earned'])
            <p class="badge-tile-date">Earned {{ $badge['earnedAt']->format('M j, Y') }}</p>
          @else
            <p class="badge-tile-locked-label">🔒 Not yet</p>
          @endif
        </div>
      @endforeach
    </div>

    <a href="{{ route('learner.dashboard') }}" class="back-link">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      Back to My Dashboard
    </a>
  </div>
</div>
</body>
</html>
