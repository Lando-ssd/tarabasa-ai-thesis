"""Insert-ready text, Part A (front matter and Chapter 1) and Part B (Chapter 2)."""
import re
from docx_kit import *


def smart(s):
    """Typographic quotes like the manuscript (apostrophes and double quotes)."""
    s = re.sub(r"(?<=[A-Za-z0-9.,)])'", "’", s)
    s = s.replace("'", "‘")
    out, open_q = [], True
    for ch in s:
        if ch == '"':
            out.append("“" if open_q else "”")
            open_q = not open_q
        else:
            out.append(ch)
    return "".join(out)


def P(d, text, **kw):
    return body(d, smart(text), **kw)


def part_a(d):
    h1(d, "PART A. FRONT MATTER AND CHAPTER 1")
    # ---------------------------------------------------------------- A1
    meta_box(d, "A1", "FIX", "Cover page, line 'A Capstone Proposal' and the date line.", "'A Capstone Proposal' and 'July 2026'.",
             note="The document is the final manuscript, so it is a Capstone Project, not a proposal. Put the real month and year of submission.")
    p = d.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.first_line_indent = Inches(0)
    r = p.add_run("A Capstone Project")
    from docx_kit import _set_font
    _set_font(r, TNR, 12)
    end_marker(d, "A1")

    # ---------------------------------------------------------------- A2
    meta_box(d, "A2", "UPDATED", "Chapter 1, 'Scope and Limitations of the Study'. Replace the second paragraph (starts 'The TaraBasa AI platform integrates ...', pages 5 to 6) and the last two paragraphs before 'Despite these limitations' (starts 'The study is limited ...' and 'The application requires a stable internet connection ...'). Keep the first paragraph, after deciding the school name (see C5).",
             "The three paragraphs named above.",
             "Real system as of 20 October 2026: the phone app serves Learner, Teacher and Parent; the website serves all four; the Admin uses the website only; no SMS; rule-based support.",
             "The study's school is written two ways in version 29 (Cebu City here, Suba Elementary School in Liloan, Cebu in the Validation Board). Choose one and use it everywhere.")
    h3(d, "Scope and Limitations of the Study")
    P(d, "The TaraBasa AI platform integrates AI-assisted speech recognition for oral reading assessment, rule-based adaptive support that personalizes reading activities according to learners' reading performance, gamified learning to enhance learner engagement and motivation, and analytics to assist teachers and parents in monitoring learners' reading development. The system consists of an Android mobile application for learners, teachers, and parents, and a website that serves all four types of users: learners, teachers, parents, and system administrators. The administrator uses the website only. The mobile application and the website use the same accounts and the same database, so a reading completed on one is visible on the other. Learners read aloud, play read-aloud practice games, and see their reading path and badges. Teachers create classes, generate and review reading activities, assign them to learners, classes, or reading groups, and monitor learners through analytics and alerts. Parents create and manage their children's profiles, view progress and tips for practicing at home, and unlock additional activities from the Open Repository. Administrators approve teacher accounts, manage accounts, and monitor the health of the system.")
    P(d, "The study is limited to the development and evaluation of TaraBasa AI within its current implementation in Cebu and does not examine its long-term effects on learners' English reading proficiency, academic achievement, reading motivation, or overall literacy development. Likewise, the study does not evaluate large-scale or nationwide institutional adoption, integration with existing Department of Education information systems and reading programs, or external factors such as digital literacy, device availability, internet connectivity, and home learning environments. While these factors may influence the effectiveness and future scalability of the system, they are beyond the scope of the present study.")
    P(d, "The application requires an internet connection to score a reading, to write activities, and to send account emails. The speech recognition component supports English oral reading assessment and may produce inaccurate results because of variations in learners' pronunciation, speech clarity, background noise, microphone quality, internet connectivity, or limitations of the speech recognition model, which is built for adult speech. To reduce unfair marking, the system accepts words that sound the same, leaves words it heard only faintly out of the score, and allows a recording that could not be heard to be repeated. Assessment results are intended to assist teachers and should not replace professional judgment in evaluating learners' reading performance.")
    P(d, "The adaptive support in TaraBasa AI is rule-based. The reading step, the recommended activity, the mistake patterns, and the alerts are produced by written rules and fixed formulas, and the researchers did not train a machine learning model. The numerical settings of these rules were chosen by the researchers from the literature and from testing with synthesized voices; they have not yet been validated with recordings of Filipino children. The services for scoring, activity writing, and recommendation run on free hosting plans that sleep when they are idle, so the first request after a quiet period can take about a minute. Adaptive reading activities are limited to the content available in the system: the texts of the first reading check, which were written by the researchers, and the activities that an AI service drafted and a teacher approved. The system does not send text messages (SMS); teachers and parents receive updates inside the application and the website.")
    end_marker(d, "A2")

    # ---------------------------------------------------------------- A3
    meta_box(d, "A3", "FIX", "Chapter 1, 'Flow of the Study', the sentence under the heading (page 7) and the paragraph beginning 'The next phase focuses on determining the algorithms ...' (page 9). Figure 1 stays.",
             "Page 7: 'The procedure involves identifying hand gestures and interpreting their meanings by matching them to the corresponding universal words, and vice versa ...' (left over from another study). Page 9: second paragraph.",
             note="The hand gesture sentence describes a different project and must be removed.")
    h3(d, "Flow of the Study")
    P(d, "This diagram shows the study's inputs, processes, and outputs. The inputs are the data gathered on the English reading difficulties and intervention practices of Grade 1 to Grade 3 learners, the review of related literature, and the curriculum references (the MATATAG Curriculum and the Philippine Informal Reading Inventory). The process follows the Agile methodology to design, develop, test, and review the speech recognition-based assessment, the rule-based adaptive support, the gamified activities, and the monitoring tools. The output is TaraBasa AI, an adaptive reading intervention system available as a mobile application and a website.")
    P(d, "The next phase focuses on determining the mechanisms that were integrated into the system. A speech recognition service was identified to assess learners' English oral reading by measuring word accuracy, reading speed, and a voice score. An adaptive support mechanism was likewise determined: a recommendation service that uses fixed formulas, combined with written rules that move a learner's reading step slowly, find repeated mistakes, and suggest activities. These ensure that learners receive reading activities that match their assessed performance.")
    end_marker(d, "A3")

    # ---------------------------------------------------------------- A4
    meta_box(d, "A4", "NEW", "Chapter 1, 'Definition of Terms' (pages 9 to 10). Add these terms in alphabetical order with the existing ones. Use the same pattern: the term in bold, then 'It refers to ...'.",
             "Nothing; these terms are new.",
             "Terms the manuscript uses but never defines (Phil-IRI, MATATAG, Open Repository, Learner Code and others).")
    h3(d, "Definition of Terms (additions)")
    terms = [
        ("Adaptive Recommender.", "It refers to the outside service used by TaraBasa AI that keeps a numerical profile of each learner's reading skill and, by fixed formulas, suggests the next kind of reading activity and its level."),
        ("Alerts.", "It refers to the messages that TaraBasa AI shows to teachers and parents when a learner's recent readings show a need for support, readiness to move up, a need to start with letters, a long quiet period, or a move to a higher level."),
        ("First Reading Check.", "It refers to the short oral reading check, of at most three items, that a learner completes the first time the learner signs in. It starts at the step suggested by the Parent's profile of the child and ends with a reading step."),
        ("Learner Code.", "It refers to the unique code, such as TB26-48293, that TaraBasa AI makes for each learner. With the learner's 4-digit PIN it is used to sign in. The last character is a check digit that lets the system notice a mistyped code."),
        ("MATATAG Curriculum.", "It refers to the Department of Education's curriculum for basic education. TaraBasa AI uses its learning competency codes to describe what each reading activity practices."),
        ("Mistake Pattern.", "It refers to a kind of reading mistake, such as missing word endings or letter blends, that a learner repeats across several readings and that the system finds by rules from the words the learner missed."),
        ("Open Repository.", "It refers to the shared library in which teachers list approved activities, free or paid, and from which parents unlock activities for their children."),
        ("Personal Word Bank.", "It refers to the list of words a learner missed. A word is 'struggling' when it is first missed, 'improving' after it is read correctly, and 'mastered' after it is read correctly three times."),
        ("Philippine Informal Reading Inventory (Phil-IRI).", "It refers to the Department of Education's tool for finding learners' reading levels. Its levels (Independent, Instructional, and Frustration) are based on oral reading accuracy."),
        ("Reading Group.", "It refers to one of the three groups into which TaraBasa AI divides a class by reading level, so that a teacher can assign an activity to the group that can read it."),
        ("Reading Step.", "It refers to a learner's place on a four-step reading path: Letter Explorer, Word Builder, Sentence Reader, and Story Reader. The step moves slowly, as explained in the Adaptive Reading Support Design."),
        ("Rule-Based Support.", "It refers to decisions made by written rules and fixed formulas, for example moving a reading step, as opposed to decisions made by a trained machine learning model."),
    ]
    for lead, t in terms:
        P(d, t, bold_lead=lead)
    end_marker(d, "A4")

    meta_box(d, "A4b", "UPDATED", "Chapter 1, 'Definition of Terms'. Replace these four existing definitions.",
             "'Gamified Learning', 'Parent Notification System', 'System Administrator' and 'Learners'.",
             "'Parent Notification System' said TaraBasa informs parents automatically; it now means in-app alerts. The Administrator no longer manages reading materials or reports.")
    P(d, "It refers to the integration of game elements such as points, badges, levels, a weekly goal, and read-aloud practice games within TaraBasa AI to encourage learner engagement and sustained participation in reading activities.", bold_lead="Gamified Learning.")
    P(d, "It refers to the elementary pupils who use TaraBasa AI as the primary beneficiaries of the adaptive reading intervention system. A learner does not register alone: a parent creates the learner's profile, and the learner signs in with a Learner Code and a 4-digit PIN.", bold_lead="Learners.")
    P(d, "It refers to the feature of TaraBasa AI that shows parents and teachers, inside the mobile application and the website, a learner's reading results, assigned activities, and alerts about the learner's progress.", bold_lead="Alerts and Notifications System.")
    P(d, "It refers to the authorized user who approves teacher accounts and the grades teachers handle, manages accounts, monitors the health of the system's services, and reads the activity log of administrative actions within TaraBasa AI.", bold_lead="System Administrator.")
    end_marker(d, "A4b")


