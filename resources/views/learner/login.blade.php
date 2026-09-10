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
    background:var(--blue-700);
    display:flex; align-items:safe center; justify-content:center; padding:24px;
    overflow-x:hidden;
  }

  /* ---- Full-screen colorful backdrop: TaraBasa's own tokens, top-to-
     bottom sky-to-sunshine, plus a very low-opacity scattered "learning
     icon" texture and a soft angled light-band for depth. ---- */
  .bg-stage{ position:fixed; inset:0; z-index:-1; overflow:hidden; }
  .bg-stage .grad{
    position:absolute; inset:0;
    background:linear-gradient(180deg, #062d5c 0%, var(--blue-700) 22%, var(--blue-500) 45%, var(--clay-yellow) 74%, var(--owl-orange-500) 100%);
  }
  .bg-stage .icons{
    position:absolute; inset:-80px; opacity:.11; mix-blend-mode:screen;
    background-repeat:repeat; background-size:190px 190px;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='190' height='190'%3E%3Cg fill='white'%3E%3Cpath d='M24 14l2.6 6.6 7 .6-5.3 4.7 1.6 6.9-5.9-3.9-5.9 3.9 1.6-6.9-5.3-4.7 7-.6z'/%3E%3Cpath transform='translate(148 24) rotate(18)'%3E%3Ccircle r='9' fill='none' stroke='white' stroke-width='2'/%3E%3Cpath d='M-3 -3h6v2h-2v9h-2v-9h-2z'/%3E%3C/g%3E%3Cg transform='translate(30 130)'%3E%3Cpath d='M0 0h34v26a3 3 0 0 1-3 3H3a3 3 0 0 1-3-3z' opacity='.18'/%3E%3Cpath d='M2 2h13v24H5a3 3 0 0 1-3-3z' opacity='.8'/%3E%3Cpath d='M32 2H19v24h10a3 3 0 0 0 3-3z' opacity='.55'/%3E%3C/g%3E%3Cpath d='M160 120c1.5 4 6.5 4 8 0 4 1.5 4 6.5 0 8 1.5 4-4 6.5-8 4-4 2.5-9.5 0-8-4-4-1.5-4-6.5 0-8z' opacity='.7'/%3E%3Ccircle cx='95' cy='60' r='3.5' opacity='.5'/%3E%3Ccircle cx='60' cy='170' r='3.5' opacity='.5'/%3E%3C/g%3E%3C/svg%3E");
  }
  .bg-stage .glow{
    position:absolute; left:50%; top:0; width:640px; height:640px; transform:translate(-50%,-58%);
    background:radial-gradient(circle, rgba(255,255,255,0.35), rgba(255,255,255,0.08) 45%, transparent 70%);
  }
  .bg-stage .band{
    position:absolute; left:-15%; right:-15%; top:58%; height:260px;
    background:radial-gradient(60% 100% at 50% 0%, rgba(255,255,255,0.22), transparent 72%);
    transform:rotate(-4deg);
  }

  .wrap{ position:relative; width:100%; max-width:420px; margin-top:118px; }

  /* ---- Owl illustration: a real hand-drawn SVG cartoon modeled
     directly on the TaraBasa app icon (public/images/logo.png) — warm
     orange-brown body, light tan facial disc, rosy cheeks, a visible
     beak, V ear tufts, dark cartoon outline — standing over and
     "holding" the top edge of the card, the same structural idea as
     the reference screenshot's mascot, just this app's own character
     in clean 2D instead of 3D (2D reads far more reliably as "owl"
     than the primitive-geometry 3D attempts did). ---- */
  .owl-stage{
    position:absolute; left:50%; top:-196px; transform:translateX(-50%);
    width:260px; height:260px; z-index:2; pointer-events:none;
  }
  .owl-illustration{
    width:100%; height:100%; display:block; transform-origin:50% 100%;
    animation:owlEntrance .7s cubic-bezier(.34,1.56,.64,1) both,
              owlBob 3.4s ease-in-out .7s infinite;
  }
  @keyframes owlEntrance{
    0%{ transform:translateY(36px) scale(0.7); opacity:0; }
    70%{ transform:translateY(-6px) scale(1.05); opacity:1; }
    100%{ transform:translateY(0) scale(1); opacity:1; }
  }
  @keyframes owlBob{
    0%,100%{ transform:translateY(0) rotate(0deg); }
    50%{ transform:translateY(-7px) rotate(-1.2deg); }
  }
  .owl-illustration .eyes-closed{ opacity:0; }
  .owl-illustration.is-blinking .eyes-open{ opacity:0; }
  .owl-illustration.is-blinking .eyes-closed{ opacity:1; }
  @media (prefers-reduced-motion:reduce){
    .owl-illustration{ animation:none; }
  }

  .card{
    position:relative; z-index:1;
    background:var(--surface); border-radius:32px; padding:56px 28px 30px; text-align:center;
    box-shadow:0 36px 70px -30px rgba(10,40,80,0.45), 0 4px 0 rgba(255,255,255,0.6) inset;
  }

  /* The owl-stage is a fixed pixel size, but .wrap/.card shrink below
     their 420px cap on narrow screens — without this, the owl grows
     proportionally larger than the (now-narrower) card. Scale it down
     a bit below ~480px. */
  @media (max-width:480px){
    .owl-stage{ width:210px; height:210px; top:-158px; }
  }
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
<div class="bg-stage" aria-hidden="true">
  <div class="grad"></div>
  <div class="icons"></div>
  <div class="glow"></div>
  <div class="band"></div>
</div>

<div class="wrap">
  <div class="owl-stage" id="owlStage">
    <svg class="owl-illustration" id="owlIllustration" viewBox="0 0 300 300" xmlns="http://www.w3.org/2000/svg">
      <!-- Ear tufts, drawn first so the head silhouette overlaps their base -->
      <path d="M92,118 C74,88 54,56 46,24 C69,45 96,71 110,108 Z" fill="#e0862f" stroke="#2b1810" stroke-width="5" stroke-linejoin="round"/>
      <path d="M208,118 C226,88 246,56 254,24 C231,45 204,71 190,108 Z" fill="#e0862f" stroke="#2b1810" stroke-width="5" stroke-linejoin="round"/>

      <!-- Body/head silhouette -->
      <path d="M150,55 C206,55 242,96 246,151 C251,212 230,272 150,277 C70,272 49,212 54,151 C58,96 94,55 150,55 Z"
            fill="#e0862f" stroke="#2b1810" stroke-width="6" stroke-linejoin="round"/>

      <!-- Facial disc -->
      <circle cx="117" cy="146" r="59" fill="#f7d9a8"/>
      <circle cx="183" cy="146" r="59" fill="#f7d9a8"/>

      <!-- Wings/arms, ending in small hands resting on the card's top edge -->
      <path d="M60,182 C39,213 44,253 86,277 C97,282 108,276 102,266 C81,251 70,220 79,189 Z" fill="#e0862f" stroke="#2b1810" stroke-width="5" stroke-linejoin="round"/>
      <path d="M240,182 C261,213 256,253 214,277 C203,282 192,276 198,266 C219,251 230,220 221,189 Z" fill="#e0862f" stroke="#2b1810" stroke-width="5" stroke-linejoin="round"/>
      <circle cx="90" cy="271" r="9" fill="#e0862f" stroke="#2b1810" stroke-width="4"/>
      <circle cx="210" cy="271" r="9" fill="#e0862f" stroke="#2b1810" stroke-width="4"/>

      <!-- Belly patch -->
      <ellipse cx="150" cy="228" rx="54" ry="46" fill="#f7d9a8"/>

      <!-- Cheeks -->
      <ellipse cx="88" cy="184" rx="15" ry="10" fill="#ffb3ab" opacity=".85"/>
      <ellipse cx="212" cy="184" rx="15" ry="10" fill="#ffb3ab" opacity=".85"/>

      <!-- Eyes: open (default) -->
      <g class="eyes-open">
        <circle cx="117" cy="149" r="32" fill="#ffffff" stroke="#2b1810" stroke-width="5"/>
        <circle cx="183" cy="149" r="32" fill="#ffffff" stroke="#2b1810" stroke-width="5"/>
        <circle cx="117" cy="155" r="19" fill="#2b1810"/>
        <circle cx="183" cy="155" r="19" fill="#2b1810"/>
        <circle cx="108" cy="141" r="6" fill="#ffffff"/>
        <circle cx="174" cy="141" r="6" fill="#ffffff"/>
      </g>
      <!-- Eyes: closed (blink) -->
      <g class="eyes-closed">
        <path d="M92,149 Q117,163 142,149" fill="none" stroke="#2b1810" stroke-width="6" stroke-linecap="round"/>
        <path d="M158,149 Q183,163 208,149" fill="none" stroke="#2b1810" stroke-width="6" stroke-linecap="round"/>
      </g>

      <!-- Beak -->
      <path d="M150,168 C159,168 165,174 165,181 C165,191 150,199 150,199 C150,199 135,191 135,181 C135,174 141,168 150,168 Z"
            fill="#f5a623" stroke="#2b1810" stroke-width="4" stroke-linejoin="round"/>
      <path d="M142,190 Q150,196 158,190" fill="none" stroke="#a33d1f" stroke-width="3" stroke-linecap="round"/>
    </svg>
  </div>

  <div class="card">
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

  // A real friendly chime when the child taps in — reuses the same
  // Mixkit-licensed "correct.mp3" already cleared/shipped for Practice
  // Games (see CLAUDE.md), not a new asset. Fires on the first genuine
  // user gesture (focusing the code field), since browsers block
  // autoplay without one — this is NOT tied to page load.
  (function () {
    try {
      const chime = new Audio('/sounds/correct.mp3');
      chime.volume = 0.35;
      document.getElementById('learner_code').addEventListener('focus', function once() {
        chime.play().catch(() => {});
      }, { once: true });
    } catch (e) { /* never block login over a sound failing to init */ }
  })();

  // Periodic blinking — the same class-toggle technique already
  // established in games/_owl-mascot.blade.php (swap which pre-drawn
  // eye state is visible), just driven by a timer here instead of a
  // gameplay event. Skipped entirely under prefers-reduced-motion.
  (function () {
    const illustration = document.getElementById('owlIllustration');
    if (!illustration) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    function scheduleBlink() {
      const delay = 2400 + Math.random() * 2600;
      setTimeout(function () {
        illustration.classList.add('is-blinking');
        setTimeout(function () {
          illustration.classList.remove('is-blinking');
          scheduleBlink();
        }, 160);
      }, delay);
    }
    scheduleBlink();
  })();
</script>
</body>
</html>
