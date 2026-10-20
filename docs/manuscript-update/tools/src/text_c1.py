"""Insert-ready text, Part C (Chapter 3), blocks C1 to C6."""
from docx_kit import *
from text_ab import P, smart

FIG = "../out/figures/"


def fig(d, fname, label, title, max_h=7.6):
    picture(d, FIG + fname, TEXT_W_IN, max_h)
    fig_caption(d, label, title)


def part_c1(d):
    h1(d, "PART C. CHAPTER 3: RESEARCH METHODOLOGY")
    # ---------------------------------------------------------------- C1
    meta_box(d, "C1", "UPDATED", "Chapter 3, 'Software Engineering Methodology' (page 30): the sentence listing the system's components in the first paragraph of that page, and the 'Development Phase' paragraph.",
             "Two phrases that name SMS: 'and SMS notifications' and 'and SMS notification functionality'.",
             note="Also change 'will be' to the past tense where the work is already done (the sprint plan and the Gantt chart keep their future wording only for work not yet finished).")
    wp(d, "Replace the sentence (page 30, first paragraph):", bold=True)
    P(d, "This methodology is appropriate for TaraBasa AI because the system integrates multiple components, including AI-powered reading assessment, speech recognition, adaptive learning activities, gamified reading exercises, learner progress monitoring, teacher and parent dashboards, and in-app alerts and email notifications.")
    wp(d, "Replace the sentence (Development Phase):", bold=True)
    P(d, "This includes implementing the AI-assisted reading assessment, adaptive learning activities, gamified reading exercises, learner monitoring features, teacher and parent dashboards, and the alert and notification functions.")
    end_marker(d, "C1")

    # ---------------------------------------------------------------- C2
    meta_box(d, "C2", "UPDATED", "Chapter 3, Agile Sprint Plan, Tables 3.5 to 3.8 (pages 35 to 38), the column of the 9th Sprint.",
             "'Progress Alerts and SMS Communication' and every cell of that column that says SMS.",
             "SMS was dropped; the sprint delivered in-app alerts and emails.")
    table(d, ["SDLC phase", "9th Sprint: Progress Alerts and In-App Notifications (Time frame: 2 weeks)"],
          [["Plan", "Define notification triggers, recipients, message types, learner progress alerts, and the alert rules for needing support, readiness to move up, starting to read, and long quiet periods."],
           ["Design", "Design the notification center, alert presentation, read and unread status, the suggested next step, and the notification management interfaces."],
           ["Develop", "Implement system notifications, notification records, read and unread status, learner progress alerts, suggested activities, and confirmation and reset emails."],
           ["Test", "Test notification generation, recipient accuracy, notification status, progress alerts, suggested activities, and email delivery and reliability."],
           ["Deploy", "Deploy notification management and email functions to the staging environment."],
           ["Review", "Review notification relevance, alert reliability, email delivery, and communication effectiveness."]],
          [1.0, 5.0], size=8.5)
    d.add_paragraph()
    end_marker(d, "C2")

    # ---------------------------------------------------------------- C3
    meta_box(d, "C3", "UPDATED", "Chapter 3, 'Business Model Canvas', Table 4 and Table 4.1 (pages 40 to 41) and the paragraph after them (page 42).",
             "Cells that name Google Cloud Speech-to-Text, Semaphore SMS, SMS updates, Android Studio, React.js, Firebase and Advertisement; and the whole paragraph on page 42.",
             "Real stack and features. Advertisement is removed because TaraBasa AI is used by children and its privacy page states that no advertising is shown. If the panel wants a revenue stream, keep Subscription and the sale of shared activities.",
             "'No Read, No Move' has no citation in version 29. Cite the DepEd issuance or remove the phrase (see 04_New_References_and_Basis).")
    wp(d, "Block by block (replace the old bullet with the new text):", bold=True)
    table(d, ["Block", "Replace this", "With this"],
          [["Key Partners", "(Teachers, Parents)", "Teachers; parents; the partner school; the Department of Education curriculum references; service providers (Render hosting, TiDB Cloud database, Google for the Gmail API and the Gemini model)."],
           ["Key Activities", "Develop AI models for reading assessment and intervention.", "Connect a speech recognition service for reading assessment and write the rules for adaptive intervention."],
           ["Value Proposition", "Phil-IRI-aligned AI reading assessment and diagnostic placement", "AI-assisted oral reading assessment with a first reading check, reported in Phil-IRI level names for adults."],
           ["Value Proposition", "AI-powered reading intervention planning and learner intervention identification", "Rule-based alerts and suggested activities for teachers, explained by the evidence."],
           ["Value Proposition", "SMS parent notifications that require no smartphone or internet access", "In-app alerts and progress pages for parents on the phone app or the website."],
           ["Customer Relationships", "Automated SMS updates", "Automatic in-app alerts to teachers and parents after each reading."],
           ["Customer Relationships", "Weekly progress summaries maintain ongoing parent involvement.", "A weekly reading goal and a progress page keep parents involved."],
           ["Key Resources", "Google Cloud Speech-to-Text API", "Vosk speech recognition service (Reading-api)."],
           ["Key Resources", "Cloud hosting platform", "Render web hosting and TiDB Cloud database."],
           ["Key Resources", "Semaphore SMS Gateway API", "Gmail API for account emails."],
           ["Key Resources", "English reading content database (Phil-IRI levels)", "First reading check texts and AI-drafted, teacher-approved activities, coded to the MATATAG Curriculum."],
           ["Key Resources", "Android development environment (Android Studio, Expo)", "React Native with Expo; Visual Studio Code."],
           ["Key Resources", "Web development (VS Code, React.js)", "Laravel 13 (PHP 8.4) with Blade pages; Visual Studio Code."],
           ["Channels", "Mobile app; web application; school deployment", "Android application for learners, teachers and parents; website for all four roles; school deployment."],
           ["Cost Structure", "Google Cloud Speech-to-Text API usage fees (per request)", "Hosting and database plans (free plans were used in development; paid plans are needed for steady classroom use)."],
           ["Cost Structure", "SMS Gateway API charges (per message sent)", "Remove."],
           ["Cost Structure", "Cloud hosting and storage fees (Firebase/Google Cloud, monthly)", "Render and TiDB Cloud plans; AI service usage (Gemini) per request."],
           ["Revenue Streams", "Subscription; Advertisement", "Subscription (school license); sale of shared activities in the Open Repository (the prototype simulates the payment)."]],
          [1.2, 2.3, 2.5], size=8.5)
    d.add_paragraph()
    wp(d, "Replace the paragraph after the canvas (page 42):", bold=True)
    P(d, "Table 4 visualizes the Business Model Canvas for TaraBasa AI, which serves three main customer segments: learners, teachers, and parents. For learners, it offers AI-assisted reading assessment with a first reading check, adaptive reading activities, read-aloud practice games, and a Personal Word Bank. Teachers benefit from rule-based alerts and suggested activities, learner progress monitoring, and class reports that support reading instruction and the Department of Education's reading assessment practices. Parents receive progress pages, tips for practicing at home, and in-app alerts about their child's reading. The system is delivered as an Android application and a website, and is supported by partnerships with teachers, parents, and the partner school, and by service providers for hosting, the database, speech recognition, and email. Revenue is expected from school subscriptions and from the sale of shared activities. Key resources include the speech recognition service, the rules for adaptive support, cloud hosting, and curriculum-coded reading content, with core activities in data gathering, interface design, the writing of adaptive rules, and system integration. These are sustained by a cost structure of hosting, database, and AI service plans and content development for an adaptive reading intervention platform for Philippine public elementary schools.")
    end_marker(d, "C3")


