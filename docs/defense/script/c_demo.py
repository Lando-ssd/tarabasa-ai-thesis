# The 40-minute live demo (slide 16). Screen names follow the running system (see docs/defense/01 and the app).

SETUP = [
 "**Wake everything 15 minutes before.** GitHub, Actions, **Keep TaraBasa awake**, Run workflow (1 to 5 hours). The website and the three helper services sleep after about 15 quiet minutes and need about a minute to wake.",
 "**Check the demo payment switch.** In Render, Environment, `PAYMENTS_MODE` must be `demo`. Without it, step 3 has no checkout page. (This was typed but never confirmed as saved: check it before the defense.) The two `PAYMONGO_*` rows must NOT be saved.",
 "**Demo accounts ready on the live site** (made beforehand, never during the defense, and never a real child): an Admin; two Teachers (one already approved for steps 2 and 5, and one still Pending for the Admin step); a Parent; and one child, for example Maria Demo, with her learner card printed or on a second screen.",
 "**One reading done today** on the account you will demo, so the reading service is awake and the microphone permission is already given.",
 "**Two devices.** The laptop for the website; a second device signed in as the child on mobile data in case the room Wi-Fi fails. If you use the Android phone for the learner step, rehearse it first: the phone app (version 0.6.3) has not yet been tried on a real phone. The website learner screen is the fallback.",
 "**Backups open in other tabs or files:** the local copy of the site (`php artisan serve`), a short recorded video of one reading and the dashboards, and the PDF of the deck.",
 "**Laptop:** plugged in, notifications off, volume up, browser zoom 100 percent, microphone allowed for the site.",
]

ROLES = "Aaron drives Admin, Teacher and Parent. John Kenneth drives the Learner. Tyron narrates the story and watches the clock. John Benedict listens for panel questions and writes them down. (Suggested; change to suit your team.)"

