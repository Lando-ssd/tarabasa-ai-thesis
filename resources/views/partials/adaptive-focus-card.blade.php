{{--
  Shared by teacher/analytics.blade.php (By Learner mode) and
  parent/progress.blade.php — makes the Adaptive_Recommendator engine's
  current focus for this specific Learner visible to the adult actors
  too, not just the "Picked just for you!" picker label and the
  Learner's own "How I'm Growing" dashboard section. Markup only — each
  including page defines the .adaptive-card/.adaptive-chip styles in its
  own <style> block (this app's standalone-per-view convention), reusing
  tokens (--line/--surface/--slate-600/--owl-orange-600) both pages
  already define.

  Expects: $learner (the full Learner model — competency_states and
  next_recommended_competency are read directly off it).
--}}
@php $focus = $learner->competencyProgressSummary(); @endphp
<div class="adaptive-card">
  <div class="adaptive-card-title">🎯 Adaptive Focus</div>
  @if (empty($focus))
    <p class="adaptive-empty">{{ $learner->first_name }} hasn't completed their first-login reading check yet — this fills in once they do.</p>
  @else
    @php $upNext = collect($focus)->firstWhere('isUpNext', true); @endphp
    <p class="adaptive-headline">
      @if ($upNext)
        Currently practicing: <b>{{ $upNext['label'] }}</b>@if ($upNext['difficultyWord']) ({{ $upNext['difficultyWord'] }})@endif
      @else
        No specific focus recommended right now.
      @endif
    </p>
    <div class="adaptive-chips">
      @foreach ($focus as $item)
        <span class="adaptive-chip {{ $item['isUpNext'] ? 'active' : '' }}">
          {{ $item['label'] }}@if ($item['difficultyWord']) — {{ $item['difficultyWord'] }}@else — Not assessed @endif
        </span>
      @endforeach
    </div>
  @endif
</div>
