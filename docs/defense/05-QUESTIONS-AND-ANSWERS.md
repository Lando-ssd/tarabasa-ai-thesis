# 5. Questions and answers

How to use this file: cover the answer, say your own answer out loud, then compare. **Do not memorize the wording**; learn the shape.
The shape for any question you did not expect: **(1) what we did, (2) what the data or source shows, (3) what we did not measure, (4) what we would do next.**

Marks used in the answers:
- **[FILL]** means the real number must come from your own appendices (teacher survey, user acceptance test). Do not invent it.
- **[CONFIRM]** means check the exact wording in the DepEd document or the manuscript before you say it.

---

## Part A. The 10 standard defense questions

These are the ten questions every capstone panel asks (translated from the list you pasted). Understand them; do not memorize.

### A1. Why did you choose this topic?
"We chose reading in Grades 1 to 3 because it is the foundation of every other subject, and the national numbers show a gap: the Philippines scored 347 in reading in PISA 2022, and the World Bank puts learning poverty (children aged 10 who cannot read and understand a simple story) near 90 percent [CONFIRM both on the source pages].
DepEd has its own response, the programme Hamon: Bawat Bata Bumabasa, which says every learner should be a reader at their grade level. We saw that a teacher cannot listen to every child often enough, so we built a tool that does the listening and tells the teacher who needs help first."

### A2. What problem does your system solve?
"Three problems. **Teachers** cannot give each child frequent oral reading checks, so help comes late. **Parents** do not know how their child is really reading or what to do at home. **Children** get the same material whether they read well or not.
TaraBasa listens to each child read aloud, shows the teacher the evidence and a suggested next step, shows the parent a plain summary and a home tip, and gives the child activities that fit their level."

### A3. Who benefits?
"Learners (practice that fits, with friendly feedback), teachers (time saved and a clear list of who needs support, with the evidence), parents (a way to take part), and the school (a record of reading progress that supports the Phil-IRI practice).
Indirectly, DepEd's reading goal. The Administrator benefits from account control and audit records."

### A4. Why did you choose this methodology?
"We used an **Agile** approach [CONFIRM the exact model written in your Chapter 3, for example Scrum or iterative prototyping]: small working slices, tested one at a time, with feedback from teachers, the instructor and the adviser between rounds.
It suits a problem where the requirements changed as we learned (for example the grade rule, the levels, the first check). We built one full feature at a time, from database to screen, and tested each before the next."

### A5. Why did you choose this technology?
"**Laravel** for the website and its server rules, because it is mature, secure by default and quick to build with. **React Native with Expo** for the phone app, because one team can write one codebase for Android and share the same rules through an API.
**TiDB Cloud** (MySQL compatible) for the database and **Render** for hosting, because both have free plans a student team can afford and both can be upgraded without rewriting. **Vosk** for speech recognition, because it is open source and children's voices stay in a service we control. **Gemini** to draft activities, with a teacher approving every draft.
Every one of these has a paid upgrade path; we chose them for cost, simplicity and control."

### A6. What are the differences between your system and the existing ones?
"Four differences: it is **coded to the Philippine curriculum** (MATATAG codes) and speaks in **Phil-IRI words** teachers already use; **every adaptive decision shows its reason** (rules we wrote, not a black box); **a teacher approves every AI draft** and decides its level; and one system serves **teacher, parent and child**, on a phone or the web, with no ads.
What we do **not** claim: better speech accuracy than commercial products, or more content."

### A7. What are your limitations?
"Say these yourself, first. (1) **No testing with real children yet**; we used automated tests and computer voices. (2) **Speech recognition is weaker on children** and on names and same-sounding words; our scoring is forgiving, but it is not perfect. (3) The adaptive numbers are **our own settings**, not from DepEd; they need checking with real data.
(4) It is **English reading only**. (5) It needs **internet**. (6) The three AI services run on free hosting, so the first use after a quiet period waits about a minute. (7) The payment in the Open Repository is **simulated**. (8) Nothing was **trained**: it is rules plus pretrained services.
Then say what you would do next: a consented classroom pilot."

