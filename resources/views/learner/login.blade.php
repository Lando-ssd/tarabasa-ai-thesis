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

  .wrap{ position:relative; width:100%; max-width:420px; margin-top:96px; }

  /* ---- 3D owl stage: overlaps the top of the card, "holding" it the
     way the reference mascot rests its paws on the card's top edge. ---- */
  .owl-stage{
    position:absolute; left:50%; top:-168px; transform:translateX(-50%);
    width:230px; height:230px; z-index:2; pointer-events:none;
  }
  .owl-stage canvas{ width:100% !important; height:100% !important; display:block; }
  .owl-fallback{
    display:none; width:120px; height:120px; border-radius:32px; margin:55px auto 0; font-size:60px;
    background:linear-gradient(155deg, var(--clay-yellow), var(--owl-orange-600));
    align-items:center; justify-content:center; box-shadow:0 16px 28px -12px rgba(221,112,20,0.5);
    animation:bob 2.4s ease-in-out infinite;
  }
  @keyframes bob{ 0%,100%{transform:translateY(0);} 50%{transform:translateY(-8px);} }

  .card{
    position:relative; z-index:1;
    background:var(--surface); border-radius:32px; padding:44px 28px 30px; text-align:center;
    box-shadow:0 36px 70px -30px rgba(10,40,80,0.45), 0 4px 0 rgba(255,255,255,0.6) inset;
  }

  /* The owl-stage is a fixed pixel size, but .wrap/.card shrink below
     their 420px cap on narrow screens — without this, the owl grows
     proportionally larger than the (now-narrower) card and its feet
     encroach on the heading. Scale the owl down and give the card a
     bit more top clearance below ~480px. */
  @media (max-width:480px){
    .owl-stage{ width:190px; height:190px; top:-134px; }
    .card{ padding-top:56px; }
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

  @media (prefers-reduced-motion: reduce){
    .owl-fallback{ animation:none; }
  }
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
    <div class="owl-fallback" id="owlFallback">🦉</div>
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

  // Safety net #1: if the 3D owl module below never even runs (CDN
  // blocked, network down, WebGL disabled) — not something a try/catch
  // inside that module can always catch, since a failed top-level
  // `import` throws before any of its own code executes — fall back to
  // the plain emoji mascot after a short grace period.
  setTimeout(function () {
    if (!document.querySelector('#owlStage canvas')) {
      const fb = document.getElementById('owlFallback');
      if (fb) fb.style.display = 'flex';
    }
  }, 4000);
</script>

<script type="module">
  // Safety net #2: any runtime error inside the 3D setup itself (a real
  // WebGL failure, an API mismatch) is caught here and falls back the
  // same way — the login screen must never depend on this succeeding.
  try {
    const THREE = await import('https://cdnjs.cloudflare.com/ajax/libs/three.js/0.186.0/three.module.min.js');

    function supportsWebGL() {
      try {
        const c = document.createElement('canvas');
        return !!(window.WebGLRenderingContext && (c.getContext('webgl2') || c.getContext('webgl')));
      } catch (e) { return false; }
    }
    if (!supportsWebGL()) throw new Error('no webgl');

    const stage = document.getElementById('owlStage');
    const width = stage.clientWidth, height = stage.clientHeight;

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(32, width / height, 0.1, 100);
    camera.position.set(0, 0.35, 5.6);
    camera.lookAt(0, 0.05, 0);

    const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
    renderer.setSize(width, height);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    stage.appendChild(renderer.domElement);

    // The canvas now genuinely exists — make sure the fallback emoji
    // is hidden even if the timeout-based safety net above already
    // raced ahead and shown it (a slow CDN fetch, not a real failure).
    const fallbackEl = document.getElementById('owlFallback');
    if (fallbackEl) fallbackEl.style.display = 'none';

    // Lighting — soft ambient fill + a warm key light + a cool rim
    // light, aiming for the same gentle, rounded "claymorphism" look
    // the rest of the app uses, just genuinely three-dimensional here.
    scene.add(new THREE.AmbientLight(0xfff2df, 0.75));
    const key = new THREE.DirectionalLight(0xffffff, 0.95);
    key.position.set(2.2, 3, 4);
    scene.add(key);
    const rim = new THREE.DirectionalLight(0xbfe0ff, 0.4);
    rim.position.set(-3, 0.6, -2);
    scene.add(rim);

    // Tara the owl — built from primitive geometry, this time modeled
    // directly on the real TaraBasa app icon (public/images/logo.png),
    // not the paler flat 2D mascot elsewhere in the app: a warm
    // orange-brown owl with a lighter tan facial disc, rosy cheeks, a
    // clearly visible yellow-orange beak, proper V-shaped ear tufts,
    // and a dark cartoon outline around every silhouette — the earlier
    // pass skipped the facial disc/outline entirely and used oversized,
    // widely-spaced eyes with thin spike-like ear tufts, which is
    // exactly what read as "alien" rather than owl.
    const OUTLINE_COLOR = 0x3a2110;
    function addOutline(mesh, growth) {
      const outline = new THREE.Mesh(mesh.geometry, new THREE.MeshBasicMaterial({ color: OUTLINE_COLOR, side: THREE.BackSide }));
      outline.position.copy(mesh.position);
      outline.rotation.copy(mesh.rotation);
      outline.scale.copy(mesh.scale).multiplyScalar(1 + growth);
      return outline;
    }

    const owl = new THREE.Group();
    const BODY_COLOR = 0xe08830;  // warm orange-brown — the logo's body/wing color
    const FACE_COLOR = 0xffe3ad;  // light tan facial disc / belly
    const BEAK_COLOR = 0xf5a623;  // warm yellow-orange beak
    const FOOT_COLOR = 0xd97b28;  // slightly deeper orange for legs/feet
    const CHEEK_COLOR = 0xffb3ab; // rosy cheek blush

    const bodyMat = new THREE.MeshStandardMaterial({ color: BODY_COLOR, roughness: 0.55 });
    const faceMat = new THREE.MeshStandardMaterial({ color: FACE_COLOR, roughness: 0.6 });

    const body = new THREE.Mesh(new THREE.SphereGeometry(1, 32, 24), bodyMat);
    body.scale.set(1, 0.98, 0.88);
    owl.add(body, addOutline(body, 0.045));

    // Facial disc — the single biggest fix: a lighter, flattened patch
    // spanning both eyes, exactly like a real owl (and the logo) has,
    // instead of eyes floating on a uniform-colored head.
    const face = new THREE.Mesh(new THREE.SphereGeometry(0.72, 28, 20), faceMat);
    face.scale.set(1.05, 0.85, 0.35);
    face.position.set(0, 0.12, 0.68);
    owl.add(face);

    const belly = new THREE.Mesh(new THREE.SphereGeometry(0.5, 20, 16), faceMat);
    belly.scale.set(0.95, 1.1, 0.3);
    belly.position.set(0, -0.55, 0.62);
    owl.add(belly);

    // Ear tufts — a real two-tone V shape (body-colored outer + tan
    // inner), angled up-and-out like the logo's, not thin antenna
    // spikes.
    function earTuft(x, tilt) {
      const g = new THREE.Group();
      const outer = new THREE.Mesh(new THREE.ConeGeometry(0.26, 0.6, 4), bodyMat);
      outer.scale.set(0.85, 1, 0.5);
      g.add(outer, addOutline(outer, 0.05));
      const inner = new THREE.Mesh(new THREE.ConeGeometry(0.14, 0.4, 4), faceMat);
      inner.scale.set(0.8, 1, 0.5);
      inner.position.z = 0.06;
      g.add(inner);
      g.position.set(x, 0.92, 0.05);
      g.rotation.z = tilt;
      g.rotation.y = Math.PI / 4;
      return g;
    }
    owl.add(earTuft(-0.4, 0.28), earTuft(0.4, -0.28));

    // Wings — angled forward so they read clearly from the front camera
    // (the earlier pass hid them almost edge-on, removing a key
    // owl-identifying feature entirely).
    function wing(x, rotY) {
      const w = new THREE.Mesh(new THREE.SphereGeometry(0.48, 20, 16), bodyMat);
      w.scale.set(0.4, 0.95, 0.55);
      w.position.set(x, -0.1, 0.15);
      w.rotation.y = rotY;
      w.rotation.z = x < 0 ? 0.35 : -0.35;
      return w;
    }
    const wingL = wing(-0.92, 0.4);
    const wingR = wing(0.92, -0.4);
    owl.add(wingL, addOutline(wingL, 0.05), wingR, addOutline(wingR, 0.05));

    // Eyes — smaller and closer together than before (oversized, wide-
    // set eyes were the single biggest cause of the "alien" read), with
    // a thin dark ring so they pop against the light facial disc, same
    // as the logo.
    function eye(x) {
      const g = new THREE.Group();
      const white = new THREE.Mesh(new THREE.SphereGeometry(0.21, 20, 16), new THREE.MeshStandardMaterial({ color: 0xffffff, roughness: 0.25 }));
      g.add(white, addOutline(white, 0.16));
      const pupil = new THREE.Mesh(new THREE.SphereGeometry(0.105, 16, 12), new THREE.MeshStandardMaterial({ color: 0x2b1810, roughness: 0.5 }));
      pupil.position.set(0, -0.01, 0.16);
      const hl = new THREE.Mesh(new THREE.SphereGeometry(0.035, 8, 8), new THREE.MeshStandardMaterial({ color: 0xffffff, roughness: 0.1 }));
      hl.position.set(-0.05, 0.06, 0.24);
      g.add(pupil, hl);
      g.position.set(x, 0.14, 0.85);
      return g;
    }
    const eyeL = eye(-0.24);
    const eyeR = eye(0.24);
    owl.add(eyeL, eyeR);

    // Rosy cheeks — a cute logo-matching detail, absent before.
    function cheek(x) {
      const c = new THREE.Mesh(new THREE.SphereGeometry(0.09, 12, 10), new THREE.MeshStandardMaterial({ color: CHEEK_COLOR, roughness: 0.8, transparent: true, opacity: 0.85 }));
      c.scale.set(1, 0.8, 0.25);
      c.position.set(x, -0.08, 0.86);
      return c;
    }
    owl.add(cheek(-0.42), cheek(0.42));

    // Beak — bigger and clearly visible this time (the earlier one was
    // nearly invisible), warm yellow-orange matching the logo.
    const beak = new THREE.Mesh(new THREE.ConeGeometry(0.15, 0.2, 16), new THREE.MeshStandardMaterial({ color: BEAK_COLOR, roughness: 0.45 }));
    beak.scale.set(1, 0.8, 0.85);
    beak.position.set(0, -0.15, 0.93);
    beak.rotation.x = Math.PI * 0.56;
    owl.add(beak, addOutline(beak, 0.1));

    // Legs + feet — a real standing stance: a visible leg connecting
    // body to a proper flattened foot with toe bumps, instead of a
    // foot floating disconnected below the body.
    function legAndFoot(x) {
      const g = new THREE.Group();
      const legMat = new THREE.MeshStandardMaterial({ color: FOOT_COLOR, roughness: 0.55 });
      const leg = new THREE.Mesh(new THREE.CylinderGeometry(0.09, 0.1, 0.32, 10), legMat);
      leg.position.set(0, -1.02, 0.3);
      g.add(leg);
      const foot = new THREE.Mesh(new THREE.SphereGeometry(0.15, 14, 10), legMat);
      foot.scale.set(1.1, 0.5, 1.4);
      foot.position.set(0, -1.2, 0.42);
      g.add(foot, addOutline(foot, 0.08));
      for (let i = -1; i <= 1; i++) {
        const toe = new THREE.Mesh(new THREE.SphereGeometry(0.045, 8, 6), legMat);
        toe.position.set(i * 0.075, -1.2, 0.58);
        g.add(toe);
      }
      g.position.x = x;
      return g;
    }
    owl.add(legAndFoot(-0.38), legAndFoot(0.38));

    scene.add(owl);

    // Pointer parallax — small, clamped, purely decorative.
    let pointerX = 0, pointerY = 0;
    window.addEventListener('pointermove', (e) => {
      pointerX = ((e.clientX / window.innerWidth) * 2 - 1) * 0.12;
      pointerY = -((e.clientY / window.innerHeight) * 2 - 1) * 0.06;
    }, { passive: true });

    function backOut(t) {
      const c1 = 1.70158, c3 = c1 + 1;
      return 1 + c3 * Math.pow(t - 1, 3) + c1 * Math.pow(t - 1, 2);
    }

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let start = null;
    let blinkAt = 2 + Math.random() * 2;
    let blinking = false, blinkStart = 0;

    function frame(t) {
      if (start === null) start = t;
      const elapsed = (t - start) / 1000;

      const entranceDur = reducedMotion ? 0.01 : 0.85;
      const p = Math.min(elapsed / entranceDur, 1);
      owl.scale.setScalar(p < 1 ? Math.max(backOut(p), 0) : 1);

      if (!reducedMotion) {
        owl.position.y = Math.sin(elapsed * 1.3) * 0.06;
        owl.rotation.y = Math.sin(elapsed * 0.6) * 0.08 + pointerX;
        owl.rotation.x = pointerY;

        if (!blinking && elapsed > blinkAt) {
          blinking = true;
          blinkStart = elapsed;
        }
        if (blinking) {
          const bt = elapsed - blinkStart;
          const dur = 0.22;
          const bp = Math.min(bt / dur, 1);
          const s = bp < 0.5 ? 1 - (bp / 0.5) * 0.9 : 0.1 + ((bp - 0.5) / 0.5) * 0.9;
          eyeL.scale.y = s; eyeR.scale.y = s;
          if (bp >= 1) {
            blinking = false;
            eyeL.scale.y = 1; eyeR.scale.y = 1;
            blinkAt = elapsed + 2.5 + Math.random() * 2.5;
          }
        }
      }

      renderer.render(scene, camera);
      requestAnimationFrame(frame);
    }
    requestAnimationFrame(frame);

    window.addEventListener('resize', () => {
      const w = stage.clientWidth, h = stage.clientHeight;
      if (!w || !h) return;
      camera.aspect = w / h;
      camera.updateProjectionMatrix();
      renderer.setSize(w, h);
    });
  } catch (err) {
    const fb = document.getElementById('owlFallback');
    if (fb) fb.style.display = 'flex';
    const canvas = document.querySelector('#owlStage canvas');
    if (canvas) canvas.remove();
  }
</script>
</body>
</html>
