{{--
  A Parent's test payments, newest first (config/payments.php). Every attempt is listed with its way of paying, the amount and what happened, so
  the parent can see why something is or is not unlocked. Expects $payments (with listing.activity and learner) and $paymentsOn.
--}}
@extends('layouts.parent-shell')

@section('title', 'Payment history | TaraBasa AI')

@section('content')
@php $word = \App\Services\PaymentService::word(); @endphp
<h1>Payment history</h1>
<p class="sub">{{ ucfirst($word) }} payments for shared activities. They use pretend money, so nothing real was ever charged.</p>

@if (! $paymentsOn)
  <div class="pay-banner">{{ ucfirst($word) }} payments are switched off at the moment. Paid activities use the simulated step instead.</div>
@endif

@if ($payments->isEmpty())
  <div class="card empty-hero">
    <div class="empty-ico">@include('learner._badge-icon', ['icon' => 'storefront', 'class' => 'ico'])</div>
    <h2>No payments yet</h2>
    <p>When you pay for a paid activity in {{ $word }} mode, each attempt is listed here.</p>
    <div class="actions"><a href="{{ route('parent.repository.index') }}" class="btn">Go to activities</a></div>
  </div>
@else
  <div class="pay-list">
    @foreach ($payments as $payment)
      <div class="card pay-row">
        <div>
          <b>{{ $payment->listing?->activity?->title ?? 'An activity' }}</b>
          <small>For {{ $payment->learner?->first_name ?? 'your child' }} &middot; {{ $payment->methodLabel() }} &middot; &#8369;{{ number_format($payment->pesos(), 2) }} ({{ $payment->modeWord() }})</small>
          <small>{{ $payment->reference }} &middot; {{ $payment->created_at->timezone('Asia/Manila')->format('j M Y, g:i A') }}</small>
        </div>
        <span class="tag {{ $payment->statusClass() }}">{{ $payment->statusLabel() }}</span>
      </div>
    @endforeach
  </div>
  <p class="note" style="margin-top:14px">Only the most recent 50 attempts are shown.</p>
@endif
@endsection
