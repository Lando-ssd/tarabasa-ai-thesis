# "How it works": the study core. Numbers come from the app's own public page /how-it-works (read 22 November 2026) and the code.
# Block types: ('h3', text) ('p', text) ('say', text) ('ul', [..]) ('ol', [..]) ('table', [header], [[row],...]) ('svg', markup) ('note', text) ('ask', [(q,a),...]) ('warn', text)

ARCH_SVG = """
<svg viewBox="0 0 760 392" role="img" aria-label="System map">
 <defs><marker id="ar" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto"><path d="M0 0L10 5L0 10z" fill="#5b6b7a"/></marker></defs>
 <g font-family="Inter, sans-serif" font-size="12">
  <text x="10" y="18" font-weight="700" fill="#5b6b7a">WHO</text>
  <rect x="10" y="30" width="150" height="54" rx="10" fill="#e8f2fc" stroke="#1c7ed6"/><text x="85" y="52" text-anchor="middle" font-weight="700" fill="#0f5fae">Learner</text><text x="85" y="70" text-anchor="middle" fill="#334">Android app</text>
  <rect x="10" y="98" width="150" height="54" rx="10" fill="#e8f2fc" stroke="#1c7ed6"/><text x="85" y="120" text-anchor="middle" font-weight="700" fill="#0f5fae">Parent</text><text x="85" y="138" text-anchor="middle" fill="#334">Android app and web</text>
  <rect x="10" y="166" width="150" height="54" rx="10" fill="#e8f2fc" stroke="#1c7ed6"/><text x="85" y="188" text-anchor="middle" font-weight="700" fill="#0f5fae">Teacher</text><text x="85" y="206" text-anchor="middle" fill="#334">Web dashboard (app too)</text>
  <rect x="10" y="234" width="150" height="54" rx="10" fill="#e8f2fc" stroke="#1c7ed6"/><text x="85" y="256" text-anchor="middle" font-weight="700" fill="#0f5fae">Admin</text><text x="85" y="274" text-anchor="middle" fill="#334">Web panel only</text>
  <path d="M160 57H214M160 125H214M160 193H214M160 261H214" stroke="#5b6b7a" stroke-width="1.6" marker-end="url(#ar)"/>
  <text x="187" y="318" text-anchor="middle" fill="#5b6b7a" font-size="11">HTTPS</text>
  <rect x="216" y="30" width="250" height="262" rx="14" fill="#fff4e6" stroke="#ef8d2a" stroke-width="2"/>
  <text x="341" y="54" text-anchor="middle" font-weight="800" font-size="14" fill="#a8560a">TaraBasa server</text>
  <text x="341" y="72" text-anchor="middle" fill="#5b6b7a" font-size="11">Laravel 13, PHP 8.4, Docker, Render (Singapore)</text>
  <rect x="234" y="86" width="214" height="42" rx="8" fill="#fff" stroke="#e0b27a"/><text x="341" y="104" text-anchor="middle" font-weight="700" fill="#333">Website pages + phone API</text><text x="341" y="119" text-anchor="middle" fill="#5b6b7a" font-size="11">session cookie / Sanctum token</text>
  <rect x="234" y="138" width="214" height="62" rx="8" fill="#fff" stroke="#e0b27a"/><text x="341" y="156" text-anchor="middle" font-weight="700" fill="#333">The rules (services layer)</text><text x="341" y="172" text-anchor="middle" fill="#5b6b7a" font-size="11">scoring, first check, levels, alerts,</text><text x="341" y="186" text-anchor="middle" fill="#5b6b7a" font-size="11">can-this-child-read-it, games, badges</text>
  <rect x="234" y="210" width="214" height="68" rx="8" fill="#fff" stroke="#e0b27a"/><text x="341" y="228" text-anchor="middle" font-weight="700" fill="#333">Background workers</text><text x="341" y="244" text-anchor="middle" fill="#5b6b7a" font-size="11">score a recording, write activities,</text><text x="341" y="258" text-anchor="middle" fill="#5b6b7a" font-size="11">send email. The page asks:</text><text x="341" y="272" text-anchor="middle" fill="#5b6b7a" font-size="11">"is it done yet?"</text>
  <path d="M466 160H520" stroke="#5b6b7a" stroke-width="1.6" marker-end="url(#ar)"/>
  <rect x="522" y="30" width="228" height="48" rx="10" fill="#eafaf3" stroke="#1f9e83"/><text x="636" y="50" text-anchor="middle" font-weight="700" fill="#0d6b57">TiDB Cloud database</text><text x="636" y="67" text-anchor="middle" fill="#334" font-size="11">28 tables, MySQL compatible</text>
  <rect x="522" y="92" width="228" height="48" rx="10" fill="#f1ecfc" stroke="#7c5cd6"/><text x="636" y="112" text-anchor="middle" font-weight="700" fill="#4a31a0">Reading API (Vosk)</text><text x="636" y="129" text-anchor="middle" fill="#334" font-size="11">voice in, words out (teammate's service)</text>
  <rect x="522" y="154" width="228" height="48" rx="10" fill="#f1ecfc" stroke="#7c5cd6"/><text x="636" y="174" text-anchor="middle" font-weight="700" fill="#4a31a0">Activity writer (Gemini)</text><text x="636" y="191" text-anchor="middle" fill="#334" font-size="11">drafts texts, a teacher approves</text>
  <rect x="522" y="216" width="228" height="48" rx="10" fill="#f1ecfc" stroke="#7c5cd6"/><text x="636" y="236" text-anchor="middle" font-weight="700" fill="#4a31a0">Adaptive recommender</text><text x="636" y="253" text-anchor="middle" fill="#334" font-size="11">formulas only, no machine learning</text>
  <rect x="522" y="278" width="110" height="44" rx="10" fill="#fdeedb" stroke="#ef8d2a"/><text x="577" y="297" text-anchor="middle" font-weight="700" fill="#a8560a">Gmail API</text><text x="577" y="313" text-anchor="middle" fill="#334" font-size="11">emails</text>
  <rect x="640" y="278" width="110" height="44" rx="10" fill="#fdeedb" stroke="#ef8d2a"/><text x="695" y="297" text-anchor="middle" font-weight="700" fill="#a8560a">Google sign-in</text><text x="695" y="313" text-anchor="middle" fill="#334" font-size="11">optional</text>
  <path d="M466 130L520 54M466 150L520 116M466 170L520 178M466 190L520 240M466 205L520 296" stroke="#5b6b7a" stroke-width="1.4" marker-end="url(#ar)"/>
  <text x="380" y="352" text-anchor="middle" fill="#a8560a" font-weight="700">Only the server talks to the services. The phone never does, and no service receives a child's name.</text>
  <text x="380" y="372" text-anchor="middle" fill="#5b6b7a">The three AI services, and the web server, are on free hosting and sleep after about 15 quiet minutes.</text>
 </g>
</svg>
"""

