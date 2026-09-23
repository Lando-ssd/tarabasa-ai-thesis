<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Learner Login — TaraBasa AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/lottie-web@5.12.2/build/player/lottie.min.js"></script>
<style>
  :root{
    --blue-500:#1c7ed6; --blue-600:#0f5fae; --blue-700:#0a3d73;
    --navy-900:#131f2b; --slate-600:#5b6b7a; --slate-400:#8a97a3;
    --owl-orange-500:#ef8d2a;
    --line:#e7edf3; --field-bg:#f3f7fb; --surface:#ffffff;
    --danger:#d64545; --danger-bg:#fdecec;
  }
  *{box-sizing:border-box;} html,body{margin:0;padding:0;height:100%;}
  body{
    min-height:100%; font-family:'Inter',sans-serif; color:var(--navy-900);
    background:#ffffff;
    display:flex; align-items:safe center; justify-content:center; padding:28px 16px 44px;
  }

  .wrap{ width:100%; max-width:420px; text-align:center; }

  /* Real Lottie hero — a genuine "children holding up letters" animation
     (user-supplied), replacing the earlier hand-drawn owl scene per the
     approved redesign. Loops gently; a static final frame is used
     instead under prefers-reduced-motion, same pattern as every other
     Lottie in this app. */
  .hero-lottie{ width:100%; max-width:280px; aspect-ratio:720/500; margin:0 auto 6px; }

  h1{ font-family:'Baloo 2',sans-serif; font-size:34px; font-weight:800; color:var(--blue-600); margin:4px 0 10px; letter-spacing:-.01em; }
  .sub{ font-size:18px; color:var(--slate-600); font-weight:600; margin:0 0 30px; line-height:1.5; }

  .field{ margin-bottom:22px; text-align:center; }
  input[type="text"]{
    width:100%; font:800 32px/1 'Baloo 2',sans-serif; letter-spacing:.06em; padding:20px 18px; border:2.5px solid var(--line);
    border-radius:20px; background:var(--field-bg); color:var(--navy-900); outline:none; text-align:center; text-transform:uppercase;
    transition:border-color .15s ease, box-shadow .15s ease, background .15s ease;
  }
  input[type="text"]::placeholder{ color:var(--slate-400); }
  input[type="text"]:focus{ border-color:var(--blue-500); background:#fff; box-shadow:0 0 0 5px rgba(28,126,214,.14); }

  .pin-boxes{ display:flex; gap:12px; justify-content:center; }
  .pin-box{
    width:64px; height:72px; border:2.5px solid var(--line); border-radius:20px; background:var(--field-bg);
    display:flex; align-items:safe center; justify-content:center; font-family:'Baloo 2',sans-serif; font-size:28px; font-weight:700;
    transition:border-color .15s ease, background .15s ease;
  }
  .pin-box.filled{ border-color:var(--owl-orange-500); background:#fff; }

  .inline-error{
    background:var(--danger-bg); color:var(--danger); border:1.5px solid var(--danger); border-radius:14px;
    padding:11px 14px; font-size:15px; font-weight:700; margin-bottom:18px; text-align:left;
  }

  .big-btn{
    width:100%; margin-top:6px; padding:17px; border:none; border-radius:16px;
    font:800 17px/1 'Baloo 2',sans-serif; letter-spacing:.04em; text-transform:uppercase; cursor:pointer;
    background:linear-gradient(160deg, var(--blue-500), var(--blue-700)); color:#fff;
    box-shadow:0 4px 0 var(--blue-700), 0 16px 26px -12px rgba(15,95,174,.5), inset 0 2px 0 rgba(255,255,255,.3);
    transition:transform .15s ease, box-shadow .15s ease;
  }
  .big-btn:hover{ transform:translateY(-2px); }
  .big-btn:active{ transform:translateY(2px); box-shadow:0 1px 0 var(--blue-700), 0 6px 12px -8px rgba(15,95,174,.5), inset 0 2px 0 rgba(255,255,255,.3); }
  .big-btn:disabled{ opacity:.65; cursor:not-allowed; transform:none; }

  .back-link{ display:inline-block; font-size:14px; font-weight:700; color:var(--slate-600); text-decoration:none; margin-top:22px; }
  .back-link:hover{ color:var(--blue-600); }
  a:focus-visible, button:focus-visible, input:focus-visible{ outline:2px solid var(--blue-500); outline-offset:2px; }
</style>
</head>
<body>
<div class="wrap">
  <div class="hero-lottie" id="heroLottie" role="img" aria-label="Children holding up letters, ready to read"></div>

  <h1>Hi there!</h1>
  <p class="sub">Type your code, then your secret PIN.</p>

  @if ($errors->any())
    <div class="inline-error">{{ $errors->first() }}</div>
  @endif

  <form method="POST" action="{{ route('learner.login.submit') }}" id="learnerLoginForm">
    @csrf
    <div class="field">
      <input type="text" name="learner_code" id="learner_code" placeholder="TB-XXXXX" maxlength="8"
             value="{{ old('learner_code') }}" autocomplete="off" autofocus required>
    </div>

    <div class="field">
      <div class="pin-boxes" id="pinBoxes">
        <div class="pin-box" data-i="0"></div>
        <div class="pin-box" data-i="1"></div>
        <div class="pin-box" data-i="2"></div>
        <div class="pin-box" data-i="3"></div>
      </div>
      <input type="tel" inputmode="numeric" maxlength="4" style="position:absolute;opacity:0;pointer-events:none;" id="pinInput">
      <input type="hidden" name="pin" id="pin">
    </div>

    <button type="submit" class="big-btn" id="submitBtn">Let's Go</button>
  </form>

  <a href="{{ route('landing') }}" class="back-link">Back to home</a>
</div>

<script>
  lottie.loadAnimation({
    container: document.getElementById('heroLottie'),
    renderer: 'svg',
    loop: !window.matchMedia('(prefers-reduced-motion: reduce)').matches,
    autoplay: !window.matchMedia('(prefers-reduced-motion: reduce)').matches,
    path: '{{ asset("animations/tarabasa-learner-login-hero.json") }}'
  });

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

  // A real friendly chime when the child taps in — reuses the same
  // Mixkit-licensed "correct.mp3" already cleared/shipped for Practice
  // Games (see CLAUDE.md), not a new asset. Fires on the first genuine
  // user gesture (focusing the code field), since browsers block
  // autoplay without one.
  (function () {
    try {
      const chime = new Audio('/sounds/correct.mp3');
      chime.volume = 0.35;
      document.getElementById('learner_code').addEventListener('focus', function once() {
        chime.play().catch(() => {});
      }, { once: true });
    } catch (e) { /* never block login over a sound failing to init */ }
  })();
</script>
</body>
</html>
