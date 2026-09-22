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
    --line:#e3ebf2; --surface:#ffffff; --bg-0:#f6faff; --section-tint:#eef2f7;
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

  /* Widened from 1040px to 1300px specifically to give the headline room to
     wrap onto 2 lines again (matching how it read before the owl grew) while
     keeping the owl at its current bigger size, instead of shrinking either
     one to fit the old width. Only the hero's own content is affected - the
     nav bar keeps its separate, unchanged 1040px max-width. */
  .hero-inner{ position:relative; z-index:3; max-width:1300px; margin:0 auto; padding:24px 20px 0; }
  .hero-grid{ position:relative; display:grid; grid-template-columns:1fr 1fr; gap:20px; align-items:center; }
  .hero-copy{ text-align:left; }
  .hero-headline{
    /* Bumped again per direct follow-up feedback: clamp max 84px -> 100px,
       min 32px -> 36px, vw factor 7.5 -> 8.5 so it scales up proportionally
       larger at mid-size viewports too, not just at the very top/bottom of
       the clamp range. Re-tested at every width already on record for this
       hero (375/1059/1280/1920) to confirm it still wraps cleanly with zero
       overflow and no overlap with the owl. */
    font-family:'Baloo 2',sans-serif; font-weight:800; line-height:1.0;
    font-size:clamp(36px, 8.5vw, 100px); margin:0 0 18px; color:#ffffff;
  }
  /* white-space:nowrap keeps "love reading." from breaking between the two
     words at narrower widths - per direct feedback, it should read as one
     continuous phrase, never "love" / "reading." split across two lines. */
  .hero-headline .accent{ color:var(--owl-orange-400); white-space:nowrap; }
  /* Bumped per direct follow-up feedback - 16px -> 20px, a real but smaller
     step than the headline's own jump. */
  .hero-sub{ font-size:20px; color:var(--sky-50); opacity:.92; font-weight:500; line-height:1.6; margin:0; max-width:420px; }

  /* Shifted from centered to flex-end (right-aligned within its own grid
     column) per direct feedback that a dead-centered owl looked off - now it
     sits toward the right side of the hero instead of the middle. Only for
     the two-column desktop layout; re-centered below in the stacked
     single-column media query, where "right-aligned" would just look
     off-center against the full-width column instead of intentional. */
  .hero-mascot{ position:relative; display:flex; flex-direction:column; align-items:flex-end; }
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
    .hero-mascot{ order:2; margin-top:6px; align-items:center; }
  }

  /* ---------- Section 2: reading scene (rabbit + text, closing sunrise) ----------
     A fresh build, not a replacement - the old sword-bird section was fully
     removed earlier this session (HTML/CSS/JS and its assets), so nothing
     here reuses or collides with that removed code. */
  .reading-scene{
    position:relative;
    /* Flat, matching the horizon wave's own fill (--section-tint) exactly -
       per direct feedback, the earlier two-stop gradient started at a
       different shade than the wave above it, creating a visible hard
       rectangular seam ("a square box") right at the section boundary.
       Using the identical color the wave is filled with means the curve
       itself is the only boundary - no seam to blend. */
    background:var(--section-tint);
    padding:64px 0 24px;
    overflow:hidden;
  }
  .reading-scene-inner{
    max-width:1040px; margin:0 auto; padding:0 20px;
    display:flex; align-items:flex-start; gap:36px; flex-wrap:wrap;
  }
  /* Bumped up per direct feedback ("suit the best size") - 300px is a real,
     visible increase from 240px, matching the size/font relationship shown
     in the reference mockup more closely. */
  .rabbit-stage{ position:relative; width:min(300px, 58vw); flex-shrink:0; }
  .rabbit-lottie{ width:100%; aspect-ratio:1/1; display:block; }
  /* Starts hidden, revealed via IntersectionObserver + a delayed class add
     once the section scrolls into view - the delay matches the rabbit's own
     real "settle" frame (frame 15 of its 30fps gesture = 0.5s), verified
     from the file's own keyframe data, not guessed. */
  .reading-text{
    flex:1 1 300px; min-width:260px; padding-top:8px;
    opacity:0; transform:translateY(16px);
    transition:opacity .6s ease, transform .6s ease;
  }
  .reading-text.revealed{ opacity:1; transform:translateY(0); }
  .reading-text .section-headline{
    font-family:'Baloo 2',sans-serif; font-weight:700;
    font-size:clamp(28px, 4vw, 40px); line-height:1.2;
    color:var(--navy-900); margin:0 0 12px;
  }
  .reading-text .section-headline .section-accent{ color:var(--owl-orange-500); }
  .reading-text .section-body{
    font-family:'Inter',sans-serif; font-weight:500; font-size:19px; line-height:1.6;
    color:var(--slate-600); margin:0; max-width:480px;
  }
  /* Widened again per direct feedback ("full in the screen") - the earlier
     1040px cap with 20px side padding still left visible margin on wide
     screens. Raised the cap to 1600px (not fully unbounded - at 594:222
     aspect, unbounded width would make it absurdly tall on ultra-wide
     monitors) and dropped padding to a minimal 8px, matching the reference
     image the user pointed at. */
  .sunrise-stage{ max-width:1600px; margin:28px auto 0; padding:0 8px; }
  .sunrise-lottie{ width:100%; aspect-ratio:594/222; display:block; }

  @media (max-width:640px){
    .reading-scene-inner{ justify-content:center; text-align:center; }
    .rabbit-stage{ margin:0 auto; }
    .reading-text{ text-align:center; }
    .reading-text .section-body{ margin:0 auto; }
  }

  /* ---------- Section 3: personalized learning (text + flying hero, plane
     accent) ---------- */
  .learner-scene{
    position:relative;
    /* Starts at the exact same color Section 2 ends on (--section-tint) so
       there's no seam at that boundary - the same lesson learned from the
       hero-to-Section-2 transition - then drifts to a soft mint/teal tint
       by the section's own end, echoing the headline's --parent-teal
       accent without being loud. */
    background:linear-gradient(180deg, var(--section-tint) 0%, #f3faf8 100%);
    /* Spacing trimmed to just what the 720px plane needs. The plane is a
       full-frame flight (its real painted pixels span y 56-756 of its 800px
       canvas, checked frame by frame), so it needs about 620px of vertical
       room centered on the text block, far more than the ~340px row itself.
       The top padding is the smallest that keeps the plane's highest point
       below Section 2's sunrise (the plane may dip into that section's own
       empty bottom margin, which is why overflow-y is left visible - hiding
       it would slice the plane off). Horizontal overflow is still clipped
       so the 720px stage can't cause sideways scrolling. */
    padding:63px 0 150px;
    overflow:hidden;
    overflow-x:clip;
    overflow-y:visible;
  }
  .learner-scene-inner{
    max-width:1040px; margin:0 auto; padding:0 20px;
    display:flex; align-items:center; gap:36px; flex-wrap:wrap;
  }
  .learner-text-wrap{ position:relative; flex:1 1 320px; min-width:260px; }
  /* Given a real stacking context (z-index:1) so the actual words render
     in front of .plane-stage (z-index:0, behind) - the plane now overlaps
     the text block itself rather than sitting below/beside it, so this is
     what keeps the copy legible while the plane peeks out from behind and
     around its edges. */
  .learner-text{
    position:relative; z-index:1;
    opacity:0; transform:translateY(16px);
    transition:opacity .6s ease, transform .6s ease;
  }
  .learner-text.revealed{ opacity:1; transform:translateY(0); }
  .learner-text .section-headline{
    font-family:'Baloo 2',sans-serif; font-weight:700;
    font-size:clamp(28px, 4vw, 40px); line-height:1.2;
    color:var(--parent-teal); margin:0 0 12px;
  }
  /* text-align:justify per direct feedback ("justify for clean") - clean,
     even edges on both sides of the paragraph instead of a ragged right
     edge. */
  .learner-text .section-body{
    font-family:'Inter',sans-serif; font-weight:500; font-size:19px; line-height:1.6;
    color:var(--slate-600); margin:0; max-width:480px; text-align:justify;
  }
  /* The plane accent - alignment (centered on the text-wrap) and layering
     (behind the text, z-index:0 under .learner-text's z-index:1) both
     confirmed good per direct feedback, kept unchanged. Per the same
     feedback: doubled in size again (360px -> 720px) and opacity brought
     back up to fully clear/solid (0.55 -> 1) instead of faded - the
     margins are still exactly half its own width/height so it stays
     centered on the text-wrap at the new size. Plane.json is a 270-frame
     raster image sequence (confirmed from its raw source: every layer's
     own transform position is fixed at [0,0], each visible for exactly
     one frame), so there's no separate position/transform data to sync a
     path to - the CSS orbit below is what makes it genuinely move,
     independent of whatever motion is baked into the asset's own pixels. */
  .plane-stage{
    position:absolute; z-index:0;
    left:50%; top:50%; margin-left:-360px; margin-top:-300px;
    width:720px; aspect-ratio:960/800; opacity:1;
    animation:planeOrbit 14s ease-in-out infinite;
    pointer-events:none;
  }
  .plane-lottie{ width:100%; height:100%; display:block; }
  @keyframes planeOrbit{
    0%{ transform:translate(0,0) rotate(0deg); }
    25%{ transform:translate(-14px,10px) rotate(-4deg); }
    50%{ transform:translate(-4px,22px) rotate(2deg); }
    75%{ transform:translate(10px,8px) rotate(4deg); }
    100%{ transform:translate(0,0) rotate(0deg); }
  }
  .hero-flying-stage{ position:relative; width:min(340px, 62vw); flex-shrink:0; }
  .hero-flying-lottie{ width:100%; aspect-ratio:1/1; display:block; }

  @media (max-width:640px){
    .learner-scene-inner{ justify-content:center; text-align:center; }
    .learner-text{ text-align:center; }
    .learner-text .section-body{ margin:0 auto; }
    .hero-flying-stage{ margin:0 auto; }
    .plane-stage{ margin-left:-240px; margin-top:-200px; width:480px; }
    /* Stacked layout: the plane (480x400) only reaches ~100px below the
       text block, well inside the boy below it, so the big bottom padding
       the side-by-side desktop layout needs would just be empty space. */
    .learner-scene{ padding:63px 0 30px; }
  }

  /* ---------- Section 4: stay motivated (looping text + sword-bird
     chase) ---------- */
  /* A real flexbox row, NOT absolute-offset math - direct feedback caught a
     real problem with the earlier absolute-position version: the owl ended
     up overlapping/behind the text, when the actual ask is a clean
     side-by-side composition (owl left, a real gap, text right) that the
     owl's sword must never cross into ("no trespass"), at rest AND at every
     point during the chase. Flexbox guarantees this structurally instead of
     needing careful pixel-tuning: both children's LAYOUT boxes (including
     the real `gap` between them) are fixed by flex from the very first
     frame - only their `transform` is animated for the entrance, and
     `transform` never affects layout, so the owl's rendered pixels can
     never actually enter the text's own reserved column no matter how far
     it's translated. `order:-1` visually places the owl first (left) while
     it stays second in the real DOM/reading order (decorative content
     shouldn't need to be read before the actual copy). */
  .stay-scene{
    position:relative;
    /* Starts on Section 3's own closing tint (#f3faf8) so there's no seam,
       drifts to a soft warm orange tint by the end - echoing the
       streaks/points theme via the existing --owl-orange/--clay-yellow
       tokens instead of introducing a new color. */
    background:linear-gradient(180deg, #f3faf8 0%, #fff6e9 100%);
    /* Tightened (was 100px top/bottom): the owl's artboard has a lot of
       empty margin (its painted pixels only fill ~400 of its 680px), which
       .duo-stage now trims with a negative margin below, so the section no
       longer needs big padding on top of that. The 51px top is the smallest
       that still clears the paper plane's lowest point in Section 3. */
    padding:51px 0 20px;
    overflow:hidden;
  }
  /* Wider than the site's usual 1040px column - measured live (see the
     max-width:1099px breakpoint below): a 680px owl + a real 56px gap +
     the text column need ~1200px to sit side by side without the owl's
     own left edge clipping past the viewport at typical laptop widths.
     Only this section needs the extra room, since it's the only one with
     a character this large next to its text. */
  .stay-scene-inner{
    display:flex; align-items:center; justify-content:flex-end;
    gap:56px; flex-wrap:nowrap;
    max-width:1200px; margin:0 auto; padding:0 20px;
    min-height:460px;
  }
  .stay-text-wrap{ position:relative; z-index:1; flex:0 0 auto; max-width:480px; }
  /* The text genuinely runs from a left starting point to its right-
     anchored resting spot (confirmed with the user directly - this is the
     literal "chased by the owl" visual, not just a plain fade). It fully
     completes and comes to a stop BEFORE the owl's own chase-in begins
     (see the phase timing below) so the two motions never race each other -
     text runs and stops, then the owl runs and catches up to it. */
  .stay-text{
    text-align:right;
    opacity:0; transform:translateX(-320px);
    transition:opacity .6s ease, transform .65s cubic-bezier(.22,.68,.36,1);
  }
  .stay-text.revealed{ opacity:1; transform:translateX(0); }
  .stay-text .section-headline{
    font-family:'Baloo 2',sans-serif; font-weight:700;
    font-size:clamp(28px, 4vw, 40px); line-height:1.2;
    color:var(--owl-orange-600); margin:0 0 12px;
  }
  .stay-text .section-body{
    font-family:'Inter',sans-serif; font-weight:500; font-size:19px; line-height:1.6;
    color:var(--slate-600); margin:0 0 0 auto; max-width:460px;
  }
  /* The owl - doubled in size per direct feedback (340px -> 680px), placed
     in its own real flex column to the text's left (order:-1) with a real
     56px gap between them that it never crosses. It chases in via its own
     transform, starting off past its own reserved slot (translateX(-820px)
     - well past its own 680px width, so it's genuinely off-screen, not
     merely adjacent) and animating to translateX(0), which is simply its
     already-reserved flex position - the "catching up" is entirely this
     element's own motion, never a change to where the text sits. */
  .duo-stage{
    position:relative; z-index:0; order:-1; flex:0 0 auto;
    width:680px; aspect-ratio:1/1;
    /* Trims the owl artboard's empty top/bottom margin out of the layout
       (its artwork sits in the middle ~400px of the 680px box). Only
       layout changes: the artwork isn't scaled or moved relative to the
       text, and the chase transform below is unaffected. */
    margin:-110px 0;
    opacity:0; transform:translateX(-820px);
    transition:opacity .3s ease, transform .83s cubic-bezier(.22,.68,.36,1);
    pointer-events:none;
  }
  .duo-stage.visible{ opacity:1; transform:translateX(0); }
  /* Duo's own baked keyframes move it right-to-left within its own frame
     (Body's real position data goes from x:1408 to x:502 in a 1080-wide
     canvas) - the opposite of the new left-to-right chase direction this
     section now needs. Mirroring the artwork itself (not the outer
     .duo-stage, which is busy with the chase transition) makes that baked
     motion read as coming from the left instead of fighting the new
     direction, with no changes to the asset itself. */
  .duo-flip{ transform:scaleX(-1); width:100%; height:100%; }
  .duo-lottie{ width:100%; height:100%; display:block; }

  @media (max-width:1099px){
    /* A real side-by-side "owl left, gap, text right" composition at
       680px-owl size needs roughly 1200px of width (680 owl + 56 gap +
       ~460 text column) - measured live, not guessed: on any viewport
       narrower than that, either the owl or the text would have to
       shrink well below what was asked for, or the two would end up
       clipped/cramped. Rather than compromise the size or the real gap
       Sections needs, anything under 1100px (phones AND most tablets)
       gets a vertical stack instead: text above, owl chasing in from
       below. Real normal document flow (flex-column), not absolute
       offsets against an auto-height box - the earlier absolute-offset
       version measured a real live bug here (the owl's bottom:24px was
       anchored against a short auto-height inner container, pushing most
       of the 380px-tall owl above the section's own overflow:hidden
       bounds - confirmed via getBoundingClientRect showing duoStage's top
       143px above stayScene's own top edge). Flex stacking sizes the
       container from its children's real height instead, so there's
       nothing to miscalculate. */
    /* Tightened with the desktop pass: the stacked owl artboard has empty
       margin top and bottom (its artwork fills the middle ~70%), trimmed
       below with negative margins instead of big section padding. The top
       padding still has to clear the Section 3 paper plane above. */
    .stay-scene{ padding:30px 0 20px; }
    .stay-scene-inner{
      height:auto; min-height:0; display:flex; flex-direction:column; align-items:center; gap:28px;
    }
    .stay-text-wrap{
      position:relative; top:auto; right:auto; margin-top:0;
      max-width:100%; text-align:center;
    }
    .stay-text{ text-align:center; transform:translateY(-24px); }
    .stay-text.revealed{ transform:translateY(0); }
    .stay-text .section-body{ margin:0 auto; }
    .duo-stage{
      position:static; order:0; margin:-18px 0 -50px;
      width:min(320px, 78vw);
      transform:translateY(50px);
    }
    .duo-stage.visible{ transform:translateY(0); }
  }

  /* ---------- Section 5: backed by real teachers (walking-boy foreground +
     city-skyline background banner) ---------- */
  .school-scene{
    position:relative;
    /* Continues Section 4's closing warm cream (#fff6e9) into a soft sky
       tint (--sky-50) - both light/neutral enough that the handoff stays
       gentle, and the sky tint sets up the outdoor city-skyline banner
       below it instead of clashing with it. */
    background:linear-gradient(180deg, #fff6e9 0%, var(--sky-50) 100%);
    /* Bottom padding is real, measured space for the walking-boy stage's
       own downward overlap past the banner strip (see
       .go-to-school-stage's negative bottom value below) - without it,
       this section's own overflow:hidden would clip his feet. */
    padding:56px 0 118px;
    overflow:hidden;
  }
  .school-scene-inner{
    position:relative; z-index:2;
    max-width:1040px; margin:0 auto; padding:0 20px;
  }
  .school-text{
    max-width:560px;
    opacity:0; transform:translateY(16px);
    transition:opacity .6s ease, transform .6s ease;
  }
  .school-text.revealed{ opacity:1; transform:translateY(0); }
  .school-text .section-headline{
    font-family:'Baloo 2',sans-serif; font-weight:700;
    font-size:clamp(28px, 4vw, 40px); line-height:1.2;
    color:var(--blue-700); margin:0 0 12px;
  }
  .school-text .section-body{
    font-family:'Inter',sans-serif; font-weight:500; font-size:19px; line-height:1.6;
    color:var(--slate-600); margin:0; max-width:520px;
  }

  /* The adapted city-banner scene - originally clipped to a 345x59 rounded
     pill (see CLAUDE.md for the real matte-removal fix), now rendered at
     its own full natural rectangular frame and scaled up as a unit
     (width:100%, aspect-ratio preserved) to span the section, rather than
     distorting it to a different ratio. */
  .school-banner-wrap{ position:relative; width:100%; margin-top:110px; }
  .city-banner-stage{
    position:relative; z-index:0;
    width:100%; aspect-ratio:345/59;
    opacity:0; transition:opacity .7s ease;
  }
  .city-banner-stage.revealed{ opacity:1; }
  .city-banner-lottie{ width:100%; height:100%; display:block; }

  /* The walking boy - a real, disclosed size adjustment from a literal 2x
     (680px) of the ~320px "single character" convention (Section 2's
     rabbit-stage, Section 3's hero-flying-stage): measured live that a
     truly 640-680px character standing on this section's own genuinely
     thin banner strip (345:59 aspect - only ~130-220px tall at real
     section widths) either overlapped the text above it or needed an
     awkward 350px+ blank gap to clear it, depending on viewport width.
     420px (~1.3x the baseline, not a literal 2x) is what keeps him
     genuinely bigger/more prominent than every other single-character
     illustration on this page while still fitting this specific
     thin-banner composition without either defect - confirmed via direct
     measurement at 768px and 1280px, both real widths that broke the
     original 640px sizing. He overlaps the banner's bottom edge by a real
     120px (not a small peek) so most of his height sits low, close to the
     strip, minimizing how far he reaches up toward the text above. */
  .go-to-school-stage{
    position:absolute; z-index:1;
    left:6%; bottom:-120px;
    width:min(420px, 34vw); aspect-ratio:1/1;
    opacity:0; transform:translateY(24px);
    transition:opacity .6s ease .15s, transform .6s ease .15s;
  }
  .go-to-school-stage.revealed{ opacity:1; transform:translateY(0); }
  .go-to-school-lottie{ width:100%; height:100%; display:block; }

  @media (max-width:760px){
    .school-scene{ padding:56px 0 40px; }
    .school-scene-inner{ text-align:center; }
    .school-text{ max-width:100%; margin:0 auto; }
    .school-text .section-body{ margin:0 auto; }
    /* Measured live: the walking-boy stage is tall enough (relative to a
       narrow phone) that it was genuinely overlapping the body text above
       it (confirmed via getBoundingClientRect - the boy's own top edge sat
       98px above the text's bottom edge before this fix). The base rule's
       margin-top:110px (shared with desktop) plus a smaller mobile stage
       size closes that for real. */
    .go-to-school-stage{
      left:50%; bottom:-10px;
      width:min(190px, 42vw);
      transform:translateX(-50%) translateY(24px);
    }
    .go-to-school-stage.revealed{ transform:translateX(-50%) translateY(0); }
  }

  /* ---------- Section 6: learning that feels like play (aerial farmland +
     kite + seesaw) ---------- */
  .games-scene{
    position:relative;
    /* Continues Section 5's closing sky tint (--sky-50) into a soft pale
       green, tying into the real green/brown farmland colors baked into
       the Lottie asset itself below, instead of a hard seam. */
    background:linear-gradient(180deg, var(--sky-50) 0%, #eef9f0 100%);
    /* The farmland now sits flush at the bottom of the section (the
       see-saw stands ON it, per the reference picture), so no extra
       bottom allowance is needed. */
    padding:39px 0 0;
    overflow:hidden;
  }
  .games-scene-inner{
    position:relative; z-index:3;
    max-width:1040px; margin:0 auto; padding:0 20px;
  }
  /* Text and kite sit side by side in a real flexbox row - the same
     structural guarantee already proven for Section 4's chase (owl/text):
     both children's layout boxes, including the real gap between them,
     are fixed by flex itself, so the kite can never actually overlap the
     text no matter its own size. This replaces the earlier "kite
     overlapping the text" version - direct follow-up asked for the kite
     positioned on the right instead, matching the reference screenshot. */
  .games-text-row{
    display:flex; align-items:flex-end; flex-wrap:wrap;
    gap:28px;
  }
  .games-text-wrap{ position:relative; flex:1 1 320px; max-width:560px; padding-bottom:14px; }
  .games-text{
    opacity:0; transform:translateY(16px);
    transition:opacity .6s ease, transform .6s ease;
  }
  .games-text.revealed{ opacity:1; transform:translateY(0); }
  .games-text .section-headline{
    font-family:'Baloo 2',sans-serif; font-weight:700;
    font-size:clamp(28px, 4vw, 40px); line-height:1.2;
    color:var(--parent-teal); margin:0 0 12px;
  }
  .games-text .section-body{
    font-family:'Inter',sans-serif; font-weight:500; font-size:19px; line-height:1.6;
    color:var(--slate-600); margin:0; max-width:520px;
  }

  /* The kite - a real flex item (not absolutely positioned over the
     text), sitting to the text's right with a genuine gap, matching the
     reference picture. Doubled again per direct feedback (240px -> 480px).
     margin-bottom lifts its tail clear of the see-saw's raised end below. */
  .kite-stage{
    flex:0 0 auto;
    width:min(480px, 44vw); aspect-ratio:1/1;
    margin-bottom:120px;
    opacity:0; transform:translateY(-16px);
    transition:opacity .6s ease, transform .6s ease;
  }
  .kite-stage.revealed{ opacity:1; transform:translateY(0); }
  .kite-lottie{ width:100%; height:100%; display:block; }

  /* farm-land-wrap does NOT clip its own overflow (unlike farm-land-stage
     below) - it's the see-saw's real positioning context, and the see-saw
     is deliberately allowed to extend past the farmland strip's own box
     (standing prominently in front of it) rather than being cropped to
     it. */
  .farm-land-wrap{ position:relative; width:100%; margin-top:0; }

  /* The farmland stays put - no panning (direct feedback: it should not
     move left to right). It plays only its OWN animation, confirmed from
     its source: three green hills rising from flat (scale 14%) up to full
     height over ~1.7-2.5s. The JS below replays that rise on a slow loop
     (rise, hold, sink, rest) using only the asset's own keyframes, run in
     forward and reverse so there is no jarring snap back to flat. */
  .farm-land-stage{
    position:relative; z-index:0;
    width:100%; aspect-ratio:2960/700;
    opacity:0; transition:opacity .3s ease;
    overflow:hidden;
  }
  .farm-land-stage.revealed{ opacity:1; }
  .farm-land-lottie{ width:100%; height:100%; display:block; }

  /* The see-saw kids - placed to match the reference picture: standing ON
     the farmland, right of center (not the far edge). Sized and positioned
     as percentages of the land strip (the wrap is exactly the strip's
     size) so the composition scales identically at every viewport width
     instead of needing per-breakpoint pixel guesses. */
  .seesaw-stage{
    position:absolute; z-index:2;
    left:58%; bottom:12%;
    width:36%; aspect-ratio:800/619;
    opacity:0; transition:opacity .6s ease .15s;
  }
  .seesaw-stage.revealed{ opacity:1; }
  .seesaw-lottie{ width:100%; height:100%; display:block; }

  @media (max-width:760px){
    /* Real bug caught live earlier: flex-wrap alone let the text-wrap's
       own min-content width force a horizontal overflow instead of the
       kite wrapping to its own line. A column direction removes the
       ambiguity: text and kite simply stack, each free to use the full
       width. Raised from 640px to 760px now that the kite is doubled. */
    .games-scene{ padding:48px 0 0; }
    .games-scene-inner{ text-align:center; }
    .games-text-row{ flex-direction:column; align-items:center; }
    /* flex-basis is a HEIGHT once this row stacks into a column, so the
       desktop flex:1 1 320px was forcing 320px of height under ~230px of
       text; auto lets it size to its content. */
    .games-text-wrap{ max-width:100%; padding-bottom:0; flex:0 0 auto; }
    .games-text .section-body{ margin:0 auto; }
    .kite-stage{ width:min(300px, 72vw); margin-bottom:45px; }
    .seesaw-stage{ left:46%; bottom:10%; width:50%; }
  }

  /* ---------- Section 7 (final): "I'm a Learner!" card over the globe ----------
     The closing section of the page. Starts on Section 6's own ending tone
     (#eef9f0) and fades to a pale sky, the owl peeks over the top edge of
     the card (behind it), and the animated globe fills the full width at the
     very bottom of the page, edge to edge, with the card overlapping its top. */
  .finale{
    position:relative;
    background:linear-gradient(180deg, #eef9f0 0%, #e3f1fc 45%, #d3e8fa 100%);
    padding-top:56px;
    overflow:hidden;
  }
  .finale-inner{ position:relative; z-index:2; max-width:600px; margin:0 auto; padding:0 20px; }
  /* The owl artwork is drawn in a 512x512 canvas with a lot of empty margin,
     so the animation is cropped to its real painted area (254x208, see the
     JS) and sized here. The negative bottom margin tucks its lower part
     behind the card, so it reads as peeking over the top edge. The soft
     drop shadow lifts it off the sky like the other cartoon art on the page. */
  .finale-owl{
    position:relative; z-index:1; width:min(250px, 58vw); aspect-ratio:254/208;
    margin:0 auto -66px;
    filter:drop-shadow(0 12px 10px rgba(8,34,74,.28));
  }
  .finale-owl-lottie{ width:100%; height:100%; display:block; }

  /* Claymorphism, the same soft puffy language as the Learner screens: a
     bright rim light along the top, a darker inner shade along the bottom,
     big rounded corners, and layered soft shadows underneath. */
  .finale-card{
    position:relative; z-index:2; overflow:hidden;
    padding:50px 38px 36px; text-align:center; color:#f4f9fd;
    background:
      radial-gradient(90% 60% at 16% 0%, rgba(255,255,255,.16) 0%, rgba(255,255,255,0) 60%),
      linear-gradient(165deg, #1a4189 0%, #1d5a93 52%, #1a7a8c 100%);
    border-radius:40px;
    box-shadow:
      0 40px 64px -30px rgba(5,28,66,.75),
      0 14px 24px -12px rgba(5,28,66,.4),
      inset 0 5px 0 rgba(255,255,255,.28),
      inset 0 -18px 26px rgba(3,24,58,.42),
      inset 10px 0 18px -8px rgba(255,255,255,.14);
    opacity:0; transform:translateY(16px);
    transition:opacity .6s ease, transform .6s ease;
  }
  .finale-card.revealed{ opacity:1; transform:translateY(0); }
  /* Two soft clay "blobs" of light inside the card, plus a few twinkles. */
  .finale-card::before{
    content:''; position:absolute; top:-70px; right:-50px; width:210px; height:210px; border-radius:50%;
    background:radial-gradient(circle at 35% 35%, rgba(255,255,255,.14), rgba(255,255,255,0) 70%);
    pointer-events:none;
  }
  .finale-card::after{
    content:''; position:absolute; bottom:-90px; left:-60px; width:260px; height:260px; border-radius:50%;
    background:radial-gradient(circle at 60% 40%, rgba(120,238,214,.22), rgba(120,238,214,0) 70%);
    pointer-events:none;
  }
  .finale-card > :not(.finale-spark){ position:relative; z-index:1; }
  .finale-spark{ position:absolute; z-index:1; fill:#ffd98a; pointer-events:none; animation:finaleTwinkle 3.2s ease-in-out infinite; }
  .finale-spark.s1{ top:30px; left:11%; width:20px; height:20px; }
  .finale-spark.s2{ top:62px; right:10%; width:13px; height:13px; animation-delay:1.1s; }
  .finale-spark.s3{ top:22px; right:24%; width:9px; height:9px; animation-delay:2s; }
  @keyframes finaleTwinkle{ 0%,100%{ transform:scale(.7) rotate(0deg); opacity:.55; } 50%{ transform:scale(1.12) rotate(14deg); opacity:1; } }
  .finale-title{
    font-family:'Baloo 2',sans-serif; font-weight:700;
    font-size:clamp(32px, 4.6vw, 44px); line-height:1.15; margin:0 0 14px; color:#fff;
    text-shadow:0 3px 0 rgba(4,26,64,.38);
  }
  .finale-title .accent{ color:#ffb04a; text-shadow:0 3px 0 rgba(120,52,0,.45); }
  .finale-body{
    font-family:'Inter',sans-serif; font-weight:500; font-size:18px; line-height:1.6;
    color:#f4f9fd; margin:0 auto 28px; max-width:440px;
  }
  .finale-body span{ display:block; text-wrap:balance; }
  .finale-body span + span{ margin-top:6px; }
  /* The one frictionless path for a child: a plain, real link with no gate.
     A chunky pressable clay button: cream top, a solid warm "base" edge under
     it, and a round orange lock badge that hangs on its left. */
  .finale-pin{
    display:inline-flex; align-items:center; gap:14px;
    padding:12px 36px 12px 12px; border-radius:999px; text-decoration:none;
    background:linear-gradient(180deg, #ffffff 0%, #fff3df 100%);
    color:#b34d08;
    font-family:'Baloo 2',sans-serif; font-weight:700; font-size:22px; line-height:1;
    box-shadow:
      0 6px 0 #f2b56e,
      0 22px 28px -10px rgba(3,20,50,.7),
      inset 0 3px 0 #fff,
      inset 0 -8px 12px rgba(239,141,42,.28);
    transition:transform .18s ease, box-shadow .18s ease;
  }
  .finale-pin:hover{
    transform:translateY(-3px);
    box-shadow:
      0 9px 0 #f2b56e,
      0 28px 32px -10px rgba(3,20,50,.72),
      inset 0 3px 0 #fff,
      inset 0 -8px 12px rgba(239,141,42,.28);
  }
  .finale-pin:active{
    transform:translateY(5px);
    box-shadow:
      0 1px 0 #f2b56e,
      0 8px 14px -8px rgba(3,20,50,.6),
      inset 0 3px 0 #fff,
      inset 0 -8px 12px rgba(239,141,42,.28);
  }
  .finale-pin-badge{
    flex:none; width:44px; height:44px; border-radius:50%;
    display:grid; place-items:center;
    background:linear-gradient(180deg, #ffc36a 0%, #ef8d2a 100%);
    box-shadow:
      inset 0 3px 0 rgba(255,255,255,.55),
      inset 0 -5px 7px rgba(150,60,0,.35),
      0 5px 8px -3px rgba(150,60,0,.5);
  }
  .finale-pin-badge svg{ display:block; }
  /* No code yet? A quiet helper line under the button, pointing down to the
     Teacher/Parent/Admin cards below (id="roleCards") - the divider text,
     pills and footnote that used to live inside this card moved out into
     their own cards, each with its own real routes. */
  .finale-help{
    font-family:'Inter',sans-serif; font-weight:500; font-size:14.5px; color:#f4f9fd;
    margin:28px 0 0; padding-top:18px; border-top:2px solid rgba(255,255,255,.12);
  }
  .finale-help a{ color:#fff; font-weight:700; text-underline-offset:3px; }

  /* ---------- The headline: speaks to all four roles, not just the child
     who taps "Enter my PIN" - the section's real job is routing every kind
     of visitor (Learner, Teacher, Parent, Admin) to their own door. ---------- */
  .finale-head{ position:relative; z-index:2; text-align:center; max-width:720px; margin:0 auto 40px; padding:0 20px; }
  .finale-chip{
    display:inline-flex; align-items:center; gap:8px;
    padding:8px 18px; border-radius:999px;
    font-family:'Inter',sans-serif; font-weight:700; font-size:12.5px; letter-spacing:.05em; text-transform:uppercase;
    color:var(--blue-700);
    background:linear-gradient(180deg,#fff 0%,#eaf3fc 100%);
    box-shadow:0 4px 0 #c5d8ee, 0 12px 16px -8px rgba(15,60,110,.32), inset 0 2px 0 #fff;
  }
  .finale-heading{
    font-family:'Baloo 2',sans-serif; font-weight:800;
    font-size:clamp(30px, 4.6vw, 46px); line-height:1.15; margin:18px 0 12px; color:var(--navy-900);
  }
  .finale-heading .accent{ color:var(--owl-orange-600); }
  .finale-sub{ font-size:17.5px; line-height:1.6; font-weight:500; color:var(--slate-600); margin:0; }

  /* ---------- The three adult cards: Teacher, Parent, Admin - real hook
     copy pulled from what each role's dashboard actually does, and real
     routes (Get started -> registration, Log in -> the shared login form
     with ?role=). Admin has no "Get started": no self-registration, exactly
     one seeded Admin account, matching the rest of this app. ---------- */
  .finale-cards{
    position:relative; z-index:2; display:grid; grid-template-columns:repeat(3, 1fr);
    gap:24px; max-width:1080px; margin:56px auto 0; padding:0 20px;
  }
  .finale-role{
    position:relative; display:flex; flex-direction:column; align-items:center; text-align:center;
    padding:36px 26px 26px; border-radius:34px;
    background:linear-gradient(180deg,#ffffff 0%,#f1f7fd 100%);
    box-shadow:
      0 30px 50px -28px rgba(6,32,72,.5),
      0 10px 18px -10px rgba(6,32,72,.24),
      inset 0 4px 0 #fff,
      inset 0 -10px 18px rgba(150,180,215,.26);
    --c1:#4aa8f5; --c2:var(--blue-500); --dark:#0f4f91; --tint:#e6f0fb; --base:#0b3d75; --accent:var(--blue-500);
  }
  .finale-role.parent{ --c1:#43dcb8; --c2:var(--parent-teal); --dark:#0d6653; --tint:#e2f5f0; --base:#0a4d3f; --accent:#1a8f76; }
  .finale-role.admin{ --c1:#6b7f92; --c2:#243343; --dark:#243343; --tint:#e8edf2; --base:#0e1620; --accent:#4a5b6b; }
  .finale-role-badge{
    width:60px; height:60px; border-radius:50%; display:grid; place-items:center; color:#fff; margin-bottom:14px;
    background:linear-gradient(180deg,var(--c1),var(--c2));
    box-shadow:inset 0 4px 0 rgba(255,255,255,.5), inset 0 -6px 9px rgba(0,0,0,.24), 0 10px 14px -8px var(--base);
  }
  .finale-role h3{ font-family:'Baloo 2',sans-serif; font-weight:700; font-size:25px; line-height:1.1; margin:0 0 9px; color:var(--navy-900); }
  .finale-role h3 .accent{ color:var(--accent); }
  .finale-role .hook{ margin:0 0 15px; font-size:15.5px; line-height:1.55; font-weight:500; color:var(--slate-600); }
  .finale-chips{ display:flex; flex-wrap:wrap; justify-content:center; gap:8px; margin:0 0 20px; padding:0; list-style:none; }
  .finale-chips li{
    display:inline-flex; align-items:center; padding:6px 12px; border-radius:999px;
    font-size:12.5px; font-weight:700; color:var(--dark); background:var(--tint);
    box-shadow:inset 0 2px 0 rgba(255,255,255,.9), inset 0 -2px 4px rgba(0,0,0,.06);
  }
  .finale-role .spacer{ flex:1; }
  .finale-cta{
    display:flex; align-items:center; justify-content:center; width:100%; padding:14px 18px; border-radius:999px; text-decoration:none; color:#fff;
    font-family:'Baloo 2',sans-serif; font-weight:700; font-size:18px; line-height:1;
    background:linear-gradient(180deg,var(--c1) 0%,var(--c2) 100%);
    box-shadow:0 5px 0 var(--base), 0 16px 20px -10px var(--base), inset 0 3px 0 rgba(255,255,255,.4), inset 0 -6px 10px rgba(0,0,0,.16);
    transition:transform .18s ease, box-shadow .18s ease;
  }
  .finale-cta:hover{ transform:translateY(-2px); }
  .finale-cta:active{ transform:translateY(3px); box-shadow:0 1px 0 var(--base), 0 6px 10px -6px var(--base), inset 0 3px 0 rgba(255,255,255,.4), inset 0 -6px 10px rgba(0,0,0,.16); }
  .finale-signin{ margin:14px 0 0; font-size:13.5px; font-weight:500; color:var(--slate-600); }
  .finale-signin a{ color:var(--dark); font-weight:700; text-underline-offset:3px; }
  @media (max-width:980px){
    .finale-cards{ grid-template-columns:1fr; max-width:420px; }
  }

  .finale-pin:focus-visible, .finale-help a:focus-visible, .finale-cta:focus-visible, .finale-signin a:focus-visible, .finale-top-btn:focus-visible{ outline:3px solid #ffb04a; outline-offset:4px; }
  /* The globe: 1000x500 artwork whose top ~40% is transparent sky, so the
     card can overlap the arc. Full width, zero margin, drawn with a "slice"
     fit so a phone (where the natural height would be tiny) gets a taller
     box and the artwork's sides are cropped evenly instead of leaving gaps.
     The negative top margin is 30% of the width: 20% for the empty sky above
     the arc, plus 10% for how far the card overlaps the globe, so the
     proportions stay the same at every width (a fixed pixel overlap left
     too little globe showing on a tablet). */
  .finale-globe{
    position:relative; z-index:1; width:100%;
    aspect-ratio:2/1; min-height:280px;
    margin-top:-30%;
  }
  .finale-globe-lottie{ position:absolute; inset:0; }

  /* ---------- A real close to the page: brand, tagline, and a way back to
     the top - so the scroll ends somewhere deliberate instead of trailing
     off after the globe. ---------- */
  .finale-end{
    position:relative; z-index:3; color:#fff; text-align:center;
    background:linear-gradient(180deg, var(--blue-600) 0%, var(--blue-700) 100%);
    box-shadow:inset 0 4px 0 rgba(255,255,255,.14);
    padding:38px 20px 34px;
  }
  .finale-end-inner{ max-width:1080px; margin:0 auto; display:flex; align-items:center; justify-content:space-between; gap:22px; flex-wrap:wrap; }
  .finale-brand{ display:flex; align-items:center; gap:11px; }
  .finale-brand img{ width:42px; height:42px; border-radius:13px; display:block; box-shadow:0 4px 0 rgba(0,0,0,.22), inset 0 2px 0 rgba(255,255,255,.3); }
  .finale-brand b{ font-family:'Baloo 2',sans-serif; font-weight:700; font-size:21px; }
  .finale-brand b span{ color:#ffb04a; }
  .finale-end-line{ flex:1 1 300px; }
  .finale-end-line strong{ display:block; font-family:'Baloo 2',sans-serif; font-weight:700; font-size:clamp(18px,2.4vw,23px); line-height:1.3; }
  .finale-end-line small{ display:block; margin-top:4px; font-size:14px; font-weight:500; color:#dbe9f9; }
  .finale-top-btn{
    display:inline-flex; align-items:center; gap:8px; padding:10px 18px; border-radius:999px; text-decoration:none;
    font-family:'Baloo 2',sans-serif; font-weight:700; font-size:15px; color:var(--blue-700);
    background:linear-gradient(180deg,#fff,#e8f1fb);
    box-shadow:0 4px 0 #a9c3e0, 0 10px 14px -8px rgba(0,0,0,.4), inset 0 2px 0 #fff;
    transition:transform .18s ease;
  }
  .finale-top-btn:hover{ transform:translateY(-2px); }

  @media (max-width:640px){
    .finale-head{ margin-bottom:30px; }
    .finale-heading{ font-size:clamp(26px,7vw,34px); }
    .finale-owl{ margin-bottom:-50px; }
    .finale-card{ padding:44px 22px 30px; border-radius:34px; }
    .finale-body{ font-size:17px; }
    .finale-pin{ font-size:21px; padding-right:30px; }
    .finale-cards{ margin-top:40px; }
    .finale-globe{ margin-top:-130px; }
    .finale-end-inner{ justify-content:center; text-align:center; }
  }

  /* ---------- Parent gate: a small "grownups only" math question ----------
     Same clay language as the card above: puffy white dialog, an inset
     answer well, and chunky pressable buttons. */
  .gate{ position:fixed; inset:0; z-index:1000; display:flex; align-items:center; justify-content:center; padding:16px; }
  .gate[hidden]{ display:none; }
  .gate-backdrop{ position:absolute; inset:0; background:rgba(8,24,48,.58); -webkit-backdrop-filter:blur(3px); backdrop-filter:blur(3px); }
  .gate-dialog{
    position:relative; width:min(390px, 100%); box-sizing:border-box;
    padding:34px 28px 28px; text-align:center;
    background:linear-gradient(180deg, #ffffff 0%, #f1f7fd 100%);
    border-radius:34px;
    box-shadow:
      0 40px 70px -26px rgba(5,24,56,.65),
      0 10px 20px -10px rgba(5,24,56,.3),
      inset 0 4px 0 #fff,
      inset 0 -10px 18px rgba(150,180,215,.28);
    animation:gatePop .28s cubic-bezier(.22,.9,.3,1.1);
  }
  .gate-dialog:focus{ outline:none; }
  .gate-dialog.shake{ animation:gateShake .4s ease; }
  @keyframes gatePop{ from{ opacity:0; transform:translateY(14px) scale(.96); } to{ opacity:1; transform:none; } }
  @keyframes gateShake{ 0%,100%{ transform:translateX(0); } 20%{ transform:translateX(-9px); } 40%{ transform:translateX(8px); } 60%{ transform:translateX(-6px); } 80%{ transform:translateX(4px); } }
  .gate-badge{
    width:58px; height:58px; margin:0 auto 12px; border-radius:50%;
    display:grid; place-items:center;
    background:linear-gradient(180deg, #ffc36a 0%, #ef8d2a 100%);
    box-shadow:
      inset 0 3px 0 rgba(255,255,255,.55),
      inset 0 -5px 8px rgba(150,60,0,.35),
      0 9px 14px -6px rgba(150,60,0,.5);
  }
  .gate-badge svg{ display:block; }
  .gate-title{ font-family:'Baloo 2',sans-serif; font-weight:700; font-size:27px; line-height:1.2; color:var(--navy-900); margin:0 0 4px; }
  .gate-hint{ font-family:'Inter',sans-serif; font-weight:500; font-size:15px; color:var(--slate-600); margin:0; }
  .gate-question{ display:block; font-family:'Baloo 2',sans-serif; font-weight:700; font-size:38px; line-height:1.1; color:var(--blue-700); margin:20px 0 14px; }
  .gate-input{
    width:100%; box-sizing:border-box; text-align:center; padding:14px 14px;
    font-family:'Baloo 2',sans-serif; font-weight:700; font-size:30px; color:var(--navy-900);
    border:none; border-radius:22px; background:#eaf2fb;
    box-shadow:inset 0 5px 10px rgba(30,70,120,.2), inset 0 -2px 0 #fff;
  }
  .gate-input:focus{ outline:none; box-shadow:inset 0 5px 10px rgba(30,70,120,.2), inset 0 -2px 0 #fff, 0 0 0 4px rgba(28,126,214,.3); }
  .gate-error{ min-height:22px; margin:12px 0 0; font-family:'Inter',sans-serif; font-weight:600; font-size:14.5px; color:#b42318; }
  .gate-actions{ display:flex; gap:12px; margin-top:10px; padding-bottom:5px; }
  .gate-actions button{
    flex:1; padding:14px 10px; border-radius:20px; border:none; cursor:pointer;
    font-family:'Baloo 2',sans-serif; font-weight:700; font-size:18px; line-height:1;
    transition:transform .16s ease, box-shadow .16s ease;
  }
  .gate-actions button:active{ transform:translateY(4px); }
  .gate-cancel{
    color:var(--slate-600); background:linear-gradient(180deg, #ffffff 0%, #eaf2fb 100%);
    box-shadow:0 5px 0 #c4d3e4, 0 12px 14px -8px rgba(30,70,120,.4), inset 0 2px 0 #fff;
  }
  .gate-cancel:active{ box-shadow:0 1px 0 #c4d3e4, 0 4px 8px -6px rgba(30,70,120,.4), inset 0 2px 0 #fff; }
  .gate-ok{
    color:#fff; background:linear-gradient(180deg, #3d95ea 0%, var(--blue-600) 100%);
    box-shadow:0 5px 0 #0a3d73, 0 14px 18px -8px rgba(15,95,174,.6), inset 0 2px 0 rgba(255,255,255,.35), inset 0 -4px 8px rgba(3,30,70,.25);
  }
  .gate-ok:hover{ filter:brightness(1.06); }
  .gate-ok:active{ box-shadow:0 1px 0 #0a3d73, 0 4px 8px -6px rgba(15,95,174,.6), inset 0 2px 0 rgba(255,255,255,.35), inset 0 -4px 8px rgba(3,30,70,.25); }

  a:focus-visible, button:focus-visible{ outline:2px solid var(--blue-500); outline-offset:2px; }

  @media (prefers-reduced-motion: reduce){
    html{ scroll-behavior:auto; }
    .reading-text{ opacity:1; transform:none; transition:none; }
    .learner-text{ opacity:1; transform:none; transition:none; }
    .plane-stage{ animation:none; }
    .stay-text{ opacity:1; transform:none; transition:none; }
    .duo-stage{ opacity:1; transition:none; }
    .school-text{ opacity:1; transition:none; }
    .city-banner-stage{ opacity:1; transition:none; }
    .go-to-school-stage{ opacity:1; transition:none; }
    .games-text{ opacity:1; transform:none; transition:none; }
    .kite-stage{ opacity:1; transform:none; transition:none; }
    .farm-land-stage{ opacity:1; transition:none; }
    .seesaw-stage{ opacity:1; transition:none; }
    .finale-card{ opacity:1; transform:none; transition:none; }
    .finale-pin, .finale-cta, .finale-top-btn, .gate-actions button{ transition:none; }
    .finale-spark{ animation:none; opacity:.9; transform:none; }
    .gate-dialog, .gate-dialog.shake{ animation:none; }
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
        <path d="M0,46 C 180,10 340,78 520,50 C 700,22 860,72 1040,48 C 1220,24 1320,60 1440,40 L1440,90 L0,90 Z" fill="var(--section-tint)"/>
      </svg>
    </div>
  </section>

  <section class="reading-scene" id="readingScene">
    <div class="reading-scene-inner">
      <div class="rabbit-stage">
        <!-- Real Lottie character animation (jsdelivr/lottie-web@5.12.2,
             already loaded above for the hero owl - no new script tag
             needed here). The file already loops its own 0.5s gesture 4
             times to fill its native 2s duration, so this plays that full
             native range rather than trimming a sub-segment - trimming
             would work against how it was actually authored. -->
        <div id="rabbitLottie" class="rabbit-lottie" role="img" aria-label="A rabbit character"></div>
      </div>
      <div id="readingText" class="reading-text">
        <h2 class="section-headline">Real reading, <span class="section-accent">actually fun</span></h2>
        <p class="section-body">TaraBasa listens while a child reads a real story out loud, checking every word as they go, right there in the moment.</p>
      </div>
    </div>

    <div class="sunrise-stage">
      <!-- Real Lottie "breathe in, breathe out" sunrise animation, a closing
           visual for this section. Already a complete, self-returning loop
           on its own (every layer's own keyframes return to their starting
           value by the end), so it plays its full native range too. -->
      <div id="sunriseLottie" class="sunrise-lottie" role="img" aria-label="A calm sunrise through clouds"></div>
    </div>
  </section>

  <section class="learner-scene" id="learnerScene">
    <div class="learner-scene-inner">
      <div class="learner-text-wrap">
        <!-- Small decorative Lottie plane orbiting near the text via a CSS
             keyframe loop layered on top of its own baked-in motion (this
             asset is a raster frame-sequence with no separate position
             data to sync a path to - see CLAUDE.md). -->
        <div class="plane-stage">
          <div id="planeLottie" class="plane-lottie" role="img" aria-label="A small paper plane"></div>
        </div>
        <div id="learnerText" class="learner-text">
          <h2 class="section-headline">Personalized for every learner</h2>
          <p class="section-body">TaraBasa pays attention to how the last story went and quietly adjusts what comes next, so a child keeps climbing at their own pace instead of getting stuck or bored.</p>
        </div>
      </div>

      <div class="hero-flying-stage">
        <!-- Real Lottie flying-superhero-boy animation, a clean 3s loop
             confirmed from its own keyframe data - every layer returns to
             its frame-0 value by frame 90, no trimming needed. -->
        <div id="heroFlyingLottie" class="hero-flying-lottie" role="img" aria-label="A boy flying like a superhero"></div>
      </div>
    </div>
  </section>

  <section class="stay-scene" id="stayScene">
    <div class="stay-scene-inner">
      <div class="stay-text-wrap">
        <div id="stayText" class="stay-text">
          <h2 class="section-headline">Stay motivated</h2>
          <p class="section-body">Streaks, points and a friendly nudge from Tara keep a child looking forward to reading again tomorrow, not dreading it.</p>
        </div>
      </div>

      <div id="duoStage" class="duo-stage">
        <!-- Real Lottie sword-bird animation ("Duo") - a genuine vector
             rig, not a raster sequence. Body's own position keyframes move
             it from x:1408 (outside the 1080-wide canvas) to x:502 between
             frame 0 and ~18, so the "flies in from the right" motion is
             already baked into the asset itself - no extra CSS translation
             needed here. Sword_Blade's rotation peaks at 14.86deg at frame
             19 (settling to 10.88deg at frame 20), and Body's own rotation
             independently peaks at 15deg at that same frame 19 - two
             unrelated layers agreeing on frame 19 is what makes that the
             real, verified "sword has arrived" moment (19/24s = 0.79s at
             this asset's native 24fps), not a guess. Played on a repeating
             loop driven by the state machine below, not autoplay - this is
             the one Lottie instance on this page that's reused every cycle
             instead of loaded once and left alone. -->
        <div class="duo-flip">
          <div id="duoLottie" class="duo-lottie" role="img" aria-label="Tara the owl arriving with a sword, pointing toward the text"></div>
        </div>
      </div>
    </div>
  </section>

  <section class="school-scene" id="schoolScene">
    <div class="school-scene-inner">
      <div id="schoolText" class="school-text">
        <h2 class="section-headline">Backed by real teachers</h2>
        <p class="section-body">Every activity a child sees is checked by an actual teacher before it goes live, and every real session sends an update straight to their teacher and parent.</p>
      </div>
    </div>

    <div class="school-banner-wrap">
      <!-- Real Lottie city-skyline scene (plane, clouds, buildings, trees) -
           originally clipped to a small 345x59 rounded pill by 6 alpha-matte
           mask layers (one per content layer, all sharing the same
           capsule shape - confirmed from the source file directly). Those
           mask layers, and the matte flag on each layer they clipped, were
           removed before self-hosting this copy - no position/color/timing
           data was touched, only the clipping relationship - so this now
           renders its full natural rectangular frame instead of a rounded
           badge, scaled up as a whole unit to span the section. -->
      <div id="cityBannerStage" class="city-banner-stage">
        <div id="cityBannerLottie" class="city-banner-lottie" role="img" aria-label="A city skyline with a plane flying past clouds"></div>
      </div>

      <!-- Real Lottie walking-boy character - a genuine 19-layer rig (body,
           limbs, bag, face, hair, etc.) inside a single precomposition
           layer, confirmed from the source file's own asset structure, not
           assumed from the root composition's single top-level layer.
           Every animated property returns to its exact frame-0 value by
           the final frame (confirmed layer by layer), so this plays its
           full native walking-cycle loop with no trimming. -->
      <div id="goToSchoolStage" class="go-to-school-stage">
        <div id="goToSchoolLottie" class="go-to-school-lottie" role="img" aria-label="A boy walking to school with a backpack"></div>
      </div>
    </div>
  </section>

  <section class="games-scene" id="gamesScene">
    <div class="games-scene-inner">
      <div class="games-text-row">
        <div class="games-text-wrap">
          <div id="gamesText" class="games-text">
            <h2 class="section-headline">Learning that feels like play</h2>
            <p class="section-body">Word Builder and Letter Match turn practice into a real game, built around each child's own tricky words, no points or pressure attached.</p>
          </div>
        </div>

        <!-- Real Lottie kite - a genuine flat 7-layer rig (a colorful
             diamond body plus 3 tail ribbons), confirmed from source: zero
             precomps, zero stray solids. Every animated position/rotation
             property returns to its exact frame-0 value by the final
             frame, so this plays its full native ~2s loop, no trimming.
             A real flex item next to the text (not absolutely positioned
             over it), so it sits to the text's right with a genuine gap
             that flex itself guarantees. -->
        <div id="kiteStage" class="kite-stage">
          <div id="kiteLottie" class="kite-lottie" role="img" aria-label="A colorful kite flying in the sky"></div>
        </div>
      </div>
    </div>

    <div class="farm-land-wrap">
      <!-- Real Lottie aerial farmland - confirmed from source to be a
           genuinely flat single shape layer (refId:null, zero assets),
           not a precomp hiding more layers the way GO_TO_SCHOOL's root
           layer did. Its own layer transform is fully static - no baked
           scrolling - but a few nested elements play a real one-shot
           "grow from squashed to full size" animation over their first
           ~2.5s (the three green hills rising). The strip itself stays
           put - no panning; the script below plays that rise on reveal
           and then repeats it smoothly (forward, hold, reverse, rest)
           using only the asset's own keyframes. -->
      <div id="farmLandStage" class="farm-land-stage">
        <div id="farmLandLottie" class="farm-land-lottie" role="img" aria-label="An aerial view of farmland"></div>
      </div>

      <!-- Real Lottie see-saw scene - a genuine flat 42-layer rig (two
           full character rigs plus the see-saw itself), confirmed zero
           precomps and zero stray solids. Every one of its 168 animated
           transform properties returns to its exact frame-0 value by the
           final frame, confirmed layer by layer, so this plays its full
           native ~2.3s rocking loop. A sibling of farm-land-stage (not
           nested inside it), so it can stand prominently in front of the
           strip without being clipped to it. -->
      <div id="seesawStage" class="seesaw-stage">
        <div id="seesawLottie" class="seesaw-lottie" role="img" aria-label="Two children playing on a see-saw"></div>
      </div>
    </div>
  </section>

  <section class="finale" id="finaleScene" aria-labelledby="finaleTitle">
    <div class="finale-head">
      <span class="finale-chip">Choose your path</span>
      <h2 class="finale-heading">Four roles. One goal: a child who <span class="accent">loves reading.</span></h2>
      <p class="finale-sub">Whether you are reading, teaching, parenting, or keeping things running, your door is right here.</p>
    </div>

    <div class="finale-inner">
      <!-- The owl mascot peeking over the top of the card (drawn behind it).
           Decorative, so hidden from assistive tech. -->
      <div class="finale-owl" aria-hidden="true">
        <div id="finaleOwl" class="finale-owl-lottie"></div>
      </div>

      <div id="finaleCard" class="finale-card">
        <!-- Decorative twinkles (four-point stars), hidden from assistive tech. -->
        <svg class="finale-spark s1" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 0L14.6 9.4L24 12L14.6 14.6L12 24L9.4 14.6L0 12L9.4 9.4Z"/></svg>
        <svg class="finale-spark s2" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 0L14.6 9.4L24 12L14.6 14.6L12 24L9.4 14.6L0 12L9.4 9.4Z"/></svg>
        <svg class="finale-spark s3" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 0L14.6 9.4L24 12L14.6 14.6L12 24L9.4 14.6L0 12L9.4 9.4Z"/></svg>

        <h2 id="finaleTitle" class="finale-title">I'm a <span class="accent">Learner!</span></h2>
        <p class="finale-body"><span>Read out loud, play games, and earn badges.</span> <span>Just your own code and a secret PIN, no email or password needed.</span></p>

        <!-- The child's own path: a plain real link, deliberately NOT gated. -->
        <a class="finale-pin" href="{{ route('learner.login') }}">
          <span class="finale-pin-badge" aria-hidden="true">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M8 11V8a4 4 0 0 1 8 0v3" stroke="#fff" stroke-width="2.6" stroke-linecap="round"/><rect x="5" y="11" width="14" height="9.5" rx="2.6" fill="#fff"/><circle cx="12" cy="15.6" r="1.6" fill="#dd7014"/></svg>
          </span>
          <span>Enter my PIN</span>
        </a>

        <p class="finale-help">No code yet? <a href="#roleCards">Ask your parent</a></p>
      </div>
    </div>

    <!-- The three adult roles, each with its own real hook and its own real
         routes: Get started (registration) and Log in are two separate
         gated links now, instead of one shared pill each. Admin has no
         registration route - matches the rest of the app (exactly one
         seeded Admin account, no self-registration). Each link is real (so
         it works even with scripts off); data-adult-gate is what the
         parent-gate script below listens for, so one gate covers all five. -->
    <div class="finale-cards" id="roleCards">
      <article class="finale-role teacher">
        <div class="finale-role-badge" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none"><path d="M12 3L2 8l10 5 8-4.2V15h1V8L12 3z" fill="currentColor"/><path d="M6 11.5V16c0 1.7 2.7 3 6 3s6-1.3 6-3v-4.5l-6 3.2-6-3.2z" fill="currentColor" opacity="0.85"/></svg></div>
        <h3>I'm a <span class="accent">Teacher</span></h3>
        <p class="hook">Turn any topic into reading activities in minutes, review each one, and see how every learner is doing.</p>
        <ul class="finale-chips"><li>You approve every activity</li><li>Class insights</li></ul>
        <div class="spacer"></div>
        <a class="finale-cta" data-adult-gate href="{{ route('register.teacher') }}">Get started</a>
        <p class="finale-signin">Already have an account? <a data-adult-gate href="{{ route('login', ['role' => 'teacher']) }}">Log in</a></p>
      </article>

      <article class="finale-role parent">
        <div class="finale-role-badge" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none"><circle cx="9" cy="8" r="3" fill="currentColor"/><circle cx="17" cy="9" r="2.4" fill="currentColor" opacity="0.85"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6" fill="currentColor"/><path d="M14 20c.3-2.4 1.8-4.3 3.8-5.1 2 .9 3.2 2.8 3.2 5.1" fill="currentColor" opacity="0.85"/></svg></div>
        <h3>I'm a <span class="accent">Parent</span></h3>
        <p class="hook">Watch your child's reading grow, and get a gentle alert when they need a little extra help.</p>
        <ul class="finale-chips"><li>Progress at a glance</li><li>Alerts that matter</li></ul>
        <div class="spacer"></div>
        <a class="finale-cta" data-adult-gate href="{{ route('register.parent') }}">Get started</a>
        <p class="finale-signin">Already have an account? <a data-adult-gate href="{{ route('login', ['role' => 'parent']) }}">Log in</a></p>
      </article>

      <article class="finale-role admin">
        <div class="finale-role-badge" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none"><path d="M12 2l7 3v6c0 5-3 8.5-7 11-4-2.5-7-6-7-11V5l7-3z" fill="currentColor"/></svg></div>
        <h3>I'm an <span class="accent">Admin</span></h3>
        <p class="hook">Keep every classroom trusted. Review each teacher and switch accounts on or off in a few taps.</p>
        <ul class="finale-chips"><li>Approve teachers</li><li>Manage accounts</li></ul>
        <div class="spacer"></div>
        <a class="finale-cta" data-adult-gate href="{{ route('login', ['role' => 'admin']) }}">Admin log in</a>
        <p class="finale-signin">Admin accounts are set up by the team.</p>
      </article>
    </div>

    <!-- The animated globe (1000x500, transparent sky above the arc): full
         width, no padding or max-width. -->
    <div class="finale-globe" aria-hidden="true">
      <div id="finaleGlobe" class="finale-globe-lottie"></div>
    </div>
  </section>

  <footer class="finale-end">
    <div class="finale-end-inner">
      <div class="finale-brand"><img src="{{ asset('images/logo.png') }}" alt="TaraBasa AI logo"><b>TaraBasa<span>AI</span></b></div>
      <div class="finale-end-line">
        <strong>Every child deserves to love reading.</strong>
        <small>AI powered, teacher verified reading support for Grade 1 to 3 Filipino learners.</small>
      </div>
      <a class="finale-top-btn" href="#siteNav"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>Back to top</a>
    </div>
  </footer>

  <!-- Parent gate: shown before any adult link in the section above. A quick
       multiplication question a young child can't answer but a grown-up can
       in a few seconds. It's friction to stop an accidental tap, not
       security (the links are real, and a determined child could still open
       one in a new tab or use the header's Log in). -->
  <div id="parentGate" class="gate" hidden>
    <div class="gate-backdrop" data-gate-close></div>
    <div id="gateDialog" class="gate-dialog" role="dialog" aria-modal="true" aria-labelledby="gateTitle" aria-describedby="gateHint" tabindex="-1">
      <div class="gate-badge" aria-hidden="true">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none"><path d="M8 11V8a4 4 0 0 1 8 0v3" stroke="#fff" stroke-width="2.6" stroke-linecap="round"/><rect x="5" y="11" width="14" height="9.5" rx="2.6" fill="#fff"/><circle cx="12" cy="15.6" r="1.6" fill="#dd7014"/></svg>
      </div>
      <h3 id="gateTitle" class="gate-title">Grownups only</h3>
      <p id="gateHint" class="gate-hint">Answer this to continue.</p>
      <form id="gateForm" novalidate>
        <label id="gateQuestion" class="gate-question" for="gateAnswer">What is 14 × 6?</label>
        <input id="gateAnswer" class="gate-input" type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="off" maxlength="4" aria-describedby="gateError">
        <p id="gateError" class="gate-error" role="alert"></p>
        <div class="gate-actions">
          <button type="button" class="gate-cancel" data-gate-close>Cancel</button>
          <button type="submit" class="gate-ok">Continue</button>
        </div>
      </form>
    </div>
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

  // Section 2: rabbit + sunrise. Both files are already complete,
  // self-looping animations by design (confirmed from their own keyframe
  // data), so both play their full native range rather than a computed
  // sub-segment. Loading/playing is gated behind the section actually
  // scrolling into view - the same IntersectionObserver pattern already
  // used elsewhere in this app (the My Growth chart's liquid-fill reveal),
  // not a new dependency, since this section sits below the fold.
  const readingScene = document.getElementById('readingScene');
  const readingText = document.getElementById('readingText');
  if (readingScene && window.lottie) {
    let readingSceneActivated = false;
    function activateReadingScene2() {
      if (readingSceneActivated) return;
      readingSceneActivated = true;

      const rabbitAnim = lottie.loadAnimation({
        container: document.getElementById('rabbitLottie'),
        renderer: 'svg',
        loop: !prefersReducedMotion,
        autoplay: !prefersReducedMotion,
        path: '{{ asset("animations/tarabasa-rabbit.json") }}'
      });
      const sunriseAnim = lottie.loadAnimation({
        container: document.getElementById('sunriseLottie'),
        renderer: 'svg',
        loop: !prefersReducedMotion,
        autoplay: !prefersReducedMotion,
        path: '{{ asset("animations/tarabasa-sunrise.json") }}'
      });

      if (prefersReducedMotion) {
        rabbitAnim.goToAndStop(15, true);
        sunriseAnim.goToAndStop(0, true);
        readingText.classList.add('revealed');
        return;
      }

      // The rabbit's real settle frame - verified from its own keyframe
      // data: the body outline, both hand shapes/positions, the mouth, and
      // the moustache all reach their exact frame-0 value again by frame 15
      // of its 30fps timeline - is 15/30 = 0.5s. The text reveals right as
      // that first gesture completes, not on an arbitrary delay.
      setTimeout(() => readingText.classList.add('revealed'), 500);
    }

    if ('IntersectionObserver' in window) {
      const readingSceneObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            activateReadingScene2();
            readingSceneObserver.disconnect();
          }
        });
      }, { threshold: 0.25 });
      readingSceneObserver.observe(readingScene);
    } else {
      activateReadingScene2();
    }
  }

  // Section 3: personalized learning (text + flying-hero-boy, plane
  // accent). Hero-flying is a clean 3s loop (every layer returns to its
  // frame-0 value by frame 90, confirmed from its own keyframe data); the
  // plane is a 270-frame raster flipbook (confirmed from its raw source -
  // each frame is its own image layer with a fixed [0,0] transform, no
  // separate position keyframes to trim or sync). Both play their full
  // native range. Loading/playing is gated behind the section scrolling
  // into view, matching Section 2's own pattern.
  const learnerScene = document.getElementById('learnerScene');
  const learnerText = document.getElementById('learnerText');
  if (learnerScene && window.lottie) {
    let learnerSceneActivated = false;
    function activateLearnerScene() {
      if (learnerSceneActivated) return;
      learnerSceneActivated = true;

      const heroFlyingAnim = lottie.loadAnimation({
        container: document.getElementById('heroFlyingLottie'),
        renderer: 'svg',
        loop: !prefersReducedMotion,
        autoplay: !prefersReducedMotion,
        path: '{{ asset("animations/tarabasa-hero-flying.json") }}'
      });
      const planeAnim = lottie.loadAnimation({
        container: document.getElementById('planeLottie'),
        renderer: 'svg',
        loop: !prefersReducedMotion,
        autoplay: !prefersReducedMotion,
        path: '{{ asset("animations/tarabasa-plane.json") }}'
      });

      if (prefersReducedMotion) {
        heroFlyingAnim.goToAndStop(0, true);
        planeAnim.goToAndStop(0, true);
        learnerText.classList.add('revealed');
        return;
      }

      setTimeout(() => learnerText.classList.add('revealed'), 300);
    }

    if ('IntersectionObserver' in window) {
      const learnerSceneObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            activateLearnerScene();
            learnerSceneObserver.disconnect();
          }
        });
      }, { threshold: 0.25 });
      learnerSceneObserver.observe(learnerScene);
    } else {
      activateLearnerScene();
    }
  }

  // ---------- Section 4: stay motivated (looping text + sword-bird chase) ----------
  // Unlike every earlier section, this one repeats forever while it's on
  // screen - so, unlike Sections 2-3's "activate once via IntersectionObserver
  // and disconnect" pattern, this observer stays connected for the section's
  // whole lifetime: entering starts the loop, leaving stops it (clears the
  // pending timer and pauses the Lottie) so the timer chain and the
  // animation's own rendering only ever run while actually visible. The
  // Lottie instance itself is created once and reused every cycle via
  // goToAndPlay/goToAndStop, never destroyed and recreated, so nothing leaks
  // over an extended session. Every phase below only ever toggles
  // opacity/transform-driven classes, never a layout-affecting property.
  const stayScene = document.getElementById('stayScene');
  const stayText = document.getElementById('stayText');
  const duoStage = document.getElementById('duoStage');

  if (stayScene && stayText && duoStage && window.lottie) {
    let duoAnim = null;
    let stayTimer = null;
    let stayStepIndex = 0;
    let staySteps = null;
    let stayLoopRunning = false;

    function ensureDuoAnim() {
      if (!duoAnim) {
        duoAnim = lottie.loadAnimation({
          container: document.getElementById('duoLottie'),
          renderer: 'svg',
          loop: false,
          autoplay: false,
          path: '{{ asset("animations/tarabasa-owl.json") }}'
        });
      }
      return duoAnim;
    }

    function scheduleStayStep() {
      if (!stayLoopRunning) return;
      const step = staySteps[stayStepIndex];
      step.action();
      stayTimer = setTimeout(() => {
        stayStepIndex = (stayStepIndex + 1) % staySteps.length;
        scheduleStayStep();
      }, step.delay);
    }

    function startStayLoop() {
      if (stayLoopRunning) return;
      stayLoopRunning = true;
      ensureDuoAnim();

      if (!staySteps) {
        staySteps = [
          // Phase 1: text runs in from the left to its right-anchored
          // resting spot (.65s transition) and fully stops there.
          { delay: 800, action: () => { stayText.classList.add('revealed'); } },
          // Phase 2 (the 800ms delay above = text's own .65s run + a clean
          // 150ms gap after it has genuinely stopped, so the two motions
          // never overlap/race) -> Phase 3: owl becomes visible and chases
          // in from further left (.83s transform transition on .duo-stage),
          // playing the Lottie from frame 0 at the same moment - the real,
          // verified sword-arrival rotation (frame 19-20, 0.79s-0.83s at
          // this asset's native 24fps) lands right as the chase transform
          // finishes, so the owl visually catches up to the text right when
          // the sword swings.
          { delay: 830, action: () => { duoStage.classList.add('visible'); duoAnim.goToAndPlay(0, true); } },
          // Phase 4: arrived / hold - sword has caught up to the text, held
          // for a beat so the moment actually reads before fading out.
          { delay: 650, action: () => {} },
          // Phase 5: synchronized fade - text and owl fade out together in
          // one action, not staggered like the first version of this section.
          { delay: 500, action: () => {
              stayText.classList.remove('revealed');
              duoStage.classList.remove('visible');
            } },
          // Phase 6: reset gap - both are already fully transparent by now,
          // so their positions can settle back off-screen unseen before the
          // Lottie resets and the loop repeats from Phase 1. Long enough
          // (450ms) that the .83s owl transform-out has finished by the time
          // Phase 1 fires again (500ms fade + 450ms gap = 950ms > 830ms).
          { delay: 450, action: () => { duoAnim.goToAndStop(0, true); } }
        ];
      }

      stayStepIndex = 0;
      scheduleStayStep();
    }

    function stopStayLoop() {
      if (!stayLoopRunning) return;
      stayLoopRunning = false;
      if (stayTimer) { clearTimeout(stayTimer); stayTimer = null; }
      if (duoAnim) duoAnim.pause();
      // Reset visual state so re-entering the section always restarts
      // cleanly from Phase 1, instead of resuming mid-fade from wherever
      // scrolling away happened to interrupt it.
      stayText.classList.remove('revealed');
      duoStage.classList.remove('visible');
    }

    if (prefersReducedMotion) {
      // A fully static "arrived" end-state - the real verified frame where
      // the sword reads as pointed - with zero timers running at all.
      ensureDuoAnim().goToAndStop(20, true);
      stayText.classList.add('revealed');
      duoStage.classList.add('visible');
    } else if ('IntersectionObserver' in window) {
      const stayObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            startStayLoop();
          } else {
            stopStayLoop();
          }
        });
      }, { threshold: 0.25 });
      stayObserver.observe(stayScene);
    } else {
      startStayLoop();
    }
  }

  // ---------- Section 5: backed by real teachers (walking-boy foreground +
  // city-skyline background banner) ---------- //
  // Both Lotties play their full native range on a simple continuous loop
  // (matching Sections 1-3's own established pattern for decorative,
  // always-looping elements), gated behind the section scrolling into view
  // the same way as every earlier lazy-loaded section on this page.
  const schoolScene = document.getElementById('schoolScene');
  const schoolText = document.getElementById('schoolText');
  const cityBannerStage = document.getElementById('cityBannerStage');
  const goToSchoolStage = document.getElementById('goToSchoolStage');

  if (schoolScene && schoolText && cityBannerStage && goToSchoolStage && window.lottie) {
    let schoolSceneActivated = false;
    function activateSchoolScene() {
      if (schoolSceneActivated) return;
      schoolSceneActivated = true;

      const cityBannerAnim = lottie.loadAnimation({
        container: document.getElementById('cityBannerLottie'),
        renderer: 'svg',
        loop: !prefersReducedMotion,
        autoplay: !prefersReducedMotion,
        path: '{{ asset("animations/tarabasa-city-banner.json") }}'
      });
      const goToSchoolAnim = lottie.loadAnimation({
        container: document.getElementById('goToSchoolLottie'),
        renderer: 'svg',
        loop: !prefersReducedMotion,
        autoplay: !prefersReducedMotion,
        path: '{{ asset("animations/tarabasa-go-to-school.json") }}'
      });

      if (prefersReducedMotion) {
        cityBannerAnim.goToAndStop(0, true);
        goToSchoolAnim.goToAndStop(0, true);
        schoolText.classList.add('revealed');
        cityBannerStage.classList.add('revealed');
        goToSchoolStage.classList.add('revealed');
        return;
      }

      schoolText.classList.add('revealed');
      setTimeout(() => cityBannerStage.classList.add('revealed'), 200);
      setTimeout(() => goToSchoolStage.classList.add('revealed'), 350);
    }

    if ('IntersectionObserver' in window) {
      const schoolSceneObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            activateSchoolScene();
            schoolSceneObserver.disconnect();
          }
        });
      }, { threshold: 0.25 });
      schoolSceneObserver.observe(schoolScene);
    } else {
      activateSchoolScene();
    }
  }

  // ---------- Section 6: learning that feels like play (aerial farmland +
  // kite + seesaw) ---------- //
  // The kite and see-saw are genuine loops and play continuously once
  // revealed (matching Sections 1-3's own established "always-looping
  // decorative element" pattern). The farmland is different - confirmed
  // from its own source that it's a one-shot "hills rise into place"
  // animation, not a loop, so it's loaded with loop:false and repeated by
  // hand in activateGamesScene (rise, hold, reverse, rest) so it never
  // snaps from full height back to flat.
  const gamesScene = document.getElementById('gamesScene');
  const gamesText = document.getElementById('gamesText');
  const kiteStage = document.getElementById('kiteStage');
  const farmLandStage = document.getElementById('farmLandStage');
  const seesawStage = document.getElementById('seesawStage');

  if (gamesScene && gamesText && kiteStage && farmLandStage && seesawStage && window.lottie) {
    let gamesSceneActivated = false;
    function activateGamesScene() {
      if (gamesSceneActivated) return;
      gamesSceneActivated = true;

      const kiteAnim = lottie.loadAnimation({
        container: document.getElementById('kiteLottie'),
        renderer: 'svg',
        loop: !prefersReducedMotion,
        autoplay: !prefersReducedMotion,
        path: '{{ asset("animations/tarabasa-kite.json") }}'
      });
      const farmLandAnim = lottie.loadAnimation({
        container: document.getElementById('farmLandLottie'),
        renderer: 'svg',
        loop: false,
        autoplay: false,
        path: '{{ asset("animations/tarabasa-farm-land.json") }}'
      });
      const seesawAnim = lottie.loadAnimation({
        container: document.getElementById('seesawLottie'),
        renderer: 'svg',
        loop: !prefersReducedMotion,
        autoplay: !prefersReducedMotion,
        path: '{{ asset("animations/tarabasa-seesaw.json") }}'
      });

      if (prefersReducedMotion) {
        kiteAnim.goToAndStop(0, true);
        seesawAnim.goToAndStop(0, true);
        // The farmland's frame 0 is the "squashed, not grown yet" state -
        // freezing there would look unfinished, not like a real resting
        // frame, so this waits for the real duration to load and stops on
        // its own last frame (the settled, fully-grown state) instead.
        farmLandAnim.addEventListener('DOMLoaded', () => {
          farmLandAnim.goToAndStop(farmLandAnim.totalFrames - 1, true);
        });
        gamesText.classList.add('revealed');
        kiteStage.classList.add('revealed');
        farmLandStage.classList.add('revealed');
        seesawStage.classList.add('revealed');
        return;
      }

      // The land's hills rise using the asset's own keyframes: all four
      // animated properties finish by frame 249 (checked in the JSON; frames
      // 250-350 just hold). The frame is driven directly on a fixed timeline
      // - rise (2.5s = the asset's native pace at 100fps), hold at full
      // height, sink back along the same keyframes, rest flat, repeat - so
      // there is never an instant snap from full height to flat, and the
      // timing is deterministic rather than depending on lottie's reverse
      // playback. Only renders while the strip is on screen.
      farmLandAnim.addEventListener('DOMLoaded', () => {
        const RISE_END = 249, RISE_MS = 2500, HOLD_MS = 3200, SINK_MS = 1800, REST_MS = 900;
        const CYCLE = RISE_MS + HOLD_MS + SINK_MS + REST_MS;
        let farmInView = true;
        if ('IntersectionObserver' in window) {
          new IntersectionObserver((es) => { farmInView = es[0].isIntersecting; }).observe(farmLandStage);
        }
        let farmStart = null;
        function farmTick(now) {
          if (farmStart === null) farmStart = now;
          if (farmInView) {
            const t = (now - farmStart) % CYCLE;
            let frame;
            if (t < RISE_MS) frame = RISE_END * (t / RISE_MS);
            else if (t < RISE_MS + HOLD_MS) frame = RISE_END;
            else if (t < RISE_MS + HOLD_MS + SINK_MS) frame = RISE_END * (1 - (t - RISE_MS - HOLD_MS) / SINK_MS);
            else frame = 0;
            farmLandAnim.goToAndStop(frame, true);
          }
          requestAnimationFrame(farmTick);
        }
        requestAnimationFrame(farmTick);
      });

      gamesText.classList.add('revealed');
      farmLandStage.classList.add('revealed');
      setTimeout(() => kiteStage.classList.add('revealed'), 200);
      setTimeout(() => seesawStage.classList.add('revealed'), 350);
    }

    if ('IntersectionObserver' in window) {
      const gamesSceneObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            activateGamesScene();
            gamesSceneObserver.disconnect();
          }
        });
      }, { threshold: 0.2 });
      gamesSceneObserver.observe(gamesScene);
    } else {
      activateGamesScene();
    }
  }

  // ---------- Final section: parent gate ----------
  // Every link marked data-adult-gate first asks a quick multiplication
  // question (first number 12-29, second 3-9, never a multiple of ten, and
  // never the same question twice in a row). Correct: goes to the link's real
  // destination. Wrong: shakes and asks a NEW question, so guessing the same
  // problem repeatedly doesn't work. This is friction for a curious child, not
  // security: the links are real (they work without scripts), and the header's
  // own Log in is deliberately not gated.
  const parentGate = document.getElementById('parentGate');
  const gateDialog = document.getElementById('gateDialog');
  const gateForm = document.getElementById('gateForm');
  const gateQuestion = document.getElementById('gateQuestion');
  const gateAnswer = document.getElementById('gateAnswer');
  const gateError = document.getElementById('gateError');

  if (parentGate && gateDialog && gateForm && gateQuestion && gateAnswer && gateError) {
    let gateExpected = 0, gateTarget = null, gateOpener = null, gateLastQuestion = '';

    function gateRandInt(min, max) {
      if (window.crypto && crypto.getRandomValues) {
        const buf = new Uint32Array(1);
        crypto.getRandomValues(buf);
        return min + (buf[0] % (max - min + 1));
      }
      return min + Math.floor(Math.random() * (max - min + 1));
    }
    function newGateQuestion() {
      let a, b, key;
      do {
        a = gateRandInt(12, 29);
        b = gateRandInt(3, 9);
        key = a + 'x' + b;
      } while (a % 10 === 0 || key === gateLastQuestion);
      gateLastQuestion = key;
      gateExpected = a * b;
      gateQuestion.textContent = 'What is ' + a + ' × ' + b + '?';
    }
    function openGate(link) {
      gateOpener = link;
      gateTarget = link.href;
      newGateQuestion();
      gateAnswer.value = '';
      gateError.textContent = '';
      parentGate.hidden = false;
      document.body.style.overflow = 'hidden';
      gateAnswer.focus();
    }
    function closeGate() {
      parentGate.hidden = true;
      document.body.style.overflow = '';
      gateTarget = null;
      if (gateOpener) gateOpener.focus();
    }

    document.querySelectorAll('[data-adult-gate]').forEach((link) => {
      link.addEventListener('click', (e) => { e.preventDefault(); openGate(link); });
    });
    parentGate.querySelectorAll('[data-gate-close]').forEach((el) => el.addEventListener('click', closeGate));

    gateForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const typed = gateAnswer.value.trim();
      if (typed === '') { gateError.textContent = 'Type your answer first.'; gateAnswer.focus(); return; }
      if (/^\d+$/.test(typed) && parseInt(typed, 10) === gateExpected) {
        const destination = gateTarget;
        parentGate.hidden = true;
        document.body.style.overflow = '';
        window.location.href = destination;
        return;
      }
      gateError.textContent = 'Not quite, try this one.';
      gateDialog.classList.remove('shake');
      void gateDialog.offsetWidth; // restart the shake animation
      gateDialog.classList.add('shake');
      newGateQuestion();
      gateAnswer.value = '';
      gateAnswer.focus();
    });

    document.addEventListener('keydown', (e) => {
      if (parentGate.hidden) return;
      if (e.key === 'Escape') { e.preventDefault(); closeGate(); return; }
      if (e.key === 'Tab') {
        // Keep keyboard focus inside the dialog while it is open.
        const items = Array.from(gateDialog.querySelectorAll('input, button')).filter((el) => !el.disabled);
        const first = items[0], last = items[items.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
      }
    });
  }

  // ---------- Final section: owl + globe ----------
  // The owl's artwork sits in the middle of a 512x512 canvas; the animation is
  // cropped to the union of everything it paints over its whole loop
  // (x 136-375, y 102-297, measured frame by frame, plus a small margin) so
  // the wing flap never clips. The globe loops seamlessly (its clouds are off
  // the canvas at both ends). Both only play while the section is on screen.
  const finaleScene = document.getElementById('finaleScene');
  const finaleOwlEl = document.getElementById('finaleOwl');
  const finaleGlobeEl = document.getElementById('finaleGlobe');
  const finaleCard = document.getElementById('finaleCard');

  if (finaleScene && finaleOwlEl && finaleGlobeEl && finaleCard && window.lottie) {
    let finaleActivated = false, finaleInView = false, finaleOwlAnim = null, finaleGlobeAnim = null;

    function finaleWhenReady(anim, fn) {
      if (anim.isLoaded) fn(); else anim.addEventListener('DOMLoaded', fn);
    }
    function applyFinalePlayState() {
      if (prefersReducedMotion) return;
      [finaleOwlAnim, finaleGlobeAnim].forEach((anim) => {
        if (!anim) return;
        if (finaleInView) anim.play(); else anim.pause();
      });
    }
    function activateFinale() {
      if (finaleActivated) return;
      finaleActivated = true;
      finaleOwlAnim = lottie.loadAnimation({
        container: finaleOwlEl,
        renderer: 'svg',
        loop: !prefersReducedMotion,
        autoplay: false,
        path: '{{ asset("animations/tarabasa-owl-logo.json") }}',
        rendererSettings: { preserveAspectRatio: 'xMidYMax meet', viewBoxSize: '128 96 254 208' }
      });
      finaleGlobeAnim = lottie.loadAnimation({
        container: finaleGlobeEl,
        renderer: 'svg',
        loop: !prefersReducedMotion,
        autoplay: false,
        path: '{{ asset("animations/tarabasa-globe.json") }}',
        rendererSettings: { preserveAspectRatio: 'xMidYMax slice' }
      });
      // Reduced motion: still frames (owl at rest, globe with clouds in view).
      finaleWhenReady(finaleOwlAnim, () => { finaleOwlAnim.goToAndStop(prefersReducedMotion ? 60 : 0, true); applyFinalePlayState(); });
      finaleWhenReady(finaleGlobeAnim, () => { finaleGlobeAnim.goToAndStop(prefersReducedMotion ? 500 : 300, true); applyFinalePlayState(); });
    }

    if ('IntersectionObserver' in window) {
      // Start loading (the globe is ~1.5MB) well before it scrolls into view.
      const finaleLoadObserver = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting) { activateFinale(); finaleLoadObserver.disconnect(); }
      }, { rootMargin: '900px 0px' });
      finaleLoadObserver.observe(finaleScene);
      new IntersectionObserver((entries) => {
        finaleInView = entries[0].isIntersecting;
        applyFinalePlayState();
      }, { threshold: 0.05 }).observe(finaleScene);
      const finaleCardObserver = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting) { finaleCard.classList.add('revealed'); finaleCardObserver.disconnect(); }
      }, { threshold: 0.3 });
      finaleCardObserver.observe(finaleCard);
    } else {
      finaleInView = true;
      activateFinale();
      finaleCard.classList.add('revealed');
    }
  }
</script>
</body>
</html>
