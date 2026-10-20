"""Insert-ready text, Part C blocks C7 to C11 (FDD, use cases, adaptive support, interface, storyboards)."""
from docx_kit import *
from text_ab import P, smart
from text_c1 import fig
from model import MODULES

FDDD = "../out/fdd/"
UCD = "../out/usecase/"

FDD_FILES = {
    1: "FDD_01_account_management_clean.png", 2: "FDD_02_learner_management_clean.png", 3: "FDD_03_class_management_clean.png",
    4: "FDD_04_reading_activity_management_clean.png", 5: "FDD_05_open_repository_management_clean.png",
    6: "FDD_06_learner_reading_assessment_clean.png", 7: "FDD_07_gamified_learning_clean.png", 8: "FDD_08_dashboard_analytics_clean.png",
    9: "FDD_09_notification_management_clean.png", 10: "FDD_10_adaptive_reading_support_clean.png",
    11: "FDD_11_system_administration_and_privacy_clean.png",
}

FDD_TEXT = {
    1: "Figure 7.2 presents the Account Management module. The Administrator signs in and signs out. A Teacher and a Parent each register, confirm the email address by a link (and may ask for a new link), sign in with an email and password or with a Google account on the website, reset a forgotten password, view and edit the profile, change the password, and sign out. A Teacher may also ask to handle another grade, and both may delete their own account. A Learner scans the learner card or types the Learner Code, enters the PIN, views the learner's own profile details, uses the learner dashboard, and signs out. A Learner never registers alone, which reflects the system's design principle that a parent creates the learner's account.",
    2: "Figure 7.3 presents the Learner Management module, which belongs to the Parent. The Parent views the linked learners and adds a learner, which generates the Learner Code. The Parent sets the reading stage and answers three questions about the child's reading, sets the learning style, the home language, the supports, and the interests, and sets the PIN. The Parent can reset a forgotten PIN, link an existing learner with the code and the PIN, print the learner card with its QR code, and remove the learner profile.",
    3: "Figure 7.4 presents the Class Management module, which belongs to the Teacher. The Teacher views the classes of a school year, creates a class for one grade only, and edits the class. The Teacher adds a learner by typing the last characters of the Learner Code or scanning the QR code, views the roster divided into reading groups, views a learner's profile, moves a learner to another class, views the class history, and manages learner promotion by releasing a learner or claiming a released learner.",
    4: "Figure 7.5 presents the Reading Activity Management module, which belongs to the Teacher. The Teacher views the activities, asks the AI engine to generate drafts, choosing how many are wanted at each level and letting the work run in the background, and may write for a class. The Teacher views the generation credits, edits a draft, places it in the Easy, Medium, or Hard level or rejects it, and assigns an activity after the system checks that the children can read it.",
    5: "Figure 7.6 presents the Open Repository Management module. The Teacher shares an approved activity to the repository and sets its price, which may be free. The Parent browses the repository, where the best matches for the child come first, unlocks an activity, rates it, and makes the payment for a paid activity. The payment is simulated; no real money is handled.",
    6: "Figure 7.7 presents the Learner Reading Assessment module, which belongs to the Learner. The Learner does a microphone warm-up, takes the first reading check, and views the adaptive recommendation. The Learner starts the recommended activity or another one, practices with free tries, records the reading, answers the comprehension quiz when the story has one, views the result with a word-by-word review, and views the reading path and progress.",
    7: "Figure 7.8 presents the Gamified Learning module, which belongs to the Learner. The Learner views points and the 100 badges, the day streak and the weekly goal, plays the four read-aloud practice games, practices difficult words, views the bookshelf, re-reads a finished activity for free, and changes the text size.",
    8: "Figure 7.9 presents the Dashboard Analytics module. The Teacher views the dashboard, selects a learner or group, views the analytics overview and the 'Did it work?' comparison, downloads the class report as a CSV file, and views the reading history. The Parent views the dashboard, selects one of the linked learners, and views that learner's progress, with the reading level in plain words and tips for practicing at home, and the reading history.",
    9: "Figure 7.10 presents the Notification Management module. The Teacher views the alerts, which the system works out from the learners' readings, gives the suggested activity in one tap, marks an alert handled, and reads notifications. The Parent views and reads notifications and marks them all as read. The system sends the confirmation and reset emails. There are no text messages.",
    10: "Figure 7.11 presents the Adaptive Reading Support module, which is carried out by the system itself and not by a user. The system places the learner on the reading ladder, scores the oral reading through the speech service, accepts words that sound the same, marks faintly heard words as not sure, moves the reading step gradually, updates the learner's profile and recommends the next activity through the recommendation service, finds mistake patterns, keeps the personal word bank, checks whether the children can read an activity, and suggests activities for a group.",
    11: "Figure 7.12 presents the System Administration and Privacy module, which belongs to the Administrator. The Administrator views the overview and the waiting items, approves a teacher or rejects the account with a reason, reopens a rejected teacher, and approves or declines a request to add a grade. The Administrator confirms an email address by hand, deactivates or reactivates an account, deletes an account, views the system health and checks the services, reconnects email sending, and views the activity log.",
}

