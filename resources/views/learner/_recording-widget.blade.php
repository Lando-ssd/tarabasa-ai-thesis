{{--
  Shared audio-recording UI: MediaRecorder capture, 60s auto-stop timer,
  permission/unsupported handling, and DataTransfer-injection into a real
  file input for a normal multipart form submit (same pattern already
  used for the Cropper.js photo-upload feature — kept consistent with
  this app's "plain form submission, no AJAX" convention). Extracted here
  since LearnerReadingController and LearnerDiagnosticController both
  need the exact same interactive behavior — one place, not two copies
  that can drift apart.

  The including page must define these CSS classes in its own <style>
  block (each Blade view here is standalone, no shared layout): .step,
  .step.active, .mic-zone, .mic-btn, .mic-btn.listening, .mic-label,
  .timer-label, .timer-label.warn, .loading-spin, .note-banner,
  .note-banner.amber, .big-btn. See activity-found.blade.php for the
  reference definitions.

  Also does a purely on-device "is any sound actually coming through
  the mic at all" check via the Web Audio API's AnalyserNode (real
  audio-level monitoring on the same MediaStream already granted for
  recording — no separate permission, no data ever leaves the browser,
  no third-party service). This is NOT speech recognition and never
  attempts to be — it can't tell if the child is reading correctly,
  only whether the mic is picking up sound above the noise floor at
  all. It exists to catch the specific "mic is muted/blocked/broken"
  case with an immediate, friendly message instead of silently
  uploading a dead recording and waiting for Reading-api's own (slower,
  round-trip-based) "audio is silent" check to catch it. That server-
  side check still exists and still runs — this is a faster, earlier,
  client-side-only heads-up for the most common real cause, not a
  replacement for it.

  Dispatches two window-level custom events an including page can
  optionally listen for to drive its own passage word-tracking
  animation in sync with the real recording state: 'tarabasa:recording-
  started' (right after the mic actually starts) and 'tarabasa:
  recording-stopped' (right before the "Checking..." step and the real
  form submit) — the stopped event's `detail.durationSeconds` carries
  the REAL elapsed recording time (from this widget's own timer), so an
  including page can rescale a replay of its tracking animation to
  actually match how long the child took, not a guessed pace. On the
  silent-mic path below, 'tarabasa:recording-stopped' still fires (so
  any live tracking loop stops cleanly) but with no `durationSeconds` —
  this take is discarded, not submitted, so there's nothing real to
  rescale a replay to. Kept as events rather than calling a hardcoded
  function name so this partial stays the same neutral, reusable piece
  regardless of whether a given including page wants that animation at
  all.

  Expects: $recordAction (string) — the form's target URL.
--}}
<style>
  /* Scoped here (not the including page) since this is the shared
     step-transition behavior every including page gets automatically —
     a fade+rise on entry so idle → recording → processing never feels
     like an instant snap. */
  @keyframes recStepEnter{ from{ opacity:0; transform:translateY(6px); } to{ opacity:1; transform:translateY(0); } }
  .rec-step-enter{ animation:recStepEnter .25s ease; }
  @media (prefers-reduced-motion: reduce){ .rec-step-enter{ animation:none; } }
</style>
<form method="POST" action="{{ $recordAction }}" enctype="multipart/form-data" id="recordForm">
  @csrf
  <input type="file" name="audio" id="audioInput" style="display:none">
</form>

<div class="step" id="stepUnsupported">
  <div class="note-banner amber">Recording isn't supported in this browser yet — please try a newer browser like Chrome.</div>
</div>

<div class="step" id="stepPermission">
  <div class="note-banner amber">We need to hear you read! Please allow microphone access when your browser asks, then tap the mic again.</div>
</div>

<div class="step active" id="stepReady">
  <div class="mic-zone">
    <button type="button" class="mic-btn" id="micBtn">
      <svg width="30" height="30" viewBox="0 0 24 24" fill="none"><rect x="9" y="3" width="6" height="11" rx="3" fill="white"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3" stroke="white" stroke-width="2.2" stroke-linecap="round"/></svg>
    </button>
    <span class="mic-label">Tap the mic, then read the words above out loud!</span>
  </div>
</div>

<div class="step" id="stepRecording">
  <div class="mic-zone">
    <div class="mic-btn listening">
      <svg width="30" height="30" viewBox="0 0 24 24" fill="none"><rect x="9" y="3" width="6" height="11" rx="3" fill="white"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3" stroke="white" stroke-width="2.2" stroke-linecap="round"/></svg>
    </div>
    <span class="timer-label" id="timerLabel">0:00</span>
  </div>
  <button type="button" class="big-btn" id="doneBtn">I'm done reading!</button>
</div>

<div class="step" id="stepChecking">
  <div class="loading-spin"></div>
  <p class="mic-label">Checking...</p>
</div>

<div class="step" id="stepSilent">
  <div class="note-banner amber">We didn't hear anything that time! Check that your microphone isn't muted or blocked, then give it another try.</div>
  <button type="button" class="big-btn" id="silentRetryBtn">Try Again</button>
</div>

