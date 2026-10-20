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
| Games (hub, Balloon Pop, Word Train, Sentence Stars) | `src/Games.tsx`, `src/BalloonPop.tsx`, `src/WordTrain.tsx`, `src/SentenceStars.tsx`, `src/ReadGame.tsx`, `src/SayWord.tsx`, `src/SayText.tsx`, `src/gameKit.tsx` | see "Practice Games" |
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
- **Every game is reading out loud.** Word Builder (tap letters to spell) and Letter Match (match cards) were removed: they were not
  reading. The three games now are **Balloon Pop** (five balloons, three marked in turn, say the word to pop it), **Word Train** (a
  train of cars; read the whole row of words, or letters for a child still on the letters step, to make it roll) and **Sentence
  Stars** (read a sentence to catch a star). Three levels each. The level a game STARTS at comes from the child's step on the reading
  path (letters and first words: 1, short and longer sentences: 2, stories: 3), not their grade; a round with two or more things read
  right the first time climbs one level; up to 3 rounds, or one round once on level 3. A finished game reports once
  (`POST /api/learner/games/{game}/finish`, `game_plays`), and that is what earns the ten Games badges (renamed for the new games;
  the codes are unchanged). The report can never break a game: if it fails the child just sees no new badge.
- **What a game offers is adaptive, from data the app already has:** the child's own missed words first, then words that practise
  their main kind of mistake, then the level's list (`GET /api/learner/games/words` for Balloon Pop, `/games/train` for Word Train,
  `/games/sentences` for Sentence Stars, where a sentence containing a word the child misses is "preferred"). The game the hub marks
  "Recommended" follows the same signals (`GET /api/learner/games`: `recommended` and `reason`).
- **Reading a row or a sentence** (`SayText.tsx`, the shared loop is `ReadGame.tsx`): one recording that stops by itself when the child
  pauses (or on a tap of the check mark), a little under two seconds a word, never more than 18 seconds. The phone first checks that a
  voice was heard (nothing is sent and no try is used if not), then the clip goes to `POST /api/learner/games/check-text` with the
  words asked and the kind (`row`, `letters`, `sentence`), which answers `heard`, `again` or `unavailable` (never "wrong") plus which
  of the words asked were heard, and keeps nothing. "Heard" needs about two thirds of the words. The words that were heard are shown
  in green and the rest are asked again; after two tries the game moves on anyway. `unavailable` (blocked or missing microphone,
  service unreachable) turns the game into an "I read it!" button so it can always be played. Balloon Pop still uses the one word
  `check-word` call (`SayWord.tsx`).
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

