"""Insert-ready text, Part C blocks C12 to C20 (database, network, stack, specifications, modules, testing, privacy)."""
from docx_kit import *
from text_ab import P, smart
from text_c1 import fig
from model import MODULES, ACTORS_ORDER

DD_HEAD = ["Column Name", "Data Type", "Field Size", "Null", "Description"]
DD_W = [1.75, 0.85, 0.6, 0.55, 2.25]
NN, YES = "NOT NULL", "YES"


def dd(d, label, title, rows, after):
    table_caption(d, label, title)
    table(d, DD_HEAD, rows, DD_W, size=8.5)
    P(d, after)


NEW_TABLES = [
    ("Table 22", "ACTIVITY_ASSIGNMENT", [
        ["Asg_ID (PK)", "INT", "--", NN, "Unique identifier for the assignment"],
        ["Asg_ActivityID (FK)", "INT", "--", NN, "Refers to the activity that was assigned"],
        ["Asg_LearnerID (FK)", "INT", "--", YES, "Set when the activity is assigned to one learner"],
        ["Asg_ClassID (FK)", "INT", "--", YES, "Set when the activity is assigned to a whole class"],
        ["Asg_GroupTag", "VARCHAR", "255", YES, "Set when the activity is assigned to a focus group, which is a tag used by the teacher's classes"],
        ["Asg_ReadingBand", "VARCHAR", "255", YES, "Set when the activity is assigned to one reading group of a class (needs support, instructional, or independent)"],
        ["Asg_AssignedByTeacherID (FK)", "INT", "--", NN, "Refers to the teacher who made the assignment"],
        ["Asg_AssignedAt", "TIMESTAMP", "--", NN, "Date and time the assignment was made"]],
     "Table 22 describes the Activity_Assignment table, which records who an activity was given to. Exactly one target is set for each row: a learner, a class, a reading group of a class, or a focus group. The same table is read to decide which activities appear in a learner's list."),
    ("Table 23", "ACTIVITY_GENERATION", [
        ["Gen_ID (PK)", "INT", "--", NN, "Unique identifier for the request for activities"],
        ["Gen_TeacherID (FK)", "INT", "--", NN, "Refers to the teacher who asked for the activities"],
        ["Gen_Status", "VARCHAR", "255", NN, "Queued, Running, Done, or Failed"],
        ["Gen_GradeLevel", "VARCHAR", "255", NN, "Grade level asked for"],
        ["Gen_Competency", "VARCHAR", "255", NN, "Reading skill asked for"],
        ["Gen_ActivityType", "VARCHAR", "255", NN, "Kind of reading activity asked for"],
        ["Gen_Topic", "VARCHAR", "255", YES, "Optional topic typed by the teacher"],
        ["Gen_TeacherNotes", "TEXT", "--", YES, "Optional notes typed by the teacher"],
        ["Gen_Levels", "JSON", "--", NN, "How many drafts were asked for at each level"],
        ["Gen_ClassID", "BIGINT", "--", YES, "Class the activities were written for, if any"],
        ["Gen_Basis", "JSON", "--", YES, "The short class summary sent to the AI writer"],
        ["Gen_CreatedCount", "SMALLINT", "--", NN, "Number of drafts saved"],
        ["Gen_Message", "TEXT", "--", YES, "Plain message shown to the teacher when the request fails or ends"],
        ["Gen_StartedAt / Gen_FinishedAt / Gen_AcknowledgedAt", "TIMESTAMP", "--", YES, "When the work started, when it ended, and when the teacher dismissed the notice"]],
     "Table 23 describes the Activity_Generation table. An AI request can take about two minutes, so it is saved here and written in the background; the teacher can leave the page and return. One free credit is used only when the drafts are saved."),
    ("Table 24", "REPOSITORY_RATING", [
        ["Rr_ID (PK)", "INT", "--", NN, "Unique identifier for the rating"],
        ["Rr_ListingID (FK)", "INT", "--", NN, "Refers to the repository listing that was rated"],
        ["Rr_ParentID (FK)", "INT", "--", NN, "Refers to the parent who gave the rating"],
        ["Rr_LearnerID (FK)", "INT", "--", NN, "Refers to the learner who used the activity"],
        ["Rr_Rating", "TINYINT", "--", NN, "Rating from 1 to 5 stars"],
        ["Rr_Comment", "TEXT", "--", YES, "Optional comment from the parent"],
        ["Rr_RatedAt", "TIMESTAMP", "--", NN, "Date and time of the rating"]],
     "Table 24 describes the Repository_Rating table. A parent may rate a listing once; the average rating is shown to the teacher who shared it."),
    ("Table 25", "GAME_PLAY", [
        ["Gp_ID (PK)", "INT", "--", NN, "Unique identifier for the finished game"],
        ["Gp_LearnerID (FK)", "INT", "--", NN, "Refers to the learner who played"],
        ["Gp_Game", "VARCHAR", "255", NN, "Balloon Pop, Word Train, Sentence Stars, or Syllable Steps"],
        ["Gp_LevelReached", "TINYINT", "--", NN, "Level the learner ended on"],
        ["Gp_TopLevelCleared", "BOOLEAN", "--", NN, "True when a whole round was finished at the top level"],
        ["Gp_HadPerfectRound", "BOOLEAN", "--", NN, "True when a round was read right the first time"],
        ["Gp_Rounds", "TINYINT", "--", NN, "Number of rounds played"],
        ["Gp_PlayedAt", "TIMESTAMP", "--", NN, "Date and time the game finished"]],
     "Table 25 describes the Game_Play table. The practice games are free play, so a finished game saves only this one row, which is used to award the game badges. It never changes points, streak, or reading step."),
    ("Table 26", "TEACHER_ALERT_ACTION", [
        ["Tac_ID (PK)", "INT", "--", NN, "Unique identifier"],
        ["Tac_TeacherID (FK)", "INT", "--", NN, "Refers to the teacher who handled the alert"],
        ["Tac_LearnerID (FK)", "INT", "--", NN, "Refers to the learner the alert was about"],
        ["Tac_Kind", "VARCHAR", "255", NN, "Kind of alert: support, ready, start, quiet, or moved"],
        ["Tac_HandledAt", "TIMESTAMP", "--", NN, "When the teacher marked the alert handled"]],
     "Table 26 describes the Teacher_Alert_Action table. Alerts themselves are worked out from the readings; this table keeps only the fact that a teacher handled one. A new reading opens the alert again."),
    ("Table 27", "LEARNER_PIN_CHANGE", [
        ["Pin_ID (PK)", "INT", "--", NN, "Unique identifier"],
        ["Pin_LearnerID (FK)", "INT", "--", NN, "Refers to the learner whose PIN was changed"],
        ["Pin_UserID (FK)", "INT", "--", YES, "Refers to the parent who made the change"],
        ["Pin_Method", "VARCHAR", "255", NN, "Generated by the system, or chosen by the parent"],
        ["Pin_ChangedAt", "TIMESTAMP", "--", NN, "Date and time of the change"]],
     "Table 27 describes the Learner_Pin_Change table, which limits a PIN to three changes a day. The PIN itself is never stored here."),
    ("Table 28", "ADMIN_ACTION", [
        ["Adm_ID (PK)", "INT", "--", NN, "Unique identifier"],
        ["Adm_AdminUserID (FK)", "INT", "--", YES, "Refers to the administrator who acted"],
        ["Adm_AdminName", "VARCHAR", "255", NN, "The administrator's name at the time"],
        ["Adm_Action", "VARCHAR", "255", NN, "What was done, such as approved, rejected, or deleted an account"],
        ["Adm_TargetUserID (FK)", "INT", "--", YES, "Refers to the account acted on"],
        ["Adm_TargetName", "VARCHAR", "255", NN, "The account holder's name, or a general word when the account was deleted"],
        ["Adm_TargetRole", "VARCHAR", "255", NN, "Role of the account acted on"],
        ["Adm_Detail / Adm_Note", "VARCHAR", "255", YES, "The reason and any note"],
        ["Adm_CreatedAt", "TIMESTAMP", "--", NN, "Date and time of the action"]],
     "Table 28 describes the Admin_Action table, the activity log that the administrator reads. Names are copied into the row so the log still reads after an account is deleted, and a deleted person's name is removed."),
    ("Table 29", "SERVICE_FAILURE", [
        ["Svf_ID (PK)", "INT", "--", NN, "Unique identifier"],
        ["Svf_Service", "VARCHAR", "255", NN, "Which service: reading, activity writer, recommender, or email"],
        ["Svf_Status", "SMALLINT", "--", YES, "Answer code of the service"],
        ["Svf_Trail / Svf_ContentType / Svf_What", "VARCHAR", "255", YES, "What was tried and what came back"],
        ["Svf_Body / Svf_Details", "TEXT", "--", YES, "The service's answer and the check details"],
        ["Svf_CreatedAt", "TIMESTAMP", "--", NN, "Date and time"]],
     "Table 29 describes the Service_Failure table, a short diary of problems with the outside services. It is shown on the administrator's System health screen and kept for 14 days."),
    ("Table 30", "DATA_ERASURE", [
        ["Era_ID (PK)", "INT", "--", NN, "Unique identifier"],
        ["Era_Kind", "VARCHAR", "255", NN, "child, account, or unlinked"],
        ["Era_Role", "VARCHAR", "255", NN, "Role of the person whose data was removed"],
        ["Era_RequestedBy", "VARCHAR", "255", NN, "self, or admin"],
        ["Era_ChildrenErased", "SMALLINT", "--", NN, "Number of children whose records were deleted"],
        ["Era_CreatedAt", "TIMESTAMP", "--", NN, "Date and time"]],
     "Table 30 describes the Data_Erasure table. It records only that a deletion happened. It keeps no name, email, code, or identifier, so that deleting data does not leave new personal data behind."),
]

