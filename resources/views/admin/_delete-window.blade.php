{{--
  The window that opens from "Delete account" on the Accounts list. One window per account. Needs: $u (the User), $eraseReasons.
  The server re-checks everything (AdminController::deleteAccount, DataErasureService): a reason is required, "Other" needs a note, an Admin
  account can never be deleted, a Teacher whose released learners are still unclaimed is refused, and the Activity log keeps the reason but not the name.
--}}
@php($dn = trim($u->first_name.' '.$u->last_name))
<dialog id="dl-{{ $u->id }}" class="win" aria-label="Delete the account of {{ $dn }}">
  <div class="win-in narrow">
    <header class="win-head"><div class="win-titles"><h2>Delete {{ $dn }}'s account?</h2></div><button type="button" class="x" data-close aria-label="Close">@include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])</button></header>
    <form method="POST" action="{{ route('admin.users.delete', $u) }}">
      @csrf
      <div class="win-body">
        <p class="help">@include('learner._badge-icon', ['icon' => 'warning-circle', 'class' => 'ico'])<span>This <b>cannot be undone</b>. The account and its sign ins are deleted.
          @if ($u->user_type === 'Parent') Children who have no other guardian are deleted too, with every reading record.
          @else Their classes are deleted; the learners stay with their parents. The activities they wrote stay, without their name. @endif
          The Activity log keeps your name and the reason, but not theirs.</span></p>
        <div class="field">
          <label for="dl-why-{{ $u->id }}">Why?</label>
          <select id="dl-why-{{ $u->id }}" name="reason" required>
            @foreach ($eraseReasons as $r)<option value="{{ $r }}">{{ $r === 'Other' ? 'Other (add a short note)' : $r }}</option>@endforeach
          </select>
        </div>
        <div class="field">
          <label for="dl-note-{{ $u->id }}">Note <span class="opt">(required for Other, otherwise optional)</span></label>
          <input type="text" id="dl-note-{{ $u->id }}" name="note" maxlength="140" placeholder="A short note, kept in the activity log">
        </div>
      </div>
      <div class="win-foot">
        <button type="submit" class="btn small danger-fill">Yes, delete the account</button>
        <button type="button" class="btn small ghost" data-close>Cancel</button>
      </div>
    </form>
  </div>
</dialog>
