@extends('layouts.auth')
@section('title', 'Parent Registration — TaraBasa AI')
@section('content')

  <h1>Create your account</h1>
  <p class="sub">You'll add your child's profile after you sign in.</p>

  @if ($errors->any())
    <div class="form-error-banner">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" style="flex-shrink:0;margin-top:1px;">
        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/>
        <path d="M12 8v5M12 16h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
      <span>Please fix the highlighted fields below.</span>
    </div>
  @endif

  <form method="POST" action="{{ route('register.parent.submit') }}" id="regForm">
    @csrf

    <div class="field">
      <label for="full_name">Your full name</label>
      <input type="text" name="full_name" id="full_name" placeholder="e.g. Maria Cruz"
             value="{{ old('full_name') }}" class="@error('full_name') error @enderror" required>
      @error('full_name') <span class="field-error">{{ $message }}</span> @enderror
    </div>

    <div class="field">
      <label for="email">Email address</label>
      <input type="email" name="email" id="email" placeholder="you@email.com"
             value="{{ old('email') }}" class="@error('email') error @enderror" required>
      @error('email') <span class="field-error">{{ $message }}</span> @enderror
    </div>

    <div class="field">
      <label for="password">Password</label>
      <input type="password" name="password" id="password" placeholder="At least 8 characters"
             class="@error('password') error @enderror" required>
      @error('password') <span class="field-error">{{ $message }}</span> @enderror
    </div>

    <div class="field">
      <label for="password_confirmation">Confirm password</label>
      <input type="password" name="password_confirmation" id="password_confirmation"
             placeholder="Re-type your password" required>
    </div>

    <div class="verify-note">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M12 8v5M12 16h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      <span>We'll send a confirmation link to your email. You can start using your account right away — no need to wait for it.</span>
    </div>

    <button type="submit" class="submit-btn" id="submitBtn">Create account</button>
  </form>

  <p class="switch-note">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>

  <script>
    document.getElementById('regForm').addEventListener('submit', function () {
      const btn = document.getElementById('submitBtn');
      btn.disabled = true;
      btn.textContent = 'Creating your account…';
    });
  </script>
@endsection
