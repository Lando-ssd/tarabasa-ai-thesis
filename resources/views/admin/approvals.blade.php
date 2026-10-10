{{--
  Approvals: confirm the employee ID and school before a teacher can touch real class rosters.
  Waiting teachers can be approved (once their email is verified) or rejected with a reason the teacher will see;
  rejected teachers can be reopened. Approve and Reject each open a window that says what will happen. The server
  re-checks every rule (AdminController), so a hidden or disabled button is only a convenience.
--}}
@extends('layouts.admin-shell')

@section('title', 'Approvals | Admin | TaraBasa AI')

@section('content')
<h1>Approvals</h1>
<p class="sub">Confirm the employee ID and school before a teacher can touch real class rosters.</p>

<div class="chips" role="group" aria-label="Show" style="margin-bottom:16px">
  <a class="chip plain" href="{{ route('admin.approvals') }}" aria-pressed="{{ $tab === 'waiting' ? 'true' : 'false' }}">Waiting <span class="n">{{ $waitingCount }}</span></a>
  <a class="chip plain" href="{{ route('admin.approvals', ['tab' => 'rejected']) }}" aria-pressed="{{ $tab === 'rejected' ? 'true' : 'false' }}">Rejected <span class="n">{{ $rejectedCount }}</span></a>
</div>

@if ($teachers->isEmpty())
  <div class="card empty-hero">
    <div class="empty-ico">@include('learner._badge-icon', ['icon' => $tab === 'waiting' ? 'check-circle' : 'list-checks', 'class' => 'ico'])</div>
    <h2>{{ $tab === 'waiting' ? 'Nobody is waiting' : 'No rejected teachers' }}</h2>
    <p>{{ $tab === 'waiting' ? 'New teacher registrations show up here, and the number appears on the menu.' : 'A teacher you reject will be listed here, and can be brought back.' }}</p>
  </div>
@else
  <div class="people">
    @foreach ($teachers as $t)
      @php
          $u = $t->user;
          $verified = $u->email_verified_at !== null;
          $g = $t->grades_handled;
          $grades = $g ? \Illuminate\Support\Arr::join($g, ', ', ' and ').(count($g) === 1 ? ' only' : '') : 'Any grade';
          $name = trim($u->first_name.' '.$u->last_name);
      @endphp
      <article class="card person">
        <div class="person-main">
          <h3>{{ $name }}</h3>
          <dl class="facts">
            <dt>School</dt><dd>{{ $t->school_name }}</dd>
            <dt>Employee ID</dt><dd>{{ $t->employee_id }}</dd>
            <dt>Email</dt><dd>{{ $u->email }}</dd>
            @if ($u->contact_number)<dt>Contact</dt><dd>{{ $u->contact_number }}</dd>@endif
            <dt>Grades</dt><dd>{{ $grades }}</dd>
            <dt>Registered</dt><dd>{{ $u->created_at->timezone('Asia/Manila')->format('M j, Y') }}</dd>
            @if ($tab === 'rejected')<dt>Reason</dt><dd>{{ $t->rejection_reason ?: 'No reason was recorded.' }}</dd>@endif
          </dl>
        </div>

        <div class="person-side">
          @if ($tab === 'waiting')
            <span class="pill {{ $verified ? 'ok' : 'amber' }}">{{ $verified ? 'Email verified' : 'Email not verified' }}</span>
            @unless ($verified)
              <p class="gate">Ask the teacher to open the link we sent. Approve unlocks after that.</p>
              <form method="POST" action="{{ route('admin.users.resend-verification', $u) }}">
                @csrf
                <button type="submit" class="btn small ghost">Send the email again</button>
              </form>
            @endunless
          @else
            <span class="pill warn">Rejected</span>
          @endif
        </div>

        <div class="person-acts">
          @if ($tab === 'waiting')
            <button type="button" class="btn small green" data-open="ap-{{ $t->id }}" @disabled(! $verified)>Approve</button>
            <button type="button" class="btn small ghost danger" data-open="rj-{{ $t->id }}">Reject</button>
          @else
            <form method="POST" action="{{ route('admin.teachers.reopen', $t) }}">
              @csrf
              <button type="submit" class="btn small ghost">Reopen</button>
            </form>
          @endif
        </div>
      </article>

      @if ($tab === 'waiting')
        @push('dialogs')
          <dialog id="ap-{{ $t->id }}" class="win" aria-label="Approve {{ $name }}">
            <div class="win-in narrow">
              <header class="win-head"><div class="win-titles"><h2>Approve {{ $name }}?</h2></div><button type="button" class="x" data-close aria-label="Close">@include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])</button></header>
              <form method="POST" action="{{ route('admin.teachers.activate', $t) }}">
                @csrf
                <div class="win-body">
                  <p class="help">@include('learner._badge-icon', ['icon' => 'shield-check', 'class' => 'ico'])<span>They get access to real class rosters and learners. You can still deactivate the account later. You are approving <b>{{ $t->school_name }}</b>, employee ID <b>{{ $t->employee_id }}</b>.</span></p>
                </div>
                <div class="win-foot">
                  <button type="submit" class="btn small green">Yes, approve</button>
                  <button type="button" class="btn small ghost" data-close>Cancel</button>
                </div>
              </form>
            </div>
          </dialog>

          <dialog id="rj-{{ $t->id }}" class="win" aria-label="Reject {{ $name }}">
            <div class="win-in narrow">
              <header class="win-head"><div class="win-titles"><h2>Reject {{ $name }}?</h2></div><button type="button" class="x" data-close aria-label="Close">@include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])</button></header>
              <form method="POST" action="{{ route('admin.teachers.reject', $t) }}">
                @csrf
                <div class="win-body">
                  <div class="field">
                    <label for="why-{{ $t->id }}">Reason</label>
                    <select id="why-{{ $t->id }}" name="reason" required>
                      @foreach ($reasons as $r)<option value="{{ $r }}">{{ $r === 'Other' ? 'Other (add a short note)' : $r }}</option>@endforeach
                    </select>
                    <span class="fhint">The teacher sees this the next time they try to sign in.</span>
                  </div>
                  <div class="field">
                    <label for="note-{{ $t->id }}">Note <span class="opt">(required for Other, otherwise optional)</span></label>
                    <input type="text" id="note-{{ $t->id }}" name="note" maxlength="140" placeholder="A short note, kept in the activity log">
                  </div>
                </div>
                <div class="win-foot">
                  <button type="submit" class="btn small ghost danger">Reject</button>
                  <button type="button" class="btn small ghost" data-close>Cancel</button>
                </div>
              </form>
            </div>
          </dialog>
        @endpush
      @endif
    @endforeach
  </div>
@endif
@endsection
