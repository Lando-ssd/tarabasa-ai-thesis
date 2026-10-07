{{--
  The child's card, to print or save as a PDF. It carries the child's name, the Learner Code and its QR code, and
  NEVER the PIN. A teacher scans the QR with the TaraBasa scanner (Add learner) to put the child in their class; the
  child logs in with the code and the PIN. A6 size so it can be cut out and kept in a school bag.
  Expects: $learner.
--}}
@php $code = $learner->learner_code; @endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>{{ $learner->first_name }}'s learner card | TaraBasa AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Semi+Condensed:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
<noscript><link href="https://fonts.googleapis.com/css2?family=Barlow+Semi+Condensed:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"></noscript>
<style>
  :root{ --ink:#15283a; --muted:#587086; --line:#dbe7f3; --bg:#f5f9ff; --blue:#1c7ed6; --blue-ink:#0f5fae; --orange:#ee8a26; --orange-ink:#a8570b; --font-game:'Bahnschrift SemiCondensed','Bahnschrift','Barlow Semi Condensed','Arial Narrow',sans-serif; }
  *{ box-sizing:border-box; }
  html,body{ margin:0; padding:0; }
  body{ background:var(--bg); color:var(--ink); font-family:'Inter',system-ui,sans-serif; min-height:100vh; display:flex; flex-direction:column; align-items:center; gap:18px; padding:28px 16px; }
  .bar{ display:flex; gap:10px; flex-wrap:wrap; justify-content:center; }
  .btn{ display:inline-flex; align-items:center; gap:8px; padding:12px 20px 10px; border-radius:14px; border:2px solid var(--line); background:#fff; color:var(--muted); font:600 18px/1 var(--font-game); letter-spacing:.05em; text-transform:uppercase; text-decoration:none; cursor:pointer; box-shadow:0 4px 0 #e3ebf3; }
  .btn.main{ background:linear-gradient(#f9a544,#ee8a26); border-color:#d97a1a; color:#fff; box-shadow:0 4px 0 #b4560b; text-shadow:0 1px 0 rgba(0,0,0,.18); }
  .btn:focus-visible{ outline:3px solid var(--orange); outline-offset:2px; }
  .btn .ico{ width:20px; height:20px; }

  /* the card itself: A6 (105 x 148 mm) */
  .card{ width:105mm; min-height:148mm; background:#fff; border:2px solid var(--line); border-radius:6mm; padding:8mm 8mm 7mm; display:flex; flex-direction:column; align-items:center; text-align:center; box-shadow:0 18px 40px -22px rgba(15,60,110,.35); }
  .brand{ display:flex; align-items:center; gap:3mm; font:700 7.5mm/1 var(--font-game); letter-spacing:.01em; }
  .brand img{ width:9mm; height:9mm; object-fit:contain; }
  .brand span{ color:var(--orange); }
  .kind{ margin-top:2mm; font:600 3.6mm/1 var(--font-game); letter-spacing:.18em; text-transform:uppercase; color:var(--muted); }
  .who{ margin:5mm 0 1mm; font:700 8mm/1.05 var(--font-game); }
  .grade{ font-size:3.6mm; color:var(--muted); font-weight:500; }
  .qr{ margin:5mm 0 3mm; width:56mm; height:56mm; }
  .qr svg{ display:block; width:100%; height:100%; }
  .code{ font:700 9mm/1 var(--font-game); letter-spacing:.07em; color:var(--blue-ink); }
  .how{ margin:4mm 0 0; font-size:3.3mm; line-height:1.45; color:var(--ink); }
  .how b{ color:var(--blue-ink); }
  .safe{ margin-top:auto; padding-top:4mm; font-size:2.9mm; color:var(--muted); line-height:1.4; }

  @media print{
    @page{ size:A6; margin:0; }
    html,body{ background:#fff; }
    body{ padding:0; display:block; min-height:0; }
    .bar{ display:none; }
    .card{ border:0; border-radius:0; box-shadow:none; width:105mm; height:148mm; min-height:0; margin:0; page-break-after:avoid; }
    .qr svg, .qr svg *{ -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  }
</style>
</head>
<body>
  <div class="bar">
    <button type="button" class="btn main" onclick="window.print()">@include('learner._badge-icon', ['icon' => 'printer', 'class' => 'ico']) Print card</button>
    <a class="btn" href="{{ route('parent.children.index') }}">Back to My Children</a>
  </div>

  <article class="card" aria-label="Learner card for {{ $learner->first_name }}">
    <div class="brand"><img src="{{ asset('images/logo.png') }}" alt=""><div>TaraBasa<span>AI</span></div></div>
    <div class="kind">Learner card</div>
    <div class="who">{{ $learner->first_name }} {{ $learner->last_name }}</div>
    <div class="grade">{{ $learner->grade_level }}</div>
    <div class="qr">{!! \App\Support\LearnerQr::svg($code, 560) !!}</div>
    <div class="code">{{ $code }}</div>
    <p class="how"><b>Teacher:</b> open Add learner and scan this code to put {{ $learner->first_name }} in your class.<br><b>{{ $learner->first_name }}:</b> log in with this code and your PIN.</p>
    <p class="safe">The PIN is not on this card. Keep it private.</p>
  </article>
</body>
</html>