DD_CHANGES = [
    ["User (Table 8)", "User_MiddleInitial VARCHAR 5 YES. User_EmailVerifiedAt TIMESTAMP YES (sign in is blocked for a Teacher or Parent until it is filled).", "User_Status values: Active, Inactive. User_Type: Teacher, Parent, Admin.", "-"],
    ["Teacher (Table 9)", "Tchr_RejectionReason VARCHAR 255 YES. Tchr_GradesHandled JSON YES. Tchr_GradesRequested JSON YES, Tchr_GradesRequestedAt TIMESTAMP YES, Tchr_GradesRequestNote VARCHAR 255 YES.", "Tchr_Status NOT NULL with the values Pending, Active, Rejected (version 29 says 'YES' for null).", "-"],
    ["Learner (Table 11)", "Lrnr_AvatarPhotoPath, Lrnr_LearningStyle, Lrnr_ReadingStage, Lrnr_PlacementAnswers (JSON), Lrnr_ReadingRung TINYINT (0 to 6), Lrnr_RungChangedAt, Lrnr_CompetencyStates (JSON), Lrnr_NextRecommendedCompetency, Lrnr_NextRecommendedDifficulty, Lrnr_SubdomainStates (JSON), Lrnr_NextRecommendedSubdomain, Lrnr_ReadingFontStep, Lrnr_ThemeColor, Lrnr_HomeLanguage, Lrnr_Supports (JSON), Lrnr_Interests (JSON). All YES (null).", "Lrnr_ClassID: Null is YES (empty until a teacher adds the learner). Lrnr_PIN: VARCHAR 255, stored hashed (version 29 says VARCHAR 10). Lrnr_LearnerCode: format such as TB26-48293 with a check digit. Lrnr_MasteryLevel: Null is YES. Lrnr_Streak: counts scored readings, not days in a row (the day streak is worked out).", "-"],
    ["Parent_Learner (Table 12)", "-", "Add: one row for each parent and learner pair.", "-"],
    ["Class (Table 13)", "-", "Cls_GradeLevel holds one grade only; Cls_SchoolYear marks the school year; past years are read only.", "-"],
    ["Promotion_Record (Table 14)", "Promt_ReleasedFromClassID, Promt_ClaimedIntoClassID INT YES.", "-", "-"],
    ["Activity (Table 15)", "Act_GenerationID, Act_BundleTitle, Act_VariantLabel, Act_CompetencyLabel, Act_ActivityType, Act_TeacherNotes TEXT, Act_Title, Act_Instructions TEXT, Act_PassageText TEXT, Act_ReferenceText TEXT, Act_WordCount, Act_TargetSkills JSON, Act_ReadingFeatures JSON, Act_FollowUpQuestions JSON, Act_AiDifficultyTier, Act_Purpose, Act_CurriculumCode, Act_CheckRung, Act_GenerationBasis JSON.", "Act_TeacherID: Null is YES (the first reading check texts have no teacher). Act_Topic: Null is YES. Act_SkillFocus becomes Act_Competency. Act_DifficultyTier is the level the teacher chose.", "Act_GameType (the system has no game type on an activity)."],
    ["Open_Repository_Listing (Table 16)", "-", "Orl_ActivityID is unique: an activity is listed once.", "-"],
    ["Repository_Unlock (Table 17)", "-", "A learner can unlock a listing once. The amount is simulated.", "-"],
    ["Reading_Session (Table 18)", "Sess_SessionType (Practice or Diagnostic), Sess_SpeedScore, Sess_ProsodyScore, Sess_AdaptiveAttemptScore, Sess_ComprehensionScore FLOAT YES; Sess_SkippedWordCount, Sess_SubstitutionCount, Sess_InsertionCount INT YES; Sess_RungBefore, Sess_RungAfter TINYINT YES; Sess_WordFeedback JSON YES; Sess_AdaptiveSubdomain VARCHAR YES.", "Sess_LevelBefore and Sess_LevelAfter are the Beginning, Developing or Proficient level. Pronunciation and fluency scores and the mispronunciation and repetition counts are left empty (null): the reading service does not measure them.", "-"],
    ["Personal_Word_Bank (Table 19)", "Unique pair (learner, word).", "Rename the table 'Personal Word Bank' (version 29 says 'World').", "-"],
    ["Learner_Badge (Table 20)", "-", "Lbdg_BadgeID becomes Lbdg_BadgeCode VARCHAR 255: the 100 badge definitions are in a settings file, not a table.", "Table 'Badge' (Bdge_*): there is no such table."],
    ["Notification (Table 21)", "-", "Notf_RecipientUserID refers to a user (Teacher or Parent). Notf_Type is a short text such as session summary, needs attention, level confirmed, PIN changed, guardian removed. No SMS.", "-"],
    ["Curriculum_Guide (Table 14 in v29)", "-", "-", "There is no such table. The MATATAG codes and wording are kept in settings and on each activity (Act_CurriculumCode). Delete the table and its paragraph, and the 'Curriculum Guide' entity from the ERD narrative."],
]


