{{--
  The scene shown after a reading item: the "Great job" screen between items,
  the result at the end of the first-login check, and the two "try again"
  screens. A full document (this app's Learner screens are standalone), used by
  diagnostic-encourage, diagnostic-results and reading-unclear so they stay one
  design: a plain white page, a star on one side (happy when it went well, sad
  when the recording could not be heard), a cloud with the headline, then the
  message and one big clay button. It matches the intro screen, and stacks on
  phones.

  The sad star is ONLY for "we could not hear you". It is never shown for a low
  score: the check has no visible score or pass/fail framing (Placement
  Diagnostic patch, Part 4.2), so a child who landed on Beginning sees the same
  happy star as one who landed on Proficient.

  Expects: $mood ('happy' | 'sad'), $pageTitle, $heading, $message,
           $buttonLabel, $buttonHref.
  Optional: $dotsDone + $dotsTotal (progress dots), $level (the level pill),
            $newBadges (badges just earned: code, name, description, emoji).
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $pageTitle }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Semi+Condensed:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/lottie-web@5.12.2/build/player/lottie.min.js"></script>
<style>
  :root{
    --navy-900:#131f2b; --slate-600:#5b6b7a; --blue-500:#1c7ed6; --teal:#2bb89c; --line:#dbe7f3;
    --font-game:'Bahnschrift SemiCondensed','Bahnschrift','Barlow Semi Condensed',sans-serif;
  }
  *{ box-sizing:border-box; }
  html,body{ margin:0; padding:0; background:#ffffff; }
  body{ font-family:'Inter',sans-serif; color:var(--navy-900); }

  /* The cloud's colours: warm yellow when it went well, a cool soft blue when it did not. */
  .fb{ --cloud:#ffe08a; --cloud-lip:#e3b53a; }
  .fb.sad{ --cloud:#d9e8f8; --cloud-lip:#b5cde6; }

  /* Sizes worked out once so the cloud can line up with the star's face (the glasses sit
     about 0.48 of the way down the animation box, measured from the Lottie). */
  .fb{
    --padx:clamp(20px, 5vw, 72px);
    --gap:clamp(20px, 4vw, 72px);
    --col:calc((min(1180px, 100vw - 2 * var(--padx)) - var(--gap)) / 2);
    --starw:min(480px, 62vh, var(--col));
    --cloudw:min(540px, var(--col));
    min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:14px; padding:28px var(--padx);
  }
  .fb-dots{ display:flex; gap:12px; justify-content:center; }
  .fb-dot{ width:16px; height:16px; border-radius:50%; background:var(--line); }
  .fb-dot.done{ background:var(--teal); }

  .fb-stage{ width:100%; max-width:1180px; display:grid; grid-template-columns:minmax(0, 1fr) minmax(0, 1fr); align-items:start; gap:var(--gap); }

  .fb-star{ position:relative; width:100%; max-width:var(--starw); aspect-ratio:180 / 220; margin:0 auto; }
  #starAnim{ position:absolute; inset:0; }

  .fb-talk{ display:flex; flex-direction:column; align-items:flex-start; min-width:0; padding-top:clamp(8px, 1.6vw, 26px); }
  @supports (width:min(1px, 2px)){ .fb-talk{ padding-top:max(0px, calc(0.587 * var(--starw) - 0.28125 * var(--cloudw))); } }

  .fb-cloud{ position:relative; width:100%; max-width:540px; aspect-ratio:640 / 360; margin-bottom:26px; }
  .fb-cloud-shape{ position:absolute; inset:0; width:100%; height:100%; overflow:visible; filter:drop-shadow(0 9px 0 var(--cloud-lip)) drop-shadow(0 26px 28px rgba(19,64,110,.18)); }
  .fb-cloud h1{
    position:absolute; inset:0; display:flex; align-items:center; justify-content:center; text-align:center; margin:0; padding:0 15%;
    font-family:var(--font-game); font-weight:700; font-size:clamp(30px, 3.1vw, 48px); line-height:1.08; color:#2d2308; text-wrap:balance;
  }
  .fb-cloud h1.long{ font-size:clamp(26px, 2.5vw, 38px); }
  .fb.sad .fb-cloud h1{ color:#1f3550; }
  .fb-bubble{ position:absolute; border-radius:50%; background:var(--cloud); box-shadow:0 5px 0 var(--cloud-lip); }
  .fb-bubble.b1{ width:6.2%; aspect-ratio:1; left:-2%; top:50%; }
  .fb-bubble.b2{ width:3.6%; aspect-ratio:1; left:-8.5%; top:54%; }

  .fb-level{
    display:inline-block; margin:0 0 20px; padding:12px 30px 11px; border-radius:999px; background:#d9f3ea; color:#0c4a3c;
    font:700 clamp(22px, 1.9vw, 28px)/1.1 var(--font-game); letter-spacing:.02em; box-shadow:0 5px 0 #9fdcc6;
  }
  .fb-badges{ display:flex; flex-wrap:wrap; gap:12px; margin:0 0 20px; }
  .fb-badge{
    display:flex; align-items:center; gap:12px; margin:0; padding:10px 20px 10px 14px; border-radius:22px; background:#ffe08a;
    box-shadow:0 6px 0 #e3b53a; animation:fbPop .5s cubic-bezier(.34,1.56,.64,1);
  }
  @keyframes fbPop{ 0%{ transform:scale(.6); opacity:0; } 70%{ transform:scale(1.05); opacity:1; } 100%{ transform:scale(1); opacity:1; } }
  .fb-badge-emoji{ font-size:34px; line-height:1; }
  .fb-badge-label{ font:600 15px/1 var(--font-game); letter-spacing:.08em; text-transform:uppercase; color:#7a5400; margin-bottom:4px; }
  .fb-badge-name{ font:700 23px/1.1 var(--font-game); color:#3f2c00; }

  .fb-msg{ font-family:var(--font-game); font-weight:500; font-size:clamp(21px, 2vw, 30px); line-height:1.38; color:#2f455b; max-width:540px; margin:0 0 26px; }

  .fb-btn{
    display:inline-block; text-align:center; text-decoration:none; cursor:pointer; color:#fff; border:none;
    font-family:var(--font-game); font-weight:600; font-size:clamp(24px, 2.1vw, 32px); letter-spacing:.06em; text-transform:uppercase;
    padding:20px 64px 18px; border-radius:22px; background:linear-gradient(180deg,#f9a544,#ee8a26); text-shadow:0 1px 0 rgba(0,0,0,.2);
    box-shadow:0 7px 0 #b4560b, 0 18px 24px -10px rgba(180,86,11,.65), inset 0 2px 0 rgba(255,255,255,.45);
    transition:transform .08s ease, box-shadow .08s ease;
  }
  .fb-btn:hover{ transform:translateY(-1px); }
  .fb-btn:active{ transform:translateY(4px); box-shadow:0 3px 0 #b4560b, 0 8px 12px -6px rgba(180,86,11,.6), inset 0 2px 0 rgba(255,255,255,.4); }
  .fb-btn:focus-visible{ outline:4px solid var(--blue-500); outline-offset:4px; }

  /* ---- phones and small tablets: the scene stacks, the star on top ---- */
  @media (max-width:860px){
    .fb{ justify-content:flex-start; padding:20px 20px 32px; }
    .fb-stage{ grid-template-columns:1fr; gap:0; }
    .fb-star{ max-width:min(64vw, 34vh, 300px); }
    .fb-talk{ align-items:center; text-align:center; padding-top:0; }
    .fb-cloud{ max-width:440px; margin:0 auto 24px; }
    .fb-bubble.b1{ left:46.5%; top:-8%; width:6.5%; }
    .fb-bubble.b2{ left:44%; top:-15.5%; width:3.8%; }
    .fb-msg{ margin-left:auto; margin-right:auto; }
    .fb-btn{ width:100%; max-width:460px; padding:20px 24px 18px; }
  }
  @media (max-height:640px) and (min-width:861px){ .fb{ --starw:min(480px, 56vh, var(--col)); } }
  @media (prefers-reduced-motion:reduce){ .fb-badge{ animation:none; } .fb-btn{ transition:none; } }
</style>
</head>
<body>
<main class="fb {{ $mood === 'sad' ? 'sad' : 'happy' }}">

  @isset($dotsTotal)
    <div class="fb-dots" role="img" aria-label="{{ $dotsDone }} of up to {{ $dotsTotal }} readings done">
      @for ($i = 1; $i <= $dotsTotal; $i++)
        <span class="fb-dot {{ $i <= $dotsDone ? 'done' : '' }}"></span>
      @endfor
    </div>
  @endisset

  <div class="fb-stage">

    <div class="fb-star">
      <div id="starAnim" role="img" aria-label="{{ $mood === 'sad' ? 'A worried star, with a drop of sweat' : 'A happy star wearing heart glasses, throwing confetti' }}"></div>
    </div>

    <div class="fb-talk">
      <div class="fb-cloud">
        <svg class="fb-cloud-shape" viewBox="0 0 640 360" aria-hidden="true">
          <g fill="var(--cloud)">
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
        <h1 class="{{ mb_strlen($heading) > 24 ? 'long' : '' }}">{{ $heading }}</h1>
        <span class="fb-bubble b1" aria-hidden="true"></span>
        <span class="fb-bubble b2" aria-hidden="true"></span>
      </div>

      @if (! empty($level))
        <div class="fb-level">{{ $level }}</div>
      @endif

      @if (! empty($newBadges))
        <div class="fb-badges">
          @foreach ($newBadges as $badge)
            <div class="fb-badge">
              <span class="fb-badge-emoji">{{ $badge['emoji'] }}</span>
              <div>
                <div class="fb-badge-label">New badge</div>
                <div class="fb-badge-name">{{ $badge['name'] }}</div>
              </div>
            </div>
          @endforeach
        </div>
      @endif

      <p class="fb-msg">{{ $message }}</p>

      <a href="{{ $buttonHref }}" class="fb-btn" id="fbBtn">{{ $buttonLabel }}</a>
    </div>

  </div>
</main>

<script>
  // The star loops for seven seconds (confetti bursts when it is happy, a drop of sweat when
  // it is worried). With reduced motion it holds still on the first frame.
  (function () {
    try {
      var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      var star = lottie.loadAnimation({
        container: document.getElementById('starAnim'), renderer: 'svg', loop: true, autoplay: !reduce,
        path: @json(asset('animations/learner/star-'.($mood === 'sad' ? 'sad' : 'happy').'.json'))
      });
      if (reduce) { star.addEventListener('DOMLoaded', function () { star.goToAndStop(0, true); }); }
    } catch (e) { /* the screen works without the star */ }
  })();
</script>
</body>
</html>
