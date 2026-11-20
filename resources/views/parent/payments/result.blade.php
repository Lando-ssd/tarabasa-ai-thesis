{{--
  The page a Parent lands on after the provider's test page (config/payments.php), as drawn in the approved preview. It only SHOWS the result:
  the controller has just asked the provider (PaymentService::refresh), and nothing here unlocks anything. While the answer is still pending the
  page checks quietly every few seconds, and after about two minutes it says to leave the page: the activity unlocks by itself once the provider's
  signed message arrives. Expects $payment (with listing.activity and learner).
--}}
@extends('layouts.parent-shell')

@section('title', 'Payment | TaraBasa AI')

@section('content')
@php
  $title = $payment->listing?->activity?->title ?? 'this activity';
  $first = $payment->learner?->first_name ?? 'your child';
  $status = $payment->status;
  $tagClass = ['paid' => 'pay-paid', 'pending' => 'pay-pending', 'cancelled' => 'pay-cancelled', 'expired' => 'pay-expired', 'failed' => 'pay-failed'][$status] ?? 'pay-pending';
  $tagText = ['paid' => 'Paid', 'pending' => 'Pending', 'cancelled' => 'Cancelled', 'expired' => 'Expired', 'failed' => 'Not started'][$status] ?? 'Pending';
@endphp
<div class="pay-result">
  <div class="pay-banner">Test mode. Pretend money only. Nothing real was charged.</div>

  @if ($status === 'paid')
    <div class="pay-hero">
      <div class="pay-mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7"/></svg></div>
      <h1>Unlocked for {{ $first }}</h1>
      <p>{{ $title }} is ready to read. This was a test payment, so no real money was charged.</p>
    </div>
  @elseif ($status === 'pending')
    <div class="pay-hero" id="payWaiting">
      <div class="pay-spin" aria-hidden="true"></div>
      <h1>Checking your payment</h1>
      <p>This takes a few seconds. Please do not press back.</p>
    </div>
    <div class="pay-note" id="payWaitNote" aria-live="polite">Nothing is unlocked yet. The activity unlocks when the payment provider confirms the payment.</div>
  @elseif ($status === 'cancelled')
    <div class="pay-hero">
      <div class="pay-mark neutral" aria-hidden="true">&times;</div>
      <h1>You cancelled</h1>
      <p>Nothing was unlocked and nothing was charged.</p>
    </div>
  @elseif ($status === 'expired')
    <div class="pay-hero">
      <div class="pay-mark neutral" aria-hidden="true">!</div>
      <h1>The payment request ran out of time</h1>
      <p>Nothing was unlocked and nothing was charged. You can start again.</p>
    </div>
  @else
    <div class="pay-hero">
      <div class="pay-mark bad" aria-hidden="true">!</div>
      <h1>The payment could not be started</h1>
      <p>Nothing was unlocked and nothing was charged. Please try again in a moment.</p>
    </div>
  @endif

  <div class="card">
    <dl class="pay-record">
      <dt>Status</dt><dd><span class="tag {{ $tagClass }}">{{ $tagText }}</span></dd>
      <dt>Activity</dt><dd>{{ $title }}</dd>
      <dt>For</dt><dd>{{ $first }}</dd>
      <dt>Paid with</dt><dd>{{ $payment->methodLabel() }}</dd>
      <dt>Amount</dt><dd>&#8369;{{ number_format($payment->pesos(), 2) }} (test)</dd>
      <dt>Reference</dt><dd>{{ $payment->reference }}</dd>
    </dl>
  </div>

  <div class="pay-actions">
    @if ($status === 'pending')
      <a class="btn ghost" href="{{ url()->current() }}" id="payCheckAgain" hidden>Check again</a>
    @endif
    <a class="btn {{ $status === 'paid' ? '' : 'blue' }}" href="{{ route('parent.repository.index', ['learner_id' => $payment->learner_id]) }}">{{ $status === 'paid' ? 'Back to activities' : ($status === 'pending' ? 'Leave this page and go to activities' : 'Back to activities') }}</a>
    <a class="btn ghost" href="{{ route('parent.payments.index') }}">See payment history</a>
  </div>
</div>
@endsection

@if ($payment->status === 'pending')
@push('scripts')
<script>
  // While the answer is pending, ask quietly every few seconds. When it changes, show the new page. After about two minutes, say what is going on.
  (function () {
    var url = @json(route('parent.payments.status', $payment->reference));
    var tries = 0;
    var maxTries = @json((int) config('payments.poll_tries', 40));
    var gap = @json((int) config('payments.poll_seconds', 3) * 1000);
    function giveUp() {
      var note = document.getElementById('payWaitNote');
      if (note) { note.textContent = 'Still checking. You can leave this page. If you paid, the activity unlocks as soon as the payment provider confirms it.'; }
      var again = document.getElementById('payCheckAgain');
      if (again) { again.hidden = false; }
    }
    function ask() {
      tries += 1;
      fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) {
          if (d && d.status && d.status !== 'pending') { window.location.reload(); return; }
          if (tries >= maxTries) { giveUp(); return; }
          setTimeout(ask, gap);
        })
        .catch(function () { if (tries >= maxTries) { giveUp(); } else { setTimeout(ask, gap); } });
    }
    setTimeout(ask, 2500);
  })();
</script>
@endpush
@endif
