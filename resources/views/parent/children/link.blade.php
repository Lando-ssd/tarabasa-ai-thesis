<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Link Existing Child | TaraBasa AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
<noscript><link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"></noscript>
<style>
  :root{
    --sky-50:#eef6ff; --sky-100:#dcedff;
    --blue-500:#1c7ed6; --blue-600:#0f5fae; --blue-700:#0a3d73;
    --navy-900:#131f2b; --slate-600:#5b6b7a; --slate-400:#8a97a3;
    --clay-yellow:#ffcf6e; --owl-orange-600:#dd7014;
    --line:#e3ebf2; --surface:#ffffff; --bg-0:#f6faff;
    --danger:#d64545; --danger-bg:#fdecec;
    --success:#1f9e83; --success-bg:#e9f7f3;
    --shadow-card:0 30px 60px -30px rgba(15,60,110,0.28), 0 6px 16px -8px rgba(15,60,110,0.12);
  }
  *{box-sizing:border-box;} html,body{margin:0;padding:0;}
  body{
    min-height:100vh; font-family:'Inter',sans-serif; color:var(--navy-900);
    background: radial-gradient(1100px 620px at 88% -10%, var(--sky-100), transparent 60%),
                radial-gradient(900px 500px at -10% 108%, var(--sky-100), transparent 55%), var(--bg-0);
    background-attachment:fixed;
    display:flex; align-items:safe center; justify-content:center; padding:32px 20px;
  }
  .back-link{
    display:inline-flex; align-items:center; gap:6px; font-size:13.5px; font-weight:700; color:var(--slate-600);
    text-decoration:none; margin-bottom:16px; transition:color .15s ease;
  }
  .back-link:hover{ color:var(--blue-600); }
  .card-outer{ width:100%; max-width:440px; }
  .card{ background:var(--surface); border:1px solid var(--line); border-radius:24px; box-shadow:var(--shadow-card); padding:32px 30px; }
  .icon-badge{
    width:52px;height:52px;border-radius:16px; background:var(--success-bg); color:var(--success);
    display:flex;align-items:center;justify-content:center; margin-bottom:16px;
  }
  h1{ font-family:'Baloo 2',sans-serif; font-size:23px; font-weight:700; margin:0 0 8px; }
  .sub{ margin:0 0 22px; font-size:13.5px; color:var(--slate-600); font-weight:500; line-height:1.55; }

  .field{ display:flex; flex-direction:column; gap:6px; margin-bottom:16px; }
  .field label{ font-size:13px; font-weight:700; }
  .code-row{ display:flex; gap:10px; }
  input, select{
    flex:1; font:700 16px/1 'Baloo 2',sans-serif; letter-spacing:.03em; padding:13px 14px; border:1.5px solid var(--line);
    border-radius:12px; background:var(--bg-0); color:var(--navy-900); outline:none; text-transform:uppercase;
    transition:border-color .15s ease, box-shadow .15s ease, background .15s ease;
  }
  select{ font:600 14.5px/1 'Inter',sans-serif; text-transform:none; appearance:none;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none'%3E%3Cpath d='M6 9l6 6 6-6' stroke='%238a97a3' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-repeat:no-repeat; background-position:right 14px center; padding-right:38px;
  }
  input::placeholder{ color:var(--slate-400); font-weight:700; }
  input:focus, select:focus{ border-color:var(--blue-500); background:var(--surface); box-shadow:0 0 0 4px rgba(28,126,214,0.14); }
  input.error{ border-color:var(--danger); }
  .find-btn{
    flex-shrink:0; padding:0 20px; border:none; border-radius:12px; background:linear-gradient(155deg, var(--blue-500), var(--blue-700));
    color:#fff; font:700 14px/1 'Inter',sans-serif; cursor:pointer; transition:transform .15s ease;
  }
  .find-btn:hover{ transform:translateY(-1px); }

  .error-msg{ display:flex; align-items:center; gap:6px; font-size:12.5px; font-weight:600; color:var(--danger); margin-top:4px; }

  .found-card{
    display:flex; align-items:center; gap:14px; background:var(--sky-50); border:1px solid var(--line);
    border-radius:16px; padding:16px; margin-top:6px; margin-bottom:18px;
  }
  .found-avatar{
    width:48px;height:48px;border-radius:16px; font-size:24px; flex-shrink:0; overflow:hidden;
    background:linear-gradient(155deg, var(--clay-yellow), var(--owl-orange-600));
    display:flex;align-items:center;justify-content:center;
  }
  .avatar-photo-img{ width:100%; height:100%; object-fit:cover; display:block; }
  .found-info{ flex:1; min-width:0; }
  .found-info .fn{ font-weight:800; font-size:15px; }
  .found-info .fm{ font-size:12.5px; color:var(--slate-600); font-weight:600; }

  .confirm-note{
    display:flex; gap:10px; background:var(--success-bg); border:1px solid var(--line); border-radius:14px;
    padding:13px 14px; font-size:12.5px; color:var(--navy-900); font-weight:500; line-height:1.5; margin-bottom:20px;
  }

  .btn{
    width:100%; padding:14px; border:none; border-radius:12px; font:700 15px/1 'Inter',sans-serif; cursor:pointer;
    display:flex; align-items:safe center; justify-content:center; gap:8px; transition:transform .15s ease;
  }
  .btn-primary{ background:linear-gradient(155deg, var(--success), #157a67); color:#fff; box-shadow:0 12px 22px -10px rgba(31,158,131,0.45); }
  .btn-primary:hover{ transform:translateY(-1px); }
  a:focus-visible, button:focus-visible, input:focus-visible, select:focus-visible{ outline:2px solid var(--blue-500); outline-offset:2px; }
</style>
</head>
<body>
<div class="card-outer">
  <a href="{{ route('parent.children.index') }}" class="back-link">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    Back to My Children
  </a>

  <div class="card">
    <div class="icon-badge">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6" stroke="currentColor" stroke-width="1.8"/><path d="M17 8l3 3-3 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </div>
    <h1>Link Existing Child</h1>
    <p class="sub">To be added as a second guardian, type the child's learner code and the child's 4 digit PIN. The child's other guardian can give you both. Everyone already linked is told when someone new is added.</p>

    <form method="POST" action="{{ route('parent.children.link.submit') }}" id="linkForm" novalidate>
      @csrf
      <div class="field">
        <label for="learner_code">Learner code</label>
        <input type="text" name="learner_code" id="learner_code" placeholder="TB26-48293" maxlength="12" autocomplete="off" autocapitalize="characters" spellcheck="false" value="{{ old('learner_code') }}" class="@error('learner_code') error @enderror" required>
      </div>

      <div class="field">
        <label for="pin">Child's PIN</label>
        <input type="password" name="pin" id="pin" inputmode="numeric" maxlength="4" pattern="[0-9]{4}" placeholder="4 digits" autocomplete="off" style="letter-spacing:.3em" class="@error('pin') error @enderror" required>
        @error('pin') <span class="error-msg">{{ $message }}</span> @enderror
      </div>

      @error('learner_code')
        <span class="error-msg" style="margin:-6px 0 14px">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M12 8v5M12 16h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          {{ $message }}
        </span>
      @enderror

      <div class="field">
        <label for="relationship">Your relationship to the child</label>
        <select name="relationship" id="relationship">
          @foreach (['Mother', 'Father', 'Guardian'] as $rel)
            <option value="{{ $rel }}" @selected(old('relationship') === $rel)>{{ $rel }}</option>
          @endforeach
        </select>
        @error('relationship') <span class="error-msg">{{ $message }}</span> @enderror
      </div>

      <div class="confirm-note">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="flex-shrink:0; margin-top:1px;"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M12 8v5M12 16h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        <span>You will be able to see this child's progress and alerts and unlock activities for them. The child's own account does not change.</span>
      </div>

      <button type="submit" class="btn btn-primary" id="linkSubmitBtn">Link as guardian</button>
    </form>
  </div>
</div>
<script>
  document.getElementById('linkForm')?.addEventListener('submit', function () {
    const btn = document.getElementById('linkSubmitBtn');
    btn.disabled = true;
    btn.textContent = 'Linking...';
  });
</script>
</body>
</html>
