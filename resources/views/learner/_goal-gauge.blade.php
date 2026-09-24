{{--
  The weekly goal dial: one arc per reading in the target, filled for each real
  reading this week. Expects $count and $target. Arc geometry is computed, so it
  stays correct if the target in config/reading_goals.php ever changes.
--}}
@php
    $target = max(1, (int) $target);
    $filled = min((int) $count, $target);
    $step = 360 / $target;          // degrees each arc owns
    $span = $step * 0.625;          // the visible part of it (45 of 72 degrees when the target is 5)
    $gap = $step - $span;
    $arc = function (int $i, float $radius) use ($step, $span, $gap) {
        $start = deg2rad(-90 + $gap / 2 + $i * $step);
        $end = deg2rad(-90 + $gap / 2 + $i * $step + $span);

        return sprintf('M%.2f,%.2f A%s,%s 0 0 1 %.2f,%.2f', 100 + $radius * cos($start), 100 + $radius * sin($start), $radius, $radius, 100 + $radius * cos($end), 100 + $radius * sin($end));
    };
    $met = $count >= $target;
@endphp
<svg class="goal-gauge" viewBox="0 0 200 212" fill="none" role="img" aria-label="{{ $count }} readings this week, goal {{ $target }}">
  <defs>
    <linearGradient id="gaugeOrange" gradientUnits="userSpaceOnUse" x1="34" y1="26" x2="166" y2="178">
      <stop offset="0" stop-color="#ffbe6c"/><stop offset=".5" stop-color="#ef8d2a"/><stop offset="1" stop-color="#d0670b"/>
    </linearGradient>
    <linearGradient id="gaugePlate" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#e9f2ff"/><stop offset="1" stop-color="#b3cef0"/>
    </linearGradient>
    <linearGradient id="gaugeDisc" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#fff6e6"/><stop offset="1" stop-color="#ffd9a8"/>
    </linearGradient>
  </defs>
  <circle cx="100" cy="108" r="94" fill="#82a7d8"/>
  <circle cx="100" cy="100" r="94" fill="url(#gaugePlate)" stroke="#cfe1f8" stroke-width="2"/>
  <g stroke="#7397c8" stroke-width="27" stroke-linecap="round">
    @for ($i = 0; $i < $target; $i++)<path d="{{ $arc($i, 68) }}"/>@endfor
  </g>
  <g stroke="#a1bfe5" stroke-width="22" stroke-linecap="round">
    @for ($i = 0; $i < $target; $i++)<path d="{{ $arc($i, 68) }}"/>@endfor
  </g>
  @if ($filled > 0)
    <g transform="translate(0,5)" stroke="#a84f0a" stroke-width="24" stroke-linecap="round">
      @for ($i = 0; $i < $filled; $i++)<path d="{{ $arc($i, 68) }}"/>@endfor
    </g>
    <g stroke="url(#gaugeOrange)" stroke-width="24" stroke-linecap="round">
      @for ($i = 0; $i < $filled; $i++)<path class="gseg" style="animation-delay:{{ number_format($i * 0.12, 2) }}s" d="{{ $arc($i, 68) }}"/>@endfor
    </g>
    <g stroke="rgba(255,255,255,.5)" stroke-width="4" stroke-linecap="round">
      @for ($i = 0; $i < $filled; $i++)<path class="gseg" style="animation-delay:{{ number_format($i * 0.12, 2) }}s" d="{{ $arc($i, 75.5) }}"/>@endfor
    </g>
  @endif
  <circle cx="100" cy="106" r="48" fill="#e3b573"/>
  <circle cx="100" cy="100" r="48" fill="url(#gaugeDisc)" stroke="#ffeacb" stroke-width="2"/>
  <text x="100" y="116" text-anchor="middle" class="gauge-num gauge-num-shadow">{{ $count }}</text>
  <text x="100" y="113" text-anchor="middle" class="gauge-num">{{ $count }}</text>
  {{-- Past the target "10 of 5" reads like a mistake, so once the goal is met the caption is the goal itself. --}}
  <text x="100" y="137" text-anchor="middle" class="gauge-of">{{ $met ? 'goal met' : 'of '.$target }}</text>
</svg>
