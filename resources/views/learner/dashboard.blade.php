@extends('layouts.learner-app')

@section('title', 'My Dashboard — TaraBasa AI')

@section('content')
<style>
  .stat-row{ display:flex; gap:12px; margin-bottom:18px; }
  .stat-card{
    flex:1; background:var(--surface); border:1px solid var(--line); border-radius:16px;
    padding:14px 10px; text-align:center; box-shadow:var(--shadow-sm);
  }
  .stat-card .v{ font-family:'Baloo 2',sans-serif; font-size:20px; font-weight:800; }
  .stat-card .v.level{ color:var(--teal-700); font-size:16px; }
  .stat-card .l{ font-size:10.5px; font-weight:700; color:var(--slate-600); text-transform:uppercase; letter-spacing:.03em; margin-top:3px; }

  /*
   * Bento grid — the deliberate opposite of the old uniform stack. Two
   * columns × two rows on desktop, with the hero tile spanning both rows
   * of column 1 (the console-"Continue"-tile concept: the one thing your
   * eye lands on first). Collapses to a single column, hero first, on
   * narrow screens.
   */
  .bento{ display:grid; grid-template-columns: 1.3fr 1fr; gap:16px; }
  @media (max-width:820px){ .bento{ grid-template-columns:1fr; } }
  .bento-side{ display:grid; grid-template-columns:1fr 1fr; gap:16px; align-content:start; }
  @media (max-width:820px){ .bento-side{ grid-template-columns:1fr 1fr; } }

  .hero-tile{
    position:relative; overflow:hidden;
    background:linear-gradient(150deg, #eaf6ff 0%, #d9f0ea 100%);
    border:1.5px solid var(--sky-100); border-radius:26px; padding:26px 24px;
    display:flex; flex-direction:column; box-shadow:0 20px 40px -24px rgba(19,60,110,0.25);
    min-height:0;
  }
  .hero-blob{ position:absolute; border-radius:50%; pointer-events:none; }
  .hero-blob.b1{ width:170px;height:170px; top:-70px; right:-60px; background:radial-gradient(circle, rgba(255,207,110,0.4), transparent 70%); }
  .hero-blob.b2{ width:140px;height:140px; bottom:-50px; left:-50px; background:radial-gradient(circle, rgba(43,184,156,0.25), transparent 70%); }
  .hero-mascot{
    width:96px; height:96px; margin-bottom:14px; position:relative; z-index:1;
    filter:drop-shadow(0 10px 16px rgba(19,60,110,0.18));
  }
  .hero-tag{
    position:relative; z-index:1; display:inline-flex; align-self:flex-start; align-items:center; gap:5px;
    font-size:10.5px; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:var(--teal-700);
    background:rgba(255,255,255,0.7); padding:4px 11px; border-radius:999px; margin-bottom:10px;
  }
  .hero-tile h3{ position:relative; z-index:1; font-family:'Baloo 2',sans-serif; font-size:21px; margin:0 0 8px; line-height:1.25; }
  @media (min-width:1024px){ .hero-tile h3{ font-size:24px; } }
  .hero-tile p{ position:relative; z-index:1; font-size:14px; color:var(--slate-600); font-weight:600; line-height:1.5; margin:0 0 20px; max-width:340px; }
  .hero-cta{
    position:relative; z-index:1; align-self:flex-start; margin-top:auto; padding:15px 26px; border:none; border-radius:16px;
    font:800 15px/1 'Baloo 2',sans-serif; cursor:pointer; text-decoration:none; display:inline-block;
    background:linear-gradient(155deg, var(--blue-500), var(--blue-700)); color:#fff;
    box-shadow:0 16px 26px -12px rgba(15,95,174,0.5); transition:transform .2s cubic-bezier(.34,1.56,.64,1);
  }
  .hero-cta:hover{ transform:translateY(-2px) scale(1.02); }

  .tile{
    background:var(--surface); border:1px solid var(--line); border-radius:20px; padding:16px 18px;
    box-shadow:var(--shadow-sm); text-decoration:none; color:var(--navy-900); display:flex; flex-direction:column;
  }
  .tile-games{
    display:flex; flex-direction:row; align-items:center; gap:12px;
    background:linear-gradient(155deg, var(--clay-yellow), var(--owl-orange-600)); border:none; color:#fff;
    box-shadow:0 14px 24px -12px rgba(221,112,20,0.45);
  }
  .tile-games h4{ font-family:'Baloo 2',sans-serif; font-size:14.5px; margin:0 0 2px; }
  .tile-games p{ font-size:11.5px; font-weight:600; opacity:.92; margin:0; }

  .tile .head{ display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; }
  .tile h4{ font-family:'Baloo 2',sans-serif; font-size:13px; margin:0; }
  .tile .see-all{ font-size:10.5px; font-weight:800; color:var(--blue-600); }

  .badge-strip{ display:flex; gap:6px; }
  .mini-badge{ width:32px;height:32px;border-radius:10px; font-size:16px; display:flex;align-items:center;justify-content:center; }
  .mini-badge.earned{ background:linear-gradient(155deg,var(--clay-yellow),var(--owl-orange-600)); }
  .mini-badge.locked{ background:var(--bg-0); border:1.5px dashed var(--line); filter:grayscale(1); opacity:.6; }
  .badge-count-note{ font-size:11px; font-weight:700; color:var(--slate-600); margin-top:8px; }

  .mini-track{ height:8px; border-radius:999px; background:var(--bg-0); overflow:hidden; margin-bottom:8px; }
  .mini-fill{ height:100%; border-radius:999px; background:linear-gradient(90deg, var(--teal), var(--blue-500)); }
  .mini-fill.met{ background:linear-gradient(90deg,#f0b03e,#dd7014); }
</style>

<h2 class="greet">Hi, {{ $learner->first_name }}!</h2>

<div class="stat-row">
  <div class="stat-card"><div class="v level">{{ $learner->mastery_level ?? 'New' }}</div><div class="l">Level</div></div>
  <div class="stat-card"><div class="v">{{ $learner->points }}</div><div class="l">Points</div></div>
  <div class="stat-card"><div class="v">🔥 {{ $learner->streak }}</div><div class="l">Streak</div></div>
</div>

<div class="bento">
  <div class="hero-tile">
    <div class="hero-blob b1"></div>
    <div class="hero-blob b2"></div>
    <div class="hero-mascot">@include('learner.games._owl-mascot', ['id' => 'heroOwl'])</div>
    @if ($upNextCompetency)
      <span class="hero-tag">🎯 Picked just for you</span>
      <h3>Time to work on {{ $upNextCompetency['label'] }}!</h3>
      <p>TaraBasa picked today's story because of how your last few readings went — {{ strtolower($upNextCompetency['difficultyWord'] ?? 'just right') }} for you right now.</p>
    @else
      <span class="hero-tag">📖 Ready when you are</span>
      <h3>Ready to read, {{ $learner->first_name }}?</h3>
      <p>Tap below and let's find your next story!</p>
    @endif
    <a href="{{ route('learner.activity.find') }}" class="hero-cta">Start Reading Activity</a>
  </div>

  <div class="bento-side">
    <a href="{{ route('learner.games.index') }}" class="tile tile-games">
      <svg width="30" height="30" viewBox="0 0 24 24" fill="none"><rect x="3" y="7" width="18" height="11" rx="4" fill="white"/><circle cx="8" cy="12.5" r="1.4" fill="#ef8d2a"/><circle cx="16" cy="12.5" r="1.4" fill="#ef8d2a"/></svg>
      <div><h4>🎮 Games</h4><p>Free play</p></div>
    </a>

    <a href="{{ route('learner.goals.index') }}" class="tile">
      <div class="head"><h4>This Week's Goal</h4></div>
      <div class="mini-track"><div class="mini-fill {{ $weeklyMet ? 'met' : '' }}" style="width:{{ $weeklyTarget > 0 ? min(100, round($weeklyCount / $weeklyTarget * 100)) : 0 }}%"></div></div>
      <p class="badge-count-note">{{ $weeklyCount }} of {{ $weeklyTarget }} this week{{ $weeklyMet ? ' 🏆' : '' }}</p>
    </a>

    <div class="tile">
      <div class="head"><h4>My Badges</h4><a href="{{ route('learner.badges.index') }}" class="see-all">See all</a></div>
      <div class="badge-strip">
        @foreach (array_slice($badges, 0, 5) as $badge)
          <div class="mini-badge {{ $badge['earned'] ? 'earned' : 'locked' }}">{{ $badge['emoji'] }}</div>
        @endforeach
      </div>
      <p class="badge-count-note">{{ $earnedBadgeCount }} of {{ $totalBadgeCount }} earned</p>
    </div>

    <a href="{{ route('learner.growth.index') }}" class="tile">
      <div class="head"><h4>My Growth</h4><span class="see-all">See chart</span></div>
      <p class="badge-count-note">{{ $weeklyCount }} {{ $weeklyCount === 1 ? 'story' : 'stories' }} read this week</p>
    </a>
  </div>
</div>
@endsection
