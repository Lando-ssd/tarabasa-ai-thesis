<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Dashboard — TaraBasa AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --sky-100:#dcedff; --blue-500:#1c7ed6; --blue-600:#1567bf; --blue-700:#0a3d73;
    --navy-900:#131f2b; --slate-600:#5b6b7a; --slate-400:#8b98a6;
    --owl-orange-500:#ef8d2a; --owl-orange-600:#dd7014; --clay-yellow:#ffcf6e;
    --teal:#2bb89c; --teal-700:#0f6e5c; --purple:#8b5fd6;
    --line:#e3ebf2; --surface:#ffffff; --bg-0:#f6faff;
    --success:#1f9e83; --success-bg:#e9f7f3; --shadow-sm:0 6px 16px -10px rgba(19,60,110,0.18);
    --owl-body:#e0862f; --owl-face:#f7d9a8; --owl-beak:#f5a623; --owl-cheek:#ffb3ab; --owl-ink:#2b1810;
  }
  *{ box-sizing:border-box; }
  html,body{ margin:0; padding:0; }
  body{
    min-height:100vh; font-family:'Inter',sans-serif; color:var(--navy-900);
    background: radial-gradient(1200px 700px at 90% -10%, var(--sky-100), transparent 55%),
                radial-gradient(900px 600px at 0% 100%, #ffe9c9, transparent 50%), var(--bg-0);
    background-attachment:fixed;
  }
  .page{ max-width:1180px; margin:0 auto; padding:28px 24px 60px; }
  @media (prefers-reduced-motion:reduce){
    *, *::before, *::after{ animation-duration:0.001ms !important; animation-iteration-count:1 !important; }
  }

  /* ---- Profile header ---- */
  .profile-header{ display:flex; align-items:center; gap:18px; margin-bottom:22px; flex-wrap:wrap; }
  .avatar{
    width:76px; height:76px; border-radius:24px; flex-shrink:0; overflow:hidden;
    background:linear-gradient(155deg, var(--owl-orange-500), var(--owl-orange-600));
    display:flex; align-items:center; justify-content:center; color:#fff;
    font-family:'Baloo 2',sans-serif; font-size:28px; font-weight:800; text-align:center;
    box-shadow:0 14px 26px -14px rgba(221,112,20,0.55);
  }
  .avatar img{ width:100%; height:100%; object-fit:cover; }
  .profile-text{ flex:1; min-width:200px; }
  .profile-text h1{ font-family:'Baloo 2',sans-serif; font-size:32px; margin:0 0 4px; line-height:1.15; }
  .profile-text .grade-line{ font-size:17px; font-weight:700; color:var(--slate-600); margin:0; }
  .switch-form button{
    display:inline-flex; align-items:center; gap:7px; font-size:14px; font-weight:800; color:var(--slate-600);
    background:var(--surface); border:1.5px solid var(--line); border-radius:999px; padding:10px 18px 10px 14px;
    cursor:pointer; box-shadow:var(--shadow-sm); transition:border-color .15s ease, color .15s ease, transform .15s ease;
  }
  .switch-form button:hover{ color:var(--blue-600); border-color:var(--blue-500); transform:translateY(-1px); }

  /* ---- Stats ---- */
  .stat-row{ display:flex; gap:16px; margin-bottom:22px; flex-wrap:wrap; }
  .stat-card{
    flex:1; min-width:140px; background:var(--surface); border:1px solid var(--line); border-radius:20px;
    padding:20px 14px; text-align:center; box-shadow:var(--shadow-sm);
  }
  .stat-card .v{ font-family:'Baloo 2',sans-serif; font-size:30px; font-weight:800; }
  .stat-card .v.level{ color:var(--teal-700); font-size:22px; }
  .stat-card .l{ font-size:14px; font-weight:700; color:var(--slate-600); text-transform:uppercase; letter-spacing:.03em; margin-top:6px; }

  /* ---- Hero: real two-column layout on wide screens (content left,
     a bigger animated owl on the right with a looping halo/pulse
     accent), collapsing to one column on narrow screens. ---- */
  .hero-tile{
    position:relative; overflow:hidden; margin-bottom:16px;
    background:linear-gradient(150deg, #eaf6ff 0%, #d9f0ea 100%);
    border:1.5px solid var(--sky-100); border-radius:30px; padding:36px 34px;
    display:grid; grid-template-columns:1fr auto; align-items:center; gap:28px;
    box-shadow:0 20px 40px -24px rgba(19,60,110,0.25);
  }
  @media (max-width:760px){ .hero-tile{ grid-template-columns:1fr; padding:30px 26px; } }
  .hero-blob{ position:absolute; border-radius:50%; pointer-events:none; }
  .hero-blob.b1{ width:260px;height:260px; top:-110px; right:-90px; background:radial-gradient(circle, rgba(255,207,110,0.4), transparent 70%); }
  .hero-blob.b2{ width:200px;height:200px; bottom:-70px; left:-70px; background:radial-gradient(circle, rgba(43,184,156,0.25), transparent 70%); }

  .hero-content{ position:relative; z-index:1; }
  .hero-tag{
    display:inline-flex; align-items:center; gap:6px;
    font-size:13px; font-weight:800; letter-spacing:.05em; text-transform:uppercase; color:var(--teal-700);
    background:rgba(255,255,255,0.75); padding:6px 14px; border-radius:999px; margin-bottom:14px;
  }
  .hero-tile h2{ font-family:'Baloo 2',sans-serif; font-size:32px; font-weight:800; margin:0 0 12px; line-height:1.2; letter-spacing:-.01em; }
  .hero-tile p{ font-size:18px; color:var(--slate-600); font-weight:600; line-height:1.55; margin:0 0 24px; max-width:460px; }
  .hero-cta{
    display:inline-block; padding:18px 34px; border:none; border-radius:18px;
    font:800 18px/1 'Baloo 2',sans-serif; cursor:pointer; text-decoration:none;
    background:linear-gradient(155deg, var(--blue-500), var(--blue-700)); color:#fff;
    box-shadow:0 16px 26px -12px rgba(15,95,174,0.5); transition:transform .2s cubic-bezier(.34,1.56,.64,1);
  }
  .hero-cta:hover{ transform:translateY(-2px) scale(1.02); }

  /* Hero owl visual: a real illustrated character (matching the
     TaraBasa logo, same technique as the Learner Login redesign),
     bigger and richer than the old plain circular mascot, with a
     looping animated halo behind it and real flapping wings — the
     "improve the logo / add a loop, like some homepages do" ask. */
  .hero-visual{ position:relative; z-index:1; width:180px; height:180px; justify-self:center; }
  .hero-halo{
    position:absolute; inset:-20px; border-radius:50%;
    border:3px dashed rgba(28,126,214,0.32);
    animation:heroSpin 16s linear infinite;
  }
  .hero-pulse{
    position:absolute; inset:-34px; border-radius:50%;
    background:radial-gradient(circle, rgba(255,207,110,0.4), transparent 70%);
    animation:heroPulse 3.6s ease-in-out infinite;
  }
  @keyframes heroSpin{ from{ transform:rotate(0deg); } to{ transform:rotate(360deg); } }
  @keyframes heroPulse{ 0%,100%{ transform:scale(0.92); opacity:.7; } 50%{ transform:scale(1.07); opacity:1; } }
  .hero-owl{ position:relative; width:100%; height:100%; animation:heroBob 3s ease-in-out infinite; }
  @keyframes heroBob{ 0%,100%{ transform:translateY(0); } 50%{ transform:translateY(-6px); } }
  .hero-owl svg{ width:100%; height:100%; display:block; overflow:visible; }
  .wing{ transform-box:fill-box; }
  .wing-l{ transform-origin:100% 25%; animation:flapL 1.6s ease-in-out infinite; }
  .wing-r{ transform-origin:0% 25%; animation:flapR 1.6s ease-in-out infinite; }
  @keyframes flapL{ 0%,100%{ transform:rotate(0deg); } 50%{ transform:rotate(-16deg); } }
  @keyframes flapR{ 0%,100%{ transform:rotate(0deg); } 50%{ transform:rotate(16deg); } }
  @media (max-width:760px){ .hero-visual{ width:140px; height:140px; } }

  /* ---- Practice Games: a real "nav card" layout — icon badge, title
     block, a decorative pattern, and a trailing chevron affordance —
     instead of a stretched bar with everything crammed to the left. ---- */
  .games-btn{
    position:relative; overflow:hidden; display:flex; align-items:center; gap:18px;
    margin-bottom:26px; padding:22px 26px; border-radius:24px;
    background:linear-gradient(155deg, var(--clay-yellow), var(--owl-orange-600)); color:#fff; text-decoration:none;
    box-shadow:0 16px 28px -14px rgba(221,112,20,0.5); transition:transform .2s cubic-bezier(.34,1.56,.64,1);
  }
  .games-btn:hover{ transform:translateY(-2px) scale(1.01); }
  .games-btn .icon{
    width:58px; height:58px; border-radius:18px; background:rgba(255,255,255,0.3);
    display:flex; align-items:center; justify-content:center; flex-shrink:0; position:relative; z-index:1;
  }
  .games-btn .text{ position:relative; z-index:1; }
  .games-btn h3{ font-family:'Baloo 2',sans-serif; font-size:22px; margin:0 0 2px; }
  .games-btn p{ font-size:15px; font-weight:600; opacity:.95; margin:0; }
  .games-btn .chevron{ margin-left:auto; position:relative; z-index:1; opacity:.9; flex-shrink:0; }
  .games-btn .deco{ position:absolute; inset:0; opacity:.15; pointer-events:none; }

  /* ---- Panel sections ---- */
  .panel{ margin-bottom:26px; }
  .panel-title{ font-family:'Baloo 2',sans-serif; font-size:24px; margin:0 0 14px; display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
  .panel-title .see-count{ font-size:16px; font-weight:700; color:var(--slate-600); }
  .panel-card{ background:var(--surface); border:1px solid var(--line); border-radius:26px; padding:26px 24px; box-shadow:var(--shadow-sm); }

  .pair-row{ display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:26px; align-items:stretch; }
  @media (max-width:820px){ .pair-row{ grid-template-columns:1fr; } }
  /* Stretch both cards to the same height and center their content
     vertically, so "This Week's Goal" and "My Growth" line up cleanly
     regardless of which one has more/less content. */
  .pair-row .panel{ display:flex; flex-direction:column; }
  .pair-row .panel-card{ flex:1; display:flex; flex-direction:column; justify-content:center; }

  /* ---- Journey ---- */
  .journey-legend{
    display:grid; grid-template-columns:repeat(5,1fr); gap:6px; margin-bottom:20px; padding-bottom:16px;
    border-bottom:1px dashed var(--line); text-align:center;
  }
  .journey-legend span{ font-size:11.5px; font-weight:700; color:var(--slate-400); line-height:1.3; }
  .journey-legend span:first-child{ text-align:left; }
  .journey-legend span:last-child{ text-align:right; }
  .journey-row{ padding:18px 0; border-bottom:1px solid var(--line); }
  .journey-row:last-child{ border-bottom:none; padding-bottom:0; }
  .journey-row:first-child{ padding-top:0; }
  .journey-label{ display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:10px; }
  .journey-label strong{ font-family:'Baloo 2',sans-serif; font-size:19px; }
  .journey-word{ font-size:15px; font-weight:700; color:var(--teal-700); }
  .up-next-badge{
    font-size:13px; font-weight:800; color:var(--owl-orange-600); background:#fff3e0;
    padding:4px 12px; border-radius:999px;
  }
  .journey-track{ width:100%; max-width:640px; height:auto; display:block; overflow:visible; }
  .track-grey{ fill:none; stroke:var(--line); stroke-width:5; }
  .track-colored{ fill:none; stroke:var(--teal); stroke-width:5; }
  .track-locked{ fill:none; stroke:var(--line); stroke-width:4; }
  .tick-dot{ fill:var(--line); }
  .checkpoint-done{ fill:var(--teal); }
  .checkpoint-todo{ fill:var(--surface); stroke:var(--line); stroke-width:3; }
  .checkpoint-locked{ fill:var(--bg-0); stroke:var(--line); stroke-width:3; stroke-dasharray:3 3; }
  .checkmark{ font-size:11px; fill:#fff; text-anchor:middle; font-weight:800; }
  .journey-here{ font-size:13.5px; font-weight:700; color:var(--owl-orange-600); margin:10px 0 0; }
  .journey-empty{ font-size:17px; font-weight:600; color:var(--slate-600); text-align:center; padding:20px 0; }
  @media (max-width:480px){ .journey-legend span{ font-size:9.5px; } }

  /* Flying owl marker — real flapping wings, not a static glyph */
  .fly-owl{ animation:markerBob 1.4s ease-in-out infinite; transform-box:fill-box; transform-origin:50% 50%; }
  @keyframes markerBob{ 0%,100%{ transform:translateY(0); } 50%{ transform:translateY(-2px); } }
  .fly-wing-l{ transform-box:fill-box; transform-origin:100% 40%; animation:flapMarkerL 0.7s ease-in-out infinite; }
  .fly-wing-r{ transform-box:fill-box; transform-origin:0% 40%; animation:flapMarkerR 0.7s ease-in-out infinite; }
  @keyframes flapMarkerL{ 0%,100%{ transform:rotate(0deg); } 50%{ transform:rotate(-30deg); } }
  @keyframes flapMarkerR{ 0%,100%{ transform:rotate(0deg); } 50%{ transform:rotate(30deg); } }

  /* ---- Goal: a real circular progress ring, not just a flat bar ---- */
  .goal-panel .panel-card{ align-items:center; }
  .goal-panel.met .panel-card{ background:linear-gradient(150deg,#fff3c4,#f0b03e); border-color:transparent; }
  .goal-ring-wrap{ position:relative; width:158px; height:158px; margin-bottom:16px; filter:drop-shadow(0 10px 18px -10px rgba(15,60,110,0.35)); }
  .goal-panel.met .goal-ring-wrap{ filter:drop-shadow(0 10px 18px -10px rgba(122,84,0,0.4)); }
  .goal-ring-wrap svg{ width:100%; height:100%; transform:rotate(-90deg); display:block; }
  .goal-ring-bg{ fill:none; stroke:var(--bg-0); stroke-width:13; }
  .goal-panel.met .goal-ring-bg{ stroke:rgba(255,255,255,0.55); }
  .goal-ring-fill{ fill:none; stroke:url(#goalGradient); stroke-width:13; stroke-linecap:round; transition:stroke-dashoffset .6s ease; }
  /* A crisp white "badge" sits inside the ring's empty middle so the
     ring itself reads with real contrast against the card behind it,
     instead of the two blending together (worst on the gold "met"
     card, where a plain orange ring on an orange card looked flat). */
  .goal-ring-center{
    position:absolute; left:50%; top:50%; transform:translate(-50%,-50%);
    width:116px; height:116px; border-radius:50%; background:var(--surface);
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    box-shadow:0 8px 18px -10px rgba(15,60,110,0.3), inset 0 0 0 1px rgba(15,60,110,0.05);
  }
  .goal-panel.met .goal-ring-center{ box-shadow:0 8px 18px -10px rgba(122,84,0,0.35), inset 0 0 0 1px rgba(122,84,0,0.08); }
  .goal-ring-icon{ font-size:32px; line-height:1; }
  .goal-ring-count{ font-family:'Baloo 2',sans-serif; font-size:28px; font-weight:800; margin-top:3px; color:var(--navy-900); line-height:1; }
  .goal-ring-count span{ font-size:16px; font-weight:700; color:var(--slate-400); margin-left:1px; }
  .goal-panel.met .goal-ring-count{ color:#8a5a00; }
  .goal-panel.met .goal-ring-count span{ color:#c78d2e; }
  .goal-sub{ font-size:15px; font-weight:600; color:var(--slate-600); margin:0 0 4px; text-align:center; }
  .goal-panel.met .goal-sub{ color:#7a5400; }
  .goal-note{ font-size:16.5px; font-weight:700; margin-top:8px; text-align:center; }
  .goal-panel:not(.met) .goal-note{ color:var(--teal-700); }
  .goal-panel.met .goal-note{ color:#5a3d00; }

  /* ---- Growth: rounded "status bar" style bars in a visible track,
     shared baseline so every day lines up cleanly. ---- */
  .growth-panel .panel-card{ align-items:stretch; }
  .growth-total{
    display:flex; align-items:center; justify-content:center; gap:8px;
    font-size:16px; font-weight:700; color:var(--slate-600); margin:0 0 22px; text-align:center;
  }
  .growth-total-icon{ font-size:19px; line-height:1; }
  .growth-total strong{ color:var(--teal-700); font-family:'Baloo 2',sans-serif; font-size:20px; }
  .growth-chart{
    flex:1; display:flex; align-items:flex-end; gap:10px; min-height:140px;
    padding-bottom:12px; border-bottom:2px solid var(--line);
  }
  .growth-day{ flex:1; display:flex; flex-direction:column; align-items:center; height:100%; }
  .growth-count{ font-size:13.5px; font-weight:800; color:var(--slate-600); margin:0 0 6px; min-height:17px; }
  .growth-day.today .growth-count{ color:var(--owl-orange-600); }
  .growth-bar-track{
    flex:1; width:100%; max-width:26px; display:flex; align-items:flex-end;
    background:var(--bg-0); border-radius:999px; overflow:hidden; box-shadow:inset 0 0 0 1px var(--line);
  }
  .growth-day.today .growth-bar-track{ box-shadow:inset 0 0 0 2px var(--owl-orange-500); }
  .growth-bar{
    width:100%; border-radius:999px; position:relative;
    background:linear-gradient(180deg, var(--sky-100), var(--blue-500));
    /* A real "liquid pouring in" effect: height starts at 0 (set by JS) and
       transitions up to its real value once this chart scrolls into view —
       staggered per day (transition-delay, set inline) so it reads as one
       wave washing across the week, not 7 bars popping at once. */
    transition:height 1.05s cubic-bezier(.22,.85,.28,1.15);
  }
  .growth-day.today .growth-bar{ background:linear-gradient(180deg, var(--clay-yellow), var(--owl-orange-600)); }
  /* A soft glossy cap at the fill's top edge, like light catching a
     liquid's surface, so it reads as "filling" rather than a plain bar. */
  .growth-bar::after{
    content:''; position:absolute; top:0; left:2px; right:2px; height:5px;
    border-radius:999px; background:rgba(255,255,255,0.6);
  }
  @media (prefers-reduced-motion:reduce){ .growth-bar{ transition:none !important; } }
  .growth-label{ font-size:12.5px; font-weight:700; color:var(--slate-400); text-transform:uppercase; letter-spacing:.03em; margin-top:10px; }
  .growth-day.today .growth-label{ color:var(--owl-orange-600); }

  /* ---- Badges: bigger, with a subtle shine sweep on earned tiles ---- */
  .badge-grid{ display:grid; grid-template-columns:repeat(2,1fr); gap:18px; }
  @media (min-width:640px){ .badge-grid{ grid-template-columns:repeat(3,1fr); } }
  @media (min-width:960px){ .badge-grid{ grid-template-columns:repeat(6,1fr); } }
  .badge-tile{ position:relative; overflow:hidden; border-radius:24px; padding:24px 14px; text-align:center; transition:transform .18s ease; }
  .badge-tile.earned{ background:linear-gradient(155deg,#fff3c4,#f0b03e); box-shadow:0 14px 24px -14px rgba(240,176,62,0.55); }
  .badge-tile.earned:hover{ transform:translateY(-3px); }
  .badge-tile.earned::after{
    content:''; position:absolute; top:-60%; left:-30%; width:40%; height:220%;
    background:linear-gradient(120deg, transparent, rgba(255,255,255,0.55), transparent);
    animation:badgeShine 4.5s ease-in-out infinite;
  }
  @keyframes badgeShine{ 0%,60%,100%{ transform:translateX(0) rotate(20deg); } 78%{ transform:translateX(340%) rotate(20deg); } }
  .badge-tile.locked{ background:var(--bg-0); border:2px dashed var(--line); }
  .badge-tile-icon{ font-size:56px; line-height:1; margin-bottom:12px; position:relative; z-index:1; }
  .badge-tile.locked .badge-tile-icon{ filter:grayscale(1); opacity:.45; }
  .badge-tile-name{ font-family:'Baloo 2',sans-serif; font-size:16px; font-weight:800; margin:0 0 6px; position:relative; z-index:1; }
  .badge-tile.earned .badge-tile-name{ color:#5a3d00; }
  .badge-tile.locked .badge-tile-name{ color:var(--slate-600); }
  .badge-tile-desc{ font-size:13px; font-weight:600; line-height:1.4; margin:0 0 10px; position:relative; z-index:1; }
  .badge-tile.earned .badge-tile-desc{ color:#7a5400; }
  .badge-tile.locked .badge-tile-desc{ color:var(--slate-600); opacity:.85; }
  .badge-tile-date{ font-size:12.5px; font-weight:800; color:#5a3d00; margin:0; position:relative; z-index:1; }
  .badge-tile-locked-label{ font-size:12.5px; font-weight:800; color:var(--slate-600); margin:0; position:relative; z-index:1; }

  /* ---- Bookshelf ---- */
  .book-list{ display:flex; flex-direction:column; gap:16px; }
  @media (min-width:760px){ .book-list{ display:grid; grid-template-columns:1fr 1fr; gap:16px; } }
  .book-card{
    display:flex; align-items:center; gap:16px; background:var(--bg-0); border:1px solid var(--line);
    border-radius:22px; padding:18px 20px; transition:transform .15s ease, box-shadow .15s ease;
  }
  .book-card:hover{ transform:translateY(-2px); box-shadow:var(--shadow-sm); }
  .book-icon{
    width:62px;height:62px;border-radius:18px; flex-shrink:0; font-size:26px; position:relative; overflow:hidden;
    background:linear-gradient(155deg, var(--sky-100), var(--blue-500)); color:#fff;
    display:flex;align-items:center;justify-content:center;
  }
  .book-icon::after{ content:''; position:absolute; left:0; top:0; bottom:0; width:6px; background:rgba(255,255,255,0.35); }
  .book-info{ flex:1; min-width:0; }
  .book-title{ font-family:'Baloo 2',sans-serif; font-size:18px; font-weight:700; margin:0 0 5px; }
  .book-meta{ font-size:14.5px; font-weight:600; color:var(--slate-600); line-height:1.4; display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
  .book-accuracy{
    display:inline-flex; align-items:center; font-family:'Baloo 2',sans-serif; font-weight:800; font-size:13px;
    color:var(--teal-700); background:var(--success-bg); padding:2px 9px; border-radius:999px;
  }
  .reread-btn{
    flex-shrink:0; display:inline-flex; align-items:center; gap:6px; font:800 15px/1 'Baloo 2',sans-serif;
    color:#fff; background:linear-gradient(155deg, var(--blue-500), var(--blue-700)); padding:13px 18px;
    border-radius:14px; text-decoration:none; transition:transform .15s ease;
  }
  .reread-btn:hover{ transform:translateY(-1px); }
  .empty-note{ font-size:16px; color:var(--slate-600); font-weight:600; line-height:1.5; }

  @media (max-width:520px){
    .profile-text h1{ font-size:26px; }
    .hero-tile h2{ font-size:24px; }
    .stat-card .v{ font-size:24px; }
  }
</style>
</head>
<body>
<div class="page">

  @php
    $avatarGlyph = $learner->avatar_id ?? '';
    // A real, disclosed data-shape quirk: avatar_id is meant to hold a
    // short preset key/emoji, but at least one real Learner record has
    // a full name string in it instead — which overflowed this small
    // badge before this fix. Falls back to a plain first-initial for
    // anything longer than a short glyph, rather than truncating text
    // mid-character or letting it spill out of the shape.
    $avatarIsShortGlyph = mb_strlen($avatarGlyph) <= 4;
  @endphp

  <div class="profile-header">
    <div class="avatar">
      @if ($learner->avatar_photo_path)
        <img src="{{ Storage::url($learner->avatar_photo_path) }}" alt="{{ $learner->first_name }}">
      @elseif ($avatarIsShortGlyph && $avatarGlyph !== '')
        {{ $avatarGlyph }}
      @else
        {{ strtoupper(mb_substr($learner->first_name, 0, 1)) }}
      @endif
    </div>
    <div class="profile-text">
      <h1>Hi, {{ $learner->first_name }}!</h1>
      <p class="grade-line">{{ $learner->grade_level }}</p>
    </div>
    <form method="POST" action="{{ route('learner.logout') }}" class="switch-form">
      @csrf
      <button type="submit">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M7 8l-4 4 4 4M3 12h13M17 4h2a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Not you? Switch learner
      </button>
    </form>
  </div>

  <div class="stat-row">
    <div class="stat-card"><div class="v level">{{ $learner->mastery_level ?? 'New' }}</div><div class="l">Level</div></div>
    <div class="stat-card"><div class="v">{{ $learner->points }}</div><div class="l">Points</div></div>
    <div class="stat-card"><div class="v">🔥 {{ $learner->streak }}</div><div class="l">Streak</div></div>
  </div>

  <div class="hero-tile">
    <div class="hero-blob b1"></div>
    <div class="hero-blob b2"></div>
    <div class="hero-content">
      @if ($upNextCompetency)
        <span class="hero-tag">🎯 Picked just for you</span>
        <h2>Time to work on {{ $upNextCompetency['label'] }}!</h2>
        <p>TaraBasa picked today's story because of how your last few readings went — {{ strtolower($upNextCompetency['difficultyWord'] ?? 'just right') }} for you right now.</p>
      @else
        <span class="hero-tag">📖 Ready when you are</span>
        <h2>Ready to read, {{ $learner->first_name }}?</h2>
        <p>Tap below and let's find your next story!</p>
      @endif
      <a href="{{ route('learner.activity.find') }}" class="hero-cta">Start Reading Activity</a>
    </div>
    <div class="hero-visual">
      <div class="hero-pulse"></div>
      <div class="hero-halo"></div>
      <div class="hero-owl">
        <svg viewBox="0 0 300 300" xmlns="http://www.w3.org/2000/svg">
          <path d="M92,118 C74,88 54,56 46,24 C69,45 96,71 110,108 Z" fill="var(--owl-body)" stroke="var(--owl-ink)" stroke-width="5" stroke-linejoin="round"/>
          <path d="M208,118 C226,88 246,56 254,24 C231,45 204,71 190,108 Z" fill="var(--owl-body)" stroke="var(--owl-ink)" stroke-width="5" stroke-linejoin="round"/>
          <path d="M150,55 C206,55 242,96 246,151 C251,212 230,272 150,277 C70,272 49,212 54,151 C58,96 94,55 150,55 Z"
                fill="var(--owl-body)" stroke="var(--owl-ink)" stroke-width="6" stroke-linejoin="round"/>
          <circle cx="117" cy="146" r="59" fill="var(--owl-face)"/>
          <circle cx="183" cy="146" r="59" fill="var(--owl-face)"/>
          <g class="wing wing-l">
            <path d="M60,182 C39,213 44,253 86,277 C97,282 108,276 102,266 C81,251 70,220 79,189 Z" fill="var(--owl-body)" stroke="var(--owl-ink)" stroke-width="5" stroke-linejoin="round"/>
          </g>
          <g class="wing wing-r">
            <path d="M240,182 C261,213 256,253 214,277 C203,282 192,276 198,266 C219,251 230,220 221,189 Z" fill="var(--owl-body)" stroke="var(--owl-ink)" stroke-width="5" stroke-linejoin="round"/>
          </g>
          <ellipse cx="150" cy="228" rx="54" ry="46" fill="var(--owl-face)"/>
          <ellipse cx="88" cy="184" rx="15" ry="10" fill="var(--owl-cheek)" opacity=".85"/>
          <ellipse cx="212" cy="184" rx="15" ry="10" fill="var(--owl-cheek)" opacity=".85"/>
          <circle cx="117" cy="149" r="32" fill="#ffffff" stroke="var(--owl-ink)" stroke-width="5"/>
          <circle cx="183" cy="149" r="32" fill="#ffffff" stroke="var(--owl-ink)" stroke-width="5"/>
          <circle cx="117" cy="155" r="19" fill="var(--owl-ink)"/>
          <circle cx="183" cy="155" r="19" fill="var(--owl-ink)"/>
          <circle cx="108" cy="141" r="6" fill="#ffffff"/>
          <circle cx="174" cy="141" r="6" fill="#ffffff"/>
          <path d="M150,168 C159,168 165,174 165,181 C165,191 150,199 150,199 C150,199 135,191 135,181 C135,174 141,168 150,168 Z"
                fill="var(--owl-beak)" stroke="var(--owl-ink)" stroke-width="4" stroke-linejoin="round"/>
          <path d="M142,190 Q150,196 158,190" fill="none" stroke="#a33d1f" stroke-width="3" stroke-linecap="round"/>
        </svg>
      </div>
    </div>
  </div>

  <a href="{{ route('learner.games.index') }}" class="games-btn">
    <svg class="deco" width="100%" height="100%" preserveAspectRatio="none"><circle cx="92%" cy="20%" r="34" fill="white"/><circle cx="98%" cy="75%" r="22" fill="white"/></svg>
    <div class="icon"><svg width="30" height="30" viewBox="0 0 24 24" fill="none"><rect x="3" y="7" width="18" height="11" rx="4" fill="white"/><circle cx="8" cy="12.5" r="1.4" fill="#dd7014"/><circle cx="16" cy="12.5" r="1.4" fill="#dd7014"/></svg></div>
    <div class="text"><h3>🎮 Practice Games</h3><p>Free play — no points, just for fun</p></div>
    <svg class="chevron" width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M9 6l6 6-6 6" stroke="white" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
  </a>

  <div class="panel">
    <h2 class="panel-title">How I'm Growing 🌱</h2>
    <div class="panel-card">
      @if (! $hasJourneyData)
        <p class="journey-empty">Your growth path fills in once you complete your first reading check! 🌱</p>
      @else
        <div class="journey-legend">
          <span>Just Started</span><span>Getting Going</span><span>Halfway There</span><span>Almost There</span><span>All Done!</span>
        </div>
        @foreach ($journeyRows as $row)
          @php
            // Purely decorative extra ticks between the 5 real
            // checkpoints, computed here in the view (simple linear
            // interpolation over the already-real point coordinates
            // passed in from the controller) — makes the path read as
            // a richer "road" without inventing any new milestones.
            $ticks = [];
            if ($row['track']) {
              $pts = $row['track']['points'];
              for ($i = 0; $i < count($pts) - 1; $i++) {
                $ticks[] = ['x' => $pts[$i]['x'] + ($pts[$i+1]['x'] - $pts[$i]['x']) * 0.33, 'y' => $pts[$i]['y'] + ($pts[$i+1]['y'] - $pts[$i]['y']) * 0.33];
                $ticks[] = ['x' => $pts[$i]['x'] + ($pts[$i+1]['x'] - $pts[$i]['x']) * 0.67, 'y' => $pts[$i]['y'] + ($pts[$i+1]['y'] - $pts[$i]['y']) * 0.67];
              }
            }
          @endphp
          <div class="journey-row">
            <div class="journey-label">
              <strong>{{ $row['label'] }}</strong>
              @if ($row['track'])
                <span class="journey-word">{{ $row['difficultyWord'] ?? 'Just Right' }}</span>
              @else
                <span class="journey-word" style="color:var(--slate-400)">Not started yet</span>
              @endif
              @if ($row['isUpNext'])
                <span class="up-next-badge">⭐ Up Next</span>
              @endif
            </div>
            <svg class="journey-track" viewBox="0 -4 500 78" preserveAspectRatio="xMidYMid meet">
              @if ($row['track'])
                <polyline points="{{ $row['track']['greyPolyline'] }}" class="track-grey" />
                <polyline points="{{ $row['track']['coloredPolyline'] }}" class="track-colored" />
                @foreach ($ticks as $tk)
                  <circle cx="{{ $tk['x'] }}" cy="{{ $tk['y'] }}" r="2.5" class="tick-dot" />
                @endforeach
                @foreach ($row['track']['points'] as $i => $p)
                  <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="10" class="{{ $row['track']['checkpointsDone'][$i] ? 'checkpoint-done' : 'checkpoint-todo' }}" />
                  @if ($row['track']['checkpointsDone'][$i])
                    <text x="{{ $p['x'] }}" y="{{ $p['y'] + 4 }}" class="checkmark">✓</text>
                  @endif
                @endforeach
                <g transform="translate({{ $row['track']['marker']['x'] }},{{ $row['track']['marker']['y'] }})">
                  <circle cx="0" cy="0" r="17" fill="var(--surface)" stroke="var(--owl-orange-500)" stroke-width="2.5"/>
                  <g class="fly-owl">
                    <path d="M-7,-9 L-10,-14 L-4,-11 Z" fill="var(--owl-body)" stroke="var(--owl-ink)" stroke-width="1"/>
                    <path d="M7,-9 L10,-14 L4,-11 Z" fill="var(--owl-body)" stroke="var(--owl-ink)" stroke-width="1"/>
                    <g class="fly-wing-l"><path d="M-6,-4 C-15,-8 -17,3 -8,8 C-9,2 -8,-2 -6,-4 Z" fill="var(--owl-body)" stroke="var(--owl-ink)" stroke-width="1.3"/></g>
                    <g class="fly-wing-r"><path d="M6,-4 C15,-8 17,3 8,8 C9,2 8,-2 6,-4 Z" fill="var(--owl-body)" stroke="var(--owl-ink)" stroke-width="1.3"/></g>
                    <ellipse cx="0" cy="0" rx="8.5" ry="8" fill="var(--owl-body)" stroke="var(--owl-ink)" stroke-width="1.3"/>
                    <circle cx="-3.4" cy="-1" r="3" fill="#fff" stroke="var(--owl-ink)" stroke-width="1"/>
                    <circle cx="3.4" cy="-1" r="3" fill="#fff" stroke="var(--owl-ink)" stroke-width="1"/>
                    <circle cx="-3.4" cy="-0.5" r="1.4" fill="var(--owl-ink)"/>
                    <circle cx="3.4" cy="-0.5" r="1.4" fill="var(--owl-ink)"/>
                    <path d="M-1.8,3 L1.8,3 L0,5.8 Z" fill="var(--owl-beak)"/>
                  </g>
                </g>
              @else
                <polyline points="20,50 140,18 260,50 380,18 480,50" class="track-locked" stroke-dasharray="6 6" />
                @foreach ([[20,50],[140,18],[260,50],[380,18],[480,50]] as $p)
                  <circle cx="{{ $p[0] }}" cy="{{ $p[1] }}" r="10" class="checkpoint-locked" />
                @endforeach
              @endif
            </svg>
            @if ($row['track'])
              <p class="journey-here">🦉 Flying here right now — {{ $row['difficultyWord'] ?? 'just getting started' }}</p>
            @endif
          </div>
        @endforeach
      @endif
    </div>
  </div>

  <div class="pair-row">
    <div class="panel goal-panel {{ $weeklyMet ? 'met' : '' }}" style="margin-bottom:0;">
      <h2 class="panel-title">This Week's Goal</h2>
      <div class="panel-card">
        @php
          $goalCircumference = 2 * M_PI * 63;
          $goalOffset = $goalCircumference * (1 - min(100, $weeklyPercent) / 100);
        @endphp
        <div class="goal-ring-wrap">
          <svg viewBox="0 0 150 150">
            <defs>
              <linearGradient id="goalGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                @if ($weeklyMet)
                  <stop offset="0%" stop-color="#f0b03e"/><stop offset="100%" stop-color="#dd7014"/>
                @else
                  <stop offset="0%" stop-color="var(--teal)"/><stop offset="100%" stop-color="var(--blue-500)"/>
                @endif
              </linearGradient>
            </defs>
            <circle cx="75" cy="75" r="63" class="goal-ring-bg"/>
            <circle cx="75" cy="75" r="63" class="goal-ring-fill" stroke-dasharray="{{ $goalCircumference }}" stroke-dashoffset="{{ $goalOffset }}"/>
          </svg>
          <div class="goal-ring-center">
            <div class="goal-ring-icon">{{ $weeklyMet ? '🏆' : '🎯' }}</div>
            @if ($weeklyMet)
              <div class="goal-ring-count">{{ $weeklyCount }}<span>!</span></div>
            @else
              <div class="goal-ring-count">{{ $weeklyCount }}<span>/{{ $weeklyTarget }}</span></div>
            @endif
          </div>
        </div>
        @if ($weeklyMet)
          <p class="goal-sub">your goal was {{ $weeklyTarget }} real readings this week</p>
          <p class="goal-note">You hit your goal this week — amazing job! 🎉</p>
        @else
          <p class="goal-sub">real reading activities this week</p>
          <p class="goal-note">{{ $weeklyTarget - $weeklyCount }} more {{ ($weeklyTarget - $weeklyCount) === 1 ? 'reading' : 'readings' }} to go!</p>
        @endif
      </div>
    </div>

    <div class="panel growth-panel" style="margin-bottom:0;">
      <h2 class="panel-title">My Growth</h2>
      <div class="panel-card">
        <p class="growth-total"><span class="growth-total-icon">📈</span>You've read <strong>{{ $weeklyCount }}</strong> {{ $weeklyCount === 1 ? 'story' : 'stories' }} this week</p>
        <div class="growth-chart" id="growthChart">
          @foreach ($growthDays as $day)
            <div class="growth-day {{ $day['isToday'] ? 'today' : '' }}">
              <div class="growth-count">{{ $day['count'] > 0 ? $day['count'] : '' }}</div>
              <div class="growth-bar-track">
                @if ($day['count'] > 0)
                  {{-- Starts at 0 height; JS animates it up to data-fill (like a
                       real reading log) once this chart scrolls into view, and
                       resets back to 0 when it scrolls out — see the script at
                       the bottom of the page. Falls back to the final height
                       immediately if JS never runs or motion is reduced. --}}
                  <div class="growth-bar" data-fill="{{ max(12, round($day['count'] / $growthMax * 100)) }}" style="height:{{ max(12, round($day['count'] / $growthMax * 100)) }}%; transition-delay:{{ $loop->index * 90 }}ms;"></div>
                @endif
              </div>
              <div class="growth-label">{{ $day['label'] }}</div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>

  <div class="panel">
    <h2 class="panel-title">My Badges <span class="see-count">— {{ $earnedBadgeCount }} of {{ $totalBadgeCount }}</span></h2>
    <div class="badge-grid">
      @foreach ($badges as $badge)
        <div class="badge-tile {{ $badge['earned'] ? 'earned' : 'locked' }}">
          <div class="badge-tile-icon">{{ $badge['emoji'] }}</div>
          <p class="badge-tile-name">{{ $badge['name'] }}</p>
          <p class="badge-tile-desc">{{ $badge['description'] }}</p>
          @if ($badge['earned'])
            <p class="badge-tile-date">Earned {{ $badge['earnedAt']->format('M j, Y') }}</p>
          @else
            <p class="badge-tile-locked-label">🔒 Not yet</p>
          @endif
        </div>
      @endforeach
    </div>
  </div>

  <div class="panel">
    <h2 class="panel-title">My Bookshelf</h2>
    @if ($books->isEmpty())
      <p class="empty-note">Nothing here yet — finish a reading activity and it'll show up on your shelf!</p>
    @else
      <div class="book-list">
        @foreach ($books as $book)
          <div class="book-card">
            <div class="book-icon">📖</div>
            <div class="book-info">
              <p class="book-title">{{ $book['activity']->title }}</p>
              <p class="book-meta">
                Read {{ $book['timesRead'] }} {{ $book['timesRead'] === 1 ? 'time' : 'times' }} ·
                <span class="book-accuracy">Best: {{ round($book['bestAccuracy']) }}%</span> ·
                {{ $book['mostRecentDate']->format('M j') }}
              </p>
            </div>
            <a href="{{ route('learner.bookshelf.reread', $book['activity']) }}" class="reread-btn">Read Again</a>
          </div>
        @endforeach
      </div>
    @endif
  </div>

</div>
<script>
  // "My Growth" liquid-fill: bars start empty and pour up to their real
  // count-height the moment the chart scrolls into view, staggered per
  // day (transition-delay, set inline in Blade) for a wave-like fill
  // across the week — then reset back to empty if the chart scrolls out
  // of view again, so scrolling back down replays the fill. A purely
  // decorative enhancement over real, already-rendered data — if this
  // script fails for any reason, the bars stay at their correct final
  // heights (the server-rendered value), never blank or wrong.
  (function () {
    try {
      var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      var chart = document.getElementById('growthChart');
      var bars = chart ? chart.querySelectorAll('.growth-bar') : [];
      if (reduceMotion || !chart || !bars.length || !('IntersectionObserver' in window)) return;

      bars.forEach(function (bar) { bar.style.height = '0%'; });

      var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          bars.forEach(function (bar) {
            bar.style.height = entry.isIntersecting ? (bar.dataset.fill + '%') : '0%';
          });
        });
      }, { threshold: 0.35 });

      observer.observe(chart);
    } catch (e) {
      // A failed decorative animation must never block the real dashboard.
    }
  })();
</script>
</body>
</html>
