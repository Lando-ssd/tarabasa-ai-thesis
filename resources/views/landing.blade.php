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
    /* Top/bottom padding grew from 64/72px to fit the now much-bigger
       plane (720px, centered on the text block) without this section's
       own overflow:hidden clipping it - measured live (it was clipped by
       66px at the top and 58px at the bottom before this change) and
       increased past that with a small buffer. */
    padding:140px 0 140px;
    overflow:hidden;
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
    padding:100px 0;
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
    .stay-scene{ padding:64px 0 72px; }
    .stay-scene-inner{
      height:auto; display:flex; flex-direction:column; align-items:center; gap:28px;
    }
    .stay-text-wrap{
      position:relative; top:auto; right:auto; margin-top:0;
      max-width:100%; text-align:center;
    }
    .stay-text{ text-align:center; transform:translateY(-24px); }
    .stay-text.revealed{ transform:translateY(0); }
    .stay-text .section-body{ margin:0 auto; }
    .duo-stage{
      position:static; order:0; margin-top:0;
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
    padding:96px 0 170px;
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
    .school-scene{ padding:72px 0 90px; }
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
    padding:96px 0 60px;
    overflow:hidden;
  }
  .games-scene-inner{
    position:relative; z-index:3;
    max-width:1040px; margin:0 auto; padding:0 20px;
  }
  .games-text{
    max-width:560px;
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

  /* A fixed-height "sky zone" holds the kite, deliberately NOT an
     absolute-positioned element left to overflow upward past the land
     strip and guess at clearance - that exact mistake (Section 5's
     walking boy overlapping its text) is what this structure avoids: the
     wrap's own real layout height already includes room for the kite
     (sky-zone's own height + the land strip's aspect-ratio height), so a
     single modest margin-top is all that's needed above it, not a
     measured-after-the-fact fudge factor. */
  .farm-scene-wrap{ position:relative; width:100%; margin-top:60px; }
  .sky-zone{ position:relative; width:100%; height:220px; }
  .kite-stage{
    position:absolute; z-index:2;
    right:8%; top:0;
    width:min(220px, 50vw); aspect-ratio:1/1;
    opacity:0; transform:translateY(-16px);
    transition:opacity .6s ease, transform .6s ease;
  }
  .kite-stage.revealed{ opacity:1; transform:translateY(0); }
  .kite-lottie{ width:100%; height:100%; display:block; }

  /* The farmland - a genuinely flat, non-scrolling illustration (confirmed
     from its own source: the layer's own transform is fully static) with
     one real one-shot detail animation baked in (some crop/foreground
     elements grow from a squashed to a full scale over its first ~2.5s) -
     played once on reveal, not looped, matching what the asset actually
     does rather than forcing a loop it was never authored for. Scaled up
     as a whole unit (width:100%, its own 2960:700 aspect-ratio preserved)
     to span the section, the same wide-banner technique already
     established for Section 2's sunrise and Section 5's city banner. */
  .farm-land-stage{
    position:relative; z-index:0;
    width:100%; aspect-ratio:2960/700;
    opacity:0; transition:opacity .8s ease;
  }
  .farm-land-stage.revealed{ opacity:1; }
  .farm-land-lottie{ width:100%; height:100%; display:block; }

  /* The see-saw kids - sized to read as small, recognizable children on a
     big aerial landscape (per the bird's-eye-view brief), anchored with a
     percentage bottom offset (not a fixed px value) so it stays
     proportionally in the same spot on the land strip regardless of the
     strip's own responsive height. */
  .seesaw-stage{
    position:absolute; z-index:1;
    left:30%; bottom:10%;
    width:min(160px, 40vw); aspect-ratio:800/619;
    opacity:0; transform:translateY(12px);
    transition:opacity .6s ease .1s, transform .6s ease .1s;
  }
  .seesaw-stage.revealed{ opacity:1; transform:translateY(0); }
  .seesaw-lottie{ width:100%; height:100%; display:block; }

  @media (max-width:640px){
    .games-scene{ padding:72px 0 48px; }
    .games-scene-inner{ text-align:center; }
    .games-text{ max-width:100%; margin:0 auto; }
    .games-text .section-body{ margin:0 auto; }
    .farm-scene-wrap{ margin-top:44px; }
    .sky-zone{ height:150px; }
    .kite-stage{ right:4%; width:min(140px, 46vw); }
    .seesaw-stage{ left:26%; width:min(110px, 34vw); }
  }

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
    .games-text{ opacity:1; transition:none; }
    .kite-stage{ opacity:1; transition:none; }
    .farm-land-stage{ opacity:1; transition:none; }
    .seesaw-stage{ opacity:1; transition:none; }
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
      <div id="gamesText" class="games-text">
        <h2 class="section-headline">Learning that feels like play</h2>
        <p class="section-body">Word Builder and Letter Match turn practice into a real game, built around each child's own tricky words, no points or pressure attached.</p>
      </div>
    </div>

    <div class="farm-scene-wrap">
      <div class="sky-zone">
        <!-- Real Lottie kite - a genuine flat 7-layer rig (a colorful
             diamond body plus 3 tail ribbons), confirmed from source: zero
             precomps, zero stray solids. Every animated position/rotation
             property returns to its exact frame-0 value by the final
             frame, so this plays its full native ~2s loop, no trimming. -->
        <div id="kiteStage" class="kite-stage">
          <div id="kiteLottie" class="kite-lottie" role="img" aria-label="A colorful kite flying in the sky"></div>
        </div>
      </div>

      <!-- Real Lottie aerial farmland - confirmed from source to be a
           genuinely flat single shape layer (refId:null, zero assets),
           not a precomp hiding more layers the way GO_TO_SCHOOL's root
           layer did. Its own layer transform is fully static - no baked
           scrolling - but a few nested elements play a real one-shot
           "grow from squashed to full size" animation over their first
           ~2.5s, then hold still; played once on reveal below rather than
           forced into a loop it was never authored for. -->
      <div id="farmLandStage" class="farm-land-stage">
        <div id="farmLandLottie" class="farm-land-lottie" role="img" aria-label="An aerial view of farmland"></div>

        <!-- Real Lottie see-saw scene - a genuine flat 42-layer rig (two
             full character rigs plus the see-saw itself), confirmed zero
             precomps and zero stray solids. Every one of its 168 animated
             transform properties returns to its exact frame-0 value by the
             final frame, confirmed layer by layer, so this plays its full
             native ~2.3s rocking loop. -->
        <div id="seesawStage" class="seesaw-stage">
          <div id="seesawLottie" class="seesaw-lottie" role="img" aria-label="Two children playing on a see-saw"></div>
        </div>
      </div>
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
  // from its own source that it's a one-shot "grow into place" animation,
  // not a loop, so it's played once (loop:false) and left to hold its own
  // final settled frame, matching what the asset actually does.
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
        autoplay: !prefersReducedMotion,
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

      gamesText.classList.add('revealed');
      setTimeout(() => farmLandStage.classList.add('revealed'), 150);
      setTimeout(() => kiteStage.classList.add('revealed'), 300);
      setTimeout(() => seesawStage.classList.add('revealed'), 450);
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
</script>
</body>
</html>
