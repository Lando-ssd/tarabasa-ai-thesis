{{--
  My Children: one card per child with their level, points and real day streak, the Learner Code
  (with a copy button), and a shortcut to their progress. Adding or linking a child opens the
  add-a-child steps, which are still their own focused screens.
--}}
@extends('layouts.parent-shell')

@section('title', 'Children | TaraBasa AI')

@section('content')
<h1>My children</h1>
<p class="sub">Keep track of each child's reading in one place. Give a child their Learner Code so they can log in.</p>

@if (session('status'))
  <div class="flash">{{ session('status') }}</div>
@endif

@if ($learners->isEmpty())
  <div class="card empty-hero">
    <div class="empty-ico">@include('learner._badge-icon', ['icon' => 'users-three', 'class' => 'ico'])</div>
    <h2>Add your first child</h2>
    <p>Create a profile for your child to get their own Learner Code and PIN. No class or teacher is needed to get started.</p>
    <div class="actions">
      <a href="{{ route('parent.children.create') }}" class="btn">Add your child</a>
      <a href="{{ route('parent.children.link') }}" class="btn ghost">Link an existing child's account</a>
    </div>
  </div>
@else
  <div class="grid3">
    @foreach ($learners as $learner)
      <div class="card child-card">
        <div class="child-top">
          @include('parent._avatar', ['learner' => $learner, 'class' => 'focus-av'])
          <div>
            <h3>{{ $learner->first_name }}</h3>
            <div class="g">{{ $learner->grade_level }} &middot; {{ $learner->schoolClass->name ?? 'Not in a class yet' }}</div>
          </div>
        </div>
        <div class="pills">
          @if ($learner->mastery_level)
            <span class="pill lv-{{ strtolower($learner->mastery_level) }}">{{ $learner->mastery_level }}</span>
          @else
            <span class="pill">Not assessed yet</span>
          @endif
          <span class="pill">@include('learner._badge-icon', ['icon' => 'star', 'class' => 'ico']) {{ $learner->points }} points</span>
          <span class="pill">@include('learner._badge-icon', ['icon' => 'fire', 'class' => 'ico']) {{ $learner->readingDayStreak() }} days in a row</span>
          @if ($learner->learning_style)<span class="pill">{{ $learner->learning_style }}</span>@endif
        </div>
        <div class="code-row">
          <div><small>Learner code</small><code>{{ $learner->learner_code }}</code></div>
          <button class="btn ghost small copy" type="button" data-copy="{{ $learner->learner_code }}">@include('learner._badge-icon', ['icon' => 'copy', 'class' => 'ico']) Copy</button>
          <button class="btn ghost small" type="button" data-qr-open="qr-{{ $learner->id }}">@include('learner._badge-icon', ['icon' => 'qr-code', 'class' => 'ico']) QR</button>
        </div>
        <dialog id="qr-{{ $learner->id }}" class="qr-dlg" aria-label="QR code for {{ $learner->first_name }}">
          <div class="qr-in">
            <button type="button" class="qr-x" data-qr-close aria-label="Close">@include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])</button>
            <h3>{{ $learner->first_name }}'s QR code</h3>
            <div class="qr-img">{!! \App\Support\LearnerQr::svg($learner->learner_code, 320) !!}</div>
            <code class="qr-code">{{ $learner->learner_code }}</code>
            <p>Show this to {{ $learner->first_name }}'s teacher. They scan it with TaraBasa (Add learner) to put {{ $learner->first_name }} in the class, instead of typing the code. It holds the code only, never the PIN.</p>
            <div class="qr-actions">
              <a class="btn small" href="{{ route('parent.children.card', $learner) }}" target="_blank" rel="noopener">@include('learner._badge-icon', ['icon' => 'printer', 'class' => 'ico']) Print card</a>
              <button type="button" class="btn ghost small" data-qr-close>Close</button>
            </div>
          </div>
        </dialog>
        <div class="foot">
          <a href="{{ route('parent.progress', ['learner_id' => $learner->id]) }}" class="btn ghost small">See progress</a>
        </div>
      </div>
    @endforeach
  </div>

  <div class="actions" style="margin-top:22px">
    <a href="{{ route('parent.children.create') }}" class="btn">@include('learner._badge-icon', ['icon' => 'plus-circle', 'class' => 'ico']) Add another child</a>
    <a href="{{ route('parent.children.link') }}" class="btn ghost">@include('learner._badge-icon', ['icon' => 'link-simple', 'class' => 'ico']) Link an existing child</a>
  </div>
@endif
@endsection

@push('scripts')
<script>
  // The QR window of each child: opens from the QR button, closes with Close, Escape or a tap outside.
  document.querySelectorAll('[data-qr-open]').forEach(function (button) {
    button.addEventListener('click', function () { var d = document.getElementById(button.dataset.qrOpen); if (d && d.showModal) { d.showModal(); } });
  });
  document.querySelectorAll('dialog.qr-dlg').forEach(function (d) {
    d.addEventListener('click', function (e) { if (e.target === d || e.target.closest('[data-qr-close]')) { d.close(); } });
  });

  // The copy buttons: copy the Learner Code, and say so on the button for a moment.
  document.querySelectorAll('[data-copy]').forEach(function (button) {
    button.addEventListener('click', function () {
      var label = button.lastChild;
      var done = function () { var old = label.textContent; label.textContent = ' Copied'; setTimeout(function () { label.textContent = old; }, 1600); };
      try { navigator.clipboard.writeText(button.dataset.copy).then(done, done); } catch (e) { done(); }
    });
  });
</script>
@endpush
