{{--
  The Admin menu bar. The active item follows the route. Approvals shows how many teachers wait, and System health
  shows a red count only when a service has a real problem (a sleeping service is not one).
--}}
@php
    $me = auth()->user();
    $nav = [
        ['house', 'admin.dashboard', 'admin.dashboard', 'Overview'],
        ['list-checks', 'admin.approvals', 'admin.approvals', 'Approvals'],
        ['users', 'admin.accounts', 'admin.accounts', 'Accounts'],
        ['shield-check', 'admin.health', 'admin.health', 'System health'],
        ['list-bullets', 'admin.log', 'admin.log', 'Activity log'],
    ];
    $counts = ['admin.approvals' => $navWaiting ?? 0, 'admin.health' => $navHealth ?? 0];
@endphp
<aside class="sidebar" aria-label="Main menu">
  <div class="brand">TaraBasa<span>AI</span></div>

  @foreach ($nav as [$icon, $route, $pattern, $label])
    <a class="nav-item" href="{{ route($route) }}" @if (request()->routeIs($pattern)) aria-current="page" @endif>
      @include('learner._badge-icon', ['icon' => $icon, 'class' => 'ico'])
      <span>{{ $label }}</span>
      @if (($counts[$route] ?? 0) > 0)
        <span class="nav-count {{ $route === 'admin.health' ? 'red' : '' }}">{{ $counts[$route] > 9 ? '9+' : $counts[$route] }}</span>
      @endif
    </a>
  @endforeach

  <div class="sidebar-foot">
    <form method="POST" action="{{ route('logout') }}">
      @csrf
      <button type="submit" class="nav-item">
        @include('learner._badge-icon', ['icon' => 'sign-out', 'class' => 'ico'])
        <span>Log out</span>
      </button>
    </form>
    <div class="me">
      <div class="me-av">{{ mb_strtoupper(mb_substr($me->first_name, 0, 1)) }}</div>
      <div><b>Hi, {{ $me->first_name }}!</b><span>Admin</span></div>
    </div>
  </div>
</aside>
