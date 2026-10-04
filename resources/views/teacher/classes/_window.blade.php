{{--
  One class's window. Views (data-goto switches between them, all inside the same window):
    learners  the roster, searchable (default)
    acts      what the class has been given, with Assign activity
    assign    pick an approved activity from a dropdown, see it, assign it to the class
    add       add a learner by the last 5 characters of their Learner Code
    edit      the class's name, section, grade and group tag
    learner-N one learner's grade history and reading level over time
  Expects: $class (with learners), $classActivities, $openClassId, $openTab, $levelInfo.
  A form that failed validation reopens its own view (old('target_class_id') and old('view')).
--}}
@php
    $c = $class;
    $past = \App\Models\SchoolClass::isYearPast($c->school_year);
    $acts = $classActivities[$c->id];
    $auto = false; $startView = 'learners';
    if ($openClassId === $c->id) { $auto = true; $startView = $openTab; }
    $isTarget = (string) old('target_class_id') === (string) $c->id;
    if ($isTarget) { $auto = true; $startView = old('view', 'learners'); }
    $nLearners = $c->learners->count();
    $meta = $c->section.' · SY '.$c->school_year.' · '.$nLearners.' '.($nLearners === 1 ? 'learner' : 'learners');
    $level = fn ($l) => $l->mastery_level ?? 'New';
    $levelPill = fn ($l) => '<span class="pill '.($l->mastery_level ? 'lv-'.strtolower($l->mastery_level) : '').'">'.e($level($l)).'</span>';
