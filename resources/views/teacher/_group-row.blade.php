{{-- One learner in a group's list. Expects $row (learner, session_count, avg_accuracy). --}}
@php $l = $row['learner']; @endphp
<div class="lrow static" @if (! empty($searchable)) data-search="{{ strtolower($l->first_name.' '.$l->last_name) }}" @endif>
  @include('teacher._avatar', ['learner' => $l])
  <span class="lname"><b>{{ $l->first_name }} {{ $l->last_name }}</b><small>{{ $l->mastery_level ?? 'New' }} · {{ $row['session_count'] }} {{ $row['session_count'] === 1 ? 'session' : 'sessions' }}</small></span>
  <a class="btn small ghost" href="{{ route('teacher.analytics.index', ['mode' => 'learner', 'learner_id' => $l->id]) }}">Progress</a>
</div>
