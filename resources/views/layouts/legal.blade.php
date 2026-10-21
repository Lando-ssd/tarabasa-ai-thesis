{{--
  The shared page for every legal document (Privacy Policy, Terms of Use, Cookie Policy, Refund Policy, Parent and
  Guardian Consent). The layout follows what people already know from the legal pages of large sites: the list of
  documents on the left, a sticky "On this page" list, a short plain words summary card first, numbered sections with
  links you can copy, the date and version under the title, and a version that prints cleanly.

  Each page does:  @extends('layouts.legal', ['doc' => 'privacy', 'title' => '...', 'description' => '...', 'sections' => ['id' => 'Heading', ...]])
  and fills @section('summary') (the short version) and @section('body') (the numbered sections, each <section id> with an <h2>).
  Standalone like the error pages, so it works without the rest of the layout.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $title }} | TaraBasa AI</title>
<meta name="description" content="{{ $description ?? '' }}">
<link rel="stylesheet" href="{{ asset('css/fonts.css') }}?v={{ substr(md5_file(public_path('css/fonts.css')), 0, 10) }}">
<style>
  :root{ --ink:#15283a; --muted:#587086; --line:#dbe7f3; --bg:#f5f9ff; --blue:#1c7ed6; --blue-dark:#0f5fae; --orange:#ee8a26; --orange-lip:#b4560b; --soft:#eef6ff; --warn:#fff6e8; --warn-line:#f1c98e; --ok:#e9f7f3; --ok-line:#a9dccd; }
  *{ box-sizing:border-box; } html{ scroll-behavior:smooth; } html,body{ margin:0; }
  body{ min-height:100vh; background:var(--bg); color:var(--ink); font:16px/1.7 'Inter',system-ui,sans-serif; -webkit-text-size-adjust:100%; }
  a{ color:var(--blue-dark); } a:hover{ color:var(--blue); }
  a:focus-visible,summary:focus-visible,button:focus-visible{ outline:3px solid var(--blue); outline-offset:3px; border-radius:6px; }

  .lg-top{ position:sticky; top:0; z-index:20; background:#fff; border-bottom:1px solid var(--line); }
  .lg-top-in{ max-width:1160px; margin:0 auto; padding:12px 20px; display:flex; align-items:center; justify-content:space-between; gap:16px; }
  .brand{ font:800 26px/1 'Baloo 2',sans-serif; color:var(--ink); text-decoration:none; } .brand span{ color:var(--orange); }
  .lg-top nav{ display:flex; gap:6px; flex-wrap:wrap; }
  .lg-top nav a{ text-decoration:none; font-weight:600; font-size:15px; padding:9px 14px; border-radius:12px; color:var(--muted); min-height:40px; display:inline-flex; align-items:center; }
  .lg-top nav a:hover{ background:var(--soft); color:var(--ink); }

  .lg-wrap{ max-width:1160px; margin:0 auto; padding:32px 20px 56px; display:grid; grid-template-columns:270px minmax(0,1fr); gap:40px; align-items:start; }
  .lg-side{ position:sticky; top:86px; max-height:calc(100vh - 104px); overflow-y:auto; padding-right:6px; }
  .lg-side h2{ margin:0 0 8px; font:700 13px/1.2 'Inter',sans-serif; letter-spacing:.08em; text-transform:uppercase; color:var(--muted); }
  .lg-docs{ list-style:none; margin:0 0 26px; padding:0; }
  .lg-docs a{ display:block; padding:9px 12px; margin:0 0 2px; text-decoration:none; color:var(--ink); border-left:3px solid transparent; border-radius:0 10px 10px 0; font-weight:500; }
  .lg-docs a:hover{ background:var(--soft); }
  .lg-docs a[aria-current="page"]{ background:#fff; border-left-color:var(--orange); font-weight:700; }
  .lg-toc{ margin:0; padding:0; } .lg-toc summary{ cursor:pointer; list-style:none; font:700 13px/1.2 'Inter',sans-serif; letter-spacing:.08em; text-transform:uppercase; color:var(--muted); margin-bottom:8px; }
  .lg-toc summary::-webkit-details-marker{ display:none; }
  .lg-toc ol{ margin:0; padding:0 0 0 32px; font-size:14.5px; line-height:1.45; }
  .lg-toc li{ margin:0 0 7px; padding-left:2px; } .lg-toc a{ color:var(--muted); text-decoration:none; } .lg-toc a:hover{ color:var(--ink); text-decoration:underline; }
  .lg-toc a[aria-current="true"]{ color:var(--ink); font-weight:700; }

  .lg-main{ background:#fff; border:2px solid var(--line); border-radius:28px; box-shadow:0 6px 0 #e3ebf3; padding:38px 42px 34px; min-width:0; }
  .lg-main h1{ margin:0 0 10px; font:700 40px/1.12 'Baloo 2',sans-serif; }
  .lg-meta{ display:flex; flex-wrap:wrap; gap:8px; margin:0 0 24px; padding:0; list-style:none; }
  .lg-meta li{ background:var(--soft); border:1px solid var(--line); color:var(--muted); font-size:13.5px; padding:4px 12px; border-radius:999px; }
  .lg-meta b{ color:var(--ink); font-weight:600; }
  .lg-summary{ background:var(--soft); border:1px solid #cfe3f7; border-radius:20px; padding:20px 24px 12px; margin:0 0 34px; }
  .lg-summary h2{ margin:0 0 8px; font:700 21px/1.2 'Baloo 2',sans-serif; counter-increment:none; }
  .lg-summary h2::before{ content:none; }
  .lg-summary ul{ margin:0 0 8px; padding-left:22px; } .lg-summary li{ margin:0 0 8px; }
  .lg-summary p{ margin:0 0 12px; }

  .lg-body{ counter-reset:sec; }
  .lg-body section{ margin:0 0 6px; }
  .lg-body h2{ counter-increment:sec; margin:34px 0 10px; font:700 25px/1.2 'Baloo 2',sans-serif; scroll-margin-top:92px; position:relative; }
  .lg-body h2::before{ content:counter(sec) '. '; color:var(--orange); }
  .lg-body h3{ margin:22px 0 6px; font:700 18px/1.3 'Inter',sans-serif; }
  .lg-anchor{ margin-left:8px; font:600 18px/1 'Inter',sans-serif; color:var(--line); text-decoration:none; opacity:0; transition:opacity .15s; }
  .lg-body h2:hover .lg-anchor,.lg-anchor:focus{ opacity:1; color:var(--blue); }
  .lg-body p{ margin:0 0 14px; } .lg-body ul,.lg-body ol.lg-list{ margin:0 0 14px; padding-left:24px; } .lg-body li{ margin:0 0 8px; }
  .lg-body hr{ border:0; border-top:1px solid var(--line); margin:30px 0; }
  .lg-note,.lg-warn,.lg-ok{ margin:16px 0; padding:14px 18px; border-radius:14px; border:1px solid var(--line); }
  .lg-note{ background:var(--soft); border-color:#cfe3f7; } .lg-warn{ background:var(--warn); border-color:var(--warn-line); } .lg-ok{ background:var(--ok); border-color:var(--ok-line); }
  .lg-note>:last-child,.lg-warn>:last-child,.lg-ok>:last-child{ margin-bottom:0; }
  .lg-scroll{ overflow-x:auto; margin:14px 0 18px; border:1px solid var(--line); border-radius:14px; }
  .lg-table{ width:100%; border-collapse:collapse; font-size:15px; line-height:1.5; min-width:560px; }
  .lg-table th{ text-align:left; background:var(--soft); font-weight:700; padding:10px 14px; border-bottom:1px solid var(--line); white-space:nowrap; }
  .lg-table td{ padding:10px 14px; border-bottom:1px solid var(--line); vertical-align:top; } .lg-table tr:last-child td{ border-bottom:0; }
  .lg-table code{ font:13.5px/1.3 ui-monospace,Menlo,Consolas,monospace; background:var(--soft); padding:1px 6px; border-radius:6px; }

  .lg-foot{ margin-top:34px; padding-top:22px; border-top:1px solid var(--line); display:flex; flex-wrap:wrap; gap:12px 22px; align-items:center; justify-content:space-between; color:var(--muted); font-size:14.5px; }
  .lg-foot p{ margin:0; }
  .lg-btn{ display:inline-flex; align-items:center; min-height:44px; text-decoration:none; padding:10px 20px 9px; border-radius:14px; font:700 17px/1.1 'Baloo 2',sans-serif; letter-spacing:.03em; color:#fff; background:linear-gradient(180deg,#f9a544,var(--orange)); box-shadow:0 4px 0 var(--orange-lip); border:0; cursor:pointer; }
  .lg-btn.ghost{ background:#fff; color:var(--ink); border:2px solid var(--line); box-shadow:0 4px 0 #e3ebf3; }
  .lg-site-foot{ max-width:1160px; margin:0 auto; padding:0 20px 44px; color:var(--muted); font-size:14px; display:flex; flex-wrap:wrap; gap:8px 20px; }
  .lg-site-foot a{ color:var(--muted); }

  @media (max-width:940px){
    .lg-wrap{ grid-template-columns:minmax(0,1fr); gap:20px; padding-top:20px; }
    .lg-side{ position:static; max-height:none; overflow:visible; padding:0; }
    .lg-docs{ display:flex; gap:8px; overflow-x:auto; margin:0 0 16px; padding:2px 2px 6px; -webkit-overflow-scrolling:touch; }
    .lg-docs a{ white-space:nowrap; border:2px solid var(--line); border-radius:999px; background:#fff; padding:8px 16px; margin:0; border-left-width:2px; min-height:44px; display:inline-flex; align-items:center; }
    .lg-docs a[aria-current="page"]{ border-color:var(--orange); }
    .lg-side>h2{ display:none; }
    .lg-toc{ background:#fff; border:2px solid var(--line); border-radius:16px; padding:12px 16px; }
    .lg-toc summary{ margin:0; min-height:28px; } .lg-toc[open] summary{ margin-bottom:10px; }
    .lg-main{ border-radius:22px; padding:26px 20px 24px; } .lg-main h1{ font-size:31px; }
    .lg-body h2{ font-size:22px; } .lg-anchor{ display:none; }
  }
  @media (prefers-reduced-motion:reduce){ html{ scroll-behavior:auto; } }
  @media print{
    body{ background:#fff; font-size:12pt; } .lg-top,.lg-side,.lg-site-foot,.lg-foot .lg-btn,.lg-anchor{ display:none !important; }
    .lg-wrap{ display:block; padding:0; } .lg-main{ border:0; box-shadow:none; padding:0; border-radius:0; }
    .lg-summary,.lg-note,.lg-warn,.lg-ok{ break-inside:avoid; } .lg-body h2{ break-after:avoid; }
    a{ color:#000; text-decoration:none; } .lg-scroll{ overflow:visible; border:0; } .lg-table{ min-width:0; }
  }
</style>
</head>
<body>
<header class="lg-top">
  <div class="lg-top-in">
    <a class="brand" href="{{ url('/') }}" aria-label="TaraBasa AI home">TaraBasa<span>AI</span></a>
    <nav aria-label="Site">
      <a href="{{ url('/') }}">Home</a>
      <a href="{{ route('how-it-works') }}">How TaraBasa decides</a>
      <a href="{{ route('login') }}">Log in</a>
    </nav>
  </div>
</header>

<div class="lg-wrap">
  <aside class="lg-side" aria-label="Legal documents">
    <h2>Legal</h2>
    <ul class="lg-docs">
      @foreach (config('legal.docs') as $key => $d)
        <li><a href="{{ route($d['route']) }}" @if ($key === $doc) aria-current="page" @endif>{{ $d['title'] }}</a></li>
      @endforeach
    </ul>
    @if (!empty($sections))
      <details class="lg-toc" id="lgToc" open>
        <summary>On this page</summary>
        <ol>
          @foreach ($sections as $id => $label)
            <li><a href="#{{ $id }}">{{ $label }}</a></li>
          @endforeach
        </ol>
      </details>
    @endif
  </aside>

  <main class="lg-main" id="top">
    <h1>{{ $title }}</h1>
    <ul class="lg-meta">
      <li><b>Last updated</b> {{ config('legal.updated') }}</li>
      <li><b>Version</b> {{ config('legal.version') }}</li>
    </ul>

    @hasSection('summary')
      <div class="lg-summary">
        <h2>The short version</h2>
        @yield('summary')
      </div>
    @endif

    <div class="lg-body">
      @yield('body')
    </div>

    <div class="lg-foot">
      <p>{{ config('legal.operator') }}@if (config('legal.operator_detail')), {{ config('legal.operator_detail') }}@endif. TaraBasa AI is a student capstone project.</p>
      <span style="display:flex;gap:10px;flex-wrap:wrap">
        <button type="button" class="lg-btn ghost" onclick="window.print()">Print this page</button>
        <a class="lg-btn" href="{{ url('/') }}">Back to the home page</a>
      </span>
    </div>
  </main>
</div>

<footer class="lg-site-foot">
  @foreach (config('legal.docs') as $d)
    <a href="{{ route($d['route']) }}">{{ $d['title'] }}</a>
  @endforeach
  <a href="{{ route('how-it-works') }}">How TaraBasa decides</a>
</footer>

<script>
  (function () {
    // Copyable links on the headings, and the "On this page" list following the reader.
    var heads = document.querySelectorAll('.lg-body h2[id]');
    heads.forEach(function (h) {
      var a = document.createElement('a');
      a.className = 'lg-anchor'; a.href = '#' + h.id; a.setAttribute('aria-label', 'Link to this section'); a.textContent = '#';
      h.appendChild(a);
    });
    var toc = document.getElementById('lgToc');
    if (toc && window.matchMedia('(max-width: 940px)').matches) { toc.removeAttribute('open'); }
    if (!toc || !('IntersectionObserver' in window)) { return; }
    var links = {};
    toc.querySelectorAll('a[href^="#"]').forEach(function (a) { links[a.getAttribute('href').slice(1)] = a; });
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting && links[e.target.id]) {
          Object.keys(links).forEach(function (k) { links[k].removeAttribute('aria-current'); });
          links[e.target.id].setAttribute('aria-current', 'true');
        }
      });
    }, { rootMargin: '-90px 0px -65% 0px', threshold: 0 });
    heads.forEach(function (h) { io.observe(h); });
  })();
</script>
</body>
</html>