UC_FIGS = [
    ("UC_1_account.png", "Figure 8.1:", "Use Case Diagram - Account Management and Administration",
     "Figure 8.1 presents the Account Management and System Administration modules. Teachers, Parents, and Learners share the core access functions of registering (for Teachers and Parents), confirming the email address, signing in, and signing out. Teachers and Parents can also reset a forgotten password, change their password, view and edit their profile, and delete their own account, and Teachers can ask to handle another grade. Learners sign in with the Learner Code and the PIN. The Administrator signs in, views the waiting items, approves or rejects teachers, reopens rejected teachers, decides on grade requests, confirms an email address by hand, deactivates or reactivates accounts, deletes an account, views the system health, reconnects email sending, and views the activity log."),
    ("UC_2_learner_class.png", "Figure 8.2:", "Use Case Diagram cont'd - Learner and Class Management",
     "Figure 8.2 presents the Learner Management and Class Management modules. Parents view their learners, add a learner and generate the Learner Code, set the reading stage and the answers to three questions, set the learning style, the home language, the supports, and the interests, and set or reset the PIN. They can also link an existing learner, print the learner card with its QR code, and remove a learner profile. Teachers view their classes by school year, create or edit a class for one grade, and add a learner by code or QR scan. They view the roster and the reading groups, view a learner's profile, move a learner to another class, view the class history, and manage learner promotion."),
    ("UC_3_activities.png", "Figure 8.3:", "Use Case Diagram cont'd - Reading Activities and Open Repository",
     "Figure 8.3 presents the Reading Activity Management and Open Repository Management modules. Teachers view activities, generate drafts with the AI engine, write for a class, and view their generation credits. They edit a draft, place it in a level or reject it, and assign the activity after the system checks that the children can read it. Teachers also share an activity to the repository and set its price. Parents browse the repository, where the best matches come first, unlock an activity, rate it, and make the payment, which is simulated."),
    ("UC_4_reading.png", "Figure 8.4:", "Use Case Diagram cont'd - Reading Assessment and Gamified Learning",
     "Figure 8.4 presents the Learner Reading Assessment and Gamified Learning modules, which are used by Learners. Learners do a microphone warm-up, take the first reading check, and view the adaptive recommendation. They start the recommended activity or another one, practice with free tries, record the reading, answer the comprehension quiz, and view the result with a word review. Learners also view the reading path, their points and badges, the day streak and weekly goal, play the practice games, practice difficult words, view the bookshelf, re-read an activity for free, and change the text size."),
    ("UC_5_dashboard.png", "Figure 8.5:", "Use Case Diagram cont'd - Dashboards and Alerts",
     "Figure 8.5 presents the Dashboard Analytics and Notification Management modules. Teachers and Parents both view the dashboard, select a learner, and view the reading history. Teachers also view the analytics overview, the 'Did it work?' comparison, and the class report, and Parents view the progress with tips for practicing at home. Teachers view the alerts, give the suggested activity from an alert, and mark an alert handled. Parents view notifications and mark them as read. The system sends the confirmation and reset emails."),
    ("UC_6_adaptive.png", "Figure 8.6:", "Use Case Diagram cont'd - Adaptive Reading Support",
     "Figure 8.6 presents the Adaptive Reading Support module. The system itself carries out these functions after a Learner reads: placing the learner on the reading ladder, scoring the oral reading, accepting words that sound the same, marking faintly heard words as not sure, moving the reading step gradually, updating the profile and recommending the next activity, finding mistake patterns, keeping the personal word bank, checking whether the children can read an activity, and suggesting activities for a group. Learners, Teachers, and Parents use the results of these functions through their own screens."),
]


