{{--
  TaraBasa's own DEMO checkout, step 1 of 2 (PAYMENTS_MODE=demo, config/payments.php), as drawn in the approved preview "A demo checkout inside TaraBasa":
  the price, the activity, a reference, three ways to pay and, for the demo only, what should happen. It is an ordinary form with no script, and it has no
  input box of any kind, so a real card or wallet number can never be typed here. Nothing on this page unlocks anything: the unlock comes only from
  PaymentService::demoPay() after "Pay (pretend)" on the next page. Expects $payment (with listing.activity and learner), $methods, $outcomes, $method, $outcome.
--}}
@extends('layouts.parent-shell')

@section('title', 'Demo checkout | TaraBasa AI')

@section('content')
@php
  $title = $payment->listing?->activity?->title ?? 'this activity';
  $first = $payment->learner?->first_name ?? 'your child';
  $outcomeHelp = ['approve' => 'The payment goes through.', 'decline' => 'The payment is refused.', 'cancel' => 'You leave without paying.'];
@endphp
<div class="pay-result">
  <div class="pay-banner">Demo mode. Pretend money only. Nothing real is charged.</div>

  <ol class="pay-steps" aria-label="Steps">
    <li class="on" aria-current="step">Choose</li><li>Pay</li><li>Wait</li><li>Result</li>
  </ol>

  <div class="pay-co">
    <div class="pay-co-head"><b>TaraBasa demo checkout</b><span class="pay-co-tag">Pretend</span></div>
    <div class="pay-co-amount">&#8369;{{ number_format($payment->pesos(), 2) }}</div>
    <p class="pay-co-meta">{{ $title }} &middot; for {{ $first }}<br>Reference {{ $payment->reference }}</p>
  </div>

  @if ($errors->any())
    <div class="flash error">{{ $errors->first() }}</div>
  @endif

  <form method="GET" action="{{ route('parent.payments.demo.step', $payment->reference) }}" class="pay-form">
    <fieldset class="pay-opts">
      <legend>How do you want to pay?</legend>
      @foreach ($methods as $key => $label)
        <label class="pay-opt">
          <input type="radio" name="method" value="{{ $key }}" @checked($method === $key) required>
          <span><b>{{ $label }}</b><small>{{ $key === 'card' ? 'A pretend card. Nothing to type.' : 'A pretend wallet approval.' }}</small></span>
        </label>
      @endforeach
    </fieldset>

    <fieldset class="pay-test">
      <legend>Demo only: what should happen?</legend>
      <div class="pay-test-opts">
        @foreach ($outcomes as $key => $label)
          <label class="pay-opt small">
            <input type="radio" name="outcome" value="{{ $key }}" @checked($outcome === $key) required>
            <span><b>{{ $label }}</b><small>{{ $outcomeHelp[$key] }}</small></span>
          </label>
        @endforeach
      </div>
      <p class="note" style="margin:0">A real checkout would not ask this. It is here so you can see every ending.</p>
    </fieldset>

    <div class="pay-actions">
      <button type="submit" class="btn blue">Continue</button>
      <a class="btn ghost" href="{{ route('parent.payments.cancelled', $payment->reference) }}">Cancel and go back</a>
    </div>
  </form>
</div>
@endsection
