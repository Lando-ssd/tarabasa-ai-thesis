{{--
  The scene shown after a reading item: the "Great job" screen between items,
  the result at the end of the first-login check, and the two "try again"
  screens. A full document (this app's Learner screens are standalone), used by
  diagnostic-encourage, diagnostic-results and reading-unclear so they stay one
  design: a plain white page, a star on one side (happy when it went well, sad
  when the recording could not be heard), then a big headline, the message and
  one big clay button on the other. It matches the intro and reading screens,
  and stacks on phones.

  The sad star is ONLY for "we could not hear you". It is never shown for a low
  score: the check has no visible score or pass/fail framing (Placement
  Diagnostic patch, Part 4.2), so a child who landed on Beginning sees the same
  happy star as one who landed on Proficient.

  Expects: $mood ('happy' | 'sad'), $pageTitle, $heading, $buttonLabel, $buttonHref.
  Optional: $subline (a line under the headline), $message, $dotsDone + $dotsTotal (progress dots), $level (the level
            pill), $pill (a small note such as "Free practice"), $newBadges
            (badges just earned: code, name, description; each is shown with its
            own icon, see _badge-icon), $stats (number tiles: value, label and
            optionally icon and tone) and $details (a view to show under the
            star, with $detailsData, e.g. _reading-review for the word by word
            look at a reading).
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $pageTitle }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Semi+Condensed:wght@500;600;700&family=Inter:wght@400;500;600;700&family=Lexend:wght@500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/lottie-web@5.12.2/build/player/lottie.min.js"></script>
<style>
  :root{
    --navy-900:#131f2b; --slate-600:#5b6b7a; --blue-500:#1c7ed6; --teal:#2bb89c; --line:#dbe7f3;
    --font-game:'Bahnschrift SemiCondensed','Bahnschrift','Barlow Semi Condensed',sans-serif;
  }
  *{ box-sizing:border-box; }
  html,body{ margin:0; padding:0; background:#ffffff; }
  body{ font-family:'Inter',sans-serif; color:var(--navy-900); }

  /* The headline's colours: warm orange when it went well, a calm blue when a recording could not be heard. */
  .fb{ --title:#e07a10; --title-lip:#ffd9a0; --bar:#ffcf6e; --bar-lip:#e3b53a; }
  .fb.sad{ --title:#2f5d94; --title-lip:#d3e3f4; --bar:#cfe0f3; --bar-lip:#a9c4e0; }

  .fb{
    --padx:clamp(20px, 5vw, 72px);
    --gap:clamp(20px, 4vw, 72px);
    --col:calc((min(1180px, 100vw - 2 * var(--padx)) - var(--gap)) / 2);
    --starw:min(480px, 62vh, var(--col));
    min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:14px; padding:28px var(--padx);
  }
  .fb-dots{ display:flex; gap:12px; justify-content:center; }
  .fb-dot{ width:16px; height:16px; border-radius:50%; background:var(--line); }
  .fb-dot.done{ background:var(--teal); }

  .fb-stage{ width:100%; max-width:1180px; display:grid; grid-template-columns:minmax(0, 1fr) minmax(0, 1fr); align-items:center; gap:var(--gap); }

  .fb-star{ position:relative; width:100%; max-width:var(--starw); aspect-ratio:180 / 220; margin:0 auto; }
  #starAnim{ position:absolute; inset:0; }

  /* The star's body sits a little below the middle of its box (the shadow is at the bottom), so the words
     are nudged down to sit level with it. */
  .fb-talk{ display:flex; flex-direction:column; align-items:flex-start; min-width:0; padding-top:calc(var(--starw) * .18); }

  .fb-title{
    margin:0 0 26px; font-family:var(--font-game); font-weight:700; font-size:clamp(46px, 5.6vw, 88px); line-height:1.02;
    color:var(--title); text-shadow:0 5px 0 var(--title-lip); text-wrap:balance;
  }
  .fb-title.long{ font-size:clamp(38px, 4.2vw, 64px); }
  .fb-title::after{ content:''; display:block; width:min(150px, 34%); height:12px; margin-top:20px; border-radius:8px; background:var(--bar); box-shadow:0 4px 0 var(--bar-lip); }

  .fb-sub{ margin:-6px 0 22px; font:500 clamp(22px, 2vw, 28px)/1.3 var(--font-game); color:#5b6b7a; max-width:34em; }
  .fb-level{
    display:inline-block; margin:0 0 20px; padding:12px 30px 11px; border-radius:999px; background:#d9f3ea; color:#0c4a3c;
    font:700 clamp(22px, 1.9vw, 28px)/1.1 var(--font-game); letter-spacing:.02em; box-shadow:0 5px 0 #9fdcc6;
  }
  .fb-badges{ display:flex; flex-wrap:wrap; gap:12px; margin:0 0 22px; }
  .fb-badge{
    display:flex; align-items:center; gap:12px; margin:0; padding:10px 22px 12px 12px; border-radius:26px; background:#ffe9b0;
    box-shadow:0 6px 0 #e3b53a; animation:fbPop .5s cubic-bezier(.34,1.56,.64,1);
  }
  @keyframes fbPop{ 0%{ transform:scale(.6); opacity:0; } 70%{ transform:scale(1.05); opacity:1; } 100%{ transform:scale(1); opacity:1; } }
  /* The badge's medal, the same orange one the Badges page uses, with the badge's own icon in it. */
  .fb-badge-medal{
    flex:none; width:54px; height:54px; border-radius:50%; display:flex; align-items:center; justify-content:center;
    background:radial-gradient(circle at 34% 26%, #ffd79a, #ef8d2a 62%, #d4690d); box-shadow:0 4px 0 #b4560b, inset 0 3px 0 rgba(255,255,255,.45);
  }
  .fb-badge-medal .badge-svg{ width:30px; height:30px; color:#fff; display:block; }
  .fb-badge-label{ font:600 15px/1 var(--font-game); letter-spacing:.08em; text-transform:uppercase; color:#7a5400; margin-bottom:4px; }
  .fb-badge-name{ font:700 23px/1.1 var(--font-game); color:#3f2c00; }

  .fb-pill{ display:inline-block; margin:0 0 18px; padding:8px 20px 7px; border-radius:999px; background:#fff3e2; color:#a8570b; font:700 19px/1.1 var(--font-game); letter-spacing:.03em; box-shadow:0 4px 0 #f3d3a4; }

  /* Number tiles (a reading's accuracy, points and so on). */
  .fb-stats{ display:flex; flex-wrap:wrap; gap:12px; width:100%; max-width:560px; margin:0 0 26px; }
  .fb-stat{ flex:1 1 138px; background:#ffffff; border:2px solid #d3e3f4; border-radius:20px; box-shadow:0 5px 0 #c3d8ee; padding:12px 10px 11px; text-align:center; }
  .fb-stat-v{ display:flex; align-items:center; justify-content:center; gap:8px; font:700 40px/1 var(--font-game); color:var(--tone, #1f9e83); }
  .fb-stat-v .badge-svg{ width:30px; height:30px; flex:none; }
  .fb-stat-l{ margin-top:7px; font:600 17px/1.1 var(--font-game); letter-spacing:.05em; text-transform:uppercase; color:#5b6b7a; }

  /* With details underneath, the star is a little smaller and the page starts at the top. */
  .fb.has-details{ justify-content:flex-start; gap:30px; }
  .fb.has-details .fb-star{ --starw:min(340px, 44vh, var(--col)); }
  .fb.has-details .fb-talk{ padding-top:calc(min(340px, 44vh, var(--col)) * .12); }
  .fb-details{ width:100%; display:flex; justify-content:center; }

  .fb-msg{ font-family:var(--font-game); font-weight:500; font-size:clamp(21px, 2vw, 30px); line-height:1.38; color:#2f455b; max-width:540px; margin:0 0 30px; }

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
    .fb-star{ max-width:min(54vw, 30vh, 280px); }
    .fb-talk, .fb.has-details .fb-talk{ align-items:center; text-align:center; padding-top:8px; }
    .fb-title{ margin-bottom:18px; font-size:clamp(38px, 11vw, 56px); }
    .fb-title.long{ font-size:clamp(32px, 9vw, 46px); }
    .fb-title::after{ margin-left:auto; margin-right:auto; margin-top:16px; }
    .fb-stats{ justify-content:center; }
    /* Badges stack full width with their words left aligned (centring them would make two different length names look uneven). */
    .fb-badges{ flex-direction:column; align-items:stretch; width:100%; max-width:380px; margin-bottom:18px; }
    .fb-badge{ text-align:left; }
    .fb-msg{ margin-left:auto; margin-right:auto; }
    .fb-btn{ width:100%; max-width:460px; padding:20px 24px 18px; }
  }
  @media (max-height:640px) and (min-width:861px){ .fb{ --starw:min(480px, 56vh, var(--col)); } }
  @media (prefers-reduced-motion:reduce){ .fb-badge{ animation:none; } .fb-btn{ transition:none; } }
</style>
</head>
<body>
<main class="fb {{ $mood === 'sad' ? 'sad' : 'happy' }} {{ ! empty($details) ? 'has-details' : '' }}">

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
      <h1 class="fb-title {{ mb_strlen($heading) > 26 ? 'long' : '' }}">{{ $heading }}</h1>

      @if (! empty($subline))
        <p class="fb-sub">{{ $subline }}</p>
      @endif

      @if (! empty($level))
        <div class="fb-level">{{ $level }}</div>
      @endif

      @if (! empty($pill))
        <div class="fb-pill">{{ $pill }}</div>
      @endif

      @if (! empty($newBadges))
        <div class="fb-badges">
          @foreach ($newBadges as $badge)
            <div class="fb-badge">
              <span class="fb-badge-medal">@include('learner._badge-icon', ['code' => $badge['code'] ?? null])</span>
              <div>
                <div class="fb-badge-label">New badge</div>
                <div class="fb-badge-name">{{ $badge['name'] }}</div>
              </div>
            </div>
          @endforeach
        </div>
      @endif

      @if (! empty($stats))
        <div class="fb-stats">
          @foreach ($stats as $stat)
            <div class="fb-stat" @if (! empty($stat['tone'])) style="--tone:{{ $stat['tone'] }}" @endif>
              <div class="fb-stat-v">
                @if (! empty($stat['icon']))@include('learner._badge-icon', ['icon' => $stat['icon']])@endif
                <span>{{ $stat['value'] }}</span>
              </div>
              <div class="fb-stat-l">{{ $stat['label'] }}</div>
            </div>
          @endforeach
        </div>
      @endif

      @if (! empty($message))
        <p class="fb-msg">{{ $message }}</p>
      @endif

      <a href="{{ $buttonHref }}" class="fb-btn" id="fbBtn">{{ $buttonLabel }}</a>
    </div>

  </div>

  @if (! empty($details))
    <div class="fb-details">@include($details, $detailsData ?? [])</div>
  @endif
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
