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

## The screens (version 0.2.0, built 2026-10-12 from the approved design preview)
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
| Games, Shelf | `src/ComingSoon.tsx` | "coming soon" in the first version |
| Bottom bar, back button | `src/Main.tsx` | five places; the bar hides while reading; Android back goes back one step, then Home |
| Shared pieces | `src/ui.tsx`, `src/theme.ts`, `src/Feedback.tsx`, `src/ReadingScene.tsx`, `src/MicButton.tsx`, `src/WordReview.tsx`, `src/Waiting.tsx` | |

Developer tool, phone frame: in the browser preview (`http://localhost:8081`) a wide window shows the app inside a drawn phone with
buttons for a small (360x640), common (390x844) and large (412x915) phone and a tablet, so the real screen size is clear
(`src/PhoneFrame.tsx`; `?frame=off` turns it off; a narrow window or a phone shows the app directly).

Developer tool, gallery: in the browser preview open `http://localhost:8081/#gallery=results` (also `home`, `badges`,
`check-intro`, `check-letters`, `check-next`, `check-unclear`, `check-done`, `practice`, `quiz`, `waiting`, `failed`,
`soon`) to see one screen with sample data at any phone size (`src/devGallery.tsx`, never in a phone build).

App icon and splash are made from the website's real logo by `node tools/make-icons.cjs` (it cuts the owl out of the
blue tile). Icons are drawn from `src/iconPaths.ts`, generated by `node tools/make-icon-paths.cjs` from
`public/icons/badges.svg`; run it again if the website adds an icon.

## Status (2026-10-12)
Done: everything in the table above, tested in the browser preview against the local server with the live reading
service (sign in, the first reading check including letters, a real scored reading, Home and Badges updating) and with
sample data at 360x640, 393x852 and 768x1024. The phone API gained badge icons and groups, a safe new-badges list, and
a "for later" mark on stories (tests in `tests/Feature/MobileApiTest.php`, 18 passing; whole suite 343).
NOT proven yet (needs a real phone): the microphone and the m4a upload, the camera card scan, the phone's own read
aloud voice, timing on mobile data, and children's voices. The browser preview cannot test those.
Not done: Games, Shelf (Bookshelf), the first check's one word microphone warm up, a silence check before sending,
text size control on the first check is saved but untested on a phone, a Play Store build and signing key.

## Next steps, in order
1. A tester runs `PHONE-TEST-CHECKLIST.md` on a real Android phone and reports back.
2. Fix what that finds (most likely the microphone file format, upload time, the camera, or a screen that does not fit).
3. Games and Shelf if there is time (cut first if not), then the Play Store steps.

## Known gaps in the API for the phone
Games finish call, Bookshelf list and re-read, the first check warm up, avatar photos (they vanish on a redeploy, so the
app shows the child's initial), `GET /api/learner/activities` has no pagination (fine for a class sized list).
