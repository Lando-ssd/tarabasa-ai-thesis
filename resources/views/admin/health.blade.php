{{--
  System health: four plain cards (reading checker, activity generator, adaptive recommender, email sending) saying
  what each does, what happens if it is down, and whether it works right now. A service that has gone to sleep on
  free hosting is shown as "Asleep" in amber, not as an error. "Check now" first wakes the sleeping services from
  THIS browser (a request from the app server does not wake them), then asks them from the server and writes the result.
--}}
@extends('layouts.admin-shell')

@section('title', 'System health | Admin | TaraBasa AI')

@section('content')
@php
    $pills = [
        'ok' => ['ok', 'Working'], 'asleep' => ['amber', 'Asleep'], 'problem' => ['warn', 'Needs you'],
        'off' => ['mute', 'Not set up'], 'unknown' => ['mute', 'Not checked yet'], 'na' => ['mute', 'Not used here'],
    ];
@endphp
<div class="head">
  <div>
    <h1>System health</h1>
    <p class="sub">Four things the platform depends on, checked from the live server.</p>
  </div>
</div>

<div class="card now">
  <div class="now-text">
    <p class="card-title" style="margin:0 0 4px">Right now</p>
    <p class="card-sub" style="margin:0">Last checked {{ \App\Support\AdminHealth::whenText($checkedAt) }} Philippine time. It also runs by itself every time the app starts.</p>
    @if ($checkSummary)<p class="note" style="margin:6px 0 0">{{ $checkSummary }}</p>@endif
  </div>
  <form method="POST" action="{{ route('admin.service-check') }}" id="checkForm" data-wake="{{ json_encode($wakeUrls) }}">
    @csrf
    <button type="submit" class="btn small" id="checkBtn">Check now</button>
  </form>
</div>
<p class="note" id="checkNote" aria-live="polite" style="margin:8px 4px 14px" hidden></p>

<div class="svc-grid">
  @foreach ($cards as $c)
    @php [$pc, $pt] = $pills[$c['state']] ?? ['mute', $c['state']]; @endphp
    <section class="card svc {{ $c['state'] }}">
      <h3>{{ $c['title'] }} <span class="pill {{ $pc }}">{{ $pt }}</span></h3>
      <p class="svc-does">{{ $c['does'] }}</p>
      <p class="svc-now">{{ $c['headline'] }}</p>
      <p class="svc-if">{{ $c['ifdown'] }}</p>

      @if ($c['key'] === 'mail' && $c['state'] === 'problem')
        @if ($gmailConfigured)
          <p class="note" style="margin:8px 0 6px">To fix it, sign in again as the sender account. Google will show a code: copy it into <b>GMAIL_SEND_REFRESH_TOKEN</b> in the host settings, then redeploy.</p>
          <a class="btn small" href="{{ route('internal.gmail-authorize.redirect') }}">Reconnect Gmail</a>
        @else
          <p class="note" style="margin:8px 0 0">The Google client settings (GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET) are not set on this host, so it cannot be reconnected from here.</p>
        @endif
      @endif

      @if (count($c['lines']) > 1)
        <details class="svc-more"><summary>What each check found</summary><ul>@foreach ($c['lines'] as $l)<li>{{ $l }}</li>@endforeach</ul></details>
      @endif
    </section>
  @endforeach
</div>

<section class="card" style="margin-top:16px">
  <p class="card-title">Problems in the last 14 days</p>
  <p class="card-sub">When the activity generator, the reading checker or the adaptive recommender could not do what was asked, what they answered is written here (newest first). Times are Philippine time. A service that only went to sleep is not listed.</p>

  @if ($problems->isEmpty())
    <p class="note" style="margin:0">Nothing has gone wrong in the last 14 days.</p>
  @else
    <ul class="logs">
      @foreach ($problems as $f)
        <li class="logl">
          <time>{{ $f->created_at?->timezone('Asia/Manila')->format('M j, g:i A') }}</time>
          <div>
            <b>{{ \App\Models\ServiceFailure::SERVICES[$f->service] ?? $f->service }}</b>
            @if ($f->service === 'check')
              <span class="pill warn">Has problems</span>
            @else
              <span class="pill {{ $f->status && $f->status < 500 && $f->status !== 429 ? 'amber' : 'warn' }}">{{ $f->status ? 'Answered '.$f->status : 'No answer' }}</span>
            @endif
            @if ($f->trail && str_contains($f->trail, ','))<span class="reason">tried {{ count(explode(',', $f->trail)) }} times: {{ $f->trail }}</span>@endif
            <br>{{ $f->what }}
            @if ($f->body)
              <details><summary>{{ $f->service === 'check' ? 'What each check found' : 'What it sent back' }}</summary><pre>{{ $f->body }}</pre></details>
            @endif
          </div>
        </li>
      @endforeach
    </ul>
  @endif
</section>

@if ($technical)
  <details class="card tech" style="margin-top:16px">
    <summary>Technical details from the newest check</summary>
    <pre>{{ $technical }}</pre>
  </details>
@endif
@endsection

@push('scripts')
<script>
  // "Check now": wake the sleeping services from this browser first (the app server cannot wake them), then let the
  // server check them. Only each service's public /health page is asked (no key, no cookie, nothing about anyone).
  (function () {
    var form = document.getElementById('checkForm'), btn = document.getElementById('checkBtn'), note = document.getElementById('checkNote');
    if (!form || !btn) { return; }
    var urls = {};
    try { urls = JSON.parse(form.getAttribute('data-wake') || '{}'); } catch (e) {}
    var names = Object.keys(urls);

    form.addEventListener('submit', function (e) {
      if (form.getAttribute('data-woke') === '1' || names.length === 0) { return; }
      e.preventDefault();
      btn.disabled = true;
      btn.textContent = 'Waking the services...';
      note.hidden = false;
      note.textContent = 'Waking the sleeping services from this browser. This can take up to a minute. Please keep this page open.';

      var asks = names.map(function (n) {
        var ask = fetch(urls[n], { mode: 'no-cors', cache: 'no-store', credentials: 'omit', referrerPolicy: 'no-referrer' }).catch(function () {});
        var limit = new Promise(function (resolve) { setTimeout(resolve, 75000); });
        return Promise.race([ask, limit]);
      });

      Promise.all(asks).then(function () {
        note.textContent = 'Now checking them from the server...';
        form.setAttribute('data-woke', '1');
        form.submit();
      });
    });
  })();
</script>
@endpush
