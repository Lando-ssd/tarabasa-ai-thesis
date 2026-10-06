{{--
  The one look every error page shares (404, 403, 419, 429, 500, 503, 400), in the app's colours and fonts,
  with plain words and a way out. Standalone on purpose: an error page must work even when the part of
  the app that broke is the layout or the database, so it uses nothing from the app but the home link.
  It never prints the exception message: that can contain internal details.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>@yield('title') | TaraBasa AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
<noscript><link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"></noscript>
<style>
  :root{ --ink:#15283a; --muted:#587086; --line:#dbe7f3; --bg:#f5f9ff; --blue:#1c7ed6; --orange:#ee8a26; --orange-lip:#b4560b; }
  *{ box-sizing:border-box; } html,body{ margin:0; }
  body{ min-height:100vh; display:flex; align-items:safe center; justify-content:center; padding:28px 20px; background:var(--bg); color:var(--ink); font:16px/1.55 'Inter',system-ui,sans-serif; }
  .box{ width:100%; max-width:480px; text-align:center; background:#fff; border:2px solid var(--line); border-radius:28px; box-shadow:0 6px 0 #e3ebf3; padding:36px 30px 32px; }
  .brand{ font:800 28px/1 'Baloo 2',sans-serif; margin-bottom:18px; } .brand span{ color:var(--orange); }
  .code{ font:800 64px/1 'Baloo 2',sans-serif; color:var(--blue); margin:0 0 6px; letter-spacing:.02em; }
  h1{ margin:0 0 10px; font:700 28px/1.15 'Baloo 2',sans-serif; }
  p{ margin:0 0 22px; color:var(--muted); }
  .row{ display:flex; gap:12px; justify-content:center; flex-wrap:wrap; }
  a.btn, button.btn{ display:inline-block; border:0; cursor:pointer; text-decoration:none; padding:13px 24px 12px; border-radius:16px; font:700 18px/1.1 'Baloo 2',sans-serif; letter-spacing:.03em; color:#fff; background:linear-gradient(180deg,#f9a544,var(--orange)); box-shadow:0 5px 0 var(--orange-lip); }
  a.btn.ghost, button.btn.ghost{ background:#fff; color:var(--muted); border:2px solid var(--line); box-shadow:0 4px 0 #e3ebf3; }
  a:focus-visible, button:focus-visible{ outline:3px solid var(--blue); outline-offset:3px; }
</style>
</head>
<body>
<main class="box" role="main">
  <div class="brand">TaraBasa<span>AI</span></div>
  <div class="code">@yield('code')</div>
  <h1>@yield('heading')</h1>
  <p>@yield('message')</p>
  <div class="row">
    @hasSection('primary')
      @yield('primary')
    @else
      <a class="btn" href="{{ url('/') }}">Go to the home page</a>
    @endif
    <button type="button" class="btn ghost" onclick="history.length > 1 ? history.back() : location.assign('{{ url('/') }}')">Go back</button>
  </div>
</main>
</body>
</html>
