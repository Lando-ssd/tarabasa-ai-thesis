<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TaraBasa AI — Parent Dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --sky-50:#eef6ff; --sky-100:#dcedff;
    --blue-500:#1c7ed6; --blue-600:#0f5fae; --blue-700:#0a3d73;
    --navy-900:#131f2b; --slate-600:#5b6b7a; --slate-400:#8a97a3;
    --clay-yellow:#ffcf6e; --owl-orange-600:#dd7014; --parent-teal:#1f9e83; --parent-teal-dark:#157a67;
    --line:#e3ebf2; --surface:#ffffff; --bg-0:#f6faff;
    --danger:#d64545; --danger-bg:#fdecec; --danger-line:#f3c9c9;
    --purple:#7c5cd6; --purple-bg:#f1edfc; --success-bg:#e9f7f3;
    --amber:#c9820b;
    --shadow-sm:0 1px 2px rgba(19,31,43,0.06);
    --shadow-card:0 20px 40px -24px rgba(15,60,110,0.18);
  }
  *{box-sizing:border-box;} html,body{margin:0;padding:0;}
  body{
    min-height:100vh; font-family:'Inter',sans-serif; color:var(--navy-900);
    background: radial-gradient(1100px 500px at 90% -10%, var(--sky-100), transparent 55%), var(--bg-0);
    background-attachment:fixed;
  }
  .shell{ max-width:1080px; margin:0 auto; padding:22px 24px 64px; }

  .topbar{ display:flex; align-items:center; justify-content:space-between; margin-bottom:26px; }
  .logo-lockup{ display:flex; align-items:center; gap:10px; }
  .logo-badge{ width:38px;height:38px;border-radius:11px; overflow:hidden; box-shadow:0 6px 14px -5px rgba(15,95,174,0.5); }
  .logo-badge img{ width:100%; height:100%; object-fit:cover; display:block; }
  .wordmark{ font-family:'Baloo 2',sans-serif; font-weight:700; font-size:18px; }
  .wordmark span{ color:var(--owl-orange-600); }
  .topbar-actions{ display:flex; align-items:center; gap:10px; }
  .icon-btn{
    position:relative; width:40px;height:40px; border-radius:12px; background:var(--surface); border:1px solid var(--line);
    display:flex;align-items:center;justify-content:center; cursor:pointer; color:var(--slate-600); box-shadow:var(--shadow-sm);
    text-decoration:none; transition:color .15s ease, border-color .15s ease;
  }
  .icon-btn:hover{ color:var(--parent-teal); border-color:var(--parent-teal); }
  .badge-dot{
    position:absolute; top:-4px; right:-4px; min-width:18px; height:18px; padding:0 4px; border-radius:999px;
    background:var(--danger); color:#fff; font-size:10.5px; font-weight:800;
    display:flex;align-items:center;justify-content:center; border:2px solid var(--bg-0);
  }
  .avatar-chip{
    display:flex; align-items:center; gap:9px; background:var(--surface); border:1px solid var(--line);
    padding:5px 12px 5px 5px; border-radius:999px; box-shadow:var(--shadow-sm); text-decoration:none; color:inherit;
    transition:border-color .15s ease, box-shadow .15s ease;
  }
  .avatar-chip:hover{ border-color:var(--parent-teal); box-shadow:var(--shadow-card); }
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

  .greeting h1{ font-family:'Baloo 2',sans-serif; font-size:27px; font-weight:700; margin:0 0 4px; }
  .greeting p{ margin:0 0 22px; color:var(--slate-600); font-size:15px; font-weight:500; }

  .child-selector{ margin-bottom:22px; }
  .child-selector .label{ font-size:12.5px; font-weight:700; color:var(--slate-600); margin-bottom:9px; }
  .chip-row{ display:flex; gap:9px; flex-wrap:wrap; }
  .child-chip{
    display:flex; align-items:center; gap:8px; padding:8px 16px 8px 8px; border-radius:999px;
    border:1.5px solid var(--line); background:var(--surface); cursor:pointer; font-weight:700; font-size:13.5px; color:var(--slate-600);
    text-decoration:none; transition:border-color .15s ease, background .15s ease, color .15s ease;
  }
  .child-chip:hover{ border-color:var(--parent-teal); }
  .child-chip .mini-av{
    width:26px;height:26px;border-radius:50%; display:flex;align-items:center;justify-content:center; font-size:14px; overflow:hidden;
    background:linear-gradient(155deg, var(--clay-yellow), var(--owl-orange-600));
  }
  .child-chip .mini-av img{ width:100%; height:100%; object-fit:cover; }
  .child-chip.active{ border-color:var(--parent-teal); background:var(--success-bg); color:var(--parent-teal); }
  .child-chip .flag-dot{ width:7px;height:7px;border-radius:50%; background:var(--danger); margin-left:-2px; }

  .alert-banner{
    display:flex; align-items:flex-start; gap:14px; background:var(--danger-bg); border:1px solid var(--danger-line);
    border-radius:18px; padding:16px 18px; margin-bottom:22px;
  }
  .alert-icon{
    width:38px;height:38px;border-radius:12px; background:var(--danger); flex-shrink:0;
    display:flex;align-items:center;justify-content:center; color:#fff;
  }
  .alert-body{ flex:1; min-width:0; }
  .alert-body .title{ font-weight:800; font-size:14.5px; color:var(--danger); margin-bottom:2px; }
  .alert-body .desc{ font-size:13.5px; color:var(--navy-900); font-weight:500; opacity:.9; }
  .alert-cta{
    flex-shrink:0; align-self:center; background:var(--danger); color:#fff; font-weight:700; font-size:13px;
    padding:9px 16px; border-radius:999px; white-space:nowrap; text-decoration:none; border:none; cursor:pointer;
  }
  .alert-cta:hover{ opacity:.9; }

  .focus-card{
    background:var(--surface); border:1px solid var(--line); border-radius:22px; padding:22px;
    box-shadow:var(--shadow-sm); display:flex; align-items:center; gap:18px; margin-bottom:18px; flex-wrap:wrap;
  }
  .focus-card.flagged{ border-color:var(--danger-line); }
  .focus-avatar{
    width:64px;height:64px;border-radius:20px; flex-shrink:0; font-size:32px; overflow:hidden;
    background:linear-gradient(155deg, var(--clay-yellow), var(--owl-orange-600));
    display:flex;align-items:center;justify-content:center;
  }
  .focus-avatar img{ width:100%; height:100%; object-fit:cover; }
  .focus-info{ flex:1; min-width:180px; }
  .focus-info h2{ font-family:'Baloo 2',sans-serif; font-size:19.5px; font-weight:700; margin:0 0 3px; display:flex; align-items:center; gap:8px; }
  .focus-info .meta{ font-size:12.5px; color:var(--slate-600); font-weight:600; margin-bottom:8px; }
  .flag-pill{
    display:inline-flex; align-items:center; gap:4px; background:var(--danger); color:#fff;
    font-size:10.5px; font-weight:800; padding:3px 8px; border-radius:999px;
  }
  .focus-stats{ display:flex; gap:18px; flex-wrap:wrap; }
  .focus-stat{ text-align:center; }
  .focus-stat b{ display:block; font-size:16.5px; font-weight:800; }
  .focus-stat span{ display:block; font-size:10.5px; color:var(--slate-600); font-weight:700; text-transform:uppercase; letter-spacing:.03em; }
  .start-practice-btn{
    display:flex; align-items:center; gap:8px; background:linear-gradient(155deg, var(--blue-500), var(--blue-700)); color:#fff;
    font-weight:700; font-size:14px; padding:12px 20px; border-radius:14px; border:none; cursor:pointer;
    box-shadow:0 12px 22px -10px rgba(15,95,174,0.55); white-space:nowrap; transition:transform .15s ease, opacity .15s ease;
    text-decoration:none;
  }
  .start-practice-btn:hover{ transform:translateY(-1px); }
  .start-practice-btn.soon{ background:var(--bg-0); color:var(--slate-400); box-shadow:none; border:1.5px solid var(--line); cursor:default; }
  .start-practice-btn.soon:hover{ transform:none; }

  .stat-cards{ display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:22px; }
  .stat-card{ background:var(--surface); border:1px solid var(--line); border-radius:18px; padding:16px 18px; box-shadow:var(--shadow-sm); }
  .stat-card .label{ font-size:12.5px; font-weight:700; color:var(--slate-600); margin-bottom:4px; }
  .stat-card .value{ font-family:'Baloo 2',sans-serif; font-size:24px; font-weight:700; }
  .stat-card .avg{ font-size:12px; color:var(--slate-400); font-weight:600; margin-top:2px; }

  .section-head{ display:flex; align-items:baseline; justify-content:space-between; margin-bottom:14px; }
  .section-head h2{ font-family:'Baloo 2',sans-serif; font-size:18px; font-weight:700; margin:0; }
  .section-head a{ font-size:13px; font-weight:700; color:var(--blue-600); text-decoration:none; }

  .activity-list{ background:var(--surface); border:1px solid var(--line); border-radius:20px; overflow:hidden; box-shadow:var(--shadow-sm); margin-bottom:26px; }
  .activity-row{ display:flex; align-items:center; gap:13px; padding:14px 18px; border-top:1px solid var(--line); }
  .activity-row:first-child{ border-top:none; }
  .activity-icon{ width:38px;height:38px;border-radius:11px; flex-shrink:0; display:flex;align-items:center;justify-content:center; color:#fff; }
  .activity-icon.ok{ background:linear-gradient(155deg,var(--parent-teal),var(--parent-teal-dark)); }
  .activity-icon.warn{ background:var(--danger); }
  .activity-body{ flex:1; min-width:0; }
  .activity-body .line1{ font-size:13.5px; font-weight:700; }
  .source-tag{
    display:inline-block; font-size:10px; font-weight:800; padding:2px 7px; border-radius:999px; margin-left:6px;
    background:var(--sky-50); color:var(--blue-600); vertical-align:middle;
  }
  .source-tag.parent{ background:var(--purple-bg); color:var(--purple); }
  .activity-body .line2{ font-size:12px; color:var(--slate-600); font-weight:500; margin-top:1px; }
  .activity-time{ font-size:12px; color:var(--slate-400); font-weight:600; white-space:nowrap; flex-shrink:0; }

  .empty-note{ background:var(--surface); border:1px solid var(--line); border-radius:18px; padding:24px 18px; text-align:center; color:var(--slate-600); font-weight:600; font-size:13.5px; box-shadow:var(--shadow-sm); margin-bottom:26px; }

  .action-grid{ display:grid; grid-template-columns:repeat(auto-fill, minmax(200px,1fr)); gap:14px; }
  .action-tile{
    background:var(--surface); border:1px solid var(--line); border-radius:18px; padding:18px;
    display:flex; flex-direction:column; gap:10px; text-decoration:none; color:inherit;
    transition:box-shadow .18s ease, transform .18s ease; box-shadow:var(--shadow-sm);
  }
  .action-tile:not(.soon):hover{ box-shadow:var(--shadow-card); transform:translateY(-2px); }
  .action-tile.soon{ opacity:.6; cursor:default; }
  .action-tile .tile-icon{ width:38px;height:38px;border-radius:11px; display:flex;align-items:center;justify-content:center; }
  .action-tile .tile-icon.add{ background:var(--sky-50); color:var(--blue-600); }
  .action-tile .tile-icon.link{ background:var(--success-bg); color:var(--parent-teal); }
  .action-tile .tile-icon.repo{ background:var(--purple-bg); color:var(--purple); }
  .action-tile .tt{ font-size:14.5px; font-weight:700; }
  .action-tile .td{ font-size:12px; color:var(--slate-600); font-weight:500; }

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

  @media (max-width:640px){
    .topbar{ flex-wrap:wrap; gap:10px; }
    .topbar-actions .avatar-chip span.name{ display:none; }
    .logout-btn{ white-space:nowrap; }
    .greeting h1{ font-size:22px; }
    .focus-card{ flex-direction:column; align-items:flex-start; }
    .start-practice-btn{ width:100%; justify-content:center; }
  }
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
      <a href="{{ route('parent.notifications.index') }}" class="icon-btn" aria-label="Notifications">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 8a6 6 0 0 1 12 0c0 4 1.5 5.5 1.5 5.5H4.5S6 12 6 8Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9.5 17a2.5 2.5 0 0 0 5 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        @if ($unreadNotifications > 0)
          <span class="badge-dot">{{ $unreadNotifications > 9 ? '9+' : $unreadNotifications }}</span>
        @endif
      </a>
      <a href="{{ route('parent.profile.edit') }}" class="avatar-chip" aria-label="My Profile">
        <span class="av">{{ strtoupper(substr($user->first_name, 0, 1)) }}</span>
        <span class="name">{{ $user->first_name }}</span>
      </a>
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="logout-btn">Log out</button>
      </form>
    </div>
  </div>

  @php
    $hour = now()->hour;
    $timeGreeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
  @endphp

  <div class="greeting">
    <h1>{{ $timeGreeting }}, {{ $user->first_name }}! 👋</h1>
    <p>Here's how your {{ $learners->count() === 1 ? 'child is' : 'children are' }} doing today.</p>
  </div>

  @if ($learners->isEmpty())
    {{-- Parent Actor Prompt, Validation & Edge Cases: zero Learners linked
         gets its own friendly empty state, never a broken/blank dashboard. --}}
    <div class="empty-hero">
      <div class="big-icon">🧒</div>
      <h2>No children added yet</h2>
      <p>Add your first child to start tracking their reading journey with TaraBasa AI.</p>
      <a href="{{ route('parent.children.create') }}" class="btn-primary">Add Your Child</a>
    </div>
  @else
    @if ($learners->count() > 1)
      <div class="child-selector">
        <div class="label">Select child</div>
        <div class="chip-row">
          @foreach ($learners as $learner)
            <a href="{{ route('parent.dashboard', ['learner_id' => $learner->id]) }}" class="child-chip {{ $learner->id === $selectedLearner->id ? 'active' : '' }}">
              <span class="mini-av">
                @if ($learner->avatar_photo_path)
                  <img src="{{ Storage::url($learner->avatar_photo_path) }}" alt="{{ $learner->first_name }}">
                @else
                  {{ $learner->avatar_id }}
                @endif
              </span>
              {{ $learner->first_name }}
              @if ($flaggedByLearner->get($learner->id))
                <span class="flag-dot"></span>
              @endif
            </a>
          @endforeach
        </div>
      </div>
    @endif

    @if ($childData['is_flagged'])
      <div class="alert-banner">
        <div class="alert-icon">
          <svg width="19" height="19" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="white" stroke-width="2"/><path d="M12 8v5M12 16h.01" stroke="white" stroke-width="2" stroke-linecap="round"/></svg>
        </div>
        <div class="alert-body">
          <div class="title">{{ $selectedLearner->first_name }} needs attention</div>
          <div class="desc">Their most recent reading session was flagged for extra support — a quick look could help.</div>
        </div>
        <a href="{{ route('parent.progress', ['learner_id' => $selectedLearner->id]) }}" class="alert-cta">View session</a>
      </div>
    @endif

    <div class="focus-card {{ $childData['is_flagged'] ? 'flagged' : '' }}">
      <div class="focus-avatar">
        @if ($selectedLearner->avatar_photo_path)
          <img src="{{ Storage::url($selectedLearner->avatar_photo_path) }}" alt="{{ $selectedLearner->first_name }}">
        @else
          {{ $selectedLearner->avatar_id }}
        @endif
      </div>
      <div class="focus-info">
        <h2>{{ $selectedLearner->first_name }} @if ($childData['is_flagged'])<span class="flag-pill">⚠ Flagged</span>@endif</h2>
        <div class="meta">{{ $selectedLearner->grade_level }} · {{ $selectedLearner->schoolClass->name ?? 'Not yet in a class' }} · Code {{ $selectedLearner->learner_code }}</div>
        <div class="focus-stats">
          <div class="focus-stat"><b>🔥 {{ $selectedLearner->streak }}</b><span>Streak</span></div>
          <div class="focus-stat"><b>{{ $selectedLearner->points }}</b><span>Points</span></div>
          <div class="focus-stat"><b>{{ $childData['wcpm'] !== null ? round($childData['wcpm']) : '—' }}</b><span>WCPM</span></div>
          <div class="focus-stat"><b>{{ $selectedLearner->mastery_level ?? 'New' }}</b><span>Level</span></div>
        </div>
      </div>
      {{-- The prototype's original "Start Practice" button implied the
           Parent reads directly with no PIN — that contradicts Learner
           Actor Prompt Step 1's hard rule that a Learner always needs
           their PIN, no exceptions. The real spec'd flow is: Parent
           unlocks something here, the Learner reads it later through
           their own normal PIN-gated picker (Step 3 already resolves
           unlocked content alongside Teacher assignments). This links to
           the real, spec-compliant entry point instead of a fake or
           misleading action. --}}
      <a href="{{ route('parent.repository.index', ['learner_id' => $selectedLearner->id]) }}" class="start-practice-btn">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M4 8l1.5-4h13L20 8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><rect x="4" y="8" width="16" height="12" rx="2" stroke="currentColor" stroke-width="1.8"/></svg>
        Browse Extra Activities
      </a>
    </div>

    <div class="stat-cards">
      <div class="stat-card">
        <div class="label">Teacher-Assigned Sessions</div>
        <div class="value">{{ $childData['source_summary']['Teacher']['count'] }}</div>
        <div class="avg">Avg score: {{ $childData['source_summary']['Teacher']['avg_accuracy'] !== null ? $childData['source_summary']['Teacher']['avg_accuracy'] . '%' : '—' }}</div>
      </div>
      <div class="stat-card">
        <div class="label">Parent-Initiated Sessions</div>
        <div class="value">{{ $childData['source_summary']['Parent']['count'] }}</div>
        <div class="avg">Avg score: {{ $childData['source_summary']['Parent']['avg_accuracy'] !== null ? $childData['source_summary']['Parent']['avg_accuracy'] . '%' : '—' }}</div>
      </div>
    </div>

    <div class="section-head"><h2>Recent Sessions</h2><a href="{{ route('parent.progress', ['learner_id' => $selectedLearner->id]) }}">See all</a></div>
    @if ($childData['recent_sessions']->isEmpty())
      <div class="empty-note">No reading sessions yet for {{ $selectedLearner->first_name }} — once they read something, it'll show up here.</div>
    @else
      <div class="activity-list">
        @foreach ($childData['recent_sessions'] as $session)
          <div class="activity-row">
            <div class="activity-icon {{ $session->flagged_needs_attention ? 'warn' : 'ok' }}">
              @if ($session->flagged_needs_attention)
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="white" stroke-width="2"/><path d="M12 8v5M12 16h.01" stroke="white" stroke-width="2" stroke-linecap="round"/></svg>
              @else
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="white" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
              @endif
            </div>
            <div class="activity-body">
              <div class="line1">{{ $session->activity->title ?? 'Reading Activity' }} <span class="source-tag {{ $session->initiated_by === 'Parent' ? 'parent' : '' }}">{{ $session->initiated_by }}</span></div>
              <div class="line2">
                @if ($session->flagged_needs_attention)
                  Flagged for extra support
                @elseif ($session->level_before !== $session->level_after)
                  {{ round($session->accuracy_percent) }}% accuracy — moved to {{ $session->level_after }}
                @else
                  {{ round($session->accuracy_percent) }}% accuracy
                @endif
              </div>
            </div>
            <div class="activity-time">{{ $session->timestamp->diffForHumans() }}</div>
          </div>
        @endforeach
      </div>
    @endif
  @endif

  <div class="section-head"><h2>Quick Actions</h2></div>
  <div class="action-grid">
    <a href="{{ route('parent.children.create') }}" class="action-tile">
      <span class="tile-icon add">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
      </span>
      <span class="tt">Add Another Child</span>
      <span class="td">Set up a new Learner profile</span>
    </a>
    <a href="{{ route('parent.children.link') }}" class="action-tile">
      <span class="tile-icon link">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6" stroke="currentColor" stroke-width="1.8"/><path d="M17 8l3 3-3 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </span>
      <span class="tt">Link Existing Child</span>
      <span class="td">Join as a second guardian</span>
    </a>
    {{-- Open Repository isn't built yet (Parent Actor Prompt Step 6) —
         honest Coming Soon tile, not a dead link. --}}
    <a href="{{ route('parent.repository.index', ['learner_id' => $selectedLearner->id ?? null]) }}" class="action-tile">
      <span class="tile-icon repo">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M4 8l1.5-4h13L20 8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><rect x="4" y="8" width="16" height="12" rx="2" stroke="currentColor" stroke-width="1.8"/></svg>
      </span>
      <span class="tt">Browse Repository</span>
      <span class="td">Extra activities, free &amp; paid</span>
    </a>
    <a href="{{ route('parent.notifications.index') }}" class="action-tile">
      <span class="tile-icon add">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 8a6 6 0 0 1 12 0c0 4 1.5 5.5 1.5 5.5H4.5S6 12 6 8Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9.5 17a2.5 2.5 0 0 0 5 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      </span>
      <span class="tt">Notifications</span>
      <span class="td">{{ $unreadNotifications > 0 ? $unreadNotifications.' unread update'.($unreadNotifications === 1 ? '' : 's') : 'Updates about your children' }}</span>
    </a>
  </div>

</div>
</body>
</html>
