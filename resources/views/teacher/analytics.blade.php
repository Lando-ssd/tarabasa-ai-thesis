<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TaraBasa AI — Analytics</title>
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
    --amber:#c9820b;
    --shadow-sm:0 1px 2px rgba(19,31,43,0.06);
  }
  *{box-sizing:border-box;} html,body{margin:0;padding:0;}
  body{
    min-height:100vh; font-family:'Inter',sans-serif; color:var(--navy-900);
    background: radial-gradient(1100px 500px at 90% -10%, var(--sky-100), transparent 55%), var(--bg-0);
    background-attachment:fixed;
  }
  .shell{ max-width:820px; margin:0 auto; padding:22px 24px 64px; }

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
  .logout-btn:hover{ color:var(--danger, #d64545); border-color:var(--danger, #d64545); }

  .back-link{
    display:inline-flex; align-items:center; gap:6px; font-size:13.5px; font-weight:700; color:var(--slate-600);
    text-decoration:none; margin:18px 0 6px;
  }
  .back-link:hover{ color:var(--blue-600); }

  h1{ font-family:'Baloo 2',sans-serif; font-size:25px; font-weight:700; margin:0 0 16px; }

  .mode-tabs{ display:flex; gap:8px; margin-bottom:16px; }
  .mode-tab{
    padding:9px 16px; border-radius:999px; border:1.5px solid var(--line); background:var(--surface);
    font:700 13px/1 'Inter',sans-serif; color:var(--slate-600); cursor:pointer; text-decoration:none;
    transition:border-color .15s ease, color .15s ease;
  }
  .mode-tab:not(.active):hover{ border-color:var(--blue-500); color:var(--blue-600); }
  .mode-tab.active{ background:var(--navy-900); color:var(--bg-0); border-color:var(--navy-900); }

  select.picker{
    width:100%; font:600 14px/1 'Inter',sans-serif; padding:12px 14px; border:1.5px solid var(--line); border-radius:12px;
    background:var(--surface); color:var(--navy-900); margin-bottom:20px; appearance:none;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none'%3E%3Cpath d='M6 9l6 6 6-6' stroke='%235b6b7a' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-repeat:no-repeat; background-position:right 14px center; padding-right:38px;
  }

  .stat-cards{ display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:22px; }
  .stat-card{ background:var(--surface); border:1px solid var(--line); border-radius:18px; padding:16px 18px; box-shadow:var(--shadow-sm); }
  .stat-card .lbl{ font-size:12.5px; color:var(--slate-600); font-weight:700; }
  .stat-card .val{ font-family:'Baloo 2',sans-serif; font-size:24px; font-weight:700; margin:2px 0; }
  .stat-card .sub{ font-size:12px; color:var(--slate-400); font-weight:600; }

  .adaptive-card{ background:var(--surface); border:1px solid var(--line); border-radius:18px; padding:16px 18px; box-shadow:var(--shadow-sm); margin-bottom:20px; }
  .adaptive-card-title{ font-family:'Baloo 2',sans-serif; font-size:14.5px; font-weight:700; margin-bottom:8px; }
  .adaptive-headline{ font-size:13px; color:var(--navy-900); font-weight:500; margin:0 0 10px; }
  .adaptive-empty{ font-size:13px; color:var(--slate-600); font-weight:500; margin:0; }
  .adaptive-chips{ display:flex; gap:7px; flex-wrap:wrap; }
  .adaptive-chip{ font-size:11px; font-weight:700; padding:4px 10px; border-radius:999px; background:var(--bg-0); border:1px solid var(--line); color:var(--slate-600); }
  .adaptive-chip.active{ background:#fff3e2; border-color:var(--owl-orange-500); color:var(--owl-orange-600); }

  .chart-card{ background:var(--surface); border:1px solid var(--line); border-radius:18px; padding:20px; box-shadow:var(--shadow-sm); margin-bottom:20px; }
  .chart-title{ font-family:'Baloo 2',sans-serif; font-size:15px; font-weight:700; margin-bottom:14px; }
  svg.chart{ width:100%; height:auto; }

  .history-list{ background:var(--surface); border:1px solid var(--line); border-radius:18px; overflow:hidden; box-shadow:var(--shadow-sm); }
  .history-row{ display:flex; align-items:center; gap:12px; padding:13px 16px; border-top:1px solid var(--line); }
  .history-row:first-child{ border-top:none; }
  .hr-info{ flex:1; min-width:0; }
  .hr-info .nm{ font-size:13.5px; font-weight:700; }
  .hr-info .mt{ font-size:12px; color:var(--slate-600); font-weight:500; }
  .hr-source{ font-size:10.5px; font-weight:800; padding:3px 9px; border-radius:999px; background:var(--sky-50); color:var(--blue-600); flex-shrink:0; }
  .hr-score{ font-weight:800; font-size:14px; flex-shrink:0; }

  .group-row{
    display:flex; align-items:center; gap:12px; background:var(--surface); border:1px solid var(--line); border-radius:14px;
    padding:12px 16px; margin-bottom:8px; box-shadow:var(--shadow-sm);
  }
  .group-row .av{ width:36px;height:36px;border-radius:11px; font-size:17px; background:linear-gradient(155deg,#ffcf6e,var(--owl-orange-600)); display:flex;align-items:center;justify-content:center; flex-shrink:0; overflow:hidden; }
  .group-row .av img{ width:100%; height:100%; object-fit:cover; }

  .empty-note{ background:var(--surface); border:1px solid var(--line); border-radius:18px; padding:28px 20px; text-align:center; color:var(--slate-600); font-weight:600; font-size:13.5px; box-shadow:var(--shadow-sm); }
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

  <h1>Analytics</h1>

  <div class="mode-tabs">
    <a href="{{ route('teacher.analytics.index', ['mode' => 'learner']) }}" class="mode-tab {{ $mode === 'learner' ? 'active' : '' }}">By Learner</a>
    <a href="{{ route('teacher.analytics.index', ['mode' => 'group']) }}" class="mode-tab {{ $mode === 'group' ? 'active' : '' }}">By Group</a>
  </div>

  @if ($learners->isEmpty() && $groupTags->isEmpty())
    <div class="empty-note">
      No learners yet. Once you've joined learners into your classes, their reading progress will show up here.
    </div>
  @elseif ($mode === 'learner')
    @if ($learners->isEmpty())
      <div class="empty-note">You don't have any learners yet.</div>
    @else
      <form method="GET" action="{{ route('teacher.analytics.index') }}" id="learnerForm">
        <input type="hidden" name="mode" value="learner">
        <select name="learner_id" class="picker" onchange="document.getElementById('learnerForm').submit()">
          @foreach ($learners as $learner)
            <option value="{{ $learner->id }}" @selected($learner->id === $selectedLearner->id)>{{ $learner->first_name }} {{ $learner->last_name }} — {{ $learner->grade_level }}</option>
          @endforeach
        </select>
      </form>

      <div class="stat-cards">
        <div class="stat-card">
          <div class="lbl">Teacher-Assigned</div>
          <div class="val">{{ $learnerStats['source_summary']['Teacher']['count'] }} session{{ $learnerStats['source_summary']['Teacher']['count'] === 1 ? '' : 's' }}</div>
          <div class="sub">Avg accuracy: {{ $learnerStats['source_summary']['Teacher']['avg_accuracy'] !== null ? $learnerStats['source_summary']['Teacher']['avg_accuracy'] . '%' : '—' }}</div>
        </div>
        <div class="stat-card">
          <div class="lbl">Parent-Initiated</div>
          <div class="val">{{ $learnerStats['source_summary']['Parent']['count'] }} session{{ $learnerStats['source_summary']['Parent']['count'] === 1 ? '' : 's' }}</div>
          <div class="sub">Avg accuracy: {{ $learnerStats['source_summary']['Parent']['avg_accuracy'] !== null ? $learnerStats['source_summary']['Parent']['avg_accuracy'] . '%' : '—' }}</div>
        </div>
      </div>

      @include('partials.adaptive-focus-card', ['learner' => $selectedLearner])

      @if ($learnerStats['chart_points']->isEmpty())
        <div class="empty-note">No reading sessions yet for {{ $selectedLearner->first_name }}.</div>
      @else
        <div class="chart-card">
          <div class="chart-title">Accuracy Trend</div>
          <svg class="chart" viewBox="0 0 400 160">
            <line x1="30" y1="10" x2="30" y2="140" stroke="#e3ebf2" stroke-width="1.5"/>
            <line x1="30" y1="140" x2="380" y2="140" stroke="#e3ebf2" stroke-width="1.5"/>
            <text x="10" y="15" font-size="10" fill="#8a97a3">100</text>
            <text x="10" y="80" font-size="10" fill="#8a97a3">50</text>
            <text x="10" y="144" font-size="10" fill="#8a97a3">0</text>
            @if ($learnerStats['chart_points']->count() > 1)
              <polyline points="{{ $learnerStats['polyline'] }}" fill="none" stroke="#1f9e83" stroke-width="2.5"/>
            @endif
            @foreach ($learnerStats['chart_points'] as $point)
              <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4" fill="#1f9e83"/>
              <text x="{{ $point['x'] - 5 }}" y="155" font-size="10" fill="#8a97a3">{{ $point['label'] }}</text>
            @endforeach
          </svg>
        </div>

        <div class="chart-title" style="margin-bottom:10px;">Session History</div>
        <div class="history-list">
          @foreach ($learnerStats['history'] as $session)
            <div class="history-row">
              <div class="hr-info">
                <div class="nm">{{ $session->activity->title ?? 'Reading Activity' }}</div>
                <div class="mt">{{ $session->level_before ?? '—' }} → {{ $session->level_after ?? '—' }} · {{ $session->timestamp->format('M j, g:i A') }}</div>
              </div>
              <span class="hr-source">{{ $session->initiated_by }}</span>
              <span class="hr-score" style="color:{{ $session->accuracy_percent >= 80 ? 'var(--success)' : 'var(--amber)' }};">{{ round($session->accuracy_percent) }}%</span>
            </div>
          @endforeach
        </div>
      @endif
    @endif
  @else
    @if ($groupTags->isEmpty())
      <div class="empty-note">You don't have any Group tags set up on your classes yet.</div>
    @else
      <form method="GET" action="{{ route('teacher.analytics.index') }}" id="groupForm">
        <input type="hidden" name="mode" value="group">
        <select name="group_tag" class="picker" onchange="document.getElementById('groupForm').submit()">
          @foreach ($groupTags as $tag)
            <option value="{{ $tag }}" @selected($tag === $selectedGroupTag)>{{ $tag }}</option>
          @endforeach
        </select>
      </form>

      <div class="stat-cards">
        <div class="stat-card">
          <div class="lbl">Teacher-Assigned</div>
          <div class="val">{{ $groupStats['source_summary']['Teacher']['count'] }} session{{ $groupStats['source_summary']['Teacher']['count'] === 1 ? '' : 's' }}</div>
          <div class="sub">Across group</div>
        </div>
        <div class="stat-card">
          <div class="lbl">Parent-Initiated</div>
          <div class="val">{{ $groupStats['source_summary']['Parent']['count'] }} session{{ $groupStats['source_summary']['Parent']['count'] === 1 ? '' : 's' }}</div>
          <div class="sub">Across group</div>
        </div>
      </div>

      @if ($groupStats['learner_rows']->isEmpty())
        <div class="empty-note">No learners are currently in this group.</div>
      @else
        @foreach ($groupStats['learner_rows'] as $row)
          <div class="group-row">
            <span class="av">
              @if ($row['learner']->avatar_photo_path)
                <img src="{{ Storage::url($row['learner']->avatar_photo_path) }}" alt="{{ $row['learner']->first_name }}">
              @else
                {{ $row['learner']->avatar_id }}
              @endif
            </span>
            <div class="hr-info">
              <div class="nm">{{ $row['learner']->first_name }} {{ $row['learner']->last_name }}</div>
              <div class="mt">{{ $row['learner']->mastery_level ?? 'New' }} · {{ $row['session_count'] }} session{{ $row['session_count'] === 1 ? '' : 's' }}</div>
            </div>
          </div>
        @endforeach
      @endif
    @endif
  @endif
</div>
</body>
</html>
