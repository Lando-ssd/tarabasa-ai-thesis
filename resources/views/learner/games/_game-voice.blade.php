{{--
  Voice for the Practice Games: hear something read aloud, and read it aloud yourself.

  Hearing uses the browser's own read-aloud voice (partials/speak), so nothing about it leaves the
  device. Reading aloud is always practice:
    - one press, one recording, never a live stream. A single word is recorded for about three
      seconds; a row of words or a sentence is recorded for as long as it needs (a little under two
      seconds a word) and stops by itself when the child pauses after speaking, or when they press
      the button again
    - a quick check on the device first: if the microphone heard nothing at all, the clip is not
      even sent (the child is simply asked to try again)
    - the clip goes to the same service that scores readings (GameController::checkText), which
      answers only "heard" or "again" (and which of the words were heard), never "wrong", and
      keeps nothing
    - a microphone that is blocked, missing or unreachable just means the game carries on without
      the speaking step

  window.tarabasaSayLetter(letter)   read a letter's NAME aloud ("ay", "bee"; reading the bare
                                     letter would give a word like "a" or "I")
  window.tarabasaSayWord(word)       read a word aloud, slowly
  window.tarabasaSayText(text)       read a row of words or a sentence aloud, slowly
  window.tarabasaCanListen           true when this browser can record at all
  window.tarabasaListenForText(text, kind, { onListening(control), onChecking, onDone })
      kind is 'word' | 'row' | 'letters' | 'sentence'. onListening gets { stop() } so the page can
      let the child press the button again to finish. onDone gets 'heard' | 'again' | 'unavailable'
      and an array saying, for each word asked, whether it was heard.
  window.tarabasaListenForWord(word, { onListening, onChecking, onDone })
      the single word version Balloon Pop uses; onDone gets only the status.
  Wakes the scoring service as the game opens (ReadingAiClient::wake), so the first thing read aloud
  is not the one that waits for it to start.

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
  window.tarabasaSayText = function (text) {
    if (text) window.tbSpeak(String(text), { rate: 0.8 });
  };

  var SILENCE_RMS = 0.02;       // below this the microphone heard nothing
  var PAUSE_MS = 1600;          // a row or a sentence stops this long after the voice stops
  var CHECK_URL = @json(route('learner.games.check-text'));
  window.tarabasaCanListen = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.MediaRecorder);
  var blocked = false;

  function pickMime() {
    var list = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4'];
    for (var i = 0; i < list.length; i++) {
      try { if (MediaRecorder.isTypeSupported(list[i])) return list[i]; } catch (e) {}
    }
    return '';
  }

  window.tarabasaListenForText = function (text, kind, opts) {
    opts = opts || {};
    var finished = false;
    var wordCount = String(text).trim().split(/\s+/).length;
    // One word: three seconds. More: a little under two seconds a word on top of a short start, and never more than 18 seconds.
    var maxMs = kind === 'word' ? 3000 : Math.min(18000, 3500 + 1800 * wordCount);
    var pauseStops = kind !== 'word';

    function done(status, matched) {
      if (finished) return;
      finished = true;
      if (opts.onDone) opts.onDone(status, matched || []);
    }

    if (!window.tarabasaCanListen || blocked) { done('unavailable'); return; }

    navigator.mediaDevices.getUserMedia({ audio: { channelCount: 1, echoCancellation: true, noiseSuppression: true, autoGainControl: true } }).then(function (stream) {
      var mime = pickMime();
      var recorder;
      try { recorder = mime ? new MediaRecorder(stream, { mimeType: mime }) : new MediaRecorder(stream); }
      catch (e) { stream.getTracks().forEach(function (t) { t.stop(); }); done('unavailable'); return; }

      var chunks = [];
      var heardSomething = false;
      var lastVoiceAt = 0;
      var startedAt = Date.now();
      var audioCtx = null, timer = null;

      function stop() { try { if (recorder.state === 'recording') recorder.stop(); } catch (e) {} }

      // A quick look at the loudness while recording: if nothing at all came in, say so at once, and for a row or a sentence
      // stop once the voice has gone quiet for a moment.
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
          var now = Date.now();
          if (Math.sqrt(sum / buf.length) > SILENCE_RMS) { heardSomething = true; lastVoiceAt = now; }
          else if (pauseStops && heardSomething && now - lastVoiceAt > PAUSE_MS && now - startedAt > 1800) { stop(); }
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
        form.append('text', text);
        form.append('kind', kind);
        form.append('audio', new Blob(chunks, { type: type }), 'reading.' + (type.indexOf('mp4') > -1 ? 'mp4' : 'webm'));
        var tokenEl = document.querySelector('meta[name="csrf-token"]');
        var ctrl = ('AbortController' in window) ? new AbortController() : null;
        var abort = ctrl ? setTimeout(function () { ctrl.abort(); }, 45000) : null;

        fetch(CHECK_URL, {
          method: 'POST', body: form, credentials: 'same-origin', signal: ctrl ? ctrl.signal : undefined,
          headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': tokenEl ? tokenEl.content : '' }
        }).then(function (r) { return r.ok ? r.json() : null; })
          .then(function (d) {
            var ok = d && (d.status === 'heard' || d.status === 'again');
            done(ok ? d.status : 'unavailable', ok && d.matched ? d.matched : []);
          })
          .catch(function () { done('unavailable'); })
          .then(function () { if (abort) clearTimeout(abort); });
      };

      if (opts.onListening) opts.onListening({ stop: stop });
      recorder.start();
      setTimeout(stop, maxMs);
    }).catch(function () {
      blocked = true;       // permission refused or no microphone: do not ask again this visit
      done('unavailable');
    });
  };

  // Balloon Pop's one word.
  window.tarabasaListenForWord = function (word, opts) {
    opts = opts || {};
    window.tarabasaListenForText(word, 'word', {
      onListening: opts.onListening,
      onChecking: opts.onChecking,
      onDone: function (status) { if (opts.onDone) opts.onDone(status); }
    });
  };

  // Wake the scoring service as the game opens (fire and forget).
  try { fetch(@json(route('learner.warm')), { credentials: 'same-origin' }).catch(function () {}); } catch (e) {}
})();
</script>
@include('partials.wake-from-browser', ['wake' => ['reader']])
