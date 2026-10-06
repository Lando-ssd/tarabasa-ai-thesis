{{--
  Read-aloud for any screen, using the browser's own voice (SpeechSynthesis). Nothing is sent to a
  server, so no child's voice or text leaves the device (RA 10173), and there is nothing to install.

  Put a button on the page with data-speak="Text to read" (or data-speak-from="#elementId" to read
  that element's text). Buttons are hidden when the browser has no voice.

  window.tbSpeak(text, { rate, onend }) reads text slowly (0.85 by default, young learners and low
  literacy adults). window.tbSpeakWords(words, { onword, onend }) reads a list of words one at a
  time and reports each one, which the practice stage uses to highlight the word being said.
  window.tbStopSpeaking() stops. Nothing here ever throws: a missing voice just does nothing.
--}}
<script>
(function () {
  var synth = ('speechSynthesis' in window) ? window.speechSynthesis : null;
  window.tbCanSpeak = !!synth;

  function pickVoice() {
    if (!synth) return null;
    var voices = synth.getVoices() || [];
    var pref = ['en-PH', 'en-US', 'en-GB', 'en'];
    for (var i = 0; i < pref.length; i++) {
      for (var j = 0; j < voices.length; j++) {
        if (voices[j].lang && voices[j].lang.indexOf(pref[i]) === 0) return voices[j];
      }
    }
    return null;
  }

  window.tbStopSpeaking = function () { try { if (synth) synth.cancel(); } catch (e) {} };

  window.tbSpeak = function (text, opts) {
    opts = opts || {};
    if (!synth || !text) { if (opts.onend) opts.onend(); return; }
    try {
      synth.cancel();
      var u = new SpeechSynthesisUtterance(String(text));
      var v = pickVoice();
      if (v) { u.voice = v; u.lang = v.lang; } else { u.lang = 'en-US'; }
      u.rate = opts.rate || 0.85;
      u.pitch = 1.05;
      if (opts.onend) { u.onend = opts.onend; u.onerror = opts.onend; }
      synth.speak(u);
    } catch (e) { if (opts.onend) opts.onend(); }
  };

  window.tbSpeakWords = function (words, opts) {
    opts = opts || {};
    if (!synth || !words || !words.length) { if (opts.onend) opts.onend(); return; }
    var i = 0, stopped = false;
    function next() {
      if (stopped || i >= words.length) { if (opts.onend) opts.onend(); return; }
      var idx = i++;
      if (opts.onword) opts.onword(idx);
      window.tbSpeak(words[idx], { rate: opts.rate || 0.75, onend: function () { setTimeout(next, 120); } });
    }
    next();
    return function stop() { stopped = true; window.tbStopSpeaking(); };
  };

  document.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('[data-speak],[data-speak-from]') : null;
    if (!b) return;
    e.preventDefault();
    var text = b.getAttribute('data-speak');
    if (!text) {
      var src = document.querySelector(b.getAttribute('data-speak-from'));
      text = src ? src.textContent.replace(/\s+/g, ' ').trim() : '';
    }
    window.tbSpeak(text);
  });

  function hideIfUnsupported() {
    if (window.tbCanSpeak) return;
    document.querySelectorAll('[data-speak],[data-speak-from]').forEach(function (el) { el.hidden = true; });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', hideIfUnsupported);
  else hideIfUnsupported();

  // Some browsers load their voice list late; ask once so the first press already has a voice.
  if (synth && synth.addEventListener) synth.addEventListener('voiceschanged', function () {});
})();
</script>
