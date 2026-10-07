{{--
  One class's window. Views (data-goto switches between them, all inside the same window):
    learners  the roster: search, sort, Phil-IRI level, a quiet level check (default)
    groups    reading groups made automatically from levels, with suggested activities
    acts      what the class has been given, with Assign activity
    assign    pick an approved activity from a dropdown, see it, assign it to the class
    add       add a learner by the last 5 characters of their Learner Code
    edit      the class's name, section, grade and group tag
    learner-N one learner's grade history and reading level over time
  Expects: $class (with learners), $classActivities, $insights, $classes, $openClassId, $openTab, $levelInfo.
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
    $ins = $insights[$c->id];
    $others = $classes->where('id', '!=', $c->id);
@endphp
<dialog id="class-{{ $c->id }}" class="win" data-class="{{ $c->id }}" data-grade="{{ $c->grade_level }}" aria-label="{{ $c->name }}" @if ($auto) data-autoopen data-autoview="{{ $startView }}" @endif>
  <div class="win-in">

    {{-- ---------- learners ---------- --}}
    <section class="win-view" data-view="learners" @if ($startView !== 'learners') hidden @endif>
      <header class="win-head">
        <div class="win-titles">
          <div class="tags"><span class="pill">{{ $c->grade_level }}</span>@if ($c->group_tag)<span class="pill blue" title="Focus group">@include('learner._badge-icon', ['icon' => 'users-three', 'class' => 'ico']){{ $c->group_tag }}</span>@endif @if ($past)<span class="pill amber">Read only</span>@endif</div>
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
          @if ($nLearners > 1)
            <div class="sortrow">
              <label for="rs-{{ $c->id }}">Sort</label>
              <select id="rs-{{ $c->id }}" class="mini wide" data-sort="#roster-{{ $c->id }}">
                <option value="last">Last name, A to Z</option>
                <option value="first">First name, A to Z</option>
                <option value="level">Reading level</option>
                <option value="active">Recently active</option>
              </select>
            </div>
          @endif
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
            <p>{{ $past ? 'Nobody was recorded in this class.' : 'Use Add learner with a Learner Code to enroll them. You can already assign activities to this class; each learner gets them as soon as they join.' }}</p>
          </div>
        @else
          <div class="lgrid" id="roster-{{ $c->id }}" style="grid-template-columns:1fr" data-roster>
            @foreach ($c->learners->sortBy(fn ($l) => mb_strtolower($l->last_name.' '.$l->first_name)) as $l)
              @php
                  $lv = \App\Support\ReadingLevel::forAdult($l);
                  $flag = $ins['flags'][$l->id] ?? null;
                  $lastRead = $l->readingSessions->where('session_type', '!=', 'Diagnostic')->max('timestamp');
              @endphp
              <div class="lrow {{ $flag ? 'flag' : '' }}" role="button" tabindex="0" data-goto="learner-{{ $l->id }}"
                   data-search="{{ strtolower($l->first_name.' '.$l->last_name.' '.$l->learner_code) }}"
                   data-last="{{ mb_strtolower($l->last_name.' '.$l->first_name) }}" data-first="{{ mb_strtolower($l->first_name.' '.$l->last_name) }}"
                   data-level="{{ \App\Support\ReadingLevel::BAND_ORDER[$lv['band']] ?? 9 }}" data-active="{{ $lastRead ? $lastRead->timestamp : 0 }}">
                @include('teacher._avatar', ['learner' => $l])
                <span class="lname"><b>{{ $l->last_name }}, {{ $l->first_name }}</b><small>{{ $l->learner_code }}</small></span>
                <span class="lv"><span class="pill {{ $lv['class'] }}">{{ $lv['label'] }}</span>@if ($lv['step'])<small>{{ $lv['step'] }}</small>@endif</span>
                @if ($flag)
                  <div class="lflag">@include('learner._badge-icon', ['icon' => 'warning-circle', 'class' => 'ico'])<span>{{ $flag['text'] }}</span>@unless ($past)<button type="button" class="btn small ghost" data-goto="learner-{{ $l->id }}">Choose</button>@endunless</div>
                @endif
              </div>
            @endforeach
          </div>
          <p class="note" data-empty hidden>No learner matches.</p>
        @endif
      </div>
      <footer class="win-foot"><button type="button" class="btn ghost small" data-close>Close</button></footer>
    </section>

    {{-- ---------- reading groups ---------- --}}
    <section class="win-view" data-view="groups" @if ($startView !== 'groups') hidden @endif>
      <header class="win-head">
        <div class="win-titles">
          <div class="tags"><span class="pill">{{ $c->grade_level }}</span>@if ($c->group_tag)<span class="pill blue" title="Focus group">@include('learner._badge-icon', ['icon' => 'users-three', 'class' => 'ico']){{ $c->group_tag }}</span>@endif @if ($past)<span class="pill amber">Read only</span>@endif</div>
          <h2>{{ $c->name }}</h2>
          <p class="win-meta">{{ $meta }}</p>
        </div>
        <button type="button" class="x" data-close aria-label="Close">@include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])</button>
      </header>
      <div class="win-tabs">@include('teacher.classes._tabs', ['active' => 'groups', 'nLearners' => $nLearners, 'nActs' => $acts->count()])</div>
      <div class="win-body">
        @if ($isTarget && old('view') === 'groups' && $errors->has('activity_id'))<div class="form-error-banner" role="alert">{{ $errors->first('activity_id') }}</div>@endif
        @if (! $ins['hasLevels'])
          <div class="card empty-hero" style="box-shadow:none">
            <div class="empty-ico">@include('learner._badge-icon', ['icon' => 'users-three', 'class' => 'ico'])</div>
            <h2>Reading groups appear here</h2>
            <p>{{ $nLearners === 0 ? 'Add learners to this class first.' : 'Learners are placed in a group once they finish their first reading check.' }} Groups are made automatically from each learner's reading level, so nobody has to sort them by hand.</p>
          </div>
        @else
          <div class="help">@include('learner._badge-icon', ['icon' => 'info', 'class' => 'ico'])<div><b>Made for you, changeable by you.</b> Groups come from each learner's latest reading, so they update as children improve. To move a learner to another class, open them from the Learners tab. Nothing is assigned until you press Assign.</div></div>
          <div class="rg">
            @foreach (\App\Support\ReadingLevel::GROUPS as $key => $meta2)
              <div class="rgcol">
                <h4>{{ $meta2['title'] }}</h4>
                <p class="rgsub">{{ $meta2['sub'] }}</p>
                @forelse ($ins['groups'][$key] as $l)
                  @php $flag = $ins['flags'][$l->id] ?? null; @endphp
                  <div class="rgl {{ $flag ? 'flag' : '' }}">@include('teacher._avatar', ['learner' => $l]){{ $l->first_name }} {{ mb_substr($l->last_name, 0, 1) }}.@if ($flag)<small>level check</small>@endif</div>
                @empty
                  <p class="pg-empty">Nobody in this group right now.</p>
                @endforelse
                @unless ($past)
                  @if ($ins['groups'][$key]->isNotEmpty())
                    <div class="sugg-h">@include('learner._badge-icon', ['icon' => 'lightbulb', 'class' => 'ico']) Suggested for this group</div>
                    @forelse ($ins['suggestions'][$key] ?? [] as $sg)
                      @include('teacher._suggestion', ['s' => $sg, 'class' => $c, 'band' => $key, 'view' => 'groups'])
                    @empty
                      <p class="pg-empty">None of your approved activities is short enough for this group, or they have all been given. Generate Easy activities for this grade and approve them, and they will be suggested here.</p>
                    @endforelse
                  @endif
                @endunless
              </div>
            @endforeach
          </div>
        @endif
      </div>
      <footer class="win-foot"><span class="note">Suggestions match the group's reading level and the skill the adaptive engine says is next.</span><button type="button" class="btn ghost small spacer" data-close>Close</button></footer>
    </section>

    {{-- ---------- activities ---------- --}}
    <section class="win-view" data-view="acts" @if ($startView !== 'acts') hidden @endif>
      <header class="win-head">
        <div class="win-titles">
          <div class="tags"><span class="pill">{{ $c->grade_level }}</span>@if ($c->group_tag)<span class="pill blue" title="Focus group">@include('learner._badge-icon', ['icon' => 'users-three', 'class' => 'ico']){{ $c->group_tag }}</span>@endif @if ($past)<span class="pill amber">Read only</span>@endif</div>
          <h2>{{ $c->name }}</h2>
          <p class="win-meta">{{ $meta }}</p>
        </div>
        <button type="button" class="x" data-close aria-label="Close">@include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])</button>
      </header>
      <div class="win-tabs">@include('teacher.classes._tabs', ['active' => 'acts', 'nLearners' => $nLearners, 'nActs' => $acts->count()])</div>
      <div class="win-body">
        @if ($isTarget && old('view') === 'acts' && $errors->has('activity_id'))<div class="form-error-banner" role="alert">{{ $errors->first('activity_id') }}</div>@endif
        <div class="toolbar">
          <span class="note" style="flex:1">{{ $acts->isEmpty() ? '' : $acts->count().' '.($acts->count() === 1 ? 'activity' : 'activities').' for this class.' }}</span>
          @unless ($past)
            <button type="button" class="btn small" data-goto="assign" data-assign-open>@include('learner._badge-icon', ['icon' => 'paper-plane-tilt', 'class' => 'ico']) Assign activity</button>
          @endunless
        </div>
        @if ($nLearners === 0 && ! $past)
          <div class="help">@include('learner._badge-icon', ['icon' => 'info', 'class' => 'ico'])<div><b>This class has no learners yet, and you can still assign.</b> Activities you assign now reach each learner as soon as they join with their code. Nothing has to be repeated.</div></div>
        @endif
        @if ($ins['starter']->isNotEmpty() && ! $past)
          <div class="sugg-h" style="margin-top:0">@include('learner._badge-icon', ['icon' => 'lightbulb', 'class' => 'ico']) Suggested for {{ $c->grade_level }}@if ($c->group_tag), {{ $c->group_tag }} focus @endif</div>
          <div class="sugg-strip">
            @foreach ($ins['starter'] as $sg)
              @include('teacher._suggestion', ['s' => $sg, 'class' => $c, 'view' => 'acts', 'style' => 'card', 'for' => $c->grade_level.' starter'])
            @endforeach
          </div>
        @endif
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
                <span class="astate">{{ match ($row['via']) { 'class' => 'Given to this class', 'band' => 'Given to the '.strtolower(\App\Support\ReadingLevel::GROUPS[$row['band']]['title']).' group', default => 'Through focus group '.$row['tag'] } }}@if ($row['on']) · {{ $row['on']->format('M j') }}@endif</span>
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
            <div data-assign-fit></div>
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
          {{-- Scan: the camera reads each learner's QR code (on the parent's phone or the printed card) and adds them at once. --}}
          <div class="scan" data-scan data-scan-url="{{ route('teacher.classes.join-learner', $c) }}" data-done-url="{{ route('teacher.classes.index', ['school_year' => $c->school_year, 'open' => $c->id, 'tab' => 'learners']) }}" data-jsqr="{{ asset('vendor/jsQR-1.4.0.js') }}" data-class-name="{{ $c->name }}">
            <p class="note" style="margin:0 0 12px">Scan each learner's QR code, on the parent's phone or on the printed card. Scan the whole class one after another: each child is added as soon as the code is read.</p>
            <div class="scan-actions">
              <button type="button" class="btn" data-scan-start>@include('learner._badge-icon', ['icon' => 'qr-code', 'class' => 'ico']) Scan QR codes</button>
              <label class="btn ghost small scan-photo">@include('learner._badge-icon', ['icon' => 'camera', 'class' => 'ico']) Use a photo of the code<input type="file" accept="image/*" capture="environment" class="sr" data-scan-photo></label>
            </div>
            <div class="scan-stage" data-scan-stage hidden>
              <div class="scan-video">
                <video playsinline muted aria-label="Camera view" data-scan-video></video>
                <div class="scan-frame" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
              </div>
              <p class="scan-status" role="status" aria-live="polite" data-scan-status>Starting the camera...</p>
              <div class="scan-actions">
                <button type="button" class="btn ghost small" data-scan-stop>Stop camera</button>
              </div>
            </div>
            <p class="scan-msg" role="status" aria-live="polite" data-scan-msg></p>
            <ul class="scan-list" aria-label="Learners added" data-scan-list></ul>
            <button type="button" class="btn small" data-scan-done hidden>Done, show the class</button>
          </div>

          <div class="or"><span>or type the code</span></div>

          <form id="add-form-{{ $c->id }}" method="POST" action="{{ route('teacher.classes.join-learner', $c) }}" data-busy="Adding">
            @csrf
            <input type="hidden" name="form" value="class"><input type="hidden" name="target_class_id" value="{{ $c->id }}"><input type="hidden" name="view" value="add">
            <p class="note" style="margin:0 0 14px">Ask the parent for the Learner Code, like TB26-48293. Type only the last 5 characters (48293). Pasting the whole code works too. A learner can only be in one class at a time.</p>
            <div class="field">
              <label for="code-{{ $c->id }}">Learner Code</label>
              <div class="codebox"><span class="pre">TB..-</span><input type="text" id="code-{{ $c->id }}" name="learner_code" data-af placeholder="48293" maxlength="10" autocomplete="off" spellcheck="false" value="{{ $isTarget && old('view') === 'add' ? old('learner_code') : '' }}"></div>
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
              <div class="field"><label for="eg-{{ $c->id }}">Grade level</label><select id="eg-{{ $c->id }}" name="grade_level" required>@foreach (array_unique([...auth()->user()->teacher->gradesAllowed(), $c->grade_level]) as $g)<option @selected(($editing ? old('grade_level') : $c->grade_level) === $g)>{{ $g }}</option>@endforeach</select></div>
              </div>
            <div class="field"><label for="et-{{ $c->id }}">Focus group <span class="opt">optional</span></label><input type="text" id="et-{{ $c->id }}" name="group_tag" value="{{ $editing ? old('group_tag') : $c->group_tag }}"><span class="fhint">A label for what this class is working on. Give two classes the same focus to assign one activity to both at once. It does not sort learners: reading groups inside the class are made automatically from reading levels.</span></div>
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
          $lv = \App\Support\ReadingLevel::forAdult($l);
          $flag = $ins['flags'][$l->id] ?? null;
      @endphp
      <section class="win-view" data-view="learner-{{ $l->id }}" hidden>
        <header class="win-head">
          <div class="win-titles">
            <button type="button" class="back" data-goto="learners">@include('learner._badge-icon', ['icon' => 'caret-left', 'class' => 'ico']) {{ $c->name }}</button>
            <div class="tags"><span class="pill {{ $lv['class'] }}">{{ $lv['label'] }}</span>@if ($lv['step'])<span class="pill">{{ $lv['step'] }}</span>@endif</div>
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
          @php $pf = \App\Support\LearnerProfile::forTeacher($l); @endphp
          <div class="block profile">
            <span class="eyebrow">What the parent shared</span>
            <dl class="pf">
              <div><dt>How the parent describes {{ $l->first_name }}'s reading</dt><dd>{{ $pf['stage'] ?? 'Not given' }}</dd></div>
              @if ($pf['language'])<div><dt>Home language</dt><dd>{{ $pf['language'] }}</dd></div>@endif
              @if ($pf['supports'])<div><dt>What helps most</dt><dd>{{ implode(', ', $pf['supports']) }}</dd></div>@endif
              @if ($pf['interests'])<div><dt>Topics {{ $l->first_name }} likes</dt><dd>{{ implode(', ', $pf['interests']) }}</dd></div>@endif
            </dl>
            @if ($pf['answers'])
              <details class="pf-more"><summary>The parent's answers to the three questions</summary>
                <ul>@foreach ($pf['answers'] as $qa)<li>{{ $qa['question'] }} <b>{{ $qa['answer'] }}</b></li>@endforeach</ul>
              </details>
            @endif
          </div>
          <div class="block profile">
            <span class="eyebrow">Where {{ $l->first_name }} reads now</span>
            @if ($pf['check']['done'])
              <p><b>{{ $pf['check']['step'] }}</b> step, {{ $pf['check']['band'] }}. From the first reading check, then updated by each reading.</p>
            @else
              <p>The first reading check has not been done yet. It starts at {{ $pf['startsAt'] ?? 'the first step' }}, from what the parent shared, and moves up or down by itself.</p>
            @endif
            @if ($pf['mismatch'])<p class="note" style="margin-top:6px">{{ $pf['mismatch'] }}</p>@endif
            <div class="bestfit">
              <b>What suits {{ $l->first_name }} now</b>
              <span>{{ $pf['bestFit']['step'] }}: {{ $pf['bestFit']['skill'] }} ({{ $pf['bestFit']['code'] }}). {{ ucfirst($pf['bestFit']['level']) }} activities, {{ $pf['bestFit']['words'] }}.</span>
              <span>{{ $pf['bestFit']['advice'] }}</span>
            </div>
          </div>
          <div class="block"><span class="eyebrow">Grade history</span>
            @if (! empty($hist['chain']))
              <details><summary>{{ $hist['headline'] }}</summary><ul>@foreach ($hist['chain'] as $step)<li>{{ $step }}</li>@endforeach</ul></details>
            @else
              <p>{{ $hist['headline'] }}</p>
            @endif
          </div>
          <div class="block"><span class="eyebrow">Reading level over time</span><p>{{ $l->proficiencyTrajectorySummary() }}</p></div>
          @if ($flag)
            <div class="help">@include('learner._badge-icon', ['icon' => 'warning-circle', 'class' => 'ico'])<div><b>Level check.</b> {{ $flag['text'] }} You decide: give {{ $flag['direction'] === 'above' ? 'higher' : 'easier' }} activities (assign them to this class's {{ strtolower(\App\Support\ReadingLevel::GROUPS[\App\Support\ReadingLevel::groupOf($l) ?? 'instructional']['title']) }} group in Reading groups), or move {{ $l->first_name }} to another of your classes below.</div></div>
          @endif
          @unless ($past)
            @if ($others->isNotEmpty())
              <div class="block"><span class="eyebrow">Move to another class</span>
                <form method="POST" action="{{ route('teacher.classes.move-learner', [$c, $l]) }}" data-busy="Moving" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:6px">
                  @csrf
                  <input type="hidden" name="form" value="class"><input type="hidden" name="target_class_id" value="{{ $c->id }}"><input type="hidden" name="view" value="learner-{{ $l->id }}">
                  <select name="to_class_id" class="mini wide" aria-label="Class to move to">
                    @foreach ($others as $o)<option value="{{ $o->id }}">{{ $o->name }} ({{ $o->grade_level }}, {{ $o->section }})</option>@endforeach
                  </select>
                  <button type="submit" class="btn small ghost">Move</button>
                </form>
                @if ($isTarget && old('view') === 'learner-'.$l->id) @error('to_class_id')<span class="field-error">{{ $message }}</span>@enderror @endif
              </div>
            @endif
          @endunless
        </div>
        <footer class="win-foot">
          <a class="btn small" href="{{ route('teacher.analytics.index', ['learner_id' => $l->id]) }}">@include('learner._badge-icon', ['icon' => 'chart-line-up', 'class' => 'ico']) See progress</a>
          <button type="button" class="btn ghost small" data-goto="learners">Back to class</button>
        </footer>
      </section>
    @endforeach
  </div>
</dialog>
