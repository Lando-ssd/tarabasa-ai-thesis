# TaraBasa AI: defense study kit (written 23 October 2026)

This kit is for you to **understand** the system, not to memorize it. A panel can ask anything; if you know the story and the basis, you can
answer the question nobody prepared you for. Every number below was read from the live app's own settings on 23 October 2026, and every
claim says how far it was checked.

## The files, in the order to read them

| # | File | What it gives you | Read it |
|---|---|---|---|
| 1 | `01-HOOK-AND-STORYLINE-SCRIPT.md` | The spoken script: a hook, the problem, a storyline from sign up to alerts, the closing. Word for word. | First, twice. Read it aloud. |
| 2 | `02-WORKFLOW-AND-TECH.md` | What happens behind each screen: where the data goes, the email, the database, the services. The tech stack and why. Web versus phone app. Network model. | Second. |
| 3 | `03-BASIS-OF-EVERY-DECISION.md` | Why each thing exists and what it is based on: Phil-IRI, the reading path, the levels, activity writing, Easy/Medium/Hard, alerts, patterns, games, word bank, UI and UX. | Third. This is the adviser's favourite. |
| 4 | `04-BUSINESS-MODEL-AND-PROFIT.md` | The Business Model Canvas, the revenue streams, the costs, and an honest profit calculation. | Fourth. |
| 5 | `05-QUESTIONS-AND-ANSWERS.md` | The 10 standard defense questions (translated) plus about 70 practice questions, each with a model answer. | Fifth, then cover the answers and test yourself. |
| 6 | `06-RUN-AND-TROUBLESHOOT.md` | How to run the website and the app on your computer step by step, and where to look when something breaks (Render, TiDB, GitHub, phone). | Before the demo. |
| 7 | `07-GLOSSARY-AND-NUMBERS.md` | What Phil-IRI, DepEd, MATATAG and every technical word means, and the one-page list of numbers. | Keep open while you study. |

The easiest way to study is the web page `TaraBasa-Defense-Study-Kit.html` (in this same folder). Double click it to open it in a browser. It has **one tab for each file above**
(tab 0 to tab 7), a menu on the left of every tab, drawn diagrams (the sign-up sequence, the network, the reading ladder, the business canvas, the break-even chart),
and a search box in the Questions and Words tabs. It also works on a phone. For paper: Ctrl + P, then Save as PDF (every tab prints in order).
If you change a `.md` file, run `python build_kit_html.py` in this folder to rebuild the page.

## How to read the words in square brackets

Every basis in this kit carries one of three labels. Learn them, because they are how you stay honest in front of a panel:

- **[CURRICULUM]**: it comes from a DepEd document (MATATAG, Phil-IRI, DepEd Memorandum 173). Say "DepEd's" and name the document.
- **[RESEARCH]**: it comes from a published study. The study was found by search and its title and authors were confirmed, but the full paper was not always read.
  The manuscript's reference pack says which ones are VERIFIED, LISTED or RECALLED. Say "research found that..." and never invent a number from it.
- **[TEAM CHOICE]**: the team decided this number or rule. There is no source for it. Say so plainly: "this is our own setting, kept in one settings file so it can
  be changed after real children use it." A panel respects this far more than a made-up citation.

## Three rules for the whole defense

1. **Never say the AI was trained.** No model was trained, because there is no collection of children's recordings. The system is a speech recognition
   service (Vosk), an AI language model that writes activity drafts (Gemini), and **written rules and formulas**. Say it before they ask.
2. **Never say it was tested with real children.** It was tested with automated tests (775 passing), with computer voices, and in live use by the team.
   Real-child testing is the next step and the first limitation. Fill in the results of your teacher survey and user acceptance test **only** with the real
   numbers from your appendices.
3. **Never say DepEd approved it.** It follows DepEd documents. It is not made or endorsed by the Department of Education (the home page says so).

## The 12 sentences to know by heart

1. TaraBasa AI is a reading practice and monitoring system for Grade 1 to 3 learners, with a phone app and a website for learners, teachers, parents and an administrator.
2. A child reads aloud; a speech recognition service writes down the words; our rules compare them with the activity and decide what the child needs next.
3. Teachers see results in Phil-IRI words (Independent, Instructional, Frustration). Children never see those words, only four friendly steps.
4. A child's reading step does not depend on grade: a Grade 3 child who cannot read yet starts on letters.
5. The first reading check has at most 3 texts: 90 percent or more moves up, under 70 moves down, 70 to 89 stops; letters move up at 80.
6. After that, a child moves one step up after 3 strong readings (90 percent or more) from at least 2 activities, and one step down after 2 weak readings (under 70) in a row.
7. Activities are written by an AI language model, but a teacher must approve each one and decides its level.
8. Easy, Medium and Hard are this app's levels (word familiarity and length), not DepEd scores, and the teacher decides.
9. Alerts always show their evidence and never act by themselves.
10. A class holds one grade only; the school decides which grade a teacher teaches.
11. A recording is deleted as soon as it is scored; there are no ads and no outside tracking.
12. Everything adaptive is rule based and explainable; the numbers are the team's settings and are the first thing we will check with real children.

## A 3-day plan

- **Day 1**: read file 1 aloud twice, then file 2. Draw the network picture (file 2, section 6) from memory on paper.
- **Day 2**: read file 3 slowly. For each decision say out loud: what it is, why it exists, what it is based on, how sure we are.
- **Day 3**: files 4 and 5. Cover the answers and say your own, then compare. Run the demo once from file 6 with the keep-awake button on.

## When you do not know the answer

Use this shape, it works for any question: **(1) what we did, (2) what the data or the source shows, (3) what we did not measure, (4) what we would do next.**

Example: "Did it improve reading scores?" Answer: "We built and tested the system and it works as designed (775 automated tests). We did not run a
long-term study, so we cannot claim reading scores improved. The next step is a classroom pilot that compares children's Phil-IRI results before and after."
