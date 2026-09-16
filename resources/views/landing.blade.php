<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TaraBasa AI — Choose your role</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/lottie-web@5.12.2/build/player/lottie.min.js"></script>
<style>
  :root{
    --sky-50:#eef6ff; --sky-100:#dcedff;
    --blue-500:#1c7ed6; --blue-600:#0f5fae; --blue-700:#0a3d73;
    --navy-900:#131f2b; --slate-600:#5b6b7a; --slate-400:#8a97a3;
    --owl-orange-400:#f5a544; --owl-orange-500:#ef8d2a; --owl-orange-600:#dd7014;
    --clay-yellow:#ffcf6e; --parent-teal:#1f9e83;
    --line:#e3ebf2; --surface:#ffffff; --bg-0:#f6faff;
    --shadow-sm:0 1px 2px rgba(19,31,43,0.06);
    --radius-lg:22px; --radius-md:16px; --radius-sm:10px;
  }
  *{box-sizing:border-box;}
  html{ scroll-behavior:smooth; }
  html,body{margin:0;padding:0;}
  body{ font-family:'Inter',sans-serif; color:var(--navy-900); background:var(--bg-0); overflow-x:hidden; }
  .wrap{ max-width:1040px; margin:0 auto; padding:0 20px 48px; }

  /* ---------- Sticky nav ---------- */
  .site-nav{
    position:sticky; top:0; z-index:50;
    background:rgba(255,255,255,0.55); backdrop-filter:blur(10px); -webkit-backdrop-filter:blur(10px);
    transition:background-color .25s ease, box-shadow .25s ease;
  }
  .site-nav.scrolled{ background:rgba(255,255,255,0.92); box-shadow:0 6px 20px -10px rgba(19,31,43,0.2); }
  .nav-inner{ max-width:1040px; margin:0 auto; padding:14px 20px; display:flex; align-items:center; justify-content:space-between; gap:16px; }
  .nav-logo{ display:flex; align-items:center; gap:10px; }
  .nav-logo .logo-badge{ width:38px; height:38px; border-radius:12px; overflow:hidden; flex-shrink:0; box-shadow:0 6px 14px -6px rgba(15,95,174,0.5); }
  .nav-logo .logo-badge img{ width:100%; height:100%; object-fit:cover; display:block; }
  .nav-logo .wordmark{ font-family:'Baloo 2',sans-serif; font-weight:700; font-size:19px; color:var(--navy-900); }
  .nav-logo .wordmark span{ color:var(--owl-orange-500); }

  .nav-actions{ display:flex; align-items:center; gap:10px; }
  .nav-dropdown{ position:relative; }
  .nav-btn{
    display:inline-flex; align-items:center; gap:7px; padding:10px 16px; border-radius:11px;
    font:700 13.5px/1 'Inter',sans-serif; cursor:pointer; white-space:nowrap; text-decoration:none;
    transition:transform .15s ease, background-color .15s ease, border-color .15s ease, color .15s ease;
  }
  .nav-btn.ghost{ background:none; border:1.5px solid var(--blue-500); color:var(--blue-600); }
  .nav-btn.ghost:hover{ background:var(--sky-50); }
  .nav-btn.solid{ background:linear-gradient(155deg,var(--blue-500),var(--blue-700)); border:none; color:#fff; box-shadow:0 8px 16px -6px rgba(15,95,174,.5); }
  .nav-btn.solid:hover{ transform:translateY(-1px); }
  .nav-btn[aria-expanded="true"]{ box-shadow:0 0 0 3px rgba(28,126,214,0.25); }
  @media (max-width:400px){
    .nav-inner{ padding:12px 14px; gap:10px; }
    .nav-actions{ gap:6px; }
    .nav-logo .wordmark{ font-size:16.5px; }
    .nav-btn{ padding:9px 11px; font-size:12px; gap:0; }
  }

  .dropdown-menu{
    position:absolute; top:calc(100% + 8px); right:0; min-width:190px; z-index:20;
    background:var(--surface); border:1px solid var(--line); border-radius:14px; box-shadow:0 20px 40px -18px rgba(19,31,43,0.3);
    padding:6px; opacity:0; transform:translateY(-6px); visibility:hidden; pointer-events:none;
    transition:opacity .15s ease, transform .15s ease, visibility .15s ease;
  }
  .dropdown-menu.open{ opacity:1; transform:translateY(0); visibility:visible; pointer-events:auto; }
  .dropdown-item{
    display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:9px;
    font-size:13.5px; font-weight:600; color:var(--navy-900); text-decoration:none;
    transition:background-color .15s ease;
  }
  .dropdown-item:hover{ background:var(--sky-50); }
  .dropdown-item .dot{ width:8px; height:8px; border-radius:50%; flex-shrink:0; }
  .dropdown-item .dot.teacher{ background:var(--blue-500); }
  .dropdown-item .dot.parent{ background:var(--parent-teal); }
  @media (max-width:400px){ .dropdown-menu{ right:-40px; } }

  /* ---------- Hero scene (layered) ---------- */
  .hero{
    position:relative; overflow:hidden;
    background:linear-gradient(180deg, var(--blue-700) 0%, var(--blue-600) 32%, var(--blue-500) 62%, var(--sky-100) 100%);
    padding-top:38px;
  }
  .cloud-layer{ position:absolute; inset:0; z-index:1; pointer-events:none; }
  .cloud{ position:absolute; }
  /* The outer .cloud div only ever gets a scroll-parallax translateY (set via
     JS) — the continuous idle drift lives on the svg child instead, so the
     two transforms never fight over the same element. */
  .cloud svg{ display:block; width:100%; height:auto; animation:cloudDrift linear infinite alternate; }
  @keyframes cloudDrift{ 0%{ transform:translateX(0); } 100%{ transform:translateX(46vw); } }
  .horizon-band{ position:relative; z-index:2; margin-top:-2px; }
  .horizon-band svg{ display:block; width:100%; height:auto; }
  @media (max-width:640px){
    /* On mobile the hero stacks to one column and text runs full-width, so
       clouds tuned for the two-column desktop layout would sit on top of the
       headline. Keep only the two small corner clouds, clear of the text. */
    .cloud-wide{ display:none; }
  }
  @media (prefers-reduced-motion: reduce){
    .cloud svg{ animation:none; }
  }

  .hero-inner{ position:relative; z-index:3; max-width:1040px; margin:0 auto; padding:24px 20px 0; }
  .hero-grid{ position:relative; display:grid; grid-template-columns:1fr 1fr; gap:20px; align-items:center; }
  .hero-copy{ text-align:left; }
  .hero-headline{
    font-family:'Baloo 2',sans-serif; font-weight:800; line-height:1.12;
    font-size:clamp(28px, 4.2vw, 44px); margin:0 0 14px; color:#ffffff;
  }
  .hero-headline .accent{ color:var(--owl-orange-400); }
  .hero-sub{ font-size:16px; color:var(--sky-50); opacity:.92; font-weight:500; line-height:1.55; margin:0; max-width:420px; }

  .hero-mascot{ position:relative; display:flex; flex-direction:column; align-items:center; }
  /* Bumped up again per direct feedback - 700px is a real, meaningfully
     larger jump from 560px (and 420px, and the original static SVG's
     320px). The stacking breakpoint below is re-tuned to match. */
  .mascot-stage{ position:relative; width:min(700px, 86vw); }
  /* The Lottie asset's own composition is a 1080x1080 square (confirmed from
     its real source, unrelated to the old static SVG's 300x320 viewBox), so
     the container is square too — forcing the old aspect ratio here would
     stretch the new animation. */
  .hero-owl-lottie{ position:relative; z-index:1; width:100%; aspect-ratio:1/1; display:block; }

  /* Re-tuned again for the 700px owl (previously 920px, for the 560px owl) -
     re-tested the same way: below ~1060px, a fixed 700px mascot squeezed the
     text column down to ~175px. Stacking to one column below this width
     keeps the mascot full-size without crowding the text. */
  @media (max-width:1060px){
    .hero-grid{ grid-template-columns:1fr; gap:14px; }
    .hero-copy{ text-align:center; }
    .hero-sub{ margin:0 auto; }
    .hero-mascot{ order:2; margin-top:6px; }
  }

  a:focus-visible, button:focus-visible{ outline:2px solid var(--blue-500); outline-offset:2px; }

  @media (prefers-reduced-motion: reduce){
    html{ scroll-behavior:auto; }
  }
</style>
</head>
<body>

  <nav class="site-nav" id="siteNav">
    <div class="nav-inner">
      <div class="nav-logo">
        <div class="logo-badge"><img src="{{ asset('images/logo.png') }}" alt="TaraBasa AI logo"></div>
        <span class="wordmark">TaraBasa<span>AI</span></span>
      </div>

      <div class="nav-actions">
        <!-- Direct link, no role picker: the login form is one shared form for
             Teacher/Parent/Admin, so forcing a role choice before reaching it
             was an unnecessary extra step. -->
        <a class="nav-btn ghost" href="{{ route('login') }}">
          <span class="btn-label">Log in</span>
        </a>

        <div class="nav-dropdown" data-dropdown>
          <button type="button" class="nav-btn solid" aria-haspopup="true" aria-expanded="false" aria-label="Get Started" data-dropdown-trigger>
            <span class="btn-label">Get Started</span>
          </button>
          <!-- No Admin option here — no self-registration for Admin accounts (Admin Actor Prompt). -->
          <div class="dropdown-menu" role="menu" aria-label="Sign up as" data-dropdown-menu>
            <a class="dropdown-item" role="menuitem" href="{{ route('register.teacher') }}"><span class="dot teacher"></span>Teacher</a>
            <a class="dropdown-item" role="menuitem" href="{{ route('register.parent') }}"><span class="dot parent"></span>Parent</a>
          </div>
        </div>
      </div>
    </div>
  </nav>

  <section class="hero">
    <!-- Layer 2: cloud field — varied size/opacity/blur to imply near vs far, plus a slow parallax drift. -->
    <div class="cloud-layer">
      <div class="cloud cloud-corner" data-parallax="0.06" style="top:4%; left:4%; width:56px; opacity:.4; filter:blur(1.5px);">
        <svg viewBox="0 0 100 60" fill="none" style="animation-duration:95s; animation-delay:-12s;"><ellipse cx="30" cy="34" rx="30" ry="18" fill="#fff"/><ellipse cx="58" cy="24" rx="22" ry="16" fill="#fff"/><ellipse cx="74" cy="36" rx="18" ry="13" fill="#fff"/></svg>
      </div>
      <div class="cloud cloud-wide" data-parallax="0.1" style="top:14%; left:42%; width:44px; opacity:.35; filter:blur(2px);">
        <svg viewBox="0 0 100 60" fill="none" style="animation-duration:115s; animation-delay:-34s;"><ellipse cx="34" cy="32" rx="26" ry="16" fill="#fff"/><ellipse cx="62" cy="24" rx="18" ry="14" fill="#fff"/></svg>
      </div>
      <div class="cloud cloud-corner" data-parallax="0.14" style="top:4%; right:4%; width:76px; opacity:.55;">
        <svg viewBox="0 0 100 60" fill="none" style="animation-duration:72s; animation-delay:-6s;"><ellipse cx="34" cy="32" rx="26" ry="16" fill="#fff"/><ellipse cx="62" cy="24" rx="18" ry="14" fill="#fff"/><ellipse cx="12" cy="38" rx="14" ry="10" fill="#fff"/></svg>
      </div>
      <div class="cloud cloud-wide" data-parallax="0.2" style="top:8%; left:6%; width:110px; opacity:.7;">
        <svg viewBox="0 0 100 60" fill="none" style="animation-duration:55s; animation-delay:-21s;"><ellipse cx="30" cy="34" rx="30" ry="18" fill="#fff"/><ellipse cx="58" cy="24" rx="22" ry="16" fill="#fff"/><ellipse cx="74" cy="36" rx="18" ry="13" fill="#fff"/></svg>
      </div>
      <div class="cloud cloud-wide" data-parallax="0.22" style="top:34%; right:16%; width:100px; opacity:.65;">
        <svg viewBox="0 0 100 60" fill="none" style="animation-duration:62s; animation-delay:-45s;"><ellipse cx="34" cy="32" rx="26" ry="16" fill="#fff"/><ellipse cx="62" cy="24" rx="18" ry="14" fill="#fff"/></svg>
      </div>
      <div class="cloud cloud-wide" data-parallax="0.16" style="top:4%; left:64%; width:34px; opacity:.3; filter:blur(1.5px);">
        <svg viewBox="0 0 100 60" fill="none" style="animation-duration:135s; animation-delay:-58s;"><ellipse cx="30" cy="34" rx="30" ry="18" fill="#fff"/><ellipse cx="58" cy="24" rx="22" ry="16" fill="#fff"/></svg>
      </div>
    </div>

    <div class="hero-inner">
      <div class="hero-grid">
        <div class="hero-copy">
          <h1 class="hero-headline">Every child deserves to <span class="accent">love reading.</span></h1>
          <p class="hero-sub">AI-powered, teacher-verified reading support built for Grade&nbsp;1–3 Filipino learners.</p>
        </div>

        <div class="hero-mascot" data-parallax="0.03">
          <div class="mascot-stage">
            <!-- Real Lottie greeting animation (jsdelivr/lottie-web@5.12.2, the
                 same CDN/version already established in this app). Frames 0-176
                 are the verified clean loop — the body's own position/rotation
                 and the speech-bubble's own scale all return to the exact same
                 "at rest" values at both ends. Includes its own baked-in ground
                 shadow (the "sombra" layer), so no separate shadow div is
                 needed here — an opaque white background layer baked into the
                 raw export was stripped out before saving, or it would have
                 rendered as a solid white square behind the owl. -->
            <div id="heroOwlLottie" class="hero-owl-lottie" role="img" aria-label="Basa the owl waving hello"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Layer 3: horizon — a wavy, curved edge (not a hard line) transitioning into the page's normal background. -->
    <div class="horizon-band">
      <svg viewBox="0 0 1440 90" preserveAspectRatio="none" fill="none">
        <path d="M0,46 C 180,10 340,78 520,50 C 700,22 860,72 1040,48 C 1220,24 1320,60 1440,40 L1440,90 L0,90 Z" fill="var(--bg-0)"/>
      </svg>
    </div>
  </section>

<script>
  // Get Started dropdown: closes on outside click / Escape.
  const dropdowns = document.querySelectorAll('[data-dropdown]');
  function closeAllDropdowns() {
    dropdowns.forEach(d => {
      d.querySelector('[data-dropdown-menu]').classList.remove('open');
      d.querySelector('[data-dropdown-trigger]').setAttribute('aria-expanded', 'false');
    });
  }
  dropdowns.forEach(dropdown => {
    const trigger = dropdown.querySelector('[data-dropdown-trigger]');
    const menu = dropdown.querySelector('[data-dropdown-menu]');
    trigger.addEventListener('click', function (e) {
      e.stopPropagation();
      const wasOpen = menu.classList.contains('open');
      closeAllDropdowns();
      if (!wasOpen) {
        menu.classList.add('open');
        trigger.setAttribute('aria-expanded', 'true');
      }
    });
  });
  document.addEventListener('click', closeAllDropdowns);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAllDropdowns(); });

  // Sticky nav gains a solid backdrop once scrolled, plus a subtle parallax
  // drift on the cloud layer and mascot — background moves slower than the
  // page, foreground barely moves, which is what actually reads as "depth"
  // beyond a static layered image. Skipped entirely for reduced-motion users.
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const nav = document.getElementById('siteNav');
  const parallaxEls = document.querySelectorAll('[data-parallax]');
  function onScroll() {
    nav.classList.toggle('scrolled', window.scrollY > 12);
    if (!prefersReducedMotion) {
      parallaxEls.forEach(el => {
        const speed = parseFloat(el.dataset.parallax);
        el.style.transform = `translateY(${window.scrollY * speed}px)`;
      });
    }
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  // Hero owl: real Lottie greeting animation. Frames 0-176 (of a 600-frame
  // asset) are the one verified clean loop - four independent layers (body
  // position, body rotation, the speech bubble's scale, and both eyes) all
  // land on the exact same "at rest" values at both frame 0 and frame ~176,
  // so looping this exact range never pops or jumps. Played at 0.5x speed
  // (a real ~5.87s loop) since native speed reads too fast for a decorative,
  // always-visible hero element. For reduced-motion, it renders once as a
  // still frame instead of looping.
  if (window.lottie) {
    const heroOwlAnim = lottie.loadAnimation({
      container: document.getElementById('heroOwlLottie'),
      renderer: 'svg',
      loop: !prefersReducedMotion,
      autoplay: !prefersReducedMotion,
      path: '{{ asset("animations/tarabasa-owl-hello.json") }}',
      initialSegment: [0, 176]
    });
    if (!prefersReducedMotion) {
      heroOwlAnim.setSpeed(0.5);
    } else {
      heroOwlAnim.goToAndStop(90, true);
    }
  }
</script>
</body>
</html>
