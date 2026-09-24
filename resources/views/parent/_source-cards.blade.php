{{-- Sessions the teacher assigned versus sessions a parent started. Expects: $sourceSummary (ReadingSession::sourceSummaryForLearner). --}}
<div class="src">
  @foreach (['Teacher' => 'Teacher assigned sessions', 'Parent' => 'Parent started sessions'] as $source => $label)
    @php $avg = $sourceSummary[$source]['avg_accuracy']; @endphp
    <div class="card">
      <div class="label">{{ $label }}</div>
      <div class="value">{{ $sourceSummary[$source]['count'] }}</div>
      <div class="avg">Average score: {{ $avg !== null ? $avg.'%' : 'not yet' }}</div>
    </div>
  @endforeach
</div>