def part_c12(d):
    meta_box(d, "C12", "UPDATED", "Chapter 3, 'Database Design' (pages 128 to 131): Figure 41 (ERD) and the three pages of narrative after it; and 'Data Dictionary' (pages 107 to 124). ALSO MOVE the whole 'Database Design' section so that it comes before 'Data Dictionary': the Table of Contents lists it first, but in version 29 it sits after the Technology Stack.",
             "Figure 41 and the narrative that mentions 'Curriculum Guide', 'Badge', 'Notification ... SMS notifications', 'Teachers may upload curriculum guides'.",
             "The real database (the migrations) has 23 tables made by the application: 14 of the 16 tables of version 29, and 9 new ones. 'Curriculum Guide' and 'Badge' are not tables.",
             "Figure 42 below is the new ERD (version 29's Figure 41). Table numbers in this block follow the numbering map in 01: the data dictionary becomes Tables 8 to 30.")
    h2(d, "Database Design")
    fig(d, "F16_erd_version2.png", "Figure 42:", "Entity Relationship Diagram", 8.0)
    P(d, "Figure 42 presents the Entity Relationship Diagram (ERD) of the TaraBasa AI platform, showing the 23 tables that the application keeps and how they relate. The tables support AI-assisted reading assessment, adaptive gamified learning, teacher monitoring, parent monitoring, and repository-based reading intervention for Grade 1 to Grade 3 learners in Philippine public elementary schools. The tables that the framework itself needs, such as sessions and queued jobs, are left out.")
    P(d, "At the center of the system is the User entity, which stores the account information of every registered adult: name, email address, a hashed password, user type (Teacher, Parent, or Admin), status, and the date the email address was confirmed. The Teacher entity extends the User entity through a one-to-one relationship and stores the school, the employee identification number, the status (Pending, Active, or Rejected), the grades the teacher handles, any request to add a grade, and the number of free generation credits. The Parent entity extends the User entity in the same way.")
    P(d, "Teachers own one or more Class records, and each class holds learners of one grade only. A Learner record stores the Learner Code, the grade, a hashed PIN, the reading rung and level, points and streak, and the learner's adaptive profile. A learner may belong to no class until a teacher adds the learner. Parents and learners are linked through the Parent Learner entity, which resolves the many-to-many relationship so that a learner may have more than one guardian. The Promotion Record entity keeps the history of a learner who is released by one teacher and claimed by another when the learner moves to the next grade.")
    P(d, "The Activity entity holds the reading activities, whether drafted by the AI writer for a teacher or written for the first reading check. Each activity stores its grade, competency, kind, passage, instructions, comprehension questions, curriculum code, the level suggested by the AI and the level chosen by the teacher. A teacher gives an activity to learners through the Activity Assignment entity, whose target is a learner, a class, a reading group, or a focus group. The Activity Generation entity records a teacher's request for drafts while the AI writer works in the background.")
    P(d, "Every reading is recorded in the Reading Session entity: accuracy, words correct per minute, the comprehension score, the level and reading rung before and after, the word-by-word feedback, and whether the reading was flagged. Missed words are kept in the Personal Word Bank entity, one row for each learner and word. The Learner Badge entity records which of the 100 badges a learner earned and when; the badge definitions themselves are kept in a settings file. The Game Play entity records each finished practice game, which is used only for the game badges.")
    P(d, "A teacher may share an approved activity through the Open Repository Listing entity, free or at a price. A parent unlocks a listing for a child through the Repository Unlock entity and may rate it once through the Repository Rating entity. The payment is simulated. Notifications to teachers and parents are kept in the Notification entity, the handling of an alert by a teacher in the Teacher Alert Action entity, and the changes of a child's PIN in the Learner Pin Change entity. The Admin Action entity is the activity log, the Service Failure entity is a short diary of problems with outside services, and the Data Erasure entity records only that a deletion took place, without any name or identifier.")
    P(d, "Overall, the ERD shows how reading performance data flow from the speech assessment into the reading step, the word bank, the alerts, and the dashboards that teachers and parents use, while the records that identify a person can be deleted on request.")
    wp(d, "Data Dictionary: what changes in each table (working table; use the numbers of the new numbering map).", bold=True)
    wtable(d, ["Table", "Add these columns", "Change", "Remove"], DD_CHANGES, [1.0, 2.8, 2.2, 1.1], size=8, total_w=6.0)
    d.add_paragraph()
    wp(d, "New data dictionary tables (insert after the Notification table, in the same format as the existing ones):", bold=True)
    for lab, title, rows, after in NEW_TABLES:
        dd(d, lab, title, rows, after)
    end_marker(d, "C12")


