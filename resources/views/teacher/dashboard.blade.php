<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TaraBasa AI — Teacher Dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --sky-50:#eef6ff; --sky-100:#dcedff;
    --blue-500:#1c7ed6; --blue-600:#0f5fae; --blue-700:#0a3d73;
    --navy-900:#131f2b; --slate-600:#5b6b7a; --slate-400:#8a97a3;
    --owl-orange-400:#f5a544; --owl-orange-500:#ef8d2a; --owl-orange-600:#dd7014;
    --line:#e3ebf2; --surface:#ffffff; --bg-0:#f6faff;
    --success:#1f9e83; --success-bg:#e9f7f3;
    --amber:#c9820b; --amber-bg:#fef6e6;
    --purple:#7c5cd6; --purple-bg:#f1edfc;
    --teal:#1f9e83; --teal-bg:#e9f7f3;
    --danger:#d64545; --danger-bg:#fdecec; --danger-line:#f3c9c9;
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

  .topbar{ display:flex; align-items:center; justify-content:space-between; margin-bottom:8px; }
  .logo-lockup{ display:flex; align-items:center; gap:10px; }
  .logo-badge{
    width:38px;height:38px;border-radius:11px; overflow:hidden;
    box-shadow:0 6px 14px -5px rgba(15,95,174,0.5);
  }
  .logo-badge img{ width:100%; height:100%; object-fit:cover; display:block; }
  .wordmark{ font-family:'Baloo 2',sans-serif; font-weight:700; font-size:18px; }
  .wordmark span{ color:var(--owl-orange-600); }
  .topbar-actions{ display:flex; align-items:center; gap:10px; }
  .icon-btn{
    position:relative; width:40px;height:40px; border-radius:12px; background:var(--surface); border:1px solid var(--line);
    display:flex;align-items:center;justify-content:center; cursor:pointer; color:var(--slate-600); box-shadow:var(--shadow-sm);
    text-decoration:none; transition:color .15s ease, border-color .15s ease;
  }
  .icon-btn:hover{ color:var(--blue-600); border-color:var(--blue-500); }
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
  .avatar-chip:hover{ border-color:var(--blue-500); box-shadow:var(--shadow-card); }
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

  .greeting h1{ font-family:'Baloo 2',sans-serif; font-size:26px; font-weight:700; margin:22px 0 4px; }
  .greeting p{ margin:0 0 22px; color:var(--slate-600); font-size:14.5px; font-weight:500; }

  .pending-banner{
    display:flex; gap:14px; align-items:flex-start; background:var(--amber-bg); border:1px solid var(--amber);
    border-radius:18px; padding:16px 18px; margin-bottom:22px;
  }
  .pending-banner .pb-icon{
    width:38px;height:38px;border-radius:12px; background:var(--amber); flex-shrink:0;
    display:flex;align-items:center;justify-content:center; color:#fff;
  }
  .pending-banner .pb-title{ font-weight:800; font-size:14px; color:var(--amber); margin-bottom:2px; }
  .pending-banner .pb-desc{ font-size:13px; color:var(--navy-900); font-weight:500; opacity:.9; line-height:1.5; }

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
    transition:opacity .15s ease;
  }
  .alert-cta:hover{ opacity:.9; }

  .stat-row{ display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:26px; }
  .stat-card{
    background:var(--surface); border:1px solid var(--line); border-radius:18px; padding:18px; box-shadow:var(--shadow-sm);
    transition:box-shadow .18s ease, transform .18s ease;
  }
  .stat-card:hover{ box-shadow:var(--shadow-card); transform:translateY(-2px); }
  .stat-card .val{ font-family:'Baloo 2',sans-serif; font-size:27px; font-weight:700; }
  .stat-card .lbl{ font-size:12.5px; color:var(--slate-600); font-weight:700; margin-top:2px; }

  .section-title{ font-family:'Baloo 2',sans-serif; font-size:16.5px; font-weight:700; margin:0 0 14px; }

  .nav-grid{ display:grid; grid-template-columns:repeat(3, 1fr); gap:16px; }
  .nav-card{
    background:var(--surface); border:1px solid var(--line); border-radius:20px; padding:20px;
    box-shadow:var(--shadow-sm); display:flex; flex-direction:column; gap:12px; text-decoration:none; color:inherit;
    transition:box-shadow .18s ease, transform .18s ease; position:relative;
  }
  .nav-card:not(.soon):hover{ box-shadow:var(--shadow-card); transform:translateY(-3px); }
  .nav-card .icon{ width:44px;height:44px;border-radius:13px; display:flex;align-items:center;justify-content:center; }
  .nav-card .nt{ font-family:'Baloo 2',sans-serif; font-size:16.5px; font-weight:700; }
  .nav-card .nd{ font-size:12.5px; color:var(--slate-600); font-weight:500; line-height:1.4; }
  .nav-card.soon{ opacity:.6; cursor:default; }
  .soon-pill{
    position:absolute; top:14px; right:14px; display:flex; align-items:center; gap:4px;
    background:var(--bg-0); border:1px solid var(--line); padding:3px 9px; border-radius:999px;
    font-size:10.5px; font-weight:800; color:var(--slate-600);
  }

  .icon.blue{ background:var(--sky-50); color:var(--blue-600); }
  .icon.purple{ background:var(--purple-bg); color:var(--purple); }
  .icon.teal{ background:var(--teal-bg); color:var(--teal); }
  .icon.amber{ background:var(--amber-bg); color:var(--amber); }

  a:focus-visible, button:focus-visible{ outline:2px solid var(--blue-500); outline-offset:2px; }

  @media (max-width:760px){
    .stat-row{ grid-template-columns:1fr 1fr; }
    .nav-grid{ grid-template-columns:1fr 1fr; }
  }
  @media (max-width:480px){
    .nav-grid{ grid-template-columns:1fr; }
  }
  @media (max-width:640px){
    .topbar{ flex-wrap:wrap; gap:10px; }
    .avatar-chip span.name{ display:none; }
    .logout-btn{ white-space:nowrap; }
    .alert-banner{ flex-wrap:wrap; }
    .alert-cta{ margin-left:52px; }
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
      <a href="{{ route('teacher.notifications.index') }}" class="icon-btn" aria-label="Notifications">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 8a6 6 0 0 1 12 0c0 4 1.5 5.5 1.5 5.5H4.5S6 12 6 8Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9.5 17a2.5 2.5 0 0 0 5 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        @if ($unreadNotifications > 0)
          <span class="badge-dot">{{ $unreadNotifications > 9 ? '9+' : $unreadNotifications }}</span>
        @endif
      </a>
      <a href="{{ route('teacher.profile.edit') }}" class="avatar-chip" aria-label="My Profile">
        <span class="av">{{ strtoupper(substr($user->first_name, 0, 1)) }}{{ strtoupper(substr($user->last_name, 0, 1)) }}</span>
        <span class="name">{{ $user->first_name }} {{ $user->last_name }}</span>
      </a>
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="logout-btn">Log out</button>
      </form>
    </div>
  </div>

  <div class="greeting">
    <h1>Welcome back, {{ $user->first_name }}!</h1>
    <p>Here's what's happening with your classes today.</p>
  </div>

  @if ($teacher->status === 'Pending')
    <div class="pending-banner">
      <div class="pb-icon">
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="white" stroke-width="2"/><path d="M12 8v5M12 16h.01" stroke="white" stroke-width="2" stroke-linecap="round"/></svg>
      </div>
      <div>
        <div class="pb-title">Your account is awaiting Admin approval</div>
        <div class="pb-desc">You can try AI Lesson Generation now ({{ $teacher->free_generation_credits_remaining }} free credit{{ $teacher->free_generation_credits_remaining === 1 ? '' : 's' }}) — nothing you create is visible to any student. Class Management and everything else unlocks automatically once your school verifies you.</div>
      </div>
    </div>
  @endif

  @if ($needsAttentionCount > 0)
    <div class="alert-banner">
      <div class="alert-icon">
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="white" stroke-width="2"/><path d="M12 8v5M12 16h.01" stroke="white" stroke-width="2" stroke-linecap="round"/></svg>
      </div>
      <div class="alert-body">
        <div class="title">{{ $needsAttentionCount }} learner{{ $needsAttentionCount === 1 ? '' : 's' }} need{{ $needsAttentionCount === 1 ? 's' : '' }} attention</div>
        <div class="desc">{{ $latestNeedsAttention->message }}</div>
      </div>
      <a href="{{ route('teacher.notifications.index', ['filter' => 'attention']) }}" class="alert-cta">View</a>
    </div>
  @endif

  <div class="stat-row">
    <div class="stat-card"><div class="val">{{ $classesCount }}</div><div class="lbl">Classes</div></div>
    <div class="stat-card"><div class="val">{{ $activitiesCount }}</div><div class="lbl">Activities</div></div>
    <div class="stat-card"><div class="val">{{ $approvedCount }}</div><div class="lbl">Approved</div></div>
    <div class="stat-card"><div class="val">{{ $teacher->free_generation_credits_remaining }}</div><div class="lbl">Credits</div></div>
  </div>

  <div class="section-title">Quick Access</div>
  <div class="nav-grid">
    <a href="{{ route('teacher.classes.index') }}" class="nav-card">
      <span class="icon blue"><svg width="20" height="20" viewBox="0 0 24 24" fill="none"><circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6" stroke="currentColor" stroke-width="1.8"/><circle cx="17" cy="9" r="2.4" stroke="currentColor" stroke-width="1.8"/></svg></span>
      <span class="nt">Class Management</span>
      <span class="nd">Manage classes &amp; learners</span>
    </a>
    <a href="{{ route('teacher.activities.create') }}" class="nav-card">
      <span class="icon teal"><svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M12 3l1.8 4.6L18 9l-4.2 1.8L12 15l-1.8-4.2L6 9l4.2-1.4L12 3z" fill="currentColor"/></svg></span>
      <span class="nt">Generate Activity</span>
      <span class="nd">AI-powered activity creation</span>
    </a>
    <a href="{{ route('teacher.activities.index') }}" class="nav-card">
      <span class="icon blue"><svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M4 4C4 4 7 3 9 5.5C9.6 6.3 10 7.5 10 9V19C10 19 8 17 4 17.5V4Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M20 4C20 4 17 3 15 5.5C14.4 6.3 14 7.5 14 9V19C14 19 16 17 20 17.5V4Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg></span>
      <span class="nt">My Activities</span>
      <span class="nd">Review &amp; manage activities</span>
    </a>
    <a href="{{ route('teacher.analytics.index') }}" class="nav-card">
      <span class="icon teal"><svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M4 20V10M12 20V4M20 20v-7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span>
      <span class="nt">Analytics</span>
      <span class="nd">Learner progress &amp; reports</span>
    </a>
    <a href="{{ route('teacher.promotions.index') }}" class="nav-card">
      <span class="icon purple"><svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M17 2l4 4-4 4M21 6H8M7 22l-4-4 4-4M3 18h13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
      <span class="nt">Promotions</span>
      <span class="nd">Release &amp; claim learners</span>
    </a>
    <a href="{{ route('teacher.activities.index') }}" class="nav-card">
      <span class="icon amber"><svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M4 8l1.5-4h13L20 8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><rect x="4" y="8" width="16" height="12" rx="2" stroke="currentColor" stroke-width="1.8"/></svg></span>
      <span class="nt">Repository</span>
      <span class="nd">Share an Approved activity for +2 credits</span>
    </a>
    <a href="{{ route('teacher.notifications.index') }}" class="nav-card">
      <span class="icon blue"><svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M6 8a6 6 0 0 1 12 0c0 4 1.5 5.5 1.5 5.5H4.5S6 12 6 8Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9.5 17a2.5 2.5 0 0 0 5 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></span>
      <span class="nt">Notifications</span>
      <span class="nd">{{ $unreadNotifications > 0 ? $unreadNotifications.' unread' : 'Session updates for your learners' }}</span>
    </a>
  </div>

</div>
</body>
</html>
