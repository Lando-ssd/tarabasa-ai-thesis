<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Let's Read Together — TaraBasa AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --sky-100:#dcedff;
    --blue-500:#1c7ed6; --blue-700:#0a3d73;
    --navy-900:#131f2b; --slate-600:#5b6b7a;
    --owl-orange-500:#ef8d2a; --owl-orange-600:#dd7014; --clay-yellow:#ffcf6e;
    --teal:#2bb89c;
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

  .progress-wrap{ margin-bottom:16px; }
  .progress-label{ text-align:center; font:800 12.5px/1 'Baloo 2',sans-serif; color:var(--slate-600); margin-bottom:8px; letter-spacing:.02em; }
  .progress-dots{ display:flex; gap:8px; justify-content:center; }
  .pdot{ width:10px;height:10px;border-radius:50%; background:var(--line); }
  .pdot.done{ background:var(--teal); }
  .pdot.current{ background:var(--owl-orange-500); transform:scale(1.3); }

  .card{
    position:relative; overflow:hidden;
    background:var(--surface); border-radius:30px; padding:32px 28px; text-align:center;
    box-shadow:0 30px 60px -28px rgba(15,60,110,0.25);
  }
  .clay-blob{ position:absolute; border-radius:50%; pointer-events:none; z-index:0; }
  .clay-blob.b1{ width:150px;height:150px; top:-60px; right:-50px; background:radial-gradient(circle, rgba(255,207,110,0.35), transparent 70%); }
  .clay-blob.b2{ width:120px;height:120px; bottom:-40px; left:-40px; background:radial-gradient(circle, rgba(28,126,214,0.12), transparent 70%); }
  .card > *{ position:relative; z-index:1; }
  .mascot{
    width:72px;height:72px;border-radius:24px; margin:0 auto 14px; font-size:36px;
    background:linear-gradient(155deg, var(--clay-yellow), var(--owl-orange-600));
    display:flex;align-items:center;justify-content:center; box-shadow:0 16px 28px -12px rgba(221,112,20,0.5);
  }
  .passage-card{
    background:var(--bg-0); border:2px solid var(--line); border-radius:22px; padding:22px; margin-bottom:22px;
    font-family:'Baloo 2',sans-serif; font-size:23px; font-weight:600; line-height:1.7; color:var(--navy-900);
    text-align:left;
  }
  /* Same paced, decorative word-tracking as the regular reading screen —
     not real transcription, see activity-found.blade.php's comment for
     the full explanation. Kept here too since a young reader shouldn't
     get a visually plainer diagnostic than a regular activity. */
  .lw{ padding:1px 3px; border-radius:6px; transition:background-color .2s ease, color .2s ease; }
  .lw.tracking{ background:var(--sky-100); box-shadow:0 0 0 2px rgba(28,126,214,0.25) inset; }

  .step{ display:none; }
  .step.active{ display:block; }

  .mic-zone{ display:flex; flex-direction:column; align-items:center; gap:10px; margin-bottom:14px; }
  .mic-btn{
    width:84px;height:84px;border-radius:50%; border:none; cursor:pointer;
    background:linear-gradient(155deg, #ff8a65, var(--owl-orange-600)); box-shadow:0 16px 28px -12px rgba(221,112,20,0.55);
    display:flex;align-items:center;justify-content:center; transition:transform .2s cubic-bezier(.34,1.56,.64,1);
  }
  .mic-btn:hover{ transform:scale(1.05); }
  .mic-btn.listening{ animation:pulse 1s ease-in-out infinite; background:linear-gradient(155deg, #ff6b6b, var(--danger)); }
  @keyframes pulse{ 0%,100%{ box-shadow:0 0 0 0 rgba(214,69,69,0.4);} 50%{ box-shadow:0 0 0 16px rgba(214,69,69,0);} }
  .mic-label{ font-size:17px; font-weight:700; color:var(--slate-600); }
  .timer-label{ font-family:'Baloo 2',sans-serif; font-size:22px; font-weight:700; color:var(--navy-900); }
  .timer-label.warn{ color:var(--danger); }

  .loading-spin{
    width:44px;height:44px;border-radius:50%; margin:0 auto 12px; border:4px solid var(--line); border-top-color:var(--owl-orange-500);
    animation:spin 0.9s linear infinite;
  }
  @keyframes spin{ to{ transform:rotate(360deg); } }

  .note-banner{
    display:flex; gap:10px; text-align:left; border-radius:16px; padding:14px 16px; margin-bottom:14px;
    font-size:15px; font-weight:500; line-height:1.5;
  }
  .note-banner.amber{ background:var(--amber-bg); border:1px solid var(--amber); color:var(--navy-900); }

  .big-btn{
    display:block; width:100%; padding:16px; border:none; border-radius:18px; font:800 15px/1 'Baloo 2',sans-serif;
    cursor:pointer; background:linear-gradient(155deg, var(--blue-500), var(--blue-700)); color:#fff;
    text-decoration:none; box-sizing:border-box; box-shadow:0 16px 26px -12px rgba(15,95,174,0.5);
    transition:transform .2s cubic-bezier(.34,1.56,.64,1);
  }
  .big-btn:hover{ transform:translateY(-2px) scale(1.02); }
</style>
</head>
<body>
<div class="wrap">
  {{-- Part 4.5: simple visual progress so the child knows there's a
       clear end in sight. Three dots always shown (the actual staircase
       may stop after 1 or 2) rather than a false "of N" count, since the
       real total isn't known ahead of time. --}}
  <div class="progress-wrap">
    <div class="progress-label">Passage {{ $passageNumber }}</div>
    <div class="progress-dots">
      @for ($i = 1; $i <= $maxPassages; $i++)
        <div class="pdot {{ $i < $passageNumber ? 'done' : ($i === $passageNumber ? 'current' : '') }}"></div>
      @endfor
    </div>
  </div>

  <div class="card">
    <div class="clay-blob b1"></div>
    <div class="clay-blob b2"></div>
    <div class="mascot">🦉</div>

    <div class="passage-card" id="passageCard">@foreach (preg_split('/\s+/', trim($activity->passage_text)) as $word)<span class="lw">{{ $word }}</span> @endforeach</div>

    @include('learner._recording-widget', ['recordAction' => route('learner.diagnostic.record')])
  </div>
</div>
<script>
  // Same word-length-weighted, then real-duration-rescaled pacing
  // animation as activity-found.blade.php — see that file's comment for
  // the full rationale (still not real transcription).
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
        // See activity-found.blade.php's comment on this exact block for
        // the full explanation of the real bug fixed here: a short
        // recording rescaled with no floor and played once (loop=false)
        // finished in a near-instant blur, then sat blank for the rest
        // of the real (and unrelated-length) Reading-api wait — read by
        // a real user as "it jumps straight to the end." Fixed with a
        // per-word floor (LIVE_MIN_MS) plus looping instead of a single
        // pass.
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
