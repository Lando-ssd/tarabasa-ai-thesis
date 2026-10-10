# Phone test checklist (for whoever tests the APK on a real Android phone)

This is a TEST build. It is a plain checklist screen, not the real app design. Its job is to find out
what works on a real phone before the screens are designed. Please be honest about what fails: a
failure found now is cheap, the same failure found on demo day is not.

## Before you start
- An Android phone (Android 7 or newer, 64-bit, which is almost every phone made after 2017).
- Internet on the phone (Wi-Fi or mobile data).
- The APK file and a test child's **learner code and PIN** (sent to you privately, never in a group chat).
- Do not use a real child's name or photo anywhere.

## Install
1. Copy the APK to the phone (Google Drive, Messenger, USB cable, any way you like).
2. Open it on the phone. If the phone says "install blocked", tap **Settings** and allow installs from
   that app (Files, Drive, Chrome...). This is normal for a test file that is not from the Play Store.
3. Open **TaraBasa AI**.

## Test, in this order
Write down for each one: **worked / did not work / odd**, and what the screen said.

1. **Sign in.** Type the learner code and PIN. Note how long it takes. The first request after a quiet
   period can take up to a minute because the free server is asleep. That is expected, but tell us how long.
2. **Card 1, Reach the server.** Tap the button. Note the seconds it reports.
3. **Card 2, Microphone.** Allow the microphone when asked. Record a few words, stop, play it back.
   Is your voice clear? Is it loud enough?
4. **Card 3, Read an activity** (or **First reading check** if the child has not done it yet).
   - Load the activities, open one, tap **Record a practice try**, read the words aloud, tap again to stop.
     Wait for the result. Does the accuracy look fair for how you read?
   - Then **Record the real reading**. Note the points, the level and the word list.
   - If there is a quiz, pick answers first.
   - Try once with a child's voice if a child is available (with a parent's permission). This is the
     test nobody has done yet and the most valuable one.
5. **Card 4, Camera.** Allow the camera. Point it at a printed learner card QR code (the parent screen
   of the website prints one). It should say "A TaraBasa learner card" and show the code.
6. **Card 5, Voice and animation.** The phone should read "cat, dog, pig, hen, cow" aloud and a star
   animation should move.
7. **Log out** and sign in again. Does it remember you after you close and reopen the app?

## What to send back
- Phone brand and model, Android version.
- For each card: worked / did not work, and a screenshot of anything odd or any error message.
- How many seconds the first sign in took, and how long a scored reading took.
- Anything that felt slow, confusing or wrong.

## If something does not work
- **App will not install:** say what the phone printed.
- **"The microphone is blocked":** phone Settings, Apps, TaraBasa AI, Permissions, allow Microphone.
- **Reading never finishes:** wait up to 4 minutes (the scoring service can be asleep), then send a screenshot.
- **Anything about a wrong score:** send the words you said and what the app showed.