### A8. How did you prove that it works?
"What we can say truthfully: it works **as designed**. We have **788 automated tests** that pass, we ran real readings through the live services with computer voices and measured the results (for example letter-name repair went from 14 to 18 of 18 rows right with no score lowered), we tested every screen at phone and laptop sizes, and the team used it live.
The **teacher survey and the user acceptance test** in the manuscript give the opinion of real users: **[FILL: the real numbers, with n]**.
What we have **not** proven is that children's reading scores improved. That needs a pilot over weeks."

### A9. What if it did not work as expected?
"Each failure has a fallback. If the speech service is asleep, the child sees a waiting message and **Send Again** keeps the recording. If it scores a reading wrongly, the teacher sees the evidence and the numbers are in **one settings file** to change.
If an AI draft is poor, the teacher rejects it. If a free host ends, we move to the paid plans (about PHP 3,000 a month for a steady school). If the whole idea fails in a class, the design logs what happened, which is itself the data we need to fix it."

### A10. What is your contribution?
"A **working, explainable framework** for oral reading practice and monitoring in Grades 1 to 3, coded to the Philippine curriculum: speech recognition for scoring, written rules for placing and moving a child, teacher-approved AI-drafted activities, and role-based screens with privacy built in.
Its value to the field is that every adaptive decision is a readable rule with a stated basis, a sound base to tune with real data. It is not a new algorithm; it is a careful, honest combination of existing parts for a Philippine classroom."

---

## Part B. The problem and the purpose

**B1. Why Grades 1 to 3 and not Grades 4 to 6?**
Decoding is learned in the first three years, and a child who has not learned it falls behind in every subject. The MATATAG curriculum puts foundational skills first in Key Stage 1. The Phil-IRI manual is written for Grades 3 to 6 per one source; we say honestly that using its bands for younger children is our extension.

**B2. Why English reading? Why not Filipino or the mother tongue?**
Our speech service (Vosk model) and the curriculum codes we used are English. The Grade 1 mother-tongue instruction is real, and we list it as a limitation and a future step. We would need a Filipino speech model and Filipino content.

**B3. How do you know teachers need this?**
From the teacher survey in the manuscript **[FILL: percentage and n]**, and from the teachers' and instructor's feedback during development (they asked for windows instead of scrolling, class cards with activities, level sorting). Do not quote a percentage without the number of respondents.

**B4. Does this replace the teacher?**
No. It assists. The teacher confirms every activity, decides its level, chooses what to assign, and reads every alert's evidence. The first line on our how-it-works page says the results assist a teacher's judgment.

**B5. Does this replace the Phil-IRI?**
No. The Phil-IRI is DepEd's official assessment. TaraBasa gives frequent practice readings and uses the Phil-IRI **words** for levels. It is a between-tests tool, not an official assessment, and its levels are not official scores.

**B6. Who is the "user" and who is the "customer"?**
Users: learner, teacher, parent, Administrator. Customer who pays: the school (see file 04). They differ, which is common in education software.

**B7. What is the scope, and what is outside it?**
Inside: English oral reading practice, first check, activities, alerts, parent view, games, word bank, badges, privacy. Outside: comprehension beyond a short quiz, writing, other subjects, Filipino-language reading, real payment, SMS, offline use, official grading.

---

## Part C. How the system works

**C1. Walk me through what happens when a parent signs up.**
The parent fills the form and ticks the consent box. The server checks the email format and that the domain can receive mail, and suggests a fix for a common typo ("Did you mean gmail.com?"). It saves the account with a hashed password, writes a consent record with the policy version, and sends a confirmation email through the Gmail API. The parent cannot sign in until they open the link. (Details: file 01, scene 1, and file 02.)

**C2. Where is the password stored?**
Only as a hash, never as the password. The sign in is limited to 5 tries and then waits.

**C3. How does a child sign in without an email?**
With the **Learner Code** (like TB26-48293, with a check digit) and a **4 digit PIN**, or by scanning the **QR card**. The QR holds only the code, never the PIN, so scanning does not sign anyone in. After 5 wrong PINs the child is locked for a short time and the guardians are told once.

