<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TaraBasa AI — Open Repository</title>
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
    --purple:#7c5cd6; --purple-bg:#f1edfc;
    --success:#1f9e83; --success-bg:#e9f7f3;
    --amber:#c9820b; --amber-bg:#fef6e6;
    --danger:#d64545; --danger-bg:#fdecec;
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

  .back-link{
    display:inline-flex; align-items:center; gap:6px; font-size:13.5px; font-weight:700; color:var(--slate-600);
    text-decoration:none; margin:18px 0 6px;
  }
  .back-link:hover{ color:var(--blue-600); }

  h1{ font-family:'Baloo 2',sans-serif; font-size:26px; font-weight:700; margin:0 0 4px; }
  .sub{ color:var(--slate-600); font-size:14.5px; font-weight:500; margin:0 0 18px; }

  .child-tabs{ display:flex; gap:8px; margin-bottom:18px; flex-wrap:wrap; }
  .child-tab{
    display:inline-flex; align-items:center; gap:8px; padding:8px 16px 8px 8px; border-radius:999px;
    border:1.5px solid var(--line); background:var(--surface); cursor:pointer; font-weight:700; font-size:13px; color:var(--slate-600);
    text-decoration:none; transition:border-color .15s ease, color .15s ease;
  }
  .child-tab:not(.active):hover{ border-color:var(--parent-teal); color:var(--parent-teal); }
  .child-tab.active{ background:var(--navy-900); color:var(--bg-0); border-color:var(--navy-900); }
  .child-tab .av{ width:24px;height:24px;border-radius:50%; overflow:hidden; background:linear-gradient(155deg, var(--clay-yellow), var(--owl-orange-600)); display:flex;align-items:center;justify-content:center; font-size:13px; flex-shrink:0; }
  .child-tab .av img{ width:100%; height:100%; object-fit:cover; }

  .filters{ display:flex; gap:9px; flex-wrap:wrap; margin-bottom:22px; }
  .filter-chip{
    padding:8px 15px; border-radius:999px; border:1.5px solid var(--line); background:var(--surface);
    font-size:13px; font-weight:700; color:var(--slate-600); cursor:pointer; text-decoration:none;
    transition:border-color .15s ease, color .15s ease, background .15s ease;
  }
  .filter-chip.active{ border-color:var(--blue-500); background:var(--sky-50); color:var(--blue-600); }

  .flash{ font-weight:700; font-size:13px; border-radius:14px; padding:12px 16px; margin-bottom:18px; background:var(--success-bg); border:1px solid var(--success); color:var(--success); }
  .flash.error{ background:var(--danger-bg); border-color:var(--danger); color:var(--danger); }

  .grid{ display:grid; grid-template-columns:repeat(auto-fill, minmax(260px,1fr)); gap:16px; }
  .item-card{
    background:var(--surface); border:1px solid var(--line); border-radius:20px; padding:18px;
    box-shadow:var(--shadow-sm); display:flex; flex-direction:column; gap:10px; transition:box-shadow .18s ease, transform .18s ease;
  }
  .item-card:hover{ box-shadow:var(--shadow-card); transform:translateY(-2px); }
  .tag-row{ display:flex; gap:6px; flex-wrap:wrap; }
  .tag{ font-size:11px; font-weight:800; padding:3px 9px; border-radius:999px; background:var(--purple-bg); color:var(--purple); }
  .tag.diff{ background:var(--sky-50); color:var(--blue-600); }
  .tag.free{ background:var(--success-bg); color:var(--success); }
  .tag.paid{ background:var(--amber-bg); color:var(--amber); }
  .tag.owned{ background:var(--success-bg); color:var(--success); }

  .item-title{ font-family:'Baloo 2',sans-serif; font-size:16.5px; font-weight:700; margin:0; }
  .item-meta{ font-size:12.5px; color:var(--slate-600); font-weight:600; }
  .item-rating{ font-size:12px; color:var(--amber); font-weight:700; }
  .item-rating.none{ color:var(--slate-400); font-weight:500; }

  .item-action{
    margin-top:2px; display:flex; align-items:center; justify-content:center; gap:7px; padding:10px;
    border-radius:11px; font:700 13px/1 'Inter',sans-serif; cursor:pointer; border:none; transition:transform .15s ease, opacity .15s ease;
    width:100%;
  }
  .item-action:hover{ transform:translateY(-1px); }
  .item-action:disabled{ opacity:.6; cursor:not-allowed; transform:none; }
  .item-action.free, .item-action.paid{ background:var(--navy-900); color:var(--bg-0); }
  .item-action.owned{ background:var(--success-bg); color:var(--success); cursor:default; }
  .item-action.owned:hover{ transform:none; }
  .btn-ghost-sm{ background:none; border:1.5px solid var(--line); color:var(--slate-600); padding:9px; border-radius:10px; font:700 12.5px/1 'Inter',sans-serif; cursor:pointer; width:100%; }

  .confirm-panel{ display:none; margin-top:4px; padding-top:10px; border-top:1px dashed var(--line); }
  .confirm-panel.show{ display:block; }
  .confirm-note{ font-size:12px; color:var(--slate-600); font-weight:500; margin:0 0 10px; line-height:1.5; }

  .rate-panel{ margin-top:2px; padding-top:10px; border-top:1px dashed var(--line); }
  .star-picker{ display:flex; gap:4px; margin-bottom:8px; }
  .star-btn{ background:none; border:none; font-size:20px; cursor:pointer; color:var(--line); padding:0; line-height:1; transition:color .1s ease; }
  .star-btn.filled{ color:var(--amber); }
  .rate-comment{
    width:100%; font:500 12.5px/1.4 'Inter',sans-serif; padding:8px 10px; border:1.5px solid var(--line); border-radius:9px;
    background:var(--bg-0); color:var(--navy-900); outline:none; margin-bottom:8px; box-sizing:border-box; resize:vertical; min-height:44px;
  }
  .btn-rate-submit{ background:var(--parent-teal); color:#fff; border:none; padding:9px; border-radius:10px; font:700 12.5px/1 'Inter',sans-serif; cursor:pointer; width:100%; }

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
        <span class="av">{{ strtoupper(substr($user->first_name, 0, 1)) }}</span>
        <span class="name">{{ $user->first_name }}</span>
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

  <h1>Open Repository</h1>

  @if ($learners->isEmpty())
    <p class="sub">Extra reading activities shared by teachers across TaraBasa AI.</p>
    <div class="empty-hero">
      <div class="big-icon">📚</div>
      <h2>Add a child first</h2>
      <p>Once you've added a child, you can unlock extra reading activities for them here.</p>
      <a href="{{ route('parent.children.create') }}" class="btn-primary">Add Your Child</a>
    </div>
  @else
    <p class="sub">Extra reading activities shared by teachers across TaraBasa AI — for {{ $selectedLearner->first_name }}.</p>

    @if (session('status'))
      <div class="flash">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
      <div class="flash error">{{ $errors->first() }}</div>
    @endif

    @if ($learners->count() > 1)
      <div class="child-tabs">
        @foreach ($learners as $learner)
          <a href="{{ route('parent.repository.index', ['learner_id' => $learner->id, 'filter' => $filter]) }}" class="child-tab {{ $learner->id === $selectedLearner->id ? 'active' : '' }}">
            <span class="av">
              @if ($learner->avatar_photo_path)
                <img src="{{ Storage::url($learner->avatar_photo_path) }}" alt="{{ $learner->first_name }}">
              @else
                {{ $learner->avatar_id }}
              @endif
            </span>
            {{ $learner->first_name }}
          </a>
        @endforeach
      </div>
    @endif

    <div class="filters">
      @foreach (['all' => 'All', 'free' => 'Free', 'paid' => 'Paid', 'unlocked' => 'Unlocked'] as $key => $label)
        <a href="{{ route('parent.repository.index', ['learner_id' => $selectedLearner->id, 'filter' => $key]) }}" class="filter-chip {{ $filter === $key ? 'active' : '' }}">{{ $label }}</a>
      @endforeach
    </div>

    @if ($rows->isEmpty())
      <div class="empty-hero">
        <div class="big-icon">📚</div>
        <h2>{{ $filter === 'all' ? 'No activities shared yet' : 'No activities match this filter' }}</h2>
        <p>{{ $filter === 'all' ? 'Check back soon — teachers share activities here as they create them.' : 'Try a different tab above.' }}</p>
      </div>
    @else
      <div class="grid">
        @foreach ($rows as $row)
          @php $listing = $row['listing']; $activity = $listing->activity; @endphp
          <div class="item-card">
            <div class="tag-row">
              <span class="tag">{{ $activity->competency_label }}</span>
              <span class="tag diff">{{ $activity->difficulty_tier }}</span>
              @if ($row['is_unlocked'])
                <span class="tag owned">Unlocked</span>
              @elseif ($listing->price_type === 'Free')
                <span class="tag free">Free</span>
              @else
                <span class="tag paid">₱{{ number_format($listing->price) }}</span>
              @endif
            </div>
            <p class="item-title">{{ $activity->title }}</p>
            <p class="item-meta">{{ $activity->grade_level }} &middot; {{ $activity->competency_label }}</p>
            <p class="item-rating {{ $row['avg_rating'] === null ? 'none' : '' }}">
              {{ $row['avg_rating'] !== null ? '★ '.$row['avg_rating'].' ('.$row['rating_count'].' rating'.($row['rating_count'] === 1 ? '' : 's').')' : 'No ratings yet' }}
            </p>

            @if ($row['is_unlocked'])
              <button type="button" class="item-action owned" disabled>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Ready to Read
              </button>

              <div class="rate-panel">
                <form method="POST" action="{{ route('parent.repository.rate', $listing) }}" class="rate-form">
                  @csrf
                  <input type="hidden" name="learner_id" value="{{ $selectedLearner->id }}">
                  <input type="hidden" name="rating" class="rating-input" value="{{ $row['my_rating']->rating ?? '' }}">
                  <div class="star-picker">
                    @for ($i = 1; $i <= 5; $i++)
                      <button type="button" class="star-btn {{ ($row['my_rating']->rating ?? 0) >= $i ? 'filled' : '' }}" data-value="{{ $i }}">★</button>
                    @endfor
                  </div>
                  <textarea name="comment" class="rate-comment" placeholder="Optional comment (for {{ $selectedLearner->first_name }})">{{ $row['my_rating']->comment ?? '' }}</textarea>
                  <button type="submit" class="btn-rate-submit">{{ $row['my_rating'] ? 'Update Rating' : 'Rate This' }}</button>
                </form>
              </div>
            @elseif ($listing->price_type === 'Free')
              <form method="POST" action="{{ route('parent.repository.unlock', $listing) }}">
                @csrf
                <input type="hidden" name="learner_id" value="{{ $selectedLearner->id }}">
                <button type="submit" class="item-action free">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M12 3v13M6 10l6 6 6-6M5 21h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  Get for Free
                </button>
              </form>
            @else
              @php $confirmId = 'confirm-'.$listing->id; @endphp
              <button type="button" class="item-action paid" data-toggle-confirm="{{ $confirmId }}">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><rect x="5" y="11" width="14" height="9" rx="2" stroke="currentColor" stroke-width="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3" stroke="currentColor" stroke-width="2"/></svg>
                Unlock for ₱{{ number_format($listing->price) }}
              </button>
              <div class="confirm-panel" id="{{ $confirmId }}">
                <p class="confirm-note">Unlock this for ₱{{ number_format($listing->price) }} for {{ $selectedLearner->first_name }}? (simulated payment — no real charge)</p>
                <form method="POST" action="{{ route('parent.repository.unlock', $listing) }}">
                  @csrf
                  <input type="hidden" name="learner_id" value="{{ $selectedLearner->id }}">
                  <button type="submit" class="item-action paid" style="margin-bottom:8px;">Confirm — Unlock for ₱{{ number_format($listing->price) }}</button>
                </form>
                <button type="button" class="btn-ghost-sm" data-toggle-confirm="{{ $confirmId }}">Cancel</button>
              </div>
            @endif
          </div>
        @endforeach
      </div>
    @endif
  @endif

</div>

<script>
  document.querySelectorAll('[data-toggle-confirm]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById(btn.dataset.toggleConfirm)?.classList.toggle('show');
    });
  });

  document.querySelectorAll('.star-picker').forEach(function (picker) {
    const input = picker.parentElement.querySelector('.rating-input');
    const stars = picker.querySelectorAll('.star-btn');
    stars.forEach(function (star) {
      star.addEventListener('click', function () {
        const value = parseInt(star.dataset.value, 10);
        input.value = value;
        stars.forEach(function (s) { s.classList.toggle('filled', parseInt(s.dataset.value, 10) <= value); });
      });
    });
  });

  // Real loading state on every unlock/rate submission.
  document.querySelectorAll('.item-card form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      const btn = e.submitter || form.querySelector('button[type="submit"]');
      if (!btn) return;
      btn.disabled = true;
      btn.textContent = '…';
    });
  });
</script>
</body>
</html>
