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

    <div class="card">
      <p class="card-title">Password</p>
      <p class="card-sub">Change it any time. You will need your current password.</p>
      <button type="button" class="btn ghost" data-open="pwdDlg">@include('learner._badge-icon', ['icon' => 'key', 'class' => 'ico']) Change password</button>
    </div>
  </div>
</div>
@endsection

@push('dialogs')
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
