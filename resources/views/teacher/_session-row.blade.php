{{-- One reading session in a list. Expects $s (a ReadingSession with its activity). --}}
@php
    $acc = (int) round($s->accuracy_percent ?? 0);
    $cls = $acc >= 80 ? 'good' : ($acc >= 65 ? 'mid' : 'low');
@endphp
<div class="sess">
  <div class="s-info"><b>{{ $s->activity->title ?? 'Reading Activity' }}</b><span>{{ $s->timestamp->format('M j, g:i A') }} · {{ $s->level_after ?? 'New' }}</span></div>
  <span class="pill blue">{{ $s->initiated_by === 'Teacher' ? 'You' : 'Parent' }}</span>
  <span class="score {{ $cls }}">{{ $acc }}%</span>
</div>
