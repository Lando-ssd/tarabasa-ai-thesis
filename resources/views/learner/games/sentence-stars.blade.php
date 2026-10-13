{{--
  Sentence Stars: a sentence to read out loud. Reading it catches a star; three sentences make a round, and two or more read
  right the first time climb a level, like the other games. Free play: no points, no streak, no level, and what the child says is
  never graded or kept.

  The page and the play loop are games/read-game (shared with Word Train); this file is only the sentence and the stars.
  Expects $levels (the sentences for each level, the ones that suit this child first: PracticeGameService::sentenceLevels)
  and $startLevel.
--}}
@extends('learner.games.read-game', ['gameKey' => 'sentence-stars'])

@section('title', 'Sentence Stars')
@section('card-class', 'sky-night')

@section('css')
<style>
  .card.sky-night{ background:linear-gradient(180deg,#dfe8ff 0%,#eef0ff 55%,#fbf4ff 100%); }

  .stars{ display:flex; justify-content:center; gap:14px; margin:0 0 12px; }
  .star-slot{ width:48px; height:48px; color:#c9d3e6; transition:transform .3s ease, color .3s ease; }
  .star-slot svg{ width:100%; height:100%; display:block; }
  .star-slot.on{ color:#f6b93b; animation:starPop .6s cubic-bezier(.34,1.56,.64,1); filter:drop-shadow(0 3px 0 rgba(180,120,0,.25)); }
  @keyframes starPop{ 0%{ transform:scale(.3) rotate(-30deg); } 70%{ transform:scale(1.35) rotate(8deg); } 100%{ transform:scale(1) rotate(0); } }

  .sentence{
    margin:6px auto 4px; padding:20px 18px 22px; background:#fff; border-radius:22px; border:2px solid #d6def0; box-shadow:0 6px 0 #c9d3e6;
    font:500 clamp(26px, 6.4vw, 38px)/1.55 'Lexend',sans-serif; color:#17324d; text-align:center; transition:box-shadow .3s ease, transform .3s ease;
  }
  .sentence.long{ font-size:clamp(22px, 5.2vw, 30px); }
  .sentence .w{ display:inline-block; margin:0 .14em; padding:0 .12em; border-radius:10px; transition:background .25s ease, color .25s ease; }
  .sentence .w.ok{ color:#1f7a50; background:#dff5e9; }
  .sentence.caught{ box-shadow:0 0 0 6px rgba(246,185,59,.45), 0 6px 0 #c9d3e6; transform:scale(1.02); }
  @media (max-width:520px){ .star-slot{ width:42px; height:42px; } .sentence{ padding:16px 12px 18px; } }
  @media (prefers-reduced-motion:reduce){ .star-slot.on{ animation:none; } }
</style>
@endsection

@section('ask')
  <span>Read the sentence</span>
  <button type="button" class="listen-btn" id="listenBtn" aria-label="Hear the sentence">@include('learner._badge-icon', ['icon' => 'speaker-high', 'class' => 'badge-svg'])</button>
@endsection

@section('stage')
  <div class="stars" id="stars" aria-hidden="true"></div>
  <div class="sentence" id="sentence" aria-live="polite"></div>
@endsection

@section('script')
<script>
  (function () {
    var LEVELS = @json($levels);
    var sentenceEl = document.getElementById('sentence');
    var starsEl = document.getElementById('stars');
    var STAR = window.tarabasaBadgeSvg('star');

    function shuffle(arr) {
      var a = arr.slice();
      for (var i = a.length - 1; i > 0; i--) {
        var j = Math.floor(Math.random() * (i + 1));
        var t = a[i]; a[i] = a[j]; a[j] = t;
      }
      return a;
    }

    // The words a sentence asks for, one for each word on the screen: the same cleaning the server does.
    function wordsOf(sentence) {
      return sentence.split(/\s+/).map(function (tok) { return tok.toLowerCase().replace(/[^a-z']/g, ''); }).filter(function (w) { return w; });
    }

    function renderStars(done) {
      var html = '';
      for (var i = 0; i < 3; i++) html += '<span class="star-slot' + (i < done ? ' on' : '') + '">' + STAR + '</span>';
      starsEl.innerHTML = html;
    }

    window.READ_GAME = {
      startLevel: {{ (int) $startLevel }},
      unit: 'Sentence',
      levelIcons: { 1: 'plant', 2: 'leaf', 3: 'tree' },
      micNote: 'Tap the microphone and read the sentence.',
      tapNote: 'Read the sentence out loud, then press the button.',
      kind: function () { return 'sentence'; },

      // Three different sentences: the ones that suit this child first (their own missed words, their kind of mistake), then the level's.
      makeTargets: function (level) {
        var data = LEVELS[level];
        var pref = shuffle(data.preferred || []);
        var rest = shuffle(data.fallback.filter(function (s) { return pref.indexOf(s) < 0; }));
        return pref.concat(rest).slice(0, 3).map(function (s) { return { sentence: s, text: wordsOf(s).join(' ') }; });
      },

      show: function (target, index) {
        renderStars(index);
        var words = wordsOf(target.sentence);
        var html = '';
        target.sentence.split(/\s+/).forEach(function (tok) {
          if (tok.toLowerCase().replace(/[^a-z']/g, '')) html += '<span class="w">' + tok + '</span> ';
        });
        sentenceEl.classList.remove('caught');
        sentenceEl.classList.toggle('long', words.length > 9);
        sentenceEl.innerHTML = html;
      },

      hear: function (target) { window.tarabasaSayText(target.sentence); },

      // Show which words were heard. null means all of them.
      markHeard: function (matched) {
        sentenceEl.querySelectorAll('.w').forEach(function (el, i) { el.classList.toggle('ok', matched ? !!matched[i] : true); });
      },

      // A star is caught.
      celebrate: function () {
        sentenceEl.classList.add('caught');
        var slots = starsEl.querySelectorAll('.star-slot');
        for (var i = 0; i < slots.length; i++) {
          if (!slots[i].classList.contains('on')) { slots[i].classList.add('on'); break; }
        }
        return 1100;
      }
    };
  })();
</script>
@endsection