def part_c13(d):
    meta_box(d, "C13", "UPDATED", "Chapter 3, 'Network Design' (pages 124 to 127): Figures 42 and 43 and their paragraphs. The introduction stays.",
             "The network model (Google Cloud Speech-to-Text and SMS Gateway API) and the topology (a school local area network with Android tablets, the SMS path to non-smartphone parents).",
             "No SMS and no Google Speech-to-Text. Free hosting that sleeps. The Admin on the website only.",
             "The old topology said parents without a smartphone are reached by SMS. That path does not exist now, so the digital equity claim ('no parent is excluded') must also be removed or reworded.")
    h3(d, "Network Model")
    P(d, "A network model is a conceptual framework that describes the architecture, components, and interactions within a computer network. It outlines how different devices and services communicate with each other to achieve seamless connectivity and efficient data transfer.")
    fig(d, "F03_network_model.png", "Figure 43:", "TaraBasa AI Network Model", 7.0)
    P(d, "Learners, teachers, parents, and the administrator reach TaraBasa AI through a school or home network or mobile data, and the Internet, over an encrypted HTTPS connection. The application server runs on Render, in Singapore, and serves both the website and the mobile application. The server reads and writes the TiDB Cloud database through an encrypted SQL connection. It calls four outside services over HTTPS and never lets a phone or a browser call them directly, so that the keys stay on the server: the reading service (Vosk), which scores each recording; the activity writer (Gemini), which drafts activities; the adaptive recommender, which updates each learner's profile; and the Gmail API, which sends account emails. Google sign in is an optional way for adults to sign in on the website. The system has no SMS gateway: teachers and parents see updates inside the application and the website. Because the hosting plans are free, the server and the three AI services sleep when they are not used. A page may send one harmless request from the browser to wake them, and the server waits for them before it scores a reading.")
    h3(d, "Network Topology")
    P(d, "This section presents a visual illustration of the network topology, which represents how devices and components are integrated into a computer network. The topology diagram shows the links between network nodes and helps in understanding the data flow and the protocols used for data transfer.")
    fig(d, "F04_network_topology.png", "Figure 44:", "TaraBasa AI Network Topology", 7.6)
    P(d, "The network topology of TaraBasa AI centers on a cloud application server, which hosts the website, the application programming interface, the rules, and a background worker, and which is reached through the Internet. From this server the TiDB Cloud database and the outside services are called over the Internet. On the access side, teachers and learners at the partner school connect through the school's WiFi router, with teacher laptops or phones and learner Android tablets, to the school's Internet connection. Parents and children at home connect through home WiFi or mobile data, and the administrator connects through an office or home Internet connection to the website. All traffic is carried over HTTPS. Because the system needs an Internet connection for scoring and for writing activities, a school with a weak connection may find the first reading of a session slow, and the limits of the study state this.")
    end_marker(d, "C13")


