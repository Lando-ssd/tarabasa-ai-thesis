@extends('layouts.learner-app')

@section('title', 'My Growth Path — TaraBasa AI')

@section('content')
<style>
  .journey-wrap{ background:var(--surface); border:1px solid var(--line); border-radius:22px; padding:24px 20px; box-shadow:var(--shadow-sm); }
  @media (min-width:700px){ .journey-wrap{ padding:32px 30px; } }
  .journey-row{ display:flex; align-items:center; gap:18px; margin-bottom:14px; padding-bottom:14px; border-bottom:1px solid var(--line); }
  .journey-row:last-child{ margin-bottom:0; padding-bottom:0; border-bottom:none; }
  .journey-label{ width:130px; flex-shrink:0; }
  @media (min-width:700px){ .journey-label{ width:160px; } }
  .journey-label h5{ font-family:'Baloo 2',sans-serif; font-size:13.5px; margin:0 0 3px; line-height:1.25; }
  .journey-label .word{ font-size:11px; font-weight:700; color:var(--slate-600); }
  .journey-label .up-next{
    display:inline-flex; align-items:center; gap:3px; font-size:10px; font-weight:800; color:var(--owl-orange-600);
    background:#fff3e2; border:1px solid var(--owl-orange-500); border-radius:999px; padding:2px 8px; margin-top:3px;
  }
  .journey-track{ flex:1; min-width:0; }
  .journey-track svg{ width:100%; height:auto; display:block; }
  .checkpoint-dot{ transition:fill .2s ease, stroke .2s ease; }
  .journey-marker-owl{ width:34px; height:34px; }

  .not-started{ font-size:11px; font-weight:700; color:var(--slate-400); }

  .empty-note{ font-size:15px; color:var(--slate-600); font-weight:600; line-height:1.5; text-align:center; padding:30px 10px; }
</style>

<h2 class="greet">How I'm Growing</h2>

@if (! $hasAnyData)
  <div class="journey-wrap">
    <p class="empty-note">Your growth path fills in once you complete your first reading check! 🌱</p>
  </div>
@else
  <div class="journey-wrap">
    @foreach ($rows as $row)
      <div class="journey-row">
        <div class="journey-label">
          <h5>{{ $row['label'] }}</h5>
          @if ($row['track'] === null)
            <span class="not-started">Not started yet</span>
          @else
            <span class="word">{{ $row['difficultyWord'] }}</span>
          @endif
          @if ($row['isUpNext'])
            <span class="up-next">⭐ Up Next</span>
          @endif
        </div>
        <div class="journey-track">
          <svg viewBox="0 0 500 64" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid meet">
            @php $track = $row['track']; @endphp
            @if ($track === null)
              <polyline points="20,48 140,16 260,48 380,16 480,48" fill="none" stroke="var(--line)" stroke-width="4" stroke-linecap="round" stroke-dasharray="1 10" />
              @foreach ([['x'=>20,'y'=>48],['x'=>140,'y'=>16],['x'=>260,'y'=>48],['x'=>380,'y'=>16],['x'=>480,'y'=>48]] as $p)
                <circle class="checkpoint-dot" cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="9" fill="var(--bg-0)" stroke="var(--line)" stroke-width="2" stroke-dasharray="2 2" />
              @endforeach
            @else
              <polyline points="{{ $track['greyPolyline'] }}" fill="none" stroke="var(--line)" stroke-width="4" stroke-linecap="round" />
              <polyline points="{{ $track['coloredPolyline'] }}" fill="none" stroke="var(--teal)" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
              @foreach ($track['points'] as $i => $p)
                <circle class="checkpoint-dot" cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="9"
                  fill="{{ $track['checkpointsDone'][$i] ? 'var(--teal)' : 'var(--bg-0)' }}"
                  stroke="{{ $track['checkpointsDone'][$i] ? 'var(--teal-700)' : 'var(--line)' }}" stroke-width="2" />
                @if ($track['checkpointsDone'][$i])
                  <path d="M{{ $p['x'] - 4 }} {{ $p['y'] }} l3 3 l5 -6" stroke="#fff" stroke-width="1.8" fill="none" stroke-linecap="round" stroke-linejoin="round" />
                @endif
              @endforeach
              <circle cx="{{ $track['marker']['x'] }}" cy="{{ $track['marker']['y'] }}" r="15" fill="#fff" stroke="var(--owl-orange-500)" stroke-width="2.5" />
              <text x="{{ $track['marker']['x'] }}" y="{{ $track['marker']['y'] + 4 }}" text-anchor="middle" font-size="14">🦉</text>
            @endif
          </svg>
        </div>
      </div>
    @endforeach
  </div>
@endif
@endsection
