<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Generate Activity — TaraBasa AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --sky-50:#eef6ff; --sky-100:#dcedff;
    --blue-500:#1c7ed6; --blue-600:#0f5fae; --blue-700:#0a3d73;
    --navy-900:#131f2b; --slate-600:#5b6b7a; --slate-400:#8a97a3;
    --line:#e3ebf2; --surface:#ffffff; --bg-0:#f6faff;
    --success:#1f9e83; --success-bg:#e9f7f3;
    --amber:#c9820b; --amber-bg:#fef6e6;
    --danger:#d64545; --danger-bg:#fdecec;
    --teal:#1f9e83;
    --shadow-sm:0 1px 2px rgba(19,31,43,0.06);
  }
  *{box-sizing:border-box;} html,body{margin:0;padding:0;}
  body{
    min-height:100vh; font-family:'Inter',sans-serif; color:var(--navy-900);
    background: radial-gradient(1100px 500px at 90% -10%, var(--sky-100), transparent 55%), var(--bg-0);
    background-attachment:fixed;
  }
  .shell{ max-width:680px; margin:0 auto; padding:22px 24px 64px; }

  .topbar{ display:flex; align-items:center; justify-content:space-between; margin-bottom:8px; }
  .logo-lockup{ display:flex; align-items:center; gap:10px; }
  .logo-badge{ width:38px;height:38px;border-radius:11px; overflow:hidden; box-shadow:0 6px 14px -5px rgba(15,95,174,0.5); }
  .logo-badge img{ width:100%; height:100%; object-fit:cover; display:block; }
  .wordmark{ font-family:'Baloo 2',sans-serif; font-weight:700; font-size:18px; }
  .wordmark span{ color:var(--blue-500); }
  .topbar-actions{ display:flex; align-items:center; gap:10px; }
  .avatar-chip{
    display:flex; align-items:center; gap:9px; background:var(--surface); border:1px solid var(--line);
    padding:5px 12px 5px 5px; border-radius:999px; box-shadow:var(--shadow-sm);
    text-decoration:none; color:inherit; transition:border-color .15s ease, box-shadow .15s ease;
  }
  .avatar-chip:hover{ border-color:var(--blue-500); box-shadow:var(--shadow-card, 0 8px 18px -8px rgba(15,95,174,0.35)); }
  .avatar-chip .av{
    width:30px;height:30px;border-radius:50%; background:linear-gradient(155deg,var(--blue-500),var(--blue-700));
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
  .back-link:hover{ color:var(--blue-600); }
  h1{ font-family:'Baloo 2',sans-serif; font-size:24px; font-weight:700; margin:0 0 18px; }

  .flash{ font-weight:700; font-size:13px; border-radius:14px; padding:12px 16px; margin-bottom:18px; }
  .flash.error{ background:var(--danger-bg); border:1px solid var(--danger); color:var(--danger); }

  .credit-banner{
    display:flex; align-items:center; gap:12px; background:var(--success-bg); border:1px solid var(--success);
    border-radius:16px; padding:14px 16px; margin-bottom:20px;
  }
  .credit-banner.out{ background:var(--amber-bg); border-color:var(--amber); }
  .credit-banner .ci{ width:34px;height:34px;border-radius:10px; background:var(--success); color:#fff; display:flex;align-items:center;justify-content:center; flex-shrink:0; }
  .credit-banner.out .ci{ background:var(--amber); }
  .credit-banner .ct{ font-weight:800; font-size:13.5px; color:var(--success); }
  .credit-banner.out .ct{ color:var(--amber); }
  .credit-banner .cd{ font-size:12.5px; color:var(--navy-900); font-weight:500; opacity:.85; }

  .card{ background:var(--surface); border:1px solid var(--line); border-radius:22px; padding:24px; box-shadow:var(--shadow-sm); }

  .field{ margin-bottom:16px; }
  .field label{ font-size:13px; font-weight:700; display:block; margin-bottom:6px; }
  .field .opt{ font-weight:600; color:var(--slate-400); }
  .field .hint{ font-size:11.5px; color:var(--slate-400); font-weight:500; margin-top:5px; }
  input, select, textarea{
    width:100%; font:500 14px/1 'Inter',sans-serif; padding:12px 13px; border:1.5px solid var(--line); border-radius:11px;
    background:var(--bg-0); color:var(--navy-900); outline:none; transition:border-color .15s ease; appearance:none;
  }
  select{
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none'%3E%3Cpath d='M6 9l6 6 6-6' stroke='%238a97a3' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-repeat:no-repeat; background-position:right 14px center; padding-right:38px;
  }
  textarea{ resize:vertical; min-height:70px; font-family:'Inter',sans-serif; }
  input:focus, select:focus, textarea:focus{ border-color:var(--blue-500); background:var(--surface); }
  .field-error{ font-size:11.5px; color:var(--danger); font-weight:600; margin-top:5px; }
  .grid2{ display:grid; grid-template-columns:1fr 1fr; gap:12px; }

  .generate-btn{
    width:100%; padding:14px; border:none; border-radius:12px; font:700 15px/1 'Inter',sans-serif; cursor:pointer;
    background:linear-gradient(155deg, var(--teal), #157a67); color:#fff; box-shadow:0 12px 22px -10px rgba(31,158,131,0.45);
    transition:transform .15s ease, opacity .15s ease;
  }
  .generate-btn:hover{ transform:translateY(-1px); }
  .generate-btn:disabled{ opacity:.5; cursor:not-allowed; transform:none; }

  @media (max-width:480px){ .grid2{ grid-template-columns:1fr; } }
  a:focus-visible, button:focus-visible, input:focus-visible, select:focus-visible{ outline:2px solid var(--blue-500); outline-offset:2px; }
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
      <a href="{{ route('teacher.profile.edit') }}" class="avatar-chip" aria-label="My Profile">
        <span class="av">{{ strtoupper(substr($teacher->user->first_name, 0, 1)) }}</span>
        <span class="name">{{ $teacher->user->first_name }}</span>
      </a>
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="logout-btn">Log out</button>
      </form>
    </div>
  </div>

  <a href="{{ route('teacher.dashboard') }}" class="back-link">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    Dashboard
  </a>
  <h1>Generate AI Activity</h1>

  @if ($errors->any())
    <div class="flash error">{{ $errors->first() }}</div>
  @endif

  @php $credits = $teacher->free_generation_credits_remaining; @endphp
  <div class="credit-banner {{ $credits <= 0 ? 'out' : '' }}">
    <div class="ci">
      @if ($credits > 0)
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="white" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
      @else
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="white" stroke-width="2"/><path d="M12 8v5M12 16h.01" stroke="white" stroke-width="2" stroke-linecap="round"/></svg>
      @endif
    </div>
    <div>
      <div class="ct">{{ $credits }} free credit{{ $credits === 1 ? '' : 's' }} remaining</div>
      <div class="cd">
        @if ($credits > 0)
          Every "Generate" click uses 1 credit and returns Easy/Medium/Hard drafts together.
        @elseif ($teacher->status === 'Active')
          Share an approved activity to the Repository to earn 2 more. (Sharing isn't built yet — coming soon.)
        @else
          More free credits unlock once your school verification is approved.
        @endif
      </div>
    </div>
  </div>

  <div class="card">
    <form method="POST" action="{{ route('teacher.activities.generate') }}" id="generateForm">
      @csrf

      <div class="grid2">
        <div class="field">
          <label for="grade_level">Grade Level</label>
          <select name="grade_level" id="grade_level" required>
            @foreach (['Grade 1', 'Grade 2', 'Grade 3'] as $grade)
              <option value="{{ $grade }}" @selected(old('grade_level') === $grade)>{{ $grade }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label for="activity_count">Activity Count <span class="opt">per difficulty level</span></label>
          <input type="number" name="activity_count" id="activity_count" min="1" max="{{ $maxVariants }}"
                 value="{{ old('activity_count', 3) }}" required>
        </div>
      </div>

      <div class="field">
        <label for="competency">Competency</label>
        <select name="competency" id="competency" required>
          @foreach ($competencies as $key => $c)
            <option value="{{ $key }}" data-types="{{ implode(',', $c['activity_types']) }}" @selected(old('competency') === $key)>{{ $c['label'] }}</option>
          @endforeach
        </select>
        <div class="hint" id="competencyHint"></div>
      </div>

      <div class="field">
        <label for="activity_type">Activity Type</label>
        <select name="activity_type" id="activity_type" required></select>
      </div>

      <div class="field">
        <label for="topic">Topic <span class="opt">optional</span></label>
        <input type="text" name="topic" id="topic" placeholder="e.g. Animals" maxlength="200" value="{{ old('topic') }}">
      </div>

      <div class="field">
        <label for="teacher_notes">Notes for the AI <span class="opt">optional</span></label>
        <textarea name="teacher_notes" id="teacher_notes" maxlength="1000" placeholder="e.g. Focus on words with the 'sh' sound">{{ old('teacher_notes') }}</textarea>
      </div>

      <button type="submit" class="generate-btn" id="generateBtn" @disabled($credits <= 0)>
        Generate Activity
      </button>
    </form>
  </div>
</div>

<script>
  const ACTIVITY_TYPE_LABELS = @json($activityTypeLabels);
  const competencySelect = document.getElementById('competency');
  const activityTypeSelect = document.getElementById('activity_type');
  const competencyHint = document.getElementById('competencyHint');
  const oldActivityType = @json(old('activity_type'));

  const competencyDescriptions = @json(collect($competencies)->pluck('description')->values());
  const competencyKeys = @json(array_keys($competencies));

  function refreshActivityTypes() {
    const opt = competencySelect.selectedOptions[0];
    const types = opt.dataset.types.split(',');
    activityTypeSelect.innerHTML = '';
    types.forEach(type => {
      const el = document.createElement('option');
      el.value = type;
      el.textContent = ACTIVITY_TYPE_LABELS[type] || type;
      if (type === oldActivityType) el.selected = true;
      activityTypeSelect.appendChild(el);
    });
    const index = competencyKeys.indexOf(competencySelect.value);
    competencyHint.textContent = index >= 0 ? competencyDescriptions[index] : '';
  }

  competencySelect.addEventListener('change', refreshActivityTypes);
  refreshActivityTypes();

  document.getElementById('generateForm').addEventListener('submit', function () {
    const btn = document.getElementById('generateBtn');
    btn.disabled = true;
    btn.textContent = 'AI is writing your lesson…';
  });
</script>
</body>
</html>
