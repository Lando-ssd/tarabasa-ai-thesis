# TaraBasa AI: project context for Claude Code

Read automatically at the start of every session. It is the SHORT, CURRENT version (rewritten 2026-11-18).
The long log of every decision, test and bug from August to November 2026 is `docs/HISTORY.md` (the old
CLAUDE.md, word for word). If this file and the history disagree, this file and the code win.

Other pages that are kept current:
- `docs/FINISH-CHECKLIST.md`: what only a person can still do (tests with real children, settings, manuscript).
- `docs/KEEP-IT-ALIVE.md`: what runs where, who owns each account, what can break it, a monthly routine.
- `mobile/HANDOVER.md`: how to build the phone app and what each version contains.
- `docs/legal/LEGAL-RISK-REGISTER.md` and `docs/legal/DATA-INVENTORY.md`: the legal list and the facts behind it.
- `docs/README.md`: which reference folder is current and which is stale. Read it before using anything in `/docs`.
- `/how-it-works` (public page, `HowItWorksController`): every rule the app uses, with the numbers read from the config.

## What this project is

TaraBasa AI is an adaptive reading intervention platform for Grade 1 to 3 Filipino learners. It is a capstone/thesis
project by a 4 person team (BS Information Technology, University of Cebu Banilad). Four actors: Learner, Teacher,
Parent, Admin. Website for all four; the phone app (Android) covers Learner, Teacher and Parent. Admin is website only.

"AI" in this project means: a speech recognition service (Vosk, run by a teammate), an activity writing service
(Google Gemini, run by a teammate), a formula based recommender (run by a teammate), plus WRITTEN RULES in this
repository (mistake patterns, alerts, progression, "can this child read it", generation basis). **Nothing was
trained and there is no dataset of children's recordings.** Say this plainly whenever AI comes up. The numbers in
the rules are the team's own choices; they are in `config/*.php` so a teacher or the adviser can change them.

## How to work with the owner (these override convenience)

- The owner is a beginner with git and code. Explain in plain language. Walk through GUI steps. Short, honest status.
- **Never commit or push without the owner's explicit word.** Never merge into the live branch without it.
  Before any merge: tests pass on the merged code. After the deploy: check the live pages from outside.
- Work step by step in the order agreed. Do not start a new task while agreed steps are open.
- Never say "ready" while gaps remain. End an audit with three lists: fixed / still open / only a real test shows.
  The app has only ever been tested with computer voices: no real child, microphone or class has used it.
- Show an approval preview (a published Artifact mock) BEFORE a redesign. Adult roles get a calmer look than the Learner.
- A new phone build (APK): say what it contains first. Do not send or install anything for the owner.
- Do not create accounts on the LIVE site. Do not read `.env` values (the tool blocks it and it is never needed).
  Never put a real password in a file or a chat. Never commit `client_secret*.json`, key files or `.env*`.
- Every feature needs a basis the adviser can accept (MATATAG curriculum, DepEd Phil-IRI, a real study). Write to the
  adviser respectfully, never as a command. Do not invent citations; mark what was only found by search.
- Copy rules for anything a user reads: no emoji, no dashes used as punctuation. (Avatars the child picks are data, not decoration.)
- The tech stack below is confirmed. Do not change it without asking.

## Tech stack (confirmed)

