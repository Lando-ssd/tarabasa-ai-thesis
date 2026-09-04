<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Activities — TaraBasa AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --sky-50:#eef6ff; --sky-100:#dcedff;
    --blue-500:#1c7ed6; --blue-600:#0f5fae; --blue-700:#0a3d73;
    --navy-900:#131f2b; --slate-600:#5b6b7a; --slate-400:#8a97a3;
    --line:#e3ebf2; --surface:#ffffff; --bg-0:#f6faff;
    --success:#1f9e83; --success-bg:#e9f7f3;
    --danger:#d64545; --danger-bg:#fdecec;
    --amber:#c9820b; --amber-bg:#fef6e6;
    --purple:#7c5cd6; --purple-bg:#f1edfc;
    --shadow-sm:0 1px 2px rgba(19,31,43,0.06);
  }
  *{box-sizing:border-box;} html,body{margin:0;padding:0;}
  body{
    min-height:100vh; font-family:'Inter',sans-serif; color:var(--navy-900);
    background: radial-gradient(1100px 500px at 90% -10%, var(--sky-100), transparent 55%), var(--bg-0);
    background-attachment:fixed;
  }
  .shell{ max-width:820px; margin:0 auto; padding:22px 24px 64px; }

  .topbar{ display:flex; align-items:center; justify-content:space-between; margin-bottom:8px; }
  .logo-lockup{ display:flex; align-items:center; gap:10px; }
  .logo-badge{ width:38px;height:38px;border-radius:11px; overflow:hidden; box-shadow:0 6px 14px -5px rgba(15,95,174,0.5); }
  .logo-badge img{ width:100%; height:100%; object-fit:cover; display:block; }
  .wordmark{ font-family:'Baloo 2',sans-serif; font-weight:700; font-size:18px; }
  .wordmark span{ color:var(--blue-500); }
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
    text-decoration:none; margin:18px 0 6px;
  }
  .back-link:hover{ color:var(--blue-600); }
  .head-row{ display:flex; align-items:center; justify-content:space-between; margin-bottom:18px; flex-wrap:wrap; gap:12px; }
  h1{ font-family:'Baloo 2',sans-serif; font-size:24px; font-weight:700; margin:0; }
  .generate-link{
    display:inline-flex; align-items:center; gap:7px; background:linear-gradient(155deg,var(--blue-500),var(--blue-600));
    color:#fff; font:700 13.5px/1 'Inter',sans-serif; padding:11px 18px; border-radius:12px; text-decoration:none;
  }

  .flash{ font-weight:700; font-size:13px; border-radius:14px; padding:12px 16px; margin-bottom:18px; }
  .flash.success{ background:var(--success-bg); border:1px solid var(--success); color:var(--success); }
  .flash.error{ background:var(--danger-bg); border:1px solid var(--danger); color:var(--danger); }

  .lock-banner{
    display:flex; gap:14px; background:var(--amber-bg); border:1px solid var(--amber); border-radius:18px;
    padding:16px 18px; margin-bottom:20px; font-size:12.5px; color:var(--navy-900); font-weight:500; line-height:1.5;
  }

  .section-title{ font-family:'Baloo 2',sans-serif; font-size:15.5px; font-weight:700; margin:26px 0 12px; }
  .section-title:first-of-type{ margin-top:0; }
  .search-row{ position:relative; margin-bottom:6px; }
  .search-row svg{ position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--slate-400); pointer-events:none; }
  #activity-search{
    width:100%; font:500 14px/1 'Inter',sans-serif; padding:12px 14px 12px 40px; border:1.5px solid var(--line);
    border-radius:12px; background:var(--surface); color:var(--navy-900); outline:none;
    transition:border-color .15s ease, box-shadow .15s ease;
  }
  #activity-search::placeholder{ color:var(--slate-400); font-weight:500; }
  #activity-search:focus{ border-color:var(--blue-500); box-shadow:0 0 0 4px rgba(15,95,174,0.12); }
  .no-match-note{ display:none; color:var(--slate-600); font-size:13.5px; font-weight:500; padding:14px 2px; }

  .activity-card{ background:var(--surface); border:1px solid var(--line); border-radius:18px; padding:18px; box-shadow:var(--shadow-sm); margin-bottom:14px; }
  .tag-row{ display:flex; gap:7px; flex-wrap:wrap; margin-bottom:9px; }
  .tag{ font-size:11px; font-weight:800; padding:3px 9px; border-radius:999px; }
  .tag.status-draft{ background:var(--sky-50); color:var(--blue-600); }
  .tag.status-approved{ background:var(--success-bg); color:var(--success); }
  .tag.status-rejected{ background:var(--danger-bg); color:var(--danger); }
  .tag.type{ background:var(--purple-bg); color:var(--purple); }
  .tag.diff{ background:var(--bg-0); color:var(--slate-600); border:1px solid var(--line); }

  .act-title{ font-family:'Baloo 2',sans-serif; font-size:16px; font-weight:700; margin:0 0 2px; }
  .act-meta{ font-size:12.5px; color:var(--slate-600); font-weight:600; margin-bottom:10px; }
  .act-passage{
    font-size:13px; color:var(--navy-900); font-weight:500; line-height:1.6; background:var(--bg-0);
    border:1px solid var(--line); border-radius:12px; padding:12px; margin-bottom:12px; white-space:pre-wrap;
  }

  .actions{ display:flex; gap:8px; flex-wrap:wrap; }
  .actions button, .actions a{
    padding:9px 15px; border-radius:10px; font:700 12.5px/1 'Inter',sans-serif; cursor:pointer; text-decoration:none;
    transition:transform .15s ease, background-color .15s ease, border-color .15s ease, opacity .15s ease;
  }
  .actions button:hover, .actions a:hover{ transform:translateY(-1px); }
  .actions button:disabled{ opacity:.6; cursor:not-allowed; transform:none; }
  .btn-approve{ background:var(--success); color:#fff; border:none; }
  .btn-approve:hover{ background:#188a72; }
  .btn-edit{ background:var(--surface); color:var(--navy-900); border:1.5px solid var(--line); }
  .btn-edit:hover{ border-color:var(--blue-500); color:var(--blue-600); }
  .btn-reject{ background:var(--danger-bg); color:var(--danger); border:1px solid transparent; }
  .btn-reject:hover{ background:var(--danger); color:#fff; }
  .muted-note{ font-size:12px; color:var(--slate-400); font-weight:600; align-self:center; }

  .edit-form{ display:none; margin-top:14px; padding-top:14px; border-top:1px dashed var(--line); }
  .edit-form.show{ display:block; }
  .edit-form label{ font-size:12px; font-weight:700; display:block; margin-bottom:5px; }
  .edit-form input, .edit-form textarea{
    width:100%; font:500 13.5px/1.5 'Inter',sans-serif; padding:10px 12px; border:1.5px solid var(--line); border-radius:10px;
    background:var(--bg-0); color:var(--navy-900); outline:none; margin-bottom:10px; box-sizing:border-box;
  }
  .edit-form textarea{ min-height:90px; resize:vertical; }

  .empty-note{ text-align:center; padding:30px 20px; color:var(--slate-600); font-size:13.5px; font-weight:500; background:var(--surface); border:1px dashed var(--line); border-radius:14px; }

  .btn-assign{ background:var(--blue-600); color:#fff; border:none; }
  .btn-assign:hover{ background:var(--blue-700); }
  .assigned-to{ font-size:12px; color:var(--slate-600); font-weight:600; margin:0 0 10px; }
  .assigned-to b{ color:var(--navy-900); }
  .assign-target-row{ display:flex; gap:14px; margin-bottom:10px; }
  .assign-target-row label{ display:flex; align-items:center; gap:5px; font-size:12.5px; font-weight:700; margin-bottom:0; }
  .assign-field{ display:none; }
  .assign-field.show{ display:block; }
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
    <h1>My Activities</h1>
    <a href="{{ route('teacher.activities.create') }}" class="generate-link">Generate Activity</a>
  </div>

  @if (session('status'))
    <div class="flash success">{{ session('status') }}</div>
  @endif
  @if ($errors->any())
    <div class="flash error">{{ $errors->first() }}</div>
  @endif

  @if ($teacher->status !== 'Active')
    <div class="lock-banner">
      Approve/Edit/Reject are locked until your school verification is approved. You can still see your generated Drafts below.
    </div>
  @endif

  @if ($drafts->count() + $approved->count() + $rejected->count() > 5)
    <div class="search-row">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M21 21l-4.3-4.3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      <input type="search" id="activity-search" placeholder="Search by title, grade, competency, or topic&hellip;" autocomplete="off">
    </div>
    <p class="no-match-note" id="activity-no-match">No activities match "<span></span>".</p>
  @endif

  <div class="section-title" data-section-title>Drafts Awaiting Review ({{ $drafts->count() }})</div>
  @forelse ($drafts as $activity)
    @include('teacher.activities._card', ['activity' => $activity, 'activityTypeLabels' => $activityTypeLabels, 'teacher' => $teacher])
  @empty
    <div class="empty-note">No drafts waiting — generate a new activity to see it here.</div>
  @endforelse

  <div class="section-title" data-section-title>Approved ({{ $approved->count() }})</div>
  @forelse ($approved as $activity)
    @include('teacher.activities._card', [
      'activity' => $activity,
      'activityTypeLabels' => $activityTypeLabels,
      'teacher' => $teacher,
      'assignLearners' => $assignLearners,
      'assignClasses' => $assignClasses,
      'assignGroupTags' => $assignGroupTags,
    ])
  @empty
    <div class="empty-note">Nothing approved yet.</div>
  @endforelse

  <div class="section-title" data-section-title>Rejected ({{ $rejected->count() }})</div>
  @forelse ($rejected as $activity)
    @include('teacher.activities._card', ['activity' => $activity, 'activityTypeLabels' => $activityTypeLabels, 'teacher' => $teacher])
  @empty
    <div class="empty-note">Nothing rejected.</div>
  @endforelse
</div>

<script>
  document.querySelectorAll('[data-toggle-edit]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById(btn.dataset.toggleEdit)?.classList.toggle('show');
    });
  });

  // Assign forms: only the radio-selected target field is shown AND
  // enabled — a disabled <select> doesn't submit, so the server always
  // sees exactly one of the three assign_* fields populated.
  document.querySelectorAll('.assign-target-row:not(.share-target-row)').forEach(function (row) {
    const form = row.closest('form');
    const fields = form.querySelectorAll('.assign-field');
    function sync() {
      const checked = row.querySelector('input[type="radio"]:checked').value;
      fields.forEach(function (field) {
        const active = field.dataset.for === checked;
        field.classList.toggle('show', active);
        field.disabled = !active;
      });
    }
    row.querySelectorAll('input[type="radio"]').forEach(function (radio) {
      radio.addEventListener('change', sync);
    });
    sync();
  });

  // Share to Repository: the price field only matters (and only submits
  // meaningfully) when "Paid" is picked — disabled otherwise so the
  // server never sees a stray price value alongside price_type=Free.
  document.querySelectorAll('[data-share-radio]').forEach(function (radio) {
    radio.addEventListener('change', function () {
      const formId = radio.dataset.shareRadio;
      const priceField = document.getElementById('price-' + formId);
      const isPaid = document.querySelector('input[data-share-radio="' + formId + '"][value="Paid"]').checked;
      priceField.disabled = !isPaid;
      if (isPaid) priceField.focus();
    });
  });

  // Real (not fake) loading state on every Approve/Reject/Save/Assign
  // form — disables the clicked button so the in-flight request can't be
  // double-submitted, same pattern used on Class Management.
  document.querySelectorAll('.activity-card form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      const btn = e.submitter || form.querySelector('button[type="submit"]');
      if (!btn) return;
      btn.disabled = true;
      btn.dataset.originalText = btn.textContent;
      btn.textContent = '…';
    });
  });

  // Client-side search only shown once the list is long enough to need
  // it (see the conditional above in the markup) — no server round trip,
  // no AJAX, consistent with this app's own convention of favoring plain
  // page interactions over JS-heavy ones except where it clearly helps.
  const activitySearch = document.getElementById('activity-search');
  if (activitySearch) {
    const cards = Array.from(document.querySelectorAll('.activity-card'));
    const sectionTitles = Array.from(document.querySelectorAll('[data-section-title]'));
    const noMatchNote = document.getElementById('activity-no-match');
    activitySearch.addEventListener('input', function () {
      const q = activitySearch.value.trim().toLowerCase();
      let anyVisible = false;
      cards.forEach(function (card) {
        const match = !q || card.dataset.search.includes(q);
        card.style.display = match ? '' : 'none';
        if (match) anyVisible = true;
      });
      sectionTitles.forEach(function (title) {
        const section = [];
        let node = title.nextElementSibling;
        while (node && !node.hasAttribute('data-section-title')) {
          if (node.classList.contains('activity-card')) section.push(node);
          node = node.nextElementSibling;
        }
        const sectionHasVisible = !q || section.length === 0 || section.some(function (c) { return c.style.display !== 'none'; });
        title.style.display = sectionHasVisible ? '' : 'none';
      });
      noMatchNote.style.display = (q && !anyVisible) ? 'block' : 'none';
      noMatchNote.querySelector('span').textContent = q;
    });
  }
</script>
</body>
</html>
