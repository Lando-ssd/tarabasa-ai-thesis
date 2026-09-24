{{--
  Alerts (Teacher Actor Prompt Step 11): session results for the teacher's learners, newest first,
  grouped by day. Needs Attention is the urgent kind and stands out. Each one can be marked read
  or opened at that learner's progress. Shows the five newest; "Show older alerts" reveals the
  rest, so the screen stays one laptop window tall.
--}}
@extends('layouts.teacher-shell')

@section('title', 'Alerts | TaraBasa AI')

@php
    $showAll = request()->boolean('all');
    $limit = 5;
    $total = $groups->flatten(1)->count();
    $shown = 0;
@endphp

@section('content')
<div class="head">
  <div>
    <h1>Alerts</h1>
    <p class="sub">Session results for your learners, newest first.</p>
  </div>
  @if ($unreadCount > 0)
    <form method="POST" action="{{ route('notifications.mark-all-read') }}" class="mark-all-form" style="margin:0">@csrf<button type="submit" class="btn small ghost">Mark all as read</button></form>
  @endif
</div>

<div class="chips" style="margin-bottom:6px" role="group" aria-label="Filter">
  @foreach (['all' => 'All', 'attention' => 'Needs attention', 'routine' => 'Session summaries'] as $key => $label)
    <a class="chip plain" href="{{ route('teacher.notifications.index', ['filter' => $key]) }}" aria-pressed="{{ $filter === $key ? 'true' : 'false' }}">{{ $label }}</a>
  @endforeach
</div>

@if ($groups->isEmpty())
  <div class="card empty-hero" style="margin-top:16px">
    <div class="empty-ico">@include('learner._badge-icon', ['icon' => 'bell', 'class' => 'ico'])</div>
    <h2>No alerts yet</h2>
    <p>Session summaries for your learners show up here once they read.</p>
  </div>
@else
  @foreach ($groups as $label => $notifications)
    @php $rows = $showAll ? $notifications : $notifications->take(max(0, $limit - $shown)); $shown += $rows->count(); @endphp
    @continue($rows->isEmpty())
    <div class="day">{{ $label }}</div>
    <div class="list">
      @foreach ($rows as $n)
        @php
            $attention = $n->type === \App\Models\Notification::TYPE_NEEDS_ATTENTION;
            $kind = $attention ? 'attention' : ($n->type === \App\Models\Notification::TYPE_LEVEL_CONFIRMED ? 'level' : 'summary');
            $icon = $attention ? 'warning-circle' : ($kind === 'level' ? 'trend-up' : 'check-circle');
            $title = $attention ? 'Needs a look' : ($kind === 'level' ? 'Level confirmed' : 'Session summary');
        @endphp
        <div class="notif {{ ! $n->is_read ? 'unread' : '' }} {{ $attention && ! $n->is_read ? 'flagged' : '' }}">
          <span class="notif-ico {{ $kind }}">@include('learner._badge-icon', ['icon' => $icon, 'class' => 'ico'])</span>
          <span class="notif-text"><b>{{ $title }}</b><span class="d">{{ $n->message }}</span></span>
          <span class="notif-side">
            <span>@unless ($n->is_read)<span class="new">New</span> @endunless{{ $n->timestamp->format($n->timestamp->isToday() || $n->timestamp->isYesterday() ? 'g:i A' : 'D, g:i A') }}</span>
            <span class="acts">
              @if ($n->learner_id)<a class="btn small ghost" href="{{ route('teacher.analytics.index', ['mode' => 'learner', 'learner_id' => $n->learner_id]) }}">Progress</a>@endif
              @unless ($n->is_read)
                <form method="POST" action="{{ route('notifications.read', $n) }}">@csrf<button type="submit" class="btn small ghost">Mark read</button></form>
              @endunless
            </span>
          </span>
        </div>
      @endforeach
    </div>
  @endforeach

  @if (! $showAll && $total > $limit)
    <div style="margin-top:14px"><a class="btn small ghost" href="{{ route('teacher.notifications.index', ['filter' => $filter, 'all' => 1]) }}">Show older alerts ({{ $total - $limit }})</a></div>
  @endif
@endif
@endsection