def part_c4(d):
    # ---------------------------------------------------------------- C4
    meta_box(d, "C4", "UPDATED", "Chapter 3, 'Program Workflow' (pages 42 to 53). Replace Figures 3.1 to 6.1 and the four explanation paragraphs under them. The introduction paragraph on page 42 stays. The new Figure 3.1 (System Flow Chart) comes first; the Learner figures become 3.2 to 3.4.",
             "Figure 3.1 and 3.2 (Learner), 4.1 and 4.2 (Teacher), 5.1 and 5.2 (Parents), 6.1 (Administrator).",
             "The old figures show a Parent starting a practice reading, a Teacher browsing the Open Repository, an Admin editing a profile, a Pending teacher blocked from signing in, and a learner moved up or down by one reading. None of these is how the system works.",
             "Figure files are in 05_Figures (PNG for Word, .drawio to edit). Each picture is 6.0 inches wide so it fits the text width.")
    h2(d, "Program Workflow")
    fig(d, "F01_system_flow.png", "Figure 3.1:", "System Flow Chart")
    P(d, "Figure 3.1 gives the flow of the whole system as one story. A parent signs up, confirms the email address, and adds a child, and the system creates the learner and the Learner Code. A teacher signs up, confirms the email address, and is approved by the Administrator, then creates a class, adds the child by code or QR, asks the AI service to write activities, reviews them, and assigns them. The learner signs in, completes the first reading check, and reads; the reading service scores each recording, written rules move the reading step slowly and keep the word bank and badges, and the adaptive recommender updates the learner's profile and suggests what is next. The system then makes alerts and updates the dashboards that teachers and parents see, and the cycle repeats. The figures that follow show each actor's workflow in detail.")
    fig(d, "F05_learner_workflow_1.png", "Figure 3.2:", "Program Workflow (Learners)")
    fig(d, "F06_learner_workflow_2.png", "Figure 3.3:", "Program Workflow (Learners) cont'd")
    fig(d, "F07_learner_workflow_3.png", "Figure 3.4:", "Program Workflow (Learners) cont'd", 6.2)
    P(d, "Figures 3.2 to 3.4 illustrate the workflow of the Learner actor. In Figure 3.2, the Learner signs in by scanning the learner card or typing the Learner Code and then entering a 4-digit PIN. The system checks the code and the PIN; after five wrong tries the Learner must wait 15 minutes and the Parent is told. A Learner who has not yet completed the first reading check hears a welcome, does a one-word microphone warm-up, and reads up to three items, starting at the step that the Parent's profile suggests. The recording is scored by the reading service, and the system moves one rung up, one rung down, or stops, according to the score. The Learner sees a friendly result without a score, and the system saves the reading step and starts the learner's adaptive profile. In Figure 3.3, a Learner who has finished the first check chooses an activity from those assigned by the Teacher, unlocked by the Parent, or recommended by the system, listens to Tara read the text, and may practice twice without any effect on the record. The final reading is recorded; a recording that cannot be heard may be repeated up to three times before the Learner is asked to see a Teacher or Parent. A valid recording is scored, words that sound the same are accepted, and faintly heard words are marked as not sure. A story with questions is followed by a short comprehension quiz. The system saves the reading, adds missed words to the Personal Word Bank, and gives points. The reading step moves only after three strong readings of at least two activities, or after two weak readings in a row, and then by one step only. The adaptive recommender updates the learner's profile and suggests what to read next, badges are awarded, and alerts are made for the Teacher and the Parent. Figure 3.4 shows the Learner's other options: read-aloud Practice Games, which never change points, streak, or reading step; My Bookshelf, which allows a finished story to be read again for free; and the Rewards area, which shows badges, the weekly goal, the day streak, and the reading path.")
    fig(d, "F08_teacher_workflow_1.png", "Figure 4.1:", "Program Workflow (Teacher)", 6.6)
    fig(d, "F09_teacher_workflow_2.png", "Figure 4.2:", "Program Workflow (Teacher) cont'd")
    fig(d, "F10_teacher_workflow_3.png", "Figure 4.3:", "Program Workflow (Teacher) cont'd")
    fig(d, "F11_teacher_workflow_4.png", "Figure 4.4:", "Program Workflow (Teacher) cont'd", 6.4)
    P(d, "Figures 4.1 to 4.4 present the workflow of the Teacher actor. In Figure 4.1, the Teacher signs up with a name, school, employee identification number, the grades handled, and an email address. The system checks the form and the email domain, creates the account as Pending, and sends a confirmation link. After the Teacher opens the link and signs in, a Pending account can view the dashboard and use the free AI drafts, while an account approved by the Administrator has full access to classes, activities, assigning, and sharing. A Teacher who later handles another grade asks the Administrator to add it and names a person at the school who can confirm it; the grade is added only when the Administrator approves. Figure 4.2 shows classes and learners. A class belongs to one grade only, even for a Teacher who handles several grades. A Teacher adds a learner by typing the last five characters of the Learner Code or by scanning the learner card. The system checks the code while it is typed and allows the Add button only when the learner is of the class's grade, does not read far above the level of the class, and is not already in another class. The Teacher can then view the roster divided into reading groups, open a learner's profile, move a learner to another class of the same grade, and release or claim a learner for promotion. Figure 4.3 shows activities. The Teacher asks for activities by grade, skill, kind, and the number wanted at each level, optionally for one class, and the activity writer prepares the drafts in the background. The Teacher edits each draft, places it in the Easy, Medium, or Hard level, or rejects it, and assigns approved activities to a learner, class, reading group, or focus group. The system refuses an assignment that the children could not read because of its grade or length, and an approved activity may be shared to the Open Repository, which adds free generation credits. Figure 4.4 shows monitoring: analytics, alerts that suggest a next step, and the learner profile. The Teacher does not browse the Open Repository; only Parents do.")
    fig(d, "F12_parent_workflow_1.png", "Figure 5.1:", "Program Workflow (Parents)", 6.8)
    fig(d, "F13_parent_workflow_2.png", "Figure 5.2:", "Program Workflow (Parents) cont'd", 6.2)
    P(d, "Figures 5.1 and 5.2 detail the workflow of the Parent actor. The Parent signs up, confirms the email address, and signs in. To add a child, the Parent enters the name, grade, character, reading stage, and optionally the home language, supports, and interests, answers three questions about the child's reading, and sets a 4-digit PIN. The system creates the learner and the Learner Code and shows a printable learner card with a QR code that holds only the code. A Parent can link an existing child with the code and the child's PIN, make a new PIN for a child who forgot it (the Parent's password is needed and the number of changes is limited), and remove a child's profile; if another guardian is linked, only the Parent who asked is unlinked, and otherwise the child's records are deleted. In Figure 5.2, the Parent opens Progress to see the child's reading level in plain words, days in a row, weekly goal, badges, recent readings, and tips for practicing at home; opens Activities to see the Open Repository with the best matches for the child first, unlock a free or paid activity (the payment is simulated), and rate it; and opens Alerts and the profile. A Parent cannot start a reading for a child. A child reads only after signing in with the PIN, so that the Parent's own sign in is not a way around the child's account.")
    fig(d, "F14_admin_workflow.png", "Figure 6.1:", "Program Workflow (Administrator)", 6.0)
    P(d, "Figure 6.1 presents the workflow of the Administrator actor, who works on the website only. After signing in, the Administrator opens Approvals to see the teachers who are waiting, with their school, employee identification number, grades, and whether the email address is confirmed. The Administrator approves a teacher whose details are correct, or rejects the account with a reason that the teacher will see, and may reopen a rejected account or approve or decline a request to add a grade. In Accounts the Administrator searches and filters accounts, deactivates or reactivates an account, confirms an email address by hand with a reason, and deletes an account with a reason. In System health the Administrator tests the reading service, the activity writer, the adaptive recommender, and email sending, and reconnects email sending when needed. The Activity log records who did what and why. There is one Administrator account, created by the system's owners, and there is no self-registration.")
    end_marker(d, "C4")


