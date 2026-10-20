{{--
  My Profile (Teacher): name and contact number, the read-only school verification (it went
  through Admin verification, so it is not editable here), and a Change password window. Email is
  the login and is not editable here.
--}}
@extends('layouts.teacher-shell')

@section('title', 'Profile | TaraBasa AI')

@php
    $isActive = $teacher->status === 'Active';
    $pwErrors = $errors->hasAny(['current_password', 'password']);
@endphp

@section('content')
<div class="head-row">
  <div class="head-av">{{ mb_strtoupper(mb_substr($user->first_name, 0, 1)) }}{{ mb_strtoupper(mb_substr($user->last_name, 0, 1)) }}</div>
  <div>
    <h1 style="margin:0">My profile</h1>
    <div class="note">{{ $user->email }}</div>
  </div>
</div>

<div class="profile-grid">
  <div class="card">
    <p class="card-title">Personal information</p>
    <p class="card-sub">Your name and contact number. Your email is your login and can't be changed here.</p>

    @if ($errors->hasAny(['first_name', 'last_name', 'middle_initial', 'contact_number']))
      <div class="form-error-banner">Please fix the highlighted fields below.</div>
    @endif

    <form method="POST" action="{{ route('profile.update') }}" data-busy="Saving">
      @csrf
      @method('PUT')
      <input type="hidden" name="form" value="profile">
      <div class="name-row">
        <div class="field">
          <label for="first_name">First name</label>
          <input type="text" name="first_name" id="first_name" value="{{ old('first_name', $user->first_name) }}" class="@error('first_name') error @enderror" required>
          @error('first_name') <span class="field-error">{{ $message }}</span> @enderror
        </div>
        <div class="field">
          <label for="middle_initial">M.I. <span class="opt">optional</span></label>
          <input type="text" name="middle_initial" id="middle_initial" maxlength="5" value="{{ old('middle_initial', $user->middle_initial) }}" class="@error('middle_initial') error @enderror">
          @error('middle_initial') <span class="field-error">{{ $message }}</span> @enderror
        </div>
        <div class="field">
          <label for="last_name">Last name</label>
          <input type="text" name="last_name" id="last_name" value="{{ old('last_name', $user->last_name) }}" class="@error('last_name') error @enderror" required>
          @error('last_name') <span class="field-error">{{ $message }}</span> @enderror
        </div>
      </div>
      <div class="field">
        <label for="contact_number">Contact number <span class="opt">optional</span></label>
        <input type="text" name="contact_number" id="contact_number" placeholder="e.g. 0917 123 4567" value="{{ old('contact_number', $user->contact_number) }}" class="@error('contact_number') error @enderror">
        @error('contact_number') <span class="field-error">{{ $message }}</span> @enderror
      </div>
      <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" value="{{ $user->email }}" readonly>
      </div>
      <button type="submit" class="btn">Save changes</button>
    </form>
  </div>

  <div class="stack" style="display:flex;flex-direction:column;gap:16px">
    @php
        $current = $teacher->gradesAllowed();
        $pending = $teacher->gradesPending();
        $decision = $teacher->latestGradeDecision();
    @endphp
    <div class="card" id="grades-card">
      <p class="card-title">Your classes</p>
      <p class="card-sub">The grades you handle. Creating a class offers only these, and every class holds one grade only. Classes you already have are not changed.</p>

      <p style="margin:0 0 10px;display:flex;gap:6px;flex-wrap:wrap;align-items:center"><span class="note" style="margin-right:2px">Handled now:</span>
        @foreach ($current as $g)<span class="pill ok">{{ $g }}</span>@endforeach
      </p>

      @if ($pending !== [])
        <div class="help">@include('learner._badge-icon', ['icon' => 'info', 'class' => 'ico'])<div><b>Waiting for the Admin.</b> You asked to handle {{ \Illuminate\Support\Arr::join($pending, ', ', ' and ') }}. The Admin checks it with your school first. Until it is approved you can open classes for {{ \Illuminate\Support\Arr::join($current, ', ', ' and ') }} only.
          @if ($teacher->grades_request_note)<br><span class="note">Your note: {{ $teacher->grades_request_note }}</span>@endif
          <form method="POST" action="{{ route('profile.grades.cancel') }}" data-busy="Cancelling" style="margin-top:8px">@csrf @method('DELETE')<button type="submit" class="btn small ghost">Cancel this request</button></form>
        </div></div>
      @elseif ($decision)
        <div class="help {{ $decision['approved'] ? 'good' : '' }}">@include('learner._badge-icon', ['icon' => $decision['approved'] ? 'check-circle' : 'info', 'class' => 'ico'])<div>{{ $decision['message'] }}</div></div>
      @endif

      <form method="POST" action="{{ route('profile.grades.update') }}" data-busy="Saving" id="grades-form" data-current='@json($current)'>
        @csrf
        @method('PUT')
        <input type="hidden" name="form" value="profile">
        @include('teacher._grades-field', ['selected' => $pending !== [] ? $pending : $current, 'idp' => 'prof'])

        <div class="field" id="grade-note-box" hidden>
          <label for="grades_request_note">Who at your school can confirm this?</label>
          <textarea name="grades_request_note" id="grades_request_note" rows="2" maxlength="300" placeholder="For example: Mrs. Cruz, the school head, gave me Grade 2 this school year." class="@error('grades_request_note') error @enderror">{{ old('grades_request_note', $teacher->grades_request_note) }}</textarea>
          <span class="fhint">Adding a grade is checked by the Admin with your school, because the school head assigns who teaches which grade. Taking a grade away takes effect at once.</span>
          @error('grades_request_note') <span class="field-error">{{ $message }}</span> @enderror
        </div>

        <details class="note" style="margin:0 0 12px">
          <summary style="cursor:pointer">Why one grade per class, and why the Admin checks</summary>
          <p style="margin:8px 0 0">The curriculum TaraBasa follows is written grade by grade (the MATATAG curriculum, DepEd Order 10, s. 2024), so a class, its activities and its reading levels are for one grade only. Which grade a teacher teaches is decided by the school, through the school head's class program, so a new grade is confirmed with the school before you can open a class for it.</p>
        </details>
        <button type="submit" class="btn small" id="grades-submit" data-pending="{{ $pending !== [] ? '1' : '0' }}">{{ $pending !== [] ? 'Send a different request' : 'Save grades' }}</button>
      </form>
      <script>
        (function () {
          var form = document.getElementById('grades-form'), box = document.getElementById('grade-note-box'), submit = document.getElementById('grades-submit');
          if (!form || !box || !submit) return;
          var current = JSON.parse(form.getAttribute('data-current') || '[]');
          function sync() {
            var added = Array.prototype.some.call(form.querySelectorAll('input[name="grades_handled[]"]'), function (c) { return c.checked && current.indexOf(c.value) === -1; });
            box.hidden = !added;
            // Adding a grade is a request to the Admin; taking one away saves at once.
            submit.textContent = added ? (submit.getAttribute('data-pending') === '1' ? 'Send a different request' : 'Ask the Admin') : (submit.getAttribute('data-pending') === '1' ? 'Save grades (cancels your request)' : 'Save grades');
          }
          form.addEventListener('change', sync);
          sync();
        })();
      </script>
    </div>

    <div class="card">
      <p class="card-title">School verification</p>
      <span class="pill {{ $isActive ? 'ok' : 'amber' }}">@if ($isActive)@include('learner._badge-icon', ['icon' => 'seal-check', 'class' => 'ico']) Verified @else Waiting for Admin approval @endif</span>
      <div class="locked-note">
        @include('learner._badge-icon', ['icon' => 'lock-simple', 'class' => 'ico'])
        <span>These went through Admin verification, so they can't be edited here. Contact your school's TaraBasa admin if either needs to change.</span>
      </div>
      <div class="field"><label for="school_name">School name</label><input type="text" id="school_name" value="{{ $teacher->school_name }}" readonly></div>
      <div class="field" style="margin-bottom:0"><label for="employee_id">DepEd employee ID</label><input type="text" id="employee_id" value="{{ $teacher->employee_id }}" readonly></div>
    </div>

    <div class="card erase-card">
      <p class="card-title">Delete my account</p>
      <p class="card-sub">This is permanent. Here is exactly what happens:</p>
      <ul class="erase-list">
        @foreach ($erase['lines'] as $line)<li>{{ $line }}</li>@endforeach
      </ul>
      @if ($erase['blocked'])
        <div class="locked-note">@include('learner._badge-icon', ['icon' => 'warning-circle', 'class' => 'ico'])<span>{{ $erase['blocked'] }}</span></div>
      @else
        <button type="button" class="btn ghost danger" data-open="eraseDlg">Delete my account</button>
      @endif
    </div>

    <div class="card">
      <p class="card-title">Password</p>
      <p class="card-sub">Change it any time. You will need your current password.</p>
      <button type="button" class="btn ghost" data-open="pwdDlg">@include('learner._badge-icon', ['icon' => 'key', 'class' => 'ico']) Change password</button>
    </div>
  </div>
