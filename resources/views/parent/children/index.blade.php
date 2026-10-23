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
      @php $hasOthers = isset($sharedIds[$learner->id]); @endphp
      <div class="card child-card">
        <div class="child-top">
          @include('parent._avatar', ['learner' => $learner, 'class' => 'focus-av'])
          <div>
            <h3>{{ $learner->first_name }}</h3>
            <div class="g">{{ $learner->grade_level }} &middot; {{ $learner->schoolClass->name ?? 'Not in a class yet' }}</div>
          </div>
        </div>
        <div class="pills">
          @php $step = \App\Support\ParentInsights::step($learner); @endphp
          <span class="pill {{ $step['class'] }}">{{ $step['pill'] }}</span>
          <span class="pill">@include('learner._badge-icon', ['icon' => 'star', 'class' => 'ico']) {{ $learner->points }} points</span>
          <span class="pill">@include('learner._badge-icon', ['icon' => 'fire', 'class' => 'ico']) {{ $learner->readingDayStreak() }} {{ $learner->readingDayStreak() === 1 ? 'day' : 'days' }} in a row</span>
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
          <button type="button" class="btn ghost small" data-qr-open="pin-{{ $learner->id }}">@include('learner._badge-icon', ['icon' => 'key', 'class' => 'ico']) New PIN</button>
        </div>
        <div class="rm-row"><button type="button" class="rm-link" data-qr-open="rm-{{ $learner->id }}">{{ $hasOthers ? 'Remove me from '.$learner->first_name : 'Delete '.$learner->first_name."'s profile" }}</button></div>

        {{-- Forgot the PIN? A guardian makes a new one. The rules (password again, 3 a day, no easy PINs) are enforced by LearnerPinService; this only asks nicely. --}}
        @php
          $madePin = session('new_pin');
          $madeForThis = is_array($madePin) && ($madePin['learner_id'] ?? null) === $learner->id ? $madePin['pin'] : null;
          $formFailed = (string) old('pin_form') === (string) $learner->id && $errors->any();
        @endphp
        <dialog id="pin-{{ $learner->id }}" class="qr-dlg pin-dlg" aria-labelledby="pin-title-{{ $learner->id }}" @if ($madeForThis || $formFailed) data-open @endif>
          <div class="qr-in pin-in">
            <button type="button" class="qr-x" data-qr-close aria-label="Close">@include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])</button>

            @if ($madeForThis)
              <h3 id="pin-title-{{ $learner->id }}">{{ $learner->first_name }}'s new PIN</h3>
              <div class="pin-digits" role="img" aria-label="New PIN: {{ implode(' ', str_split($madeForThis)) }}">
                @foreach (str_split($madeForThis) as $digit)<span>{{ $digit }}</span>@endforeach
              </div>
              <p class="pin-warn"><b>Tell {{ $learner->first_name }} now, or write it down.</b> For safety, TaraBasa cannot show this PIN again.</p>
              <ul class="pin-notes">
                <li>The old PIN does not work any more.</li>
                <li>{{ $learner->first_name }} is signed out on any other device.</li>
                <li>{{ $learner->first_name }} can type the new PIN right away.</li>
              </ul>
              <div class="qr-actions"><button type="button" class="btn small" data-qr-close>Done</button></div>
            @else
              <h3 id="pin-title-{{ $learner->id }}">New PIN for {{ $learner->first_name }}</h3>
              <p class="pin-lead">Forgot the PIN? Make a new one. The old PIN stops working right away.</p>

              <form method="POST" action="{{ route('parent.children.pin', $learner) }}" class="pin-form" autocomplete="off" novalidate>
                @csrf
                <input type="hidden" name="pin_form" value="{{ $learner->id }}">
                @php $mode = $formFailed ? old('mode', 'generate') : 'generate'; @endphp

                <div class="pin-modes" role="radiogroup" aria-label="How to make the new PIN">
                  <label class="pin-mode"><input type="radio" name="mode" value="generate" @checked($mode === 'generate')><span><b>Make one for me</b><small>We pick a random PIN and show it once.</small></span></label>
                  <label class="pin-mode"><input type="radio" name="mode" value="choose" @checked($mode === 'choose')><span><b>I'll choose one</b><small>Four numbers {{ $learner->first_name }} can remember.</small></span></label>
                </div>

                <div class="pin-choose" @if ($mode !== 'choose') hidden @endif>
                  <div class="pin-pair">
                    <label>New PIN<input type="text" name="pin" inputmode="numeric" pattern="[0-9]*" maxlength="4" autocomplete="off" placeholder="4 numbers" class="@if ($formFailed && $errors->has('pin')) error @endif"></label>
                    <label>Again<input type="text" name="pin_confirmation" inputmode="numeric" pattern="[0-9]*" maxlength="4" autocomplete="off" placeholder="Type it again" class="@if ($formFailed && $errors->has('pin_confirmation')) error @endif"></label>
                  </div>
                  <p class="pin-hint">Not allowed: four of the same number (1111) or a run (1234, 4321), or the PIN {{ $learner->first_name }} has now.</p>
                </div>

                <label class="pin-pw">Your TaraBasa password <small>so we know it is you</small>
                  <input type="password" name="password" autocomplete="current-password" required class="@if ($formFailed && $errors->has('password')) error @endif">
                </label>

                @if ($formFailed)
                  @foreach (['password', 'pin', 'pin_confirmation', 'mode'] as $field)
                    @if ($errors->has($field))<div class="flash error pin-err" role="alert">{{ $errors->first($field) }}</div>@endif
                  @endforeach
                @endif

                <p class="pin-hint">You can make up to 3 new PINs a day for each child. The other guardians are told when a PIN changes.</p>
                <div class="qr-actions">
                  <button type="submit" class="btn small" data-pin-submit>Make a new PIN</button>
                  <button type="button" class="btn ghost small" data-qr-close>Cancel</button>
                </div>
              </form>
            @endif
          </div>
        </dialog>

        {{-- Remove the child. Alone as a guardian: deletes the profile and every record for good. With another guardian: only unlinks this parent. DataErasureService enforces it; this explains it. --}}
        @php $removeFailed = (string) old('remove_form') === (string) $learner->id && $errors->any(); @endphp
        <dialog id="rm-{{ $learner->id }}" class="qr-dlg pin-dlg" aria-labelledby="rm-title-{{ $learner->id }}" @if ($removeFailed) data-open @endif>
          <div class="qr-in pin-in">
            <button type="button" class="qr-x" data-qr-close aria-label="Close">@include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])</button>
            <h3 id="rm-title-{{ $learner->id }}">{{ $hasOthers ? 'Remove yourself from '.$learner->first_name.'?' : 'Delete '.$learner->first_name."'s profile?" }}</h3>
            @if ($hasOthers)
              <p class="pin-lead">{{ $learner->first_name }} has another guardian. This only removes <b>you</b>. {{ $learner->first_name }}'s profile and reading records stay for them, and you will no longer see {{ $learner->first_name }} here.</p>
            @else
              <p class="pin-lead">You are {{ $learner->first_name }}'s only guardian, so this <b>deletes {{ $learner->first_name }}'s profile for good</b>: every reading, badge, word, alert and game record, and the sign in. This cannot be undone.</p>
            @endif

            <form method="POST" action="{{ route('parent.children.remove', $learner) }}" class="erase-form" autocomplete="off" novalidate>
              @csrf
              <input type="hidden" name="remove_form" value="{{ $learner->id }}">
              <label class="pin-pw">Your TaraBasa password <small>so we know it is you</small>
                <input type="password" name="password" autocomplete="current-password" required class="@if ($removeFailed && $errors->has('password')) error @endif">
              </label>
              <label class="erase-check"><input type="checkbox" name="confirm" value="1" required><span>I understand this cannot be undone.</span></label>
              @if ($removeFailed)
                @foreach (['password', 'confirm'] as $field)
                  @if ($errors->has($field))<div class="flash error pin-err" role="alert">{{ $errors->first($field) }}</div>@endif
                @endforeach
              @endif
              <div class="qr-actions">
                <button type="submit" class="btn small danger-fill" data-erase-submit>{{ $hasOthers ? 'Remove me' : 'Delete '.$learner->first_name }}</button>
                <button type="button" class="btn ghost small" data-qr-close>Cancel</button>
              </div>
            </form>
          </div>
        </dialog>
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

  // A "New PIN" window that has a result or an error to show opens by itself when the page loads.
  document.querySelectorAll('dialog[data-open]').forEach(function (d) { if (d.showModal) { d.showModal(); } });

  // The New PIN form: the PIN boxes only show for "I'll choose one", only numbers can be typed, and one press sends it once.
  document.querySelectorAll('.pin-form').forEach(function (form) {
    var choose = form.querySelector('.pin-choose');
    var submit = form.querySelector('[data-pin-submit]');
    function sync() {
      var mode = form.querySelector('input[name="mode"]:checked').value;
      choose.hidden = mode !== 'choose';
      submit.textContent = mode === 'choose' ? 'Save new PIN' : 'Make a new PIN';
    }
    form.querySelectorAll('input[name="mode"]').forEach(function (r) { r.addEventListener('change', sync); });
    form.querySelectorAll('input[name="pin"], input[name="pin_confirmation"]').forEach(function (i) {
      i.addEventListener('input', function () { i.value = i.value.replace(/\D/g, '').slice(0, 4); });
    });
    form.addEventListener('submit', function () { submit.disabled = true; submit.textContent = 'Working...'; });
    sync();
  });

  // The Remove window: one press sends it once.
  document.querySelectorAll('.erase-form').forEach(function (form) {
    var submit = form.querySelector('[data-erase-submit]');
    form.addEventListener('submit', function () { submit.disabled = true; submit.textContent = 'Working...'; });
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
