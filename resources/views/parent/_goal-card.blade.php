{{-- This week's reading goal for one child. Expects: $learner, $summary (App\Support\ChildSummary). --}}
<div class="eyebrow">This week's goal</div>
<div class="goal-top"><b>{{ $summary['weeklyCount'] }} <small>of {{ $summary['weeklyTarget'] }} readings</small></b></div>
<div class="segs">
  @for ($i = 1; $i <= $summary['weeklyTarget']; $i++)
    <span class="seg {{ $i <= $summary['weeklyCount'] ? 'on' : '' }}"></span>
  @endfor
</div>
<p class="goal-note">
  @if ($summary['weeklyMet'])
    {{ $learner->first_name }} reached the goal this week. Well done.
  @else
    {{ $learner->first_name }} is {{ $summary['weeklyLeft'] }} {{ $summary['weeklyLeft'] === 1 ? 'reading' : 'readings' }} away from the weekly goal.
  @endif
</p>
