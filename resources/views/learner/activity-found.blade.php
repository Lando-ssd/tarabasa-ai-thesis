<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $activity->title }} — TaraBasa AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --sky-100:#dcedff;
    --blue-500:#1c7ed6; --blue-700:#0a3d73;
    --navy-900:#131f2b; --slate-600:#5b6b7a;
    --owl-orange-500:#ef8d2a; --owl-orange-600:#dd7014; --clay-yellow:#ffcf6e;
    --line:#e3ebf2; --surface:#ffffff; --bg-0:#f6faff;
    --amber:#c9820b; --amber-bg:#fef6e6;
    --danger:#d64545; --danger-bg:#fdecec;
  }
  *{box-sizing:border-box;} html,body{margin:0;padding:0;}
  body{
    min-height:100vh; font-family:'Inter',sans-serif; color:var(--navy-900);
    background: radial-gradient(1200px 700px at 90% -10%, var(--sky-100), transparent 55%),
                radial-gradient(900px 600px at 0% 100%, #ffe9c9, transparent 50%), var(--bg-0);
    background-attachment:fixed;
    display:flex; align-items:safe center; justify-content:center; padding:24px;
  }
  .wrap{ width:100%; max-width:460px; }
  .card{
    position:relative; overflow:hidden;
    background:var(--surface); border-radius:30px; padding:32px 28px; text-align:center;
    box-shadow:0 30px 60px -28px rgba(15,60,110,0.25);
  }
  /* Claymorphism accents, matching the Learner tile on the homepage —
     soft, playful, never a bare white card for a young reader. */
  .clay-blob{ position:absolute; border-radius:50%; pointer-events:none; z-index:0; }
  .clay-blob.b1{ width:150px;height:150px; top:-60px; right:-50px; background:radial-gradient(circle, rgba(255,207,110,0.35), transparent 70%); }
  .clay-blob.b2{ width:120px;height:120px; bottom:-40px; left:-40px; background:radial-gradient(circle, rgba(28,126,214,0.12), transparent 70%); }
  .card > *{ position:relative; z-index:1; }
  .mascot{
    width:88px;height:88px;border-radius:28px; margin:0 auto 14px; font-size:44px;
    background:linear-gradient(155deg, var(--clay-yellow), var(--owl-orange-600));
    display:flex;align-items:center;justify-content:center; box-shadow:0 16px 28px -12px rgba(221,112,20,0.5);
    animation:bob 2.4s ease-in-out infinite;
  }
  @keyframes bob{ 0%,100%{transform:translateY(0);} 50%{transform:translateY(-8px);} }
  h1{ font-family:'Baloo 2',sans-serif; font-size:25px; font-weight:700; margin:0 0 6px; }
  .sub{ font-size:18px; color:var(--slate-600); font-weight:600; margin:0 0 22px; line-height:1.55; }
  .passage-card{
    background:var(--bg-0); border:2px solid var(--line); border-radius:22px; padding:22px; margin-bottom:22px;
    font-family:'Baloo 2',sans-serif; font-size:23px; font-weight:600; line-height:1.7; color:var(--navy-900);
    text-align:left;
  }
  /* Live word-tracking, shown only while actually recording — a paced,
     decorative highlight that walks through the passage at a steady
     guessed pace. This is NOT real transcription: Reading-api only
     scores the recording after it's fully submitted, there is no
     real-time word-by-word signal at all. It exists purely to keep a
     young reader visually engaged while the mic is listening. */
  .lw{ padding:1px 3px; border-radius:6px; transition:background-color .2s ease, color .2s ease; }
  .lw.tracking{ background:var(--sky-100); box-shadow:0 0 0 2px rgba(28,126,214,0.25) inset; }

  .step{ display:none; }
  .step.active{ display:block; }

  .mic-zone{ display:flex; flex-direction:column; align-items:center; gap:14px; margin-bottom:16px; }
  .mic-btn{
    width:88px;height:88px;border-radius:50%; border:none; cursor:pointer;
    background:linear-gradient(155deg, #ff8a65, var(--owl-orange-600));
    box-shadow:0 16px 28px -12px rgba(221,112,20,0.55), 0 0 0 14px rgba(239,141,42,0.12);
    display:flex;align-items:center;justify-content:center; transition:transform .2s cubic-bezier(.34,1.56,.64,1);
  }
  .mic-btn:hover{ transform:scale(1.05); }
  .mic-btn.listening{
    animation:pulse 1.1s ease-in-out infinite; background:linear-gradient(155deg, #ff6b6b, var(--danger));
    box-shadow:0 16px 28px -12px rgba(214,69,69,0.55), 0 0 0 14px rgba(214,69,69,0.12);
  }
  @keyframes pulse{ 0%,100%{ box-shadow:0 16px 28px -12px rgba(214,69,69,0.55), 0 0 0 14px rgba(214,69,69,0.12);} 50%{ box-shadow:0 16px 28px -12px rgba(214,69,69,0.55), 0 0 0 22px rgba(214,69,69,0);} }
  .mic-label{ font-size:17px; font-weight:700; color:var(--slate-600); }
  .timer-label{ font-family:'Baloo 2',sans-serif; font-size:24px; font-weight:700; color:var(--navy-900); }
  .timer-label.warn{ color:var(--danger); }

  .loading-spin{
    width:48px;height:48px;border-radius:50%; margin:0 auto 14px; border:4px solid var(--line); border-top-color:var(--owl-orange-500);
    animation:spin 0.9s linear infinite;
  }
  @keyframes spin{ to{ transform:rotate(360deg); } }

  .note-banner{
    display:flex; gap:10px; text-align:left; border-radius:16px; padding:14px 16px; margin-bottom:14px;
    font-size:15px; font-weight:500; line-height:1.5;
  }
  .note-banner.amber{ background:var(--amber-bg); border:1px solid var(--amber); color:var(--navy-900); }
  .note-banner.danger{ background:var(--danger-bg); border:1px solid var(--danger); color:var(--danger); }

  .big-btn{
    display:block; width:100%; padding:17px; border:none; border-radius:20px; font:800 16px/1 'Baloo 2',sans-serif;
    cursor:pointer; background:linear-gradient(155deg, var(--blue-500), var(--blue-700)); color:#fff;
    text-decoration:none; box-sizing:border-box; box-shadow:0 16px 26px -12px rgba(15,95,174,0.5);
    transition:transform .2s cubic-bezier(.34,1.56,.64,1);
  }
  .big-btn:hover{ transform:translateY(-2px) scale(1.02); }
  .big-btn:disabled{ opacity:.6; cursor:not-allowed; transform:none; }
  a:focus-visible, button:focus-visible{ outline:2px solid var(--blue-500); outline-offset:2px; }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="clay-blob b1"></div>
    <div class="clay-blob b2"></div>
    <div class="mascot">🦉</div>
    <h1>{{ $activity->title }}</h1>
    <p class="sub">Read the words below out loud, then tap the mic!</p>

    <div class="passage-card" id="passageCard">@foreach (preg_split('/\s+/', trim($activity->passage_text)) as $word)<span class="lw">{{ $word }}</span> @endforeach</div>

    @if (session('error') || $errors->any())
      <div class="note-banner danger">{{ $errors->first() ?: session('error') }}</div>
    @endif

    @include('learner._recording-widget', ['recordAction' => route('learner.activity.record', $activity)])

    <a href="{{ route('learner.dashboard') }}" class="big-btn" style="margin-top:14px; background:var(--surface); color:var(--slate-600); box-shadow:none; border:1.5px solid var(--line);">Back to My Dashboard</a>
  </div>
</div>
<script>
  // Paced, decorative word-tracking — see the .lw/.lw.tracking comment
  // above for why this is not real transcription (Reading-api has no
  // real-time per-word signal at all, only a score after the full
  // recording is submitted). Two real signals are used to make the
  // pacing feel less robotic than a flat per-word tick, even though
  // neither is true speech timing:
  //   1. Word length — longer words hold the highlight proportionally
  //      longer than short ones ("a"/"the" flash by, "wooden"/
  //      "together" hold), used while recording is still in progress
  //      and the real total duration isn't known yet.
  //   2. Real elapsed recording time — once the child taps "I'm done
  //      reading!", the widget's own timer tells us exactly how long
  //      they actually took. The animation then REPLAYS once, start to
  //      finish, rescaled so the whole sequence takes exactly that real
  //      duration (still weighted by word length, not split evenly).
  //      This plays out during the real "Checking..." wait for
  //      Reading-api's response, so it's not wasted screen time either.
  //   We still never know WHICH word they were actually on at any real
  //   moment — only their total real reading time — so this remains an
  //   approximation, just one tied to their actual pace instead of an
  //   arbitrary fixed one.
  (function () {
    const words = [...document.querySelectorAll('#passageCard .lw')];
    if (!words.length) return;

    const weights = words.map(w => Math.max(w.textContent.trim().length, 1));
    const totalWeight = weights.reduce((a, b) => a + b, 0);

    const LIVE_MS_PER_CHAR = 90;
    const LIVE_MIN_MS = 220;
    const liveDurations = weights.map(w => Math.max(w * LIVE_MS_PER_CHAR, LIVE_MIN_MS));

    let seqTimer = null;

    function clearHighlight() {
      words.forEach(w => w.classList.remove('tracking'));
    }

    // Walks the passage once per call, dwelling on word i for
    // durations[i] ms; loop=true wraps back to the start indefinitely
    // (used while recording, since we don't yet know a real end time).
    function runSequence(durations, loop) {
      clearTimeout(seqTimer);
      let i = 0;
      (function step() {
        clearHighlight();
        if (i >= words.length) {
          if (!loop) return;
          i = 0;
        }
        words[i].classList.add('tracking');
        seqTimer = setTimeout(step, durations[i]);
        i++;
      })();
    }

    function startTracking() {
      runSequence(liveDurations, true);
    }

    function stopTracking(event) {
      const realSeconds = event?.detail?.durationSeconds;

      if (typeof realSeconds === 'number' && realSeconds > 0) {
        // Real bug found here: the "Checking..." wait this replay plays
        // during has NO relationship to how long the recording itself
        // was — it's however long the real Reading-api/Vosk round trip
        // takes (often several real seconds, sometimes more). A short
        // recording (very common — many passages are just a handful of
        // words) rescales into a tiny total, so a single non-looping
        // pass (the original version of this fix) finished in a blur
        // and then sat blank for the rest of the wait — which is
        // exactly the "jumps straight to the end" bug reported. Fixed
        // two ways: (1) a floor on each word's share, same as the live
        // loop's LIVE_MIN_MS, so a fast reading still visibly paces
        // instead of flashing by; (2) loop the replay indefinitely at
        // that same real-pace-derived cadence instead of stopping after
        // one pass, so it never goes idle/blank while the child is
        // still actually waiting — it just keeps gently repeating until
        // the real results page replaces this one.
        const totalMs = realSeconds * 1000;
        const replayDurations = weights.map(w => Math.max((w / totalWeight) * totalMs, LIVE_MIN_MS));
        runSequence(replayDurations, true);
      } else {
        clearTimeout(seqTimer);
        clearHighlight();
      }
    }

    window.addEventListener('tarabasa:recording-started', startTracking);
    window.addEventListener('tarabasa:recording-stopped', stopTracking);
  })();
</script>
</body>
</html>
