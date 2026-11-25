# Builds "TaraBasa AI: Defense Script and Study Guide" (HTML then PDF) from the c_*.py content files.
# Run:  python build_script_pdf.py        (needs Microsoft Edge and PyMuPDF; makes TaraBasa-AI-Defense-Script-and-Study-Guide.pdf)
import html, re, os, subprocess, sys, time, json
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import c_script as S, c_demo as D, c_how as H, c_qa as Q, c_study as T, c_extra as X

HERE = os.path.dirname(os.path.abspath(__file__))
OUT_HTML = os.path.join(HERE, "TaraBasa-AI-Defense-Script-and-Study-Guide.html")
OUT_PDF = os.path.join(HERE, "TaraBasa-AI-Defense-Script-and-Study-Guide.pdf")
EDGE = r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe"
FONTS = "../../../public/fonts"
TITLE = "TaraBasa AI: Defense Script and Study Guide"
DATE = "24 November 2026"

# ---------- tiny markup ----------
def md(s):
    s = html.escape(str(s), quote=False)
    s = re.sub(r"`([^`]+)`", r"<code>\1</code>", s)
    s = re.sub(r"\*\*(.+?)\*\*", r"<b>\1</b>", s)
    s = re.sub(r"(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])", r"<i>\1</i>", s)
    s = s.replace("<b>FILL", '<b class="fill">FILL').replace("<b>CHECK", '<b class="chk">CHECK')
    return s

HEADS = []   # (level, id, text)
def head(level, text, extra=""):
    hid = "h%d" % (len(HEADS) + 1)
    HEADS.append((level, hid, text))
    return '<h%d id="%s" %s><span class="mk">§%s§</span>%s</h%d>' % (level, hid, extra, hid, md(text), level)

def chips(*items):
    return '<div class="chips">' + "".join('<span class="chip %s">%s</span>' % (c, md(t)) for c, t in items) + "</div>"

def ul(items, cls=""):
    return '<ul class="%s">' % cls + "".join("<li>%s</li>" % md(i) for i in items) + "</ul>"

def ol(items):
    return "<ol>" + "".join("<li>%s</li>" % md(i) for i in items) + "</ol>"

def table(header, rows, cls=""):
    h = "<thead><tr>" + "".join("<th>%s</th>" % md(c) for c in header) + "</tr></thead>"
    b = "<tbody>" + "".join("<tr>" + "".join("<td>%s</td>" % md(c) for c in r) + "</tr>" for r in rows) + "</tbody>"
    return '<table class="%s">%s%s</table>' % (cls, h, b)

def qa_block(q, a, num=None):
    n = '<span class="qn">%s</span>' % num if num else ""
    return '<div class="qa"><div class="q">%s%s</div><div class="a">%s</div></div>' % (n, md(q), md(a))

def part_page(num, title, sub):
    return '<section class="part"><div class="partnum">PART %s</div>%s<p class="partsub">%s</p></section>' % (num, head(1, "Part %s. %s" % (num, title)), md(sub))

# ---------- blocks from c_how ----------
def render_block(b):
    t = b[0]
    if t == "h3": return head(3, b[1])
    if t == "p": return "<p>%s</p>" % md(b[1])
    if t == "say": return '<div class="onesentence"><span>Say it in one sentence</span>%s</div>' % md(b[1])
    if t == "ul": return ul(b[1])
    if t == "ol": return ol(b[1])
    if t == "table": return table(b[1], b[2])
    if t == "svg": return '<div class="fig">%s</div>' % b[1]
    if t == "note": return '<div class="callout note">%s</div>' % md(b[1])
    if t == "warn": return '<div class="callout warn">%s</div>' % md(b[1])
    if t == "ask": return '<div class="beready"><div class="brt">Be ready to be asked</div>' + "".join(qa_block(q, a) for q, a in b[1]) + "</div>"
    raise ValueError(t)

# ---------- parts ----------
def cover():
    return """
<section class="cover">
 <div class="cv-top">TaraBasa AI</div>
 <h1 class="cv-title">Oral Defense<br>Script and Study Guide</h1>
 <p class="cv-sub">Capstone 42 &middot; University of Cebu &ndash; Banilad &middot; College of Computer Studies</p>
 <div class="cv-box">
  <p><b>For the 19-slide deck</b> &ldquo;TaraBasa-AI-Oral-Defense&rdquo;</p>
  <p>The spoken script for every slide, the 40-minute demo script, how the system works, the panel questions with answers, the numbers, the paper-versus-system answers, a card for each member, and the checklists for the day.</p>
 </div>
 <div class="cv-team">Tyron S. Pulga &middot; Aaron A. Acuyan &middot; John Kenneth L. Rojas &middot; John Benedict R. Morbos<br>Adviser: Mr. Jecris G. Sabanal</div>
 <div class="cv-foot">%s &middot; Version for the team. Roles are suggested and must be confirmed. Numbers were read from the running system on 21 to 22 November 2026.</div>
</section>""" % DATE

