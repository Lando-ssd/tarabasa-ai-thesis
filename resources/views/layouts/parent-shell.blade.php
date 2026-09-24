{{--
  The Parent app shell: a left menu bar (a bottom bar on a phone) plus the page. Used by
  Home, Children, Progress, Activities (the open repository), Alerts and Profile. The
  add-a-child steps and the link-a-child page are still standalone focused screens.

  The look is the Learner Home's family, calmer for adults: white cards with a lip, the
  orange clay buttons, the game face for headings, no emoji. Styles are in
  public/css/parent-app.css. $navUnread (the Alerts count) comes from a view composer
  (AppServiceProvider), so no controller has to pass it.
--}}
@php $me = auth()->user(); @endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'TaraBasa AI')</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Semi+Condensed:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/parent-app.css') }}?v={{ substr(md5_file(public_path('css/parent-app.css')), 0, 12) }}">
@stack('head')
</head>
<body>
<div class="shell">
  @include('parent._sidebar')

  <div class="main-col">
    <div class="phone-top">
      <div class="brand">TaraBasa<span>AI</span></div>
      <a class="me-av" href="{{ route('parent.profile.edit') }}" aria-label="My profile">{{ mb_strtoupper(mb_substr($me->first_name, 0, 1)) }}</a>
    </div>
    <main class="main"><div class="wrap">
      @yield('content')
    </div></main>
  </div>
</div>

@include('parent._tabbar')
@stack('scripts')
</body>
</html>
