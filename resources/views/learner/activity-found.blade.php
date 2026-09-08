<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $activity->title }} — TaraBasa AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
{{-- Lexend is used ONLY for the passage text a child actually reads
     aloud (studied for reading fluency) — every other UI element on
     this screen (buttons, headers, the size control) stays Baloo 2/
     Inter, so branding is unaffected. --}}
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Inter:wght@400;500;600;700;800&family=Lexend:wght@500;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --sky-100:#dcedff;
    --blue-500:#1c7ed6; --blue-700:#0a3d73;
    --navy-900:#131f2b; --slate-600:#5b6b7a;
    --owl-orange-500:#ef8d2a; --owl-orange-600:#dd7014; --clay-yellow:#ffcf6e;
    --line:#e3ebf2; --surface:#ffffff; --bg-0:#f6faff;
    --amber:#c9820b; --amber-bg:#fef6e6;
    --danger:#d64545; --danger-bg:#fdecec;
    /* The passage text's own base size per breakpoint — the +/- control
       (see _reading-font-control.blade.php) multiplies THIS value via
       calc(), so its 5 steps stay proportional at every viewport tier
       instead of needing a separate step table per breakpoint. */
    --passage-font-base:23px;
  }
  @media (min-width:700px){ :root{ --passage-font-base:26px; } }
  @media (min-width:1024px){ :root{ --passage-font-base:28px; } }
  *{box-sizing:border-box;} html,body{margin:0;padding:0;}
  body{
    min-height:100vh; font-family:'Inter',sans-serif; color:var(--navy-900);
    background: radial-gradient(1200px 700px at 90% -10%, var(--sky-100), transparent 55%),
                radial-gradient(900px 600px at 0% 100%, #ffe9c9, transparent 50%), var(--bg-0);
    background-attachment:fixed;
    display:flex; align-items:safe center; justify-content:center; padding:24px;
  }
  /* Responsive layout — three tiers, mobile-first. Below, this is the
     phone-width baseline (unchanged from before). ≥700px (tablet) and
     ≥1024px (laptop/desktop) widen the card and scale up touch targets
     and reading text so a larger screen isn't just a mobile layout
     floating in unused space — matches this app's own Teacher/Parent
     shells, which already cap around 820px on desktop. */
  .wrap{ width:100%; max-width:460px; }
  @media (min-width:700px){ .wrap{ max-width:640px; } }
  @media (min-width:1024px){ .wrap{ max-width:780px; } }
  .card{
    position:relative; overflow:hidden;
    background:var(--surface); border-radius:30px; padding:32px 28px; text-align:center;
    box-shadow:0 30px 60px -28px rgba(15,60,110,0.25);
  }
  @media (min-width:700px){ .card{ padding:44px 48px; border-radius:34px; } }
  @media (min-width:1024px){ .card{ padding:56px 64px; border-radius:38px; } }
  /* Claymorphism accents, matching the Learner tile on the homepage —
     soft, playful, never a bare white card for a young reader. */
  .clay-blob{ position:absolute; border-radius:50%; pointer-events:none; z-index:0; }
  .clay-blob.b1{ width:150px;height:150px; top:-60px; right:-50px; background:radial-gradient(circle, rgba(255,207,110,0.35), transparent 70%); }
  .clay-blob.b2{ width:120px;height:120px; bottom:-40px; left:-40px; background:radial-gradient(circle, rgba(28,126,214,0.12), transparent 70%); }
  .card > *{ position:relative; z-index:1; }
  .mascot{
    width:88px;height:88px;border-radius:28px; margin:0 auto 14px; font-size:44px;
    background:linear-gradient(155deg, var(--clay-yellow), var(--owl-orange-600));
    display:flex;align-items:center;justify-content:center; box-shadow:0 16px 28px -12px rgba(221,112,20,0.5);
    animation:bob 2.4s ease-in-out infinite;
  }
  @media (min-width:700px){ .mascot{ width:108px; height:108px; font-size:54px; border-radius:32px; } }
  @keyframes bob{ 0%,100%{transform:translateY(0);} 50%{transform:translateY(-8px);} }
  h1{ font-family:'Baloo 2',sans-serif; font-size:25px; font-weight:700; margin:0 0 6px; }
  @media (min-width:700px){ h1{ font-size:30px; } }
  .sub{ font-size:18px; color:var(--slate-600); font-weight:600; margin:0 0 22px; line-height:1.55; }
  @media (min-width:700px){ .sub{ font-size:20px; } }
  .passage-card{
    background:var(--bg-0); border:2px solid var(--line); border-radius:22px; padding:22px; margin-bottom:22px;
    font-family:'Lexend',sans-serif; font-size:var(--passage-font-base); font-weight:500; line-height:1.7; color:var(--navy-900);
    text-align:left; overflow-wrap:break-word; word-break:break-word; transition:font-size .15s ease;
  }
  @media (min-width:700px){ .passage-card{ padding:28px 32px; border-radius:26px; } }

  .step{ display:none; }
  .step.active{ display:block; }

  .mic-zone{ display:flex; flex-direction:column; align-items:center; gap:14px; margin-bottom:16px; }
  .mic-btn{
    width:88px;height:88px;border-radius:50%; border:none; cursor:pointer;
    background:linear-gradient(155deg, #ff8a65, var(--owl-orange-600));
    box-shadow:0 16px 28px -12px rgba(221,112,20,0.55), 0 0 0 14px rgba(239,141,42,0.12);
    display:flex;align-items:center;justify-content:center; transition:transform .2s cubic-bezier(.34,1.56,.64,1);
  }
  @media (min-width:700px){ .mic-btn{ width:112px; height:112px; } .mic-btn svg{ width:38px; height:38px; } }
  .mic-btn:hover{ transform:scale(1.05); }
  .mic-btn.listening{
    animation:pulse 1.1s ease-in-out infinite; background:linear-gradient(155deg, #ff6b6b, var(--danger));
    box-shadow:0 16px 28px -12px rgba(214,69,69,0.55), 0 0 0 14px rgba(214,69,69,0.12);
  }
  @keyframes pulse{ 0%,100%{ box-shadow:0 16px 28px -12px rgba(214,69,69,0.55), 0 0 0 14px rgba(214,69,69,0.12);} 50%{ box-shadow:0 16px 28px -12px rgba(214,69,69,0.55), 0 0 0 22px rgba(214,69,69,0);} }
  .mic-label{ font-size:17px; font-weight:700; color:var(--slate-600); }
  @media (min-width:700px){ .mic-label{ font-size:19px; } }
  .timer-label{ font-family:'Baloo 2',sans-serif; font-size:24px; font-weight:700; color:var(--navy-900); }
  @media (min-width:700px){ .timer-label{ font-size:28px; } }
  .timer-label.warn{ color:var(--danger); }

  .loading-spin{
    width:48px;height:48px;border-radius:50%; margin:0 auto 14px; border:4px solid var(--line); border-top-color:var(--owl-orange-500);
    animation:spin 0.9s linear infinite;
  }
  @keyframes spin{ to{ transform:rotate(360deg); } }

  .note-banner{
    display:flex; gap:10px; text-align:left; border-radius:16px; padding:14px 16px; margin-bottom:14px;
    font-size:15px; font-weight:500; line-height:1.5;
  }
  .note-banner.amber{ background:var(--amber-bg); border:1px solid var(--amber); color:var(--navy-900); }
  .note-banner.danger{ background:var(--danger-bg); border:1px solid var(--danger); color:var(--danger); }

  .big-btn{
    display:block; width:100%; padding:17px; border:none; border-radius:20px; font:800 16px/1 'Baloo 2',sans-serif;
    cursor:pointer; background:linear-gradient(155deg, var(--blue-500), var(--blue-700)); color:#fff;
    text-decoration:none; box-sizing:border-box; box-shadow:0 16px 26px -12px rgba(15,95,174,0.5);
    transition:transform .2s cubic-bezier(.34,1.56,.64,1);
  }
  @media (min-width:700px){ .big-btn{ padding:20px; font-size:18px; border-radius:22px; } }
  .big-btn:hover{ transform:translateY(-2px) scale(1.02); }
  .big-btn:disabled{ opacity:.6; cursor:not-allowed; transform:none; }
  a:focus-visible, button:focus-visible{ outline:2px solid var(--blue-500); outline-offset:2px; }

  /* Comprehension quiz — only shown for reading_comprehension-competency
     Activities that actually carry real follow_up_questions. Inserted
     between "done reading" and the real form submit (Reading-api needs
     comprehension_score in the SAME /analyze call as the audio, so this
     can't happen after results). Reuses .step/.step.active from above. */
  .quiz-title{ font-size:18px; font-weight:700; color:var(--slate-600); margin:0 0 16px; text-align:center; }
  .quiz-question{ text-align:left; margin-bottom:18px; }
  .quiz-question p{ font-family:'Baloo 2',sans-serif; font-size:16px; font-weight:700; margin:0 0 10px; }
  .quiz-choice{
    display:flex; align-items:center; gap:10px; padding:12px 14px; border:1.5px solid var(--line); border-radius:14px;
    margin-bottom:8px; cursor:pointer; transition:border-color .15s ease, background-color .15s ease;
  }
  .quiz-choice:hover{ border-color:var(--owl-orange-500); }
  .quiz-choice input{ width:18px; height:18px; flex-shrink:0; accent-color:var(--owl-orange-600); }
  .quiz-choice span{ font-size:15px; font-weight:600; color:var(--navy-900); }
  .quiz-note{ font-size:13px; color:var(--slate-600); font-weight:600; text-align:center; margin:0 0 12px; display:none; }
  .quiz-note.show{ display:block; }
  @media (min-width:700px){
    .quiz-title{ font-size:20px; }
    .quiz-question p{ font-size:18px; }
    .quiz-choice{ padding:14px 16px; }
    .quiz-choice input{ width:20px; height:20px; }
    .quiz-choice span{ font-size:17px; }
    .quiz-note{ font-size:14px; }
  }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="clay-blob b1"></div>
    <div class="clay-blob b2"></div>
    <div class="mascot">🦉</div>
    <h1>{{ $activity->title }}</h1>
    <p class="sub">Read the words below out loud, then tap the mic!</p>

    @include('learner._reading-font-control', ['initialStep' => $learner->effectiveReadingFontStep()])
    <div class="passage-card">{{ $activity->passage_text }}</div>

    @if (session('error') || $errors->any())
      <div class="note-banner danger">{{ $errors->first() ?: session('error') }}</div>
    @endif

    @include('learner._recording-widget', ['recordAction' => route('learner.activity.record', $activity)])

    @php
      $comprehensionQuestions = ($activity->competency === 'reading_comprehension') ? ($activity->follow_up_questions ?: []) : [];
    @endphp
    @if (! empty($comprehensionQuestions))
      <div class="step" id="stepComprehensionQuiz">
        <p class="quiz-title">Now let's see what you remember!</p>
        @foreach ($comprehensionQuestions as $i => $q)
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

    <a href="{{ route('learner.dashboard') }}" class="big-btn" style="margin-top:14px; background:var(--surface); color:var(--slate-600); box-shadow:none; border:1.5px solid var(--line);">Back to My Dashboard</a>
  </div>
</div>
@if (! empty($comprehensionQuestions))
<script>
  // Inserted between "done reading" and the real form submit — Reading-api
  // needs comprehension_score in the SAME /analyze call as the audio, so
  // this can't wait until after results. The recording widget calls this
  // (see its own comment) right after the recorded file is attached to
  // the form; answers submit alongside it via each radio's form="recordForm".
  window.tarabasaBeforeSubmit = function () {
    document.querySelectorAll('.step').forEach(function (el) {
      if (el.id !== 'stepComprehensionQuiz') {
        el.classList.remove('active');
        el.style.display = 'none';
      }
    });
    const quizStep = document.getElementById('stepComprehensionQuiz');
    quizStep.style.display = 'block';
    quizStep.classList.add('active');
  };

  document.getElementById('quizSubmitBtn').addEventListener('click', function () {
    const answered = {};
    document.querySelectorAll('#stepComprehensionQuiz input[type="radio"]').forEach(function (radio) {
      if (radio.checked) answered[radio.name] = true;
    });
    const totalQuestions = new Set([...document.querySelectorAll('#stepComprehensionQuiz input[type="radio"]')].map(r => r.name)).size;

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