def intro():
    h = ['<section class="front">', head(1, "How to use this guide")]
    h.append("<p>This guide is for you to <b>understand</b> the system and to <b>say it well</b>, not to memorize it. A panel can ask anything. If you know the story and the basis of each decision, you can answer the question nobody prepared you for.</p>")
    h.append(table(["Part", "What it gives you", "Read it"], [
     ["**1. Read this first**", "The schedule and time plan, the three rules, the twelve sentences, the words to avoid.", "First. Everyone."],
     ["**2. The script**", "What to say on every slide: who speaks, how long, what to point at, the hand-off, and the questions that may interrupt you.", "Read it aloud, twice."],
     ["**3. The demo script**", "The 40-minute live demo in six steps: exact clicks, what to say, what to do when something breaks.", "Before the demo rehearsal."],
     ["**4. How it works**", "The whole system in plain language, with the rules and their numbers.", "Everyone. This is the study core."],
     ["**5. Questions and answers**", "One page of likely questions for each role, the second question about the system, study or project, the ten standard questions, and about 50 more by topic.", "Cover the answers and test yourself."],
     ["**6. Study guide**", "The numbers, a glossary, the paper-versus-system answers, the references, a self-test, a card for each member, and a study plan.", "Keep it open while you study."],
     ["**7. The day**", "A minute-by-minute plan for the 5-minute preparation, checklists for the day before and 15 minutes before, and what to do if something breaks.", "The day before."],
     ["**Appendix A**", "A ready narration for the 2-minute explainer video.", "If the video is not made yet."],
    ]))
    h.append('<div class="callout warn"><b>Marks used in this guide.</b> <b class="fill">FILL</b> means the real answer must come from your team or your appendices: do not invent it. <b class="chk">CHECK</b> means look at the source before you say it. Everything else was checked against the running system or the manuscript notes.</div>')
    h.append('<div class="callout note"><b>One honest line sums up the whole defense:</b> TaraBasa AI is a working, explainable reading practice system whose rules are our own settings. It has been checked with computer voices and automated tests. It has not yet been used by real children. That is what the pilot is for. Say it before they ask.</div>')
    h.append("</section>")
    return "\n".join(h)

def part1():
    h = [part_page(1, "Read this first", "The schedule, the three rules, the twelve sentences, and the words to avoid."), '<section class="body">']
    h.append(head(2, "1.1 The defense at a glance"))
    h.append("<p>From the schedule the team received (check it against the official one): the defense proper is <b>25 minutes</b>; the <b>system demo is 40 minutes</b> and includes the panel's clarification and cross-examination; then the panel deliberates for 5 minutes, the verdict is presented for 5 minutes and finalization takes 5 minutes. <b>Each member is asked two questions: one related to the corresponding group role, and one related to the system, study or project</b> (5 minutes for each round), so everyone presents at least one slide. The deck follows the outline in Appendix Y: concept or explainer video, title page, objectives (verbatim), scope and limitations, FDD, ERD, technology stack, network design, roadmap, conclusions, recommendations, system demo, references.</p>")
    h.append(table(["", "Part of the session (as in the schedule)", "Time", "Where in this guide"], X.SESSION, "sched"))
    h.append(head(2, "1.2 The time plan (slides 1 to 15 take about 23 minutes)"))
    h.append(table(["Slide", "Title", "Minutes", "Speaker (suggested)"], T.SCHEDULE, "sched"))
    h.append('<div class="callout note">Cut <b>slide 12</b> first if you are short on time. If the video is not ready, slide 2 still takes about 3 minutes by talking through the picture. The roles on slide 1 are not defined per person in the paper (only Tyron Pulga is named Project Manager): <b class="fill">FILL IN</b> each person&rsquo;s title before the defense.</div>')
    h.append(head(2, "1.3 The three rules for the whole defense"))
    for i, (a, b) in enumerate(T.THREE_RULES, 1):
        h.append('<div class="rule"><span class="rn">%d</span><div><b>%s</b><br>%s</div></div>' % (i, md(a), md(b)))
    h.append(head(2, "1.4 The twelve sentences to know by heart"))
    h.append(ol(T.TWELVE))
    h.append(head(2, "1.5 Words to avoid, and what to say instead"))
    h.append(table(["Do not say", "Say"], T.WORDS_TO_AVOID))
    h.append(head(2, "1.6 When you do not know the answer"))
    h.append("<p>Use this shape for any question. <b>(1) What we did. (2) What the data or the source shows. (3) What we did not measure. (4) What we would do next.</b></p>")
    h.append('<div class="callout note"><b>Example.</b> Question: &ldquo;Did it improve reading scores?&rdquo; Answer: &ldquo;We built and tested the system, and it works as designed: 912 automated tests pass. We did not run a long-term study, so we cannot claim reading scores improved. The next step is a consented classroom pilot that compares children&rsquo;s Phil-IRI results before and after.&rdquo;</div>')
    h.append("</section>")
    return "\n".join(h)

