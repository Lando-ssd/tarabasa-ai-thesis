{{--
  TaraBasa's own DEMO checkout, step 2 of 2 (PAYMENTS_MODE=demo, config/payments.php): the pretend step for the way to pay that was chosen. A card shows a
  READ ONLY pretend card, a wallet shows one pretend number and an approve button, like approving in the app. There is no input box here, so a real card
  or wallet number can never be typed, and no card or wallet detail exists to be kept. "Pay (pretend)" posts to PaymentController::demoPay, which is the
  only thing that can unlock the activity. A short waiting screen is shown first (script); without script the form posts at once.
  Expects $payment (with listing.activity and learner), $methodKey, $methodName, $outcome, $outcomeName.
--}}
@extends('layouts.parent-shell')

@section('title', 'Demo checkout | TaraBasa AI')

@section('content')
@php
  $title = $payment->listing?->activity?->title ?? 'this activity';
  $first = $payment->learner?->first_name ?? 'your child';
  $amount = '&#8369;'.number_format($payment->pesos(), 2);
@endphp
<div class="pay-result">
  <div class="pay-banner">Demo mode. Pretend money only. Nothing real is charged.</div>

  <ol class="pay-steps" aria-label="Steps" id="payStepsBar">
    <li class="done">Choose</li><li class="on" aria-current="step">Pay</li><li>Wait</li><li>Result</li>
  </ol>

  <div id="payStepPanel" class="pay-result" style="max-width:none">
    <h1 class="pay-h">Pay with {{ $methodName }}</h1>

    <div class="pay-co">
      <div class="pay-co-head"><b>{{ $methodName }}</b><span class="pay-co-tag">Pretend</span></div>
      <div class="pay-co-amount">{!! $amount !!}</div>
      <p class="pay-co-meta">{{ $title }} &middot; for {{ $first }}<br>Reference {{ $payment->reference }}</p>

      @if ($methodKey === 'card')
        <div class="pay-fields">
          <div class="pay-field wide"><small>Pretend card number (read only)</small><div>&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; 4242</div></div>
          <div class="pay-field"><small>Expiry</small><div>12 / 30</div></div>
          <div class="pay-field"><small>Security code</small><div>&bull;&bull;&bull;</div></div>
        </div>
        <p class="pay-co-meta">There is nothing to type. This page cannot take a real card number.</p>
      @else
        <div class="pay-fields">
          <div class="pay-field wide"><small>Pretend {{ $methodName }} number (read only)</small><div>0917 &bull;&bull;&bull; 0000</div></div>
        </div>
        <p class="pay-co-meta">In the real {{ $methodName }} app you would approve the payment on your phone. Here it is one button, and no real wallet is touched.</p>
      @endif
    </div>

    <p class="note" style="margin:0">Demo ending: <b>{{ $outcomeName }}</b>. You chose it on the previous page.</p>

    <form method="POST" action="{{ route('parent.payments.demo.pay', $payment->reference) }}" id="payDemoForm" class="pay-actions">
      @csrf
      <input type="hidden" name="method" value="{{ $methodKey }}">
      <input type="hidden" name="outcome" value="{{ $outcome }}">
      <button type="submit" class="btn" id="payDemoGo">Pay {!! $amount !!} (pretend)</button>
      <a class="btn ghost" href="{{ route('parent.payments.demo', $payment->reference) }}">Choose another way</a>
    </form>
  </div>

  <div id="payWaitPanel" class="pay-hero" hidden>
    <div class="pay-spin" role="status" aria-label="Working"></div>
    <h1>Checking your payment</h1>
    <p>This takes a moment. Please stay on this page.</p>
  </div>
</div>
@endsection

@push('scripts')
<script>
  // A short waiting screen, so the parent sees the same steps a real checkout has. Without script the form simply posts.
  (function () {
    var form = document.getElementById('payDemoForm');
    if (!form) { return; }
    form.addEventListener('submit', function (e) {
      if (form.dataset.sent) { return; }
      e.preventDefault();
      form.dataset.sent = '1';
      document.getElementById('payDemoGo').disabled = true;
      document.getElementById('payStepPanel').hidden = true;
      document.getElementById('payWaitPanel').hidden = false;
      var bar = document.getElementById('payStepsBar');
      if (bar) { bar.innerHTML = '<li class="done">Choose</li><li class="done">Pay</li><li class="on" aria-current="step">Wait</li><li>Result</li>'; }
      setTimeout(function () { form.submit(); }, 1200);
    });
    // Back from the next page may bring this one back as it was left: show the form again.
    window.addEventListener('pageshow', function (e) {
      if (!e.persisted) { return; }
      delete form.dataset.sent;
      document.getElementById('payDemoGo').disabled = false;
      document.getElementById('payStepPanel').hidden = false;
      document.getElementById('payWaitPanel').hidden = true;
    });
  })();
</script>
@endpush