</div>
@endsection

@push('dialogs')
  @php $eraseFailed = (string) old('erase_form') === '1' && $errors->erase->any(); @endphp
  <dialog id="eraseDlg" class="win" aria-label="Delete my account" @if ($eraseFailed) data-autoopen @endif>
    <div class="win-in narrow">
      <header class="win-head">
        <div class="win-titles"><h2>Delete your account?</h2><p class="win-meta">You cannot get it back. Type your password to confirm it is you.</p></div>
        <button type="button" class="x" data-close aria-label="Close">@include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])</button>
      </header>
      <div class="win-body">
        <form id="erase-form" method="POST" action="{{ route('profile.delete') }}" data-busy="Deleting" autocomplete="off">
          @csrf
          <input type="hidden" name="erase_form" value="1">
          <div class="field"><label for="erase_password">Your TaraBasa password</label><input type="password" id="erase_password" name="password" data-af autocomplete="current-password" class="@if ($eraseFailed && $errors->erase->has('password')) error @endif" required></div>
          <label class="erase-check"><input type="checkbox" name="confirm" value="1" required><span>I understand this cannot be undone.</span></label>
          @if ($eraseFailed)
            @foreach (['password', 'confirm', 'account'] as $field)
              @if ($errors->erase->has($field))<span class="field-error">{{ $errors->erase->first($field) }}</span>@endif
            @endforeach
          @endif
        </form>
      </div>
      <footer class="win-foot">
        <button type="submit" form="erase-form" class="btn small danger-fill">Delete my account</button>
        <button type="button" class="btn ghost small" data-close>Cancel</button>
      </footer>
    </div>
  </dialog>

  <dialog id="pwdDlg" class="win" aria-label="Change password" @if ($pwErrors) data-autoopen @endif>
    <div class="win-in narrow">
      <header class="win-head">
        <div class="win-titles"><h2>Change password</h2><p class="win-meta">Enter your current password to set a new one.</p></div>
        <button type="button" class="x" data-close aria-label="Close">@include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])</button>
      </header>
      <div class="win-body">
        <form id="pwd-form" method="POST" action="{{ route('profile.password.update') }}" data-busy="Saving">
          @csrf
          @method('PUT')
          <input type="hidden" name="form" value="profile">
          <div class="field"><label for="current_password">Current password</label><input type="password" id="current_password" name="current_password" data-af placeholder="Your current password" class="@error('current_password') error @enderror" required>@error('current_password')<span class="field-error">{{ $message }}</span>@enderror</div>
          <div class="field"><label for="password">New password</label><input type="password" id="password" name="password" placeholder="At least 8 characters" class="@error('password') error @enderror" required>@error('password')<span class="field-error">{{ $message }}</span>@enderror</div>
          <div class="field"><label for="password_confirmation">Confirm new password</label><input type="password" id="password_confirmation" name="password_confirmation" placeholder="Type it again" required></div>
        </form>
      </div>
      <footer class="win-foot">
        <button type="submit" form="pwd-form" class="btn small">Change password</button>
        <button type="button" class="btn ghost small" data-close>Cancel</button>
      </footer>
    </div>
  </dialog>
@endpush
