{{--
  Word Train: a train of cars, each one a word (or, for a child still learning letters, a letter). The child reads the whole
  train out loud and it rolls away. Three trains make a round; two or more read right the first time climb a level, like the
  other games. Free play: no points, no streak, no level, and what the child says is never graded or kept.

  The page and the play loop are games/read-game (shared with Sentence Stars); this file is only the train.
  Expects $mode ('letters' | 'words'), $cars ([1 => 3, 2 => 4, 3 => 5] or the letter counts), $levels (the pools for each level,
  the child's own missed words first: PracticeGameService::trainLevels) and $startLevel.
--}}
@extends('learner.games.read-game', ['gameKey' => 'word-train'])

@section('title', 'Word Train')
@section('card-class', 'rail')

@section('css')
<style>
  .card.rail{ background:linear-gradient(180deg,#d9eefc 0%,#eef8ff 55%,#f4fbf2 100%); }

  .train{ display:flex; flex-wrap:wrap; justify-content:center; align-items:flex-end; gap:30px 14px; margin:14px 0 8px; padding:6px 4px 18px; }
  .engine{ position:relative; width:64px; height:64px; border-radius:16px 22px 12px 12px; background:#5b6b7a; color:#fff; display:grid; place-items:center; box-shadow:inset 0 -8px 0 rgba(0,0,0,.2); }
  .engine .badge-svg{ width:34px; height:34px; }
  .car{
    position:relative; min-width:64px; padding:14px 16px 16px; border-radius:16px; color:#fff;
    font:700 clamp(24px, 6vw, 36px)/1 'Lexend',sans-serif; text-shadow:0 2px 0 rgba(0,0,0,.18);
    box-shadow:inset 0 -9px 0 rgba(0,0,0,.18); transition:transform .25s ease;
  }
  .car.letter{ min-width:62px; padding:14px 18px 16px; font-size:clamp(30px, 8vw, 44px); }
  /* a coupler to the next car, and two wheels under each car */
  .car::before{ content:''; position:absolute; left:-15px; top:50%; width:15px; height:6px; margin-top:-3px; background:#8aa0b4; border-radius:3px; }
  .car::after{ content:''; position:absolute; left:14%; right:14%; bottom:-10px; height:14px; background:
      radial-gradient(circle at 20% 50%, #3b4a58 0 6px, transparent 7px), radial-gradient(circle at 80% 50%, #3b4a58 0 6px, transparent 7px); }
  .engine::after{ content:''; position:absolute; left:14%; right:14%; bottom:-10px; height:14px; background:
      radial-gradient(circle at 20% 50%, #3b4a58 0 6px, transparent 7px), radial-gradient(circle at 80% 50%, #3b4a58 0 6px, transparent 7px); }
  .car.c1{ background:#ef5b4a; } .car.c2{ background:#2f8de0; } .car.c3{ background:#2bb89c; } .car.c4{ background:#f2b134; } .car.c5{ background:#9b6bd6; }
  /* heard: the car turns green and gets a small tick */
  .car.ok{ background:#2e9e6b; transform:translateY(-4px); }
  .car.ok span.tick{ position:absolute; top:-12px; right:-8px; width:26px; height:26px; border-radius:50%; background:#fff; box-shadow:0 2px 0 rgba(0,0,0,.18); }
  .car.ok span.tick::after{ content:''; position:absolute; left:8px; top:4px; width:6px; height:11px; border:solid #2e9e6b; border-width:0 4px 4px 0; transform:rotate(45deg); }
  .train.go .engine, .train.go .car{ animation:roll .85s ease-in forwards; }
  .train.go .car:nth-of-type(2){ animation-delay:.05s; } .train.go .car:nth-of-type(3){ animation-delay:.1s; }
  .train.go .car:nth-of-type(4){ animation-delay:.15s; } .train.go .car:nth-of-type(5){ animation-delay:.2s; } .train.go .car:nth-of-type(6){ animation-delay:.25s; }
  @keyframes roll{ 0%{ transform:translateX(0); } 100%{ transform:translateX(130vw); } }
  @media (max-width:520px){
    .train{ gap:26px 12px; }
    .engine{ width:52px; height:52px; } .engine .badge-svg{ width:28px; height:28px; }
    .car{ min-width:54px; padding:12px 12px 14px; }
  }
  @media (prefers-reduced-motion:reduce){ .train.go .engine, .train.go .car{ animation:none !important; opacity:.35; } }
</style>
@endsection

@section('ask')
  <span id="askText">Read the train</span>
  <button type="button" class="listen-btn" id="listenBtn" aria-label="Hear the words">@include('learner._badge-icon', ['icon' => 'speaker-high', 'class' => 'badge-svg'])</button>
@endsection

@section('stage')
  <div class="train" id="train" aria-live="polite"></div>
@endsection

@section('script')
<script>
  (function () {
    var MODE = @json($mode);
    var LEVELS = @json($levels);
    var CARS = @json($cars);
    var LETTER = MODE === 'letters';
    var trainEl = document.getElementById('train');
    var ENGINE = window.tarabasaBadgeSvg('train');

    function shuffle(arr) {
      var a = arr.slice();
      for (var i = a.length - 1; i > 0; i--) {
        var j = Math.floor(Math.random() * (i + 1));
        var t = a[i]; a[i] = a[j]; a[j] = t;
      }
      return a;
    }

    // Everything a level can offer, in the order it should be used: the child's own missed words first, then the ones that
    // practise their kind of mistake, then the rest of the level's list, each group in a fresh random order.
    function pool(level) {
      var data = LEVELS[level];
      var own = shuffle(data.struggling || []);
      var fav = shuffle((data.favoured || []).filter(function (w) { return own.indexOf(w) < 0; }));
      var rest = shuffle(data.fallback.filter(function (w) { return own.indexOf(w) < 0 && fav.indexOf(w) < 0; }));
      return own.concat(fav, rest);
    }

    function render(target) {
      var html = '<div class="engine" aria-hidden="true">' + ENGINE + '</div>';
      target.words.forEach(function (w, i) {
        var shown = LETTER ? w.toUpperCase() : w;
        html += '<div class="car c' + ((i % 5) + 1) + (LETTER ? ' letter' : '') + '" data-i="' + i + '"><span>' + shown + '</span></div>';
      });
      trainEl.classList.remove('go');
      trainEl.innerHTML = html;
      trainEl.setAttribute('aria-label', 'Read the train: ' + target.words.join(', '));
    }

    window.READ_GAME = {
      startLevel: {{ (int) $startLevel }},
      unit: 'Train',
      levelIcons: { 1: 'plant', 2: 'leaf', 3: 'tree' },
      micNote: LETTER ? 'Tap the microphone and say the letters.' : 'Tap the microphone and read the words.',
      tapNote: 'Read the train out loud, then press the button.',
      kind: function () { return LETTER ? 'letters' : 'row'; },

      // Three trains for a round, taken from one pool without repeating, so the child's own words are spread across them.
      makeTargets: function (level) {
        var n = CARS[level];
        var p = pool(level);
        var out = [];
        for (var k = 0; k < 3; k++) {
          var words = [];
          for (var j = 0; j < n; j++) words.push(p[(k * n + j) % p.length]);
          words = shuffle(words);
          out.push({ words: words, text: words.join(' ') });
        }
        return out;
      },

      show: function (target) {
        render(target);
        document.getElementById('askText').textContent = LETTER ? 'Say the letters' : 'Read the train';
      },

      hear: function (target) {
        if (LETTER) window.tarabasaSayLetter && target.words.forEach(function (l, i) { setTimeout(function () { window.tarabasaSayLetter(l); }, i * 900); });
        else window.tarabasaSayText(target.words.join(', '));
      },

      // Show which cars were heard. null means everything was.
      markHeard: function (matched) {
        trainEl.querySelectorAll('.car').forEach(function (car, i) {
          var ok = matched ? !!matched[i] : true;
          car.classList.toggle('ok', ok);
          var tick = car.querySelector('.tick');
          if (ok && !tick) car.insertAdjacentHTML('beforeend', '<span class="tick" aria-hidden="true"></span>');
          if (!ok && tick) tick.remove();
        });
      },

      // The train rolls away.
      celebrate: function () {
        trainEl.classList.add('go');
        return 950;
      }
    };
  })();
</script>
@endsection
