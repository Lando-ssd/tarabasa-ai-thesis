{{--
  Alerts (Teacher Actor Prompt Step 11, revised): who needs attention, why, and what to do about it.

  Each alert names the evidence (three readings under 70 percent in five days, the words missed),
  suggests ONE step taken from the teacher's own approved activities, and offers one click to act:
  "Assign" gives that activity to that learner, "Open progress" goes to the learner, "Mark handled"
  hides the alert until the learner's readings change. Good news is flagged too: three readings at
  90 percent or above means ready to move up. The routine session summaries are still here,
  folded away at the bottom. The alerts are worked out from the readings each time (App\Services\
  TeacherAlerts), so nothing here can be out of date.
--}}
@extends('layouts.teacher-shell')

@section('title', 'Alerts | TaraBasa AI')

@php
    use App\Models\Notification;
    use App\Support\ReadingLevel;

    $chips = ['all' => 'All', 'support' => 'Needs support', 'up' => 'Moving up', 'quiet' => 'Quiet'];
    $sections = ['support' => 'Needs support now', 'up' => 'Moving up', 'quiet' => 'Quiet'];
    $style = ['support' => ['need', 'attention', 'warning-circle'], 'up' => ['up', 'summary', 'trend-up'], 'quiet' => ['quiet', 'level', 'moon']];
    $pillFor = ['support' => 'warn', 'up' => 'ok', 'quiet' => 'amber'];
    $unreadRoutine = $routine->where('is_read', false)->count();
@endphp

@section('content')
<div class="head">
  <div>
    <h1>Alerts</h1>
    <p class="sub">Who needs your attention, why, and what you could do about it.</p>
  </div>
</div>

<div class="chips" style="margin-bottom:10px" role="group" aria-label="Filter">
  @foreach ($chips as $key => $label)
    <a class="chip plain" href="{{ route('teacher.notifications.index', $key === 'all' ? [] : ['filter' => $key]) }}" aria-pressed="{{ $filter === $key ? 'true' : 'false' }}">{{ $label }}<span class="n">{{ $counts[$key] }}</span></a>
  @endforeach
</div>

@if ($alerts->isEmpty())
  <div class="card empty-hero" style="margin-top:12px">
    <div class="empty-ico">@include('learner._badge-icon', ['icon' => 'check-circle', 'class' => 'ico'])</div>
    <h2>{{ $counts['all'] === 0 ? 'Nothing needs your attention' : 'Nothing in this list' }}</h2>
    <p>{{ $counts['all'] === 0 ? 'Alerts appear here when a learner needs support, is ready to move up, or has gone quiet. Right now everyone is on track, or has not read yet.' : 'Choose another filter to see the rest.' }}</p>
  </div>
