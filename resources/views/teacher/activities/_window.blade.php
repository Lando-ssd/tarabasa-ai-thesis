{{--
  One activity's window, fetched when a card opens and put inside #dlg. It holds several views
  (the details, edit, reject, assign, share) that data-goto switches between, so the teacher
  stays in one window. Every action is a real form, so it works with plain POSTs.

  The details show WHY the activity is in its level: what the level means, the word count against
  the usual range for this grade and activity type, and the features the AI used. The levels are
  this app's own (familiar words and word length), not official DepEd scores, and the AI cannot
  know what is hard for these learners, so the teacher decides.
--}}
@php
    $a = $activity;
    $id = $a->id;
    $tier = $a->difficulty_tier;
    $band = $a->levelBand();
    $fits = $a->lengthFitsLevel();
    $tiers = config('activity_levels.tiers');
    $statusPill = ['Draft' => 'amber', 'Approved' => 'ok', 'Rejected' => 'warn'][$a->status];
    $label = function ($as) {
        if ($as->learner) { return $as->learner->first_name.' '.$as->learner->last_name; }
        if ($as->schoolClass) { return $as->schoolClass->name.' (class)'; }
        return $as->group_tag.' (group)';
    };
    $tags = '<span class="pill '.$statusPill.'">'.e($a->status === 'Draft' ? 'Draft' : $a->status).'</span><span class="pill">'.e($a->typeLabel()).'</span><span class="pill t-'.strtolower($tier).'">'.e($tier).'</span>';
