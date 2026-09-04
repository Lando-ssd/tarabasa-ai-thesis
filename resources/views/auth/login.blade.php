@extends('layouts.auth')
@section('title', 'Sign in — TaraBasa AI')
@section('content')

  @php
    // No default here on purpose: the nav bar's "Log in" link is a general
    // shortcut with no role param (Teacher/Parent/Admin all share this exact
    // form), so a missing role means "general", not "assume Teacher".
    $role = request('role');
    $roleLabels = ['teacher' => 'Teacher', 'parent' => 'Parent', 'admin' => 'Admin'];
    $roleName = $roleLabels[$role] ?? null;
  @endphp

  @if ($roleName)
    <div class="role-badge">
      <span class="icon {{ $role }}">
        @if($role === 'parent')
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none"><circle cx="9" cy="8" r="3" fill="currentColor"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6" fill="currentColor"/></svg>
        @elseif($role === 'admin')
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="M12 2l7 3v6c0 5-3 8.5-7 11-4-2.5-7-6-7-11V5l7-3z" fill="currentColor"/></svg>
        @else
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="M12 3L2 8l10 5 8-4.2V15h1V8L12 3z" fill="currentColor"/></svg>
        @endif
      </span>
      <span>Signing in as <b>{{ $roleName }}</b></span>
    </div>
  @endif

  <h1>Welcome back</h1>
  <p class="sub">Enter your details to access your dashboard.</p>

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
  @endif

  @if ($role === 'admin')
    <div class="verify-note">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M12 2l7 3v6c0 5-3 8.5-7 11-4-2.5-7-6-7-11V5l7-3z" stroke="currentColor" stroke-width="1.6"/></svg>
      <span>Only authorized administrators can access this portal. There's no self-registration for Admin accounts.</span>
    </div>
  @endif

  <form method="POST" action="{{ route('login.submit') }}" id="loginForm">
    @csrf
    <input type="hidden" name="role" value="{{ $role }}">

    <div class="field">
      <label for="email">Email</label>
      <input type="email" name="email" id="email" placeholder="you@email.com"
             value="{{ old('email') }}" required autofocus>
    </div>

    <div class="field">
      <label for="password">Password</label>
      <div style="position:relative;">
        <input type="password" name="password" id="password" placeholder="Your password"
               style="padding-right:44px;" required>
        <button type="button" id="togglePw" class="field-icon-btn" aria-label="Show password">
          <svg id="eyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z" stroke="currentColor" stroke-width="1.8"/>
            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/>
          </svg>
        </button>
      </div>
    </div>

    <button type="submit" class="submit-btn" id="submitBtn">Sign in</button>
  </form>

  @if ($role === 'teacher')
    <p class="switch-note">New here? <a href="{{ route('register.teacher') }}">Register as Teacher</a></p>
  @elseif ($role === 'parent')
    <p class="switch-note">New here? <a href="{{ route('register.parent') }}">Register as Parent</a></p>
  @elseif (! $role)
    <p class="switch-note">New here? <a href="{{ route('landing') }}#signin">Choose your role to get started</a></p>
  @endif
  {{-- Admin: no registration link at all, by design. --}}

  <script>
    document.getElementById('loginForm').addEventListener('submit', function () {
      const btn = document.getElementById('submitBtn');
      btn.disabled = true;
      btn.textContent = 'Signing in…';
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