# 3. The basis of every decision

For each decision: **What it is**, **Why it exists**, **The basis** (with the label from file 00: [CURRICULUM], [RESEARCH], [TEAM CHOICE]), **How sure**, and **How to say it**.
The numbers on the live page `/how-it-works` ("How TaraBasa decides") are the same numbers as here: they are read from the app's settings, so if the panel opens that page it matches what you say.

Quick map: 1 problem | 2 oral reading | 3 Phil-IRI and level names | 4 four steps | 5 reading path | 6 first check | 7 moving up and down | 8 scoring | 9 AI activity writing |
10 Easy Medium Hard | 11 can this child read it | 12 grade rule | 13 reading groups | 14 alerts | 15 AI suggestions | 16 mistake patterns | 17 recommender | 18 word bank | 19 games |
20 syllable method | 21 points and badges | 22 UI and UX | 23 privacy | 24 why rules and not a trained model | 25 the numbers that are ours.

---

## 1. The problem and why Grades 1 to 3

- **What it is.** Many Filipino children do not read well, and the foundation years are Grades 1 to 3.
- **The basis.**
  - PISA 2022: the Philippines scored **347 in reading, about 76th of 81 countries** (340 in 2018, then the lowest of 79). [RESEARCH, reported by news summaries of the OECD results; open the OECD page before quoting]
  - World Bank **learning poverty** (the share of 10-year-olds who cannot read and understand a simple story): about **90 percent** for the Philippines (a 2021 report; a 2022 update says 91). [RESEARCH, reported by news; open the World Bank report before quoting]
  - DepEd's own programme **Hamon: Bawat Bata Bumabasa** (DepEd Memorandum No. 173, s. 2019): every learner a reader at their grade level, built on six elements of reading (oral language, phonological awareness, phonics, vocabulary, fluency, comprehension). [CURRICULUM]
  - The **MATATAG Curriculum** (DepEd Order No. 10, s. 2024) puts foundational skills first in the early grades and is written grade by grade, with competency codes such as RL1PWS-I-5. [CURRICULUM]
  - Your own **teacher survey** (the Validation Board in Chapter 3). Quote only the numbers you confirm in Appendices D and E, with the "n =" for each group. The review found percentages without respondent counts; fix that before the defense.
- **Why Grades 1 to 3.** The first three years are when decoding is learned. A child who cannot decode by Grade 3 struggles in every subject after. A system that catches the problem early has the most value. It is also the age group the Phil-IRI and MATATAG treat as the foundation stage.
- **How sure.** The statistics are well documented but you read them second-hand. The curriculum documents are real; their exact wording must be checked on deped.gov.ph.
- **How to say it.** "Our topic is the foundation of reading in Grades 1 to 3. The national numbers show a gap, DepEd has its own programme for it, and we saw that teachers cannot listen to every child often enough. We built a tool that does the listening and tells the teacher what it heard."

## 2. Why oral reading and a speech recognition service

- **What it is.** The child reads aloud and software writes down what it heard.
- **Why it exists.** Reading aloud shows accuracy and fluency directly, and it is how DepEd's own reading test works (the Phil-IRI is an oral reading test). A teacher cannot listen to thirty children every week; software can.
- **The basis.** The Phil-IRI measures oral reading accuracy. [CURRICULUM] The National Reading Panel (2000) reported that guided repeated oral reading improves word recognition, fluency and comprehension; Therrien (2004) found the same for repeated reading in a meta-analysis. [RESEARCH: RECALLED in the reference pack; confirm before quoting]
- **Why Vosk.** It is open source (built on Kaldi; Povey et al., 2011, RECALLED), free per request, and we run it ourselves, so **a child's voice is not sent to a large company**. It returns each word with its time and a confidence. [TEAM CHOICE]
- **The honest limit.** Speech recognition is less accurate on children (Potamianos and Narayanan, 2003, RECALLED), and Vosk's small model is built for adult speech. That is why the scoring is forgiving (section 8) and why we say results assist a teacher and never replace the teacher's judgment.
- **How to say it.** "We used speech recognition because reading aloud is how reading is assessed, and we chose an open source engine so children's voices stay in a service we control. We know it is weaker on children, so our rules are lenient and the teacher has the final say."

## 3. Phil-IRI and the level names (Independent, Instructional, Frustration)

### What is the Phil-IRI?
The **Philippine Informal Reading Inventory** is DepEd's tool for finding out how a learner reads. A teacher gives the learner graded passages, listens to the learner read aloud, counts the word reading errors, then asks comprehension questions. The results place the learner at a reading level. DepEd uses it as a pretest and posttest of the school year. The current policy is **DepEd Order No. 14, s. 2018**. [CURRICULUM, order number from search; DepEd's site did not open for us, so copy the exact title from deped.gov.ph]

