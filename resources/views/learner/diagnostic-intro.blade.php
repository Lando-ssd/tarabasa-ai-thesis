{{--
  The first screen a new learner sees: Tara the owl greets them, a thought cloud
  says "Let's Read Together, {name}!", and one big button starts the reading
  check. It is a full-screen scene (owl on one side, cloud, message and button on
  the other) that stacks on phones. Never the word "test" (Placement Diagnostic
  patch, Part 4.1).

  The wait while the reading passages are being written happens AFTER the button
  is pressed, on this same screen with a friendly loading state, instead of a
  blank white page before this screen could even appear.
--}}
<!DOCTYPE html>
<html lang="en" data-page="diagnostic">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Let's Read Together | TaraBasa AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Barlow+Semi+Condensed:wght@500;600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/lottie-web@5.12.2/build/player/lottie.min.js"></script>
<link rel="stylesheet" href="{{ asset('css/learner-app.css') }}?v={{ substr(md5_file(public_path('css/learner-app.css')), 0, 12) }}">
<style>
  html[data-page="diagnostic"]{ background:#d3e6fb; }
  body{ background:transparent; }

  .dx{ min-height:100vh; display:flex; align-items:center; justify-content:center; padding:32px clamp(20px, 5vw, 72px); }
  .dx-stage{ width:100%; max-width:1280px; display:grid; grid-template-columns:minmax(0, 1fr) minmax(0, 1fr); align-items:center; gap:clamp(20px, 4vw, 72px); }

  /* ---- Tara ---- */
  .dx-owl-wrap{ position:relative; width:100%; max-width:min(620px, 80vh); aspect-ratio:1; margin:0 auto; }
  .dx-owl{ position:absolute; inset:0; }
  .dx-ground{ position:absolute; left:26%; right:26%; bottom:11%; height:4.5%; border-radius:50%; background:rgba(19,64,110,.18); filter:blur(7px); }

  /* ---- the thought cloud, its trail of bubbles, the message and the button ---- */
  .dx-talk{ display:flex; flex-direction:column; align-items:flex-start; min-width:0; }
  .dx-cloud{ position:relative; width:100%; max-width:600px; aspect-ratio:640 / 360; margin-bottom:34px; }
  .dx-cloud-shape{ position:absolute; inset:0; width:100%; height:100%; overflow:visible; filter:drop-shadow(0 9px 0 #e3b53a) drop-shadow(0 26px 28px rgba(19,64,110,.2)); }
  .dx-cloud h1{
    position:absolute; inset:0; display:flex; align-items:center; justify-content:center; text-align:center; margin:0; padding:0 15%;
    font-family:var(--font-game); font-weight:700; font-size:clamp(32px, 3.5vw, 54px); line-height:1.08; color:#2d2308;
  }
  .dx-bubble{ position:absolute; border-radius:50%; background:#ffe08a; box-shadow:0 5px 0 #e3b53a; }
  .dx-bubble.b1{ width:6.2%; aspect-ratio:1; left:-3%; bottom:-6%; }
  .dx-bubble.b2{ width:3.6%; aspect-ratio:1; left:-8.5%; bottom:-15%; }

  .dx-msg{ font-family:var(--font-game); font-weight:500; font-size:clamp(21px, 2vw, 30px); line-height:1.38; color:#2f455b; max-width:540px; margin:0 0 30px; }

  .dx-go{ font-size:clamp(24px, 2.1vw, 32px); letter-spacing:.06em; padding:20px 64px 18px; border-radius:22px; text-decoration:none; display:inline-block; text-align:center; }
  .clay-btn.dx-go.orange{ box-shadow:0 7px 0 #b4560b, 0 18px 24px -10px rgba(180,86,11,.65), inset 0 2px 0 rgba(255,255,255,.45); }
  .clay-btn.dx-go.orange:active{ transform:translateY(4px); box-shadow:0 3px 0 #b4560b, 0 8px 12px -6px rgba(180,86,11,.6), inset 0 2px 0 rgba(255,255,255,.4); }
  .dx-go[aria-busy="true"]{ pointer-events:none; opacity:.92; }
  .dx-dots::after{ content:''; display:inline-block; width:1.2em; text-align:left; animation:dxDots 1.2s steps(4, end) infinite; }
  @keyframes dxDots{ 0%{ content:''; } 25%{ content:'.'; } 50%{ content:'..'; } 75%{ content:'...'; } 100%{ content:''; } }

  .dx-wait{ font-family:var(--font-game); font-weight:500; font-size:clamp(18px, 1.5vw, 22px); color:#3f566d; margin:16px 0 0; min-height:1.4em; }
  .dx-note{ font-family:var(--font-game); font-weight:600; font-size:clamp(18px, 1.5vw, 22px); line-height:1.35; color:#7a2d16; background:#ffe4dc; border-radius:14px; padding:12px 18px; margin:0 0 20px; max-width:540px; }

  /* ---- phones and small tablets: the scene stacks, Tara on top ---- */
  @media (max-width:860px){
    .dx{ align-items:flex-start; padding:20px 20px 32px; }
    .dx-stage{ grid-template-columns:1fr; gap:0; }
    .dx-owl-wrap{ max-width:min(74vw, 40vh, 380px); }
    .dx-talk{ align-items:center; text-align:center; }
    .dx-cloud{ max-width:460px; margin:6px auto 24px; }
    .dx-bubble.b1{ left:33%; top:-8%; bottom:auto; width:6.5%; }
    .dx-bubble.b2{ left:29%; top:-15.5%; bottom:auto; width:3.8%; }
    .dx-msg{ margin-left:auto; margin-right:auto; }
    .dx-go{ width:100%; max-width:460px; padding:20px 24px 18px; }
    .dx-note{ text-align:left; }
  }
  @media (max-height:640px) and (min-width:861px){ .dx-owl-wrap{ max-width:min(620px, 70vh); } }
  @media (prefers-reduced-motion:reduce){ .dx-dots::after{ animation:none; content:'...'; } }
</style>
</head>
<body>
<main class="dx">
  <div class="dx-stage">

    <div class="dx-owl-wrap">
      <div class="dx-ground" aria-hidden="true"></div>
      <div class="dx-owl" id="taraOwl" role="img" aria-label="Tara the owl, jumping and waving hello"></div>
    </div>

    <div class="dx-talk">
      <div class="dx-cloud">
        <svg class="dx-cloud-shape" viewBox="0 0 640 360" aria-hidden="true">
          <g fill="#ffe08a">
            <circle cx="132" cy="216" r="92"/>
            <circle cx="214" cy="138" r="96"/>
            <circle cx="332" cy="102" r="106"/>
            <circle cx="452" cy="128" r="100"/>
            <circle cx="522" cy="208" r="96"/>
            <circle cx="470" cy="272" r="82"/>
            <circle cx="338" cy="276" r="92"/>
            <circle cx="214" cy="274" r="82"/>
            <rect x="130" y="150" width="392" height="150" rx="60"/>
          </g>
        </svg>
        <h1>Let's Read Together, {{ $learner->first_name }}!</h1>
        <span class="dx-bubble b1" aria-hidden="true"></span>
        <span class="dx-bubble b2" aria-hidden="true"></span>
      </div>

      <p class="dx-msg">Tara the owl wants to hear you read! There are no wrong answers. Just do your best.</p>

      @if ($errors->has('diagnostic'))
        <p class="dx-note" role="alert">Tara could not find your story just now. Please tap the button to try again.</p>
      @endif

      <a href="{{ route('learner.diagnostic.passage') }}" class="clay-btn orange dx-go" id="goBtn">I'm Ready</a>
      <p class="dx-wait" id="waitNote" aria-live="polite"></p>
    </div>

  </div>
</main>

<script>
  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Tara: a two second jump-and-wave loop. With reduced motion she just stands there, waving.
  var tara = lottie.loadAnimation({
    container: document.getElementById('taraOwl'), renderer: 'svg', loop: true, autoplay: !reduceMotion,
    path: @json(asset('animations/learner/tara-owl-intro.json'))
  });
  if (reduceMotion) { tara.addEventListener('DOMLoaded', function () { tara.goToAndStop(0, true); }); }

  // Writing the reading passages takes a while the first time. Say so, keep Tara jumping, and go on when it is ready.
  var btn = document.getElementById('goBtn');
  var note = document.getElementById('waitNote');
  btn.addEventListener('click', function (e) {
    if (btn.getAttribute('aria-busy') === 'true') { e.preventDefault(); return; }
    e.preventDefault();
    btn.setAttribute('aria-busy', 'true');
    btn.innerHTML = 'Getting your story ready<span class="dx-dots"></span>';
    note.textContent = 'This can take a minute. Tara is picking the best story for you.';
    window.location.href = btn.href;
  });
  // coming back with the browser's back button must not leave the button stuck on "getting ready"
  window.addEventListener('pageshow', function (e) {
    if (e.persisted) { btn.removeAttribute('aria-busy'); btn.textContent = "I'm Ready"; note.textContent = ''; }
  });
</script>
</body>
</html>
