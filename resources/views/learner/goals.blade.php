@extends('layouts.learner-app')

@section('title', 'Weekly Goal — TaraBasa AI')

@section('content')
<style>
  .goal-card{
    background:var(--surface); border:1px solid var(--line); border-radius:22px; padding:28px 24px; text-align:center;
    box-shadow:var(--shadow-sm); max-width:420px; margin:20px auto 0;
  }
  .goal-card.met{ background:linear-gradient(150deg,#fff3c4,#f0b03e); border-color:transparent; }
  .goal-icon{ font-size:34px; line-height:1; margin-bottom:10px; }
  .goal-count{ font-family:'Baloo 2',sans-serif; font-size:26px; font-weight:800; margin:0 0 4px; }
  .goal-card.met .goal-count{ color:#5a3d00; }
  .goal-sub{ font-size:14px; font-weight:600; color:var(--slate-600); margin:0 0 18px; }
  .goal-card.met .goal-sub{ color:#7a5400; }
  .goal-track{ height:16px; border-radius:999px; background:var(--bg-0); overflow:hidden; }
  .goal-card.met .goal-track{ background:rgba(255,255,255,0.5); }
  .goal-fill{ height:100%; border-radius:999px; background:linear-gradient(90deg, var(--teal), var(--blue-500)); transition:width .4s ease; }
  .goal-card.met .goal-fill{ background:linear-gradient(90deg,#f0b03e,#dd7014); }
  .goal-note{ font-size:15px; font-weight:700; margin-top:16px; }
  .goal-card:not(.met) .goal-note{ color:var(--teal-700); }
  .goal-card.met .goal-note{ color:#5a3d00; }
</style>

<h2 class="greet">This Week's Goal</h2>

<div class="goal-card {{ $met ? 'met' : '' }}">
  <div class="goal-icon">{{ $met ? '🏆' : '🎯' }}</div>
  <p class="goal-count">{{ $count }} of {{ $target }}</p>
  <p class="goal-sub">real reading activities this week</p>
  <div class="goal-track"><div class="goal-fill" style="width:{{ $percent }}%"></div></div>
  @if ($met)
    <p class="goal-note">You hit your goal this week — amazing job! 🎉</p>
  @else
    <p class="goal-note">{{ $target - $count }} more {{ ($target - $count) === 1 ? 'reading' : 'readings' }} to go!</p>
  @endif
</div>
@endsection
