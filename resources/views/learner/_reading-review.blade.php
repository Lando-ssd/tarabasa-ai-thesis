{{--
  The "how did I read it" part of a results screen: the passage word by word with
  what happened to each word, the legend, words the child said that are not in the
  passage, and (for a comprehension activity) how the questions went. Included by
  _feedback-scene below the star and the numbers, so it brings its own styles.

  Expects: $wordBreakdown (from LearnerReadingService::buildWordBreakdown(), or empty),
           optional $extraWordsSaid (list), optional $comprehension
           (['correctCount','totalCount','breakdown'=>[['question','isCorrect','correctAnswer']]]).

  Five kinds of word, only three of them straight from Reading-api's word_feedback:
  correct, skipped (deletion), said a different word (substitution). "Mispronounced" and
  "repeated" are worked out in LearnerReadingService from real data (see its doc
  comments), not reported by the AI service. Class names are prefixed rv- on purpose:
  an unprefixed name here once collided with another rule on the same page.
--}}
<style>
  .rv{ --rv-good:#1f9e83; --rv-good-bg:#e9f7f3; --rv-danger:#d64545; --rv-danger-bg:#fdecec; --rv-blue:#1c7ed6; --rv-blue-bg:#e6f2fd; --rv-purple:#6b4bc7; --rv-purple-bg:#efe8fb; --rv-amber:#9a6a00; --rv-amber-bg:#fff3d6;
    width:100%; max-width:1180px; margin:6px auto 0; display:flex; flex-direction:column; gap:22px; }
  .rv-card{ background:#f1f7ff; border:2px solid #d3e3f4; border-radius:30px; box-shadow:0 8px 0 #c3d8ee; padding:26px 24px 24px; }
  @media (min-width:700px){ .rv-card{ padding:32px 36px 30px; border-radius:34px; } }
  .rv-title{ margin:0 0 16px; font:700 26px/1.15 var(--font-game); color:#2f455b; }
  @media (min-width:700px){ .rv-title{ font-size:30px; } }

  .rv-words{
    background:#ffffff; border:2px solid #d3e3f4; border-radius:22px; padding:16px 18px;
    font-family:'Lexend',sans-serif; font-size:24px; font-weight:500; line-height:2.5; color:#131f2b; text-align:left;
  }
  @media (min-width:700px){ .rv-words{ font-size:28px; padding:20px 26px; } }
  .rv-w{ padding:2px 5px; border-radius:8px; color:var(--rv-good); }
  .rv-w.rv-skip{ background:var(--rv-amber-bg); color:var(--rv-amber); text-decoration:line-through; text-decoration-thickness:2px; }
  /* A word that needs to say what was actually heard gets a small caption under it, always visible:
     a hover tooltip is invisible on a touchscreen, which is what a child uses. */
  .rv-note{ display:inline-flex; flex-direction:column; align-items:center; vertical-align:top; padding:5px 10px 4px; margin:2px 3px; border-radius:14px; line-height:1.25; }
  .rv-note .rv-heard{ margin-top:3px; white-space:nowrap; font:600 15px/1 var(--font-game); letter-spacing:.02em; }
  .rv-note.rv-sub{ background:var(--rv-purple-bg); color:var(--rv-purple); }
  .rv-note.rv-sub .rv-word{ border-bottom:3px dotted var(--rv-purple); }
  .rv-note.rv-mis{ background:var(--rv-danger-bg); color:var(--rv-danger); }
  .rv-note.rv-mis .rv-word{ border-bottom:3px dotted var(--rv-danger); }
  .rv-note.rv-rep{ background:var(--rv-blue-bg); color:var(--rv-blue); }

  .rv-legend{ display:flex; flex-wrap:wrap; align-items:center; justify-content:center; gap:10px 22px; margin:16px 0 0; }
  .rv-key{ white-space:nowrap; font:600 18px/14px var(--font-game); color:#3f566d; }
  .rv-key .rv-dot{ display:inline-block; width:14px; height:14px; margin-right:8px; border-radius:50%; vertical-align:middle; }
  .rv-key .rv-lab{ display:inline-block; vertical-align:middle; }
  .rv-dot.rv-d-good{ background:var(--rv-good-bg); border:2px solid var(--rv-good); }
  .rv-dot.rv-d-skip{ background:var(--rv-amber-bg); border:2px solid var(--rv-amber); }
  .rv-dot.rv-d-mis{ background:var(--rv-danger-bg); border:2px solid var(--rv-danger); }
  .rv-dot.rv-d-sub{ background:var(--rv-purple-bg); border:2px solid var(--rv-purple); }
  .rv-dot.rv-d-rep{ background:var(--rv-blue-bg); border:2px solid var(--rv-blue); }
  .rv-extra{ margin:14px 0 0; font:500 20px/1.35 var(--font-game); color:#3f566d; text-align:left; }

  .rv-count{ display:flex; align-items:center; gap:10px; margin:0 0 14px; font:600 24px/1.2 var(--font-game); color:#131f2b; }
  .rv-ico{ width:28px; height:28px; flex:none; display:block; }
  .rv-q{ display:flex; align-items:flex-start; gap:12px; border-radius:18px; padding:14px 16px; margin-bottom:10px; text-align:left; }
  .rv-q.rv-right{ background:var(--rv-good-bg); }
  .rv-q.rv-right .rv-ico{ color:var(--rv-good); }
  .rv-q.rv-miss{ background:var(--rv-amber-bg); }
  .rv-q.rv-miss .rv-ico{ color:#d99a0b; }
  .rv-q p{ margin:0; font:600 21px/1.3 var(--font-game); color:#131f2b; }
  .rv-q .rv-answer{ margin-top:6px; font-weight:500; color:var(--rv-amber); }
</style>

<div class="rv">
  @if (! empty($wordBreakdown))
    <section class="rv-card" aria-label="How you read each word">
      <h2 class="rv-title">Here's how you read each word</h2>
      <div class="rv-words">
        @foreach ($wordBreakdown as $word)
          @if ($word['status'] === 'correct')
            <span class="rv-w">{{ $word['text'] }}</span>
          @elseif ($word['status'] === 'skip')
            <span class="rv-w rv-skip">{{ $word['text'] }}</span>
          @elseif ($word['status'] === 'repeated')
            <span class="rv-w rv-note rv-rep"><span class="rv-word">{{ $word['text'] }}</span><span class="rv-heard">said twice</span></span>
          @elseif ($word['status'] === 'mispronounced')
            <span class="rv-w rv-note rv-mis"><span class="rv-word">{{ $word['text'] }}</span><span class="rv-heard">heard "{{ $word['heard'] }}"</span></span>
          @else
            <span class="rv-w rv-note rv-sub"><span class="rv-word">{{ $word['text'] }}</span><span class="rv-heard">heard "{{ $word['heard'] }}"</span></span>
          @endif
        @endforeach
      </div>
      <div class="rv-legend">
        <span class="rv-key"><span class="rv-dot rv-d-good"></span><span class="rv-lab">Correct</span></span>
        <span class="rv-key"><span class="rv-dot rv-d-skip"></span><span class="rv-lab">Skipped</span></span>
        <span class="rv-key"><span class="rv-dot rv-d-mis"></span><span class="rv-lab">Mispronounced</span></span>
        <span class="rv-key"><span class="rv-dot rv-d-sub"></span><span class="rv-lab">Said a different word</span></span>
        <span class="rv-key"><span class="rv-dot rv-d-rep"></span><span class="rv-lab">Repeated</span></span>
      </div>
      @if (! empty($extraWordsSaid))
        <p class="rv-extra">You also said "{{ implode('", "', $extraWordsSaid) }}", which is not in this passage. Great effort reading out loud!</p>
      @endif
    </section>
  @endif

  @if (! empty($comprehension))
    <section class="rv-card" aria-label="How well you understood it">
      <h2 class="rv-title">How well did you understand it?</h2>
      <p class="rv-count">
        @include('learner._badge-icon', ['icon' => $comprehension['correctCount'] === $comprehension['totalCount'] ? 'star' : 'smiley', 'class' => 'rv-ico'])
        You got {{ $comprehension['correctCount'] }} out of {{ $comprehension['totalCount'] }} right!
      </p>
      @foreach ($comprehension['breakdown'] as $q)
        <div class="rv-q {{ $q['isCorrect'] ? 'rv-right' : 'rv-miss' }}">
          @include('learner._badge-icon', ['icon' => $q['isCorrect'] ? 'check-circle' : 'lightbulb', 'class' => 'rv-ico'])
          <div>
            <p>{{ $q['question'] }}</p>
            @unless ($q['isCorrect'])
              <p class="rv-answer">The answer was: {{ $q['correctAnswer'] }}</p>
            @endunless
          </div>
        </div>
      @endforeach
    </section>
  @endif
</div>
