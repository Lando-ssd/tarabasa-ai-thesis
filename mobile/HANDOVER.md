# TaraBasa AI mobile app: handover

React Native with Expo, Learner side only (Teacher, Parent and Admin stay on the website).
The phone app talks to the same Laravel server and database as the website through a small JSON API
(`routes/api.php`, Sanctum bearer tokens). It holds no secret keys: only a sign in token after login.

## Where things are
| What | Where |
|---|---|
| Phone app code | `mobile/` (this folder) |
| Phone API (server side) | `routes/api.php`, `app/Http/Controllers/Api/`, tests in `tests/Feature/MobileApiTest.php` |
| The numbers on Home, shared with the website | `app/Support/LearnerHome.php` |
| Full project notes | `CLAUDE.md`, section "Mobile app, Part 2" |

## Run it (browser preview, no phone needed)
1. Install Node 22 and run `npm install` inside `mobile/`.
2. Create `mobile/.env.local` with one line: `EXPO_PUBLIC_API_URL=https://tarabasa-ai.onrender.com`
   (or `http://localhost:8123` if you run the Laravel server on your own computer).
3. Run `npx expo start --web` and open http://localhost:8081.
The browser preview checks sign in and the server calls. It cannot check a real phone's microphone or camera.

## Run it on a phone
- Quick look: install **Expo Go** on the phone and run `npx expo start`, then scan the QR code (same Wi-Fi).
  Expo's current docs say the iPhone version of Expo Go needs a paid Apple account. Android does not.
- Real test file (APK): see below. This is what we send a tester.

## Build an Android APK (Windows)
Needs Java 17 and the Android SDK (Android Studio brings both). Copy `android-env.local.ps1` from a teammate or
write your own: it only sets `JAVA_HOME`, `ANDROID_HOME` and `GRADLE_USER_HOME` for one PowerShell window.
```
. .\android-env.local.ps1
$env:EXPO_PUBLIC_API_URL = 'https://tarabasa-ai.onrender.com'
npx expo prebuild --platform android --no-install
cd android
.\gradlew.bat assembleRelease -PreactNativeArchitectures=arm64-v8a --no-daemon
```
The file is `android/app/build/outputs/apk/release/app-release.apk`. The first build downloads several GB and takes
about 30 minutes; later builds are much faster. `android/` is generated and is not committed.

**Windows long paths.** If the build stops with `ninja: error: Stat(... safeareacontext ...)`, the folder is too deep for
Windows (260 characters). Do not change a Windows setting and do not use `subst` (Node resolves the drive back to the long
path and the build fails with "different roots"). Build from a plain copy in a short folder instead:
`robocopy mobile D:\tb /E /XD node_modules android .expo`, then in `D:\tb`: `npm ci`, delete `.env.local` (it points at
localhost), set `EXPO_PUBLIC_API_URL`, run the prebuild and Gradle commands above. This was needed for
`react-native-safe-area-context` on 2026-10-12 (the 0.2.0 APK was built that way, about 16 minutes).
App id: `com.tarabasaai.app`. The release build is signed with the debug key (fine for tests, not for a store).

## Rules the team agreed (they live in the Claude notes, so they are written here too)
- Show a design preview and get it approved BEFORE building new screens.
- No emoji and no dashes in app text. Plain, warm wording for children. Respectful tone to adults.
- Never call the app a "trained AI". It is rule based and says so. There is no dataset to train on.
- Every feature needs a basis (MATATAG curriculum codes, Phil-IRI, DepEd Orders, or a cited study).
- Never put a secret in git. Never use a real child's name or photo for tests.
- One grade per class, no exception (the website enforces it).
- Do not push to the branch Render deploys (`claude/admin-dashboard-approvals-62dcd0`) without agreeing first.
  Work on a separate branch.

