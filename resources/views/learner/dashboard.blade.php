<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Dashboard — TaraBasa AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --sky-100:#dcedff; --blue-500:#1c7ed6; --blue-600:#1567bf; --blue-700:#0a3d73;
    --navy-900:#131f2b; --slate-600:#5b6b7a; --slate-400:#8b98a6;
    --owl-orange-500:#ef8d2a; --owl-orange-600:#dd7014; --clay-yellow:#ffcf6e;
    --teal:#2bb89c; --teal-700:#0f6e5c; --purple:#8b5fd6;
    --line:#e3ebf2; --surface:#ffffff; --bg-0:#f6faff;
    --success:#1f9e83; --success-bg:#e9f7f3; --shadow-sm:0 6px 16px -10px rgba(19,60,110,0.18);
  }
  *{ box-sizing:border-box; }
  html,body{ margin:0; padding:0; }
  body{
    min-height:100vh; font-family:'Inter',sans-serif; color:var(--navy-900);
    background: radial-gradient(1200px 700px at 90% -10%, var(--sky-100), transparent 55%),
                radial-gradient(900px 600px at 0% 100%, #ffe9c9, transparent 50%), var(--bg-0);
    background-attachment:fixed;
  }
  .page{ max-width:1180px; margin:0 auto; padding:28px 24px 60px; }

  /* ---- Profile header ---- */
  .profile-header{ display:flex; align-items:center; gap:18px; margin-bottom:22px; flex-wrap:wrap; }
  .avatar{
    width:76px; height:76px; border-radius:24px; flex-shrink:0; overflow:hidden;
    background:linear-gradient(155deg, var(--owl-orange-500), var(--owl-orange-600));
    display:flex; align-items:center; justify-content:center; color:#fff;
    font-family:'Baloo 2',sans-serif; font-size:15px; font-weight:800; text-align:center; padding:4px;
    box-shadow:0 14px 26px -14px rgba(221,112,20,0.55);
  }
  .avatar img{ width:100%; height:100%; object-fit:cover; }
  .profile-text{ flex:1; min-width:200px; }
  .profile-text h1{ font-family:'Baloo 2',sans-serif; font-size:32px; margin:0 0 4px; line-height:1.15; }
  .profile-text .grade-line{ font-size:17px; font-weight:700; color:var(--slate-600); margin:0; }
  .switch-form button{
    background:none; border:none; font-size:15px; font-weight:700; color:var(--slate-600);
    text-decoration:underline; cursor:pointer; padding:8px;
  }
  .switch-form button:hover{ color:var(--blue-600); }

  /* ---- Stats ---- */
  .stat-row{ display:flex; gap:16px; margin-bottom:22px; flex-wrap:wrap; }
  .stat-card{
    flex:1; min-width:140px; background:var(--surface); border:1px solid var(--line); border-radius:20px;
    padding:20px 14px; text-align:center; box-shadow:var(--shadow-sm);
  }
  .stat-card .v{ font-family:'Baloo 2',sans-serif; font-size:30px; font-weight:800; }
  .stat-card .v.level{ color:var(--teal-700); font-size:22px; }
  .stat-card .l{ font-size:14px; font-weight:700; color:var(--slate-600); text-transform:uppercase; letter-spacing:.03em; margin-top:6px; }

  /* ---- Hero ---- */
  .hero-tile{
    position:relative; overflow:hidden; margin-bottom:16px;
    background:linear-gradient(150deg, #eaf6ff 0%, #d9f0ea 100%);
    border:1.5px solid var(--sky-100); border-radius:30px; padding:34px 30px;
    display:flex; flex-direction:column; box-shadow:0 20px 40px -24px rgba(19,60,110,0.25);
  }
  .hero-blob{ position:absolute; border-radius:50%; pointer-events:none; }
  .hero-blob.b1{ width:220px;height:220px; top:-90px; right:-70px; background:radial-gradient(circle, rgba(255,207,110,0.4), transparent 70%); }
  .hero-blob.b2{ width:180px;height:180px; bottom:-60px; left:-60px; background:radial-gradient(circle, rgba(43,184,156,0.25), transparent 70%); }
  .hero-mascot{ width:130px; height:130px; margin-bottom:16px; position:relative; z-index:1; filter:drop-shadow(0 10px 16px rgba(19,60,110,0.18)); }
  .hero-tag{
    position:relative; z-index:1; display:inline-flex; align-self:flex-start; align-items:center; gap:6px;
    font-size:13px; font-weight:800; letter-spacing:.05em; text-transform:uppercase; color:var(--teal-700);
    background:rgba(255,255,255,0.75); padding:6px 14px; border-radius:999px; margin-bottom:12px;
  }
  .hero-tile h2{ position:relative; z-index:1; font-family:'Baloo 2',sans-serif; font-size:30px; margin:0 0 10px; line-height:1.2; }
  .hero-tile p{ position:relative; z-index:1; font-size:18px; color:var(--slate-600); font-weight:600; line-height:1.5; margin:0 0 22px; max-width:520px; }
  .hero-cta{
    position:relative; z-index:1; align-self:flex-start; padding:18px 32px; border:none; border-radius:18px;
    font:800 18px/1 'Baloo 2',sans-serif; cursor:pointer; text-decoration:none; display:inline-block;
    background:linear-gradient(155deg, var(--blue-500), var(--blue-700)); color:#fff;
    box-shadow:0 16px 26px -12px rgba(15,95,174,0.5); transition:transform .2s cubic-bezier(.34,1.56,.64,1);
  }
  .hero-cta:hover{ transform:translateY(-2px) scale(1.02); }

  .games-btn{
    display:flex; align-items:center; gap:16px; margin-bottom:26px; padding:20px 26px; border-radius:24px;
    background:linear-gradient(155deg, var(--clay-yellow), var(--owl-orange-600)); color:#fff; text-decoration:none;
    box-shadow:0 16px 28px -14px rgba(221,112,20,0.5); transition:transform .2s cubic-bezier(.34,1.56,.64,1);
  }
  .games-btn:hover{ transform:translateY(-2px) scale(1.01); }
  .games-btn .icon{ width:52px;height:52px;border-radius:16px; background:rgba(255,255,255,0.3); display:flex;align-items:center;justify-content:center; flex-shrink:0; }
  .games-btn h3{ font-family:'Baloo 2',sans-serif; font-size:22px; margin:0 0 2px; }
  .games-btn p{ font-size:15px; font-weight:600; opacity:.95; margin:0; }

  /* ---- Panel sections ---- */
  .panel{ margin-bottom:26px; }
  .panel-title{ font-family:'Baloo 2',sans-serif; font-size:24px; margin:0 0 14px; display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
  .panel-title .see-count{ font-size:16px; font-weight:700; color:var(--slate-600); }
  .panel-card{ background:var(--surface); border:1px solid var(--line); border-radius:26px; padding:26px 24px; box-shadow:var(--shadow-sm); }

  .pair-row{ display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:26px; }
  @media (max-width:820px){ .pair-row{ grid-template-columns:1fr; } }

  /* ---- Journey ---- */
  .journey-row{ padding:18px 0; border-bottom:1px solid var(--line); }
  .journey-row:last-child{ border-bottom:none; padding-bottom:0; }
  .journey-row:first-child{ padding-top:0; }
  .journey-label{ display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:10px; }
  .journey-label strong{ font-family:'Baloo 2',sans-serif; font-size:19px; }
  .journey-word{ font-size:15px; font-weight:700; color:var(--teal-700); }
  .up-next-badge{
    font-size:13px; font-weight:800; color:var(--owl-orange-600); background:#fff3e0;
    padding:4px 12px; border-radius:999px;
  }
  .journey-track{ width:100%; max-width:640px; height:auto; display:block; }
  .track-grey{ fill:none; stroke:var(--line); stroke-width:5; }
  .track-colored{ fill:none; stroke:var(--teal); stroke-width:5; }
  .track-locked{ fill:none; stroke:var(--line); stroke-width:4; }
  .checkpoint-done{ fill:var(--teal); }
  .checkpoint-todo{ fill:var(--surface); stroke:var(--line); stroke-width:3; }
  .checkpoint-locked{ fill:var(--bg-0); stroke:var(--line); stroke-width:3; stroke-dasharray:3 3; }
  .checkmark{ font-size:11px; fill:#fff; text-anchor:middle; font-weight:800; }
  .marker-circle{ fill:var(--surface); stroke:var(--owl-orange-500); stroke-width:3; }
  .marker-emoji{ font-size:20px; text-anchor:middle; }
  .journey-empty{ font-size:17px; font-weight:600; color:var(--slate-600); text-align:center; padding:20px 0; }

  /* ---- Goal ---- */
  .goal-panel.met .panel-card{ background:linear-gradient(150deg,#fff3c4,#f0b03e); border-color:transparent; }
  .goal-icon{ font-size:40px; line-height:1; margin-bottom:10px; text-align:center; }
  .goal-count{ font-family:'Baloo 2',sans-serif; font-size:28px; font-weight:800; margin:0 0 4px; text-align:center; }
  .goal-panel.met .goal-count{ color:#5a3d00; }
  .goal-sub{ font-size:15px; font-weight:600; color:var(--slate-600); margin:0 0 18px; text-align:center; }
  .goal-panel.met .goal-sub{ color:#7a5400; }
  .goal-track{ height:20px; border-radius:999px; background:var(--bg-0); overflow:hidden; }
  .goal-panel.met .goal-track{ background:rgba(255,255,255,0.5); }
  .goal-fill{ height:100%; border-radius:999px; background:linear-gradient(90deg, var(--teal), var(--blue-500)); }
  .goal-panel.met .goal-fill{ background:linear-gradient(90deg,#f0b03e,#dd7014); }
  .goal-note{ font-size:17px; font-weight:700; margin-top:16px; text-align:center; }
  .goal-panel:not(.met) .goal-note{ color:var(--teal-700); }
  .goal-panel.met .goal-note{ color:#5a3d00; }

  /* ---- Growth ---- */
  .growth-total{ font-size:16px; font-weight:700; color:var(--slate-600); margin:0 0 18px; text-align:center; }
  .growth-total strong{ color:var(--teal-700); font-family:'Baloo 2',sans-serif; font-size:19px; }
  .growth-chart{ display:flex; align-items:flex-end; gap:10px; height:130px; }
  .growth-day{ flex:1; display:flex; flex-direction:column; align-items:center; justify-content:flex-end; height:100%; }
  .growth-bar-track{ flex:1; width:100%; display:flex; align-items:flex-end; }
  .growth-bar{ width:100%; border-radius:8px 8px 3px 3px; background:linear-gradient(180deg, var(--sky-100), var(--blue-500)); min-height:5px; }
  .growth-day.today .growth-bar{ background:linear-gradient(180deg, var(--clay-yellow), var(--owl-orange-600)); }
  .growth-count{ font-size:14px; font-weight:800; color:var(--slate-600); margin:6px 0 6px; }
  .growth-label{ font-size:13px; font-weight:700; color:var(--slate-400); text-transform:uppercase; letter-spacing:.03em; }
  .growth-day.today .growth-label{ color:var(--owl-orange-600); }

  /* ---- Badges ---- */
  .badge-grid{ display:grid; grid-template-columns:repeat(2,1fr); gap:18px; }
  @media (min-width:640px){ .badge-grid{ grid-template-columns:repeat(3,1fr); } }
  @media (min-width:960px){ .badge-grid{ grid-template-columns:repeat(6,1fr); } }
  .badge-tile{ border-radius:24px; padding:22px 14px; text-align:center; }
  .badge-tile.earned{ background:linear-gradient(155deg,#fff3c4,#f0b03e); box-shadow:0 14px 24px -14px rgba(240,176,62,0.55); }
  .badge-tile.locked{ background:var(--bg-0); border:2px dashed var(--line); }
  .badge-tile-icon{ font-size:52px; line-height:1; margin-bottom:12px; }
  .badge-tile.locked .badge-tile-icon{ filter:grayscale(1); opacity:.45; }
  .badge-tile-name{ font-family:'Baloo 2',sans-serif; font-size:16px; font-weight:800; margin:0 0 6px; }
  .badge-tile.earned .badge-tile-name{ color:#5a3d00; }
  .badge-tile.locked .badge-tile-name{ color:var(--slate-600); }
  .badge-tile-desc{ font-size:13px; font-weight:600; line-height:1.4; margin:0 0 10px; }
  .badge-tile.earned .badge-tile-desc{ color:#7a5400; }
  .badge-tile.locked .badge-tile-desc{ color:var(--slate-600); opacity:.85; }
  .badge-tile-date{ font-size:12.5px; font-weight:800; color:#5a3d00; margin:0; }
  .badge-tile-locked-label{ font-size:12.5px; font-weight:800; color:var(--slate-600); margin:0; }

  /* ---- Bookshelf ---- */
  .book-list{ display:flex; flex-direction:column; gap:16px; }
  @media (min-width:760px){ .book-list{ display:grid; grid-template-columns:1fr 1fr; gap:16px; } }
  .book-card{
    display:flex; align-items:center; gap:16px; background:var(--bg-0); border:1px solid var(--line);
    border-radius:22px; padding:18px 20px;
  }
  .book-icon{
    width:58px;height:58px;border-radius:16px; flex-shrink:0; font-size:28px;
    background:linear-gradient(155deg, var(--sky-100), var(--blue-500)); color:#fff;
    display:flex;align-items:center;justify-content:center;
  }
  .book-info{ flex:1; min-width:0; }
  .book-title{ font-family:'Baloo 2',sans-serif; font-size:18px; font-weight:700; margin:0 0 4px; }
  .book-meta{ font-size:14.5px; font-weight:600; color:var(--slate-600); line-height:1.4; }
  .book-meta strong{ color:var(--teal); }
  .reread-btn{
    flex-shrink:0; display:inline-flex; align-items:center; gap:6px; font:800 15px/1 'Baloo 2',sans-serif;
    color:#fff; background:linear-gradient(155deg, var(--blue-500), var(--blue-700)); padding:13px 18px;
    border-radius:14px; text-decoration:none; transition:transform .15s ease;
  }
  .reread-btn:hover{ transform:translateY(-1px); }
  .empty-note{ font-size:16px; color:var(--slate-600); font-weight:600; line-height:1.5; }

  @media (max-width:520px){
    .profile-text h1{ font-size:26px; }
    .hero-tile h2{ font-size:24px; }
    .stat-card .v{ font-size:24px; }
  }
</style>
</head>
<body>
<div class="page">

  <div class="profile-header">
    <div class="avatar">
      @if ($learner->avatar_photo_path)
        <img src="{{ Storage::url($learner->avatar_photo_path) }}" alt="{{ $learner->first_name }}">
      @else
        {{ $learner->avatar_id }}
      @endif
    </div>
    <div class="profile-text">
      <h1>Hi, {{ $learner->first_name }}!</h1>
      <p class="grade-line">{{ $learner->grade_level }}</p>
    </div>
    <form method="POST" action="{{ route('learner.logout') }}" class="switch-form">
      @csrf
      <button type="submit">Not you? Switch learner</button>
    </form>
  </div>

  <div class="stat-row">
    <div class="stat-card"><div class="v level">{{ $learner->mastery_level ?? 'New' }}</div><div class="l">Level</div></div>
    <div class="stat-card"><div class="v">{{ $learner->points }}</div><div class="l">Points</div></div>
    <div class="stat-card"><div class="v">🔥 {{ $learner->streak }}</div><div class="l">Streak</div></div>
  </div>

  <div class="hero-tile">
    <div class="hero-blob b1"></div>
    <div class="hero-blob b2"></div>
    <div class="hero-mascot">@include('learner.games._owl-mascot', ['id' => 'heroOwl'])</div>
    @if ($upNextCompetency)
      <span class="hero-tag">🎯 Picked just for you</span>
      <h2>Time to work on {{ $upNextCompetency['label'] }}!</h2>
      <p>TaraBasa picked today's story because of how your last few readings went — {{ strtolower($upNextCompetency['difficultyWord'] ?? 'just right') }} for you right now.</p>
    @else
      <span class="hero-tag">📖 Ready when you are</span>
      <h2>Ready to read, {{ $learner->first_name }}?</h2>
      <p>Tap below and let's find your next story!</p>
    @endif
    <a href="{{ route('learner.activity.find') }}" class="hero-cta">Start Reading Activity</a>
  </div>

  <a href="{{ route('learner.games.index') }}" class="games-btn">
    <div class="icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none"><rect x="3" y="7" width="18" height="11" rx="4" fill="white"/><circle cx="8" cy="12.5" r="1.4" fill="#dd7014"/><circle cx="16" cy="12.5" r="1.4" fill="#dd7014"/></svg></div>
    <div><h3>🎮 Practice Games</h3><p>Free play — no points, just for fun</p></div>
  </a>

  <div class="panel">
    <h2 class="panel-title">How I'm Growing 🌱</h2>
    <div class="panel-card">
      @if (! $hasJourneyData)
        <p class="journey-empty">Your growth path fills in once you complete your first reading check! 🌱</p>
      @else
        @foreach ($journeyRows as $row)
          <div class="journey-row">
            <div class="journey-label">
              <strong>{{ $row['label'] }}</strong>
              @if ($row['track'])
                <span class="journey-word">{{ $row['difficultyWord'] ?? 'Just Right' }}</span>
              @else
                <span class="journey-word" style="color:var(--slate-400)">Not started yet</span>
              @endif
              @if ($row['isUpNext'])
                <span class="up-next-badge">⭐ Up Next</span>
              @endif
            </div>
            <svg class="journey-track" viewBox="0 -4 500 78" preserveAspectRatio="xMidYMid meet">
              @if ($row['track'])
                <polyline points="{{ $row['track']['greyPolyline'] }}" class="track-grey" />
                <polyline points="{{ $row['track']['coloredPolyline'] }}" class="track-colored" />
                @foreach ($row['track']['points'] as $i => $p)
                  <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="10" class="{{ $row['track']['checkpointsDone'][$i] ? 'checkpoint-done' : 'checkpoint-todo' }}" />
                  @if ($row['track']['checkpointsDone'][$i])
                    <text x="{{ $p['x'] }}" y="{{ $p['y'] + 4 }}" class="checkmark">✓</text>
                  @endif
                @endforeach
                <circle cx="{{ $row['track']['marker']['x'] }}" cy="{{ $row['track']['marker']['y'] }}" r="18" class="marker-circle" />
                <text x="{{ $row['track']['marker']['x'] }}" y="{{ $row['track']['marker']['y'] + 6 }}" class="marker-emoji">🦉</text>
              @else
                <polyline points="20,50 140,18 260,50 380,18 480,50" class="track-locked" stroke-dasharray="6 6" />
                @foreach ([[20,50],[140,18],[260,50],[380,18],[480,50]] as $p)
                  <circle cx="{{ $p[0] }}" cy="{{ $p[1] }}" r="10" class="checkpoint-locked" />
                @endforeach
              @endif
            </svg>
          </div>
        @endforeach
      @endif
    </div>
  </div>

  <div class="pair-row">
    <div class="panel goal-panel {{ $weeklyMet ? 'met' : '' }}" style="margin-bottom:0;">
      <h2 class="panel-title">This Week's Goal</h2>
      <div class="panel-card">
        <div class="goal-icon">{{ $weeklyMet ? '🏆' : '🎯' }}</div>
        @if ($weeklyMet)
          <p class="goal-count">{{ $weeklyCount }} real readings!</p>
          <p class="goal-sub">your goal was {{ $weeklyTarget }} this week</p>
        @else
          <p class="goal-count">{{ $weeklyCount }} of {{ $weeklyTarget }}</p>
          <p class="goal-sub">real reading activities this week</p>
        @endif
        <div class="goal-track"><div class="goal-fill" style="width:{{ $weeklyPercent }}%"></div></div>
        @if ($weeklyMet)
          <p class="goal-note">You hit your goal this week — amazing job! 🎉</p>
        @else
          <p class="goal-note">{{ $weeklyTarget - $weeklyCount }} more {{ ($weeklyTarget - $weeklyCount) === 1 ? 'reading' : 'readings' }} to go!</p>
        @endif
      </div>
    </div>

    <div class="panel" style="margin-bottom:0;">
      <h2 class="panel-title">My Growth</h2>
      <div class="panel-card">
        <p class="growth-total">You've read <strong>{{ $weeklyCount }}</strong> {{ $weeklyCount === 1 ? 'story' : 'stories' }} this week</p>
        <div class="growth-chart">
          @foreach ($growthDays as $day)
            <div class="growth-day {{ $day['isToday'] ? 'today' : '' }}">
              <div class="growth-count">{{ $day['count'] > 0 ? $day['count'] : '' }}</div>
              <div class="growth-bar-track">
                <div class="growth-bar" style="height:{{ $day['count'] > 0 ? max(8, round($day['count'] / $growthMax * 100)) : 4 }}%"></div>
              </div>
              <div class="growth-label">{{ $day['label'] }}</div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>

  <div class="panel">
    <h2 class="panel-title">My Badges <span class="see-count">— {{ $earnedBadgeCount }} of {{ $totalBadgeCount }}</span></h2>
    <div class="badge-grid">
      @foreach ($badges as $badge)
        <div class="badge-tile {{ $badge['earned'] ? 'earned' : 'locked' }}">
          <div class="badge-tile-icon">{{ $badge['emoji'] }}</div>
          <p class="badge-tile-name">{{ $badge['name'] }}</p>
          <p class="badge-tile-desc">{{ $badge['description'] }}</p>
          @if ($badge['earned'])
            <p class="badge-tile-date">Earned {{ $badge['earnedAt']->format('M j, Y') }}</p>
          @else
            <p class="badge-tile-locked-label">🔒 Not yet</p>
          @endif
        </div>
      @endforeach
    </div>
  </div>

  <div class="panel">
    <h2 class="panel-title">My Bookshelf</h2>
    @if ($books->isEmpty())
      <p class="empty-note">Nothing here yet — finish a reading activity and it'll show up on your shelf!</p>
    @else
      <div class="book-list">
        @foreach ($books as $book)
          <div class="book-card">
            <div class="book-icon">📖</div>
            <div class="book-info">
              <p class="book-title">{{ $book['activity']->title }}</p>
              <p class="book-meta">
                Read {{ $book['timesRead'] }} {{ $book['timesRead'] === 1 ? 'time' : 'times' }} ·
                Best: <strong>{{ round($book['bestAccuracy']) }}%</strong> ·
                {{ $book['mostRecentDate']->format('M j') }}
              </p>
            </div>
            <a href="{{ route('learner.bookshelf.reread', $book['activity']) }}" class="reread-btn">Read Again</a>
          </div>
        @endforeach
      </div>
    @endif
  </div>

</div>
</body>
</html>
