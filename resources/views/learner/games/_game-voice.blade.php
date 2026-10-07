{{--
  Voice for the Practice Games: hear a word or a letter, and say a word into the microphone.

  Hearing uses the browser's own read-aloud voice (partials/speak), so nothing about it leaves the
  device. Saying is optional and always practice:
    - one press, one short recording (about three seconds), one word, never a live stream
    - a quick check on the device first: if the microphone heard nothing at all, the clip is not
      even sent (the child is simply asked to try again)
    - the clip goes to the same service that scores readings (GameController::checkWord), which
      answers only "heard" or "again", never "wrong", and keeps nothing
    - a microphone that is blocked, missing or unreachable just means the game carries on without
      the speaking step

  window.tarabasaSayLetter(letter)   read a letter's NAME aloud ("ay", "bee"; reading the bare
                                     letter would give a word like "a" or "I")
  window.tarabasaSayWord(word)       read a word aloud, slowly
  window.tarabasaCanListen           true when this browser can record at all
  window.tarabasaListenForWord(word, { onListening, onChecking, onDone })
                                     onDone gets 'heard' | 'again' | 'unavailable'
  Wakes the scoring service as the game opens (ReadingAiClient::wake), so the first spoken word is
  not the one that waits for it to start.

  Expects the page to include games/_game-look (the button looks) and to be a standalone document.
--}}
@include('partials.speak')
<script>
(function () {
  var LETTER_NAMES = {
    A: 'ay', B: 'bee', C: 'see', D: 'dee', E: 'ee', F: 'eff', G: 'jee', H: 'aitch', I: 'eye', J: 'jay',
    K: 'kay', L: 'el', M: 'em', N: 'en', O: 'oh', P: 'pee', Q: 'cue', R: 'are', S: 'ess', T: 'tee',
    U: 'you', V: 'vee', W: 'double you', X: 'ex', Y: 'why', Z: 'zee'
  };

  window.tarabasaSayLetter = function (letter) {
    var name = LETTER_NAMES[String(letter || '').toUpperCase()];
    if (name) window.tbSpeak(name, { rate: 0.8 });
  };
  window.tarabasaSayWord = function (word) {
    if (word) window.tbSpeak(String(word), { rate: 0.7 });
  };

  var RECORD_MS = 3000;
  var SILENCE_RMS = 0.02;
  var CHECK_URL = @json(route('learner.games.check-word'));
  window.tarabasaCanListen = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.MediaRecorder);
  var blocked = false;

  function pickMime() {
    var list = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4'];
    for (var i = 0; i < list.length; i++) {
      try { if (MediaRecorder.isTypeSupported(list[i])) return list[i]; } catch (e) {}
    }
    return '';
  }

  window.tarabasaListenForWord = function (word, opts) {
    opts = opts || {};
    var finished = false;
    function done(status) {
      if (finished) return;
      finished = true;
      if (opts.onDone) opts.onDone(status);
    }

    if (!window.tarabasaCanListen || blocked) { done('unavailable'); return; }

    navigator.mediaDevices.getUserMedia({ audio: { channelCount: 1, echoCancellation: true, noiseSuppression: true, autoGainControl: true } }).then(function (stream) {
      var mime = pickMime();
      var recorder;
      try { recorder = mime ? new MediaRecorder(stream, { mimeType: mime }) : new MediaRecorder(stream); }
      catch (e) { stream.getTracks().forEach(function (t) { t.stop(); }); done('unavailable'); return; }

      var chunks = [];
      var heardSomething = false;
      var audioCtx = null, timer = null;

      // A quick look at the loudness while recording: if nothing at all came in, say so at once.
      try {
        var Ctx = window.AudioContext || window.webkitAudioContext;
        audioCtx = new Ctx();
        var analyser = audioCtx.createAnalyser();
        analyser.fftSize = 1024;
        audioCtx.createMediaStreamSource(stream).connect(analyser);
        var buf = new Uint8Array(analyser.fftSize);
        timer = setInterval(function () {
          analyser.getByteTimeDomainData(buf);
          var sum = 0;
          for (var i = 0; i < buf.length; i++) { var v = (buf[i] - 128) / 128; sum += v * v; }
          if (Math.sqrt(sum / buf.length) > SILENCE_RMS) heardSomething = true;
        }, 100);
      } catch (e) { heardSomething = true; /* cannot measure: let the service decide */ }

      recorder.ondataavailable = function (e) { if (e.data && e.data.size) chunks.push(e.data); };
      recorder.onstop = function () {
        if (timer) clearInterval(timer);
        stream.getTracks().forEach(function (t) { t.stop(); });
        try { if (audioCtx) audioCtx.close(); } catch (e) {}

        if (!heardSomething) { done('again'); return; }
        if (opts.onChecking) opts.onChecking();

        var type = recorder.mimeType || mime || 'audio/webm';
        var form = new FormData();
        form.append('word', word);
        form.append('audio', new Blob(chunks, { type: type }), 'word.' + (type.indexOf('mp4') > -1 ? 'mp4' : 'webm'));
        var tokenEl = document.querySelector('meta[name="csrf-token"]');
        var ctrl = ('AbortController' in window) ? new AbortController() : null;
        var abort = ctrl ? setTimeout(function () { ctrl.abort(); }, 30000) : null;

        fetch(CHECK_URL, {
          method: 'POST', body: form, credentials: 'same-origin', signal: ctrl ? ctrl.signal : undefined,
          headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': tokenEl ? tokenEl.content : '' }
        }).then(function (r) { return r.ok ? r.json() : null; })
          .then(function (d) { done(d && (d.status === 'heard' || d.status === 'again') ? d.status : 'unavailable'); })
          .catch(function () { done('unavailable'); })
          .then(function () { if (abort) clearTimeout(abort); });
      };

      if (opts.onListening) opts.onListening();
      recorder.start();
      setTimeout(function () { try { if (recorder.state === 'recording') recorder.stop(); } catch (e) {} }, RECORD_MS);
    }).catch(function () {
      blocked = true;       // permission refused or no microphone: do not ask again this visit
      done('unavailable');
    });
  };

  // Wake the scoring service as the game opens (fire and forget).
  try { fetch(@json(route('learner.warm')), { credentials: 'same-origin' }).catch(function () {}); } catch (e) {}
})();
</script>
@include('partials.wake-from-browser', ['wake' => ['reader']])
