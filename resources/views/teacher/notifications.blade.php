<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TaraBasa AI — Notifications</title>
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
    --danger:#d64545; --danger-bg:#fdecec; --danger-line:#f3c9c9;
    --success:#1f9e83; --success-bg:#e9f7f3;
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
  .shell{ max-width:720px; margin:0 auto; padding:22px 24px 64px; }

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

  .head-row{ display:flex; align-items:center; justify-content:space-between; margin-bottom:4px; flex-wrap:wrap; gap:12px; }
  h1{ font-family:'Baloo 2',sans-serif; font-size:25px; font-weight:700; margin:0; }

  .sub-row{ display:flex; align-items:center; justify-content:space-between; margin:6px 0 18px; flex-wrap:wrap; gap:10px; }
  .sub{ color:var(--slate-600); font-size:13.5px; font-weight:500; margin:0; }
  .mark-all{ font-size:12.5px; font-weight:700; color:var(--blue-600); background:none; border:none; cursor:pointer; padding:0; }
  .mark-all:hover{ text-decoration:underline; }

  .filters{ display:flex; gap:9px; margin-bottom:22px; flex-wrap:wrap; }
  .filter-chip{
    padding:8px 15px; border-radius:999px; border:1.5px solid var(--line); background:var(--surface);
    font-size:13px; font-weight:700; color:var(--slate-600); cursor:pointer; text-decoration:none;
    transition:border-color .15s ease, color .15s ease, background .15s ease;
  }
  .filter-chip.active{ border-color:var(--blue-500); background:var(--sky-50); color:var(--blue-600); }

  .day-label{ font-size:11.5px; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:var(--slate-400); margin:22px 0 10px; }
  .day-label:first-of-type{ margin-top:4px; }

  .notif-list{ display:flex; flex-direction:column; gap:10px; }
  .notif-item{
    width:100%; text-align:left; display:flex; gap:13px; background:var(--surface); border:1.5px solid var(--line); border-radius:18px;
    padding:16px 18px; box-shadow:var(--shadow-sm); position:relative; font-family:inherit; cursor:default;
    transition:box-shadow .18s ease, transform .18s ease;
  }
  button.notif-item{ cursor:pointer; }
  button.notif-item:hover{ box-shadow:var(--shadow-card); transform:translateY(-2px); }
  .notif-item.unread{ background:var(--sky-50); border-color:var(--sky-100); }
  .notif-item.unread.flagged{ background:var(--danger-bg); border-color:var(--danger-line); }
  .notif-icon{ width:42px;height:42px;border-radius:13px; flex-shrink:0; display:flex;align-items:center;justify-content:center; color:#fff; box-shadow:0 8px 16px -8px rgba(0,0,0,0.35); }
  .notif-icon.attention{ background:linear-gradient(155deg,#ff6b6b,var(--danger)); }
  .notif-icon.summary{ background:linear-gradient(155deg,#3fd4b0,var(--success)); }
  .notif-icon.level{ background:linear-gradient(155deg,#ffcf6e,var(--owl-orange-600)); }
  .notif-body{ flex:1; min-width:0; }
  .notif-body .title{ font-size:14px; font-weight:700; margin-bottom:3px; }
  .notif-item:not(.unread) .notif-body .title{ font-weight:600; color:var(--slate-600); }
  .notif-body .desc{ font-size:13px; color:var(--slate-600); font-weight:500; line-height:1.45; }
  .notif-meta{ display:flex; flex-direction:column; align-items:flex-end; gap:8px; flex-shrink:0; }
  .notif-time{ font-size:11.5px; color:var(--slate-400); font-weight:600; white-space:nowrap; }
  .unread-pill{ font-size:10px; font-weight:800; color:#fff; background:var(--blue-500); padding:2px 8px; border-radius:999px; }
  .notif-item.unread.flagged .unread-pill{ background:var(--danger); }

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
        <span class="av">{{ strtoupper(substr($user->first_name, 0, 1)) }}</span>
        <span class="name">{{ $user->first_name }}</span>
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

  <div class="head-row">
    <h1>Notifications</h1>
  </div>
  <div class="sub-row">
    <p class="sub">Session results for your learners.</p>
    @if ($unreadCount > 0)
      <form method="POST" action="{{ route('notifications.mark-all-read') }}">
        @csrf
        <button type="submit" class="mark-all">Mark all as read</button>
      </form>
    @endif
  </div>

  <div class="filters">
    <a href="{{ route('teacher.notifications.index', ['filter' => 'all']) }}" class="filter-chip {{ $filter === 'all' ? 'active' : '' }}">All</a>
    <a href="{{ route('teacher.notifications.index', ['filter' => 'attention']) }}" class="filter-chip {{ $filter === 'attention' ? 'active' : '' }}">Needs Attention</a>
    <a href="{{ route('teacher.notifications.index', ['filter' => 'routine']) }}" class="filter-chip {{ $filter === 'routine' ? 'active' : '' }}">Session Summaries</a>
  </div>

  @if ($groups->isEmpty())
    <div class="empty-note">No notifications yet — session summaries for your learners will show up here.</div>
  @else
    @foreach ($groups as $label => $notifications)
      <div class="day-label">{{ $label }}</div>
      <div class="notif-list">
        @foreach ($notifications as $notification)
          @php
            $isAttention = $notification->type === \App\Models\Notification::TYPE_NEEDS_ATTENTION;
            $iconClass = $isAttention ? 'attention' : ($notification->type === \App\Models\Notification::TYPE_LEVEL_CONFIRMED ? 'level' : 'summary');
          @endphp
          @if (! $notification->is_read)
            <form method="POST" action="{{ route('notifications.read', $notification) }}">
              @csrf
              <button type="submit" class="notif-item unread {{ $isAttention ? 'flagged' : '' }}">
                <span class="notif-icon {{ $iconClass }}">
                  @if ($isAttention)
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="white" stroke-width="2"/><path d="M12 8v5M12 16h.01" stroke="white" stroke-width="2" stroke-linecap="round"/></svg>
                  @elseif ($notification->type === \App\Models\Notification::TYPE_LEVEL_CONFIRMED)
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 2l2.6 5.3 5.8.8-4.2 4.1 1 5.8L12 15l-5.2 2.8 1-5.8L3.6 8l5.8-.8L12 2z" fill="white"/></svg>
                  @else
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="white" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  @endif
                </span>
                <span class="notif-body">
                  <span class="title">{{ $notification->type }}</span>
                  <span class="desc">{{ $notification->message }}</span>
                </span>
                <span class="notif-meta">
                  <span class="unread-pill">New</span>
                  <span class="notif-time">{{ $notification->timestamp->format('g:i A') }}</span>
                </span>
              </button>
            </form>
          @else
            <div class="notif-item">
              <span class="notif-icon {{ $iconClass }}">
                @if ($isAttention)
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="white" stroke-width="2"/><path d="M12 8v5M12 16h.01" stroke="white" stroke-width="2" stroke-linecap="round"/></svg>
                @elseif ($notification->type === \App\Models\Notification::TYPE_LEVEL_CONFIRMED)
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 2l2.6 5.3 5.8.8-4.2 4.1 1 5.8L12 15l-5.2 2.8 1-5.8L3.6 8l5.8-.8L12 2z" fill="white"/></svg>
                @else
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="white" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                @endif
              </span>
              <span class="notif-body">
                <span class="title">{{ $notification->type }}</span>
                <span class="desc">{{ $notification->message }}</span>
              </span>
              <span class="notif-meta">
                <span class="notif-time">{{ $notification->timestamp->format('g:i A') }}</span>
              </span>
            </div>
          @endif
        @endforeach
      </div>
    @endforeach
  @endif

</div>
</body>
</html>