def slide_card(s):
    cls = "slide backup" if s["n"] >= 18 else "slide"
    x = ['<article class="%s">' % cls]
    x.append('<header class="sh"><span class="sn">%s</span><div class="st">%s<div class="smeta">%s</div></div></header>' % (
        s["n"], head(2, "Slide %s. %s" % (s["n"], s["title"])), chips(("c-blue", s["speaker"]), ("c-orange", "Time %s" % s["mins"]))))
    x.append('<div class="row"><span class="lab">On the screen</span><span>%s</span></div>' % md(s["onscreen"]))
    x.append('<div class="row"><span class="lab do">Do</span><span>%s</span></div>' % md(s["do"]))
    x.append('<div class="say"><span class="saylab">SAY</span>%s</div>' % "".join("<p>%s</p>" % md(p) for p in s["say"]))
    x.append('<div class="row"><span class="lab">Hand-off</span><span>%s</span></div>' % md(s["handoff"]))
    if s["asked"]:
        x.append('<div class="ifasked"><div class="iat">If the panel asks</div>' + "".join(qa_block(q, a) for q, a in s["asked"]) + "</div>")
    x.append("</article>")
    return "\n".join(x)

def part2():
    h = [part_page(2, "The script, slide by slide", "Say these words in your own voice. The numbers and facts must stay exact. Slides 1 to 15 take about 23 minutes; 16 is the demo; 17 closes; 18 and 19 are backups for questions."), '<section class="body">']
    h.append('<div class="callout note"><b>How to read a slide page.</b> The blue chip is the suggested speaker, the orange chip is the time. <b>SAY</b> is the spoken script. <b>Do</b> is what you point at or click. <b>If the panel asks</b> lists the questions most likely to interrupt that slide, with a short answer. Bold words in the script are the words to lean on.</div>')
    for s in S.SLIDES:
        h.append(slide_card(s))
    h.append("</section>")
    return "\n".join(h)

def part3():
    h = [part_page(3, "The demo script", "Slide 16: forty minutes in six steps (Admin 3, Teacher 8, Parent 7, Learner 12, Teacher and Parent 6, Privacy and health 4). The panel may interrupt at any step. Demo accounts only, never a real child."), '<section class="body">']
    h.append(head(2, "3.1 Setup, before the demo"))
    h.append(ul(D.SETUP))
    h.append('<div class="callout note"><b>Who does what.</b> %s</div>' % md(D.ROLES))
    for st in D.STEPS:
        x = ['<article class="slide demo">']
        x.append('<header class="sh"><span class="sn">%d</span><div class="st">%s<div class="smeta">%s</div></div></header>' % (
            st["n"], head(2, "3.%d Step %d. %s: %s" % (st["n"] + 1, st["n"], st["who"], st["title"])), chips(("c-blue", "Driver: " + st["driver"]), ("c-orange", "%d min" % st["mins"]))))
        x.append('<div class="row"><span class="lab">Goal</span><span>%s</span></div>' % md(st["goal"]))
        x.append('<div class="row"><span class="lab do">Do</span><span>%s</span></div>' % ol(st["doit"]))
        x.append('<div class="say"><span class="saylab">SAY</span>%s</div>' % "".join("<p>%s</p>" % md(p) for p in st["say"]))
        x.append('<div class="row"><span class="lab">Point out</span><span>%s</span></div>' % md(st["hooks"]))
        if st["asked"]:
            x.append('<div class="ifasked"><div class="iat">If the panel asks</div>' + "".join(qa_block(q, a) for q, a in st["asked"]) + "</div>")
        x.append('<div class="row"><span class="lab warnlab">If it breaks</span><span>%s</span></div>' % md(st["broke"]))
        x.append("</article>")
        h.append("\n".join(x))
    h.append(head(2, "3.8 If something goes wrong: calm sentences"))
    h.append(table(["When", "What to say and do"], [[a, b] for a, b in D.RECOVERY]))
    h.append("</section>")
    return "\n".join(h)

