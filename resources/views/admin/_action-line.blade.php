{{-- One line of the Admin's activity log: when, who did what to whom, and the reason or note they gave. --}}
<li class="logl">
  <time datetime="{{ $a->created_at->toIso8601String() }}">{{ $a->created_at->timezone('Asia/Manila')->format('M j, g:i A') }}</time>
  <div>
    <b>{{ $a->admin_name }}</b> {{ $a->phrase() }} <b>{{ $a->target_name }}</b>@if ($a->detail) ({{ $a->detail }})@endif.
    @if ($a->note)
      <span class="reason">{{ $a->action === 'rejected' ? 'Reason' : 'Note' }}: {{ $a->note }}</span>
    @endif
  </div>
</li>
