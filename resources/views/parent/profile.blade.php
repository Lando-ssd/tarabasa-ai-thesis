<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TaraBasa AI — My Profile</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --sky-50:#eef6ff; --sky-100:#dcedff;
    --navy-900:#131f2b; --slate-600:#5b6b7a; --slate-400:#8a97a3;
    --parent-teal:#1f9e83; --parent-teal-dark:#157a67;
    --line:#e3ebf2; --surface:#ffffff; --bg-0:#f6faff;
    --success:#1f9e83; --success-bg:#e9f7f3;
    --danger:#d64545; --danger-bg:#fdecec; --danger-border:#f3c9c9;
    --shadow-sm:0 1px 2px rgba(19,31,43,0.06);
  }
  *{box-sizing:border-box;} html,body{margin:0;padding:0;}
  body{
    min-height:100vh; font-family:'Inter',sans-serif; color:var(--navy-900);
    background: radial-gradient(1100px 500px at 90% -10%, var(--sky-100), transparent 55%), var(--bg-0);
    background-attachment:fixed;
  }
  .shell{ max-width:620px; margin:0 auto; padding:22px 24px 64px; }

  .topbar{ display:flex; align-items:center; justify-content:space-between; margin-bottom:8px; }
  .logo-lockup{ display:flex; align-items:center; gap:10px; }
  .logo-badge{ width:38px;height:38px;border-radius:11px; overflow:hidden; box-shadow:0 6px 14px -5px rgba(15,95,174,0.5); }
  .logo-badge img{ width:100%; height:100%; object-fit:cover; display:block; }
  .wordmark{ font-family:'Baloo 2',sans-serif; font-weight:700; font-size:18px; }
  .wordmark span{ color:var(--owl-orange-600, #dd7014); }
  .topbar-actions{ display:flex; align-items:center; gap:10px; }
  .avatar-chip{
    display:flex; align-items:center; gap:9px; background:var(--surface); border:1px solid var(--line);
    padding:5px 12px 5px 5px; border-radius:999px; box-shadow:var(--shadow-sm);
  }
  .avatar-chip .av{
    width:30px;height:30px;border-radius:50%; background:linear-gradient(155deg,var(--parent-teal),var(--parent-teal-dark));
    display:flex;align-items:center;justify-content:center; color:#fff; font-weight:700; font-size:13px;
  }
  .avatar-chip span.name{ font-size:13.5px; font-weight:700; }
  .logout-btn{
    background:var(--surface); border:1px solid var(--line); color:var(--slate-600); font:700 13px/1 'Inter',sans-serif;
    padding:10px 16px; border-radius:12px; cursor:pointer; box-shadow:var(--shadow-sm);
    transition:color .15s ease, border-color .15s ease;
  }
  .logout-btn:hover{ color:var(--danger); border-color:var(--danger); }

  .back-link{
    display:inline-flex; align-items:center; gap:6px; font-size:13.5px; font-weight:700; color:var(--slate-600);
    text-decoration:none; margin:18px 0 6px;
  }
  .back-link:hover{ color:var(--parent-teal); }

  .head-row{ display:flex; align-items:center; gap:14px; margin-bottom:22px; }
  .head-avatar{
    width:56px;height:56px;border-radius:16px; background:linear-gradient(155deg,var(--parent-teal),var(--parent-teal-dark));
    display:flex;align-items:center;justify-content:center; color:#fff; font-weight:700; font-size:21px; flex-shrink:0;
    box-shadow:0 10px 20px -8px rgba(31,158,131,0.5);
  }
  h1{ font-family:'Baloo 2',sans-serif; font-size:25px; font-weight:700; margin:0 0 3px; }
  .head-sub{ font-size:13.5px; color:var(--slate-600); font-weight:500; }

  .flash{ font-weight:700; font-size:13px; border-radius:14px; padding:12px 16px; margin-bottom:18px; background:var(--success-bg); border:1px solid var(--success); color:var(--success); }

  .card{ background:var(--surface); border:1px solid var(--line); border-radius:20px; padding:22px 24px; box-shadow:var(--shadow-sm); margin-bottom:18px; }
  .card-title{ font-family:'Baloo 2',sans-serif; font-size:16px; font-weight:700; margin:0 0 4px; }
  .card-sub{ font-size:12.5px; color:var(--slate-600); font-weight:500; margin:0 0 18px; line-height:1.5; }

  .field{ display:flex; flex-direction:column; gap:6px; margin-bottom:14px; }
  .field label{ font-size:12.5px; font-weight:700; }
  .field .opt{ font-size:11px; font-weight:600; color:var(--slate-400); margin-left:3px; }
  .name-row{ display:grid; grid-template-columns:1fr 74px 1fr; gap:10px; }
  input, input[readonly]{
    width:100%; font:500 14px/1 'Inter',sans-serif; padding:12px 13px; border:1.5px solid var(--line);
    border-radius:11px; background:var(--bg-0); color:var(--navy-900); outline:none;
    transition:border-color .15s ease, box-shadow .15s ease, background .15s ease;
  }
  input::placeholder{ color:var(--slate-400); font-weight:500; }
  input:focus{ border-color:var(--parent-teal); background:var(--surface); box-shadow:0 0 0 4px rgba(31,158,131,0.14); }
  input.error{ border-color:var(--danger); }
  input[readonly]{ color:var(--slate-600); cursor:not-allowed; }
  .field-error{ font-size:12px; color:var(--danger); font-weight:600; margin-top:2px; }

  .submit-btn{
    padding:12px 22px; border:none; border-radius:12px; font:700 14px/1 'Inter',sans-serif;
    cursor:pointer; background:linear-gradient(155deg,#28b895,var(--parent-teal)); color:#fff;
    box-shadow:0 12px 22px -10px rgba(31,158,131,0.5); transition:transform .15s ease, opacity .15s ease;
  }
  .submit-btn:hover{ transform:translateY(-1px); }
  .submit-btn:disabled{ opacity:.65; cursor:not-allowed; transform:none; }

  .form-error-banner{
    display:flex; gap:8px; align-items:flex-start; background:var(--danger-bg); border:1px solid var(--danger-border);
    color:var(--danger); border-radius:11px; padding:11px 13px; font-size:12.5px; font-weight:600; margin-bottom:16px;
  }

  a:focus-visible, button:focus-visible, input:focus-visible{ outline:2px solid var(--parent-teal); outline-offset:2px; }
</style>
</head>
<body>
<div class="shell">

  <div class="topbar">
    <div class="logo-lockup">
      <div class="logo-badge"><img src="{{ asset('images/logo.png') }}" alt="TaraBasa AI logo"></div>
      <span class="wordmark">TaraBasa<span>AI</span></span>
    </div>
    <div class="topbar-actions">
      <div class="avatar-chip">
        <span class="av">{{ strtoupper(substr($user->first_name, 0, 1)) }}</span>
        <span class="name">{{ $user->first_name }}</span>
      </div>
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="logout-btn">Log out</button>
      </form>
    </div>
  </div>

  <a href="{{ route('parent.dashboard') }}" class="back-link">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    Dashboard
  </a>

  <div class="head-row">
    <div class="head-avatar">{{ strtoupper(substr($user->first_name, 0, 1)) }}{{ strtoupper(substr($user->last_name, 0, 1)) }}</div>
    <div>
      <h1>My Profile</h1>
      <div class="head-sub">{{ $user->email }}</div>
    </div>
  </div>

  @if (session('status'))
    <div class="flash">{{ session('status') }}</div>
  @endif

  <div class="card">
    <p class="card-title">Personal Information</p>
    <p class="card-sub">Your name and contact number. Your email is your login and can't be changed here.</p>

    @if ($errors->any() && $errors->hasAny(['first_name', 'last_name', 'middle_initial', 'contact_number']))
      <div class="form-error-banner">Please fix the highlighted fields below.</div>
    @endif

    <form method="POST" action="{{ route('profile.update') }}">
      @csrf
      @method('PUT')

      <div class="name-row" style="margin-bottom:14px;">
        <div class="field" style="margin-bottom:0;">
          <label for="first_name">First Name</label>
          <input type="text" name="first_name" id="first_name" value="{{ old('first_name', $user->first_name) }}" class="@error('first_name') error @enderror" required>
          @error('first_name') <span class="field-error">{{ $message }}</span> @enderror
        </div>
        <div class="field" style="margin-bottom:0;">
          <label for="middle_initial">M.I. <span class="opt">optional</span></label>
          <input type="text" name="middle_initial" id="middle_initial" maxlength="5" value="{{ old('middle_initial', $user->middle_initial) }}" class="@error('middle_initial') error @enderror">
          @error('middle_initial') <span class="field-error">{{ $message }}</span> @enderror
        </div>
        <div class="field" style="margin-bottom:0;">
          <label for="last_name">Last Name</label>
          <input type="text" name="last_name" id="last_name" value="{{ old('last_name', $user->last_name) }}" class="@error('last_name') error @enderror" required>
          @error('last_name') <span class="field-error">{{ $message }}</span> @enderror
        </div>
      </div>

      <div class="field">
        <label for="contact_number">Contact Number <span class="opt">optional</span></label>
        <input type="text" name="contact_number" id="contact_number" placeholder="e.g. 0917 123 4567" value="{{ old('contact_number', $user->contact_number) }}" class="@error('contact_number') error @enderror">
        @error('contact_number') <span class="field-error">{{ $message }}</span> @enderror
      </div>

      <div class="field">
        <label>Email</label>
        <input type="email" value="{{ $user->email }}" readonly>
      </div>

      <button type="submit" class="submit-btn">Save Changes</button>
    </form>
  </div>

  <div class="card">
    <p class="card-title">Change Password</p>
    <p class="card-sub">Enter your current password to set a new one.</p>

    @if ($errors->any() && $errors->hasAny(['current_password', 'password']))
      <div class="form-error-banner">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('profile.password.update') }}">
      @csrf
      @method('PUT')

      <div class="field">
        <label for="current_password">Current Password</label>
        <input type="password" name="current_password" id="current_password" placeholder="Your current password" class="@error('current_password') error @enderror" required>
        @error('current_password') <span class="field-error">{{ $message }}</span> @enderror
      </div>
      <div class="field">
        <label for="password">New Password</label>
        <input type="password" name="password" id="password" placeholder="At least 8 characters" class="@error('password') error @enderror" required>
        @error('password') <span class="field-error">{{ $message }}</span> @enderror
      </div>
      <div class="field">
        <label for="password_confirmation">Confirm New Password</label>
        <input type="password" name="password_confirmation" id="password_confirmation" placeholder="Re-type your new password" required>
      </div>

      <button type="submit" class="submit-btn">Change Password</button>
    </form>
  </div>

</div>
<script>
  document.querySelectorAll('form').forEach(function (form) {
    form.addEventListener('submit', function () {
      const btn = form.querySelector('button[type="submit"]');
      if (!btn) return;
      btn.disabled = true;
      btn.dataset.originalText = btn.textContent;
      btn.textContent = 'Saving…';
    });
  });
</script>
</body>
</html>
