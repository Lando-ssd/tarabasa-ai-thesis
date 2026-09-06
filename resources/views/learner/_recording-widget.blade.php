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

  Dispatches two window-level custom events an including page can
  optionally listen for to drive its own passage word-tracking
  animation in sync with the real recording state: 'tarabasa:recording-
  started' (right after the mic actually starts) and 'tarabasa:
  recording-stopped' (right before the "Checking..." step and the real
  form submit). Kept as events rather than calling a hardcoded function
  name so this partial stays the same neutral, reusable piece regardless
  of whether a given including page wants that animation at all.

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

<script>
  (function () {
    const MAX_SECONDS = 60;
    let mediaRecorder, chunks = [], stream, timerInterval, seconds = 0;

    const steps = {
      unsupported: document.getElementById('stepUnsupported'),
      permission: document.getElementById('stepPermission'),
      ready: document.getElementById('stepReady'),
      recording: document.getElementById('stepRecording'),
      checking: document.getElementById('stepChecking'),
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
      if (mediaRecorder && mediaRecorder.state !== 'inactive') mediaRecorder.stop();
      if (stream) stream.getTracks().forEach(t => t.stop());
    }

    document.getElementById('doneBtn').addEventListener('click', stopRecording);

    function handleStop() {
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

      window.dispatchEvent(new Event('tarabasa:recording-stopped'));
      showStep('checking');
      document.getElementById('recordForm').submit();
    }
  })();
</script>