### What do the three levels mean?
| Level | Meaning in plain words | Word reading (one passage) | Comprehension (2018-era dashboards) |
|---|---|---|---|
| **Independent** | The child reads this level comfortably alone. | 97 percent or more | 80 to 100 percent |
| **Instructional** | The child learns at this level **with a teacher's guidance**. | 90 to 96 percent | 60 to 79 percent |
| **Frustration** | Too hard: too many errors, little understanding, even with help. | below 90 percent | below 60 percent |

A **Non-reader** is a learner who cannot yet recognize and sound out letters and simple words.

### Where does the "Frustration level" idea come from?
It comes from the **Informal Reading Inventory** tradition in reading education: a teacher finds the level where a child reads easily alone (independent), learns best with help (instructional), and gives up or guesses (frustration).
The Philippine version was adapted from that tradition; an older Phil-IRI document says its criteria were adapted from **Johnson, Kress and Pikulski (1987)**. [RESEARCH: found by search, not read; I could not confirm the origin anywhere else, so say "adapted from the informal reading inventory tradition" and name Johnson, Kress and Pikulski only if you open the document]
**Caution on numbers:** an older Phil-IRI document gives slightly different comprehension cut-offs (instructional 59 to 79, frustration 58 and below) from the 2018-era dashboards (60 and below 60). **Our app uses only the word reading bands (97 and 90).** It does not map its quiz to the comprehension bands.

### What does TaraBasa do with the three levels?
- **For one reading**, the name is given by accuracy: 97 or more Independent, 90 to 96 Instructional, under 90 Frustration. [CURRICULUM for the numbers]
- **For a child's standing**, the child's reading step is mapped to a band: Letters is Non-reader, Words and Short sentences are Frustration, Sentences and Short stories are Instructional, Stories and Longer stories are Independent.
  **That mapping is the team's own choice**, not DepEd's. [TEAM CHOICE]
- **Who sees which words.** **Teachers** see the Phil-IRI words (Non-reader, Frustration, Instructional, Independent). **Children and parents** both see the four friendly steps (next section): Letter Explorer, Word Builder, Sentence Reader, Story Reader. A parent also gets a plain sentence ("Mia is learning to sound out and read words") and a home tip. No one outside the teacher's screens sees the Phil-IRI words, and the system's own stored level (Beginning, Developing, Proficient) is shown to nobody. Why: "frustration level" sounds harsh to a parent, and a parent who sees the same step names as their child can talk about it together.
- **One more honest point.** One source says the 2018 manual is written for Grades 3 to 6. Using the bands for a single passage read by a Grade 1 or 2 child is **our own extension**. And our moving-up rule (section 7) is a separate rule from the Phil-IRI bands; we show both and never claim they are the same thing.
- **How to say it.** "We use DepEd's Phil-IRI words because teachers already know them. The three bands are about oral reading accuracy: 97 and above independent, 90 to 96 instructional, below 90 frustration. We borrowed the words; our rule for moving a child up or down is our own, and we say so."

## 4. The child's four steps: Letter Explorer, Word Builder, Sentence Reader, Story Reader

- **What they are.** The names a child sees for where they are.
| Step | Name | Skill | Curriculum code and meaning |
|---|---|---|---|
| 1 | **Letter Explorer** | Letters and Sounds | RL1PWS-I-1: produce the sound of each letter (Grade 1 Reading and Literacy) |
| 2 | **Word Builder** | Sounding Out Words | RL1PWS-I-5: sound out words accurately |
| 3 | **Sentence Reader** | Reading Sentences | RL1CAT-III-1: read sentences with speed, accuracy and expression |
| 4 | **Story Reader** | Reading Stories | EN3CAT-I-1 and EN3CAT-I-2: read with speed, accuracy and expression and understand stories (English Grade 3) |
- **Why they exist.** Children aged 6 to 9 cannot be asked to understand "Frustration". They can understand "I am a Word Builder, and the next road is sentences". The names are what the child **does** (explore, build, read), not a judgment.
- **The basis.** The order **letters, words, sentences, stories** is the order MATATAG teaches reading, and it follows the phases of learning to read words (Ehri, 2005). [CURRICULUM; RESEARCH] The names are the team's. [TEAM CHOICE]
- **Behind them.** Seven finer rungs: Letters, Words, Short sentences, Sentences, Short stories, Stories, Longer stories. They fold into the four steps: 0 is Letter Explorer, 1 is Word Builder, 2 and 3 are Sentence Reader, 4 to 6 are Story Reader. The finer rungs let the first check place a child more precisely than four steps could.
- **How to say it.** "The child sees four friendly steps that follow the order the curriculum teaches reading. The teacher sees the exact rung and the Phil-IRI word."

## 5. The reading path (the progress path) and why it fits Grades 1 to 3

