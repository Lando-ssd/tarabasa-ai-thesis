{{--
  Approvals: confirm the employee ID and school before a teacher can touch real class rosters.
  Waiting teachers can be approved (once their email is verified) or rejected with a reason the teacher will see;
  rejected teachers can be reopened. A third list holds approved teachers who asked to handle another grade: the Admin
  confirms it with the school (the school head assigns who teaches which grade), then approves or declines. Approve and
  Reject each open a window that says what will happen. The server re-checks every rule (AdminController,
  GradeChangeService), so a hidden or disabled button is only a convenience.
--}}
@extends('layouts.admin-shell')

@section('title', 'Approvals | Admin | TaraBasa AI')

@section('content')
<h1>Approvals</h1>
<p class="sub">Confirm the employee ID, school and grades before a teacher can touch real class rosters.</p>

<div class="chips" role="group" aria-label="Show" style="margin-bottom:16px">
  <a class="chip plain" href="{{ route('admin.approvals') }}" aria-pressed="{{ $tab === 'waiting' ? 'true' : 'false' }}">Waiting <span class="n">{{ $waitingCount }}</span></a>
  <a class="chip plain" href="{{ route('admin.approvals', ['tab' => 'grades']) }}" aria-pressed="{{ $tab === 'grades' ? 'true' : 'false' }}">Grade requests <span class="n">{{ $gradeCount }}</span></a>
  <a class="chip plain" href="{{ route('admin.approvals', ['tab' => 'rejected']) }}" aria-pressed="{{ $tab === 'rejected' ? 'true' : 'false' }}">Rejected <span class="n">{{ $rejectedCount }}</span></a>
</div>

@if ($tab === 'grades')
  <details class="card" style="margin-bottom:16px">
    <summary style="cursor:pointer;font-weight:700">How to check a grade request</summary>
    <div style="display:grid;gap:8px;margin-top:10px;font-size:14px;line-height:1.5">
      <p style="margin:0">In a DepEd school the <b>school head</b> assigns who teaches which grade (the class program and teaching load). TaraBasa keeps every class to <b>one grade</b>, so a grade here means "this teacher may open a class for it and take in its learners". Please confirm it with the school, using the name the teacher gave, before approving.</p>
      <p style="margin:0">The curriculum is also written grade by grade (MATATAG, DepEd Order 10, s. 2024), which is why an activity or a class is only for its own grade.</p>
      <p style="margin:0">Approving changes nothing for existing classes. Declining leaves the teacher's grades as they were, and the teacher is told the reason you pick.</p>
    </div>
  </details>
@endif

