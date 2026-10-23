# 1. The spoken script: hook and storyline

How to use this file: read the **bold quoted lines** aloud, word for word, until they feel natural. The notes in plain text around them tell you what to point at, what
to pause for, and where the numbers come from. Replace anything in [square brackets] with your own details. Total time if you read everything: about 12 minutes.
If you only have 5 minutes: Part A, Part B, Scenes 4, 6 and 7, and Part D.

---

## Part A. The hook (45 seconds)

Say it slowly. Pause after the question.

> **"Good morning, panel. Imagine a Grade 1 teacher with a class of more than thirty children and thirty minutes for reading. To know how each child reads, she must
> listen to every child, one by one, and write down what she hears. That is less than a minute per child, and the next lesson is waiting.**
>
> **This is the real picture behind the numbers. In the 2022 PISA, the Philippines scored 347 in reading, about 76th out of 81 countries. The World Bank estimated that about
> nine in ten Filipino ten-year-olds cannot read and understand a simple story.**
>
> **So we asked one question: what if every child had a patient listener, and every teacher got the results before the next lesson? That is TaraBasa AI."**

Notes for you (not spoken):
- PISA 347 and "about 76th of 81" and the World Bank "about 90 percent" were confirmed in news reports of the original reports, not in the OECD or World Bank documents themselves.
  Before the defense, open the OECD PISA 2022 results and the World Bank learning poverty page, and say "reported by" if you cannot. [RESEARCH, found by search]
- "More than thirty children" is an illustration. Replace it with the real class size from your teacher survey if you have it.
- If a panelist interrupts at "nine in ten", answer: "That is the World Bank's learning poverty estimate: the share of ten-year-olds who cannot read and understand a simple story."

---

## Part B. The problem and the idea (1 minute 30 seconds)

> **"There are three problems behind the statistics.**
>
> **First, listening to children read is the most reliable way to know how they read. DepEd's own tool, the Phil-IRI, is an oral reading test done one child at a time. It
> works, but it takes a teacher's time, so it is done a few times a year, not every week.**
>
> **Second, children in Grades 1 to 3 are at very different points. In one classroom some children cannot name letters while others read short stories, and one set of
> worksheets cannot fit them all.**
>
> **Third, parents want to help but do not know how, and teachers do not hear about a struggling child until it is late.**
>
> **TaraBasa AI answers these with three promises. One: we listen. The child reads aloud into the phone and a speech recognition service writes down what was said. Two: we
> place and guide. Written rules, based on DepEd references, decide where the child is and what to read next. Three: we tell. Teachers and parents get clear results and
> alerts, with the evidence, so a person decides. Everything adaptive is explainable: it is a set of written rules, not a black box."**

Notes for you:
- "Problem 1" is the Phil-IRI. See file 3, section 3, for what the Phil-IRI is. [CURRICULUM]
- "Problem 3": the manuscript cites Hernandez and Decena (2024) on parent involvement and reading achievement (VERIFIED in the reference pack) and Kaliisa et al. (2024) on
  dashboards (VERIFIED). Use those two if asked for a source.
- Say "explainable" on purpose. It is your main difference from a black box (file 3, section 24).

---

## Part C. The storyline: one child, from sign up to alert

Tell it as a story with one made-up child. Call her **Maria**. Use the same child on every slide of your demo. Each scene has four lines to say:
**On the screen**, **Behind the screen**, **Why it exists**, **Where it ends**.

### Scene 1. The parent creates the account and the child's profile

- **On the screen.** The parent taps Create an account, then Parent. First name, last name, email, password, "Confirm password", and a tick box: "I am 18 or older. I agree to the Terms of Use and I have read the Privacy Policy."
  A message says "Almost there: check your email." The parent opens the email and taps the link. Then the parent signs in, taps Children, then Add a child: the child's name, grade, a character, how the child reads now
  (just starting, knows letters and sounds, can read simple sentences, reads independently), three yes or no questions that change with the grade, a 4-digit PIN, and one more tick box:
  "I am this child's parent or guardian and I agree to how the child's information is used."
- **Behind the screen.** The server checks the email is real (the domain can receive mail), creates the account with the password scrambled (hashed), and sends the confirmation email through
  Gmail's API. The link is signed and works for 60 minutes. The child's record gets a **learner code** such as `TB26-48293` (TB, the year, four digits, and a check digit so a typing error is caught) and the PIN is hashed.
  Both consent ticks are written to a consent record with the version of the policy that was shown.
- **Why it exists.** Children cannot give consent. The Data Privacy Act (Republic Act 10173) treats a child's information as sensitive, so the **parent** creates the profile and consents. The parent's description of how the
  child reads is what decides where the first reading check starts. [CURRICULUM for the law; TEAM CHOICE for the questions]
- **Where it ends.** The parent sees "All set": the child's code, a QR card to print, and the PIN is never shown again. The child can now log in.