## The screens (version 0.3.0; the first screens were built 2026-10-12 from the approved design preview)
One colour for every child (the website's blue or pink choice is not used on the phone). A white page, a light blue
panel, clay orange buttons, Tara and the star animations from the website, Phosphor icons from the website's sprite.
Sized for a phone: every tap target is at least 48dp (main buttons 56dp), the main action sits at the bottom where the
thumb is, content keeps clear of the notch and the gesture bar, scrolls on a short phone, and stays a readable 560dp
column on a tablet. Fonts are bundled (Baloo 2, Barlow Semi Condensed, Lexend), so text shows without internet.

| Screen | File | Notes |
|---|---|---|
| Sign in (Scan my card, or type the code, then the PIN) | `src/SignIn.tsx`, `src/CardScanner.tsx` | the card holds the code only; the check digit is verified on the phone |
| First reading check | `src/FirstCheck.tsx` | intro, up to 3 items (letters or words), "Great job" between, level and badges at the end |
| Home | `src/Home.tsx` | reading path, days in a row, points, weekly goal ring, week chart, log out |
| Read (list, story, results) | `src/ReadList.tsx`, `src/Story.tsx`, `src/Results.tsx` | Listen, two free practice tries, the real reading, the quiz for comprehension stories |
| Badges | `src/Badges.tsx` | all 100 by group, tap for details |
| Shelf (My Shelf) | `src/Shelf.tsx`, `src/Reread.tsx` | stories really read, times and last day; a free re-read (unscored, nothing saved, no limit on tries) |
| Warm-up and silence check | `src/WarmUp.tsx`, `src/MicButton.tsx`, `src/LevelBar.tsx`, `src/recording.ts` | see below |
| Games (hub, Word Builder, Letter Match, Balloon Pop) | `src/Games.tsx`, `src/WordBuilder.tsx`, `src/LetterMatch.tsx`, `src/BalloonPop.tsx`, `src/SayWord.tsx`, `src/gameKit.tsx` | see "Practice Games" below |
| Bottom bar, back button | `src/Main.tsx` | five places; the bar hides while reading; Android back goes back one step, then Home |
| Shared pieces | `src/ui.tsx`, `src/theme.ts`, `src/Feedback.tsx`, `src/ReadingScene.tsx`, `src/MicButton.tsx`, `src/WordReview.tsx`, `src/Waiting.tsx` | |

Developer tool, phone frame: in the browser preview (`http://localhost:8081`) a wide window shows the app inside a drawn phone with
buttons for a small (360x640), common (390x844) and large (412x915) phone and a tablet, so the real screen size is clear
(`src/PhoneFrame.tsx`; `?frame=off` turns it off; a narrow window or a phone shows the app directly).

Developer tool, gallery: in the browser preview open `http://localhost:8081/#gallery=results` (also `home`, `badges`,
`check-intro`, `check-letters`, `check-next`, `check-unclear`, `check-done`, `practice`, `quiz`, `waiting`, `failed`,
`crash`, `soon`) to see one screen with sample data at any phone size (`src/devGallery.tsx`, never in a phone build).

App icon and splash are made from the website's real logo by `node tools/make-icons.cjs` (it cuts the owl out of the
blue tile). Icons are drawn from `src/iconPaths.ts`, generated by `node tools/make-icon-paths.cjs` from
`public/icons/badges.svg`; run it again if the website adds an icon.

## Silence check and warm-up (2026-10-12, second round)
- **Before a recording is sent** (every reading screen) the phone checks it with the recorder's own level (`isMeteringEnabled`,
  read ten times a second): under 1 second is "very short", and a recording whose loudest moment never reached -45 dB is
  "we did not hear anything". Nothing is sent in either case; a calm note appears under the microphone and it is ready again at
  once. With no level at all the recording is allowed through (the server still catches silence), so a missing meter can never stop a
  child. The numbers are in `src/recording.ts` (checked by `node --experimental-strip-types tools/test-recording.mjs`). They were set
  from how the values behave (Android reports the loudest moment since the last reading, the browser and iOS an average), NOT from a
  real child on a real phone: if the phone test finds a false "we did not hear anything", change `SILENT_DB` first.