def part_c14(d):
    meta_box(d, "C14", "UPDATED", "Chapter 3, 'Development/Construction/Build Phase', the paragraph on page 127.",
             "The whole paragraph (XAMPP, Android Studio, Google Cloud Speech-to-Text, Philippine SMS gateway).",
             "The partner school name must match the decision made in C5.")
    h2(d, "Development/Construction/Build Phase")
    P(d, "During the development phase, everything required to implement the project was organized following the Agile Methodology adopted in Chapter 1. The research team coordinated with the partner school [name of school, to match Chapter 1] to confirm classroom access, learner participation, and device availability for pilot testing. Development environments were set up using Visual Studio Code for the website and the mobile application, PHP 8.4 with Laravel 13 for the server, Node.js with Expo for the mobile application, and SQLite for local development. The production system was hosted on Render with a TiDB Cloud database. Access to the outside services was arranged early in this phase: the reading service (Vosk), the activity writer (Gemini), the adaptive recommender, and the Gmail API, since each required an account and, in some cases, verification time before development could proceed. A working timeline was established for each Agile sprint, covering account management, the reading assessment, gamification, the teacher and parent screens, and the administrative tools, with testing and revisions at the end of each sprint.")
    end_marker(d, "C14")


STACK_ROWS = [
    ["• React Native 0.86 with Expo SDK 57\n• React 19 and TypeScript\n• Expo Audio (recording)\n• Expo Camera (QR scanning)\n• Expo Speech (read aloud)\n• Secure Store (sign in token)\n• Lottie animations\n• Android 7.0 or newer",
     "• Laravel Blade pages\n• HTML, JavaScript\n• Hand-written CSS (no CSS framework)\n• Lottie web player\n• Cropper.js (photo crop)\n• jsQR (QR scanning)\n• Chrome, Edge, Firefox",
     "• PHP 8.4 and Laravel 13\n• Laravel Sanctum (phone sign in)\n• Database queue and worker\n• Docker image on Render (Singapore)\n• TiDB Cloud Starter (MySQL compatible)\n• SQLite (development only)\n• Gmail API (OAuth) for email",
     "• Vosk small English model (Kaldi based) through Reading-api (FastAPI)\n• Gemini through a FastAPI activity writer\n• Adaptive recommender (FastAPI, fixed formulas)\n• Rules written in the application\n• All three on Render",
     "• iOS application\n• Filipino and Bisaya voice recognition\n• Offline mode\n• Paid hosting and a persistent file store\n• Tuning the rules with recordings of children\n• Teacher and parent messaging"],
]


