@php
  $statusClass = 'status-'.strtolower($activity->status);
  $typeLabel = $activityTypeLabels[$activity->activity_type] ?? $activity->activity_type;
  $canAct = $teacher->status === 'Active';
  $editFormId = 'edit-'.$activity->id;
@endphp
<div class="activity-card" data-search="{{ strtolower($activity->title.' '.$activity->grade_level.' '.$activity->competency_label.' '.$typeLabel.' '.$activity->topic) }}">
  <div class="tag-row">
    <span class="tag {{ $statusClass }}">{{ $activity->status }}</span>
    <span class="tag type">{{ $typeLabel }}</span>
    <span class="tag diff">{{ $activity->difficulty_tier }}</span>
    @if ($activity->variant_label)
      <span class="tag diff">Variant {{ $activity->variant_label }}</span>
    @endif
  </div>

  <p class="act-title">{{ $activity->title }}</p>
  <p class="act-meta">
    {{ $activity->grade_level }} &middot; {{ $activity->competency_label }}
    @if ($activity->topic) &middot; Topic: {{ $activity->topic }} @endif
  </p>

  <div class="act-passage">{{ $activity->passage_text }}</div>

  @if ($activity->status === 'Draft')
    <div class="actions">
      @if ($canAct)
        <form method="POST" action="{{ route('teacher.activities.approve', $activity) }}">
          @csrf
          <button type="submit" class="btn-approve">&#10003; Approve</button>
        </form>
        <button type="button" class="btn-edit" data-toggle-edit="{{ $editFormId }}">&#9998; Edit</button>
        <form method="POST" action="{{ route('teacher.activities.reject', $activity) }}">
          @csrf
          <button type="submit" class="btn-reject">&#10005; Reject</button>
        </form>
      @else
        <span class="muted-note">Locked until your account is Active.</span>
      @endif
    </div>

    @if ($canAct)
      <div class="edit-form" id="{{ $editFormId }}">
        <form method="POST" action="{{ route('teacher.activities.update', $activity) }}">
          @csrf
          @method('PUT')
          <label for="title-{{ $activity->id }}">Title</label>
          <input type="text" name="title" id="title-{{ $activity->id }}" value="{{ old('title', $activity->title) }}" maxlength="140" required>

          <label for="instructions-{{ $activity->id }}">Instructions</label>
          <textarea name="instructions" id="instructions-{{ $activity->id }}" maxlength="500" required>{{ old('instructions', $activity->instructions) }}</textarea>

          <label for="passage_text-{{ $activity->id }}">Reading Text</label>
          <textarea name="passage_text" id="passage_text-{{ $activity->id }}" maxlength="6000" required>{{ old('passage_text', $activity->passage_text) }}</textarea>

          <div class="actions">
            <button type="submit" class="btn-approve">Save &amp; Approve</button>
            <button type="button" class="btn-edit" data-toggle-edit="{{ $editFormId }}">Cancel</button>
          </div>
        </form>
      </div>
    @endif
  @elseif ($activity->status === 'Approved')
    @if ($activity->assignments->isNotEmpty())
      <p class="assigned-to">
        Assigned to:
        @foreach ($activity->assignments as $assignment)
          <b>{{ $assignment->learner ? $assignment->learner->first_name.' '.$assignment->learner->last_name : ($assignment->schoolClass ? $assignment->schoolClass->name : $assignment->group_tag) }}</b>{{ !$loop->last ? ', ' : '' }}
        @endforeach
      </p>
    @endif

    @if ($activity->shared_to_repository && $activity->repositoryListing)
      @php
        $ratings = $activity->repositoryListing->ratings;
        $avgRating = $ratings->isNotEmpty() ? round($ratings->avg('rating'), 1) : null;
      @endphp
      <p class="assigned-to">
        <span class="tag status-approved">✓ Shared to Repository</span>
        {{ $activity->repositoryListing->price_type === 'Free' ? 'Free' : '₱'.number_format($activity->repositoryListing->price) }}
        @if ($avgRating !== null)
          &middot; ★ {{ $avgRating }} ({{ $ratings->count() }} rating{{ $ratings->count() === 1 ? '' : 's' }})
        @endif
      </p>
    @endif

    <div class="actions">
      @if ($canAct)
        @php $assignFormId = 'assign-'.$activity->id; @endphp
        <button type="button" class="btn-assign" data-toggle-edit="{{ $assignFormId }}">Assign</button>
        @if (! $activity->shared_to_repository)
          @php $shareFormId = 'share-'.$activity->id; @endphp
          <button type="button" class="btn-edit" data-toggle-edit="{{ $shareFormId }}">Share to Repository</button>
        @endif
      @else
        <span class="muted-note">Locked until your account is Active.</span>
      @endif
    </div>

    @if ($canAct && ! $activity->shared_to_repository)
      <div class="edit-form" id="{{ $shareFormId }}">
        <form method="POST" action="{{ route('teacher.activities.share', $activity) }}">
          @csrf
          @error('price_type') <div class="field-error" style="color:var(--danger);font-size:11.5px;font-weight:600;margin-bottom:8px;">{{ $message }}</div> @enderror
          @error('price') <div class="field-error" style="color:var(--danger);font-size:11.5px;font-weight:600;margin-bottom:8px;">{{ $message }}</div> @enderror

          <div class="assign-target-row share-target-row">
            <label><input type="radio" name="price_type" value="Free" data-share-radio="{{ $shareFormId }}" checked> Free</label>
            <label><input type="radio" name="price_type" value="Paid" data-share-radio="{{ $shareFormId }}"> Paid</label>
          </div>

          <input type="number" name="price" step="0.01" min="0.01" placeholder="Price in ₱, e.g. 25" class="share-price-field" id="price-{{ $shareFormId }}" disabled>

          <p class="muted-note" style="display:block; margin:8px 0;">Sharing earns +2 free generation credits — one-time, per Activity.</p>

          <div class="actions">
            <button type="submit" class="btn-approve">Share</button>
            <button type="button" class="btn-edit" data-toggle-edit="{{ $shareFormId }}">Cancel</button>
          </div>
        </form>
      </div>
    @endif

    @if ($canAct)
      <div class="edit-form" id="{{ $assignFormId }}">
        <form method="POST" action="{{ route('teacher.activities.assign', $activity) }}">
          @csrf
          @error('assign_target') <div class="field-error" style="color:var(--danger);font-size:11.5px;font-weight:600;margin-bottom:8px;">{{ $message }}</div> @enderror

          <div class="assign-target-row">
            <label><input type="radio" name="assign_target_type" value="learner" checked> Learner</label>
            <label><input type="radio" name="assign_target_type" value="class"> Class</label>
            <label><input type="radio" name="assign_target_type" value="group"> Group</label>
          </div>

          <select name="assign_learner_id" class="assign-field show" data-for="learner">
            <option value="">Choose a learner…</option>
            @foreach ($assignLearners as $learner)
              <option value="{{ $learner->id }}">{{ $learner->first_name }} {{ $learner->last_name }}@if($learner->schoolClass) ({{ $learner->schoolClass->name }})@endif</option>
            @endforeach
          </select>

          <select name="assign_class_id" class="assign-field" data-for="class" disabled>
            <option value="">Choose a class…</option>
            @foreach ($assignClasses as $class)
              <option value="{{ $class->id }}">{{ $class->name }} ({{ $class->grade_level }})</option>
            @endforeach
          </select>

          <select name="assign_group_tag" class="assign-field" data-for="group" disabled>
            <option value="">Choose a group…</option>
            @foreach ($assignGroupTags as $tag)
              <option value="{{ $tag }}">{{ $tag }}</option>
            @endforeach
          </select>

          <div class="actions">
            <button type="submit" class="btn-approve">Assign</button>
            <button type="button" class="btn-edit" data-toggle-edit="{{ $assignFormId }}">Cancel</button>
          </div>
        </form>
      </div>
    @endif
  @else
    <div class="actions">
      <span class="muted-note">No further action available.</span>
    </div>
  @endif
</div>
