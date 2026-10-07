<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $learner->first_name }} is all set | TaraBasa AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
<noscript><link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"></noscript>
<style>
  :root{
    --sky-50:#eef6ff; --sky-100:#dcedff;
    --blue-500:#1c7ed6; --blue-600:#0f5fae; --blue-700:#0a3d73;
    --navy-900:#131f2b; --slate-600:#5b6b7a; --slate-400:#8a97a3;
    --clay-yellow:#ffcf6e; --owl-orange-600:#dd7014;
    --line:#e3ebf2; --surface:#ffffff; --bg-0:#f6faff;
    --success:#1f9e83;
    --shadow-card:0 30px 60px -30px rgba(15,60,110,0.28), 0 6px 16px -8px rgba(15,60,110,0.12);
  }
  *{box-sizing:border-box;} html,body{margin:0;padding:0;}
  body{
    min-height:100vh; font-family:'Inter',sans-serif; color:var(--navy-900);
    background: radial-gradient(1100px 620px at 88% -10%, var(--sky-100), transparent 60%),
                radial-gradient(900px 500px at -10% 108%, var(--sky-100), transparent 55%), var(--bg-0);
    background-attachment:fixed;
    display:flex; align-items:safe center; justify-content:center; padding:32px 20px;
  }
  .card-outer{ width:100%; max-width:440px; }
  .card{ background:var(--surface); border:1px solid var(--line); border-radius:24px; box-shadow:var(--shadow-card); padding:34px 30px; text-align:center; }
  .big-avatar{
    width:84px;height:84px;border-radius:26px; margin:0 auto 16px; font-size:42px; overflow:hidden;
    background:linear-gradient(155deg, var(--clay-yellow), var(--owl-orange-600));
    display:flex; align-items:safe center; justify-content:center;
  }
  .avatar-photo-img{ width:100%; height:100%; object-fit:cover; display:block; }
  h1{ font-family:'Baloo 2',sans-serif; font-size:22px; font-weight:700; margin:0 0 6px; }
  .level-line{ font-size:14px; color:var(--slate-600); font-weight:600; margin:0 0 4px; line-height:1.5; }
  .level-line b{ color:var(--success); font-weight:800; }
  .next-note{ font-size:12.5px; color:var(--slate-400); font-weight:500; margin:0 0 22px; line-height:1.5; }
  .code-box{ background:var(--sky-50); border:1px solid var(--line); border-radius:16px; padding:20px; margin-bottom:22px; }
  .code-box .label{ font-size:12px; font-weight:700; color:var(--slate-600); text-transform:uppercase; letter-spacing:.04em; margin-bottom:6px; }
  .code-box .code{ font-family:'Baloo 2',sans-serif; font-size:28px; font-weight:700; color:var(--blue-700); letter-spacing:.03em; margin-bottom:6px; }
  .code-box .hint{ font-size:12.5px; color:var(--slate-600); font-weight:500; }
  .qr-box{ margin-top:-6px; }
  .qr-box .qr{ width:200px; max-width:100%; margin:6px auto 10px; background:#fff; border-radius:12px; padding:0; line-height:0; }
  .qr-box .qr svg{ width:100%; height:auto; display:block; }
  .code-box .parts{ display:grid; grid-template-columns:repeat(4,1fr); gap:6px; margin:12px 0 12px; }
  .code-box .parts div{ background:var(--surface); border:1px solid var(--line); border-radius:10px; padding:8px 4px; font-size:10.5px; font-weight:600; color:var(--slate-600); line-height:1.3; }
  .code-box .parts b{ display:block; font:700 16px/1.2 'Baloo 2',sans-serif; color:var(--blue-700); }
  .copy-btn{
    display:inline-flex; align-items:center; gap:6px; margin-top:10px; background:var(--surface); border:1px solid var(--line);
    padding:7px 14px; border-radius:999px; font-size:12.5px; font-weight:700; color:var(--blue-600); cursor:pointer;
  }
  .copy-btn:hover{ border-color:var(--blue-500); }
  .copy-btn.copied{ color:var(--success); border-color:var(--success); }
  .nav-row{ display:flex; gap:10px; }
  .btn{
    flex:1; padding:14px; border:none; border-radius:12px; font:700 15px/1 'Inter',sans-serif; cursor:pointer;
    text-decoration:none; display:flex; align-items:safe center; justify-content:center; transition:transform .15s ease;
  }
  .btn:hover{ transform:translateY(-1px); }
  .btn-primary{ background:linear-gradient(155deg, var(--blue-500), var(--blue-700)); color:#fff; box-shadow:0 12px 22px -10px rgba(15,95,174,0.55); }
  .btn-ghost{ background:var(--bg-0); color:var(--slate-600); border:1.5px solid var(--line); }
  a:focus-visible, button:focus-visible{ outline:2px solid var(--blue-500); outline-offset:2px; }
</style>
</head>
<body>
<div class="card-outer">
  <div class="card">
    <div class="big-avatar">
      @if ($learner->avatar_photo_path)
        <img src="{{ Storage::url($learner->avatar_photo_path) }}" alt="{{ $learner->first_name }}" class="avatar-photo-img">
      @else
        {{ $learner->avatar_id }}
      @endif
    </div>
    <h1>{{ $learner->first_name }} is all set!</h1>
    <p class="next-note">{{ $learner->first_name }} will take a short first reading check the first time they log in. It finds where to start.</p>

    <div class="code-box">
      <div class="label">Learner Code</div>
      <div class="code" id="learnerCode">{{ $learner->learner_code }}</div>
      @php
        // TB26-48293: TB, the school year, four random digits and a check digit. No personal information.
        $codeParts = preg_match('/^TB(\d{2})-(\d{4})(\d)$/', $learner->learner_code, $cm) ? $cm : null;
      @endphp
      @if ($codeParts)
        <div class="parts">
          <div><b>TB</b>TaraBasa</div>
          <div><b>{{ $cm[1] }}</b>School year 20{{ $cm[1] }}</div>
          <div><b>{{ $cm[2] }}</b>Random</div>
          <div><b>{{ $cm[3] }}</b>Check digit</div>
        </div>
      @endif
      <div class="hint">{{ $learner->first_name }} types this code and the PIN to log in. A teacher adds {{ $learner->first_name }} to a class with the last five characters.</div>
      <button type="button" class="copy-btn" id="copyBtn">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><rect x="9" y="9" width="12" height="12" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M5 15V5a2 2 0 0 1 2-2h10" stroke="currentColor" stroke-width="1.8"/></svg>
        <span id="copyBtnLabel">Copy code</span>
      </button>
    </div>

    <div class="code-box qr-box">
      <div class="label">QR code</div>
      <div class="qr" aria-hidden="false">{!! \App\Support\LearnerQr::svg($learner->learner_code, 200) !!}</div>
      <div class="hint">{{ $learner->first_name }}'s teacher can scan this to add {{ $learner->first_name }} to the class, instead of typing the code. It holds the code only, never the PIN.</div>
      <a class="copy-btn" href="{{ route('parent.children.card', $learner) }}" target="_blank" rel="noopener" style="text-decoration:none">Print {{ $learner->first_name }}'s card</a>
    </div>

    <div class="nav-row">
      <a href="{{ route('parent.children.create') }}" class="btn btn-ghost">Add Another Child</a>
      <a href="{{ route('parent.children.index') }}" class="btn btn-primary">My Children</a>
    </div>
  </div>
</div>
<script>
  document.getElementById('copyBtn').addEventListener('click', function () {
    const code = document.getElementById('learnerCode').textContent.trim();
    navigator.clipboard.writeText(code).then(() => {
      const btn = document.getElementById('copyBtn');
      const label = document.getElementById('copyBtnLabel');
      btn.classList.add('copied');
      label.textContent = 'Copied!';
      setTimeout(() => { btn.classList.remove('copied'); label.textContent = 'Copy code'; }, 1600);
    });
  });
</script>
</body>
</html>
