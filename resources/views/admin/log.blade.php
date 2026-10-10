{{--
  Activity log: every approval, rejection, reopening, activation and deactivation, with who did it and the reason or
  note they gave. Kept so the Admin's work can be audited. Names are copied into each row, so an entry still reads
  correctly after an account is deleted.
--}}
@extends('layouts.admin-shell')

@section('title', 'Activity log | Admin | TaraBasa AI')

@section('content')
<h1>Activity log</h1>
<p class="sub">Every approval, rejection and account change, with who did it. Kept so the Admin work can be audited.</p>

<div class="card">
  @if ($actions->isEmpty())
    <p class="card-sub" style="margin:0">Nothing has been recorded yet. The next approval, rejection or account change will appear here.</p>
  @else
    <ul class="logs">
      @foreach ($actions as $a)
        @include('admin._action-line', ['a' => $a])
      @endforeach
    </ul>

    @if ($actions->hasPages())
      <div class="pager">
        <span>Showing {{ $actions->firstItem() }} to {{ $actions->lastItem() }} of {{ $actions->total() }}</span>
        <span class="pager-btns">
          @if ($actions->previousPageUrl())<a class="btn small ghost" href="{{ $actions->previousPageUrl() }}">Newer</a>@endif
          @if ($actions->nextPageUrl())<a class="btn small ghost" href="{{ $actions->nextPageUrl() }}">Older</a>@endif
        </span>
      </div>
    @endif
  @endif
</div>
@endsection
