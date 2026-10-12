# Play Store groundwork (written 2026-10-12)

What it takes to put the TaraBasa AI phone app on Google Play, what is already true of the app, what must change, and
what only people (not Claude) can do. Facts below come from Google's own help pages read on 2026-10-12; Google changes
these rules, so re-check a page before relying on it (links at the end). Anything not confirmed is marked **UNVERIFIED**.
Drafts for Google's forms are marked **DRAFT**: a teacher or the adviser should read them before they are submitted,
because a wrong answer in those forms can get an app removed.

## 1. The finding that changes the plan: the 12 testers rule

A **personal** Play developer account created after 13 November 2023 cannot publish until a **closed test** has run with at
least **12 testers who stay opted in for 14 days in a row**. Then the team applies for production access, which Google
"usually" reviews within 7 days (can be longer). Organization accounts are exempt.

Rough dates if everything goes well, counting from today (12 Oct):
- 13 to 16 Oct: create the account, verify identity (government ID, phone, $25 fee).
- Upload the first release to the closed test (a review of the release may take some days: **UNVERIFIED**, Google gave no
  figure for it). Get 12 testers (an Android phone and a
  Google account each) to opt in.
- 14 days later (about 3 Nov at the very earliest) apply for production; approval about a week after (about 10 Nov or later).

So a public listing in the first week of November is **not realistic with a personal account**. Options:
1. **Demo with the APK file** (works today, no store needed) and finish the store listing after the defense. Recommended.
2. **Use the closed test itself for the demo.** Testers (panel members, teachers) opted in to the closed test can install
   from Play before production. Needs the account and a reviewed release, so start this week if wanted.
