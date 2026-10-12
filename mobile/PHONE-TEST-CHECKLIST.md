# Phone test checklist (for whoever tests the APK on a real Android phone)

This is a TEST build of the real app (version 0.3.0): sign in, the first reading check, Home, Read, Games, Badges
and Shelf. Its job is to find out what works on a real phone before the demo. Please be honest about what fails:
a failure found now is cheap, the same failure found on demo day is not.

Use only this version (0.3.0). The older test builds (0.1.x, 0.2.0) are missing screens, and 0.1.1 is also
missing a library the microphone needs and may close by itself.

## Before you start
- An Android phone (Android 7 or newer, 64-bit, which is almost every phone made after 2017).
- Internet on the phone (Wi-Fi or mobile data).
- The APK file and a test child's **learner code and PIN** (sent to you privately, never in a group chat).
- Do not use a real child's name or photo anywhere.
- Ideally also a printed learner card (the parent screen of the website prints one with a QR code).

## Install
1. Copy the APK to the phone (Google Drive, Messenger, USB cable, any way you like).
2. Open it on the phone. If the phone says "install blocked", tap **Settings** and allow installs from
   that app (Files, Drive, Chrome...). This is normal for a test file that is not from the Play Store.
3. Open **TaraBasa AI**. You should see the owl logo as the app icon and a white splash screen.

## Test, in this order
Write down for each one: **worked / did not work / odd**, and what the screen said.

1. **Sign in with the card.** Tap **Scan my card** and allow the camera. Point it at the printed card.
   A green tick and the code should appear, then the PIN boxes light up. Type the PIN and tap **Log in**.
   Also try a QR code that is NOT a TaraBasa card: the app should say so and keep looking.
2. **Sign in by typing.** Log out (bottom of Home) and sign in by typing the code and PIN instead.
   Note how long it takes. The first request after a quiet period can take up to a minute because the free
   server is asleep. The screen says so. Tell us how long it really was.
3. **First reading check** (only if this child has not done it): tap **I'm Ready**. Allow the microphone.
   Read the words (or say the letters) and tap the green check mark. Wait for the result. It can take up to
   a minute the first time. Does it say "Great job" and then finish with a level and two badges?
4. **Home.** The reading path with the child's step marked, days in a row, points, weekly goal, week chart.
   Do the numbers look right? Is anything cut off or too small to read?
5. **Read.** Open **Read**, tap a story. Tap the speaker: the phone should read the story aloud.
   Tap **My Turn**, record a practice try, and look at the coloured words. Then **Read For Real**.
   Is your voice clear? Does the word feedback match what you really said?
   - Change the text size with the minus and plus buttons. Does it stay the same after you leave and come back?
   - If a story has questions, answer them after reading.
   - Try once with a child's voice if a child is available (with a parent's permission). This is the test
     nobody has done yet and the most valuable one.
6. **Results.** Points, words read right, badges, level message. Tap **Done**: Home should show the new points.
7. **Badges.** Scroll the list. Tap a badge for the details.
8. **Silence check.** On a reading screen tap the microphone and stay silent for a few seconds (or put the phone face
   down), then tap the green check. The app should say it did not hear anything, send nothing, and let you try
   again at once. Then try again and read normally: that must work. If it ever says "we did not hear anything"
   when you did speak, note the phone model and how close the phone was: that is the number we need to adjust.
9. **Microphone warm-up.** Sign in with a child who has NOT done the first reading check yet. Before the first
   item the app asks you to say one easy word and shows a moving bar. Does the bar move when you speak? Does it say
   "I heard you"? Can you skip it?
10. **Games.** Open **Games**. Play each of the three games (Word Builder, Letter Match, Balloon Pop) for a round.
    - Tap **Hear the word** or turn a Letter Match card: does the phone speak, and can you hear it?
    - Do the short sounds play (a happy ding, a quiet low tone for a wrong match)?
    - In Word Builder, after spelling a word tap the microphone and say it. In Balloon Pop, say the marked word.
      How many seconds until the answer? Does a wrong or unclear try ever say "wrong"? (It must not.)
    - Leave a game half way (Games tab, then back): the board should still be there for Word Builder and Letter Match.
    - Finish a game: the "All done" screen may show a new badge. Check **Badges** afterwards.
11. **Shelf.** Open **Shelf**. Stories you have read appear; open one and read it again. Points and level must
    NOT change after a re-read.
12. **The phone's back button.** On a reading screen it should go back one step, then to Home, then leave the app.
13. **Turn the internet off** in the middle of sending a reading. The app should say it could not send, keep
    the reading, and let you tap **Send Again** after the internet is back.
14. **Close and reopen the app.** It should remember the child.

## What to send back
- Phone brand and model, Android version.
- For each step above: worked / did not work, and a screenshot of anything odd or any error message.
- How many seconds the first sign in took, and how long a scored reading took.
- Anything that felt slow, confusing, too small or in the wrong place on your screen. The layout is meant to
  fit every phone size, so a phone where it does not is useful news.

## If something does not work
- **App will not install:** say what the phone printed.
- **"The microphone is blocked":** phone Settings, Apps, TaraBasa AI, Permissions, allow Microphone.
- **The camera does not open:** same place, allow Camera. You can always type the code instead.
- **Reading never finishes:** wait up to 4 minutes (the scoring service can be asleep), then send a screenshot.
- **Anything about a wrong score:** send the words you said and what the app showed.