- **What it is.** A path on Home with the four steps, the child's step marked, and a ring that shows "great readings so far" toward the next step ("2 of 3").
- **Why it exists.** A young child needs to **see** progress and know what is next. It must move in small steps so it is visible. It is shown as a road, not a table, because "where am I on the road" is concrete.
- **Why it is connected to the child's reading step and not to the grade.** In Grades 1 to 3 many children **cannot read well yet**, so their grade says little about what they can read. A Grade 3 child who cannot read yet starts on Letter Explorer; a Grade 2 child who reads well starts on stories. The grade still decides the **class**, the **activities' grade** and the **curriculum code**, but the **step** decides what the child is given to read. This follows the adviser's idea that the system should "find where the child is, not where the grade says". The first check's own documents say the same (a struggling Grade 3 reader may need Grade 1 content). [TEAM CHOICE, supported by MATATAG's grade by grade design and by the Phil-IRI idea of reading levels]
- **What makes it open and move.** The path moves one step up after 3 strong readings and one step back after 2 weak readings (section 7). It never skips steps, and a single reading never moves a child.
- **How to say it.** "The path is tied to the child's reading, not to the grade, because in Grades 1 to 3 the grade does not tell you who can read. The grade picks the class and the curriculum codes; the reading step picks the book."

## 6. The first reading check

- **What it is.** The first thing a new child does: at most **3 short texts**, each read aloud.
- **Why it exists.** To place the child before anything else, so the first activities fit. Without it, every child would get Grade-level text.
- **The rule.** [TEAM CHOICE]
  - It begins on the lowest step that the **parent's description** and the parent's **three yes or no answers** suggest. (The three questions change with the grade: Grade 1 asks about letters, sounds, blending; Grade 2 about short sentences, sight words, answering a question; Grade 3 about a paragraph, main idea, a word from context.)
  - 90 percent or more moves up one step; under 70 moves down one step; 70 to 89 stops. **Letters move up at 80** because speech recognition is weakest on single letter names, and with six letters one miss is 83 percent.
  - The child is never shown a score. The dots show only how many texts have been read so far, never a total, because the check can stop after any text.
- **Why curated texts and not AI.** The 21 check texts (3 for each of 7 steps) are written by the team and coded to MATATAG (for example EN2PWS-I-2 "identify alphabet letter names"). They open instantly (about 0.2 seconds, against 8 to 105 seconds when an AI wrote them), have the same difficulty for every child, and cost nothing per use. [CURRICULUM for the codes]
- **Honest limits.** The texts were written by the team, so a Grade 1 and a Grade 2 teacher should review them. They were tested with computer voices only. Letter names are the hardest thing for speech recognition; we measured that and built a repair for known problems (section 8).
- **How to say it.** "The first check is a placement, not a test. At most three texts. We start from the parent's answers so a child who cannot read does not face a story, and we never show the child a score."

## 7. Moving a child up or down

- **What it is.** After each real reading, the system decides whether the child stays, moves up one step or moves down one step.
- **The rule** (settings file `config/progression.php`). [TEAM CHOICE]
  - **Up**: at least **3 readings of 90 percent or more**, from at least **2 different activities**, among the **last 5 readings since the last move**. The child moves **one step**.
  - A reading only counts if it has enough words for the step the child is leaving: Letters 4, Words 6, Short sentences 10, Sentences 17, Short stories 27, Stories 45.
  - **Down**: **2 readings in a row under 70 percent** on texts that were not too long for the child. One weak reading never moves anyone.
- **Why this way.** A single perfect reading of six words shows the child read those six words, not that they mastered a step. Reading programmes decide on **repeated evidence**; the Phil-IRI measures a whole passage, and repeated reading is what builds fluency (National Reading Panel, 2000; Therrien, 2004). Before this rule, one 90 percent reading could jump a child a whole level, which was a bug we found and fixed.
- **How to say it.** "We do not move a child on one good day. Three strong readings from two different activities move a child up one step; two weak readings in a row move a child back one. The numbers are ours, in one settings file, and the first thing a real-children pilot should tune."

## 8. How a reading is scored

- **What it is.** Which words were read right, skipped, or said as a different word.
- **The steps.** The recording goes to the speech service. The words it heard are lined up against the activity's words. Each word is right, skipped or a different word; extra words are noted. [TEAM CHOICE for the rules, applied to the service's output]
- **Fairness rules** (these exist because the recognizer was built for adults):
  1. **Same-sounding words are accepted** (sun and son), spelling variants (mangoes and mangos), numbers (2 and two), a missing apostrophe, one word written as two (sand castle).
  2. **Letter names repaired.** When a child names letters, the recognizer may write "are" for R or "hr" for H then R. We repair only what the service marked wrong, only add credit, never move words, and a row said in the wrong order gets no extra credit. We measured this on 70 recordings with two computer voices: rows right went from 14 of 18 to 18 of 18 and no score went down.
  3. **"Not sure" words.** A word the recognizer heard as a different word but only faintly (confidence under 0.5) is left out of the score, the word bank and the alerts. At most 25 percent of the words can be "not sure", so it can never hide a reading that is mostly wrong.
  4. **Near misses stay mistakes** (ripe and right, hit and hat).
