<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TaraBasa AI — Choose your role</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.15.0/gsap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.15.0/ScrollTrigger.min.js"></script>
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
  .mascot-stage{ position:relative; width:min(320px, 78vw); }
  .mascot-contact-shadow{
    position:absolute; left:50%; bottom:2%; transform:translateX(-50%); width:58%; height:22px; border-radius:50%;
    background:rgba(6,16,30,0.32); filter:blur(6px); z-index:0;
  }
  .owl-mascot{ position:relative; z-index:1; width:100%; height:auto; display:block; animation:bob 4s ease-in-out infinite; }
  @keyframes bob{ 0%,100%{ transform:translateY(0); } 50%{ transform:translateY(-7px); } }

  @media (max-width:760px){
    .hero-grid{ grid-template-columns:1fr; gap:14px; }
    .hero-copy{ text-align:center; }
    .hero-sub{ margin:0 auto; }
    .hero-mascot{ order:2; margin-top:6px; }
  }

  /* ---------- Reading scene (replaces the old "choose how you'll sign in"
     role-select section entirely) ---------- */
  .reading-scene{ margin-top:40px; display:grid; grid-template-columns:1fr 1fr; gap:40px; align-items:center; }
  @media (max-width:760px){ .reading-scene{ grid-template-columns:1fr; gap:24px; text-align:center; } }

  .ground-scene{ position:relative; width:340px; height:300px; margin:0 auto; overflow:hidden; }
  @media (max-width:400px){ .ground-scene{ width:280px; height:250px; transform:scale(.86); transform-origin:top center; } }
  .shared-shadow{
    position:absolute; left:50%; bottom:8px; transform:translateX(-50%);
    width:300px; height:30px; border-radius:50%; background:rgba(19,31,43,0.16); filter:blur(4px);
  }
  .ground-child{ position:absolute; left:0; bottom:16px; width:185px; height:auto; z-index:2; }
  .ground-stack{ position:absolute; left:178px; bottom:16px; width:112px; height:auto; z-index:1; }
  .owl-mascot-lottie{ position:absolute; left:196px; bottom:80px; width:120px; height:120px; z-index:3; }
  .ground-book{ position:absolute; left:64px; bottom:96px; width:110px; height:auto; z-index:4; transform-origin:22% 30%; }
  .cel{ stroke:var(--navy-900); stroke-width:3; stroke-linejoin:round; stroke-linecap:round; }

  /* Typography spec for this section, exact values as approved - kept
     separate from the rest of the page's own type scale on purpose. */
  .section-headline{
    font-family:'Baloo 2', sans-serif; font-size:28px; font-weight:700;
    color:#f0982c; margin:0 0 12px;
  }
  .section-body{
    font-family:'Inter', sans-serif; font-size:15px; font-weight:500; line-height:1.6;
    color:#56697a; margin:0 auto; max-width:420px;
  }
  @media (min-width:761px){ .section-body{ margin:0; } }
  /* clip-path never changes box dimensions, so this reveal cannot cause
     layout shift - the text's real space is reserved from first paint,
     only its visibility sweeps in. */
  .text-reveal{ clip-path: inset(0 100% 0 0); }
  @media (prefers-reduced-motion: reduce){
    .text-reveal{ clip-path:none; }
  }

  a:focus-visible, button:focus-visible{ outline:2px solid var(--blue-500); outline-offset:2px; }

  @media (prefers-reduced-motion: reduce){
    .owl-mascot{ animation:none; }
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
            <div class="mascot-contact-shadow"></div>
            <svg class="owl-mascot" viewBox="0 0 300 320" fill="none" role="img" aria-label="Basa the owl, holding an open book">
              <rect x="112" y="278" width="26" height="22" rx="9" fill="var(--owl-orange-600)"/>
              <rect x="162" y="278" width="26" height="22" rx="9" fill="var(--owl-orange-600)"/>

              <path d="M45 205c-6 20-2 45 20 55l14-38c-14-2-27-9-34-17z" fill="var(--owl-orange-600)"/>
              <path d="M255 205c6 20 2 45-20 55l-14-38c14-2 27-9 34-17z" fill="var(--owl-orange-600)"/>

              <ellipse cx="150" cy="168" rx="112" ry="116" fill="var(--owl-orange-500)"/>

              <path d="M78 92c-10-24-6-48 8-62 4 20 16 34 30 40-16 4-30 12-38 22z" fill="var(--owl-orange-600)"/>
              <path d="M222 92c10-24 6-48-8-62-4 20-16 34-30 40 16 4 30 12 38 22z" fill="var(--owl-orange-600)"/>

              <ellipse cx="110" cy="168" rx="46" ry="50" fill="rgba(255,255,255,0.92)"/>
              <ellipse cx="190" cy="168" rx="46" ry="50" fill="rgba(255,255,255,0.92)"/>

              <circle cx="108" cy="192" r="13" fill="var(--owl-orange-400)" opacity="0.55"/>
              <circle cx="192" cy="192" r="13" fill="var(--owl-orange-400)" opacity="0.55"/>

              <circle cx="110" cy="168" r="33" fill="#ffffff"/>
              <circle cx="190" cy="168" r="33" fill="#ffffff"/>
              <circle cx="110" cy="168" r="15" fill="var(--navy-900)"/>
              <circle cx="190" cy="168" r="15" fill="var(--navy-900)"/>
              <circle cx="104" cy="161" r="4.5" fill="#ffffff"/>
              <circle cx="184" cy="161" r="4.5" fill="#ffffff"/>

              <path d="M150 190l12 14h-24z" fill="var(--owl-orange-600)"/>

              <path d="M96 232c14 10 34 15 54 15s40-5 54-15c-4 26-28 46-54 46s-50-20-54-46z" fill="var(--owl-orange-500)"/>

              <g transform="translate(114,222)">
                <path d="M0 8c0 0 14-6 24 3 2.4 2.2 4 6 4 10.4V44c0 0-8-6-28-4V8z" fill="var(--blue-500)"/>
                <path d="M72 8c0 0-14-6-24 3-2.4 2.2-4 6-4 10.4V44c0 0 8-6 28-4V8z" fill="var(--parent-teal)"/>
                <rect x="34" y="10" width="4" height="34" fill="#ffffff" opacity="0.8"/>
              </g>
            </svg>
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

  <div class="wrap">
    <section class="reading-scene" id="readingScene">
      <div class="ground-scene">
        <!-- one shared ground shadow for the whole group, not one per character -->
        <div class="shared-shadow"></div>

        <!-- child: real unDraw asset ("Relaxation"), recolored fills only, linework untouched -->
        <img class="ground-child" src="{{ asset('images/landing-reading-child.svg') }}" alt="A child sitting on the ground, reading">

        <!-- book stack: 3 boxes, alternating palette, hard-shaded side face each -->
        <svg class="ground-stack" viewBox="0 0 130 80">
          <polygon class="cel" points="0,58 110,58 110,80 0,80" fill="var(--blue-600)"/>
          <polygon class="cel" points="110,58 110,80 122,70 122,48" fill="var(--blue-700)"/>
          <polygon class="cel" points="8,38 96,38 96,58 8,58" fill="var(--parent-teal)"/>
          <polygon class="cel" points="96,38 96,58 108,48 108,28" fill="#146b56"/>
          <polygon class="cel" points="16,20 82,20 82,38 16,38" fill="var(--clay-yellow)"/>
          <polygon class="cel" points="82,20 82,38 94,28 94,8" fill="#c9973e"/>
        </svg>

        <!-- entering character: the real Lottie animation (approved, LottieFiles free tier) -->
        <div id="owlMascotLottie" class="owl-mascot-lottie"></div>

        <!-- the held book: simple hand-coded geometry, cel-shaded spine -->
        <svg id="heldBook" class="ground-book" viewBox="0 0 90 50">
          <polygon points="45,6 6,16 6,44 45,36" fill="#fff" stroke="var(--navy-900)" stroke-width="2.6" stroke-linejoin="round"/>
          <polygon points="45,6 84,16 84,44 45,36" fill="#f4f8fc" stroke="var(--navy-900)" stroke-width="2.6" stroke-linejoin="round"/>
          <polygon points="42,10 48,10 48,34 42,32" fill="var(--blue-700)"/>
          <path d="M14 22h20M14 28h16" stroke="var(--sky-100)" stroke-width="3" stroke-linecap="round"/>
          <path d="M52 22h20M52 28h16" stroke="var(--sky-100)" stroke-width="3" stroke-linecap="round"/>
        </svg>
      </div>

      <div id="textReveal" class="text-reveal">
        <h2 class="section-headline">Real reading, actually fun</h2>
        <p class="section-body">Tara the owl listens while a child reads a real story out loud, checking every word as they go, right there in the moment.</p>
      </div>
    </section>
  </div>

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

  // Reading-scene entrance: Tara's real Lottie animation (LottieFiles free
  // tier, approved as-is) enters from the right; the headline/body are
  // revealed via an authored clip-path sweep timed to the real, verified
  // moment the sword's swing arrives - not tracked pixel-by-pixel, timed.
  // See CLAUDE.md for how frame 19 was derived from this file's own keyframes.
  if (!prefersReducedMotion && window.gsap && window.lottie) {
    gsap.registerPlugin(ScrollTrigger);

    const owlAnim = lottie.loadAnimation({
      container: document.getElementById('owlMascotLottie'),
      renderer: 'svg',
      loop: false,
      autoplay: false,
      path: '{{ asset("animations/tarabasa-owl.json") }}'
    });

    // Sword_Blade's own rotation peaks at 14.86deg exactly at frame 19
    // (settling to 10.88deg at frame 20 - the real swing-then-recoil beat),
    // and Body's rotation independently peaks at 15deg at that same frame 19.
    // Two unrelated layers agreeing on frame 19 is what makes this computed
    // from the file's real data, not guessed. At the file's real 24fps,
    // frame 19 = 19/24s.
    const SWORD_EXTENSION_SECONDS = 19 / 24; // 0.7917s

    const readingTl = gsap.timeline({
      scrollTrigger: { trigger: '.reading-scene', start: 'top 80%' }
    });

    // Three real bugs found in testing, not assumed correct from the code:
    // 1) ScrollTrigger measures trigger positions at creation time, before
    //    the child illustration (an <img> with no explicit height) finishes
    //    loading - its eventual size shifts the document's height, so the
    //    trigger's start/end were computed against a too-short page.
    //    Refreshing once everything has actually loaded fixes that.
    // 2) On short pages the computed trigger "start" can land above 0 (i.e.
    //    already behind the scroll position at load) - confirmed directly:
    //    forcing the timeline's own progress to 1 revealed the text and
    //    moved the owl correctly, proving the timeline itself works, but
    //    ScrollTrigger's onEnter never fires because there's no discrete
    //    "crossing" scroll event to trigger it from a state that was already
    //    active. Explicitly playing the timeline once if it's already inside
    //    its active range covers this case.
    // 3) On a fast local/cached load, `window`'s 'load' event can fire
    //    before this script even attaches a listener for it - confirmed via
    //    document.readyState already reading "complete" at this point in a
    //    real test, meaning the listener below would simply never run.
    //    Running the same fix immediately when that's already true, instead
    //    of only waiting for an event that may never come, is what actually
    //    fixes it.
    function activateReadingScene() {
      ScrollTrigger.refresh();
      if (readingTl.scrollTrigger.isActive) {
        readingTl.play(0);
      }
    }
    if (document.readyState === 'complete') {
      activateReadingScene();
    } else {
      window.addEventListener('load', activateReadingScene);
    }

    readingTl.set('#owlMascotLottie', { opacity: 0, x: 160 })
      .set('#textReveal', { clipPath: 'inset(0 100% 0 0)' })
      .to('#owlMascotLottie', {
        opacity: 1, x: 0, duration: 0.6, ease: 'power2.out',
        onStart: () => owlAnim.play()
      }, 0)
      .to('#textReveal', {
        clipPath: 'inset(0 0% 0 0)',
        duration: SWORD_EXTENSION_SECONDS,
        ease: 'power2.out'
      }, 0);

    // Independent idle bob for the child + a held-book flip loop - only
    // start once the one-shot entrance has fully finished, so nothing
    // fights the reveal while it's happening.
    readingTl.eventCallback('onComplete', () => {
      gsap.to('.ground-child', { y: -6, duration: 1.3, repeat: -1, yoyo: true, ease: 'sine.inOut' });
      gsap.to('#heldBook', {
        rotationZ: -4, transformOrigin: '20% 60%',
        duration: 1.1, delay: -0.4, repeat: -1, yoyo: true, ease: 'sine.inOut'
      });
    });
  }
</script>
</body>
</html>
