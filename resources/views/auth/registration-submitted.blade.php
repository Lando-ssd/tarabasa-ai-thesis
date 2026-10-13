@extends('layouts.auth')
@section('title', "You're registered — TaraBasa AI")
@section('content')

  <style>
    /* Scoped to this page only — the shared logo-badge icon (layouts/auth.blade.php)
       is replaced here by the real Lottie celebration below, centered instead of
       left-aligned to read as a genuine "thank you" moment, not a continuation of
       the registration form. */
    .logo-badge{ display:none; }
    .success-stage{ display:flex; flex-direction:column; align-items:center; text-align:center; }
    .success-lottie{ width:148px; height:96px; margin:-6px 0 6px; }
    .success-stage h1{ font-size:27px; margin-bottom:8px; }
    .success-stage .sub{ margin-bottom:24px; }
    .success-stage .verify-note,
    .success-stage .submit-btn{ text-align:left; }
  </style>

  <div class="success-stage">
    <div id="registrationSuccessLottie" class="success-lottie" role="img" aria-label="A celebratory thank-you animation"></div>

    @if ($mustConfirm ?? false)
      <h1>Almost there, {{ $firstName }}!</h1>
      <p class="sub">Confirm your email to finish creating your account.</p>
    @else
      <h1>You're registered, {{ $firstName }}!</h1>
      <p class="sub">Your account is ready to use.</p>
    @endif
  </div>

  <div class="verify-note">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M3 6l9 6 9-6M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
    @if ($mustConfirm ?? false)
      <span>We sent a confirmation link to <strong>{{ $email }}</strong>. Open it to turn on your account, then log in. Look in your spam folder too. Wrong address? Register again with the right one.</span>
    @else
      <span>We've sent a confirmation link to <strong>{{ $email }}</strong>. Click it whenever you get a chance — you don't have to wait for it to start.</span>
    @endif
  </div>

  @if ($role === 'teacher')
    <div class="verify-note amber">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M12 8v5M12 16h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      @if ($mustConfirm ?? false)
        <span>After you confirm your email, an Admin reviews your school details. Until then you can try activity generation with 2 free credits. Real class rosters unlock once you are approved.</span>
      @else
        <span>Your school details are being reviewed by an Admin. Until then, you can try AI activity generation with 2 free credits — real class rosters unlock once you're verified.</span>
      @endif
    </div>
  @endif

  @if ($mustConfirm ?? false)
    <form method="POST" action="{{ route('verification.resend') }}" style="margin:0 0 12px;text-align:center;">
      @csrf
      <input type="hidden" name="email" value="{{ $email }}">
      <button type="submit" style="background:none;border:0;color:var(--blue-600);font:inherit;font-weight:700;font-size:14px;cursor:pointer;text-decoration:underline;">Did not get it? Send it again</button>
    </form>
  @endif

  <a href="{{ route('login') }}" class="submit-btn" style="display:block;text-align:center;text-decoration:none;box-sizing:border-box;">
    Continue to sign in
  </a>

  <script src="{{ asset('vendor/lottie-web-5.12.2.min.js') }}"></script>
  <script>
    (function () {
      var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      var anim = lottie.loadAnimation({
        container: document.getElementById('registrationSuccessLottie'),
        renderer: 'svg',
        loop: false,
        autoplay: !prefersReducedMotion,
        path: '{{ asset("animations/tarabasa-registration-success.json") }}'
      });
      if (prefersReducedMotion) {
        anim.goToAndStop(anim.totalFrames - 1, true);
      }
    })();
  </script>
@endsection
