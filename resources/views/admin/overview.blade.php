{{--
  Admin Overview: what needs the Admin today (in order of importance), then the numbers (each one opens its list),
  then the latest Admin actions. A sleeping service is not on this list: only things a person has to do something about.
--}}
@extends('layouts.admin-shell')

@section('title', 'Overview | Admin | TaraBasa AI')

@section('content')
<h1>Overview</h1>
<p class="sub">What needs you today, then the numbers.</p>

<div class="card">
  <p class="card-title">Needs you</p>
  @if (empty($needs))
    <p class="card-sub" style="margin:0">Nothing needs you right now. New teacher sign ups will appear here.</p>
  @else
    <p class="card-sub">In order of importance.</p>
    <div class="list">
      @foreach ($needs as $n)
        @php
            $ico = ['blue' => '', 'amber' => 'o', 'red' => 'r'][$n['tone']] ?? '';
            $tag = $n['href'] ? 'a' : 'div';
        @endphp
        <{{ $tag }} class="item need {{ $n['tone'] }}" @if ($n['href']) href="{{ $n['href'] }}" @endif>
          <span class="item-ico {{ $ico }}">@include('learner._badge-icon', ['icon' => $n['icon'], 'class' => 'ico'])</span>
          <span class="item-body"><span class="item-title">{{ $n['title'] }}</span><span class="item-sub">{{ $n['text'] }}</span></span>
          @if ($n['href'])@include('learner._badge-icon', ['icon' => 'caret-right', 'class' => 'ico go'])@endif
        </{{ $tag }}>
      @endforeach
    </div>
  @endif
</div>

<div class="tiles tiles-gap">
  <a class="tile" href="{{ route('admin.approvals') }}"><b>{{ $waitingCount }}</b><span>Waiting for approval</span></a>
  <a class="tile" href="{{ route('admin.accounts', ['filter' => 'teachers']) }}"><b>{{ $activeTeacherCount }}</b><span>Active teachers</span></a>
  <a class="tile" href="{{ route('admin.accounts', ['filter' => 'parents']) }}"><b>{{ $parentCount }}</b><span>Parents</span></a>
  <a class="tile" href="{{ route('admin.accounts') }}"><b>{{ $totalAccountCount }}</b><span>All accounts</span></a>
</div>

<div class="card">
  <div class="head" style="margin-bottom:8px">
    <div><p class="card-title" style="margin:0">Latest admin actions</p></div>
    <a class="btn small ghost" href="{{ route('admin.log') }}">See the log</a>
  </div>
  @if ($latest->isEmpty())
    <p class="card-sub" style="margin:0">Nothing has been recorded yet. Approvals, rejections and account changes will be listed here.</p>
  @else
    <ul class="logs">
      @foreach ($latest as $a)
        @include('admin._action-line', ['a' => $a])
      @endforeach
    </ul>
  @endif
</div>
@endsection