def part4():
    h = [part_page(4, "How it works", "The system in plain language, with the rules and their numbers. Each section starts with one sentence you can say, and ends with the questions you may be asked."), '<section class="body">']
    for sec in H.SECTIONS:
        h.append(head(2, sec["title"]))
        for b in sec["blocks"]:
            h.append(render_block(b))
    h.append("</section>")
    return "\n".join(h)

def part5():
    h = [part_page(5, "Questions and answers", "One page for each role, the second question about the system, study or project (the schedule asks each member both), the ten standard questions, and the topic bank. Cover the answers and say your own first."), '<section class="body">']
    h.append(head(2, "5.0 How to use this part"))
    h.append(ul(Q.HOW_TO))
    for i, r in enumerate(Q.ROLES, 1):
        h.append('<div class="rolepage">')
        h.append(head(2, "5.%d %s questions: %s" % (i, r["role"], r["who"]), 'class="role-%s"' % r["color"]))
        h.append('<p class="rolesub">%s</p>' % md(r["intro"]))
        for j, (q, a) in enumerate(r["qs"], 1):
            h.append(qa_block(q, a, "%s%d" % ("PM DT UX TW".split()[i - 1], j)))
        h.append("</div>")
    h.append(head(2, "5.5 The second question: the system, the study or the project"))
    h.append(ul(X.SECOND_INTRO))
    for grp, qs in X.SECOND_QS:
        h.append(head(3, grp))
        for j, (q, a) in enumerate(qs, 1):
            h.append(qa_block(q, a))
    h.append(head(2, "5.6 The ten standard defense questions"))
    for j, (q, a) in enumerate(Q.STANDARD, 1):
        h.append(qa_block(q, a, "A%d" % j))
    h.append(head(2, "5.7 The topic bank"))
    for k, (topic, qs) in enumerate(Q.BANK, 1):
        h.append(head(3, topic))
        for j, (q, a) in enumerate(qs, 1):
            h.append(qa_block(q, a))
    h.append("</section>")
    return "\n".join(h)

def part6():
    h = [part_page(6, "Study guide", "The numbers on a few pages, the words, the answers about the paper, the references, a self-test, a card for each member, and a study plan."), '<section class="body">']
    h.append(head(2, "6.1 The numbers on a few pages"))
    h.append('<p class="small">Basis: <b>D</b> = a DepEd document, <b>T</b> = the team&rsquo;s own setting (kept in one settings file). Where a row says 90 and 97, those are DepEd&rsquo;s Phil-IRI bands; the other numbers are ours.</p>')
    for title, rows in T.NUMBERS:
        h.append(head(3, title))
        h.append(table(["Item", "Value"], rows, "nums"))
    h.append(head(2, "6.2 Glossary"))
    h.append('<dl class="gloss">' + "".join("<dt>%s</dt><dd>%s</dd>" % (md(a), md(b)) for a, b in T.GLOSSARY) + "</dl>")
    h.append(head(2, "6.3 The paper versus the system: what to say"))
    h.append('<p>The deck shows the real system. In these places the paper (the 18 November 2026 PDF, as read on 21 November) says something else, so a panel member reading the paper could ask. <b>Fix the paper first;</b> use the last column if you are asked before it is fixed.</p>')
    h.append(table(["Where", "The paper says", "The system is", "If asked, say"], [list(m) for m in T.MISMATCHES], "mism"))
    h.append(head(2, "6.4 The key references (as on slide 15)"))
    h.append('<div class="callout note">Every DOI below was looked up in Crossref on 21 November 2026 and returns the right article. In the manuscript, two DOIs are wrong (Alrawashdeh and Wang; corrected here), Abocejo (2022) could not be found, and Chu (2021) lists a different first author. Do not rely on those two.</div>')
    h.append('<ol class="refs">' + "".join("<li>%s</li>" % md(r) for r in T.REFS) + "</ol>")
    h.append(head(2, "6.5 The self-test"))
    h.append("<p>Cover the right-hand column. You should get every one without hesitating.</p>")
    h.append(table(["Question", "Answer"], [[q, a] for q, a in T.SELFTEST], "self"))
    h.append(head(2, "6.6 A card for each member"))
    for c in T.CARDS:
        h.append('<div class="card card-%s">' % c["color"])
        h.append('<div class="cardhead"><b>%s</b><span>%s</span></div>' % (md(c["name"]), md(c["role"])))
        h.append('<div class="row"><span class="lab">Your slides</span><span>%s &middot; %s</span></div>' % (md(c["slides"]), md(c["mins"])))
        h.append('<div class="row"><span class="lab do">Know cold</span><span>%s</span></div>' % ul(c["must"]))
        h.append('<div class="row"><span class="lab">Numbers</span><span>%s</span></div>' % md(c["numbers"]))
        h.append('<div class="row"><span class="lab">Your questions</span><span>%s</span></div>' % md(c["role_q"]))
        h.append('<div class="row"><span class="lab warnlab">If you blank</span><span>%s</span></div>' % md(c["blank"]))
        h.append("</div>")
    h.append(head(2, "6.7 The study plan"))
    for when, items in T.PLAN:
        h.append('<div class="plan"><div class="when">%s</div>%s</div>' % (md(when), ul(items)))
    h.append("</section>")
    return "\n".join(h)

