<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>@yield('title', 'TaraBasa AI')</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  {{-- Same locked brand tokens as layouts/auth.blade.php (Register/etc keep
       that file untouched) — --blue-500/600/700 are the exact blue already
       used everywhere else in the app, including the logo's own backdrop,
       so nothing new is introduced here. --}}
  :root{
    --sky-50:#eef6ff; --sky-100:#dcedff;
    --blue-500:#1c7ed6; --blue-600:#0f5fae; --blue-700:#0a3d73;
    --navy-900:#131f2b; --slate-600:#5b6b7a; --slate-400:#8a97a3; --slate-300:#c3ccd4;
    --danger:#d64545; --danger-bg:#fdecec; --danger-border:#f3c9c9;
    --success:#1f9e83; --success-bg:#e9f7f3;
    --amber:#c9820b; --amber-bg:#fef6e6;
    --line:#e3ebf2; --surface:#ffffff; --field-bg:#f1f4f7;
    --orange:#f0982c;
  }
  *{box-sizing:border-box;}
  html,body{margin:0;padding:0;}
  body{
    min-height:100vh; font-family:'Inter',sans-serif; color:var(--navy-900);
    background:#ffffff;
    display:flex; align-items:safe center; justify-content:center;
    padding:32px 20px 48px;
  }
  .screen{ width:100%; max-width:360px; position:relative; animation:rise .5s ease both; }
  @keyframes rise{ from{ opacity:0; transform:translateY(14px); } to{ opacity:1; transform:translateY(0); } }

  .home-link{
    position:absolute; top:-6px; left:0; display:inline-flex; align-items:center; gap:6px;
    color:var(--slate-600); font:600 13px/1 'Inter',sans-serif; text-decoration:none; padding:6px 2px; transition:color .15s ease;
  }
  .home-link:hover{ color:var(--blue-600); }
  .home-link svg{ flex-shrink:0; transition:transform .15s ease; }
  .home-link:hover svg{ transform:translateX(-2px); }

  .col{ display:flex; flex-direction:column; align-items:center; text-align:center; padding-top:44px; }

  .lockup{ margin-bottom:30px; }
  .word{ font-family:'Baloo 2',sans-serif; font-weight:800; font-size:29px; color:var(--navy-900); letter-spacing:-0.01em; }
  .word .accent{ color:var(--orange); }

  h1{ font-family:'Baloo 2',sans-serif; font-size:27px; font-weight:800; margin:0 0 22px; color:var(--navy-900); }

  .role-badge{ display:inline-flex; align-items:center; gap:7px; background:var(--sky-50); border:1px solid var(--line); border-radius:999px; padding:5px 12px 5px 5px; font-size:11.5px; font-weight:600; color:var(--slate-600); margin:-8px 0 18px; }
  .role-badge .icon{ width:19px;height:19px;border-radius:50%; display:flex;align-items:center;justify-content:center; color:#fff; box-shadow:inset 0 2px 0 rgba(255,255,255,.5); }
  .role-badge .icon.teacher{ background:linear-gradient(150deg,var(--blue-500),var(--blue-700)); }
  .role-badge .icon.parent{ background:linear-gradient(150deg,#28b895,#1f9e83); }
  .role-badge .icon.admin{ background:linear-gradient(150deg,#4a5b6b,var(--navy-900)); }

  .form-error-banner{ display:flex; gap:8px; align-items:flex-start; width:100%; text-align:left; background:var(--danger-bg); border:1px solid var(--danger-border); color:var(--danger); border-radius:11px; padding:11px 13px; font-size:12.5px; font-weight:600; margin-bottom:16px; }
  .verify-note{ display:flex; gap:10px; width:100%; text-align:left; background:var(--sky-50); border:1px solid var(--line); border-radius:13px; padding:12px 13px; font-size:12px; color:var(--slate-600); font-weight:500; line-height:1.5; margin-bottom:18px; }
  .verify-note svg{ flex-shrink:0; margin-top:1px; color:var(--blue-600); }
  .verify-note.success{ background:var(--success-bg); } .verify-note.success svg{ color:var(--success); }
  .verify-note.amber{ background:var(--amber-bg); } .verify-note.amber svg{ color:var(--amber); }

  .field{ display:flex; flex-direction:column; gap:6px; margin-bottom:14px; width:100%; text-align:left; }
  .field label{ font-size:12.5px; font-weight:700; color:var(--navy-900); }
  input{
    width:100%; font:500 14.5px/1 'Inter',sans-serif; padding:13px 14px; border-radius:13px; outline:none;
    border:1.5px solid transparent; background:var(--field-bg); color:var(--navy-900);
    transition:border-color .15s ease, background .15s ease, box-shadow .15s ease;
  }
  input::placeholder{ color:var(--slate-400); font-weight:500; }
  input:focus{ border-color:var(--blue-500); background:#fff; box-shadow:0 0 0 4px rgba(28,126,214,.12); }
  input.error{ border-color:var(--danger); }
  .pw-wrap{ position:relative; }
  .field-icon-btn{ position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:var(--blue-500); padding:2px; display:flex; }

  .forgot-link{ display:inline-block; font-size:12px; font-weight:800; letter-spacing:.03em; text-transform:uppercase; color:var(--blue-600); text-decoration:none; margin:2px 0 22px; border-bottom:1.5px dashed rgba(15,95,174,.35); padding-bottom:1px; transition:color .15s ease; }
  .forgot-link:hover{ color:var(--blue-700); }

  .submit-btn{
    width:100%; padding:14px; border:none; border-radius:14px; cursor:pointer;
    font:800 14.5px/1 'Inter',sans-serif; letter-spacing:.03em; text-transform:uppercase;
    background:linear-gradient(160deg, var(--blue-500), var(--blue-700)); color:#fff;
    box-shadow: 0 4px 0 var(--blue-700), 0 16px 26px -12px rgba(15,95,174,.5), inset 0 2px 0 rgba(255,255,255,.3);
    transition:transform .15s ease, box-shadow .15s ease, opacity .15s ease;
  }
  .submit-btn:hover{ transform:translateY(-2px); }
  .submit-btn:active{ transform:translateY(2px); box-shadow:0 1px 0 var(--blue-700), 0 6px 12px -8px rgba(15,95,174,.5), inset 0 2px 0 rgba(255,255,255,.3); }
  .submit-btn:disabled{ opacity:.65; cursor:not-allowed; transform:none; }

  .divider-label{ width:100%; font-size:10.5px; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:var(--slate-400); margin:20px 0 16px; display:flex; align-items:center; gap:10px; }
  .divider-label::after, .divider-label::before{ content:""; flex:1; height:1px; background:var(--line); }

  .google-btn{
    width:100%; display:flex; align-items:center; justify-content:center; gap:10px; padding:12.5px; border-radius:14px;
    border:1.5px solid var(--line); background:#fff; box-shadow:0 1px 2px rgba(19,31,43,.05);
    font:800 13px/1 'Inter',sans-serif; letter-spacing:.03em; text-transform:uppercase; color:var(--navy-900);
    text-decoration:none; transition:border-color .15s ease, box-shadow .15s ease, transform .15s ease;
  }
  .google-btn:hover{ border-color:var(--slate-300); box-shadow:0 4px 10px -4px rgba(19,31,43,.15); transform:translateY(-1px); }

  .switch-note{ text-align:center; font-size:13px; color:var(--slate-600); font-weight:500; margin:24px 0 0; }
  .switch-note a{ display:block; margin-top:4px; color:var(--blue-600); font-weight:800; letter-spacing:.03em; text-transform:uppercase; font-size:12px; text-decoration:none; transition:color .15s ease; }
  .switch-note a:hover{ color:var(--blue-700); }

  a:focus-visible, button:focus-visible{ outline:2px solid var(--blue-500); outline-offset:2px; }
  @media (prefers-reduced-motion: reduce){ .screen{ animation:none; } }
</style>
</head>
<body>
<div class="screen">
  <a href="{{ route('landing') }}" class="home-link">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    Back to home
  </a>
  <div class="col">
    <div class="lockup">
      <div class="word">TaraBasa<span class="accent">AI</span></div>
    </div>
    @yield('content')
  </div>
</div>
</body>
</html>