**C4. What if a child forgets the PIN?**
Only a linked guardian can make a new one, after typing their own password again. The old PIN stops at once, the child's phone sign ins end, and the other guardians are told. There is a limit of 3 changes a day.

**C5. How does a teacher join a child to a class?**
Typing the last 5 characters of the child's code or scanning the QR. The window checks before Add is pressed: wrong grade, a reading level far above the class, or already in a class all turn Add off with a reason. The child's parents are told.

**C6. What happens when a child records a reading?**
The phone or browser records; the screen checks the internet and wakes the reading service; the recording is uploaded; a background worker sends it to the speech service; the words are lined up against the activity; fairness rules apply; the result is saved; points, badges, notifications and the recommender update; and the child's page shows the result. The recording is deleted when the scoring ends. (File 02, flow D.)

**C7. Why is scoring in the background?**
Scoring takes 5 to 45 seconds. Holding a web request that long used up the few web workers, so the host's health check failed and everyone saw a 502. Now the upload is accepted at once, a worker scores it, and the child's page asks every 2 seconds "is it done?".

**C8. What was the 502 problem?**
The server was running one worker instead of four (a missing flag), so one slow recording blocked everything, including the host's health check, and Render stopped sending traffic. We fixed the flag and moved scoring to the background. We tested with a slow fake service: the health check's slowest answer went from 24 seconds to 1.1.

**C9. How do the website and the phone app share logic?**
The rules live in server services; the website pages and the phone API both call the same services, so they cannot disagree. The phone app only draws what the server says.

**C10. What is different between the website and the app?**
The app is for learner, teacher and parent; the Administrator is website only. The app installs on a phone and signs in with a token; the website uses a session cookie. Teachers drag cards on the website and use buttons on the phone. Scoring in the background is used by the website; the phone waits in the request behind a two-slot guard.

**C11. What happens if the internet drops mid-reading?**
The app checks first; if it cannot reach TaraBasa it shows "Send Again", which keeps the recording. A quick failure is retried automatically up to two times. A drop in the middle of an upload is the one case we cannot recover.

**C12. Where is the data kept?**
In a TiDB Cloud database (MySQL compatible). Recordings are not kept: they wait as a temporary file for seconds and are deleted when scoring ends. Child photos are kept in the database. Sessions are in the database.

