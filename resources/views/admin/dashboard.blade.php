<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TaraBasa AI — Admin Dashboard</title>
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
    --amber:#c9820b; --amber-bg:#fef6e6;
    --danger:#d64545; --danger-bg:#fdecec;
    --slate-bg:#eef1f4; --owl-orange-500:#ef8d2a;
    --shadow-sm:0 1px 2px rgba(19,31,43,0.06);
  }
  *{box-sizing:border-box;} html,body{margin:0;padding:0;}
  body{
    min-height:100vh; font-family:'Inter',sans-serif; color:var(--navy-900);
    background: radial-gradient(1100px 500px at 90% -10%, var(--sky-100), transparent 55%), var(--bg-0);
    background-attachment:fixed;
  }
  .shell{ max-width:1080px; margin:0 auto; padding:22px 24px 64px; }

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
  }
  .avatar-chip .av{
    width:30px;height:30px;border-radius:50%; background:linear-gradient(155deg,var(--navy-900),#3a4b5c);
    display:flex;align-items:center;justify-content:center; color:#fff; font-weight:700; font-size:13px;
  }
  .avatar-chip span.name{ font-size:13.5px; font-weight:700; }
  .logout-btn{
    background:var(--surface); border:1px solid var(--line); color:var(--slate-600); font:700 13px/1 'Inter',sans-serif;
    padding:10px 16px; border-radius:12px; cursor:pointer; box-shadow:var(--shadow-sm);
    transition:color .15s ease, border-color .15s ease;
  }
  .logout-btn:hover{ color:var(--danger); border-color:var(--danger); }

  h1{ font-family:'Baloo 2',sans-serif; font-size:25px; font-weight:700; margin:18px 0 6px; }
  .sub{ color:var(--slate-600); font-size:13.5px; font-weight:500; margin:0 0 22px; }

  .flash{
    background:var(--success-bg); border:1px solid var(--success); color:var(--success); font-weight:700;
    font-size:13px; border-radius:14px; padding:12px 16px; margin-bottom:20px;
  }

  .stat-row{ display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:30px; }
  .stat-card{ background:var(--surface); border:1px solid var(--line); border-radius:18px; padding:18px; box-shadow:var(--shadow-sm); }
  .stat-card .val{ font-family:'Baloo 2',sans-serif; font-size:26px; font-weight:700; }
  .stat-card .lbl{ font-size:12.5px; color:var(--slate-600); font-weight:700; margin-top:2px; }

  section.panel{ background:var(--surface); border:1px solid var(--line); border-radius:20px; box-shadow:var(--shadow-sm); padding:22px; margin-bottom:24px; }
  section.panel h2{ font-family:'Baloo 2',sans-serif; font-size:17px; font-weight:700; margin:0 0 3px; }
  section.panel .panel-sub{ color:var(--slate-600); font-size:12.5px; font-weight:500; margin:0 0 16px; }

  table{ width:100%; border-collapse:collapse; }
  thead th{
    text-align:left; font-size:11px; font-weight:700; color:var(--slate-600); text-transform:uppercase;
    letter-spacing:.03em; padding:0 10px 10px; border-bottom:1px solid var(--line);
  }
  tbody td{ padding:13px 10px; border-bottom:1px solid var(--line); font-size:13.5px; font-weight:500; vertical-align:middle; }
  tbody tr:last-child td{ border-bottom:none; }
  tbody tr{ transition:background-color .15s ease; }
  tbody tr:hover{ background-color:var(--sky-50); }

  .status-pill{ font-size:11px; font-weight:800; padding:4px 10px; border-radius:999px; display:inline-block; }
  .status-pill.pending{ background:var(--amber-bg); color:var(--amber); }
  .status-pill.active{ background:var(--success-bg); color:var(--success); }
  .status-pill.rejected{ background:var(--danger-bg); color:var(--danger); }
  .status-pill.inactive{ background:var(--slate-bg); color:var(--slate-600); }

  .row-actions{ display:flex; gap:8px; }
  .row-actions form{ display:inline; }
  .btn-sm{
    padding:8px 14px; border-radius:10px; font:700 12.5px/1 'Inter',sans-serif; cursor:pointer; border:1.5px solid transparent;
    white-space:nowrap; transition:background-color .15s ease, color .15s ease, border-color .15s ease, transform .15s ease;
  }
  .btn-sm:hover{ transform:translateY(-1px); }
  .btn-activate{ background:var(--success); color:#fff; }
  .btn-activate:hover{ background:#188a72; }
  .btn-reject{ background:var(--danger-bg); color:var(--danger); }
  .btn-reject:hover{ background:var(--danger); color:#fff; }
  .btn-deactivate{ background:var(--danger-bg); color:var(--danger); }
  .btn-deactivate:hover{ background:var(--danger); color:#fff; }
  .btn-reactivate{ background:var(--success-bg); color:var(--success); }
  .btn-reactivate:hover{ background:var(--success); color:#fff; }
  .btn-sm:focus-visible, .logout-btn:focus-visible{ outline:2px solid var(--blue-500); outline-offset:2px; }

  .empty-note{ text-align:center; padding:32px 20px; color:var(--slate-600); font-size:13.5px; font-weight:500; }

  .table-scroll{ overflow-x:auto; }

  @media (max-width:640px){
    .topbar{ flex-wrap:wrap; gap:10px; }
    .avatar-chip span.name{ display:none; }
    .logout-btn{ white-space:nowrap; }
  }

  @media (max-width:760px){
    .stat-row{ grid-template-columns:1fr; }
    table, thead, tbody, th, td, tr{ display:block; }
    thead{ display:none; }
    tbody tr{ border:1px solid var(--line); border-radius:14px; margin-bottom:10px; padding:10px; }
    tbody td{ border-bottom:none; display:flex; justify-content:space-between; gap:10px; }
    tbody td::before{ content: attr(data-label); font-weight:700; color:var(--slate-600); }
  }
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
      <div class="avatar-chip">
        <span class="av">{{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}</span>
        <span class="name">{{ auth()->user()->first_name }}</span>
      </div>
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="logout-btn">Log out</button>
      </form>
    </div>
  </div>

  <h1>Admin Dashboard</h1>
  <p class="sub">Verify Teacher registrations and manage account status.</p>

  @if (session('status'))
    <div class="flash">{{ session('status') }}</div>
  @endif

  <div class="stat-row">
    <div class="stat-card"><div class="val">{{ $pendingTeacherCount }}</div><div class="lbl">Pending Teachers</div></div>
    <div class="stat-card"><div class="val">{{ $activeTeacherCount }}</div><div class="lbl">Active Teachers</div></div>
    <div class="stat-card"><div class="val">{{ $totalAccountCount }}</div><div class="lbl">Total Accounts</div></div>
  </div>

  <section class="panel">
    <h2>Pending Teacher Approvals</h2>
    <p class="panel-sub">Confirm Employee ID + School Name before granting access to real class rosters.</p>

    @if ($pendingTeachers->isEmpty())
      <div class="empty-note">No pending teacher registrations right now.</div>
    @else
      <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>First Name</th>
            <th>Last Name</th>
            <th>School Name</th>
            <th>Employee ID</th>
            <th>Email</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach ($pendingTeachers as $teacher)
            <tr>
              <td data-label="First Name">{{ $teacher->user->first_name }}</td>
              <td data-label="Last Name">{{ $teacher->user->last_name }}</td>
              <td data-label="School Name">{{ $teacher->school_name }}</td>
              <td data-label="Employee ID">{{ $teacher->employee_id }}</td>
              <td data-label="Email">{{ $teacher->user->email }}</td>
              <td data-label="Actions">
                <div class="row-actions">
                  <form method="POST" action="{{ route('admin.teachers.activate', $teacher) }}">
                    @csrf
                    <button type="submit" class="btn-sm btn-activate">Activate</button>
                  </form>
                  <form method="POST" action="{{ route('admin.teachers.reject', $teacher) }}">
                    @csrf
                    <button type="submit" class="btn-sm btn-reject">Reject</button>
                  </form>
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
      </div>
    @endif
  </section>

  <section class="panel">
    <h2>All Accounts</h2>
    <p class="panel-sub">Every Teacher and Parent account. Deactivating blocks login immediately.</p>

    @if ($accounts->isEmpty())
      <div class="empty-note">No accounts yet.</div>
    @else
      <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>First Name</th>
            <th>Last Name</th>
            <th>User Type</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach ($accounts as $account)
            <tr>
              <td data-label="First Name">{{ $account->first_name }}</td>
              <td data-label="Last Name">{{ $account->last_name }}</td>
              <td data-label="User Type">{{ $account->user_type }}</td>
              <td data-label="Status">
                <span class="status-pill {{ strtolower($account->status) }}">{{ $account->status }}</span>
              </td>
              <td data-label="Action">
                <form method="POST" action="{{ route('admin.users.toggle-status', $account) }}" class="status-action-form">
                  @csrf
                  @if ($account->status === 'Active')
                    <button type="submit" class="btn-sm btn-deactivate">Deactivate</button>
                  @else
                    <button type="submit" class="btn-sm btn-reactivate">Activate</button>
                  @endif
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
      </div>
    @endif
  </section>

</div>

<script>
  // Real (not fake) loading state on every Activate/Reject/Deactivate
  // button — the request is genuinely in flight, so disabling prevents a
  // double-submit and gives real feedback instead of the button just
  // sitting there looking unresponsive for the round-trip.
  document.querySelectorAll('.row-actions form, .status-action-form').forEach(form => {
    form.addEventListener('submit', function (e) {
      const btn = e.submitter || form.querySelector('button[type="submit"]');
      if (btn) {
        btn.disabled = true;
        btn.dataset.originalText = btn.textContent;
        btn.textContent = '…';
      }
    });
  });
</script>
</body>
</html>
