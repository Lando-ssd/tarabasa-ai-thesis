{{--
  The open repository, called Activities in the menu (Parent Actor Prompt Step 6): extra reading
  activities teachers have shared, for one child at a time. Unlocking is always per activity and per
  child. Free ones unlock at once; paid ones ask first (a simulated payment, no gateway); an unlocked
  one can be rated, one rating per parent per activity. The child then reads what was unlocked through
  their own login. A parent never reads for the child directly.
--}}
@extends('layouts.parent-shell')

@section('title', 'Activities | TaraBasa AI')

@section('content')
<h1>Open repository</h1>

@if ($learners->isEmpty())
  <p class="sub">Extra reading activities shared by teachers across TaraBasa AI.</p>
  <div class="card empty-hero">
    <div class="empty-ico">@include('learner._badge-icon', ['icon' => 'storefront', 'class' => 'ico'])</div>
    <h2>Add a child first</h2>
    <p>Once you have added a child, you can unlock extra reading activities for them here.</p>
    <div class="actions"><a href="{{ route('parent.children.create') }}" class="btn">Add your child</a></div>
  </div>
@else
  <p class="sub">Extra reading activities shared by teachers across TaraBasa AI, for {{ $selectedLearner->first_name }}.</p>

  @if (session('status'))
    <div class="flash">{{ session('status') }}</div>
  @endif
  @if ($errors->any())
    <div class="flash error">{{ $errors->first() }}</div>
  @endif

  @include('parent._child-chips', ['route' => 'parent.repository.index', 'extra' => ['filter' => $filter]])

  <div class="filters" style="margin-top:18px">
    @foreach (['all' => 'All', 'free' => 'Free', 'paid' => 'Paid', 'unlocked' => 'Unlocked'] as $key => $label)
      <a href="{{ route('parent.repository.index', ['learner_id' => $selectedLearner->id, 'filter' => $key]) }}" class="chip plain {{ $filter === $key ? 'on' : '' }}" @if ($filter === $key) aria-current="true" @endif>{{ $label }}</a>
    @endforeach
  </div>

  @if ($rows->isEmpty())
    <div class="card empty-hero">
      <div class="empty-ico">@include('learner._badge-icon', ['icon' => 'storefront', 'class' => 'ico'])</div>
      <h2>{{ $filter === 'all' ? 'No activities shared yet' : 'No activities match this filter' }}</h2>
      <p>{{ $filter === 'all' ? 'Check back soon. Teachers share activities here as they create them.' : 'Try a different tab above.' }}</p>
    </div>
  @else
    <div class="grid3">
      @foreach ($rows as $row)
        @php $listing = $row['listing']; $activity = $listing->activity; @endphp
        <div class="card item-card">
          <div class="tags">
            <span class="tag">{{ $activity->competency_label }}</span>
            <span class="tag diff">{{ $activity->difficulty_tier }}</span>
            @if ($row['is_unlocked'])
              <span class="tag owned">Unlocked</span>
            @elseif ($listing->price_type === 'Free')
              <span class="tag free">Free</span>
            @else
              <span class="tag paid">&#8369;{{ number_format($listing->price) }}</span>
            @endif
          </div>
          <p class="item-name">{{ $activity->title }}</p>
          <p class="note" style="margin:0">{{ $activity->grade_level }} &middot; {{ $activity->competency_label }}</p>
          <p class="rating {{ $row['avg_rating'] === null ? 'none' : '' }}">
            {{ $row['avg_rating'] !== null ? '★ '.$row['avg_rating'].' ('.$row['rating_count'].' rating'.($row['rating_count'] === 1 ? '' : 's').')' : 'No ratings yet' }}
          </p>

          @if ($row['is_unlocked'])
            <button type="button" class="btn ghost" disabled>Ready to read</button>

            <form method="POST" action="{{ route('parent.repository.rate', $listing) }}" class="rate-form">
              @csrf
              <input type="hidden" name="learner_id" value="{{ $selectedLearner->id }}">
              <input type="hidden" name="rating" class="rating-input" value="{{ $row['my_rating']->rating ?? '' }}">
              <div class="eyebrow" style="margin:6px 0">Rate this activity</div>
              <div class="stars star-picker">
                @for ($i = 1; $i <= 5; $i++)
                  <button type="button" class="star {{ ($row['my_rating']->rating ?? 0) >= $i ? 'on' : '' }}" data-value="{{ $i }}" aria-label="{{ $i }} {{ $i === 1 ? 'star' : 'stars' }}">@include('learner._badge-icon', ['icon' => 'star', 'class' => 'ico'])</button>
                @endfor
              </div>
              <textarea name="comment" placeholder="Optional comment (for {{ $selectedLearner->first_name }})">{{ $row['my_rating']->comment ?? '' }}</textarea>
              <button type="submit" class="btn small" style="margin-top:10px">{{ $row['my_rating'] ? 'Update rating' : 'Rate this' }}</button>
            </form>
          @elseif ($listing->price_type === 'Free')
            <form method="POST" action="{{ route('parent.repository.unlock', $listing) }}">
              @csrf
              <input type="hidden" name="learner_id" value="{{ $selectedLearner->id }}">
              <button type="submit" class="btn" style="width:100%">Get for free</button>
            </form>
          @else
            @php $confirmId = 'confirm-'.$listing->id; @endphp
            <button type="button" class="btn blue" data-toggle-confirm="{{ $confirmId }}">@include('learner._badge-icon', ['icon' => 'lock-simple', 'class' => 'ico']) Unlock for &#8369;{{ number_format($listing->price) }}</button>
            <div class="confirm" id="{{ $confirmId }}" hidden>
              Unlock this for &#8369;{{ number_format($listing->price) }} for {{ $selectedLearner->first_name }}? This is a simulated payment, so nothing is charged.
              <form method="POST" action="{{ route('parent.repository.unlock', $listing) }}">
                @csrf
                <input type="hidden" name="learner_id" value="{{ $selectedLearner->id }}">
                <button type="submit" class="btn small" style="margin-top:10px">Confirm unlock</button>
              </form>
              <button type="button" class="btn ghost small" data-toggle-confirm="{{ $confirmId }}">Cancel</button>
            </div>
          @endif
        </div>
      @endforeach
    </div>
  @endif
@endif
@endsection

@push('scripts')
<script>
  // Paid activities: show or hide the "are you sure" panel.
  document.querySelectorAll('[data-toggle-confirm]').forEach(function (button) {
    button.addEventListener('click', function () {
      var panel = document.getElementById(button.dataset.toggleConfirm);
      if (panel) { panel.hidden = !panel.hidden; }
    });
  });

  // The five stars: tapping one fills up to it and sets the hidden rating that is submitted.
  document.querySelectorAll('.star-picker').forEach(function (picker) {
    var input = picker.closest('.rate-form').querySelector('.rating-input');
    var stars = picker.querySelectorAll('.star');
    stars.forEach(function (star) {
      star.addEventListener('click', function () {
        var value = parseInt(star.dataset.value, 10);
        input.value = value;
        stars.forEach(function (s) { s.classList.toggle('on', parseInt(s.dataset.value, 10) <= value); });
      });
    });
  });

  // A real loading state on every unlock and rate submission, so a second tap cannot double submit.
  document.querySelectorAll('.item-card form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      var button = e.submitter || form.querySelector('button[type="submit"]');
      if (!button) { return; }
      button.disabled = true;
      button.textContent = 'One moment';
    });
  });
</script>
@endpush