def part7():
    h = [part_page(7, "The day", "Checklists, and what to do when something breaks. A panel judges how you recover, not only whether it works."), '<section class="body">']
    h.append(head(2, "7.1 The 5-minute preparation (Initialization / initial preparations)"))
    h.append("<p>The schedule starts with 5 minutes of initial preparations. This is the order to use, so nothing is forgotten and nobody is idle. <b>Anyone can do any task</b>: agree who does what at minute 4, or earlier. The keep-awake workflow must already be running before these 5 minutes begin.</p>")
    for when, items in X.INIT_PLAN:
        h.append(head(3, when))
        h.append('<ul class="checklist">' + "".join("<li>%s</li>" % md(i) for i in items) + "</ul>")
    h.append(head(2, "7.2 Checklists"))
    for when, items in T.CHECK_BEFORE:
        h.append(head(3, when))
        h.append('<ul class="checklist">' + "".join("<li>%s</li>" % md(i) for i in items) + "</ul>")
    h.append(head(3, "What to bring"))
    h.append('<ul class="checklist">' + "".join("<li>%s</li>" % md(i) for i in [
        "Laptop and charger; the phone and its charger; a spare cable; headphones are not needed.",
        "The clicker; printed copies of this guide for each member; the learner card printed on paper.",
        "The deck PDF on a USB drive and on the laptop, in case PowerPoint fails in the room.",
        "A pen and a small paper: John Benedict writes down every panel question during the demo."]) + "</ul>")
    h.append(head(2, "7.3 If something breaks"))
    h.append(table(["Symptom", "Likely cause", "What to do"], T.TROUBLE))
    h.append('<div class="callout note"><b>If the live demo fails:</b> say one calm sentence (&ldquo;The free hosting is waking up; let me show you the local copy&rdquo;), switch to the local copy or the recorded video, and carry on with the story.</div>')
    h.append(head(2, "7.4 Links"))
    h.append(ul([
        "Live site: `https://tarabasa-ai.onrender.com`. Health: `/up`. Public rules page: `/how-it-works`.",
        "Code: `github.com/Lando-ssd/tarabasa-ai-thesis` (public). Study kit and deck notes: the `docs/defense` folder.",
        "Keep-awake: GitHub, Actions, **Keep TaraBasa awake**, Run workflow.",
        "Everything still open (paper fixes, legal reading, real tests): `docs/OPEN-ITEMS.md`."]))
    h.append("</section>")
    return "\n".join(h)

def appendix():
    h = ['<section class="part small-part"><div class="partnum">APPENDIX A</div>' + head(1, "Appendix A. Narration for the explainer video") + '<p class="partsub">About two minutes, seven scenes. Use screen recordings of the running system, and credit any outside footage.</p></section>', '<section class="body">']
    h.append("<p>The deck needs a concept or explainer video on slide 2. If the team has not made it yet, record the screens listed in the middle column and read the right-hand column as the voice-over (about 220 words, at an unhurried pace). Do not show real children&rsquo;s faces or names.</p>")
    h.append(table(["Scene", "What is on the screen", "Voice-over"], [[a, b, c] for a, b, c in T.NARRATION], "narr"))
    h.append('<div class="callout warn">Keep the video honest: it must not say the AI was trained, that children were tested, or that DepEd approved the system. If you use the classroom video from the earlier Canva deck, credit its source or replace it.</div>')
    h.append("</section>")
    return "\n".join(h)