def part_c15(d):
    meta_box(d, "C15", "UPDATED", "Chapter 3, 'Technology Stack Diagram' (pages 127 to 128): Table 23 and its paragraphs. Add Figure 45 (architecture picture) under the first paragraph.",
             "Kotlin, Android Studio, XAMPP, Apache, Bootstrap, RESTful API, Cloud SQL, SMS Gateway API, Google Cloud Speech-to-Text.",
             "Real stack, confirmed from the repository (composer.json, mobile/package.json, Dockerfile) on 20 October 2026; Expo SDK 57 needs Android 7 or newer (Expo documentation); Render free services sleep after 15 minutes without traffic (Render documentation); TiDB Cloud Starter free quota is 5 GiB of row storage and 50 million request units a month for each cluster (TiDB Cloud documentation).",
             "Table number becomes 31 in the new numbering.")
    h3(d, "Technology Stack Diagram")
    P(d, "The table below is the technology stack diagram, which presents the technologies of the proposed system, categorized into five sections: mobile application, website, server and database, AI services, and future development.")
    table_caption(d, "Table 31", "Technology Stack")
    table(d, ["Mobile Application", "Website", "Server and Database", "AI Services", "Future Development"], STACK_ROWS, [1.2, 1.1, 1.3, 1.3, 1.1], size=8.5)
    P(d, "TaraBasa AI is an AI-assisted mobile and web platform for adaptive reading assessment and intervention. The mobile application is built with React Native and Expo, which produce an Android application from one TypeScript program, and uses the device's microphone to record a learner's reading, the camera to scan the QR code on a learner card, and the device's own voice to read words aloud. The website is built with Laravel Blade pages, hand-written style sheets, and plain JavaScript, and serves all four types of users, with the administrator using the website only.")
    fig(d, "F02_architecture_tech_stack.png", "Figure 45:", "System Architecture and Technology Stack", 7.6)
    P(d, "The server runs PHP 8.4 and Laravel 13 in a Docker image on the Render hosting service in Singapore. Laravel serves the web pages and a JSON interface for the mobile application, which signs in with Sanctum tokens. A background worker, using a queue kept in the database, writes AI activity drafts without making a teacher wait. The data are kept in TiDB Cloud, a database compatible with MySQL, and in SQLite on a developer's computer. The speech recognition is done by Vosk, an offline toolkit built on Kaldi, through a separate Python service that compares the recognized words with the activity text; the researchers did not train a speech model. Activities are drafted by the Gemini model through a second service, and a third service keeps each learner's adaptive profile with fixed formulas. Account emails are sent through the Gmail interface. Looking ahead, future development includes an iOS application, Filipino and Bisaya voice recognition, an offline mode, paid hosting and a persistent store for photographs, and tuning the rules with recordings of children.")
    end_marker(d, "C15")


SOFT_ROWS = [
    ["Operating System", "Windows 10/11 (development); Android 7.0 or newer (mobile application); any current browser (website)"],
    ["Text Editor/IDE", "Visual Studio Code; Git and GitHub for version control"],
    ["Programming Language", "PHP 8.4, TypeScript, JavaScript, Python (the AI services)"],
    ["Web Browser", "Google Chrome, Microsoft Edge, Mozilla Firefox"],
    ["Mobile Framework", "React Native 0.86 with Expo SDK 57"],
    ["Web Framework", "Laravel 13 with Blade pages and hand-written CSS (no CSS framework)"],
    ["Database", "TiDB Cloud (MySQL compatible) for the live system; SQLite for development"],
    ["Speech Recognition Service", "Vosk (Kaldi based) through Reading-api"],
    ["Activity Writing Service", "Google Gemini through a FastAPI service"],
    ["Adaptive Recommendation Service", "FastAPI service with fixed formulas"],
    ["Email", "Gmail API (OAuth)"],
    ["Hosting", "Render (Docker), Singapore"],
    ["Automated Testing", "PHPUnit (656 tests on 20 October 2026; replace with the number on the day of submission)"],
]
HARD_ROWS = [
    ["PC (development)", "CPU", "64-bit, 2.5 GHz or faster; Intel Core i5 10th Generation or AMD Ryzen 5 3000 series or higher"],
    ["", "Memory", "At least 8 GB RAM"],
    ["", "Storage", "At least 10 GB available"],
    ["", "GPU", "Integrated or dedicated graphics"],
    ["Android device (learner, teacher, parent)", "System", "Android 7.0 or newer (the minimum of Expo SDK 57)"],
    ["", "Memory", "At least 2 GB RAM [test phone model and RAM: team to fill in]"],
    ["", "Storage", "At least 100 MB free for the application (the test build is about 44 MB)"],
    ["", "Microphone", "Required: the learner's reading is recorded with it"],
    ["", "Camera", "Optional: only to scan the QR code on a learner card; a code can be typed instead"],
    ["", "Network", "WiFi or mobile data: an Internet connection is needed to score a reading"],
    ["Server (cloud)", "Hosting", "Render web service (Docker) in Singapore; the free plan sleeps after 15 minutes without traffic, and about a minute is needed to wake it"],
    ["", "Database", "TiDB Cloud Starter (the free quota is 5 GiB of row storage and 50 million request units a month for each cluster)"],
]


def part_c16(d):
    meta_box(d, "C16", "UPDATED", "Chapter 3, 'Software Specification' (page 132): Table 24 and its paragraph.",
             "Kotlin, Android Studio, Cloud SQL, Bootstrap, XAMPP, Google Cloud Speech-to-Text, Semaphore SMS API, 'Android OS Version 8.0 and above'.",
             "Real stack (see C15). Android 7.0 is the Expo SDK 57 minimum.",
             "Table number becomes 32.")
    h3(d, "Software Specification")
    P(d, "This section presents the software that was used, including the operating system, programming languages for both the front end and the back end of the application, platforms, and the database needed to develop the entire TaraBasa AI system. It functions as a blueprint that the researchers followed to develop the program correctly.")
    table_caption(d, "Table 32", "Software Specifications")
    table(d, ["Component", "Specification"], SOFT_ROWS, [2.0, 4.0], size=11, line=1.5, bold_first_col=False)
    P(d, "Table 32 outlines the software specification for developing the TaraBasa AI platform. The researchers chose Windows for development machines and Android 7.0 or newer for the mobile application, which is the lowest version that Expo SDK 57 supports and reflects the older Android devices that are common in Philippine public elementary schools. Visual Studio Code is the main editor, with PHP 8.4, TypeScript, and JavaScript for the website, the server, and the mobile application, and Python for the AI services. Supported browsers are Google Chrome, Microsoft Edge, and Mozilla Firefox. TiDB Cloud serves as the live database and SQLite is used for development. The Vosk speech recognition service, the Gemini activity writing service, the adaptive recommendation service, and the Gmail interface complete the specification, supporting the platform's AI assessment and its account emails. The system has no SMS gateway.")
    end_marker(d, "C16")


