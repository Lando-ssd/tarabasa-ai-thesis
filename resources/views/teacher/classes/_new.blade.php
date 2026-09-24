{{-- The New class window. A failed form reopens it with what was typed. --}}
@php
    $currentYear = \App\Models\SchoolClass::currentSchoolYear();
    $nextStart = (int) explode('-', $currentYear)[0] + 1;
    $nextYear = $nextStart.'-'.($nextStart + 1);
    $reopen = old('form') === 'new-class' && $errors->any();
@endphp
<dialog id="newClassDlg" class="win" aria-label="New class" @if ($reopen) data-autoopen @endif>
  <div class="win-in mid">
    <header class="win-head">
      <div class="win-titles"><h2>New class</h2><p class="win-meta">Create a class, then add learners with their codes.</p></div>
      <button type="button" class="x" data-close aria-label="Close">@include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])</button>
    </header>
    <div class="win-body">
      <form id="new-class-form" method="POST" action="{{ route('teacher.classes.store') }}" data-busy="Creating">
        @csrf
        <input type="hidden" name="form" value="new-class">
        <div class="grid2">
          <div class="field"><label for="nc-name">Class name</label><input type="text" id="nc-name" name="name" data-af placeholder="e.g. Sampaguita" value="{{ $reopen ? old('name') : '' }}" required>@if ($reopen) @error('name')<span class="field-error">{{ $message }}</span>@enderror @endif</div>
          <div class="field"><label for="nc-section">Section</label><input type="text" id="nc-section" name="section" placeholder="e.g. Section A" value="{{ $reopen ? old('section') : '' }}" required>@if ($reopen) @error('section')<span class="field-error">{{ $message }}</span>@enderror @endif</div>
          <div class="field"><label for="nc-grade">Grade level</label><select id="nc-grade" name="grade_level" required>@foreach (['Grade 1', 'Grade 2', 'Grade 3'] as $g)<option @selected(old('grade_level') === $g)>{{ $g }}</option>@endforeach</select></div>
          <div class="field"><label for="nc-year">School year</label>
            <select id="nc-year" name="school_year" required>
              <option value="{{ $currentYear }}" @selected(old('school_year', $currentYear) === $currentYear)>SY {{ $currentYear }} (current)</option>
              <option value="{{ $nextYear }}" @selected(old('school_year') === $nextYear)>SY {{ $nextYear }}</option>
            </select>
          </div>
        </div>
        <div class="field"><label for="nc-tag">Group tag <span class="opt">optional</span></label><input type="text" id="nc-tag" name="group_tag" placeholder="e.g. needs-phonics-support" value="{{ $reopen ? old('group_tag') : '' }}"><span class="fhint">Use the same tag on classes you want to assign to or track together.</span></div>
      </form>
    </div>
    <footer class="win-foot">
      <button type="submit" form="new-class-form" class="btn small">Create class</button>
      <button type="button" class="btn ghost small" data-close>Cancel</button>
    </footer>
  </div>
</dialog>
