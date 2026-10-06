<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TaraBasa AI — Add your child</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
<noscript><link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"></noscript>
<link href="{{ asset('vendor/cropper-1.6.2.min.css') }}" rel="stylesheet">
<style>
  :root{
    --sky-50:#eef6ff; --sky-100:#dcedff;
    --blue-500:#1c7ed6; --blue-600:#0f5fae; --blue-700:#0a3d73;
    --navy-900:#131f2b; --slate-600:#5b6b7a; --slate-400:#8a97a3;
    --clay-yellow:#ffcf6e; --owl-orange-600:#dd7014; --parent-teal:#1f9e83;
    --line:#e3ebf2; --surface:#ffffff; --bg-0:#f6faff;
    --danger:#d64545; --danger-bg:#fdecec;
    --success:#1f9e83; --success-bg:#e9f7f3;
    --shadow-card:0 30px 60px -30px rgba(15,60,110,0.28), 0 6px 16px -8px rgba(15,60,110,0.12);
  }
  *{box-sizing:border-box;} html,body{margin:0;padding:0;}
  body{
    min-height:100vh; font-family:'Inter',sans-serif; color:var(--navy-900);
    background: radial-gradient(1100px 620px at 88% -10%, var(--sky-100), transparent 60%),
                radial-gradient(900px 500px at -10% 108%, var(--sky-100), transparent 55%), var(--bg-0);
    background-attachment:fixed;
    display:flex; align-items:flex-start; justify-content:center; padding:32px 20px 80px;
  }
  .shell{ width:100%; max-width:560px; margin-top:12px; }

  .back-link{
    display:inline-flex; align-items:center; gap:6px; font-size:13.5px; font-weight:700; color:var(--slate-600);
    text-decoration:none; margin-bottom:16px; cursor:pointer; background:none; border:none; padding:0; font-family:inherit;
  }
  .back-link:hover{ color:var(--blue-600); }
  .back-link:disabled{ opacity:.4; cursor:not-allowed; }

  .progress-wrap{ margin-bottom:22px; }
  .progress-track{ height:6px; border-radius:99px; background:var(--line); overflow:hidden; }
  .progress-fill{ height:100%; border-radius:99px; background:linear-gradient(90deg, var(--parent-teal), var(--blue-500)); transition:width .35s ease; }
  .progress-label{ font-size:12px; font-weight:700; color:var(--slate-400); margin-top:7px; }

  .card{ background:var(--surface); border:1px solid var(--line); border-radius:24px; box-shadow:var(--shadow-card); padding:32px 30px; }
  .step-eyebrow{ font-size:11.5px; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:var(--blue-600); margin-bottom:6px; }
  h1{ font-family:'Baloo 2',sans-serif; font-size:23px; font-weight:700; margin:0 0 6px; }
  .sub{ margin:0 0 22px; font-size:13.5px; color:var(--slate-600); font-weight:500; line-height:1.5; }

  .field{ display:flex; flex-direction:column; gap:6px; margin-bottom:16px; }
  .field label{ font-size:13px; font-weight:700; }
  input[type="text"], select{
    width:100%; font:500 14.5px/1 'Inter',sans-serif; padding:13px 14px; border:1.5px solid var(--line);
    border-radius:12px; background:var(--bg-0); color:var(--navy-900); outline:none;
    transition:border-color .15s ease, box-shadow .15s ease, background .15s ease; appearance:none;
  }
  select{
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none'%3E%3Cpath d='M6 9l6 6 6-6' stroke='%238a97a3' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-repeat:no-repeat; background-position:right 14px center; padding-right:38px;
  }
  input:focus, select:focus{ border-color:var(--blue-500); background:var(--surface); box-shadow:0 0 0 4px rgba(28,126,214,0.14); }
  .name-row{ display:grid; grid-template-columns:1fr 1fr; gap:10px; }
  .name-row .full{ grid-column:1 / -1; }
  .optional-tag{ font-size:11px; font-weight:600; color:var(--slate-400); margin-left:4px; }
  .field-error{ font-size:12px; color:var(--danger); font-weight:600; margin-top:2px; }

  .option-btn{
    width:100%; text-align:left; padding:14px 16px; border:1.5px solid var(--line); border-radius:14px;
    background:var(--surface); font:600 14px/1.3 'Inter',sans-serif; color:var(--navy-900); cursor:pointer;
    margin-bottom:9px; transition:border-color .15s ease, background .15s ease;
  }
  .option-btn:hover{ border-color:var(--blue-500); }
  .option-btn.selected{ border-color:var(--parent-teal); background:var(--success-bg); color:var(--success); }

  .avatar-grid{ display:grid; grid-template-columns:repeat(4, 1fr); gap:12px; margin-bottom:8px; }
  .avatar-opt{
    aspect-ratio:1; border-radius:18px; border:2px solid var(--line); background:var(--bg-0);
    display:flex; align-items:safe center; justify-content:center; font-size:28px; cursor:pointer;
    transition:border-color .15s ease, transform .15s ease;
  }
  .avatar-opt:hover{ transform:translateY(-2px); }
  .avatar-opt.selected{ border-color:var(--blue-500); background:var(--sky-50); box-shadow:0 0 0 3px rgba(28,126,214,0.14); }

  .avatar-mode-toggle{ display:flex; gap:6px; margin-bottom:16px; background:var(--bg-0); border:1px solid var(--line); border-radius:12px; padding:4px; }
  .mode-tab{
    flex:1; padding:9px; border:none; border-radius:9px; background:none; color:var(--slate-600);
    font:700 12.5px/1 'Inter',sans-serif; cursor:pointer; transition:background .15s ease, color .15s ease;
  }
  .mode-tab.selected{ background:var(--surface); color:var(--blue-600); box-shadow:0 1px 3px rgba(19,31,43,.12); }

  .photo-toggle{
    display:flex; align-items:safe center; justify-content:center; gap:8px; padding:22px;
    border:1.5px dashed var(--line); border-radius:14px; font-size:13px; font-weight:700; color:var(--slate-600);
    cursor:pointer; background:none; width:100%;
  }
  .photo-toggle:hover{ border-color:var(--blue-500); color:var(--blue-600); }

  .cropper-frame{ max-height:320px; overflow:hidden; border-radius:14px; border:1px solid var(--line); background:var(--bg-0); }
  .cropper-frame img{ display:block; max-width:100%; }

  .photo-preview-circle{ width:110px; height:110px; border-radius:50%; object-fit:cover; border:3px solid var(--sky-100); margin:0 auto 10px; display:block; }
  .photo-actions{ display:flex; gap:10px; margin-top:12px; }
  .photo-actions .btn-primary-sm, .photo-actions .btn-ghost-sm{
    flex:1; padding:11px; border-radius:10px; font:700 13px/1 'Inter',sans-serif; cursor:pointer; text-align:center;
  }
  .photo-actions .btn-primary-sm{ background:var(--blue-600); color:#fff; border:none; }
  .photo-actions .btn-ghost-sm{ background:none; border:1.5px solid var(--line); color:var(--slate-600); }

  .pq{ background:var(--bg-0); border:1px solid var(--line); border-radius:16px; padding:16px; margin-bottom:12px; }
  .pq .q{ font-size:14px; font-weight:700; margin-bottom:11px; line-height:1.4; }
  .pq-opts{ display:flex; gap:8px; }
  .pq-opt{
    flex:1; padding:10px; border:1.5px solid var(--line); border-radius:11px; background:var(--surface);
    font:700 13px/1 'Inter',sans-serif; color:var(--navy-900); cursor:pointer; text-align:center;
    transition:border-color .15s ease, background .15s ease;
  }
  .pq-opt.selected{ border-color:var(--parent-teal); background:var(--success-bg); color:var(--success); }
  .grade-tag{
    display:inline-flex; align-items:center; gap:6px; background:var(--sky-50); border:1px solid var(--line);
    padding:5px 11px; border-radius:999px; font-size:12px; font-weight:700; color:var(--blue-600); margin-bottom:16px;
  }

  .pin-boxes{ display:flex; gap:10px; justify-content:center; margin:6px 0 8px; }
  .pin-box{
    width:52px; height:60px; border:1.5px solid var(--line); border-radius:14px; background:var(--bg-0);
    display:flex; align-items:safe center; justify-content:center; font-family:'Baloo 2',sans-serif; font-size:24px; font-weight:700;
  }
  .pin-box.filled{ border-color:var(--blue-500); background:var(--surface); }
  .pin-hidden-input{ position:absolute; opacity:0; pointer-events:none; }
  .pin-label{ text-align:center; font-size:12.5px; font-weight:700; color:var(--slate-600); margin:14px 0 4px; }

  .nav-row{ display:flex; gap:10px; margin-top:22px; justify-content:space-between; align-items:center; }
  .btn{
    flex:0 0 auto; padding:14px 34px; border:none; border-radius:12px; font:700 15px/1 'Inter',sans-serif; cursor:pointer;
    display:flex; align-items:safe center; justify-content:center; gap:8px; transition:transform .15s ease, opacity .15s ease;
  }
  .btn:hover{ transform:translateY(-1px); }
  .btn:disabled{ opacity:.5; cursor:not-allowed; transform:none; }
  .btn-primary{ background:linear-gradient(155deg, var(--blue-500), var(--blue-700)); color:#fff; box-shadow:0 12px 22px -10px rgba(15,95,174,0.55); }
  .btn-ghost{ background:var(--bg-0); color:var(--slate-600); border:1.5px solid var(--line); padding:14px 28px; }

  .chiprow{ display:flex; flex-wrap:wrap; gap:8px; margin:6px 0 4px; }
  .chip{ position:relative; display:inline-flex; align-items:center; padding:9px 14px; border:1.5px solid var(--line); border-radius:999px; background:var(--surface); font:700 13.5px/1 'Inter',sans-serif; color:var(--navy-900); cursor:pointer; transition:border-color .15s ease, background .15s ease; }
  .chip input{ position:absolute; opacity:0; pointer-events:none; }
  .chip.selected{ border-color:var(--blue-500); background:var(--sky-50); color:var(--blue-600); }
  .field-hint{ font:500 12.5px/1.45 'Inter',sans-serif; color:var(--slate-600); margin:6px 0 0; }
  .qdots{ display:flex; gap:7px; margin:0 0 14px; }
  .qdots i{ width:10px; height:10px; border-radius:50%; background:var(--line); display:block; }
  .qdots i.c{ background:var(--blue-500); } .qdots i.d{ background:var(--parent-teal); }
  .pq-opts.three .pq-opt{ padding:14px 8px; font-size:14px; }

  .step-hidden{ display:none; }
  @keyframes stepFadeIn{ from{ opacity:0; transform:translateY(6px); } to{ opacity:1; transform:translateY(0); } }
  .step-enter{ animation:stepFadeIn .25s ease; }
  @media (prefers-reduced-motion: reduce){ .step-enter{ animation:none; } }
  .inline-error{ background:var(--danger-bg); color:var(--danger); border:1px solid var(--danger); border-radius:11px; padding:10px 13px; font-size:12.5px; font-weight:600; margin-bottom:14px; }

  @media (max-width:480px){
    .avatar-grid{ grid-template-columns:repeat(3,1fr); }
    .name-row{ grid-template-columns:1fr; }
  }
  a:focus-visible, button:focus-visible{ outline:2px solid var(--blue-500); outline-offset:2px; }
</style>
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
            <button type="button" class="btn-ghost-sm" id="changePhotoBtn" style="padding:9px 16px; border-radius:10px; background:none; border:1.5px solid var(--line); color:var(--slate-600); font:700 12.5px/1 'Inter',sans-serif; cursor:pointer;">Choose a Different Photo</button>
          </div>

          <div class="field-error" id="photoError" style="display:none;"></div>
        </div>

        <input type="hidden" name="avatar_id" id="avatar_id" value="{{ $avatars[0] }}">
        <input type="file" name="avatar_photo" id="avatarPhotoRealInput" style="display:none;">
      </div>

      <div class="step step-hidden" id="step3">
        <div class="step-eyebrow">Your best guess is fine</div>
        <h1>How would you describe <span class="child-name-possessive-ref">their</span> reading?</h1>
        <p class="sub">We'll confirm this with a quick placement check next — this initial estimate isn't final, it just helps set a starting point.</p>
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
        <p class="sub"><span class="child-name-ref">Your child</span> will use this PIN, along with their avatar, to open their own dashboard — no typing needed.</p>
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
        <div class="field-error" id="pinMismatchError" style="text-align:center; display:none;">PINs don't match — try again.</div>
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
      showPhotoError('That photo is larger than 2MB — please choose a smaller one.');
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
      if (!NAME_REGEX.test(fn)){ showInlineError('First name may only contain letters, spaces, hyphens, and apostrophes — no numbers.'); return false; }
      if (!NAME_REGEX.test(ln)){ showInlineError('Last name may only contain letters, spaces, hyphens, and apostrophes — no numbers.'); return false; }
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
      if (confirm.length < 4 || confirm !== pin){ showInlineError("PINs don't match — try again."); return false; }
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
