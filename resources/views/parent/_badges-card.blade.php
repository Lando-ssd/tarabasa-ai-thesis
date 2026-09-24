{{-- The badges a child has earned (the newest three). Expects: $summary (App\Support\ChildSummary). --}}
<div class="eyebrow">Badges</div>
<h3 style="margin-top:8px">{{ $summary['badgesEarned'] }} of {{ $summary['badgesTotal'] }} earned</h3>
@if (empty($summary['latestBadges']))
  <p class="none">No badges yet. The first one comes with the first reading.</p>
@else
  <div class="medals">
    @foreach ($summary['latestBadges'] as $badge)
      <div class="medal">
        <div class="medal-disc">@include('learner._badge-icon', ['icon' => $badge['icon'], 'class' => 'ico'])</div>
        <span>{{ $badge['name'] }}</span>
      </div>
    @endforeach
  </div>
@endif
