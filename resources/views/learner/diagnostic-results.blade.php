<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Great reading! — TaraBasa AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --sky-100:#dcedff;
    --blue-500:#1c7ed6; --blue-700:#0a3d73;
    --navy-900:#131f2b; --slate-600:#5b6b7a;
    --teal:#2bb89c;
    --surface:#ffffff; --bg-0:#f6faff;
  }
  *{box-sizing:border-box;} html,body{margin:0;padding:0;}
  body{
    min-height:100vh; font-family:'Inter',sans-serif; color:var(--navy-900);
    background: radial-gradient(1200px 700px at 90% -10%, var(--sky-100), transparent 55%),
                radial-gradient(900px 600px at 0% 100%, #ffe9c9, transparent 50%), var(--bg-0);
    background-attachment:fixed;
    display:flex; align-items:safe center; justify-content:center; padding:24px;
  }
  .wrap{ width:100%; max-width:440px; }
  .card{
    background:var(--surface); border-radius:30px; padding:34px 28px; text-align:center;
    box-shadow:0 30px 60px -28px rgba(15,60,110,0.25);
  }
  .star-badge{
    width:110px;height:110px;border-radius:50%; margin:0 auto 16px; font-size:56px;
    background:linear-gradient(155deg, #fff3c4, #f0b03e); display:flex;align-items:center;justify-content:center;
    box-shadow:0 18px 30px -14px rgba(240,176,62,0.55);
    animation:pop .5s cubic-bezier(.34,1.56,.64,1);
  }
  @keyframes pop{ 0%{ transform:scale(0); } 70%{ transform:scale(1.15); } 100%{ transform:scale(1); } }
  h1{ font-family:'Baloo 2',sans-serif; font-size:25px; font-weight:700; margin:0 0 10px; }
  .sub{ font-size:18px; color:var(--slate-600); font-weight:600; margin:0 0 20px; line-height:1.55; }
  .badge-note{
    display:inline-flex; align-items:center; gap:8px; background:linear-gradient(155deg,#fff3c4,#f0b03e);
    color:#5a3d00; font:800 14px/1 'Baloo 2',sans-serif; padding:10px 18px; border-radius:999px; margin-bottom:16px;
  }
  .result-level{
    display:inline-flex; align-items:center; gap:8px; background:linear-gradient(155deg,#d6f5ea,var(--teal));
    color:#08352c; font:800 14.5px/1 'Baloo 2',sans-serif; padding:10px 20px; border-radius:999px; margin-bottom:24px;
  }
  .big-btn{
    display:block; width:100%; padding:17px; border:none; border-radius:20px; font:800 16px/1 'Baloo 2',sans-serif;
    cursor:pointer; background:linear-gradient(155deg, var(--blue-500), var(--blue-700)); color:#fff;
    text-decoration:none; box-sizing:border-box; box-shadow:0 16px 26px -12px rgba(15,95,174,0.5);
    transition:transform .2s cubic-bezier(.34,1.56,.64,1);
  }
  .big-btn:hover{ transform:translateY(-2px) scale(1.02); }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    {{-- Part 4.2: no visible score or pass/fail framing, ever — the
         child should feel exactly as celebrated regardless of which
         level they landed on. $resultLabel is a warm, non-numeric phrase
         (matching tarabasa-learner-diagnostic__1_.html's own Result step),
         never a raw accuracy percentage or grade. --}}
    <div class="star-badge">⭐</div>
    <h1>Great reading, {{ $learner->first_name }}! 🎉</h1>
    <p class="sub">You earned your first badge — the First Reading Star!</p>
    <div class="badge-note">🌟 First Reading Star</div>
    <div><div class="result-level">{{ $resultLabel }}</div></div>
    <p class="sub">You're ready to start your first activity.</p>
    <a href="{{ route('learner.dashboard') }}" class="big-btn">Start Exploring! 🚀</a>
  </div>
</div>
</body>
</html>