STEPS = [
 dict(n=1, who="Admin", mins=3, driver="Aaron", title="The Admin approves a new teacher",
  goal="Show that a person, not the system, decides who may see real children.",
  doit=[
   "Sign in as the Admin. The overview shows teachers waiting for approval and grade requests.",
   "Open **Approvals**. Open the Pending teacher's card: school, employee ID, confirmed email.",
   "Press **Approve**.",
  ],
  say=[
   "A teacher can sign in after confirming her email, and can ask for activities with two free credits, but nothing that touches real students until a person has approved her. The Admin checks with the school, then approves. Every admin action is written in the activity log with a reason.",
  ],
  hooks="Every change a teacher makes to a class checks her approval on the server, not only on the screen. Admin has no self-registration: there is one Admin account, set from a secret.",
  asked=[("Why must the Admin approve teachers?", "A teacher can reach children's records. The Admin checks the school and the employee ID first. Until then she can sign in but cannot touch real students.")],
  broke="If the page is slow, say: 'The free hosting is waking up.' Show the Activity log in the meantime."),

 dict(n=2, who="Teacher", mins=8, driver="Aaron", title="A class, a learner, an AI-written activity",
  goal="Show one grade per class, adding a learner safely, and 'the AI drafts, the teacher decides'.",
  doit=[
   "Sign in as the approved Teacher. Open **Classes** and make a class: **one grade only**.",
   "Open **Add a learner**. Type the **last five characters** of Maria's learner code. Show that the **Add** button stays off with a reason until the code is for a child of this grade and not already in a class. Then press Add. (Or scan the QR on the printed card.)",
   "Open **Activities**, then **Generate**. Choose the grade, a skill, the type of reading, and how many Easy, Medium and Hard. Choose **For a class**: show **what the AI will use** (the step most of the class is on, the kind of mistake, the words missed, the topics parents like). Press **Write**.",
   "While it writes (about two minutes, longer if the service was asleep) tell the story below. Then open **To review**: place one draft in Easy, Medium or Hard (or reject one), and edit a word.",
   "**Assign** the approved activity to Maria (to a learner, a reading group or the whole class).",
  ],
  say=[
   "A class holds one grade only, because the curriculum is written grade by grade. To add a child the teacher types the last five characters of the code. Notice the Add button stays off, with the reason, until the child can really be added. The parents are told.",
   "To write activities the AI is given a grade, a skill, a topic and a short note about the class, never a name. The teacher reads exactly what the AI will use before pressing Write. **The AI only drafts. A teacher approves every activity and decides its level.** Easy, Medium and Hard are this app's levels, not DepEd scores, and the screen says so.",
   "One more rule: an activity that is too long for a child cannot be assigned. If most of a class could not read it, it is refused for the class, so it goes to the group that can.",
  ],
  hooks="Point at the sentence under the levels ('our levels, not DepEd's'). Point at 'Written for the class because...' on a draft: the evidence stays with the activity.",
  asked=[
   ("What does the AI know about the children?", "Nothing about any one child. For a class it receives a short note built from facts at least two children share. The teacher reads the note before pressing Write."),
   ("Could the AI write something wrong or unsafe?", "Yes. That is why a teacher approves every draft. Only approved activities can be given to children. A teacher is told not to type a child's name in the notes."),
  ],
  broke="If generation is slow or fails: 'The activity writer is a separate free service and may be asleep. A request that never finishes closes as failed after 20 minutes and charges no credit.' Open a pre-approved activity and carry on. Never wait in silence."),

 dict(n=3, who="Parent", mins=7, driver="Aaron", title="The parent adds a child and unlocks an activity at the demo checkout",
  goal="Show consent, the parent's description deciding where the child starts, the QR card, and a payment that cannot move real money.",
  doit=[
   "Sign in as the Parent. Open **Children**, then **Add a child** (use a second demo child so Maria's data stays clean): name, grade, a character, **how the child reads now**, the three yes or no questions, a four-digit PIN, and the **consent tick box**.",
   "Show the **learner card**: the code and a QR. Say the QR holds only the code, never the PIN.",
   "Open **Activities** (the shared library). Choose a paid activity, press **Unlock for ... (demo)**. The **demo checkout** opens: choose **Card, GCash or Maya**, then the demo picker: **Approved**. Show the result and the payment history. (Optionally run **Declined** or **Cancelled** to show nothing unlocks.)",
  ],
  say=[
   "A child cannot give consent, so the parent creates the profile and agrees to the privacy terms. The parent's description of how the child reads decides where the first reading check starts: a Grade 3 child who cannot read yet will not be given a story. The learner card holds only the code, so a lost card is not a lost account.",
   "For the Open Repository we built our own **demo checkout**. It is TaraBasa's own page with pretend money: there is no box to type a card number, nothing is sent to any payment company, and a demo-only picker lets us choose how it ends. The unlock happens in one place only, after a successful payment, and a page the parent returns to can never unlock anything. **We say demo checkout with pretend money, never that payments work.** Real payments are Recommendation eight, only after legal review.",
  ],
  hooks="The Activities page says 'Demo mode' and the button reads 'Unlock for ... (demo)'. Show the payment history. Declined and Cancelled leave the activity locked.",
  asked=[
   ("Is the payment real?", "No. It is a demo checkout with pretend money. It proves TaraBasa's own screens and rules, not that a payment company or a bank would accept anything."),
   ("What if the parent pays twice or closes the browser?", "It is still one unlock: the database allows one per child and activity. A checkout that nobody finishes is closed by us after thirty minutes, and nothing is unlocked."),
  ],
  broke="If the demo checkout does not appear, the switch is not on: say 'In this build the payment step is simulated' and show the older simulated unlock. Fix PAYMENTS_MODE after the defense."),

 dict(n=4, who="Learner", mins=12, driver="John Kenneth", title="The learner reads",
  goal="The heart of the system: a real reading, scored, with no score shown to the child.",
  doit=[
   "On the child's device: **Scan my card** (or type the code), then four PIN numbers. Tara says hello.",
   "**First reading check** (if not done yet): the one-word warm-up ('say sun'), then at most three texts. A beginner is given six letters, not a story. No score is shown, only a friendly step.",
   "**Home:** show the four-step path ('you are here'), the progress ring, the weekly goal, tap the flame and the star to open their cards.",
   "Open the **Read** tab. The recommended story is first, marked **Picked just for you**. Open a story: **Listen** (Tara reads it), **Your turn** (two free tries, they count for nothing), **Read for real**. Press the microphone, read, press stop. Show the 'checking' screen, then the result: a happy star, the passage with the words to practise marked, points.",
   "Open **Games** and play one round of **Sound Hunt** (no microphone) or **Balloon Pop** (read one word). Show that games never change points, streak or level.",
   "Open **Badges** and the **Bookshelf**.",
  ],
  say=[
   "We never show a child a score on the first check. We show a friendly step, because the check is not a test to pass, it is a place to start.",
   "For a real reading Tara reads the story first, the child practises twice with no marks, then reads for real. About ten seconds later she has a friendly result. **The recording is already deleted.** The star is always happy: we do not tell a six-year-old that she failed. We tell the teacher, who can do something about it.",
   "Games are free play. They never change a child's points, streak or level, and what a child says or taps in a game is not kept.",
  ],
  hooks="Say: 'Watch what happens when a Grade 3 child who cannot read yet logs in. She does not get a story. She gets six letters.' If you read a few words wrong on purpose, the teacher alerts in step 5 will show it.",
  asked=[
   ("Does it work with a real child's voice?", "We have not tested with real children yet. The scoring was built and checked with computer voices through the live speech service. A child's voice will make the recognizer write other words, so our scoring is forgiving by design, and measuring it with children is Recommendation two."),
   ("What if the internet drops mid-reading?", "The app checks first. If it cannot reach TaraBasa it shows Send Again, which keeps the recording. A quick failure is retried twice. A drop in the middle of an upload is the one case we cannot recover."),
  ],
  broke="'Didn't quite catch that' three times means a silent child or a blocked microphone: check the browser's or phone's microphone permission. If the reading service is waking up, the screen says so on purpose: wait up to a minute, or switch to the recorded backup video and say so."),

 dict(n=5, who="Teacher and Parent", mins=6, driver="Aaron", title="The dashboards have updated",
  goal="Show that the same reading now appears, in the right words, for the teacher and the parent.",
  doit=[
   "Back on the **Teacher**: Home shows how many learners need support. Open **Alerts**: the evidence ('latest reading under 70 percent'), one suggested activity from the teacher's own approved list, and a button. Open **Analytics**: Phil-IRI levels, the words missed most, and download the **class report**.",
   "On the **Parent**: **Progress** (days in a row, points, the reading step in plain words, the chart), **Practise at home** (one thing to try, the words to practise), and the notifications.",
  ],
  say=[
   "The teacher sees the school's own words: Independent, Instructional, Frustration. Every alert shows its evidence and one suggestion, and **it never gives anything to a child by itself**: the teacher presses the button or marks it handled.",
   "The parent sees the same four steps their child sees, with a plain sentence about what the step means and one thing to practise tonight. A parent never sees the school's words, and never the system's internal level names.",
  ],
  hooks="Point out that the alert states its rule: needs support is a latest reading under 70 percent in the last 14 days; ready to move up is three readings of 90 percent or more; gone quiet is seven days.",
  asked=[("How does the system decide a child needs support?", "By rules, every time the page opens, over the readings in the database. Needs support is a latest reading under seventy percent and less than fourteen days old, stronger if the last three readings were all under. It shows the evidence, and a person acts.")],
  broke="If an alert has not appeared yet, open Analytics instead, or an older demo child that already has readings. Do not wait."),

 dict(n=6, who="Privacy and health", mins=4, driver="Aaron", title="Consent, deleting a child, and system health",
  goal="Show that privacy is a feature, not a promise.",
  doit=[
   "Show the **consent boxes** on the parent's add-a-child form and open the Privacy Policy page (no ads, no trackers, exactly two cookies).",
   "As the Parent, open **Children** and delete the second demo child: show the warning that everything about the child is deleted, and confirm.",
   "As the Admin, open **System health**, press **Check services now**: the reading service, the activity writer, the recommender and email sending show Working, Asleep or Needs you.",
  ],
  say=[
   "The parent consents, and the policy version is stored with the consent. A recording is deleted as soon as it is scored. There are no ads and no outside trackers, and our fonts are hosted by us. A parent can delete a child, or the whole account, and everything about the child goes. The Admin can do it for someone who cannot sign in.",
   "System health shows whether each outside service is awake. Asleep is normal on free hosting; Needs you is a real problem, with the reason under it.",
  ],
  hooks="Say the open items yourself: registration with the National Privacy Commission, a Privacy Impact Assessment, a breach plan and a lawyer's review are on our list (Recommendation seven).",
  asked=[("Is the system registered with the National Privacy Commission?", "That is an open item. It is in our risk register with a Privacy Impact Assessment, a breach plan and written agreements with the hosting services.")],
  broke="If the services show Asleep, press the button again after a minute and say that Asleep is expected on free hosting."),
]

RECOVERY = [
 ("A page is slow", "\"The free hosting is waking up; this is the one-minute wake-up we listed as a limitation.\" Keep talking about the next step while it loads."),
 ("The live site is down", "\"Let me show you the local copy.\" Switch to the localhost tab. Then the recorded video and the PDF. A panel judges how you recover, not only whether it works."),
 ("The microphone fails", "Use the website learner on the laptop, or the second device on mobile data. If both fail, play the recorded video of one reading."),
 ("An AI draft is poor", "\"This is exactly why a teacher approves every draft.\" Reject it and place a better one."),
 ("The panel asks something you do not know", "Use the four-part answer: what we did, what the source shows, what we did not measure, what we would do next. Never invent a number: \"I will check the appendix.\""),
]