<script>
  (function () {
    const MAX_SECONDS = 60;
    // Below this RMS deviation from silence (byte time-domain data is
    // centered on 128), a sample is treated as "no real sound" — tuned
    // to sit above a live mic's own tiny self-noise floor but well
    // below actual speech or ambient room noise. Only meant to catch a
    // genuinely dead/muted/blocked input, not to judge reading volume.
    const SILENCE_RMS_THRESHOLD = 0.02;
    // Ignore the check entirely for a very short recording (an
    // accidental instant tap) — not enough real time to fairly judge.
    const MIN_SECONDS_FOR_SILENCE_CHECK = 1;

    let mediaRecorder, chunks = [], stream, timerInterval, seconds = 0;
    let audioCtx, analyser, levelCheckInterval, hasDetectedSound = false;

    const steps = {
      unsupported: document.getElementById('stepUnsupported'),
      permission: document.getElementById('stepPermission'),
      ready: document.getElementById('stepReady'),
      recording: document.getElementById('stepRecording'),
      checking: document.getElementById('stepChecking'),
      silent: document.getElementById('stepSilent'),
    };

    function showStep(name) {
      Object.values(steps).forEach(el => { el.classList.remove('active', 'rec-step-enter'); el.style.display = 'none'; });
      const target = steps[name];
      target.style.display = 'block';
      // Force a reflow so the animation class retriggers every time,
      // not just the first — a re-add with no reflow between is a no-op.
      void target.offsetWidth;
      target.classList.add('active', 'rec-step-enter');
    }

    if (typeof MediaRecorder === 'undefined' || !navigator.mediaDevices) {
      showStep('unsupported');
    } else {
      document.getElementById('micBtn').addEventListener('click', startRecording);
    }

    async function startRecording() {
      // getUserMedia + MediaRecorder.start() must both fire directly
      // inside this click handler (no await before them completing the
      // user-gesture chain) — iOS Safari refuses microphone access
      // otherwise.
      try {
        stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      } catch (err) {
        showStep('permission');
        return;
      }

      chunks = [];
      mediaRecorder = new MediaRecorder(stream);
      mediaRecorder.ondataavailable = e => { if (e.data.size > 0) chunks.push(e.data); };
      mediaRecorder.onstop = handleStop;
      mediaRecorder.start();

      startLevelMonitoring();

      showStep('recording');
      window.dispatchEvent(new Event('tarabasa:recording-started'));
      seconds = 0;
      updateTimer();
      timerInterval = setInterval(() => {
        seconds++;
        updateTimer();
        if (seconds >= MAX_SECONDS) stopRecording();
      }, 1000);
    }

    // Taps the same real MediaStream non-destructively (never connected
    // to audioCtx.destination, so nothing is played back / no feedback)
    // purely to watch its volume level. If a browser lacks Web Audio
    // support at all (very old), this just quietly no-ops and the
    // silence check is skipped — never blocks real recording over it.
    function startLevelMonitoring() {
      hasDetectedSound = false;
      const AudioContextClass = window.AudioContext || window.webkitAudioContext;
      if (!AudioContextClass) return;

      try {
        audioCtx = new AudioContextClass();
        const source = audioCtx.createMediaStreamSource(stream);
        analyser = audioCtx.createAnalyser();
        analyser.fftSize = 512;
        source.connect(analyser);

        const data = new Uint8Array(analyser.frequencyBinCount);
        levelCheckInterval = setInterval(() => {
          analyser.getByteTimeDomainData(data);
          let sumSquares = 0;
          for (let i = 0; i < data.length; i++) {
            const normalized = (data[i] - 128) / 128;
            sumSquares += normalized * normalized;
          }
          const rms = Math.sqrt(sumSquares / data.length);
          if (rms > SILENCE_RMS_THRESHOLD) hasDetectedSound = true;
        }, 200);
      } catch (err) {
        audioCtx = null;
      }
    }

    function stopLevelMonitoring() {
      clearInterval(levelCheckInterval);
      if (audioCtx) {
        audioCtx.close().catch(() => {});
        audioCtx = null;
      }
    }

    function updateTimer() {
      const remaining = MAX_SECONDS - seconds;
      const m = Math.floor(seconds / 60);
      const s = seconds % 60;
      const label = document.getElementById('timerLabel');
      label.textContent = m + ':' + String(s).padStart(2, '0');
      label.classList.toggle('warn', remaining <= 10);
    }

    function stopRecording() {
      clearInterval(timerInterval);
      stopLevelMonitoring();
      if (mediaRecorder && mediaRecorder.state !== 'inactive') mediaRecorder.stop();
      if (stream) stream.getTracks().forEach(t => t.stop());
    }

    document.getElementById('doneBtn').addEventListener('click', stopRecording);

    document.getElementById('silentRetryBtn').addEventListener('click', () => {
      showStep('ready');
    });

    function handleStop() {
      // Real on-device signal (not speech recognition — see the file's
      // top comment): if the mic never picked up anything above the
      // noise floor for the whole recording, don't waste an upload and
      // a real Reading-api round trip on what's almost certainly a
      // muted/blocked mic — tell the child right away instead. Dispatch
      // the stop event with no real duration so the including page's
      // tracking animation just clears cleanly rather than starting a
      // "checking" replay for a take we're not actually submitting.
      if (!hasDetectedSound && seconds >= MIN_SECONDS_FOR_SILENCE_CHECK) {
        window.dispatchEvent(new CustomEvent('tarabasa:recording-stopped', { detail: {} }));
        showStep('silent');
        return;
      }

      window.dispatchEvent(new CustomEvent('tarabasa:recording-stopped', { detail: { durationSeconds: seconds } }));

      const mimeType = mediaRecorder.mimeType || 'audio/webm';
      let ext = 'webm';
      if (mimeType.includes('mp4')) ext = 'mp4';
      else if (mimeType.includes('ogg')) ext = 'ogg';
      else if (mimeType.includes('wav')) ext = 'wav';

      const blob = new Blob(chunks, { type: mimeType });
      const file = new File([blob], 'recording.' + ext, { type: mimeType });

      const dataTransfer = new DataTransfer();
      dataTransfer.items.add(file);
      document.getElementById('audioInput').files = dataTransfer.files;

      showStep('checking');
      document.getElementById('recordForm').submit();
    }
  })();
</script>
