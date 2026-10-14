<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add your child | TaraBasa AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Semi+Condensed:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
<noscript><link href="https://fonts.googleapis.com/css2?family=Barlow+Semi+Condensed:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"></noscript>
<link href="{{ asset('vendor/cropper-1.6.2.min.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/parent-focus.css') }}?v={{ substr(md5_file(public_path('css/parent-focus.css')), 0, 12) }}">
</head>
<body>
<div class="shell">
  <a href="{{ route('parent.children.index') }}" class="back-link" id="backLink">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    Back
  </a>

  <div class="progress-wrap" id="progressWrap">
    <div class="progress-track"><div class="progress-fill" id="progressFill" style="width:20%"></div></div>
    <div class="progress-label" id="progressLabel">Step 1 of 5</div>
  </div>

  <div class="card">
    <form method="POST" action="{{ route('parent.children.store') }}" id="wizardForm" enctype="multipart/form-data">
      @csrf

      @if ($errors->any())
        {{-- Only first_name/last_name have dedicated inline slots below —
             this catches everything else (avatar_photo, placement, PIN)
             so a server-side rejection is never silent, even though the
             wizard always reopens on step 1. --}}
        <div class="inline-error">{{ $errors->first() }}</div>
      @endif

      <div class="step" id="step1">
        <div class="step-eyebrow">Let's get started</div>
        <h1>What's your child's name?</h1>
        <p class="sub">This is how they'll appear on your dashboard and to their teacher.</p>
        <div class="name-row">
          <div class="field full">
            <label for="first_name">First name</label>
            <input type="text" name="first_name" id="first_name" placeholder="e.g. Miguel" value="{{ old('first_name') }}">
            @error('first_name') <div class="field-error">{{ $message }}</div> @enderror
          </div>
          <div class="field">
            <label for="middle_name">Middle name <span class="optional-tag">optional</span></label>
            <input type="text" name="middle_name" id="middle_name" placeholder="e.g. Santos" value="{{ old('middle_name') }}">
          </div>
          <div class="field">
            <label for="last_name">Last name</label>
            <input type="text" name="last_name" id="last_name" placeholder="e.g. Cruz" value="{{ old('last_name') }}">
            @error('last_name') <div class="field-error">{{ $message }}</div> @enderror
          </div>
        </div>
        <div class="field">
          <label for="grade_level">Grade level</label>
          <select name="grade_level" id="grade_level">
            <option value="Grade 1">Grade 1</option>
            <option value="Grade 2">Grade 2</option>
            <option value="Grade 3">Grade 3</option>
          </select>
        </div>
        <div class="field">
          <label for="home_language">Language spoken at home <span class="optional-tag">optional</span></label>
          <select name="home_language" id="home_language">
            <option value="">Choose one</option>
            @foreach (\App\Http\Controllers\LearnerController::HOME_LANGUAGES as $lang)
              <option value="{{ $lang }}" @selected(old('home_language') === $lang)>{{ $lang }}</option>
            @endforeach
          </select>
          <p class="field-hint">Helps us understand how much English your child hears at home. It does not change the activities.</p>
        </div>
      </div>

      <div class="step step-hidden" id="step2">
        <div class="step-eyebrow">Give them an identity</div>
        <h1>Choose an avatar</h1>
        <p class="sub">Pick a character for <span class="child-name-ref">your child</span>, or use a real photo instead.</p>

        <div class="avatar-mode-toggle">
          <button type="button" class="mode-tab selected" id="modePresetTab">Choose a character</button>
          <button type="button" class="mode-tab" id="modePhotoTab">Upload a photo</button>
        </div>

        <div id="presetPanel">
          <div class="avatar-grid" id="avatarGrid">
            @foreach ($avatars as $i => $avatar)
              <div class="avatar-opt @if($i === 0) selected @endif" data-avatar="{{ $avatar }}">{{ $avatar }}</div>
            @endforeach
          </div>
        </div>

        <div id="photoPanel" class="step-hidden">
          <div id="photoPickPrompt">
            <button type="button" class="photo-toggle" id="photoPickBtn">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="3.2" stroke="currentColor" stroke-width="1.8"/><path d="M8 5l1.3-2h5.4L16 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
              Choose a photo from your device
            </button>
            <input type="file" id="photoPicker" accept="image/jpeg,image/png,image/webp" style="display:none;">
          </div>

          <div id="cropPanel" class="step-hidden">
            <div class="cropper-frame"><img id="cropperImage" src="" alt="Photo to crop"></div>
            <div class="photo-actions">
              <button type="button" class="btn-primary-sm" id="useCropBtn">Use This Photo</button>
              <button type="button" class="btn-ghost-sm" id="retakeBtn">Choose Different Photo</button>
            </div>
          </div>

          <div id="photoPreviewPanel" class="step-hidden" style="text-align:center;">
            <img id="photoPreviewImg" src="" alt="Cropped preview" class="photo-preview-circle">
            <button type="button" class="btn-ghost-sm" id="changePhotoBtn" style="flex:none; padding-left:18px; padding-right:18px;">Choose a Different Photo</button>
          </div>

          <div class="field-error" id="photoError" style="display:none;"></div>
        </div>

        <input type="hidden" name="avatar_id" id="avatar_id" value="{{ $avatars[0] }}">
        <input type="file" name="avatar_photo" id="avatarPhotoRealInput" style="display:none;">
      </div>

      <div class="step step-hidden" id="step3">
        <div class="step-eyebrow">Your best guess is fine</div>
        <h1>How would you describe <span class="child-name-possessive-ref">their</span> reading?</h1>
        <p class="sub">We'll confirm this with a quick placement check next. This first guess is not final, it only sets a starting point.</p>
        <div id="stageOptions">
          <button type="button" class="option-btn" data-stage="starting">Cannot yet recognize letters</button>
          <button type="button" class="option-btn" data-stage="letters">Recognizes letters but cannot read words yet</button>
          <button type="button" class="option-btn" data-stage="blending">Reads simple words</button>
          <button type="button" class="option-btn" data-stage="sentences">Reads short sentences</button>
          <button type="button" class="option-btn" data-stage="independent">Reads grade-level passages on their own</button>
          <button type="button" class="option-btn" data-stage="unsure">Not sure</button>
        </div>
        <input type="hidden" name="reading_stage" id="reading_stage" value="">

        <div class="field" style="margin-top:20px;">
          <label>What helps <span class="child-name-ref">your child</span> most? <span class="optional-tag">choose any</span></label>
          <div class="chiprow" id="supportChips">
            @foreach (\App\Http\Controllers\LearnerController::SUPPORTS as $key => $label)
              <label class="chip"><input type="checkbox" name="supports[]" value="{{ $key }}"><span>{{ $label }}</span></label>
            @endforeach
          </div>
          <p class="field-hint">These switch on supports such as voice help and hints. They do not decide the activities.</p>
        </div>
        <div class="field">
          <label>Topics <span class="child-name-ref">your child</span> likes <span class="optional-tag">choose any</span></label>
          <div class="chiprow" id="interestChips">
            @foreach (\App\Http\Controllers\LearnerController::INTERESTS as $key => $label)
              <label class="chip"><input type="checkbox" name="interests[]" value="{{ $key }}"><span>{{ $label }}</span></label>
            @endforeach
          </div>
        </div>
      </div>

      <div class="step step-hidden" id="step4">
        <div class="step-eyebrow">Almost there</div>
        <h1>A few quick questions</h1>
        <p class="sub">Three questions for <span class="grade-tag" id="gradeTagText">Grade 1</span>. A guess is fine. <span class="child-name-ref">Your child</span>'s first reading check finds the real starting point.</p>
        <div class="qdots" id="qDots"><i class="c"></i><i></i><i></i></div>
        <div id="placementQuestions"></div>
        <input type="hidden" name="q1" id="q1" value="">
        <input type="hidden" name="q2" id="q2" value="">
        <input type="hidden" name="q3" id="q3" value="">
      </div>

      <div class="step step-hidden" id="step5">
        <div class="step-eyebrow">Last step</div>
        <h1>Set a 4-digit PIN</h1>
        <p class="sub"><span class="child-name-ref">Your child</span> will use this PIN, along with their avatar, to open their own dashboard, with no typing needed.</p>
        <div class="pin-boxes" id="pinBoxes">
          <div class="pin-box" data-i="0"></div>
          <div class="pin-box" data-i="1"></div>
          <div class="pin-box" data-i="2"></div>
          <div class="pin-box" data-i="3"></div>
        </div>
        <input type="tel" inputmode="numeric" maxlength="4" class="pin-hidden-input" id="pinInput">
        <input type="hidden" name="pin" id="pin">
        <p class="sub" style="text-align:center; margin:6px 0 0;">Tap the boxes above to enter a PIN</p>

        <div class="pin-label">Confirm PIN</div>
        <div class="pin-boxes" id="pinBoxesConfirm">
          <div class="pin-box" data-i="0"></div>
          <div class="pin-box" data-i="1"></div>
          <div class="pin-box" data-i="2"></div>
          <div class="pin-box" data-i="3"></div>
        </div>
        <input type="tel" inputmode="numeric" maxlength="4" class="pin-hidden-input" id="pinConfirmInput">
        <input type="hidden" name="pin_confirmation" id="pin_confirmation">
        <div class="field-error" id="pinMismatchError" style="text-align:center; display:none;">PINs don't match. Try again.</div>
      </div>

      <div class="nav-row" id="navRow">
        <button type="button" class="btn btn-ghost" id="prevBtn">Back</button>
        <button type="button" class="btn btn-primary" id="nextBtn">Next</button>
        <button type="submit" class="btn btn-primary step-hidden" id="submitBtn">Complete setup</button>
      </div>
    </form>
  </div>