@endphp
<div class="win-in">

  {{-- ---------- the details ---------- --}}
  <section class="win-view" data-view="main">
    <header class="win-head">
      <div class="win-titles">
        <div class="tags">{!! $tags !!}</div>
        <h2>{{ $a->title }}</h2>
        <p class="win-meta">{{ $a->grade_level }} · {{ $a->competency_label }}@if ($a->topic) · Topic: {{ $a->topic }}@endif</p>
      </div>
      <button type="button" class="x" data-close aria-label="Close">@include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])</button>
    </header>
    <div class="win-body">
      <div class="block"><span class="eyebrow">Instructions</span><p>{{ $a->instructions }}</p></div>
      <div class="block"><span class="eyebrow">Reading text</span><div class="passage">{{ $a->passage_text }}</div></div>

      @if ($a->status === 'Draft')
        <div class="field" style="max-width:280px">
          <label for="lvSel-{{ $id }}">Approve in level</label>
          <select id="lvSel-{{ $id }}" name="level" form="approve-{{ $id }}" @disabled($locked)>
            @foreach ($tiers as $t)<option @selected($t === $tier)>{{ $t }}</option>@endforeach
          </select>
        </div>
      @elseif ($a->status === 'Approved')
        <form method="POST" action="{{ route('teacher.activities.place', $a) }}" class="field" style="max-width:280px">
          @csrf
          <label for="lvSel-{{ $id }}">Level</label>
          <select id="lvSel-{{ $id }}" name="level" data-autosubmit @disabled($locked)>
            @foreach ($tiers as $t)<option @selected($t === $tier)>{{ $t }}</option>@endforeach
          </select>
        </form>
      @endif

      <div class="why">
        <div class="why-h"><span class="eyebrow">Why {{ $tier }}</span><span class="pill t-{{ strtolower($tier) }}">{{ $tier }}</span></div>
        <p style="margin:0 0 10px">{{ $levelInfo[$tier]['long'] }}</p>
        @if ($band)
          <div class="facts">
            <div class="fact"><b>{{ $a->word_count ?? 'n/a' }}</b><span>Words in this text</span></div>
            <div class="fact"><b>{{ $band[0] }} to {{ $band[1] }}</b><span>{{ $a->grade_level }} {{ $tier }} range</span></div>
            <div class="fact {{ $fits === null ? '' : ($fits ? 'good' : 'warn') }}"><b>{{ $fits === null ? 'Unknown' : ($fits ? 'Fits' : 'Outside') }}</b><span>{{ $fits ? 'Right length' : ($fits === null ? 'No word count' : 'Not the usual length') }}</span></div>
          </div>
        @endif
        @if (! empty($a->reading_features))
          <div class="block" style="margin:0 0 8px"><span class="eyebrow">What the AI used</span>
            <div class="skill-chips" style="margin-top:6px">@foreach ($a->reading_features as $f)<span class="skill">{{ $f }}</span>@endforeach</div>
          </div>
        @endif
        @if ($a->movedByTeacher())
          <p class="note" style="margin:8px 0 0">The AI suggested <b>{{ $a->ai_difficulty_tier }}</b>. You placed it in <b>{{ $tier }}</b>. The AI is told about changes like this the next time you generate.</p>
        @endif
        <p class="note" style="margin:8px 0 0">Levels come from how familiar the words are and how long the text is. They are this app's levels, not official DepEd scores. The AI cannot know what is hard for your learners, so you decide.</p>
      </div>

      @if ($a->status === 'Approved')
        <div class="block" style="margin-top:14px"><span class="eyebrow">Assigned to</span>
          <p>@forelse ($a->assignments as $as)<b>{{ $label($as) }}</b>{{ $loop->last ? '' : ', ' }}@empty Nobody yet.@endforelse</p>
        </div>
        @if ($a->shared_to_repository && $a->repositoryListing)
          @php $ratings = $a->repositoryListing->ratings; $avg = $ratings->isNotEmpty() ? round($ratings->avg('rating'), 1) : null; @endphp
          <div class="block"><span class="eyebrow">Repository</span>
            <p><span class="pill ok">@include('learner._badge-icon', ['icon' => 'check-circle', 'class' => 'ico']) Shared</span> {{ $a->repositoryListing->price_type === 'Free' ? 'Free' : '₱'.number_format($a->repositoryListing->price) }}@if ($avg !== null) · {{ $avg }} stars ({{ $ratings->count() }} {{ $ratings->count() === 1 ? 'rating' : 'ratings' }})@else · No ratings yet @endif</p>
          </div>
        @endif
      @endif
    </div>
    <footer class="win-foot">
      @if ($locked)
        <span class="lock">@include('learner._badge-icon', ['icon' => 'lock-simple', 'class' => 'ico']) Locked until your account is Active</span>
      @elseif ($a->status === 'Draft')
        <form id="approve-{{ $id }}" method="POST" action="{{ route('teacher.activities.approve', $a) }}" style="display:contents">@csrf</form>
        <button type="submit" form="approve-{{ $id }}" class="btn small green">@include('learner._badge-icon', ['icon' => 'check', 'class' => 'ico']) Approve</button>
        <button type="button" class="btn small ghost" data-goto="edit">@include('learner._badge-icon', ['icon' => 'pencil-simple', 'class' => 'ico']) Edit</button>
        <button type="button" class="btn small ghost danger spacer" data-goto="reject">@include('learner._badge-icon', ['icon' => 'x-circle', 'class' => 'ico']) Reject</button>
      @elseif ($a->status === 'Approved')
        <button type="button" class="btn small" data-goto="assign">@include('learner._badge-icon', ['icon' => 'paper-plane-tilt', 'class' => 'ico']) Assign</button>
        @unless ($a->shared_to_repository)
          <button type="button" class="btn small ghost" data-goto="share">@include('learner._badge-icon', ['icon' => 'storefront', 'class' => 'ico']) Share to repository</button>
        @endunless
      @else
        <form method="POST" action="{{ route('teacher.activities.restore', $a) }}" style="display:contents">@csrf
          <button type="submit" class="btn small ghost">@include('learner._badge-icon', ['icon' => 'arrow-counter-clockwise', 'class' => 'ico']) Restore to review</button>
        </form>
      @endif
    </footer>
  </section>

  @if (! $locked && $a->status === 'Draft')
    {{-- ---------- edit (saving also approves it) ---------- --}}
    <section class="win-view" data-view="edit" hidden>
      <header class="win-head">
        <div class="win-titles"><button type="button" class="back" data-goto="main">@include('learner._badge-icon', ['icon' => 'caret-left', 'class' => 'ico']) Back</button><h2>Edit activity</h2><p class="win-meta">Saving also approves it in the level you choose.</p></div>
        <button type="button" class="x" data-close aria-label="Close">@include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])</button>
      </header>
      <div class="win-body">
        <form id="edit-{{ $id }}" method="POST" action="{{ route('teacher.activities.update', $a) }}" data-busy="Saving">
          @csrf @method('PUT')
          <div class="field"><label for="eTitle-{{ $id }}">Title</label><input type="text" id="eTitle-{{ $id }}" name="title" value="{{ $a->title }}" maxlength="140" required></div>
          <div class="field"><label for="eInstr-{{ $id }}">Instructions</label><textarea id="eInstr-{{ $id }}" name="instructions" maxlength="500" required>{{ $a->instructions }}</textarea></div>
          <div class="field"><label for="eText-{{ $id }}">Reading text</label><textarea id="eText-{{ $id }}" name="passage_text" style="min-height:110px" maxlength="6000" required>{{ $a->passage_text }}</textarea></div>
          <div class="field" style="max-width:280px"><label for="eLevel-{{ $id }}">Level</label><select id="eLevel-{{ $id }}" name="level">@foreach ($tiers as $t)<option @selected($t === $tier)>{{ $t }}</option>@endforeach</select></div>
        </form>
      </div>
      <footer class="win-foot">
        <button type="submit" form="edit-{{ $id }}" class="btn small green">@include('learner._badge-icon', ['icon' => 'check', 'class' => 'ico']) Save and approve</button>
        <button type="button" class="btn ghost small" data-goto="main">Cancel</button>
      </footer>
    </section>

    {{-- ---------- reject ---------- --}}
    <section class="win-view" data-view="reject" hidden>
      <header class="win-head">
        <div class="win-titles"><button type="button" class="back" data-goto="main">@include('learner._badge-icon', ['icon' => 'caret-left', 'class' => 'ico']) Back</button><h2>Reject this activity?</h2><p class="win-meta">{{ $a->title }}</p></div>
        <button type="button" class="x" data-close aria-label="Close">@include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])</button>
      </header>
      <div class="win-body"><p style="margin:0">It moves to Rejected and cannot be assigned or shared. You can restore it from the list if you change your mind.</p></div>
      <footer class="win-foot">
        <form method="POST" action="{{ route('teacher.activities.reject', $a) }}" style="display:contents">@csrf
          <button type="submit" class="btn small" style="background:linear-gradient(180deg,#e26a6a,var(--danger));box-shadow:0 4px 0 #9c2c2c, inset 0 2px 0 rgba(255,255,255,.3)">Reject</button>
        </form>
        <button type="button" class="btn ghost small" data-goto="main">Keep it</button>
      </footer>
    </section>
  @endif

  @if (! $locked && $a->status === 'Approved')
    {{-- ---------- assign: a class, a group or one learner ---------- --}}
    <section class="win-view" data-view="assign" hidden>
      <header class="win-head">
        <div class="win-titles"><button type="button" class="back" data-goto="main">@include('learner._badge-icon', ['icon' => 'caret-left', 'class' => 'ico']) Back</button><h2>Assign</h2><p class="win-meta">{{ $a->title }} · {{ $a->grade_level }} · {{ $tier }}</p></div>
        <button type="button" class="x" data-close aria-label="Close">@include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])</button>
      </header>
      <div class="win-body">
        <form id="assign-{{ $id }}" method="POST" action="{{ route('teacher.activities.assign', $a) }}" data-busy="Assigning">
          @csrf
          <div class="chips" style="margin-bottom:14px" role="group" aria-label="Assign to">
            <button type="button" class="chip plain" data-assign-type="class" aria-pressed="true">A class</button>
            <button type="button" class="chip plain" data-assign-type="group" aria-pressed="false">A group</button>
            <button type="button" class="chip plain" data-assign-type="learner" aria-pressed="false">One learner</button>
          </div>

          <div data-panel="class">
            <div class="opt-list">
              @forelse ($assignClasses as $c)
                <label class="opt-row" style="cursor:pointer"><input type="radio" name="assign_class_id" value="{{ $c->id }}" style="width:auto"><span><b>{{ $c->name }}</b><small>{{ $c->grade_level }} · {{ $c->section }}</small></span></label>
              @empty
                <p class="note">You have no classes for this school year yet.</p>
              @endforelse
            </div>
          </div>
          <div data-panel="group" hidden>
            <div class="opt-list">
              @forelse ($assignGroupTags as $tag)
                <label class="opt-row" style="cursor:pointer"><input type="radio" name="assign_group_tag" value="{{ $tag }}" style="width:auto" disabled><span><b>{{ $tag }}</b><small>Every class that uses this tag</small></span></label>
              @empty
                <p class="note">None of your classes has a group tag yet. Add one when you edit a class.</p>
              @endforelse
            </div>
          </div>
          <div data-panel="learner" hidden>
            <div class="search" style="margin-bottom:10px">
              @include('learner._badge-icon', ['icon' => 'magnifying-glass', 'class' => 'ico'])
              <label class="sr" for="alq-{{ $id }}">Search learners</label>
              <input type="search" id="alq-{{ $id }}" placeholder="Type a name or a code" autocomplete="off" data-filter="#al-{{ $id }}" data-limit="5">
            </div>
            <div class="opt-list" id="al-{{ $id }}">
              @foreach ($assignLearners as $l)
                <label class="opt-row" style="cursor:pointer" data-search="{{ strtolower($l->first_name.' '.$l->last_name.' '.$l->learner_code) }}" @if ($loop->index >= 5) hidden @endif><input type="radio" name="assign_learner_id" value="{{ $l->id }}" style="width:auto" disabled><span><b>{{ $l->first_name }} {{ $l->last_name }}</b><small>{{ $l->learner_code }} · {{ $l->schoolClass->name ?? '' }}</small></span></label>
              @endforeach
            </div>
            <p class="note" data-empty hidden>No learner matches.</p>
          </div>
        </form>
      </div>
      <footer class="win-foot">
        <button type="submit" form="assign-{{ $id }}" class="btn small" data-assign-go disabled>@include('learner._badge-icon', ['icon' => 'paper-plane-tilt', 'class' => 'ico']) Assign</button>
        <button type="button" class="btn ghost small" data-goto="main">Cancel</button>
      </footer>
    </section>

    @unless ($a->shared_to_repository)
      {{-- ---------- share to the repository ---------- --}}
      <section class="win-view" data-view="share" hidden>
        <header class="win-head">
          <div class="win-titles"><button type="button" class="back" data-goto="main">@include('learner._badge-icon', ['icon' => 'caret-left', 'class' => 'ico']) Back</button><h2>Share to repository</h2><p class="win-meta">{{ $a->title }}</p></div>
          <button type="button" class="x" data-close aria-label="Close">@include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])</button>
        </header>
        <div class="win-body">
          <form id="share-{{ $id }}" method="POST" action="{{ route('teacher.activities.share', $a) }}" data-busy="Sharing">
            @csrf
            <p class="note" style="margin:0 0 12px">Parents of other teachers' learners can unlock this. Sharing earns you 2 free generation credits, one time for each activity.</p>
            <div class="opt-list" role="radiogroup" aria-label="Price">
              <label class="opt-row" style="cursor:pointer"><input type="radio" name="price_type" value="Free" checked style="width:auto"><span><b>Free</b><small>Anyone can unlock it.</small></span></label>
              <label class="opt-row" style="cursor:pointer"><input type="radio" name="price_type" value="Paid" style="width:auto"><span><b>Paid</b><small>Set a price in pesos. Payment is simulated for now.</small></span></label>
            </div>
            <div class="field" style="margin-top:14px" data-price-field hidden><label for="sPrice-{{ $id }}">Price in pesos</label><input type="number" id="sPrice-{{ $id }}" name="price" min="1" step="1" placeholder="e.g. 25" disabled></div>
          </form>
        </div>
        <footer class="win-foot">
          <button type="submit" form="share-{{ $id }}" class="btn small">@include('learner._badge-icon', ['icon' => 'storefront', 'class' => 'ico']) Share</button>
          <button type="button" class="btn ghost small" data-goto="main">Cancel</button>
        </footer>
      </section>
    @endunless
  @endif
</div>