3. **Ask whether the school can own an organization account.** Exempt from the 12 tester rule, but needs a D-U-N-S number and
   business documents, and makes the institution the publisher (and the party responsible for the children's data).
   Worth one question to the adviser.

## 2. Decisions only the team can make

| Decision | Why it matters | My suggestion |
|---|---|---|
| Who owns the developer account (a student, the adviser, the school) | The owner's legal name and address are public on the listing; losing access to the account loses the app | Ask the adviser; a shared team mailbox for recovery |
| Personal or organization account | Section 1 | See above |
| Target age groups (Google offers: 5 and under, 6-8, 9-12, 13-15, 16-17, 18+) | Selecting an age group under 13 puts the app under the **Families Policy**. Select more than one group only if the app is designed for each. Grade 1 to 3 is about 6 to 9 years | 6-8 and 9-12, because Grade 3 learners are 8 and 9. Do not add 5 and under or 13+ |
| Privacy contact email | The policy and the listing need one a real person reads. The website's privacy page only shows it if `PRIVACY_CONTACT_EMAIL` is set on Render | Set it before submitting (it is listed in `render.yaml` but I cannot see its value) |
| Who is the "data controller" | The Philippine Data Privacy Act names one; the adviser/school should say who | Ask the adviser |

## 3. What Google requires, and where the app stands

Checked against the real release file (`TaraBasa-AI-0.3.0-test4.apk`, read with `aapt2`):

| Requirement | Status |
|---|---|
| New apps must target Android 16 (API 36) from 31 Aug 2026 | **Met**: `targetSdkVersion 36` |
| New apps must be uploaded as an **Android App Bundle (.aab)**, not an APK | **Not yet**: we only built APKs. Needs a release build with an upload key (section 5) |
| Privacy policy at a public address, linked in the listing and Data safety form | **Exists** (`/privacy`) but describes the website only: must be updated (section 4) |
| Data safety form, content rating questionnaire, Target audience and content | Not started; drafts below |
| App access: reviewers need a way in (the app is behind a code and PIN) | Needs a **permanent demo child** on the live site, with the code and PIN written in the form. Use a made up child, never a real one |
| Families Policy: no ads, no ad SDKs, no precise location, no device identifiers sent | **Met**: no ads, no analytics, no location permission, no advertising ID permission in the file |
| Families Policy: only SDKs approved for child-directed services | **UNVERIFIED**: the app has no ad or analytics SDK, but `expo-camera` bundles Google ML Kit barcode scanning (it runs on the phone and sends nothing in our use). Check Play Console's SDK page for it before submitting |
| Disclose microphone data collected from children | Required; in the drafts below |
| Declared permissions should match what the app does | **Over-declared**: the file asks for 15 permissions, several unused (next table) |

Permissions in the 0.3.0 file and what to do (do these together in the next build, after the first phone test, so the
teammate does not retest twice):

| Permission | Used for | Plan |
|---|---|---|
| RECORD_AUDIO, CAMERA, INTERNET, ACCESS_NETWORK_STATE | reading, card scan, server | keep |
| VIBRATE, MODIFY_AUDIO_SETTINGS, WAKE_LOCK | small system helpers from libraries | keep (harmless), mention nothing |
| **SYSTEM_ALERT_WINDOW** ("draw over other apps") | not used by us; added by a library for debug tools | **remove** (`android.blockedPermissions`): a review flag in a children's app |
| **USE_BIOMETRIC, USE_FINGERPRINT** | not used (the sign-in token needs no fingerprint) | **remove** |
| **FOREGROUND_SERVICE, FOREGROUND_SERVICE_MEDIA_PLAYBACK** | background audio, which we do not use; Play asks developers to justify these | **remove** after a phone test that sounds still play |
| **READ/WRITE_EXTERNAL_STORAGE** (Android 12 and older only) | not needed | **remove** |
| uses-feature camera and microphone are "required" | a phone without a camera cannot install | decide: required is fine for a reading app, but a tablet without a camera could not use it; typing the code works without a camera |

Also found: recordings are **not deleted from the phone** after they are sent (they stay in the app's private cache until
Android clears it). The website's privacy page says "the app does not save the recording", which would be untrue for the
phone. Fix in the next build (delete the file after a successful upload and when leaving a screen) and then say so.

## 4. The privacy policy must be updated before the first submission

`resources/views/privacy.blade.php` says it is a website. Its own note says to change it in the same commit as any new data
practice. The phone app adds, and the page must state truthfully (check each against the code before publishing):
- the app records the child's voice when the child taps the microphone and sends it to be scored; whether the audio is kept
  anywhere afterwards (**confirm with the teammate who runs Reading-api whether it keeps any audio**; our code does not);
- the camera is used on the phone only to read the QR code on a learner card; nothing is stored or sent except the code the
  parent printed;
- a sign-in token is kept in the phone's secure storage and removed at log out;
- the phone reads words aloud with its own voice; nothing is sent for that;
- no ads, no analytics, no location, no advertising ID (true today);
- how a parent asks for a child's data to be removed (the contact email), and that the child's account is created by a parent
  on the website, not in the app.

The Play listing needs this address to be a normal web page (not a PDF) and to work without signing in: `/privacy` does.

## 5. Building the file Google accepts (do this in the next build)

1. Make an **upload key** once (JDK 17 is at `D:\tools\jdk-17`): `keytool -genkeypair -v -storetype PKCS12 -keystore
   tarabasa-upload.keystore -alias tarabasa-upload -keyalg RSA -keysize 2048 -validity 10000`. Choose a long password.
   **Keep the keystore file and password in two safe places (a password manager and an offline copy). Never commit them,
   never send them in a chat.** If lost, Google can reset the upload key through support, but it takes time.
2. Enroll in **Play App Signing** (the default for new apps): Google keeps the real signing key, our upload key only proves
   an upload is ours.
3. Add a release signing block in the generated `android/app/build.gradle` (it is generated by `expo prebuild`, so the block
   must be applied by a small config plugin or a post-prebuild step, not edited by hand) and run
   `gradlew bundleRelease`; the file is `android/app/build/outputs/bundle/release/app-release.aab`.
4. Keep building from the short copy `D:\tb` (Windows long paths), and make sure `org.gradle.configureondemand` is not true.
5. `.gitignore` now blocks `*.keystore` and `*.jks` in the repo.
6. Bump `versionCode` for every upload (Google rejects a repeated number).

## 6. Draft answers for Google's forms (DRAFT: have them read by a teacher or the adviser)

**Target audience and content:** ages 6-8 and 9-12; the app is designed for children; no ads; the listing uses children's
imagery on purpose (it is a children's app, so it must follow the Families Policy; do not hide that).

**Content rating (IARC questionnaire):** category Reference/Education; no violence, no sexual content, no profanity, no
gambling, no user-to-user messages or sharing, no location sharing, no purchases. Answer "no" to unrestricted web access.
(A child cannot contact anyone in the app.)

**Data safety, what the phone app sends off the phone:**

| Data type | Collected? | Why | Shared with third parties? |
|---|---|---|---|
| Audio files: voice recordings (the child reading) | Yes, only when the child taps the microphone | App functionality (scoring the reading) | No: the scoring service works for us and returns scores (a service provider, which Google does not count as sharing). Processed to score; **confirm retention** with the teammate |
| Personal info: User IDs (the learner code), and the 4 digit PIN as sign-in information | Yes | App functionality, account management | No |
| App activity: readings, quiz answers, game results | Yes | App functionality, personalization (picks what to read next) | No |
| Location, contacts, photos and videos, files, device or advertising IDs, financial info, health, web browsing, messages | No | | |

Also: data is encrypted in transit (HTTPS); users can request deletion (parent writes to the privacy contact); not sold; no
advertising; the form also lets us show "Committed to follow the Play Families Policy" once the policy review is done.

## 7. Store listing drafts (no emoji, plain wording, never "trained AI")

- **Name (30 max):** `TaraBasa AI` (11).
- **Short description (80 max):** `Read-aloud practice for Grade 1 to 3 learners that adapts to each child.` (72)
- **Category:** Education. **Tags:** reading, education.
- **Full description (4000 max), draft:**
  > TaraBasa AI helps Grade 1 to 3 learners practise reading out loud with Tara the owl.
  >
  > A parent creates the child's account on the TaraBasa AI website and gets a learner card with a QR code. On the phone the
  > child scans the card (or types the code) and enters a secret 4 digit PIN. A short first reading check finds a good
  > place to start. After that the app picks stories to read, listens while the child reads out loud, and shows which
  > words went well and which need more practice. Points, badges and days in a row keep practice fun, and three word games
  > (Word Builder, Letter Match, Balloon Pop) give extra practice without any marks.
  >
  > For parents and teachers: progress, reading levels and activities are on the TaraBasa AI website. The app has no ads, no
  > chat, and no way for a child to contact anyone. Read the privacy page for exactly what is kept.
  >
  > TaraBasa AI is a university capstone project, still being tested with schools. Its help is meant to support, not
  > replace, a teacher.
- **Assets Google needs:** icon 512x512 PNG (we can cut it from `assets/icon.png`), feature graphic 1024x500, at least 2
  phone screenshots (take them from a real phone or the phone frame preview at 1080x1920 or similar), developer email,
  privacy policy URL, website URL.

## 8. Order of work

| Step | Who | When |
|---|---|---|
| Ask the adviser: account owner, personal vs organization, data controller, the age groups | team | this week |
| Phone test of 0.3.0 (checklist) | teammate | in progress |
| Make a permanent demo child on the live site for reviewers (never a real child) | you | this week |
| Set `PRIVACY_CONTACT_EMAIL` on Render | you | this week |
| Confirm Reading-api retention of audio | teammate | this week |
| Build 0.3.1: remove unused permissions, delete recordings after upload, fix what the phone test finds | Claude | after the test report |
| Update `/privacy` for the app (same commit as 0.3.1) | Claude | with 0.3.1 |
| Create the developer account, verify identity, pay $25 | the account owner | as soon as decided |
| Make the upload key, build the .aab, upload to **closed testing**, add 12+ testers | Claude builds, owner uploads | when the account is ready |
| 14 days of testing, then apply for production | owner | about 3 Nov at the earliest |

## Sources (all read 2026-10-12)

- Families Policy: https://support.google.com/googleplay/android-developer/answer/9893335
- Target audience and content (age groups, steps): https://support.google.com/googleplay/android-developer/answer/9867159
- Testing requirement for new personal accounts: https://support.google.com/googleplay/android-developer/answer/14151465
- Data safety form (collected vs shared, exemptions, deletion): https://support.google.com/googleplay/android-developer/answer/10787469
- Target API requirement: https://developer.android.com/google/play/requirements/target-sdk
- App Bundle requirement for new apps: https://developer.android.com/guide/app-bundle
- Account registration ($25, identity): https://support.google.com/googleplay/android-developer/answer/6112435
- Android developer verification (sideloading, first countries Brazil, Indonesia, Singapore, Thailand on 30 Sep 2026; the
  Philippines was not named in anything I read, **UNVERIFIED** for later waves): https://developer.android.com/developer-verification/guides
- Signing with an upload key: https://reactnative.dev/docs/signed-apk-android and https://docs.expo.dev/distribution/app-signing
- Philippine NPC child oriented transparency advisory (child = under 18): https://privacy.gov.ph/wp-content/uploads/2024/12/FAQs-Advisory-on-Guidelines-on-Child-Oriented-Transparency.pdf
  (Registration threshold "250 employees or 1,000 sensitive records or risk to data subjects" came from a law firm summary of
  NPC Circular 2022-04: **UNVERIFIED**, ask the adviser and read the circular on privacy.gov.ph.)

This is research for the team, not legal advice. Children's data in the Philippines, the United States (COPPA) and the EU
(GDPR) has its own rules; the adviser or the school's data protection officer should review the privacy page and the
Data safety answers before anything is submitted.
