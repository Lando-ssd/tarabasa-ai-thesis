{{--
  The Admin app shell: the Teacher and Parent look (a left menu bar, a bottom bar on a phone), with five places:
  Overview, Approvals, Accounts, System health and Activity log. It reuses public/css/teacher-app.css and
  public/js/teacher-app.js (windows, toasts, chips), plus public/css/admin-app.css for the few Admin-only pieces.
  Every action that changes an account opens in a window that says what will happen, so nothing is one stray click.
  $navWaiting and $navHealth come from a view composer (AppServiceProvider).
--}}
@php $me = auth()->user(); @endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Admin | TaraBasa AI')</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Semi+Condensed:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
<noscript><link href="https://fonts.googleapis.com/css2?family=Barlow+Semi+Condensed:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"></noscript>
<link rel="stylesheet" href="{{ asset('css/teacher-app.css') }}?v={{ substr(md5_file(public_path('css/teacher-app.css')), 0, 12) }}">
<link rel="stylesheet" href="{{ asset('css/admin-app.css') }}?v={{ substr(md5_file(public_path('css/admin-app.css')), 0, 12) }}">
@stack('head')
</head>
<body>
<div class="shell">
  @include('admin._sidebar')

  <div class="main-col">
    <div class="phone-top">
      <div class="brand">TaraBasa<span>AI</span></div>
      <button type="button" class="me-av" data-open="menuDlg" aria-label="More">{{ mb_strtoupper(mb_substr($me->first_name, 0, 1)) }}</button>
    </div>
    <main class="main"><div class="wrap">
      @if ($errors->any())
        <div class="flash error" role="alert">{{ $errors->first() }}</div>
      @endif
      @yield('content')
    </div></main>
  </div>
</div>

@include('admin._tabbar')

@stack('dialogs')

{{-- The phone's "more" sheet: what does not fit on the bottom bar. --}}
<dialog id="menuDlg" class="win" aria-label="More">
  <div class="win-in narrow">
    <header class="win-head"><div class="win-titles"><h2>{{ $me->first_name }} (Admin)</h2></div><button type="button" class="x" data-close aria-label="Close">@include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])</button></header>
    <div class="win-body">
      <div class="list">
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
