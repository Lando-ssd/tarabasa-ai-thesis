{{--
  The Learner app shell: the sidebar plus a main area, used by Home, Reading,
  Games, Badges and Bookshelf. The focused task screens (recording a reading,
  the assessment, the two games themselves) deliberately stay outside it, like
  every other focused flow in this app, so nothing distracts a child mid-task.

  Each page sets its own background through <html data-page="...">, and the
  learner's chosen colour tints the profile chip through <body data-theme>.
  Styles live in public/css/learner-app.css.
--}}
@php $learner = $learner ?? auth('learner')->user(); @endphp
<!DOCTYPE html>
<html lang="en" data-page="@yield('page', 'home')">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'TaraBasa AI')</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Barlow+Semi+Condensed:wght@500;600;700&family=Fredoka:wght@400;500;600&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/lottie-web@5.12.2/build/player/lottie.min.js"></script>
<link rel="stylesheet" href="{{ asset('css/learner-app.css') }}?v={{ substr(md5_file(public_path('css/learner-app.css')), 0, 12) }}">
@stack('head')
</head>
<body data-theme="{{ $learner->theme_color === 'pink' ? 'pink' : 'blue' }}">
@stack('sprites')
<div class="shell">
  @include('learner._sidebar')
  <div class="main">
    @yield('content')
  </div>
</div>
@stack('scripts')
</body>
</html>