def part_c17(d):
    meta_box(d, "C17", "UPDATED", "Chapter 3, 'Hardware Specification' (pages 133 to 134): Table 25 and its paragraph.",
             "'Camera 12MP or higher', 'Octa Core ... 2.0 GHz', '4 GB RAM', '6 GB available space' for the Android device.",
             "A camera is only needed to scan a card and its quality does not matter; a microphone is required. The Android numbers were not tested; fill in the real test phone in the bracket.",
             "Table number becomes 33. The server row is new: it matters because the free plan sleeps and limits speed.")
    h3(d, "Hardware Specification")
    P(d, "This section provides a description of the hardware requirements needed for the system. It outlines the specifications, requirements, and limitations of each hardware component necessary to support the development, deployment, and operation of the proposed system.")
    table_caption(d, "Table 33", "Hardware Specifications")
    table(d, ["Device", "Component", "Specification"], HARD_ROWS, [1.4, 0.9, 3.7], size=8.5)
    P(d, "Table 33 outlines the hardware specifications for TaraBasa AI. For development, a personal computer with a 64-bit processor of 2.5 GHz or faster, at least 8 GB of memory, 10 GB of free storage, and graphics is used. For learners, teachers, and parents, an Android device with Android 7.0 or newer and at least 2 GB of memory is needed, with a microphone to record the learner's reading. A camera is optional and is used only to scan the QR code on a learner card. Because scoring and activity writing happen on the server, the device does not need a strong processor, but it needs WiFi or mobile data. The server runs on a free hosting plan that sleeps when idle, so the first request after a quiet period takes about a minute, and the live database is limited by the free quota of its plan. A steady classroom use would need paid plans.")
    end_marker(d, "C17")


def part_c18(d):
    meta_box(d, "C18", "UPDATED", "Chapter 3, 'List of Modules' (pages 134 to 139): Tables 26.1 to 26.5 become Tables 34.1 and following.",
             "The nine-module list and its totals ('Total Number of Modules 15').",
             "Same format as version 29, rebuilt from the same list of functions as the FDD. A System column is added because modules 9 and 10 contain functions the system performs itself.",
             "Programmer names: modules 1 to 9 keep the owners in version 29; modules 10 and 11 are for the team to assign.")
    h3(d, "List of Modules")
    P(d, "The modules and features required to run the TaraBasa AI application efficiently are outlined in this section. Different programmers are given the modules to build the different features.")
    owners = {1: "Acuyan", 2: "Acuyan", 3: "Acuyan", 4: "Morbos", 5: "Rojas", 6: "Morbos", 7: "Morbos", 8: "Acuyan", 9: "Pulga", 10: "[assign]", 11: "[assign]"}
    cols = ["Learner", "Teacher", "Parent", "Admin", "System"]
    rows = []
    totals = {c: 0 for c in cols}
    for m in MODULES:
        rows.append([owners[m["no"]], f"MODULE {m['no']}: {m['name']}", "", "", "", "", ""])
        used = set()
        for ai, (actor, fns) in enumerate(m["actors"], start=1):
            used.add(actor)
            for fi, (name, st) in enumerate(fns, start=1):
                row = ["", f"{m['no']}.{ai}.{fi} {name}"] + ["*" if c == actor else "" for c in cols]
                rows.append(row)
        pts = ["", "No. of Points (1 point per module per user)"] + [("1" if c in used else "") for c in cols]
        for c in cols:
            if c in used:
                totals[c] += 1
        rows.append(pts)
    rows.append(["", "Total number of modules per user"] + [str(totals[c]) for c in cols])
    rows.append(["", "Total Number of Modules", str(sum(totals.values())), "", "", "", ""])
    table_caption(d, "Table 34.1", "List of Modules")
    table(d, ["Programmer", "Module / Functionality", "Learner", "Teacher", "Parent", "Admin", "System"], rows, [0.8, 3.0, 0.5, 0.5, 0.5, 0.5, 0.5], size=8, col_align=["left", "left", "center", "center", "center", "center", "center"])
    end_marker(d, "C18")


