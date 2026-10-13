@extends('layouts.auth')
@section('title', 'This link did not work | TaraBasa AI')
@section('content')

  <style>
    .logo-badge{ display:none; }
    .problem-stage{ display:flex; flex-direction:column; align-items:center; text-align:center; margin-bottom:6px; }
    .problem-mark{ width:64px; height:64px; border-radius:50%; background:var(--amber-bg); color:var(--amber); display:flex; align-items:center; justify-content:center; margin:2px 0 14px; }
    .problem-stage h1{ font-size:27px; margin-bottom:8px; }
    .problem-stage .sub{ margin-bottom:20px; }
    .again-form .field{ margin-bottom:12px; }
  </style>

  <div class="problem-stage">
    <div class="problem-mark" aria-hidden="true">
      <svg width="32" height="32" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M12 7.5v5.5M12 16.5h.01" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
    </div>
    <h1>This link did not work</h1>
    <p class="sub">A confirmation link lasts 60 minutes and it must be opened exactly as it was sent. Type your email and we will send you a new one.</p>
  </div>

  <form method="POST" action="{{ route('verification.resend') }}" class="again-form">
    @csrf
    <div class="field">
      <label for="email">Your email</label>
      <input type="email" name="email" id="email" placeholder="you@email.com" autocomplete="email" autocapitalize="off" spellcheck="false" required autofocus>
    </div>
    <button type="submit" class="submit-btn">Send me a new link</button>
  </form>

  <p style="text-align:center;margin:16px 0 0;font-size:14px;"><a href="{{ route('login') }}">Back to log in</a></p>
@endsection
