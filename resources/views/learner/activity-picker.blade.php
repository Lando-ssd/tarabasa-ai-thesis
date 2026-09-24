{{--
  Reading: what to read next. Expects $learner and $options, each option being
  ['activity' => Activity, 'source' => 'Picked just for you! 🎯' | 'Assigned by
  your Teacher' | 'Extra Practice']. The one the adaptive engine picked leads,
  as the peach card; the rest are blue cards.
--}}
@extends('layouts.learner-shell')

@section('title', 'What Should I Read? — TaraBasa AI')
@section('page', 'reading')

@php
    $alignment = app(\App\Services\MatatagAlignmentResolver::class);
    $labels = config('matatag_subdomains.labels');
    $difficultyWords = config('matatag_subdomains.difficulty_words');
@endphp

@section('content')
<div class="subpage" id="page-reading">
  <h1 class="pg-title">What Should I Read?</h1>

  @if ($options->isEmpty())
    <p class="pg-sub">Nothing to read yet. Ask your teacher, or check back soon!</p>
    <div class="rd-grid">
      <div class="rd-card rd-extra">
        <div class="rd-main">
          <div class="rd-icon">📚</div>
          <div class="rd-text"><h3>No activity yet</h3></div>
        </div>
        <p class="rd-line">When your teacher gives you a story, it will show up here.</p>
        <a class="clay-btn blue" style="display:inline-block;text-decoration:none;" href="{{ route('learner.dashboard') }}">Back to Home</a>
      </div>
    </div>
  @else
    <p class="pg-sub">Pick one and read it out loud!</p>
    <div class="rd-grid">
      @foreach ($options as $option)
        @php
            $activity = $option['activity'];
            $isPicked = str_contains($option['source'], 'Picked just for you');
            $isExtra = $option['source'] === 'Extra Practice';
            $skill = $labels[$alignment->subdomainFor($activity)]['label'] ?? null;
            $difficulty = $difficultyWords[strtolower((string) $activity->difficulty_tier)] ?? null;
            $minutes = max(1, (int) round(str_word_count((string) $activity->passage_text) / 40));
        @endphp
        <div class="rd-card {{ $isPicked ? 'rd-featured' : 'rd-extra' }}">
          @if ($isPicked)
            <div class="rd-owl"></div>
            <div class="rd-ribbon">🎯 Picked just for you</div>
          @endif
          <div class="rd-main">
            <div class="rd-icon">{{ $isExtra ? '🌟' : '📘' }}</div>
            <div class="rd-text">
              <h3>{{ $activity->title }}</h3>
              <div class="rd-tags">
                <span class="rd-tag {{ $isExtra ? 'green' : 'blue' }}">{{ $isExtra ? 'Extra Practice' : 'Assigned by your Teacher' }}</span>
                @if ($difficulty)<span class="rd-tag">{{ $difficulty }}</span>@endif
                <span class="rd-tag lilac">About {{ $minutes }} {{ $minutes === 1 ? 'minute' : 'minutes' }}</span>
              </div>
            </div>
          </div>
          <p class="rd-line">{{ $skill ? 'Practice: '.$skill.'. ' : '' }}Tara is cheering for you!</p>
          <a class="clay-btn {{ $isPicked ? 'orange' : 'blue' }}" style="display:inline-block;text-decoration:none;" href="{{ route('learner.activity.show', $activity) }}">Start Reading</a>
        </div>
      @endforeach
    </div>
  @endif
</div>
@endsection

@push('scripts')
<script>
  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  document.querySelectorAll('.rd-owl').forEach(function (el) {
    lottie.loadAnimation({ container: el, renderer: 'svg', loop: true, autoplay: !reduceMotion, path: @json(asset('animations/learner/owl-bird.json')) });
  });
</script>
@endpush
