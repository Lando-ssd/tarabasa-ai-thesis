{{--
  The privacy page (public, no sign in). Plain words, because parents and teachers read it, and it
  says only what the app really does (checked against the code on 2026-10-05): recordings are not saved
  by the app, nothing is sold, there are no analytics or advertising cookies, and the outside services
  are listed. Standalone like the error pages, so it works without the rest of the layout.
  If the app starts doing something new with data (a new outside service, storing recordings), change
  this page in the same commit.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Privacy | TaraBasa AI</title>
<meta name="description" content="What TaraBasa AI collects about parents, teachers and children, why, who sees it, and how to ask for it to be removed.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
<noscript><link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"></noscript>
<style>
  :root{ --ink:#15283a; --muted:#587086; --line:#dbe7f3; --bg:#f5f9ff; --blue:#1c7ed6; --orange:#ee8a26; --orange-lip:#b4560b; }
  *{ box-sizing:border-box; } html,body{ margin:0; }
  body{ min-height:100vh; padding:28px 20px 48px; background:var(--bg); color:var(--ink); font:16px/1.65 'Inter',system-ui,sans-serif; }
  .page{ width:100%; max-width:760px; margin:0 auto; background:#fff; border:2px solid var(--line); border-radius:28px; box-shadow:0 6px 0 #e3ebf3; padding:34px 34px 30px; }
  .brand{ font:800 28px/1 'Baloo 2',sans-serif; margin-bottom:14px; } .brand span{ color:var(--orange); }
  h1{ margin:0 0 6px; font:700 34px/1.15 'Baloo 2',sans-serif; }
  .updated{ margin:0 0 22px; color:var(--muted); font-size:14px; }
  h2{ margin:28px 0 8px; font:700 22px/1.2 'Baloo 2',sans-serif; color:var(--ink); }
  p{ margin:0 0 12px; } ul{ margin:0 0 12px; padding-left:22px; } li{ margin:0 0 6px; }
  .lead{ font-size:18px; color:var(--ink); }
  .note{ margin:14px 0 0; padding:12px 16px; border-left:4px solid var(--blue); background:#eef6ff; border-radius:0 12px 12px 0; }
  a{ color:var(--blue); }
  a.btn{ display:inline-block; margin-top:22px; text-decoration:none; padding:13px 24px 12px; border-radius:16px; font:700 18px/1.1 'Baloo 2',sans-serif; letter-spacing:.03em; color:#fff; background:linear-gradient(180deg,#f9a544,var(--orange)); box-shadow:0 5px 0 var(--orange-lip); }
  a:focus-visible{ outline:3px solid var(--blue); outline-offset:3px; }
  @media (max-width:520px){ .page{ padding:26px 20px 24px; border-radius:22px; } h1{ font-size:28px; } }
</style>
</head>
<body>
<main class="page" role="main">
  <div class="brand">TaraBasa<span>AI</span></div>
  <h1>Privacy</h1>
  <p class="updated">Last updated 5 October 2026</p>

  <p class="lead">TaraBasa AI is a reading practice website for Grade 1 to 3 learners. It was built by a student team as a capstone project. This page says what it keeps, why, and who can see it.</p>

  <h2>What we keep</h2>
  <ul>
    <li><b>Parents and teachers:</b> name, email address and a password (kept scrambled, never readable). A contact number is optional. Teachers also give a school name and employee ID, which the site administrator checks.</li>
    <li><b>Children:</b> a parent adds each child. We keep the child's first and last name, grade, an avatar or an optional photo the parent chooses, and a 4 digit PIN (kept scrambled). The parent may also tell us the child's reading stage, learning style, home language, supports and interests so the first reading check starts in the right place.</li>
    <li><b>Reading activity:</b> scores, which words were missed, points, badges, days read and reading level.</li>
    <li><b>Recordings:</b> a child's voice is recorded in the browser only when the child taps the microphone. It is sent to be scored. <b>The app does not save the recording.</b> We keep the result (the scores and the words), not the audio.</li>
  </ul>

  <h2>Why</h2>
  <p>To score a reading, choose what to read next, show progress to a child's parent and teacher, and send account emails (verifying an email address, resetting a password).</p>

  <h2>Who can see it</h2>
  <ul>
    <li>A child's parent or parents.</li>
    <li>The teacher of a class the child has joined: the child's name, reading level and progress.</li>
    <li>The site administrator, to manage accounts.</li>
  </ul>
  <p>We do not sell information, show advertising, or use analytics or advertising cookies. The only cookies are the ones needed to keep a person signed in.</p>

  <h2>Services that help us</h2>
  <ul>
    <li><b>Reading scoring service:</b> receives the recording and the words of the activity, and returns the scores.</li>
    <li><b>Activity recommendation service:</b> receives scores and a number for the child, not the child's name.</li>
    <li><b>AI activity writer:</b> receives only what a teacher types (grade, skill, topic and notes). It receives no child information.</li>
    <li><b>Gmail:</b> sends account emails from the TaraBasa AI mailbox.</li>
    <li><b>Google sign in:</b> optional, for adults. We receive the name and email address of the Google account.</li>
    <li><b>Google Fonts:</b> shows our text in the right typeface. Google can see a visitor's internet address when a page loads.</li>
    <li>The device's own voice reads words aloud. That happens on the device and nothing is sent anywhere.</li>
  </ul>

  <h2>Children</h2>
  <p>A child cannot sign up alone. A parent creates the child's account and chooses what to enter. The photo is optional.</p>

  <h2>Keeping and removing information</h2>
  <p>Information stays while the account is in use. A parent can ask for a child's records, or an adult for their own account, to be removed.</p>
  @php $contact = config('app.privacy_contact'); @endphp
  @if ($contact)
    <p>To ask, write to <a href="mailto:{{ $contact }}">{{ $contact }}</a>.</p>
  @else
    <p>To ask, tell the school administrator or the TaraBasa AI team who gave you access.</p>
  @endif

  <h2>Keeping it safe</h2>
  <p>Passwords and PINs are stored scrambled, and the live site uses an encrypted connection. No website can promise perfect security, so we keep the information we hold small.</p>

  <h2>Changes</h2>
  <p>If this changes, we will update this page and the date at the top.</p>

  <a class="btn" href="{{ url('/') }}">Back to the home page</a>
</main>
</body>
</html>
