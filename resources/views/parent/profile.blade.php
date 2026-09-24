{{--
  My Profile (Parent): name and contact number, and a password change that needs the current
  password. Email is the login and is not editable here. On a phone, the log out button lives at
  the bottom of this page (the desktop menu bar has its own).
--}}
@extends('layouts.parent-shell')

@section('title', 'Profile | TaraBasa AI')

@section('content')
<div class="head-row">
  <div class="head-av">{{ mb_strtoupper(mb_substr($user->first_name, 0, 1)) }}{{ mb_strtoupper(mb_substr($user->last_name, 0, 1)) }}</div>
  <div>
    <h1 style="margin:0">My profile</h1>
    <div class="note">{{ $user->email }}</div>
  </div>
</div>

@if (session('status'))
  <div class="flash">{{ session('status') }}</div>
@endif

<div class="profile-grid">
  <div class="card">
    <p class="card-title">Personal information</p>
    <p class="card-sub">Your name and contact number. Your email is your login and can't be changed here.</p>

    @if ($errors->any() && $errors->hasAny(['first_name', 'last_name', 'middle_initial', 'contact_number']))
      <div class="form-error-banner">Please fix the highlighted fields below.</div>
    @endif

    <form method="POST" action="{{ route('profile.update') }}">
      @csrf
      @method('PUT')

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

  <div class="card">
    <p class="card-title">Change password</p>
    <p class="card-sub">Enter your current password to set a new one.</p>

    @if ($errors->any() && $errors->hasAny(['current_password', 'password']))
      <div class="form-error-banner">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('profile.password.update') }}">
      @csrf
      @method('PUT')

      <div class="field">
        <label for="current_password">Current password</label>
        <input type="password" name="current_password" id="current_password" placeholder="Your current password" class="@error('current_password') error @enderror" required>
        @error('current_password') <span class="field-error">{{ $message }}</span> @enderror
      </div>
      <div class="field">
        <label for="password">New password</label>
        <input type="password" name="password" id="password" placeholder="At least 8 characters" class="@error('password') error @enderror" required>
        @error('password') <span class="field-error">{{ $message }}</span> @enderror
      </div>
      <div class="field">
        <label for="password_confirmation">Confirm new password</label>
        <input type="password" name="password_confirmation" id="password_confirmation" placeholder="Type your new password again" required>
      </div>

      <button type="submit" class="btn">Change password</button>
    </form>
  </div>
</div>

<form method="POST" action="{{ route('logout') }}" class="phone-only" style="margin-top:22px">
  @csrf
  <button type="submit" class="btn ghost">@include('learner._badge-icon', ['icon' => 'sign-out', 'class' => 'ico']) Log out</button>
</form>
@endsection

@push('scripts')
<script>
  // A real saving state on each form, so a second tap cannot double submit.
  document.querySelectorAll('.profile-grid form').forEach(function (form) {
    form.addEventListener('submit', function () {
      var button = form.querySelector('button[type="submit"]');
      if (!button) { return; }
      button.disabled = true;
      button.textContent = 'Saving';
    });
  });
</script>
@endpush
