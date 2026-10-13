{{--
  The window that opens from "Confirm email myself" (Approvals cards and the Accounts list). One shared copy so the
  words and the rule cannot drift apart. Needs: $u (the User), $confirmReasons. The server re-checks everything
  (AdminController::confirmEmail): the reason is required, "Other" needs a note, and the action is written to the
  Activity log with the Admin's name.
--}}
@php($cn = trim($u->first_name.' '.$u->last_name))
<dialog id="ce-{{ $u->id }}" class="win" aria-label="Confirm the email of {{ $cn }}">
  <div class="win-in narrow">
    <header class="win-head"><div class="win-titles"><h2>Confirm {{ $cn }}'s email yourself?</h2></div><button type="button" class="x" data-close aria-label="Close">@include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])</button></header>
    <form method="POST" action="{{ route('admin.users.confirm-email', $u) }}">
      @csrf
      <div class="win-body">
        <p class="help">@include('learner._badge-icon', ['icon' => 'warning-circle', 'class' => 'ico'])<span>Normally a person confirms by opening the link we email them. Only do this if you have checked that <b>{{ $u->email }}</b> really belongs to them, or their email blocks our messages. They can log in right away, and your name and reason are kept in the Activity log.</span></p>
        <div class="field">
          <label for="ce-why-{{ $u->id }}">How did you check?</label>
          <select id="ce-why-{{ $u->id }}" name="reason" required>
            @foreach ($confirmReasons as $r)<option value="{{ $r }}">{{ $r === 'Other' ? 'Other (add a short note)' : $r }}</option>@endforeach
          </select>
        </div>
        <div class="field">
          <label for="ce-note-{{ $u->id }}">Note <span class="opt">(required for Other, otherwise optional)</span></label>
          <input type="text" id="ce-note-{{ $u->id }}" name="note" maxlength="140" placeholder="A short note, kept in the activity log">
        </div>
      </div>
      <div class="win-foot">
        <button type="submit" class="btn small green">Yes, confirm the email</button>
        <button type="button" class="btn small ghost" data-close>Cancel</button>
      </div>
    </form>
  </div>
</dialog>
