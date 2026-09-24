{{--
  The Teacher menu bar. The active item follows the route. Alerts and Promotions show a count when
  there is something waiting (unread alerts, released learners this teacher could claim).
--}}
@php
    $me = auth()->user();
    $nav = [
        ['house', 'teacher.dashboard', 'teacher.dashboard', 'Home'],
        ['chalkboard-teacher', 'teacher.classes.index', 'teacher.classes.*', 'Classes'],
        ['books', 'teacher.activities.index', 'teacher.activities.*', 'Activities'],
        ['chart-line-up', 'teacher.analytics.index', 'teacher.analytics.*', 'Analytics'],
        ['graduation-cap', 'teacher.promotions.index', 'teacher.promotions.*', 'Promotions'],
        ['bell', 'teacher.notifications.index', 'teacher.notifications.*', 'Alerts'],
        ['user-circle', 'teacher.profile.edit', 'teacher.profile.*', 'Profile'],
    ];
    $counts = ['teacher.promotions.index' => $navClaim ?? 0, 'teacher.notifications.index' => $navUnread ?? 0];
    $isPending = optional($me->teacher)->status !== 'Active';
@endphp
<aside class="sidebar" aria-label="Main menu">
  <div class="brand">TaraBasa<span>AI</span></div>

  @foreach ($nav as [$icon, $route, $pattern, $label])
    <a class="nav-item" href="{{ route($route) }}" @if (request()->routeIs($pattern)) aria-current="page" @endif>
      @include('learner._badge-icon', ['icon' => $icon, 'class' => 'ico'])
      <span>{{ $label }}</span>
      @if (($counts[$route] ?? 0) > 0)
        <span class="nav-count">{{ $counts[$route] > 9 ? '9+' : $counts[$route] }}</span>
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
    <a class="me" href="{{ route('teacher.profile.edit') }}" style="text-decoration:none;color:inherit;">
      <div class="me-av">{{ mb_strtoupper(mb_substr($me->first_name, 0, 1)) }}</div>
      <div><b>Hi, {{ $me->first_name }}!</b><span>{{ $isPending ? 'Teacher (pending)' : 'Teacher' }}</span></div>
    </a>
  </div>
</aside>
