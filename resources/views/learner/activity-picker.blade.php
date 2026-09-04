<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>What should I read? — TaraBasa AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --sky-100:#dcedff;
    --blue-500:#1c7ed6; --blue-700:#0a3d73;
    --navy-900:#131f2b; --slate-600:#5b6b7a;
    --owl-orange-500:#ef8d2a; --owl-orange-600:#dd7014; --clay-yellow:#ffcf6e;
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
  .card{
    background:var(--surface); border-radius:30px; padding:32px 28px; text-align:center;
    box-shadow:0 30px 60px -28px rgba(15,60,110,0.25);
  }
  .mascot{
    width:80px;height:80px;border-radius:26px; margin:0 auto 14px; font-size:40px;
    background:linear-gradient(155deg, var(--clay-yellow), var(--owl-orange-600));
    display:flex;align-items:center;justify-content:center; box-shadow:0 16px 28px -12px rgba(221,112,20,0.5);
  }
  h1{ font-family:'Baloo 2',sans-serif; font-size:25px; font-weight:700; margin:0 0 8px; }
  .sub{ font-size:18px; color:var(--slate-600); font-weight:600; margin:0 0 22px; line-height:1.5; }

  .option-list{ display:flex; flex-direction:column; gap:10px; margin-bottom:8px; }
  .option-card{
    display:block; text-align:left; background:var(--bg-0); border:2px solid var(--line); border-radius:18px;
    padding:14px 16px; text-decoration:none; color:var(--navy-900); transition:border-color .15s ease, transform .15s ease;
  }
  .option-card:hover{ border-color:var(--blue-500); transform:translateY(-1px); }
  .option-title{ font-family:'Baloo 2',sans-serif; font-size:18px; font-weight:700; margin:0 0 3px; }
  .option-label{
    display:inline-block; font-size:13px; font-weight:800; color:var(--blue-700); background:var(--sky-100);
    padding:2px 9px; border-radius:999px;
  }
  .option-label.extra-practice{ color:#5c3fb0; background:#efe9fc; }

  .big-btn{
    display:block; width:100%; padding:16px; border:none; border-radius:18px; font:800 15px/1 'Baloo 2',sans-serif;
    cursor:pointer; background:linear-gradient(155deg, var(--blue-500), var(--blue-700)); color:#fff;
    text-decoration:none; box-sizing:border-box; box-shadow:0 16px 26px -12px rgba(15,95,174,0.5);
    transition:transform .2s cubic-bezier(.34,1.56,.64,1); margin-top:14px;
  }
  .big-btn:hover{ transform:translateY(-2px) scale(1.02); }
  a:focus-visible{ outline:2px solid var(--blue-500); outline-offset:2px; }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="mascot">🦉</div>

    @if ($options->isEmpty())
      <h1>No activity yet</h1>
      <p class="sub">Ask your teacher, or check back soon!</p>
      <a href="{{ route('learner.dashboard') }}" class="big-btn">Back to My Dashboard</a>
    @else
      <h1>What should I read?</h1>
      <p class="sub">Pick one to get started.</p>
      <div class="option-list">
        @foreach ($options as $option)
          <a href="{{ route('learner.activity.show', $option['activity']) }}" class="option-card">
            <div class="option-title">{{ $option['activity']->title }}</div>
            <span class="option-label {{ $option['source'] === 'Extra Practice' ? 'extra-practice' : '' }}">{{ $option['source'] }}</span>
          </a>
        @endforeach
      </div>
    @endif
  </div>
</div>
</body>
</html>