# ---------- page ----------
CSS = """
@font-face{font-family:'Baloo 2';src:url('%(f)s/baloo-2-latin-500-800.woff2') format('woff2');font-weight:500 800;}
@font-face{font-family:'Inter';src:url('%(f)s/inter-latin-400-800.woff2') format('woff2');font-weight:400 800;}
@page{size:A4;margin:17mm 16mm 19mm 16mm;@bottom-center{content:"TaraBasa AI  |  Defense script and study guide  |  page " counter(page);font:8pt 'Inter',sans-serif;color:#7b8794;}}
@page:first{margin:0;@bottom-center{content:none;}}
*{box-sizing:border-box;}
body{font:10pt/1.5 'Inter',sans-serif;color:#17212b;margin:0;}
h1,h2,h3{font-family:'Baloo 2',sans-serif;color:#0a3d73;margin:0;line-height:1.15;}
h1{font-size:26pt;} h2{font-size:15pt;margin:20pt 0 6pt;border-bottom:2px solid #dbe7f4;padding-bottom:3pt;break-after:avoid;} h3{font-size:12pt;margin:14pt 0 5pt;color:#1c5fa8;break-after:avoid;}
p{margin:0 0 7pt;} .small{font-size:9pt;color:#5b6b7a;}
code{font:8.5pt Consolas,monospace;background:#eef3f9;padding:0 3pt;border-radius:3pt;}
.mk{color:#fff;font-size:1pt;line-height:0;}
b.fill{background:#fff0c2;color:#7a4a00;padding:0 3pt;border-radius:3pt;} b.chk{background:#e4f0ff;color:#0f5fae;padding:0 3pt;border-radius:3pt;}
ul,ol{margin:0 0 8pt;padding-left:16pt;} li{margin:0 0 3pt;}
table{border-collapse:collapse;width:100%%;margin:0 0 10pt;font-size:9pt;} th{background:#eaf3fc;color:#0a3d73;text-align:left;font-weight:700;} th,td{border:1px solid #d6e2ef;padding:4pt 6pt;vertical-align:top;} tr{break-inside:avoid;} thead{display:table-header-group;}
table.nums td:first-child,table.self td:first-child,table.sched td:first-child{white-space:nowrap;}
table.self td:first-child{white-space:normal;width:48%%;}
table.nums td:first-child{width:34%%;font-weight:600;}
table.mism{font-size:8.3pt;} table.narr td:first-child{white-space:nowrap;width:16%%;}
/* cover */
.cover{height:297mm;padding:30mm 24mm;background:linear-gradient(160deg,#0a3d73 0%%,#1c7ed6 100%%);color:#fff;break-after:page;display:flex;flex-direction:column;}
.cv-top{font:800 18pt 'Baloo 2';letter-spacing:.02em;color:#ffd9a6;} .cv-title{font-size:44pt;color:#fff;margin:34mm 0 6mm;line-height:1.05;} .cv-sub{font-size:11pt;color:#d7e8fa;}
.cv-box{margin-top:16mm;background:rgba(255,255,255,.14);border-radius:10pt;padding:10pt 14pt;font-size:10.5pt;} .cv-box p{margin:0 0 5pt;}
.cv-team{margin-top:auto;font-size:11pt;line-height:1.6;color:#fff;} .cv-foot{margin-top:8mm;font-size:8.5pt;color:#c4dcf5;}
.front{break-after:page;} .contents{break-after:page;}
.contents h1{font-size:22pt;margin-bottom:8pt;} .toc{columns:2;column-gap:14mm;font-size:9pt;} .toc a{color:#17212b;text-decoration:none;display:flex;gap:4pt;} .toc .tl{flex:1;border-bottom:1px dotted #aab8c6;} .toc .l1{font-weight:700;color:#0a3d73;margin-top:7pt;break-after:avoid;} .toc .l2{padding-left:8pt;} .toc div{break-inside:avoid;margin:1pt 0;}
/* parts */
.part{break-before:page;padding:0 0 5mm;} .small-part{padding-top:0;} .partnum{font:700 10pt 'Inter';letter-spacing:.2em;color:#ef8d2a;} .part h1{font-size:30pt;margin:3pt 0 8pt;border-left:8px solid #ef8d2a;padding-left:12pt;} .partsub{font-size:11pt;color:#4d5d70;max-width:150mm;}
.body{break-before:auto;}
/* slide cards */
.slide{border:1.5px solid #d3e2f2;border-radius:10pt;margin:0 0 14pt;padding:0 0 8pt;overflow:hidden;}
.slide.backup{border-style:dashed;} .slide.demo{border-color:#f0cfa4;}
.sh{display:flex;gap:10pt;align-items:center;background:#eef5fc;padding:7pt 10pt;break-after:avoid;} .slide.demo .sh{background:#fff4e6;}
.sn{font:800 20pt 'Baloo 2';background:#1c7ed6;color:#fff;min-width:34pt;height:34pt;border-radius:9pt;display:flex;align-items:center;justify-content:center;} .slide.demo .sn{background:#ef8d2a;color:#2b1500;} .slide.backup .sn{background:#7c8da3;}
.st{flex:1;} .st h2{border:0;margin:0 0 3pt;padding:0;font-size:13.5pt;} .chips{display:flex;gap:5pt;flex-wrap:wrap;} .chip{font-size:8pt;font-weight:600;border-radius:99px;padding:1pt 7pt;} .c-blue{background:#dcebfa;color:#0f5fae;} .c-orange{background:#fde4c2;color:#8a4a07;}
.row{display:flex;gap:8pt;padding:3pt 10pt;font-size:9.3pt;} .row .lab{flex:0 0 58pt;font:700 7.5pt 'Inter';text-transform:uppercase;letter-spacing:.07em;color:#6b7c8e;padding-top:2pt;} .row .lab.do{color:#a8560a;} .row .lab.warnlab{color:#a12f1c;} .row ol,.row ul{margin:0;padding-left:13pt;} .row>span:last-child{flex:1;}
.say{margin:6pt 10pt;padding:8pt 12pt 3pt;background:#f3f8fe;border-left:5px solid #1c7ed6;border-radius:0 8pt 8pt 0;font-size:11pt;line-height:1.58;position:relative;} .say p{margin:0 0 7pt;} .saylab{display:block;font:800 7.5pt 'Inter';letter-spacing:.2em;color:#1c7ed6;margin-bottom:3pt;} .say b{color:#0a3d73;}
.ifasked{margin:6pt 10pt 0;border-top:1px dashed #c6d6e6;padding-top:5pt;} .iat{font:700 7.5pt 'Inter';text-transform:uppercase;letter-spacing:.07em;color:#6b7c8e;margin-bottom:3pt;}
.qa{break-inside:avoid;margin:0 0 7pt;} .q{font-weight:700;color:#0a3d73;font-size:9.6pt;} .a{font-size:9.3pt;margin-top:1pt;} .qn{display:inline-block;background:#1c7ed6;color:#fff;font:700 7.5pt 'Inter';padding:0 5pt;border-radius:4pt;margin-right:5pt;}
.ifasked .qa{margin-bottom:5pt;}
.rolepage{break-before:page;} .rolepage h2{margin-top:0;font-size:17pt;} .role-blue{border-color:#1c7ed6;} .role-green{border-color:#1f9e83;} .role-orange{border-color:#ef8d2a;} .role-purple{border-color:#7c5cd6;} .rolesub{color:#4d5d70;margin-bottom:8pt;}
/* how it works */
.onesentence{background:#fff4e6;border-left:5px solid #ef8d2a;border-radius:0 8pt 8pt 0;padding:7pt 12pt;margin:6pt 0 9pt;font-size:10.8pt;break-inside:avoid;} .onesentence span{display:block;font:800 7.5pt 'Inter';letter-spacing:.14em;text-transform:uppercase;color:#a8560a;margin-bottom:2pt;}
.callout{border-radius:8pt;padding:7pt 11pt;margin:6pt 0 9pt;font-size:9.3pt;break-inside:avoid;} .callout.note{background:#eef5fc;border-left:4px solid #1c7ed6;} .callout.warn{background:#fff4e6;border-left:4px solid #ef8d2a;}
.beready{margin:8pt 0 12pt;padding:7pt 11pt 2pt;border:1.5px solid #cfe0f1;border-radius:8pt;} .brt{font:800 7.5pt 'Inter';letter-spacing:.14em;text-transform:uppercase;color:#1c7ed6;margin-bottom:4pt;}
.fig{margin:6pt 0 10pt;break-inside:avoid;} .fig svg{width:100%%;height:auto;border:1px solid #dbe7f4;border-radius:8pt;background:#fbfdff;padding:4pt;}
.rule{display:flex;gap:10pt;margin:0 0 7pt;padding:7pt 10pt;background:#fff4e6;border-radius:8pt;break-inside:avoid;} .rn{font:800 17pt 'Baloo 2';color:#ef8d2a;width:20pt;}
.gloss{columns:2;column-gap:10mm;font-size:9pt;margin:0;} .gloss dt{font-weight:700;color:#0a3d73;break-after:avoid;margin-top:5pt;} .gloss dd{margin:0 0 2pt;} .gloss dt,.gloss dd{break-inside:avoid;}
.refs{font-size:8.8pt;} .refs li{margin-bottom:5pt;break-inside:avoid;}
.card{border:1.5px solid #cbd9e8;border-radius:10pt;margin:0 0 12pt;padding:0 0 6pt;break-inside:avoid;overflow:hidden;} .cardhead{display:flex;justify-content:space-between;align-items:baseline;background:#eef5fc;padding:6pt 10pt;font-size:11pt;} .cardhead span{font-weight:600;color:#4d5d70;font-size:9pt;} .card-green .cardhead{background:#e3f5f0;} .card-orange .cardhead{background:#fdeedb;} .card-purple .cardhead{background:#efebfb;}
.plan{margin:0 0 8pt;break-inside:avoid;} .when{font:700 10pt 'Baloo 2';color:#a8560a;}
ul.checklist{list-style:none;padding-left:0;} ul.checklist li{padding-left:16pt;position:relative;} ul.checklist li::before{content:'';position:absolute;left:0;top:3pt;width:9pt;height:9pt;border:1.5px solid #1c7ed6;border-radius:2pt;}
"""

