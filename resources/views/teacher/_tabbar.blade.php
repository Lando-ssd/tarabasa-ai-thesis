{{-- The phone's bottom bar: the five screens a teacher opens most. Promotions, Profile and Log out
     are in the "More" sheet behind the avatar at the top. --}}
@php
    $tabs = [
        ['house', 'teacher.dashboard', 'teacher.dashboard', 'Home'],
        ['chalkboard-teacher', 'teacher.classes.index', 'teacher.classes.*', 'Classes'],
        ['books', 'teacher.activities.index', 'teacher.activities.*', 'Activities'],
        ['chart-line-up', 'teacher.analytics.index', 'teacher.analytics.*', 'Analytics'],
        ['bell', 'teacher.notifications.index', 'teacher.notifications.*', 'Alerts'],
    ];
@endphp
<nav class="tabbar" aria-label="Main menu">
  @foreach ($tabs as [$icon, $route, $pattern, $label])
    <a class="tab" href="{{ route($route) }}" @if (request()->routeIs($pattern)) aria-current="page" @endif>
      @include('learner._badge-icon', ['icon' => $icon, 'class' => 'ico'])
      {{ $label }}
      @if ($route === 'teacher.notifications.index' && ($navUnread ?? 0) > 0)
        <span class="dot">{{ $navUnread > 9 ? '9+' : $navUnread }}</span>
      @endif
    </a>
  @endforeach
</nav>
