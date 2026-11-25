# The spoken script, slide by slide. Source of truth for the PDF and for the speaker notes in the PowerPoint.
# Markup inside strings: **bold**, *italic*. Write numbers the way they are SAID.
# Roles are SUGGESTED (the paper only names Tyron Pulga as Project Manager). Change the speaker here and rebuild.

TYRON = "Tyron Pulga (Project Manager)"
AARON = "Aaron Acuyan (Developer)"
KEN = "John Kenneth Rojas (UI/UX Designer)"
JB = "John Benedict Morbos (Technical Writer)"

SLIDES = [
 dict(n=1, title="Title page", speaker=TYRON, mins="0:30", short="Tyron",
  onscreen="Title, the four team members with roles, the university, the adviser, November 2026.",
  do="Stand together. Tyron holds the clicker. Look at the panel, not the screen.",
  say=[
   "Good day, panel, and good day to our adviser, Mr. Jecris Sabanal. We are Team TaraBasa AI from the College of Computer Studies, University of Cebu Banilad.",
   "Our capstone is **TaraBasa AI: an AI-powered adaptive reading intervention system with gamified learning for Grade 1 to 3 learners.**",
   "I am Tyron Pulga, the Project Manager. With me are Aaron Acuyan, our developer; John Kenneth Rojas, our UI/UX designer; and John Benedict Morbos, our technical writer.",
   "We will present for about twenty-five minutes, and then we will show the system live.",
  ],
  handoff="Click to the next slide.",
  asked=[("What do Hustler, Hacker, Hipster and Writer mean?", "They are our team labels for the four roles: project manager, developer, UI/UX designer and writer. (Confirm each person's title before the defense; the paper only names Tyron as Project Manager.)")]),

 dict(n=2, title="Concept and explainer video", speaker=TYRON, mins="3:30", short="Tyron",
  onscreen="'TaraBasa AI in one picture': five numbered steps, the four reading steps, and the box 'What AI means here'.",
  do="Play the team's own explainer video first (about two minutes). If the video is not ready, skip to 'After the video' and talk through the picture, pointing at steps 1 to 5. Appendix A of this guide has a ready narration for the video.",
  say=[
   "**Before the video.** Before the details, here is the whole idea in one picture. Please watch our short explainer video.",
   "**After the video.** In one sentence: a child reads aloud, the system listens, scores every word with written rules, picks the next activity, and tells the teacher and the parent.",
   "Step by step. One: the child reads aloud on the Android app, after a short listen and a practice. Two: a speech recognition service called Vosk turns the voice into words. Three: our written rules score every word as correct, mispronounced, unsure or skipped. Four: a rule-based recommender picks the next activity, but one reading never moves a child's level. Five: the teacher and the parent see the result on their dashboards.",
   "The child never sees a score. A child sees a four-step path: Letter Explorer, Word Builder, Sentence Reader and Story Reader. Teachers see the Department of Education's own Phil-IRI names: Non-reader, Frustration, Instructional and Independent, from DepEd Order Number 14, series of 2018.",
   "We want to be clear about the word AI, because it is in our title. In this project, AI means four things. Vosk, an open-source speech recognition engine. Google Gemini, which drafts activities that a teacher always approves. A rule-based recommender. And written rules for scoring and for levels.",
   "**We did not train a model, and there is no dataset of children's recordings.** We say this first, so that you can judge us on what is true.",
  ],
  handoff="\"Now, why this study? John Benedict will explain what teachers and parents told us.\"",
  asked=[("If you did not train anything, why is it called AI?", "The speech recognition and the activity writer are AI services, and the system adapts. We call the adaptive part rule-based and explainable on purpose: there are no children's recordings to train on, and every decision must be explainable to a teacher and a parent. The manuscript uses AI for the services and the assistance, not for a model of our own.")]),

 dict(n=3, title="Why this study: what teachers and parents told us", speaker=JB, mins="1:00", short="John Benedict",
  onscreen="Four survey figures: 70.8%, 54.2%, 62.5% (n = 24 teachers) and 75.6% (n = 86 parents).",
  do="Say only these four numbers. They are the ones that can be read from Appendices D and E.",
  say=[
   "Why this study? We asked the people who live with the problem. We surveyed twenty-four Grade 1 to 3 teachers and eighty-six parents. The results are in Appendices D and E of our manuscript.",
   "Among the teachers, **seventy point eight percent have thirty-one to forty learners in one class.** Fifty-four point two percent say most of their learners can read simple words but struggle with sentences. And sixty-two point five percent say most learners read slowly but try to sound words out on their own. Among the parents, **seventy-five point six percent speak Bisaya or Cebuano at home.**",
   "So the picture is a large class, many different reading levels, and parents who want to help but have little time and little English at home. One teacher cannot listen to every child often enough. That is the gap TaraBasa AI is built for. It is also why our sixth recommendation asks for Bisaya and Filipino support.",
  ],
  handoff="\"These findings shaped our objectives. Tyron.\"",
  asked=[
   ("Chapter 3 quotes different percentages (90.9%, 84.7%, 36.4%, 63.6%).", "Those come from an earlier, smaller survey. The final numbers are the ones in Appendices D and E, with 24 teachers and 86 parents, and they are the ones on this slide. We are correcting Chapter 3 so the paper and the appendices agree."),
   ("Is 24 teachers and 86 parents enough?", "It is enough to confirm the need, which was Objective 1. It is not a study of effect, and we do not claim it is. The effect on reading is what the pilot in Recommendation 1 is for."),
  ]),

 dict(n=4, title="Objectives of the study", speaker=TYRON, mins="2:00", short="Tyron",
  onscreen="The general objective, the four specific objectives (word for word from Chapter 1), and a blue box under each: 'In the system'.",
  do="The outline asks for the objectives in toto, so READ them as written. Then, for each one, say where it lives in the running system (the blue boxes).",
  say=[
   "Our general objective, as written in Chapter 1: **This study aims to develop TaraBasa AI: An AI-Powered Adaptive Reading Intervention System with Gamified Learning for Grade 1 to 3 Learners. The system uses AI-powered speech recognition to assess learners' English reading proficiency and provide adaptive reading interventions through gamified learning activities.**",
   "**Objective one:** to gather data on the English reading difficulties, instructional needs, and reading intervention practices of Grade 1 to 3 learners through surveys and interviews with teachers and parents. In the system, this is the survey of twenty-four teachers and eighty-six parents.",
   "**Objective two:** to determine an AI-powered speech recognition mechanism capable of assessing learners' English reading proficiency. In the system, this is Vosk behind a separate Reading API, with written scoring rules.",
   "**Objective three:** to integrate an AI-assisted adaptive reading intervention mechanism that delivers gamified reading activities and learning recommendations based on learners' assessed reading proficiency. In the system: the four-step reading path, teacher-approved AI activities, practice games and badges.",
   "**Objective four:** to implement a real-time monitoring and progress-reporting module that enables teachers and parents to track learners' reading performance, intervention progress, and reading achievements. In the system: teacher analytics and alerts, the parent dashboard, and the admin health page and activity log.",
   "Later we will show what each one has as evidence, and what is still to be shown.",
  ],
  handoff="\"Next, the scope, and the limits we set for ourselves.\"",
  asked=[("How did you measure that each objective was met?", "Slide twelve answers that: what the system does, the evidence for each objective, and what is still to be shown. Objective one has the survey. Objective two was checked end to end with recorded readings, not yet with children's voices. Objectives three and four are working in the app, and 912 automated tests pass. The effect on reading needs the pilot.")]),

 dict(n=5, title="Scope and limitations of the study", speaker=TYRON, mins="2:00", short="Tyron",
  onscreen="Two columns: SCOPE and LIMITATIONS, as short bulleted terms. An orange box: 'Where we are today'.",
  do="The outline says: explain and elaborate the bullets orally. Do not read them. Use the words below.",
  say=[
   "**The scope.** TaraBasa AI is for Grade 1 to 3 learners in Philippine public elementary schools. It covers English reading assessment and intervention. The participants are teachers, learners and parents. As the initial phase, we planned a selected public school in Cebu City. The system uses AI-assisted speech scoring, adaptive learning, gamification and analytics. Learners and parents use an Android app, and teachers, parents and administrators use web dashboards.",
   "**The limitations.** We say these ourselves. It is implemented in Cebu City only. It does not measure the long-term effect on reading. It does not cover nationwide adoption or DepEd integration. It needs internet and a device. Speech scoring can be wrong, because of pronunciation, background noise or the microphone. The results help teachers and never replace their judgment. And activities are limited to the content in the system.",
   "**Where we are today.** The orange box is the honest part. Our scoring has been checked with computer voices only. A real child, a real microphone and a real classroom are the next step. This is also our first recommendation.",
  ],
  handoff="\"Aaron will now show how the system is designed, starting with its modules.\"",
  asked=[("Why is the Phil-IRI band used for Grade 1 and 2 if its manual is for Grades 3 to 6?", "One source says the 2018 manual is written for Grades 3 to 6. Using its bands for a single passage read by a younger child is our own extension, and the how-it-works page says so. The app's own moving-up and moving-down rule is separate from the bands.")]),

 dict(n=6, title="Functional Decomposition Diagram", speaker=AARON, mins="2:00", short="Aaron",
  onscreen="Ten modules in five groups: Who uses the system, Activities, Learner experience, Keeping people informed, Administration.",
  do="Point at each group as you name it. Do not list every function; two per module are on the slide.",
  say=[
   "This is our Functional Decomposition Diagram, from Chapter 3. TaraBasa AI is broken into **ten modules**, in five groups.",
   "**Who uses the system:** Account Management, Learner Management and Class Management. Teachers and parents register, confirm their email and log in with email or Google. A learner has no email: a learner logs in with a code and a four-digit PIN. A parent adds a learner and prints a learner card with a QR code. A teacher builds a class and adds learners by code.",
   "**Activities:** Reading Activity Management, where a teacher generates activities with AI, edits and approves them, and assigns them to a learner, a group or a class. And Open Repository Management, where activities are shared, browsed and rated, and a parent can unlock one. In the prototype, the payment is simulated: no real money moves.",
   "**Learner experience:** Learner Reading Assessment, with the first reading check, reading aloud, the word review and the reading path. And Gamified Learning: points, badges, streak, weekly goal, practice games, bookshelf and word bank.",
   "**Keeping people informed:** Dashboard Analytics, and Notification Management, which gives teachers alerts with a suggested activity and gives parents notifications.",
   "**Administration:** System Administration and Privacy. The Admin approves teachers, sees system health and the activity log, and can delete data.",
   "On the next slide, John Kenneth will show that every module is a working screen.",
  ],
  handoff="\"John Kenneth.\"",
  asked=[("The earlier proposal had six modules. Why ten now?", "The final paper decomposes the system into ten modules, in Figures 7.1 to 7.6. We split the early, larger modules once we built them: for example Open Repository and Notification Management became their own modules.")]),

 dict(n=7, title="Every module is a working screen", speaker=KEN, mins="1:30", short="John Kenneth",
  onscreen="Four real screenshots from the running system: Learner, Teacher, Parent, Admin.",
  do="Point to each screenshot. These were captured on demo accounts, so they show no real child.",
  say=[
   "This slide connects the diagram to the real system. These are screens from the running app, captured on demo accounts, so no real learner's data is shown.",
   "**Learner.** This is the reading screen, from the Learner Reading Assessment module. A child listens first, practices, then reads for real. The owl, Tara, guides a six-year-old. Headings are at least twenty-five pixels, the passage a child reads is twenty-three pixels, and every tap target is at least forty-eight pixels.",
   "**Teacher.** Dashboard Analytics. A teacher sees Phil-IRI reading levels, the words missed most this week, and can download a class report.",
   "**Parent.** The child's reading step, weekly goal and badges at a glance, in plain language. A parent sees the same four steps their child sees, not the school's words.",
   "**Admin.** System Administration: teachers waiting for approval and grade requests.",
   "Adult roles get a calmer look with a menu on the left. The learner look is warmer and bigger, because a child is a different user from a teacher.",
  ],
  handoff="\"Aaron will now show how the data is organized.\"",
  asked=[
   ("What is the design basis for six to eight year olds?", "Large type, one action at a time, spoken instructions together with a short line of text and a glowing next button, and taps instead of drags. A study of 90 children aged 2 to 8 found that among the 7 to 8 year olds 83 percent could tap, 40 percent swipe and 30 percent drag and drop. It is a small sample, so it is a reason for our choice, not proof."),
   ("Did you test the design with children?", "No. We reviewed every screen at several phone and laptop sizes, and teachers and our adviser gave feedback during development. Usability testing with children is part of the pilot."),
  ]),

 dict(n=8, title="Entity Relationship Diagram", speaker=AARON, mins="2:00", short="Aaron",
  onscreen="The database diagram in six colored groups, with four tiles: 28 tables, 45 foreign keys, 6 module groups, 58 migrations.",
  do="Walk the colors in the order below. The diagram was generated from the live database, not drawn by hand.",
  say=[
   "This is our Entity Relationship Diagram. We did not draw it by hand: **it was generated from the live database.** There are twenty-eight application tables and forty-five foreign keys, in six groups, and fifty-eight migrations that built them step by step.",
   "**Blue, accounts.** The users table is the hub. Teachers and parents are linked to a user, and every admin action is logged.",
   "**Green, learners and classes.** A learner belongs to a class, and to guardians through a link table. Promotions and PIN changes are recorded.",
   "**Orange, reading activities.** Activities, assignments to a learner, a group or a class, and AI generation requests.",
   "**Purple, Open Repository.** Listings, unlocks, ratings and payment records.",
   "**Teal, reading and gamification.** Reading sessions, the word bank, badges, game plays and scoring jobs.",
   "**Gray, monitoring and system.** Notifications, teacher alert actions, service failures and the data erasure log.",
   "The database is TiDB Cloud, which is MySQL compatible, in Singapore. Local development and tests use SQLite. On this slide we show twenty-one of the twenty-eight tables; photos, PIN changes, consents, scoring jobs, failures and the erasure log are left out to keep it readable. The full data dictionary is in the paper.",
  ],
  handoff="\"Next, the technology we built it with.\"",
  asked=[
   ("Why is the learner not a user?", "Children have no email. A learner logs in with a code and a PIN, with limited tries, and a guardian controls the profile. Keeping learners separate from users also means a child can never be given a teacher's or an admin's access by mistake."),
   ("Why 37 tables in the database but 28 on the slide?", "The database has 37 tables in total. The other nine are framework tables: sessions, cache, jobs, migrations and similar. The 28 are the ones we designed."),
  ]),

 dict(n=9, title="Technology stack", speaker=AARON, mins="2:00", short="Aaron",
  onscreen="Four columns: Mobile app, Web dashboards, Server, AI services. A strip: Future work. An orange box: 'Why this stack'.",
  do="Say each column in one or two sentences. Do not read the versions aloud unless asked.",
  say=[
   "Our technology stack, updated to what is actually deployed.",
   "**The mobile app** is React Native with Expo, in TypeScript. It uses the microphone through expo-audio, the camera to scan a learner card, and expo-speech to read words aloud. It is packaged as an Android APK. We use Expo SDK fifty-seven and React Native zero point eighty-six.",
   "**The web dashboards** are Laravel Blade pages with plain HTML, custom CSS for each role, and JavaScript. Teacher, Parent and Admin screens.",
   "**The server** is PHP eight point four and Laravel thirteen, in a Docker container on Render in Singapore. Laravel Sanctum gives the phone its login tokens. The database is TiDB Cloud. Emails are sent through the Gmail API, and Google sign-in is optional.",
   "**The AI services.** Vosk, an open-source speech recognition engine, behind a Reading API. Google Gemini, behind an Activity Generation API, which drafts activities. A rule-based adaptive recommender. And our written rules for scoring and levels.",
   "**Why this stack:** it is built for low-resource schools. An open-source speech model means no model had to be trained. Free-tier hosting keeps the cost low, with one known cost: a wake-up delay of about a minute after quiet periods. Recommendation five is always-on hosting. The strip at the bottom is our future work: iOS, Bisaya voice recognition, a speech model trained on children's voices, a real payment gateway and a low-connectivity mode.",
  ],
  handoff="\"And now, how all of these parts are connected.\"",
  asked=[
   ("Why Vosk and not Google Speech-to-Text, which the earlier proposal named?", "Vosk is open source, has no per-request fee, does not send children's voices to a large provider, and the team can run it. The earlier proposal named Google Speech-to-Text; the final system does not use it."),
   ("The paper says Kotlin, XAMPP, Bootstrap, Vite and Tailwind. Why does the slide differ?", "The slide shows what is deployed: React Native with Expo, Laravel, TiDB, Vosk and plain CSS. The paper's Table 34.1 already says the same. A few older sentences in the software paragraph were not updated, and we are correcting them."),
  ]),

 dict(n=10, title="Network design", speaker=AARON, mins="1:30", short="Aaron",
  onscreen="The network model, left to right: users, ISP router, internet, application server, database and outside services. A strip: 'One reading' in four steps.",
  do="Trace the picture with your finger from left to right. Finish with the one-reading strip.",
  say=[
   "This is our network model, Figure fifty-five in Chapter 3. Read it from left to right.",
   "**Users.** The learner and the parent use the Android app, and the parent can also use the web. The teacher and the admin use the web. They reach us through their ISP router, which is school Wi-Fi or mobile data, and then the internet over HTTPS.",
   "**The application server** is Laravel thirteen on Render, in a Docker container in Singapore. The phone app and the web both talk to the same REST API; Sanctum issues the phone's tokens.",
   "**Behind it,** the server talks to the TiDB Cloud database and to five outside services: the Reading API with Vosk, the Activity Generation API with Gemini, the adaptive recommender, the Gmail API and Google sign-in. **Only the server talks to these services.** The phone never calls them directly, and none of them receives a child's name.",
   "Follow **one reading**: one, a recording is sent over HTTPS. Two, the Reading API turns it into words. Three, our rules score it and the result is saved. Four, dashboards and alerts update. **The recording is deleted as soon as it is scored.**",
   "An honest note: the web server and the three AI services are on free hosting, so the first request after a quiet period can take about a minute.",
  ],
  handoff="\"Now Tyron will show what we built so far, and what comes next.\"",
  asked=[("What does each outside service receive?", "Render and TiDB hold the app's data. The Gmail API gets the email text. Gemini gets only a grade, a skill, a topic and a short class summary, never a name. The speech service gets the recording and deletes it after scoring. There are no analytics, no ads and no trackers.")]),

 dict(n=11, title="Project roadmap", speaker=TYRON, mins="1:30", short="Tyron",
  onscreen="Left: what is built so far (commits per month, six counters). Right: the paper's four quarters for 2027.",
  do="Do not claim commits per person: all commits are under one GitHub account.",
  say=[
   "Our roadmap has two halves: what is built, and what comes next.",
   "**Built so far.** Between the twenty-fifth of August and the twenty-first of November 2026 there are **252 commits** on GitHub. The system has 223 routes, 34 classes in the services layer, 132 web views, 67 app screen files, 28 database tables, and **912 automated tests that pass.** The commits are under one team account, so we do not claim a count for each person.",
   "**Next, the paper's roadmap for 2027.** Quarter one, January: reading assessment reliability and classroom validation. We expand accepted words and letter names, refine speech scoring, tune the move-up, move-down and alert settings, and begin early testing with teachers, parents and volunteer learners. Quarter two, March: more learning content, and Bisaya and Filipino for instructions and parent messages, with always-on hosting. Quarter three, June: awareness for teachers, school heads and the DepEd division office. Quarter four, September: rollout in partner schools with teacher training, and a comparison of Phil-IRI results before and after.",
   "The order matters: **validate first, then grow.** We do not scale a system until a classroom has shown that it works.",
  ],
  handoff="\"John Benedict will now show what the results are.\"",
  asked=[
   ("How did you organize the work and the schedule?", "In small working slices, from the database to the screen, each tested before the next, with feedback from teachers, the instructor and our adviser between rounds. The build ran from late August to November 2026. The paper has the sprint plan and the Gantt chart for it."),
   ("Who did how much of the work?", "We do not split by commit count because everything is committed under one account. Each of us owns an area, and each of us can explain every area. [FILL IN the real split, one sentence each. Suggested wording: Aaron, the server and the services; John Kenneth, the screens and the design; John Benedict, the survey, the literature and the manuscript; Tyron, scope, schedule and testing. Change it to what is true for your team.]"),
  ]),

 dict(n=12, title="Objectives, and what shows each one is met", speaker=JB, mins="1:00", short="John Benedict",
  onscreen="A table: each objective, what the system does, and the evidence and status.",
  do="Read the right-hand column slowly. It is the honest column.",
  say=[
   "This slide answers the question: how do you know the objectives are met? We show what the system does, and the evidence, and what is still to be shown.",
   "**Objective one,** gather data: surveys of twenty-four teachers and eighty-six parents, confirmed in Appendices D and E.",
   "**Objective two,** speech recognition: Vosk behind a Reading API. Every word is marked correct, mispronounced, unsure or skipped, and we compute accuracy and words per minute. The recording is deleted after scoring. It was checked end to end with recorded readings. **Real children's voices are still to be measured.**",
   "**Objective three,** adaptive and gamified: the first reading check, the four-step path where it takes three strong readings to move up, teacher-approved AI activities, points, streak, goal, badges, games and a bookshelf. It is working in the app. **The effect on reading is to be shown by the pilot.**",
   "**Objective four,** monitoring and reporting: teacher analytics, alerts and the class report, the parent dashboard, and the admin pages. It works on the web and on the phone, and 912 automated tests pass.",
   "We do not say the system improves reading. We say it is built to support it, and the pilot will show whether it does.",
  ],
  handoff="\"Our conclusions.\"",
  asked=[("So does it work?", "It works as a functional platform that does what the four objectives describe, and it works as designed in our tests. Whether it improves children's reading is a different question, and only a consented classroom pilot can answer it.")]),

 dict(n=13, title="Conclusions", speaker=JB, mins="1:00", short="John Benedict",
  onscreen="Four points and an orange box: 'Still to be shown'.",
  do="Read the orange box last. It is the paper's own final sentence.",
  say=[
   "Four conclusions.",
   "**One: the need is real.** Twenty-four teachers and eighty-six parents confirmed that individual reading support is hard in large classes with different reading levels.",
   "**Two: a working platform.** English oral reading assessment, adaptive practice, gamified learning and progress monitoring, in one system for public elementary schools.",
   "**Three: AI, described honestly.** Vosk behind a Reading API, plus written rules. A rule-based recommender moves learners along a four-step path. AI-drafted activities are always approved by a teacher. **No model was trained.**",
   "**Four: objectives met as a build.** The gamified features, the teacher and parent monitoring and the admin tools were implemented to meet the four objectives.",
   "And the orange box. **Still to be shown:** the effect of the system on learners' reading, the accuracy of its scoring with real children's voices, and its acceptance by users in a classroom. A consented pilot in 2027 will test these.",
  ],
  handoff="\"And what we recommend.\"",
  asked=[]),

 dict(n=14, title="Recommendations", speaker=JB, mins="1:00", short="John Benedict",
  onscreen="Nine recommendations in three groups: Validate (1 to 4), Strengthen (5 to 7), Grow (8 and 9).",
  do="Keep the paper's numbers so the panel can follow in the manuscript.",
  say=[
   "Our nine recommendations are in three groups.",
   "**Validate.** One: run a consented classroom pilot with Grade 1 to 3 learners, teachers and parents, and compare Phil-IRI results before and after. Two: measure speech scoring with real children's voices on real phones, and update the accepted words and letter names. Three: have teachers review the first-check texts, the game sentences and the movement and alert settings. Four: complete the unit, alpha and acceptance testing records with real results from real users.",
   "**Strengthen.** Five: move from free hosting to always-on hosting, to remove the wake-up delay. Six: add Bisaya and Filipino support for instructions and parent messages, while keeping English reading as the goal. Seven: complete the privacy and legal work: contact details, National Privacy Commission registration if required, a Privacy Impact Assessment, a breach plan and a lawyer's review of the terms.",
   "**Grow.** Eight: add real payments to the Open Repository only after legal review. Nine: explore a low-connectivity mode, and links with school reading programs and DepEd reporting, once the pilot has shown that the system works.",
   "These map directly to our limitations, and to the 2027 roadmap.",
  ],
  handoff="\"The key references behind all of this.\"",
  asked=[("Which recommendation matters most?", "Number one, the consented pilot, and number two, measuring the speech scoring with real children's voices. Every number in our rules was set without real children's recordings. The pilot is how we find out which of them are wrong.")]),

 dict(n=15, title="Key references", speaker=JB, mins="0:30", short="John Benedict",
  onscreen="Nine key references, each with its DOI or source.",
  do="Do not read them out. Name the groups and move on.",
  say=[
   "These are the key references. Each one supports a part of the system. On technology-based and adaptive learning for reading: Alqahtani, Alrawashdeh and colleagues, and Wang and colleagues, all 2024. On gamification: Sailer and Homner. On oral reading with an automated tutor that listens: Mostow and colleagues. On speech recognition: Kheddar and colleagues. On phonics: Ehri. And the primary sources: DepEd Order 14 for the Phil-IRI, the MATATAG curriculum, and the Vosk toolkit.",
   "The full reference list is in the manuscript. **We looked up every DOI on this slide, and each returns the right article.**",
  ],
  handoff="\"Now, the live demonstration. Tyron.\"",
  asked=[("Two DOIs in your paper do not work.", "Correct. We found that when we checked every DOI. The paper's Alrawashdeh DOI should end edurev.2023.100587, and the Wang DOI should be 10.1177/07356331241240459. The slide uses the correct ones, and we are correcting the paper. Two other references in the paper, Abocejo 2022 and Chu 2021, have problems, so we do not rely on them here.")]),

 dict(n=16, title="System demo (40 minutes)", speaker="Tyron introduces; Aaron and John Kenneth drive", mins="40:00", short="Everyone",
  onscreen="The run-sheet: six steps with their minutes (Admin 3, Teacher 8, Parent 7, Learner 12, Teacher and Parent 6, Privacy and health 4).",
  do="The full demo script is Part 3 of this guide. Invite the panel to interrupt at any step.",
  say=[
   "Now the live demonstration. It is forty minutes, in six steps: the Admin, the Teacher, the Parent, the Learner, then the Teacher and Parent again to see what updated, and finally privacy and system health. We use demo accounts only, never a real child's data.",
   "The panel is welcome to stop us at any step for questions or cross-examination.",
  ],
  handoff="Open the demo script (Part 3).",
  asked=[]),

 dict(n=17, title="Thank you and questions", speaker=TYRON, mins="0:30", short="Tyron",
  onscreen="Thank you; two QR codes (the live site, the GitHub repository).",
  do="After the demo, return to this slide. Face the panel.",
  say=[
   "Thank you, panel, and thank you to our adviser, to the teachers and parents who answered our survey, and to everyone who supported us.",
   "The first QR code opens the live site, and the second opens the public code on GitHub. The live site is on free hosting, so the first load can take about a minute.",
   "We are ready for your questions and cross-examination.",
  ],
  handoff="Each member is ready for two questions: one about their role and one about the system, study or project (see Part 5).",
  asked=[]),

 dict(n=18, title="Backup: how one reading becomes a level", speaker="Whoever is asked (Aaron or Tyron)", mins="1:30", short="Backup",
  onscreen="A sequence diagram in six steps between Child, Server, Reading API, Database, Teacher and Parent.",
  do="Use only if the panel asks how a reading is scored.",
  say=[
   "Here is one reading from start to finish. One: the child's app records the reading and sends it to our server. Two: the server passes the recording to the Reading API, which runs Vosk. Three: the Reading API returns the words it heard. Four: our server scores each word with written rules, saves the session, and **deletes the recording**. Five: the child sees a friendly result and a word review. Six: the dashboards, the alerts and the next activity update.",
   "The scoring rules are fair on purpose. Words that sound the same count as the same word, like sun and son. Numbers count either way, two or the word two. When a child names letters, the recognizer often writes a letter name as another word, so we repair those, but only to add credit, never to lower a score. And a word the recognizer heard only faintly is marked Not sure and left out, but never more than a quarter of the words.",
   "The website scores in the background so the page is never held up; the page checks every two seconds whether the result is ready. **The numbers are our own settings,** kept in configuration files, and were set without recordings of real children. That is why Recommendations two and three exist.",
  ],
  handoff="Return to the question.",
  asked=[]),

 dict(n=19, title="Backup: the rules that move a learner", speaker="Whoever is asked (Aaron or Tyron)", mins="1:30", short="Backup",
  onscreen="Five rules: one reading never moves a level; moving down; the first check; teachers approve AI; a teacher can only assign what a child can read.",
  do="Use only for questions about the adaptive 'AI' and fairness. The public page tarabasa-ai.onrender.com/how-it-works lists every number.",
  say=[
   "These are rules written in the code, pinned by automated tests, and listed on our public How it works page.",
   "**One reading never moves a level.** To move up, a child needs three strong readings of ninety percent or more, from at least two different activities, among the last five since the last move, and only one step at a time. To move down, a child needs two weak readings in a row, under seventy percent, on texts that were not too long for the child.",
   "**The first reading check starts where the parent says the child is,** not at the grade. It shows at most three texts. A score of ninety or more moves it up, under seventy moves it down, and seventy to eighty-nine stops. Letters are the exception: they move up at eighty percent, which is five letters of six. The child is never shown a score.",
   "**Teachers approve what the AI writes.** A draft waits in To review until a teacher places it in a level or rejects it. And **a teacher can only assign what a child can read:** a text too long for a learner cannot be assigned.",
   "Everything here is a number someone can change in one settings file. The thresholds are our own choices and have not been tested on real children's recordings. The pilot is how they will be tuned.",
  ],
  handoff="Return to the question.",
  asked=[]),
]

# Per-slide quick facts used in the PowerPoint notes header
ORDER = [s["n"] for s in SLIDES]