def part_c7(d):
    meta_box(d, "C7", "UPDATED", "Chapter 3, 'Functional Decomposition Diagram' (pages 61 to 66). Replace Figures 7.1 to 7.6 and their paragraphs with Figures 7.1 to 7.12 below.",
             "The nine-module FDD. Page 61 also repeats the words 'Figure 7.1 Figure 7.1 presents' (delete one).",
             "Nine modules in version 29 leave out the system's adaptive rules and the Administrator's screens, and the modules appear in a different order in the figure (Reading Assessment fifth) than in the List of Modules (Open Repository fifth). Version 2 keeps the List of Modules numbering for both.",
             "The panel review sheet (03_FDD_and_Use_Case_Panel_Review.docx) lists every function as new, updated, or unchanged and shows the old to new mapping.")
    h2(d, "Functional Decomposition Diagram")
    P(d, "This section presents the functional decomposition diagram, which breaks the proposed system into eleven modules and shows, for each module, the functions that each type of user can perform. It provides a clear overview of the system's processes and improves the understanding of how the application operates.")
    fig(d, "../fdd/FDD_00_overview_clean.png", "Figure 7.1:", "Functional Decomposition Diagram", 6.4)
    P(d, "Figure 7.1 presents the overall structure of TaraBasa AI, decomposed into eleven modules: Account Management, Learner Management, Class Management, Reading Activity Management, Open Repository Management, Learner Reading Assessment, Gamified Learning, Dashboard Analytics, Notification Management, Adaptive Reading Support, and System Administration and Privacy. Account Management, Learner Management, and Class Management handle who uses the system, who belongs to it, and how learners are grouped. Reading Activity Management and Open Repository Management cover the creation, approval, assignment, and sharing of reading activities. Learner Reading Assessment and Gamified Learning cover the learner's reading experience, while Dashboard Analytics and Notification Management keep teachers and parents informed of progress. Adaptive Reading Support holds the rules and services that the system applies to each reading, and System Administration and Privacy holds the administrator's functions, including the deletion of accounts.")
    for m in MODULES:
        n = m["no"]
        fig(d, "../fdd/" + FDD_FILES[n], f"Figure 7.{n + 1}:", "Functional Decomposition Diagram cont'd", 6.3)
        P(d, FDD_TEXT[n])
    end_marker(d, "C7")


def part_c8(d):
    meta_box(d, "C8", "UPDATED", "Chapter 3, 'Use Case Diagram/DFD' (pages 67 to 70). Replace Figures 8.1 to 8.4 with Figures 8.1 to 8.6 and their paragraphs. The introduction paragraph stays.",
             "The four use case diagrams of version 29.",
             "Generated from the same list of functions as the FDD, so the two cannot disagree. The colours of the review version (new, updated, unchanged) are removed here: these pictures are the manuscript versions in one colour family; the colour review versions are in 05_Figures/usecase.")
    h2(d, "Use Case Diagram/DFD")
    for fname, label, title, text in UC_FIGS:
        fig(d, "../usecase/" + fname, label, title, 6.3)
        P(d, text)
    end_marker(d, "C8")


