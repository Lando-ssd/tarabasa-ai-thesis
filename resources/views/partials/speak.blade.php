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

  /* Which of the device's English voices to use. A voice does not say whether it is a woman's or a man's, and the old robotic voices of a
     computer sound very different from the natural ones, so the voices are ranked by what their names say: Philippine English first (Microsoft's
     "Rosa", for one), then natural or online voices, then women's names, and known men's names last. The best one wins. */
  function rank(v) {
    var n = (v.name || '').toLowerCase(), lang = v.lang || '', s = 0;
    if (/^en/i.test(lang)) s += 10;
    if (/^en[-_]ph/i.test(lang) || n.indexOf('philippines') > -1) s += 14;
    if (/en[-_]us/i.test(lang)) s += 2;
    if (/natural|online|neural/.test(n)) s += 8;
    if (/rosa\b/.test(n)) s += 6;
    if (/aria|jenny|ava\b|emma|sonia|libby|samantha|zira|susan|allison|karen|tessa|moira|serena|victoria|google us english|female/.test(n)) s += 6;
    if (/david|mark\b|guy\b|ryan|james|george|male\b|alex|fred|daniel|tom\b|eric|brian|christopher|davis|tony/.test(n)) s -= 8;
    return s;
  }

  function pickVoice() {
    if (!synth) return null;
    var voices = (synth.getVoices() || []).filter(function (v) { return v.lang && /^en/i.test(v.lang); });
    if (!voices.length) return null;
    voices.sort(function (a, b) { return rank(b) - rank(a); });
    return voices[0];
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
