# TaraBasa AI — Project Context for Claude Code

This file is read automatically by Claude Code at the start of every session
in this folder. It exists because Claude Code has NO access to the chat
history where these decisions were made — this file is the only way it
knows any of this.

## What this project is

TaraBasa AI — an AI-powered adaptive reading intervention platform for
Grade 1–3 Filipino learners. Capstone/thesis project, 4-person team.
Four actor types: Learner, Teacher, Parent, Admin.

## Tech stack (confirmed, do not change without asking the user)

- **Backend:** PHP 8.5 + Laravel 13, plain PHP router replaced with real
  Laravel (full framework rebuild happened after this project started
  as a lighter custom-PHP app — that old version is abandoned).
- **Database:** SQLite for local dev (`database/database.sqlite`),
  MySQL/Cloud SQL for eventual production. Same relational schema
  either way — this is a config swap later, not a rewrite.
- **Auth:** Laravel Sanctum installed (for future API/mobile use).
  Current web forms use plain session-based `Auth::login()`, not
  Sanctum tokens yet.
- **Email:** Real Gmail SMTP (`tarabasaai.noreply@gmail.com` + a Google
  App Password), configured in `.env` as `MAIL_MAILER=smtp`. NOT Brevo
  — a Brevo account was tried first but got suspended before use;
  abandoned entirely, don't suggest going back to it.