RULES = [
    ["First reading check, length", "At most 3 items", "Team's choice (keeps the check short for Grade 1 to 3)"],
    ["First reading check, moving", "90 percent or more: up one rung. Below 70: down one rung. 70 to 89: stop.", "Team's choice; 90 matches the lower edge of the Phil-IRI Instructional level"],
    ["First reading check, ladder", "Seven rungs: letters, short words, short sentences, longer sentences, stories of about 30, 60 and 100 words", "Team's choice; items coded to MATATAG competencies"],
    ["Level after the check", "Rungs 0 to 2 Beginning; 3 and 4 Developing; 5 and 6 Proficient", "Team's choice; follows the recommender's easy, medium, hard bands"],
    ["A strong reading", "90 percent or more with at least 4, 6, 10, 17, 27 or 45 words (steps 0 to 5)", "Team's choice; the word minimum stops a very short text from counting"],
    ["Moving a step up", "3 strong readings of at least 2 different activities among the last 5 after the last move: up one step", "Team's choice"],
    ["Moving a step down", "2 weak readings (under 70 percent) in a row, on texts that were not too long for the child: down one step", "Team's choice; a weak reading is set well below the Phil-IRI Frustration line (90) so one hard text does not move a child"],
    ["Words that sound the same", "Lists of same sound words, spelling variants, numbers and split words", "Written lists in the settings; near misses stay mistakes"],
    ["Not sure words", "A missed word heard below 0.50 confidence is left out; at most 25 percent of the words; never a skipped word", "Team's first guess from synthesized voices; to be tuned with children's recordings"],
    ["Mistake pattern", "Last 8 readings; at least 3 readings and 6 misses; the pattern must be 25 percent or more of the misses; called early until 5 readings", "Team's choice"],
    ["Personal word bank", "Struggling, then Improving after one correct reading, then Mastered after 3 correct readings; at most 4 new words per reading", "Team's design; no study checked for these numbers"],
    ["Can the children read it?", "A comfortable and a stretch number of words for each step: 6 and 10, 10 and 14, 16 and 24, 28 and 40, 45 and 60, 75 and 100, 130 and 170; a class is refused when 20 percent or more of its known learners could not read it", "Team's choice, anchored on the first check's own texts"],
    ["Alert: needs support", "The latest reading was under 70 percent within 14 days, or the last 3 readings were under 70", "Team's choice"],
    ["Alert: ready to move up", "The last 3 readings were all 90 percent or more (and the app has not moved the learner yet)", "Team's choice"],
    ["Alert: starting to read / gone quiet", "A learner on the letters step; no reading for 7 days or more", "Team's choice"],
    ["Practice games", "3 things per round; 2 first-try reads make a clean round and the next level; 2 tries each; up to 3 rounds; a row or sentence counts as read when two thirds of its words are heard", "Team's choice; set with synthesized voices"],
    ["Weekly goal", "5 readings a week, counted by Philippine time", "Team's choice"],
    ["Adaptive recommender", "Moving average of the profile (0.30); confidence up 0.08 per reading; easy, medium, hard bands from the service's own settings", "The teammate's service (fixed formulas)"],
    ["Basis for a class", "Something is said about a class only when at least 2 children show it; the note to the AI writer is at most 560 characters", "Team's choice"],
]


def part_c9(d):
    meta_box(d, "C9", "NEW", "Chapter 3, 'Analysis Design Phase', after 'Use Case Diagram/DFD' (page 70) and before 'User Interface Design' (page 71). This adds one H3 section, Figure 9, and Table 7.",
             "Nothing; the rule-based adaptive support is not described anywhere in version 29.",
             "Phil-IRI levels (DepEd Order No. 14, s. 2018); MATATAG Curriculum; National Reading Panel (2000); Ehri et al. (2001); Therrien (2004); the practice-game research in B3.",
             "This section is where the panel will test the claim 'adaptive'. It says plainly what is a rule, what is an outside service, and that no model was trained. Do not change that wording.")
    h3(d, "Adaptive Reading Support Design")
    P(d, "TaraBasa AI supports each learner with a chain of written rules and fixed formulas, shown in Figure 9, that turns one recording into a decision about the next step. The researchers did not train a machine learning model. Two outside services take part: a reading service that turns a recording into words, timing, and scores, and a recommendation service that keeps a numerical profile of the learner by fixed formulas. All other decisions are rules written in the application, with the settings listed in Table 7.")
    fig(d, "F15_adaptive_rule_chain.png", "Figure 9:", "Adaptive Reading Support Rule Chain", 7.2)
    P(d, "The first reading check places a learner on a ladder of seven rungs, from saying letter names, through reading short words, short sentences, and longer sentences, to reading stories of about 30, 60, and 100 words. The check starts at the rung that the Parent's description of the child and the child's answers suggest, not at the learner's grade, so that a Grade 3 child who cannot yet read starts with letters and a Grade 2 child who reads well starts with a story. A reading of 90 percent or more moves the check up one rung, a reading below 70 percent moves it down one rung, and a reading in between ends the check, which lasts at most three items. The rung where the check ends becomes the learner's reading step, and the step is shown to the child as one of four: Letter Explorer, Word Builder, Sentence Reader, and Story Reader. Adults see the same step as a Phil-IRI level name (Non-reader, Frustration, Instructional, or Independent), and the Department of Education bases the Phil-IRI levels on oral reading accuracy (Department of Education, 2018).")
    P(d, "After each reading the system cleans the score before it is used. Words that sound the same, such as sun and son, and spelling variants are accepted. A word that the reading service marked wrong but heard only faintly is marked as not sure and left out of the score, the word bank, and the alerts, up to a quarter of the words, so that a poor recording is not counted as the child's mistake. The reading step then moves slowly: it moves up one step only after three strong readings, of at least two different activities, since the last move, and it moves down one step only after two weak readings in a row. A single reading never moves a learner, so that reading six words perfectly once does not make a child a sentence reader. The step is the learner's place on the path and the level that adults see.")
    P(d, "The recommendation service is sent the scores of each reading together with the learner's current profile, and it returns the updated profile, which the application stores, and a suggestion for the next kind of activity and its level. The system puts a matching activity first in the learner's list as 'Picked just for you'. The system also looks at the words a learner missed in the last eight readings and sorts the misses into kinds, such as small words skipped, word endings, vowel sounds, first sounds, last sounds, letter blends, and longer words read in pieces. When one kind accounts for a quarter or more of the misses over at least three readings, the system names it as the learner's main mistake pattern. The pattern is used to order the activities offered to the learner, to choose the words in the practice games, to give the Parent a tip for practicing at home, and to add a short note to the request that a Teacher sends to the activity writer when the Teacher asks for activities for a class. A word the learner misses goes to the Personal Word Bank as a struggling word, and it becomes mastered after three correct readings.")
    P(d, "The teacher's side of the support is an alert and a suggestion. The system marks a learner who needs support (the latest reading under 70 percent, or three under 70 in a row), a learner who is ready to move up (three readings of 90 percent or more), a learner who is just starting to read (on the letters step), a learner who has gone quiet (no reading for seven days), and a learner who has moved up. Each alert shows the evidence and, when a suitable activity exists in the Teacher's own approved activities, suggests one that the Teacher can give in one tap. Before any assignment the system checks that the children can read the activity: the activity must be of the children's grade, and its length must not be far above what a child at that step can read. The Teacher can still choose, because the system advises and the Teacher decides.")
    P(d, "The four practice games (Balloon Pop, Word Train, Sentence Stars, and Syllable Steps) ask the learner to read aloud. The Syllable Steps game follows a syllable method of four steps: hear and echo the parts of a word, split it, join it by tapping the parts in order, and read it in a sentence. Only the last step is scored, because the recognizer cannot reliably hear single syllables. The games are free play and never change the learner's points, streak, or reading step.")
    table_caption(d, "Table 7", "Rules and Settings of the Adaptive Reading Support")
    table(d, ["Rule", "Setting", "Where the number comes from"], RULES, [1.3, 3.0, 1.7], size=8.5)
    P(d, "Table 7 lists every setting that the rules use. The settings are kept in one file for each rule so that they can be changed without changing the program. They are the researchers' own choices, set from the literature and from tests with synthesized voices, and they have not been checked against recordings of Filipino children. The intended check is described in the testing section: the readings collected in the pilot will be used to see whether the reading step moves when teachers expect it to move.")
    end_marker(d, "C9")


