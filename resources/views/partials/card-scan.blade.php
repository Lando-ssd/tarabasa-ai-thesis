{{--
  "Scan my card": a camera sheet that reads the QR code on a child's learner card and fills in the Learner Code, so a child
  does not have to type it. Used on the child's own login and on the general sign in.

  What it does and does not do:
  - It only FILLS IN the code field. The child still types the secret PIN: the QR holds the code only, never the PIN, and
    the server decides everything as it always did (the same wrong-PIN lock applies).
  - No name is shown after a scan (devices are shared); only the code, as when it is typed.
  - The picture is read on the device by jsQR (public/vendor, loaded only when the sheet opens) and thrown away. Nothing is
    uploaded. The camera switches off when the sheet closes, the page is hidden or the child leaves.
  - The code is checked on the device first (its check digit), so a misread or somebody else's QR code is turned away
    before anything is typed or sent.

  Params: $target (selector of the code input), $focus (selector to focus after a scan), $speak (bool: speaker buttons;
  needs partials.speak on the page), $owl (bool: Tara peeks over the sheet; needs lottie-web on the page).
  Put a button with data-card-scan-open anywhere on the page to open it.
--}}
@php
    $speak = $speak ?? false;
    $owl = $owl ?? false;
    $icon = fn (string $name) => '<svg viewBox="0 0 256 256" aria-hidden="true" focusable="false"><use href="'.asset('icons/badges.svg').'#ph-'.$name.'"></use></svg>';
@endphp
<div class="cs-root" id="csRoot" hidden
     data-target="{{ $target }}" data-focus="{{ $focus }}" data-jsqr="{{ asset('vendor/jsQR-1.4.0.js') }}" data-chime="{{ asset('sounds/correct.mp3') }}"
     @if ($owl) data-owl="{{ asset('animations/learner/tara-owl-head.json') }}" @endif>
  <div class="cs-sheet" role="dialog" aria-modal="true" aria-labelledby="csTitle">
    @if ($owl)<div class="cs-owl" id="csOwl" aria-hidden="true"></div>@endif

    <div class="cs-panel" data-cs-panel="scan">
      <h2 id="csTitle">Show me your card!</h2>
      <p>Hold it so the square code is inside the frame.
        @if ($speak)<button type="button" class="cs-speak" data-speak="Show me your card! Hold it so the square code is inside the frame." aria-label="Hear this">{!! $icon('speaker-high') !!}</button>@endif
      </p>
      <p class="cs-warn" id="csWarn" role="alert" hidden></p>
      <div class="cs-cam loading" id="csCam" aria-label="Camera view">
        <video id="csVideo" playsinline muted></video>
        <div class="cs-demo" aria-hidden="true">{!! \App\Support\LearnerQr::svg('TB26-00005', 120) !!}<b>TB26-00005</b></div>
        <div class="cs-corners" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
        <div class="cs-line" aria-hidden="true"></div>
      </div>
      <p class="cs-state" id="csState" role="status" aria-live="polite">Starting the camera...</p>
      <div class="cs-btns"><button type="button" class="cs-ghost" data-cs-close>Close</button></div>
    </div>

    <div class="cs-panel" data-cs-panel="ok" hidden>
      <div class="cs-ok" aria-hidden="true">{!! $icon('check') !!}</div>
      <h2>Got it!</h2>
      <div class="cs-code" id="csCode"></div>
      <p>Now type your secret PIN.</p>
    </div>

    <div class="cs-panel" data-cs-panel="blocked" hidden>
      <h2>I cannot see your card</h2>
      <p class="cs-soft" id="csBlockedText">The camera is turned off. Ask a grown up to let TaraBasa use the camera, or type your code instead.</p>
      <div class="cs-btns"><button type="button" class="cs-main" data-cs-type>Type my code</button></div>
    </div>
  </div>
</div>