> **Say:** "A child cannot consent, so the parent creates the profile and agrees to the privacy terms. The parent's answers also tell us where to start, so a Grade 3 child who cannot read yet is not given a story."

### Scene 2. The teacher is verified and builds a class

- **On the screen.** The teacher signs up with school name and employee ID, confirms the email, and waits. The Admin opens Approvals, sees the teacher card, checks the school with the school, and presses Approve.
  The teacher then creates a class: one grade only. To add a child, the teacher types the last five characters of the child's code. The Add button stays grey and shows the reason until the code is for a child of that grade.
- **Behind the screen.** Only an approved teacher can touch real children: every action that changes a class checks the teacher's status on the server, not just on the screen. The code check runs as the teacher types.
  Wrong guesses are limited, so a teacher cannot walk through all codes.
- **Why it exists.** A child's data must not be open to anyone who registers. The Admin step is the human check that this is a real teacher. One grade per class follows the curriculum, which is written grade by grade. [CURRICULUM]
- **Where it ends.** The class page shows the learners, grouped by reading level, with a reading group for each: Needs most support, Instructional, Independent.

> **Say:** "A teacher account is verified by a human, the Admin, before it can see any child. And a class holds one grade only, because the curriculum is written grade by grade."

### Scene 3. The child logs in

- **On the screen.** The child taps Scan my card and shows the QR card, or types the code, then taps four PIN numbers. Tara the owl says hello.
- **Behind the screen.** The QR holds only the code, never the PIN, so a lost card is not a lost account. Five wrong PINs lock the child for 15 minutes and the parent gets an alert; the parent can make a new PIN after typing their own password.
- **Why it exists.** Grade 1 to 3 children have no email and cannot manage passwords. A card plus a PIN is something a six-year-old can do.
- **Where it ends.** The child lands on Home, or on the first reading check if it has not been done.

### Scene 4. The first reading check: where does this child start?

- **On the screen.** Tara says "Let's read together." A one-word warm-up checks the microphone ("say sun"). Then the child reads the first text. If it is a Grade 3 child who cannot read yet, the first text is six letters.
  After the text a screen says "Great job" and the next one begins. The check stops after at most three texts, then shows the result as a friendly step, never a score.
- **Behind the screen.** The recording goes to our server, then to the **speech recognition service (Vosk)**, which writes down the words. Our rules line the heard words up against the text, count right, skipped and different words,
  and decide: 90 percent or more moves up one step, under 70 percent moves down one step, 70 to 89 percent stops. Letters move up at 80 percent. The step is saved.
- **Why it exists.** A child's grade is not a child's reading level. The check finds where each child really is. The texts are curated and coded to the MATATAG curriculum, so they are always the same quality, they open instantly, and they cost no AI call.
  [CURRICULUM for the codes; TEAM CHOICE for 90, 70, 80]
- **Where it ends.** The child sees one of four steps: Letter Explorer, Word Builder, Sentence Reader or Story Reader. The parent sees the same four steps, with a plain sentence. Only the teacher sees the Phil-IRI words.

> **Say:** "We never show a child a score on the first check. We show a friendly step. The check is not a test to pass; it is a place to start."

### Scene 5. Home: the reading path

- **On the screen.** Home shows the child's four steps as a path, with the child's step marked "you are here", a progress ring ("2 of 3 great readings so far"), a big Start Reading button, the weekly goal (five readings), a small week chart, and a flame and a star: days in a row and points. Tapping the flame or the star opens a card that explains the number.
- **Behind the screen.** The path is read from the child's saved step. "Great readings so far" counts readings of 90 percent or more since the last move.
- **Why it exists.** Children aged six to nine understand "where am I on the road" far better than a number. The path always moves forward in small steps so they can see progress. The four steps follow how the curriculum teaches reading: letters, then words, then sentences, then stories. [CURRICULUM]
- **Where it ends.** The child taps Start Reading.

### Scene 6. A real reading

- **On the screen.** The Read tab shows what the teacher gave the child and what a parent unlocked. The recommended one is first, marked "Picked just for you". The child opens a story: **Listen** (Tara reads it), **Your turn** (two free tries, they count for nothing), then **Read for real**. The child presses the microphone and reads, then presses stop. The screen says "checking", then shows the result: a happy star, how many words were right, the passage with the words to practise marked, and points. A comprehension story ends with two or three questions.
- **Behind the screen.** On the website the recording is saved for a few seconds as a temporary file and a background worker scores it, so the web server is never held up; the page asks "is it done?" every two seconds. The worker calls the speech service, cleans the result (words that sound the same are accepted, words heard only faintly are not counted against the child), saves the reading, updates the word bank, points, streak and badges, checks whether the child should move a step, tells the teacher and parents, and asks the recommender what comes next. Then the recording is **deleted**.
- **Why it exists.** The free tries let a child practise without pressure. The results screen is always a happy star, because a low score on a first try is not a reason to feel bad. The recording is deleted because we do not need it.
- **Where it ends.** The child moves on, or tries a game.