@else
  @foreach ($sections as $kind => $heading)
    @php $rows = $alerts->where('kind', $kind); @endphp
    @continue($rows->isEmpty())
    <div class="day">{{ $heading }}</div>
    @foreach ($rows as $a)
      @php
          [$cardClass, $icoClass, $icon] = $style[$kind];
          $l = $a['learner'];
          $sg = $a['suggestion'] ?? null;
          $card = $sg['card'] ?? null;
      @endphp
      <article class="acard {{ $cardClass }}">
        <span class="notif-ico {{ $icoClass }}">@include('learner._badge-icon', ['icon' => $icon, 'class' => 'ico'])</span>
        <div class="grow">
          <b>{{ $a['title'] }}</b>
          <div class="chipsrow">
            <span class="pill {{ ReadingLevel::bandClass($a['band']) }}">{{ ReadingLevel::bandLabel($a['band']) }}</span>
            @if ($a['class'])<span class="pill">{{ $a['class']->name }}</span>@endif
            <span class="pill {{ $pillFor[$kind] }}">{{ $a['evidence'] }}</span>
          </div>
          <p class="why">{{ $a['why'] }}</p>
          @if (! empty($a['pattern']))<p class="patline">{{ $a['pattern'] }}</p>@endif

          @if ($kind === 'support' && $sg)
            <div class="sugline">@include('learner._badge-icon', ['icon' => 'lightbulb', 'class' => 'ico'])
              <span>
                @if ($card)
                  Try the <b>{{ $sg['tier'] }}</b> activity <b>{{ $card['activity']->title }}</b>@if ($card['code']) ({{ $card['code'] }}, {{ lcfirst($card['skill']) }})@endif. {{ $sg['tip'] }}
                @else
                  You have no approved {{ strtolower($sg['tier']) }} activity left for {{ $l->first_name }}. <a href="{{ route('teacher.activities.index', ['generate' => 1]) }}">Generate one</a>. {{ $sg['tip'] }}
                @endif
              </span>
            </div>
          @elseif ($kind === 'up' && $sg)
            <div class="sugline">@include('learner._badge-icon', ['icon' => 'lightbulb', 'class' => 'ico'])
              <span>
                @if ($card)
                  @if (($a['variant'] ?? '') === 'moved')Give {{ $l->first_name }} a <b>{{ $sg['tier'] }}</b> activity for the new group, for example <b>{{ $card['activity']->title }}</b>@else Offer a <b>{{ $sg['tier'] }}</b> activity next, for example <b>{{ $card['activity']->title }}</b>@endif @if ($card['code']) ({{ $card['code'] }}, {{ lcfirst($card['skill']) }})@endif.
                @else
                  You have no approved {{ strtolower($sg['tier']) }} activity left for {{ $l->first_name }}. <a href="{{ route('teacher.activities.index', ['generate' => 1]) }}">Generate one</a>.
                @endif
              </span>
            </div>
          @endif

          <div class="acts">
            @if ($card && $teacher->status === 'Active')
              <form method="POST" action="{{ route('teacher.alerts.assign', $l) }}" data-busy="Assigning">@csrf
                <input type="hidden" name="kind" value="{{ $kind }}"><input type="hidden" name="activity_id" value="{{ $card['activity']->id }}">
                <button type="submit" class="btn small">{{ $kind === 'support' ? 'Assign easier activity' : 'Assign next level' }}</button>
              </form>
            @endif
            <a class="btn small ghost" href="{{ route('teacher.analytics.index', ['mode' => 'learner', 'learner_id' => $l->id]) }}">Open progress</a>
            <form method="POST" action="{{ route('teacher.alerts.handled', $l) }}">@csrf
              <input type="hidden" name="kind" value="{{ $kind }}">
              <button type="submit" class="btn small ghost">Mark handled</button>
            </form>
          </div>
        </div>
      </article>
    @endforeach
  @endforeach
@endif

{{-- The routine messages: a summary of each reading, a confirmed level. Folded away. --}}
@if ($routine->isNotEmpty())
  <details class="rtn" @if (request()->boolean('summaries')) open @endif>
    <summary>{{ $routine->count() }} routine {{ $routine->count() === 1 ? 'message' : 'messages' }}@if ($unreadRoutine > 0) · {{ $unreadRoutine }} unread @endif · Show</summary>
    @if ($unreadCount > 0)
      <form method="POST" action="{{ route('notifications.mark-all-read') }}" style="margin:0 0 10px">@csrf<button type="submit" class="btn small ghost">Mark all as read</button></form>
    @endif
    <div class="list">
      @foreach ($routine as $n)
        @php
            $attention = $n->type === Notification::TYPE_NEEDS_ATTENTION;
            $kind = $attention ? 'attention' : ($n->type === Notification::TYPE_LEVEL_CONFIRMED ? 'level' : 'summary');
            $icon = $attention ? 'warning-circle' : ($kind === 'level' ? 'trend-up' : 'check-circle');
            $title = $attention ? 'Low score' : ($kind === 'level' ? 'Level confirmed' : 'Session summary');
        @endphp
        <div class="notif {{ ! $n->is_read ? 'unread' : '' }}" style="margin-bottom:8px">
          <span class="notif-ico {{ $kind }}">@include('learner._badge-icon', ['icon' => $icon, 'class' => 'ico'])</span>
          <span class="notif-text"><b>{{ $title }}</b><span class="d">{{ $n->message }}</span></span>
          <span class="notif-side">
            <span>@unless ($n->is_read)<span class="new">New</span> @endunless{{ $n->timestamp->format($n->timestamp->isToday() || $n->timestamp->isYesterday() ? 'g:i A' : 'M j, g:i A') }}</span>
            <span class="acts">
              @unless ($n->is_read)
                <form method="POST" action="{{ route('notifications.read', $n) }}">@csrf<button type="submit" class="btn small ghost">Mark read</button></form>
              @endunless
            </span>
          </span>
        </div>
      @endforeach
    </div>
  </details>
@endif
@endsection