<style>
  .cs-root{ position:fixed; inset:0; z-index:2000; display:flex; align-items:flex-end; justify-content:center; background:rgba(19,31,43,.55); font-family:'Inter',system-ui,sans-serif; }
  .cs-root[hidden]{ display:none; }
  .cs-sheet{ position:relative; width:100%; max-width:460px; background:#fff; border-radius:30px 30px 0 0; padding:46px 20px 24px; text-align:center; box-shadow:0 -20px 50px -20px rgba(0,0,0,.4); animation:csUp .25s ease; }
  @media (min-width:640px){ .cs-root{ align-items:center; } .cs-sheet{ border-radius:30px; } }
  @keyframes csUp{ from{ transform:translateY(24px); opacity:0; } to{ transform:none; opacity:1; } }
  .cs-owl{ position:absolute; left:50%; top:0; translate:-50% -62%; width:150px; height:110px; pointer-events:none; }
  .cs-sheet h2{ font:800 28px/1.1 'Baloo 2','Barlow Semi Condensed',sans-serif; color:#0f5fae; margin:6px 0 4px; }
  .cs-sheet p{ margin:0 0 12px; font-size:17px; font-weight:600; color:#5b6b7a; line-height:1.4; }
  .cs-speak{ display:inline-flex; align-items:center; justify-content:center; width:42px; height:42px; margin-left:6px; vertical-align:middle; border:0; border-radius:50%; cursor:pointer; background:#1c7ed6; color:#fff; box-shadow:0 3px 0 #0a3d73; }
  .cs-speak svg{ width:22px; height:22px; fill:currentColor; }
  .cs-cam{ position:relative; width:min(100%,300px); aspect-ratio:1; margin:6px auto 10px; border-radius:26px; overflow:hidden; background:radial-gradient(circle at 50% 40%,#2a3f54,#0d1a26); border:3px solid #131f2b; }
  .cs-cam video{ position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
  .cs-corners i{ position:absolute; width:56px; height:56px; border:7px solid #fff; filter:drop-shadow(0 2px 4px rgba(0,0,0,.5)); transition:border-color .15s; }
  .cs-corners i:nth-child(1){ left:22px; top:22px; border-right:0; border-bottom:0; border-top-left-radius:20px; }
  .cs-corners i:nth-child(2){ right:22px; top:22px; border-left:0; border-bottom:0; border-top-right-radius:20px; }
  .cs-corners i:nth-child(3){ left:22px; bottom:22px; border-right:0; border-top:0; border-bottom-left-radius:20px; }
  .cs-corners i:nth-child(4){ right:22px; bottom:22px; border-left:0; border-top:0; border-bottom-right-radius:20px; }
  .cs-cam.bad .cs-corners i{ border-color:#ff8a8a; }
  .cs-line{ position:absolute; left:34px; right:34px; height:4px; border-radius:4px; background:linear-gradient(90deg,transparent,#7fe0c4,transparent); box-shadow:0 0 14px #7fe0c4; animation:csSweep 2.2s ease-in-out infinite alternate; }
  @keyframes csSweep{ from{ top:20%; } to{ top:78%; } }
  .cs-demo{ display:none; position:absolute; left:50%; top:50%; width:46%; translate:-50% -50%; background:#fff; border-radius:12px; padding:9px; box-shadow:0 10px 24px rgba(0,0,0,.45); animation:csHold 4.4s ease-in-out infinite; }
  .cs-cam.loading .cs-demo{ display:block; }
  .cs-demo svg{ display:block; width:100%; height:auto; }
  .cs-demo b{ display:block; text-align:center; font:700 11px/1.2 'Baloo 2',sans-serif; color:#0f5fae; letter-spacing:.06em; margin-top:4px; }
  @keyframes csHold{ 0%{ transform:translate(40%,40%) scale(.7) rotate(8deg); opacity:0; } 35%,75%{ transform:none; opacity:1; } 100%{ transform:translate(-40%,-30%) scale(.8) rotate(-8deg); opacity:0; } }
  .cs-state{ min-height:1.4em; font-size:15px !important; margin-bottom:10px !important; }
  .cs-btns{ display:flex; flex-direction:column; gap:10px; align-items:center; }
  .cs-ghost{ min-height:54px; padding:14px 26px 12px; border-radius:16px; border:2px solid #e7edf3; background:#fff; color:#5b6b7a; font:800 18px/1 'Baloo 2',sans-serif; letter-spacing:.05em; text-transform:uppercase; cursor:pointer; box-shadow:0 4px 0 #e3ebf3; }
  .cs-main{ width:100%; min-height:60px; padding:14px 18px 12px; border:2px solid #d97a1a; border-radius:20px; cursor:pointer; background:linear-gradient(#f9a544,#ef8d2a); color:#fff; font:800 20px/1 'Baloo 2',sans-serif; letter-spacing:.05em; text-transform:uppercase; text-shadow:0 1px 0 rgba(0,0,0,.18); box-shadow:0 5px 0 #b4560b, inset 0 2px 0 rgba(255,255,255,.35); }
  .cs-warn{ background:#fdecec; border:2px solid #f0b3b3; color:#9c2c2c !important; border-radius:16px; padding:10px 14px; font-weight:700 !important; }
  .cs-soft{ background:#fff3d6; border:2px solid #f1d9a4; color:#7a5200 !important; border-radius:16px; padding:12px 14px; font-weight:700 !important; margin-bottom:14px !important; }
  .cs-ok{ width:96px; height:96px; margin:4px auto 8px; border-radius:50%; background:#1f9e83; color:#fff; display:grid; place-items:center; box-shadow:0 8px 0 #167a64; animation:csPop .45s cubic-bezier(.3,1.6,.5,1); }
  .cs-ok svg{ width:56px; height:56px; fill:currentColor; }
  @keyframes csPop{ from{ transform:scale(.3); opacity:0; } to{ transform:none; opacity:1; } }
  .cs-code{ font:800 40px/1 'Baloo 2',sans-serif; letter-spacing:.08em; color:#0f6a55; margin:2px 0 8px; }
  .cs-ghost:focus-visible, .cs-main:focus-visible, .cs-speak:focus-visible{ outline:3px solid #ef8d2a; outline-offset:2px; }
  .cs-panel[hidden]{ display:none; }
  @media (prefers-reduced-motion:reduce){ .cs-sheet, .cs-line, .cs-demo, .cs-ok{ animation:none; } .cs-demo{ opacity:1; } }
</style>

<script>
(function () {
  var root = document.getElementById('csRoot');
  if (!root) { return; }
  var openers = document.querySelectorAll('[data-card-scan-open]');
  if (!openers.length) { return; }

  var video = document.getElementById('csVideo'), cam = document.getElementById('csCam'), state = document.getElementById('csState'), warn = document.getElementById('csWarn');
  var stream = null, timer = null, decode = null, opener = null, warnTimer = null, closeTimer = null, owl = null;
  var canvas = document.createElement('canvas'), ctx = canvas.getContext('2d', { willReadFrequently: true });

  // Same check digit the server makes (App\Support\LearnerCode::checkDigit): Luhn over the year and four digits.
  function checkDigit(digits) {
    var sum = 0, dbl = true;
    for (var i = digits.length - 1; i >= 0; i--) { var d = parseInt(digits.charAt(i), 10); if (dbl) { d *= 2; if (d > 9) { d -= 9; } } sum += d; dbl = !dbl; }
    return String((10 - (sum % 10)) % 10);
  }
  function parse(text) {
    var t = String(text || '').toUpperCase().replace(/\s+/g, '');
    var m = t.match(/TB(\d{2})-?(\d{4})(\d)(?!\d)/);
    if (m) {
      if (checkDigit(m[1] + m[2]) !== m[3]) { return { error: 'That card looks scratched. Try again, or type your code.' }; }
      return { code: 'TB' + m[1] + '-' + m[2] + m[3] };
    }
    var o = t.match(/TB-([0-9A-Z]{5})(?![0-9A-Z])/);
    return o ? { code: 'TB-' + o[1] } : { error: 'That is not a TaraBasa card. Try your own card.' };
  }
  function loadJsQR() {
    return new Promise(function (resolve, reject) {
      if (window.jsQR) { return resolve(window.jsQR); }
      var s = document.createElement('script'); s.src = root.getAttribute('data-jsqr');
      s.onload = function () { window.jsQR ? resolve(window.jsQR) : reject(new Error('decoder')); };
      s.onerror = function () { reject(new Error('decoder')); };
      document.head.appendChild(s);
    });
  }

  function panel(name) { [].forEach.call(root.querySelectorAll('[data-cs-panel]'), function (p) { p.hidden = p.getAttribute('data-cs-panel') !== name; }); }
  function stopCamera() {
    if (timer) { clearInterval(timer); timer = null; }
    if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
    try { video.srcObject = null; } catch (e) { /* fine */ }
  }
  function close(refocus) {
    clearTimeout(closeTimer); clearTimeout(warnTimer); stopCamera();
    root.hidden = true; if (owl) { try { owl.pause(); } catch (e) { /* fine */ } }
    if (refocus && opener) { try { opener.focus(); } catch (e) { /* fine */ } }
  }
  function say(text, bad) {
    if (!bad) { warn.hidden = true; state.textContent = text; return; }
    warn.textContent = text; warn.hidden = false; cam.classList.add('bad');
    clearTimeout(warnTimer); warnTimer = setTimeout(function () { warn.hidden = true; cam.classList.remove('bad'); }, 3200);
  }
  function blocked(text) {
    stopCamera(); panel('blocked');
    if (text) { document.getElementById('csBlockedText').textContent = text; }
  }

  function fill(code) {
    var input = document.querySelector(root.getAttribute('data-target'));
    if (!input) { return; }
    input.value = code;
    // Marked as scanned BEFORE the page's own scripts hear about it (they listen for typing: the general sign in
    // switches to the PIN box on a learner code, the child's login says "Card scanned" and lights the first PIN box).
    input.classList.add('cs-scanned');
    input.dispatchEvent(new Event('input', { bubbles: true }));
    var next = document.querySelector(root.getAttribute('data-focus'));
    try { (next || input).focus({ preventScroll: false }); } catch (e) { /* fine */ }
  }
  function success(code) {
    stopCamera(); panel('ok'); document.getElementById('csCode').textContent = code;
    try { var a = new Audio(root.getAttribute('data-chime')); a.volume = 0.35; a.play().catch(function () {}); } catch (e) { /* fine */ }
    try { if (navigator.vibrate) { navigator.vibrate(60); } } catch (e) { /* fine */ }
    closeTimer = setTimeout(function () { close(false); fill(code); }, 1700);
  }

  function frame() {
    if (!video.videoWidth) { return; }
    cam.classList.remove('loading');
    var scale = Math.min(1, 640 / video.videoWidth);
    canvas.width = Math.round(video.videoWidth * scale); canvas.height = Math.round(video.videoHeight * scale);
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    var img = ctx.getImageData(0, 0, canvas.width, canvas.height);
    var hit = decode(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' });
    if (!hit || !hit.data) { return; }
    var parsed = parse(hit.data);
    if (parsed.code) { success(parsed.code); } else { say(parsed.error, true); }
  }

  function open(btn) {
    opener = btn; panel('scan'); warn.hidden = true; cam.classList.add('loading'); cam.classList.remove('bad'); say('Starting the camera...');
    root.hidden = false;
    if (!owl && root.hasAttribute('data-owl') && window.lottie) {
      try { owl = lottie.loadAnimation({ container: document.getElementById('csOwl'), renderer: 'svg', loop: true, autoplay: true, path: root.getAttribute('data-owl') }); } catch (e) { /* fine */ }
    } else if (owl) { try { owl.play(); } catch (e) { /* fine */ } }
    try { root.querySelector('[data-cs-close]').focus(); } catch (e) { /* fine */ }

    if (!window.isSecureContext || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      blocked('This tablet cannot use the camera here. Ask a grown up for help, or type your code instead.'); return;
    }
    loadJsQR().then(function (fn) {
      decode = fn;
      return navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 960 } }, audio: false });
    }).then(function (s) {
      if (root.hidden) { s.getTracks().forEach(function (t) { t.stop(); }); return; }
      stream = s; video.srcObject = s; return video.play();
    }).then(function () {
      if (stream) { say('Looking for your card...'); timer = setInterval(frame, 120); }
    }).catch(function (e) {
      var n = e && e.name;
      blocked(n === 'NotFoundError' || n === 'OverconstrainedError'
        ? 'There is no camera on this device. Type your code instead.'
        : 'The camera is turned off. Ask a grown up to let TaraBasa use the camera, or type your code instead.');
    });
  }

  [].forEach.call(openers, function (b) { b.addEventListener('click', function (e) { e.preventDefault(); open(b); }); });
  root.addEventListener('click', function (e) {
    if (e.target === root || e.target.closest('[data-cs-close]')) { close(true); return; }
    if (e.target.closest('[data-cs-type]')) { close(false); var i = document.querySelector(root.getAttribute('data-target')); if (i) { i.focus(); } }
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !root.hidden) { close(true); } });
  document.addEventListener('visibilitychange', function () { if (document.hidden && !root.hidden) { close(false); } });
  window.addEventListener('pagehide', function () { stopCamera(); });
})();
</script>
