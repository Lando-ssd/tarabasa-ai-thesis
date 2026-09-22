@extends('layouts.auth-login')
@section('title', 'Forgot password — TaraBasa AI')
@section('content')

  <h1>Forgot password?</h1>

  @if ($errors->any())
    <div class="form-error-banner">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" style="flex-shrink:0;margin-top:1px;">
        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/>
        <path d="M12 8v5M12 16h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
      <span>{{ $errors->first() }}</span>
    </div>
  @endif

  @if (session('status'))
    <div class="verify-note success">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      <span>{{ session('status') }}</span>
    </div>
  @else
    <div class="verify-note">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6"/><path d="M12 8v5M12 16h.01" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
      <span>Enter the email on your account and we'll send you a link to reset your password.</span>
    </div>
  @endif

  <form method="POST" action="{{ route('password.email') }}" id="forgotForm">
    @csrf
    <div class="field">
      <label for="email">Email</label>
      <input type="email" name="email" id="email" placeholder="you@email.com" value="{{ old('email') }}" required autofocus>
    </div>

    <button type="submit" class="submit-btn" id="submitBtn">Send reset link</button>
  </form>

  <p class="switch-note">Remembered it after all?<a href="{{ route('login') }}">Back to log in</a></p>

  <script>
    document.getElementById('forgotForm').addEventListener('submit', function () {
      const btn = document.getElementById('submitBtn');
      btn.disabled = true;
      btn.textContent = 'Sending…';
    });
  </script>
@endsection
