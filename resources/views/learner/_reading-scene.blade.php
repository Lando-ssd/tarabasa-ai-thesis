{{--
  The screen where a child reads something aloud. A full document (this app's
  Learner screens are standalone), shared by three views so they stay one design:
    diagnostic-passage  the first-login reading check (letters or a short passage)
    activity-found      a real activity from the teacher or the repository
    bookshelf-reread    reading a finished book again, just for fun

  Plain white page. Tara peeks over the top of a light blue panel that holds the
  direction and the words (or letters); the clay orange mic and stop buttons sit
  under it. Same look as the intro and the "Great job" screens.

  Expects: $pageTitle, $prompt, $recordAction, $learner.
  Words to read, one of: $passageText (a passage; shows the text size control) or
    $letters (array of ['upper','lower'], shown as big tiles).
  Optional:
    $eyebrow            small label above Tara ("Passage 2", an activity title)
    $dotsDone,$dotsTotal, $dotsCurrent   progress dots (the reading check)
    $pill               a small note under the label ("Free practice ...")
    $micLabel,$doneLabel wording for the shared recording widget
    $backHref,$backLabel a quiet link at the bottom
    $quizQuestions      comprehension questions, asked after the recording and
                        submitted with it (see the script at the bottom)
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $pageTitle }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
{{-- Lexend is used ONLY for the words and letters a child actually reads
     aloud. The game face (Barlow Semi Condensed when Bahnschrift is missing)
     carries the directions and buttons, as on the intro screen. --}}
<link href="https://fonts.googleapis.com/css2?family=Barlow+Semi+Condensed:wght@500;600;700&family=Inter:wght@400;500;600;700;800&family=Lexend:wght@500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/lottie-web@5.12.2/build/player/lottie.min.js"></script>
<style>
  :root{
    --blue-500:#1c7ed6; --blue-700:#0a3d73;
    --navy-900:#131f2b; --slate-600:#5b6b7a;
    --owl-orange-500:#ef8d2a; --owl-orange-600:#dd7014; --clay-yellow:#ffcf6e;
    --teal:#2bb89c;
    --line:#dbe7f3; --surface:#ffffff; --bg-0:#f5f9ff;
    --panel:#f1f7ff; --panel-line:#d3e3f4; --lip:#c3d8ee;
    --amber:#c9820b; --amber-bg:#fef6e6;
    --danger:#d64545; --danger-bg:#fdecec;
    --font-game:'Bahnschrift SemiCondensed','Bahnschrift','Barlow Semi Condensed',sans-serif;
    /* The text-size control multiplies this with calc(), so its steps stay
       proportional at every breakpoint. */
    --passage-font-base:23px;
    --peek-w:210px;
  }
  @media (min-width:700px){ :root{ --passage-font-base:26px; --peek-w:250px; } }
  @media (min-width:1024px){ :root{ --passage-font-base:28px; --peek-w:290px; } }

  *{ box-sizing:border-box; }
  html,body{ margin:0; padding:0; background:#ffffff; }
  body{
    min-height:100vh; font-family:'Inter',sans-serif; color:var(--navy-900);
    display:flex; align-items:safe center; justify-content:center; padding:24px 20px 40px;
  }
  .wrap{ width:100%; max-width:460px; }
  @media (min-width:700px){ .wrap{ max-width:640px; } }
  @media (min-width:1024px){ .wrap{ max-width:780px; } }

  /* ---- label and progress ---- */
  .progress-wrap{ margin-bottom:14px; }
  .progress-label{
    text-align:center; font:700 18px/1.15 var(--font-game); letter-spacing:.06em; text-transform:uppercase; color:var(--slate-600);
    margin:0 auto 10px; max-width:30em; text-wrap:balance;
  }
  @media (min-width:700px){ .progress-label{ font-size:20px; } }
  .progress-dots{ display:flex; gap:10px; justify-content:center; }
  .pdot{ width:12px; height:12px; border-radius:50%; background:var(--line); }
  @media (min-width:700px){ .pdot{ width:14px; height:14px; } }
  .pdot.done{ background:var(--teal); }
  .pdot.current{ background:var(--owl-orange-500); transform:scale(1.3); }
  .pill-note{
    display:table; margin:10px auto 0; padding:7px 18px 6px; border-radius:999px; background:#fff3e2; color:#a8570b;
    font:700 16px/1.1 var(--font-game); letter-spacing:.04em; box-shadow:0 3px 0 #f3d3a4;
  }

  /* ---- Tara, peeking over the top of the panel ---- */
  .peek{ position:relative; z-index:0; width:var(--peek-w); aspect-ratio:700 / 458; margin:0 auto calc(var(--peek-w) * -0.13); }
  #taraHead{ position:absolute; inset:0; }

  /* ---- the panel that holds the words or letters ---- */
  .panel{
    position:relative; z-index:1; background:var(--panel); border:2px solid var(--panel-line); border-radius:30px;
    box-shadow:0 8px 0 var(--lip); padding:30px 22px 26px;
  }
  @media (min-width:700px){ .panel{ padding:38px 36px 32px; border-radius:34px; } }

  .panel-head{ display:flex; flex-direction:column; align-items:center; gap:12px; margin-bottom:18px; text-align:center; }
  .prompt{ margin:0; font:600 22px/1.25 var(--font-game); color:#2f455b; }
  @media (min-width:700px){
    .prompt{ font-size:26px; }
    .panel-head.has-control{ flex-direction:row; justify-content:space-between; text-align:left; gap:20px; }
  }
  .panel-head .font-control{ margin:0; }

  .passage-card{
    background:#ffffff; border:2px solid var(--panel-line); border-radius:22px; padding:20px 22px;
    font-family:'Lexend',sans-serif; font-size:var(--passage-font-base); font-weight:500; line-height:1.7; color:var(--navy-900);
    text-align:left; overflow-wrap:break-word; word-break:break-word; transition:font-size .15s ease;
  }
  @media (min-width:700px){ .passage-card{ padding:26px 30px; border-radius:26px; } }

  /* ---- the letters rung: six big tiles, in reading order ---- */
  .letters{ display:grid; grid-template-columns:repeat(3, 1fr); gap:12px; margin:0; padding:0; list-style:none; }
  @media (min-width:700px){ .letters{ grid-template-columns:repeat(6, 1fr); gap:12px; } }
  .letter{
    background:#ffffff; border:2px solid var(--panel-line); border-radius:22px; box-shadow:0 5px 0 var(--lip);
    padding:16px 4px 14px; text-align:center; font-family:'Lexend',sans-serif; line-height:1; white-space:nowrap;
  }
  .letter .up{ font-size:56px; font-weight:700; color:var(--navy-900); }
  .letter .low{ font-size:38px; font-weight:500; color:var(--blue-500); margin-left:2px; }
  @media (min-width:700px){ .letter .up{ font-size:46px; } .letter .low{ font-size:32px; } }
  @media (min-width:1024px){ .letter .up{ font-size:54px; } .letter .low{ font-size:38px; } }

  /* ---- the recording controls (classes the shared widget expects) ---- */
  .rec-area{ margin-top:34px; }
  .step{ display:none; }
  .step.active{ display:block; }

  .mic-zone{ display:flex; flex-direction:column; align-items:center; gap:12px; margin-bottom:14px; }
  .mic-btn{
    width:96px; height:96px; border-radius:50%; border:none; cursor:pointer;
    background:linear-gradient(180deg,#f9a544,#ee8a26);
    box-shadow:0 7px 0 #b4560b, 0 18px 24px -10px rgba(180,86,11,.65), inset 0 2px 0 rgba(255,255,255,.45);
    display:flex; align-items:center; justify-content:center; transition:transform .08s ease, box-shadow .08s ease;
  }
  .mic-btn svg{ width:36px; height:36px; }
  @media (min-width:700px){ .mic-btn{ width:112px; height:112px; } .mic-btn svg{ width:42px; height:42px; } }
  .mic-btn:hover{ transform:translateY(-1px); }
  .mic-btn:active{ transform:translateY(4px); box-shadow:0 3px 0 #b4560b, 0 8px 12px -6px rgba(180,86,11,.6), inset 0 2px 0 rgba(255,255,255,.4); }
  .mic-btn:focus-visible, .big-btn:focus-visible, .back-link:focus-visible{ outline:4px solid var(--blue-500); outline-offset:4px; }
  .mic-btn.listening{
    cursor:default; background:linear-gradient(180deg,#ff7d6d,#e0483a);
    animation:micRing 1.2s ease-out infinite;
  }
  .mic-btn.listening:hover{ transform:none; }
  @keyframes micRing{
    0%{ box-shadow:0 7px 0 #a92b20, 0 0 0 0 rgba(224,72,58,.45), inset 0 2px 0 rgba(255,255,255,.4); }
    70%{ box-shadow:0 7px 0 #a92b20, 0 0 0 20px rgba(224,72,58,0), inset 0 2px 0 rgba(255,255,255,.4); }
    100%{ box-shadow:0 7px 0 #a92b20, 0 0 0 0 rgba(224,72,58,0), inset 0 2px 0 rgba(255,255,255,.4); }
  }
  .mic-label{ margin:0; font:600 21px/1.3 var(--font-game); color:#3f566d; text-align:center; }
  @media (min-width:700px){ .mic-label{ font-size:24px; } }
  .timer-label{ font:700 28px/1 var(--font-game); color:var(--navy-900); }
  @media (min-width:700px){ .timer-label{ font-size:32px; } }
  .timer-label.warn{ color:var(--danger); }

  .loading-spin{
    width:48px; height:48px; border-radius:50%; margin:0 auto 12px; border:5px solid var(--line); border-top-color:var(--owl-orange-500);
    animation:spin .9s linear infinite;
  }
  @keyframes spin{ to{ transform:rotate(360deg); } }
  #stepChecking{ text-align:center; }

  .note-banner{
    display:flex; gap:10px; text-align:left; border-radius:16px; padding:14px 16px; margin-bottom:14px;
    font:600 18px/1.4 var(--font-game);
  }
  .note-banner.amber{ background:var(--amber-bg); border:1px solid var(--amber); color:var(--navy-900); }
  .note-banner.danger{ background:var(--danger-bg); border:1px solid var(--danger); color:var(--danger); }

  .big-btn{
    display:block; width:100%; max-width:420px; margin:0 auto; padding:17px 24px 15px; border:none; border-radius:20px;
    font:600 24px/1.1 var(--font-game); letter-spacing:.06em; text-transform:uppercase; text-align:center; color:#fff; cursor:pointer;
    background:linear-gradient(180deg,#f9a544,#ee8a26); text-shadow:0 1px 0 rgba(0,0,0,.2);
    box-shadow:0 6px 0 #b4560b, 0 16px 22px -10px rgba(180,86,11,.6), inset 0 2px 0 rgba(255,255,255,.45);
    transition:transform .08s ease, box-shadow .08s ease;
  }
  .big-btn:hover{ transform:translateY(-1px); }
  .big-btn:active{ transform:translateY(4px); box-shadow:0 2px 0 #b4560b, 0 6px 10px -6px rgba(180,86,11,.6), inset 0 2px 0 rgba(255,255,255,.4); }
  .big-btn:disabled{ opacity:.6; cursor:not-allowed; transform:none; }

  /* ---- the comprehension questions, asked after the reading ---- */
  .quiz-title{ margin:0 0 16px; font:700 26px/1.2 var(--font-game); color:#2f455b; text-align:center; }
  .quiz-question{ text-align:left; margin-bottom:22px; }
  .quiz-question p{ margin:0 0 10px; font:600 22px/1.3 var(--font-game); color:var(--navy-900); }
  .quiz-choice{
    display:flex; align-items:center; gap:14px; padding:14px 18px; margin-bottom:10px; cursor:pointer;
    background:#ffffff; border:2px solid var(--panel-line); border-radius:18px; box-shadow:0 4px 0 var(--lip);
    transition:border-color .12s ease, background-color .12s ease, transform .08s ease;
  }
  .quiz-choice:hover{ border-color:var(--owl-orange-500); }
  .quiz-choice:has(input:checked){ border-color:var(--owl-orange-500); background:#fff3e2; box-shadow:0 4px 0 #f3d3a4; }
  .quiz-choice:active{ transform:translateY(2px); }
  .quiz-choice input{ width:22px; height:22px; flex-shrink:0; accent-color:var(--owl-orange-600); }
  .quiz-choice span{ font:500 21px/1.3 var(--font-game); color:var(--navy-900); }
  .quiz-note{ display:none; margin:0 0 14px; text-align:center; font:600 19px/1.3 var(--font-game); color:#a8570b; }
  .quiz-note.show{ display:block; }
  @media (min-width:700px){
    .quiz-question p{ font-size:25px; }
    .quiz-choice span{ font-size:24px; }
    .quiz-choice input{ width:26px; height:26px; }
  }

  /* ---- the way out ---- */
  .back-link{
    display:table; margin:26px auto 0; padding:12px 26px 10px; border-radius:16px; text-decoration:none;
    font:600 20px/1 var(--font-game); letter-spacing:.05em; text-transform:uppercase; color:var(--slate-600);
    background:#ffffff; border:2px solid var(--line); box-shadow:0 4px 0 #e3ebf3; transition:transform .08s ease, box-shadow .08s ease;
  }
  .back-link:hover{ transform:translateY(-1px); }
  .back-link:active{ transform:translateY(3px); box-shadow:0 1px 0 #e3ebf3; }

  @media (prefers-reduced-motion:reduce){
    .mic-btn, .big-btn, .passage-card, .quiz-choice, .back-link{ transition:none; }
    .mic-btn.listening{ animation:none; }
    .loading-spin{ animation-duration:2.4s; }
  }
</style>
</head>
<body>
<div class="wrap">
  @if (! empty($eyebrow) || isset($dotsTotal) || ! empty($pill))
    <div class="progress-wrap">
      @if (! empty($eyebrow))
        <div class="progress-label">{{ $eyebrow }}</div>
      @endif
      @isset($dotsTotal)
        <div class="progress-dots">
          @for ($i = 1; $i <= $dotsTotal; $i++)
            <div class="pdot {{ $i < $dotsCurrent ? 'done' : ($i === $dotsCurrent ? 'current' : '') }}"></div>
          @endfor
        </div>
      @endisset
      @if (! empty($pill))
        <div class="pill-note">{{ $pill }}</div>
      @endif
    </div>
  @endif

  <div class="peek"><div id="taraHead" role="img" aria-label="Tara the owl, looking at the words"></div></div>

  <div class="panel">
    @if (! empty($letters))
      <div class="panel-head">
        <p class="prompt">{{ $prompt }}</p>
      </div>
      <ul class="letters" aria-label="Letters to name">
        @foreach ($letters as $letter)
          <li class="letter" aria-label="Letter {{ $letter['upper'] }}"><span class="up">{{ $letter['upper'] }}</span><span class="low">{{ $letter['lower'] }}</span></li>
        @endforeach
      </ul>
    @else
      <div class="panel-head has-control">
        <p class="prompt">{{ $prompt }}</p>
        @include('learner._reading-font-control', ['initialStep' => $learner->effectiveReadingFontStep()])
      </div>
      <div class="passage-card">{{ $passageText }}</div>
    @endif
  </div>

  <div class="rec-area">
    @if (session('error') || $errors->any())
      <div class="note-banner danger">{{ $errors->first() ?: session('error') }}</div>
    @endif

    @include('learner._recording-widget', array_filter([
      'recordAction' => $recordAction,
      'micLabel' => $micLabel ?? null,
      'doneLabel' => $doneLabel ?? null,
    ], fn ($value) => $value !== null))

    @if (! empty($quizQuestions))
      <div class="step" id="stepComprehensionQuiz">
        <p class="quiz-title">Now let's see what you remember!</p>
        @foreach ($quizQuestions as $i => $q)
          <div class="quiz-question">
            <p>{{ $q['question'] }}</p>
            @foreach ($q['choices'] as $c => $choice)
              <label class="quiz-choice">
                <input type="radio" name="answers[{{ $i }}]" value="{{ $choice }}" form="recordForm" data-question-index="{{ $i }}">
                <span>{{ $choice }}</span>
              </label>
            @endforeach
          </div>
        @endforeach
        <p class="quiz-note" id="quizNote">Please answer every question first!</p>
        <button type="button" class="big-btn" id="quizSubmitBtn">Submit My Answers</button>
      </div>
    @endif
  </div>

  @if (! empty($backHref))
    <a href="{{ $backHref }}" class="back-link">{{ $backLabel ?? 'Back to My Dashboard' }}</a>
  @endif
</div>

<script>
  // Tara's head: a five second loop of looking around and blinking. With
  // reduced motion she just holds still on the first frame.
  (function () {
    try {
      var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      var head = lottie.loadAnimation({
        container: document.getElementById('taraHead'), renderer: 'svg', loop: true, autoplay: !reduce,
        path: @json(asset('animations/learner/tara-owl-head.json'))
      });
      if (reduce) { head.addEventListener('DOMLoaded', function () { head.goToAndStop(0, true); }); }
    } catch (e) { /* the page works without her */ }
  })();
</script>

@if (! empty($quizQuestions))
<script>
  // Inserted between "done reading" and the real form submit: Reading-api needs
  // comprehension_score in the SAME /analyze call as the audio, so this can't wait
  // until after the results. The recording widget calls this (see its own comment)
  // right after the recorded file is attached to the form; the answers submit with
  // it through each radio's form="recordForm".
  window.tarabasaBeforeSubmit = function () {
    document.querySelectorAll('.step').forEach(function (el) {
      if (el.id !== 'stepComprehensionQuiz') {
        el.classList.remove('active');
        el.style.display = 'none';
      }
    });
    var quizStep = document.getElementById('stepComprehensionQuiz');
    quizStep.style.display = 'block';
    quizStep.classList.add('active');
  };

  document.getElementById('quizSubmitBtn').addEventListener('click', function () {
    var answered = {};
    document.querySelectorAll('#stepComprehensionQuiz input[type="radio"]').forEach(function (radio) {
      if (radio.checked) answered[radio.name] = true;
    });
    var totalQuestions = new Set([].map.call(document.querySelectorAll('#stepComprehensionQuiz input[type="radio"]'), function (r) { return r.name; })).size;

    if (Object.keys(answered).length < totalQuestions) {
      document.getElementById('quizNote').classList.add('show');
      return;
    }

    document.getElementById('quizNote').classList.remove('show');
    window.tarabasaSubmitRecording();
  });
</script>
@endif
</body>
</html>
