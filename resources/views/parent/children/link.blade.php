<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Link Existing Child | TaraBasa AI</title>
<link rel="stylesheet" href="{{ asset('css/fonts.css') }}?v={{ substr(md5_file(public_path('css/fonts.css')), 0, 10) }}">
<link rel="stylesheet" href="{{ asset('css/parent-focus.css') }}?v={{ substr(md5_file(public_path('css/parent-focus.css')), 0, 12) }}">
</head>
<body class="centered">
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

      <x-consent-check name="guardian_confirm">I am this child's parent or legal guardian, and I agree to the <a href="{{ route('consent') }}" target="_blank" rel="noopener">Parent and Guardian Consent</a> notice and the <a href="{{ route('privacy') }}" target="_blank" rel="noopener">Privacy Policy</a>.</x-consent-check>

      <button type="submit" class="btn btn-primary btn-block" id="linkSubmitBtn">Link as guardian</button>
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