> **Say:** "The star is always happy. We never tell a six-year-old they failed. We tell the teacher, who can do something about it."

### Scene 7. The teacher sees what matters

- **On the screen.** The teacher's Home shows how many learners need support. Alerts lists children with the evidence: "Latest reading 25 percent, under 70 percent", "Ready to move up", "Moved up a level", "Gone quiet", "Just starting to read", each with one suggested activity from the teacher's own approved activities and a button. Analytics shows the class, the words missed most, and whether activities written for the class worked better.
- **Behind the screen.** Alerts are rules, run every time the page opens, over the readings in the database. Nothing is sent to a child by itself. A suggestion is only offered if no child in the group would find it too long.
- **Why it exists.** A teacher should look at the children who need help today, and see why, not a wall of numbers. The suggestion saves planning time but the teacher stays in charge.
- **Where it ends.** The teacher assigns an activity, or marks the alert as handled.

### Scene 8. The teacher writes activities with the AI

- **On the screen.** Generate: grade, skill, type of reading, how many Easy, Medium and Hard, optionally "For a class". Before pressing Write the teacher sees "what the AI will use": the step most of the class is on, the kind of mistake several children make, the words they missed, and the topics parents said their children like. The request runs in the background, about two minutes. Drafts appear; the teacher places each draft into Easy, Medium or Hard, or rejects it, and can edit it.
- **Behind the screen.** The app sends a grade, a skill, a topic and a short note (never a name) to the activity-writing service, which asks Google's Gemini language model for texts aligned to the MATATAG curriculum. It returns all three levels; we keep the ones asked for.
- **Why it exists.** Teachers have no time to write hundreds of leveled texts, but an AI can write texts that are wrong or too hard. So the AI only drafts. **A teacher approves every activity and decides its level.**
- **Where it ends.** The approved activity can be assigned to a child, a reading group, a class, and optionally shared in the Open Repository.

> **Say:** "The AI drafts, the teacher decides. The levels are our app's levels, not DepEd's, so the teacher has the last word."

### Scene 9. The parent follows and helps

- **On the screen.** Progress: days in a row, points, the reading level in plain words ("reads with some help"), a small chart of recent readings, and "Practise at home": one thing to try and the words to practise together. Alerts. Activities: more activities a teacher shared, free or paid, best match first.
- **Behind the screen.** The same rules the teacher uses, written in parent words. The paid unlock is **simulated** in the prototype; no real money moves.
- **Why it exists.** Parent involvement improves reading achievement, so the parent gets something they can do tonight. [RESEARCH: Hernandez and Decena, 2024, verified]
- **Where it ends.** The parent can also remove the child, or the whole account, and everything about the child is deleted.

### Scene 10. The Admin keeps it healthy

- **On the screen.** Admin: approvals, accounts, system health (reading checker, activity writer, recommender, email), activity log.
- **Why it exists.** Someone must approve teachers, answer "I did not get the email", and see when a service is asleep or down.
- **Where it ends.** Every action is written in the log with the reason.

---

## Part D. The close (1 minute)

> **"To summarize. TaraBasa AI listens to a child read, places the child on a four-step reading path based on DepEd's own references, and tells teachers and parents what to do next, with the evidence.**
>
> **Our contribution is not a model that nobody can question. It is a system where every rule can be read, every number can be changed in one settings file, and a teacher stays in charge.**
>
> **We are honest about the limits. It has not yet been tested with real children over time. The speech recognition was built for adult speech, so we made the scoring forgiving. And the numbers are our own settings, which is exactly what the next study should check with children's recordings.**
>
> **Thank you. We are ready for your questions."**

---

## Part E. Lines that keep the panel interested

Use two or three, not all.

- Before the demo: **"Watch what happens when a Grade 3 child who cannot read yet logs in. She does not get a story. She gets six letters."**
- After a live reading: **"That took about ten seconds. The recording is already deleted."**
- When showing the alert: **"The system never decides alone. It shows its evidence and asks the teacher."**
- When showing Easy, Medium, Hard: **"Notice the sentence under every level: these are our levels, not DepEd's. The teacher validates."**
- When asked something hard: **"That is a fair challenge. Here is what our data supports, and here is what we did not measure."**

## Part F. The demo path (6 minutes)

Do the keep-awake button first (file 6, section 1). Then, with one prepared child (Maria) and one prepared class:

1. Child login with the card (30 s). 2. First check, six letters (60 s). 3. Home and the reading path, tap the flame (30 s). 4. A story: Listen, one practice, a real reading, the result (90 s).
5. A game (30 s). 6. Teacher: Alerts with the evidence, then the Generate window and "what the AI will use" (90 s). 7. Parent: Progress and "Practise at home" (30 s).
If the internet is slow, say: "The first request after a quiet period can take about a minute because we use free hosting. In classroom use a paid plan removes this." That is honest and true.
