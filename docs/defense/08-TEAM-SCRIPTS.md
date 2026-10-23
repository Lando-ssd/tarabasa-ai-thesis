# 8. Team scripts: four speakers, one defense

The panel will ask **anyone** on the team about **anything**. So everybody studies files 0 to 7. This file only decides **who speaks when**, so the defense flows and nobody talks over anyone.
Write the real names in the boxes. Use the letters below until you decide. Times are for a 15 minute presentation; scale them to your panel's time limit.

---

## 1. The split

| Speaker | Owns | Scenes and parts (file 1) | Time | Holds |
|---|---|---|---|---|
| **A, the lead** | The hook, the problem, the close, and the first answer to tricky questions | Part A, Part B, Part D | 3 min 30 s | The clicker, the closing |
| **B, the child's side** | What a child and a parent do | Scenes 1, 3, 4, 5, 6 and a game | 4 min | The phone or the laptop for the demo |
| **C, the adults' side and the AI** | What a teacher, a parent and the Administrator see; how activities are written | Scenes 2, 7, 8, 9, 10 | 3 min 30 s | The teacher and Admin screens |
| **D, technology, evidence, business** | How it is connected, why these tools, the tests, the limits, the money | File 2 (the network picture), file 3 section 24, file 4 | 3 min 30 s | The network picture on paper or on a slide |

Total: about 14 minutes, one minute spare for a slow internet moment.

**Order on stage:** A, then B, then C, then D, then A for the close.

**Before you start:** B presses the keep-awake button 15 minutes earlier (file 6, section 2.5) and signs in once on the phone so the microphone permission is already given.

---

## 2. Each speaker's card

Say these lines in your own voice; do not memorize them word for word. The numbers and facts must stay exact.

### Speaker A, the lead

1. **Hook and problem:** read **Part A** and **Part B** of file 1 almost word for word. They are written to be said aloud.
2. **Hand-off to B:** "To show you what this means for one child, [B] will walk you through Maria's day."
3. **The close, after D:** read **Part D** of file 1.
4. **In questions:** take Part A (the ten standard questions), Part B (problem and purpose) and Part K (tricky questions) first. If another speaker is better placed, say "[Name] built that, [Name]?"

### Speaker B, the child's side

Point at the screen as you speak.

1. "Maria is in Grade 1. Her parent made her profile and agreed to the privacy terms, because a child cannot consent. The parent's answers about how Maria reads decide where her first reading check starts."
2. "Maria taps Scan my card. The card holds only her code, never her PIN, so a lost card is not a lost account. She types four numbers and Tara the owl says hello."
3. "The first reading check is a placement, not a test. A child who cannot read yet is given six letters, not a story. Maria never sees a score."
4. "Home shows four steps: Letter Explorer, Word Builder, Sentence Reader, Story Reader. Maria is on [her step]. Her parent sees the same four steps, with one sentence about what that step means."
5. "For a real reading, Tara reads the story first, Maria practises twice with no marks, then reads for real. About ten seconds later she has a friendly result. The recording is already deleted."
6. **Hand-off to C:** "Behind Maria's screen, her teacher sees something different. [C]."

If the internet is slow, say the line from file 1, Part F: "The first request after a quiet period can take about a minute because we use free hosting. In classroom use a paid plan removes this."

### Speaker C, the adults' side and the AI

1. "A teacher is confirmed by a person, our Administrator, before she can see any child. A class holds one grade only, because the curriculum is written grade by grade."
2. "The teacher sees the school's words, Independent, Instructional and Frustration. A child never sees them. A parent sees the child's own four steps."
3. "An alert always shows its evidence and one suggested activity from the teacher's own list. The system never gives anything to a child by itself; the teacher presses the button."
4. "To write activities the teacher picks a grade, a skill and a class. The AI sees a grade, a skill, a topic and a short summary of the class, never a child's name. The teacher approves every draft and places it in a level. Easy, Medium and Hard are our levels, not DepEd scores, and the screen says so."
5. "The parent sees progress in plain words and one thing to practise at home. The Administrator keeps the system healthy, and every action is written in a log with a reason."
6. **Hand-off to D:** "[D] will show how all of this is connected, and why we chose it."

### Speaker D, technology, evidence, business

Show the network picture (file 2, section 2) on paper or a slide.