- **Backend:** Laravel 13 on PHP 8.4 (production image `php:8.4-cli`; local Windows PHP is 8.5 at `F:\php`). Blade pages with plain CSS in `public/css` (no Tailwind or Vite in the pages).
- **Database:** TiDB Cloud Starter (MySQL compatible, Singapore, free) in production; SQLite (`database/database.sqlite`) locally; tests use SQLite in memory. Never point `RefreshDatabase` at a real database (it drops every table). Sessions are in the database.
- **Hosting:** Render free web service `tarabasa-ai`, Docker, region Singapore, https://tarabasa-ai.onrender.com. It sleeps after about 15 quiet minutes (about a minute to wake) and has 512 MB and no persistent disk. Railway was used before (trial ended 2026-10-05); `render.yaml` is the blueprint. Health check path is intentionally EMPTY in the Render dashboard.
- **Auth:** Teacher, Parent, Admin use session login on the website and Sanctum bearer tokens on the phone (`/api/auth/*`). A Learner has its own `learner` guard (code + 4 digit PIN, no email). `EnsureTokenActor` (`token.actor:learner|teacher|parent`) fences which token may reach which API group.
- **Email:** Gmail API over HTTPS (`MAIL_MAILER=gmail-api`, sender `tarabasaai.noreply@gmail.com`, token in `GMAIL_SEND_REFRESH_TOKEN`). NOT SMTP (Render free blocks the ports), NOT Brevo, NOT Resend. If the token expires: Admin, System health, Reconnect Gmail.
- **Teammate services (owner BldZeuz, free Render services, they sleep too):** Reading-api (Vosk, has NO password), the activity generator `gemini_activity_gen` (`X-App-Key`), `Adaptive_Recommendator` (deployed as v1.0.0; the app speaks v1 and v2, picked from the service's `/health`). Never rebuild their logic in PHP: call them over HTTP through `ReadingAiClient`, `ActivityAiClient`, `AdaptiveRecommendatorClient`.
- **Phone app:** `mobile/`, Expo SDK 57, React Native 0.86, TypeScript. Plain navigation in `App.tsx`/`Main.tsx`/`AccountApp.tsx` (NOT Expo Router, although the template's `mobile/AGENTS.md` says so). Learner, Teacher and Parent screens. The manuscript still says Kotlin: a document edit for the team.
- **No third parties:** no analytics, trackers, embeds or ads; fonts are self hosted (`public/fonts`); exactly two cookies (session and XSRF). A visitor's browser makes one bare health request to the teammate services to wake them (disclosed in the policies). Adding anything that sends data elsewhere means changing the legal pages in the same commit.
- **Version control:** GitHub `Lando-ssd/tarabasa-ai-thesis` (public).
  - Render deploys ONLY `claude/admin-dashboard-approvals-62dcd0` (the "live branch"). Every push to it rebuilds and restarts the site.
  - Day to day work is on `mobile-app`; it has been merged (fast forward) into the live branch each time the owner said so. On 2026-11-18 both were at the same commit.
  - `main` is the old August state, far behind. Do not branch from it or clone it for work.
  - Other worktrees exist (`admin-dashboard-approvals-62dcd0`, `affectionate-mcclintock-6650cb`) and may hold unpushed work: check before claiming something is missing.
- **Windows PHP and HTTPS:** the local PHP needed a CA bundle (`F:\php\extras\ssl\cacert.pem`, set in `php.ini`) for outbound HTTPS. Never disable SSL verification. The production Dockerfile installs `ca-certificates` for the same reason.

## Run it, test it

```bash
php artisan serve --port=8123                      # website (launch.json: laravel-admin-test)
php artisan migrate                                # also needed after pulling new migrations
php artisan queue:work --queue=scoring,default     # background scoring and activity writing (without it the waiting page does the work after a delay)
php artisan db:seed --class=DemoAccountsSeeder     # local demo Teacher, Parent, child (password is in the seeder header; refuses on production)
php -d memory_limit=1G artisan test                # full suite, 821 tests on 2026-11-18, takes about 150 s
cd mobile && npx tsc --noEmit                      # phone type check
cd mobile && npx expo start --web --port 8081      # phone app in the browser (needs mobile/.env.local with EXPO_PUBLIC_API_URL)
```

Tests write no log (`LOG_CHANNEL=null` in `phpunit.xml`). After editing `phpunit.xml`, re-run the suite: an XML comment may not contain a double hyphen.
Admin login locally: set `ADMIN_PASSWORD` (12+ characters) and run `php artisan admin:sync`.

Production start (`docker/entrypoint.sh`): `migrate --force`, `admin:sync`, a background `services:check`, one `default` queue worker, `SCORING_WORKERS` (default 2) `scoring` workers, then `php artisan serve --no-reload` with `PHP_CLI_SERVER_WORKERS=4`.
**`--no-reload` is required**: without it Laravel ignores the worker setting and runs ONE worker, and every slow request then fails Render's health check (this caused the 502s of 22 to 23 Oct 2026; `ServerStartupTest` pins it). The deploy log must NOT contain "Unable to respect the PHP_CLI_SERVER_WORKERS".

## Where the logic lives

- **Controllers are thin.** The rules live in `app/Services/*` and `app/Support/*`, and BOTH the website and the phone API call them, so the two cannot disagree. Examples: `LearnerReadingService`, `LearnerDiagnosticService`, `LearnerAuthService`, `AccountAuthService`, `TeacherClassService`, `TeacherActivityService`, `ParentChildService`, `PracticeGameService`, `DataErasureService`, `LearnerPinService`, `BadgeService`, `ReadingProgression`, `TeacherAlerts`. When adding a feature for both web and phone, put the rule in a service and call it from `Controllers/*` and `Controllers/Api/*`.
- **Every tunable number is in `config/`** and the public page reads it: `progression`, `activity_fit`, `reading`, `reading_words`, `practice_games`, `scoring`, `diagnostic` + `diagnostic_bank`, `syllable_method`, `practice_sentences`, `teaching_path`, `badges` + `badge_icons`, `reading_goals`, `matatag_subdomains`, `legal`, `activity_levels`. The phone app is TypeScript and cannot read them; `PracticeGameRulesTest` fails if the phone's copies drift.
- **Background scoring:** the website's recordings are saved to a temporary file and scored by a worker (`BackgroundScoring`, `ScoreRecordingJob`, table `scoring_jobs`); the page polls `learner/scoring/{id}/status`. Phone scoring and the games' word checks still run inside the request, behind `LimitScoringWorkers` (2 at once, then a polite 503). The recording is deleted when scoring ends.
- **Activity generation** is a background request too (`ActivityGeneration`, `GenerateActivitiesJob`); one credit is charged only when activities are saved.
- **Middleware aliases** are in `bootstrap/app.php` (`admin`, `teacher.active`, `learner.diagnostic`, `learner.diagnostic.api`, `token.actor`, `scoring.slots`). Global: `CanonicalHost`, `RejectNestedQuery`, `SecurityHeaders`.
- **Phone sizes** all go through `s()` in `mobile/src/scale.ts`; spacing uses the `space[...]` ladder in `theme.ts`; tap targets never under 48dp; no emoji. See `mobile/HANDOVER.md`.

## Domain rules in force (each has tests; details and basis in `/how-it-works` and `docs/HISTORY.md`)

- **One grade per class, no exception.** A Grade 1 class takes only Grade 1 children, and an activity can only be given to classes, groups and learners of its own grade (`GradeMatch`, `SchoolClass::acceptsGrade`). A teacher who handles several grades opens one class per grade. A DepEd "multigrade class" is deliberately NOT modelled. Adding a grade as an Active teacher is a REQUEST the Admin confirms (`GradeChangeService`).
- **Adding a learner:** the teacher types only the last 5 characters of the Learner Code (`TB26-48293`; old `TB-XXXXX` codes still work) or scans the QR (it holds only the code, never the PIN). The window checks the code BEFORE Add (`TeacherClassService::check`); refusals are rate limited; the parents are told.
- **Reading levels, two vocabularies.** Stored: `learners.mastery_level` (Beginning/Developing/Proficient) and `reading_rung` (0 to 6). Teachers see Phil-IRI names (Non-reader, Frustration, Instructional, Independent, DepEd Order 14 s. 2018). Children and parents see four steps (Letter Explorer, Word Builder, Sentence Reader, Story Reader). One reading never moves a level: it takes three strong readings of at least two activities, one rung at a time; a move down needs two weak readings in a row (`ReadingProgression`, `config/progression.php`).
- **First reading check:** a curated, curriculum coded bank (`config/diagnostic_bank.php`, letters in `config/diagnostic.php`), NOT the generator. It starts where the Parent's description says the child is, not at the grade; at most 3 items; letters move up at 5 of 6; no score is shown to the child. Texts were written by the team and still need a Grade 1 and Grade 2 teacher's review.
- **Scoring fairness:** `SpeechNormalizer` (same sound words, numbers), `LetterNames` (letter names the recognizer writes as other words), `NotSure` (faintly heard words are left out). Reading-api's `constrain_vocabulary` was tested and REJECTED (it forgives real mistakes).
- **Mistake patterns** (`ErrorPatterns`, 10 kinds incl. the syllable method's "Longer words in pieces"), the word bank (`WordBank`), "can this child read it" (`ActivityFit`: too long cannot be assigned, no override), "write it for a class" (`GenerationBasis`), "did it help" (`ClassActivityOutcomes`). All rule based.
- **Practice Games are free play** (no points, streak or level): Balloon Pop, Word Train, Sentence Stars, Syllable Steps, all read aloud. Word Builder and Letter Match were removed (old links redirect). The ten Games badges are earned through `game_plays`.
- **Pending Teacher:** can sign in after confirming the email and can ask for activities with 2 free credits, but anything touching real students needs Admin approval. **Email must be confirmed before sign in** (`REQUIRE_VERIFIED_EMAIL`, default true). Admin has no self registration (one account, `ADMIN_PASSWORD` from the environment).
- **Privacy and consent:** recorded consent boxes (`consent_records`), self hosted fonts, no third parties, child photos stored in the database (`learner_photos`, private route, 404 for everyone not allowed), Teacher/Parent own photos (`user_photos`, private). Anyone can delete a child or an account in the app (`DataErasureService`; an Admin can for someone who cannot sign in). A forgotten child PIN is reset only by a linked guardian with their password (`LearnerPinService`; no easy PINs: `PinRules`, also at creation).
- **Time:** days, weeks and hours go through `LearnerClock` (Asia/Manila). `config/app.php` stays UTC.

## Design and UX rules

1. **Look in `docs/README.md`, then `docs/actor-prompts/`, before building any user facing flow.** Do not improvise login, registration or approval behaviour. Find the matching HTML prototype in `docs/design-reference-html/` before styling a screen.
2. **"Reference and enhance".** Use the prototype as the foundation and improve it (spacing, type, states, hover, transitions). Do not invent something unrelated. Where there is no prototype, be as ambitious as when enhancing one. A mic button and a label is not a finished screen for a 6 year old.
3. **Learner typography floor:** h1 at least 25px, body at least 16px (18px is used), the passage a child reads at least 19px (23px used) with line height 1.7. Smaller is a regression.
4. **Design tokens** are fixed: see the `:root` variables in `layouts/auth.blade.php` and `public/css/*-app.css`. Adults get calmer screens with a left menu (bottom bar on a phone); the Learner look is warmer.
5. **Windows, not scrolling, for Teachers:** every Teacher action opens in a window (a bottom sheet on a phone) and each screen should fit one laptop view.
6. **Build in small vertical slices** (migration, model, service, controller, route, view, test) and say what is done and what is deferred.
7. A page that is waiting for something slow must SAY it is working (waiting screens, "Still working", Send Again). Never leave a blank page or a 502.

## Traps that already cost time (do not repeat)

- `Parent` and `Class` are PHP reserved words: the models are `ParentAccount` (table `parents`) and `SchoolClass` (table `classes`). Never rename them.
- **Never edit a shipped migration.** Add a new one. Two migrations rewrite data (word bank, Repository listings) and some add unique indexes.
- `where('col', $x)` with `$x === null` becomes `IS NULL` and can match unrelated null rows (a real access bug, fixed once). Guard it.
- A column missing from `$fillable` is silently dropped by `create()/update()` (`claimed_at`, `timestamp`). Check the database after a write, not the return value.
- PHP's array `+` keeps the LEFT value for a repeated key: use `array_merge` when the right side must win (a real bug in the Parent API).
- A short, generic CSS class name can collide with another rule on the same page (`.sub`). Prefix new status classes (`st-`, `rv-`).
- `Http::fake` keeps the FIRST answer registered for a URL; register one fake that returns a changing value.
- An unnamed `Route::redirect` inside a named group takes the group prefix as its name: name it explicitly.
- A phone recording must not be sent as an open file handle that is never closed (blocks deletion on Windows); read it into memory.
- Laravel `artisan serve` ignores `PHP_CLI_SERVER_WORKERS` without `--no-reload`. A Render service cannot wake another sleeping Render service; the visitor's browser does it.
- A browser tab that is hidden freezes `requestAnimationFrame` and CSS transitions (test panes report `document.hidden`), and `background-attachment: fixed` makes some screenshots stale. Verify by measuring the DOM, not by trusting one screenshot.
- Windows long paths break the Android build from the repo folder: build from a short path copy (`D:\tb`). See below.
- The Reading service, generator and recommender are free hosted and may be asleep: the first call can take a minute. Call sites must degrade, never crash.

## Phone app: build and release (Windows)

- Build tools live outside the repo: Java 17 in `D:\tools\jdk-17`, Gradle cache `D:\tools\gradle-home`, Android SDK in `%LOCALAPPDATA%\Android\Sdk`. `mobile/android-env.local.ps1` (untracked) sets them for one PowerShell window.
- Build from a plain copy in `D:\tb` (robocopy the source, `npm ci`, `npx expo prebuild`, then `gradlew assembleRelease -PreactNativeArchitectures=arm64-v8a`). About 15 to 50 minutes. If clang crashes for lack of memory, retry with fewer cores (`--max-workers=1`, `-Xmx1536m`, limit processor affinity).
- Output goes to `D:\tools\apk-out` (APK, `.sha256.txt`, tester pack zip). Check inside the file: package `com.tarabasaai.app`, versionName, the live address and no `localhost`. Bump `mobile/app.json` (version, versionCode) and `mobile/src/config.ts` (`APP_VERSION`) together and add a section to `mobile/HANDOVER.md`.
- Release signing for the store uses an upload key that is NOT in the repo (`mobile/plugins/with-upload-signing.js`, `mobile/PLAY-STORE-PLAN.md`). A new Play account needs 12 testers for 14 days before a public listing.
- Expo changes every SDK: read the versioned docs (`docs.expo.dev/versions/v57.0.0/`) before touching an Expo API, and trust `tsc --noEmit`.

## Honest limits and provisional decisions (full text in `docs/HISTORY.md`)

- **Activity generation is built around what the teammate's generator can do** (3 grouped competencies, 8 activity types, all 3 levels always written, no per learner word injection). The manuscript's `gameType` model was NOT built. The team and adviser must still accept this. (History: "PROVISIONAL, NOT FINAL, Activity Generation data model conflict".)
- **The cross-grade diagnostic gap is resolved** (the check follows the Parent's profile, a 7 rung ladder); the older note that said otherwise is marked superseded in the history.
- **The adaptive subdomain coverage is partial** (`config/matatag_subdomains.php`): the app can only measure what it has activities and scoring for (word accuracy, comprehension quiz). Needs per subdomain activities from the generator.
- **Reading-api has no password.** Ask BldZeuz. Also ask for a `levels` option on the generator.
- **Synthesized voices only.** Children's voices will make the recognizer write other words; extend `config/reading_words.php` from real recordings. The thresholds (not sure, heard share, move up, alert lines) were set without real recordings.
- **Drag and drop** between levels needs a mouse (touch uses the Move to menu). Uploaded photos live in the database (a base64 row, about 1 MB ceiling): fine for a class, not for thousands.
- **Deviations from the manuscript or actor prompts** (all deliberate): Parent sign up uses First/Middle initial/Last name (not "Full Name"); the Parent chooses a preset avatar and may upload a photo (not a random avatar), with a separate photo consent box; linking an existing child needs the child's PIN as well as the code; Teacher and Parent must confirm their email before the first sign in; the Vosk based reading service replaced Google Speech to Text (the manuscript text still needs this edit); the manuscript's Kotlin and SMS claims do not match what was built (the manuscript update pack lists them).
- **Legal:** nobody on the team is a lawyer. Open items are in `docs/legal/LEGAL-RISK-REGISTER.md` (NPC registration check, a short privacy impact assessment, a breach plan, written agreements with the hosts, a retention rule, a lawyer's reading of the Terms, whether earlier consenting parents must be told recordings now wait briefly in a temporary file).

## State on 2026-11-18

- Live branch and `mobile-app` are at the same commit; 821 tests pass; `composer audit` clean; the live site answers and the Render deploy log shows no worker warning and no "Instance failed" since 23 Oct.
- Render settings done: `PRIVACY_CONTACT_EMAIL`, `LEGAL_OPERATOR_DETAIL`, `LEGAL_ACCOUNTABLE_PERSON`, `API_CONSENT_REQUIRED=true`.
- Latest phone build: **0.6.2 (versionCode 22)** in `D:\tools\apk-out` (`TaraBasa-AI-0.6.2-test22.apk`, tester pack zip beside it), sent by the owner to the teammate; the real-phone test result is awaited (the owner will report, do not ask).
- Clean-up round (owner asked "clean the system and app so there are no errors"): step 1 (phpseclib advisory, class list names, easy PIN at creation) and step A (dead files, route names, test logging) are done and live; step B is this split of CLAUDE.md. Proposed and waiting for the owner: C routine package update (Laravel 13.43, Guzzle 8.5, Sanctum 4.5, Socialite 5.33) with the full tests; D Pint code style (138 files, cosmetic, recommended to skip).
- Still to look at: Render Metrics Memory chart (512 MB limit, 3 queue workers) and a 7 day search of Render Logs for ERROR or Exception.
- Set aside by the owner for later: C: drive cleanup, three credential looking files in Downloads (delete or deactivate those keys), untracked clutter in the repo (`out.txt`, `preview/`, the `docs/manuscript-*` folders), the extra worktrees, `main` versus the long live branch name.
- Manuscript fixes sit with the writer (`docs/manuscript-update`, `docs/manuscript-review-v33`, `docs/manuscript-userguide`, `docs/manuscript-roadmap`).

## Where the old section names went

Code comments and docs cite these titles; search `docs/HISTORY.md` for the quoted words: "Mobile API layer" / "Mobile app, Part 1" (the phone API and the service extraction), "Mobile app, Part 2" (the Expo project and every phone round), "PROVISIONAL, NOT FINAL" (the Activity Generation conflict and the old diagnostic gap), "Real Badges" / "Slice 1: Real Badges", "How to switch hosts: Railway <-> Render + TiDB", "Syllable Steps", "Syllable method", "Legal pages, consent records and no third parties", "The 502s explained", "Recordings are scored in the background", "Phone app: layout pass 2", "Smallest phone fit", "Phone app: the rest of the Teacher screens and the whole Parent side", "Known deviations from the manuscript / actor prompts", "Before pushing this round" (an old checklist, now mostly done).