## Version 0.3.2 (2026-10-13): the reading path, the flying owl, the crash screen's two line title
- **Files:** `D:\tools\apk-out\TaraBasa-AI-0.3.2-test6.apk` (41.9 MiB, arm64 only, version 0.3.2, versionCode 6, SHA-256
  `11b9580559566d4fa2a4f7d1edc31dc2f110b03916a5a4f7cfc7a755975463bd`, built from a copy in `D:\tb` in 13 min 46 s, prebuild cleared and
  recreated `android/`). Checked inside: live address present, no `127.0.0.1` or emulator address (the one word "localhost" in the bundle is a
  string inside React's own code, not an address the app calls), the crash screen text, START READING, the `Owl Bird` animation, the three
  game sounds, 10 permissions (same as 0.3.1), no signing key used (debug key, fine for tests).
- **Needs the live server to have `pathProgress`** to show the ring and "1 of 3". `mobile-app` was merged into the live branch again on
  2026-10-13 (merge commit `ed5c5c6`, clean merge, full suite 356 passing, fast-forward push), so the live code has it. NOT yet confirmed
  against the running site: that needs a login as the test child (the phone works without the field, it just leaves the ring out).
- Still not proven on a real phone: everything listed in the 0.3.1 section above, plus how the flying owl looks and performs on Android.

## Reading path redesign (2026-10-12 night; PUSHED as 6175976 and 7097c65; in the 0.3.2 APK)
The owner shared two screenshots of a language app's winding lesson path and asked for the same kind of path on Home, in TaraBasa's own
colours, fonts and layout rules. Built as `src/ReadingPath.tsx` (replaces the plain list on Home):
- A blue banner "STEP 2 OF 4 / Word Builder / Sound out and read words", then the four steps as round buttons on an S shaped trail: steps already
  left behind are green with a bold check, the child's own step is the orange star with a ring that fills one segment per great reading counted
  (3 needed, `config/progression.php`), steps ahead are grey with a lock and the last is a trophy. Small dots join the buttons (green once passed).
- Under the child's own step an orange card: the skill ("Sounding Out Words"), "1 of 3 great readings so far", "Read 2 more really well to grow.",
  and one big START READING button (this replaced Home's old separate button). Beside the child's own step flies the SAME flapping owl the website's
  road uses (`assets/animations/owl-bird.json`, copied from `public/animations/learner/owl-bird.json`), hovering up and down a little; the owner asked for the
  flying bird instead of a still picture of the owl's head. The star breathes gently unless the phone is set to reduce motion. At the top step the ring turns gold and the card says "You are a Story Reader".
- Real data: `GET /api/learner/dashboard` now also returns `pathProgress {have, need, atTop}` (`LearnerApiController::pathProgress`, from
  `ReadingProgression::progress`, read only; 2 new tests, `MobileApiTest` 29 passing). The phone treats it as optional, so the older live server
  (no `pathProgress` yet) still works: the ring and the count are simply left out.
- On a short phone Home scrolls once, as it opens, just far enough to show the Start button (`Screen` got an optional `scrollRef`).
- Gallery pictures of every step: `#gallery=path-1` ... `#gallery=path-4`. Checked at 390x844 and 360x640 and with the live local server.
- NOT done: the website's Home already has its own road; it was not changed. The 0.3.2 APK (see above) has it.

## Version 0.4.0 (2026-10-13): layout rescaled to the approved preview, read-aloud games
- **File:** `D:\tools\apk-out\TaraBasa-AI-0.4.0-test7.apk` (41.9 MiB, arm64 only, versionCode 7, SHA-256
  `e084fb3092ef239af06b3b76ee7089be5735ca51be0512239db6ac4e50c9cc82`, built from `D:\tb` in 10.6 min, prebuild cleaned). Checked inside: the live
  address is present, no localhost or emulator address, Word Train, Sentence Stars and the new games calls are present, Word Builder and
  Letter Match are gone, 10 permissions (same as 0.3.x). NOT sent to anyone yet, and nothing in it has been proven on a real phone.
- Send this one, not 0.3.x: the Games calls changed, so older builds cannot play the new games. The server side is already live (merged into
  the live branch as `9876f5c`).

## Version 0.4.1 (2026-10-14): the layout pass 2 fixes
- **File:** `D:/tools/apk-out/TaraBasa-AI-0.4.1-test8.apk` (41.9 MiB, arm64 only, versionCode 8, SHA-256
  `4cea7652b33680c3536a32e8b496f6ad652cde9094b595c229ebae0624663058`, built from `D:	b` in about 12 minutes). It carries the layout pass
  (see CLAUDE.md "Phone app: layout pass 2 and the hosted preview"): the spacing ladder, tap targets never under 48dp, the six letter tiles
  three to a row, the warm-up, "Your turn" and Balloon Pop fitting common phones, a thin line above a pinned button only when the page scrolls.
- **Checked inside:** versionName 0.4.1 / versionCode 8; the same 10 permissions as 0.4.0; the live address is present; Word Train, Sentence
  Stars and the check-text call are present; Letter Match, the emulator address and ALL of the preview's sample data (sample child code, sample
  story, the sample-data server) are absent. NOT sent to anyone yet, and nothing in it has been proven on a real phone.
- **Two build notes.** (1) The first build of the day stopped halfway through Gradle with exit code 4 and no error text: the computer had only
  about 1 GB of free memory (8 GB total, other programs open). The same build then finished on a plain retry. If it happens again, close other
  programs first and retry. (2) The first finished build still had the preview's sample-data server inside the bundle, because the line that loads
  it checked the demo flag BEFORE the platform, so the bundler could not drop it. It never ran (demo mode is off in a phone build), but it
  was removed anyway by putting the platform check first (`App.tsx`); the 0.4.1 file above is the second, clean build and replaced the first.
- Send this one, not 0.4.0 or older: it is the only one with the layout fixes. The server side is unchanged and already live.
  (Superseded by 0.4.2 below, which adds the smallest phone fit on top.)

## Version 0.4.2 (2026-10-14): the smallest phone fit
- **File:** `D:/tools/apk-out/TaraBasa-AI-0.4.2-test9.apk` (41.9 MiB, arm64 only, versionCode 9, SHA-256
  `4a2a53bd0681715fe031d5e2e49d021f00bdf2b8cab2e9080d892bd329ab4141`, built from `D:	b` in 14.1 minutes on the first try, with only
  about 1 GB of free memory). It carries everything in 0.4.1 plus the smallest phone fit (CLAUDE.md "Smallest phone fit"): the quiz one
  question at a time, the warm-up "quiet" state, "Your turn" and Balloon Pop fitting a 360x640 phone, and the first check's six letter
  tiles three to a row on every width.
- **Checked inside:** versionName 0.4.2 / versionCode 9; the same 10 permissions as 0.4.0 and 0.4.1; the live address is present and the
  emulator address and all of the preview's sample data (sample child code, sample story, the sample-data server) are absent; the quiz
  text ("Question", "Next Question"), Word Train and Sentence Stars are present; Letter Match is absent. The word "localhost" appears once
  but it is a library string, not the server address. NOT sent to anyone yet, and nothing in it has been proven on a real phone.
- Send this one, not 0.4.1 or older: it is the latest. The server side is unchanged and already live.

## Version 0.5.0 (2026-10-15): the Teacher and Parent screens
- **File:** `D:/tools/apk-out/TaraBasa-AI-0.5.0-test10.apk` (42.1 MiB, arm64 only, versionCode 10, SHA-256
  `71eb982a952ce3047c00b23902a9c8848bfab81a062c6e203f013cd00a9889d6`, built from `D:	b` in 17 min 37 s). It carries everything in 0.4.2 plus the
  whole Teacher app (Home, Classes with camera scan, Activities review with level buttons, Generate for a class, Alerts, More with Analytics,
  Promotions and My profile) and the whole Parent app (Home, Children with the QR card, add a child in five steps, link a child with code and
  PIN, Progress, Activities with unlock and rating, Alerts, profile). See CLAUDE.md "Phone app: the rest of the Teacher screens and the whole
  Parent side". Source: branch `mobile-app`, commit `1470694` (the APK was built from exactly that code).
- **Checked inside:** versionName 0.5.0 / versionCode 10; the same 10 permissions as 0.4.x; the live address is present and `localhost:8123` and
  the emulator address are absent; the Teacher and Parent texts and their API paths are present ("Write new activities", "Add my child",
  "Link this child", "Practise at home", `api/parent/home`, `api/teacher/alerts`).
- **It cannot use the Teacher or Parent screens against the live server yet.** The live server does not have the new `/api/teacher/*` and
  `/api/parent/*` routes until `mobile-app` is merged into the live branch and deployed (needs the owner's yes). The Learner screens still work.
  Not sent to anyone, and nothing in it has been proven on a real phone. Test with `PHONE-TEST-CHECKLIST-TEACHER-PARENT.md`.

## Version 0.5.1 (2026-10-15 night): move a learner, school years
- **File:** `D:/tools/apk-out/TaraBasa-AI-0.5.1-test11.apk` (42.1 MiB, arm64 only, versionCode 11, SHA-256
  `22176b513c17a0006aca11aaa6a858978b2edc239963c5870e5ca396325e369f`, built from `D:\tb` in 17 min 33 s). It is 0.5.0 plus the Move card at the end of a
  learner's page and the school year chips on Classes (a past year is read only). Source: branch `mobile-app`, commits `baec273` (the feature) and `89f39dd`
  (the demo seeder; not in the app). **Send this one, not 0.5.0.** The tester pack is `D:/tools/apk-out/TaraBasa-0.5.1-tester-pack.zip` (the APK, its checksum,
  a start page and the two checklists).
- **Checked inside:** versionName 0.5.1 / versionCode 11; the same 10 permissions as 0.4.x and 0.5.0; the live address is present and `localhost:8123` and
  the emulator address are absent; "Move to another", "School year", "This is a past school year" and the Teacher and Parent texts are present.
- **Server side:** the live server answered 401 for `/api/teacher/home` and `/api/parent/home` on 2026-10-15 night (the Teacher and Parent routes ARE live: the
  0.5.0 note above that says they are not was true only until the merge `10cc745`), but 404 for the new move route. So until `mobile-app` is merged into the live
  branch again, the Move card and the year chips simply do not appear against the live server: the phone reads `years`, `canMove` and `moveTargets` as optional on
  purpose (an older server cannot crash the Classes tab). Merge on the owner's word to switch them on.
- **Not sent to anyone yet, and nothing in it has been proven on a real phone.**

## Version 0.5.2 (2026-10-16): a new PIN for a child who forgot theirs
- **File:** `D:/tools/apk-out/TaraBasa-AI-0.5.2-test12.apk` (42.2 MiB, arm64 only, versionCode 12, SHA-256 `6e83a84f0edb140df24df0eefe9a5dfd8497072c5dccbd94ddb1171f07fcc953`).
  It is 0.5.1 plus the Parent "New PIN" screen (Children, the child's card), the child's "Forgot my PIN?" help on the sign in
  screen, and the PIN alerts (a key icon on "PIN Locked" and "PIN Changed"). Source: branch `mobile-app`, commit `76631eb`.
  The tester pack is `D:/tools/apk-out/TaraBasa-0.5.2-tester-pack.zip` (the APK, its checksum, a start page and the two checklists;
  the Teacher and Parent checklist has the new step 5b under Parent). **Send this one, not 0.5.1.**
- **Server side:** the new PIN route is live on Render (merged to the live branch on 2026-10-15; `POST /api/parent/children/{id}/pin`
  answers 401 when not signed in), together with the move and school year routes, so every 0.5.2 feature works against the live server.
- **Not proven:** a real phone, the Android keyboard with the password and PIN fields, the big PIN digits on a small screen.

## Version 0.5.3 (2026-10-20): delete a child or account, the code check before Add, and grade requests
- **File:** `D:/tools/apk-out/TaraBasa-AI-0.5.3-test13.apk` (42.2 MiB, arm64 only, versionCode 13, SHA-256 `5b76bbc9b76c6d9a105282a6e83cfd24fbc4c052e509f40a2872411fe1101b43`,
  signed with the debug key, fine for tests). It is 0.5.2 plus: Parent "Remove" a child and "Delete my account" (Teacher and Parent), the Teacher's Add with a code that
  checks the code as it is typed (red note and a grey button for a child of another grade, green when it can be added), and the Teacher's Grades panel (asking the Admin for
  another grade, with a note, a waiting box with Cancel, and the Admin's answer). Source: branch `mobile-app`, commit `7eb0a86` (on top of `a200660`).
  The tester pack is `D:/tools/apk-out/TaraBasa-0.5.3-tester-pack.zip` (the APK, its checksum, START-HERE, and the two checklists, updated for the new steps). **Send this one.**
- **Checked inside the file:** versionCode 13 / versionName 0.5.3, zip intact, the bundle holds the live address and the new screens' strings, no localhost.
- **Server side:** all the routes it calls are live on Render since the merge of 2026-10-20 (`5fa2915`): `POST /api/teacher/classes/{id}/learners/check`, the grade request routes,
  and the delete routes. Checked: the check route answers 401 (not 404) when not signed in.
- **Build note:** the first run of the build script stopped on a harmless line (Gradle prints "NODE_ENV environment variable is required" to stderr and PowerShell 5.1 with
  `$ErrorActionPreference='Stop'` treats that as a failure). Gradle itself carried on and wrote the APK. Next time use `Continue` and check `$LASTEXITCODE`.
- **Not proven:** a real phone, the Android keyboard with the grade note box, the camera.

## Next steps, in order
0. **RESOLVED 2026-10-12 (blocker found and fixed the same evening): the live server now has the newer phone endpoints.** Render was
   on `66ebc30`, which lacked practice tries, My Shelf, Games, the warm-up call and the new Home numbers. With the owner's yes,
   `mobile-app` was merged into `claude/admin-dashboard-approvals-62dcd0` (merge commit `8e1caa4`, fast-forward push, no migrations,
   packages or Docker changes). Render deployed it by 20:43: the website pages answer 200, the new phone routes answer 401 (they exist
   and want a login), and `/privacy` has "The phone app". Not yet tried with a real login from a phone.
   A second merge on 2026-10-13 (`ed5c5c6`) added the `pathProgress` field for the reading path; website pages and `/privacy` answered 200
   and the dashboard route 401 afterwards (the first request took 36 s, the service was waking or deploying).
   The tester has NOT been sent anything yet (the owner waited until the whole Learner end was finished), so send 0.4.2 (the latest), not 0.4.1, 0.4.0, 0.3.2 or older.
1. A tester runs `PHONE-TEST-CHECKLIST.md` on a real Android phone (0.3.1) and reports back. How to prepare it: `TEST-SETUP.md`.
   What to expect and what is risky: `APP-REVIEW.md`.
2. Fix what that finds (most likely the microphone file format, upload time, the camera, or a screen that does not fit), and bump the
   version (0.3.2 and so on; always a new `versionCode`).
3. Play Store: the account and the closed test take at least 14 days (see `PLAY-STORE-PLAN.md`), so decide the account owner now.

## Known gaps in the API for the phone
Avatar photos (they vanish on a redeploy, so the app shows the child's initial), `GET /api/learner/activities` has no pagination
(fine for a class sized list).