SCREEN_MAP = [
    ["9", "Splash Screen", "Exists in the phone app.", "KEEP"],
    ["10", "Welcome Screen", "Caption text repeats the Splash Screen description (page 72).", "FIX the text"],
    ["11", "Parent Registration", "Real form has First name, Middle initial, Last name, email, password; the email must be confirmed before sign in. The text says 'full name'.", "UPDATE text; replace picture with the real screen"],
    ["12", "Login Screen", "Teachers and Parents sign in with email; Learners scan a card or type a code and a PIN. 'Password recovery' exists.", "UPDATE text"],
    ["13", "Create Child Profile", "Real wizard has 5 steps: name and grade, character, reading stage and 3 questions, PIN. No 'age' or 'school name'.", "REPLACE with the real wizard"],
    ["14", "Parent Dashboard", "Real Home shows child chips, a flag, days in a row, weekly goal, badges, source of readings.", "REPLACE with the real screen"],
    ["15", "Learner Home", "Real Home shows reading path, stats, goal and growth; navigation is Home, Read, Games, Badges, Shelf.", "REPLACE with the real screen"],
    ["16 to 21", "Assessment intro, reading, quiz, results, lesson result, rewards", "Real screens exist but look different (Tara the owl, star results, reading scene, badges page).", "REPLACE with the real screens"],
    ["22 to 29", "Teacher screens (dashboard, class list, learner profile, assign, intervention flag, reports, notifications, profile)", "Real Teacher menu: Home, Classes, Activities, Alerts, Analytics, Promotions, Profile. 'Intervention Flag' is now Alerts. Assigning is a window.", "REPLACE with the real screens; rename 'Intervention Flag' to Alerts"],
    ["30", "Admin Login (Web)", "Exists.", "KEEP"],
    ["31", "Admin Dashboard (Web)", "Real Admin Overview shows waiting items and tiles, not 'total schools' and charts.", "REPLACE"],
    ["32", "AI Configuration (Web)", "NOT BUILT. There is no screen that sets thresholds, badge conditions or rewards.", "REMOVE (or label as future work)"],
    ["33", "Reports and Analytics (Web admin)", "NOT BUILT for the Admin. Reports and the CSV exist for Teachers only.", "REMOVE (or label as future work)"],
    ["34", "All Users (Web)", "Real screen is Accounts: search, filter, deactivate, confirm email, delete. No edit, password reset, export.", "UPDATE"],
    ["35", "System Logs (Web)", "Real screen is Activity log: who, what, why. No IP address column.", "UPDATE"],
    ["36", "User Detail (Web)", "NOT BUILT. The caption text repeats Figure 17 (Reading Assessment).", "REMOVE"],
]