**C13. How are emails sent?**
With the Gmail API over HTTPS (Render's free tier blocks the usual mail ports). If the Gmail token expires, nobody can confirm an email; the Admin's System health page shows it and the Admin can confirm an address by hand with a reason that is logged.

**C14. What does the Administrator do?**
Approves teachers (after checking with the school), approves grade requests, deactivates or deletes accounts, confirms an email by hand, and watches System health. Every action is in an Activity log with a reason.

**C15. Why must the Admin approve teachers?**
A teacher can reach children's records. The Admin checks the school and employee ID first. A teacher can sign in before approval but cannot touch real students.

---

## Part D. The AI and the rules

**D1. Is your AI trained?**
No. Say it before they ask. The system uses **pretrained** services (Vosk for speech, Gemini for drafting activities) and **rules and formulas we wrote**. Training needs a collection of children's recordings, and none exists yet.

**D2. Then why do you call it AI?**
The speech recognition and the activity writer are AI services, and the system adapts using rules. We call the adaptive part rule-based and explainable. The manuscript uses "AI" for the services and the assistance, not for a trained model of our own.

**D3. Why not train your own model?**
No data, no consent process yet for collecting children's voices, and a model would be harder to explain to a teacher. Our settings are in one file so a future team can replace a rule with a trained model after a pilot supplies data.

**D4. How does the system decide the child's level?**
A first check of up to three texts places the child; after that, three strong readings (90 percent or more, from two different activities) move the child up one step, and two weak readings (under 70) in a row move the child down one. Phil-IRI words are shown to adults. (File 03, sections 6 and 7.)

**D5. Why 90 and 70?**
90 matches the Phil-IRI boundary between instructional and independent. 70 is our alert line, lower than 90 so teachers are not flooded. Both are team settings.

**D6. Why three readings and not one?**
One reading of a short text shows that the child read that text, not that they mastered a step. Reading programmes use repeated evidence. Before this rule, one good reading jumped a child a whole level, which was a bug.

**D7. What is "Not sure"?**
When the recognizer heard a different word but only faintly (confidence under 0.5), the word is left out of the score, the word bank and alerts. At most 25 percent of words can be "not sure", so it cannot hide a reading that is mostly wrong.

**D8. How do you handle the recognizer being weak on children?**
Fairness rules: same-sounding words accepted, spelling variants, numbers, missing apostrophes, compound words heard as two; letter names repaired; faintly heard words left out. Near misses (ripe/right) stay mistakes. Everything shows the teacher the evidence.

**D9. How do you know the letter repair does not give free marks?**
We measured it on 70 live recordings with two computer voices: the rows right went from 14 of 18 to 18 of 18, no score went down, and a row said in the wrong order scored exactly what the service gave. A first, more generous version gave 16.67 to a wrong order, so we rewrote it.

**D10. How does the AI write an activity? What does it know about the children?**
It receives a grade, a skill, a type, a topic and a short note. It knows nothing about any child. If the teacher picks "For a class", our server builds a note from facts at least two children share (the step, the kind of mistake, missed words, liked topics) and the teacher reads it before pressing Write.

**D11. Could the AI write something wrong or unsafe?**
Yes, that is why a teacher must approve each draft. Only approved activities can be given to children. A teacher is told not to type a child's name in the notes, because the text goes to a Google AI model.

**D12. What is the "Did it work?" card?**
It compares the same children's readings of activities written for their class with general activities. It shows a number only when at least 3 children read both, and calls a difference under 3 points "about the same". It is a signal, not proof, because a text can be easier or more interesting.

**D13. What is the recommender?**
A small service with formulas: an attempt score from accuracy, speed and prosody (weights depend on the skill), a slow moving average (0.7 old, 0.3 new), and difficulty bands (easy up to 59, medium up to 79, hard above). We confirmed a live result equals the formula exactly (83.75 then 93.3).

**D14. What is a "mistake pattern"?**
What a child's missed words have in common: small words skipped, word endings, vowel sounds, first or last sounds, blends, look-alike letters, longer words in pieces, and so on. Reported only with at least 3 readings and 6 misses, and if the pattern is 25 percent or more of the misses.

**D15. How does a suggestion get chosen?**
Rules: the teacher's own approved activities, the right grade, not too long for anyone, closest to a comfortable length, matching liked topics and the child's pattern. Rule-based scoring with reasons, not a learned model.

**D16. Can the system make a wrong decision about a child?**
Yes. That is why it moves slowly, why alerts show their evidence, why nothing is given to a child without a teacher or a parent action (apart from the child's own picker, which shows only what was assigned or unlocked), and why a teacher can overrule levels.

**D17. Can a child cheat?**
A child can have help reading. The system cannot tell. Points and badges are small, free practice and games do not change the level, and the level moves only after repeated evidence. A teacher can see the pattern.

**D18. What does "prosody" mean here?**
The expression and rhythm of reading. The reading service gives a heuristic score. It is used only in the fluency formula in the recommender and is never shown to a child.

---

## Part E. Curriculum and Phil-IRI

**E1. What is the Phil-IRI?**
The Philippine Informal Reading Inventory, DepEd's tool to find a learner's reading level: the teacher listens to the learner read graded passages aloud, counts errors, and asks comprehension questions. Policy: DepEd Order No. 14, s. 2018 [CONFIRM the title on deped.gov.ph].

**E2. What are Independent, Instructional and Frustration?**
For word reading in one passage: Independent 97 percent or more (reads alone comfortably), Instructional 90 to 96 (learns with a teacher's guidance), Frustration under 90 (too hard even with help). A Non-reader cannot yet recognize and sound out letters and simple words.

**E3. Where does "frustration level" come from?**
From the informal reading inventory tradition; an older Phil-IRI document says it was adapted from Johnson, Kress and Pikulski (1987) [unverified; confirm in the document before naming them].

**E4. Do you show children those words?**
No. Children see four friendly steps. Parents see the same four steps, with a plain sentence about what that step means. Only teachers see the Phil-IRI words, with the evidence.

**E5. How did you connect a reading step to a Phil-IRI word?**
Our own mapping: Letters is Non-reader; Words and Short sentences are Frustration; Sentences and Short stories are Instructional; Stories and Longer stories are Independent. It is a team choice, said openly.

**E6. Why are the four steps named Letter Explorer, Word Builder, Sentence Reader and Story Reader?**
Because they follow the order MATATAG teaches reading (letters, words, sentences, stories), and the names tell a child what they do, not how they score.

**E7. What is MATATAG?**
The revised basic education curriculum (DepEd Order No. 10, s. 2024). It is written by grade, with codes such as RL1PWS-I-5 ("sound out words accurately"). We attach codes to the first check texts, mistake patterns and activities [CONFIRM the wording against the guides].

**E8. What does "Hamon: Bawat Bata Bumabasa" mean?**
A DepEd reading programme (Memorandum No. 173, s. 2019): "Challenge: Every Child a Reader". It names six elements of reading: oral language, phonological awareness, phonics, vocabulary, fluency, comprehension.

**E9. Your reading path is not tied to grade. Isn't that against the curriculum?**
The class, the activity's grade and the curriculum code follow the grade. The child's reading step decides what the child reads now. A Grade 3 child who cannot read yet should start on letters; the curriculum itself is built to teach those foundations. The grade says where a child is enrolled, not what they can read.

**E10. Why one grade per class? DepEd has multigrade classes.**
DepEd defines a multigrade class as two or more grades under one teacher [CONFIRM the orders, DO 96 s. 1997 and DO 81 s. 2009]. We deliberately did not model it: a teacher with several grades opens one class per grade. The curriculum is by grade and the school decides who teaches which grade, so the Admin confirms a teacher's added grade with the school.

**E11. Where do Easy, Medium and Hard come from?**
They are the app's levels, based on word familiarity and text length, not DepEd scores. The curriculum gives no word counts, so we say so and let the teacher place every activity.

**E12. What are the length limits based on?**
The activity writer's own length bands and the first check's texts. They are team figures; the curriculum gives none.

---

## Part F. UI and UX

**F1. Why does the child never see a score?**
Young children understand a road and a next step better than a percentage, and a low score on a first try should not make a six-year-old feel bad. The result star is always happy. The teacher and parent see the numbers.

**F2. Why is the text so big, and why five sizes?**
Emergent readers need large print, and some need larger. The default is set by grade (Grade 1 largest). The font Lexend was designed for reading comfort; the evidence for it is mixed, so we do not claim it improves reading.

**F3. Why tap and not drag for children?**
A study of 90 children aged 2 to 8 found that 83 percent of the 7 to 8 year olds could tap, 40 percent swipe and 30 percent drag and drop (Yadav et al., 2020). It is a small sample, so it is a reason, not proof.

**F4. Why does Tara speak every instruction and also show it in text and a glowing button?**
A child who cannot read yet can still follow the voice; the text and the glow support children who can read; written prompts alone are not reliable for children of 8 and under.

**F5. Why do you use windows (dialogs) in the teacher screens?**
Teachers asked for it: every action in a window and every screen fitting one laptop view with no scrolling. We measured each screen at a laptop size.

**F6. Why does a parent's screen show "Word Builder" and not "Frustration" or "Developing"?**
A parent sees the same four step names as their child, so they can talk about it together, and a plain sentence says what it means and leads to what to do (the home tip). "Frustration level" sounds harsh to a parent, and the system's own stored level (Beginning, Developing, Proficient) is internal and is shown to no one.

**F7. How is the design accessible?**
Large tap targets (48 points on the phone, 44 pixels on the web), text-size control, contrast, reduced motion respected, a spoken voice, icons not emoji. We reviewed every screen at 360, 390, 430 and 768 pixel widths and at text 20 percent larger. We did not test with children who have disabilities.

**F8. Why a QR card?**
Children have no email; a card they can scan is a fast, low-typing way to enter the code. It holds only the code and never the PIN.

---

## Part G. Privacy, ethics and security

**G1. What law applies?**
The Data Privacy Act of 2012 (Republic Act No. 10173). A child's data is sensitive and a child cannot consent, so the parent creates the profile and gives two ticks. Everyone can ask for their data to be removed (Section 16(e)) [CONFIRM by reading the Act].

**G2. What did you do for privacy?**
Parent consent with the policy version stored; recordings deleted after scoring; fonts and scripts hosted by us (no outside trackers); the activity writer never gets a name; a delete button for a child or an account; five public legal pages; an Admin log that keeps a reason but not the person's name after deletion.

**G3. What outside services get data, and what do they get?**
Render (hosting) and TiDB (database): the app's data. Google: the Gmail API gets the email text; Gemini gets only a grade, a skill, a topic and a class summary note. The speech service (hosted by our teammate) gets the recording and deletes it after scoring. No analytics, no ads, no trackers. The visitor's browser makes one bare wake-up request to the helper services.

**G4. Is the speech service secure?**
It has no password yet, which is a known risk we list. It would need a key before wide use.

**G5. What if someone guesses a learner's code?**
A code has a check digit and the PIN is needed to sign in; five wrong PINs lock the child. A teacher's lookup by last five characters is limited (20 refusals an hour) and a "yes" never names the child.

**G6. What did the security audit find?**
For example, the seeded Admin password was in a public repository (now set from a secret), a reflected XSS on an internal page (fixed), and lists in the address caused crashes (fixed). We upgraded Laravel after a security notice and added security headers and friendly error pages. Each has a test.

**G7. Do you keep the child's photo?**
Only if the parent adds one with a separate optional consent tick. It is stored in the database (so it no longer disappears when the server restarts) and only the child, a linked guardian and the child's class teacher can see it.

**G8. Is the system registered with the National Privacy Commission?**
Open item. We list it in the risk register as something to check (whether the registration rules apply), together with a Privacy Impact Assessment, a breach plan and written agreements with the hosting services.

**G9. Can a parent delete everything?**
Yes. A guardian removes a child; with another guardian linked it only unlinks the one who asked. A person can delete their own account with their password. The Administrator can do it for someone who cannot sign in.

---

## Part H. Testing and evidence

**H1. How was it tested?**
788 automated tests; live runs with computer voices through the real speech service; browser checks at several screen sizes; load tests with a slow fake service; and the team's own use.

**H2. What did the teacher survey and the user acceptance test show?**
**[FILL: use the real numbers and n from Appendices D and E.]** If asked about a number you cannot trace to an appendix, say "I will check the appendix" and do not guess.

**H3. Did you test with real children?**
No, not yet. We say it as the first limitation. The next step is a consented pilot.

**H4. Why does the number of tests matter?**
It shows rules do what they say, and that fixes did not break old behaviour. It does not show the rules are educationally right; that needs a pilot.

**H5. What did you test the speech scoring with?**
Windows computer voices (David and Zira) and the live Reading-api service. Children's voices are different, so the settings may change.

**H6. What bugs did you find that you are proud to have found?**
A level jump on one reading (now repeated evidence); a hidden security hole (a child with no class could open other children's assignments because an empty class matched an empty class); the one-worker server; the first check ending on one missed letter (now letters move up at 80); a class name that collided with a CSS rule.

**H7. What is the next test?**
A consented pilot in one or two classes: do teachers use alerts, do children finish readings, do the settings need to change, do scores move over weeks.

---

## Part I. Technology and operations

**I1. Why Render and TiDB? Why not Google Cloud, which the manuscript says?**
The first plan named Google Cloud and Kotlin. Hosting cost and speed of building changed the choice: Render and TiDB have free plans and the same code runs on paid plans. The manuscript pack updates the stack and figures.

**I2. Why does the first use take a minute?**
Free hosting sleeps after 15 idle minutes and wakes in about a minute. The website and the three helper services are woken by the keep-awake button, a GitHub workflow, or the page itself.

**I3. What happens if the teammate's services go down?**
Reading and activity writing stop; sign in and the rest work. There are offline copies of his repositories and a rebuild plan in `docs/KEEP-IT-ALIVE.md` section 7.

**I4. How do you deploy a change?**
Push to the live branch on GitHub; Render builds the Docker image, runs migrations, and starts the website, a default queue worker and scoring workers.

**I5. How would it scale to 30 children recording at once?**
The upload is accepted at once and workers score in a queue, so the website stays responsive. The speech service may score one at a time, so the last child waits on a "Still working" screen, not an error. Not measured on Render.

**I6. How do you back up?**
A code bundle and a copy of the helper services are kept on D: and in GitHub. The database has no automatic backup on the free plan; this is on the checklist.

**I7. What is the phone app built with?**
Expo SDK 57 and React Native, in TypeScript; an Android APK is built from a short-path copy; the app talks to the same server through a token API.

**I8. Did you test the phone app on a real phone?**
We ran the app's code in a browser preview on a computer (same screens, same server) and built the Android file (APK). A test on a real phone with a real child is **pending**; a teammate is to run the phone checklist. Say exactly which of these you did yourself.

**I9. What is a queue worker?**
A background program that takes jobs from a list (score a recording, write activities, wake a service) so web requests stay short.

---

## Part J. Business

(Details in file 04.)

**J1. Who pays?** The school or its sponsor; the learner, teacher and parent are users.

**J2. How much?** Our example is PHP 1,500 a month per school; it is an assumption to test.

**J3. Is there profit?** Not yet; the plan breaks even at two schools at that price, because the cost (about PHP 3,000 a month) is mostly fixed.

**J4. Why no ads?** Children use it; our policy says none.

**J5. Is the payment real?** No, it is simulated.

---

## Part K. Tricky questions

**K1. Your adviser said AI. Where is the AI?**
"In the speech recognition that listens to the child, in the model that drafts activities, and in the adaptive support. The adaptive part is rule-based and explainable on purpose."

**K2. Isn't a rule-based system just if-statements?**
"The rules are the knowledge: placing a child, moving them slowly, alerts, suggestions and patterns, each tied to a curriculum code. That is what a teacher does by hand. Rules are the right first step because we have no data to train on and every decision must be explainable to a teacher and a parent."

**K3. Can the system harm a child?**
"The main risks are a wrong placement (the teacher sees evidence and can overrule), a child feeling bad about a score (they never see one), and privacy (consent, deletion, no ads, no trackers). We list the open items honestly."

**K4. Why should we believe your numbers are right?**
"We don't ask you to: they are our settings, in one file, with the reason next to each. The first thing a pilot will do is check them. Where a number comes from DepEd (97 and 90), we say so."

**K5. What happens when a reading is scored wrongly because of accent?**
"The teacher sees the evidence and the words heard, one reading never moves a child, and 'Not sure' words are left out. We list accent and child voices as a limitation and as the main thing to measure in a pilot."

**K6. What is the single most important design decision?**
"Moving a child only on repeated evidence and always showing the evidence. It protects the child from a wrong label and the teacher from a black box."

**K7. If you started again, what would you change?**
"Collect consented sample recordings earlier, write the reading service's password in from the start, and ask a school head about funding in the first month."

**K8. What would you do with another six months?**
"A pilot in two classes, measure the settings against real readings, add Filipino reading, give the speech service a key, and complete the privacy items."

**K9. What did you personally build?**
Answer with what you did. Know one flow end to end (file 01, scene 6) and one decision you can defend with its basis (file 03).

**K10. What if a panelist says "that is not DepEd's rule"?**
"Correct. That one is our own setting and we say so. The DepEd rule is the Phil-IRI band; our rule for moving a child is separate and is the thing we want to check with real data."
