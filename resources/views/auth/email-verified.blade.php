@extends('layouts.auth')
@section('title', 'Email confirmed | TaraBasa AI')
@section('content')

  <style>
    .logo-badge{ display:none; }
    .done-stage{ display:flex; flex-direction:column; align-items:center; text-align:center; margin-bottom:6px; }
    .done-mark{ width:64px; height:64px; border-radius:50%; background:var(--success-bg); color:var(--success); display:flex; align-items:center; justify-content:center; margin:2px 0 14px; }
    .done-stage h1{ font-size:27px; margin-bottom:8px; }
    .done-stage .sub{ margin-bottom:22px; }
  </style>

  <div class="done-stage">
    <div class="done-mark" aria-hidden="true">
      <svg width="34" height="34" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </div>
    <h1>Email confirmed</h1>
    <p class="sub">Thank you. <strong>{{ $email }}</strong> is now confirmed. You can log in.</p>
  </div>

  @if ($isTeacher)
    <div class="verify-note amber">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M12 8v5M12 16h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      <span>An Admin still reviews your school details. Until then you can try activity generation with 2 free credits. Real class rosters unlock once you are approved.</span>
    </div>
  @endif

  <a href="{{ route('login', ['role' => $role]) }}" class="submit-btn" style="display:block;text-align:center;text-decoration:none;box-sizing:border-box;">
    Continue to log in
  </a>
@endsection