def part_c10(d):
    meta_box(d, "C10", "DECISION", "Chapter 3, 'User Interface Design' (pages 71 to 98).",
             "The introduction paragraph, and Figures 9 to 36.",
             "The figures are the first design (prototype). Several no longer match the system, three Admin screens were never built, and two captions repeat the wrong text. Decide with the team: (a) keep them and say they are the initial design, then add real screenshots in a new 'Implemented Screens' section; or (b) replace each with a real screenshot. Option (a) is quicker.")
    wp(d, "Replace the introduction paragraph with:", bold=True)
    P(d, "The following figures present the initial User Interface Design of TaraBasa AI, showing how the application was first designed to look and function. The layout, color scheme, and experience were made to suit Grade 1 to Grade 3 learners, their teachers, and their parents. During development some screens changed; the screens that were implemented are shown in the section Implemented Screens, which follows the storyboards.")
    wp(d, "Screen map (working table, not for the manuscript). 'Figure' is the number in version 29; add 1 to every number from 9 on because a new Figure 9 is inserted.", bold=True)
    wtable(d, ["Figure", "Screen", "What the real system has", "Action"], SCREEN_MAP, [0.7, 1.7, 3.3, 1.4], size=8.5, total_w=6.0)
    d.add_paragraph()
    wp(d, "Real screens to photograph for the section 'Implemented Screens' (take them from the live site or the Android app, 1080 px wide, no real names): Parent: Home, My children, Progress, Activities, Alerts. Teacher: Home, Classes (open a class), Activities (the level board), Alerts, Analytics. Learner: Home, Read, Games, Badges, a reading screen, a result screen. Admin: Overview, Approvals, Accounts, System health.", italic=True)
    end_marker(d, "C10")


STORY_FIX = [
    ["Figure 37 (Admin storyboard), page 99", "Says the Admin sets thresholds and rewards in 'AI Configuration', composes announcements, edits a user and resets passwords, and exports reports as PDF or CSV.", "Replace by: 'The Administrator signs in to the Overview, which shows the teachers waiting and the state of the services. From Approvals the Administrator approves or rejects teachers; from Accounts the Administrator searches, deactivates, confirms an email or deletes an account; System health tests the services; the Activity log records every action with its reason.'"],
    ["Figure 38 (Learner storyboard), page 101", "Says 'new learners are prompted to register'; mentions 'My Lessons'.", "Replace by: 'A learner does not register alone. The parent creates the profile; the learner signs in by scanning the card or typing the Learner Code and the PIN.' Replace 'My Lessons' with 'Read'."],
    ["Figure 39 (Teacher storyboard), pages 103 to 104", "Says the sidebar toggles 'automated email alerts, push notifications, or weekly reporting summaries' and that an 'Intervention Flags' screen has a 'Message Parent' option.", "Replace by: 'Alerts shows who needs support, who is ready to move up, and who is just starting, with the evidence and a suggested activity that can be given in one tap.' Delete the sentences about push notifications, weekly summaries, and messaging the parent: they are not in the system."],
    ["Figure 40 (Parent storyboard), pages 105 to 106", "Says 'as detailed in parent.png' (file name left in the text); describes a 'Teacher Messaging' portal; says the child profile has age and school name.", "Delete 'as detailed in parent.png'. Delete the messaging sentences. Replace the profile description with the real wizard (name, grade, character, reading stage and questions, PIN)."],
]


def part_c11(d):
    meta_box(d, "C11", "UPDATED", "Chapter 3, 'Storyboard' (pages 99 to 106).",
             "Sentences about features that are not in the system.",
             "Teacher to Parent messaging, push notifications, weekly summaries, Admin AI configuration, announcements and report export are not built.")
    wtable(d, ["Where", "Problem in version 29", "Replace with"], STORY_FIX, [1.4, 2.2, 2.9], size=8.5, total_w=6.0)
    d.add_paragraph()
    end_marker(d, "C11")
