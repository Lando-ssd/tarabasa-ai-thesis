{{--
  The Learner sidebar. Expects $learner. The active item follows the route,
  so nothing here needs to be told which page it is on. "Switch" signs the
  child out of the Learner session and lands on the Learner login (the same
  code + PIN form), which is what "switch learner" means on a shared device.
--}}
@php
    $glyph = $learner->avatar_id ?? '';
    $initial = mb_strtoupper(mb_substr($learner->first_name, 0, 1));
    $nav = [
        ['home', 'learner.dashboard', 'learner.dashboard', 'Home'],
        ['reading', 'learner.activity.find', 'learner.activity.*', 'Reading'],
        ['games', 'learner.games.index', 'learner.games.index', 'Games'],
        ['badges', 'learner.badges.index', 'learner.badges.*', 'Badges'],
        ['bookshelf', 'learner.bookshelf.index', 'learner.bookshelf.index', 'Bookshelf'],
    ];
@endphp
<aside class="sidebar" aria-label="Main menu">
  <div class="brand">TaraBasa<span>AI</span></div>

  @foreach ($nav as [$key, $route, $pattern, $label])
    <a class="nav-item {{ request()->routeIs($pattern) ? 'active' : '' }}" href="{{ route($route) }}" @if (request()->routeIs($pattern)) aria-current="page" @endif>
      <span class="nav-icon">
        @switch($key)
          @case('home')
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" aria-hidden="true"><polygon fill="#f5bc00" points="42,43 6,43 6,15.056 24,1.453 42,15.025"/><polygon fill="#f55376" points="3.675,24.333 0.042,19.559 24,1.453 47.958,19.518 44.378,24.333 24.021,8.926"/><polygon fill="#eb0000" points="6,22.573 24.021,8.926 42,22.533 42,15.025 24,1.453 6,15.056"/><rect width="12" height="16" x="18" y="27" fill="#eb7900"/></svg>
            @break
          @case('reading')
            <img src="{{ asset('images/learner/icons8-reading-48.png') }}" alt="">
            @break
          @case('games')
            <img src="{{ asset('images/learner/icons8-leaderboard-100.png') }}" alt="">
            @break
          @case('badges')
            <img src="{{ asset('images/learner/icons8-badge-64.png') }}" alt="">
            @break
          @case('bookshelf')
            <img src="{{ asset('images/learner/icons8-books-100.png') }}" alt="">
            @break
        @endswitch
      </span>
      <span class="nav-label">{{ $label }}</span>
    </a>
  @endforeach

  <form method="POST" action="{{ route('learner.logout') }}" class="nav-form">
    @csrf
    <button type="submit" class="nav-item">
      <span class="nav-icon">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" aria-hidden="true"><path fill="#bf360c" d="M35.126,44c0,0-6-2-11-2S13,44,13,44l-1-12h24L35.126,44z"/><path fill="#ffa726" d="M14.126 28c0 2.208-1.791 4-4 4s-4-1.792-4-4c0-2.209 1.791-4 4-4S14.126 25.791 14.126 28M42.126 28c0 2.208-1.791 4-4 4s-4-1.792-4-4c0-2.209 1.791-4 4-4S42.126 25.791 42.126 28"/><path fill="#ffe0b2" d="M38.126,18c0-12.725-28-8.285-28,0v9c0,8.286,6.269,15,14,15s14-6.714,14-15V18z"/><path fill="#784719" d="M32,26c0,1.106-0.896,2-2,2c-1.105,0-2-0.894-2-2c0-1.105,0.895-2,2-2C31.104,24,32,24.895,32,26 M20,26c0-1.105-0.896-2-2-2c-1.105,0-2,0.895-2,2c0,1.106,0.895,2,2,2C19.104,28,20,27.106,20,26"/><path fill="#ff5722" d="M24.126,4C15.621,4,3.126,9,3,36l10,8V24l16.876-9l5.125,7l0.125,22L45,36c0-12-0.417-29-14.874-29l-2-3H24.126z"/><path fill="#ffab91" d="M19,35h10c0,0-2,3-5,3S19,35,19,35z"/></svg>
      </span>
      <span class="nav-label">Switch</span>
    </button>
  </form>

  <div class="sidebar-foot">
    <div class="sf-avatar">
      @if ($learner->avatar_photo_path)
        <img src="{{ Storage::url($learner->avatar_photo_path) }}" alt="">
      @elseif ($glyph !== '' && mb_strlen($glyph) <= 4)
        {{ $glyph }}
      @else
        {{ $initial }}
      @endif
    </div>
    <div class="sf-text"><b>Hi, {{ $learner->first_name }}!</b><span>{{ $learner->grade_level }}</span></div>
  </div>
</aside>
