<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $final ? 'Let’s try later' : 'Try again' }} — TaraBasa AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --sky-100:#dcedff;
    --blue-500:#1c7ed6; --blue-700:#0a3d73;
    --navy-900:#131f2b; --slate-600:#5b6b7a;
    --clay-yellow:#ffcf6e; --owl-orange-600:#dd7014;
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
    background:var(--surface); border-radius:30px; padding:32px 28px; text-align:center;
    box-shadow:0 30px 60px -28px rgba(15,60,110,0.25);
  }
  .mascot{
    width:88px;height:88px;border-radius:28px; margin:0 auto 16px; font-size:44px;
    background:linear-gradient(155deg, var(--clay-yellow), var(--owl-orange-600));
    display:flex;align-items:center;justify-content:center; box-shadow:0 16px 28px -12px rgba(221,112,20,0.5);
    animation:bob 2.4s ease-in-out infinite;
  }
  @keyframes bob{ 0%,100%{transform:translateY(0);} 50%{transform:translateY(-8px);} }
  h1{ font-family:'Baloo 2',sans-serif; font-size:24px; font-weight:700; margin:0 0 10px; }
  .sub{ font-size:18px; color:var(--slate-600); font-weight:600; margin:0 0 24px; line-height:1.55; }
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
    <div class="mascot">🦉</div>
    @if ($final)
      <h1>Please ask your Teacher or Parent for help</h1>
      <p class="sub">Tara couldn't quite hear that a few times in a row — a grown-up can help you try again later!</p>
      {{-- For the diagnostic specifically, this link naturally restarts a
           fresh attempt (the standing EnsureDiagnosticComplete guard sends
           an incomplete Learner right back here) rather than being a true
           dead end — the patch docs don't address this exact combination
           (diagnostic + repeated unclear) directly, so this is the most
           reasonable reading rather than inventing a separate escape route. --}}
      <a href="{{ route('learner.dashboard') }}" class="big-btn">Back to My Dashboard</a>
    @else
      <h1>Didn't quite catch that — try again!</h1>
      <p class="sub">No worries! Tap the mic and give it another go.</p>
      <a href="{{ ($isDiagnostic ?? false) ? route('learner.diagnostic.passage') : route('learner.activity.show', $activity) }}" class="big-btn">Try Again</a>
    @endif
  </div>
</div>
</body>
</html>
