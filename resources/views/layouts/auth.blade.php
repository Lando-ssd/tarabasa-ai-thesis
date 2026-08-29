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
  :root{
    --sky-50:#eef6ff; --sky-100:#dcedff;
    --blue-500:#1c7ed6; --blue-600:#0f5fae; --blue-700:#0a3d73;
    --navy-900:#131f2b; --slate-600:#5b6b7a; --slate-400:#8a97a3;
    --danger:#d64545; --success:#2f9e5b;
    --line:#e3ebf2; --surface:#ffffff; --bg-0:#f6faff;
    --shadow-sm:0 1px 2px rgba(19,31,43,0.06);
    --radius-lg:22px; --radius-md:16px; --radius-sm:10px;
  }
  *{box-sizing:border-box;}
  html,body{margin:0;padding:0;}
  body{
    min-height:100vh; font-family:'Inter',sans-serif; color:var(--navy-900);
    background:
      radial-gradient(1100px 620px at 82% -8%, var(--sky-100), transparent 60%),
      radial-gradient(900px 500px at -10% 110%, var(--sky-100), transparent 55%),
      var(--bg-0);
    background-attachment:fixed;
    display:flex; align-items:center; justify-content:center;
    padding:32px 20px 48px;
  }
  .card-outer{ width:100%; max-width:440px; }
  .back-link{
    display:inline-flex; align-items:center; gap:6px; background:none; border:none;
    color:var(--slate-600); font:600 13px/1 'Inter',sans-serif; cursor:pointer;
    margin-bottom:14px; padding:0; text-decoration:none;
  }
  .back-link:hover{ color:var(--blue-600); }
  .card{
    background:var(--surface); border:1px solid var(--line); border-radius:var(--radius-lg);
    box-shadow:0 24px 60px -30px rgba(19,31,43,0.25); padding:32px;
  }
  .logo-badge{
    width:48px;height:48px;border-radius:14px; overflow:hidden; margin-bottom:14px;
    box-shadow:0 6px 16px -6px rgba(15,95,174,0.5);
  }
  .logo-badge img{ width:100%; height:100%; object-fit:cover; display:block; }
  h1{ font-family:'Baloo 2',sans-serif; font-size:22px; font-weight:700; margin:0 0 4px; }
  .sub{ margin:0 0 22px; font-size:13.5px; color:var(--slate-600); font-weight:500; line-height:1.5; }
  .role-badge{
    display:inline-flex; align-items:center; gap:8px; background:var(--sky-50); border:1px solid var(--line);
    border-radius:999px; padding:6px 14px 6px 6px; font-size:12.5px; font-weight:600; color:var(--slate-600);
    margin-bottom:18px;
  }
  .role-badge .icon{ width:22px;height:22px;border-radius:50%; display:flex;align-items:center;justify-content:center; color:#fff; }
  .role-badge .icon.teacher{ background:linear-gradient(150deg,var(--blue-500),var(--blue-700)); }
  .role-badge .icon.parent{ background:linear-gradient(150deg,#28b895,#1f9e83); }
  .role-badge .icon.admin{ background:linear-gradient(150deg,#4a5b6b,var(--navy-900)); }
  .field{ display:flex; flex-direction:column; gap:6px; margin-bottom:14px; }
  .field label{ font-size:12.5px; font-weight:700; }
  .field .opt{ font-size:11px; font-weight:600; color:var(--slate-400); margin-left:3px; }
  .name-row{ display:grid; grid-template-columns:1fr 1fr; gap:10px; }
  input{
    width:100%; font:500 14px/1 'Inter',sans-serif; padding:12px 13px; border:1.5px solid var(--line);
    border-radius:11px; background:var(--bg-0); color:var(--navy-900); outline:none;
    transition:border-color .15s ease, box-shadow .15s ease, background .15s ease;
  }
  input::placeholder{ color:var(--slate-400); font-weight:500; }
  input:focus{ border-color:var(--blue-500); background:var(--surface); box-shadow:0 0 0 4px rgba(28,126,214,0.14); }
  input.error{ border-color:var(--danger); }
  .field-error{ font-size:12px; color:var(--danger); font-weight:600; margin-top:2px; }
  .divider-label{
    font-size:11px; font-weight:800; letter-spacing:.05em; text-transform:uppercase; color:var(--slate-400);
    margin:6px 0 14px; display:flex; align-items:center; gap:8px;
  }
  .divider-label::after{ content:""; flex:1; height:1px; background:var(--line); }
  .verify-note{
    display:flex; gap:10px; background:var(--sky-50); border:1px solid var(--line); border-radius:13px;
    padding:12px 13px; font-size:12px; color:var(--slate-600); font-weight:500; line-height:1.5; margin-bottom:18px;
  }
  .verify-note svg{ flex-shrink:0; margin-top:1px; color:var(--blue-600); }
  .form-error-banner{
    display:flex; gap:8px; align-items:flex-start; background:#fdeeee; border:1px solid #f3c9c9;
    color:var(--danger); border-radius:11px; padding:11px 13px; font-size:12.5px; font-weight:600;
    margin-bottom:16px;
  }
  .submit-btn{
    width:100%; padding:14px; border:none; border-radius:12px; font:700 15px/1 'Inter',sans-serif;
    cursor:pointer; background:linear-gradient(155deg, var(--blue-500), var(--blue-700)); color:#fff;
    box-shadow:0 12px 22px -10px rgba(15,95,174,0.55); transition:transform .15s ease, opacity .15s ease;
  }
  .submit-btn:hover{ transform:translateY(-1px); }
  .submit-btn:disabled{ opacity:.65; cursor:not-allowed; transform:none; }
  .switch-note{ text-align:center; font-size:13px; color:var(--slate-600); font-weight:500; margin:18px 0 0; }
  .switch-note a{ color:var(--blue-600); font-weight:700; text-decoration:none; }
  .switch-note a:hover{ text-decoration:underline; }
</style>
</head>
<body>
<div class="card-outer">
  <a href="{{ route('landing') }}" class="back-link">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    Back to role select
  </a>
  <div class="card">
    <div class="logo-badge"><img src="{{ asset('images/logo.png') }}" alt="TaraBasa AI logo"></div>
    @yield('content')
  </div>
</div>
</body>
</html>