- **The honest limits.** Names and rare same-sound words can still be marked wrong; the numbers were set with computer voices and need checking with children's recordings; we could not use the service's own pronunciation scores as a mark.
- **How to say it.** "We do not trust the speech recognizer blindly. We accept same-sounding words, we leave out words it only heard faintly, and we repair the letter-name habits we measured. We never hide a mostly wrong reading, and the teacher sees the evidence."

## 9. How the AI writes activities

- **What it is.** A teacher asks for reading activities; an AI language model drafts them.
- **The path.** Teacher picks grade, skill (foundational reading, reading fluency or reading comprehension), type of reading (word reading, sight words, phonics reading, sentence reading, passage reading, timed reading, repeated reading, reading comprehension), how many of each level, an optional topic and an optional class. Our server sends only a grade, a skill, a topic and a short note to the **activity writer**, which asks **Gemini** to write texts aligned to the MATATAG curriculum for that grade and returns **all three levels**. We keep the levels the teacher asked for.
- **Why an AI at all.** A teacher cannot write hundreds of correctly leveled texts. A language model can draft them in two minutes. [RESEARCH: Gemini Team, 2023, LISTED]
- **Why a teacher must approve.** A language model can write text that is wrong, too hard or off-topic. So every draft is **checked by a teacher and placed in a level, or rejected**. Only approved activities can be given to a child. This is called "human in the loop".
- **What the AI knows about the children.** Nothing. When the teacher chooses "For a class", our server builds a short note from the class's own readings (the step most children are on, the kind of mistake several children make, the words several missed, the topics parents said children like) and the teacher **reads it before pressing Write**. A fact is only included when **at least 2 children** show it, so one child's habit is never presented as the class's. No name, code or single child's details are ever sent. The facts stay on the activity ("Written for Kammunggay because..."), so the reason can always be seen.
- **The "Did it work?" check.** Analytics compares the same children's readings of activities written for their class against the teacher's general activities. A number is shown only when at least 3 children read both kinds, and a difference under 3 points is "about the same". It is a signal to watch, not proof, because a text can be easier or more interesting.
- **Credits.** A new teacher has 2 free writing requests; sharing an approved activity earns 2 more. This limits the cost of AI use. [TEAM CHOICE]
- **How to say it.** "The AI is a drafting assistant. It knows only a grade, a skill, a topic and a class summary, never a child. A teacher approves every draft and decides its level. And we show the teacher exactly what the AI was told."

## 10. Easy, Medium and Hard: where the words and numbers come from

