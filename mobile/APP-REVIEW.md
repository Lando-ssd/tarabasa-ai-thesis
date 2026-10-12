# Learner phone app review (version 0.3.1, written 2026-10-12)

An honest review for the team and the adviser: what exists, what is good, what is weak, and what nobody has proven yet.
It is a review of the Learner side only; Teacher, Parent and Admin stay on the website.

## What it is
- A React Native (Expo) Android app, about 5,800 lines in 39 files, built by the team with Claude Code. It holds no secret keys:
  after sign in it keeps only a login token in the phone's secure storage.
- It talks to the same Laravel server and database as the website through 18 small endpoints (`routes/api.php`). The server
  rules (scoring, levels, badges, games, the first reading check) are shared code, not copied, so the website and the phone
  cannot disagree about a child's level or points.

## The child's journey
1. **Sign in:** scan the QR on the learner card (checked on the phone: right shape and check digit) or type the code, then a
   4 digit PIN. A second child on the same phone just logs out and in.
2. **First reading check** (once): a one word microphone warm-up, then up to three short items from a curated, curriculum
   coded bank, starting where the parent said the child is. No score is ever shown. It ends with a friendly level and badges.
3. **Home:** the child's step on a four step path (Letter Explorer, Word Builder, Sentence Reader, Story Reader), days in a
   row, points, a weekly goal ring and a week chart.
4. **Read:** the stories a teacher assigned (or the app picked). Listen (the phone reads it), two free practice tries, then
   the real reading. Comprehension stories end with a short quiz marked on the server. Results show the words that went well,
   words to practise, points and badges, and never a red mark.
5. **Games:** Word Builder, Letter Match and Balloon Pop, three levels each, with spoken words and short sounds. Free play:
   no points or level change; a finished game can earn a badge.
6. **Badges** (100, in groups) and **Shelf** (stories already read, free unscored re-reads).

## What is good
- **One source of truth.** Same server logic as the website, covered by 354 server tests (27 for the phone endpoints).
- **Safe by design.** The quiz answers never leave the server; the server recomputes scores; a child is never told they are
  "wrong" in a game; a recording that is too short or silent is not sent (the child is asked again, and a try is not used up);
  practice tries and re-reads cannot change level, points or streak.
- **Built for a sleepy server.** Waiting screens, "Send Again" after a failed upload, and a calm crash screen instead of a
  white page.
- **Built for a phone, not a shrunk website.** Taps at least 48dp and main buttons 56dp, the main action sits at the bottom
  under the thumb, safe areas, scrolls on a 640dp tall phone, readable column on a tablet, font scaling capped, no emoji, colour
  is never the only signal, a text size control for reading, a dyslexia friendly reading face (Lexend).
- **Privacy.** No ads, no analytics, no location, no advertising ID. The phone deletes its copy of a recording after it is
  sent. The website privacy page says all this.
- **Checked, not assumed.** Every screen and all three games were driven in a browser at phone sizes with a synthetic
  microphone and the real scoring service; the built file was opened and checked (live address, permissions, sounds).

## What is weak or risky
1. **It has never run on a real phone.** The browser preview cannot test the microphone file (m4a) upload, the camera card scan,
   the phone's own reading-aloud voice, the game sounds, or how smooth the animations are on a cheap Android phone. The
   microphone upload is the biggest single risk: Read, the first check and the speaking games all depend on it.
2. **It has never heard a real child.** Scoring is Vosk (a general speech recognizer), tested with synthesized voices only.
   Children's voices, names and look-alike words will be marked wrong sometimes. The "faintly heard" rule and the silence
   threshold (-45 dB) were set by reasoning, not from recordings.
3. **It can be slow.** On free hosting the first request after a quiet time can take up to a minute, a scored reading took 10 to
   40 seconds in our tests, and a one word game check about 10 seconds. That is a long time for a six year old, even with a
   waiting screen.
4. **It needs the internet for everything.** There is no offline mode.
5. **It depends on three other services** (reading scoring, activity writing, recommendations) on free hosting, with no
   guarantee they are awake, and the reading service has no password.
6. **A whole class recording at once is untested.** Each recording holds a server worker for several seconds.
7. **Reading content from the first check was written by the team**, not reviewed by a Grade 1 or Grade 2 teacher.
8. **No automated tests for the phone screens themselves.** The server side is well tested; the screens were checked by hand
   in the browser preview. A screen bug would be caught by a person, not by a test.
9. **Gaps:** Android only (no iPhone), a child's uploaded photo is shown as an initial (photos vanish on a redeploy), the
   manuscript still says Kotlin and Google speech, and the learner end has no screen reader test on a device.

## Verdict
Feature complete for the demo: every learner screen on the website has a phone version, and what a computer can check has
been checked. It is **not proven**: it has not run on a phone or heard a child. The first real test decides.

## The five questions the phone test must answer
1. Does a recording made on a real phone upload and score? (m4a file, microphone permission, time taken)
2. Does the silence check ever say "we did not hear anything" when the child did speak? (change `SILENT_DB` in
   `src/recording.ts` first if it does)
3. Does the camera read a printed card and a card on a laptop screen?
4. Do the voice, the sounds and the animations work, and are they not annoying?
5. How long do sign in, the first check, a reading and a game word take on mobile data, and does anything look wrong on
   that phone's screen?