- **Warm-up** before the very first item of the first check: say one easy word ("sun", or "A a" when the check starts on letters),
  with a live voice meter, and hear "I heard you! Your microphone works." The level is measured on the phone, nothing is recorded for
  scoring or sent. It listens up to 4 seconds and stops by itself after a few readings of a voice. "Skip This Part" is always there.
  Same idea and wording as the website's warm-up.

## Practice Games (2026-10-12, third round)
Same rules as the website, and the same server logic (`app/Services/PracticeGameService.php`, shared by the website's
`GameController` and the phone API). Free play: no points, no streak, no level change.
- **Three games, three levels each.** The child starts at the level of their grade (Grade 1 starts at 1, Grade 3 at 3); a round with
  3 mistakes or fewer (Balloon Pop: 2 or more words popped on the first try) climbs one level; up to 3 rounds, or one round once on
  level 3. A finished game reports once (`POST /api/learner/games/{game}/finish`, `game_plays`), and that is what earns the ten Games
  badges. The report can never break a game: if it fails the child just sees no new badge.
- **Word Builder** (tap letters in order; Listen says the word; an optional "Now say it out loud"), **Letter Match** (cards say their
  letter by NAME when turned over, "ay" not "a"), **Balloon Pop** (five balloons, three marked in turn, say the word to pop it: two
  tries, "Tara is not sure" is never "wrong", then it pops anyway). The word lists come from the child's own missed words first, then
  words that practise their kind of mistake, then the level's list (`GET /api/learner/games/words`).
- **Saying a word** (`SayWord.tsx`): about 3 seconds, one word. The phone first checks that a voice was heard (nothing is sent and no
  try is used if not), then the clip goes to `POST /api/learner/games/check-word`, which answers only `heard`, `again` or
  `unavailable`, never "wrong", and keeps nothing. `unavailable` (blocked or missing microphone, service unreachable) turns Balloon Pop
  into tap-to-pop so a game can always be played; Play Again tries the microphone again.
- **Leaving and coming back** keeps the board (Word Builder, Letter Match) for as long as the app stays open.
- **Sounds** are the website's three Mixkit free licence files (`assets/sounds`); a wrong match is played quietly on purpose.
- **Browser preview results (not a real phone):** all three games were played to the finish against the local server with a
  synthetic microphone: levels climbed 1 to 3, the finish report wrote `game_plays` rows and returned real badges, a silent
  microphone sent nothing, "again" twice popped the balloon anyway, "unavailable" switched to tap-to-pop. Layout was checked at
  390x844 and 360x640 (the short phone moves "Hear the word" beside the word so all five balloons stay in view).
- **NOT proven yet (a real phone shows it):** the phone's own read-aloud voice for words and letters (`expo-speech`), the game sounds on
  a device, saying a word with a real child's voice, and how long a word check takes on mobile data (about 10 s against the live
  reading service when it is awake, up to a minute and a half when it is asleep).

## Status (2026-10-12)
Done: everything in the table above, tested in the browser preview against the local server with the live reading
service (sign in, the first reading check including letters, a real scored reading, Home and Badges updating) and with
sample data at 360x640, 393x852 and 768x1024. The phone API gained badge icons and groups, a safe new-badges list, and
a "for later" mark on stories (tests in `tests/Feature/MobileApiTest.php`, 18 passing; whole suite 343).
NOT proven yet (needs a real phone): the microphone and the m4a upload, the camera card scan, the phone's own read
aloud voice, timing on mobile data, and children's voices. The browser preview cannot test those.
## Version 0.3.1 (2026-10-12 night): store preparation, no new screens
Four things that do not depend on the phone test, so the teammate's report on 0.3.0 is not wasted:
- **Recordings are deleted from the phone** (`src/recordingFiles.ts`): after the server answers (`api.uploadAudio`), when a recording
  was too quiet to send (`MicButton`), after the warm-up (`WarmUp`), and a sweep of the app's cache when the app opens and at log out
  (`App.tsx`). A send that FAILS keeps its file so "Send Again" works. Uses `expo-file-system` (now a direct dependency). The website
  privacy page says so. NOT proven on a real phone (the browser preview has no files); check the cache is empty after a reading.
