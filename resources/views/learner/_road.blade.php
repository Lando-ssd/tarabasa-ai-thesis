{{--
  One skill's road map. Expects $row from Learner::subdomainProgressSummary():
  label, description, proficiency (0-100 or null), difficultyWord, isUpNext.

  Nine steps along a fixed S curve: eight stars and a trophy. How far a child
  has come is their real proficiency for the skill, one star per 12.5 points, so
  the flying owl sits on the step they have actually reached. A skill nobody has
  been measured on yet shows every step locked and no owl, never a made-up start.
--}}
@php
    // Step centres on the 600 x 875 road, spaced evenly along the S curve.
    $steps = [[300.0, 70.0], [389.3, 150.5], [434.1, 257.8], [371.6, 357.8], [280.4, 436.2], [194.5, 520.0], [172.5, 632.2], [247.2, 725.2], [339.1, 802.8]];
    // The five milestone words sit beside steps 1, 3, 5, 7 and 9, on the inner side of the curve.
    $milestones = [0 => ['Just Started', 'start'], 2 => ['Getting Going', 'end'], 4 => ['Halfway There', 'start'], 6 => ['Almost There', 'start'], 8 => ['All Done!', 'end']];

    $started = $row['proficiency'] !== null;
    $current = $started ? min(7, (int) floor($row['proficiency'] / 12.5)) : null;

    // The owl hangs beside its step, on the outer side, level with the puck.
    $owl = null;
    if ($current !== null) {
        [$cx, $cy] = $steps[$current];
        $left = $cx < 300 ? $cx - 193.3 : $cx + 41.6;
        $owl = ['left' => round($left / 600 * 100, 3), 'top' => round(($cy - 73.3) / 875 * 100, 3)];
    }
@endphp

<div class="roadmap-banner {{ $started ? '' : 'locked' }}">
  <div class="rb-text">
    @if ($row['isUpNext'])<div class="rb-eyebrow">⭐ Up Next</div>@endif
    <b>{{ $row['label'] }}</b>
    <span class="rb-sub">{{ $row['description'] }}</span>
  </div>
  <span class="roadmap-diff-chip {{ $started ? '' : 'locked' }}">{{ $row['difficultyWord'] ?? 'Not started yet' }}</span>
</div>

<div class="road-wrap">
  <svg class="roadmap-svg" viewBox="0 0 600 875" fill="none" role="img" aria-label="{{ $row['label'] }} road: {{ $started ? 'you are on step '.($current + 1).' of 8' : 'not started yet' }}">
    @foreach ($steps as $i => [$x, $y])
      @if ($i !== $current)<use href="#haloGray" transform="translate({{ $x }},{{ $y }})"/>@endif
    @endforeach
    @foreach ($steps as $i => [$x, $y])
      @if ($i === 8)
        <use href="#puckTrophy" transform="translate({{ $x }},{{ $y }})"/>
      @elseif ($i !== $current)
        <use href="{{ $current !== null && $i < $current ? '#puckDone' : '#puckLocked' }}" transform="translate({{ $x }},{{ $y }})"/>
      @endif
    @endforeach
    @if ($current !== null)
      <use href="#haloCurrent" transform="translate({{ $steps[$current][0] }},{{ $steps[$current][1] }})"/>
      <use href="#puckCurrent" transform="translate({{ $steps[$current][0] }},{{ $steps[$current][1] }})"/>
    @endif
    @foreach ($milestones as $i => [$text, $anchor])
      <text x="{{ $anchor === 'start' ? $steps[$i][0] + 62 : $steps[$i][0] - 62 }}" y="{{ $steps[$i][1] + 6 }}" @if ($anchor === 'end') text-anchor="end" @endif class="rl {{ $current !== null && $i <= $current ? 'rl-done' : 'rl-locked' }}">{{ $text }}</text>
    @endforeach
  </svg>
  @if ($owl)
    <div class="road-owl" style="left:{{ $owl['left'] }}%;top:{{ $owl['top'] }}%;width:26.667%"></div>
  @endif
</div>

@unless ($started)
  <div class="roadmap-caption locked">{{ $row['startHint'] ?? 'Fills in once you finish a reading for this skill.' }}</div>
@endunless
