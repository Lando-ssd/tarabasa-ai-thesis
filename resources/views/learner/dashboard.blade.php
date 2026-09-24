{{--
  Learner Home: "How I'm Growing" road maps on the left; on the right, the
  streak / points / level pills and the weekly goal and growth cards. Every
  number is real: proficiency from the adaptive engine, streak from the days
  the child actually read, goal and growth from this week's readings.
--}}
@extends('layouts.learner-shell')

@section('title', 'My Dashboard — TaraBasa AI')
@section('page', 'home')

@php
    $levels = [
        'Beginning' => ['🌱', 'Beginning'],
        'Developing' => ['🌿', 'Developing'],
        'Proficient' => ['🌟', 'Proficient'],
    ];
    $levelName = $learner->mastery_level ?? 'New';
    $goalLeft = max(0, $weeklyTarget - $weeklyCount);
    $animUrls = [
        'flame' => asset('animations/learner/flame-icon.json'),
        'star' => asset('animations/learner/star-icon.json'),
        'flameBurst' => asset('animations/learner/flame-burst.json'),
          'owl' => asset('animations/learner/owl-bird.json'),
    ];
@endphp

@section('content')
@include('learner._puck-sprite')

<div class="main-grid" id="page-home">
  <div class="center">
    <div class="journey-head"><h2>How I'm Growing 🌱</h2></div>

    @if (! $hasJourneyData || empty($journeyRows))
      <div class="roadmap-banner locked">
        <div class="rb-text">
          <b>Your path is getting ready</b>
          <span class="rb-sub">It fills in as you read. Let's start!</span>
        </div>
      </div>
      <p style="margin:20px 0 0;"><a class="clay-btn orange" style="display:inline-block;text-decoration:none;" href="{{ route('learner.activity.find') }}">Start Reading</a></p>
    @else
      @foreach ($journeyRows as $row)
        @include('learner._road', ['row' => $row])
      @endforeach
    @endif

    <div class="credits">Icons by <a href="https://icons8.com" target="_blank" rel="noopener">Icons8</a></div>
  </div>

  <div class="right-rail">
    <div class="topstats-col">
      <div class="stat-pills">

        <div class="pill-wrap">
          <button type="button" class="pill level" id="levelPill" aria-haspopup="true" aria-expanded="false">
            <span class="stat-num">{{ $levelName }}</span>
          </button>
        </div>

        {{-- Two files, two jobs: the small row icon is a real button (still until tapped, pops once);
             the bigger animation in the popover plays fresh every time the popover opens. --}}
        <div class="pill-wrap">
          <button type="button" class="pill streak" id="streakPill" aria-haspopup="true" aria-expanded="false">
            <span class="stat-icon-lottie" id="streakIconLottie"></span>
            <span class="stat-num">{{ $dayStreak }}</span>
          </button>
        </div>

        <div class="pill-wrap">
          <button type="button" class="pill points" id="pointsPill" aria-haspopup="true" aria-expanded="false">
            <span class="stat-icon-lottie" id="pointsIconLottie"></span>
            <span class="stat-num">{{ number_format($learner->points) }}</span>
          </button>
        </div>

        {{-- The popovers are siblings of the pills, not children, so each can be as wide as the whole
             row (like Duolingo's) and open over the cards below without anything clipping them. --}}
        <div class="popover level-pop" id="levelPopover">
          <span class="popover-caret"></span>
          <div class="popover-inner">
            <div class="lv-body">
              <h4>Your Reading Level</h4>
              <p class="lv-sub">This grows as your real reading accuracy improves.</p>
              <div class="lv-rows">
                @foreach ($levels as $name => [$icon, $label])
                  <div class="lv-row {{ $levelName === $name ? 'current' : '' }}">
                    <span class="lv-ico">{{ $icon }}</span>
                    <span class="lv-name">{{ $label }}</span>
                    @if ($levelName === $name)<span class="lv-you">That's you</span>@endif
                  </div>
                @endforeach
              </div>
            </div>
          </div>
        </div>

        <div class="popover streak-pop" id="streakPopover">
          <span class="popover-caret"></span>
          <div class="popover-inner">
            <div class="sp-head">
              <div class="sp-copy">
                @if ($dayStreak > 0)
                  <h4>{{ $dayStreak }} day streak</h4>
                  <p>You've read on {{ $dayStreak }} {{ $dayStreak === 1 ? 'day' : 'days' }} in a row. Keep it going!</p>
                @else
                  <h4>Start a streak</h4>
                  <p>Read today to start your streak. Read again tomorrow to keep it going!</p>
                @endif
              </div>
              <div class="streak-burst-lottie" id="streakBurstLottie"></div>
              <div class="sp-week">
                @foreach ($streakWeek as $day)
                  <div class="sp-day {{ $day['done'] ? 'done' : '' }}">
                    <span class="sp-day-label">{{ $day['label'] }}</span>
                    <span class="sp-dot">
                      @if ($day['done'])
                        <svg width="16" height="13" viewBox="0 0 12 10" fill="none"><path d="M1 5l3 3 7-7" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                      @endif
                    </span>
                  </div>
                @endforeach
              </div>
            </div>
            <div class="sp-body">
              <div class="sp-goal">
                <div class="sp-goal-ico">{{ $dayStreak >= 7 ? '🏅' : '🔒' }}</div>
                <div class="sp-goal-text">
                  <b>7 Day Streak badge</b>
                  <span>{{ $dayStreak >= 7 ? 'You earned it. Great reading habit!' : 'Read on 7 days in a row to earn it.' }}</span>
                </div>
              </div>
              <a class="clay-btn blue sp-btn" href="{{ route('learner.badges.index') }}">View Badges</a>
            </div>
          </div>
        </div>

        <div class="popover points-pop" id="pointsPopover">
          <span class="popover-caret"></span>
          <div class="popover-inner">
            <div class="pp-row">
              <div class="pp-art" id="pointsArtLottie"></div>
              <div class="pp-text">
                <h4>Points</h4>
                <p>You have {{ number_format($learner->points) }} {{ $learner->points === 1 ? 'point' : 'points' }}</p>
                <span class="pp-note">You earn points every time you finish a reading.</span>
                <a class="pp-link" href="{{ route('learner.activity.find') }}">Start Reading</a>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <div class="right-col">
      <div class="clay-card theme-sky">
        <h3>This Week's Goal</h3>
        @include('learner._goal-gauge', ['count' => $weeklyCount, 'target' => $weeklyTarget])
        <div class="goal-pill">{{ $weeklyMet ? 'Goal reached! 🎉' : $goalLeft.' more to go!' }}</div>
      </div>

      <div class="clay-card theme-mint">
        <h3>My Growth</h3>
        <div class="growth-note">You've read <b>{{ $weeklyCount }}</b> {{ $weeklyCount === 1 ? 'story' : 'stories' }} this week</div>
        <div class="week-bars">
          @foreach ($growthDays as $day)
            <div class="wb-col">
              <div class="wb-track {{ $day['isToday'] ? 'today' : '' }} {{ $day['isFuture'] ? 'future' : '' }}">
                @if ($day['count'] > 0)
                  <div class="wb-fill" style="height:{{ min(100, round($day['count'] / $growthScale * 100)) }}%"><span>{{ $day['count'] }}</span></div>
                @endif
              </div>
              <div class="wb-day {{ $day['isToday'] ? 'today' : '' }}">{{ $day['label'] }}</div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var anim = @json($animUrls);

  // ---- Popovers: click a pill to open its popover; opening one closes any other. ----
  var allPopovers = document.querySelectorAll('.popover');
  function closeAllPopovers() {
    allPopovers.forEach(function (p) { p.classList.remove('open'); });
    document.querySelectorAll('.pill[aria-expanded]').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
    // reset the popover-only animations so they replay from the start next time
    if (streakBurstAnim) streakBurstAnim.goToAndStop(0, true);
    if (pointsArtAnim) pointsArtAnim.goToAndStop(0, true);
  }
  // The caret points at the pill that opened the popover, wherever the pill sits in the row.
  function placeCaret(pill, popover) {
    var row = pill.closest('.stat-pills').getBoundingClientRect();
    var box = pill.getBoundingClientRect();
    popover.style.setProperty('--caret', Math.round(box.left + box.width / 2 - row.left + 10) + 'px');
  }
  function togglePopover(pill, popover) {
    var wasOpen = popover.classList.contains('open');
    closeAllPopovers();
    popover.classList.toggle('open', !wasOpen);
    if (!wasOpen) placeCaret(pill, popover);
    pill.setAttribute('aria-expanded', wasOpen ? 'false' : 'true');
    return !wasOpen;
  }

  // The small row icons: still until tapped, play once, settle back. Only one plays at a time.
  var streakRestFrame = 0;      // the flame's first frame is already a full flame
  var pointsRestFrame = null;   // the star builds up to its last frame, so rest there
  var streakIconAnim = lottie.loadAnimation({ container: document.getElementById('streakIconLottie'), renderer: 'svg', loop: false, autoplay: false, path: anim.flame });
  var pointsIconAnim = lottie.loadAnimation({ container: document.getElementById('pointsIconLottie'), renderer: 'svg', loop: false, autoplay: false, path: anim.star });
  pointsIconAnim.addEventListener('DOMLoaded', function () {
    pointsRestFrame = pointsIconAnim.totalFrames - 1;
    pointsIconAnim.goToAndStop(pointsRestFrame, true);
  });
  var streakBurstAnim = lottie.loadAnimation({ container: document.getElementById('streakBurstLottie'), renderer: 'svg', loop: false, autoplay: false, path: anim.flameBurst });
  var pointsArtAnim = lottie.loadAnimation({ container: document.getElementById('pointsArtLottie'), renderer: 'svg', loop: false, autoplay: false, path: anim.star });
  pointsArtAnim.addEventListener('DOMLoaded', function () { pointsArtAnim.goToAndStop(pointsArtAnim.totalFrames - 1, true); });

  function restIcon(a, frame) { if (a && frame !== null && frame !== undefined) a.goToAndStop(frame, true); }
  function playIconOnce(a) { if (a && !reduceMotion) a.goToAndPlay(0, true); }

  document.getElementById('levelPill').addEventListener('click', function () {
    togglePopover(this, document.getElementById('levelPopover'));
    restIcon(streakIconAnim, streakRestFrame); restIcon(pointsIconAnim, pointsRestFrame);
  });
  document.getElementById('streakPill').addEventListener('click', function () {
    var opened = togglePopover(this, document.getElementById('streakPopover'));
    restIcon(pointsIconAnim, pointsRestFrame); playIconOnce(streakIconAnim);
    if (opened && streakBurstAnim && !reduceMotion) streakBurstAnim.goToAndPlay(0, true);
  });
  document.getElementById('pointsPill').addEventListener('click', function () {
    var opened = togglePopover(this, document.getElementById('pointsPopover'));
    restIcon(streakIconAnim, streakRestFrame); playIconOnce(pointsIconAnim);
    if (opened && pointsArtAnim && !reduceMotion) pointsArtAnim.goToAndPlay(0, true);
  });
  document.addEventListener('click', function (e) {
    if (!e.target.closest('.pill-wrap') && !e.target.closest('.popover')) {
      closeAllPopovers();
      restIcon(streakIconAnim, streakRestFrame); restIcon(pointsIconAnim, pointsRestFrame);
    }
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAllPopovers(); });

  // ---- The flying owl marking the step a child has reached on each road. ----
  document.querySelectorAll('.road-owl').forEach(function (el) {
    lottie.loadAnimation({ container: el, renderer: 'svg', loop: true, autoplay: !reduceMotion, path: anim.owl });
  });
</script>
@endpush