- **Unused permissions are removed** (`android.blockedPermissions` in `app.json`): draw over other apps, biometrics, fingerprint,
  external storage. The release file went from 15 permissions to 10 (checked with `aapt2`). Kept on purpose until a phone test shows
  sounds still play without them: `FOREGROUND_SERVICE` and `FOREGROUND_SERVICE_MEDIA_PLAYBACK` (Play asks developers to justify them).
- **Error screen** (`src/ErrorBoundary.tsx`, around the whole app): a crash shows "Oops! Tara got mixed up" and a Start Again button
  instead of a white page. Look at it in the browser preview at `http://localhost:8081/#gallery=crash`.
- **Upload key plumbing for Google Play** (`plugins/with-upload-signing.js`, an Expo config plugin applied on every `expo prebuild`).
  With no key, nothing changes (test APKs are still debug signed). With four Gradle properties set (`TARABASA_UPLOAD_STORE_FILE`,
  `..._STORE_PASSWORD`, `..._KEY_ALIAS`, `..._KEY_PASSWORD`, put in `D:\tools\gradle-home\gradle.properties`, NEVER in the project) a
  release build is signed with that key, and `gradlew bundleRelease` makes the `.aab` Google wants. `bundleRelease` WITHOUT the key stops
  with a clear message (tested). Tested with a throwaway key: a 30.7 MB bundle signed by that key (the key was deleted afterwards).
  The real upload key is not made yet: see `PLAY-STORE-PLAN.md` section 5 (the account owner should keep the file and password safe).
- **Files:** `D:\tools\apk-out\TaraBasa-AI-0.3.1-test5.apk` (43.9 MB, arm64 only, version 0.3.1, versionCode 5, SHA-256
  `870da01ee4bfebf88bc4d9da8c6f16d35c91b6959877cbb92fceb4cfec572118`, built from a copy in `D:\tb`). Checked inside: live address,
  no localhost, the new screens, and the three game sounds. The teammate may still be testing 0.3.0 (`...-0.3.0-test4.apk`): wait for
  that report before sending 0.3.1. Older files (0.1.x, 0.2.0-test3) lack screens: do not send them.

## Next steps, in order
0. **RESOLVED 2026-10-12 (blocker found and fixed the same evening): the live server now has the newer phone endpoints.** Render was
   on `66ebc30`, which lacked practice tries, My Shelf, Games, the warm-up call and the new Home numbers. With the owner's yes,
   `mobile-app` was merged into `claude/admin-dashboard-approvals-62dcd0` (merge commit `8e1caa4`, fast-forward push, no migrations,
   packages or Docker changes). Render deployed it by 20:43: the website pages answer 200, the new phone routes answer 401 (they exist
   and want a login), and `/privacy` has "The phone app". Not yet tried with a real login from a phone.
   The tester has NOT been sent anything yet (the owner waited until the whole Learner end was finished), so send 0.3.1, not 0.3.0.
1. A tester runs `PHONE-TEST-CHECKLIST.md` on a real Android phone (0.3.1) and reports back. How to prepare it: `TEST-SETUP.md`.
   What to expect and what is risky: `APP-REVIEW.md`.
2. Fix what that finds (most likely the microphone file format, upload time, the camera, or a screen that does not fit), and bump the
   version (0.3.2 and so on; always a new `versionCode`).
3. Play Store: the account and the closed test take at least 14 days (see `PLAY-STORE-PLAN.md`), so decide the account owner now.

## Known gaps in the API for the phone
Avatar photos (they vanish on a redeploy, so the app shows the child's initial), `GET /api/learner/activities` has no pagination
(fine for a class sized list).
