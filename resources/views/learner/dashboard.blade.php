<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Dashboard — TaraBasa AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --sky-100:#dcedff;
    --blue-500:#1c7ed6; --blue-600:#0f5fae; --blue-700:#0a3d73;
    --navy-900:#131f2b; --slate-600:#5b6b7a;
    --owl-orange-500:#ef8d2a; --owl-orange-600:#dd7014; --clay-yellow:#ffcf6e;
    --teal:#2bb89c;
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

  .avatar-big{
    width:96px;height:96px;border-radius:30px; margin:0 auto 14px; font-size:48px; overflow:hidden;
    background:linear-gradient(155deg, var(--clay-yellow), var(--owl-orange-600));
    display:flex;align-items:center;justify-content:center; box-shadow:0 16px 28px -12px rgba(221,112,20,0.5);
  }
  .avatar-photo-img{ width:100%; height:100%; object-fit:cover; border-radius:inherit; display:block; }
  h1{ font-family:'Baloo 2',sans-serif; font-size:26px; font-weight:700; margin:0 0 6px; }
  .grade-line{ font-size:18px; color:var(--slate-600); font-weight:700; margin:0 0 22px; }

  .stat-row{ display:flex; gap:10px; margin-bottom:24px; }
  .stat-tile{ flex:1; background:var(--bg-0); border:2px solid var(--line); border-radius:20px; padding:16px 6px; }
  .stat-tile .v{ font-family:'Baloo 2',sans-serif; font-size:24px; font-weight:800; }
  .stat-tile .l{ font-size:13px; font-weight:700; color:var(--slate-600); text-transform:uppercase; letter-spacing:.03em; margin-top:4px; }
  .stat-tile.level .v{ color:var(--teal); font-size:18px; }

  .big-btn{
    display:block; width:100%; padding:18px; border:none; border-radius:20px; font:800 17px/1 'Baloo 2',sans-serif;
    cursor:pointer; background:linear-gradient(155deg, var(--blue-500), var(--blue-700)); color:#fff; text-decoration:none;
    box-sizing:border-box; box-shadow:0 16px 26px -12px rgba(15,95,174,0.5);
    transition:transform .2s cubic-bezier(.34,1.56,.64,1); margin-bottom:16px;
  }
  .big-btn:hover{ transform:translateY(-2px) scale(1.02); }
  .big-btn:active{ transform:scale(0.97); }

  .switch-btn{ background:none; border:none; color:var(--slate-600); font:700 14.5px/1 'Inter',sans-serif; cursor:pointer; padding:6px; }
  .switch-btn:hover{ color:var(--blue-600); }
  button:focus-visible, a:focus-visible{ outline:2px solid var(--blue-500); outline-offset:2px; }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="avatar-big">
      @if ($learner->avatar_photo_path)
        <img src="{{ Storage::url($learner->avatar_photo_path) }}" alt="{{ $learner->first_name }}" class="avatar-photo-img">
      @else
        {{ $learner->avatar_id }}
      @endif
    </div>
    <h1>Hi, {{ $learner->first_name }}!</h1>
    <p class="grade-line">{{ $learner->grade_level }}</p>

    <div class="stat-row">
      <div class="stat-tile level">
        <div class="v">{{ $learner->mastery_level ?? 'New' }}</div>
        <div class="l">Level</div>
      </div>
      <div class="stat-tile">
        <div class="v">{{ $learner->points }}</div>
        <div class="l">Points</div>
      </div>
      <div class="stat-tile">
        <div class="v">🔥 {{ $learner->streak }}</div>
        <div class="l">Streak</div>
      </div>
    </div>

    <a href="{{ route('learner.activity.find') }}" class="big-btn">Start Reading Activity</a>

    <form method="POST" action="{{ route('learner.logout') }}">
      @csrf
      <button type="submit" class="switch-btn">Not you? Switch learner</button>
    </form>
  </div>
</div>
</body>
</html>
