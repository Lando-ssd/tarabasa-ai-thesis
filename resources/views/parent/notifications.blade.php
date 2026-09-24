{{--
  Alerts (Parent Actor Prompt Step 8): updates about the parent's children, in a clearly separate
  section per child, newest first, grouped by day. An unread one is a form: tapping it marks it read.
  Needs Attention is the urgent kind; everything else is a routine update.
--}}
@extends('layouts.parent-shell')

@section('title', 'Alerts | TaraBasa AI')

@section('content')
<div class="top-actions">
  <div>
    <h1>Alerts</h1>
    <p class="sub" style="margin-bottom:0">Updates about your children's reading, grouped by child.</p>
  </div>
  @if ($unreadCount > 0)
    <form method="POST" action="{{ route('notifications.mark-all-read') }}" class="mark-all-form">
      @csrf
      <button type="submit" class="btn ghost small">Mark all as read</button>
    </form>
  @endif
</div>

<div class="filters" style="margin-top:18px">
  @foreach (['all' => 'All', 'attention' => 'Needs attention', 'routine' => 'Updates'] as $key => $label)
    <a href="{{ route('parent.notifications.index', ['filter' => $key]) }}" class="chip plain {{ $filter === $key ? 'on' : '' }}" @if ($filter === $key) aria-current="true" @endif>{{ $label }}</a>
  @endforeach
</div>

@if ($byLearner->isEmpty())
  <div class="card hint">No alerts here yet. Updates about your children's reading will show up when they read.</div>
@else
  @foreach ($byLearner as $learnerName => $dayGroups)
    @php $anyNotification = $dayGroups->flatten()->first(); @endphp
    <div class="learner-block">
      <div class="learner-head">
        @if ($anyNotification?->learner)
          @include('parent._avatar', ['learner' => $anyNotification->learner])
        @endif
        <b>{{ $learnerName }}</b>
      </div>

      @foreach ($dayGroups as $label => $notifications)
        <div class="day">{{ $label }}</div>
        <div class="list">
          @foreach ($notifications as $notification)
            @php
              $isAttention = $notification->type === \App\Models\Notification::TYPE_NEEDS_ATTENTION;
              $kind = $isAttention ? 'attention' : ($notification->type === \App\Models\Notification::TYPE_LEVEL_CONFIRMED ? 'level' : 'summary');
              $icon = $isAttention ? 'warning-circle' : ($kind === 'level' ? 'trend-up' : 'check-circle');
              $title = $isAttention ? 'Needs a look' : $notification->type;
            @endphp
            @if (! $notification->is_read)
              <form method="POST" action="{{ route('notifications.read', $notification) }}" class="notif-form">
                @csrf
                <button type="submit" class="notif unread {{ $isAttention ? 'flagged' : '' }}">
                  <span class="notif-ico {{ $kind }}">@include('learner._badge-icon', ['icon' => $icon, 'class' => 'ico'])</span>
                  <span><b>{{ $title }}</b><span class="d">{{ $notification->message }}</span></span>
                  <span class="notif-meta"><span class="new">New</span><span>{{ $notification->timestamp->format('g:i A') }}</span></span>
                </button>
              </form>
            @else
              <div class="notif">
                <span class="notif-ico {{ $kind }}">@include('learner._badge-icon', ['icon' => $icon, 'class' => 'ico'])</span>
                <span><b>{{ $title }}</b><span class="d">{{ $notification->message }}</span></span>
                <span class="notif-meta"><span>{{ $notification->timestamp->format('g:i A') }}</span></span>
              </div>
            @endif
          @endforeach
        </div>
      @endforeach
    </div>
  @endforeach
@endif
@endsection
