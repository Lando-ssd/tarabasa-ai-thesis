@extends('layouts.auth')
@section('title', "You're registered — TaraBasa AI")
@section('content')

  <div style="width:52px;height:52px;border-radius:50%;background:var(--success);display:flex;align-items:center;justify-content:center;margin-bottom:16px;">
    <svg width="26" height="26" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
  </div>

  <h1>You're registered, {{ $firstName }}!</h1>
  <p class="sub">Your account is ready to use.</p>

  <div class="verify-note">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M3 6l9 6 9-6M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <span>We've sent a confirmation link to <strong>{{ $email }}</strong>. Click it whenever you get a chance — you don't have to wait for it to start.</span>
  </div>

  @if ($role === 'teacher')
    <div class="verify-note" style="background:#fff8ec;">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M12 8v5M12 16h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      <span>Your school details are being reviewed by an Admin. Until then, you can try AI activity generation with 2 free credits — real class rosters unlock once you're verified.</span>
    </div>
  @endif

  <a href="{{ route('login') }}" class="submit-btn" style="display:block;text-align:center;text-decoration:none;box-sizing:border-box;">
    Continue to sign in
  </a>
@endsection
