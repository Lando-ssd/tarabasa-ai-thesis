<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TaraBasa AI — Progress</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --sky-50:#eef6ff; --sky-100:#dcedff;
    --blue-600:#0f5fae;
    --navy-900:#131f2b; --slate-600:#5b6b7a; --slate-400:#8a97a3;
    --clay-yellow:#ffcf6e; --owl-orange-600:#dd7014; --parent-teal:#1f9e83;
    --line:#e3ebf2; --surface:#ffffff; --bg-0:#f6faff;
    --success:#1f9e83; --amber:#c9820b;
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
  .wordmark span{ color:var(--owl-orange-600); }
  .topbar-actions{ display:flex; align-items:center; gap:10px; }
  .avatar-chip{
    display:flex; align-items:center; gap:9px; background:var(--surface); border:1px solid var(--line);
    padding:5px 12px 5px 5px; border-radius:999px; box-shadow:var(--shadow-sm);
    text-decoration:none; color:inherit; transition:border-color .15s ease, box-shadow .15s ease;
  }
  .avatar-chip:hover{ border-color:var(--parent-teal); box-shadow:var(--shadow-card, 0 8px 18px -8px rgba(31,158,131,0.35)); }
  .avatar-chip .av{
    width:30px;height:30px;border-radius:50%; background:linear-gradient(155deg,var(--parent-teal),#157a67);
    display:flex;align-items:center;justify-content:center; color:#fff; font-weight:700; font-size:13px;
  }
  .avatar-chip span.name{ font-size:13.5px; font-weight:700; }
  .logout-btn{
    background:var(--surface); border:1px solid var(--line); color:var(--slate-600); font:700 13px/1 'Inter',sans-serif;
    padding:10px 16px; border-radius:12px; cursor:pointer; box-shadow:var(--shadow-sm);
    transition:color .15s ease, border-color .15s ease;
  }
  .logout-btn:hover{ color:#d64545; border-color:#d64545; }

  .back-link{
    display:inline-flex; align-items:center; gap:6px; font-size:13.5px; font-weight:700; color:var(--slate-600);
    text-decoration:none; margin:18px 0 6px;
  }
  .back-link:hover{ color:var(--blue-600); }

  h1{ font-family:'Baloo 2',sans-serif; font-size:25px; font-weight:700; margin:0 0 4px; }
  .sub{ color:var(--slate-600); font-size:13.5px; font-weight:500; margin:0 0 18px; }

  .child-tabs{ display:flex; gap:8px; margin-bottom:20px; flex-wrap:wrap; }
  .child-tab{
    display:inline-flex; align-items:center; gap:8px; padding:8px 16px 8px 8px; border-radius:999px; border:1.5px solid var(--line);
    background:var(--surface); font:700 13px/1 'Inter',sans-serif; color:var(--slate-600); cursor:pointer; text-decoration:none;
    transition:border-color .15s ease, color .15s ease;
  }
  .child-tab:not(.active):hover{ border-color:var(--parent-teal); color:var(--parent-teal); }
  .child-tab.active{ background:var(--navy-900); color:var(--bg-0); border-color:var(--navy-900); }
  .child-tab .av{ width:26px;height:26px;border-radius:50%; overflow:hidden; background:linear-gradient(155deg,var(--clay-yellow),var(--owl-orange-600)); display:flex;align-items:center;justify-content:center; font-size:14px; flex-shrink:0; }
  .child-tab .av img{ width:100%; height:100%; object-fit:cover; }

  .stat-cards{ display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:22px; }
  .stat-card{ background:var(--surface); border:1px solid var(--line); border-radius:18px; padding:16px 18px; box-shadow:var(--shadow-sm); }
  .stat-card .lbl{ font-size:12.5px; color:var(--slate-600); font-weight:700; }
  .stat-card .val{ font-family:'Baloo 2',sans-serif; font-size:24px; font-weight:700; margin:2px 0; }
  .stat-card .sub{ font-size:12px; color:var(--slate-400); font-weight:600; }

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

  .empty-hero{ background:var(--surface); border:1px solid var(--line); border-radius:22px; padding:40px 30px; text-align:center; box-shadow:var(--shadow-sm); }
  .empty-hero .big-icon{
    width:64px;height:64px;border-radius:20px; margin:0 auto 16px; background:linear-gradient(155deg,var(--clay-yellow),var(--owl-orange-600));
    display:flex;align-items:center;justify-content:center; font-size:30px;
  }
  .empty-hero h2{ font-family:'Baloo 2',sans-serif; font-size:19px; margin:0 0 6px; }
  .empty-hero p{ color:var(--slate-600); font-size:13.5px; font-weight:500; margin:0 0 24px; max-width:380px; margin-left:auto; margin-right:auto; }
  .btn-primary{
    display:inline-flex; align-items:center; gap:8px; background:linear-gradient(155deg,#28b895,var(--parent-teal));
    color:#fff; font:700 14px/1 'Inter',sans-serif; padding:12px 20px; border-radius:12px; border:none; cursor:pointer;
    text-decoration:none; box-shadow:0 10px 20px -8px rgba(31,158,131,.5);
  }

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
      <a href="{{ route('parent.profile.edit') }}" class="avatar-chip" aria-label="My Profile">
        <span class="av">{{ strtoupper(substr($parent->user->first_name, 0, 1)) }}</span>
        <span class="name">{{ $parent->user->first_name }}</span>
      </a>
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="logout-btn">Log out</button>
      </form>
    </div>
  </div>

  <a href="{{ route('parent.children.index') }}" class="back-link">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    My Children
  </a>

  <h1>Progress</h1>
  <p class="sub">Here's how {{ $learners->count() === 1 ? "your child is" : "your children are" }} doing.</p>

  @if ($learners->isEmpty())
    {{-- Parent Actor Prompt, Validation & Edge Cases: zero Learners linked
         gets its own friendly empty state, never a broken/blank page. --}}
    <div class="empty-hero">
      <div class="big-icon">📈</div>
      <h2>No progress to show yet</h2>
      <p>Once you've added a child, their reading sessions and progress will show up here.</p>
      <a href="{{ route('parent.children.create') }}" class="btn-primary">Add Your Child</a>
    </div>
  @else
    @if ($learners->count() > 1)
      <div class="child-tabs">
        @foreach ($learners as $learner)
          <a href="{{ route('parent.progress', ['learner_id' => $learner->id]) }}" class="child-tab {{ $learner->id === $selectedLearner->id ? 'active' : '' }}">
            <span class="av">
              @if ($learner->avatar_photo_path)
                <img src="{{ Storage::url($learner->avatar_photo_path) }}" alt="{{ $learner->first_name }}">
              @else
                {{ $learner->avatar_id }}
              @endif
            </span>
            {{ $learner->first_name }}
          </a>
        @endforeach
      </div>
    @endif

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

    @if ($learnerStats['chart_points']->isEmpty())
      <div class="empty-note">No reading sessions yet for {{ $selectedLearner->first_name }} — once they read something, it'll show up here.</div>
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
</div>
</body>
</html>
