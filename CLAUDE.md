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

## The user's working style

- Limited hands-on coding experience — explain what you're doing and
  why in plain language, not just diffs.
- Prefers being walked through GUI steps (File Explorer, PowerShell,
  GitHub Desktop) explicitly — don't assume familiarity with terminal
  conventions.
- Wants real, working features tested end-to-end before moving to the
  next piece — not multiple half-built things at once.
