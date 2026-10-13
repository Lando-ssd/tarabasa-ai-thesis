{{--
  The animations the Practice Games use: the same Lottie files the Home page, the reading screens and the result screens use
  (public/animations/learner), so a game looks like the rest of the Learner side. Tara's head looks around at the top of a game, and
  a happy star plays when a round or a game is finished.

    window.tarabasaAnimate(container, name)   draws 'tara-owl-head' or 'star-happy' (or any file in animations/learner) into the
                                              element; returns the animation, or null if it could not be drawn

  The animation is only decoration: with reduced motion it holds still on its first frame, and a game works without it if the
  file or the library cannot be loaded.
--}}
<script src="{{ asset('vendor/lottie-web-5.12.2.min.js') }}"></script>
<script>
  (function () {
    var BASE = @json(asset('animations/learner')) + '/';
    var reduce = false;
    try { reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches; } catch (e) {}

    window.tarabasaAnimate = function (container, name) {
      try {
        if (!container || !window.lottie) return null;
        container.innerHTML = '';
        var anim = lottie.loadAnimation({ container: container, renderer: 'svg', loop: true, autoplay: !reduce, path: BASE + name + '.json' });
        if (reduce) { anim.addEventListener('DOMLoaded', function () { anim.goToAndStop(0, true); }); }
        return anim;
      } catch (e) { return null; }
    };
  })();
</script>