def part_c19(d):
    meta_box(d, "C19", "NEW", "Chapter 3, after 'Program Specification' and 'List of Modules' (page 139), as a new H3 section before the References.",
             "Nothing. Version 29 has no testing or evaluation evidence.",
             "The repository's automated tests (PHPUnit) and the live-service checks recorded in the project notes.",
             "Numbers must be refreshed on the day of submission: run 'php artisan test' and replace 656. Replace the bracketed lines with the results of the user acceptance test and the real phone test (planned for 21 October 2026).")
    h3(d, "System Testing")
    P(d, "TaraBasa AI was tested by automated tests, by walking through each role in a browser, and by sending real recordings to the live scoring service. On 20 October 2026 the automated test suite, which uses PHPUnit, contained 656 tests that passed. They check sign up and sign in for each role, the rules for confirming an email address, the one-grade rule for classes and the check before a learner is added, the first reading check and its staircase, the rule that moves a reading step slowly, the cleaning of speech scores, the alerts, the practice games, the Open Repository, the deletion of a child or an account, the Administrator's screens, the security headers and rate limits, and the interface that the mobile application uses. Every role's pages were also opened in a browser at phone, tablet, and laptop widths, and checked for sideways scrolling, text that was cut off, and buttons that were too small to tap.")
    P(d, "The reading rules were checked with real recordings: synthesized English voices read the first reading check texts, the games' words and sentences, and the syllable method words to the live reading service, and the texts were reworded when the recognizer mixed words up. This shows that the pipeline works from a recording to a result, and it does not show how well the system works for children. The recognizer is built for adult speech, a synthesized voice is not a child, and a row of single short words is the hardest case for it. For this reason the numerical settings in Table 7 are provisional.")
    P(d, "[Insert here the method and results of the user acceptance test with teachers and parents at the partner school: number of participants, tasks, and the percentage who completed each task. Insert also the result of testing the Android application on a real phone, naming the phone model and the Android version.]", indent=True)
    P(d, "The following were not tested and are limits of the study: a real child reading into a real microphone, a whole class recording at the same time, and the effect of the system on learners' reading proficiency over time.")
    end_marker(d, "C19")


def part_c20(d):
    meta_box(d, "C20", "NEW", "Chapter 3, after 'System Testing', as a new H3 section 'Data Privacy and Ethical Considerations'; also one sentence in Chapter 1 'Scope and Limitations' is not needed.",
             "Nothing. Version 29 has no section on data privacy, although the system holds the data of children.",
             "Republic Act No. 10173 (Data Privacy Act of 2012), Section 16(e) on the right to remove or destroy personal data; the live site's Privacy Policy, Terms of Use, Cookie Policy, Refund Policy and Parent and Guardian Consent notice, dated 21 October 2026.",
             "The statements below describe what the system does today. If the system changes, change this text and the Privacy page together. The Act's exact wording was found by search and must be read in the original before it is quoted.")
    h3(d, "Data Privacy and Ethical Considerations")
    P(d, "TaraBasa AI holds personal data of adults and of children, so it was designed to keep as little as possible. A parent, not the child, creates the child's profile and chooses what to enter; the photograph is optional. The system keeps the child's first and last name, grade, a character or photograph, and a 4-digit PIN that is stored scrambled (hashed), together with the reading results: scores, the words missed, points, badges, and the reading level. A child's recording is sent to the scoring service only to be scored and is not saved; only the result is kept. Passwords and PINs are stored hashed, the connections use HTTPS, and the system shows no advertising and uses no analytics.")
    P(d, "The Data Privacy Act of 2012 (Republic Act No. 10173) gives a person the right to have personal data removed or destroyed. TaraBasa AI lets a parent remove a child's profile and lets a parent or a teacher delete their own account, without anyone's approval, on the website and in the mobile application. When a parent is the only guardian of a child, the child's profile and every reading record, badge, word, and alert are deleted; when another guardian is linked, only the parent who asked is removed. A teacher's account takes with it the teacher's classes and shared listings, while the learners stay with their parents. The administrator can delete an account for a person who cannot sign in, with a reason that is written in the activity log, and the log keeps the reason but not the person's name. The system keeps one line that a deletion happened, with no name or identifier.")
    P(d, "Because a child cannot give consent alone, and the Act treats a person's age and information about education as sensitive personal information, a child's profile is created only after the parent or legal guardian ticks a consent box that links to a Parent and Guardian Consent notice. The photograph has its own separate, optional box, and a second guardian who links to a child confirms that they are the child's parent or guardian. Adults tick a box to accept the Terms of Use and to confirm they have read the Privacy Policy when they register. Each tick is recorded with the version of the documents and the date, and the record is deleted together with the child or the account. The website publishes a Privacy Policy, Terms of Use, Cookie Policy, Refund Policy, and the consent notice. It sets only two cookies that are needed to keep a person signed in, loads no analytics, advertising, or font service from other companies, and takes no payments, because the prices shown on shared activities are only simulated. These documents were written by the project team, not by a lawyer, and a list of the points still open (registration with the National Privacy Commission, a named data protection contact, and written agreements with the service providers) is kept with the project files.")
    P(d, "Teachers see the name, reading level, and progress of the learners in their classes, and parents see their own children. The administrator manages accounts but does not read a child's readings. Outside services receive only what they need: the scoring service receives the recording and the words of the activity; the recommendation service receives scores and numbers, not the child's name; and the activity writer receives what the teacher typed and, for a class, a short summary without any name or learner code. [Writer: add one sentence about the surveys and interviews of this study, confirming that respondents took part voluntarily and that answers are reported without names, and cite the consent statement in the transmittal letter, Appendix A. The team must confirm this; it is not recorded in the project files.]")
    end_marker(d, "C20")