def part_b(d):
    h1(d, "PART B. CHAPTER 2: REVIEW OF RELATED LITERATURE AND STUDIES")
    # ---------------------------------------------------------------- B1
    meta_box(d, "B1", "UPDATED", "Chapter 2, 'Comparative Matrix', the two paragraphs after Table 1.3 (pages 25 to 26). Replace only the second paragraph ('Despite these similarities ...'). The first paragraph stays.",
             "The paragraph that says TaraBasa AI is 'the only system among those reviewed that provides parent monitoring with SMS notifications' and that it has 'AI intervention recommendations'.",
             "SMS is not built; the 'AI recommendations' are produced by rules and a formula service, which the text should say.")
    P(d, "Despite these similarities, TaraBasa AI offers several features that distinguish it from the reviewed systems. Unlike the existing applications, TaraBasa AI combines a recommendation service with written rules to suggest reading activities and to alert teachers when a learner needs support, is ready to move up, or needs to start with letters. The suggestions come from the learner's recent readings and are explained to the teacher, who decides what to assign. It also includes a Personal Word Bank that records the words a learner missed and follows each word until it is read correctly several times, so that the practice games can return to the learner's own difficult words. Furthermore, among the five systems reviewed, TaraBasa AI is the only one that gives parents their own view of the child's progress, with the reading level in plain words, tips for practicing at home, and in-app alerts, and that lets parents unlock additional activities made by teachers. By combining AI-assisted reading assessment, speech recognition, adaptive learning, rule-based recommendations, gamified learning, teacher and parent monitoring, learner analytics, and a personal word bank within a single platform, TaraBasa AI delivers a more comprehensive and practical reading intervention system for Philippine public elementary schools than the existing applications.")
    end_marker(d, "B1")

    # ---------------------------------------------------------------- B2
    meta_box(d, "B2", "UPDATED", "Chapter 2, Tables 1.1 to 1.3, Table 2, and the paragraph after Table 2 (pages 23 to 28).",
             "Words that name features TaraBasa does not have: SMS, 'AI pronunciation coaching', 'AI Reading Intervention Planner', 'AI Intervention Identification'.",
             "Table 2 compares features: every claim about TaraBasa must be something the system does.",
             "The related systems' own cells (Google Read Along, Lexia, Raz-Kids, Ello, Amira) stay as they are.")
    wp(d, "Find and replace in the tables (working note, not manuscript text):", bold=True)
    table(d, ["Where", "Replace this", "With this"],
          [["Tables 1.1 to 1.3, 'Limitations' column of each system", "No parent monitoring or SMS notifications.", "No parent monitoring or in-app alerts."],
           ["Table 2, row label", "Parent monitoring / SMS notification", "Parent monitoring and in-app alerts"],
           ["Table 2, row label", "AI Intervention recommendations", "Rule-based intervention suggestions"],
           ["Table 2, row label", "Personal Word Bank", "Personal Word Bank (missed words followed to mastery)"],
           ["Table 2, row label", "AI-powered reading assessment", "AI-assisted reading assessment"]],
          [1.6, 2.2, 2.2], size=9, font=SANS)
    d.add_paragraph()
    P(d, "In contrast, TaraBasa AI uniquely integrates AI-assisted oral reading assessment with speech recognition, word-by-word feedback, adaptive reading steps and recommendations, rule-based intervention suggestions for teachers, a Personal Word Bank, gamified read-aloud activities, teacher and parent dashboards with in-app alerts, learner progress analytics, and alignment with the MATATAG Curriculum and the Phil-IRI within a unified platform. By combining these features into one system, TaraBasa AI provides a more comprehensive, localized, and practical solution for supporting reading intervention among Grade 1 to Grade 3 learners in Philippine public elementary schools.")
    P(d, "(This paragraph replaces the last two sentences of the paragraph after Table 2, from 'In contrast, TaraBasa AI uniquely integrates ...' to the end.)", italic=True, indent=False)
    end_marker(d, "B2")

    # ---------------------------------------------------------------- B3
    meta_box(d, "B3", "NEW", "Chapter 2, 'Related Literature', after the paragraph that ends 'a meaningful advantage over the manual tracking Phil-IRI currently requires' (page 19) and before 'Several AI-supported reading platforms currently demonstrate ...'. No new heading.",
             "Nothing; these five paragraphs are new.",
             "Gives the literature basis for choices the system already makes: the speech engine, the speed score, the AI writer, tap-only controls, the syllable method.",
             "Each citation is in 04_New_References_and_Basis.docx with a verification status. The sources marked 'found by search' must be opened and read by the writer before the paragraph is kept.")
    P(d, "The speech recognition used by TaraBasa AI is Vosk, an offline recognition toolkit that is built on Kaldi, an open-source speech recognition toolkit introduced by Povey et al. (2011). The researchers used a small English model to turn a learner's recording into words with timing and confidence values, and the system compares these words with the activity text to compute accuracy and words correct per minute. Recognition systems are usually built and tested on adult speech, and Potamianos and Narayanan (2003) reported that children's speech is harder to recognize because it differs acoustically from adult speech and varies with the child's age. For this reason TaraBasa AI does not treat the recognizer's output as final: it accepts words that sound the same, leaves faintly heard words out of the score, allows a recording that could not be heard to be repeated, and presents the result as support for the teacher's judgment.")
    P(d, "Reading speed is reported as words correct per minute. Hasbrouck and Tindal (2017) published oral reading fluency norms for Grades 1 to 6 in the United States. The speed score that TaraBasa AI receives from its reading service is computed from one target range of words per minute and not from these norms, so it is not adjusted for each grade or for learners who read English as a second language. For this reason the reading step moves on word accuracy alone, while the speed and voice scores contribute only to the weighted profile score that the recommendation service keeps, and the limit is stated openly in this study.")
    P(d, "The reading activities are drafted by a generative language model, Gemini (Gemini Team, 2023), which is reached through a separate service. The model writes drafts at the grade, skill, and level a teacher asks for, and nothing reaches a learner until the teacher edits it, places it in a level, or rejects it. The teacher, not the model, decides the level of each activity, and the system adds a length check so that an activity is not assigned to children who could not read it.")
    P(d, "Interface choices for Grade 1 to Grade 3 learners follow research on young children's use of touchscreens. In a study of 90 children aged 2 to 8 years, Yadav et al. (2020) found that most children aged 7 to 8 could tap, but fewer could swipe and fewer still could drag and drop. TaraBasa AI therefore uses taps for every learner action, including the syllable game, in which the parts of a word are joined by tapping them in order. Hirsh-Pasek et al. (2015) argued that learning applications support learning when children are mentally active, engaged, and given meaningful experiences, and that tapping alone does not make an application educational. For this reason, in each practice game the child has to read aloud, the game answers what the child reads, and the games never change the child's points or reading step.")
    P(d, "The syllable game and the practice of longer words follow the syllable method taught in Philippine classrooms, in which learners first split a word into syllables, then blend the parts, and only then read the word in a sentence. The MATATAG guide for Grade 1 Reading and Literacy lists competencies for segmenting a word of two or three syllables and for sounding out words accurately (Department of Education, 2024a), and it asks for explicit instruction that gradually hands responsibility to the learner. Earlier studies found that syllables are easier for young children to separate than single sounds (Liberman et al., 1974) and that repeated oral reading improves fluency (Therrien, 2004).")
    end_marker(d, "B3")
