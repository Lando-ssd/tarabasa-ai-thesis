<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>@yield('title', 'TaraBasa AI')</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --sky-100:#dcedff; --bg-0:#f6faff; --surface:#ffffff;
    --navy-900:#131f2b; --slate-600:#5b6b7a; --slate-400:#8a97a3;
    --blue-500:#1c7ed6; --blue-600:#0f5fae; --blue-700:#0a3d73;
    --owl-orange-500:#ef8d2a; --owl-orange-600:#dd7014; --clay-yellow:#ffcf6e;
    --teal:#2bb89c; --teal-700:#0f6f5e; --success-bg:#e4f7f0;
    --purple:#7c5cd6;
    --line:#e3ebf2; --shadow-sm:0 1px 2px rgba(19,31,43,0.06);
  }
  *{box-sizing:border-box;} html,body{margin:0;padding:0;}
  body{
    min-height:100vh; font-family:'Inter',sans-serif; color:var(--navy-900);
    background: radial-gradient(1000px 600px at 90% -10%, var(--sky-100), transparent 55%),
                radial-gradient(800px 500px at 0% 100%, #ffe9c9, transparent 50%), var(--bg-0);
    background-attachment:fixed;
  }

  /*
   * The rail is deliberately icon-only at rest — not the reference's
   * icon+label rail — to feel like a real narrow app shell rather than a
   * sidebar bolted onto a webpage. Confirmed with the user this is safe
   * for a 6-9 year-old specifically BECAUSE of the two reinforcements
   * below (not as icon-only alone): the active item always shows its own
   * label, and every label reveals once on this Learner's first-ever
   * visit before collapsing — a young reader gets the icon-to-meaning
   * mapping reinforced twice before ever being expected to rely on the
   * icon alone. Real `title` attributes give desktop hover tooltips too.
   */
  .layout{ display:flex; min-height:100vh; }
  .rail{
    width:84px; flex-shrink:0; background:var(--surface); border-right:1px solid var(--line);
    padding:18px 10px; display:flex; flex-direction:column; align-items:center; gap:6px;
  }
  .rail-avatar{
    width:52px;height:52px;border-radius:16px; margin-bottom:16px; overflow:hidden;
    background:linear-gradient(155deg,var(--clay-yellow),var(--owl-orange-600));
    display:flex;align-items:center;justify-content:center; box-shadow:var(--shadow-sm);
    font-family:'Baloo 2',sans-serif; font-weight:800; font-size:20px; color:#fff; flex-shrink:0;
  }
  .rail-avatar img{ width:100%; height:100%; object-fit:cover; display:block; }
  .rail-nav{ display:flex; flex-direction:column; gap:6px; width:100%; }
  .rail-item{
    display:flex; flex-direction:column; align-items:center; justify-content:center; gap:2px;
    width:100%; padding:10px 4px; border-radius:15px; text-decoration:none; color:var(--slate-600);
    transition:background-color .15s ease, color .15s ease;
  }
  .rail-item:hover{ background:var(--sky-100); }
  .rail-item.active{ background:var(--owl-orange-500); color:#fff; }
  .rail-item svg{ flex-shrink:0; }
  .rail-item-label{
    font-size:9.5px; font-weight:800; line-height:1.1; text-align:center; max-width:100%;
    display:none;
  }
  .rail-item.active .rail-item-label{ display:block; }
  /* First-visit reveal: every label shown briefly, even on non-active
     items — toggled by JS adding this class to .rail-nav, removed after
     ~3s and never shown again on this device (localStorage, per Learner
     code — the same lightweight per-device-preference pattern already
     used for Practice Games' resume state). */
  .rail-nav.reveal-labels .rail-item-label{ display:block; }

  .switch-learner{
    margin-top:auto; padding-top:14px; background:none; border:none; text-align:center;
    font-size:9.5px; font-weight:700; color:var(--slate-400); cursor:pointer; width:100%;
  }
  .switch-learner:hover{ color:var(--blue-600); }

  .main{ flex:1; padding:26px 28px 40px; min-width:0; }
  @media (min-width:1024px){ .main{ padding:36px 44px 48px; } }
  .main-inner{ max-width:1080px; margin:0 auto; }
  .greet{ font-family:'Baloo 2',sans-serif; font-size:22px; margin:0 0 18px; }
  @media (min-width:1024px){ .greet{ font-size:26px; } }

  a:focus-visible, button:focus-visible{ outline:2px solid var(--blue-500); outline-offset:2px; }

  /* Phone-width fallback: the rail becomes a bottom tab bar instead of a
     left column — a left rail at 375px would eat a fifth of the screen
     width just for 6 icons. This keeps the "real app shell" feel
     (persistent icon nav, not a hamburger menu) while actually fitting. */
  @media (max-width:680px){
    .layout{ flex-direction:column; }
    .rail{
      width:100%; height:auto; flex-direction:row; justify-content:space-around; align-items:center;
      padding:8px 4px; border-right:none; border-top:1px solid var(--line);
      order:2; position:sticky; bottom:0; z-index:10;
    }
    .rail-avatar{ display:none; }
    .rail-nav{ flex-direction:row; justify-content:space-around; gap:2px; }
    .rail-item{ padding:8px 2px; border-radius:12px; }
    .switch-learner{ display:none; }
    .main{ order:1; padding:20px 16px 24px; }
  }
</style>
@stack('styles')
</head>
<body>
<div class="layout">
  <div class="rail">
    <div class="rail-avatar">
      @if ($learner->avatar_photo_path)
        <img src="{{ Storage::url($learner->avatar_photo_path) }}" alt="{{ $learner->first_name }}">
      @else
        {{ $learner->avatar_id }}
      @endif
    </div>
    <nav class="rail-nav" id="railNav">
      <a href="{{ route('learner.dashboard') }}" class="rail-item {{ request()->routeIs('learner.dashboard') ? 'active' : '' }}" title="Home">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M4 11l8-7 8 7v9a1 1 0 0 1-1 1h-4v-6h-6v6H5a1 1 0 0 1-1-1v-9z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
        <span class="rail-item-label">Home</span>
      </a>
      <a href="{{ route('learner.journey.index') }}" class="rail-item {{ request()->routeIs('learner.journey.index') ? 'active' : '' }}" title="My Growth Path">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><circle cx="6" cy="7" r="2.3" stroke="currentColor" stroke-width="1.8"/><circle cx="18" cy="17" r="2.3" stroke="currentColor" stroke-width="1.8"/><path d="M8 8c3 1 3 6 6 7s3-5 4-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-dasharray="1 3.2"/></svg>
        <span class="rail-item-label">Journey</span>
      </a>
      <a href="{{ route('learner.badges.index') }}" class="rail-item {{ request()->routeIs('learner.badges.index') ? 'active' : '' }}" title="My Badges">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="9" r="5" stroke="currentColor" stroke-width="1.8"/><path d="M9 13.2L7 20l5-3 5 3-2-6.8" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
        <span class="rail-item-label">Badges</span>
      </a>
      <a href="{{ route('learner.bookshelf.index') }}" class="rail-item {{ request()->routeIs('learner.bookshelf.*') ? 'active' : '' }}" title="My Bookshelf">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M12 6c-1.5-1.5-4-2-7-1v13c3-1 5.5-.5 7 1V6z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M12 6c1.5-1.5 4-2 7-1v13c-3-1-5.5-.5-7 1V6z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
        <span class="rail-item-label">Bookshelf</span>
      </a>
      <a href="{{ route('learner.goals.index') }}" class="rail-item {{ request()->routeIs('learner.goals.index') ? 'active' : '' }}" title="Weekly Goal">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="4.5" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="1.4" fill="currentColor"/></svg>
        <span class="rail-item-label">Goals</span>
      </a>
      <a href="{{ route('learner.growth.index') }}" class="rail-item {{ request()->routeIs('learner.growth.index') ? 'active' : '' }}" title="My Growth">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M4 20V10M12 20V4M20 20v-7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        <span class="rail-item-label">Growth</span>
      </a>
    </nav>
    <form method="POST" action="{{ route('learner.logout') }}">
      @csrf
      <button type="submit" class="switch-learner">Switch<br>learner</button>
    </form>
  </div>

  <div class="main">
    <div class="main-inner">
      @yield('content')
    </div>
  </div>
</div>

<script>
  // First-visit label reveal — a lightweight per-device convenience, not a
  // real account preference (same reasoning as Practice Games' resume
  // state: this is transient UI polish, not data worth a server round
  // trip or a DB column). Wrapped in try/catch since localStorage can
  // legitimately throw (private browsing, disabled storage) — a missed
  // reveal is a cosmetic inconvenience, never allowed to break navigation.
  (function () {
    try {
      const key = 'tarabasa_seen_rail_labels_{{ $learner->learner_code }}';
      if (!localStorage.getItem(key)) {
        const nav = document.getElementById('railNav');
        nav.classList.add('reveal-labels');
        setTimeout(function () { nav.classList.remove('reveal-labels'); }, 2800);
        localStorage.setItem(key, '1');
      }
    } catch (e) { /* never block navigation over this */ }
  })();
</script>
@stack('scripts')
</body>
</html>