def toc_html(pages):
    rows = ['<section class="contents"><h1>Contents</h1><div class="toc">']
    for lvl, hid, text in HEADS:
        if lvl == 3: continue
        pg = pages.get(hid, "")
        t = html.escape(re.sub(r"\*+", "", text))
        rows.append('<div class="l%d"><a href="#%s"><span>%s</span><span class="tl"></span><span>%s</span></a></div>' % (lvl, hid, t, pg))
    rows.append("</div></section>")
    return "\n".join(rows)

def build(pages):
    HEADS.clear()
    body = [cover(), intro()]
    # parts are generated first (to fill HEADS), then the contents page is placed after the intro
    parts = [part1(), part2(), part3(), part4(), part5(), part6(), part7(), appendix()]
    toc = toc_html(pages)
    doc = "<!doctype html><html lang='en'><head><meta charset='utf-8'><title>%s</title><style>%s</style></head><body>%s%s%s</body></html>" % (
        TITLE, CSS % {"f": FONTS}, body[0] + body[1], toc, "".join(parts))
    open(OUT_HTML, "w", encoding="utf-8").write(doc)
    return len(HEADS)

def print_pdf():
    if os.path.exists(OUT_PDF): os.remove(OUT_PDF)
    cmd = [EDGE, "--headless=new", "--disable-gpu", "--no-pdf-header-footer", "--allow-file-access-from-files", "--print-to-pdf=" + OUT_PDF, "file:///" + OUT_HTML.replace("\\", "/")]
    subprocess.run(cmd, check=True, timeout=240, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    for _ in range(40):
        if os.path.exists(OUT_PDF) and os.path.getsize(OUT_PDF) > 10000: break
        time.sleep(0.5)

def find_pages():
    import pymupdf
    doc = pymupdf.open(OUT_PDF); pages = {}
    for i, pg in enumerate(doc):
        for m in re.findall(r"§(h\d+)§", pg.get_text()):
            pages.setdefault(m, i + 1)
    n = len(doc); doc.close(); return pages, n

if __name__ == "__main__":
    n = build({}); print_pdf(); pages, total = find_pages()
    build(pages); print_pdf(); pages2, total2 = find_pages()
    print("headings:", n, "pages:", total2, "first-pass pages:", total, "toc stable:", pages == pages2 or all(abs(pages[k]-pages2[k]) == 0 for k in pages if k in pages2))
    print(OUT_PDF, round(os.path.getsize(OUT_PDF) / 1024), "KB")
