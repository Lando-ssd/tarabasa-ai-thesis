<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TaraBasa AI — Grade Promotions</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --sky-50:#eef6ff; --sky-100:#dcedff;
    --blue-500:#1c7ed6; --blue-600:#0f5fae; --blue-700:#0a3d73;
    --navy-900:#131f2b; --slate-600:#5b6b7a; --slate-400:#8a97a3;
    --owl-orange-500:#ef8d2a; --owl-orange-600:#dd7014;
    --line:#e3ebf2; --surface:#ffffff; --bg-0:#f6faff;
    --success:#1f9e83; --success-bg:#e9f7f3;
    --amber:#c9820b; --amber-bg:#fef6e6;
    --purple:#7c5cd6; --purple-bg:#f1edfc;
    --danger:#d64545;
    --shadow-sm:0 1px 2px rgba(19,31,43,0.06);
    --shadow-card:0 20px 40px -24px rgba(15,60,110,0.18);
  }
  *{box-sizing:border-box;} html,body{margin:0;padding:0;}
  body{
    min-height:100vh; font-family:'Inter',sans-serif; color:var(--navy-900);
    background: radial-gradient(1100px 500px at 90% -10%, var(--sky-100), transparent 55%), var(--bg-0);
    background-attachment:fixed;
  }
  .shell{ max-width:760px; margin:0 auto; padding:22px 24px 64px; }

  .topbar{ display:flex; align-items:center; justify-content:space-between; margin-bottom:8px; }
  .logo-lockup{ display:flex; align-items:center; gap:10px; }
  .logo-badge{ width:38px;height:38px;border-radius:11px; overflow:hidden; box-shadow:0 6px 14px -5px rgba(15,95,174,0.5); }
  .logo-badge img{ width:100%; height:100%; object-fit:cover; display:block; }
  .wordmark{ font-family:'Baloo 2',sans-serif; font-weight:700; font-size:18px; }
  .wordmark span{ color:var(--owl-orange-500); }
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

  h1{ font-family:'Baloo 2',sans-serif; font-size:25px; font-weight:700; margin:0 0 16px; }

  .flash{ font-weight:700; font-size:13px; border-radius:14px; padding:12px 16px; margin-bottom:18px; background:var(--success-bg); border:1px solid var(--success); color:var(--success); }

  .lock-banner{
    display:flex; gap:12px; align-items:flex-start; background:var(--amber-bg); border:1px solid var(--amber);
    border-radius:16px; padding:14px 16px; margin-bottom:20px;
  }
  .lock-banner .ic{ width:32px;height:32px;border-radius:10px; background:var(--amber); flex-shrink:0; display:flex;align-items:center;justify-content:center; }
  .lock-banner .lt{ font-weight:800; font-size:13.5px; color:var(--amber); margin-bottom:2px; }
  .lock-banner .ld{ font-size:12.5px; color:var(--navy-900); font-weight:500; line-height:1.5; opacity:.9; }

  .tabs{ display:flex; gap:8px; margin-bottom:22px; }
  .tab{
    padding:9px 16px; border-radius:999px; border:1.5px solid var(--line); background:var(--surface);
    font:700 13px/1 'Inter',sans-serif; color:var(--slate-600); cursor:pointer; text-decoration:none;
    transition:border-color .15s ease, color .15s ease;
  }
  .tab:not(.active):hover{ border-color:var(--blue-500); color:var(--blue-600); }
  .tab.active{ background:var(--navy-900); color:var(--bg-0); border-color:var(--navy-900); }

  .class-label{ font-size:11.5px; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:var(--slate-400); margin:20px 0 10px; }
  .class-label:first-of-type{ margin-top:2px; }

  .row-card{
    display:flex; align-items:center; gap:14px; background:var(--surface); border:1px solid var(--line); border-radius:16px;
    padding:14px 16px; box-shadow:var(--shadow-sm); margin-bottom:10px; flex-wrap:wrap;
    transition:box-shadow .18s ease;
  }
  .row-card:hover{ box-shadow:var(--shadow-card); }
  .av{ width:42px;height:42px;border-radius:13px; font-size:20px; flex-shrink:0; overflow:hidden; background:linear-gradient(155deg,#ffcf6e,var(--owl-orange-600)); display:flex;align-items:center;justify-content:center; }
  .av img{ width:100%; height:100%; object-fit:cover; }
  .info{ flex:1; min-width:150px; }
  .info .nm{ font-size:14px; font-weight:700; }
  .info .mt{ font-size:12.5px; color:var(--slate-600); font-weight:600; margin-top:1px; }
  .promote-btn{
    display:flex; align-items:center; gap:7px; background:var(--navy-900); color:var(--bg-0); border:none; padding:10px 16px;
    border-radius:11px; font:700 12.5px/1 'Inter',sans-serif; cursor:pointer; transition:transform .15s ease, opacity .15s ease;
  }
  .promote-btn:hover{ transform:translateY(-1px); }
  .promote-btn:disabled{ opacity:.6; cursor:not-allowed; transform:none; }
  .final-year{ font-size:12px; color:var(--slate-400); font-weight:700; }

  .claim-item{ flex-direction:column; align-items:stretch; }
  .claim-top{ display:flex; align-items:center; gap:14px; }
  .grade-match{ display:flex; align-items:center; gap:8px; margin-top:10px; flex-wrap:wrap; }
  .claim-select{
    flex:1; min-width:180px; font:600 12.5px/1 'Inter',sans-serif; padding:9px 10px; border:1.5px solid var(--line); border-radius:9px;
    background:var(--bg-0); color:var(--navy-900);
  }
  .claim-btn{ background:var(--success); color:#fff; border:none; padding:9px 16px; border-radius:9px; font:700 12.5px/1 'Inter',sans-serif; cursor:pointer; transition:transform .15s ease, opacity .15s ease; }
  .claim-btn:hover{ transform:translateY(-1px); }
  .no-match-note{ font-size:12px; color:var(--slate-400); font-weight:600; margin-top:10px; }

  .empty-note{ background:var(--surface); border:1px solid var(--line); border-radius:18px; padding:36px 20px; text-align:center; color:var(--slate-600); font-weight:600; font-size:13.5px; box-shadow:var(--shadow-sm); }
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

  <h1>Grade Promotions</h1>

  @if (session('status'))
    <div class="flash">{{ session('status') }}</div>
  @endif

  @if ($teacher->status !== 'Active')
    <div class="lock-banner">
      <div class="ic">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><rect x="5" y="10" width="14" height="10" rx="2" stroke="white" stroke-width="1.8"/><path d="M8 10V7a4 4 0 0 1 8 0v3" stroke="white" stroke-width="1.8"/></svg>
      </div>
      <div>
        <div class="lt">Releasing and claiming is locked</div>
        <div class="ld">You can see what's here, but releasing a learner or claiming one into your class needs an Active account.</div>
      </div>
    </div>
  @endif

  <div class="tabs">
    <a href="{{ route('teacher.promotions.index', ['tab' => 'release']) }}" class="tab {{ $tab === 'release' ? 'active' : '' }}">Release Learners</a>
    <a href="{{ route('teacher.promotions.index', ['tab' => 'claim']) }}" class="tab {{ $tab === 'claim' ? 'active' : '' }}">Claim Incoming{{ $actionableCount > 0 ? " ({$actionableCount})" : '' }}</a>
  </div>

  @if ($tab === 'release')
    @if ($releasableLearners->isEmpty())
      <div class="empty-note">No learners in your current-year classes yet.</div>
    @else
      @foreach ($myCurrentClasses as $class)
        @continue($releasableLearners->get($class->id, collect())->isEmpty())
        <div class="class-label">{{ $class->name }}</div>
        @foreach ($releasableLearners->get($class->id) as $learner)
          @php $gradeNumber = (int) substr($learner->grade_level, 6); @endphp
          <div class="row-card">
            <div class="av">
              @if ($learner->avatar_photo_path)
                <img src="{{ Storage::url($learner->avatar_photo_path) }}" alt="{{ $learner->first_name }}">
              @else
                {{ $learner->avatar_id }}
              @endif
            </div>
            <div class="info">
              <div class="nm">{{ $learner->first_name }} {{ $learner->last_name }}</div>
              <div class="mt">{{ $learner->grade_level }} · {{ $learner->mastery_level ?? 'New' }}</div>
            </div>
            @if ($gradeNumber >= 3)
              <span class="final-year">No next grade (final year)</span>
            @elseif ($teacher->status === 'Active')
              <form method="POST" action="{{ route('teacher.promotions.release', $learner) }}">
                @csrf
                <button type="submit" class="promote-btn">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M17 2l4 4-4 4M21 6H8M7 22l-4-4 4-4M3 18h13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  Promote to Grade {{ $gradeNumber + 1 }}
                </button>
              </form>
            @else
              <button type="button" class="promote-btn" disabled title="Locked until your account is Active">Promote to Grade {{ $gradeNumber + 1 }}</button>
            @endif
          </div>
        @endforeach
      @endforeach
    @endif
  @else
    @if ($pendingRecords->isEmpty())
      <div class="empty-note">Nothing here right now — released learners waiting to be claimed into a class will show up here.</div>
    @else
      @foreach ($pendingRecords as $record)
        @php $matchingClasses = $matchingClassesByRecord->get($record->id, collect()); @endphp
        <div class="row-card claim-item">
          <div class="claim-top">
            <div class="av">
              @if ($record->learner->avatar_photo_path)
                <img src="{{ Storage::url($record->learner->avatar_photo_path) }}" alt="{{ $record->learner->first_name }}">
              @else
                {{ $record->learner->avatar_id }}
              @endif
            </div>
            <div class="info">
              <div class="nm">{{ $record->learner->first_name }} {{ $record->learner->last_name }}</div>
              <div class="mt">Released from {{ $record->releasedFromGrade() }} by {{ $record->releasedByTeacher->user->first_name ?? 'a Teacher' }} · needs {{ $record->next_grade }}</div>
            </div>
          </div>

          @if ($matchingClasses->isEmpty())
            <div class="no-match-note">You don't have a {{ $record->next_grade }} class this year — nothing to claim this one into.</div>
          @elseif ($teacher->status === 'Active')
            <form method="POST" action="{{ route('teacher.promotions.claim', $record) }}" class="grade-match">
              @csrf
              <select name="class_id" class="claim-select">
                @foreach ($matchingClasses as $class)
                  <option value="{{ $class->id }}">{{ $class->name }} — {{ $class->grade_level }} · Section {{ $class->section }}</option>
                @endforeach
              </select>
              <button type="submit" class="claim-btn">Claim</button>
            </form>
          @else
            <div class="no-match-note">Locked until your account is Active.</div>
          @endif
        </div>
      @endforeach
    @endif
  @endif

</div>
<script>
  document.querySelectorAll('form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      const btn = e.submitter || form.querySelector('button[type="submit"]');
      if (!btn || btn.disabled) return;
      btn.disabled = true;
      btn.dataset.originalText = btn.textContent;
      btn.textContent = 'Working…';
    });
  });
</script>
</body>
</html>
