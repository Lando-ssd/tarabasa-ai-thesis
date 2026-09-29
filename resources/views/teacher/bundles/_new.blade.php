{{-- The New bundle window. A failed form reopens it with what was typed. --}}
@php $reopen = old('form') === 'new-bundle' && $errors->any(); @endphp
<dialog id="newBundleDlg" class="win" aria-label="New bundle" @if ($reopen) data-autoopen @endif>
  <div class="win-in mid">
    <header class="win-head">
      <div class="win-titles"><h2>New bundle</h2><p class="win-meta">Drop approved activities into it, then assign it to a class.</p></div>
      <button type="button" class="x" data-close aria-label="Close">@include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])</button>
    </header>
    <div class="win-body">
      <form id="new-bundle-form" method="POST" action="{{ route('teacher.bundles.store') }}" data-busy="Creating">
        @csrf
        <input type="hidden" name="form" value="new-bundle">
        <div class="field">
          <label for="nb-name">Bundle name</label>
          <input type="text" id="nb-name" name="name" data-af placeholder="e.g. Bundle 1" value="{{ $reopen ? old('name') : '' }}" required>
          @if ($reopen) @error('name')<span class="field-error">{{ $message }}</span>@enderror @endif
        </div>
      </form>
    </div>
    <footer class="win-foot">
      <button type="submit" form="new-bundle-form" class="btn small">Create bundle</button>
      <button type="button" class="btn ghost small" data-close>Cancel</button>
    </footer>
  </div>
</dialog>