READING_SVG = """
<svg viewBox="0 0 760 330" role="img" aria-label="One reading">
 <defs><marker id="ar2" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto"><path d="M0 0L10 5L0 10z" fill="#0f5fae"/></marker></defs>
 <g font-family="Inter, sans-serif" font-size="12">
  <rect x="20" y="10" width="150" height="34" rx="8" fill="#e8f2fc" stroke="#1c7ed6"/><text x="95" y="32" text-anchor="middle" font-weight="700" fill="#0f5fae">Child's app</text>
  <rect x="215" y="10" width="150" height="34" rx="8" fill="#fff4e6" stroke="#ef8d2a"/><text x="290" y="32" text-anchor="middle" font-weight="700" fill="#a8560a">TaraBasa server</text>
  <rect x="410" y="10" width="150" height="34" rx="8" fill="#f1ecfc" stroke="#7c5cd6"/><text x="485" y="32" text-anchor="middle" font-weight="700" fill="#4a31a0">Reading API (Vosk)</text>
  <rect x="605" y="10" width="135" height="34" rx="8" fill="#eafaf3" stroke="#1f9e83"/><text x="672" y="32" text-anchor="middle" font-weight="700" fill="#0d6b57">Database</text>
  <path d="M95 44V316M290 44V316M485 44V316M672 44V316" stroke="#cfd8e3" stroke-dasharray="4 4"/>
  <path d="M95 74H288" stroke="#0f5fae" stroke-width="1.8" marker-end="url(#ar2)"/><text x="192" y="68" text-anchor="middle" fill="#133">1  recording sent over HTTPS</text>
  <path d="M290 108H483" stroke="#0f5fae" stroke-width="1.8" marker-end="url(#ar2)"/><text x="387" y="102" text-anchor="middle" fill="#133">2  recording passed on</text>
  <path d="M485 142H292" stroke="#0f5fae" stroke-width="1.8" marker-end="url(#ar2)"/><text x="387" y="136" text-anchor="middle" fill="#133">3  words heard, with confidence</text>
  <rect x="215" y="156" width="150" height="46" rx="8" fill="#fff" stroke="#ef8d2a"/><text x="290" y="174" text-anchor="middle" fill="#333">4  our rules score each</text><text x="290" y="190" text-anchor="middle" fill="#333">word (fairness rules first)</text>
  <path d="M290 222H670" stroke="#0f5fae" stroke-width="1.8" marker-end="url(#ar2)"/><text x="480" y="216" text-anchor="middle" fill="#133">5  session, word bank, points, streak, badges saved</text>
  <rect x="215" y="236" width="150" height="30" rx="8" fill="#fde8e4" stroke="#e8573f"/><text x="290" y="256" text-anchor="middle" font-weight="700" fill="#a12f1c">6  recording deleted</text>
  <path d="M290 296H97" stroke="#0f5fae" stroke-width="1.8" marker-end="url(#ar2)"/><text x="192" y="290" text-anchor="middle" fill="#133">7  friendly result, word review</text>
  <text x="520" y="296" fill="#5b6b7a" font-size="11">Then teacher and parent screens, alerts</text>
  <text x="520" y="311" fill="#5b6b7a" font-size="11">and the next activity update.</text>
 </g>
</svg>
"""

