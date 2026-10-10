{{-- The phone's bottom bar: the five Admin places. Log out is behind the avatar at the top. --}}
@php
    $tabs = [
        ['house', 'admin.dashboard', 'admin.dashboard', 'Overview'],
        ['list-checks', 'admin.approvals', 'admin.approvals', 'Approvals'],
        ['users', 'admin.accounts', 'admin.accounts', 'Accounts'],
        ['shield-check', 'admin.health', 'admin.health', 'Health'],
        ['list-bullets', 'admin.log', 'admin.log', 'Log'],
    ];
    $counts = ['admin.approvals' => $navWaiting ?? 0, 'admin.health' => $navHealth ?? 0];
@endphp
<nav class="tabbar" aria-label="Main menu">
  @foreach ($tabs as [$icon, $route, $pattern, $label])
    <a class="tab" href="{{ route($route) }}" @if (request()->routeIs($pattern)) aria-current="page" @endif>
      @include('learner._badge-icon', ['icon' => $icon, 'class' => 'ico'])
      {{ $label }}
      @if (($counts[$route] ?? 0) > 0)
        <span class="dot">{{ $counts[$route] > 9 ? '9+' : $counts[$route] }}</span>
      @endif
    </a>
  @endforeach
</nav>
