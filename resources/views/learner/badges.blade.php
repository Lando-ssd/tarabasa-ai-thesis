@extends('layouts.learner-app')

@section('title', 'My Badges — TaraBasa AI')

@section('content')
<style>
  .badge-grid{ display:grid; grid-template-columns:1fr 1fr; gap:14px; }
  @media (min-width:700px){ .badge-grid{ grid-template-columns:1fr 1fr 1fr; } }
  @media (min-width:1024px){ .badge-grid{ grid-template-columns:repeat(4,1fr); } }
  .badge-tile{ border-radius:20px; padding:18px 12px; text-align:center; }
  .badge-tile.earned{ background:linear-gradient(155deg,#fff3c4,#f0b03e); box-shadow:0 14px 24px -14px rgba(240,176,62,0.55); }
  .badge-tile.locked{ background:var(--bg-0); border:2px dashed var(--line); }
  .badge-tile-icon{ font-size:38px; line-height:1; margin-bottom:8px; }
  .badge-tile.locked .badge-tile-icon{ filter:grayscale(1); opacity:.45; }
  .badge-tile-name{ font-family:'Baloo 2',sans-serif; font-size:14.5px; font-weight:800; margin:0 0 4px; }
  .badge-tile.earned .badge-tile-name{ color:#5a3d00; }
  .badge-tile.locked .badge-tile-name{ color:var(--slate-600); }
  .badge-tile-desc{ font-size:12px; font-weight:600; line-height:1.35; margin:0 0 8px; }
  .badge-tile.earned .badge-tile-desc{ color:#7a5400; }
  .badge-tile.locked .badge-tile-desc{ color:var(--slate-600); opacity:.85; }
  .badge-tile-date{ font-size:11px; font-weight:800; color:#5a3d00; margin:0; }
  .badge-tile-locked-label{ font-size:11px; font-weight:800; color:var(--slate-600); margin:0; }
</style>

<h2 class="greet">My Badges — {{ count(array_filter($badges, fn($b) => $b['earned'])) }} of {{ count($badges) }}</h2>

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
@endsection
