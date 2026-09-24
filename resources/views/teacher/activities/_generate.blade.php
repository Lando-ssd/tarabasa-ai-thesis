{{--
  The Generate window. Pick a grade, the skill to build (the MATATAG grouped competency the AI
  service works from) and an activity type, then how many of EACH level you need (0 to 5). Each
  level shows its word range for that grade and type, and how many the teacher already has, so a
  teacher with plenty of Hard can ask for Medium only. public/js/teacher-app.js fills the ranges
  and counts from #genData.
--}}
@php
    $out = $teacher->free_generation_credits_remaining <= 0;
    $firstComp = old('competency', array_key_first($competencies));
@endphp
<dialog id="genDlg" class="win" aria-label="Generate activities" data-warm-url="{{ route('teacher.activities.warm') }}" @if ($openGenerate) data-autoopen @endif>
  <div class="win-in wide">
    <header class="win-head">
      <div class="win-titles"><h2>Generate activities</h2><p class="win-meta">Pick the skill to build, then how many of each level you need.</p></div>
      <button type="button" class="x" data-close aria-label="Close">@include('learner._badge-icon', ['icon' => 'x', 'class' => 'ico'])</button>
    </header>
    <div class="win-body">
      @if ($errors->any() && old('form') === 'generate')
        <div class="form-error-banner" role="alert">{{ $errors->first() }}</div>
      @endif
      @if ($out)
        <div class="alert amber" style="padding:10px 14px;margin-bottom:12px">
          <span class="alert-ico" style="width:34px;height:34px;font-size:19px;background:#c9820b">@include('learner._badge-icon', ['icon' => 'sparkle', 'class' => 'ico'])</span>
          <div>
            <b style="font-size:19px">No free credits left</b>
            <span class="d">
              @if ($teacher->status !== 'Active')More free credits unlock once your school verification is approved.
              @elseif ($shareable > 0)You have {{ $shareable }} approved {{ $shareable === 1 ? 'activity' : 'activities' }} not shared yet. Sharing one to the Repository earns you 2 more credits.
              @else Approve a draft and share it to the Repository to earn 2 more credits.
              @endif
            </span>
          </div>
          @if ($teacher->status === 'Active' && $shareable > 0)
            <a class="btn small" href="{{ route('teacher.activities.index', ['view' => 'list', 'status' => 'Approved']) }}">Share one</a>
          @endif
        </div>
      @endif

      <form id="genForm" method="POST" action="{{ route('teacher.activities.generate') }}">
        @csrf
        <input type="hidden" name="form" value="generate">
        <div class="gen-cols">
          <div>
            <div class="grid2">
              <div class="field">
                <label for="gGrade">Grade level</label>
                <select id="gGrade" name="grade_level" data-af required>
                  @foreach (['Grade 1', 'Grade 2', 'Grade 3'] as $g)<option @selected(old('grade_level', 'Grade 1') === $g)>{{ $g }}</option>@endforeach
                </select>
              </div>
              <div class="field">
                <label for="genComp">Skill to build</label>
                <select id="genComp" name="competency" required>
                  @foreach ($competencies as $key => $c)<option value="{{ $key }}" @selected($firstComp === $key)>{{ $c['label'] }}</option>@endforeach
                </select>
              </div>
            </div>
            <span class="fhint" id="genHint" style="margin:-8px 0 12px"></span>
            <div class="field">
              <label for="genType">Activity type</label>
              <select id="genType" name="activity_type" data-keep="{{ old('activity_type') }}" required></select>
            </div>
            <div class="field"><label for="gTopic">Topic <span class="opt">optional</span></label><input type="text" id="gTopic" name="topic" placeholder="e.g. Animals" maxlength="200" value="{{ old('topic') }}"></div>
            <div class="field" style="margin-bottom:0"><label for="gNotes">Notes for the AI <span class="opt">optional</span></label><textarea id="gNotes" name="teacher_notes" maxlength="1000" placeholder="e.g. Use words with the sh sound">{{ old('teacher_notes') }}</textarea></div>
          </div>

          <div>
            <div class="lv-head"><span class="eyebrow">How many of each level?</span></div>
            <div class="chips" style="margin:8px 0 12px" role="group" aria-label="Quick pick">
              @foreach ($tiers ?? config('activity_levels.tiers') as $t)<button type="button" class="chip plain" data-preset="{{ $t }}">Only {{ $t }}</button>@endforeach
              <button type="button" class="chip plain" data-preset="all">All three</button>
            </div>
            @foreach (config('activity_levels.tiers') as $t)
              <div class="lvrow" data-level="{{ $t }}">
                <div class="lv-info">
                  <div class="lv-name"><b>{{ $t }}</b><span class="pill t-{{ strtolower($t) }}" data-range></span></div>
                  <small>{{ $levelInfo[$t]['short'] }}</small>
                  <small class="have" data-have></small>
                </div>
                <div class="stepper" role="group" aria-label="{{ $t }} activities">
                  <button type="button" data-step="{{ $t }}:-1" aria-label="Fewer {{ $t }}">@include('learner._badge-icon', ['icon' => 'minus', 'class' => 'ico'])</button>
                  <output aria-live="polite">{{ old('levels.'.$t, 3) }}</output>
                  <button type="button" data-step="{{ $t }}:1" aria-label="More {{ $t }}">@include('learner._badge-icon', ['icon' => 'plus', 'class' => 'ico'])</button>
                  <input type="hidden" name="levels[{{ $t }}]" value="{{ old('levels.'.$t, 3) }}">
                </div>
              </div>
            @endforeach
            <div class="gen-total"><b data-gen-total>9</b> <span data-gen-total-word>activities</span> will be added to To review. <span class="gen-time" data-gen-time></span></div>
            <p class="note" style="margin:6px 0 0">The AI writes all three levels one after another, so the wait follows the biggest number you pick, not the total. It writes in the background, so you can keep working.</p>
            <p class="note" style="margin:6px 0 0">Levels come from how familiar the words are and how long the text is. After it is written, you check each one and move it to the level you think is right.</p>
          </div>
        </div>
        <div class="busy" id="genBusy" hidden><span class="spin"></span><span><b>Sending your request to the AI.</b> Your activities are written in the background. This window closes, and their progress shows at the top of Activities.</span></div>
      </form>
    </div>
    <footer class="win-foot">
      <button type="submit" form="genForm" class="btn small" id="genGo" @if ($out) disabled data-locked @endif>@include('learner._badge-icon', ['icon' => 'sparkle', 'class' => 'ico']) <span id="genGoText">Generate</span></button>
      <span class="note">Uses 1 free credit</span>
      <button type="button" class="btn ghost small spacer" data-close data-gen-cancel>Cancel</button>
    </footer>
  </div>
</dialog>
@php $genData = ['competencies' => $competencies, 'typeLabels' => $typeLabels, 'bands' => $bands, 'have' => $have, 'max' => $maxPerLevel, 'baseSeconds' => \App\Models\ActivityGeneration::BASE_SECONDS, 'extraSeconds' => \App\Models\ActivityGeneration::EXTRA_SECONDS_PER_TEXT]; @endphp
<script type="application/json" id="genData">@json($genData)</script>
