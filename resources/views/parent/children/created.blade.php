<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $learner->first_name }} is all set | TaraBasa AI</title>
<link rel="stylesheet" href="{{ asset('css/fonts.css') }}?v={{ substr(md5_file(public_path('css/fonts.css')), 0, 10) }}">
<link rel="stylesheet" href="{{ asset('css/parent-focus.css') }}?v={{ substr(md5_file(public_path('css/parent-focus.css')), 0, 12) }}">
</head>
<body class="centered">
<div class="card-outer">
  <div class="card center">
    <div class="big-avatar">
      @if ($learner->avatar_photo_path)
        <img src="{{ Storage::url($learner->avatar_photo_path) }}" alt="{{ $learner->first_name }}" class="avatar-photo-img">
      @else
        {{ $learner->avatar_id }}
      @endif
    </div>
    <h1>{{ $learner->first_name }} is all set!</h1>
    <p class="next-note">{{ $learner->first_name }} will take a short first reading check the first time they log in. It finds where to start.</p>

    <div class="code-box">
      <div class="label">Learner Code</div>
      <div class="code" id="learnerCode">{{ $learner->learner_code }}</div>
      @php
        // TB26-48293: TB, the school year, four random digits and a check digit. No personal information.
        $codeParts = preg_match('/^TB(\d{2})-(\d{4})(\d)$/', $learner->learner_code, $cm) ? $cm : null;
      @endphp
      @if ($codeParts)
        <div class="parts">
          <div><b>TB</b>TaraBasa</div>
          <div><b>{{ $cm[1] }}</b>School year 20{{ $cm[1] }}</div>
          <div><b>{{ $cm[2] }}</b>Random</div>
          <div><b>{{ $cm[3] }}</b>Check digit</div>
        </div>
      @endif
      <div class="hint">{{ $learner->first_name }} types this code and the PIN to log in. A teacher adds {{ $learner->first_name }} to a class with the last five characters.</div>
      <button type="button" class="copy-btn" id="copyBtn">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><rect x="9" y="9" width="12" height="12" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M5 15V5a2 2 0 0 1 2-2h10" stroke="currentColor" stroke-width="1.8"/></svg>
        <span id="copyBtnLabel">Copy code</span>
      </button>
    </div>

    <div class="code-box qr-box">
      <div class="label">QR code</div>
      <div class="qr" aria-hidden="false">{!! \App\Support\LearnerQr::svg($learner->learner_code, 200) !!}</div>
      <div class="hint">{{ $learner->first_name }}'s teacher can scan this to add {{ $learner->first_name }} to the class, instead of typing the code. It holds the code only, never the PIN.</div>
      <a class="copy-btn" href="{{ route('parent.children.card', $learner) }}" target="_blank" rel="noopener" style="text-decoration:none">Print {{ $learner->first_name }}'s card</a>
    </div>

    <div class="nav-row">
      <a href="{{ route('parent.children.create') }}" class="btn btn-ghost">Add Another Child</a>
      <a href="{{ route('parent.children.index') }}" class="btn btn-primary">My Children</a>
    </div>
  </div>
</div>
<script>
  document.getElementById('copyBtn').addEventListener('click', function () {
    const code = document.getElementById('learnerCode').textContent.trim();
    navigator.clipboard.writeText(code).then(() => {
      const btn = document.getElementById('copyBtn');
      const label = document.getElementById('copyBtnLabel');
      btn.classList.add('copied');
      label.textContent = 'Copied!';
      setTimeout(() => { btn.classList.remove('copied'); label.textContent = 'Copy code'; }, 1600);
    });
  });
</script>
</body>
</html>