def part_c5(d):
    # ---------------------------------------------------------------- C5
    meta_box(d, "C5", "DECISION", "Chapter 3, 'Validation Board' (pages 53 to 57), and Chapter 1, 'Scope and Limitations'.",
             "The pilot school and the survey numbers.",
             "Two things the writer must settle with the team before editing; this block has no text to paste.")
    wp(d, "1. Which school? Chapter 1 says 'a selected public elementary school in Cebu City'. The Validation Board says 'Suba Elementary School in Liloan, Cebu' (twice). Liloan is a municipality, not Cebu City. Use one wording everywhere (Chapter 1, Chapter 3 Validation Board and Development Phase, the transmittal letter in Appendix A).")
    wp(d, "2. How many respondents? The Validation Board gives percentages (90.9 percent and 84.7 percent agreed; 36.4 percent and 63.6 percent for phonics) but no number of respondents. Write 'n =' for each group. The teacher percentages fit 11 respondents exactly (10 of 11 is 90.9 percent, 4 of 11 is 36.4 percent, 7 of 11 is 63.6 percent). Please confirm this against Appendix D. The parent percentage 84.7 does not fit a small number; take n from Appendix E.")
    wp(d, "Suggested sentence pattern: 'Based on the survey of [n] teachers (Appendix D), [x] percent agreed that ...'.")
    end_marker(d, "C5")

    # ---------------------------------------------------------------- C6
    meta_box(d, "C6", "UPDATED", "Chapter 3, 'Gantt Chart/PERT Chart', Tables 6.1 to 6.4 (pages 58 to 61).",
             "Task names that say SMS or pronunciation feedback, and the coloured bars.",
             "SMS was dropped; the colours (green complete, yellow ongoing, red incomplete) must show the real status on the date of submission.",
             "Dates are the team's own plan; only the task names are changed here.")
    table(d, ["Task ID", "Replace this task name", "With this"],
          [["4.1.6", "SMS Notification Integration for Parents", "Alerts and Notification Management for Teachers and Parents"],
           ["4.2.3", "Speech Recognition and Pronunciation Feedback System", "Speech Recognition and Word Feedback System"],
           ["4.3.6", "Validate SMS Notification triggers", "Validate alert and notification triggers"],
           ["4.4.3", "Deploy Parent Web Dashboard and SMS integration", "Deploy Parent Web Dashboard and email sending"],
           ["4.4.4", "Deploy local version of Learner Mobile App", "Deploy the Android application (Learner, Teacher and Parent) as a test build"],
           ["4.5.6", "Verify sms notificatiom, delivery speed, message accuracy and web-based notification feedback behavior", "Verify alert accuracy, email delivery, and notification behavior"],
           ["4.2 (new rows)", "(none)", "Add: Teacher and Parent screens in the mobile application; Practice Games; Admin system health and activity log"]],
          [0.9, 2.6, 2.5], size=8.5)
    d.add_paragraph()
    end_marker(d, "C6")
