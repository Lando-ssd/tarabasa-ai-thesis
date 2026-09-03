<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TaraBasa AI — Class Management</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --sky-50:#eef6ff; --sky-100:#dcedff;
    --blue-500:#1c7ed6; --blue-600:#0f5fae; --blue-700:#0a3d73;
    --navy-900:#131f2b; --slate-600:#5b6b7a; --slate-400:#8a97a3;
    --owl-orange-500:#ef8d2a; --owl-orange-600:#dd7014;
    --line:#e3ebf2; --surface:#ffffff; --bg-0:#f6faff;
    --success:#1f9e83; --success-bg:#e9f7f3;
    --amber:#c9820b; --amber-bg:#fef6e6;
    --danger:#d64545; --danger-bg:#fdecec;
    --shadow-sm:0 1px 2px rgba(19,31,43,0.06);
  }
  *{box-sizing:border-box;} html,body{margin:0;padding:0;}
  body{
    min-height:100vh; font-family:'Inter',sans-serif; color:var(--navy-900);
    background: radial-gradient(1100px 500px at 90% -10%, var(--sky-100), transparent 55%), var(--bg-0);
    background-attachment:fixed;
  }
  .shell{ max-width:960px; margin:0 auto; padding:22px 24px 64px; }

  .topbar{ display:flex; align-items:center; justify-content:space-between; margin-bottom:8px; }
  .logo-lockup{ display:flex; align-items:center; gap:10px; }
  .logo-badge{ width:38px;height:38px;border-radius:11px; overflow:hidden; box-shadow:0 6px 14px -5px rgba(15,95,174,0.5); }
  .logo-badge img{ width:100%; height:100%; object-fit:cover; display:block; }
  .wordmark{ font-family:'Baloo 2',sans-serif; font-weight:700; font-size:18px; }
  .wordmark span{ color:var(--owl-orange-500); }
  .topbar-actions{ display:flex; align-items:center; gap:10px; }
  .avatar-chip{
    display:flex; align-items:center; gap:9px; background:var(--surface); border:1px solid var(--line);
    padding:5px 12px 5px 5px; border-radius:999px; box-shadow:var(--shadow-sm);
    text-decoration:none; color:inherit; transition:border-color .15s ease, box-shadow .15s ease;
  }
  .avatar-chip:hover{ border-color:var(--blue-500); box-shadow:var(--shadow-card, 0 8px 18px -8px rgba(15,95,174,0.35)); }
  .avatar-chip .av{
    width:30px;height:30px;border-radius:50%; background:linear-gradient(155deg,var(--blue-500),var(--blue-700));
    display:flex;align-items:center;justify-content:center; color:#fff; font-weight:700; font-size:13px;
  }
  .avatar-chip span.name{ font-size:13.5px; font-weight:700; }
  .logout-btn{
    background:var(--surface); border:1px solid var(--line); color:var(--slate-600); font:700 13px/1 'Inter',sans-serif;
    padding:10px 16px; border-radius:12px; cursor:pointer; box-shadow:var(--shadow-sm);
    transition:color .15s ease, border-color .15s ease;
  }
  .logout-btn:hover{ color:var(--danger); border-color:var(--danger); }

  .back-link{
    display:inline-flex; align-items:center; gap:6px; font-size:13.5px; font-weight:700; color:var(--slate-600);
    text-decoration:none; margin:18px 0 6px; transition:color .15s ease;
  }
  .back-link:hover{ color:var(--blue-600); }
  .back-link svg{ transition:transform .15s ease; }
  .back-link:hover svg{ transform:translateX(-2px); }

  .head-row{ display:flex; align-items:center; justify-content:space-between; margin-bottom:6px; flex-wrap:wrap; gap:12px; }
  h1{ font-family:'Baloo 2',sans-serif; font-size:25px; font-weight:700; margin:0; }
  .sub{ color:var(--slate-600); font-size:13.5px; font-weight:500; margin:2px 0 20px; }

  .new-class-btn{
    display:flex; align-items:center; gap:7px; background:linear-gradient(155deg,var(--blue-500),var(--blue-700));
    color:#fff; font:700 13.5px/1 'Inter',sans-serif; padding:11px 18px; border-radius:12px; border:none; cursor:pointer;
    box-shadow:0 8px 16px -6px rgba(15,95,174,.5); transition:transform .15s ease;
  }
  .new-class-btn:hover{ transform:translateY(-1px); }

  .flash{ font-weight:700; font-size:13px; border-radius:14px; padding:12px 16px; margin-bottom:18px; }
  .flash.success{ background:var(--success-bg); border:1px solid var(--success); color:var(--success); }
  .flash.error{ background:var(--danger-bg); border:1px solid var(--danger); color:var(--danger); }

  /* ---------- Pending lock ---------- */
  .lock-banner{
    display:flex; gap:14px; background:var(--amber-bg); border:1px solid var(--amber); border-radius:18px;
    padding:16px 18px; margin-bottom:22px;
  }
  .lock-banner .ic{ width:34px;height:34px;border-radius:10px; background:var(--amber); color:#fff; display:flex;align-items:center;justify-content:center; flex-shrink:0; }
  .lock-banner .lt{ font-weight:800; font-size:13.5px; color:var(--amber); margin-bottom:3px; }
  .lock-banner .ld{ font-size:12.5px; color:var(--navy-900); font-weight:500; line-height:1.5; opacity:.85; }

  /* ---------- Filter row ---------- */
  .filter-row{ display:flex; align-items:center; gap:10px; margin-bottom:20px; flex-wrap:wrap; }
  .year-select{
    font:700 13px/1 'Inter',sans-serif; padding:9px 14px; border:1.5px solid var(--line); border-radius:11px;
    background:var(--surface); color:var(--navy-900); cursor:pointer; appearance:none;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none'%3E%3Cpath d='M6 9l6 6 6-6' stroke='%235b6b7a' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-repeat:no-repeat; background-position:right 12px center; padding-right:34px;
  }
  .year-select:focus-visible{ outline:2px solid var(--blue-500); outline-offset:2px; }
  .current-year-badge{ font-size:11.5px; font-weight:800; background:var(--success-bg); color:var(--success); padding:5px 11px; border-radius:999px; }
  .readonly-badge{ font-size:11.5px; font-weight:800; background:var(--amber-bg); color:var(--amber); padding:5px 11px; border-radius:999px; }

  /* ---------- New class form ---------- */
  .new-class-form{
    background:var(--surface); border:1px solid var(--line); border-radius:20px; padding:20px;
    box-shadow:var(--shadow-sm); margin-bottom:22px; overflow:hidden;
    max-height:0; opacity:0; padding-top:0; padding-bottom:0; margin-bottom:0; border-width:0;
    transition:max-height .25s ease, opacity .2s ease, padding .25s ease, margin .25s ease;
  }
  .new-class-form.show{ max-height:400px; opacity:1; padding-top:20px; padding-bottom:20px; margin-bottom:22px; border-width:1px; }
  .form-grid{ display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px; }
  .form-grid .full{ grid-column:1/-1; }
  .field label{ font-size:12px; font-weight:700; display:block; margin-bottom:5px; }
  .field .opt{ font-weight:600; color:var(--slate-400); text-transform:none; letter-spacing:0; }
  .field input, .field select{
    width:100%; font:500 13.5px/1 'Inter',sans-serif; padding:10px 12px; border:1.5px solid var(--line); border-radius:10px;
    background:var(--bg-0); color:var(--navy-900); outline:none; transition:border-color .15s ease;
  }
  .field input:focus, .field select:focus{ border-color:var(--blue-500); }
  .field-error{ font-size:11.5px; color:var(--danger); font-weight:600; margin-top:4px; }
  .form-actions{ display:flex; gap:10px; }
  .btn-primary-sm{
    background:var(--blue-600); color:#fff; border:none; padding:10px 18px; border-radius:10px;
    font:700 13px/1 'Inter',sans-serif; cursor:pointer; transition:opacity .15s ease;
  }
  .btn-primary-sm:disabled{ opacity:.6; cursor:not-allowed; }
  .btn-ghost-sm{ background:none; border:1.5px solid var(--line); color:var(--slate-600); padding:10px 18px; border-radius:10px; font:700 13px/1 'Inter',sans-serif; cursor:pointer; }

  /* ---------- Class cards ---------- */
  .class-card{ background:var(--surface); border:1px solid var(--line); border-radius:20px; padding:20px; box-shadow:var(--shadow-sm); margin-bottom:16px; }
  .class-card.readonly{ opacity:.82; }
  .class-head{ display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:14px; flex-wrap:wrap; gap:10px; }
  .class-title{ font-family:'Baloo 2',sans-serif; font-size:18px; font-weight:700; }
  .class-meta{ font-size:12.5px; color:var(--slate-600); font-weight:600; margin-top:2px; }
  .class-actions{ display:flex; align-items:center; gap:8px; }
  .add-learner-btn, .edit-class-btn{
    display:flex; align-items:center; gap:6px; background:var(--surface); border:1.5px solid var(--line); color:var(--slate-600);
    font:700 12.5px/1 'Inter',sans-serif; padding:9px 14px; border-radius:11px; cursor:pointer; transition:border-color .15s ease, color .15s ease;
  }
  .add-learner-btn:hover, .edit-class-btn:hover{ border-color:var(--blue-500); color:var(--blue-600); }
  .roster-empty{ background:var(--bg-0); border:1px dashed var(--line); border-radius:14px; padding:18px; text-align:center; }
  .roster-empty p{ margin:0 0 4px; font-size:13px; font-weight:600; color:var(--slate-600); }
  .roster-empty span{ font-size:12px; color:var(--slate-400); font-weight:500; }

  .card-inline-form{
    background:var(--bg-0); border:1px solid var(--line); border-radius:16px; padding:16px;
    margin-bottom:14px; overflow:hidden;
    max-height:0; opacity:0; padding-top:0; padding-bottom:0; margin-bottom:0; border-width:0;
    transition:max-height .25s ease, opacity .2s ease, padding .25s ease, margin .25s ease;
  }
  .card-inline-form.show{ max-height:320px; opacity:1; padding-top:16px; padding-bottom:16px; margin-bottom:14px; border-width:1px; }

  .roster-list{ display:flex; flex-direction:column; gap:8px; }
  .roster-row{ display:flex; align-items:flex-start; gap:12px; background:var(--bg-0); border:1px solid var(--line); border-radius:12px; padding:10px 14px; }
  .roster-avatar{ font-size:20px; width:36px; height:36px; border-radius:10px; background:var(--sky-50); display:flex; align-items:safe center; justify-content:center; flex-shrink:0; overflow:hidden; }
  .avatar-photo-img{ width:100%; height:100%; object-fit:cover; display:block; }
  .roster-info{ padding-top:1px; }
  .roster-info .rn{ font-weight:700; font-size:13.5px; }
  .roster-info .rm{ font-size:12px; color:var(--slate-600); font-weight:600; margin-top:1px; }
  .roster-info .rh{ font-size:11px; color:var(--slate-400); font-weight:500; margin-top:4px; line-height:1.4; }
  .roster-info .rh-chain{ margin:2px 0 0 0; padding-left:14px; }
  .roster-info summary{ cursor:pointer; }
  .roster-info summary::-webkit-details-marker{ font-size:9px; }

  .empty-note{ text-align:center; padding:40px 20px; color:var(--slate-600); font-size:13.5px; font-weight:500; }
  .empty-note a{ color:var(--blue-600); font-weight:700; text-decoration:none; }
  .empty-note a:hover{ text-decoration:underline; }

  a:focus-visible, button:focus-visible{ outline:2px solid var(--blue-500); outline-offset:2px; }
</style>
</head>
<body>
<div class="shell">

  <div class="topbar">
    <div class="logo-lockup">
      <div class="logo-badge"><img src="{{ asset('images/logo.png') }}" alt="TaraBasa AI logo"></div>
      <span class="wordmark">TaraBasa<span>AI</span></span>
    </div>
    <div class="topbar-actions">
      <a href="{{ route('teacher.profile.edit') }}" class="avatar-chip" aria-label="My Profile">
        <span class="av">{{ strtoupper(substr($teacher->user->first_name, 0, 1)) }}</span>
        <span class="name">{{ $teacher->user->first_name }}</span>
      </a>
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="logout-btn">Log out</button>
      </form>
    </div>
  </div>

  <a href="{{ route('teacher.dashboard') }}" class="back-link">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    Dashboard
  </a>

  <div class="head-row">
    <div>
      <h1>Class Management</h1>
    </div>
    @if ($teacher->status === 'Active' && ! $isPastYear)
      <button type="button" class="new-class-btn" id="newClassToggle">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
        New Class
      </button>
    @endif
  </div>
  <p class="sub">Create classes, edit their details, and add your learners to them.</p>

  @if (session('status'))
    <div class="flash success">{{ session('status') }}</div>
  @endif
  @if (session('classError'))
    <div class="flash error">{{ session('classError') }}</div>
  @endif

  @if ($teacher->status !== 'Active')
    <div class="lock-banner">
      <div class="ic">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><rect x="5" y="10" width="14" height="10" rx="2" stroke="white" stroke-width="1.8"/><path d="M8 10V7a4 4 0 0 1 8 0v3" stroke="white" stroke-width="1.8"/></svg>
      </div>
      <div>
        <div class="lt">Class Management is locked</div>
        <div class="ld">
          @if ($teacher->status === 'Pending')
            Your school verification is still pending. Once approved, you'll be able to create classes and add students. In the meantime, AI activity generation is available with your 2 free credits.
          @else
            Your teacher registration wasn't approved, so Class Management isn't available on this account. Contact your school's TaraBasa admin if you believe this is a mistake.
          @endif
        </div>
      </div>
    </div>
  @endif

  @if ($teacher->status === 'Active')
    <div class="filter-row">
      <form method="GET" action="{{ route('teacher.classes.index') }}" id="yearForm">
        <select name="school_year" class="year-select" id="yearSelect" onchange="document.getElementById('yearForm').submit()">
          @foreach ($availableYears as $year)
            <option value="{{ $year }}" @selected($year === $selectedYear)>SY {{ $year }}</option>
          @endforeach
        </select>
      </form>
      @if ($isCurrentYear)
        <span class="current-year-badge">Current Year</span>
      @elseif ($isPastYear)
        <span class="readonly-badge">Read-only — past school year</span>
      @else
        <span class="current-year-badge" style="background:var(--sky-50); color:var(--blue-600);">Upcoming — set up ahead of time</span>
      @endif
    </div>

    <div class="new-class-form" id="newClassForm">
      <form method="POST" action="{{ route('teacher.classes.store') }}" id="createClassForm">
        @csrf
        <div class="form-grid">
          <div class="field">
            <label for="name">Class Name</label>
            <input type="text" name="name" id="name" placeholder="e.g. CCS" value="{{ old('name') }}" required>
            @error('name') <div class="field-error">{{ $message }}</div> @enderror
          </div>
          <div class="field">
            <label for="section">Section</label>
            <input type="text" name="section" id="section" placeholder="e.g. Section CCS" value="{{ old('section') }}" required>
            @error('section') <div class="field-error">{{ $message }}</div> @enderror
          </div>
          <div class="field">
            <label for="grade_level">Grade Level</label>
            <select name="grade_level" id="grade_level" required>
              @foreach (['Grade 1', 'Grade 2', 'Grade 3'] as $grade)
                <option value="{{ $grade }}" @selected(old('grade_level') === $grade)>{{ $grade }}</option>
              @endforeach
            </select>
            @error('grade_level') <div class="field-error">{{ $message }}</div> @enderror
          </div>
          <div class="field">
            <label for="school_year">School Year</label>
            <select name="school_year" id="school_year" required>
              @php
                $nextYearStart = (int) explode('-', $currentSchoolYear)[0] + 1;
                $nextSchoolYear = $nextYearStart . '-' . ($nextYearStart + 1);
              @endphp
              <option value="{{ $currentSchoolYear }}" @selected(old('school_year', $currentSchoolYear) === $currentSchoolYear)>SY {{ $currentSchoolYear }} (current)</option>
              <option value="{{ $nextSchoolYear }}" @selected(old('school_year') === $nextSchoolYear)>SY {{ $nextSchoolYear }}</option>
            </select>
            @error('school_year') <div class="field-error">{{ $message }}</div> @enderror
          </div>
          <div class="field full">
            <label for="group_tag">Group Tag <span class="opt">optional</span></label>
            <input type="text" name="group_tag" id="group_tag" placeholder="e.g. needs-phonics-support" value="{{ old('group_tag') }}">
          </div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn-primary-sm" id="createClassBtn">Create Class</button>
          <button type="button" class="btn-ghost-sm" id="cancelClassBtn">Cancel</button>
        </div>
      </form>
    </div>
  @endif

  @if ($teacher->status === 'Active' && $classes->isEmpty())
    <div class="empty-note">
      @if (! $isPastYear)
        No classes yet for SY {{ $selectedYear }}. <a href="#" onclick="document.getElementById('newClassToggle')?.click(); return false;">Create your first class</a> to get started.
      @else
        No classes recorded for SY {{ $selectedYear }}.
      @endif
    </div>
  @else
    @foreach ($classes as $class)
      @php
        $isEditTarget = (string) old('target_class_id') === (string) $class->id
          && ($errors->has('name') || $errors->has('grade_level') || $errors->has('section') || $errors->has('group_tag'));
        $isJoinTarget = (string) old('target_class_id') === (string) $class->id && $errors->has('learner_code');
      @endphp
      <div class="class-card {{ $isPastYear ? 'readonly' : '' }}">
        <div class="class-head">
          <div>
            <span class="class-title">{{ $class->name }}</span>
            <div class="class-meta">{{ $class->grade_level }} — {{ $class->section }} · SY {{ $class->school_year }}@if ($class->group_tag) · {{ $class->group_tag }} @endif</div>
          </div>
          @if (! $isPastYear)
            <div class="class-actions">
              <button type="button" class="edit-class-btn" data-toggle="editForm-{{ $class->id }}">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M12.5 4.5l3 3-9 9H3.5v-3l9-9z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Edit
              </button>
              <button type="button" class="add-learner-btn" data-toggle="joinForm-{{ $class->id }}">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6" stroke="currentColor" stroke-width="1.8"/><path d="M17 8v4M15 10h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                Add Learner
              </button>
            </div>
          @else
            <span class="readonly-badge">Read-only</span>
          @endif
        </div>

        @if (! $isPastYear)
          <div class="card-inline-form {{ $isEditTarget ? 'show' : '' }}" id="editForm-{{ $class->id }}">
            <form method="POST" action="{{ route('teacher.classes.update', $class) }}">
              @csrf
              @method('PUT')
              <input type="hidden" name="target_class_id" value="{{ $class->id }}">
              <div class="form-grid">
                <div class="field">
                  <label for="name-{{ $class->id }}">Class Name</label>
                  <input type="text" name="name" id="name-{{ $class->id }}" value="{{ $isEditTarget ? old('name') : $class->name }}" required>
                  @if ($isEditTarget) @error('name') <div class="field-error">{{ $message }}</div> @enderror @endif
                </div>
                <div class="field">
                  <label for="section-{{ $class->id }}">Section</label>
                  <input type="text" name="section" id="section-{{ $class->id }}" value="{{ $isEditTarget ? old('section') : $class->section }}" required>
                  @if ($isEditTarget) @error('section') <div class="field-error">{{ $message }}</div> @enderror @endif
                </div>
                <div class="field">
                  <label for="grade_level-{{ $class->id }}">Grade Level</label>
                  <select name="grade_level" id="grade_level-{{ $class->id }}" required>
                    @foreach (['Grade 1', 'Grade 2', 'Grade 3'] as $grade)
                      <option value="{{ $grade }}" @selected(($isEditTarget ? old('grade_level') : $class->grade_level) === $grade)>{{ $grade }}</option>
                    @endforeach
                  </select>
                  @if ($isEditTarget) @error('grade_level') <div class="field-error">{{ $message }}</div> @enderror @endif
                </div>
                <div class="field full">
                  <label for="group_tag-{{ $class->id }}">Group Tag <span class="opt">optional</span></label>
                  <input type="text" name="group_tag" id="group_tag-{{ $class->id }}" value="{{ $isEditTarget ? old('group_tag') : $class->group_tag }}">
                </div>
              </div>
              <div class="form-actions">
                <button type="submit" class="btn-primary-sm" data-loading-text="Saving…">Save Changes</button>
                <button type="button" class="btn-ghost-sm" data-toggle="editForm-{{ $class->id }}">Cancel</button>
              </div>
            </form>
          </div>

          <div class="card-inline-form {{ $isJoinTarget ? 'show' : '' }}" id="joinForm-{{ $class->id }}">
            <form method="POST" action="{{ route('teacher.classes.join-learner', $class) }}">
              @csrf
              <input type="hidden" name="target_class_id" value="{{ $class->id }}">
              <div class="field">
                <label for="learner_code-{{ $class->id }}">Learner Code</label>
                <input type="text" name="learner_code" id="learner_code-{{ $class->id }}" placeholder="TB-XXXXX"
                       value="{{ $isJoinTarget ? old('learner_code') : '' }}">
                @if ($isJoinTarget) @error('learner_code') <div class="field-error">{{ $message }}</div> @enderror @endif
              </div>
              <div class="form-actions">
                <button type="submit" class="btn-primary-sm" data-loading-text="Adding…">Add to Class</button>
                <button type="button" class="btn-ghost-sm" data-toggle="joinForm-{{ $class->id }}">Cancel</button>
              </div>
            </form>
          </div>
        @endif

        @if ($class->learners->isEmpty())
          <div class="roster-empty">
            <p>No learners in this class yet.</p>
            @if (! $isPastYear)
              <span>Use "Add Learner" above with a Learner's code to enroll them.</span>
            @endif
          </div>
        @else
          <div class="roster-list">
            @foreach ($class->learners as $learner)
              <div class="roster-row">
                <span class="roster-avatar">
                  @if ($learner->avatar_photo_path)
                    <img src="{{ Storage::url($learner->avatar_photo_path) }}" alt="{{ $learner->first_name }}" class="avatar-photo-img">
                  @else
                    {{ $learner->avatar_id }}
                  @endif
                </span>
                <div class="roster-info">
                  <div class="rn">{{ $learner->first_name }} {{ $learner->last_name }}</div>
                  <div class="rm">{{ $learner->learner_code }} · {{ $learner->grade_level }}</div>
                  @php $promotionHistory = $learner->promotionHistorySummary(); @endphp
                  <div class="rh">
                    @if (! empty($promotionHistory['chain']))
                      <details>
                        <summary>{{ $promotionHistory['headline'] }}</summary>
                        <ul class="rh-chain">
                          @foreach ($promotionHistory['chain'] as $step)
                            <li>{{ $step }}</li>
                          @endforeach
                        </ul>
                      </details>
                    @else
                      {{ $promotionHistory['headline'] }}
                    @endif
                  </div>
                  <div class="rh">{{ $learner->proficiencyTrajectorySummary() }}</div>
                </div>
              </div>
            @endforeach
          </div>
        @endif
      </div>
    @endforeach
  @endif

@if ($teacher->status === 'Active')
<script>
  const newClassForm = document.getElementById('newClassForm');
  const newClassBtn = document.getElementById('newClassToggle');
  newClassBtn.addEventListener('click', () => newClassForm.classList.toggle('show'));
  document.getElementById('cancelClassBtn').addEventListener('click', () => newClassForm.classList.remove('show'));

  // Real (not fake) loading state — disables the button the instant the form
  // is submitted, so it can't be double-clicked while the request is in flight.
  document.getElementById('createClassForm').addEventListener('submit', function () {
    const btn = document.getElementById('createClassBtn');
    btn.disabled = true;
    btn.textContent = 'Creating…';
  });

  @if ($errors->any() && ! old('target_class_id'))
    newClassForm.classList.add('show');
  @endif

  // Per-card Edit/Add Learner toggles — one delegated listener instead of
  // per-button IDs, since there's one of each per class card.
  document.querySelectorAll('[data-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById(btn.dataset.toggle)?.classList.toggle('show');
    });
  });

  // Same real loading-state pattern as the Create Class form, applied to
  // every per-card Edit/Add Learner form via delegation.
  document.querySelectorAll('.card-inline-form form').forEach(function (form) {
    form.addEventListener('submit', function () {
      const btn = form.querySelector('button[type="submit"]');
      btn.disabled = true;
      btn.textContent = btn.dataset.loadingText || 'Saving…';
    });
  });
</script>
@endif
</div>
</body>
</html>