- **What it is.** Three levels for each activity, shown to teachers with a one-line description.
- **The exact wording** (it is on the Generate screen and in each activity's "Why this level" box):
  - **Easy: "Familiar words, short text."** Very familiar words that are easy to sound out, in the shortest text for the grade.
  - **Medium: "Grade level words, longer text."** Words a child at this grade should know, in text that is a little longer.
  - **Hard: "Harder words, longest text."** The hardest words a child at this grade should meet, in the longest text for the grade.
  - The box adds: "Levels come from how familiar the words are and how long the text is. They are this app's levels, not official DepEd scores. The AI cannot know what is hard for your learners, so you decide."
- **Where the numbers come from.** The activity writer fixes how many words each level has, per type of reading and grade. Examples (spoken words a child reads): a **word list** for Grade 1 is Easy 5 to 7, Medium 8 to 10, Hard 11 to 14; for Grade 3 it is 8 to 10, 11 to 14, 15 to 20. A **passage** for Grade 1 is Easy 20 to 30, Medium 31 to 45, Hard 46 to 65; for Grade 3 it is 40 to 60, 61 to 90, 91 to 130. A **comprehension** text for Grade 2 is 35 to 50, 51 to 75, 76 to 105. We copy those bands so the teacher sees the range without waiting for the service. [TEAM CHOICE, the teammate's service]
- **Why they are not DepEd levels.** The curriculum guides give **no word counts**, and there is no official "Easy, Medium, Hard" score. So we say plainly that these are the app's levels. That honesty is the basis.
- **Why three levels.** Three is simple for a teacher to sort and matches the recommender's three difficulty bands (easy up to 59, medium up to 79, hard above). The difficulty a teacher chooses is what the recommender and the child's picker use.
- **How to say it.** "Easy, Medium and Hard are our app's levels, based on how familiar the words are and how long the text is. They are not DepEd scores, because DepEd gives no such scale. That is why a teacher places every activity in its level."

## 11. "Can this child read it?" (the length check)

- **What it is.** A rule that refuses to give a child an activity that is too long for their step.
- **Why it exists.** The level of an activity (Easy, Medium, Hard) only says how hard it is **for its grade**. A 69-word Hard reading could be given to a Grade 1 child who cannot read words yet. The rule looks at the **child's step**.
- **The numbers.** Words a child can read comfortably, and as a stretch:

| Step | Comfortable | Stretch (up to) |
|---|---|---|
| Letters | 6 | 10 |
| Words | 10 | 14 |
| Short sentences | 16 | 24 |
| Sentences | 28 | 40 |
| Short stories | 45 | 60 |
| Stories | 75 | 100 |
| Longer stories | 130 | 170 |

  More than the stretch cannot be given. A stretch is given with a note. For the first two steps, sentences and stories count 1.6 times their length, because connected text is harder than a list of words. A whole class or group is refused when at least 20 percent of the children whose step is known could not read it, so it can be given to the group that can.
- **The basis.** [TEAM CHOICE] The numbers are anchored on the first check's own texts and checked against the writer's length bands. The curriculum gives no word counts, so these are our figures.
- **How to say it.** "A level says how hard text is for a grade; this rule asks whether this child can read this many words. There is no override, because the teacher asked that it cannot be forced."

## 12. One grade per class, and no multigrade class

- **What it is.** A class holds one grade only, even if a teacher handles several grades.
- **The basis.** The MATATAG curriculum is written grade by grade (DepEd Order No. 10, s. 2024). In a DepEd school the **school head** assigns which grade a teacher teaches, so a teacher cannot choose grades on their own: **taking a grade away saves at once; adding a grade is a request that the Admin confirms with the school**. [CURRICULUM; TEAM CHOICE for the check]
- **Multigrade.** DepEd defines a multigrade class as two or more grades under one teacher (DepEd Orders 96 s. 1997 and 81 s. 2009, found by search; confirm on deped.gov.ph). **We chose not to model it.** A teacher with several grades opens one class for each. A Grade 2 learner cannot be added to a Grade 1 class, and a Grade 2 activity cannot be given to a Grade 1 class.
- **How the Add learner screen helps.** As the teacher types a code, the system checks it. If the child is of another grade, the Add button stays off and says why.
- **How to say it.** "A class is one grade because the curriculum is by grade and the school decides who teaches which grade. If a panelist asks about multigrade, say it is a deliberate scope choice: a multigrade teacher opens a class for each grade."

## 13. Reading groups inside a class

- **What it is.** Each class is split automatically into three groups: **Needs most support** (Non-reader and Frustration), **Instructional**, **Independent**. A teacher can assign an activity to one group.
- **Why it exists.** In one classroom children read at very different levels. Teaching in small groups at the child's instructional level is **guided reading**, which the MATATAG Key Stage 1 guidance describes. [CURRICULUM, from the guide; wording to confirm]
- **A child who improves** stays in the same class (a class is a grade) and appears in the next group. Group assignments follow the child.
- **How to say it.** "Teachers do not have to sort children by hand. The class is grouped by reading level, and the groups move as the children do."

## 14. Alerts: when a teacher is told

- **The rules** [TEAM CHOICE, numbers in `how-it-works`]:
  - **Needs support**: the latest reading is under 70 percent and less than 14 days old (stronger evidence if the last 3 were).
  - **Ready to move up**: the last 3 readings were 90 percent or more, not counting readings already used to move the child.
  - **Moved up a level**: the Phil-IRI name changed upward in the last 14 days.
  - **Gone quiet**: the child has read before but not for 7 days or more.
  - **Just starting to read**: the measured step is Letters. This is shown kindly, as where reading instruction begins.
- **Why 70 here and Phil-IRI's 90?** The Phil-IRI judges a whole passage with a trained teacher. Our readings are short, so under 90 would fill the teacher's list with children at 85 to 89. Under 70 means "look at this child today". [TEAM CHOICE]
- **What an alert never does.** It shows its evidence and one suggested activity from the teacher's **own** approved activities. It never gives anything to a child by itself. The teacher presses a button or marks it handled.
- **Why it exists.** Teachers should look at the children who need help today, and see why. Dashboards help most when they lead to an action (Kaliisa et al., 2024, VERIFIED: dashboards had a small effect on achievement but helped participation).
- **How to say it.** "An alert is a reason to look, not a decision. It always shows the evidence."

## 15. The "AI suggestion"

- **What it is.** The suggested activity under an alert, and the "best match" order in the parent's library and the child's list.
- **How it picks (this is the honest part).** It is **rule-based scoring**, not a learned model. For a child or group it takes the teacher's own approved activities, keeps only those of the **right grade**, drops any that is too long for anyone in the group (section 11), prefers the length closest to comfortable, prefers topics the parents said the children like, and prefers texts that practise the child's main mistake pattern (section 16).
- **For a child's own list** the recommender's pick comes first ("Picked just for you"), then a story that practises the pattern ("Practice word endings"). **Nothing is ever hidden.** A text far too long goes last, marked "A bigger one for later".
- **How to say it.** "Our suggestions are rules with reasons, so a teacher can ask why and get an answer."

## 16. Mistake patterns

- **What it is.** The system notices what a child's missed words have in common.
- **The ten kinds** (each has a curriculum code and one thing to try):

| Kind | What it looks like | Code |
|---|---|---|
| Small words skipped | skips "the", "was", "said" | RL1VWK-I-3 |
| Word endings | drops or changes plurals, -ed, -ing | EN2PWS-II-2 |
| Longer words in pieces | reads sunset as sun, set | RL1PWS-I-5 |
| Vowel sounds | hat read as hit | RL1PWS-I-4 |
| First sounds | cat read as hat | RL1PWS-I-3 |
| Last sounds | pig read as pit | RL1PWS-I-3 |
| Letter blends | frog read as fog | RL1PWS-I-5 |
| Look-alike letters | b and d, was and saw | RL1PWS-I-2 |
| Words skipped | skips words | RL1CAT-III-1 |
| A different word | reads a different word | RL1PWS-I-5 |

- **When a pattern is reported.** At least **3 readings** and **6 missed words**, and only if it covers at least **25 percent** of the misses. It is called "early" until 5 readings.
- **The basis.** The codes are MATATAG competencies. [CURRICULUM] The method is a plain **comparison of letters and sounds**, in the spirit of the error analysis reading teachers do by ear. It is **not a trained model**. [TEAM CHOICE]
- **How to say it.** "A pattern is reported only when there is enough evidence, and it comes with a curriculum code and one thing to try at home."

## 17. The recommender: "what to read next"

- **What it is.** A small service, built by the team, that keeps a score for each skill and names the next skill to practise. **It uses formulas, not machine learning.**
- **The formulas** (service version 1.0.0; confirm on its settings page). [TEAM CHOICE]
  - **Attempt score**: for **foundational reading** 0.80 accuracy plus 0.20 speed; for **reading fluency** 0.40 accuracy plus 0.35 speed plus 0.25 prosody; for **reading comprehension** the quiz score alone.
  - **Skill score** updates by a **moving average**: new score = 0.70 old plus 0.30 the new attempt (alpha 0.30), so one reading cannot swing it.
  - **Difficulty** from the score: up to 59 easy, up to 79 medium, above hard. **Confidence** starts at 0.60 and rises 0.08 per attempt up to 0.95. A level change needs 2 pieces of evidence, and the same skill is not chosen more than 2 times in a row.
- **A worked example for the panel.** A reading has accuracy 87.5, speed 100, prosody 55. Fluency attempt = 87.5 x 0.40 + 100 x 0.35 + 55 x 0.25 = 35 + 35 + 13.75 = **83.75**. If the old score was 97.4, the new score = 97.4 x 0.70 + 83.75 x 0.30 = **93.3**. We confirmed the live service returned exactly these numbers.
- **The honest limits.** The three skills are the service's own grouping; we translate them into the curriculum's subdomains (Phonics and Word Study, Comprehending and Analyzing Text). "Speed" is based on correct words per minute and is not adjusted by grade. The weights are the teammate's choice and are not from a study.
- **How to say it.** "The recommender is a formula. It moves a skill score slowly so one reading cannot swing it, and it explains itself."

## 18. The word bank

- **What it is.** The words a child keeps missing.
- **The rules.** After each scored reading, every missed word goes in once as **Struggling**. A word already there that the child reads right moves to **Improving** after the first correct reading and to **Mastered** after **3 correct readings**. One reading adds at most **4 new words**. A word the checker was only "not sure" about is never added.
- **Why.** A word is practised soon after a miss and then less and less: **spaced review**. The cap stops one hard passage from flooding the bank.
- **The basis.** [TEAM CHOICE] The idea of spaced review is common in learning research, but **no study was checked for these exact numbers**. Say so.
- **Where it is used.** Games offer these words first; Alerts and the parent's "Practise at home" list use them.

## 19. The practice games

- **What they are.** **Balloon Pop** (read a word), **Word Train** (read a row; a child on the Letters step gets rows of letters), **Sentence Stars** (read a sentence), **Syllable Steps** (split, join and read a longer word).
- **Why they exist.** Practice should not feel like a test, and a child needs more reading than a teacher can assign. **Every game ends with the child reading out loud**, because tapping alone is not learning. [RESEARCH: Hirsh-Pasek et al., 2015, LISTED: children learn best from apps that are active, engaged, meaningful and social]
- **The rules** [TEAM CHOICE, in `config/practice_games.php`]. A round has 3 things to read; each gets up to 2 tries; **"Tara is not sure"** is shown, never "wrong"; 2 or more right the first time climbs a level; a sitting is up to 3 rounds. A row or sentence counts as read when **at least 66 percent** of its words are heard. The level a game starts at follows the child's step. A game is **free play**: it never changes points, streak or level, and nothing the child says is kept.
- **Why free play.** If games changed the real level, a child could repeat an easy game to climb. The real level changes only from real readings.
- **What each game offers first.** The child's own missed words, then words that practise the child's main mistake, then the level's list. The Games page marks one game as recommended.
- **How to say it.** "Games are practice, not marks. Every one ends with the child reading aloud, and none can change the child's level."

## 20. The syllable method (pamamaraang pantig)

- **What it is.** Splitting a longer word into syllables and joining it again: **hear and echo, split it, join it, read it in a sentence**. Only the last step is scored.
- **The basis.** The MATATAG Grade 1 Reading and Literacy guide has the competencies **RL1PA-I-2** ("segment a two-three syllable word into its syllabic parts") and **RL1PWS-I-5** ("sound out words accurately"), and asks for explicit, systematic instruction and the **gradual release of responsibility** (the teacher says it, then together, then the child alone). The four steps follow that order. [CURRICULUM; wording read by the team] Young children split syllables more easily than single sounds (Liberman et al., 1974). [RESEARCH: RECALLED]
- **The team's choice.** It starts with **closed syllables** (sunset: sun-set), and uses no open syllables like "baby" at first, because open syllables change in English. **No study was found that proves closed syllables must come first.** It is common practice in structured literacy programmes. Say that. [TEAM CHOICE]
- **Why only the last step is scored.** The parts (bas, ket) are not words, and a speech recognizer is unreliable on isolated non-words.
- **How to say it.** "It comes from DepEd's own Grade 1 guide. The closed-syllable order is our choice and we say so."

## 21. Points, streak, weekly goal and badges

- **Points.** About half the accuracy percent per real reading (80 percent gives 40). Practice tries and games give none. [TEAM CHOICE]
- **Streak.** **Days in a row** with a real reading, counted in Philippine time. It is worked out from the readings, never stored as a counter, so it cannot be wrong.
- **Weekly goal.** 5 real readings a week (one setting).
- **Badges.** 100, in ten groups (reading, streak, accuracy, speed, level, goal, points, shelf, games, special). Earned from real history, so a child who already deserves one gets it. [TEAM CHOICE]
- **Why.** Small rewards help young children keep reading. The manuscript cites self-determination theory (Ryan and Deci, 2020) and a gamification review (Wang, Harun and Yuan, 2024); check those pages before quoting a finding.
- **What we avoid.** No leaderboard that ranks children against each other, no timers that punish, no spending.

## 22. The UI and UX decisions, and why each one exists

Every screen was designed for one of three people: a **child (6 to 9)**, a **parent**, and a **teacher or Admin**. The labels: [GUIDELINE] = a widely used design standard (Material Design, WCAG, Nielsen's usability heuristics), not a child-specific study.

| Decision | Why it exists | Basis |
|---|---|---|
| **Big passage text** (23 pixels or more on a phone, larger on a tablet), the **Lexend** font, and **five text sizes** with the default set by grade (Grade 1 largest) | Emergent readers need large, clear print; some children need it larger. | [TEAM CHOICE]. Lexend was designed for reading comfort; the evidence for it is mixed, so do not claim it improves reading. |
| **Four-step path, no scores for children** | A young child understands where they are on a road, not a percentage. | [TEAM CHOICE] |
| **The first check never shows a score** | It is a placement, not a pass or fail. | [TEAM CHOICE] (the placement notes, Part 4.2) |
| **The result star is always happy**; the sad star appears only for "we could not hear you"; games say "Tara is not sure", never "wrong" | A low score on a first try should not make a six-year-old feel bad. The teacher is told instead. | [TEAM CHOICE]; supported by the idea that feeling capable keeps motivation (Ryan and Deci, 2020, cited in the manuscript) |
| **Tara the owl** speaks every instruction | A friendly guide; a child who cannot read yet can still follow. | [TEAM CHOICE]; "social and engaging" (Hirsh-Pasek et al., 2015, LISTED) |
| **Instructions in three ways**: spoken, one short line of text, and the next button glows | Written prompts alone are not reliable for children 8 and under. | [RESEARCH] Yadav et al. (2020), 90 children aged 2 to 8, LISTED |
| **Tap only, no dragging, for children** | Among 7 to 8 year olds 83 percent could tap, 40 percent swipe and 30 percent drag and drop. | [RESEARCH] same study; small sample, a reason, not proof |
| **Large tap targets**: at least 48 points on the phone, 44 pixels on the web | A small finger needs a big target. | [GUIDELINE] Material Design 48 dp, WCAG 2.5.5 and Apple 44 |
| **Sign in with a card (QR) and a 4-digit PIN** | Children have no email and cannot manage passwords; a card can be scanned. | [TEAM CHOICE]; also data minimization |
| **Two free practice tries before a real reading** | The gradual release of responsibility: I do, we do, you do. | [CURRICULUM] the Grade 1 guide; Pearson and Gallagher (1983) |
| **The microphone is tested on the device first**, and a recording too quiet is not sent | Avoids a failed upload and a confusing result. | [GUIDELINE] error prevention |
| **"Send Again"** keeps the recording on the phone and retries a dropped connection by itself (quickly failed sends only) | Slow internet is normal; the child should not read again. | [GUIDELINE] visibility of system status and error recovery |
| **Parent screens show the child's own four steps** (the same names as on the child's Home) with a plain sentence, and a "Practise at home" tip | A parent and child can talk about the same words, "frustration level" sounds harsh, and a parent needs something to do tonight. A child who has not done the first check shows "First check not done", never a guessed step. | [TEAM CHOICE]; parent involvement helps reading (Hernandez and Decena, 2024, VERIFIED) |
| **Teacher screens use windows and avoid scrolling**; activities are placed by drag and drop (buttons on a phone) | Teachers work between classes and asked for it. | [TEAM CHOICE] from the teachers' and instructor's feedback |
| **Alerts show evidence and one action** | A teacher needs a reason and a next step, not a wall of numbers. | [RESEARCH] Kaliisa et al. (2024), VERIFIED |
| **One look across web and app**; icons, not emoji; one main action per screen; calm colours with warm orange for the main button | Consistency, so a person who learns one screen understands the others. | [GUIDELINE] Nielsen's consistency and standards |
| **Reduced motion respected**; text size control; high contrast text | Some children are sensitive to movement; some need larger text. | [GUIDELINE] WCAG |
| **No ads, no chat, no outside links on child screens, no outside trackers** | A child must be safe. | [TEAM CHOICE]; Data Privacy Act |

**How to say it.** "Every screen exists for a reason: a child needs to see progress without a score; a parent needs something to do; a teacher needs evidence and an action. Where we followed a study we say which; where it was our judgment we say that too."

## 23. Privacy, consent and safety

- **The law.** The **Data Privacy Act of 2012 (Republic Act No. 10173)**. A child's information is sensitive, and a child cannot consent, so the **parent** creates the profile and gives two ticks. Everyone has the right to have their data removed (Section 16(e), found by search; read it before quoting).
- **What we did.** Parent consent with the policy version recorded; a "last 5 characters" code check that does not reveal who a child is; recordings **deleted after scoring** (kept only for a few seconds as a temporary file); the activity writer never receives a name; fonts and scripts are hosted by us (no outside tracking); a delete button for a child or an account; an Admin log without personal names.
- **What is still open (say it honestly).** Check whether the National Privacy Commission requires registration; a short Privacy Impact Assessment; a breach plan; written agreements with the hosting services; the teammate's reading service has no password yet; no real-child testing yet. The risk register is in `docs/legal/LEGAL-RISK-REGISTER.md`.
- **How to say it.** "We built privacy in: the parent consents, we keep little, we delete recordings, and we show what each outside service receives. We know what remains open and we have a list."

## 24. Why written rules and not a trained model

- **The reasons.**
  1. **There is no data.** Training needs a collection of children's recordings; none exists yet, and collecting them needs consent and an ethics process.
  2. **Explainability.** A teacher, a parent and an adviser can read a rule and question it. A model gives an answer without a reason.
  3. **Safety.** A written rule cannot surprise us with an unexpected decision for a child.
  4. **It is a better first step.** Every number is in one settings file, so once children use the system the team can measure and tune them.
- **What is pretrained and not ours.** The speech recognizer (Vosk) and the language model (Gemini). Everything else is rules and formulas.
- **How to say it.** "Our contribution is a framework where every adaptive decision has a written rule and a reason, which is the right foundation before any model is trained. We did not train a model, and we say so."

## 25. The numbers that are ours (so you never say "DepEd says")

| Setting | Value | Source |
|---|---|---|
| First check moves up / down / stops | 90 / under 70 / 70 to 89 (letters up at 80), at most 3 texts | Team choice |
| Move up | 3 readings of 90 or more, from 2 activities, in the last 5 since the last move | Team choice |
| Move down | 2 readings in a row under 70 | Team choice |
| Words needed for a reading to count | Letters 4, Words 6, Short sentences 10, Sentences 17, Short stories 27, Stories 45 | Team choice |
| "Not sure" word | confidence under 0.5, at most 25 percent of words | Team choice |
| Phil-IRI word bands for one reading | Independent 97 or more, Instructional 90 to 96, Frustration under 90 | **DepEd Order 14, s. 2018** (recalled; confirm) |
| Mistake pattern | 3 readings, 6 missed words, 25 percent of the misses | Team choice |
| Alerts | under 70 and under 14 days; last 3 at 90 or more; 7 days quiet | Team choice |
| Can this child read it | comfortable and stretch word counts per step; class refused at 20 percent | Team choice |
| A class fact about the class | at least 2 children | Team choice |
| "Did it work?" | at least 3 children, "about the same" under 3 points | Team choice |
| Game round | 3 things, 2 tries, 2 to climb, "heard" at 66 percent | Team choice |
| Word bank | Mastered after 3 correct, at most 4 new words per reading | Team choice |
| Weekly goal | 5 readings | Team choice |
