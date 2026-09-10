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

  /* ---- Full-screen backdrop: the same TaraBasa color tokens, but now
     a genuine layered/dimensional scene instead of a single flat
     gradient — a richer multi-stop base wash, a soft radial spotlight
     behind the owl, and (below) a real WebGL field of softly-glowing
     floating orbs at different depths with mouse parallax, for actual
     3D depth and interactivity. The gradient stays underneath as the
     honest, always-present base layer — the orb field is a genuine
     enhancement on top of it, not a replacement that could leave a
     blank screen if WebGL is ever unavailable. ---- */
  .bg-stage{ position:fixed; inset:0; z-index:-1; overflow:hidden; }
  .bg-stage .grad{
    position:absolute; inset:0;
    background:
      radial-gradient(1400px 900px at 18% -10%, rgba(255,255,255,0.14), transparent 55%),
      radial-gradient(1200px 800px at 88% 108%, rgba(255,255,255,0.16), transparent 55%),
      linear-gradient(180deg, #062d5c 0%, var(--blue-700) 20%, var(--blue-500) 42%, #f3c46a 70%, var(--owl-orange-500) 100%);
  }
  .bg-stage .glow{
    position:absolute; left:50%; top:0; width:640px; height:640px; transform:translate(-50%,-58%);
    background:radial-gradient(circle, rgba(255,255,255,0.35), rgba(255,255,255,0.08) 45%, transparent 70%);
  }
  .bg-stage .orb-field{ position:absolute; inset:0; }
  .bg-stage .orb-field canvas{ display:block; width:100% !important; height:100% !important; }

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
  <div class="orb-field" id="orbField"></div>
  <div class="glow"></div>
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

<script type="module">
  // A genuine WebGL field of soft glowing orbs at different depths,
  // layered on top of the gradient background — real 3D positions, not
  // a flat pattern, with gentle drifting motion and mouse-parallax for
  // interactivity. Unlike the mascot (rejected in 3D twice over real
  // fidelity concerns — see CLAUDE.md), a background has no "does it
  // look like a specific character" precision problem, so genuine
  // WebGL here is safe: if it fails or isn't supported, the gradient
  // underneath is already a complete, honest background on its own —
  // no fallback UI is needed, just a silent catch.
  try {
    const THREE = await import('https://cdnjs.cloudflare.com/ajax/libs/three.js/0.186.0/three.module.min.js');

    const container = document.getElementById('orbField');
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(55, window.innerWidth / window.innerHeight, 0.1, 100);
    camera.position.z = 18;

    const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
    renderer.setSize(window.innerWidth, window.innerHeight);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.75));
    container.appendChild(renderer.domElement);

    // A soft radial-glow sprite texture, generated on a small canvas —
    // no external image asset needed.
    function glowTexture(hex) {
      const size = 128;
      const c = document.createElement('canvas');
      c.width = c.height = size;
      const ctx = c.getContext('2d');
      const g = ctx.createRadialGradient(size / 2, size / 2, 0, size / 2, size / 2, size / 2);
      g.addColorStop(0, hex + 'ff');
      g.addColorStop(0.45, hex + '99');
      g.addColorStop(1, hex + '00');
      ctx.fillStyle = g;
      ctx.fillRect(0, 0, size, size);
      return new THREE.CanvasTexture(c);
    }

    // TaraBasa's own tokens only — no new brand colors introduced.
    const PALETTE = ['#ffffff', '#dcedff', '#ffcf6e', '#ef8d2a', '#8fc7f2'];
    const textures = PALETTE.map(glowTexture);

    const orbs = [];
    const count = window.innerWidth < 640 ? 20 : 38;
    for (let i = 0; i < count; i++) {
      const map = textures[i % textures.length];
      const mat = new THREE.SpriteMaterial({
        map, transparent: true, depthWrite: false, blending: THREE.AdditiveBlending,
        opacity: 0.25 + Math.random() * 0.35,
      });
      const sprite = new THREE.Sprite(mat);
      const scale = 0.8 + Math.random() * 2.6;
      sprite.scale.set(scale, scale, 1);
      sprite.position.set((Math.random() - 0.5) * 34, (Math.random() - 0.5) * 26, (Math.random() - 0.5) * 16);
      sprite.userData.speed = 0.15 + Math.random() * 0.25;
      sprite.userData.driftPhase = Math.random() * Math.PI * 2;
      sprite.userData.baseX = sprite.position.x;
      sprite.userData.baseY = sprite.position.y;
      scene.add(sprite);
      orbs.push(sprite);
    }

    let pointerX = 0, pointerY = 0;
    window.addEventListener('pointermove', (e) => {
      pointerX = (e.clientX / window.innerWidth - 0.5) * 2;
      pointerY = (e.clientY / window.innerHeight - 0.5) * 2;
    }, { passive: true });

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function frame(t) {
      const time = t / 1000;
      if (!reducedMotion) {
        orbs.forEach((o) => {
          const d = o.userData;
          o.position.y = d.baseY + Math.sin(time * d.speed + d.driftPhase) * 1.4;
          o.position.x = d.baseX + Math.cos(time * d.speed * 0.7 + d.driftPhase) * 1.1;
        });
        camera.position.x += (pointerX * 1.6 - camera.position.x) * 0.02;
        camera.position.y += (-pointerY * 1.0 - camera.position.y) * 0.02;
        camera.lookAt(0, 0, 0);
      }
      renderer.render(scene, camera);
      requestAnimationFrame(frame);
    }
    requestAnimationFrame(frame);

    window.addEventListener('resize', () => {
      camera.aspect = window.innerWidth / window.innerHeight;
      camera.updateProjectionMatrix();
      renderer.setSize(window.innerWidth, window.innerHeight);
    });
  } catch (err) {
    // The gradient background is already a complete, honest scene on
    // its own — nothing further to do here.
  }
</script>
</body>
</html>
