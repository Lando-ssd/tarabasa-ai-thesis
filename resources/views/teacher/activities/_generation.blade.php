{{--
  The notice at the top of Activities for the last "Generate activities" request: writing now, done
  (not yet seen), or failed. It is drawn here for the state the page loads in; public/js/teacher-app.js
  then asks the status route every few seconds while it is still writing and redraws it. The
  wording for each state is in data-gs-* attributes' text below and is repeated in that script.
--}}
@php
    use App\Models\ActivityGeneration;

    $gs = $generation;
    $mostInOne = max($gs->levels ?: [0]);
    $minutes = max(1, (int) ceil(ActivityGeneration::estimateSeconds($mostInOne) / 60));
    $kind = $gs->isActive() ? 'work' : ($gs->status === ActivityGeneration::DONE ? 'done' : 'fail');
@endphp
<div class="alert gen-status {{ $kind === 'work' ? 'blue' : ($kind === 'done' ? 'green' : '') }}" id="genStatus" role="status" aria-live="polite"
     data-url="{{ route('teacher.activities.generation', $gs) }}"
     data-dismiss-url="{{ route('teacher.activities.generation.dismiss', $gs) }}"
     data-sprite="{{ asset('icons/badges.svg') }}"
     data-state="{{ $gs->status }}"
     data-started="{{ ($gs->started_at ?? $gs->created_at)->timestamp }}"
     data-minutes="{{ $minutes }}"
     data-total="{{ $gs->total() }}"
     data-summary="{{ $gs->summary() }}">
  <span class="alert-ico" data-gs-ico>
    @if ($kind === 'work')<span class="spin light"></span>@elseif ($kind === 'done')@include('learner._badge-icon', ['icon' => 'check', 'class' => 'ico'])@else @include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])@endif
  </span>
  <div>
    <b data-gs-title>
      @if ($kind === 'work')The AI is writing {{ $gs->total() }} {{ $gs->total() === 1 ? 'activity' : 'activities' }}
      @elseif ($kind === 'done')Your activities are ready
      @else The AI could not finish
      @endif
    </b>
    <span class="d" data-gs-text>
      @if ($kind === 'work')Asked for {{ $gs->summary() }}. This takes about {{ $minutes }} {{ $minutes === 1 ? 'minute' : 'minutes' }}, and longer if the AI service has been asleep. You can keep working. They appear in To review when they are ready.
      @else{{ $gs->message }}
      @endif
    </span>
  </div>
  <span class="gs-time" data-gs-elapsed @if ($kind !== 'work') hidden @endif></span>
  <button type="button" class="btn small ghost" data-gs-retry data-open="genDlg" @if ($kind !== 'fail') hidden @endif>Try again</button>
  <button type="button" class="btn small ghost" data-gs-dismiss @if ($kind === 'work') hidden @endif>{{ $kind === 'done' ? 'Got it' : 'Dismiss' }}</button>
</div>