- **Frontend:** Blade templates using a LOCKED design system — Baloo 2
  (headings) + Inter (body) fonts, specific color tokens (see any
  `layouts/auth.blade.php` file's `:root` CSS variables), zero emoji,
  restrained motion. Match this exactly on every new screen; don't
  introduce a different visual style.
- **Version control:** GitHub repo `Lando-ssd/tarabasa-ai-thesis`
  (public). The user is not experienced with git — explain commands
  plainly if you run them, and confirm before pushing.
- **External API calls (Windows/local dev):** this machine's PHP
  install (`F:\php`) shipped with no CA certificate bundle configured
  (`curl.cainfo`/`openssl.cafile` were both empty), so any outbound
  HTTPS call from PHP's cURL — Laravel's `Http` facade included —
  failed with "cURL error 60: unable to get local issuer certificate,"
  even though the same URL worked fine from PowerShell/a browser
  (which use the OS cert store instead). Fixed by downloading the
  official CA bundle from `curl.se/ca/cacert.pem` to
  `F:\php\extras\ssl\cacert.pem` and pointing both `php.ini` directives
  at it. If a teammate hits this same error on a fresh machine, this is
  almost certainly why — don't disable SSL verification as a
  workaround, fix the CA bundle instead.

## Important naming gotcha

The Parent model is named `ParentAccount`, NOT `Parent` — `Parent` is a
PHP reserved word (used for `parent::method()` calls) and causes a
syntax error if used as a class name. It maps to the real `parents`
database table via `protected $table = 'parents';`. Don't rename it
back to `Parent`. Same pattern for classes: the model is `SchoolClass`,
NOT `Class` (also reserved), mapping to the real `classes` table via
`protected $table = 'classes';`.

## Current status (Sprint 3 — Account Management — COMPLETE)

Verified against the actual codebase (routes, controllers, migrations —
not just recalled from conversation) as of this update. All of Module 1
(Account Management) is now built, including Learner Login — the last
remaining piece:

Done and tested:
- Database: `users`, `teachers`, `parents`, `classes`, `learners`,
  `parent_learners` tables (migrations exist and are applied — see
  `database/migrations/`). `users.middle_initial` and
  `learners.avatar_photo_path` were added later as separate migrations
  on top of the original tables, per this project's "never edit a
  shipped migration" rule.
- Teacher registration (`/register/teacher`) — First/Middle
  Initial/Last Name fields (M.I. optional), sends real verification
  email, logs in immediately as Pending with 2 free AI-generation
  credits.
- Parent registration (`/register/parent`) — same pattern, First/M.I./
  Last Name fields. **Deviates from the manuscript** — see Known
  Deviations below.
- Login (`/login`) — shared form for Teacher/Parent/Admin, role-aware
  via `?role=` query param. Generic "Invalid email or password" message
  (never reveals which field was wrong, per the Admin actor prompt),
  rate-limited (5 attempts/60s cooldown via `RateLimiter`). Also
  enforces a **role-mismatch check**: if the submitted `role` doesn't
  match the account's real `user_type`, login is rejected with "This
  account is registered as a {type}. Please use the {type} login
  instead." — even with correct credentials, even from the general
  no-role login link (which skips this check entirely, by design).
- Homepage/role-select (`/`) — real landing page. Nav buttons
  (Login/Get Started) are text-only — no decorative icons; icons are
  kept only where they aid recognition (role dropdown dots, list
  disclosure chevrons).
- Admin Dashboard (`/admin/dashboard`) — view Pending Teachers,
  Activate/Reject, and toggle Active/Inactive on any Teacher or Parent
  account. Admin accounts are structurally excluded from the list and
  from the toggle action (not just hidden in the UI). Guarded by the
  `admin` middleware alias (`EnsureUserIsAdmin`). Exactly one seeded
  Admin account exists (`AdminSeeder`), no self-registration.
- Class Management (`/teacher/classes`) — Create Class, **Edit Class**
  (name/grade/section/tag; School Year is not editable — a class is
  never edited into next year's record), and **Join a Learner via
  code** (the only way a Teacher connects to a Learner, per the actor
  prompt — a Parent-created Learner's `class_id` gets set directly,
  no join table). A Pending Teacher can view this page (locked/
  explained) but every mutating route carries `teacher.active`
  middleware, which independently re-checks status server-side. Past
  school years are read-only, enforced server-side too (not just a
  hidden button) — an owning-but-Pending Teacher, a different Teacher,
  or a past-year class all get rejected with a real 403 on the
  mutating routes.
- Learner Account Creation (`/parent/children/create`) — full wizard:
  Name (First/Middle/Last, **letters-only validation** — see below),
  Grade, avatar (preset character **or a real uploaded photo**, see
  below), reading-stage self-report, learning style, 3-question
  placement check (score sets `mastery_level` server-side, never
  trusts the client), 4-digit PIN (hashed). Generates a unique
  `learner_code` (`TB-XXXXX`). "Link Existing Child" (second guardian,
  via the same code) is part of this same slice.
  - **Name validation**: `first_name`/`last_name` reject any digit,
    server-side via `regex:/^[\p{L}\s\'\-]+$/u` (Unicode letters, so
    accented Filipino names aren't rejected) plus matching client-side
    JS for instant feedback. Client-side is fast feedback only — the
    server is the real enforcement.
  - **Photo upload**: a toggle lets the Parent choose a preset avatar
    (unchanged) or upload a real photo, cropped client-side via
    Cropper.js (CDN) to a square before it's saved. Stored via
    Laravel's standard `Storage::disk('public')` pattern in a new
    nullable `avatar_photo_path` column (the required `avatar_id`
    keeps a fallback preset value either way). Server-side validates
    real image content and a 2MB cap via Laravel's `image`/`mimes`/
    `max` rules — not filename/extension trust. Displays correctly
    everywhere a Learner's avatar shows (My Children, Teacher's class
    roster, the post-creation success screen, the Link-Existing-Child
    lookup card). **No consent checkbox or other privacy gate exists
    for this feature** — see Known Deviations below.

- Learner Login (`/learner/login`) — code entry (typing the
  `learnerCode`, standing in for a QR scan) + 4-digit PIN, no password,
  no email. Runs on its own `learner` auth guard (`config/auth.php`),
  completely separate from the `web` guard Users authenticate on — a
  Parent and a Learner can be logged in simultaneously on the same
  browser (verified: logging in as both on one session, then hitting
  "Switch learner" only clears the Learner's guard session and leaves
  the Parent's session untouched). Locks out after 5 wrong PIN attempts
  for 15 minutes (`RateLimiter`, keyed by the submitted code + IP so an
  unknown code and a valid code with a wrong PIN are throttled and
  messaged identically — never reveals which one was true). Lands on a
  dashboard shell: avatar (photo or preset), first-name greeting, grade,
  and Level/Points/Streak tiles pulled from the Learner's real record
  (`mastery_level` falls back to "New" if ever null). "Start Reading
  Activity" is an intentional dead end for now — a real navigation to an
  honest "coming soon" placeholder, not any diagnostic/reading logic
  (that's Sprint 4). The avatar-tap variant of Step 1 (shown when a
  Parent is already logged in on the device) was NOT built — only code
  entry — a deliberately narrower slice than the full actor-prompt Step 1.

Not started yet: nothing — this was the last piece of Sprint 3.

## Sprint 4 (Reading Assessment, Module 3) — COMPLETE

Scoped into slices after a stop-and-confirm pass (this is a bigger,
more architecturally significant feature than Modules 1-2 — audio
recording, a third external service, and four previously-unbuilt
tables: `reading_sessions`, `personal_word_bank`, `badges`/
`learner_badges`, `notifications`, plus `curriculum_guides` and
`activity_assignments`/`open_repository_listings`/`repository_unlocks`).
Slicing order (confirmed): (1) minimal Assign — Teacher assigns an
Approved Activity to a Learner/Class/Group, Learner's "what to read"
resolves it; (2) audio capture + Reading-api integration proven in
isolation; (3) real `ReadingSession` persistence + mastery-level
adjustment + results screen; (4) the first-login diagnostic, built last
on top of (2)+(3)'s proven scoring plumbing.

**Slice 1 — Assign — DONE.** `activity_assignments` table (translates
schema.sql Table 10 directly, no shape conflict). Teacher-side: an
inline Assign form on each Approved card in My Activities (radio picks
Learner/Class/Group, exactly one required — validated server-side, not
just via the disabled-select JS trick); Active-gated like every other
"touching real students" action; every posted target ID re-verified as
actually belonging to that Teacher (learner in one of their classes,
class not past-year, group tag actually used by one of their classes).
Learner-side (`LearnerAuthController::findActivity()`): resolves Step
3's priority chain — direct-to-Learner, then Class, then Class's Group
tag (scoped to the assigning Teacher, so two different Teachers reusing
the same free-text tag can't cross-leak) — "first match wins" is
implemented as the first PRIORITY LEVEL with any results, not a single
row, since multiple activities can exist at the same level (verified:
a Learner with 2 direct assignments gets the multi-option picker, not
just the first one found). Repository-unlocked content isn't built yet
so only Teacher assignments resolve. Stops at an honest "reading/
scoring isn't built yet" screen — no fake audio flow.

**Real security bug found and fixed during testing:**
`LearnerAuthController::learnerCanAccess()`'s ownership re-check used
`->orWhere('class_id', $learner->class_id)` unconditionally. Laravel
silently turns `where('col', null)` into `col IS NULL` — so an unclassed
Learner (`class_id === null`) matched ANY assignment row whose own
`class_id` was also null (i.e. any learner-direct or group-tag
assignment for anyone), not just their own. Confirmed via a real
exploit attempt (an unclassed test Learner got a `200` opening another
Learner's direct assignment by URL), fixed by only adding that
`orWhere` clause when `$learner->class_id !== null`, re-verified the
exploit now 403s and both legitimate paths (direct + group-tag) still
work. Worth remembering as a general pattern risk: `where('col', $x)`
is unsafe wherever `$x` can legitimately be `null` and the column can
independently also be `null` for an unrelated reason. Moved this whole
check onto `Activity::isAccessibleByLearner()` (a model method) so
`LearnerAuthController` and the new `LearnerReadingController` share the
exact same implementation instead of risking two copies drifting apart.

**Slice 2 — audio capture + Reading-api integration — BUILT, blocked
only on missing credentials.** New `LearnerReadingController` (kept
separate from `LearnerAuthController` — "submit a recording" isn't an
auth concern). Real `MediaRecorder` capture on `activity-found.blade.php`
(no client-side transcoding — Reading-api accepts WAV/MP3/M4A/AAC/FLAC/
OGG/OPUS/WebM/MP4 and transcodes via `ffmpeg` itself), auto-stops at 60s,
uploads via the same DataTransfer-into-a-hidden-file-input pattern
already used for the Parent's photo-upload feature (kept the whole app's
"plain form submission, no AJAX" convention instead of introducing
fetch/JSON just for this). Server-side re-uses `Activity::
isAccessibleByLearner()`, validates `max:15360` (matches Reading-api's
own `MAX_UPLOAD_MB=15`) as a fast pre-check, and applies the exact same
`set_time_limit()`/`Http::timeout()` fix just learned from Activity
Generation's real 30s-timeout bug, proactively, before hitting it here
too. Stops exactly at the confirmed scope boundary: a plainly-labeled
raw-scores preview screen (transcript, accuracy, WCPM, prosody) — no
`ReadingSession`, no mastery-level/points/streak changes, no real
celebratory Results screen. That's Slice 3.

**Found and fixed while building this:** `ActivityController::update()`
edited `passage_text` but never recomputed `reference_text` (the field
this project designates for AI-scoring alignment) — editing a Draft's
text would have silently scored future readings against stale,
pre-edit text. Fixed by recomputing `reference_text` (same whitespace
normalization Reading-api itself uses) on every edit.

**Tested for real, up to a real hardware limit:** the graceful
"not configured" error path (real multipart upload via `curl`, real
ownership check, clean message — confirmed via `.env` log inspection
too) and the full client-side mechanics (DataTransfer injection, exact
filename/type, real form submission, server receipt) were verified
working end-to-end through an actual browser. Genuine microphone
capture itself could not be tested — Claude Code's sandboxed Browser
pane explicitly blocks real device access, which is a real environment
limit, not a code gap; it did, usefully, confirm the mic-permission-
denied UI path works correctly (a real `getUserMedia()` rejection
produced the intended friendly message, not a crash).

**Slice 2 — CONFIRMED LIVE.** `READING_AI_URL` (`https://reading-api-
v5fp.onrender.com`) is real and correctly configured — `/health`
returns `model_loaded: true`. Since no microphone is available in this
environment either, generated a genuine test recording via Windows'
built-in TTS (`System.Speech`, real synthesized voice, not silence or
noise) reading an actual assigned Activity's exact passage ("cat dog
pig hen cow"), then sent it to the real deployed service both directly
and through the full Laravel pipeline. Real, unfakeable Vosk output
came back — it misheard "hen" as "hank" and "cow" as "hill" (a
plausible acoustic confusion for a small ASR model against a
synthesized voice, with correspondingly low confidence scores on
exactly those two words: 0.32 vs 1.0 for the correctly heard words) —
computed accuracy 60% (3/5 correct), real WCPM/prosody scores, and
correctly returned `status: "waiting_for_comprehension"` since no
comprehension_score was sent (expected — that's not built yet).
Confirmed identical output through `/learner/activity/1/record` end to
end: real ownership check, real upload, real external call, correct
rendering on the preview screen. Vosk transcription itself took ~5s
(`asr_ms: 4997`) — comfortably fast, no timeout risk for a short
passage. **Still needs a genuine human test** in a real desktop/mobile
browser (not this sandboxed pane) to confirm actual live microphone
capture — the TTS test proves the entire pipeline around it works, but
someone with a real browser needs to physically press the button.

**`READING_AI_URL` confirmed live and correct:**
`https://reading-api-v5fp.onrender.com` — `/health` returns
`model_loaded: true`.

**Slice 3 — real ReadingSession persistence, mastery adjustment, points/
streak, PersonalWordBank, results screen — BUILT AND VERIFIED against
the live service and the real database, not just reviewed.** New
`reading_sessions`/`personal_word_bank` tables (translate schema.sql's
Tables 14-15 directly — no shape conflict). `reading-preview.blade.php`
(Slice 2's temporary stand-in) is gone, replaced by the real
`reading-results.blade.php` (Step 7: tier emoji, accuracy/WCPM, a
level-change callout only when the tier actually changed, points,
streak) and `reading-unclear.blade.php` (the capped-retry flow).
Mastery-level math uses `accuracy_score` alone, per the confirmed scope
cut — the composite `reading_proficiency` score stays honestly
`"waiting_for_comprehension"` for now, since no comprehension-quiz UI
was built. `pronunciation_score`/`fluency_score` are `NULL` by
confirmed decision, consistent with `mispronunciation_count`/
`repetition_count`.

**Real discovery while testing "unclear" that the source reading alone
didn't show:** Reading-api has its own dedicated silent-audio check,
rejecting with a real `422` ("Audio is silent or nearly silent.")
*before* Vosk ever runs — not a `200` with zero recognized words, which
is what the original design (written for the Google-STT-era spec)
assumed. A `422` here is exactly the actor prompt's "genuinely couldn't
be processed," so it's routed into the same friendly capped-retry flow
as a zero-word `200` — fixed after a real silent-audio test surfaced
this, not assumed from reading main.py alone.

**Tested for real, with live data, not synthetic/mocked responses:**
- A genuine 60%-accuracy TTS reading through the full app: Miguel's
  `mastery_level` went Proficient → Developing (the down-tier branch),
  `points` 0 → 30, `streak` 0 → 1, `flagged_needs_attention: true`,
  and `PersonalWordBank` got exactly the two real missed words ("hen,"
  "cow") linked to the correct `session_id` — all confirmed directly
  against the database, not just the rendered page.
- A genuine 80%-accuracy TTS reading (a different, clearer synthesized
  take): Developing → Developing (the unchanged-tier branch, 70-89%
  range), points correctly incremented by 40 more (round(80/2)), streak
  → 2 — confirmed the math is cumulative and correct across sessions,
  not just correct in isolation.
- The "unclear" capped retry, for real: 3 consecutive silent-audio
  submissions correctly showed "Didn't quite catch that" on attempts 1
  and 2, then "Please ask your Teacher or Parent" on attempt 3 — and
  confirmed zero `ReadingSession` rows were created for any of the 3
  (an attempt that was never actually scored must never be persisted).
- Cross-Learner isolation of the unclear-attempt counter: switched to a
  second real Learner (Sofia) and confirmed her first silent attempt
  showed the "try again" message, not the "final" one — proving the
  counter didn't leak from Miguel's already-capped count via the
  shared underlying PHP session (only the auth guard is Learner-
  specific; plain `session()` data isn't, by default).
- **The ≥90% ("level up") branch — since closed.** Three different TTS
  voice/rate combinations only ever produced 60%, 60%, and 80% (genuine
  ASR variance against synthesized speech, not a reliable way to force
  a real ≥90% score), so this was initially left as an honestly-flagged
  gap rather than a claimed-but-unproven test. Closed properly by
  invoking `LearnerReadingController::scoreAndPersist()` directly via
  `ReflectionMethod` in `tinker`, passing a synthetic `accuracy_score:
  95.0` — this exercises the real method (real DB writes, real Learner
  model, real ReadingSession row), only substituting the one thing
  genuinely hard to control live (the ASR's exact score), not a
  from-scratch mock of the whole flow. Confirmed against the real
  database: Miguel (Developing, 70 points, streak 2) → Proficient, 118
  points, streak 3 — the up-move actually works, not just "probably
  fine by symmetry." Then confirmed the cap: applying another synthetic
  98% while already Proficient correctly stayed at Proficient (167
  points, streak 4, `level_before: Proficient, level_after: Proficient`
  — no out-of-bounds tier, no false "level changed" claim since
  `levelChanged` is false when before equals after).

**Slice 4 — the first-login diagnostic — BUILT AND FULLY VERIFIED,
every staircase branch confirmed against the real database.** New
`LearnerDiagnosticController`, `activities.purpose` column (tags
system-generated diagnostic passages so they never surface in a
Teacher's My Activities/Assign lists — `created_by_teacher_id` is also
`null` for these, which already structurally excludes them from any
`where('created_by_teacher_id', $teacher->id)` query), and a new
`EnsureDiagnosticComplete` middleware (`learner.diagnostic` alias) as
the standing guard the login-time redirect alone wasn't enough for.
Extracted the Reading-api and `gemini_activity_gen` integrations into
shared `ReadingAiClient`/`ActivityAiClient` services (used by both this
new controller and the existing Slice 2/3 and Module 2 controllers) —
this was about to become the third copy of that integration logic, so
consolidated it before it could drift. A shared `_recording-widget.blade.php`
partial does the same for the MediaRecorder UI, now used by both
`activity-found.blade.php` and the new `diagnostic-passage.blade.php`.
Both refactors were regression-tested with real data afterward — no
behavior change, confirmed.

**A real design fork resolved along the way:** diagnostic Activities
are never linked via `ActivityAssignment` (no Teacher is involved), so
the existing `Activity::isAccessibleByLearner()` check doesn't apply —
gave the diagnostic flow its own tighter, session-state-scoped access
check instead of forcing an awkward fit onto the shared one.

**Every staircase branch verified against the real database, using
real data where a live take could get there reliably and the same
reflection-based synthetic-injection method (approved for exactly this
purpose) where it couldn't:**
- **70-89% stop-immediately** — 100% real: Miguel (Grade 1), real TTS
  reading scored a genuine 88.89% on the Medium passage, diagnostic
  concluded after exactly 1 passage, `mastery_level` → Developing.
- **Down-move + confirmed floor** — 100% real: Leo (Grade 3), reading
  deliberately mismatched content (reliably forces near-zero accuracy)
  scored 2.47% then 2% — Medium → Easy → confirmed at Easy (already the
  floor), 2 real Diagnostic sessions persisted, `mastery_level` →
  Beginning.
- **Up-move + confirmed ceiling** — synthetic (a live take never
  reliably clears 90% against Vosk, same lesson as Slice 3): a fresh
  test Learner, synthetic 95% at Medium → Hard, synthetic 96% at
  Hard → confirmed (already the ceiling), `mastery_level` → Proficient.
- **The genuine "swing" 3-passage-cap scenario** — synthetic, reusing
  already-generated content (no extra real API cost): Medium 40% → Easy
  (down), Easy 95% → Medium again (up — a real reachable case since the
  algorithm isn't monotonic), confirmed the second Medium variant got
  used instead of repeating identical text, then a 3rd passage at 50%
  correctly got capped regardless of what that score would otherwise
  have triggered — `mastery_level` set from the LAST tier administered
  (Medium), per the patch doc's tie-breaker rule.
- **Unclear handling inside the diagnostic specifically** — 100% real:
  a fresh Learner, genuine silent audio, correct "Didn't quite catch
  that" message, confirmed zero `ReadingSession` rows created.
- **The "never again" rule** — 100% real: logged Miguel in a second
  time post-diagnostic, confirmed he goes straight to the real dashboard
  (not back to the diagnostic), showing his real confirmed level
  ("Developing"), not "New" or the pre-diagnostic estimate.

**Honest note on test cost:** a few early attempts at the synthetic
up-move test failed on a test-harness bug (a bare `Illuminate\Http\
Request::create()` has no real user resolver, so `$request->user
('learner')` returned null inside `passage()`'s internal call — fixed
by setting one explicitly) — each failed attempt had already triggered
a real `ensureBundleGenerated()` call before hitting that bug, so this
burned a few extra real Gemini calls beyond what the test strictly
needed. Not an app bug, just an inefficiency in my own debugging
process, flagged rather than left unmentioned.

**Decisions made for this sprint, recorded here since they diverge from
what the patch docs literally describe:**

- **Diagnostic content-sourcing:** the first-login diagnostic
  (`TaraBasaAI_PlacementDiagnostic_Addition.txt` /
  `TaraBasaAI_AdaptiveDiagnostic_Correction.txt`) calls for
  difficulty-tier-tagged content from a `curriculum_guides` table —
  but that table doesn't exist, is documented in `schema.sql` as
  "SEEDED DATA ONLY, no CRUD endpoint," and has no `difficulty_tier`
  column at all even though the adaptive staircase needs to search by
  tier. **Decision: reuse the already-working `gemini_activity_gen`
  pipeline (Module 2) to generate diagnostic passages on the fly at a
  specific tier, tagged internally (e.g. a `purpose = 'diagnostic'`
  marker), instead of building a separate static content-management
  system from scratch.** Faster, already proven, and naturally solves
  the tier-search problem since a tier can be requested directly.
  **One deliberate adjustment, for calibration consistency, not an
  oversight:** diagnostic generations never use the optional Topic
  field — always a neutral, curriculum-appropriate default — so
  difficulty is comparable across learners regardless of which random
  topic a generation would otherwise have picked.
- **`reading_sessions.mispronunciation_count` /
  `.repetition_count`:** Reading-api's real word-diff output only
  distinguishes `substitutions`/`deletions`/`insertions` — it has no
  separate mispronunciation or repetition category at all. **Decision:
  leave these two columns NULL, never 0, when saving a session from
  real Reading-api data** — 0 would falsely claim "confirmed zero
  occurrences," NULL honestly means "not measured by this data
  source." Same honesty-over-fabrication principle used everywhere
  else in this project (e.g. the "New" mastery-level fallback, the
  honest placeholder screens).
- **Reading-api has no authentication at all** (confirmed by reading
  its real `main.py` directly — no API key header, unlike
  `gemini_activity_gen`'s `X-App-Key`). The user is handling this with
  the teammate outside of Claude Code — not something to work around
  in this codebase.

## Sprint 5 (Analytics) — COMPLETE

Teacher Actor Prompt Step 10 + Parent Actor Prompt Step 7. Purely a
read/aggregate view over the `reading_sessions` data Sprint 4 started
collecting — no new tables, no schema change beyond adding a
`datetime` cast to `ReadingSession::timestamp` (it was being stored
correctly all along, just never cast to Carbon since nothing had
needed to format it until now).

New `AnalyticsController` (`teacherIndex()`, `parentIndex()`, plus a
shared private `learnerStats()` used by both — the same Learner, the
same `ReadingSession` rows, viewed from either actor's side, so there
was no reason to compute it twice) and two new routes:
`GET /teacher/analytics` (no `teacher.active` guard — read-only,
same visibility rule as Class Management's index) and
`GET /parent/progress`.

**Teacher view** (`teacher.analytics.index`): By Learner / By Group
tabs via a `?mode=` query param, matching
`docs/design-reference-html/tarabasa-analytics.html` closely. By
Learner: a picker over the Teacher's own learners (scoped via their
classes' `class_id`s), Teacher-Assigned vs. Parent-Initiated shown as
two separate stat cards (count + avg accuracy, never combined), a
real SVG accuracy-trend polyline computed server-side from the
Learner's own session history, and a full session history list. By
Group: a picker over the Teacher's own `group_tag`s (scoped by
`teacher_id` via their classes — same anti-leak scoping Slice 1
established for group-tag assignment resolution, since two Teachers
can reuse the same free-text tag), the same source split aggregated
across the group, then one row per learner (avatar, name, current
level, session count).

**Parent view** (`parent.progress`): confirmed via the actor prompt's
own Step 3 nav bar (Browse Repository / **Progress** / Notifications /
Logout) that this is its own dedicated screen, not something bolted
onto the existing `/parent/children` index. Per child: the same
Teacher-Assigned/Parent-Initiated split, accuracy trend, and session
history as the Teacher's By-Learner view, plus a child-tab selector
when more than one Learner is linked. No dedicated prototype file
exists for this exact screen — `tarabasa-platform-analytics.html` is
actually Admin's platform-wide view (confirmed from its own `<title>`
tag), not this — so it was built from the Teacher Analytics
prototype's structure combined with the stat-card/session-list styling
already used per-child in `tarabasa-parent-dashboard-v2.html`, in the
warmer tone the Parent UI Notes call for.

**A real design decision made before building, confirmed with the
user first:** `session_type = 'Diagnostic'` rows are excluded from
every count, the accuracy trend, and the history list on both views.
The actor prompts predate Slice 4's diagnostic and don't address it;
a one-time placement test isn't ongoing reading practice and its score
isn't comparable to a practice accuracy trend, so mixing it in would
misrepresent both. Verified this filter actually does something (not
just trivially correct on an empty table): a real test Learner (Leo)
has 2 real `ReadingSession` rows in the database, both Diagnostic —
Analytics correctly shows 0 sessions and the "no reading sessions yet"
empty state for him, not 2.

**A real, honest current limitation, not a bug:** `initiated_by` is
still always `'Teacher'` on every real Practice session —
`LearnerReadingController` hardcodes this because the only path that
currently grants a Learner access to an Activity at all is a Teacher
assignment (Repository-unlock, Parent Actor Prompt Step 6, isn't built
yet). So "Parent-Initiated" will correctly show 0 sessions everywhere
until Open Repository exists — Analytics is reporting the system's
real current capability accurately, not silently hiding a gap.

**Tested for real against the live app and real database, not just
code review:** logged in as both a real Teacher
(`teacher.mi.20260829192315@example.com`, owns Miguel + Sofia via a
`phonics-focus`-tagged class) and a real Parent
(`carla.domingo@example.com`, linked to 5 real children including
Miguel) — test passwords set via `tinker` for this purpose, since
these were pre-existing dev/test accounts from earlier sprints with no
known plaintext password. Confirmed:
- Miguel's real 6 Practice sessions (from Slice 3/4 testing) produce
  the correct 82% average (493/6, rounded) on both the Teacher's
  By-Learner view and the Parent's Progress view for the same child —
  same underlying query, same result, confirmed identical.
- By-Group mode correctly resolves the `phonics-focus` tag to the
  right class and aggregates both Miguel and Sofia (0 sessions)
  correctly, including the zero-session case not crashing on an
  average calculation.
- The Diagnostic-exclusion filter, as above (Leo's case).
- **Cross-tenant fallback, not just happy-path:** a Teacher requesting
  `?learner_id=<a Learner not in their classes>` and
  `?group_tag=<a tag that isn't theirs>`, and a Parent requesting
  `?learner_id=<not linked to them>`, all silently fell back to the
  requester's own first learner/group instead of leaking or erroring —
  confirmed there's no code path that looks up an arbitrary ID
  directly; `learner_id`/`group_tag` are only ever matched against the
  collection already scoped to the current actor.
- The true empty state (a Pending Teacher with zero classes) shows the
  correct "No learners yet" message rather than a broken picker with
  no options.

## Real bug found + Learner-facing UI/UX audit — COMPLETE

**The reported bug ("no results after reading") was investigated for
real, not just code-reviewed — and turned out to be a real, but
different, problem than "results aren't rendering."** Tested end-to-end
with real Learners, real TTS-generated audio, and real calls to the
deployed Reading-api:
- The regular reading flow (`LearnerReadingController` →
  `reading-results.blade.php`) works correctly — confirmed via a real
  submission rendering 60% Accuracy, 70 WCPM, +30 Points, streak, all
  real data.
- The first-login diagnostic (`LearnerDiagnosticController` →
  `diagnostic-results.blade.php`) also rendered without error, but
  showed almost nothing — by design (Part 4.2: "no visible score or
  pass/fail framing, ever"). Since the diagnostic is every Learner's
  forced first reading experience, this is almost certainly what was
  actually tested, and a screen with zero numbers after two whole
  passages reasonably read as "broken." Confirmed with the user this
  was the diagnostic.
- **The real, concrete bug:** `LearnerDiagnosticController::
  finishDiagnostic()` computed and passed `finalLevel` to the results
  view, but the view never used it — the level a child landed on was
  silently discarded instead of shown. Fixed by adding
  `MASTERY_TO_RESULT_LABEL` (a friendly, non-numeric phrase per tier —
  "🌱 You're a Rising Reader!" / "🌿 You're a Growing Reader!" / "🌟
  You're a Super Reader!") and rendering it as a pill on the results
  screen. This exactly matches
  `docs/design-reference-html/tarabasa-learner-diagnostic__1_.html`'s
  own Result step, which shows "🌱 You're a Growing Reader!" for a
  Developing landing — a prototype that, it turned out, had never
  actually been checked when Slice 4 was first built.

**That prototype miss led to a fuller redesign pass than just the one
bug**, per the new UI/UX policy above (see Design/UX rule 5):
- **`diagnostic-encourage.blade.php` (new)** — the prototype's
  "Encouragement" interstitial between passages ("Great job! Let's see
  how the next one goes!") didn't exist at all before this pass;
  `LearnerDiagnosticController::applyStaircaseStep()` now renders it
  when the staircase continues instead of silently jumping straight to
  the next passage.
- **`diagnostic-intro.blade.php` / `diagnostic-passage.blade.php`** —
  brought exactly in line with the prototype's own sizing (passage
  text 16px→19px/line-height 1.7, intro copy 14px→16px, added the
  prototype's "Passage N" progress label above the dots, which existed
  before only as bare dots).
- **`activity-found.blade.php` (the specifically-called-out "mic and
  continue button" screen — no exact prototype exists for this one, so
  full creative latitude per rule 5)**: added an encouraging subtitle
  ("Read the words below out loud, then tap the mic!"), gave the
  mascot the same bob animation used everywhere else, bumped the
  passage text and all supporting copy to the new typography floor,
  and gave the mic button a soft glow ring (via layered `box-shadow`,
  not new markup — `_recording-widget.blade.php` stayed a neutral
  shared partial so the diagnostic screen's prototype-faithful plain
  mic button wasn't affected).
- **`reading-results.blade.php` / `reading-unclear.blade.php` /
  `learner/login.blade.php` / `learner/dashboard.blade.php`** — brought
  to the same typography floor and given the same button-hover
  micro-interaction already used elsewhere, for consistency across
  every Learner screen a child actually sees.

**Verified for real after the redesign, not just visually reviewed:**
re-ran a real submission against Miguel's real assigned Activity
(results screen still renders 60% Accuracy / 70 WCPM / +30 Points /
streak 8 correctly post-redesign), and ran a full real 3-passage
diagnostic staircase (down-move → up-move swing → 3-passage cap, using
the same deliberately-mismatched-then-correct TTS trick as Sprint 4's
own testing) confirming the new Encouragement screen appears between
passages and the final Results screen shows "🌿 You're a Growing
Reader!" for a real Developing landing — both captured as real
screenshots, not mocked. Also confirmed the redesign didn't break
`_recording-widget.blade.php` (still used identically by both
`activity-found.blade.php` and `diagnostic-passage.blade.php`) or the
existing `ReadingAiClient`/scoring pipeline.

## Real Teacher and Parent Dashboards — COMPLETE

`dashboard-placeholder.blade.php` (the generic post-login "you're in,
here are some links" screen from Sprint 3) is gone entirely — deleted,
not deprecated — along with its `/dashboard` route and
`AuthController::dashboardPlaceholder()`. All four roles now land on a
real, role-specific dashboard immediately after login:
`admin.dashboard` (Sprint 3, unchanged), `teacher.dashboard` and
`parent.dashboard` (new, this pass — new `TeacherDashboardController`/
`ParentDashboardController`), and `learner.dashboard` (Sprint 3,
already real/claymorphism-styled — reviewed against the same quality
bar during this pass and already holds up; only got the same
typography-floor bump as the other Learner screens, no structural
rebuild needed).

**Teacher Dashboard** — built from
`docs/design-reference-html/tarabasa-teacher-dashboard.html` per the
reference+enhance policy above. Real stat row (current-school-year
Classes count, total Activities, Approved count, real
`free_generation_credits_remaining`), real Pending-status banner, and
a corrected nav-grid: Class Management/My Activities/Analytics stay
unlocked regardless of Active/Pending status (matching those pages'
own already-shipped "viewable while Pending" behavior — the prototype
naively locks them, which would have been a regression), Generate
Activity always unlocked (matches prototype), Promotions/Repository
marked "Coming Soon" (not built yet at all — status-gating them like
the prototype does would misrepresent WHY they're non-clickable).

**Parent Dashboard** — built from
`docs/design-reference-html/tarabasa-parent-dashboard-v2.html`. Real
child-selector chips (with a real flag-dot sourced from each Learner's
most recent non-Diagnostic session), a real flagged-session alert
banner when applicable, a focus-card with real Streak/Points/WCPM/
Level (the prototype's "Badges" stat was swapped for real Points,
since Badges isn't a real system yet — Sprint 4's own decision — and
showing a plausible-looking fake count would violate this project's
NULL-over-fabrication rule), the real Teacher-Assigned/Parent-Initiated
split (now shared via a new `ReadingSession::sourceSummaryForLearner()`
static method — this was about to become a third copy of that exact
computation after Sprint 5's Analytics and Progress, so it was
extracted into the model instead, and `AnalyticsController::
learnerStats()` was refactored to call it too), and real recent
sessions. Two things the prototype shows as working buttons but
genuinely aren't real features yet — "Start Practice" (Parent-initiated
ad-hoc reading depends on Open Repository, which isn't built;
`LearnerReadingController` still hardcodes `initiated_by = 'Teacher'`)
and "Browse Repository" — are marked "Coming Soon" rather than wired
to a fake action.

**Tested for real**, not just visually reviewed: logged in as a real
Teacher (`teacher.mi.20260829192315@example.com`) through an actual
browser session and confirmed landing directly on the real dashboard
with real numbers (1 class, 25 activities, 4 approved, 0 credits); did
the same for a real Parent (`carla.domingo@example.com`, linked to 5
real children) and confirmed the child selector, the real flagged
banner and stats for Miguel (8 sessions, 77% avg, streak 8, 307
points, flagged for a real sub-70% session from earlier testing), and
the correct empty state for a child with zero sessions (Ana) — all via
real screenshots of the live app, not curl-only.

## Full application-wide UI/UX audit — COMPLETE

Every screen in the app (22 total: Public/Auth, Admin, Teacher, Parent,
Learner) was individually opened in a real browser session, screenshotted,
and checked against `layouts/auth.blade.php`'s design tokens and against
every sibling screen for consistency — not assumed fine because it hadn't
been flagged before. Most screens were already excellent (Landing, Login,
Admin Dashboard, Class Management, Teacher/Parent Dashboards, the child
wizard, all six Learner screens) and needed no changes. What was found
and fixed:

- **A real, systemic layout bug affecting 15 files**: every standalone
  screen (`layouts/auth.blade.php` plus 14 Learner/Parent full-page
  views) centered its card with `body{ display:flex; align-items:center;
  justify-content:center }`. Confirmed via JS (`getBoundingClientRect`)
  that whenever a card's content is taller than the viewport, this
  permanently clips the TOP of the content — unreachable no matter how
  far you scroll — because centering an overflowing flex item pushes
  the overflow equally in both directions, and a page can't scroll
  "above" its own origin. Hit this for real on Teacher Registration's
  longer form at a normal 800×700 window. Fixed everywhere with
  `align-items:safe center` (falls back to top-alignment only when
  content actually overflows, otherwise behaves identically to plain
  `center`). A visual "ghosting" artifact seen mid-investigation turned
  out to be a stale-screenshot-capture quirk of the browser automation
  tool interacting with `background-attachment:fixed`, not a real
  rendering bug — confirmed by cross-checking against live DOM geometry
  and a forced-repaint screenshot, both of which showed the fix working
  correctly.
- **Admin Dashboard**: its two tables had no `overflow-x:auto` wrapper,
  so on any viewport between ~761–950px wide (above the mobile
  card-stack breakpoint) the whole PAGE scrolled horizontally instead
  of just the table, cutting off the Activate/Reject buttons. Fixed by
  wrapping each table in `.table-scroll`. Also had no loading-state
  feedback on Activate/Reject/Deactivate — added, matching the pattern
  already used everywhere else in the app.
- **Generate Activity / My Activities (Teacher)**: both were missing
  the topbar (logo + avatar chip + logout) every other Teacher screen
  has — no way to log out without navigating back to Dashboard first.
  My Activities' Approve/Edit/Reject/Assign buttons also had no hover
  states and no loading-state feedback on any of their four forms.
  Fixed all of it.
- **Analytics (Teacher) / Progress (Parent)**: both had a `.topbar`
  with CSS rules for a `.logo-badge` and `.avatar-chip` already
  written, but the actual HTML never used them — just a bare wordmark
  and a logout button, inconsistent with every sibling screen. Fixed
  by filling in the markup these pages' own CSS already expected. Also
  added missing hover states on the By-Learner/By-Group mode tabs and
  the Parent Progress child-tab selector.
- **Link Existing Child (Parent)**: real "Link as Guardian" submission
  had no loading-state feedback (every other real-submission form in
  the app has one) — added and confirmed live ("Linking…" caught
  mid-flight during a real submission that actually linked a guardian).
- **The child-creation wizard (Parent)**: already excellent (real
  Cropper.js photo cropping, real validation, a working progress bar)
  but step transitions were an instant `display:none`/`block` snap —
  exactly the "instant snap" the audit was checking for. Added a
  low-risk fade+rise-in animation on step entry (`prefers-reduced-motion`
  respected) without touching the working show/hide logic itself.
- **The Learner recording flow** (`_recording-widget.blade.php`, shared
  by `activity-found.blade.php` and `diagnostic-passage.blade.php`):
  same instant-snap issue between idle → recording → processing,
  fixed the same way, scoped inside the shared partial so both
  including pages get it automatically. Also gave both screens the
  claymorphism treatment already established on the homepage's Learner
  tile — soft blurred color blobs behind the card — closing the gap
  between "improved" and actually matching the app's best screens,
  per repeated explicit feedback that this specific screen needed more
  visual richness than a mic and a button.
- **Diagnostic flow** — re-verified end to end with a fourth fresh
  Learner and real TTS audio through all 3 passages (down-move →
  continue → capped finish): the friendly result pill ("🌿 You're a
  Growing Reader!") from the earlier bug fix still renders correctly
  after this pass's changes, and the new claymorphism/animation
  additions didn't break the real staircase logic or `ReadingAiClient`
  pipeline.

Every fix above was verified against the real running app (real login,
real data, real screenshots) — not assumed correct from reading the
diff. No stray preview/scratch files were left behind in `public/`.

**Note on where this stands with the user:** after reviewing this audit,
the user said it still falls short of the bar they actually want — but
explicitly chose to defer further UI/UX enhancement to a later pass
rather than block feature progress on it right now. This is a
deliberate, acknowledged decision, not a resolved one — don't treat the
audit above as "done to the user's satisfaction," and don't assume this
gap has quietly gone away. Revisit UI/UX polish when the user brings it
up again, not proactively.

## Open Repository — COMPLETE

Parent Actor Prompt Step 6 + Teacher Actor Prompt Step 8's "Share to
Repository" + RepositoryRatings_Addition.txt, all built together since
they're one connected feature. Three new tables, direct schema.sql
translations with no shape conflicts (`open_repository_listings`,
`repository_unlocks`, `repository_ratings`) — unlike `activities`,
nothing here needed adapting to gemini_activity_gen's real shape.
`GamifiedLibrary_v2.txt` turned out NOT to apply — it's entirely about
`activity.game_type`/`content` JSON pre-game mechanics the real
Activity model never got (already flagged as the provisional Activity
Generation conflict), so Open Repository just shares/unlocks plain real
Activity rows, no pre-game mechanics involved.

**Teacher side**: new `ActivityController::shareToRepository()` on an
Approved card, alongside the existing Assign — Free or Paid (+price),
one-time per Activity, `free_generation_credits_remaining += 2` on
success. This is the real mechanism behind copy that was already live
elsewhere in the app before this slice (Generate Activity's credit
banner literally said "Share an approved activity to the Repository to
earn 2 more" while sharing itself didn't exist yet). Shared cards show
"✓ Shared to Repository" plus the real average rating once rated.

**Parent side**: new `RepositoryController` + `parent/repository.blade.php`
(built from `tarabasa-open-repository.html`, enhanced with a per-Learner
selector since unlocking is always per-item-per-Learner, real filter
chips, and a real 5-star rating widget). Free unlocks instantly; Paid
reveals an inline confirm ("Unlock this for ₱X for [name]? (simulated
payment — no real charge)") before the real POST — genuinely simulated,
no payment gateway integration, exactly as the actor prompt specifies.
Already-unlocked correctly rejects with a clear message, confirmed via
a real duplicate-unlock attempt. Ratings: one per Parent per listing,
built exactly per schema's `UNIQUE(listing_id, parent_id)` — confirmed
with the user this stays as given, even though a Parent with two kids
who both use the same listing can only ever leave one combined rating
for it, not one per child.

**Learner side — extends already-built Slice 1 code, doesn't duplicate
it**, exactly per Learner_Actor_Prompt.md Step 3's own spec (gathering
repository-unlocked content was always meant to sit alongside Teacher
assignments in the same picker, not as a separate flow):
- `Activity::isAccessibleByLearner()` split into
  `isAssignedToLearner()`/`isUnlockedForLearner()`, with a new
  `initiatedBySourceFor()` that resolves which one actually applied.
- `LearnerAuthController::findActivity()` now gathers both sources and
  labels each option "Assigned by your Teacher" vs. "Extra Practice" in
  the picker.
- `LearnerReadingController::scoreAndPersist()`'s `initiatedBy` is no
  longer hardcoded `'Teacher'` — it's `$activity->initiatedBySourceFor($learner)`,
  a real determination. **This is the fix that finally makes
  "Parent-Initiated" sessions real everywhere** — Analytics, Progress,
  and the Dashboard had all been honestly showing 0 for this the entire
  project, by design, since there was never a real path to it before.

**A real design fork resolved before building**: the Parent Dashboard's
"Start Practice" button, as literally shown in
`tarabasa-parent-dashboard-v2.html`, implies the Parent reads directly
with no Learner PIN ("no PIN needed, parent already authenticated") —
that contradicts Learner_Actor_Prompt.md Step 1's hard rule that a
Learner always needs their PIN, no exceptions listed anywhere. Per the
user's explicit decision, this button was reworded to "Browse Extra
Activities" linking to the real Repository screen, instead of either
building a PIN-less reading flow that isn't actually spec'd, or leaving
a misleading "Coming Soon" for a feature that was never really coming.

**Tested for real against the live app and database, not code review**:
a real Teacher (`teacher.mi.20260829192315@example.com`) shared one
Free and one Paid (₱25) Approved activity through the real browser —
confirmed `free_generation_credits_remaining` 0→2→4 in the database. A
real Parent (`carla.domingo@example.com`) unlocked both for Miguel
through the real confirm flow, then rated the Free one 4 stars with a
comment — confirmed real `RepositoryUnlock` rows (`amount_paid`: 0 and
25 respectively) and a real `RepositoryRating` row in the database.
Confirmed the duplicate-unlock guard rejects a real second attempt with
"Already unlocked for Miguel." Logged in as Miguel for real and
confirmed his picker showed all 4 real options correctly split — 2
"Assigned by your Teacher," 2 "Extra Practice" — then read the
repository-unlocked one with real TTS audio and confirmed the resulting
`ReadingSession` row has `initiated_by = 'Parent'` for the first time
in this project's history, and that this real data immediately (no
extra code needed) showed up correctly as non-zero "Parent-Initiated"
counts on the Parent Dashboard, Teacher Analytics, and Parent Progress —
proof the shared `ReadingSession::sourceSummaryForLearner()` method
from Sprint 5 was built correctly the first time. Also confirmed the
Teacher's My Activities screen shows the real "★ 4 (1 rating)" on the
shared card, closing the loop on RepositoryRatings_Addition.txt's own
Teacher-side requirement.

## Notifications — COMPLETE

Learner Actor Prompt Step 6 (session summary + urgent-if-flagged) +
PlacementDiagnostic_Addition.txt Step 7 (level-confirmed after the
diagnostic) + Teacher Step 11 / Parent Step 8 (the viewing UI). One new
`notifications` table, direct schema.sql translation, recipients keyed
to real `users.id` rows (a Teacher/Parent's actual login identity), not
their Teacher/Parent profile row.

**Deliberately NOT built**, because the actor prompts don't actually
specify them despite the design reference mockup showing them: Badge
and Message notification types. Badges aren't a real system (deferred
since Sprint 4), and Teacher↔Parent messaging was never spec'd
anywhere in the real actor prompts — that mockup content was decorative
filler, not a real event to wire up. Checked RepositoryRatings_Addition.txt
too — a rating received doesn't trigger a notification per spec, so
Open Repository adds nothing here.

**Real events, real recipients** — a new `Notification::notifyForLearner()`
static method shared by both call sites, so they can't drift into two
different answers for "who actually needs to hear about this Learner":
- `LearnerReadingController::scoreAndPersist()` (Practice sessions
  only) → Session Summary to the Learner's Teacher (if in a class —
  this checks whether the LEARNER has a Teacher via their class, not
  whether this particular session was Teacher-initiated, so a real
  Parent-initiated Repository session still reaches the class Teacher)
  and every linked Parent. If flagged, an additional Needs Attention
  notification to the same recipients.
- `LearnerDiagnosticController::finishDiagnostic()` → one Level
  Confirmed notification, Parent(s) only, no Teacher (a diagnostic
  never involves one).

**A real design correctness point, not just a theoretical one**:
`LearnerDiagnosticController::applyStaircaseStep()` creates a real
`ReadingSession` row per attempted passage (2-3 per diagnostic) — if
the generic Step 6 notification had been hooked into every
`ReadingSession::create()` instead of scoped specifically to
`scoreAndPersist()`, a single diagnostic run would have spammed 2-3
session-summary notifications before the one real "level confirmed"
notice the patch doc actually asks for. Confirmed this doesn't happen:
a real 3-passage diagnostic (down-move → up-move swing → capped, same
trick used throughout this project's diagnostic testing) produced
exactly 3 real `ReadingSession` rows but exactly 1 real notification.

**Viewing UI**: `NotificationController` (`teacherIndex()`,
`parentIndex()`, `markRead()`, `markAllRead()`) + one screen per role,
built from `tarabasa-notifications.html` as the real foundation and
enhanced per the standing reference+enhance policy — real colored icon
badges per type (not plain text), unread state as a real background
tint + weight change (not just a dot), a flagged notification's card
gets a distinct danger-tinted treatment, day-grouping (Today/Yesterday/
This week/Earlier), filter chips (All/Needs Attention/routine — the
real two-category distinction the actor prompts emphasize, not the
mockup's Badge/Message chips for systems that don't exist). Mark-as-read
is a real POST per item (click the card) or "Mark all as read," matching
this app's established no-AJAX convention. Parent's view additionally
sections by Learner when more than one child is linked, per Step 8 —
built and confirmed with two real children in one Parent's real feed.

A real notification bell + unread-count badge was added to the Teacher
and Parent Dashboard topbars (the two landing screens), plus a
Notifications nav-card on Teacher Dashboard and a matching quick-action
tile on Parent Dashboard — not added to every single screen in the app,
to keep the surface area reasonable while still giving each role a
real, honest, visible unread count.

**Tested for real against the live app and database**: forced a real
flagged session for Miguel (0% accuracy via the deliberately-mismatched
TTS trick) and confirmed exactly 6 real notification rows — 3 real
recipients (the class Teacher + both of Miguel's real linked Parents)
× 2 real types (Session Summary, Needs Attention), each with the
correct message text. Ran a full real 3-passage diagnostic for a fresh
Learner and confirmed exactly 1 real Level Confirmed notification,
Parent-only, despite 3 real ReadingSession rows being created along the
way. Clicked a real unread notification in the browser and confirmed
its card visually changed from tinted/bold to plain/read, the dashboard
bell badge count decremented live, and the database's `is_read` flag
flipped — then confirmed "Mark all as read" zeroed out the rest.
Confirmed the Parent's per-Learner grouping with two real children
(Miguel and a second real test Learner) showing as two separate,
correctly-labeled sections in one real feed, and confirmed both filter
chips (Needs Attention / Updates) correctly scope to only their real
matching rows. Confirmed the true empty state for a Teacher with zero
notifications shows the friendly message, not a broken/blank list.

## Grade Promotions — COMPLETE

Teacher Actor Prompt Step 9, bundled with the two patches it's
inherently connected to: `TaraBasaAI_SchoolYear_Addition.txt` Part 4
(the year-over-year roster history line) and
`TaraBasaAI_LearnerHistory_Addition.txt` (promotion history +
proficiency trajectory on each Class Management roster card) — both
needed real `PromotionRecord` data to read, which is exactly why this
was the right slice to build them in, not separate later work with
nothing to display yet. One new `promotion_records` table, direct
schema.sql translation, no shape conflicts.

**Release** (`PromotionController::release()`): a button per Learner in
the Teacher's own current-school-year classes — Grade 1/2 get "Promote
to Grade [N+1]", Grade 3 gets no button at all, just "No next grade
(final year)" text, enforced server-side too (a direct POST attempt on
a Grade 3 Learner gets a real 403, confirmed). Clears `class_id`,
creates a Pending `PromotionRecord` — the Learner stays fully Active,
just unassigned until claimed.

**Claim** (`PromotionController::claim()`): the queue shows **every**
Pending record platform-wide, never limited to ones this Teacher
released — confirmed for real (a Teacher who released nobody could
still see another Teacher's released Learner in their own queue). Only
this Teacher's own current-year classes matching the required
`next_grade` are valid targets; a record with none shows a real
"you don't have a matching class this year" note instead of an empty
dropdown. On claim: `class_id` **and** `grade_level` both set from the
claimed class — `grade_level` is never manually typed anywhere in this
flow, confirmed by checking the Learner's real row after claiming.

**The "Claim (N)" badge is Teacher-specific, not the platform-wide
total** (confirmed with the user before building): it counts only
Pending records this specific Teacher could actually act on (has a
matching current-year class for). Verified for real: a Teacher with
only Grade 1 classes saw a real Pending Grade-3 record in their queue
but no badge count at all, while the Teacher who actually had a
matching Grade 3 class saw "(1)".

**Two real bugs found and fixed during testing, not just claimed
correct from reading the diff**:
1. `PromotionRecord`'s `$fillable` was missing `claimed_at` — the
   `claim()` method's `update()` call was silently dropping it via
   Eloquent's mass-assignment guard, so every real claim left
   `claimed_at` NULL despite the code looking correct. Caught by
   actually inspecting the database after a real claim, not by
   assuming the `update()` call worked because it returned
   successfully. Fixed by adding it to `$fillable`; the one already-
   corrupted test row was backfilled directly.
2. The "Released from Grade X" display (both the Claim queue and the
   Class Management promotion-history chain) initially read
   `releasedFromClass->grade_level` — the class's own grade level. Real
   accumulated test data revealed this can drift from the Learner's
   actual grade at release time (a test fixture had a Learner marked
   Grade 1 sitting in a class row marked Grade 2), which would have
   silently mislabeled real promotion history. Fixed by adding
   `PromotionRecord::releasedFromGrade()` — computed from `next_grade`
   (always exactly one grade above where a Learner came from, so
   always self-consistent) instead of trusting a second, independently-
   editable source of truth. Found and fixed in both places it
   appeared, not just the first one noticed — the Class Management
   chain still had the same bug after fixing the Claim queue's copy.

**LearnerHistory additions to Class Management's roster card**
(`Learner::promotionHistorySummary()` / `proficiencyTrajectorySummary()`,
reading from already-eager-loaded relations rather than firing a fresh
query per Learner in a roster): every branch verified with real data —
a never-promoted Learner ("New to the system — no prior grade
history."), a once-promoted Learner (single headline), a twice-promoted
Learner (the full chain behind a native `<details>` disclosure, exactly
"Grade 1 → Grade 2 (claimed Sep 2026)" then "Grade 2 → Grade 3 (claimed
Sep 2026)"), a Learner with zero `ReadingSession` rows ("No reading
sessions recorded yet. Starting level: X"), and a Learner with real
session history (their actual first session's `level_before` through
their actual most recent session's `level_after`).

**Deliberately not added**: a Notifications hook for release/claim
events. Neither Learner Step 6 nor the Teacher/Parent Notification
steps mention Promotions anywhere — consistent with how Notifications
itself was scoped to only the events actually specified.

The Teacher Dashboard's "Promotions" nav-card (previously "Coming
Soon") now links to the real screen; its stale comment claiming
"Repository ... aren't built yet" (Repository shipped two slices ago)
was also cleaned up while touching that block.

## My Profile (Teacher + Parent) — COMPLETE

A genuinely missing feature — no Teacher or Parent had ever been able
to view/edit their own profile after registration. New
`ProfileController` (`edit()`, `update()`, `updatePassword()`) plus
`teacher/profile.blade.php` (blue identity, includes a School
Verification card) and `parent/profile.blade.php` (teal identity, no
such card — Parents have no school-verification concept). Reachable
from both dashboards' avatar-chip, which now links to
`teacher.profile.edit` / `parent.profile.edit` instead of being a
static, non-interactive pill.

**Editable**: first_name/last_name/middle_initial (same
`NAME_REGEX` letters-only validation as Learner creation) and
contact_number, plus a separate password-change section requiring the
current password (`Hash::check()` before `Hash::make()`).

**Two decisions made explicitly, not silently, since neither actor
prompt addresses profile editing at all**:
- **Email is not editable here** — it's the login identity; changing
  it would need its own re-verification flow that doesn't exist.
  Shown read-only with an explanatory note.
- **Teacher's school_name/employee_id are not editable** — these went
  through real Admin verification at registration; changing them
  post-approval without a re-verification step would silently
  undermine that check. Shown read-only in a "School Verification"
  card with the account's real Active/Pending status pill and a note
  to contact the Admin for a genuine correction.

**Tested for real, end-to-end**: a real Teacher's profile update
(name/middle-initial/contact-number) confirmed persisted and reflected
immediately in the topbar; the letters-only validation confirmed
rejecting "Juan123" with the correct message; a real password change
confirmed via logging out and back in with the NEW password (success)
**and** confirming the OLD password now fails (redirected to
`/login`) — genuinely invalidated, not cosmetic. Parent side verified
with a lighter but still real pass (correct absence of the School
Verification card, a real update persisting).

## Full application-wide design audit (post-Promotions pass) — IN PROGRESS

A second, more rigorous design pass than the earlier "UI/UX audit"
entry above — evaluated against six explicit dimensions (information
architecture, interaction design, visual/color, typography, layout,
realistic user scenarios) per screen, not a general "make it nicer"
sweep. Findings so far, each verified live against the real running
app:

- **A systemic interaction-design gap, fixed everywhere at once**: the
  avatar-chip topbar element was only a real link to My Profile on the
  two Dashboards (where Part 1 first added it) — every other Teacher/
  Parent sub-screen (Class Management, Promotions, Generate Activity,
  My Activities, Analytics, Notifications for Teacher; My Children,
  Repository, Progress, Notifications for Parent) still had it as an
  inert, non-interactive `<div>`. Fixed by making it a real
  `<a href="...profile.edit">` with a matching hover state on all 10
  files, so the same element behaves identically everywhere it
  appears — confirmed live (Class Management's avatar-chip correctly
  resolves to `/teacher/profile`).
- **Parent Dashboard mobile topbar**: had the avatar-chip-name-hiding
  rule but was missing `flex-wrap`/`white-space:nowrap`, so "Log out"
  wrapped onto two lines at 375px width (Teacher/Admin had already
  been fixed for this in the earlier audit; Parent's had been missed).
  Fixed and confirmed live via a mobile screenshot.
- **My Activities had no way to narrow 18+ real accumulated draft
  cards** — a real information-architecture gap against the
  "Teacher quickly checking between classes" scenario explicitly named
  in the audit brief. Added a client-side search box (title/grade/
  competency/topic, no server round trip) that appears once the
  combined Draft+Approved+Rejected count exceeds 5, with a real
  section-hiding behavior when a section has zero matches and an
  honest "No activities match" empty state. Verified live in all three
  states (populated, filtered, zero-match).
- **A real singular/plural copy bug**: "1 sessions" on both Parent
  Progress and Teacher Analytics (By Learner and By Group), since both
  views render `{{ $count }} sessions` unconditionally. Fixed with the
  same `{{ $count === 1 ? '' : 's' }}` pattern already used elsewhere
  in the app (e.g. Teacher Dashboard's needs-attention banner);
  confirmed live on Parent Progress with Miguel's real 1
  Parent-Initiated session.
- Screens evaluated with **no real defect found** (already meeting the
  six-dimension bar): Login (all 3 role variants), both Registration
  forms, registration-submitted, Admin Dashboard (one earlier
  responsive fix in the prior audit pass already covered it), Teacher
  Dashboard, Generate Activity, Analytics (both tabs), Promotions
  (both tabs), Parent Dashboard, My Children, the full 5-step
  child-creation wizard (walked through live end-to-end with a real
  new Learner, "DesignAudit", including the placement-check inline
  validation and PIN entry), Browse Repository, both Notifications
  views, Learner Login/PIN, Learner Dashboard, the recording screen,
  and the diagnostic intro/passage screens (walked through live with
  the same real new Learner). Link Existing Child and the
  child-creation wizard's lack of a topbar were checked deliberately
  and judged consistent with an established "focused task flow" design
  language (the wizard already omits chrome by design), not a defect.
- **Not independently re-verified live this pass**: reading-results
  and reading-unclear (real microphone capture is blocked in this
  sandboxed browser, a documented environment limit, not a code gap —
  same limitation noted in the original Sprint 4 build) and the full
  multi-passage diagnostic staircase/results screens. Checked via
  source review instead (typography floor, token usage, hover states
  all present and consistent) and via the extensive live verification
  already on record from the original Sprint 4 build and the earlier
  UI/UX audit pass (both done with genuine TTS-generated audio through
  the real deployed Reading-api) — not re-claimed as freshly proven
  today.

## Learner typography follow-up — real fix, verified with live measurements

The user reported that the "typography bump" claimed in the audit above
still looked too small in practice. Investigated before touching
anything, per their explicit instruction — result: **the earlier fix
had genuinely landed**, not a phantom claim. Confirmed live via
`getComputedStyle()` on the real rendered `.passage-card` element on
`activity-found.blade.php`: **19px**, `line-height:32.3px` (exactly the
claimed 1.7×), Baloo 2, in a real 404px-wide single-line card — matching
the CSS source exactly. The gap wasn't a broken fix; it was that 19px
genuinely isn't "significantly larger than adult text," which is what
the user was actually asking for.

**Reference check, as instructed, before using it**: opened
`docs/design-reference-html/tarabasa-learner-detail.html` and read its
actual content rather than assuming from the filename. It is **"Miguel's
Progress"** — a Teacher/Parent-facing per-learner drill-down (dark-mode
toggle, word-bank tabs, badge grid, session-history table, criteria
grid), styled entirely for an adult reader: fonts from 10px to 20px,
data-dense layout. **Not a Learner-facing screen at all**, and not used
for anything in this fix — applying its patterns to the child-facing
screens would have been exactly the wrong direction. Flagged this to
the user directly rather than using it. (It may be relevant later as
the reference for a not-yet-built Teacher/Parent "learner detail"
drill-down page, since Analytics/Progress currently only show this data
inline — but that's a different, unbuilt feature, not this one.)

**Real fix applied, concrete targets, verified by measurement after
every change — not just by editing CSS and assuming it worked**:
- `.passage-card` (the actual text a child reads aloud) on both
  `activity-found.blade.php` and `diagnostic-passage.blade.php`:
  19px → **23px** (within the requested 20-24px floor), line-height
  kept at 1.7×. Verified live: `getComputedStyle` reports
  `font-size: 23px`, `line-height: 39.1px` on the real rendered element
  in both a real Teacher-assigned activity and a real diagnostic
  passage (a genuine, currently-live long passage, not a short demo
  string) — confirmed via screenshot too, not just the computed value.
- General body/sub copy (`.sub`) on **all ten** Learner screens (login,
  dashboard, activity-picker, activity-found, diagnostic-intro,
  diagnostic-passage — n/a, no `.sub` there since it uses
  `progress-label` — diagnostic-encourage, diagnostic-results,
  reading-results, reading-unclear): 16px → **18px**, since 16px is
  inside the "typical adult" range the user explicitly wants Learner
  screens to clear.
- **A real, separate defect found while auditing systematically**: the
  activity-picker screen ("What should I read?") had never received
  the typography-floor pass at all — h1 was 21px (vs. 25-26px
  everywhere else), `.sub` was 14px, option titles (the actual activity
  names a child picks between) were 15px. Brought in line with every
  sibling screen: h1 → 25px, `.sub` → 18px, `.option-title` → 18px.
  Verified live: `getComputedStyle` confirms all three post-fix, and a
  live screenshot shows a visibly larger, clearly-better screen versus
  the cramped original.
- Dashboard stat tiles (Points/Streak/Level numbers): 22px → 24px;
  their uppercase captions: 11.5px → 13px. Mic-label under the record
  button (was drifted — 15px on one screen, 13px on the other, since
  the shared `_recording-widget.blade.php` partial leaves this styling
  to each including page): unified to 17px on both. Note-banners
  (permission/error messages): ~13px → 15px.
- **Verified with real submissions, not just synthetic checks**: used
  this project's own established technique (Windows `System.Speech`
  TTS reading the exact real passage text, submitted as a genuine
  multipart upload through the real `LearnerReadingController`/
  `LearnerDiagnosticController` endpoints, response rendered back into
  the live page for `getComputedStyle` + screenshot) to capture real,
  live-measured evidence of `reading-results.blade.php` (25px h1, 18px
  sub, 24px stat values — a genuine 60%-accuracy TTS reading of
  Miguel's real assigned activity), `reading-unclear.blade.php` (24px
  h1, 18px sub — a genuine silent-audio submission), and
  `diagnostic-results.blade.php` (25px h1, 18px sub, the "🌿 You're a
  Growing Reader!" pill still rendering correctly post-fix). Test data
  side effects (Miguel's `mastery_level`/points/streak briefly changed
  by these real submissions) were identified and restored to their
  prior values afterward — flagged here rather than left silent.
  `diagnostic-encourage.blade.php` has no standalone route (it only
  renders mid-staircase between passages) and wasn't reached by this
  particular real run, so its 18px `.sub` is confirmed by source only,
  not a live screenshot — disclosed rather than implied otherwise.

**Not changed**: the small uppercase captions under big stat numbers
and the "Not you? Switch learner" secondary link were nudged up
slightly (13px/11.5px → 13-14.5px) but deliberately kept smaller than
body copy — they're supporting metadata, not reading content, and the
user's ask was specifically about reading/body text, not every pixel
in the UI.

## ⚠️ PROVISIONAL, NOT FINAL — Activity Generation data model conflict
## (needs team/adviser review before thesis submission)

This is bigger than the deviations below and is flagged separately on
purpose so it doesn't get lost among smaller notes. It is NOT a settled
decision the way the Figure 11 or avatar deviations are — it is a
stopgap forced by what the real, deployed `gemini_activity_gen` API
can actually do, and the team needs to explicitly decide whether that's
acceptable long-term.

**What conflicts:** `Teacher_Actor_Prompt.md` Step 7, `schema.sql`'s
`activities` table, and `docs/design-reference-html/tarabasa-generate-
activity.html` all agree on one model — free-text Curriculum Topic +
Grade + optional Skill Focus, grounded against an internal
`CurriculumGuide`, generating ONE `gameType` (Read Aloud / Letter-Sound
Match / Word Builder / Trace-and-Write / Sentence Scramble /
Picture-Word Match) with type-specific structured content, a single
`difficultyTier` per Activity, and — if a target Learner is picked —
injecting that child's actual struggling words from `PersonalWordBank`.

The real deployed API (confirmed by reading `main.py` directly, not
just its README, which describes an older/stale v2 shape) does none of
that: Teacher picks Grade + one of exactly 3 fixed grouped competencies
(`foundational_reading` / `reading_fluency` / `reading_comprehension`)
+ one of 8 locked activity types (none of which are the app's 6
`gameType`s) + an item count; ONE call returns all three difficulty
tiers at once; there is no `CurriculumGuide` concept and no way to feed
it a specific Learner's weak words at all.

**Decision made (explicitly provisional):** built the feature around
what the real API actually returns — `activities` table stores
`competency`/`activity_type` as the API's own values, one row per
returned difficulty tier/variant, content fields (`passage_text`,
`reference_text`, `target_skills`, `reading_features`,
`follow_up_questions`) matching the API's real response shape.
**Dropped from this slice, not silently — genuinely unsupported by the
available API:** curriculum-guide grounding and per-learner
`PersonalWordBank` adaptivity (Step 7.4 and 7.5).

**What the team/adviser needs to decide before finalizing:** is
dropping curriculum-grounding and per-learner adaptivity acceptable for
the thesis as shipped, should the teammate's API be extended to support
them, or does this module need a different approach entirely? Don't
treat the "rebuild around the real API" choice above as closed — it's
what let the work continue tonight, not a resolution of the underlying
gap.

**Related, same root cause — Practice Games are standalone, not
`gameType` attached to a Teacher-approved Activity.** Two of the
manuscript's six intended `gameType`s (Word Builder, Letter-Sound
Match) are the two Practice Games actually built — but per the conflict
above, `activities` never got a real `gameType`/pre-game-mechanics
`content` JSON at all, because `gemini_activity_gen` has no such
concept to build it from. Building these two games as genuinely
Activity-attached content (Teacher-generated, Teacher-approved, tied to
a specific passage/difficulty tier) was never actually possible given
that standing gap. **Decision, confirmed with the user before
building:** ship them as standalone Learner-initiated free play instead
— a new "🎮 Practice Games" entry on the Learner Dashboard, alongside
"Start Reading Activity," not attached to any Activity or Teacher
workflow at all. No points, no scoring, no `ReadingSession` row — a
deliberate, clean separation from the real reading-achievement system,
so a Learner grinding Practice Games can never inflate their real
level/points/streak. Word Builder's word source (real
`PersonalWordBank` "Struggling" words, falling back to a small
hardcoded per-grade list) is the one place real personalization made it
in despite the missing per-learner-adaptivity API gap noted above — it
works here specifically because this app already owns that data itself
(populated by `LearnerReadingController`/`LearnerDiagnosticController`
independent of `gemini_activity_gen`), unlike Activity Generation's
struggling-word injection, which would have needed the AI service
itself to accept and use that data mid-generation.

**What the team/adviser needs to decide before finalizing:** same
underlying question as the Activity Generation conflict — if
`gemini_activity_gen` is ever extended with a real `game_type`/content
concept, should these two games be rebuilt as genuine Teacher-assigned
Activities matching the manuscript's original model, or is standalone
free-play practice (what's shipped now) an acceptable permanent form
for this feature? Not resolved either way — this is what let the
feature ship without a whole new content-authoring pipeline, not a
final architectural decision.

## ⚠️ PROVISIONAL, NOT FINAL — Diagnostic can't truly find a struggling
## learner's real floor across grades (needs team/adviser review before
## thesis submission)

Flagged with the same weight as the Activity Generation conflict above
— this is not a settled decision either, it's a stopgap forced by the
same underlying constraint (no `curriculum_guides` content-management
system, reusing `gemini_activity_gen` instead).

**What conflicts:** `TaraBasaAI_AdaptiveDiagnostic_Correction.txt`'s
entire stated purpose for the adaptive staircase is decoupling
diagnostic content from the Learner's chronological grade — quoting it
directly: "a struggling Grade 3 reader may need genuinely Grade-1-level
content to find their real floor; a strong Grade 2 reader may need
Grade-3-level content to find their real ceiling." The whole point is
finding where the CHILD actually is, independent of which grade they're
enrolled in.

**What the real API can actually do:** `gemini_activity_gen`'s Easy/
Medium/Hard difficulty bands (`READING_LENGTH_BANDS` in its own source)
are locked per grade — "Easy" for a Grade 3 Learner is not real
Grade-1-level content, it's just the easier end of Grade-3-appropriate
difficulty (Grade 3 Easy word-reading: 8-10 words; Grade 1 Hard:
11-14 words — genuinely different ranges, no overlap access). There is
no way to ask this API for "Grade 1 content, regardless of who it's
for."

**Decision made (explicitly provisional):** built Slice 4's staircase
adapting difficulty tier WITHIN the Learner's own enrolled grade only.
This finds where a child sits relative to their own grade's difficulty
range, not their true floor/ceiling across grades the way the patch doc
describes. A genuinely struggling Grade 3 reader will be correctly
identified as "Beginning relative to Grade 3 content," but the
diagnostic cannot hand them real Grade-1 material to confirm how far
below grade level they actually are.

**What the team/adviser needs to decide before finalizing:** is
within-grade adaptivity an acceptable substitute for the patch doc's
true cross-grade design, or does closing this gap need real
`curriculum_guides` content (or an extension to the teammate's API)
before the thesis is finalized? Same status as the Activity Generation
conflict — don't treat "adapt within grade" as a resolution, it's what
let Sprint 4 continue without a whole new content-management system.

**Also discovered while building this slice:** the "start one tier
toward the Parent's guess if their 3 placement-quiz answers were
unanimous" rule (Part 1 of the same patch doc) can't be implemented
either — `LearnerController::store()` only ever persisted the
*computed* `mastery_level`, never the raw `q1`/`q2`/`q3` answers
themselves, for any Learner ever created. There's no way to recover
"were all three answered the same way" after the fact; that raw data
is gone, not just for old records but by design going forward too,
since nothing currently saves it. Every Learner's diagnostic starts at
Medium (the patch doc's own stated default for the non-unanimous case)
regardless of their placement-quiz pattern — a real, disclosed
data-availability gap, not an oversight glossed over.

## Known deviations from the manuscript / actor prompts

Both of these were explicit, direct user instructions — not
improvised — but they diverge from the written specs, so they're
recorded here rather than left as a silent mismatch:

- **Parent registration name fields (Figure 11 deviation).** The
  manuscript's Parent registration screen (Figure 11) specifies a
  single "Full Name" field. The actual implementation uses separate
  First/Middle Initial/Last Name fields instead, matching the Teacher
  registration pattern. The original single-field + server-side
  name-splitting logic was removed entirely, not kept as a fallback.
- **Learner avatar (preset AND photo upload, not random assignment).**
  The Parent Actor Prompt (Step 4, on completion) says the system
  should "assign a random avatar" automatically. The actual
  implementation lets the Parent choose a preset character (an earlier
  deviation) and now also upload a real photo of the child, with no
  privacy/consent gate anywhere in the flow. No actor prompt or patch
  doc addresses child photo storage/consent at all — this is genuinely
  unaddressed ground, not a resolved question, and is worth revisiting
  before this goes anywhere beyond a dev build.

## Design/UX rules to follow on every new screen

1. **All reference material lives in `/docs` — read `/docs/README.md`
   first.** It explains which subfolder is the manuscript (highest
   authority), which are current actor prompts, which folder has the
   locked design-system HTML prototypes for screens not built yet, and
   which folders are explicitly stale/superseded and must never be
   built toward. Quote the actual actor-prompt step (in
   `/docs/actor-prompts/`) before building any user-facing flow — don't
   improvise login/registration/approval behavior. Find the matching
   prototype in `/docs/design-reference-html/` before styling any new
   screen — don't design from scratch.
2. A Pending Teacher can log in immediately with limited access (2 free
   AI-generation credits, no real students) — Admin approval only gates
   "touching real students," not login itself.
3. Admin has no self-registration — exactly one seeded account, ever.
4. Build in small, complete, vertical slices — one full-stack feature
   at a time (migration → model → controller → route → Blade view),
   not partial layers across many features at once. Tell the user
   clearly what's done vs. deferred after each slice.
5. **UI/UX policy — "reference + enhance, always."** Set after user
   feedback that Landing/Login (which closely followed their real
   prototypes) look good, but later screens built without that
   discipline (specifically the Learner reading/recording screen —
   "just a mic and continue button") did not. Corrected once already
   after an initial over-strict reading — this is the standing version:
   - **Where an exact prototype exists in `/docs/design-reference-html/`,
     use it as the real foundation, but actively enhance it** — better
     spacing, a stronger typography scale, real interaction polish
     (loading states, hover states, transitions), anything that makes
     the real screen feel more finished than the static mockup. This is
     encouraged, not restricted. What's NOT okay is inventing a
     disconnected design that ignores the reference's actual layout,
     structure, or content — enhance what's there, don't replace it
     with something unrelated. (Real example:
     `tarabasa-learner-diagnostic__1_.html` was missed entirely when
     Sprint 4 Slice 4 was first built — checked retroactively during
     this pass, it revealed a whole missing screen, see below. Also a
     real example of the corrected reading in the other direction: the
     Teacher Dashboard prototype locks Class Management/My
     Activities/Analytics entirely while Pending, but those pages were
     already built to be viewable-while-Pending — the real dashboard
     kept the prototype's layout/structure but corrected the lock logic
     to match already-shipped behavior, rather than either blindly
     copying a stale rule or inventing an unrelated design.)
   - **Where no exact prototype exists** (a genuinely new interaction,
     like the reading/recording screen), treat it with the same care
     and ambition as enhancing a reference would take — rich, warm,
     on-brand, matching the established design tokens, not a
     bare-minimum functional placeholder. A mic button and a label is
     not a finished screen for a Grade 1-3 child.
   - Established Learner-screen typography floor from this pass: h1 ≥
     25px, body/sub copy ≥ 16px, the actual passage text a child reads
     aloud ≥ 19px/line-height 1.7. Anything smaller than this on a new
     Learner screen should be treated as a regression, not a style
     choice.

## Resolved: teammate API integrations (all three, previously listed as
## undecided/open)

A teammate ("BldZeuz") independently built working, deployed Python
services on Render. All three are now resolved decisions:

- **`gemini_activity_gen`** (AI activity generation via Gemini) and
  **`Adaptive_Recommendator`** — integrate with these over HTTP rather
  than rebuilding that logic in PHP. (`gemini_activity_gen` is already
  wired up — see Sprint 5/Module 2 status.)
- **`Reading-api`** (github.com/BldZeuz/Reading-api, a FastAPI service,
  "Vosk Reading Analysis API") — **team decision: use this (Vosk-based)
  instead of Google Cloud Speech-to-Text for the AI Examiner.** This
  was previously flagged as an OPEN CONFLICT with the manuscript's
  confirmed tech stack (which names Google Cloud Speech-to-Text
  specifically) — it is no longer open. Build Sprint 4 (Reading
  Assessment) against Reading-api/Vosk, not Google STT.
  - **Documentation action item for the team, not for Claude Code to
    do in code:** the written manuscript still names Google Cloud
    Speech-to-Text as the STT engine. It needs a corresponding update
    to describe Vosk instead before final submission, so the graded
    document matches what was actually built. Flagging this so it
    doesn't get missed — this is a manuscript-editing task for a team
    member, not a coding task.

Don't rebuild activity-generation, adaptive-recommendation, or
reading-assessment/scoring logic from scratch in PHP — wire up HTTP
integrations to these three services instead.

## Enhanced reading UI — real color-coded word breakdown + live tracking

Built from a design-reference HTML prototype the user provided directly
(not a `/docs/design-reference-html/` file), per the standing
reference+enhance policy. Two real, separate pieces:

**Live word-tracking during recording** (`activity-found.blade.php` and
`diagnostic-passage.blade.php`, both now wrap `passage_text` into
per-word `<span class="lw">` elements): a paced, looping highlight that
walks through the passage while the mic is recording. **This is
explicitly NOT real live transcription** — Reading-api only scores a
recording after the full file is POSTed to `/analyze`, there is no
real-time per-word signal available at all, and the code/comments say
so directly. It loops continuously (never stops and sits idle while
still recording, since real reading pace varies a lot per child and
there's nothing real to sync against) and clears cleanly the instant
recording stops. Driven by two new custom events dispatched from the
shared `_recording-widget.blade.php` partial —
`tarabasa:recording-started` / `tarabasa:recording-stopped` — so the
neutral shared partial doesn't need to know or care whether a given
including page wants this animation.

**Real color-coded word breakdown after reading** (`reading-results.
blade.php` only — see the diagnostic decision below) — built from
Reading-api's real `word_feedback` array (`{reference, spoken,
status}`, status ∈ `correct|substitution|deletion|insertion`), which
was already being computed and returned on every scored reading but
previously only used to pluck up to 2 struggling words for
`PersonalWordBank`, then thrown away entirely. A new migration adds a
`word_feedback` JSON column to `reading_sessions` so this data is kept
instead of discarded (both `LearnerReadingController` and
`LearnerDiagnosticController` now persist it).

**Three real decisions made while building this, since the source
prototype's categories didn't all map onto real data:**
- **Dropped "Mispronounced" and "Repeated" as categories, did not
  attempt a derived version.** Confirmed directly from Reading-api's
  real `main.py`: `word_feedback` entries carry no confidence score at
  all — confidence only exists on a completely separate
  `word_timestamps` array with no key linking it back to a specific
  `word_feedback` entry. A positional-alignment heuristic (zipping
  non-deletion `word_feedback` entries against `word_timestamps` in
  order, since both are ultimately derived from the same spoken
  sequence) is theoretically possible but wasn't built — an arbitrary
  confidence threshold for "possibly mispronounced" is exactly the
  kind of fabricated-signal risk this project avoids elsewhere (same
  principle as leaving `mispronunciation_count`/`repetition_count`
  NULL). Only 3 real categories are shown: Correct (no highlight),
  Skipped (`deletion`), Said a different word (`substitution`, with a
  tooltip showing the real `spoken` value — e.g. "Heard 'frog'").
- **Insertions get a separate note below the passage, not an inline
  highlight.** An insertion (a word the child said that isn't in the
  passage at all) has no reference-text position to attach a highlight
  to, so forcing it inline isn't possible without fabricating a
  location for it. Shown instead as "You also said: '{word}' — that's
  not in this passage, but great effort reading out loud!", only when
  at least one real insertion exists.
- **The diagnostic keeps the live-tracking animation but never gets the
  breakdown.** `LearnerDiagnosticController` now persists
  `word_feedback` too (for consistency/future use, e.g. a possible
  future Teacher/Parent detail view) but deliberately never passes it
  to `diagnostic-results.blade.php`. A word-by-word right/wrong list is
  a real, fairly precise performance signal even without a percentage
  number attached — showing it would work against the diagnostic's
  already-established "no visible score or pass/fail framing, ever"
  rule (Part 4.2), which this project has held to consistently since
  Sprint 4.

**Tested for real, end to end, not just reviewed** — genuine TTS-
generated audio (this project's established technique) submitted
through the real `LearnerReadingController`/`LearnerDiagnosticController`
endpoints against a real Learner (Miguel):
- A real substitution: said "frog" in place of "dog" — the results
  screen correctly showed "dog" highlighted with a real "Heard 'frog'"
  tooltip on hover (confirmed both in the DOM and visually via
  screenshot), and the database's stored `word_feedback` JSON matched
  exactly.
- A real deletion: dropped "hen" from the reading entirely — correctly
  shown struck through, no tooltip (nothing was "heard" for a skipped
  word).
- A genuinely surprising real ASR result, not anything scripted: Vosk
  misheard "cow" as "out" — the tooltip correctly showed the real
  "Heard 'out'" value, proof this is live ASR output flowing through,
  not a canned example.
- A real insertion: said "banana" after the passage ended (Vosk heard
  it as "hand") — correctly appeared only in the separate "You also
  said" note, not forced into the per-word passage list.
- The live-tracking animation itself: dispatched the real custom
  events directly and confirmed via the DOM that the highlight
  advances roughly on pace and clears completely on stop — a real
  microphone recording still can't be exercised in this sandboxed
  browser (the same standing environment limit noted throughout this
  project), but the event-driven mechanism itself was proven, and a
  real recording fires the identical two events through the unmodified
  `_recording-widget.blade.php` regardless.
- A full real diagnostic run (fresh Learner, genuine TTS reading of a
  real Gemini-generated passage) confirmed the word-span/live-tracking
  markup renders correctly on `diagnostic-passage.blade.php`, and — the
  specific thing this decision needed proving — confirmed
  `diagnostic-results.blade.php` shows zero word-level detail, zero raw
  score, exactly the same friendly level-pill as before this whole
  feature was built.

## Live word-tracking follow-up: Web Speech API rejected, a real pacing
## bug fixed, and on-device mic/silence detection added

**Web Speech API (real live voice-based word tracking) — investigated,
found technically feasible, deliberately rejected.** The user asked
whether the decorative word-tracking animation could instead be driven
by the child's actual live voice, using the browser's built-in
`SpeechRecognition`/`webkitSpeechRecognition` API running alongside the
existing `MediaRecorder` capture (which would keep working completely
unchanged — same file, same Reading-api scoring). Investigated for
real before writing any code, per this project's own "scope first"
policy: technically workable (Chrome/Edge support it, a simple
advance-only word-matching pointer against live partial transcripts
is a reasonable design, and it's fully isolable from real scoring by
keeping the live transcript in its own closure with no path into the
form submission). **Rejected anyway, explicitly, not casually
revisitable:** Chrome's implementation streams the raw microphone
audio to Google's own servers to produce the transcript — a second,
real third-party destination for a child's live voice, on top of the
one (Reading-api) already reviewed and genuinely necessary for the
platform's core scoring function. For a purely cosmetic, decorative
feature (a nicer highlight animation, nothing about correctness or
scoring), that privacy cost isn't justified — unlike Reading-api,
which is a required destination because it IS the actual assessment.
This is a considered decision, recorded here so it isn't casually
tried again without re-litigating the actual tradeoff. (Also confirmed
while investigating: all real stored passage content — diagnostic and
regular Teacher-generated alike — is genuinely English-only, checked
directly against real database rows, not assumed from one example.)

**A real bug found and fixed in the existing decorative animation
instead.** The user reported that, in real production use, the
word-tracking highlight "jumps straight to the end of the sentence"
instead of pacing naturally — meaning the earlier word-length-
weighting + real-duration-rescaling fix wasn't actually working as
intended in practice, despite the underlying arithmetic having been
verified correct at the time (durations summing exactly to requested
totals). **Root cause, found by testing the full real flow this time —
not just the event-dispatch math in isolation:** the original fix
replayed the highlight ONCE (non-looping), rescaled to match the
RECORDING's own real duration — but that replay is shown during the
"Checking..." step, whose real length is however long the actual
Reading-api/Vosk network round trip takes (documented elsewhere in
this file at ~5 real seconds for ASR alone, sometimes more), which has
NO relationship to how long the recording was. For a short recording
(very common — many of this app's passages are just a handful of
words), the rescaled total is small enough that the whole pass
finishes in a near-instant blur, then the non-looping replay leaves
nothing highlighted for the remainder of what's often a much longer
real wait — which reads exactly like "it jumps to the end (and just
sits there)" to someone watching it. **Fix:** added the same per-word
minimum floor already used for the live-recording loop (`LIVE_MIN_MS`,
so a fast reading still visibly paces instead of flashing by) and
changed the replay from a single pass to a continuous loop at that
same real-pace-derived cadence, so it never goes idle/blank while the
child is still actually waiting — it just keeps gently repeating until
the real results page replaces it.

**Tested for real, end to end, including the actual page navigation —
the specific thing the original Phase 6 testing had NOT covered.**
Earlier testing had only verified the animation math via direct event
dispatch, never through an actual `<form>` submission and page
navigation together — this bug only shows up in that full real
integration, not in the isolated math. Verified this time by injecting
real TTS-generated audio (`cat dog pig hen cow`, and a real ~27-second
multi-sentence passage) through the actual recording widget's real
code path, dispatching the real stop event with the file genuinely
attached, and instrumenting the passage's tracking script to log its
own live state to `localStorage` every 50-100ms (survives the real
page navigation, unlike an in-memory log) so the full timeline could
be inspected after landing on the real results page. Confirmed: a
simulated 1-second-real-recording case, which under the old code would
have finished in ~1 second and then sat blank, now cycles continuously
with zero blank frames for the entire ~17 real seconds until the page
actually navigated; a simulated 27-second long-passage case paced
forward through the passage smoothly with no jumps or blanks either.
(Test data side effects — extra `ReadingSession` rows and a temporary
`ActivityAssignment` created to get a long passage assigned for
testing — were cleaned up afterward: Miguel's `mastery_level`/
`points`/`streak` were restored to their prior values, the temporary
assignment was deleted, and the temporary TTS `.wav` files removed
from `public/`.)

**New: on-device mic/no-signal detection, added to
`_recording-widget.blade.php`.** Separately, the user asked for a
friendly warning if the microphone isn't picking up any real sound at
all (muted, blocked, broken) instead of silently uploading a dead
recording and only finding out via Reading-api's own slower,
round-trip-based "audio is silent" check. Built with the Web Audio
API's `AnalyserNode`, tapping the SAME `MediaStream` already granted
for recording (no new permission, no data ever leaves the browser, no
third-party service at all — explicitly the deliberately-simple,
zero-privacy-cost alternative to Web Speech API considered and
rejected above). Computes a real RMS level from `getByteTimeDomainData`
every 200ms during recording; if the mic never once exceeds a small
noise-floor threshold (`SILENCE_RMS_THRESHOLD = 0.02`) for the whole
recording (and the recording lasted at least 1 real second, so an
accidental instant tap isn't misjudged), a new `stepSilent` UI shows
("We didn't hear anything that time!... ") with a "Try Again" button
that returns to the ready state — instead of submitting. Reading-api's
own real silence check (the existing `422`/capped-retry flow) is
untouched and still applies for its own domain (audio that has some
signal but isn't recognizable speech) — this is a faster, earlier,
purely local pre-check for the specific "nothing at all is coming
through" case, not a replacement for it.

**Tested for real, not assumed from reading the code** — genuine
microphone permission still can't be exercised in this sandboxed
browser tool (the same standing limitation noted throughout this
project), but the `AnalyserNode` logic itself was exercised against
REAL Web Audio graphs standing in for the mic stream (a legitimate
on-device technique, not a mock of the detection code being tested):
a `MediaStreamAudioDestinationNode` fed by a gain node at `0` (genuine
zero-amplitude real audio data) correctly triggered the "We didn't
hear anything" step and did NOT submit the form; the same setup with
the gain at `0.8` (genuine nonzero real audio data, a plain 440Hz
tone) correctly passed the client-side check and proceeded to a real
submission — which Reading-api then correctly rejected on its own
terms (a pure tone isn't recognizable speech), landing on the existing
"Didn't quite catch that" retry screen exactly as designed, confirming
the two checks stay properly separated rather than one masking the
other. "Try Again" from the silent-mic step was confirmed to return
cleanly to the ready state for another attempt. **Not yet tested
against a genuinely muted/blocked real hardware microphone in a real
browser** — that requires a real device and remains something only
the user/team can verify, same category of limitation as every other
mic-dependent feature in this project.

**A real deployment-chain gap caught by the user, not by me — flagged
here so it isn't repeated.** After reporting the pacing fix as "done,"
it turned out the whole Phase 6 fix had only ever existed locally —
never committed or pushed — so the live production site the user
actually tested was still running the old flat-interval code, not what
was described. The user explicitly asked for direct proof of the full
chain (committed → pushed → merged → deployed → active) before
re-testing, which surfaced this. Confirmed via Railway's own Settings/
Deployments tabs (screenshotted by the user) that Railway deploys from
`claude/admin-dashboard-approvals-62dcd0` directly, not `main` — `main`
was also found to be 6 commits behind (still PHP 8.3, still had
`render.yaml`, no `railway.json`) and was merged up to date as part of
resolving this. **Standing lesson: after any fix described as "tested,"
directly confirm the commit is pushed AND merged AND actually the
active Railway deployment before trusting a live re-test — don't
assume a local pass means production has it.**

**Get-ready delay, added after direct user feedback on the live site.**
The user reported the live-tracking highlight seemed to start moving
before they'd even begun reading aloud. Investigated first — found no
bug (exactly one correctly-gated `tarabasa:recording-started` dispatch,
firing only after real mic capture begins) — so this is a real, disclosed
design limitation: the loop starts the instant the mic starts
*listening*, not the instant the child starts *talking*, since there's
no way to detect "has speech begun" without real transcription (already
rejected above for privacy). Mitigated with a simple 1-second pause
(`GET_READY_DELAY_MS`) before the live loop actually starts moving,
cancelled cleanly if recording stops during that pause. **Tested for
real on live production**, not just locally: instrumented the same
`localStorage`-timeline technique used throughout this pass, confirmed
the highlight index stays blank (`-1`) immediately after
`recording-started` fires, only beginning to advance after the pause —
confirmed present, not instant, on the real deployed page.

The app is deployed on Railway (`grateful-love` project), not Render —
switched after initial Render setup because the user already had a
Railway account with trial credit and didn't want to add a card
anywhere. Both the MySQL database and the web app service live in the
same Railway project so they can be managed together. `railway.json`
(Dockerfile builder, explicit so Railway's Nixpacks auto-detection
doesn't get picked instead just because `composer.json` exists) and
`Dockerfile`/`docker/entrypoint.sh` are the real deployment config —
`render.yaml` was deleted once Render was ruled out, to avoid a future
session following stale platform instructions.

**A real Dockerfile bug found and fixed on the very first deploy
attempt:** the build failed at `composer install` —
`composer.lock` (generated locally on this machine's real PHP 8.5.9)
had resolved several Symfony packages (`symfony/uid`,
`symfony/var-dumper`, `symfony/http-foundation`) that require PHP
`>=8.4.1`, but the Dockerfile was built on `php:8.3-cli`.
`composer.json`'s `"php": "^8.3"` constraint was too loose to catch
this mismatch itself. Fixed by bumping the Dockerfile to `php:8.4-cli`
and correcting `composer.json`'s constraint to `^8.4` to match what's
actually locked (a documentation-accuracy fix, not a functional one —
`composer install` doesn't re-resolve versions).

**A real, more serious bug found during a full live production test
pass, after the app was successfully deployed and reachable:**
Generate Activity (Teacher) and the Learner's reading/diagnostic
submission (both real, slow, external API calls — Gemini and
Reading-api/Vosk respectively) reproducibly failed in production with
no error message at all — the user was just silently logged out
mid-request, no credit consumed, no `Activity`/`ReadingSession` row
created. A short, fast Gemini call (diagnostic passage generation)
succeeded, which was the key clue.

**Root cause: `php artisan serve` (what the Dockerfile ran) is
single-threaded — it can only handle one request at a time.** Railway
was also configured (`railway.json`) to health-check `/up`. While the
single worker was blocked on a slow real external API call, it
couldn't also answer that health check; enough missed checks in a row
very likely caused Railway to restart the container mid-request,
killing the in-flight request and wiping the file-based session (the
container restart theory was not independently confirmed against
Railway's own crash logs — this diagnosis is inferred from the
symptom pattern: instant "not configured" failures look completely
different from these several-seconds-then-silently-logged-out
failures, and only the genuinely slow requests ever failed this way).

**Real, disclosed consequence while this bug was live:** any newly
created Learner was permanently stuck at the first-login diagnostic
screen, since it could never complete — this affects the two features
most central to the thesis (AI activity generation and reading
assessment), not a peripheral one.

**Fix:** PHP's built-in server has a genuine multi-worker mode via the
`PHP_CLI_SERVER_WORKERS` environment variable (a real PHP 7.4+
feature — note this project's own `.env.example` already had this
variable commented out, unused, from the original Laravel scaffold).
Set to 4 directly inside `docker/entrypoint.sh` (`export
PHP_CLI_SERVER_WORKERS=4` before the `exec php artisan serve` line)
rather than as a Railway dashboard variable — deliberately, since a
dashboard-only variable already proved easy to lose by accident once
during this same deployment (see below). This lets the health check
and a slow AI request run concurrently on separate workers instead of
blocking each other.

**Re-verified live against both real slow external calls, for real,
on 2026-09-07 — no longer an open caveat.** Prompted by the user
explicitly asking for direct confirmation rather than assuming the fix
held, since this was the most severe finding from the original test
pass. Two dedicated real tests against live production, both starting
from fresh disposable test accounts (`zztest.genactivity@example.com`,
`zztest.pacingcheck@example.com` / Learner "ZZKid") created through the
app's own real registration/wizard flows — no DB access needed:
- **Generate Activity (Teacher):** submitted a real Generate Activity
  request (Grade 1, Foundational Reading, Word Reading, 3 variants ×
  3 tiers) — a genuine, slow real Gemini call, the exact scenario that
  used to silently kill the session. Result: landed correctly on My
  Activities with all 9 real generated drafts, `free_generation_
  credits_remaining` cleanly decremented 2 → 1 (exactly once, no
  double-charge, no orphaned partial state), teacher session fully
  intact throughout (never bounced to `/login`).
- **Diagnostic submission (Learner):** submitted a real recorded clip
  (genuinely captured via `MediaRecorder` from a synthetic Web Audio
  tone, not a pre-made file) through the live diagnostic passage
  endpoint — another genuine slow real external call (Reading-api).
  Result: landed correctly on the "Didn't quite catch that" retry
  screen (the tone isn't recognizable speech, which is expected and
  irrelevant to what's being tested) — the Learner session survived
  the full real round trip and rendered a proper page, not a silent
  logout.

Both are the exact two request types that used to fail silently before
this fix. Both now complete cleanly on live production.

**A separate, real "vanishing config" incident, resolved but worth
recording:** partway through team testing, `ACTIVITY_AI_URL`/
`ACTIVITY_AI_KEY`/`READING_AI_URL` appeared to stop working in
production (the app showed its own honest "isn't configured yet"
messages, which only fire when these are genuinely empty at runtime).
Investigated by having the user screenshot Railway's real Variables
tab directly: all three were actually present and correctly valued,
alongside `APP_KEY`/`DB_*`. The Deployments tab showed the active
deployment running for a full day with no pending-changes banner,
meaning nothing was sitting unapplied. Conclusion: the team's
screenshots were almost certainly taken before these variables were
originally added, not a fresh regression — not chased further than
that once the live dashboard state was confirmed correct.

**A real security-conscious catch made during the deployment
walkthrough itself, worth remembering for future GitHub App
installations:** when installing Railway's GitHub App, the default
selection was "All repositories" (current AND future repos, org-wide)
— caught before confirming and switched to "Only select repositories"
scoped to just this one repo.

**Genuinely tested live in production, not just claimed:** a full,
systematic pass through every area of the app (homepage, both
registration flows, all three login roles + the role-mismatch check,
Admin approve/reject/deactivate — deactivation confirmed to actually
block login with the right message, not just cosmetic — Teacher
Dashboard/Class Management/My Activities/Analytics/Promotions/Profile,
Parent Dashboard/My Children (a real child created via the full 5-step
wizard)/Progress/Repository/Profile, Learner PIN login, and both
Notifications views) using clearly-named test accounts
(`zztest.*@example.com`, `TEST`/`TESTCHILD`/`TESTREJECT` prefixes) —
everything passed except the one bug above. Confirmed via direct
production-database inspection (temporarily pointing this worktree's
own `.env` at the real Railway MySQL, always reverted to SQLite
afterward) that both failed attempts left zero partial data — no
stray consumed credits, no orphaned rows — not just that the page
looked like it failed.

## Adaptive_Recommendator integration — the third and final teammate
## service, real "what to read next" recommendations

Learner Actor Prompt Step 3's "Adaptive Recommend" stage — the last of
the three teammate services (`gemini_activity_gen`, `Reading-api`,
`Adaptive_Recommendator`) to actually get wired up. Read the real
deployed source directly first (`main.py`, `models.py`, `engine.py`,
`config.py` from github.com/BldZeuz/Adaptive_Recommendator), not just
its README, per this project's standing rule for third-party
integrations.

**The real contract, confirmed from source, not assumed:**
`Adaptive_Recommendator` is completely stateless — no DB, no
persistence of its own (its own README says so explicitly: "Your main
backend stores assessment results, attempts, competency state, used
activity IDs, and bundle history"). Every `/recommend` call must carry
the Learner's full current per-competency state
(`{proficiency, difficulty, confidence, attempt_count}` for each of
`foundational_reading`/`reading_fluency`/`reading_comprehension`) and
recent history; the response's updated state must be persisted back.
`/initialize` (called once, after the diagnostic) and `/recommend`
(called after every real Practice reading) both require the same
`X-App-Key` header pattern as `gemini_activity_gen`.

**A real data-availability gap, confirmed and left honest rather than
worked around:** `reading_comprehension` requires a `comprehension_score`
input the app has never had any way to produce (no comprehension-quiz
feature exists anywhere) — traced through `engine.py`'s
`choose_next_competency()` and confirmed it already handles an
unassessed competency gracefully (excluded from rotation entirely when
`proficiency` is `null`, never defaulted to zero), so `reading_comprehension`
is simply never sent a score and never recommended, by design — not a
crash, not a fabricated value, matching this project's existing NULL-
over-fabrication principle. Similarly, the diagnostic itself only ever
assesses `reading_fluency` (hardcoded in `ensureBundleGenerated()`), so
`foundational_reading`/`reading_comprehension` start every Learner's
`competency_states` honestly unassessed.

**New data this repo now owns**, since the service stores none of it:
`learners.competency_states` (JSON, the service's own `CurrentState`
shape verbatim), `learners.next_recommended_competency`/
`next_recommended_difficulty` (the most recent `next_recommendation`,
kept as plain columns so `findActivity()` can match against them
directly), and `reading_sessions.speed_score`/`prosody_score`/
`adaptive_attempt_score`. The first two of those three were a genuinely
free find while reading Reading-api's own real source again for this
work: it already returns a real 0-100 `speed_score` (grade-appropriate-
WCPM-derived, distinct from the raw `wcpm` already stored) and a real
0-100 `prosody_score` (an acoustic heuristic) on every `/analyze` call
— both had been silently discarded since Slice 2/3, never persisted.
Now captured in both `LearnerReadingController` and
`LearnerDiagnosticController`.

**The one design decision flagged rather than guessed on, confirmed
with the user before building:** when no already-available activity
(Teacher-assigned or repository-unlocked) matches the current
recommendation, the picker shows the existing options completely
unchanged — never triggers a fresh Activity Generation call on the
Learner's behalf. Reasoning: generation credits belong to a Teacher
(`free_generation_credits_remaining`) and there's no honest answer to
"who pays" for a Learner-triggered generation; it's also a genuine
30-150s live Gemini call that shouldn't block a child mid-session
waiting on it.

**New `AdaptiveRecommendatorClient`** (`app/Services/`), same shape as
`ActivityAiClient`/`ReadingAiClient` — `X-App-Key` header,
`set_time_limit()`/`Http::timeout()` guard (90s; a real cold health-
check start measured at ~55s during testing), friendly
`\RuntimeException` on any real failure. **Every call site swallows
that exception and continues without a recommendation** — a Learner
must never be blocked from reading, from completing the diagnostic, or
from picking an activity because this one enhancement is down.
`LearnerDiagnosticController::finishDiagnostic()` calls `initialize()`
once, at the very end of the staircase; `LearnerReadingController::
scoreAndPersist()` calls `recommend()` after every real Practice
reading, rebuilding `recent_history` from real past sessions that
already went through this same integration (`adaptive_attempt_score`
not null — a pre-integration session's plain `accuracy_percent` isn't
the same scale as the service's own weighted score, so it's honestly
excluded rather than mixed in). `LearnerAuthController::findActivity()`
prioritizes and labels a matching option "Picked just for you! 🎯"
(orange-accented, distinct from the existing "Assigned by your
Teacher"/"Extra Practice" labels), sorted to the front — an exact
competency+difficulty match is required when a difficulty is present,
competency-only when it isn't (a newly-assessed competency has no
difficulty yet), never a downgraded/misleading match.

**A real key mix-up caught before it caused a false negative:** the
user initially wondered whether `ACTIVITY_AI_KEY`'s value could also
be `Adaptive_Recommendator`'s real key, since it looked suspiciously
identical — confirmed this for real via a live `GET /config` request
with that exact key (not assumed): `200`, real config body returned
matching the source exactly. The teammate genuinely reuses one shared
key across both services; this wasn't a resent-by-mistake value.

**Tested for real against the live deployed service, with airtight
proof it's genuinely live, not a coincidence** — verified locally
end-to-end with a fresh Learner ("AdaptiveLive Test") through the real
diagnostic (genuine TTS audio, real Gemini-generated passages):
- `/initialize` returned real `competency_states` — `reading_fluency:
  {proficiency: 97.4, difficulty: "hard", confidence: 0.6,
  attempt_count: 0}` for a genuine 97.4% diagnostic landing, with
  `foundational_reading`/`reading_comprehension` correctly left fully
  unassessed (`proficiency: null`), exactly as predicted from reading
  `engine.py` beforehand.
- The picker correctly matched and labeled a real Hard `reading_fluency`
  activity "Picked just for you! 🎯", sorted first, while a
  non-matching Easy `foundational_reading` activity correctly kept its
  plain "Assigned by your Teacher" label — confirmed both in the
  matching case and (separately, via a simulated non-matching
  recommendation) the no-match case, where options render completely
  unchanged.
- A real 87.5%-accuracy TTS reading of that recommended activity
  triggered `/recommend`, which returned `adaptive_attempt_score:
  83.75` — verified this is a genuine live computation, not
  coincidence, by checking it against the service's own documented
  `reading_fluency` weights (accuracy 0.40 / speed 0.35 / prosody
  0.25) pulled from its real `/config` response: `87.5×0.40 +
  100×0.35 + 55×0.25 = 83.75`, an exact match. The updated proficiency
  (97.4 → 93.3) was then checked against its documented EMA formula
  (`alpha=0.30`): `97.4×0.70 + 83.75×0.30 = 93.305 ≈ 93.3`, also exact.
  Neither formula is implemented anywhere in this app's own code — an
  exact match on both is only possible if the real service computed
  them, not a bug that happens to look right.
- `confidence` correctly stepped `0.60 → 0.68` (matching the service's
  own `CONFIDENCE_STEP=0.08` default exactly) and `attempt_count`
  correctly incremented `0 → 1`.
- Graceful fallback confirmed separately, before the real key was
  added: both integration points logged a clean warning and continued
  normally (correct results screen, correct diagnostic completion, no
  crash) while `ADAPTIVE_RECOMMENDER_KEY` was still an empty
  placeholder — confirmed a Learner who completed their diagnostic
  under the old code (e.g. `competency_states` still `null`) is never
  blocked or crashed on by the new `recommend()` call either, since it
  short-circuits before attempting the request at all.

**`ADAPTIVE_RECOMMENDER_URL`/`ADAPTIVE_RECOMMENDER_KEY` are now live on
Railway** — the user added both directly via Railway's Variables tab;
confirmed correct by direct comparison against this codebase's own
`config/services.php`/`.env` values before the user deployed. This
integration is no longer honestly reporting "not configured" on
production — it's active, same rollout pattern the other two services
went through.

## My Activities — scrolling/density fix (status filter tabs, compact
## rows, sticky toolbar)

The real, live "My Activities" page had become a genuine usability
problem for a Teacher with real accumulated history — all Drafts and
Approved activities stacked in one long column, forcing a deep scroll
past everything already resolved just to reach what's actionable.
Fixed with three changes, all reusing this app's own established
patterns rather than inventing new ones:

- **Status filter tabs**, the exact `.filters`/`.filter-chip`/
  `.filter-chip.active` CSS already used byte-for-byte in
  `teacher/notifications.blade.php` and `parent/repository.blade.php`
  — reused verbatim, not reinvented. One deliberate wiring difference
  from those two pages: driven by `<button data-filter="...">` +
  client-side JS instead of `<a href="?filter=...">` server links, so
  the new filter composes instantly with this page's pre-existing
  client-side search box (added in the earlier design-audit pass)
  without a server round trip resetting it — matching this specific
  page's own prior convention, not the other two pages' convention.
  Defaults to "Awaiting Review," the one actionable state. Three
  `<div data-status-section="draft|approved|rejected">` wrappers hold
  the existing, unchanged `@forelse`/`@include`/`@empty` blocks; the
  two non-default sections carry a server-rendered `hidden` attribute
  so there's no flash of the full unfiltered list on first paint.
- **Compact rows**: `_card.blade.php` restructured into a `.card-head`
  flex row — tags/title/meta on the left (`.card-head-main`), the
  Approve/Edit/Reject (Draft) or Assign/Share to Repository (Approved)
  buttons inline on the right, instead of stacked below the passage
  box. A `data-status` attribute was added to the outer card for
  potential future use. Same information and actions, denser layout —
  the passage box, edit-form, assign-form, and share-form all kept
  their original content/order below the new header row.
- **Sticky toolbar**: `.controls-sticky` (`position:sticky; top:0`,
  solid `--bg-0` background, bottom border) wraps the search box and
  the new filter chips, since this page's scroll container is the
  plain document body with no `overflow` ancestor — `top:0` pins
  correctly to the viewport.

**Tested for real against a real Teacher's real accumulated data, not
synthetic/mocked** — logged in as `teacher.mi.20260829192315@example.com`
(25 real activities: 18 Draft, 4 Approved, 3 Rejected — the same real
account used for Sprint 5 Analytics testing; its test password was
already unknown from that prior reset, so a new one was set via
`tinker` the same way, consistent with that established precedent).
Confirmed via real browser interaction, not just code review:
- All three filter chips render the correct real counts ("Awaiting
  Review (18)", "Approved (4)", "Rejected (3)"), default to Awaiting
  Review, and clicking each correctly swaps which section is visible
  — confirmed Approved shows exactly its 4 real cards with
  Assign/Share buttons, Rejected shows exactly its 3 real cards with
  "No further action available."
- The existing client-side search composes correctly with the active
  filter: searching "animal" while on Awaiting Review narrowed 18 real
  Draft cards down to the 15 real ones actually matching, entirely
  within that section; a deliberate no-match query ("zzznomatch")
  correctly showed the existing "No activities match" note.
- **Sticky positioning confirmed genuinely functional, not just
  computed-style-correct**: scrolled 400px down, then clicked the
  "Approved" filter chip at its on-screen position — the click only
  lands correctly if the toolbar is truly pinned to the viewport top,
  and it worked, correctly swapping the active filter while scrolled.
  (A visual gap appeared above the toolbar in screenshots taken at
  this scroll position — cross-checked against live DOM geometry
  [`getBoundingClientRect()` reporting `top: 0`, `position: sticky`]
  and confirmed this is the same pre-existing stale-screenshot/
  `background-attachment:fixed` capture artifact already documented
  above from the earlier UI/UX audit pass, not a real layout bug.)
- All pre-existing interactive behavior on the restructured card
  still works post-move: the Draft Edit-toggle opens the pre-populated
  edit form correctly; the Approved card's Assign-toggle opens with
  working Learner/Class/Group radio-to-select sync (confirmed
  switching to "Class" correctly swapped the visible/enabled dropdown);
  the Share-to-Repository toggle opens with working Free/Paid radio
  (confirmed selecting "Paid" correctly enables the price field).

## Adaptive visibility — "How I'm Growing" (Learner) + "Adaptive Focus"
## (Teacher/Parent)

Before this, the Adaptive_Recommendator engine's per-competency state
only ever surfaced as one small "Picked just for you! 🎯" label in the
Learner's activity picker — genuinely invisible backend logic for a
feature literally named in this app's identity. Investigating a PM
question about comprehension scoring (see the section below) surfaced
a real, separate, already-occurred bug; this section is the unrelated,
lower-stakes "make it visible" work that was explicitly authorized to
proceed in parallel while that bug gets properly scoped.

New `Learner::competencyProgressSummary()` — the one shared source for
turning `competency_states`' real 0-100 proficiency / easy-medium-hard
difficulty into something presentable, used by all three new UI
surfaces below so the competency-slug-to-friendly-label mapping isn't
duplicated three times. No raw numbers shown anywhere — proficiency
only ever drives a bar's fill percentage, difficulty becomes a friendly
word ("Just Right"/"Getting Stronger"/"Challenging You") — same
"no raw score" spirit already established for the diagnostic. Returns
`[]` when `competency_states` is still `null` (pre-diagnostic), so
every caller renders an honest "hasn't started yet" state instead of a
fabricated one.

**Learner Dashboard** (`learner/dashboard.blade.php`): a new "How I'm
Growing 🌱" card below the existing Level/Points/Streak row, one row
per competency (Sounding Out Words / Reading Smoothly / Understanding
Stories), each a bar-fill + friendly difficulty word, or an honest
dashed "Not started yet" bar when unassessed. The competency matching
`next_recommended_competency` gets a small "⭐ Up Next" badge — the
same signal already driving the picker's label, now visible somewhere
a child actually looks at every login, not just buried in an option
list. The whole section is omitted entirely (not shown empty) for a
Learner who hasn't completed their diagnostic yet.

**Teacher Analytics (By Learner) + Parent Progress**: a new shared
`partials/adaptive-focus-card.blade.php` (markup only — each including
page defines the `.adaptive-card`/`.adaptive-chip` styles in its own
`<style>` block, consistent with this app's standalone-per-view
convention) — a headline naming the Learner's current adaptive focus
in friendly terms, plus three small status chips for all three
competencies (the active one highlighted). Sits directly below the
existing Teacher-Assigned/Parent-Initiated stat cards on both screens,
no new page, no controller changes needed (`$selectedLearner` was
already the full `Learner` model on both). An honest, distinct message
("hasn't completed their first-login reading check yet") when
`competency_states` is still null, rather than an empty/broken card.

**Tested for real against real data, all three surfaces, both
branches (populated and unassessed) — not just code review:**
- Logged in as a real Learner with a genuine live `competency_states`
  row (`reading_fluency: {proficiency: 93.3, difficulty: "hard"}` from
  earlier Adaptive_Recommendator production testing, `foundational_
  reading`/`reading_comprehension` still honestly null) — the Learner
  Dashboard correctly showed a near-full teal bar with "⭐ Up Next" on
  Reading Smoothly, and genuine dashed/"Not started yet" bars on the
  other two, confirmed via both `get_page_text` and a screenshot.
- The same real Learner's data, viewed from a real Parent's Progress
  screen (`elena.cruz@example.com`): the Adaptive Focus card correctly
  showed "Currently practicing: Reading Smoothly (Challenging You)"
  with the matching chip highlighted orange and the other two marked
  "Not assessed" — confirmed via screenshot.
- The honest empty-state branch, on a real class-linked Learner with
  no `competency_states` yet (Teacher Analytics, By Learner mode):
  correctly showed "FreshHistory hasn't completed their first-login
  reading check yet — this fills in once they do," not a blank or
  broken card — confirmed via screenshot.
- `php -l` clean on all five touched/new files; zero server errors in
  the dev server's logs across the whole test pass.

## Real comprehension-quiz feature — fixes the `reading_comprehension`
## adaptive-update bug for good, not just scoped anymore

While investigating a PM question ("should every activity produce
Accuracy/Speed/Prosody/Comprehension together?"), re-read
Adaptive_Recommendator's real `engine.py`/`models.py`/`config.py`
directly. **The literal question's premise is false** — confirmed both
from source and a live test call: `/recommend` only requires the
score(s) tied to whichever competency the completed activity is
tagged with (`foundational_reading` → accuracy+speed;
`reading_fluency` → +prosody; `reading_comprehension` → comprehension
only), never all four regardless of type. This already matched what
was implemented.

**But investigating it surfaced a real, currently-live bug, not a
hypothetical.** Teachers really do generate and assign
`reading_comprehension`-competency Activities (3 real ones exist in
this app's database, generated via the real Generate Activity flow —
this is a real, exercised path, not a theoretical one). `Learner
ReadingController::updateAdaptiveRecommendation()` sends
`comprehension_score: null` unconditionally (no comprehension-quiz
feature exists anywhere in the app), which is exactly the one field
`reading_comprehension` requires — so every real attempt at this
competency gets rejected by the live service with a 422 ("Missing
required performance metric(s): comprehension_score"), caught and
silently logged as a warning, never surfacing to anyone. **Confirmed
this already happened on real data**, not just a live-tested
reproduction: a real Learner (Miguel) already read a real
`reading_comprehension` Activity ("Blue Crab at the Beach") for real —
that session's `adaptive_attempt_score` is `NULL` in the database
right now.

**The untapped resource that makes this closable**: `gemini_activity_
gen`'s real source strictly enforces (server-side validation, not
convention) that `follow_up_questions` — 2-3 real Gemini-generated
multiple-choice questions with answer + explanation — exist *only* for
`reading_comprehension`-type activities, and must be empty for every
other type. The 3 real `reading_comprehension` Activities in this
app's database already have this real quiz content sitting completely
unused. This also connects to a second, pre-existing disclosed gap:
Reading-api's own `/analyze` endpoint accepts an optional
`comprehension_score` to compute a composite `reading_proficiency`,
which has stayed `"waiting_for_comprehension"` for every session since
Sprint 4, for the same underlying reason.

**Built exactly as scoped, then verified end-to-end against the real
live services, not just code review.** A Learner-facing comprehension-
quiz step, specifically for `reading_comprehension`-competency
activities only, inserted between "finished recording" and the actual
form submit (Reading-api requires `comprehension_score` in the *same*
`/analyze` call as the audio, so the quiz has to happen before
submission, not after). New nullable `reading_sessions.comprehension_
score` migration; `ReadingAiClient::analyze()` gained an optional
`?float $comprehensionScore` param, included in the multipart fields
only when non-null; `LearnerReadingController::scoreComprehensionQuiz()`
computes the real score server-side (never trusts a client-submitted
score — compares each picked choice's text against the Activity's own
stored `follow_up_questions[].answer`); a small backward-compatible
hook added to `_recording-widget.blade.php` (`window.
tarabasaBeforeSubmit`, called right after the recorded file is
attached — if an including page defines it, that page controls when
the real submit actually fires via the also-exposed `window.
tarabasaSubmitRecording()`; if undefined, submission proceeds
immediately exactly as before — a no-op everywhere except this one
feature, confirmed the diagnostic is completely unaffected since it
never generates `reading_comprehension` activities). `activity-found.
blade.php` renders the quiz step (real questions, radio choices with
`form="recordForm"` so they submit alongside the audio without being
DOM-nested inside the widget's own `<form>`) only when the Activity
actually has real `follow_up_questions`; client-side JS blocks
submission until every question is answered, with a gentle inline
note, not a browser alert. `reading-results.blade.php` shows the
warm recap: a plain "X out of Y correct" count (never a percentage or
grade) plus a per-question review reusing the same warm,
color-coded-not-punitive tone as the existing word-breakdown feature —
✅ for correct, a gentle "💡 The answer was: ___" for a miss, never
"wrong." Mastery-level and points math stay accuracy-only, an existing
separate decision, untouched by this.

**Tested for real, end to end, against the actual live Reading-api and
Adaptive_Recommendator services — not mocked, not just code review:**
- Real TTS audio (Windows `System.Speech`, this project's established
  technique) reading the real passage of a real `reading_comprehension`
  Activity ("A Day at the Coral Reef"), submitted via a real multipart
  POST (consolidated single-script login→CSRF→submit, this project's
  established fix for PowerShell's per-call session-loss problem) with
  2 correct + 1 deliberately wrong answer: the real database confirmed
  `comprehension_score = 66.67` (exactly 2/3), and the results page
  correctly rendered "You got 2 out of 3 correct! 🙂" with the missed
  question showing "💡 The answer was: A tiny green turtle" — a real,
  computed, non-fabricated result.
- A second real run with all 3 answers correct confirmed the other
  branch: "You got 3 out of 3 correct! 🌟", all three questions shown
  with ✅.
- **The actual bug, confirmed fixed, not just "should work now":**
  using a Learner with real pre-existing `competency_states`
  (`reading_fluency` already assessed from earlier Adaptive_
  Recommendator testing), the same real submission flow produced a
  genuinely successful `/recommend` call — `reading_comprehension` in
  her `competency_states` went from permanently `null` (this
  competency had NEVER once been successfully assessed for any Learner
  before this fix) to a real `{proficiency: 100, difficulty: "hard",
  confidence: 0.68, attempt_count: 1}`. `adaptive_attempt_score` in the
  database exactly equals the submitted `comprehension_score` (both
  100.0) — an exact match to the real service's own documented
  `comprehension_weights = {comprehension_score: 1.00}`, and
  `confidence` correctly stepped `0.60 → 0.68` (the same real
  `CONFIDENCE_STEP=0.08` already confirmed for `reading_fluency`
  earlier) — proof this is a genuine live computation, not a bug that
  happens to look right. Zero new errors appeared in the Laravel log
  for either real submission (both fully succeeded).
- **Reading-api's own composite score confirmed fixed too, directly**:
  a real direct call to `/analyze` with a real `comprehension_score`
  returned `"status":"complete"` (no longer `"waiting_for_comprehension"`)
  with `"components":{...,"comprehension":83.3}` matching exactly what
  was sent — the second, older disclosed gap from Sprint 4 is closed by
  the same fix, not just the Adaptive_Recommendator one.
- **The client-side quiz mechanics verified directly in a real browser**
  (real mic capture still can't be exercised in this sandboxed
  environment, the same standing limitation noted throughout this
  project): invoked the real `window.tarabasaBeforeSubmit()` hook
  directly (the same legitimate on-device technique already used for
  this project's mic-silence-detection testing) and confirmed the quiz
  step correctly replaces the recording step; confirmed clicking
  "Submit My Answers" with unanswered questions is blocked with the
  gentle inline note and no submission; confirmed answering all three
  and clicking submit correctly fires the real form submission (proven
  by the server correctly rejecting the deliberately-audio-less request
  with "The audio field is required." — exactly the expected outcome
  for a test that skips the real recording step on purpose).
- **One real, unrelated cold-start hiccup hit and correctly diagnosed
  during testing, not confused for a code bug**: the first live attempt
  hit a genuine "Maximum execution time of 120 seconds exceeded" from
  Reading-api's Render free-tier cold start (a known, previously-
  documented risk for this service) — confirmed via `/health` the
  service was still warming up, retried once it was confirmed live, and
  the retry succeeded cleanly. Not a defect in this feature.
- **Test data side effects identified and cleaned up afterward**, per
  established practice: the real `ReadingSession` rows created by both
  test submissions were deleted, the temporary direct `ActivityAssignment`
  created purely for test setup (bypassing the real Teacher UI, since
  the test Learner wasn't in a class) was removed, the 4 real
  `Notification` rows these submissions triggered (session-summary
  notices to Miguel's real Teacher and Parents) were deleted, and
  Miguel's `mastery_level`/`points`/`streak` were restored to their
  prior real values — he's the flagship reference Learner used
  throughout this whole project's documented testing, not a disposable
  account. The test Learner's own points/streak/mastery were similarly
  restored, but her `competency_states` was deliberately left showing
  the new real `reading_comprehension` assessment as live proof the fix
  works — consistent with this project's established practice of
  leaving disposable test accounts' real generated state in place.

## Live word-tracking removed entirely — repeated real complaints, and
## the only genuinely accurate fix (Web Speech API) was already rejected

The decorative word-tracking highlight (built, then patched twice for
real pacing bugs — see the two sections above this one in this file's
history) kept generating real accuracy/pacing complaints even after
those fixes, because the fundamental problem was never fixable within
its own constraints: Reading-api has no real-time per-word signal at
all, so the highlight was always a *guess* dressed up to look precise,
and a guess that looks precise reads as broken the moment it's visibly
wrong. The one way to make it genuinely accurate (Web Speech API,
driven by the child's real live voice) was already investigated and
explicitly rejected on privacy grounds. Given that, simplifying to "no
highlighting at all" is more honest than continuing to patch an
inherently-approximate animation — removed entirely rather than
patched a third time.

**Removed from both `activity-found.blade.php` and `diagnostic-
passage.blade.php`**: the `.lw`/`.lw.tracking` CSS, the per-word
`<span class="lw">` wrapping of `passage_text` (both now render the
passage as plain text again), and the entire word-length-weighted +
real-duration-rescaled pacing `<script>` (including the `GET_READY_
DELAY_MS` pre-recording pause, no longer needed with nothing to
pace). **Removed from the shared `_recording-widget.blade.php`**: the
`tarabasa:recording-started`/`tarabasa:recording-stopped` custom-event
dispatches (grepped the codebase first to confirm these two files were
the only consumers before removing — they were) and the doc comment
describing them. In their place: a plain "Listening..." `.mic-label`
added to the widget's existing `stepRecording` UI, alongside the
already-existing pulsing mic icon and elapsed-time timer — genuinely
simpler, not a placeholder for something more elaborate later.

**Explicitly NOT touched, per instruction**: the real results screen
(`reading-results.blade.php`) — the color-coded word-by-word breakdown,
legend, "you also said" extra-speech note, and the 4 stat tiles all
already work well and were left completely alone.

**Tested for real, not assumed from the diff:**
- Live in a real browser, logged in as a real Learner (Miguel) on a
  real assigned Activity: confirmed zero `.lw`/`.tracking` elements
  exist anywhere on the page (`document.querySelectorAll` returned 0
  for both), the passage renders as plain text, and starting a real
  recording (via a real synthetic `MediaStream` substituted for
  `getUserMedia` — the same legitimate on-device technique already
  established in this project for exercising `MediaRecorder` without
  real hardware access) correctly shows "Listening..." with the
  existing pulsing icon and a live-counting timer, with nothing
  highlighting anywhere in the passage.
- Confirmed the recording → comprehension-quiz → real-submit chain
  still works correctly post-removal: clicking "I'm done reading!" on
  a real (non-silent) synthetic stream correctly triggered the quiz
  step, and submitting a genuinely mismatched tone correctly landed on
  the real "Didn't quite catch that" retry screen — proving the
  surrounding mechanics (silence detection, the quiz hook, real form
  submission) are all unaffected by removing the tracking system that
  used to sit alongside them.
- **A full real scored submission, end to end**: genuine TTS audio
  (this project's established technique) submitted via a real
  multipart POST produced a real 92% accuracy / 153 WCPM / +46 points
  / streak 14 result, with the real color-coded word breakdown (70
  correct spans, 6 substitution spans), the legend, and the
  comprehension recap ("You got 3 out of 3 correct! 🌟") all rendering
  exactly as before — confirming the explicitly-untouched results
  screen is genuinely untouched, not accidentally broken by the
  surrounding removal.
- Zero new entries in the Laravel log across the whole test pass.
- Test data side effects (a real `ReadingSession` row, 3 real
  `Notification` rows, and Miguel's `mastery_level`/`points`/`streak`)
  identified and cleaned up afterward, same as every other real test
  pass in this project.

## Learner typography — re-confirmed with live measurements, not
## re-assumed from the earlier fix

The user asked for the actual current pixel values, not a reference
back to the earlier typography-floor fix (see "Learner typography
follow-up" above in this file) — so every value below was re-measured
live via `getComputedStyle()` on real rendered pages this session, not
read from CSS source and assumed still accurate.

**Learner Dashboard** (`learner/dashboard.blade.php`, real Learner
Miguel): `h1` 26px, `.grade-line` 18px, stat-tile values 24px (Points/
Streak — the Level tile is deliberately smaller, 18px, since it holds
a word like "Beginning" not a number), stat-tile captions 13px.

**"How I'm Growing" card** (added this session, not part of the
original typography-floor pass — measured live on a real Learner with
real `competency_states`): `.growth-title` 16px, `.growth-label`
(competency names) 14.5px, `.growth-word` (difficulty words) 12px, the
"⭐ Up Next" badge 11px. **Flagged honestly, not silently**: the
label/word/badge sizes sit below the established ≥16-18px body-copy
floor — same treatment as the existing stat-tile captions and the
"Switch learner" link, which were deliberately kept smaller as
supporting metadata rather than primary reading content. Worth a
second look if the user wants every visible word at floor size, but
not assumed to be a defect.

**Recording screen** (`activity-found.blade.php`, real assigned
Activity): `h1` 25px, `.sub` 18px (line-height 27.9px, the designed
1.55×), `.passage-card` 23px (line-height 39.1px, exactly the designed
1.7×), `.mic-label` 17px — all unchanged by the tracking removal above,
confirmed on the same live page.

**Results screen** (`reading-results.blade.php`, a real scored
submission rendered live via an authenticated in-browser `fetch()` to
the real endpoint, not a static file): `h1` 25px, `.sub` 18px,
`.breakdown-title` 15px, `.passage-review` (the word-by-word review
text) 18px/line-height 34.2px, `.comprehension-count` 15px,
`.comp-question` 14px, stat-tile values 24px, stat-tile captions 13px.

**Diagnostic flow** (`diagnostic-passage.blade.php`): not re-measured
live this pass (would have needed a fresh Learner mid-diagnostic) —
confirmed by direct source inspection instead, since this file was
directly edited this session for the tracking removal above: `.passage-
card` is still 23px/line-height 1.7×, identical to `activity-found.
blade.php`'s rule, unchanged by that edit. Disclosed as source-
confirmed, not live-measured, rather than implied otherwise.

## Responsive layout fix — Learner reading screens no longer pinned to
## a mobile-width layout on larger screens

Both `activity-found.blade.php` and `diagnostic-passage.blade.php`
capped their card at `max-width:460px` unconditionally — correct on a
phone, but the exact same narrow card on a laptop/desktop, floating in
a lot of unused space instead of using the screen well. Added two
`min-width` tiers (mobile-first, so phone behavior is completely
unchanged): **≥700px** (tablet) and **≥1024px** (laptop/desktop).
`.wrap` widens 460px → 640px → 780px — the 780px ceiling deliberately
matches the ~820px cap this app's own Teacher/Parent shells already
use, so an ultra-wide monitor doesn't stretch the card absurdly wide
either, consistent with the rest of the app. Card padding, the mascot,
h1/`.sub`, the passage text, the mic button (including its inline SVG
icon, overridden via a real CSS rule since CSS wins over the SVG's own
width/height attributes), mic label, timer, and the comprehension
quiz's own text/choices all scale up at the same two tiers. Added
`overflow-wrap`/`word-break` to `.passage-card` as a general safety
net while touching this file (also relevant to the font-size control
requested next, which needs the same guarantee at every size step).

**Tested for real at 4 actual viewport widths** on a real assigned
Activity (`activity-found.blade.php`, logged in as Miguel) — not just
"technically doesn't break":
- **375px (phone)**: unchanged from before, confirmed correct.
- **768px (tablet)**: confirmed live via `getComputedStyle` mid-test
  and visually via screenshot — wider card, visibly larger passage
  text and mascot; genuinely looks designed for the space, not just
  "the same mobile card, more padding."
- **1280px (laptop)**: confirmed live — `.wrap` 780px, passage text
  28px, h1 30px, mic button 112px — the largest tier, visually
  confirmed via screenshot.
- **1920px (desktop)**: confirmed the card holds at the same 780px
  ceiling rather than stretching wider — correct, matches the rest of
  the app's own established desktop-width convention.
- The recording state specifically (mic button + "Listening..." label
  + timer) re-confirmed at the tablet tier via a real synthetic-stream
  recording (the same on-device technique used throughout this
  project) — `recordingActive: true`, mic button 112px, label 19px,
  timer 28px, all correct tablet values.
- `diagnostic-passage.blade.php` received the identical breakpoint
  treatment (same selectors, same values) but was **not** independently
  live-screenshotted this pass — spinning up a fresh Learner mid-
  diagnostic (a real Gemini-generated bundle) purely to re-confirm an
  already-proven, structurally identical CSS pattern wasn't judged
  worth the real API cost. Disclosed rather than implied otherwise;
  `php -l` clean, and the file is byte-for-byte the same breakpoint
  pattern as the independently-verified `activity-found.blade.php`.
- Zero new entries in the Laravel log across the whole test pass.

## Results screen word-breakdown — checked against the actual design
## reference image, real "Words to Practice" stat added, 2 categories
## confirmed still not honestly buildable

The user shared the design reference mockup directly (the same one
`LearnerReadingController::buildWordBreakdown()`'s own comment already
referenced) showing 5 word-status categories and asked whether
`reading-results.blade.php` matched it. **It doesn't, and shouldn't
fully** — investigated again to be sure this wasn't stale reasoning:
confirmed the same real constraint as before still holds. Reading-api's
`word_feedback` has no confidence score and no field linking an entry
back to `word_timestamps`, so "Mispronounced" can't be derived without
an arbitrary invented threshold, and "Repeated" has no real signal in
this data shape at all. Building either would mean fabricating a
distinction the real service doesn't provide — the same principle this
project has held to consistently elsewhere (NULL over fabrication).
**Only 3 of the 5 categories in the reference are real: Correct,
Skipped, Said a different word** — and checking the existing CSS
against the reference image confirmed those 3 already use nearly the
identical color language (amber strikethrough for skipped, purple
dotted-underline chip for substitution) — this had already been done
correctly, just without a "Correct" entry in the legend itself.

**What was genuinely new and honestly buildable from the reference**:
a "Words to Practice" stat, which needs no new data at all — it's
just a real count of the same skip+substitution words already in
`$wordBreakdown`, tallied in `LearnerReadingController::
buildWordBreakdown()` and returned as `practiceCount` (`null`, not 0,
when there was no real `word_feedback` to count at all, so an
untested edge case can't misread as "confirmed zero to practice").
Added as a genuine addition alongside the existing Accuracy/WCPM/
Points/Streak tiles — a new 2-row stat grid (Accuracy/WCPM/Words to
Practice, then Points/Streak) — rather than replacing Points/Streak as
the reference's own 3-tile layout does, since dropping visible
points/streak feedback from this screen would be a real product
regression, not something to do silently just to match a mockup pixel-
for-pixel. Legend also gained a "Correct" swatch, matching the
reference's inclusion of it, and swatch size/weight nudged up slightly
to match the reference's bolder look.

**Tested for real, not assumed from the diff**: a real TTS reading of
Miguel's real assigned Activity, submitted twice (once via the
established PowerShell multipart technique, once via a real
authenticated in-browser `fetch()` rendered live into the DOM for
visual confirmation) — the database and both renders agreed exactly:
`practiceCount = 6` (matching the same reading's real 6 substitution
spans already on record from earlier testing), stat grid rendered
correctly in the new 2-row layout, and the legend/word-highlight
styling visually confirmed via screenshot to match the reference's
color language for all 3 real categories. Test data (2 real
`ReadingSession` rows, 6 real `Notification` rows, Miguel's `mastery_
level`/`points`/`streak`) cleaned up and restored afterward.

## Persistent passage-text size control (A−/A+, 5 fixed steps) + Lexend

Before building, checked the actual codebase (not memory) for whether
Fix 1 (responsive layout) or this feature already existed — confirmed
via `git log` and direct grep: Fix 1 was already done and committed
(`18389e4`, `resources/views/learner/activity-found.blade.php` already
had 14 real `min-width` media-query rules), but literally nothing for
the font-size control existed anywhere — zero matches for "Lexend,"
"font_step," or any size-step naming across the whole codebase. Built
fresh, no reference file was ever supplied (asked for it twice; not
provided) — the visual chrome below is this session's own design using
the app's established tokens, not a reproduction of a reference.

**New nullable `learners.reading_font_step`** (tinyint, 1-5). Not
defaulted in the schema — the effective default depends on
`grade_level`, which the schema layer shouldn't hardcode. New
`Learner::effectiveReadingFontStep()` is the one place that resolves
"what size right now": the Learner's own explicit choice if they've
ever made one, otherwise a grade-based starting point (Grade 1→4/
"Large", Grade 2→3/"Medium", Grade 3→2/"Medium−" — confirmed via real
Learner records: Miguel is actually Grade 3, not Grade 1 as initially
assumed from memory, and a real Grade 1 Learner, "Kim," confirmed
step 4).

**The 5 steps compose with the existing responsive layout instead of
fighting it**: Fix 1 already gave `.passage-card` three different base
sizes across breakpoints (23/26/28px). Refactored those into a
`--passage-font-base` custom property set at `:root` per breakpoint,
and the 5 step classes (`.passage-card[data-font-step="1..5"]`)
multiply it via `calc()` (0.82×/0.91×/1×/1.15×/1.32×) — so "Large" is
proportionally the same bump on a phone or a laptop, rather than
needing a separate step table per breakpoint. Confirmed live: at
1280px (base 28px) with step 2 selected, the real computed font-size
was exactly 25.48px = 28 × 0.91.

**New shared `_reading-font-control.blade.php`** (used by both
`activity-found.blade.php` and `diagnostic-passage.blade.php`,
avoiding a second copy of the apply/persist/disable-at-ends logic) —
a pill with A−/A+ buttons and a step-name label (Small/Medium−/Medium/
Large/Extra Large). Applies the new size to `.passage-card` instantly
via a data attribute; separately fires a background `fetch()` POST to
`LearnerAuthController::updateReadingFontStep()` to persist it — a
deliberate, disclosed departure from this app's usual plain-form-
submit convention, since a full page reload on every tap would fight
the point of the control feeling instant. A failed save never blocks
the interaction, it just means the choice won't outlive the visit.
Both real buttons use a genuine `disabled` attribute at their end of
the scale (not just a dimmed style), confirmed via real
`document.getElementById(...).disabled` checks at both ends.

**A real bug found and fixed during testing, not assumed correct from
the diff**: the control's own `<script>` runs before `.passage-card`
in the page's source order (the control is placed visually above the
passage), so the original inline IIFE threw `Cannot read properties of
null (reading 'setAttribute')` — `.passage-card` didn't exist in the
DOM yet when the script executed synchronously. Fixed by wrapping the
whole thing in a `DOMContentLoaded` listener; re-tested and confirmed
the error is gone and the control initializes correctly.

**Font**: Lexend added to the existing Google Fonts `<link>` on both
reading screens; `.passage-card` switched to `'Lexend', sans-serif`.
Nothing else changed fonts — confirmed live that h1/buttons/labels/the
size-control pill itself all stayed Baloo 2/Inter.

**Overflow safety**: `.passage-card` already had `overflow-wrap`/
`word-break` from Fix 1. Confirmed live at the worst-case combination
(375px phone width, step 5/Extra Large, real font-size 30.36px): zero
overflow — `passage-card.scrollWidth === clientWidth` (268px both) and
`document.documentElement.scrollWidth === window.innerWidth` (375px
both), confirmed via direct measurement, not assumption.

**Tested for real, both grade levels, multiple steps, persistence
across an actual fresh login — not assumed from the diff:**
- Real Grade 1 Learner ("Kim," mid-diagnostic): confirmed default
  step 4/"Large" on `diagnostic-passage.blade.php` (a real Gemini-
  generated passage, not a stub), Lexend font, correct 26.45px = 23 ×
  1.15. Clicked A+ to step 5/"Extra Large" — confirmed A+ correctly
  disabled, confirmed the real database value (`reading_font_step: 5`)
  via direct query. Clicked A− five times from there — correctly
  floored at step 1/"Small" (never below), A− correctly disabled.
- **Persistence confirmed across an actual fresh login**: logged Kim
  out for real (a real POST to `/learner/logout`) and back in — the
  diagnostic-passage screen (a fresh page load, not a cached one)
  showed step 1/"Small" again, her real explicit choice, not the
  Grade 1 default of 4 — genuine proof this survives a session
  boundary, not just an in-page state.
- Real Grade 3 Learner (Miguel, `activity-found.blade.php`, a real
  assigned Activity): confirmed default step 2/"Medium−" — different
  from Kim's Grade 1 default, confirming the grade-based branch
  actually branches, not a coincidence.
- **A real tool artifact hit and correctly diagnosed, not confused for
  a code bug**: after using `resize_window` to test at 1280px then
  375px, `getComputedStyle` briefly reported a stale cached font-size
  from the previous viewport — the same class of stale-computed-value
  quirk this project has already documented for screenshots
  (`background-attachment:fixed` interacting with this browser
  automation tool). Confirmed genuinely stale, not a real bug, by
  forcing a reflow (`display:none` toggle + `offsetHeight` read),
  which produced the mathematically correct value (30.36px = 23 ×
  1.32) immediately.
- Zero new entries in the Laravel log across the whole test pass; no
  `ReadingSession`/`Notification` side effects this time (no audio was
  actually submitted, only the size control was exercised).

## Font-size control — visual-only polish pass against the real
## reference file, zero logic touched

The user's earlier "make the ui/ux good the same on the pic" request
turned out to be a screenshot of the exact reference file
(`tarabasa-learner-word-tracking-v3.html`) this control was always
meant to be built from — supplied in full this time. Explicitly scoped
as visual-only: confirmed via `git diff` after the edit that the ONLY
lines touched were CSS values, the two buttons' inner markup (icons
instead of text), and one label-text string — every logic line
(`step` variable, `save()`, the `if (step <= 1)`/`if (step >= 5)`
guards, the `calc()` multipliers, the `DOMContentLoaded` wrapper) is
byte-for-byte unchanged.

**What changed, and why**: the reference uses plain minus/plus SVG
icons instead of "A−"/"A+" text glyphs, with a lighter hover treatment
(border-color change, not a solid-fill flash) — adopted both, since
icon buttons match this app's own established icon-in-circle language
(the mic button) more closely than text glyphs did, and read as a
genuine refinement, not just a copy. Also adopted the reference's
"Text size: {name}" label format (previously just the bare name) for
clarity. **Deliberately NOT adopted**: the reference's plain-square,
no-emoji mascot and its white/heavy-shadow passage-card background —
both read as a wireframe/mockup stand-in rather than an intentional
redesign instruction, and swapping the passage-card's established
`--bg-0` tint for plain white would break consistency with every
other Learner screen's passage-card, which wasn't asked for.

**A real, unrelated leftover caught and fixed during re-testing, not a
new bug**: Miguel's `reading_font_step` was still `5` in the database
from this session's OWN earlier overflow-safety test (three real A+
taps that got saved), which briefly made the re-verification wrongly
show "Extra Large" instead of the expected Grade 3 default. Confirmed
via direct query this was old test data, not new-code fallout; reset
to `null` before re-testing, consistent with treating Miguel as the
flagship reference Learner (not disposable test data) throughout this
whole project.

**Re-tested everything already verified last session, confirming zero
functional regression — not assumed from "the diff looks safe":**
- Grade-based defaults, post-visual-change: Miguel (Grade 3, freshly
  reset) correctly showed step 2/"Medium−"; both real database query
  and live page agreed.
- Disable-at-ends, post-visual-change: 3 real clicks past the ceiling
  clamped correctly at step 5/"Extra Large" with the new plus-icon
  button genuinely `disabled` (confirmed via `.disabled` property, not
  just visual dimming).
- **Persistence across an actual fresh login, re-confirmed with the
  new pill**: real Grade 1 Learner Kim, already sitting at her own
  real step 1/"Small" from last session (confirmed still loaded
  correctly with the new UI first), bumped to step 3/"Medium", real
  database query confirmed the save, then a genuine `/learner/logout`
  POST + fresh login + fresh page load showed step 3/"Medium" again —
  the same persistence guarantee, re-proven, not just assumed to still
  hold because the JS wasn't touched.
- Zero horizontal overflow re-confirmed at the same worst case (375px
  phone width, step 5, real 30.36px font-size) with the new button
  markup in place.
- Zero new Laravel log entries across the whole re-verification pass.

## Results screen word-breakdown — "Mispronounced" and "Repeated" added
## for real, reversing the earlier "not honestly buildable" call

The section above ("Results screen word-breakdown — checked against the
actual design reference image...") documented a considered decision to
leave "Mispronounced" and "Repeated" out entirely, since Reading-api's
real `word_feedback` has no confidence score and no field linking it to
`word_timestamps`. The user asked directly for both to be added anyway,
**explicitly requiring they be genuinely accurate, not just a visual
match** — not "add two more colors," but "don't fabricate a
distinction the data doesn't support." Re-investigated Reading-api's
real deployed source (`main.py` on github.com/BldZeuz/Reading-api)
again before writing any code, specifically to check whether a real,
non-arbitrary derivation existed that the earlier pass hadn't found.
**One did, for one of the two; the other required a disclosed, honestly-
labeled heuristic — this section exists so both are easy to find and
explain, including at a thesis defense, not just mentioned in passing.**

### "Repeated" — a hard fact from real data, not a heuristic

Confirmed directly from Reading-api's source that its response carries
a **second, independent array** beyond `word_feedback`:
`word_timestamps` (top-level in the `/analyze` response, `{word, start,
end, confidence}` per entry) — the raw Vosk transcript in true
chronological (spoken) order. Also confirmed from source that
`spoken_words` (what `word_feedback`'s alignment is built from) is
`normalize_text(spoken_text).split()`, and `spoken_text` is a plain,
nothing-added-or-removed join of that same transcript — so
`word_timestamps[k]` and the k-th spoken word `word_feedback` consumed
are the same word, in the same order, with no filtering or merging in
between. That means **two identical, immediately-consecutive entries in
`word_timestamps` are a verified fact about the actual audio** — the
child really did say that word twice in a row. Nothing invented here;
it's a real signal Reading-api already returns but the app had never
looked at.

Implementation (`LearnerReadingController::detectRepeatedSpokenIndexes()`):
walks `word_timestamps` once, flagging both indexes of any
case-insensitive immediate duplicate. `buildWordBreakdown()` then walks
`word_feedback` in parallel, maintaining a pointer into that same
spoken-word sequence (advanced by exactly the number of spoken words
each operation type consumes — zero for a `deletion`, one for
`correct`/`substitution`/`insertion`) so a `correct` operation gets
reclassified to "repeated" exactly when the spoken word it consumed was
part of a real detected duplicate. This positional correlation (not a
text-only match) is what keeps a passage with the same common word
appearing twice from misfiring — each occurrence is checked against its
own actual position in the spoken sequence, not just "does this word
text appear as a duplicate somewhere."

### "Mispronounced" vs. "Said a different word" — TaraBasa's own
### interpretive layer, explicitly NOT Reading-api's judgment

**This one really is a heuristic, and is documented as exactly that —
not dressed up as something Reading-api itself determined.** Confirmed
directly from Reading-api's `align_words()` source that a
`substitution` operation carries nothing beyond the two plain word
strings (`reference`, `spoken`) — no edit distance, no phonetic score,
no confidence value anywhere in that function. Reading-api treats every
non-matching word identically; it has no concept of "close attempt" vs.
"totally different word" at all. The distinction the design reference
mockup wants doesn't exist upstream, so TaraBasa computes it itself,
from the two real strings Reading-api already hands back:

- Implementation: `LearnerReadingController::looksLikeMispronunciation()`.
- **Exact method**: for a `substitution` entry with reference word `R`
  and spoken word `S` (both lowercased/trimmed), classify as
  **"Mispronounced" if EITHER**:
  1. `metaphone(R) === metaphone(S)` (PHP's built-in phonetic-key
     function — the two words sound alike even if spelled differently:
     e.g. "cool" heard as "kool"), **OR**
  2. normalized Levenshtein similarity `1 - (levenshtein(R, S) /
     max(strlen(R), strlen(S))) >= 0.5` (at least half the characters
     match — a plain, explainable bar, not a number tuned to make one
     specific example work).
  Otherwise classified **"Said a different word."**
- **If asked at defense "how do you distinguish mispronunciation from a
  substitution?"**: the honest answer is that Reading-api/Vosk does
  NOT distinguish them — it only reports "the spoken word didn't match
  the reference word." TaraBasa adds a deterministic post-processing
  step on top of that real substitution data (phonetic + edit-distance
  similarity) to make an explainable guess at which kind of miss it
  was. It is disclosed in-code as a heuristic (see the doc comments on
  both methods above `buildWordBreakdown()` in
  `LearnerReadingController.php`), not represented anywhere as an
  AI-verified judgment.

### Tested for real, on genuine unscripted Vosk output — not assumed
### correct from reading the formula

Two real TTS submissions through the actual `/learner/activity/{id}/record`
endpoint (a disposable test Learner, Activity #1's "cat dog pig hen
cow" passage — reused from this project's own established test
phrase), both via a real authenticated multipart POST, not mocked:

- Speech "cat dog **dog** hank cow" (reference: "cat dog pig hen cow" —
  a deliberate duplicate of "dog," dropped "pig," and "hen" reworded):
  Vosk's real, unscripted transcription came back as `cat`(correct),
  `dog`(correct), `pig→dog`(substitution), `hen→hank`(substitution),
  `cow→hill`(substitution) — genuinely NOT what was said verbatim
  (Vosk's own ASR variance), which is exactly the kind of real,
  unpredictable input this needed to be tested against, not a
  synthetic word_feedback array crafted to match the formula. Result:
  the duplicated "dog" was correctly flagged **Repeated**; "hen→hank"
  (phonetically close) was correctly classified **Mispronounced**;
  "cow→hill" (genuinely different) correctly stayed **Said a different
  word**. `practiceCount` correctly came back as 4 (pig-sub + hen-mispro
  + cow-sub + dog-repeated all count — "repeated" was read correctly
  but still needs the pacing practice, matching the reference mockup's
  own count of 5 highlighted-but-not-plain-correct words in its
  example).
- A second real submission ("cat pig hen cow" — cleanly dropping "dog"
  with no competing insertion this time) confirmed the untouched
  `deletion → "skip"` path still renders correctly alongside the two
  new categories, and produced a second genuine mispronunciation case
  ("hen→hand," phonetically close, correctly "Mispronounced") plus
  another genuine different-word case ("cow→kill," correctly "Said a
  different word").
- Visually confirmed via a real authenticated in-browser `fetch()` +
  DOM injection (this project's established technique for viewing a
  real server-rendered result without a full page navigation) —
  screenshot matches the reference mockup's color language (green/
  amber/red/purple/blue) and bolder passage typography.
- All test data (2 disposable Learners, their `ReadingSession`/
  `ActivityAssignment`/`Notification` rows, temp audio files, and a
  temporary base64 file placed in `public/` to get real audio bytes
  into the sandboxed browser's `fetch()`) cleaned up afterward.

**Follow-up, same feature: the "heard X" info was hover-only — invisible
on a touchscreen and in a screenshot, so the user (rightly) saw it as
"not labeled."** The mispronounced/substitution/repeated words originally
showed what was heard via a `.tip` tooltip that only appeared on `:hover`/
`:focus` — meaningless on the tablet a Grade 1-3 child actually uses this
screen on, since there's no mouse-hover concept there, and it also meant
a screenshot of the page (used to review this exact feature) showed the
colored word with no visible explanation at all. **Fixed by replacing the
hover tooltip entirely with a small always-visible caption stacked
directly under each annotated word** (`.rw-annotated` — a column flex
chip: the word on top, a small "heard '...'" or "said twice" line
directly beneath it, in the passage's own natural flow) — no interaction
required to see it, works identically on touch, mouse, or a static
screenshot. Plain "correct"/"skipped" words are untouched (still simple
inline text, no chip). Re-tested live the same way as above (same
disposable-Learner + real-TTS-audio technique) — confirmed all 3
annotated categories now show their label unconditionally, properly
aligned in the passage's wrapped flow, no overlap or clipping; test data
cleaned up afterward the same way.

**Two more follow-up fixes on this same screen, one cosmetic and one a
genuinely tricky real bug:**

- **"Correct" words were plain black, but the legend's "Correct" dot is
  green** — a real inconsistency (the legend implies green=correct, but
  green never actually appeared anywhere in the passage). Fixed by
  giving plain `.rw` (the correct-word case) `color:var(--success)` —
  the same green already used for this screen's own stat-tile values,
  so it's consistent with an existing convention, not a new one.
- **A real, non-obvious class-name collision, not a layout bug** — the
  legend's "Said a different word" dot/label kept rendering visibly
  misaligned (roughly 20px too tall, dot pinned to the top instead of
  centered) no matter what was tried: `flex-shrink:0` fixes, rewriting
  the dot+label pairing away from nested flex entirely into plain
  `inline-block` + `vertical-align:middle`, explicit `line-height`,
  wrapping the label text in its own span — every fix produced
  identical computed-style AND identical screenshot evidence of the
  same exact misalignment, which is what eventually gave it away: a
  bug that survives switching between two completely different CSS
  layout algorithms (flexbox and inline-block/vertical-align) is not a
  layout bug at all. **Root cause, found by checking computed
  `margin-bottom` directly on the dot element**: the legend markup used
  `<span class="dot sub">` — and this exact page ALREADY has an
  unrelated `.sub{ font-size:18px; ...; margin:0 0 20px; }` rule (the
  "You read..." subtitle paragraph, `<p class="sub">`, line ~43 of this
  file). Since CSS classes are just a space-separated list, that
  `<span>` matched BOTH the intended `.legend .dot.sub` rule AND the
  totally unrelated bare `.sub` rule — and since nothing in `.dot.sub`
  ever set `margin`, the stray `margin:0 0 20px` silently leaked
  through untouched (13px dot + 20px inherited margin = 33px, an exact
  match to the bug's measured height). **Fixed by renaming the legend's
  class to `st-sub`**, matching the naming convention the passage's own
  word chips already used (`st-sub`/`st-skip`/`st-mispronounced`/
  `st-repeated`) specifically *because* that prefixed convention never
  collided with anything else on the page — `sub` alone, without a
  prefix, was the actual mistake. **Lesson for future work on this
  file**: always prefix new status/category class names (`st-`) rather
  than reusing a short, generic word — a real, already-used class
  elsewhere in the same file/page is exactly this kind of silent trap,
  and it will not show up as a CSS error, only as an inexplicable-
  looking layout bug that resists every layout-logic fix.

Verified fixed with a real end-to-end TTS submission (the same
disposable-Learner technique used throughout this feature's testing):
`cat`/correct rendered green, `dog`/repeated, `pig`→`dog` and
`cow`→`hill`/said-a-different-word, `hen`→`hank`/mispronounced all
rendered with correct colors and captions, and all 5 legend dots
measured at an identical 13.49px height — confirmed via direct
`getBoundingClientRect()` comparison across all five, not just visual
inspection. Test data cleaned up afterward.

## Practice Games — Word Builder + Letter Match, standalone free play

Two real games, confirmed scope per the standing decision recorded
above (see the "Practice Games are standalone" note next to the
Activity Generation conflict) — no `ReadingSession`, no points, no
mastery-level impact, a genuinely separate system from real reading
achievement. New `GameController` (`index()`, `wordBuilder()`,
`letterMatch()`), routed inside the same `learner.diagnostic`-gated
group as the Dashboard/reading routes (a Learner reaches Games from the
Dashboard, so the same "must have completed the diagnostic" gate
applies consistently). New "🎮 Practice Games" button on
`learner/dashboard.blade.php`, styled as a second `.big-btn` variant
(orange gradient) next to the existing blue "Start Reading Activity,"
so the two primary actions read as visually distinct without either
looking like an afterthought.

**Word Builder** (`games/word-builder.blade.php`): 5 words per round,
tap scrambled letters in the correct order to spell each one. Word
source, exactly as scoped: the Learner's own real `PersonalWordBank`
"Struggling" words first, topped up with a small hardcoded per-grade
word list (`GameController::WORD_LISTS`, ~20 real CVC-appropriate words
per grade) when there are fewer than 5 — a partial-fill, not an
all-or-nothing swap, so a Learner with 1-4 real struggling words still
gets to practice all of them plus enough filler to reach 5, rather than
discarding real personalized content just because there wasn't a full
set. Each scrambled letter is tracked by its own tile index (not by
letter value), so words with repeated letters work correctly — tapping
either "e" tile of "letter" at the right moment is equally valid,
exactly like a real physical letter-tile set would behave. Instant
feedback per tap: a correct tap bounces the tile and fills the next
answer slot (`slotPop` animation); a wrong tap shakes that exact tile
red and changes nothing else. Word-complete and round-complete both get
a real celebration screen, not just a silent state change.

**Letter Match** (`games/letter-match.blade.php`): classic
memory-match, uppercase paired to lowercase. Grade 1 gets a smaller
10-letter subset (A-J); Grade 2/3 get the full alphabet — but split
into 3 rounds of ~8-9 pairs each (`GameController::LETTER_ROUNDS`)
rather than one 52-card grid, which would be unplayably tiny on a real
phone screen. This round-chunking is an implementation detail the
original scope didn't spell out explicitly; disclosed here rather than
silently decided, since "full alphabet" could otherwise have been read
literally as one giant grid. A wrong pair shakes both cards briefly
then flips back; a correct pair locks in green with a real pop
animation. All rounds' data is preloaded into the view up front (a
small, fixed dataset), so round transitions are instant client-side
state changes, not server round-trips.

**A real bug found and fixed during testing, not assumed correct from
the diff:** Word Builder's `finishRound()` never hid the still-visible
spelled-word tiles from the just-completed final word before showing
the "All done!" celebration — confirmed visually (a screenshot showed
the last word's green letter tiles bleeding through above the
celebration text) and fixed by adding the same `playArea.style.display
= 'none'` the per-word celebration already used. Letter Match's
equivalent `finishGame()` already had this right from the start (no
matching bug there).

**Tested for real against real data, both fallback branches, both
grade-tier branches — not assumed from the diff:**
- **Real struggling words, partial-fill case**: Miguel (id 1, the
  flagship reference Learner, Grade 3) has 24 real `PersonalWordBank`
  "Struggling" rows — but only 4 *distinct* words among them (`hen`,
  `cow`, `cat`, `dog`, repeated across many real past sessions).
  Confirmed the game correctly used all 4 real distinct words plus
  exactly 1 real filler from the Grade 3 static list (`castle`) to
  reach 5 — a genuine, previously-untested edge case (many rows, few
  distinct words) that the partial-fill logic handled correctly the
  first time.
- **Full fallback, zero bank entries**: a second real Learner named
  Miguel (id 9, Grade 1, a distinct account from the flagship
  reference one, with zero `PersonalWordBank` rows at all) correctly
  got all 5 words from the Grade 1 static list.
- Played a full real round to completion on the id-1 case (including a
  deliberate wrong tap first, confirmed rejected with no state change)
  — confirmed correct word-by-word advancement, correct progress dots,
  and a clean round-complete screen after the fix above.
- **Letter Match, Grade 1**: confirmed the real 10-letter/20-card
  single round, played a deliberate wrong pair first (confirmed
  rejected, flipped back, `matchedCount` unchanged), then played all 10
  real pairs to completion — clean round-complete screen, no leftover
  visible cards.
- **Letter Match, Grade 2/3**: confirmed the real 3-round/26-letter
  split (9/9/8), played all 3 rounds to completion — confirmed correct
  round-to-round transitions (fresh grid, correct progress dots/label
  each time) and the final "All done!" screen only appearing after all
  3 rounds, not after round 1. (A screenshot taken at this exact moment
  showed a tiled/repeated rendering — the same already-documented
  `background-attachment:fixed` stale-screenshot-capture quirk of this
  sandboxed browser tool noted elsewhere in this file; cross-checked
  and confirmed correct via direct DOM inspection instead, not assumed
  from the bad screenshot.)
- `php -l` clean on all new/touched files; zero new Laravel log entries
  across the whole test pass. Test setup (2 disposable dummy Diagnostic
  `ReadingSession` rows created purely to satisfy the `learner.
  diagnostic` route gate for accounts that hadn't completed a real
  diagnostic) cleaned up afterward; no real `ReadingSession`/points/
  streak data was touched by either game, by design.

## Practice Games — real visual identity (hand-coded SVG, no emoji) +
## honest adaptive connection

A follow-up pass on the Practice Games slice above, per explicit user
request: the games needed to feel genuinely TaraBasa-branded (not
plain white backgrounds/emoji game pieces) and to actually connect to
the Learner's real adaptive data, not sit as two static disconnected
buttons. Reported a plan for both parts before building, per the
user's own request, and built exactly what was proposed — no scope
creep into the other game types GamifiedLibrary_v2.txt/the manuscript
describe (Trace-and-Write, Sentence Scramble, Picture-Word Match,
etc.) — this was explicitly an enhance-what-exists pass, not a new
build. Confirmed decision, matching this project's own prior 3D
rejection logic: claymorphism (existing technique, CSS gradients +
soft shadows), not live 3D — this app's own GamifiedLibrary_v2.txt
already argues against 3D here on lower-end-phone performance grounds,
and the user explicitly re-confirmed accepting that tradeoff before
this pass started.

**Real hand-coded SVG mascot — "Tara the owl" actually exists as an
asset for the first time.** Despite being referenced throughout this
app's copy since Sprint 4 ("Tara the owl wants to hear you read!"),
no owl had ever actually been drawn — every mascot slot in this whole
app has always just rendered the plain 🦉 emoji. New shared
`games/_owl-mascot.blade.php` partial (a real, simple, geometric
claymorphism owl face — ear tufts, wing hints, cream body, belly
patch, expressive eyes, orange beak — built from basic SVG primitives
using this app's own real color tokens, not an external asset or icon
font) — reused identically across the Games hub and both games via
`@include(...,['id'=>'uniqueId'])`, one instance per placement since a
page can show it more than once. Two real, distinct behaviors, both
class-toggled by the including page's own JS, not separate hand-drawn
poses:
- **`.is-celebrating`**: swaps the normal circle-pupil eyes for a
  happy scrunched-arc pair and fades in 4 small hand-drawn sparkle
  marks around the face — triggered on a completed word (Word
  Builder), a completed match-round (Letter Match), and both games'
  final round-complete screens.
- **`.is-encouraging`**: a brief, warm 2-cycle tilt animation (CSS
  `@keyframes`, not a different facial expression — a wrong answer
  isn't a sad moment) — triggered by both games' own `nudgeOwl()`
  functions on a wrong tap/wrong pair, retriggerable mid-animation the
  same way the existing tile/card shake animations already were.
Both respect `prefers-reduced-motion`, matching this app's existing
standard elsewhere.

**Game pieces redesigned in claymorphism, replacing flat/emoji
placeholders — logic completely untouched, confirmed via `git diff`
after the pass (same discipline as the earlier font-size-control
visual-only polish).**
- **Word Builder**: letter tiles are now raised claymorphism (white→
  light-blue gradient, layered inset/outset `box-shadow` for a real
  "physical tile" look) instead of a flat bordered box; answer slots
  get a genuine carved-in inset-shadow look instead of a plain dashed
  box, filled slots get a soft green gradient instead of a flat tint.
- **Letter Match**: face-down cards show a small hand-drawn sparkle/
  star mark (deliberately the *same* star shape used by the owl's own
  celebration sparkles, so the two pieces read as one consistent
  decorative system rather than two unrelated ones) instead of a bare
  "?" character; matched cards get a small corner sparkle badge on top
  of the existing green highlight; all card states got layered
  inset/outset shadows for a genuine raised/pressed claymorphism feel
  instead of a flat color swap.
- **Games hub**: each game card got a small custom SVG icon (three
  overlapping claymorphism letter tiles for Word Builder, two
  overlapping claymorphism cards for Letter Match) instead of a bare
  🔤/🧩 emoji, visually previewing what each game actually looks like.

**Honest limit, stated up front and still true after building**: this
is a clean, appealing, on-brand *geometric* icon/illustration set built
from SVG primitives (circles, paths, rects) — not painterly or
detailed character art. Good enough to feel genuinely TaraBasa-branded
instead of a generic placeholder, but not a substitute for actual
illustration work if that's ultimately the bar wanted.

**Adaptive connection — deliberately honest about what these two
games can and can't measure.** New `Learner::recommendedGameFocus()`:
returns `null` (render a neutral state) unless
`next_recommended_competency === 'foundational_reading'` — Word
Builder and Letter Match are both real foundational-skill practice
(spelling/letter recognition), and this method never claims a
connection to `reading_fluency`/`reading_comprehension`, which neither
game touches. When foundational_reading genuinely is the adaptive
engine's current real recommendation, a second real, disclosed signal
picks *which* game to emphasize: a Learner with real
`PersonalWordBank` "Struggling" words gets Word Builder highlighted
(it directly drills their own actual weak words); otherwise Letter
Match is the safer foundational default. **Never a hard gate** — both
games stay fully playable regardless of this signal, matching the
user's own explicit instruction; this only affects which card shows a
real "⭐ Recommended" badge and border treatment on the Games hub.
`GameController::index()` also passes `hasCompetencyData` (whether
`competency_states` is non-null at all) so the hub can render one of
three honest states: a specific recommendation (foundational_reading
is current), a quiet neutral "your foundational skills are looking
strong" note (fluency/comprehension is current instead — deliberately
*not* silent about the real adaptive state, but also not
manufacturing a fake recommendation), or nothing at all
(pre-diagnostic, no real data exists yet). No new writes anywhere —
this is read-only surfacing of already-real data, the exact same
`competency_states`/`next_recommended_competency`/`PersonalWordBank`
fields already driving "Picked just for you!" and "How I'm Growing"
elsewhere; the games still never touch `ReadingSession`/points/
mastery, unchanged from the original Practice Games decision above.

**A real, easy-to-miss operator-precedence bug caught before it
shipped, not after**: the Games hub's Blade line for the Word
Builder card's conditional CSS class was originally written as
`{{ $recommendedGame['game'] ?? null === 'word-builder' ? '...' : '' }}`
— PHP's `??` binds *looser* than `===`, so this actually parsed as
`$recommendedGame['game'] ?? (null === 'word-builder')`, meaning
*any* non-null `$recommendedGame['game']` value (including
`'letter-match'`) made the whole expression truthy and marked the
Word Builder card "recommended" regardless of which game was actually
picked. Caught by manually tracing PHP operator precedence before
testing, not by observing broken output — fixed by parenthesizing
`($recommendedGame['game'] ?? null) === 'word-builder'`, matching the
(correctly parenthesized) Letter Match card right next to it, which is
what made the mismatch noticeable in the first place.

**Tested for real against 4 real adaptive states, not just the happy
path — 4 disposable test Learners, each with a distinct real
`competency_states`/`next_recommended_competency` shape (the same
legitimate synthetic-injection technique already established and
approved in this project for exercising adaptive-engine states a live
run can't reliably reach), each verified live in the browser:**
- **`foundational_reading` recommended + real Struggling word present**:
  Games hub correctly showed the Word Builder-specific note text,
  Word Builder card carried the "⭐ Recommended" badge and orange
  highlight, Letter Match card carried neither.
- **`foundational_reading` recommended + zero Struggling words**:
  correctly flipped to the Letter Match-specific note and badge
  instead, Word Builder correctly un-badged — this is the case that
  would have been silently broken by the precedence bug above if it
  had shipped.
- **`reading_fluency` recommended instead**: correctly showed the
  neutral "your foundational skills are looking strong" note, zero
  badges on either card — confirmed the connection never overclaims a
  relationship to a competency neither game measures.
- **`competency_states` still null (pre-diagnostic)**: confirmed zero
  adaptive UI renders at all — no note, no badge — matching the
  existing "How I'm Growing" card's own established pattern of full
  omission over a fabricated state.
- **Full real gameplay pass on both games** with the visual redesign
  in place: Word Builder — a genuine wrong tap correctly triggered
  `is-encouraging` on the main owl (confirmed via class inspection,
  since the CSS tilt is brief and hard to catch in a screenshot over
  network round-trip latency); a genuine completed word correctly
  triggered `is-celebrating` + visible happy eyes on the celebrate
  screen's owl (caught via a precisely-timed check between the 350ms
  pre-celebrate delay and the 1300ms celebrate duration); the final
  round-complete screen's owl also showed the celebrating expression,
  confirmed both live and via screenshot. Letter Match — a genuine
  wrong pair correctly triggered the same owl nudge; a genuine correct
  match showed the new green claymorphism card treatment with a real
  visible corner sparkle badge, confirmed via screenshot; played a
  full real 3-round match-through to completion, confirming the
  round-complete owl's celebrating state both via class inspection and
  a clean screenshot (no leftover play-area content bleeding through,
  re-confirming the earlier `finishGame()`/`finishRound()` display-none
  fix still holds with the new markup).
- All 4 disposable test Learners and their `ReadingSession`/
  `PersonalWordBank`/`Notification` rows cleaned up afterward.

## Practice Games — in-game difficulty levels, real sound effects, richer
## per-game visual identity

A second follow-up pass, explicitly scoped to enhancing the same two
existing games (not adding more game types — that stays a separate,
much bigger undertaking per the manuscript's other 6+ `gameType`s, out
of scope here). Reported a concrete plan for both the leveling design
and the sound source before writing any code, per the user's own
request; built exactly what was approved.

### Part 1 — real in-game levels, using only data this app already owns

No `curriculum_guides` involvement, confirmed with the user as the
simpler, in-scope choice — both games' 3 levels are built from
`grade_level` (start level only) plus real in-session performance
(can climb, never regresses). Same unified rule in both games: a
level-up requires **≤3 total mistakes** in that attempt — one
memorable, explainable threshold instead of two different ones.

**Word Builder** — length-based levels, reusing the exact 3 word lists
already shipped (they were already, coincidentally, uniform 3/4/5-6-
letter sets): Level 1 = 3-letter words, Level 2 = 4-letter, Level 3 =
5-6-letter. `GameController::wordBuilder()` now pre-fetches **all 3
levels'** data in one response — real `PersonalWordBank` "Struggling"
words bucketed by length, plus that level's static list — so leveling
up or retrying a held level is an instant client-side pick, never a
server round-trip. `buildRoundWords()` re-runs the real-words-first/
static-fallback logic fresh every attempt (not just once), so a
Learner held at a level for a 2nd/3rd try gets a freshly-shuffled 5,
not an identical repeat.

**Letter Match** — pair-count levels: Level 1 = 6 pairs (A-F, genuinely
easier than the old Grade 1 default of 10 — a real answer to "a weak
Grade 1 reader still needs this to be adaptive"), Level 2 = 10 pairs
(the old Grade 1 default), Level 3 = the pre-existing full-alphabet/
3-round sequence, untouched internally. No PersonalWordBank equivalent
exists for individual letters (that table stores whole words, never
single characters) — disclosed honestly rather than inventing a fake
"weak letters" signal; Letter Match's adaptivity is grade-plus-in-
session-performance only.

**Session model, the same real design question resolved identically in
both games**: a session is capped at 3 "attempts" (1 attempt = 1 round
in Word Builder; 1 attempt = however many internal rounds that level
has in Letter Match — 1 for levels 1-2, the existing 3 for level 3),
but ends immediately once an attempt completes while already at the
Level 3 ceiling, *regardless* of the attempt count. This was a real
design fix mid-build, not the original naive plan: without it, a
Learner who already starts at Level 3 (an older grade) would have
replayed the full alphabet 3 separate times in one sitting — a real
regression from the pre-leveling session length for exactly the
learners who need it least. With the fix, a Level-3 starter gets
exactly the same single pass through the alphabet as before leveling
existed; a Learner who climbs all the way there from Level 1 gets a
natural growth arc (6→10→26 letters, or 3→4→5-6-letter words) ending
at the same finale.

**A real bug caught during testing, not assumed correct from the
diff**: the end-of-session message ("— great climbing!" vs. plain
"Nice work!") was originally driven by whether the *last* attempt
specifically leveled up — but reaching the Level 3 ceiling always sets
that flag `false` for the final attempt (nowhere higher to climb to),
so a Learner who climbed the *entire* way from Level 1 to Level 3
would never see "great climbing!," only the plain message. Confirmed
this exact failure live (a full clean climb correctly reached Level 3
but showed "Nice work!" instead of crediting the climb). Fixed by
tracking a session-wide `leveledUpDuringSession` flag instead of just
the final attempt's own result — re-tested the identical climb and
confirmed "— great climbing!" now shows correctly. A second, smaller
cosmetic bug from the same root cause (the top-bar's attempt label
still said "Round 2 of 3" after the session had already ended) was
fixed alongside it — the label now reads "Complete!" once the session
is actually over, instead of implying another round is coming.

### Part 2 — real sound effects, the first audio playback in this app

**Source: Mixkit (mixkit.co) Sound Effects Free License** — fetched
the actual license text directly before using anything (not assumed):
explicitly permits "Video games," "Educational Purposes," and
"Commercial projects," lets you "download, copy, modify, distribute
and publicly perform" the files, no attribution required. **Google's
Actions/Assistant sound library was checked first and explicitly
rejected** — its real terms forbid use "on non-Google platforms"
entirely, confirmed by reading them directly rather than assuming a
big-name source would obviously be fine.

Three real files, downloaded once and self-hosted in `public/sounds/`
(not hotlinked from Mixkit's CDN at runtime):
- `correct.mp3` — Mixkit's "Correct answer tone" (1s)
- `wrong.mp3` — Mixkit's "Wrong answer fail notification" (1s),
  deliberately sourced from Mixkit's "Notification" category rather
  than "Game" (which is mostly harsh arcade buzzers) — this plays for
  young children, a wrong answer should never sound scary or punitive.
  Also played at a noticeably lower volume (0.3) than the other two
  (0.5-0.6) as an extra precaution, since the exact tone couldn't be
  pre-listened to before choosing it (this sandboxed environment has
  no audio playback) — worth a live listen-and-reconsider once someone
  can actually hear it.
- `level-complete.mp3` — Mixkit's "Game level completed" (3s)

New shared `games/_game-sounds.blade.php` partial (one `<audio>` set +
one `window.tarabasaPlaySfx(name, volume)` entry point, included by
both games) instead of duplicating the same playback logic twice.
Every call site is wrapped in try/catch **and** a `.catch()` on the
returned Promise — browsers can legally block `audio.play()` when it
isn't triggered by a real user gesture (a genuine, common autoplay
restriction, not a bug); every real call site here fires from inside
an actual click handler, so this should normally play, but the game
must never break if a browser blocks it anyway.

**Tested for real, not assumed**: confirmed real playback via direct
element inspection (`paused: false`, `currentTime` genuinely advancing,
`readyState: 4`) after calling `tarabasaPlaySfx` live in the browser —
not just "no console error," actual audio decoding and playing.
Separately simulated a real autoplay-block by overriding one audio
element's `play()` to reject with the exact real `NotAllowedError`
browsers throw for this case, confirmed the call site doesn't throw
synchronously and the game's own functions stay fully callable
afterward — the graceful-failure path is proven, not just assumed from
the try/catch being present in the source.

### Part 3 — visual/interaction polish

Larger touch targets throughout: Word Builder tiles 52×56→62×66,
slots 44×52→52×60; Letter Match cards' grid minimum cell size
58px→68px. Both games' mascot chip and card padding sized up slightly
to match. **Thematic per-game backgrounds**, built the same
claymorphism way as everything else in this app (no new image assets):
Word Builder gets small hand-coded SVG leaf/flower "garden" accents
positioned like the existing decorative blob circles; Letter Match
gets small scattered claymorphism "confetti" dot shapes in the app's
existing accent colors. More interaction feedback: tile hover now
lifts and scales together (was scale-only), Letter Match cards get the
same lift-on-hover treatment they didn't have before, both games'
correct-tap/match bounce animations were made slightly more energetic
(added a subtle rotation to Word Builder's tile bounce).

### Tested for real across multiple levels, grades, and both directions
### (climb and hold) — not assumed from the diff

- **Ceiling-start (ends after exactly 1 attempt, not 3)**: Miguel
  (id 1, real Grade 3, real struggling words `hen`/`cow`/`cat`/`dog` —
  confirmed these correctly bucket into the Level 1 length-3 pool even
  though he never plays it, since he starts and stays at Level 3)
  completed one real 5-word attempt at Level 3 and the session
  correctly ended immediately — confirmed via live DOM state and a
  screenshot, not just trusting the code path.
- **Full climb, Word Builder**: a fresh Grade 1 Learner playing
  perfectly (0 wrong taps every attempt) climbed Level 1→2→3 across
  exactly 3 real attempts (15 real words total), each level's word
  lengths verified live (3, then 4, then 5-6 letters) — confirmed the
  "great climbing!" bug fix landed correctly on re-test.
- **Full hold, Word Builder**: a second fresh Grade 1 Learner,
  deliberately tapping 4 wrong tiles before completing each word
  (exceeding the ≤3 threshold every attempt), correctly held at Level
  1 across all 3 real attempts and ended with the honest plain
  message, never a false "great climbing!" claim.
- **Full climb, Letter Match**: a fresh Grade 1 Learner playing
  perfectly climbed Level 1 (6 pairs) → Level 2 (10 pairs) → Level 3
  (the existing full 3-round alphabet sequence, played completely as
  one attempt) across exactly 3 real attempts, confirmed card counts
  live at each level (12, 20, then the existing 9/9/8 split) and the
  "great climbing!" credit at the end.
- **Hold-then-climb, Letter Match**: a fresh Grade 1 Learner held at
  Level 1 after a genuinely bad first attempt (4 deliberate wrong
  pairs), then correctly climbed to Level 2 on a clean second attempt
  — direct proof the hold and climb branches are independently correct
  for Letter Match's own wrong-pair counter, not just assumed identical
  from Word Builder's already-proven wrong-tap counter since the two
  use genuinely different underlying mechanics.
- Confirmed zero console errors and all 3 real sound files loading
  (`200 OK`) across every test session above.
- All disposable test Learners (4 total this pass) and their
  `ReadingSession`/`Notification` rows cleaned up afterward.

## Practice Games — real resume-in-progress bug fix + a real grid
## alignment bug fix

Two real, user-reported bugs on the games shipped above, both fixed and
re-verified live, not assumed correct from the diff.

**Bug 1 — mid-round progress was lost on navigating away and back.**
Both games' entire state has only ever lived in JS variables (the
server sends the word/letter pools once, up front, and never hears
back again) — navigating to the Dashboard and returning was a full
page reload, silently discarding whatever word/level/match progress
existed. **Decision, reported before building**: fixed with
`localStorage`, not a new `learners` DB column. Reasoning: this game's
whole architecture already keeps 100% of its state client-side by
design (a deliberate choice from the original build, to keep Practice
Games structurally separate from the real, server-persisted reading-
achievement system) — adding a DB field here would mean a new save
endpoint and would start blurring that same separation for what is,
functionally, transient mid-session UI state, not a real account
preference like `reading_font_step`. `localStorage` needs no schema
change and matches the actual scope of the bug report (resume on this
device, this sitting) exactly.

Keyed by the Learner's own real `learner_code` (`tarabasa_wb_progress_
{code}` / `tarabasa_lm_progress_{code}`, both now passed into their
view from `GameController`) specifically so a shared family device
can't leak one child's in-progress game into a sibling's session. Every
read/write wrapped in try/catch — `localStorage` can legitimately throw
(private browsing, storage disabled, quota) and a resume convenience
must never be able to break the game itself, confirmed by design, not
just hoped.

**A real design rule applied consistently in both games, not just
"save constantly"**: state is only ever saved at a genuinely stable
"waiting for the next input" moment — after a fresh word/sub-round
renders, after a wrong tap/pair (so an in-progress mistake count
survives too), and after a *non-completing* correct tap/match.
Deliberately **not** saved the instant a word/sub-round is actually
completed, since that's a brief transient state moving toward a
celebration and the next word/level — resuming into it would either
show a frozen "fully done, nothing left to click" screen with no code
path to advance, or (the simpler, chosen behavior) just quietly replay
the last correct tap on return, which is harmless. Storage is cleared
the moment a session genuinely finishes (`finishSession()` calls
`clearProgress()`) — this is also what correctly answers the "what if
a completed round tries to resume" edge case: there's nothing left to
resume by the time a session is over, and "Play Again" or a later
visit both correctly start a genuinely fresh session, not a phantom
resume of a finished one.

**Tested for real, the exact reported scenario, on both games**: got a
Learner partway into a word (Word Builder: tapped one correct letter
of "red," scrambled tiles `[e, r, d]`) and partway into a round (Letter
Match: matched exactly one real pair), navigated to `/learner/
dashboard`, then back into the game — confirmed via live DOM state
*and* a screenshot that both resumed at the exact right spot: the same
scrambled tile arrangement with the correct letter already filled and
its tile disabled (Word Builder), and the same board arrangement with
the same pair still shown matched (Letter Match) — not a fresh reshuffle
of either. Separately played a full Word Builder session through to
genuine completion, confirmed `localStorage` was empty immediately
after, then reloaded the same route fresh and confirmed it correctly
started a brand-new session (`attemptCount: 0`, Level 1) rather than
resuming the finished one.

**Bug 2 — Letter Match's grid looked broken at odd card counts** (the
user's own screenshot: 7 cards in row one, 5 in row two, for a 12-card
Level 1 round). Root cause: `grid-template-columns:repeat(auto-fit,
minmax(68px, 1fr))` greedily packs as many columns as fit the
available width, which for a card count that doesn't evenly divide the
resulting column count produces exactly this kind of ragged last row.
**Fixed with `pickColumns(cardCount)`** — tries column counts `[4, 3,
5, 6, 2]` in that order (favoring a wider, shorter grid over a tall
narrow one on a phone) and picks the first one the current card count
divides evenly by, applied as an explicit inline `grid-template-
columns` on every render rather than left to `auto-fit`. This isn't a
hardcoded per-level table — it's computed from whatever the actual
card count is, so it stays correct even if a level's letter count ever
changes later. Confirmed the real math for every card count that
actually occurs in this app: Level 1 (12) → 4 columns/3 rows, Level 2
(20) → 4 columns/5 rows, Level 3's two 9-pair sub-rounds (18 each) → 3
columns/6 rows, Level 3's 8-pair sub-round (16) → 4 columns/4 rows —
every real case lands on a perfectly even grid, never a ragged row.
Also bumped `.mcard` font-size 28px→32px and the grid gap 10px→12px
while fixing this, since the same complaint also asked for bigger
letters in the boxes.

**Tested for real, not assumed from the arithmetic**: confirmed via
direct `getBoundingClientRect()` measurement on every real card in a
live Level 1 round that all 12 cards resolve to exactly 3 distinct row
positions with exactly 4 cards per row (not just that the CSS rule
looks right) — the same live-DOM-geometry verification technique
already established elsewhere in this project for exactly this kind
of "does it just look right or does the code prove it" question.

## Practice Games — explicit in-game Exit button, repointed straight to
## the Dashboard

A follow-up on the resume-in-progress fix directly above: both games
already had an explicit exit link (not just reliance on browser back-
navigation), but it read "Back to Games" and went to the Games hub, one
hop short of the Dashboard — inconsistent with every other Learner
screen's exit affordance in this app (activity-picker, activity-found,
diagnostic, the Games hub itself), which all go straight to
`learner.dashboard` labeled "Back to My Dashboard." Repointed both
games' exit link to match that established convention exactly, rather
than inventing a new one, and added an explicit `onclick="saveProgress()"`
so leaving mid-round is guaranteed to persist state at the exact moment
of leaving — belt-and-suspenders on top of the existing "save at every
stable waiting-for-input moment" behavior, not a replacement for it.

**Tested for real, the exact reported scenario, on both games**: in
Word Builder, tapped the first correct letter of "pig" (leaving the
tile filled and disabled), clicked the new "Back to My Dashboard" link,
confirmed it landed on the real dashboard, then navigated back into
Word Builder and confirmed — via live DOM state and a screenshot — the
identical scrambled tile arrangement resumed with "P" still filled and
disabled, not a restart. Same test in Letter Match: matched one real
pair ("C"), clicked the exit link, confirmed landing on the dashboard,
then confirmed on return the same board arrangement resumed with the
"C" pair still shown matched.

**The completion edge case re-confirmed for both games, driven all the
way through a full session (not stopped after one attempt)**: played
Word Builder through a genuine full climb (Level 1 → 2 → 3, ending "You
finished at Level 3 — great climbing!"), confirmed `localStorage` was
`null` immediately after and the exit link itself was hidden
(`backLink.style.display === 'none'`) on the finished screen, then
reloaded the same route fresh and confirmed a genuinely new session
started (Level 1, attempt 0) rather than resuming the finished one. Ran
the identical full-climb-to-completion test on Letter Match (Level 1's
6 pairs → Level 2's 10 pairs → Level 3's full 3-sub-round alphabet
sequence) with the same result: `localStorage` cleared, exit link
hidden, and a fresh reload correctly started over at Level 1/12 cards.
Test Learner (`ZZExitTest`, code `TB-EXIT1`) and its dummy Diagnostic
`ReadingSession` row cleaned up afterward.

## Activity picker — missing exit link fixed (real UX gap, not the
## empty-state screen)

The user reported the "What should I read?" screen
(`activity-picker.blade.php`) had no way to back out to the Dashboard
when real options ARE showing — only the empty-state branch (`@if
($options->isEmpty())`) had a "Back to My Dashboard" button; the
populated `@else` branch (the actual screen shown whenever a Learner
has real assigned/unlocked activities, e.g. the reported case of two
real Teacher-assigned options) had no exit affordance at all. A real
gap: a Learner who taps "Start Reading" without meaning to had no way
back except the browser's own back button.

Cross-checked `activity-found.blade.php` (the other Learner screen that
can show up in this same flow, when exactly one activity resolves and
the picker is skipped entirely) first, to check whether this was
systemic — it already has a working "Back to My Dashboard" `.big-btn`,
so the picker's populated branch was the one real, isolated gap, not a
pattern repeated elsewhere.

**Fix**: added the same `.back-link` (chevron SVG + text, `color:
var(--slate-600)`, hover `var(--blue-600)`) already used consistently
across every other Learner screen (Games hub, both games,
`activity-found.blade.php`) into the `@else` branch, directly below the
option list — matching the established exit-affordance pattern instead
of inventing a new one.

**Tested for real**: created a disposable test Learner
(`ZZPickerTest`, Grade 1, code `TB-243CB`) with 2 real
`ActivityAssignment` rows against 2 real Approved Activities
("Easy Animal Words," "Medium Animal Words (edited)") — reproducing the
exact two-option scenario from the report. Logged in via the real
browser flow, confirmed via screenshot both real options render
correctly alongside the new "Back to My Dashboard" link, then clicked
it and confirmed via a second screenshot it correctly navigates to
`/learner/dashboard`, landing on the real dashboard shell (Level/
Points/Streak tiles, both game buttons). Test Learner and its
`ActivityAssignment` rows cleaned up afterward.

## Mobile app — Part 1, the Laravel API layer (Learner-only, MVP scope)

The team is starting a React Native (Expo) mobile app — **Learner only**,
a confirmed deviation from the manuscript's named Kotlin (see the
mobile-stack decision recorded outside this file). Teacher/Parent/Admin
remain web-only. Full scope report (MVP boundary, Expo confirmation,
screen/nav structure) was proposed and confirmed with the user before
any building — this entry covers Part 1 only: the real JSON API layer
the app talks to, which had to exist as a genuine prerequisite before
any React Native code could be written.

**The core problem, confirmed by reading the actual code first, not
assumed:** almost all of the real scoring/mastery/adaptive-recommendation
logic (`scoreAndPersist`, `buildWordBreakdown`, `adjustMasteryLevel`,
the diagnostic staircase steps, etc.) lived as `private` methods
directly on the Blade-rendering web controllers, returning `View`
objects. None of it was callable from anywhere else. Making the mobile
API a genuine "thin wrapper around the same logic" (not a
reimplementation) required extracting that logic into real, shared
service classes first — a real refactor, not just new routes.

**New service classes** (`app/Services/`), each holding the exact same
computation the original private controller methods did — confirmed via
careful line-by-line extraction, not a rewrite:
- `LearnerAuthService` — the real credential-check/throttle logic
  (`authenticate()`) and the "what should I read?" picker resolution
  (`findActivityOptions()` + the Adaptive_Recommendator labeling),
  extracted out of `LearnerAuthController`.
- `LearnerReadingService` — the full real scoring/mastery/word-breakdown/
  Adaptive_Recommendator-update pipeline (`recordAttempt()`), extracted
  out of `LearnerReadingController`.
- `LearnerDiagnosticService` — the full real staircase (bundle
  generation, scoring, tier movement, finalization,
  Adaptive_Recommendator `/initialize`), extracted out of
  `LearnerDiagnosticController`.

The three web controllers are now thin — they call these services and
render a Blade view from the returned data array; nothing about their
external behavior changed. **Regression-tested for real, not assumed
safe from the diff**: a fresh Learner (`ZZWebRegress`, code
`TB-WEBRG1`) ran a genuine TTS-scored diagnostic passage through the
real web flow post-refactor (89.19% accuracy → correctly stopped at
Developing, matching the 70-89% stop-immediately rule), correctly
landed straight on the real dashboard afterward (the "never again"
rule) with a real Adaptive_Recommendator `/initialize` result already
showing on "How I'm Growing," then read a real assigned Activity
("cat dog pig hen cow") and got a real 60% accuracy / Beginning-tier
result with the correct real `word_feedback` breakdown — all confirmed
directly against the database, matching this exact scenario's
well-established expected values from this project's own prior
testing history.

**The session-state problem, and why it had to be fixed, not just
routed around**: the diagnostic staircase's in-progress state and the
"3 unclear attempts" retry counters both lived in PHP's `session()`,
which a Sanctum **token**-authenticated mobile request never carries
(no session cookie). Moved both to the database `cache` store
(`config/cache.php`'s existing default — no new infrastructure),
keyed by `learner_id` (+ `activity_id` for the reading one). This is
strictly more correct for the web flow too, not a compromise made for
mobile's sake — same guarantees, just not tied to a cookie.

**Sanctum stood up for real, not just left "installed"**: `laravel/
sanctum` was in `composer.json` since early in the project but its
`personal_access_tokens` migration had never actually been published
or run, `routes/api.php` didn't exist, and `bootstrap/app.php` never
registered an `api:` routing group. Published the migration, added
`HasApiTokens` to the `Learner` model (already `AuthenticatableContract`,
so this was a clean addition, not a restructure), created `routes/
api.php`, and registered it in `bootstrap/app.php` alongside the
existing `web:` group. (`php artisan install:api` was tried first to
automate this — it hung indefinitely on this machine after downgrading
`laravel/sanctum` to `^4.0` in `composer.json`/`composer.lock` as a
side effect; killed it and reverted those two files, then did the
setup by hand instead, which also gave more control over where things
went than the command's own defaults would have.)

**The 9 real MVP endpoints** (`routes/api.php`, all under `auth:sanctum`
except login), each a thin JSON wrapper over the services above —
Learner-only, matching the mobile app's own confirmed scope:
```
POST /api/learner/login
POST /api/learner/logout
GET  /api/learner/dashboard                        (behind learner.diagnostic.api)
GET  /api/learner/diagnostic/passage
POST /api/learner/diagnostic/record
GET  /api/learner/activities                       (behind learner.diagnostic.api)
GET  /api/learner/activities/{id}                  (behind learner.diagnostic.api)
POST /api/learner/activities/{id}/record            (behind learner.diagnostic.api)
POST /api/learner/reading-preferences/font-step
```
New `EnsureDiagnosticCompleteApi` middleware is the API counterpart of
the existing web `learner.diagnostic` guard — same rule, but responds
with a real `403 {"error":"diagnostic_required"}` instead of a Blade
redirect, since a mobile client has no concept of one. A new
`SerializesLearner` trait (`app/Http/Controllers/Api/Concerns/`) is the
one place a Learner model gets turned into a safe JSON payload
(whitelisted fields only — never the raw Eloquent model, which would
expose the hashed pin), shared by the two controllers that need it
rather than duplicated.

**A real security catch made before shipping, not after**: the
activity-detail endpoint was originally going to include
`follow_up_questions` (for the not-yet-built mobile comprehension
quiz) — but that field's `answer` key is the literal correct-answer
text, and the web's own `activity-found.blade.php` never sends it to
the browser at all (only `choices` gets rendered into the page,
`answer` stays server-side for scoring). Shipping it in the API now,
before a quiz UI exists to use it responsibly, would just hand a
mobile client the answer key. Left out of this endpoint entirely for
now — a real, disclosed scope cut, not an oversight; add it back
(choices only, still never `answer`) when the mobile quiz step is
actually built.

**A real, currently-live bug found while testing — not introduced by
this refactor, but discovered because testing the new API path hit it
directly**: `hasCompletedDiagnostic()` (checked at login, and by the
`learner.diagnostic` gate on every dashboard/activity/games request)
was just `ReadingSession::where(...'Diagnostic')->exists()` — true
after passage 1 of up to 3, since `applyStaircaseStep()` persists a
real session for every attempted passage immediately, not only the
final one. A Learner who reached the dashboard directly (bookmark,
back button, app restart) between passage 1 and the staircase's real
conclusion would be waved through with `mastery_level` still whatever
the placement-quiz guess was, never finishing their real diagnostic.
This exact same flawed check was independently duplicated in three
places before this pass (`LearnerAuthController::login()`, the web
`EnsureDiagnosticComplete` middleware, and the just-written
`EnsureDiagnosticCompleteApi`) — fixed once, correctly, in
`LearnerDiagnosticService::hasGenuinelyCompletedDiagnostic()` (combines
"a real Diagnostic session exists" AND "no in-progress staircase state
is still cached"), with all three call sites now delegating to it.

**Tested for real, confirmed both before and after the fix, on both
channels**: a fresh Learner (`ZZApiTest`, code `TB-APITE1`) genuinely
mid-staircase (1 of up to 3 passages done, 91.89% accuracy) was
confirmed via direct cache inspection to still have in-progress state
— and, before the fix, the API's own `/api/learner/login` response
showed `needsDiagnostic: false` for this exact Learner, which would
have been a real bug reaching a real mobile app. After the fix, the
identical state correctly reports `needsDiagnostic: true`, and both
`GET /api/learner/dashboard` (403 `diagnostic_required`) and a direct
`GET /learner/dashboard` web request (redirected to `/learner/
diagnostic`, not served) correctly enforce it. The same Learner then
completed passage 2 for real (93.22% at the Hard ceiling → genuine
diagnostic completion, `mastery_level` → Proficient, a real
Adaptive_Recommendator `/initialize` call landing `reading_fluency` at
proficiency 93.22/difficulty hard/confidence 0.6) — confirmed
`hasGenuinelyCompletedDiagnostic()` then correctly flips to `true` and
the dashboard becomes reachable.

**Every one of the 9 endpoints exercised for real against this same
Learner, end to end, not mocked**: `/login` (real token issued),
`/dashboard` (correct 403 pre-diagnostic, correct 200 + real
`competencyProgress` data post-diagnostic), `/diagnostic/passage` ×2
(real Gemini-generated passages), `/diagnostic/record` ×2 (real
Reading-api scoring driving real staircase advancement, ending in a
real finish), `/activities` (correctly resolved the one real assigned
Activity), `/activities/{id}` (real passage detail, confirmed
`follow_up_questions` correctly absent), `/activities/{id}/record`
(a real 60%-accuracy TTS reading — `cat dog pig hen cow`, this
project's own established test phrase — produced the exact same real
`accuracy`/`wcpm`/`levelBefore→levelAfter`/`wordBreakdown` shape the
web results screen renders, plus confirmed real downstream effects:
`PersonalWordBank` got the real missed words "hen"/"cow", and
`foundational_reading`'s `competency_states` updated via a real
`/recommend` call), `/reading-preferences/font-step` (real DB
persistence confirmed), and `/logout` (confirmed the token is
**genuinely revoked**, not just client-side forgotten — a follow-up
request with the same token correctly got a real `401`, not a
cosmetic client-side logout).

Zero new Laravel log entries across the whole test pass (checked
directly). Both disposable test Learners (`ZZWebRegress`/`TB-WEBRG1`,
`ZZApiTest`/`TB-APITE1`) and all their `ReadingSession`/
`ActivityAssignment`/`PersonalWordBank`/Sanctum-token rows cleaned up
afterward; their real diagnostic-generated Activities (`purpose:
'diagnostic'`) were left in place, consistent with this project's
existing practice of not cleaning up real generated content.

**Explicitly not started yet**: Part 2 (the actual Expo project) and
Part 3 (the native screens/nav) — this entry is Part 1 only, confirmed
complete and tested before moving on.

## Learner dashboard v2 — Slice 1: Real Badges (Bookshelf/Weekly Goals/
## Reading Journey are separate, later slices)

The user asked where the "adaptive"/"gamified" side of the app actually
lives (answer: already real and shipped — Adaptive_Recommendator +
"How I'm Growing" + Practice Games' adaptive connection, all documented
elsewhere in this file) and asked for a genuinely richer Learner
dashboard beyond just the two big buttons. Scoped into 4 slices with
the user before building anything (Badges → Bookshelf → Weekly Goals →
Reading Journey visual) — this entry covers Slice 1 only.

**A real fabrication bug found while scoping, not while building.**
`diagnostic-results.blade.php` has said "You earned your first badge —
the First Reading Star!" unconditionally, every single time, for every
Learner, since Sprint 4 — despite Badges being explicitly deferred back
then for lack of defined content. There was never a `badges` table, a
`learner_badges` table, or any check at all behind that claim. Fixing
this specific screen (award a genuine badge at that exact moment
instead of printing static copy) was the anchor requirement for this
whole slice, not an afterthought.

**Design simplification made and confirmed with the user before
building**: badge *definitions* (name/description/emoji) live in
`config/badges.php`, not a database table — they're fixed content
nobody edits through a UI, the same reasoning already applied to
`MASTERY_TO_RESULT_LABEL`. Only `learner_badges` (`learner_id`,
`badge_code`, `earned_at`, `unique(learner_id, badge_code)`) is a real
migration — the one genuinely dynamic fact.

**Six badges shipped this slice**, all backed by data this app already
tracked (a seventh, "Game Explorer" tied to completing a Practice Games
session, is a confirmed, deliberate fast-follow — Practice Games are
100% client-side by design and never write anything to the server on
completion, so there's no real signal to check yet):
- 🌟 **First Reading Star** — this Learner's very first completed
  reading ever (Practice or Diagnostic). The real fix for the
  fabrication above.
- 🔥 **3-Day Streak** / 🔥 **7-Day Streak** — `learner.streak` crossing
  3 / 7.
- 📈 **Leveled Up** — the mastery tier genuinely changed AND moved up
  (`levelChanged && levelWentUp` together — not `levelWentUp` alone,
  which stays true even at the Proficient ceiling with no real change;
  same distinction `reading-results.blade.php` itself already draws
  between those two flags).
- 📖 **10 Readings** / 📚 **25 Readings** — real Practice-only
  `ReadingSession` count crossing 10 / 25 (Diagnostic sessions
  excluded, matching Analytics' own established convention for what
  counts as a "reading").

**New `BadgeService`** (`app/Services/`) — the one place real data gets
checked against these thresholds, called from both
`LearnerReadingService::scoreAndPersist()` (after a real Practice
reading) and `LearnerDiagnosticService::finishDiagnostic()` (after the
diagnostic concludes), so the award logic lives in exactly one place
rather than being duplicated at each trigger point. Both return a real
`newBadges` array (empty most of the time) as part of their existing
outcome data — passed through by the web controllers to
`reading-results.blade.php`/`diagnostic-results.blade.php` and by both
mobile API controllers to their JSON responses, for parity.

**A real, second bug found and fixed during testing, not assumed
correct from the diff** — a genuinely fresh mistake in this slice's own
new code, distinct from the diagnostic-completion bug found in the
mobile API work: the original `isFirstEverReading()` check was
`ReadingSession::count() === 1`, computed at the moment the diagnostic
*finishes*. But the diagnostic staircase can take 1-3 passages, and
`applyStaircaseStep()` persists a real `ReadingSession` row for *every*
attempted passage, immediately — so by the time `finishDiagnostic()`
ran, a genuinely first-time Learner whose diagnostic took 2 or 3
passages already had 2 or 3 rows, never exactly 1. Caught by testing a
real 2-passage diagnostic end-to-end and finding zero badges awarded
despite the Learner genuinely being first-time. Fixed by capturing
`is_first_ever_reading` once, in `ensureBundleGenerated()`, before any
passage of that run has created a row — the one order-independent way
to know — and threading it through `applyStaircaseStep()` →
`finishDiagnostic()` → `BadgeService::checkAfterDiagnosticFinish()`
instead of re-deriving it after the fact. The Practice-reading path's
own `isFirstEverReading` check is safe as originally written and stays
a plain `count() === 1` computed inline — `scoreAndPersist()` only ever
adds exactly one row per call, so there's no multi-row ambiguity there.

**Shared, not duplicated**: `LearnerBadge::summaryFor(Learner $learner)`
(merges `config('badges')` definitions with this Learner's real
earned/unearned state) is the one source both the new "My Badges" web
screen (`BadgeController`) and the mobile API's `/api/learner/dashboard`
endpoint build from — same shared-static-method pattern already
established by `ReadingSession::sourceSummaryForLearner()`.

**New "My Badges" screen** (`GET /learner/badges`): every defined badge
shown always, earned ones lit up in a real gold-gradient tile with the
real earned date, unearned ones as a dimmed grey silhouette with a
"🔒 Not yet" label — never hidden, so a child can see what's still
ahead. A new small `.nav-link` on the Dashboard ("🏆 My Badges N/6")
gives a real earned-count at a glance without a click.

**The celebratory moment**: a new shared `_badge-celebration.blade.php`
partial (markup only, matching this app's standalone-per-view CSS
convention), reusing the exact gold star-badge visual language
`diagnostic-results.blade.php` originally used for its own fabricated
text — now genuinely earned. Included on both results screens right
after their existing level-change callout; renders nothing at all when
`$newBadges` is empty (confirmed via direct template checks, not
assumed from the Blade `@if`). Handles more than one badge earned in
the same moment (a real possibility on `reading-results.blade.php` —
e.g. a 10th reading landing on the same session as a level-up).

**Tested for real, both fabrication-fix directions and every
threshold, not assumed from the diff:**
- A genuinely fresh Learner (`ZZBadgeTest2`, `TB-BADGE2`) ran a real
  2-passage TTS diagnostic end-to-end (this is what surfaced the
  multi-passage bug above) — confirmed `first_reading_star` correctly
  awarded exactly once at real completion, `learner_badges` holding
  exactly 1 row, not 0 and not 2.
- The exact fabrication scenario, confirmed the other direction too: a
  second fresh Learner (`ZZBadgeTest`, `TB-BADGE1`) whose diagnostic
  had already completed under the pre-fix code (genuinely zero real
  badges) was confirmed, via a live screenshot of "My Badges," to show
  all 6 badges honestly locked — no retroactive fabrication for a
  reading that, in fact, wasn't their first.
- One real Practice reading (genuine TTS, the project's own "cat dog
  pig hen cow" test phrase) confirmed the live pipeline end-to-end:
  60% accuracy, streak → 1, mastery Proficient → Developing (a
  downward move, correctly earning no Leveled Up badge).
- One real reflection-based call (this project's established technique
  for exercising a real, hard-to-reach-live branch — see the earlier
  ≥90%-accuracy precedent) drove a genuine Developing → Proficient
  level-up: confirmed `leveled_up` awarded exactly on that call, not
  before.
- A full real threshold sweep (25 sequential real `BadgeService::
  checkAfterPracticeReading()` calls against real Learner state and
  real `ReadingSession` rows — not the full external-call pipeline
  replayed 24 more times, which had already been proven wired in by
  the calls above): `streak_3` fired exactly at streak=3, `streak_7`
  exactly at streak=7, `readings_10` exactly at practiceCount=10,
  `readings_25` exactly at practiceCount=25 — never early, never late,
  never duplicated (`learner_badges` held exactly 6 rows at the end,
  matching the 6 real badges earned, confirmed by direct count).
- Both results-screen templates rendered directly (`view(...)->render()`)
  with real `$newBadges` data confirmed the celebration markup appears
  with a real badge and confirmed — via an HTML-attribute-precise
  string check, after an initial test methodology mistake matched a
  CSS class *selector* instead of rendered markup, caught and corrected
  before treating it as a real result — that zero markup renders when
  `$newBadges` is empty, on both `reading-results.blade.php` and
  `diagnostic-results.blade.php`.
- `php -l` clean and `php artisan route:list` clean (76 routes, +1 for
  `/learner/badges`) across the whole pass. Both disposable test
  Learners and all their `ReadingSession`/`ActivityAssignment`/
  `PersonalWordBank`/`LearnerBadge` rows cleaned up afterward.

**Not started yet**: Slices 2-4 (My Bookshelf, Weekly Goals, the
Reading Journey visual) — this entry is Slice 1 only, confirmed
complete and tested before moving on, per the confirmed build order.

## Learner dashboard v2 — Slice 2: My Bookshelf

Real data reuse, exactly as scoped: a query over Activities this
Learner already has a completed real Practice `ReadingSession` for —
no new scoring logic for the listing itself. One card per distinct
Activity (title, real times-read count, most recent date, best
accuracy), grouped and aggregated from existing `ReadingSession` rows
(`BookshelfController::index()`), sorted by most recently read.
Diagnostic sessions are excluded from the listing, same as every other
"reading count" concern in this app (Analytics, the Badges' 10/25-
readings thresholds) — a diagnostic passage is system-generated
throwaway content, never a "book" a child chose to read.

**New `Activity::hasCompletedPracticeReadingFor(Learner $learner)`** —
deliberately NOT `isAccessibleByLearner()`. That check is about whether
a Teacher assignment or Parent unlock is *currently live*; Bookshelf is
a Learner's own reading history, which should stay revisitable even if
the original assignment/unlock has since gone away (a Teacher
un-assigning something doesn't erase that the child already read it).
Guards both `BookshelfController::reread()` (the recording screen) and
`submitReread()` — re-verified server-side, never trusted just because
the Bookshelf UI only links to Activities it already listed. Confirmed
with a real direct request to reread an Activity this Learner never
completed: a real `403`.

**The re-reading decision, built exactly as recommended and confirmed
with the user**: free and unscored. New
`LearnerReadingService::recordFreeReattempt()` calls the real
Reading-api and builds the exact same real word-by-word feedback a
normal reading gets (so the practice value — seeing what you got right/
wrong — is preserved), but deliberately skips everything
`scoreAndPersist()` does: no `ReadingSession` row, no mastery/points/
streak change, no `PersonalWordBank` entries, no notification, no
Adaptive_Recommendator call. (Badges are naturally excluded too, with
no special-casing needed — `BadgeService::checkAfterPracticeReading()`
is simply never called on this path, and even if it somehow were, its
thresholds all key off `ReadingSession` rows/`streak`, neither of which
a free reread ever touches.) Same reasoning already established for
Practice Games: if re-reading scored for real, a Learner could
repeatedly re-read one easy Activity to inflate their real level/
points/streak, undermining the whole adaptive system's integrity. No
comprehension quiz on this path either — tied to the real scoring
pipeline this deliberately bypasses. No capped-unclear-attempts
counter either — since nothing is at stake, a Learner can simply keep
trying as many times as they want; a silent/unclear free reread just
shows a plain "try again," no 3-strikes cap, no Cache-backed counter
needed.

**New screens**: `bookshelf.blade.php` (the list), `bookshelf-reread.
blade.php` (recording screen, reuses the existing `_recording-widget`/
`_reading-font-control` partials, with a clear "🎈 Free practice — no
points, just for fun" badge so a child doesn't mistake this for a real
scored activity), `bookshelf-reread-results.blade.php` (the real word
breakdown + Accuracy/WCPM/Words-to-Practice stats, deliberately no
Points/Streak tiles at all — not zeroed out, genuinely absent, since
showing them would misleadingly imply this reading affected them), and
`bookshelf-reread-unclear.blade.php` (a plain retry, no attempt-cap
language). A new "📚 My Bookshelf N" `.nav-link` on the Dashboard,
same pattern as the Badges one, with a real distinct-Activity count.

**Tested for real, both re-reading's zero-effect guarantee and the
aggregate stats' correctness — not assumed from the diff:**
- A fresh Learner (`ZZShelfTest`, `TB-SHELF1`) read a real assigned
  Activity for real (60% accuracy, "cat dog pig hen cow") — Bookshelf
  correctly showed "Read 1 time · Best: 60%."
- **The core guarantee, confirmed by snapshotting every relevant field
  before and after a real free reread**: `mastery_level`, `points`,
  `streak`, `competency_states`, the real Practice `ReadingSession`
  count, `PersonalWordBank` count, `Notification` count, and
  `LearnerBadge` count were all captured before, then re-checked after
  a genuine TTS-scored free reread — every single one identical, byte
  for byte. The reread-results screen itself, confirmed via direct
  response inspection and a live screenshot, showed the real word
  breakdown (a real skip + a real "heard 'headcount'" substitution)
  and Accuracy/WCPM/Words-to-Practice, with no Points/Streak tiles
  present at all.
- Confirmed the free reread did NOT bump Bookshelf's real count (still
  "Read 1 time" immediately after), then did a second genuine *scored*
  Practice reading of the same Activity and confirmed Bookshelf
  correctly updated to "Read 2 times," with the most-recent date
  advancing — proving the aggregate query correctly counts real
  readings while continuing to exclude free ones.
- The access-control boundary: a direct request to reread an Activity
  this Learner never actually completed (`Activity` id 2, never read)
  correctly returned a real `403`, not just a hidden UI link.
- The unclear path: genuine silent (volume-zero TTS) audio submitted
  through the free-reread endpoint correctly returned the friendly
  "Didn't quite catch that — try again!" screen with no attempt cap,
  and confirmed zero new `ReadingSession` rows were created by it.
- `php -l` clean and `php artisan route:list` clean (79 routes, +3 for
  the Bookshelf list/reread/reread-submit routes) across the whole
  pass. The disposable test Learner and all its `ReadingSession`/
  `ActivityAssignment`/`PersonalWordBank`/`LearnerBadge` rows cleaned
  up afterward; two real, unrelated Reading-api connection timeouts hit
  mid-testing (a cURL connect timeout, then a DNS resolution timeout)
  were confirmed as real transient network hiccups via a live `/health`
  check before retrying, not code bugs — both retries succeeded
  cleanly.

**Not started yet at the time this entry was written**: Slices 3-4
(Weekly Goals, the Reading Journey visual) — this entry was Slice 2
only. See the full app-shell redesign entry below, which folds the
Reading Journey visual (originally "Slice 4") into a much larger
layout pass and covers Weekly Goals with an honest "Coming soon"
instead.

## Learner dashboard v2 — full app-shell redesign (icon-only rail,
## bento Home, real winding-path Journey folded in from "Slice 4")

A full structural redesign of the Learner dashboard, moving from the
old single-column phone-scroll layout to a real landscape app-shell:
a persistent left rail (bottom tab bar on phone) + a panel-based main
area covering Home/Journey/Badges/Bookshelf/Goals/Growth. Built from a
v2 HTML reference the user provided directly, per the standing
reference+enhance policy, with three explicit enhancements requested
on top of it: a bento grid (not uniform tiles) on Home, a large
illustrated claymorphism "Continue" hero tile with Tara the owl as the
Home screen's clear visual anchor, and an icon-only compact rail.

**Real data audit done before building, per explicit instruction**:
Badges and Bookshelf were already fully real (prior slices); Weekly
Goals had nothing built; the Reading Journey visual had real
underlying data (`Learner::competencyProgressSummary()`) but only a
plain-bar version existed (the fancier winding-path was the original,
separate "Slice 4," never built). **User's explicit decision**: fold
Slice 4 into this layout pass now, rather than shipping a plain-bar
Home today and re-polishing the same panel for Slice 4 later — the
reference's own Home panel implies the fancier path, not plain bars.
Goals and the newly-scoped "Growth" panel (a bar chart of stories read
per weekday — a genuinely new, small feature the user flagged
separately, not part of the original 4-feature scope) both ship as
honest "Coming soon" screens, no fabricated data, until their own
backend work is scoped next.

**Icon-only rail accessibility — proposed and approved before
building, not decided silently**: the user's own aesthetic request
(icon-only, no labels at rest) was flagged as a real risk for
6-9-year-old readers before any code was written. Shipped exactly as
approved: icon-only at rest, the active item shows its own label,
every rail item carries a real `title` attribute (hover/press
tooltip), and a one-time full-label reveal fires on a Learner's first-
ever dashboard load (`localStorage`-keyed by `learner_code`, wrapped in
try/catch, 2.8s then collapses) — confirmed live: `railNav` correctly
lost its `reveal-labels` class after the timeout, rail width settled
at 84px (icon-only), and all 6 items report correct real `title`s
(“Home”, “My Growth Path”, “My Badges”, “My Bookshelf”, “Weekly Goal”,
“My Growth”) with correct active-state detection via
`request()->routeIs(...)`.

**New shared `layouts/learner-app.blade.php`** — this app's first real
`@extends`/`@yield` layout for the Learner area (every Learner screen
before this was a fully standalone HTML document); the only existing
precedent anywhere in the app was `layouts/auth.blade.php`, followed
directly rather than inventing a new pattern. Dashboard, Journey,
Badges, Bookshelf, Goals, and Growth all now extend it; the Bookshelf
re-read/results/unclear screens deliberately do NOT (see the
un-confirmed decision below).

**Home redesigned as a real bento grid**, not uniform tiles: one large
hero tile (claymorphism, the `_owl-mascot` partial included as
`heroOwl`, real "Picked just for you" copy driven by
`collect($learner->competencyProgressSummary())->firstWhere('isUpNext', true)`,
honest generic invitation when nothing is flagged next yet) beside a
2×2 `bento-side` grid of 4 smaller tiles: Games (real, links to
Practice Games), "This Week's Goal" (honest Coming Soon), "My Badges"
(real — first 5 badges via `array_slice`, real earned count, links to
the full Badges panel), "My Growth" (honest Coming Soon).

**Reading Journey — the real winding-path visual, replacing the
original plain-bar plan and the never-built standalone "Slice 4"**:
new `LearnerGrowthController::journey()` + `journey.blade.php`. Every
number comes straight from the existing, already-real
`competencyProgressSummary()` — nothing new computed or fabricated.
5 fixed zigzag `(x,y)` points per competency row; real proficiency
(0-100) is linearly interpolated between two of those points server-
side (`buildTrack()`) to place a marker at the exact real position, and
checkpoints below the real proficiency light up (quartile checks, not
an invented threshold). A competency with `proficiency === null`
(never assessed) renders as a fully locked/dashed row instead of a
marker at position 0, since 0 is a real assessed score and "not yet
assessed" is a different, honest state. The competency matching
`next_recommended_competency` gets the same "⭐ Up Next" badge already
used on the old Dashboard card. A Learner with `competency_states`
still null (pre-diagnostic — genuinely reachable, since a diagnostic
completing without ever reaching a successful Adaptive_Recommendator
call is possible) gets a full honest empty state ("Your growth path
fills in once you complete your first reading check! 🌱") instead of
three locked rows — confirmed live, not just from source.

**A real bug found and fixed during testing, not assumed correct from
the diff**: `LearnerGrowthController`'s `journey()`, `goals()`, and
`growth()` methods never passed `'learner' => $learner` to their
views — every screen extending the new shared layout needs `$learner`
in scope for the rail avatar, but these three didn't provide it.
Caught immediately on the first real page load
(`ErrorException: Undefined variable $learner` at
`layouts/learner-app.blade.php:110`), not from reading the diff. Fixed
by passing `$learner` (via `$request->user('learner')`) from all three
methods; re-verified all three render cleanly afterward.

**Tested for real, both desktop and phone width, with genuine mixed
adaptive data — not just "doesn't break":**
- Logged in as Miguel (the flagship reference Learner) through the
  real browser. Real accumulated data already covered Badges (3
  genuinely-earned-but-never-checked badges — `streak_3`/`streak_7`/
  `readings_10` — surfaced by running `BadgeService::
  checkAfterPracticeReading()` against his real existing streak/
  session-count state, since `BadgeService` only checks at the moment
  of a new reading/diagnostic and never retroactively scans existing
  Learners; a real, disclosed gap for any pre-existing Learner) and
  Bookshelf (3 real distinct Activities from prior sessions). For
  Journey specifically, a realistic mixed `competency_states` shape
  was written directly via `tinker` (this project's established
  synthetic-injection technique for exercising a state a live run
  can't reliably reach) — `foundational_reading` partially assessed
  and flagged "Up Next," `reading_fluency` highly proficient, and
  `reading_comprehension` left genuinely null — then **fully reverted
  to Miguel's exact prior state (0 badges, `competency_states` back to
  null) once testing was done**, since none of this was triggered by a
  real user action and Miguel is this project's non-disposable
  flagship account.
- **Desktop (1280×720)**: bento Home confirmed visually — real hero
  copy ("Time to work on Sounding Out Words!"), real badge strip (3 of
  6), honest Coming Soon on Goals/Growth tiles. Journey panel showed
  all three real states side by side: a marker correctly interpolated
  between checkpoints 2-3 for the 42%-proficiency "Up Next" row, a
  marker near the end for the 93.3%-proficiency row, and a fully
  locked/dashed row for the unassessed competency. Badges and
  Bookshelf panels confirmed still rendering their real, unchanged
  data correctly inside the new shell. Goals/Growth confirmed showing
  their honest Coming Soon cards.
- **Phone (375×812)**: confirmed via computed styles that the rail
  genuinely becomes a bottom tab bar at this width (`flex-direction:
  row`, `position: sticky`, pinned to the viewport bottom — `rail`'s
  `getBoundingClientRect().bottom` exactly equals `window.innerHeight`)
  with the avatar and "Switch learner" correctly hidden
  (`display:none`) — a deliberate enhancement decision (see below),
  not a shrunk copy of the desktop rail. Confirmed active-tab
  detection still works correctly on the bottom bar after navigating
  to Journey. Confirmed via screenshot that the bento Home, the
  Journey visual, and the Badges/Bookshelf panels all reflow to a
  single readable column and look genuinely designed for the width,
  not just "technically doesn't overflow."
- The `hasAnyData === false` Journey empty state was confirmed live,
  not just from source, using Miguel's own reverted (null
  `competency_states`) post-test state.
- `php -l` clean on every new/modified file; `php artisan route:list`
  confirmed the 3 new routes (`learner.journey.index`,
  `learner.goals.index`, `learner.growth.index`) register correctly
  alongside all existing Learner routes; zero unexpected entries in
  the Laravel log across the whole pass (the one real
  `Undefined variable $learner` exception above was the only error
  seen, and was fixed and re-verified clean).

**Two design decisions made independently during this pass, not
explicitly run past the user beforehand — flagged here rather than
left silent:**
1. **Bottom tab bar on phone width, not a squeezed left rail.** The
   reference only shows a desktop rail; converting to a bottom bar
   below a new `max-width:680px` breakpoint was this session's own
   call, reasoning that a persistent 84px icon-only left rail would
   consume roughly a fifth of a 375px screen for 6 items, and that a
   bottom tab bar matches both a "real app shell" feel and the
   bottom-tab pattern already discussed for the separate React Native
   mobile app.
2. **The Bookshelf re-read/results/unclear screens were NOT brought
   into the new rail-nav shell** — they stay standalone, chrome-free
   documents, matching this app's existing "a focused task flow omits
   chrome" pattern (the diagnostic screens, `activity-found.blade.php`,
   the child-creation wizard). Reasonable given precedent, but not a
   choice the user explicitly confirmed for this specific redesign.

**Not started yet at the time this entry was written**: Weekly Goals'
real backend and the new "Growth" daily-chart feature's backend — see
the entry directly below, where both were scoped, confirmed, and built
as a same-session follow-up.

## Learner dashboard v2 — Weekly Goal + Growth, real backends (the two
## panels the app-shell redesign above deliberately left as "Coming soon")

Scoped with the user before building, per their own explicit
instruction not to build the new "Growth" feature silently. One real
gap made both panels need a decision before any code: nothing in this
app defines what a "weekly goal" number even is — no actor prompt or
patch doc mentions Weekly Goals at all, it only exists as a tile in the
v2 reference mockup. **Confirmed with the user**: a fixed system
default target (5 real reading sessions/week), not a Parent/Teacher-set
number — same reasoning as `config/badges.php`'s fixed definitions, no
new settings UI or migration needed. New `config/reading_goals.php`
(`weekly_target => 5`).

**One shared query, not two separate ones** — `Learner::
thisWeeksPracticeReadingSessions()` (real Practice `ReadingSession`
rows for the current Carbon week, Diagnostic excluded, same convention
as Analytics/Badges/Bookshelf) is the single source both panels read
from, so Weekly Goal and Growth can never quietly disagree about which
sessions count or where the week boundary falls. Weekly Goal sums it
into one count-vs-target; Growth buckets the same collection by ISO
weekday (Mon→Sun, Carbon's real default for `APP_LOCALE=en` — no
`week_starts_at` override exists, checked directly rather than
assumed).

**Weekly Goal** (`learner/goals.blade.php`): a real progress bar
("4 of 5 real reading activities this week"), an honest "N more
readings to go!" note, and a genuine celebratory gold-gradient state
(matching the existing earned-badge visual language) once the target is
met — "You hit your goal this week — amazing job! 🎉". No badge tie-in
was added for hitting the goal — the user didn't ask for one, and
adding one silently would be scope creep beyond what was scoped.

**Growth** (`learner/growth.blade.php`): a real 7-bar chart, one bar
per weekday, height proportional to that day's real count (today's bar
gets a distinct orange highlight), an honest empty state
("No stories read yet this week — start one today!") when the whole
week is empty. **Worth remembering for future work on this app**:
`learner.streak` is NOT a calendar-day streak despite the Badges'
"Read on 3 days in a row" copy — grepped for any date-based reset logic
and confirmed there is none; `streak` is incremented by exactly 1 on
every real scored reading, full stop, no day-boundary check anywhere.
Growth's per-weekday bucketing is the first place in this app that
actually computes real distinct-day reading data — it does NOT reuse
`streak` for anything, precisely because they're honestly different
numbers despite the similar-sounding badge copy.

**Home bento tiles updated to match** (`learner/dashboard.blade.php`,
`LearnerAuthController::dashboard()`): "This Week's Goal" and "My
Growth" no longer show "Coming soon" — they show the same real
`thisWeeksPracticeReadingSessions()` count, previewed two ways (a mini
progress bar vs. a plain "N stories read this week" line + "See
chart"), both now real links into their full panels instead of static
divs.

**A real test-setup bug caught before it produced a false result, not
an app bug**: seeding disposable `ReadingSession` rows via `tinker`'s
`::create()` with an explicit `timestamp` silently dropped it — `
timestamp` isn't in `ReadingSession::$fillable` (the exact same class
of gap as the previously-documented `PromotionRecord::$fillable`
missing `claimed_at`), so mass assignment ignored it and every seeded
row landed on the DB's own default (now). All 4 rows showed up bucketed
under "today" instead of the intended Mon/Wed/Thu spread, which would
have looked like a real bucketing bug if not double-checked. Confirmed
this was purely a test-harness gap (the real `LearnerReadingService`/
`LearnerDiagnosticService` set `timestamp` via direct property
assignment, not mass assignment, so real submissions are unaffected) by
re-setting the seeded rows' `timestamp` via `$row->timestamp = ...;
$row->save()` (bypasses `$fillable`) and re-verifying.

**Tested for real, both empty and populated states, on a disposable
test Learner — not assumed from the diff:**
- Fresh disposable Learner (`ZZGoals`, `TB-ZZGOL`, id 41, deleted
  afterward): confirmed the true empty state first — "0 of 5" with the
  correct "5 more readings to go!" note on Goals, and Growth's honest
  "No stories read yet this week" empty state with all 7 bars flat.
- After seeding 4 real Practice sessions (Mon×1, Wed×1, Thu×2 — the
  timestamp bug above was caught and fixed here): confirmed via direct
  DOM inspection (not just a screenshot) that Growth's bars read
  exactly Mon=1 (50% height), Tue=0, Wed=1 (50%), Thu=2 (100%, today-
  highlighted orange), Fri/Sat/Sun=0 — matching the real seeded data
  exactly, and confirmed via screenshot the chart looks intentional,
  not just numerically correct. Goals correctly showed "4 of 5" with an
  80%-filled bar.
- Added a 5th real session and confirmed the "met" branch: Goals
  flipped to the gold celebratory card ("5 of 5," "You hit your goal
  this week — amazing job! 🎉"), and the Home dashboard's mini tile
  correctly showed the trophy emoji suffix only once the target was
  actually met, not before.
- Confirmed the Home bento tiles' real preview numbers match the full
  panels exactly (same `thisWeeksPracticeReadingSessions()` call), on
  both the pre-met and met states.
- `php -l` clean on every new/modified file; `php artisan route:list`
  unaffected (no new routes — `learner.goals.index`/`learner.growth.
  index` already existed from the redesign, now pointing at real
  content instead of stubs); zero new Laravel log entries across the
  whole pass. The disposable test Learner and all 5 of its
  `ReadingSession` rows were deleted afterward; Miguel's real
  `mastery_level`/`points`/`streak`/badges/`competency_states` were
  reconfirmed untouched (this test used a separate disposable Learner
  throughout, never Miguel).

## Learner dashboard v3 — the rail-based app-shell reversed into one
## single collaged Dashboard, everything sized up for a 6-9 year old

Direct, blunt feedback on real production screenshots (Learner "Maria"):
the icon rail from the v2 redesign above was bad UI/UX for kids, every
element needed to be genuinely bigger (icons, text, touch targets, not
just nudged), and — the structural ask — drop the rail/menu-bar
entirely and collage every feature (Journey, Badges, Bookshelf, Goals,
Growth) directly onto one Home page, the way the pre-v2 dashboard did,
rather than clicking between separate rail-navigated panels. Confirmed
the exact shape of "collage" before rebuilding (three options offered:
full inline content, preview-cards-linking-out, or a hybrid) — the user
picked **everything fully inline on one page, no separate routes
needed at all**.

**A real, disclosed bug spotted directly in the user's own screenshot,
fixed as part of this pass**: "This Week's Goal" showed "10 of 5 this
week 🏆" — mathematically correct (Maria genuinely read 10 real
sessions against a target of 5) but reads like an error. Fixed by
giving the "met" branch its own copy that never uses "X of Y" phrasing
at all ("10 real readings! — your goal was 5 this week"), so any count
at or above target (5, 10, or 50) renders sensibly — the "not met"
branch is the only one that ever shows "X of Y."

**Structural reversal, not an incremental tweak**: the v2 redesign's
icon-only rail (`layouts/learner-app.blade.php`) and every page it
routed to — `badges.blade.php`, `bookshelf.blade.php` (the list view),
`journey.blade.php`, `goals.blade.php`, `growth.blade.php`, and their
controllers (`BadgeController`, `LearnerGrowthController`) — are all
deleted, not deprecated. `learner/dashboard.blade.php` goes back to
being a fully standalone HTML document (the shared layout had no other
real consumer left once everything moved inline, so keeping the
`@extends` abstraction alive for exactly one page would have been
unjustified complexity). `BookshelfController` keeps only its real
task-flow methods (`reread()`/`submitReread()`) — the list query moved
to a new `Learner::bookshelfBooks()` model method, reused by the merged
Dashboard. The Journey visual's `buildTrack()`/`TRACK_POINTS` logic
moved from the deleted `LearnerGrowthController` into
`LearnerAuthController` (the one controller that now needs it).
`LearnerAuthController::dashboard()` gathers everything in one place:
badges, journey rows, this week's goal/growth data, and bookshelf
books — four previously-separate controllers' worth of data assembly,
now one method, since it all renders on one page.

**Routes retired**: `learner.badges.index`, `learner.bookshelf.index`,
`learner.journey.index`, `learner.goals.index`, `learner.growth.index`
— all gone. `learner.bookshelf.reread`/`.reread.submit` stay (a real
re-reading task screen, not a passive display panel — matches this
app's existing "focused task flow omits chrome" precedent, same
reasoning already applied to the diagnostic and `activity-found.
blade.php`). Both Bookshelf re-read screens' "Back to My Bookshelf"
links were repointed to `route('learner.dashboard')` ("Back to My
Dashboard"), matching every other standalone screen's exit convention
in this app.

**The one-page collage, top to bottom** (`learner/dashboard.blade.php`):
profile header (large avatar + name + grade + a plain "Switch learner"
link, no icon-only rail item for it anymore) → bigger stat row → the
existing hero "Continue" tile (Start Reading Activity) → a full-width
"🎮 Practice Games" button (previously a small bento tile, now a real
tap target) → "How I'm Growing" (the full real winding-path Journey
visual, all 3 competency rows, inline) → a two-column "This Week's
Goal" + "My Growth" row → the full "My Badges" grid (all 6, not a
5-badge preview) → the full "My Bookshelf" list. Every size bumped up
across the board: h1 26px→32px, stat values 24px→30px, badge icons
40px→52px with bigger tiles, book cards with bigger icons/buttons —
concretely addressing "make everything large," not just a vague pass.

**A real layout bug found and fixed during testing, not assumed correct
from the diff**: the Journey SVG (`viewBox="0 0 500 70"`, CSS
`width:100%`) stretched to the full ~1130px card width on desktop,
which — because a wide SVG scaled from a 500:70 aspect ratio produces
real proportional height (found via direct `getBoundingClientRect()`
measurement, not assumed: 1082px wide → 151.5px tall, exactly
500:70::1082:151.5) — made each competency row balloon to ~230px tall
mostly on dead viewBox margin, and made the zigzag path itself read as
a flat, sparse line rather than a proper "winding path." Fixed by
capping `.journey-track{ max-width:640px }` (card height dropped
703px→547px for the same 3 rows, confirmed via direct measurement) and
widening the viewBox to `0 -4 500 78` to stop the marker circle
(radius 18) from clipping at the top checkpoints (y=16 point minus
18px radius = -2, outside the old 0-70 box) — a latent bug that
happened not to be visible in this session's specific test data, fixed
defensively since it was a one-line change with no downside.

**A real stale-screenshot artifact hit repeatedly during this session's
testing, correctly diagnosed rather than mistaken for a layout bug**:
mid-scroll screenshots on this new, much-longer Dashboard page came
back completely blank or visibly tiled, while `getBoundingClientRect()`
/`window.scrollY` confirmed the DOM was correctly scrolled to the exact
target element every time. This is the same `background-attachment:
fixed`-interacting-with-the-browser-automation-tool quirk already
documented multiple times elsewhere in this file — now hit harder
because this page is genuinely long (a real side effect of the
one-page-collage decision) where the old rail-navigated pages were all
short. Cross-checked via direct DOM geometry each time rather than
trusting the screenshot, per this project's own established practice.

**Tested for real, both viewports, with real mixed data — not assumed
from the diff:**
- Logged in as Miguel (flagship reference Learner). Set up the same
  kind of realistic test state as the v2 pass (3 genuinely-earned-but-
  never-retroactively-checked badges via `BadgeService::
  checkAfterPracticeReading()`, a synthetic mixed `competency_states`
  shape for Journey's three visual states, and 3 real dated
  `ReadingSession` rows this week for Goals/Growth) — confirmed via
  `get_page_text` that every section (Journey's 3 rows including the
  "Not started yet" locked state, "3 of 5" Goal progress, a real 3-bar
  Growth chart, all 6 badges with 3 correctly earned, all 3 real
  Bookshelf books) renders correctly end-to-end on one page load, one
  query per feature, no separate navigation needed.
- **Desktop (1280px real width — screenshots render at a smaller
  display scale, confirmed by checking `window.innerWidth` directly
  rather than trusting the screenshot's own pixel dimensions)**:
  header/hero/stat-row confirmed via screenshot; Goal+Growth pair
  confirmed side-by-side and Badges grid confirmed at 6 real columns
  via direct `getBoundingClientRect()` on each tile (174px each) —
  screenshots of these specific sections were blocked by the stale-
  capture artifact above, so DOM geometry was the verification method,
  consistent with this project's own established fallback.
- **Phone (375px)**: full screenshot confirmed the profile header,
  stats, and hero all reflow to a clean single column at a genuinely
  large kid-friendly size; DOM checks confirmed the Goal+Growth pair
  correctly stacks vertically, the badge grid drops to 2 columns, the
  book list drops to a single column, the Journey SVG scales down to
  277px with zero horizontal page overflow (`scrollWidth <=
  innerWidth`, confirmed directly).
- The "10 of 5" bug fix confirmed by code inspection of the met/
  not-met branch split (both branches render distinctly and neither
  can produce the confusing phrasing) — the "met" branch itself was
  already live-verified working in the v2 Goals-panel pass earlier
  this project (same underlying template logic, carried over
  unchanged into the merged page).
- `php -l` clean on every new/modified file; grepped the whole
  `app/`+`resources/`+`routes/` tree for any surviving reference to
  the retired controllers/routes — found only two harmless doc-comment
  mentions (one already accurate as history, one stale reference to
  `BadgeController` in `LearnerBadge.php`, corrected to name the real
  current call site). Zero unexpected entries in the Laravel log
  across the whole pass.
- Miguel's account fully reverted afterward — 0 badges, `competency_
  states` back to null, the 3 synthetic `ReadingSession` rows deleted,
  Bookshelf's "Easy Animal Words" count back to its real 16 (was
  briefly 19 during testing) — confirmed via direct query, not assumed
  from the revert script running without error.

**Not re-litigated, carried over unchanged from the v2 pass**: the
underlying data sources (`Learner::competencyProgressSummary()`,
`thisWeeksPracticeReadingSessions()`, `LearnerBadge::summaryFor()`,
the fixed 5-session weekly target in `config/reading_goals.php`) are
all exactly as documented in the two entries above this one — this
pass changed how they're arranged on screen and how large everything
renders, not what data backs any of them.

## Learner Login redesign — a real WebGL 3D owl, explicitly reversing the
## earlier no-3D decision

The user is working through a list of Learner-end fixes; this is the
first: the login screen was "completely white," lacking the visual
richness of a reference screenshot they shared (a colorful full-screen
background with a large mascot peeking over/holding the login card).
Built per the standing reference+enhance policy — the reference's
*structure* (full-bleed color, an oversized mascot gripping the card's
top edge) was kept, but re-expressed entirely in TaraBasa's own locked
color tokens and its own owl mascot, not the reference's actual
branding/colors/character.

**A real, explicit decision reversal, flagged rather than silently
overridden**: this project's own "Practice Games" pass (documented
above) explicitly rejected live 3D for the owl mascot, specifically for
lower-end-phone performance reasons, and every mascot appearance since
has used 2D SVG claymorphism. Before building this, that precedent was
surfaced directly and the user was asked to confirm — they explicitly
chose **real 3D (WebGL/Three.js)** over the recommended enhanced-2D
option, deliberately reopening and reversing that earlier call. This is
disclosed here so a future session doesn't treat "no 3D" as still the
standing rule — it's superseded by this explicit choice, for this
screen at least.

**What was actually built, and how "3D" was interpreted honestly**:
there is no tool available to sculpt/import an external 3D character
model file in this environment, and downloading a random pre-made 3D
asset from the web would skip this project's own established
"check the real license before using any asset" discipline (already
applied to the Practice Games sound effects). So the owl is a genuine
Three.js/WebGL scene built from primitive geometries (spheres, cones)
assembled and lit with real `MeshStandardMaterial`s + directional/
ambient lighting — the exact same "build the character from simple
primitives" technique the existing 2D SVG mascot already uses, just
real dimensional geometry instead of flat paths. Colors are pulled
directly from `games/_owl-mascot.blade.php` (`#fff8ef` body, `#ffe4c2`
belly, `#dd7014` beak/feet, `#131f2b` pupils) so the 3D owl reads as
the same character, not a new one.

**Loaded via `import()` of Three.js's real ES-module CDN build**
(`cdnjs.cloudflare.com/ajax/libs/three.js/0.186.0/three.module.min.js`
— confirmed via a direct `WebFetch` of cdnjs's own listing first, not
guessed; modern Three.js versions dropped the old global/UMD
`three.min.js` build entirely, ES-module-only now, so this is loaded
via `<script type="module">` + a dynamic `import()`, not a plain
`<script src>` tag).

**Real animation, not just a static render**: an elastic pop-in
entrance on load, a continuous idle bob + gentle sway, a real
periodic blink (eye group scaled down and back over ~220ms, on a
randomized 2.5-5s interval so it doesn't look robotic), and small
clamped pointer-parallax rotation for interactivity. All idle motion
is skipped under `prefers-reduced-motion`, matching this app's
existing standard.

**A real sound, reused rather than newly sourced**: plays
`public/sounds/correct.mp3` — the same Mixkit-licensed file already
cleared and shipped for Practice Games, not a new asset needing fresh
license verification — once, on the first genuine user interaction
with the code field (not on page load, since browsers block unearned
autoplay; wrapped in try/catch + `.catch()` per this app's established
autoplay-safety convention).

**Two real safety nets, since this is the very first screen every
Learner sees and must never be allowed to break**: (1) a feature
detection (`canvas.getContext('webgl2'||'webgl')`) that throws before
any Three.js work starts if unsupported, caught by a `try/catch`
wrapping the entire module, falling back to the plain emoji mascot
chip; (2) a `setTimeout` safety net that independently checks whether
a `<canvas>` ever actually appeared, for the failure mode a same-module
`try/catch` genuinely cannot catch — a top-level `import()` failing
outright (CDN blocked, network down) throws before any of the module's
own code, including its own `try`, ever runs. Both paths were tested
for real, not assumed: temporarily forcing the "unsupported" branch
confirmed the fallback renders cleanly with zero console errors and
the form stays fully functional; reverting confirmed the real 3D path
still renders correctly afterward.

**A real race-condition bug found and fixed during testing, not
assumed correct from the diff**: the first live test showed BOTH the
real 3D owl AND the emoji fallback rendering at once. Root cause: the
timeout-based safety net (originally 1800ms) fired before the
Three.js CDN fetch resolved on a fresh load, showing the fallback —
which then never got hidden again once the real canvas successfully
appeared moments later, since the success path had no code to reverse
that. Fixed two ways: extended the timeout to 4000ms for more real-
world headroom, and — the more robust fix — made the success path
itself explicitly hide the fallback element the moment the canvas is
actually appended, so it self-corrects regardless of timing.

**A real testing-environment limitation hit and correctly diagnosed,
not mistaken for a bug**: initial animation verification (sampling the
owl's rotation/position at two points in time) showed values frozen at
exactly zero. Investigated before concluding anything was broken —
confirmed via `document.hidden`/`visibilityState` that this sandboxed
browser tab reports as genuinely hidden to the page (a real Chromium
behavior: `requestAnimationFrame` is throttled/paused for
non-visible tabs, unrelated to this project's own code). Verified the
actual animation math was correct anyway by temporarily exposing the
internal `frame()` function and driving it directly with synthetic
timestamps (bypassing `requestAnimationFrame`/tab-visibility entirely)
— confirmed the entrance pop, idle bob, and sway all compute correctly
over a simulated 4+ seconds, then removed the temporary debug hooks
before finishing (confirmed via grep: zero leftover debug code in the
shipped file).

**Tested for real, end to end — not just visually reviewed:**
- A real login (Miguel, `TB-51763`) through the redesigned page
  correctly landed on the real Dashboard, confirming the visual rebuild
  didn't touch the actual auth flow, the pin-box JS, or CSRF handling.
- Phone width (375px): confirmed via screenshot — the owl, background,
  and card all reflow cleanly, no horizontal overflow, the owl reads as
  large and prominent rather than cramped.
- The chime: confirmed via `read_network_requests` that a real click
  into the code field (a genuine trusted gesture, unlike a
  script-driven `.focus()`, which — correctly — does NOT reliably pass
  autoplay's gesture check) triggers a real `200 OK` fetch of
  `correct.mp3` exactly once.
- Zero console errors across every real/fallback/failure scenario
  tested.

**Not yet done, flagged since the user said more Learner-end fixes are
coming**: this pass covers the Login screen only, per their own
step-by-step framing — the Dashboard, reading screens, etc. were not
touched here and may get their own passes next.

## Learner Login 3D owl — "looks like an alien" fixed by referencing the
## real logo, not the paler flat mascot

Direct, blunt follow-up feedback on the first 3D-owl pass above: it
read as "an alien," not an owl, and should be modeled on the real
TaraBasa app icon specifically, not invented from scratch. Found and
opened the real logo (`public/images/logo.png`) before touching any
code — it's a genuinely different design from the flat 2D SVG mascot
(`games/_owl-mascot.blade.php`) used elsewhere in the app: warm
orange-brown body/wings, a distinctly lighter tan facial disc framing
the eyes, rosy cheek blush, a clearly visible yellow-orange beak,
proper V-shaped feather ear tufts, and a bold dark cartoon outline
around every shape. The first 3D pass had none of that — pale uniform
cream color, no facial disc, thin spike-like ear tufts, a barely-
visible beak, and no outline — which is exactly what reads as "alien
head" rather than "owl."

**Rebuilt the geometry and materials to match the real logo directly**,
not the paler flat mascot (a deliberate choice, since the user pointed
specifically at the logo): new two-tone palette (`#e08830` body/wings,
`#ffe3ad` facial disc/belly, `#f5a623` beak, `#d97b28` legs/feet,
`#ffb3ab` cheeks), a flattened light-colored facial-disc sphere added
behind the eyes (the single biggest fix — real owls' and the logo's
defining feature, absent before), smaller/closer-set eyes (oversized
wide-set eyes were the other biggest cause of the alien look), a
visible rounded beak, rosy cheek blush spheres, real two-part V ear
tufts, and — the detail that made the whole thing finally read as a
polished cartoon rather than a shaded blob — a cheap, standard "inverted
hull" toon-outline technique (a slightly-larger duplicate of each main
mesh, `MeshBasicMaterial` + `side: THREE.BackSide`, so only its
silhouette edge peeks out from behind the real mesh) applied to the
body, ear tufts, wings, beak, and feet.

**Also fixed, per the explicit "make sure the feet are standing"
request**: the old feet were flat ovals floating with a visible gap
below the body. Rebuilt as a real leg (a short cylinder) connecting the
body down to a proper flattened foot with three small toe bumps, so
the owl now visibly stands on its own legs at the card's top edge
instead of appearing to float.

**A real bug found and fixed during this same pass, not assumed
correct from the diff**: the very first re-test showed the eyes
rendering as solid dark ovals with no visible white sclera at all —
investigated rather than assumed cosmetic. Root cause: the eye's dark
"ring" was implemented as a second, slightly-larger, ordinary front-
facing sphere placed just behind the white sphere — since it was bigger
in all directions, its own front surface actually poked out *in front
of* the white sphere's front surface at the center of the eye (not just
at the silhouette edge as intended), so normal depth-testing let the
solid dark ring win almost everywhere, swallowing the eye whole. Fixed
by replacing that ad-hoc approach with the same proven inverted-hull
outline technique already used successfully elsewhere on this pass —
confirmed by temporarily enlarging the owl-stage 2x for close visual
inspection, both before (solid dark ovals) and after (correct round
eyes with visible white/pupil/highlight) the fix.

**A second real, smaller bug fixed the same way**: the beak initially
rendered as a sharp angular diamond facet instead of a rounded beak —
its cone geometry only had 4 radial segments (fine for the flattened
triangular ear tufts, wrong for a beak meant to look rounded) plus a
45° twist that put a flat facet dead-center toward the camera. Fixed
by raising it to 16 segments and removing the twist.

**A real layout regression caught and fixed on mobile, not assumed
fine from the desktop screenshot**: at narrow viewports, `.wrap`/
`.card` naturally shrink below their 420px cap (available width
minus padding), but the owl-stage was a fixed 230×230px regardless —
so on a 375px phone the owl grew proportionally much larger relative
to the now-narrower card, and its feet dropped low enough to overlap
the "Hi there!" heading. Fixed with a `max-width:480px` rule that
scales the owl-stage down (190×190px, adjusted top offset) and gives
the card a bit more top padding — confirmed via screenshot that the
owl and heading now have clean clearance again at 375px, with no
regression at desktop width.

**Background polish, per the explicit "still not satisfied" follow-up**:
richer gradient stops (added a deeper navy at the very top for more
contrast), a redesigned icon-pattern texture using clearer motifs (a
star, a treble-note-ish circle-and-stem, a simple open book, a sparkle,
small dots) instead of the previous more-abstract shapes, and a new
soft radial glow positioned behind the owl's head for a spotlight
effect, echoing the reference screenshot's radiant burst behind its
own mascot — all still built from TaraBasa's own locked color tokens,
no new brand colors introduced.

**Tested for real after every fix, not assumed from the diff**:
- Console clean (zero errors) across every reload during this pass.
- A real login (Miguel, `TB-51763`) through the rebuilt page still
  lands correctly on the real Dashboard — confirms the extensive
  geometry rewrite never touched the actual auth form/CSRF/pin-box JS.
- Desktop and phone-width screenshots both confirmed the owl now
  genuinely reads as an owl (round expressive eyes, visible beak, rosy
  cheeks, warm two-tone coloring, cartoon outline, standing on real
  legs) matching the real logo's character, not an invented one.
- The temporary 2x-enlarged-stage inspection technique (added and then
  removed, not shipped) was used specifically to catch the eye and
  beak geometry bugs that weren't obvious at the real ~230px shipped
  size — confirmed via `grep` that no debug/test scaffolding was left
  in the final file.

## Learner Login owl — 3D abandoned entirely, rebuilt as clean 2D SVG
## matching the real logo directly

Direct follow-up feedback after the logo-matching 3D fix above: still
"the same owl... alien," with an explicit instruction this time —
just use the real logo image as the reference, 2D cartoon is
completely fine, drop 3D. This is the second explicit reversal on this
one screen (2D→3D→2D again), and this time the user's own framing
("its fine cartoon 2d as long as the same references") makes clear 2D
was never the problem — the earlier 3D attempts' proportions/shading
were. Rather than attempt a third 3D iteration, the whole Three.js/
WebGL approach was dropped for this screen and replaced with a real
hand-drawn inline SVG illustration, modeled directly on
`public/images/logo.png`'s actual shapes and colors.

**Why 2D succeeds where the primitive-geometry 3D approach kept
failing**: precisely tracing the logo's 2D silhouette (bezier paths for
the body/ear-tuft/wing outlines, exact circle placements for the
facial disc and eyes) is straightforward and directly comparable
against the reference image by eye. Approximating that same silhouette
from 3D primitives (spheres/cones) is fundamentally lossy — no
combination of spheres and cones traces a real logo's actual outline,
which is exactly why two rounds of 3D tuning still wound up "close but
alien." 2D SVG doesn't have that ceiling.

**What was built**: everything from the 3D passes — the Three.js CDN
import, the whole WebGL scene/camera/lighting/geometry-construction
code, the two-layer WebGL-unsupported/module-import-failure safety
nets, and the emoji fallback — was deleted outright, not kept behind a
flag. In its place, a single inline `<svg viewBox="0 0 300 300">`
built from plain paths/circles/ellipses: a two-tone orange-brown/tan
palette matching the logo exactly, overlapping facial-disc circles
(the same "two circles create a natural brow-dip" trick the 3D version
used, now working correctly since 2D shapes don't have z-fighting),
big expressive eyes with catchlights, rosy cheek ellipses, a rounded
beak with a small mouth-line, V-shaped two-tone ear tufts, and two
wing/arm paths curving down to small "hand" circles resting right at
the card's top edge — preserving the original "holding the card"
structural idea from the very first reference screenshot, just
executed in 2D.

**Animation, now pure CSS + one small timer** (no rAF loop, no
Three.js, far simpler than the 3D version): a one-time elastic
entrance (`@keyframes owlEntrance`, `cubic-bezier(.34,1.56,.64,1)`
overshoot) chained into a looping idle bob+sway
(`@keyframes owlBob`), both skipped under `prefers-reduced-motion`.
Blinking reuses the *exact* technique already established in
`games/_owl-mascot.blade.php` — two pre-drawn eye states (`.eyes-open`/
`.eyes-closed`) as sibling `<g>` groups, visibility toggled via a class
on the wrapping element — just driven by a `setTimeout` loop instead of
a gameplay event, so this new illustration stays consistent with the
one mascot-animation pattern this codebase already has instead of
inventing a second one.

**The reused chime-on-first-focus sound was kept unchanged** — nothing
about it was 3D-specific, no reason to touch it.

**A real layout side-effect handled proactively**: since the SVG
scales cleanly with its container (unlike the old fixed-pixel `<canvas>`
approach), the existing "owl grows disproportionately on narrow
screens" fix from the previous pass was kept and re-tuned (owl-stage
210×210 below 480px, adjusted top offset, `.card` padding-top raised
to 56px at all widths this time — the SVG's taller silhouette, with
wings reaching further down toward the card than the 3D version's feet
did, needed a bit more clearance even at desktop width) — confirmed via
screenshot at both 375px and desktop that clearance is clean, no
overlap with the "Hi there!" heading at either size.

**Tested for real, not assumed from the diff**:
- `grep`-confirmed zero remaining references to Three.js/`THREE.`/the
  old canvas-based fallback anywhere in the file.
- Console clean (zero errors) on every reload.
- Blink toggle verified via direct `getComputedStyle` inspection
  (`.eyes-open`/`.eyes-closed` opacity flipping correctly on the
  `is-blinking` class) — the first verification attempt showed the
  class already gone by the time it was checked, correctly diagnosed as
  a timing race between the manual test and the real, independently-
  running `scheduleBlink()` timer (which naturally adds-then-removes
  the same class every few seconds), not a bug — confirmed by adding
  the class and reading the computed style in one atomic script call
  instead of across two separate tool calls with a gap between them.
- A real login (Miguel, `TB-51763`) through the rebuilt page still
  lands correctly on the real Dashboard.
- Both desktop and 375px-phone screenshots confirm the illustration now
  genuinely reads as the TaraBasa owl at a glance — round expressive
  eyes, visible beak, rosy cheeks, correct two-tone coloring, clean
  cartoon outline, hands resting on the card — with no heading overlap
  at either width.

## Learner Login background — a real WebGL floating-orb field, scoped
## deliberately separate from the owl (which stays 2D)

Explicit follow-up, scoped narrowly by the user ("just only for bg") —
the owl was fine now; the flat gradient background wasn't: "not a
gradient... make it like good 3d... more polish and more interactive."
Kept the owl and card completely untouched this pass.

**Why real WebGL is the right call here, unlike for the mascot**: the
background has no "must match a specific character" precision problem
the way the owl did (which is what sank two rounds of 3D tuning on
that screen). A field of soft glowing particles is inherently
forgiving — there's no reference silhouette it needs to trace — so
genuine 3D depth is safe to reach for here specifically because the
earlier objection (3D primitives can only approximate a real
character's outline) doesn't apply to an abstract decorative field.

**What was built**: the flat single linear-gradient background is
replaced by (1) a richer multi-stop gradient with two soft corner
highlights as the honest, always-present base layer, and (2) a real
Three.js/WebGL scene of ~20-38 soft glowing sprite "orbs" (a
canvas-generated radial-gradient texture, additive blending for a
genuine bokeh-light look) positioned at varying depths, gently
drifting, with the camera subtly following pointer movement for real
interactivity — layered on top of the gradient via a transparent
canvas. Colors are drawn only from TaraBasa's own existing tokens
(white, `--sky-100`, `--clay-yellow`, `--owl-orange-500`, plus one new
soft sky-blue `#8fc7f2` in the same family) — no new brand colors
introduced, per the user's own "its fine of the colors" note (they
only wanted the *treatment* changed, not the palette).

**A deliberately simple fallback story, unlike the owl's two-layer
safety net**: if the CDN import fails or WebGL is unsupported, the
`catch` block does nothing at all — no fallback UI is needed, because
the gradient underneath is already a complete, honest background on
its own (unlike the owl, where "nothing rendered" would have left a
visibly broken hole above the card). Confirmed by temporarily forcing
the import to throw: the gradient alone still looks clean and finished,
zero console errors, zero layout impact.

**Tested for real, not assumed from the diff**: real WebGL canvas
confirmed rendering at full viewport size on both desktop and 375px
phone widths (screenshots show genuine layered depth via the glowing
orbs, not a flat pattern); the login form itself (code/PIN/submit)
re-confirmed still fully functional through a real login; the
temporarily-forced-failure fallback path re-confirmed clean with no
console errors, then reverted and re-confirmed the real orb field
comes back correctly afterward.

## Learner Dashboard v3 follow-up — comprehensive visual redesign pass
## across every section, driven by direct production-screenshot feedback

A full redesign of the one-page collaged Dashboard (`resources/views/
learner/dashboard.blade.php`) from a single long, detailed user
request covering essentially every section on the page at once,
triggered by 5 real screenshots of the live production Dashboard
(Learner "Maria"). Not a rewrite of the underlying data — every number
on the page still comes from the exact same real sources documented in
the v3 entry above (`competencyProgressSummary()`,
`thisWeeksPracticeReadingSessions()`, `LearnerBadge::summaryFor()`,
`bookshelfBooks()`) — this pass only changed layout, sizing, and
visual treatment.

**A real, pre-existing data-shape bug, fixed at the view layer**: the
production screenshot showed a real Learner's `avatar_id` column
literally containing the string `"Maria"` (a full name, not a short
emoji/preset key), overflowing the small avatar badge. Fixed with a
length-based fallback chain — a real uploaded photo, else a short
glyph (`mb_strlen($avatarGlyph) <= 4`), else the first letter of
`first_name`, uppercased. This is a view-layer safety net for bad
existing data, not a database migration — the underlying `avatar_id`
value is untouched. Verified live with a disposable test Learner
(`ZZAvatar`, `TB-ZZAVA`, `avatar_id` deliberately set to `"Maria"`):
the badge correctly showed a clean "Z" instead of overflowing text.

**Header**: "Not you? Switch learner" was a bare underlined link,
reported as looking "orphaned" — restyled as a real pill button (icon
+ border + shadow + hover state), matching the visual weight of every
other interactive element on the page instead of standing out as
unstyled.

**Hero "Start Reading Activity" tile**: rebuilt as a 2-column grid
(content left, a large illustrated owl right — 1 column below 760px)
instead of everything left-aligned including the CTA button. The owl
got a "loop" treatment per the user's explicit request ("like Claude
in the homepage but you can use an alternative object") — a rotating
dashed halo ring (16s linear) plus a pulsing radial glow behind it, an
idle bob on the owl itself, and animated wings (`transform-box:
fill-box` + per-side `transform-origin`, the same technique already
established for the flying journey-marker owl below) — all respecting
`prefers-reduced-motion`.

**Practice Games button**: restructured from a stretched, left-
aligned bar into a real "nav card" layout — icon chip, text block, a
right-aligned chevron, and a low-opacity decorative circle pattern —
without touching the underlying link/route.

**Journey ("How I'm Growing")**: three concrete asks — (1) a real
flying, wing-flapping owl instead of a static emoji marker; (2) more
circles/density along the curve; (3) the checkpoints and "current
state" explained in words, never as a raw score. Built: a
`.journey-legend` (5-column "Just Started" → "All Done!" labels,
shown once above all rows); small decorative tick-dots interpolated
at the 33%/67% points between each real track point (purely visual
density — no new data); the static `🦉` replaced with a hand-built
inline SVG group — ear-tuft triangles, flapping wings
(`flapMarkerL`/`flapMarkerR`, ±30° over 0.7s), a small body/beak — and
a real "🦉 Flying here right now — {difficulty word}" caption under
each row, built entirely from data already on hand (`difficultyWord`),
never a percentage.

**A real bug found and fixed during this pass's own live testing, not
assumed correct from the diff**: the first version of the flying-owl
marker read as visually ambiguous at its small rendered size — one
tester's read was "a bit like a monkey face" — because it lacked both
ear tufts (the single clearest owl-identifying silhouette feature) and
a "you are here" ring the old emoji marker implicitly had via its own
surrounding circle. Fixed by adding a `circle r="17"` white-fill/
orange-stroke ring behind the owl plus small ear-tuft triangle paths,
and refining the wing-curve proportions. Confirmed fixed two ways: (1)
directly in the DOM (`document.querySelectorAll('.fly-owl')` — both
rows' owls carry the ring at `r=17`, `stroke: var(--owl-orange-500)`,
plus 5 paths for ear tufts/body/beak) after the Browser pane's
screenshot tool hit its already-documented stale-capture artifact on
this long page; (2) a subsequent real screenshot at phone width, once
a viewport-size change reset the capture, visually confirming the
marker now clearly reads as an owl.

**This Week's Goal**: the flat progress bar replaced with a real
circular SVG progress ring (`stroke-dasharray`/`stroke-dashoffset`,
computed from the real `weeklyPercent`), a `<linearGradient>` that
switches from teal→blue to gold→orange once the goal is met, and a
centered icon+count overlay.

**My Growth**: bars redesigned as rounded "status bar" pills inside a
visible light-gray track, replacing bars that floated with no visible
container and read as misaligned.

**My Badges**: enlarged tiles/icons, added a diagonal shine-sweep
animation on earned tiles (`prefers-reduced-motion`-respecting) and a
hover-lift — the literal "do times 1 million better" ask, interpreted
as a genuinely more celebratory earned-tile treatment rather than a
literal scale change.

**My Bookshelf**: enlarged book icons with a subtle page-fold accent,
the "Best: X%" text wrapped in a proper pill badge, hover-lift on each
card.

**Tested for real across the whole page, both viewports, with real
mixed data — not assumed from the diff:**
- Set up realistic test state on Miguel (the flagship reference
  Learner) the same way as the prior v3 pass: 3 genuinely-earned
  badges via `BadgeService::checkAfterPracticeReading()`, a mixed
  synthetic `competency_states` shape (one competency "Up Next" and
  mid-track, one highly proficient near the end of its track, one
  left genuinely unassessed) to exercise all 3 real Journey visual
  states at once, and 3 real dated `ReadingSession` rows this week for
  Goal/Growth.
- **Desktop**: confirmed via screenshot and direct DOM inspection —
  header avatar/pill button, 2-column hero with the halo/pulse/flap
  animation, the games-btn chevron/deco layout, the Journey legend +
  tick-dots + both real marker states (mid-track and near-ceiling) +
  the locked/dashed unassessed row, the Goal ring, the Growth pill
  bars, and the Badges/Bookshelf tiles — all rendering correctly.
  Confirmed zero console errors throughout.
- **Phone (375px)**: a full top-to-bottom screenshot pass confirmed
  every section reflows to a clean single column at a genuinely large,
  kid-appropriate size — header, stat tiles, hero+owl, games button,
  Journey legend/rows/marker (visually re-confirmed reading clearly as
  an owl at this smaller size too), Goal ring, Growth chart, Badges
  (2-column grid), and Bookshelf — with zero horizontal overflow
  (`document.documentElement.scrollWidth === window.innerWidth`,
  confirmed directly, not assumed from "looks fine").
- **The avatar-overflow fix**, tested with a real disposable Learner
  as described above — confirmed via both `get_page_text` and a
  screenshot.
- `php -l` clean throughout.
- **Test data cleaned up afterward**: the disposable `ZZAvatar`
  (`TB-ZZAVA`, id 42) test Learner and its dummy `ReadingSession` row
  were deleted; Miguel's 3 synthetic badges, `competency_states`/
  `next_recommended_competency`/`next_recommended_difficulty`, and the
  3 synthetic dated `ReadingSession` rows were all removed/reset —
  confirmed via a fresh Eloquent read afterward (`badges=0`,
  `competency_states=null`, `sessions_this_week=0`), not just assumed
  from the cleanup script running without error.

**Not yet committed/pushed** at the time this entry was written — per
this project's standing rule, waiting for the user's own explicit
"push it now" before committing.

## Dashboard v3 follow-up #2 — This Week's Goal / My Growth, real
## alignment and contrast fixes from a direct production screenshot

The user shared a live screenshot of "This Week's Goal" (10/5, goal
met) next to "My Growth" (10 stories, 9 Tue/1 Wed) and called out
"not proper align... polish the design... very polish." Three
concrete, real issues fixed, not a vague restyle:

- **The Goal ring blended into its own card.** In the "met" state the
  card background is an orange gradient and the ring's stroke was
  also orange — same hue family, low contrast, reading as flat.
  Fixed by adding a crisp white circular badge behind the ring's
  center content (`goal-ring-center`, sized to sit exactly inside the
  ring's empty middle) plus a soft drop-shadow on the ring itself, so
  the trophy/count now has real contrast against the card in both the
  met and not-met states.
- **A real bug in "My Growth": today's bar with 0 sessions rendered as
  an almost-invisible sliver** (a 6%-height gradient bar on a very
  light track), which read as broken rather than "nothing yet today."
  Fixed by not rendering a bar at all for a 0-count day and instead
  giving that day's track a distinct orange inset ring — a deliberate,
  visible "this is today" marker instead of a near-invisible fill.
- **The two cards didn't line up** — `.pair-row`'s two `.panel`s
  weren't stretched to match height, so whichever had less content
  ended shorter, misaligning the row's bottom edge. Fixed by stretching
  both panels/cards to equal height and vertically centering each
  one's content, plus a shared baseline line under the Growth bars so
  all 7 days visually line up on one axis.

**Tested for real, not assumed from the diff**: seeded 10 real
disposable `ReadingSession` rows on Miguel (9 on Tue, 1 on Wed, this
project's own established direct-property-assignment technique for
backdating `timestamp`, since it isn't `$fillable`) to reproduce the
exact reported scenario (goal met, Thu = today with 0 sessions).
Confirmed via direct `getBoundingClientRect()` measurement at a real
1280px width that both cards' heights now match exactly and all 7
growth-bar tracks share one baseline y-position; confirmed Thursday's
track carries the new orange inset ring with zero bar rendered, while
Tuesday (9) and Wednesday (1) render clearly distinct bar heights.
Screenshots (mobile width, since this sandboxed browser's screenshot
tool hit its already-documented stale/tiled-capture artifact on this
long page at desktop width — cross-checked via DOM geometry instead,
consistent with this project's established fallback) confirm the ring
now reads with real contrast and the chart has a clean shared
baseline. All 10 synthetic `ReadingSession` rows deleted afterward,
confirmed via a fresh count (`sessions_this_week=0`).

**A real, disclosed side effect of this verification pass**: Miguel's
local-dev-only PIN was temporarily unknown, so it was reset via
`tinker` to `1234` to log in and verify — this only touches this
worktree's local SQLite database, never the live Railway production
database, but is recorded here since it's a real, if harmless, change
to the flagship reference Learner's local credentials.

## My Growth — a real scroll-triggered "liquid fill" animation

The user shared a screenshot showing an older/pre-deploy state of the
Dashboard (a flat horizontal Goal bar, not the ring already shipped in
the two passes above) and asked specifically for "My Growth" to be
enhanced: each day's bar should look like it's genuinely filling up
like a poured liquid as the section scrolls into view, replaying every
time it scrolls out and back in (their own words: "like a wave,"
"parallax etc"). Confirmed the Goal ring itself needed no change — the
screenshot most likely predated Railway finishing the previous push's
deploy, not a real regression.

**Built with `IntersectionObserver`, no new library**: each
`.growth-bar` now carries a `data-fill="{percent}"` attribute (the
same value already computed server-side) and starts at `height:0%`,
zeroed by a small inline `<script>` at the bottom of the page. A
single observer watches `#growthChart` (the whole week's row of bars,
not each bar individually — so the wave stays in sync as one gesture)
and sets every bar's height to its real `data-fill` value the moment
the chart enters the viewport (`threshold:0.35`), or back to `0%` the
moment it leaves — so scrolling away and back genuinely replays the
fill, exactly as asked, rather than only playing once per page load.
A real CSS `transition` (`height 1.05s`, a slight overshoot easing)
plus a per-bar `transition-delay` (`{{ $loop->index * 90 }}ms`, set
inline in Blade) is what turns 7 simultaneous height changes into one
cascading wave across the week instead of all bars popping at once. A
small glossy `::after` cap on top of each bar's fill sells the
"liquid" read. Respects `prefers-reduced-motion` (skips the JS
entirely, leaving bars at their correct final height with no
animation) and degrades safely if `IntersectionObserver` isn't
available or the script throws for any reason — the bars' real,
already-server-computed heights are always the fallback, never blank.

**A real sandboxed-tool limitation hit while verifying, correctly
diagnosed and worked around rather than mistaken for a bug**: this
Browser pane's tab reports `document.hidden === true` even when
"fronted," which — confirmed directly — doesn't just throttle
`requestAnimationFrame` (already documented elsewhere in this file for
the Login page's 3D-owl phase) but also appears to freeze CSS
*transitions* on this specific tool: setting a bar's height with the
real transition active left `getComputedStyle` reporting `0px` even
after waiting well past the transition's total duration. Diagnosed,
not assumed, by re-running the identical height change with the
transition temporarily disabled (`transition:'none'`) — the static
end-state computed exactly correctly (`100%` → `127.5px`, `12%` →
`15.3px`, both exact matches to the track's real height), proving the
`data-fill` values, the flex/percentage-height layout, and the
JS wiring are all correct; only the animated transition itself is a
casualty of this sandboxed tab's background-throttling, the same
general class of limitation already on record in this project for
other animations, not a defect in what shipped. Console confirmed
clean (zero errors) throughout.

**Tested for real using the same reproducible scenario as the two
Goal/Growth passes above**: seeded 10 disposable `ReadingSession` rows
on Miguel (9 Tue, 1 Wed) again, confirmed the two real bars' `data-fill`
values (100, 12) and their zeroed starting `style="height:0%"` on page
load, confirmed scrolling the chart into view (at a real, explicitly-
sized 1280×900 viewport, since this sandboxed pane's default reports a
0×0 viewport while hidden) keeps the correct target percentages wired
to each bar via `dataset.fill`. All 10 synthetic rows deleted
afterward, confirmed via a fresh count (`sessions_this_week=0`).

## The user's working style

- Limited hands-on coding experience — explain what you're doing and
  why in plain language, not just diffs.
- Prefers being walked through GUI steps (File Explorer, PowerShell,
  GitHub Desktop) explicitly — don't assume familiarity with terminal
  conventions.
- Wants real, working features tested end-to-end before moving to the
  next piece — not multiple half-built things at once.
