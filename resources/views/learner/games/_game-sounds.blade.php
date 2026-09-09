<!--
  Shared sound effects for Practice Games — the first real audio playback
  anywhere in this app. Three real files (not synthesized), each under a
  verified free license (Mixkit Sound Effects Free License — confirmed
  directly from mixkit.co/license/modal/sfxFree/ before using: explicitly
  permits "Video games," "Educational Purposes," and commercial projects,
  no attribution required), self-hosted in public/sounds/ rather than
  hotlinked from Mixkit's own CDN at runtime:
    - correct.mp3          "Correct answer tone"
    - wrong.mp3             "Wrong answer fail notification" — deliberately
                             sourced from Mixkit's "Notification" category,
                             not "Game" (which mostly has harsh buzzers),
                             and played at a lower volume than the other
                             two — this is for young children, a wrong
                             answer should never sound scary or punitive.
    - level-complete.mp3   "Game level completed"

  window.tarabasaPlaySfx(name) is the one shared entry point both games
  call. Wrapped in try/catch + a Promise .catch(): browsers can legally
  block audio.play() when it isn't triggered by a real user gesture (a
  real, common autoplay restriction, not a bug) — every call site here
  fires from inside a real click handler, so this should normally play,
  but if a browser blocks it anyway (or the file fails to load for any
  reason) the game must keep working silently, never throw, never block
  gameplay on sound succeeding.
-->
<audio id="sfxCorrect" preload="auto" src="{{ asset('sounds/correct.mp3') }}"></audio>
<audio id="sfxWrong" preload="auto" src="{{ asset('sounds/wrong.mp3') }}"></audio>
<audio id="sfxLevelComplete" preload="auto" src="{{ asset('sounds/level-complete.mp3') }}"></audio>
<script>
  window.tarabasaPlaySfx = function (name, volume) {
    try {
      const ids = { correct: 'sfxCorrect', wrong: 'sfxWrong', levelComplete: 'sfxLevelComplete' };
      const el = document.getElementById(ids[name]);
      if (!el) return;
      el.volume = volume;
      el.currentTime = 0;
      const playPromise = el.play();
      if (playPromise && typeof playPromise.catch === 'function') {
        playPromise.catch(() => { /* autoplay blocked or file unavailable — fail silently, never break the game */ });
      }
    } catch (e) { /* never let a sound failure interrupt real gameplay */ }
  };
</script>
