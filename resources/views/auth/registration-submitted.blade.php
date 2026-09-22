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

    <h1>You're registered, {{ $firstName }}!</h1>
    <p class="sub">Your account is ready to use.</p>
  </div>

  <div class="verify-note">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M3 6l9 6 9-6M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <span>We've sent a confirmation link to <strong>{{ $email }}</strong>. Click it whenever you get a chance — you don't have to wait for it to start.</span>
  </div>

  @if ($role === 'teacher')
    <div class="verify-note amber">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M12 8v5M12 16h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      <span>Your school details are being reviewed by an Admin. Until then, you can try AI activity generation with 2 free credits — real class rosters unlock once you're verified.</span>
    </div>
  @endif

  <a href="{{ route('login') }}" class="submit-btn" style="display:block;text-align:center;text-decoration:none;box-sizing:border-box;">
    Continue to sign in
  </a>

  <script src="https://cdn.jsdelivr.net/npm/lottie-web@5.12.2/build/player/lottie.min.js"></script>
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
