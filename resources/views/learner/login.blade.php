<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Learner Login — TaraBasa AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --sky-100:#dcedff;
    --blue-500:#1c7ed6; --blue-600:#0f5fae; --blue-700:#0a3d73;
    --navy-900:#131f2b; --slate-600:#5b6b7a; --slate-400:#8a97a3;
    --owl-orange-500:#ef8d2a; --owl-orange-600:#dd7014; --clay-yellow:#ffcf6e;
    --line:#e3ebf2; --surface:#ffffff; --bg-0:#f6faff;
    --danger:#d64545; --danger-bg:#fdecec;
  }
  *{box-sizing:border-box;} html,body{margin:0;padding:0;}
  body{
    min-height:100vh; font-family:'Inter',sans-serif; color:var(--navy-900);
    background: radial-gradient(1200px 700px at 90% -10%, var(--sky-100), transparent 55%),
                radial-gradient(900px 600px at 0% 100%, #ffe9c9, transparent 50%), var(--bg-0);
    background-attachment:fixed;
    display:flex; align-items:safe center; justify-content:center; padding:24px;
  }
  .wrap{ width:100%; max-width:420px; }
  .card{
    background:var(--surface); border-radius:30px; padding:34px 28px; text-align:center;
    box-shadow:0 30px 60px -28px rgba(15,60,110,0.25);
  }
  .mascot{
    width:88px;height:88px;border-radius:28px; margin:0 auto 16px; font-size:44px;
    background:linear-gradient(155deg, var(--clay-yellow), var(--owl-orange-600));
    display:flex;align-items:center;justify-content:center; box-shadow:0 16px 28px -12px rgba(221,112,20,0.5);
    animation:bob 2.4s ease-in-out infinite;
  }
  @keyframes bob{ 0%,100%{transform:translateY(0);} 50%{transform:translateY(-8px);} }
  h1{ font-family:'Baloo 2',sans-serif; font-size:26px; font-weight:700; margin:0 0 8px; }
  .sub{ font-size:18px; color:var(--slate-600); font-weight:600; margin:0 0 26px; line-height:1.5; }

  .field{ text-align:left; margin-bottom:18px; }
  .field label{ display:block; font-size:16px; font-weight:800; margin-bottom:7px; }
  .field label.centered{ text-align:center; }
  input[type="text"]{
    width:100%; font:800 18px/1 'Baloo 2',sans-serif; letter-spacing:.04em; padding:14px 16px; border:2px solid var(--line);
    border-radius:16px; background:var(--bg-0); color:var(--navy-900); outline:none; text-align:center; text-transform:uppercase;
    transition:border-color .15s ease, box-shadow .15s ease;
  }
  input[type="text"]::placeholder{ color:var(--slate-400); }
  input[type="text"]:focus{ border-color:var(--blue-500); box-shadow:0 0 0 4px rgba(28,126,214,0.14); }

  .pin-boxes{ display:flex; gap:10px; justify-content:center; margin-bottom:4px; }
  .pin-box{
    width:56px; height:64px; border:2px solid var(--line); border-radius:16px; background:var(--bg-0);
    display:flex; align-items:safe center; justify-content:center; font-family:'Baloo 2',sans-serif; font-size:26px; font-weight:700;
  }
  .pin-box.filled{ border-color:var(--owl-orange-500); background:var(--surface); }
  .pin-hidden-input{ position:absolute; opacity:0; pointer-events:none; }

  .inline-error{
    background:var(--danger-bg); color:var(--danger); border:1.5px solid var(--danger); border-radius:14px;
    padding:11px 14px; font-size:15px; font-weight:700; margin-bottom:18px;
  }

  .big-btn{
    width:100%; padding:16px; border:none; border-radius:18px; font:800 16px/1 'Baloo 2',sans-serif; cursor:pointer;
    background:linear-gradient(155deg, var(--blue-500), var(--blue-700)); color:#fff;
    box-shadow:0 16px 26px -12px rgba(15,95,174,0.5); transition:transform .2s cubic-bezier(.34,1.56,.64,1);
  }
  .big-btn:hover{ transform:translateY(-2px) scale(1.02); }
  .big-btn:active{ transform:scale(0.97); }
  .big-btn:disabled{ opacity:.6; cursor:not-allowed; transform:none; }

  .back-link{
    display:inline-flex; align-items:center; gap:6px; font-size:13px; font-weight:700; color:var(--slate-600);
    text-decoration:none; margin-top:18px;
  }
  .back-link:hover{ color:var(--blue-600); }
  a:focus-visible, button:focus-visible, input:focus-visible{ outline:2px solid var(--blue-500); outline-offset:2px; }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="mascot">🦉</div>
    <h1>Hi there!</h1>
    <p class="sub">Type your code, then your secret PIN.</p>

    @if ($errors->any())
      <div class="inline-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('learner.login.submit') }}" id="learnerLoginForm">
      @csrf
      <div class="field">
        <label for="learner_code">Your Code</label>
        <input type="text" name="learner_code" id="learner_code" placeholder="TB-XXXXX" maxlength="8"
               value="{{ old('learner_code') }}" autocomplete="off" autofocus required>
      </div>

      <div class="field">
        <label class="centered">Your PIN</label>
        <div class="pin-boxes" id="pinBoxes">
          <div class="pin-box" data-i="0"></div>
          <div class="pin-box" data-i="1"></div>
          <div class="pin-box" data-i="2"></div>
          <div class="pin-box" data-i="3"></div>
        </div>
        <input type="tel" inputmode="numeric" maxlength="4" class="pin-hidden-input" id="pinInput">
        <input type="hidden" name="pin" id="pin">
      </div>

      <button type="submit" class="big-btn" id="submitBtn">Let's Go! 🚀</button>
    </form>

    <a href="{{ route('landing') }}" class="back-link">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      Back to home
    </a>
  </div>
</div>

<script>
  const boxes = document.querySelectorAll('#pinBoxes .pin-box');
  const pinInput = document.getElementById('pinInput');
  const pinHidden = document.getElementById('pin');

  boxes.forEach(box => box.addEventListener('click', () => pinInput.focus()));

  pinInput.addEventListener('input', (e) => {
    const val = e.target.value.replace(/\D/g, '').slice(0, 4);
    e.target.value = val;
    pinHidden.value = val;
    boxes.forEach((box, i) => {
      box.textContent = val[i] ? '•' : '';
      box.classList.toggle('filled', !!val[i]);
    });
  });

  document.getElementById('learnerLoginForm').addEventListener('submit', function (e) {
    if (pinHidden.value.length < 4) {
      e.preventDefault();
      pinInput.focus();
      return;
    }
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.textContent = 'Checking…';
  });
</script>
</body>
</html>
