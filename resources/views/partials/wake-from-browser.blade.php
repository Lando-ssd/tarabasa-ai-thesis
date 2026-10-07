{{--
  Wakes the sleeping teammate services from the visitor's own browser.

  Why the BROWSER: the services sleep on free hosting after about 15 minutes. A request from another Render
  service (this app's server) to a sleeping one is turned away at once with "429 Too Many Requests" and does NOT
  wake it (seen on the live site: the app's check got an instant 429, and the same page asked from a normal
  computer a moment later was held 23 seconds and answered). A request from a normal connection is held while
  the service starts and then answered. So the page asks each service's health page itself, once, quietly, while
  the child is reading the screen; by the time a recording is sent, the service is awake.

  What is sent: one bare GET of the service's /health page. Nothing about the child, no cookie, no recording, no
  key. The service sees the visitor's address, as any web page does. Services are only the ones named in $wake
  (any of 'reader', 'recommender', 'generator'); the addresses are public, the keys are never put on a page.
  Quiet and safe: nothing is shown and nothing can fail. At most once every 2 minutes per service per tab (a failed try is followed by another ask).
--}}
@php
    $urls = collect([
        'reader' => config('services.reading_ai.url'),
        'recommender' => config('services.adaptive_recommender.url'),
        'generator' => config('services.activity_ai.url'),
    ])->only($wake ?? [])->filter()->map(fn ($u) => rtrim($u, '/').'/health');
@endphp
@if ($urls->isNotEmpty())
<script>
  (function () {
    var targets = @json($urls);
    var now = Date.now();
    Object.keys(targets).forEach(function (name) {
      var key = 'tb-wake:' + name, last = 0;
      try { last = parseInt(sessionStorage.getItem(key) || '0', 10) || 0; } catch (e) {}
      if (now - last < 2 * 60 * 1000) { return; }
      try { sessionStorage.setItem(key, String(now)); } catch (e) {}
      try { fetch(targets[name], { mode: 'no-cors', cache: 'no-store', credentials: 'omit', referrerPolicy: 'no-referrer' }).catch(function () {}); } catch (e) {}
    });
  })();
</script>
@endif
