{{-- The Parent menu bar on a phone: five tabs along the bottom. Profile and Log out are reached from the avatar at the top. --}}
@php
    $keep = array_filter(['learner_id' => request()->query('learner_id')]);
    $tabs = [
        ['house', 'parent.dashboard', 'parent.dashboard', 'Home', $keep],
        ['users-three', 'parent.children.index', 'parent.children.*', 'Children', []],
        ['chart-line-up', 'parent.progress', 'parent.progress', 'Progress', $keep],
        ['storefront', 'parent.repository.index', 'parent.repository.*', 'Activities', $keep],
        ['bell', 'parent.notifications.index', 'parent.notifications.*', 'Alerts', []],
    ];
@endphp
<nav class="tabbar" aria-label="Main menu">
  @foreach ($tabs as [$icon, $route, $pattern, $label, $query])
    <a class="tab" href="{{ route($route, $query) }}" @if (request()->routeIs($pattern)) aria-current="page" @endif>
      @include('learner._badge-icon', ['icon' => $icon, 'class' => 'ico'])
      {{ $label }}
      @if ($route === 'parent.notifications.index' && ($navUnread ?? 0) > 0)
        <span class="dot">{{ $navUnread > 9 ? '9+' : $navUnread }}</span>
      @endif
    </a>
  @endforeach
</nav>