@endphp
<dialog id="class-{{ $c->id }}" class="win" data-class="{{ $c->id }}" data-grade="{{ $c->grade_level }}" aria-label="{{ $c->name }}" @if ($auto) data-autoopen data-autoview="{{ $startView }}" @endif>
  <div class="win-in">

    {{-- ---------- learners ---------- --}}
    <section class="win-view" data-view="learners" @if ($startView !== 'learners') hidden @endif>
      <header class="win-head">
        <div class="win-titles">
          <div class="tags"><span class="pill">{{ $c->grade_level }}</span>@if ($c->group_tag)<span class="pill blue">@include('learner._badge-icon', ['icon' => 'users-three', 'class' => 'ico']){{ $c->group_tag }}</span>@endif @if ($past)<span class="pill amber">Read only</span>@endif</div>
          <h2>{{ $c->name }}</h2>
          <p class="win-meta">{{ $meta }}</p>
        </div>
        <button type="button" class="x" data-close aria-label="Close">@include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])</button>
      </header>
      <div class="win-tabs">@include('teacher.classes._tabs', ['active' => 'learners', 'nLearners' => $nLearners, 'nActs' => $acts->count()])</div>
      <div class="win-body">
        <div class="toolbar">
          <div class="search">
            @include('learner._badge-icon', ['icon' => 'magnifying-glass', 'class' => 'ico'])
            <label class="sr" for="rq-{{ $c->id }}">Search this class</label>
            <input type="search" id="rq-{{ $c->id }}" placeholder="Search this class" autocomplete="off" data-filter="#roster-{{ $c->id }}">
          </div>
          @unless ($past)
            <div class="win-actions">
              <button type="button" class="btn small" data-goto="add">@include('learner._badge-icon', ['icon' => 'user-plus', 'class' => 'ico']) Add learner</button>
              <button type="button" class="btn small ghost" data-goto="edit">@include('learner._badge-icon', ['icon' => 'pencil-simple', 'class' => 'ico']) Edit class</button>
            </div>
          @endunless
        </div>
        @if ($nLearners === 0)
          <div class="card empty-hero" style="box-shadow:none">
            <div class="empty-ico">@include('learner._badge-icon', ['icon' => 'users-three', 'class' => 'ico'])</div>
            <h2>No learners in this class yet</h2>
            <p>{{ $past ? 'Nobody was recorded in this class.' : 'Use Add learner with a Learner Code to enroll them.' }}</p>
          </div>
        @else
          <div class="lgrid" id="roster-{{ $c->id }}">
            @foreach ($c->learners->sortBy('first_name') as $l)
              <button type="button" class="lrow" data-goto="learner-{{ $l->id }}" data-search="{{ strtolower($l->first_name.' '.$l->last_name.' '.$l->learner_code) }}">
                @include('teacher._avatar', ['learner' => $l])
                <span class="lname"><b>{{ $l->first_name }} {{ $l->last_name }}</b><small>{{ $l->learner_code }}</small></span>
                {!! $levelPill($l) !!}
              </button>
            @endforeach
          </div>
          <p class="note" data-empty hidden>No learner matches.</p>
        @endif
      </div>
      <footer class="win-foot"><button type="button" class="btn ghost small" data-close>Close</button></footer>
    </section>

    {{-- ---------- activities ---------- --}}
    <section class="win-view" data-view="acts" @if ($startView !== 'acts') hidden @endif>
      <header class="win-head">
        <div class="win-titles">
          <div class="tags"><span class="pill">{{ $c->grade_level }}</span>@if ($c->group_tag)<span class="pill blue">@include('learner._badge-icon', ['icon' => 'users-three', 'class' => 'ico']){{ $c->group_tag }}</span>@endif @if ($past)<span class="pill amber">Read only</span>@endif</div>
          <h2>{{ $c->name }}</h2>
          <p class="win-meta">{{ $meta }}</p>
        </div>
        <button type="button" class="x" data-close aria-label="Close">@include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])</button>
      </header>
      <div class="win-tabs">@include('teacher.classes._tabs', ['active' => 'acts', 'nLearners' => $nLearners, 'nActs' => $acts->count()])</div>
      <div class="win-body">
        <div class="toolbar">
          <span class="note" style="flex:1">{{ $acts->isEmpty() ? '' : $acts->count().' '.($acts->count() === 1 ? 'activity' : 'activities').' for this class.' }}</span>
          @unless ($past)
            <button type="button" class="btn small" data-goto="assign" data-assign-open>@include('learner._badge-icon', ['icon' => 'paper-plane-tilt', 'class' => 'ico']) Assign activity</button>
          @endunless
        </div>
        @if ($acts->isEmpty())
          <div class="card empty-hero" style="box-shadow:none">
            <div class="empty-ico">@include('learner._badge-icon', ['icon' => 'books', 'class' => 'ico'])</div>
            <h2>No activities yet</h2>
            <p>{{ $past ? 'Nothing was assigned to this class in that school year.' : 'Assign an approved activity and every learner in this class can read it.' }}</p>
          </div>
        @else
          <div class="alist">
            @foreach ($acts as $row)
              @php $a = $row['activity']; @endphp
              <div class="arow static">
                <div class="atitle"><b>{{ $a->title }}</b><span>{{ $a->grade_level }} · {{ $a->typeLabel() }} · {{ $a->word_count }} words</span></div>
                <span class="pill t-{{ strtolower($a->difficulty_tier) }}">{{ $a->difficulty_tier }}</span>
                <span class="astate">{{ $row['via'] === 'class' ? 'Given to this class' : 'Through group '.$row['tag'] }}@if ($row['on']) · {{ $row['on']->format('M j') }}@endif</span>
              </div>
            @endforeach
          </div>
        @endif
      </div>
      <footer class="win-foot"><button type="button" class="btn ghost small" data-close>Close</button></footer>
    </section>

    @unless ($past)
      {{-- ---------- assign an activity ---------- --}}
      <section class="win-view" data-view="assign" @if ($startView !== 'assign') hidden @endif>
        <header class="win-head">
          <div class="win-titles"><button type="button" class="back" data-goto="acts">@include('learner._badge-icon', ['icon' => 'caret-left', 'class' => 'ico']) {{ $c->name }}</button><h2>Assign an activity</h2><p class="win-meta">to {{ $c->name }} · {{ $c->grade_level }} · {{ $c->section }}</p></div>
          <button type="button" class="x" data-close aria-label="Close">@include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])</button>
        </header>
        <div class="win-body">
          @if ($isTarget && old('view') === 'assign' && $errors->has('activity_id'))<div class="form-error-banner" role="alert">{{ $errors->first('activity_id') }}</div>@endif
          <form id="assign-form-{{ $c->id }}" method="POST" action="{{ route('teacher.classes.assign-activity', $c) }}" data-busy="Assigning">
            @csrf
            <input type="hidden" name="form" value="class"><input type="hidden" name="target_class_id" value="{{ $c->id }}"><input type="hidden" name="view" value="assign">
            <div class="chips" style="margin-bottom:12px" role="group" aria-label="Level">
              <button type="button" class="chip plain" data-level-chip="all" aria-pressed="true">All levels</button>
              @foreach (config('activity_levels.tiers') as $t)<button type="button" class="chip plain" data-level-chip="{{ $t }}" aria-pressed="false">{{ $t }}</button>@endforeach
            </div>
            <div class="field">
              <label for="ca-{{ $c->id }}">Activity</label>
              <select id="ca-{{ $c->id }}" name="activity_id" data-af required></select>
              <span class="fhint" data-assign-hint></span>
            </div>
            <div data-preview></div>
          </form>
        </div>
        <footer class="win-foot">
          <button type="submit" form="assign-form-{{ $c->id }}" class="btn small" data-assign-go disabled>@include('learner._badge-icon', ['icon' => 'paper-plane-tilt', 'class' => 'ico']) Assign to class</button>
          <button type="button" class="btn ghost small" data-goto="acts">Cancel</button>
        </footer>
      </section>

      {{-- ---------- add a learner ---------- --}}
      <section class="win-view" data-view="add" @if ($startView !== 'add') hidden @endif>
        <header class="win-head">
          <div class="win-titles"><button type="button" class="back" data-goto="learners">@include('learner._badge-icon', ['icon' => 'caret-left', 'class' => 'ico']) {{ $c->name }}</button><h2>Add a learner</h2><p class="win-meta">to {{ $c->name }} · {{ $c->grade_level }} · {{ $c->section }}</p></div>
          <button type="button" class="x" data-close aria-label="Close">@include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])</button>
        </header>
        <div class="win-body">
          <form id="add-form-{{ $c->id }}" method="POST" action="{{ route('teacher.classes.join-learner', $c) }}" data-busy="Adding">
            @csrf
            <input type="hidden" name="form" value="class"><input type="hidden" name="target_class_id" value="{{ $c->id }}"><input type="hidden" name="view" value="add">
            <p class="note" style="margin:0 0 14px">Ask the parent for the Learner Code, like TB-12345. Every code starts with TB, so type only the last 5 characters. Pasting the whole code works too. A learner can only be in one class at a time.</p>
            <div class="field">
              <label for="code-{{ $c->id }}">Learner Code</label>
              <div class="codebox"><span class="pre">TB-</span><input type="text" id="code-{{ $c->id }}" name="learner_code" data-af placeholder="12345" maxlength="9" autocomplete="off" spellcheck="false" value="{{ $isTarget && old('view') === 'add' ? old('learner_code') : '' }}"></div>
              @if ($isTarget && old('view') === 'add') @error('learner_code')<span class="field-error">{{ $message }}</span>@enderror @endif
            </div>
          </form>
        </div>
        <footer class="win-foot">
          <button type="submit" form="add-form-{{ $c->id }}" class="btn small">Add to class</button>
          <button type="button" class="btn ghost small" data-goto="learners">Cancel</button>
        </footer>
      </section>

      {{-- ---------- edit the class ---------- --}}
      @php $editing = $isTarget && old('view') === 'edit'; @endphp
      <section class="win-view" data-view="edit" @if ($startView !== 'edit') hidden @endif>
        <header class="win-head">
          <div class="win-titles"><button type="button" class="back" data-goto="learners">@include('learner._badge-icon', ['icon' => 'caret-left', 'class' => 'ico']) {{ $c->name }}</button><h2>Edit class</h2><p class="win-meta">SY {{ $c->school_year }}. The school year cannot be changed. Create a new class for next year.</p></div>
          <button type="button" class="x" data-close aria-label="Close">@include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])</button>
        </header>
        <div class="win-body">
          <form id="edit-form-{{ $c->id }}" method="POST" action="{{ route('teacher.classes.update', $c) }}" data-busy="Saving">
            @csrf @method('PUT')
            <input type="hidden" name="form" value="class"><input type="hidden" name="target_class_id" value="{{ $c->id }}"><input type="hidden" name="view" value="edit">
            <div class="grid2">
              <div class="field"><label for="en-{{ $c->id }}">Class name</label><input type="text" id="en-{{ $c->id }}" name="name" value="{{ $editing ? old('name') : $c->name }}" required>@if ($editing) @error('name')<span class="field-error">{{ $message }}</span>@enderror @endif</div>
              <div class="field"><label for="es-{{ $c->id }}">Section</label><input type="text" id="es-{{ $c->id }}" name="section" value="{{ $editing ? old('section') : $c->section }}" required>@if ($editing) @error('section')<span class="field-error">{{ $message }}</span>@enderror @endif</div>
              <div class="field"><label for="eg-{{ $c->id }}">Grade level</label><select id="eg-{{ $c->id }}" name="grade_level" required>@foreach (['Grade 1', 'Grade 2', 'Grade 3'] as $g)<option @selected(($editing ? old('grade_level') : $c->grade_level) === $g)>{{ $g }}</option>@endforeach</select></div>
              <div class="field"><label for="et-{{ $c->id }}">Group tag <span class="opt">optional</span></label><input type="text" id="et-{{ $c->id }}" name="group_tag" value="{{ $editing ? old('group_tag') : $c->group_tag }}"></div>
            </div>
          </form>
        </div>
        <footer class="win-foot">
          <button type="submit" form="edit-form-{{ $c->id }}" class="btn small">Save changes</button>
          <button type="button" class="btn ghost small" data-goto="learners">Cancel</button>
        </footer>
      </section>
    @endunless

    {{-- ---------- one learner ---------- --}}
    @foreach ($c->learners as $l)
      @php
          $practice = $l->readingSessions->where('session_type', '!=', 'Diagnostic');
          $avg = $practice->avg('accuracy_percent');
          $hist = $l->promotionHistorySummary();
      @endphp
      <section class="win-view" data-view="learner-{{ $l->id }}" hidden>
        <header class="win-head">
          <div class="win-titles">
            <button type="button" class="back" data-goto="learners">@include('learner._badge-icon', ['icon' => 'caret-left', 'class' => 'ico']) {{ $c->name }}</button>
            <div class="tags">{!! $levelPill($l) !!}</div>
            <h2>{{ $l->first_name }} {{ $l->last_name }}</h2>
            <p class="win-meta">{{ $l->learner_code }} · {{ $l->grade_level }} · {{ $c->name }} · SY {{ $c->school_year }}</p>
          </div>
          <button type="button" class="x" data-close aria-label="Close">@include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])</button>
        </header>
        <div class="win-body">
          <div class="facts">
            <div class="fact"><b>{{ $practice->count() }}</b><span>Sessions</span></div>
            <div class="fact"><b>{{ $avg === null ? '0%' : round($avg).'%' }}</b><span>Avg score</span></div>
            <div class="fact"><b>{{ $l->readingDayStreak() }}</b><span>Days in a row</span></div>
          </div>
          <div class="block"><span class="eyebrow">Grade history</span>
            @if (! empty($hist['chain']))
              <details><summary>{{ $hist['headline'] }}</summary><ul>@foreach ($hist['chain'] as $step)<li>{{ $step }}</li>@endforeach</ul></details>
            @else
              <p>{{ $hist['headline'] }}</p>
            @endif
          </div>
          <div class="block"><span class="eyebrow">Reading level over time</span><p>{{ $l->proficiencyTrajectorySummary() }}</p></div>
        </div>
        <footer class="win-foot">
          <a class="btn small" href="{{ route('teacher.analytics.index', ['learner_id' => $l->id]) }}">@include('learner._badge-icon', ['icon' => 'chart-line-up', 'class' => 'ico']) See progress</a>
          <button type="button" class="btn ghost small" data-goto="learners">Back to class</button>
        </footer>
      </section>
    @endforeach
  </div>
</dialog>
