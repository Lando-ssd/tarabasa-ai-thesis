# Added 25 November 2026, after the schedule was checked again:
#   (1) the second question round, "1 question each related to the system / study / project";
#   (2) the whole session as listed in the schedule, and the 5-minute "Initialization / initial preparations" block.
# No names are assigned here on purpose: any member can be asked any of these, and any member can do any set-up task.

SECOND_INTRO = [
 "The schedule gives every member **one question about their role** (the role pages above) and **one question about the system, the study or the project**. This second one is not tied to a role: **any member can be asked any of these**, so everyone prepares all of them.",
 "Answer in your own words, in about 30 seconds. Use the shape: **what we did, what the source or data shows, what we did not measure, what we would do next.**",
 "If a question is deep in a teammate's area, give the short answer first, then say: \"my teammate who built or wrote that part can add.\" That is allowed, and the panel prefers it to a guess. Do not hand a question over before you have said something true about it.",
 "Practise in pairs: one asks a random question from this list, the other answers aloud with a timer. Swap. Keep going until nobody reads the answers.",
]

SECOND_QS = [
 ("About the system", [
  ("In your own words, what does your system do, and who uses it?",
   "It is a reading practice and monitoring system for Grade 1 to 3 learners. A child reads aloud on the phone or the website, a speech recognition service writes down the words, and our written rules score the reading and decide what comes next. Teachers see Phil-IRI levels and alerts with the evidence, parents see the child's step in plain words, and an administrator approves teachers and watches system health. Four kinds of users, one set of rules for the website and the phone."),
  ("How does the system decide a child's reading level?",
   "First, a reading check of at most three texts places the child. It starts where the parent says the child is, not at the grade. After that, three strong readings (90 percent or more) from at least two different activities move the child up one step, and two weak readings (under 70 percent) in a row move the child down one. One reading never moves a level. Teachers see DepEd's Phil-IRI words; children and parents see four friendly steps. The numbers are our own settings."),
  ("Where is the AI in your system?",
   "Three places. Vosk, an open-source speech recognition engine, turns the child's voice into words. Google Gemini drafts activities, and a teacher approves every draft. A recommender made of formulas picks the next skill. Everything else adaptive is written rules. We did not train a model, because there is no collection of children's recordings, and we say so first."),
  ("What happens to a child's recording?",
   "It is sent to our server, passed to the speech service, scored by our rules, and deleted as soon as the scoring ends. It waits as a temporary file for a few seconds and is never kept. What stays is the result: accuracy, words per minute, and which words were missed."),
  ("What if the internet or a service fails while a child is reading?",
   "The app checks the connection first. If it cannot reach us, it shows Send Again and keeps the recording. If the reading service is asleep because of free hosting, the screen says it is waking up and waits up to a minute. If a service is gone, sign-in and everything else still work, and the reading fails with a friendly message. The administrator sees each service's state on the System health page."),
  ("How do you protect children's data?",
   "A parent creates the child's profile and consents, because a child cannot. The policy version is stored with the consent. Recordings are deleted after scoring. There are no ads and no outside trackers. The AI writer never receives a name. A parent can delete a child or the whole account. Passwords are stored only as hashes and sign-in tries are limited. Open items, said by us first: registration with the National Privacy Commission, a Privacy Impact Assessment and a lawyer's reading of the terms."),
  ("Why can a teacher not give a child any activity?",
   "Because an activity that is too long for a child's step cannot be read, and a child who fails on it learns nothing. Each step has a comfortable length and a stretch length in words. A text longer than the stretch is refused, with the reason. A whole class is refused when at least 20 percent of the children whose level is known could not read it. The teacher decides, but the system will not hand a child something unreadable."),
 ]),
 ("About the study", [
  ("What was the objective of your study, and was it met?",
   "The general objective was to develop an AI-powered adaptive reading intervention system with gamified learning for Grade 1 to 3 learners. There are four specific objectives: gather data from teachers and parents, determine a speech recognition mechanism, integrate an adaptive and gamified intervention, and implement monitoring and reporting. Each has a working feature in the system. What is not yet shown is the effect on children's reading, the scoring accuracy with children's voices, and acceptance in a real classroom. A consented pilot will test those."),
  ("What did your survey show, and how did it change the system?",
   "We surveyed 24 Grade 1 to 3 teachers and 86 parents. 70.8 percent of teachers have 31 to 40 learners in one class, 54.2 percent say most learners read simple words but struggle with sentences, and 62.5 percent say most read slowly but try to sound words out. 75.6 percent of parents speak Bisaya or Cebuano at home. That is why we built practice that fits each child's level, a clear list of who needs help for the teacher, plain-language progress for parents, and why Bisaya and Filipino support is a recommendation."),
  ("What are the limitations of your study?",
   "It is implemented for Cebu City only; it does not measure long-term effects on reading; it does not cover nationwide adoption; it needs internet and a device; speech scoring can be wrong because of pronunciation, noise or the microphone; results help teachers and never replace their judgment; and activities are limited to the content in the system. Honestly, the scoring has been checked with computer voices only, not real children yet."),
  ("What is new in your work, and what is its contribution?",
   "We did not invent an algorithm. We combined existing parts carefully for a Philippine classroom: speech recognition for scoring, written rules for placing and moving a child, teacher-approved AI-drafted activities, and role-based screens with privacy built in, all coded to the MATATAG curriculum and the Phil-IRI words teachers already use. Its value is that every adaptive decision is a readable rule with a stated basis, so it can be tuned with real data."),
 ]),
 ("About the project", [
  ("How did you test the system?",
   "912 automated tests passed, and 915 with the latest fix. Real readings were run through the live speech service with computer voices, every screen was reviewed at phone and laptop sizes, load tests were run with a slow fake service, and we used the system ourselves. Before this defense we ran the full path again: the first reading check, three strong readings moving a child up, two weak ones moving her down, activity generation and the games. We have not tested with real children."),
  ("What was the hardest problem you faced, and how did you solve it?",
   "The website showed 502 errors because scoring a recording held up the only web worker, so the host's health check failed. We found the cause, a missing server flag, fixed it, moved scoring to a background worker, and measured that the slowest health answer fell from 24 seconds to about 1. Another hard one: a single good reading used to jump a child a whole level, so we changed it to repeated evidence."),
  ("How will you prove the system helps children read better?",
   "We cannot claim that yet. A consented pilot over several weeks would compare children's Phil-IRI results before and after, and check our own settings (move-up and move-down, the Not sure line, the alert lines) against real readings with children's voices. That is the first and second recommendation in the paper."),
  ("What would you do next, and what is still unfinished?",
   "Run the pilot, measure the scoring with children's voices on real phones, have teachers review the first-check texts and game sentences, move to always-on hosting, add Bisaya and Filipino support, and complete the privacy and legal items. Still unfinished, and said by us first: no real-child testing yet, the phone app has not run on a real phone, and payments are only a demo with pretend money."),
  ("What did you personally do, and what would you change if you started again?",
   "**FILL IN** what you personally did, in one or two sentences, and name one thing you can explain from start to finish. If we started again we would collect consented sample recordings earlier, put a password on the reading service from the start, and ask a school head about funding in the first month."),
 ]),
]