</div>

<script src="{{ asset('vendor/cropper-1.6.2.min.js') }}"></script>
<script>
  const PLACEMENT_QUESTIONS = @json($placementQuestions);

  let step = 1;
  const totalSteps = 5;
  const stepsEls = { 1: step1, 2: step2, 3: step3, 4: step4, 5: step5 };

  function selectedGrade(){ return document.getElementById('grade_level').value; }

  // One question at a time (three dots). Yes / Not yet / Not sure: "Not sure" is stored as
  // 'unsure' and counts as no signal, never as a wrong answer.
  let qIndex = 0;
  const ANSWER_LABEL = { yes: 'Yes', no: 'Not yet', unsure: 'Not sure' };

  function renderPlacementQuestions(){
    const grade = selectedGrade();
    document.getElementById('gradeTagText').textContent = grade;
    const container = document.getElementById('placementQuestions');
    const q = PLACEMENT_QUESTIONS[grade][qIndex];
    const key = 'q' + (qIndex + 1);
    container.innerHTML = `
      <div class="pq">
        <div class="q">${q}</div>
        <div class="pq-opts three">
          ${['yes', 'no', 'unsure'].map(v => `<button type="button" class="pq-opt" data-key="${key}" data-val="${v}">${ANSWER_LABEL[v]}</button>`).join('')}
        </div>
      </div>`;
    const current = document.getElementById(key).value;
    container.querySelectorAll('.pq-opt').forEach(btn => {
      if (btn.dataset.val === current) btn.classList.add('selected');
      btn.addEventListener('click', () => {
        document.getElementById(key).value = btn.dataset.val;
        container.querySelectorAll('.pq-opt').forEach(b => b.classList.remove('selected'));
        btn.classList.add('selected');
      });
    });
    document.querySelectorAll('#qDots i').forEach((dot, i) => {
      dot.className = i < qIndex ? 'd' : (i === qIndex ? 'c' : '');
    });
  }

  // Interest and support chips.
  document.querySelectorAll('.chip input').forEach(box => {
    box.addEventListener('change', () => box.parentNode.classList.toggle('selected', box.checked));
  });

  document.querySelectorAll('.avatar-opt').forEach(el => {
    el.addEventListener('click', () => {
      document.querySelectorAll('.avatar-opt').forEach(x => x.classList.remove('selected'));
      el.classList.add('selected');
      document.getElementById('avatar_id').value = el.dataset.avatar;
    });
  });

  // Avatar mode: preset character (default) vs. a real uploaded-and-cropped
  // photo. avatar_id always keeps a valid preset value either way — the
  // learners table still requires it — the uploaded photo is a separate,
  // optional column the server prefers over avatar_id when both exist.
  let avatarMode = 'preset';
  let cropper = null;
  let photoReady = false;
  const MAX_PHOTO_BYTES = 2 * 1024 * 1024;
  const ALLOWED_PHOTO_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

  function setAvatarMode(mode) {
    avatarMode = mode;
    document.getElementById('modePresetTab').classList.toggle('selected', mode === 'preset');
    document.getElementById('modePhotoTab').classList.toggle('selected', mode === 'photo');
    document.getElementById('presetPanel').classList.toggle('step-hidden', mode !== 'preset');
    document.getElementById('photoPanel').classList.toggle('step-hidden', mode !== 'photo');
  }
  document.getElementById('modePresetTab').addEventListener('click', () => setAvatarMode('preset'));
  document.getElementById('modePhotoTab').addEventListener('click', () => setAvatarMode('photo'));

  function showPhotoError(msg) {
    const el = document.getElementById('photoError');
    el.textContent = msg;
    el.style.display = 'block';
  }
  function clearPhotoError() {
    document.getElementById('photoError').style.display = 'none';
  }

  function resetPhotoPicker() {
    if (cropper) { cropper.destroy(); cropper = null; }
    document.getElementById('photoPicker').value = '';
    document.getElementById('avatarPhotoRealInput').value = '';
    photoReady = false;
    document.getElementById('cropPanel').classList.add('step-hidden');
    document.getElementById('photoPreviewPanel').classList.add('step-hidden');
    document.getElementById('photoPickPrompt').classList.remove('step-hidden');
  }

  document.getElementById('photoPickBtn').addEventListener('click', () => document.getElementById('photoPicker').click());

  document.getElementById('photoPicker').addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (!file) return;
    clearPhotoError();

    // Client-side checks are just fast feedback — the server re-validates
    // the actual uploaded file's real content and size regardless.
    if (!ALLOWED_PHOTO_TYPES.includes(file.type)) {
      showPhotoError('Please choose a JPG, PNG, or WEBP image.');
      return;
    }
    if (file.size > MAX_PHOTO_BYTES) {
      showPhotoError('That photo is larger than 2MB. Please choose a smaller one.');
      return;
    }

    const reader = new FileReader();
    reader.onload = (ev) => {
      document.getElementById('photoPickPrompt').classList.add('step-hidden');
      document.getElementById('cropPanel').classList.remove('step-hidden');
      const img = document.getElementById('cropperImage');
      img.src = ev.target.result;
      if (cropper) cropper.destroy();
      cropper = new Cropper(img, {
        aspectRatio: 1,
        viewMode: 1,
        background: false,
        autoCropArea: 1,
      });
    };
    reader.readAsDataURL(file);
  });

  document.getElementById('useCropBtn').addEventListener('click', () => {
    if (!cropper) return;
    const canvas = cropper.getCroppedCanvas({ width: 480, height: 480 });
    canvas.toBlob((blob) => {
      // Writes the cropped result into the REAL file input via DataTransfer
      // so it submits as a normal multipart file with the rest of the
      // wizard's single POST — no separate AJAX upload endpoint needed.
      const croppedFile = new File([blob], 'avatar.jpg', { type: 'image/jpeg' });
      const dt = new DataTransfer();
      dt.items.add(croppedFile);
      document.getElementById('avatarPhotoRealInput').files = dt.files;

      document.getElementById('photoPreviewImg').src = canvas.toDataURL('image/jpeg', 0.9);
      document.getElementById('cropPanel').classList.add('step-hidden');
      document.getElementById('photoPreviewPanel').classList.remove('step-hidden');
      photoReady = true;

      cropper.destroy();
      cropper = null;
    }, 'image/jpeg', 0.9);
  });

  document.getElementById('retakeBtn').addEventListener('click', resetPhotoPicker);
  document.getElementById('changePhotoBtn').addEventListener('click', resetPhotoPicker);

  document.querySelectorAll('#stageOptions .option-btn').forEach(el => {
    el.addEventListener('click', () => {
      document.querySelectorAll('#stageOptions .option-btn').forEach(x => x.classList.remove('selected'));
      el.classList.add('selected');
      document.getElementById('reading_stage').value = el.dataset.stage;
    });
  });
  function wirePinBoxes(boxesId, inputId, hiddenId, onComplete){
    const boxes = document.querySelectorAll('#' + boxesId + ' .pin-box');
    const input = document.getElementById(inputId);
    boxes.forEach(box => box.addEventListener('click', () => input.focus()));
    input.addEventListener('input', (e) => {
      const val = e.target.value.replace(/\D/g, '').slice(0, 4);
      e.target.value = val;
      document.getElementById(hiddenId).value = val;
      boxes.forEach((box, i) => {
        box.textContent = val[i] ? '•' : '';
        box.classList.toggle('filled', !!val[i]);
      });
      if (onComplete) onComplete(val);
    });
  }
  wirePinBoxes('pinBoxes', 'pinInput', 'pin');
  wirePinBoxes('pinBoxesConfirm', 'pinConfirmInput', 'pin_confirmation');

  function updateChildNameRefs(){
    const fn = document.getElementById('first_name').value.trim();
    const displayName = fn || 'your child';
    document.querySelectorAll('.child-name-ref').forEach(el => el.textContent = displayName);
    // "Miguel's" vs "James'" — the possessive form needs its own logic,
    // not just a straight name substitution, or the sentence breaks
    // grammatically once a real name replaces the placeholder "their".
    const possessive = fn
      ? (fn.toLowerCase().endsWith('s') ? fn + "'" : fn + "'s")
      : 'their';
    document.querySelectorAll('.child-name-possessive-ref').forEach(el => el.textContent = possessive);
  }
  document.getElementById('first_name').addEventListener('input', updateChildNameRefs);
  // The three questions differ by grade, so changing the grade clears earlier answers.
  document.getElementById('grade_level').addEventListener('change', () => { ['q1','q2','q3'].forEach(k => document.getElementById(k).value = ''); qIndex = 0; });

  const progressFill = document.getElementById('progressFill');
  const progressLabel = document.getElementById('progressLabel');
  const prevBtn = document.getElementById('prevBtn');
  const nextBtn = document.getElementById('nextBtn');
  const submitBtn = document.getElementById('submitBtn');

  function clearInlineErrors(){
    document.querySelectorAll('.inline-error').forEach(el => el.remove());
  }
  function showInlineError(message){
    clearInlineErrors();
    const div = document.createElement('div');
    div.className = 'inline-error';
    div.textContent = message;
    stepsEls[step].prepend(div);
  }

  function showStep(n){
    clearInlineErrors();
    Object.values(stepsEls).forEach(el => { el.classList.add('step-hidden'); el.classList.remove('step-enter'); });
    const target = stepsEls[n];
    target.classList.remove('step-hidden');
    // Force a reflow before adding the animation class so it retriggers
    // every time (a class re-add with no reflow between is a no-op).
    void target.offsetWidth;
    target.classList.add('step-enter');

    progressFill.style.width = (n / totalSteps * 100) + '%';
    progressLabel.textContent = `Step ${n} of ${totalSteps}`;
    prevBtn.style.visibility = n === 1 ? 'hidden' : 'visible';
    // On a phone the first step has no Back button at all, so Next can use the whole row (the stylesheet decides; elsewhere Back only hides).
    document.getElementById('navRow').classList.toggle('is-first', n === 1);
    if (n === 4) renderPlacementQuestions();

    nextBtn.classList.toggle('step-hidden', n === totalSteps);
    submitBtn.classList.toggle('step-hidden', n !== totalSteps);
  }

  // Letters (any language/script), spaces, hyphens, and apostrophes only —
  // must match LearnerController::NAME_REGEX exactly, since this is only
  // fast client-side feedback; the server enforces the real rule.
  const NAME_REGEX = /^[\p{L}\s'-]+$/u;

  function validateStep(n){
    if (n === 1){
      const fn = document.getElementById('first_name').value.trim();
      const ln = document.getElementById('last_name').value.trim();
      if (!fn || !ln){ showInlineError('Please enter at least a first and last name.'); return false; }
      if (!NAME_REGEX.test(fn)){ showInlineError('First name may only contain letters, spaces, hyphens, and apostrophes, and no numbers.'); return false; }
      if (!NAME_REGEX.test(ln)){ showInlineError('Last name may only contain letters, spaces, hyphens, and apostrophes, and no numbers.'); return false; }
    }
    if (n === 2){
      if (avatarMode === 'photo' && !photoReady){
        showInlineError('Please finish cropping your photo, or switch back to "Choose a character".');
        return false;
      }
    }
    if (n === 3){
      if (!document.getElementById('reading_stage').value){ showInlineError('Please choose one option to describe their reading.'); return false; }
    }
    if (n === 4){
      if (!document.getElementById('q' + (qIndex + 1)).value){
        showInlineError('Please choose Yes, Not yet or Not sure.');
        return false;
      }
    }
    if (n === 5){
      const pin = document.getElementById('pin').value;
      const confirm = document.getElementById('pin_confirmation').value;
      if (pin.length < 4){ showInlineError('Please enter a 4-digit PIN.'); return false; }
      if (confirm.length < 4 || confirm !== pin){ showInlineError("PINs don't match. Try again."); return false; }
    }
    return true;
  }

  nextBtn.addEventListener('click', () => {
    if (!validateStep(step)) return;
    updateChildNameRefs();
    if (step === 3) qIndex = 0;
    // Step 4 is three questions, one at a time.
    if (step === 4 && qIndex < 2) { qIndex++; clearInlineErrors(); renderPlacementQuestions(); return; }
    step++;
    showStep(step);
  });
  prevBtn.addEventListener('click', () => {
    if (step === 1) return;
    if (step === 4 && qIndex > 0) { qIndex--; clearInlineErrors(); renderPlacementQuestions(); return; }
    if (step === 5) qIndex = 2;
    step--;
    showStep(step);
  });

  document.getElementById('wizardForm').addEventListener('submit', function (e) {
    if (!validateStep(5)) { e.preventDefault(); return; }
    submitBtn.disabled = true;
    submitBtn.textContent = 'Creating profile…';
  });

  showStep(1);
</script>
</body>
</html>
