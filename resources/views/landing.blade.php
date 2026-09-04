<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TaraBasa AI — Choose your role</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
  @media (max-width:400px){ .nav-btn{ padding:9px 12px; font-size:12.5px; } .nav-btn span.btn-label{ display:none; } }

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

  /* ---------- Role select ---------- */
  .role-select{ margin-top:26px; }
  .role-select-eyebrow{
    text-align:center; font-size:11.5px; font-weight:700; letter-spacing:.08em; text-transform:uppercase;
    color:var(--slate-400); margin:0 0 14px;
  }
  .stage{ display:grid; grid-template-columns:minmax(260px,0.86fr) 1fr; gap:22px; align-items:stretch; }
  @media (max-width:760px){ .stage{ grid-template-columns:1fr; } }
  .learner-tile{
    position:relative; border-radius:28px; padding:30px 26px 28px;
    display:flex; flex-direction:column; justify-content:flex-end; min-height:340px;
    background:linear-gradient(160deg, var(--clay-yellow) 0%, var(--owl-orange-500) 62%, var(--owl-orange-600) 100%);
    box-shadow:0 26px 40px -18px rgba(221,112,20,0.55), inset 0 2px 0 rgba(255,255,255,0.35);
    cursor:pointer; overflow:hidden; border:none; text-align:left;
    transition:transform .2s ease, box-shadow .2s ease;
    font-family:inherit; text-decoration:none; color:inherit;
  }
  .learner-tile:hover{ transform:translateY(-4px); box-shadow:0 30px 44px -18px rgba(221,112,20,0.6), inset 0 2px 0 rgba(255,255,255,0.35); }
  .clay-blob{ position:absolute; border-radius:50%; background:rgba(255,255,255,0.16); }
  .clay-blob.b1{ width:170px;height:170px; top:-60px; right:-50px; }
  .clay-blob.b2{ width:110px;height:110px; bottom:20px; right:-30px; background:rgba(255,255,255,0.1); }
  .clay-icon{
    width:64px;height:64px;border-radius:20px; background:rgba(255,255,255,0.92);
    display:flex;align-items:center;justify-content:center;
    box-shadow:0 10px 18px -8px rgba(90,45,0,0.45); margin-bottom:16px; position:relative; z-index:1;
  }
  .learner-tile h3{ font-family:'Baloo 2',sans-serif; font-size:26px; margin:0 0 6px; color:#4a2600; position:relative; z-index:1; }
  .learner-tile p{ margin:0 0 20px; font-size:14.5px; font-weight:600; color:#6b3900; opacity:.85; position:relative; z-index:1; max-width:220px; }
  .clay-cta{ display:inline-flex; align-items:center; gap:8px; background:#4a2600; color:#fff8ec; font-weight:700; font-size:14px; padding:11px 20px; border-radius:999px; width:fit-content; position:relative; z-index:1; }
  .role-list{ background:var(--surface); border:1px solid var(--line); border-radius:var(--radius-lg); box-shadow:var(--shadow-sm); overflow:hidden; display:flex; flex-direction:column; }
  .role-list-head{ padding:18px 22px 4px; }
  .role-list-head .eyebrow{ font-size:11.5px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--slate-400); }
  .role-row{
    display:flex; align-items:center; gap:14px; padding:16px 22px; border-top:1px solid var(--line);
    background:transparent; width:100%; text-align:left; cursor:pointer; font-family:inherit;
    transition:background-color .18s ease, padding-left .18s ease; text-decoration:none; color:inherit;
  }
  .role-row:hover{ background:var(--sky-50); padding-left:26px; }
  .role-row:last-child{ border-radius:0 0 var(--radius-lg) var(--radius-lg); }
  .role-row:focus-visible{ outline:none; box-shadow:inset 0 0 0 2px var(--blue-500); }
  .role-icon{ width:42px;height:42px;border-radius:12px; display:flex;align-items:center;justify-content:center;flex-shrink:0; color:#fff; }
  .role-icon.admin{ background:linear-gradient(150deg,#4a5b6b,var(--navy-900)); }
  .role-icon.teacher{ background:linear-gradient(150deg,var(--blue-500),var(--blue-700)); }
  .role-icon.parent{ background:linear-gradient(150deg,#28b895,var(--parent-teal)); }
  .role-copy{ flex:1; min-width:0; }
  .role-copy h4{ margin:0 0 2px; font-size:15px; font-weight:700; color:var(--navy-900); }
  .role-copy p{ margin:0; font-size:13px; color:var(--slate-600); font-weight:500; }
  .role-arrow{ color:var(--slate-400); flex-shrink:0; transition:transform .18s ease, color .18s ease; }
  .role-row:hover .role-arrow{ transform:translateX(3px); color:var(--blue-600); }
  .footnote{ text-align:center; margin-top:26px; font-size:12.5px; color:var(--slate-400); font-weight:500; }
  .footnote a{ color:var(--blue-600); text-decoration:none; font-weight:700; transition:color .15s ease; }
  .footnote a:hover{ text-decoration:underline; }

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
    <section class="role-select" id="signin">
      <p class="role-select-eyebrow">Ready to start? Choose how you'll sign in</p>

      <div class="stage">
        <a class="learner-tile" href="{{ route('learner.login') }}">
          <div class="clay-blob b1"></div>
          <div class="clay-blob b2"></div>
          <div class="clay-icon">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none">
              <rect x="3" y="6" width="18" height="13" rx="4" fill="#ef8d2a"/>
              <circle cx="8.5" cy="12.5" r="1.6" fill="white"/>
              <circle cx="15.5" cy="12.5" r="1.6" fill="white"/>
              <rect x="9.5" y="3" width="2" height="4" rx="1" fill="#ef8d2a"/>
              <rect x="12.5" y="3" width="2" height="4" rx="1" fill="#ef8d2a"/>
            </svg>
          </div>
          <h3>I'm a Learner</h3>
          <p>Read, play &amp; earn badges!</p>
          <span class="clay-cta">
            Enter my PIN
          </span>
        </a>

        <div class="role-list">
          <div class="role-list-head"><span class="eyebrow">Sign in as</span></div>

          <a class="role-row" href="{{ route('login', ['role' => 'teacher']) }}">
            <span class="role-icon teacher">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M12 3L2 8l10 5 8-4.2V15h1V8L12 3z" fill="currentColor"/><path d="M6 11.5V16c0 1.7 2.7 3 6 3s6-1.3 6-3v-4.5l-6 3.2-6-3.2z" fill="currentColor" opacity="0.85"/></svg>
            </span>
            <span class="role-copy"><h4>Teacher</h4><p>Create activities &amp; track class progress</p></span>
            <svg class="role-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </a>

          <a class="role-row" href="{{ route('login', ['role' => 'parent']) }}">
            <span class="role-icon parent">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><circle cx="9" cy="8" r="3" fill="currentColor"/><circle cx="17" cy="9" r="2.4" fill="currentColor" opacity="0.85"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6" fill="currentColor"/><path d="M14 20c.3-2.4 1.8-4.3 3.8-5.1 2 .9 3.2 2.8 3.2 5.1" fill="currentColor" opacity="0.85"/></svg>
            </span>
            <span class="role-copy"><h4>Parent</h4><p>Monitor your child's reading progress</p></span>
            <svg class="role-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </a>

          <a class="role-row" href="{{ route('login', ['role' => 'admin']) }}">
            <span class="role-icon admin">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M12 2l7 3v6c0 5-3 8.5-7 11-4-2.5-7-6-7-11V5l7-3z" fill="currentColor"/></svg>
            </span>
            <span class="role-copy"><h4>Admin</h4><p>Manage teacher accounts &amp; approvals</p></span>
            <svg class="role-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </a>
        </div>
      </div>
    </section>

    <p class="footnote">New teacher at your school? <a href="{{ route('register.teacher') }}">Request an account</a></p>
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
</script>
</body>
</html>