SESSION = [
 ["I", "Initialization / initial preparations", "5 min", "Everyone sets up (plan in section 7.1)"],
 ["II", "Defense proper: concept / explainer video and presentation", "25 min", "Slides 1 to 15 take about 23 minutes"],
 ["", "1 question each related to the corresponding group role", "5 min", "Part 5, the role pages"],
 ["", "1 question each related to the system, study or project", "5 min", "Part 5, section 5.5"],
 ["III", "System demo (involves the panel's clarification and cross-examination)", "40 min", "Part 3"],
 ["IV", "Panel deliberation (grades consolidation, verdict deliberation)", "5 min", "Wait quietly"],
 ["V", "Verdict presentation (the chairman announces the group average grade, not the individual grades)", "5 min", "Listen and thank the panel"],
 ["VI", "Finalization / final adjustment", "5 min", "Write down every requested change"],
 ["", "**Total in the schedule**", "**90 min (1.5 hours)**", "The question rows are listed under the defense proper"],
]

INIT_PLAN = [
 ("Before the 5 minutes begin", [
  "The keep-awake workflow has been running for 15 minutes or more.",
  "Everyone has water and has this guide on paper or on the phone.",
 ]),
 ("Minute 1: power and connection", [
  "Laptop plugged in; notifications off; volume up; browser zoom 100 percent.",
  "Connect the laptop to the projector or screen and check the picture is the right size. Check the internet. Switch the phone to mobile data as the backup connection.",
 ]),
 ("Minute 2: the deck and the video", [
  "Open the deck in slideshow mode on slide 1. Play the explainer video once with the sound, then go back to slide 2 so it is ready.",
  "Test the clicker from where the presenter will stand. Keep the deck PDF open in another window in case PowerPoint fails.",
 ]),
 ("Minute 3: the live system", [
  "Open the live site and the three services in tabs. A slow page means it was asleep, which is normal.",
  "Sign in once on each demo account (Admin, Teacher, Parent) in separate windows, and the learner on the second device. Open the local copy in one more tab.",
  "Do one short test reading so the microphone is allowed and the reading service is awake. Check that the demo checkout appears on the Parent's Activities page.",
 ]),
 ("Minute 4: the people", [
  "Agree, out loud, who speaks when, who holds the clicker, who drives the demo, and who writes down the panel's questions.",
  "Agree who answers first for each kind of question, and the signal for \"please add something\".",
  "Put the notes paper where the note-taker can reach it. Put every phone on silent.",
 ]),
 ("Minute 5: ready", [
  "Stand in position. One slow breath each. First slide on screen.",
  "Do not start until the panel says to begin. When it starts: say good day, thank the panel and the adviser, then go to slide 1.",
 ]),
]