LADDER_SVG = """
<svg viewBox="0 0 760 250" role="img" aria-label="The reading ladder">
 <g font-family="Inter, sans-serif" font-size="12" text-anchor="middle">
  <text x="60" y="26" font-weight="700" fill="#5b6b7a" text-anchor="start">The ladder (7 rungs)</text>
  <g>
   <rect x="20" y="36" width="96" height="40" rx="6" fill="#dfe9f6"/><text x="68" y="52" fill="#0a3d73" font-weight="700">0</text><text x="68" y="68" fill="#0a3d73">Letters</text>
   <rect x="122" y="36" width="96" height="40" rx="6" fill="#cfe1f7"/><text x="170" y="52" fill="#0a3d73" font-weight="700">1</text><text x="170" y="68" fill="#0a3d73">Words</text>
   <rect x="224" y="36" width="96" height="40" rx="6" fill="#bcd6f3"/><text x="272" y="52" fill="#0a3d73" font-weight="700">2</text><text x="272" y="68" fill="#0a3d73">Short sentences</text>
   <rect x="326" y="36" width="96" height="40" rx="6" fill="#a7c9ee"/><text x="374" y="52" fill="#0a3d73" font-weight="700">3</text><text x="374" y="68" fill="#0a3d73">Sentences</text>
   <rect x="428" y="36" width="96" height="40" rx="6" fill="#8bb9e8"/><text x="476" y="52" fill="#0a3d73" font-weight="700">4</text><text x="476" y="68" fill="#0a3d73">Short stories</text>
   <rect x="530" y="36" width="96" height="40" rx="6" fill="#6aa4e0"/><text x="578" y="52" fill="#fff" font-weight="700">5</text><text x="578" y="68" fill="#fff">Stories</text>
   <rect x="632" y="36" width="108" height="40" rx="6" fill="#3f8ad6"/><text x="686" y="52" fill="#fff" font-weight="700">6</text><text x="686" y="68" fill="#fff">Longer stories</text>
  </g>
  <text x="20" y="106" font-weight="700" fill="#5b6b7a" text-anchor="start">What a CHILD and a PARENT see (4 steps)</text>
  <rect x="20" y="114" width="96" height="34" rx="6" fill="#fdeedb" stroke="#ef8d2a"/><text x="68" y="136" fill="#a8560a" font-weight="700">Letter Explorer</text>
  <rect x="122" y="114" width="96" height="34" rx="6" fill="#fdeedb" stroke="#ef8d2a"/><text x="170" y="136" fill="#a8560a" font-weight="700">Word Builder</text>
  <rect x="224" y="114" width="198" height="34" rx="6" fill="#fdeedb" stroke="#ef8d2a"/><text x="323" y="136" fill="#a8560a" font-weight="700">Sentence Reader</text>
  <rect x="428" y="114" width="312" height="34" rx="6" fill="#fdeedb" stroke="#ef8d2a"/><text x="584" y="136" fill="#a8560a" font-weight="700">Story Reader</text>
  <text x="20" y="176" font-weight="700" fill="#5b6b7a" text-anchor="start">What a TEACHER sees (Phil-IRI words, DepEd Order 14, s. 2018)</text>
  <rect x="20" y="184" width="96" height="34" rx="6" fill="#fde8e4" stroke="#e8573f"/><text x="68" y="206" fill="#a12f1c" font-weight="700">Non-reader</text>
  <rect x="122" y="184" width="198" height="34" rx="6" fill="#fff3d1" stroke="#f2b01e"/><text x="221" y="206" fill="#7a5200" font-weight="700">Frustration</text>
  <rect x="326" y="184" width="198" height="34" rx="6" fill="#e3f5f0" stroke="#1f9e83"/><text x="425" y="206" fill="#0d6b57" font-weight="700">Instructional</text>
  <rect x="530" y="184" width="210" height="34" rx="6" fill="#dff4f6" stroke="#0e9aa7"/><text x="635" y="206" fill="#0a6670" font-weight="700">Independent</text>
  <text x="20" y="242" fill="#5b6b7a" text-anchor="start" font-size="11">Stored inside the system (shown to no one): Beginning = rungs 0 to 2, Developing = rungs 3 and 4, Proficient = rungs 5 and 6.</text>
 </g>
</svg>
"""

