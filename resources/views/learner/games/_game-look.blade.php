{{--
  The look shared by both Practice Games (Word Builder and Letter Match), so they
  match the rest of the Learner screens: a plain white page, a light blue panel
  with a lip, the game face for words, clay orange buttons. Included inside <head>;
  each game keeps only what is its own (tiles and slots, cards) in its own <style>.

  Expects the page to load Barlow Semi Condensed and Inter (see the games' <head>).
--}}
<style>
  :root{
    --blue-500:#1c7ed6;
    --navy-900:#131f2b; --slate-600:#5b6b7a;
    --owl-orange-500:#ef8d2a; --owl-orange-600:#dd7014; --clay-yellow:#ffcf6e;
    --teal:#2bb89c; --success:#1f9e83; --success-bg:#e9f7f3;
    --danger:#d64545;
    --line:#dbe7f3; --panel:#f1f7ff; --panel-line:#d3e3f4; --lip:#c3d8ee;
    --font-game:'Bahnschrift SemiCondensed','Bahnschrift','Barlow Semi Condensed',sans-serif;
  }
  *{ box-sizing:border-box; }
  html,body{ margin:0; padding:0; background:#ffffff; }
  body{
    min-height:100vh; font-family:'Inter',sans-serif; color:var(--navy-900);
    display:flex; align-items:safe center; justify-content:center; padding:24px 20px 40px;
  }
  .wrap{ width:100%; max-width:480px; }
  @media (min-width:700px){ .wrap{ max-width:640px; } }

  .top-bar{ display:flex; align-items:center; justify-content:center; gap:16px; margin-bottom:14px; flex-wrap:wrap; }
  .level-badge{
    display:inline-flex; align-items:center; gap:8px; padding:9px 18px 8px; border-radius:999px;
    font:700 21px/1 var(--font-game); letter-spacing:.05em; text-transform:uppercase;
    color:var(--lv-ink, #1a6e4f); background:var(--lv-bg, #d8f2e4); box-shadow:0 4px 0 var(--lv-lip, #a7dcc0);
  }
  .level-badge .badge-svg{ width:24px; height:24px; flex:none; display:block; }
  .attempt-label{ font:600 20px/1 var(--font-game); letter-spacing:.05em; text-transform:uppercase; color:var(--slate-600); }

  .progress-wrap{ margin-bottom:16px; }
  .progress-label{ text-align:center; font:700 19px/1 var(--font-game); letter-spacing:.06em; text-transform:uppercase; color:var(--slate-600); margin-bottom:10px; }
  .progress-dots{ display:flex; gap:10px; justify-content:center; }
  .pdot{ width:13px; height:13px; border-radius:50%; background:var(--line); }
  .pdot.done{ background:var(--teal); }
  .pdot.current{ background:var(--owl-orange-500); transform:scale(1.3); }

  .card{
    position:relative; overflow:hidden; background:var(--panel); border:2px solid var(--panel-line); border-radius:30px;
    box-shadow:0 8px 0 var(--lip); padding:26px 22px 24px; text-align:center;
  }
  @media (min-width:700px){ .card{ padding:34px 34px 30px; border-radius:34px; } }
  .card > *{ position:relative; z-index:1; }

  /* Tara, the little SVG owl (games/_owl-mascot). No chip behind her: she sits straight on the panel. */
  .mascot, .celebrate-mascot{ width:104px; height:104px; margin:0 auto 6px; }
  .mascot{ animation:bob 2.4s ease-in-out infinite; }
  @keyframes bob{ 0%,100%{ transform:translateY(0); } 50%{ transform:translateY(-8px); } }
  h1{ font:700 32px/1.15 var(--font-game); color:#2f455b; margin:0 0 18px; }
  @media (min-width:700px){ h1{ font-size:38px; } }

  .celebrate{ display:none; }
  .celebrate.show{ display:block; animation:pop .5s cubic-bezier(.34,1.56,.64,1); }
  @keyframes pop{ 0%{ transform:scale(0); } 70%{ transform:scale(1.12); } 100%{ transform:scale(1); } }
  .celebrate-text{ margin:0; font:700 28px/1.2 var(--font-game); color:var(--success); }
  .level-up-text{ display:inline-flex; align-items:center; gap:8px; margin:10px 0 0; font:700 24px/1 var(--font-game); letter-spacing:.04em; text-transform:uppercase; color:var(--owl-orange-600); }
  .level-up-text .badge-svg{ width:26px; height:26px; }
  .round-summary{ margin:0 0 20px; font:500 24px/1.3 var(--font-game); color:var(--slate-600); }

  /* Badges earned by the session that just ended (filled in by tarabasaReportGame). */
  .gm-newbadges{ display:flex; flex-direction:column; align-items:center; gap:10px; margin:0 0 22px; }
  .gm-newbadge{
    display:flex; align-items:center; gap:12px; padding:10px 22px 12px 12px; border-radius:26px; background:#ffe9b0;
    box-shadow:0 5px 0 #e3b53a; text-align:left; animation:pop .5s cubic-bezier(.34,1.56,.64,1);
  }
  .gm-medal{
    flex:none; width:50px; height:50px; border-radius:50%; display:flex; align-items:center; justify-content:center;
    background:radial-gradient(circle at 34% 26%, #ffd79a, #ef8d2a 62%, #d4690d); box-shadow:0 4px 0 #b4560b, inset 0 3px 0 rgba(255,255,255,.45);
  }
  .gm-medal .badge-svg{ width:28px; height:28px; color:#fff; display:block; }
  .gm-newbadge-label{ font:600 15px/1 var(--font-game); letter-spacing:.08em; text-transform:uppercase; color:#7a5400; margin-bottom:4px; }
  .gm-newbadge-name{ font:700 23px/1.1 var(--font-game); color:#3f2c00; }

  .big-btn{
    display:block; width:100%; max-width:420px; margin:8px auto 0; padding:17px 24px 15px; border:none; border-radius:20px;
    font:600 24px/1.1 var(--font-game); letter-spacing:.06em; text-transform:uppercase; text-align:center; text-decoration:none; color:#fff; cursor:pointer;
    background:linear-gradient(180deg,#f9a544,#ee8a26); text-shadow:0 1px 0 rgba(0,0,0,.2);
    box-shadow:0 6px 0 #b4560b, 0 16px 22px -10px rgba(180,86,11,.6), inset 0 2px 0 rgba(255,255,255,.45);
    transition:transform .08s ease, box-shadow .08s ease;
  }
  .big-btn:hover{ transform:translateY(-1px); }
  .big-btn:active{ transform:translateY(4px); box-shadow:0 2px 0 #b4560b, 0 6px 10px -6px rgba(180,86,11,.6), inset 0 2px 0 rgba(255,255,255,.4); }
  .big-btn:focus-visible, .back-link:focus-visible{ outline:4px solid var(--blue-500); outline-offset:4px; }

  .back-link{
    display:table; margin:24px auto 0; padding:12px 26px 10px; border-radius:16px; text-decoration:none;
    font:600 20px/1 var(--font-game); letter-spacing:.05em; text-transform:uppercase; color:var(--slate-600);
    background:#ffffff; border:2px solid var(--line); box-shadow:0 4px 0 #e3ebf3; transition:transform .08s ease, box-shadow .08s ease;
  }
  .back-link:hover{ transform:translateY(-1px); }
  .back-link:active{ transform:translateY(3px); box-shadow:0 1px 0 #e3ebf3; }

  @media (prefers-reduced-motion:reduce){ *{ animation:none !important; transition:none !important; } }
</style>
