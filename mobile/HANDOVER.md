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

## Status (2026-10-10)
Done: project, sign in, a plain phone check screen (server, microphone, scoring, camera scan, voice, animation),
the phone API brought up to the website's logic (reading path, day streak, weekly goal, practice tries, quiz
questions without answers, warm call) with 17 tests.
Not done: every real screen (needs the design preview approved first), Games, Bookshelf, the first check warm up,
text size control screen, offline handling, app icon and splash, a Play Store build.

## Next steps, in order
1. A tester runs `PHONE-TEST-CHECKLIST.md` on a real Android phone and reports back.
2. Fix what that finds (most likely the microphone file format, upload time, or the camera).
3. Design preview of the screens for approval (reuse the website Learner look: white page, blue panel, clay orange
   buttons, Tara and the star animations, icons, no emoji).
4. Build the screens in slices: sign in (type or scan the card), first reading check, Home, reading list and
   recording, results, Badges. Games and Bookshelf last.

## Known gaps in the API for the phone
Games finish call, Bookshelf list and re-read, the first check warm up, avatar photos (they vanish on a redeploy),
`GET /api/learner/activities` has no pagination (fine for a class sized list).
