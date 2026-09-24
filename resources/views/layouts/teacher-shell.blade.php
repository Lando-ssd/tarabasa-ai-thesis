{{--
  The Teacher app shell: a left menu bar (a bottom bar on a phone) plus the page. Used by Home,
  Classes, Activities, Analytics, Promotions, Alerts and Profile.

  Same family as the Learner and Parent apps, calmer for adults. Every action on every Teacher
  screen opens in a window (a native <dialog>, a bottom sheet on a phone) instead of unrolling a
  form inline, and each screen is sized so a laptop window holds it: teachers find things by
  searching and filtering, not by scrolling. Styles are in public/css/teacher-app.css and the
  behavior in public/js/teacher-app.js. $navUnread (Alerts) and $navClaim (Promotions) come from
  a view composer (AppServiceProvider).
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
<link rel="stylesheet" href="{{ asset('css/teacher-app.css') }}?v={{ substr(md5_file(public_path('css/teacher-app.css')), 0, 12) }}">
@stack('head')
</head>
<body>
<div class="shell">
  @include('teacher._sidebar')

  <div class="main-col">
    <div class="phone-top">
      <div class="brand">TaraBasa<span>AI</span></div>
      <button type="button" class="me-av" data-open="menuDlg" aria-label="More">{{ mb_strtoupper(mb_substr($me->first_name, 0, 1)) }}</button>
    </div>
    <main class="main"><div class="wrap">
      @if ($errors->any() && ! old('form'))
        <div class="flash error" role="alert">{{ $errors->first() }}</div>
      @endif
      @if (session('classError'))
        <div class="flash error" role="alert">{{ session('classError') }}</div>
      @endif
      @yield('content')
    </div></main>
  </div>
</div>

@include('teacher._tabbar')

{{-- The two windows every screen can fill in the background (an activity's window is fetched into
     dlg; dlg2 sits above it for a small follow-up). A screen with windows of its own (one per
     class, a confirmation) pushes them onto the "dialogs" stack. --}}
<dialog id="dlg" class="win" aria-label="Window"></dialog>
<dialog id="dlg2" class="win" aria-label="Window"></dialog>
@stack('dialogs')

{{-- The phone's "more" sheet: what does not fit on the bottom bar. --}}
<dialog id="menuDlg" class="win" aria-label="More">
  <div class="win-in narrow">
    <header class="win-head"><div class="win-titles"><h2>More</h2></div><button type="button" class="x" data-close aria-label="Close">@include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])</button></header>
    <div class="win-body">
      <div class="list">
        <a class="item" href="{{ route('teacher.promotions.index') }}"><span class="item-ico">@include('learner._badge-icon', ['icon' => 'graduation-cap', 'class' => 'ico'])</span><span class="item-body"><span class="item-title">Promotions</span></span>@if (($navClaim ?? 0) > 0)<span class="pill amber">{{ $navClaim }}</span>@endif</a>
        <a class="item" href="{{ route('teacher.profile.edit') }}"><span class="item-ico">@include('learner._badge-icon', ['icon' => 'user-circle', 'class' => 'ico'])</span><span class="item-body"><span class="item-title">Profile</span></span></a>
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button type="submit" class="item"><span class="item-ico r">@include('learner._badge-icon', ['icon' => 'sign-out', 'class' => 'ico'])</span><span class="item-body"><span class="item-title">Log out</span></span></button>
        </form>
      </div>
    </div>
  </div>
</dialog>

<div class="toast" id="toast" popover="manual" role="status" @if (session('status')) data-flash="{{ session('status') }}" @endif></div>

<script src="{{ asset('js/teacher-app.js') }}?v={{ substr(md5_file(public_path('js/teacher-app.js')), 0, 12) }}"></script>
@stack('scripts')
</body>
</html>
