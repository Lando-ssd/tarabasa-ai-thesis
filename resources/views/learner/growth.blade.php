@extends('layouts.learner-app')

@section('title', 'My Growth This Week — TaraBasa AI')

@section('content')
<style>
  .growth-card{
    background:var(--surface); border:1px solid var(--line); border-radius:22px; padding:24px;
    box-shadow:var(--shadow-sm); max-width:480px; margin:20px auto 0;
  }
  .growth-total{ font-size:14px; font-weight:700; color:var(--slate-600); margin:0 0 20px; text-align:center; }
  .growth-total strong{ color:var(--teal-700); font-family:'Baloo 2',sans-serif; font-size:16px; }
  .growth-chart{ display:flex; align-items:flex-end; gap:10px; height:140px; padding:0 4px; }
  .growth-day{ flex:1; display:flex; flex-direction:column; align-items:center; justify-content:flex-end; height:100%; }
  .growth-bar-track{ flex:1; width:100%; display:flex; align-items:flex-end; }
  .growth-bar{
    width:100%; border-radius:8px 8px 3px 3px; background:linear-gradient(180deg, var(--sky-100), var(--blue-500));
    min-height:4px; transition:height .4s ease;
  }
  .growth-day.today .growth-bar{ background:linear-gradient(180deg, var(--clay-yellow), var(--owl-orange-600)); }
  .growth-count{ font-size:11.5px; font-weight:800; color:var(--slate-600); margin:6px 0 4px; }
  .growth-label{ font-size:11px; font-weight:700; color:var(--slate-400); text-transform:uppercase; letter-spacing:.03em; }
  .growth-day.today .growth-label{ color:var(--owl-orange-600); }
  .growth-empty{ font-size:14px; font-weight:600; color:var(--slate-600); text-align:center; margin-top:14px; }
</style>

<h2 class="greet">My Growth This Week</h2>

<div class="growth-card">
  <p class="growth-total">You've read <strong>{{ $totalThisWeek }}</strong> {{ $totalThisWeek === 1 ? 'story' : 'stories' }} this week</p>
  <div class="growth-chart">
    @foreach ($days as $day)
      <div class="growth-day {{ $day['isToday'] ? 'today' : '' }}">
        <div class="growth-count">{{ $day['count'] > 0 ? $day['count'] : '' }}</div>
        <div class="growth-bar-track">
          <div class="growth-bar" style="height:{{ $day['count'] > 0 ? max(8, round($day['count'] / $maxCount * 100)) : 4 }}%"></div>
        </div>
        <div class="growth-label">{{ $day['label'] }}</div>
      </div>
    @endforeach
  </div>
  @if ($totalThisWeek === 0)
    <p class="growth-empty">No stories read yet this week — start one today! 📖</p>
  @endif
</div>
@endsection