1. "One Laravel server on Render, one TiDB database. Three small services do three jobs: a reading service with Vosk speech recognition, an activity writer using Gemini, and a recommender that is only formulas. Gmail sends the confirmation emails. The website and the phone app share the same rules on the server."
2. "Everything adaptive is a written rule with a reason. No model was trained, because there is no collection of children's recordings to train on. We say so before you ask. It is our first limit and our next step."
3. "We have 788 automated tests, live runs with computer voices through the real speech service, and we use the website ourselves. The phone app is in its first real-phone test now. We have not tested with real children over time."
4. "Privacy: the parent consents, recordings are deleted when scoring ends, there are no ads and no trackers, and a parent can delete a child's profile."
5. "The business: a school subscription. Our cash cost for a steady school is about PHP 3,000 a month, mostly fixed, so two schools at PHP 1,500 would cover it. Those prices are assumptions we have not tested; the pilot is how we test them."
6. **Hand-off to A:** "[A] will close."

---

## 3. Who answers which questions

The first person answers; the second adds only if something important is missing. Everyone uses the four-part shape: **what we did, what the source shows, what we did not measure, what we would do next.**

| File 5 part | First answer | Backup |
|---|---|---|
| A. The ten standard questions | A | D |
| B. The problem and the purpose | A | C |
| C. How the system works (sign up, sign in, scoring, background work) | B for the child's side, D for the technology | C |
| D. The AI and the rules | C for activities and alerts, D for scoring and the recommender | A |
| E. Curriculum and Phil-IRI | C | A |
| F. UI and UX | B | C |
| G. Privacy, ethics, security | D | A |
| H. Testing and evidence | D | B |
| I. Technology and operations | D | the teammate who built the three services |
| J. Business | D | A |
| K. Tricky questions | A | whoever knows it best |

**Questions about the reading service, the activity writer or the recommender internals** go to the teammate who built them (BldZeuz). Everyone else knows the three-line version: Vosk small English model turns the recording into words with timing; Gemini writes texts aligned to the MATATAG guide for the grade; the recommender is formulas (0.7 old score, 0.3 new, bands at 59 and 79).

---

## 4. Rules for the stage

1. **One voice at a time.** Do not correct a teammate in front of the panel. If something was wrong, the next speaker can say "to be precise, ..." and give the right number.
2. **Pass it on.** "That one is [Name]'s area" is a good answer, not a weak one.
3. **Never say** the AI was trained, that real children tested it, or that DepEd approved it (file 0, the three rules).
4. **Do not guess a number.** Say "that is one of our settings, it is in one settings file" or "I will check the appendix".
5. **If the demo fails,** B says one calm sentence ("the free hosting is waking up") and C or D switches to the localhost version or the screenshots (file 6, section 2.10). A panel judges how you recover.
6. **Nobody leaves the table** until A says thank you.

---

## 5. If someone is missing

| Missing | Who covers | How |
|---|---|---|
| A | D | D reads Part A and B; B closes |
| B | C | C drives the demo and speaks the scenes in C's order plus Scenes 1, 3 and 4 |
| C | B | B adds Scenes 2 and 7 in one sentence each; D takes the AI paragraph |
| D | C | C shows the network picture; A takes privacy, limits and business from file 4 |

The kit was written so that any one of you can read any part. That is the point of everybody studying everything.

---

## 6. Rehearsal plan

| When | What | Pass mark |
|---|---|---|
| Rehearsal 1, no laptop | Each speaker says their card aloud, with times. Trade hand-offs. | Every card is under its time |
| Rehearsal 2, with the demo | Run file 1, Part F start to end with the keep-awake button on. Practise the "slow internet" sentence. | The demo runs in 6 minutes |
| Rehearsal 3, questions | Someone who is not the speaker reads questions from file 5 at random. The first answerer answers; the backup adds. | No question is answered with a number nobody can trace |
| The day before | Run the five minute checklist (file 6, section 2.10). Charge the phone. Save screenshots of every main screen in a folder. | Everything on the checklist is ticked |

**If the panel gives you only 5 minutes:** A hook and problem 1 minute, B one real reading 1 minute, C one alert and one AI draft 1 minute, D network picture and the honest limits 1 minute, A close 1 minute.