SECTIONS = [
 dict(id="how-1", title="1. The whole system on one page", blocks=[
  ('say', "TaraBasa AI is a reading practice and monitoring system for Grade 1 to 3 learners. A child reads aloud, a speech recognition service writes down the words, our written rules decide how it went and what comes next, and teachers and parents see the result with the evidence."),
  ('p', "There are **four kinds of user**. The **learner** reads, plays and earns badges (Android app). The **parent** adds the child, sees progress in plain words, and unlocks extra activities (Android app and web). The **teacher** manages classes, writes and assigns activities, and sees alerts and analytics (web, and the app). The **administrator** approves teachers and keeps the system healthy (web only)."),
  ('svg', ARCH_SVG),
  ('p', "**Where the rules live.** All the decisions (scoring, placement, moving up, alerts, the length check, games, badges) are written once, in the server's *services layer*. The website pages and the phone API both call those same services, so the website and the app can never disagree. The phone only draws what the server says."),
  ('p', "**What the word AI means here.** Four things, no more. (1) **Vosk**, an open-source speech recognition engine, run by the team as the Reading API. (2) **Google Gemini**, a language model that *drafts* activities through the Activity Generation API; a teacher approves every draft. (3) A **recommender**, which is only formulas. (4) **Written rules** in this repository: the first check, scoring fairness, mistake patterns, alerts, moving up and down, and the length check. **Nothing was trained and there is no dataset of children's recordings.** Every number is the team's own choice, kept in configuration files so a teacher or the adviser can question and change it."),
  ('ask', [
   ("In one sentence, what is TaraBasa AI?", "A system where a child reads aloud, speech recognition and written rules score the reading, a four-step reading path decides what comes next, and teachers and parents see the result with the evidence."),
   ("Where is the intelligence?", "In the speech recognition, in the activity drafting, and in the written rules and formulas that place a child and decide what to do next. The adaptive part is rule-based and explainable on purpose."),
  ]),
 ]),

 dict(id="how-2", title="2. One reading, step by step", blocks=[
  ('say', "A child opens a story, listens, practices twice, then reads for real. About ten seconds later she sees a friendly result. The recording is deleted as soon as it is scored."),
  ('svg', READING_SVG),
  ('ol', [
   "**Listen, practice, read.** Tara reads the story aloud first. The child gets two free practice tries that count for nothing: no points, no streak, no level, nothing saved. Then comes the real reading.",
   "**Record and send.** The phone or browser records. The screen first checks the internet and wakes the reading service if it is asleep (free hosting sleeps after about 15 quiet minutes). A reading that is too quiet is not sent.",
   "**Score in the background (website).** The recording is saved for a few seconds as a temporary file, and a background worker scores it, so the web server is never held up. The page asks every two seconds, \"is it done?\" On the phone the scoring runs inside the request, behind a guard that allows two at once and then politely says wait.",
   "**Speech to words.** The worker sends the audio to the Reading API (Vosk), which returns the words it heard, with timing and a confidence for each.",
   "**Our rules score it.** The heard words are lined up against the words of the activity. Each word is **right, skipped, or a different word**, and extra words are noted. Four fairness rules apply first (section 6 below).",
   "**Save and update.** The server saves the reading (accuracy and words per minute), adds **points (about half the accuracy: 80 percent gives 40)**, updates the **word bank** (every missed word goes in once, at most four new words per reading), the streak and the badges, checks whether the child should move a step (section 4), writes the notifications for the teacher and parents, and asks the recommender what comes next.",
   "**Delete.** The recording is deleted when scoring ends. It is never kept.",
   "**Show.** The child sees a **happy star**, how many words were right, the passage with the words to practise marked, and points. A comprehension story ends with two or three questions.",
  ]),
  ('warn', "**If something goes wrong.** If the internet drops, the app shows **Send Again** and keeps the recording; a quick failure is retried twice by itself. If the reading service is asleep, the screen says it is waking up and the child waits up to a minute. The one case we cannot recover is a drop in the middle of an upload."),
  ('ask', [
   ("Why is scoring in the background?", "Scoring takes 5 to 45 seconds. Holding a web request that long used up the few web workers, the host's health check failed and everyone saw a 502 error. Now the upload is accepted at once, a worker scores it, and the page asks every two seconds if it is done."),
   ("What was the 502 problem?", "The server was running one worker instead of four because of a missing flag (`--no-reload`), so one slow recording blocked everything, including the host's health check, and Render stopped sending traffic. We fixed the flag, moved scoring to the background, and tested with a slow fake service: the health check's slowest answer fell from 24 seconds to 1.1."),
  ]),
 ]),

 dict(id="how-3", title="3. Where a child starts: the first reading check", blocks=[
  ('say', "The first reading check is not a test to pass; it is a place to start. A child's grade is not a child's reading level, so we find where each child really is, and we never show the child a score."),
  ('ul', [
   "**One ladder of seven steps for every child,** the same for Grade 1, 2 and 3: Letters, Words, Short sentences, Sentences, Short stories, Stories, Longer stories.",
   "**It begins** on the lowest step that the parent's description of the child and the parent's three yes or no answers suggest. A Grade 3 child who cannot read yet starts on **letters**. A Grade 2 child who reads well starts on a **story**.",
   "**At most three texts.** A score of **90 percent or more** moves the check up one step, **under 70** moves it down one step, **70 to 89** stops it. Where it stops is the child's step.",
   "**Letters are the exception: up at 80 percent.** Naming 5 of the 6 letters moves the child on to the first words. Speech recognition is weakest at single letter names, and with six letters one name heard differently is already 83 percent, which on the usual line would have ended the check on the lowest step. If the first words are too hard, the check steps back down by itself.",
   "**The texts** are curated, curriculum-coded (MATATAG codes such as EN2PWS-I-2 for letter names), not generated by AI. There are 21 (three per step). They open instantly, cost no AI call, and are always the same quality. *They were written by the team and still need a Grade 1 and a Grade 2 teacher's review.*",
   "**The child sees** one of four friendly steps. The parent sees the same four steps. Only the teacher sees the Phil-IRI words.",
  ]),
  ('ask', [
   ("Why not just use the child's grade?", "Because a grade says where a child is enrolled, not what they can read. A Grade 3 child who cannot read yet should start on letters; the curriculum is built to teach those foundations."),
   ("Where do 90, 70 and 80 come from?", "They are our own settings. 90 matches the Phil-IRI boundary between instructional and independent, 70 is our lower line, and 80 for letters is our fix for weak letter-name recognition. They live in one settings file and are the first things a pilot will check."),
  ]),
 ]),

 dict(id="how-4", title="4. How a child moves up or down", blocks=[
  ('say', "One reading never moves a level. To move up a child needs three strong readings from at least two different activities. To move down, two weak readings in a row."),
  ('table', ["Move", "Rule", "Why"], [
   ["**Up one step**", "At least **3 readings of 90 percent or more**, from at least **2 different activities**, among the **last 5 readings since the child's last move**.", "A single perfect reading of six words shows the child read those words, not that a step is mastered. Reading programmes decide on repeated evidence."],
   ["**Down one step**", "**2 readings in a row under 70 percent**, on texts that were not too long for the child.", "One weak reading never moves anyone. Moving down is careful too."],
   ["**Long enough to count**", "A reading only counts if it has at least this many words for the step the child is leaving: Letters 4, Words 6, Short sentences 10, Sentences 17, Short stories 27, Stories 45.", "A tiny text is not evidence."],
  ]),
  ('p', "**Worked example.** Maria is on *Word Builder* (the Words step). Her readings since her last move: 92 percent on *The Cat* (6 words or more), 95 percent on *The Cat* again, 88 percent on *My Dog*, 93 percent on *My Dog*. Three readings are 90 or more (92, 95, 93) and they come from two activities (*The Cat* and *My Dog*), all within the last five. So Maria moves up to *Sentence Reader*. If instead she had read *The Cat* three times at 92, 95 and 94, that would be only one activity, so she would not move yet."),
  ('p', "**Teacher words.** The Phil-IRI bands (DepEd) are for one reading: Independent 97 percent or more, Instructional 90 to 96, Frustration under 90. Our own move-up and move-down rule is separate from those bands, and the two are shown side by side, never claimed to be the same thing."),
  ('ask', [
   ("Why three readings and not one?", "One reading of a short text shows that the child read that text. Before this rule, one good reading jumped a child a whole level, which was a bug we found and fixed."),
   ("Why 90 and 70? Does DepEd say that?", "DepEd's bands are 97 and 90 for one passage. The 70 and the three-reading rule are our own settings, and we say so."),
  ]),
 ]),

 dict(id="how-5", title="5. The words for levels: Phil-IRI, four steps, and what is stored", blocks=[
  ('say', "Three audiences, three vocabularies, one ladder underneath. Teachers see DepEd's Phil-IRI words. Children and parents see four friendly steps. The system stores three internal levels that no one sees."),
  ('svg', LADDER_SVG),
  ('ul', [
   "**Teachers:** Non-reader (a child whose first check did not get past letters), Frustration, Instructional, Independent. Source: DepEd Order No. 14, s. 2018. Mapping from the ladder: rung 0 Non-reader; rungs 1 and 2 Frustration; rungs 3 and 4 Instructional; rungs 5 and 6 Independent. *This mapping is our own.*",
   "**Children and parents:** Letter Explorer (rung 0), Word Builder (rung 1), Sentence Reader (rungs 2 and 3), Story Reader (rungs 4, 5 and 6). The names tell a child what they do, not how they score, and follow the order the MATATAG curriculum teaches reading: letters, words, sentences, stories.",
   "**A parent never sees** the school's words or the system's internal names, because \"frustration level\" sounds harsh to a parent.",
  ]),
 ]),

 dict(id="how-6", title="6. How a reading is scored fairly", blocks=[
  ('say', "The speech recognizer was built for adult speech, so our scoring is forgiving by design. Every word is right, skipped or a different word, and we apply fairness rules first. Near misses still count as mistakes."),
  ('ul', [
   "**Same sound, same word.** Words that sound the same (*sun* and *son*), spelling variants, numbers (*2* and *two*), a missing apostrophe, and one word written as two (*sand* and *castle* for *sandcastle*) are accepted.",
   "**Letter names.** When a child names letters aloud, the recognizer often writes a letter name as another word (*are* for R, *see* for C) or two letters as one word (*hr* for H then R). We repair only the letters the service marked wrong or skipped, and **only to add credit: a reading never scores lower because of it.** A letter that was not asked for never earns anything, and a row said in the wrong order gets no extra credit.",
   "**Not sure.** A word heard as a different word, but only faintly (confidence under 0.5), is marked *Not sure* and left out of the score, the word bank and the alerts. **At most 25 percent** of the words can be Not sure, so this can never hide a reading that is mostly wrong. A skipped word is never Not sure.",
   "**Near misses stay mistakes:** *ripe* and *right*, *hit* and *hat*.",
  ]),
  ('warn', "**Honest limits.** Names and rare same-sound words can still be marked wrong. The Not sure numbers and the letter names were set and measured with **synthesized (computer) voices**, not children. A child's voice will make the recognizer write other words, so the word lists must be extended from real recordings. In our test the letter repair raised rows read right from 14 of 18 to 18 of 18 on 70 live recordings with two computer voices, with no score lowered. The recognizer's own score is kept next to ours."),
  ('ask', [
   ("How do you know the letter repair does not give free marks?", "We measured it: on 70 live recordings with two computer voices, rows right went from 14 to 18 of 18, no score went down, and a row said in the wrong order scored exactly what the service gave. A first, more generous version gave 16.67 to a wrong order, so we rewrote it."),
  ]),
 ]),

 dict(id="how-7", title="7. From mistakes to alerts to a suggestion", blocks=[
  ('say', "The system looks for what a child's missed words have in common, tells the teacher who needs help today with the evidence, and offers one suggestion from the teacher's own approved activities. It never acts by itself."),
  ('h3', "Mistake patterns (10 kinds)"),
  ('p', "Each missed word is sorted into one of ten kinds by comparing it with the word the child said. A pattern is reported only after **at least 3 readings and 6 missed words**, and only when it covers **at least 25 percent** of the misses. It is called *early* until 5 readings. Each pattern comes with one thing to try and the MATATAG code it practises."),
  ('table', ["Kind", "What it looks like", "Code"], [
   ["Small words skipped", "skips *the*, *was*, *said*", "RL1VWK-I-3"], ["Word endings", "plurals, -ed, -ing", "EN2PWS-II-2"], ["Longer words in pieces", "*sunset* read as *sun*, *set*", "RL1PWS-I-5"], ["Vowel sounds", "*hat* read as *hit*", "RL1PWS-I-4"], ["First sounds", "*cat* read as *hat*", "RL1PWS-I-3"], ["Last sounds", "*pig* read as *pit*", "RL1PWS-I-3"], ["Letter blends", "*frog* read as *fog*", "RL1PWS-I-5"], ["Look-alike letters", "*b* and *d*, *was* and *saw*", "RL1PWS-I-2"], ["Words skipped", "skips words while reading", "RL1CAT-III-1"], ["A different word", "reads another word", "RL1PWS-I-5"],
  ]),
  ('h3', "When a teacher is told (alerts)"),
  ('table', ["Alert", "Rule"], [
   ["**Needs support**", "The latest reading is under 70 percent and less than 14 days old. If the last 3 were all under 70, it says so as stronger evidence. A later good reading clears it."],
   ["**Ready to move up**", "The last 3 readings were all 90 percent or more, not counting readings the app already used to move the child."],
   ["**Moved up a level**", "The child's level name changed upward in the last 14 days."],
   ["**Gone quiet**", "The child has read before, but not for 7 days or more."],
   ["**Just starting to read**", "The child's step is Letters. It is shown kindly, as where reading instruction begins."],
  ]),
  ('p', "**What an alert never does:** it shows its evidence and one suggested activity from the teacher's own approved activities. It never gives anything to a child. The teacher presses the button, or marks it handled."),
  ('h3', "Can this child read it? (the length check)"),
  ('table', ["Step", "Comfortable", "A stretch (up to)"], [
   ["Letters", "6 words", "10 words"], ["Words", "10", "14"], ["Short sentences", "16", "24"], ["Sentences", "28", "40"], ["Short stories", "45", "60"], ["Stories", "75", "100"], ["Longer stories", "130", "170"],
  ]),
  ('p', "More than the stretch is **too long and cannot be given**. A stretch can be given with a note. An activity for a whole class or group is refused when at least **20 percent** of the children whose level is known could not read it, so it can be given to the group that can. These numbers are the team's own, because the curriculum gives no word counts."),
  ('p', "**What a child is offered.** A child sees everything a teacher gave them and everything a parent unlocked. Only the **order** changes: the recommender's pick comes first (*Picked just for you*), then, if a clear mistake pattern exists, a story that practises it, and a text far too long goes last (*A bigger one for later*). **It never hides an activity.**"),
  ('ask', [
   ("Can the system make a wrong decision about a child?", "Yes. That is why it moves slowly, why alerts show their evidence, why nothing is given to a child without a teacher or a parent acting, and why a teacher can overrule levels."),
  ]),
 ]),

 dict(id="how-8", title="8. How the AI writes activities, and what the teacher decides", blocks=[
  ('say', "The AI only drafts. A teacher approves every activity and decides its level. The AI is never told a child's name."),
  ('ol', [
   "The teacher opens **Generate** and picks a grade, a skill, the type of reading, how many Easy, Medium and Hard, and optionally **For a class**.",
   "For a class, our server builds a short note from the class's own readings: the step most of the class is on and how many words suit it, the main kind of mistake and the words several children missed, and the topics parents said their children like. **A thing is only said about the class when at least 2 children show it.** The teacher reads those facts before pressing **Write**, and they stay on each activity (*Written for Sampaguita because...*).",
   "The request runs in the background (about two minutes, longer if the service was asleep). The server sends **a grade, a skill, a topic and the short note, never a name,** to the activity-writing service, which asks Google Gemini for texts aligned to the MATATAG guide. It returns all three levels; we keep the ones asked for.",
   "The drafts wait in **To review**. The teacher places each one in **Easy, Medium or Hard**, edits it, or rejects it. **One credit is charged only when activities are saved.** A request that never finishes closes as failed after 20 minutes and charges nothing.",
   "An approved activity can be assigned to a learner, a reading group or a class (it must pass the length check), and optionally shared in the Open Repository.",
  ]),
  ('p', "**Easy, Medium and Hard are this app's own levels** (word familiarity and length for the grade), not DepEd scores. The curriculum gives no word counts, so we say so on the screen and the teacher decides. Examples of the writer's length bands: Grade 1 passage 20 to 30, 31 to 45, 46 to 65 words; Grade 3 passage 40 to 60, 61 to 90, 91 to 130."),
  ('p', "**Did it work?** Teacher Analytics compares, for the same children, readings of activities written for their class against the teacher's general activities. Each child is compared with themselves. A number appears only when at least **3 children** read both kinds, and a difference under **3 points** is called *about the same*. It is a signal to watch, not proof."),
  ('ask', [
   ("Could the AI write something wrong or unsafe?", "Yes, which is why a teacher must approve each draft. Only approved activities can be given to children, and a teacher is told not to type a child's name in the notes because the text goes to a Google AI model."),
  ]),
 ]),

 dict(id="how-9", title="9. The recommender, in numbers", blocks=[
  ('say', "The recommender is a small service of formulas. It keeps a score for each skill and names the next skill to practise. It uses no machine learning."),
  ('ul', [
   "**Attempt score.** Foundational skills: 0.80 accuracy + 0.20 speed. Fluency: 0.40 accuracy + 0.35 speed + 0.25 prosody. Comprehension: the quiz score.",
   "**New skill score = 0.70 old + 0.30 attempt.** A slow-moving average, so one reading cannot swing it.",
   "**Difficulty bands:** easy up to 59, medium up to 79, hard above. **Confidence** starts at 0.60 and rises 0.08 per attempt, up to 0.95.",
   "**Prosody** is the expression and rhythm of reading. The reading service gives a heuristic score; it is used only in this formula and is never shown to a child.",
  ]),
  ('p', "**Worked example.** A fluency attempt with accuracy 87.5, speed 100 and prosody 55: 87.5 x 0.40 + 100 x 0.35 + 55 x 0.25 = **83.75**. If the old score was 97.4, the new score is 97.4 x 0.70 + 83.75 x 0.30 = **93.3**. We confirmed that a live result equals the formula exactly."),
  ('note', "The recommender is the teammate's service (version 1.0.0). Its formulas are on record in the study kit; ask the teammate for the internals."),
 ]),

 dict(id="how-10", title="10. Games, word bank, points, streak and badges", blocks=[
  ('say', "Practice games are free play: they never change points, streak or level, and nothing a child says or taps in a game is kept."),
  ('table', ["Game", "What the child does", "Mic?"], [
   ["**Balloon Pop**", "Reads one word to pop its balloon", "Yes"],
   ["**Word Train**", "Reads a whole row to make the train roll (a child on Letters gets rows of letters)", "Yes"],
   ["**Sentence Stars**", "Reads a sentence to catch a star", "Yes"],
   ["**Syllable Steps**", "Claps the parts of a longer word, joins them, then reads it in a sentence. For children from the Words step up. Only the last step is scored", "Yes"],
   ["**Sound Hunt**", "Listens and taps: which of 3 words rhymes with, starts like or ends like another (3 levels). Codes RL1PA-I-3, RL1PA-I-5, RL1PWS-I-3", "No"],
  ]),
  ('ul', [
   "**A round** has 3 things to read, each with up to 2 tries; \"Tara is not sure\" is shown instead of \"wrong\". If 2 or more are right the first time, the child climbs one level. A sitting is up to 3 rounds. A row or sentence counts as read when at least **66 percent** of its words are heard.",
   "**The Games page recommends one game** from the child's step, the words they miss and their main mistake. Every game stays playable.",
   "**Word bank.** After each reading every missed word goes in once as *Struggling*; after one correct reading it becomes *Improving*, after 3 it is *Mastered*. At most 4 new words per reading. It is a team design of spaced review.",
   "**Points** are about half the accuracy percent. The **streak** counts days in a row in Philippine time. The **weekly goal is 5 real readings**. There are **100 badges in 10 groups** (Reading, Streaks, Accuracy, Speed, Levels, Goals, Points, Bookshelf, Games, Special). The **Bookshelf** holds one book per finished activity, with the best score; *Read Again* is free practice and never scored.",
  ]),
  ('warn', "**Honest limits of the games.** The word lists were written by the team and must be reviewed by a Grade 1 teacher. They were tried in a browser with computer voices, not by a real child. The letter A is spelled so that a Philippine English voice does not read it like the letter I; this was chosen by reading, not by listening, and must be checked by ear. Sound Hunt says whole words only, because a voice cannot say a single sound."),
 ]),

 dict(id="how-11", title="11. Accounts, login and who can see what", blocks=[
  ('say', "A child cannot consent, so the parent creates the profile. A teacher is verified by a person before seeing any child. Every role sees the same facts in its own words."),
  ('table', ["Who", "How they get in", "Safety"], [
   ["**Parent / Teacher**", "Sign up with email and password, tick the consent box, **confirm the email** (a link good for 60 minutes) before the first sign in. Google sign-in is optional.", "Password stored only as a hash. 5 sign-in tries, then a wait."],
   ["**Learner**", "**Learner Code** (like TB26-48293, with a check digit) + a **4-digit PIN**, or scan the **QR card**. The QR holds only the code, never the PIN.", "5 wrong PINs lock the child for 15 minutes and tell the guardians. A forgotten PIN is reset only by a linked guardian with their password; 3 resets a day; no easy PINs."],
   ["**Teacher**", "Can sign in after confirming the email, ask for activities with 2 free credits, but **needs Admin approval** before anything touches real students.", "Every class action checks approval on the server. Adding a learner: type the last 5 characters of the code; refusals are rate-limited (20 an hour) and a yes never names the child."],
   ["**Admin**", "One account, set from a secret. No self-registration. Website only.", "Every action is in the activity log with a reason."],
  ]),
  ('ul', [
   "**One grade per class, no exception.** A Grade 1 class takes only Grade 1 children. A teacher who handles several grades opens one class per grade. A DepEd multigrade class is deliberately not modelled. Adding a grade as an active teacher is a request the Admin confirms with the school.",
   "**Website and phone.** The website uses a session cookie; the phone uses a Sanctum token. A token for one kind of user cannot reach another's screens. The Admin is website only.",
  ]),
 ]),

 dict(id="how-12", title="12. Privacy and what happens to the data", blocks=[
  ('say', "A recording is deleted as soon as it is scored. There are no ads and no outside trackers. A parent can delete a child, or the whole account, and everything about the child goes."),
  ('ul', [
   "**Law.** The Data Privacy Act of 2012 (Republic Act No. 10173). A child's information is sensitive and a child cannot consent, so the **parent consents** and the policy version shown is stored with the consent record.",
   "**Recordings** wait as a temporary file for seconds and are **deleted when scoring ends**. Child photos are optional (a separate consent tick), kept in the database, and visible only to the child, a linked guardian and the child's class teacher.",
   "**No third parties:** no analytics, trackers, embeds or ads; fonts are hosted by us; exactly two cookies (session and XSRF). The visitor's browser makes one bare health request to the helper services to wake them (disclosed in the policies).",
   "**What each outside service gets.** Render and TiDB: the app's data. Gmail API: the email text. Gemini: a grade, a skill, a topic and a class note, never a name. The speech service: the recording, deleted after scoring.",
   "**Delete.** A guardian removes a child (with another guardian linked it only unlinks the one who asked). A person deletes their own account with their password. The Admin can do it for someone who cannot sign in. The Admin log keeps a reason but not the person's name after deletion.",
  ]),
  ('warn', "**Open items, said by us first:** whether registration with the National Privacy Commission applies, a short Privacy Impact Assessment, a breach plan, written agreements with the hosting services, a retention rule, and a lawyer's reading of the Terms. They are on our list and are Recommendation seven. Nobody on the team is a lawyer. The Reading API has no password yet, a known risk."),
 ]),

 dict(id="how-13", title="13. The Open Repository and the demo checkout", blocks=[
  ('say', "Teachers can share activities in the Open Repository, and a parent can unlock a paid one for a child. The payment in this build is a demo checkout with pretend money."),
  ('ul', [
   "**The demo checkout** is TaraBasa's own page (Card, GCash, Maya). There is **no box to type a card number**, nothing is sent to any payment company, and a demo-only picker chooses how it ends: Approved, Declined or Cancelled. It is switched on in the hosting settings (`PAYMENTS_MODE=demo`) and is off by default.",
   "**One place unlocks.** The unlock happens only after a successful payment. The page the parent returns to can never unlock anything. It is one unlock per child and activity, even if the parent presses twice or closes the browser. A checkout nobody finishes is closed after 30 minutes and unlocks nothing.",
   "**The same record and history** are used for demo and for the later real provider test mode, so the real step is a change of provider, not a new design.",
   "**What it proves:** TaraBasa's own screens and rules. **It does not prove that PayMongo or a bank would accept a payment.** Say *demo checkout with pretend money*, never *payments work*. Real payments are Recommendation eight, only after legal review.",
  ]),
 ]),

 dict(id="how-14", title="14. The database, the stack and how it runs", blocks=[
  ('say', "One Laravel server on Render, one TiDB database, three small services, Gmail for email. The same rules serve the website and the phone. It was built to be cheap and upgradeable, not to be clever."),
  ('table', ["Part", "What", "Why"], [
   ["**Mobile app**", "React Native 0.86 with Expo SDK 57, TypeScript; expo-audio (microphone), expo-camera (scan the card), expo-speech (read aloud), Lottie; Android APK", "One codebase for the child, parent and teacher screens, calling the same server"],
   ["**Web**", "Laravel Blade templates, plain HTML and custom CSS for each role, JavaScript", "Fast to build, no build step for the pages"],
   ["**Server**", "PHP 8.4, Laravel 13, Sanctum, Docker on Render (Singapore, free plan)", "Mature, secure by default, free tier, upgradeable"],
   ["**Database**", "TiDB Cloud Starter (MySQL compatible, Singapore); SQLite for local and tests", "Free plan; the same code runs on paid plans"],
   ["**AI**", "Vosk (Reading API), Google Gemini (Activity Generation API), rule-based recommender", "Open source and pretrained, no model to train"],
   ["**Email**", "Gmail API over HTTPS", "Render's free plan blocks the usual mail ports"],
  ]),
  ('ul', [
   "**The database** has 28 application tables (37 with the framework's), 45 foreign keys, 58 migrations. Six groups: accounts; learners and classes; reading activities; Open Repository; reading and gamification; monitoring and system.",
   "**On start** the server runs the migrations, syncs the Admin, wakes the services, starts one queue worker, two scoring workers, then serves the site with four PHP workers. The flag that makes this work (`--no-reload`) is the one behind the 502 incident.",
   "**Free hosting sleeps** after about 15 quiet minutes and wakes in about a minute. The three helper services sleep too, and a visitor's browser wakes them. This is a known cost; Recommendation five is always-on hosting.",
   "**Tests.** 912 automated tests pass (counted on 21 November 2026). They show the rules do what they say and that fixes did not break old behaviour. They do **not** show the rules are educationally right; that needs a pilot.",
   "**Counts to know:** 223 routes (138 website, 85 phone API), 34 service classes, 132 web views, 67 app screen files, 252 commits (25 August to 21 November 2026), 4 user roles, 5 games, 100 badges, 10 mistake patterns, 7 ladder steps, 4 child steps, 3 outside AI services.",
  ]),
 ]),

 dict(id="how-15", title="15. What it does not do", blocks=[
  ('ul', [
   "It does not decide for a teacher or a parent. Alerts and suggestions come with their reasons, and a person acts.",
   "It does not hide an activity from a child, and it does not show a child a score on the first reading check.",
   "It does not keep a child's recording after it has been scored.",
   "It does not learn by itself. No number changes unless a person changes it.",
   "It does not measure whether reading improved. Only a consented classroom pilot can.",
   "It is not made or endorsed by the Department of Education. It follows DepEd documents.",
  ]),
 ]),
]
