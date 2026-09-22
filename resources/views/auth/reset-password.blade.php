@extends('layouts.auth-login')
@section('title', 'Reset password — TaraBasa AI')
@section('content')

  <h1>Set a new password</h1>

  @if ($errors->any())
    <div class="form-error-banner">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" style="flex-shrink:0;margin-top:1px;">
        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/>
        <path d="M12 8v5M12 16h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
      <span>{{ $errors->first() }}</span>
    </div>
  @endif

  <form method="POST" action="{{ route('password.update') }}" id="resetForm">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">

    <div class="field">
      <label for="email">Email</label>
      <input type="email" name="email" id="email" placeholder="you@email.com" value="{{ old('email', $email) }}" required autofocus>
    </div>

    <div class="field">
      <label for="password">New password</label>
      <div class="pw-wrap">
        <input type="password" name="password" id="password" placeholder="At least 8 characters" style="padding-right:44px;" required>
        <button type="button" id="togglePw" class="field-icon-btn" aria-label="Show password">
          <svg id="eyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z" stroke="currentColor" stroke-width="1.8"/>
            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/>
          </svg>
        </button>
      </div>
    </div>

    <div class="field">
      <label for="password_confirmation">Confirm new password</label>
      <input type="password" name="password_confirmation" id="password_confirmation" placeholder="Type it again" required>
    </div>

    <button type="submit" class="submit-btn" id="submitBtn">Reset password</button>
  </form>

  <script>
    document.getElementById('resetForm').addEventListener('submit', function () {
      const btn = document.getElementById('submitBtn');
      btn.disabled = true;
      btn.textContent = 'Resetting…';
    });

    const pwInput = document.getElementById('password');
    const togglePw = document.getElementById('togglePw');
    const eyeIcon = document.getElementById('eyeIcon');
    togglePw.addEventListener('click', function () {
      const show = pwInput.type === 'password';
      pwInput.type = show ? 'text' : 'password';
      togglePw.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      eyeIcon.innerHTML = show
        ? '<path d="M3 3l18 18M10.6 10.6a3 3 0 0 0 4.2 4.2M9.9 5.1A10.4 10.4 0 0 1 12 5c6.5 0 10 7 10 7a15.6 15.6 0 0 1-3.2 4.1M6.6 6.6A15.7 15.7 0 0 0 2 12s3.5 7 10 7c1.3 0 2.5-.2 3.6-.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>'
        : '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/>';
    });
  </script>
@endsection
