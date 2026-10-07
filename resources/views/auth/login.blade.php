@extends('layouts.auth-login')
@section('title', 'Log in | TaraBasa AI')
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

  <h1>Log in</h1>

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

  {{--
    One log in for everyone. Teachers, parents and the admin type an email and a password. A child
    types the learner code (like TB26-48293) in the same first box and the 4 digit PIN in the second
    box, and goes straight to their own dashboard: nothing is typed twice. The server decides what
    the first box holds (see AuthController::login); this script only changes the second box's
    label and keyboard while a learner code is being typed. When a learner logs out they land on the
    child's own log in page (the children holding letters), not here.
  --}}
  <form method="POST" action="{{ route('login.submit') }}" id="loginForm">
    @csrf
    <input type="hidden" name="role" value="{{ $role }}">

    <div class="field">
      <label for="email">Email or learner code</label>
      <input type="text" name="email" id="email" placeholder="you@email.com  or  TB26-48293"
             value="{{ old('email') }}" autocomplete="username" autocapitalize="off" spellcheck="false" required autofocus>
      <p class="id-hint" id="idHint">Teachers and parents: your email. Children: the learner code from your parent.</p>
      <p class="id-hint ok" id="idOk" hidden>Looks like a learner code. Type your PIN below.</p>
      <p class="id-hint warn" id="idBad" hidden>That code does not look right. Check it and try again.</p>
      {{-- A child can scan the QR code on their card instead of typing the code (see partials/card-scan). --}}
      <button type="button" class="scan-link" data-card-scan-open>
        <svg viewBox="0 0 256 256" width="18" height="18" aria-hidden="true" focusable="false"><use href="{{ asset('icons/badges.svg') }}#ph-qr-code"></use></svg>Scan my learner card
      </button>
    </div>

    <div class="field">
      <label for="password" id="pwLabel">Password</label>
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

    <a href="{{ route('password.request') }}" class="forgot-link" id="forgotLink">Forgot password?</a>

    <button type="submit" class="submit-btn" id="submitBtn">Log in</button>
  </form>

  <div id="adultAlt">
    <div class="divider-label">or</div>

    <a href="{{ route('auth.google.redirect', ['role' => $role]) }}" class="google-btn">
      <svg width="18" height="18" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.6l6.8-6.8C35.6 2.4 30.1 0 24 0 14.6 0 6.5 5.4 2.5 13.2l7.9 6.1C12.3 13 17.6 9.5 24 9.5z"/><path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.6c-.5 3-2.2 5.5-4.7 7.2l7.3 5.7c4.3-4 6.8-9.8 6.8-17.4z"/><path fill="#FBBC05" d="M10.4 19.3c-.5 1.5-.8 3.1-.8 4.7s.3 3.2.8 4.7l-7.9 6.1C.9 31.5 0 27.9 0 24s.9-7.5 2.5-10.8l7.9 6.1z"/><path fill="#34A853" d="M24 48c6.1 0 11.2-2 15-5.5l-7.3-5.7c-2 1.4-4.6 2.2-7.7 2.2-6.4 0-11.7-3.5-13.6-9.3l-7.9 6.1C6.5 42.6 14.6 48 24 48z"/></svg>
      Continue with Google
    </a>

    @if ($role === 'teacher')
      <p class="switch-note">New here? <a href="{{ route('register.teacher') }}">Sign up as a Teacher</a></p>
    @elseif ($role === 'parent')
      <p class="switch-note">New here? <a href="{{ route('register.parent') }}">Sign up as a Parent</a></p>
    @elseif (! $role)
      {{-- The landing page's header has the Sign up menu (Teacher or Parent). --}}
      <p class="switch-note">New here? <a href="{{ route('landing') }}">Sign up</a></p>
    @endif
    {{-- Admin: no registration link at all, by design. --}}
  </div>

  <style>
    .scan-link{ margin-top:10px; display:inline-flex; align-items:center; gap:8px; padding:9px 14px; border-radius:12px; border:1.5px solid var(--line); background:#fff; color:var(--blue-600); font:700 13.5px/1 'Inter',sans-serif; cursor:pointer; }
    .scan-link svg{ fill:currentColor; flex:none; }
    .scan-link:hover{ border-color:var(--blue-500); }
    .scan-link:focus-visible{ outline:2px solid var(--blue-500); outline-offset:2px; }
    #email.cs-scanned{ border-color:var(--success); background:#e9f7f3; }
    .id-hint{ margin:4px 0 0; font-size:12px; color:var(--slate-600); font-weight:500; line-height:1.4; }
    .id-hint.ok{ color:var(--success); font-weight:700; }
    .id-hint.warn{ color:var(--amber); font-weight:700; }
    [hidden]{ display:none !important; }
  </style>

  <script>
    (function () {
      var form = document.getElementById('loginForm');
      var idInput = document.getElementById('email');
      var pw = document.getElementById('password');
      var pwLabel = document.getElementById('pwLabel');
      var forgot = document.getElementById('forgotLink');
      var alt = document.getElementById('adultAlt');
      var hint = document.getElementById('idHint');
      var ok = document.getElementById('idOk');
      var bad = document.getElementById('idBad');
      var toggle = document.getElementById('togglePw');
      var submitBtn = document.getElementById('submitBtn');
      var learner = false;

      // Only the SHAPE of a learner code is checked here, on the device (TB, two digits, then five,
      // or the older TB and five). Nothing is sent to the server until the form is submitted, so
      // this page cannot be used to test which codes exist. A new code also carries a check digit
      // (Luhn) that catches a mistyped digit before anything is sent.
      function shape(v) {
        var c = v.replace(/\s+/g, '').toUpperCase();
        var m = c.match(/^TB(\d{2})-?(\d{5})$/);
        if (m) {
          var d = m[1] + m[2].slice(0, 4), sum = 0, dbl = true;
          for (var i = d.length - 1; i >= 0; i--) { var n = +d[i]; if (dbl) { n *= 2; if (n > 9) n -= 9; } sum += n; dbl = !dbl; }
          return ((10 - sum % 10) % 10) === +m[2][4] ? 'ok' : 'bad';
        }
        return /^TB-?[0-9A-Z]{5}$/.test(c) ? 'ok' : 'no';
      }

      function setLearner(on) {
        if (on === learner) { return; }
        learner = on;
        pwLabel.textContent = on ? 'PIN' : 'Password';
        pw.placeholder = on ? '4 digits' : 'Your password';
        pw.setAttribute('inputmode', on ? 'numeric' : 'text');
        pw.setAttribute('maxlength', on ? '4' : '255');
        pw.setAttribute('autocomplete', on ? 'one-time-code' : 'current-password');
        pw.value = '';
        forgot.hidden = on;
        alt.hidden = on;
        hint.hidden = on;
        ok.hidden = !on;
        toggle.hidden = on;
        submitBtn.textContent = on ? "Let's go" : 'Log in';
      }

      function check() {
        var s = shape(idInput.value);
        bad.hidden = s !== 'bad';
        setLearner(s === 'ok');
      }
      idInput.addEventListener('input', check);
      check();

      pw.addEventListener('input', function () { if (learner) { pw.value = pw.value.replace(/\D/g, '').slice(0, 4); } });

      form.addEventListener('submit', function () {
        submitBtn.disabled = true;
        submitBtn.textContent = learner ? 'Checking...' : 'Signing in...';
      });

      var eyeIcon = document.getElementById('eyeIcon');
      toggle.addEventListener('click', function () {
        var show = pw.type === 'password';
        pw.type = show ? 'text' : 'password';
        toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        eyeIcon.innerHTML = show
          ? '<path d="M3 3l18 18M10.6 10.6a3 3 0 0 0 4.2 4.2M9.9 5.1A10.4 10.4 0 0 1 12 5c6.5 0 10 7 10 7a15.6 15.6 0 0 1-3.2 4.1M6.6 6.6A15.7 15.7 0 0 0 2 12s3.5 7 10 7c1.3 0 2.5-.2 3.6-.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>'
          : '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/>';
      });
    })();
  </script>
  @include('partials.card-scan', ['target' => '#email', 'focus' => '#password', 'speak' => false, 'owl' => false])
@endsection
