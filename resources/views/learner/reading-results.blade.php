<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Great reading! — TaraBasa AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --sky-100:#dcedff;
    --blue-500:#1c7ed6; --blue-700:#0a3d73;
    --navy-900:#131f2b; --slate-600:#5b6b7a;
    --clay-yellow:#ffcf6e; --owl-orange-600:#dd7014;
    --teal:#2bb89c;
    --line:#e3ebf2; --surface:#ffffff; --bg-0:#f6faff;
    --success:#1f9e83; --success-bg:#e9f7f3;
    --danger:#d64545; --danger-bg:#fdecec;
    --repeat-blue:#1c7ed6; --repeat-blue-bg:#e6f2fd;
  }
  *{box-sizing:border-box;} html,body{margin:0;padding:0;}
  body{
    min-height:100vh; font-family:'Inter',sans-serif; color:var(--navy-900);
    background: radial-gradient(1200px 700px at 90% -10%, var(--sky-100), transparent 55%),
                radial-gradient(900px 600px at 0% 100%, #ffe9c9, transparent 50%), var(--bg-0);
    background-attachment:fixed;
    display:flex; align-items:safe center; justify-content:center; padding:24px;
  }
  .wrap{ width:100%; max-width:460px; }
  .card{
    background:var(--surface); border-radius:30px; padding:32px 28px; text-align:center;
    box-shadow:0 30px 60px -28px rgba(15,60,110,0.25);
  }
  .star-badge{
    width:110px;height:110px;border-radius:50%; margin:0 auto 16px; font-size:56px;
    background:linear-gradient(155deg, #fff3c4, #f0b03e); display:flex;align-items:center;justify-content:center;
    box-shadow:0 18px 30px -14px rgba(240,176,62,0.55);
    animation:pop .5s cubic-bezier(.34,1.56,.64,1);
  }
  @keyframes pop{ 0%{ transform:scale(0); } 70%{ transform:scale(1.15); } 100%{ transform:scale(1); } }
  h1{ font-family:'Baloo 2',sans-serif; font-size:25px; font-weight:700; margin:0 0 8px; }
  .sub{ font-size:18px; color:var(--slate-600); font-weight:600; margin:0 0 20px; }

  .level-callout{
    display:inline-flex; align-items:center; gap:8px; background:linear-gradient(155deg,#d6f5ea,var(--teal));
    color:#08352c; font:800 14.5px/1 'Baloo 2',sans-serif; padding:10px 18px; border-radius:999px; margin-bottom:18px;
  }

  /* Real Badges — see BadgeService. Shown only when this exact session
     genuinely earned one, never unconditionally. */
  .badge-celebration{ display:flex; flex-direction:column; gap:10px; margin-bottom:18px; }
  .badge-pop{
    display:flex; align-items:center; gap:12px; text-align:left;
    background:linear-gradient(155deg,#fff3c4,#f0b03e); border-radius:18px; padding:12px 16px;
    box-shadow:0 14px 24px -12px rgba(240,176,62,0.5);
    animation:badgePop .5s cubic-bezier(.34,1.56,.64,1);
  }
  @keyframes badgePop{ 0%{ transform:scale(0.6); opacity:0; } 70%{ transform:scale(1.06); opacity:1; } 100%{ transform:scale(1); opacity:1; } }
  .badge-pop-emoji{ font-size:34px; line-height:1; flex-shrink:0; }
  .badge-pop-label{ font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.04em; color:#7a5400; }
  .badge-pop-name{ font-family:'Baloo 2',sans-serif; font-size:16px; font-weight:800; color:#5a3d00; }

  .stat-row{ display:flex; gap:10px; margin-bottom:10px; }
  .stat-row:last-of-type{ margin-bottom:22px; }
  .stat-tile{ flex:1; background:var(--bg-0); border:2px solid var(--line); border-radius:18px; padding:16px 8px; }
  .stat-tile .v{ font-family:'Baloo 2',sans-serif; font-size:24px; font-weight:800; color:var(--success); }
  .stat-tile .l{ font-size:13px; font-weight:700; color:var(--slate-600); text-transform:uppercase; letter-spacing:.03em; margin-top:4px; }
  .stat-tile.practice .v{ color:var(--owl-orange-600); }

  /* Word-by-word breakdown — 5 categories: correct, skipped, mispronounced,
     said-a-different-word, repeated. Reading-api's real word_feedback only
     ever hands us correct/deletion/substitution/insertion directly —
     "mispronounced" and "repeated" are computed here from real data
     (LearnerReadingController::looksLikeMispronunciation() /
     detectRepeatedSpokenIndexes()), not fields the AI service reports. */
  .breakdown-title{ font-family:'Baloo 2',sans-serif; font-size:15px; font-weight:700; margin:0 0 10px; text-align:left; }
  .passage-review{
    background:var(--surface); border:2px solid var(--line); border-radius:20px; padding:22px 20px; margin-bottom:16px;
    font-family:'Baloo 2',sans-serif; font-size:22px; font-weight:800; line-height:2.5; color:var(--navy-900); text-align:left;
  }
  /* Plain words (correct/skipped) stay simple inline text. Words that need
     to show what was actually heard (mispronounced/said-a-different-word)
     or that the child repeated get a small always-visible caption stacked
     under the word instead of a hover-only tooltip — a tooltip is
     invisible on a touchscreen (this app has no mouse-hover concept for a
     child on a tablet) and doesn't show up in a screenshot either, so the
     information it carried was effectively hidden. */
  /* Plain words were left uncolored black — technically "not flagged,"
     but that reads as a mismatch against the legend's own green "Correct"
     dot, since green never actually appears anywhere in the passage.
     Given green text to actually match what the legend promises,
     consistent with this same screen's own stat-tile values already
     using --success for a correct/good result. */
  .rw{ position:relative; padding:2px 5px; border-radius:7px; color:var(--success); }
  .rw.st-skip{ background:#fff3d6; color:#9a6a00; text-decoration:line-through; text-decoration-thickness:2px; }
  .rw-annotated{
    display:inline-flex; flex-direction:column; align-items:center; vertical-align:top;
    padding:5px 9px 4px; margin:2px 3px; border-radius:12px; line-height:1.25;
  }
  .rw-annotated .rw-word{ font-family:'Baloo 2',sans-serif; }
  .rw-annotated .heard{
    font-family:'Inter',sans-serif; font-size:11px; font-weight:700; letter-spacing:.01em;
    margin-top:3px; white-space:nowrap;
  }
  .rw-annotated.st-sub{ background:#efe8fb; color:#6b4bc7; }
  .rw-annotated.st-sub .rw-word{ border-bottom:2.5px dotted #6b4bc7; }
  .rw-annotated.st-mispronounced{ background:var(--danger-bg); color:var(--danger); }
  .rw-annotated.st-mispronounced .rw-word{ border-bottom:2.5px dotted var(--danger); }
  .rw-annotated.st-repeated{ background:var(--repeat-blue-bg); color:var(--repeat-blue); }
  /* flex-wrap on .legend, combined with .chip also being display:flex,
     meant a tight wrapped row (5 chips now instead of the original 3,
     "Said a different word" being by far the longest label) let the flex
     algorithm shrink the .dot itself well below its own 14px — a fixed-
     size box has no min-content floor the way text does, so it's the
     item that visibly loses the fight for space. flex-shrink:0 on both
     the dot and the chip fixes this for good, not just for today's exact
     label set. */
  /* Deliberately NOT flex for the dot+label pairing inside a chip — a
     nested flex row here (dot + label as flex siblings) produced a
     real, reproducible misalignment specifically on the longest label
     ("Said a different word"), confirmed via both computed-style
     measurement AND an actual screenshot, that persisted across several
     different flex-based fix attempts. Plain inline-block +
     vertical-align:middle is the older, more predictable pattern for an
     icon+text pair and sidesteps whatever the flex cross-axis
     computation was doing wrong here. */
  .legend{ display:flex; flex-wrap:wrap; align-items:center; gap:9px 18px; margin-bottom:22px; justify-content:center; }
  .legend .chip{ font-size:12.5px; font-weight:700; line-height:13px; color:var(--slate-600); white-space:nowrap; }
  .legend .chip .label{ display:inline-block; vertical-align:middle; }
  .legend .dot{ display:inline-block; width:13px; height:13px; margin-right:7px; border-radius:50%; vertical-align:middle; box-shadow:0 0 0 1px rgba(19,31,43,0.04); }
  .legend .dot.correct{ background:var(--success-bg); border:1.5px solid var(--success); }
  .legend .dot.skip{ background:#fff3d6; border:1.5px solid #9a6a00; }
  .legend .dot.mispronounced{ background:var(--danger-bg); border:1.5px solid var(--danger); }
  .legend .dot.st-sub{ background:#efe8fb; border:1.5px solid #6b4bc7; }
  .legend .dot.repeated{ background:var(--repeat-blue-bg); border:1.5px solid var(--repeat-blue); }
  .extra-words{ font-size:13px; color:var(--slate-600); font-weight:600; margin:0 0 22px; text-align:left; }

  /* Comprehension recap — a plain "X out of Y correct" count, never a
     percentage or grade, following this screen's OWN existing precedent
     of showing real numbers (Accuracy%/WCPM below already do) — this is
     Practice-session feedback, not the diagnostic's stricter "never show
     a score" rule. Per-question review reuses the same warm,
     color-coded-not-punitive tone as the word breakdown above. */
  .comprehension-count{ font-size:15px; font-weight:700; color:var(--navy-900); margin:0 0 12px; text-align:left; }
  .comp-q{ border-radius:16px; padding:12px 14px; margin-bottom:8px; text-align:left; }
  .comp-q.correct{ background:var(--success-bg); }
  .comp-q.miss{ background:#fff3d6; }
  .comp-question{ font-size:14px; font-weight:700; color:var(--navy-900); margin:0; }
  .comp-answer{ font-size:12.5px; font-weight:600; color:#9a6a00; margin:6px 0 0; }

  .big-btn{
    display:block; width:100%; padding:17px; border:none; border-radius:20px; font:800 16px/1 'Baloo 2',sans-serif;
    cursor:pointer; background:linear-gradient(155deg, var(--blue-500), var(--blue-700)); color:#fff;
    text-decoration:none; box-sizing:border-box; box-shadow:0 16px 26px -12px rgba(15,95,174,0.5);
    transition:transform .2s cubic-bezier(.34,1.56,.64,1);
  }
  .big-btn:hover{ transform:translateY(-2px) scale(1.02); }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    @php
      // Never a sad/discouraging visual even on a low score — the actor
      // prompt is explicit about this.
      $emoji = $accuracy >= 90 ? '🌟' : ($accuracy >= 70 ? '👍' : '💪');
    @endphp
    <div class="star-badge">{{ $emoji }}</div>
    <h1>Great reading, {{ $learner->first_name }}!</h1>
    <p class="sub">You read "{{ $activity->title }}"</p>

    @if ($levelChanged)
      <div class="level-callout">
        {{ $levelWentUp ? '⬆️' : '' }} {{ $levelBefore ?? 'New' }} → {{ $levelAfter }}
      </div>
    @endif

    @include('learner._badge-celebration', ['newBadges' => $newBadges])

    @if (! empty($wordBreakdown))
      <p class="breakdown-title">Here's how you read each word:</p>
      <div class="passage-review">
        @foreach ($wordBreakdown as $word)
          @if ($word['status'] === 'correct')
            <span class="rw">{{ $word['text'] }}</span>
          @elseif ($word['status'] === 'skip')
            <span class="rw st-skip">{{ $word['text'] }}</span>
          @elseif ($word['status'] === 'repeated')
            <span class="rw rw-annotated st-repeated">
              <span class="rw-word">{{ $word['text'] }}</span>
              <span class="heard">said twice</span>
            </span>
          @elseif ($word['status'] === 'mispronounced')
            <span class="rw rw-annotated st-mispronounced">
              <span class="rw-word">{{ $word['text'] }}</span>
              <span class="heard">heard "{{ $word['heard'] }}"</span>
            </span>
          @else
            <span class="rw rw-annotated st-sub">
              <span class="rw-word">{{ $word['text'] }}</span>
              <span class="heard">heard "{{ $word['heard'] }}"</span>
            </span>
          @endif
        @endforeach
      </div>
      <div class="legend">
        <span class="chip"><span class="dot correct"></span><span class="label">Correct</span></span>
        <span class="chip"><span class="dot skip"></span><span class="label">Skipped</span></span>
        <span class="chip"><span class="dot mispronounced"></span><span class="label">Mispronounced</span></span>
        <span class="chip"><span class="dot st-sub"></span><span class="label">Said a different word</span></span>
        <span class="chip"><span class="dot repeated"></span><span class="label">Repeated</span></span>
      </div>
      @if (! empty($extraWordsSaid))
        <p class="extra-words">You also said: "{{ implode('", "', $extraWordsSaid) }}" — that's not in this passage, but great effort reading out loud!</p>
      @endif
    @endif

    @if ($comprehension)
      <p class="breakdown-title">How well did you understand it?</p>
      <p class="comprehension-count">
        You got {{ $comprehension['correctCount'] }} out of {{ $comprehension['totalCount'] }} correct!
        {{ $comprehension['correctCount'] === $comprehension['totalCount'] ? '🌟' : '🙂' }}
      </p>
      @foreach ($comprehension['breakdown'] as $q)
        <div class="comp-q {{ $q['isCorrect'] ? 'correct' : 'miss' }}">
          <p class="comp-question">{{ $q['isCorrect'] ? '✅' : '💡' }} {{ $q['question'] }}</p>
          @unless ($q['isCorrect'])
            <p class="comp-answer">The answer was: {{ $q['correctAnswer'] }}</p>
          @endunless
        </div>
      @endforeach
    @endif

    <div class="stat-row">
      <div class="stat-tile">
        <div class="v">{{ round($accuracy) }}%</div>
        <div class="l">Accuracy</div>
      </div>
      <div class="stat-tile">
        <div class="v">{{ $wcpm !== null ? round($wcpm) : '—' }}</div>
        <div class="l">WCPM</div>
      </div>
      <div class="stat-tile practice">
        <div class="v">{{ $wordsToPractice ?? '—' }}</div>
        <div class="l">Words to Practice</div>
      </div>
    </div>
    <div class="stat-row">
      <div class="stat-tile">
        <div class="v">+{{ $pointsEarned }}</div>
        <div class="l">Points</div>
      </div>
      <div class="stat-tile">
        <div class="v">🔥 {{ $learner->streak }}</div>
        <div class="l">Streak</div>
      </div>
    </div>

    <a href="{{ route('learner.dashboard') }}" class="big-btn">Done</a>
  </div>
</div>
</body>
</html>
