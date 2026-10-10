{{--
  Accounts: every teacher and parent in one list, with ONE search and ONE filter, a page at a time.
  "Verification" is the school check for teachers; "Account" is whether the person can sign in. Deactivating asks
  first (it blocks sign in at once) and keeps an optional note in the activity log.
--}}
@extends('layouts.admin-shell')

@section('title', 'Accounts | Admin | TaraBasa AI')

@section('content')
<h1>Accounts</h1>
<p class="sub">Teachers and parents. Verification is the school check; Account is whether they can sign in.</p>

<form method="GET" action="{{ route('admin.accounts') }}" class="toolbar">
  <div class="search">
    @include('learner._badge-icon', ['icon' => 'magnifying-glass', 'class' => 'ico'])
    <input type="search" name="q" value="{{ $q }}" placeholder="Search by name or email" aria-label="Search accounts" maxlength="80">
  </div>
  @if ($filter !== 'all')<input type="hidden" name="filter" value="{{ $filter }}">@endif
  <button type="submit" class="btn small ghost">Search</button>
  @if ($q !== '')<a class="btn small ghost" href="{{ route('admin.accounts', $filter !== 'all' ? ['filter' => $filter] : []) }}">Clear</a>@endif
</form>

<div class="chips" role="group" aria-label="Filter" style="margin-bottom:14px">
  @foreach (['all' => 'All', 'teachers' => 'Teachers', 'parents' => 'Parents', 'inactive' => 'Inactive'] as $key => $label)
    <a class="chip plain" href="{{ route('admin.accounts', array_filter(['filter' => $key === 'all' ? null : $key, 'q' => $q ?: null])) }}" aria-pressed="{{ $filter === $key ? 'true' : 'false' }}">{{ $label }} <span class="n">{{ $counts[$key] }}</span></a>
  @endforeach
</div>

<div class="card tcard">
  @if ($accounts->isEmpty())
    <p class="card-sub" style="margin:0;padding:6px 4px">No account matches{{ $q !== '' ? ' "'.$q.'"' : '' }}.</p>
  @else
    <div class="tw">
      <table>
        <thead><tr><th>Name</th><th>Role</th><th>Verification</th><th>Account</th><th>Joined</th><th><span class="sr">Actions</span></th></tr></thead>
        <tbody>
          @foreach ($accounts as $a)
            @php
                $name = trim($a->first_name.' '.$a->last_name);
                $ts = $a->user_type === 'Teacher' ? ($a->teacher->status ?? null) : null;
                $verifiedMail = $a->email_verified_at !== null;
            @endphp
            <tr class="{{ $a->status === 'Inactive' ? 'dim' : '' }}">
              <td>
                <b>{{ $name }}</b>
                <span class="mail">{{ $a->email }}</span>
                @unless ($verifiedMail)<span class="pill amber tinypill">Email not verified</span>@endunless
              </td>
              <td>{{ $a->user_type }}</td>
              <td>
                @if ($ts)
                  <span class="pill {{ ['Active' => 'ok', 'Pending' => 'amber', 'Rejected' => 'warn'][$ts] ?? '' }}">{{ $ts }}</span>
                @else
                  <span class="pill mute">Not needed</span>
                @endif
              </td>
              <td><span class="pill {{ $a->status === 'Active' ? 'ok' : 'mute' }}">{{ $a->status }}</span></td>
              <td class="nowrap">{{ $a->created_at->timezone('Asia/Manila')->format('M j, Y') }}</td>
              <td class="acts">
                @unless ($verifiedMail)
                  <form method="POST" action="{{ route('admin.users.resend-verification', $a) }}" style="display:inline">
                    @csrf
                    <button type="submit" class="btn tiny ghost">Send email again</button>
                  </form>
                @endunless
                @if ($a->status === 'Active')
                  <button type="button" class="btn tiny ghost danger" data-open="de-{{ $a->id }}">Deactivate</button>
                @else
                  <form method="POST" action="{{ route('admin.users.toggle-status', $a) }}" style="display:inline">
                    @csrf
                    <button type="submit" class="btn tiny green">Activate</button>
                  </form>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="pager">
      <span>Showing {{ $accounts->firstItem() }} to {{ $accounts->lastItem() }} of {{ $accounts->total() }}</span>
      <span class="pager-btns">
        @if ($accounts->previousPageUrl())<a class="btn small ghost" href="{{ $accounts->previousPageUrl() }}">Previous</a>@endif
        @if ($accounts->nextPageUrl())<a class="btn small ghost" href="{{ $accounts->nextPageUrl() }}">Next</a>@endif
      </span>
    </div>
  @endif
</div>

@foreach ($accounts as $a)
  @if ($a->status === 'Active')
    @push('dialogs')
      <dialog id="de-{{ $a->id }}" class="win" aria-label="Deactivate {{ $a->first_name }} {{ $a->last_name }}">
        <div class="win-in narrow">
          <header class="win-head"><div class="win-titles"><h2>Deactivate {{ trim($a->first_name.' '.$a->last_name) }}?</h2></div><button type="button" class="x" data-close aria-label="Close">@include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])</button></header>
          <form method="POST" action="{{ route('admin.users.toggle-status', $a) }}">
            @csrf
            <div class="win-body">
              <p class="help">@include('learner._badge-icon', ['icon' => 'warning-circle', 'class' => 'ico'])<span>They cannot sign in until you activate the account again. Nothing is deleted.</span></p>
              <div class="field">
                <label for="dn-{{ $a->id }}">Note <span class="opt">(optional)</span></label>
                <input type="text" id="dn-{{ $a->id }}" name="note" maxlength="140" placeholder="A short note, kept in the activity log">
              </div>
            </div>
            <div class="win-foot">
              <button type="submit" class="btn small ghost danger">Deactivate</button>
              <button type="button" class="btn small ghost" data-close>Cancel</button>
            </div>
          </form>
        </div>
      </dialog>
    @endpush
  @endif
@endforeach
@endsection
