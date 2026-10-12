# How to set up the phone test (for the person who sends the app to the tester)

The tester only needs the app file, the checklist, and one made-up child's code and PIN. Everything below prepares those.
It takes about 40 minutes the first time. Use made-up names and no photos: never a real child.

## 0. The live server must have the phone's server code first (a real blocker found on 2026-10-12)

The phone app talks to the live website (https://tarabasa-ai.onrender.com). On 2026-10-12 the live website was still on the
older code (commit `66ebc30`) and did NOT have the newer phone endpoints (practice tries, My Shelf, Games, the warm-up call,
the Home numbers). With that older server the app would sign in but several screens would fail. So before anything else,
the branch `mobile-app` must be merged into the live branch `claude/admin-dashboard-approvals-62dcd0` and Render must finish
deploying it. That merge changes the live website, so the team decides it. It adds no migrations, no new packages and no
Docker changes (checked); the only conflict is in the notes file `CLAUDE.md`. All 354 server tests pass on `mobile-app`.
After the deploy, open `https://tarabasa-ai.onrender.com/up` (it should load without an error) and
`https://tarabasa-ai.onrender.com/privacy` (it should have a section called "The phone app").

## 1. Wake the services (free hosting sleeps)

Open the live website a few minutes before the test. Sign in as Admin, open System health and press **Check now**. The
reading checker, the activity writer and the recommender each need up to a minute to wake. Do this again just before the
tester starts if more than 15 minutes pass.

## 2. A parent and a made-up child

1. On the live website choose Get Started, then Parent. Use an email address you can really open (a spare Gmail): the site
   sends a verification email. Click the link in it.
2. Parent screen, Children, **Add a child**. Made-up first and last name, Grade 1, a preset avatar (no photo). Answer the
   reading questions as a child who is "just starting" for the first test (so the first check starts on letters). Choose a
   4 digit PIN and write it down.
3. The last page shows the child's **learner code** (looks like `TB26-12345`) and a QR card. Choose **Print card**, or keep the
   page open: the phone's camera can scan the QR from a laptop screen.

## 3. Stories for the child to read (needed for Read, Shelf and the quiz)

A brand new child has nothing to read, so the Read tab would be empty. Stories come from a teacher:
1. Sign up as a Teacher on the live site (a second email you can open), verify the email, and approve the teacher as Admin
   (Approvals).
2. Teacher: Activities, **Generate**: Grade 1, any skill, one text per level. It takes about two minutes and uses one of the
   two free credits. Approve what is written (drag a draft into Easy, Medium or Hard).
3. Teacher: Classes, **New class** (Grade 1), then **Add learner** and type the child's learner code.
4. Teacher: assign an approved activity to the class. For the quiz test, generate a reading comprehension activity too.

## 4. What to send the tester (privately, never in a group chat)

- The app: `D:\tools\apk-out\TaraBasa-AI-0.3.1-test5.apk`. Many chat apps refuse .apk files. Put it in Google Drive, share a
  link with only the tester, and ask them to download it on the phone.
- `mobile/PHONE-TEST-CHECKLIST.md`.
- The child's learner code and PIN, in a private message.
- Tell them: Android 7 or newer, allow installs from the app they opened the file with, allow the microphone and camera.

## 5. After the test

Ask for the answers in the checklist's "What to send back", with screenshots. Send them to Claude. Fixes go into a new
version (0.3.2, with a higher `versionCode`), built the same way, and the tester repeats only what changed.
