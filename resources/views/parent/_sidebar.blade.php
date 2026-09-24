{{--
  The Parent menu bar. The active item follows the route. Home, Progress and Activities keep the
  child that is currently picked (?learner_id=) so moving between them stays on the same child.
--}}
@php
    $me = auth()->user();
    $keep = array_filter(['learner_id' => request()->query('learner_id')]);
    $nav = [
        ['house', 'parent.dashboard', 'parent.dashboard', 'Home', $keep],
        ['users-three', 'parent.children.index', 'parent.children.*', 'Children', []],
        ['chart-line-up', 'parent.progress', 'parent.progress', 'Progress', $keep],
        ['storefront', 'parent.repository.index', 'parent.repository.*', 'Activities', $keep],
        ['bell', 'parent.notifications.index', 'parent.notifications.*', 'Alerts', []],
        ['user-circle', 'parent.profile.edit', 'parent.profile.*', 'Profile', []],
    ];
@endphp
<aside class="sidebar" aria-label="Main menu">
  <div class="brand">TaraBasa<span>AI</span></div>

  @foreach ($nav as [$icon, $route, $pattern, $label, $query])
    <a class="nav-item" href="{{ route($route, $query) }}" @if (request()->routeIs($pattern)) aria-current="page" @endif>
      @include('learner._badge-icon', ['icon' => $icon, 'class' => 'ico'])
      <span>{{ $label }}</span>
      @if ($route === 'parent.notifications.index' && ($navUnread ?? 0) > 0)
        <span class="nav-count">{{ $navUnread > 9 ? '9+' : $navUnread }}</span>
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
    <a class="me" href="{{ route('parent.profile.edit') }}" style="text-decoration:none;color:inherit;">
      <div class="me-av">{{ mb_strtoupper(mb_substr($me->first_name, 0, 1)) }}</div>
      <div><b>Hi, {{ $me->first_name }}!</b><span>Parent</span></div>
    </a>
  </div>
</aside>
