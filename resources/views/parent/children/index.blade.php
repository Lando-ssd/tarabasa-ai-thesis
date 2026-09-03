<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TaraBasa AI — My Children</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --sky-50:#eef6ff; --sky-100:#dcedff;
    --blue-500:#1c7ed6; --blue-600:#0f5fae; --blue-700:#0a3d73;
    --navy-900:#131f2b; --slate-600:#5b6b7a; --slate-400:#8a97a3;
    --clay-yellow:#ffcf6e; --owl-orange-600:#dd7014; --parent-teal:#1f9e83;
    --line:#e3ebf2; --surface:#ffffff; --bg-0:#f6faff;
    --success:#1f9e83; --success-bg:#e9f7f3;
    --amber:#c9820b; --amber-bg:#fef6e6;
    --shadow-sm:0 1px 2px rgba(19,31,43,0.06);
  }
  *{box-sizing:border-box;} html,body{margin:0;padding:0;}
  body{
    min-height:100vh; font-family:'Inter',sans-serif; color:var(--navy-900);
    background: radial-gradient(1100px 500px at 90% -10%, var(--sky-100), transparent 55%), var(--bg-0);
    background-attachment:fixed;
  }
  .shell{ max-width:960px; margin:0 auto; padding:22px 24px 64px; }

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
    width:30px;height:30px;border-radius:50%; background:linear-gradient(155deg,#28b895,var(--parent-teal));
    display:flex;align-items:center;justify-content:center; color:#fff; font-weight:700; font-size:13px;
  }
  .avatar-chip span.name{ font-size:13.5px; font-weight:700; }
  .logout-btn{
    background:var(--surface); border:1px solid var(--line); color:var(--slate-600); font:700 13px/1 'Inter',sans-serif;
    padding:10px 16px; border-radius:12px; cursor:pointer; box-shadow:var(--shadow-sm);
    transition:color .15s ease, border-color .15s ease;
  }
  .logout-btn:hover{ color:#d64545; border-color:#d64545; }

  h1{ font-family:'Baloo 2',sans-serif; font-size:25px; font-weight:700; margin:18px 0 6px; }
  .sub{ color:var(--slate-600); font-size:13.5px; font-weight:500; margin:0 0 22px; }

  .flash{ font-weight:700; font-size:13px; border-radius:14px; padding:12px 16px; margin-bottom:20px; background:var(--success-bg); border:1px solid var(--success); color:var(--success); }

  /* ---------- Empty state ---------- */
  .empty-hero{ background:var(--surface); border:1px solid var(--line); border-radius:22px; padding:40px 30px; text-align:center; box-shadow:var(--shadow-sm); }
  .empty-hero .big-icon{
    width:64px;height:64px;border-radius:20px; margin:0 auto 16px; background:linear-gradient(155deg,var(--clay-yellow),var(--owl-orange-600));
    display:flex;align-items:center;justify-content:center; font-size:30px;
  }
  .empty-hero h2{ font-family:'Baloo 2',sans-serif; font-size:19px; margin:0 0 6px; }
  .empty-hero p{ color:var(--slate-600); font-size:13.5px; font-weight:500; margin:0 0 24px; max-width:380px; margin-left:auto; margin-right:auto; }
  .empty-actions{ display:flex; gap:12px; justify-content:center; flex-wrap:wrap; }

  .btn-primary{
    display:inline-flex; align-items:center; gap:8px; background:linear-gradient(155deg,var(--blue-500),var(--blue-700));
    color:#fff; font:700 14px/1 'Inter',sans-serif; padding:12px 20px; border-radius:12px; border:none; cursor:pointer;
    text-decoration:none; box-shadow:0 10px 20px -8px rgba(15,95,174,.5); transition:transform .15s ease;
  }
  .btn-primary:hover{ transform:translateY(-1px); }
  .btn-ghost{
    display:inline-flex; align-items:center; gap:8px; background:var(--surface); border:1.5px solid var(--line); color:var(--slate-600);
    font:700 14px/1 'Inter',sans-serif; padding:12px 20px; border-radius:12px; cursor:pointer; text-decoration:none;
    transition:color .15s ease, border-color .15s ease;
  }
  .btn-ghost:hover{ color:var(--blue-600); border-color:var(--blue-500); }

  /* ---------- Child cards ---------- */
  .children-grid{ display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:16px; margin-bottom:24px; }
  .child-card{ background:var(--surface); border:1px solid var(--line); border-radius:20px; padding:20px; box-shadow:var(--shadow-sm); }
  .child-top{ display:flex; align-items:center; gap:12px; margin-bottom:14px; }
  .child-avatar{
    width:52px;height:52px;border-radius:16px; font-size:26px; flex-shrink:0; overflow:hidden;
    background:linear-gradient(155deg,var(--clay-yellow),var(--owl-orange-600));
    display:flex;align-items:center;justify-content:center;
  }
  .avatar-photo-img{ width:100%; height:100%; object-fit:cover; display:block; }
  .child-name{ font-family:'Baloo 2',sans-serif; font-size:17px; font-weight:700; }
  .child-grade{ font-size:12px; color:var(--slate-600); font-weight:600; }
  .child-stats{ display:flex; gap:8px; margin-bottom:12px; flex-wrap:wrap; }
  .stat-pill{ font-size:11px; font-weight:800; padding:4px 10px; border-radius:999px; background:var(--sky-50); color:var(--blue-600); }
  .stat-pill.level-beginning{ background:var(--amber-bg); color:var(--amber); }
  .stat-pill.level-developing{ background:var(--sky-50); color:var(--blue-600); }
  .stat-pill.level-proficient{ background:var(--success-bg); color:var(--success); }
  .child-meta{ font-size:12px; color:var(--slate-600); font-weight:500; line-height:1.7; }
  .child-meta .code{ font-family:'Baloo 2',sans-serif; font-weight:700; color:var(--blue-700); }
  .not-enrolled{ color:var(--slate-400); font-style:normal; }

  .footer-actions{ display:flex; gap:12px; flex-wrap:wrap; }

  .back-link{
    display:inline-flex; align-items:center; gap:6px; font-size:13.5px; font-weight:700; color:var(--slate-600);
    text-decoration:none; margin:18px 0 6px;
  }
  .back-link:hover{ color:var(--blue-600); }

  a:focus-visible, button:focus-visible{ outline:2px solid var(--blue-500); outline-offset:2px; }
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

  <a href="{{ route('parent.dashboard') }}" class="back-link">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    Dashboard
  </a>

  <h1>My Children</h1>
  <p class="sub">Keep track of each child's reading progress in one place.</p>

  @if (session('status'))
    <div class="flash">{{ session('status') }}</div>
  @endif

  @if ($learners->isEmpty())
    <div class="empty-hero">
      <div class="big-icon">🧒</div>
      <h2>Add your first child</h2>
      <p>Create a profile for your child to get their own Learner Code and PIN — no class or teacher required to get started.</p>
      <div class="empty-actions">
        <a href="{{ route('parent.children.create') }}" class="btn-primary">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/></svg>
          Add Your Child
        </a>
        <a href="{{ route('parent.children.link') }}" class="btn-ghost">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6" stroke="currentColor" stroke-width="1.8"/><path d="M17 8l3 3-3 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Link an existing child's account
        </a>
      </div>
    </div>
  @else
    <div class="children-grid">
      @foreach ($learners as $learner)
        <div class="child-card">
          <div class="child-top">
            <div class="child-avatar">
              @if ($learner->avatar_photo_path)
                <img src="{{ Storage::url($learner->avatar_photo_path) }}" alt="{{ $learner->first_name }}" class="avatar-photo-img">
              @else
                {{ $learner->avatar_id }}
              @endif
            </div>
            <div>
              <div class="child-name">{{ $learner->first_name }}</div>
              <div class="child-grade">{{ $learner->grade_level }}</div>
            </div>
          </div>
          <div class="child-stats">
            <span class="stat-pill level-{{ strtolower($learner->mastery_level ?? '') }}">{{ $learner->mastery_level ?? 'Not yet assessed' }}</span>
            <span class="stat-pill">⭐ {{ $learner->points }} pts</span>
            <span class="stat-pill">🔥 {{ $learner->streak }}</span>
            @if ($learner->learning_style)
              <span class="stat-pill">{{ $learner->learning_style }}</span>
            @endif
          </div>
          <div class="child-meta">
            Learner Code: <span class="code">{{ $learner->learner_code }}</span><br>
            @if ($learner->schoolClass)
              {{ $learner->schoolClass->name }} — {{ $learner->schoolClass->grade_level }}
            @else
              <span class="not-enrolled">Not enrolled in a class yet</span>
            @endif
          </div>
        </div>
      @endforeach
    </div>

    <div class="footer-actions">
      <a href="{{ route('parent.children.create') }}" class="btn-primary">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/></svg>
        Add another child
      </a>
      <a href="{{ route('parent.children.link') }}" class="btn-ghost">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6" stroke="currentColor" stroke-width="1.8"/><path d="M17 8l3 3-3 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Link an existing child's account
      </a>
    </div>
  @endif

</div>
</body>
</html>
