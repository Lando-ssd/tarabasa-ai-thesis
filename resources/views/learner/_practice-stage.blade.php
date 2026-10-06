{{--
  The practice stage of a real activity, shown by _reading-scene when $practice is set:
    1. Listen   Tara reads the words aloud (the browser's own voice, slowly), lighting each
                word as she says it. A Slow / Normal switch changes her pace.
    2. Your turn  the child reads it too, up to two times. These tries count for nothing: no
                points, no streak, no level, nothing saved (LearnerReadingService::recordPractice).
    3. Read for real  the scored reading.
  Learning to read starts with hearing it read (modelled reading), then reading with help, then on
  your own. Nothing is ever read aloud to a child in the first-login reading check; this stage is
  only for real activities.

  Expects: $practice ['tryUrl','realUrl','triesLeft','startAt'], $passageText.
  The scene's own panel, mic and Read for real button are reused for stages 2 and 3: this partial only
  adds the Listen panel and switches between the stages.
--}}
@php
    $words = preg_split('/\s+/', trim($passageText), -1, PREG_SPLIT_NO_EMPTY);
    $startAt = $practice['startAt'] ?? 'listen';
    $practiceCfg = ['tryUrl' => $practice['tryUrl'], 'realUrl' => $practice['realUrl'], 'triesLeft' => (int) ($practice['triesLeft'] ?? 0), 'startAt' => $startAt];
@endphp

<div class="stage-steps" id="stageSteps" aria-label="Steps">
  <span class="ss" data-stage="listen">1 Listen</span>
  <span class="ss" data-stage="try">2 Your turn</span>
  <span class="ss" data-stage="real">3 Read for real</span>
</div>

<div id="listenStage" hidden>
  <div class="panel">
    <div class="panel-head">
      <div class="prompt-row"><p class="prompt">Listen to Tara read it first.</p></div>
    </div>
    <div class="passage-card" id="listenText" aria-label="The words Tara will read">@foreach ($words as $w)<span class="lw" data-w>{{ $w }}</span> @endforeach</div>
  </div>
  <div class="listen-controls">
    <button type="button" class="speak-btn big" id="listenPlay" aria-label="Hear Tara read it"><svg viewBox="0 0 256 256" aria-hidden="true" focusable="false"><use href="{{ asset('icons/badges.svg') }}#ph-speaker-high"></use></svg></button>
    <div class="seg-speed" role="group" aria-label="Reading speed">
      <button type="button" data-rate="0.7" aria-pressed="true">Slow</button>
      <button type="button" data-rate="0.95" aria-pressed="false">Normal</button>
    </div>
  </div>
  <p class="stage-hint">Tara reads slowly and shows each word as she says it.</p>
  <div class="stage-actions">
    <button type="button" class="big-btn" id="listenGo">I'm ready to try</button>
    <button type="button" class="skip-link" data-skip-practice>Skip practice and read for real</button>
  </div>
</div>

{{-- Under the mic in stages 2 and 3. --}}
<div class="stage-actions" id="tryExtras" hidden>
  <p class="stage-hint" id="triesHint"></p>
  <button type="button" class="skip-link" data-skip-practice>Skip practice and read for real</button>
</div>

<style>
  .stage-steps{ display:flex; justify-content:center; gap:8px; flex-wrap:wrap; margin:0 0 14px; }
  .ss{ padding:6px 14px 5px; border-radius:999px; background:#eef3f8; color:#7a8b9b; font:600 17px/1.1 var(--font-game); letter-spacing:.03em; }
  .ss.on{ background:var(--navy-900); color:#fff; }
  .ss.past{ background:#dff3ec; color:#137a63; }
</style>

<script>
  // Runs once the whole page is there: the scene's panel and mic are placed after this include.
  document.addEventListener('DOMContentLoaded', function () {
    var cfg = @json($practiceCfg);
    var $ = function (id) { return document.getElementById(id); };
    var listenStage = $('listenStage'), mainPanel = $('mainPanel'), realStage = $('realStage'), realArea = $('realArea');
    var note = $('noCountNote'), tryExtras = $('tryExtras'), triesHint = $('triesHint'), form = $('recordForm');
    var steps = document.querySelectorAll('#stageSteps .ss');
    // The three steps sit with the label and the dots at the top.
    var top = document.querySelector('.progress-wrap');
    if (top) { top.appendChild($('stageSteps')); }
    var words = Array.prototype.slice.call(document.querySelectorAll('#listenText [data-w]'));
    var rate = 0.7, stopTalking = null;

    function stopListening() {
      if (stopTalking) { stopTalking(); stopTalking = null; }
      if (window.tbStopSpeaking) { window.tbStopSpeaking(); }
      words.forEach(function (w) { w.classList.remove('on'); });
      $('listenPlay').classList.remove('playing');
    }

    function show(stage) {
      stopListening();
      listenStage.hidden = stage !== 'listen';
      mainPanel.hidden = stage === 'listen';
      realStage.hidden = stage === 'listen';
      realArea.hidden = stage === 'listen';
      note.hidden = stage !== 'try';
      tryExtras.hidden = stage === 'listen' || stage === 'real';
      // The text-size control and the mic are the same in both reading stages; only where the recording
      // goes (and whether the questions interrupt) changes.
      form.action = stage === 'try' ? cfg.tryUrl : cfg.realUrl;
      window.__tbTry = stage === 'try';
      if (stage === 'try') {
        triesHint.textContent = cfg.triesLeft === 1 ? 'You have 1 practice try left.' : 'You have ' + cfg.triesLeft + ' practice tries.';
      }
      var order = ['listen', 'try', 'real'];
      steps.forEach(function (s) {
        var i = order.indexOf(s.getAttribute('data-stage')), cur = order.indexOf(stage);
        s.className = 'ss' + (i === cur ? ' on' : (i < cur ? ' past' : ''));
      });
    }

    $('listenPlay').addEventListener('click', function () {
      if (!window.tbSpeakWords) { return; }
      stopListening();
      $('listenPlay').classList.add('playing');
      stopTalking = window.tbSpeakWords(words.map(function (w) { return w.textContent; }), {
        rate: rate,
        onword: function (i) { words.forEach(function (w, k) { w.classList.toggle('on', k === i); }); },
        onend: function () { words.forEach(function (w) { w.classList.remove('on'); }); $('listenPlay').classList.remove('playing'); }
      });
    });
    document.querySelectorAll('.seg-speed button').forEach(function (b) {
      b.addEventListener('click', function () {
        rate = parseFloat(b.getAttribute('data-rate'));
        document.querySelectorAll('.seg-speed button').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
      });
    });
    $('listenGo').addEventListener('click', function () { show(cfg.triesLeft > 0 ? 'try' : 'real'); });
    document.querySelectorAll('[data-skip-practice]').forEach(function (b) { b.addEventListener('click', function () { show('real'); }); });

    // No voice on this device: the Listen stage cannot work, so go straight to trying.
    var start = cfg.startAt;
    if (start === 'listen' && !window.tbCanSpeak) { start = cfg.triesLeft > 0 ? 'try' : 'real'; }
    if (start === 'try' && cfg.triesLeft <= 0) { start = 'real'; }
    show(start);
  });
</script>