@if ($teachers->isEmpty())
  <div class="card empty-hero">
    <div class="empty-ico">@include('learner._badge-icon', ['icon' => $tab === 'rejected' ? 'list-checks' : 'check-circle', 'class' => 'ico'])</div>
    <h2>{{ $tab === 'waiting' ? 'Nobody is waiting' : ($tab === 'grades' ? 'No grade requests' : 'No rejected teachers') }}</h2>
    <p>{{ $tab === 'waiting' ? 'New teacher registrations show up here, and the number appears on the menu.' : ($tab === 'grades' ? 'When an approved teacher asks to handle another grade, the request shows up here and the number appears on the menu.' : 'A teacher you reject will be listed here, and can be brought back.') }}</p>
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
            @if ($tab === 'grades')
              <dt>Handles now</dt><dd>{{ \Illuminate\Support\Arr::join($t->gradesAllowed(), ', ', ' and ') }}</dd>
              <dt>Asks to add</dt><dd><b>{{ \Illuminate\Support\Arr::join(array_values(array_diff($t->gradesPending(), $t->gradesAllowed())), ', ', ' and ') ?: 'A change of grades' }}</b></dd>
              <dt>Asked on</dt><dd>{{ $t->grades_requested_at?->timezone('Asia/Manila')->format('M j, Y') }}</dd>
              <dt>Who can confirm it</dt><dd>{{ $t->grades_request_note ?: 'No note was given.' }}</dd>
            @else
              <dt>Grades they say they handle</dt><dd>{{ $grades }}</dd>
              <dt>Registered</dt><dd>{{ $u->created_at->timezone('Asia/Manila')->format('M j, Y') }}</dd>
            @endif
            @if ($tab === 'rejected')<dt>Reason</dt><dd>{{ $t->rejection_reason ?: 'No reason was recorded.' }}</dd>@endif
          </dl>
        </div>

        <div class="person-side">
          @if ($tab === 'grades')
            <span class="pill ok">Approved teacher</span>
            <p class="gate">Check with the school head that this grade was given to them. The class program says who teaches what.</p>
          @elseif ($tab === 'waiting')
            <span class="pill {{ $verified ? 'ok' : 'amber' }}">{{ $verified ? 'Email verified' : 'Email not verified' }}</span>
            <p class="gate">Their grades are part of this check: confirm them with the school too.</p>
            @unless ($verified)
              <p class="gate">Ask the teacher to open the link we sent. Approve unlocks after that.</p>
              <form method="POST" action="{{ route('admin.users.resend-verification', $u) }}">
                @csrf
                <button type="submit" class="btn small ghost">Send the email again</button>
              </form>
              <button type="button" class="btn small ghost" data-open="ce-{{ $u->id }}">Confirm email myself</button>
            @endunless
          @else
            <span class="pill warn">Rejected</span>
          @endif
        </div>

        <div class="person-acts">
          @if ($tab === 'grades')
            <button type="button" class="btn small green" data-open="ga-{{ $t->id }}">Approve</button>
            <button type="button" class="btn small ghost danger" data-open="gd-{{ $t->id }}">Decline</button>
          @elseif ($tab === 'waiting')
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

      @if ($tab === 'grades')
        @php $asked = \Illuminate\Support\Arr::join(array_values(array_diff($t->gradesPending(), $t->gradesAllowed())), ' and ') ?: 'the change'; @endphp
        @push('dialogs')
          <dialog id="ga-{{ $t->id }}" class="win" aria-label="Approve {{ $asked }} for {{ $name }}">
            <div class="win-in narrow">
              <header class="win-head"><div class="win-titles"><h2>Approve {{ $asked }} for {{ $name }}?</h2></div><button type="button" class="x" data-close aria-label="Close">@include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])</button></header>
              <form method="POST" action="{{ route('admin.teachers.grades.approve', $t) }}">
                @csrf
                <div class="win-body">
                  <p class="help">@include('learner._badge-icon', ['icon' => 'shield-check', 'class' => 'ico'])<span>They will handle <b>{{ \Illuminate\Support\Arr::join($t->gradesPending(), ', ', ' and ') }}</b> and can open a class for each, one class per grade. Their existing classes do not change. Approve only after the school has confirmed it.</span></p>
                </div>
                <div class="win-foot">
                  <button type="submit" class="btn small green">Yes, approve</button>
                  <button type="button" class="btn small ghost" data-close>Cancel</button>
                </div>
              </form>
            </div>
          </dialog>

          <dialog id="gd-{{ $t->id }}" class="win" aria-label="Decline {{ $asked }} for {{ $name }}">
            <div class="win-in narrow">
              <header class="win-head"><div class="win-titles"><h2>Decline {{ $asked }} for {{ $name }}?</h2></div><button type="button" class="x" data-close aria-label="Close">@include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])</button></header>
              <form method="POST" action="{{ route('admin.teachers.grades.decline', $t) }}">
                @csrf
                <div class="win-body">
                  <div class="field">
                    <label for="gwhy-{{ $t->id }}">Reason</label>
                    <select id="gwhy-{{ $t->id }}" name="reason" required>
                      @foreach ($gradeReasons as $r)<option value="{{ $r }}">{{ $r === 'Other' ? 'Other (add a short note)' : $r }}</option>@endforeach
                    </select>
                    <span class="fhint">The teacher is told this, and keeps the grades they had.</span>
                  </div>
                  <div class="field">
                    <label for="gnote-{{ $t->id }}">Note <span class="opt">(required for Other, otherwise optional)</span></label>
                    <input type="text" id="gnote-{{ $t->id }}" name="note" maxlength="140" placeholder="A short note, kept in the activity log">
                  </div>
                </div>
                <div class="win-foot">
                  <button type="submit" class="btn small ghost danger">Decline</button>
                  <button type="button" class="btn small ghost" data-close>Cancel</button>
                </div>
              </form>
            </div>
          </dialog>
        @endpush
      @endif

      @if ($tab === 'waiting')
        @push('dialogs')
          @unless ($verified)
            @include('admin._confirm-email-window', ['u' => $u, 'confirmReasons' => $confirmReasons])
          @endunless

          <dialog id="ap-{{ $t->id }}" class="win" aria-label="Approve {{ $name }}">
            <div class="win-in narrow">
              <header class="win-head"><div class="win-titles"><h2>Approve {{ $name }}?</h2></div><button type="button" class="x" data-close aria-label="Close">@include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])</button></header>
              <form method="POST" action="{{ route('admin.teachers.activate', $t) }}">
                @csrf
                <div class="win-body">
                  <p class="help">@include('learner._badge-icon', ['icon' => 'shield-check', 'class' => 'ico'])<span>They get access to real class rosters and learners. You can still deactivate the account later. You are approving <b>{{ $t->school_name }}</b>, employee ID <b>{{ $t->employee_id }}</b>, who handles <b>{{ $grades }}</b>. Each class they open holds one grade only.</span></p>
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
