"""
Builds the TaraBasa AI defense study kit as ONE tabbed web page.

How to use it (PowerShell, from this folder):
    python build_kit_html.py

It reads 00-*.md to 07-*.md and writes TaraBasa-Defense-Study-Kit.html next to them.
Open that file in a browser. Each markdown file becomes one tab. To make a PDF:
press Ctrl + P and choose "Save as PDF" (printing shows every tab one after another).
Edit the .md files, not the .html, then run this again.

It uses only Python's standard library, makes no network request, and the page
asks for nothing from the internet (the two fonts are inside the file).
"""
import base64
import html
import re
import sys
from datetime import date
from pathlib import Path

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent.parent
OUT = HERE / "TaraBasa-Defense-Study-Kit.html"

# ---------------------------------------------------------------- tabs
TABS = [
    dict(id="start", file="00-START-HERE.md", num="0", label="Start here",
         title="Start here",
         tag="How to use this kit, the three rules for the whole defense, and the 12 sentences to know by heart.",
         mins=10, sub_h3=False),
    dict(id="script", file="01-HOOK-AND-STORYLINE-SCRIPT.md", num="1", label="Script",
         title="The spoken script: hook and storyline",
         tag="Word for word. A hook, the problem, then one child (Maria) from sign up to alert, then the close.",
         mins=25, sub_h3=True),
    dict(id="flow", file="02-WORKFLOW-AND-TECH.md", num="2", label="How it connects",
         title="How it is connected: workflow, tech stack, network",
         tag="What happens behind each screen: where the data goes, which service does what, and why this technology.",
         mins=25, sub_h3=True),
    dict(id="basis", file="03-BASIS-OF-EVERY-DECISION.md", num="3", label="Basis",
         title="The basis of every decision",
         tag="Why each thing exists and what it is based on. Phil-IRI, the reading path, levels, AI, UI and UX. The adviser's favourite.",
         mins=45, sub_h3=False),
    dict(id="money", file="04-BUSINESS-MODEL-AND-PROFIT.md", num="4", label="Business and profit",
         title="Business model, revenue and profit",
         tag="The Business Model Canvas, where the money comes from, what it costs, and an honest break-even.",
         mins=20, sub_h3=True, accordion_sections=("8. Likely business questions",)),
    dict(id="qa", file="05-QUESTIONS-AND-ANSWERS.md", num="5", label="Questions",
         title="Questions and answers",
         tag="The 10 standard defense questions and about 80 practice questions. Cover the answer, say yours, then open it.",
         mins=40, accordion=True, search="questions"),
    dict(id="run", file="06-RUN-AND-TROUBLESHOOT.md", num="6", label="Run and fix",
         title="Run it on your computer, and fix what breaks",
         tag="Step by step in PowerShell, then where to look in Render, TiDB, GitHub and Gmail when something fails.",
         mins=20, sub_h3=True),
    dict(id="words", file="07-GLOSSARY-AND-NUMBERS.md", num="7", label="Words and numbers",
         title="Glossary and the numbers on one page",
         tag="What every term means, and every setting in one place. Search it while you study.",
         mins=15, sub_h3=False, search="terms"),
    dict(id="team", file="08-TEAM-SCRIPTS.md", num="8", label="Team scripts",
         title="Team scripts: four speakers, one defense",
         tag="Who speaks when, a card for each speaker, who answers which questions, and a rehearsal plan.",
         mins=15, sub_h3=False),
]

LABELS = "CURRICULUM|RESEARCH|TEAM CHOICE|GUIDELINE|FACT|ASSUMPTION|TO VALIDATE|FILL|CONFIRM|VERIFIED|LISTED|RECALLED"
LABEL_RE = re.compile(r"\[((?:%s)(?:[^\[\]]*))\]" % LABELS)
LABEL_CLASSES = {
    "CURRICULUM": "curriculum", "VERIFIED": "verified", "RESEARCH": "research", "GUIDELINE": "guideline",
    "LISTED": "listed", "TEAM CHOICE": "team", "FILL": "fill", "CONFIRM": "confirm",
    "TO VALIDATE": "validate", "RECALLED": "recalled", "FACT": "fact", "ASSUMPTION": "assumption",
}
BEATS = {
    "On the screen": "screen",
    "Behind the screen": "behind",
    "Why it exists": "why",
    "Where it ends": "ends",
}


def label_class(inner):
    up = inner.upper()
    for name in sorted(LABEL_CLASSES, key=len, reverse=True):
        if up.startswith(name):
            return LABEL_CLASSES[name]
    return "other"


def slug(text, used, prefix=""):
    s = re.sub(r"<[^>]+>", "", text)
    s = re.sub(r"[^a-z0-9]+", "-", s.lower()).strip("-")[:48] or "section"
    s = (prefix + "--" + s) if prefix else s
    base, n = s, 2
    while s in used:
        s = f"{base}-{n}"
        n += 1
    used.add(s)
    return s


# ---------------------------------------------------------------- inline markdown
def inline(text):
    spans = []

    def keep(m):
        spans.append("<code>" + html.escape(m.group(1), quote=False) + "</code>")
        return "\x00%d\x00" % (len(spans) - 1)

    s = re.sub(r"`([^`]+)`", keep, text)
    s = html.escape(s, quote=False)
    s = re.sub(r"\*\*(.+?)\*\*", r"<strong>\1</strong>", s)
    s = re.sub(r"(?<![*\w])\*(?!\s)(.+?)(?<!\s)\*(?![*\w])", r"<em>\1</em>", s)
    s = re.sub(r"\[([^\]]+)\]\((https?://[^)\s]+)\)", r'<a href="\2" rel="noopener">\1</a>', s)
    s = LABEL_RE.sub(
        lambda m: '<span class="lab lab-%s%s">[%s]</span>'
        % (label_class(m.group(1)), " long" if len(m.group(1)) > 26 else "", m.group(1)),
        s,
    )
    return re.sub(r"\x00(\d+)\x00", lambda m: spans[int(m.group(1))], s)


def plain(text):
    return re.sub(r"\*\*|`", "", text).strip()


# ---------------------------------------------------------------- block parser
LIST_RE = re.compile(r"^(\s*)([-*]|\d+\.)\s+(.*)$")
FENCE_RE = re.compile(r"^(\s*)```(\w*)\s*$")
BOLD_ONLY_RE = re.compile(r"^\*\*[^*]+\*\*$")


def is_table_start(lines, i):
    return lines[i].lstrip().startswith("|") and i + 1 < len(lines) and re.match(r"^\s*\|?\s*:?-{2,}", lines[i + 1])


def split_row(line):
    line = line.strip()
    if line.startswith("|"):
        line = line[1:]
    if line.endswith("|"):
        line = line[:-1]
    cells, cur, esc = [], "", False
    for ch in line:
        if esc:
            cur += ch
            esc = False
        elif ch == "\\":
            esc = True
        elif ch == "|":
            cells.append(cur.strip())
            cur = ""
        else:
            cur += ch
    cells.append(cur.strip())
    return cells


def render_table(rows):
    head = split_row(rows[0])
    body = [split_row(r) for r in rows[2:]]
    h = ['<div class="tw"><table><thead><tr>']
    h += [f"<th>{inline(c)}</th>" for c in head]
    h.append("</tr></thead><tbody>")
    for r in body:
        r = (r + [""] * len(head))[: len(head)]
        h.append("<tr>" + "".join(f"<td>{inline(c)}</td>" for c in r) + "</tr>")
    h.append("</tbody></table></div>")
    return "".join(h)


BEAT_RE = re.compile(r"^\*\*(%s)\.\*\*\s*(.*)$" % "|".join(BEATS))


def render_items(items, extra_cls=""):
    if not items:
        return ""
    ordered = items[0]["ordered"]
    tag = "ol" if ordered else "ul"
    attr = ""
    if ordered and items[0]["num"] not in (None, 1):
        attr = f' start="{items[0]["num"]}"'
    first = items[0]["parts"][0][1] if items[0]["parts"] and items[0]["parts"][0][0] == "text" else ""
    cls = extra_cls
    if not ordered and BEAT_RE.match(first):
        cls += " beats"
    if not ordered and all(
        it["parts"] and it["parts"][0][0] == "text" and re.match(r"^\[[ xX]\]\s", it["parts"][0][1]) for it in items
    ):
        cls += " check"
    cls_attr = f' class="{cls.strip()}"' if cls.strip() else ""
    h = [f"<{tag}{attr}{cls_attr}>"]
    for it in items:
        txt0 = it["parts"][0][1] if it["parts"] and it["parts"][0][0] == "text" else ""
        bm = BEAT_RE.match(txt0) if not ordered else None
        if bm:
            h.append(f'<li class="beat b-{BEATS[bm.group(1)]}"><span class="bt">{bm.group(1)}</span><div>')
            h.append(inline(bm.group(2)))
            for kind, val in it["parts"][1:]:
                if kind == "text":
                    h.append(" " + inline(val))
            h.append("</div></li>")
            continue
        h.append("<li>")
        for kind, val in it["parts"]:
            if kind == "text":
                m = re.match(r"^\[([ xX])\]\s+(.*)$", val)
                if m:
                    h.append(f'<label class="chk"><input type="checkbox"{" checked" if m.group(1) in "xX" else ""}> <span>{inline(m.group(2))}</span></label>')
                else:
                    h.append(inline(val))
            else:
                h.append(code_block(val))
        if it["kids"]:
            h.append(render_items(it["kids"]))
        h.append("</li>")
    h.append(f"</{tag}>")
    return "".join(h)


def code_block(code):
    return '<div class="code"><button class="copy" type="button">Copy</button><pre><code>' + html.escape(code, quote=False) + "</code></pre></div>"


def build_list(lines, i):
    stack = [(-1, [])]
    root = stack[0][1]
    cur = None
    n = len(lines)
    while i < n:
        line = lines[i]
        if not line.strip():
            j = i + 1
            while j < n and not lines[j].strip():
                j += 1
            if j < n and (LIST_RE.match(lines[j]) or (lines[j].startswith("  ") and cur)):
                i = j
                continue
            break
        fm = FENCE_RE.match(line)
        if fm and cur is not None and len(fm.group(1)) > 0:
            ind = len(fm.group(1))
            i += 1
            code = []
            while i < n and not FENCE_RE.match(lines[i]):
                code.append(lines[i][ind:] if lines[i].startswith(" " * ind) else lines[i].lstrip())
                i += 1
            i += 1
            cur["parts"].append(("code", "\n".join(code)))
            continue
        m = LIST_RE.match(line)
        if m:
            indent = len(m.group(1))
            ordered = m.group(2)[0].isdigit()
            item = {
                "indent": indent, "ordered": ordered,
                "num": int(m.group(2)[:-1]) if ordered else None,
                "parts": [("text", m.group(3).strip())], "kids": [],
            }
            while len(stack) > 1 and stack[-1][0] >= indent:
                stack.pop()
            if stack[-1][0] < indent and len(stack) > 1:
                target = stack[-1][1][-1]["kids"]
                stack.append((indent, target))
            elif stack[-1][0] == -1:
                stack.append((indent, root))
            stack[-1][1].append(item)
            cur = item
            i += 1
            continue
        if line.startswith(" ") and cur is not None:
            txt = line.strip()
            if cur["parts"] and cur["parts"][-1][0] == "text":
                cur["parts"][-1] = ("text", cur["parts"][-1][1] + " " + txt)
            else:
                cur["parts"].append(("text", txt))
            i += 1
            continue
        break
    return root, i


def parse_blocks(text):
    """Markdown -> list of dicts: k (kind), html, raw (for headings, tables, code)."""
    lines = text.replace("\r\n", "\n").split("\n")
    out = []
    i, n = 0, len(lines)
    while i < n:
        line = lines[i]
        if not line.strip():
            i += 1
            continue
        hm = re.match(r"^(#{1,4})\s+(.*)$", line)
        if hm:
            out.append(dict(k=f"h{len(hm.group(1))}", raw=hm.group(2).strip(), html=""))
            i += 1
            continue
        if re.match(r"^---+\s*$", line):
            out.append(dict(k="hr", html="", raw=""))
            i += 1
            continue
        fm = FENCE_RE.match(line)
        if fm:
            i += 1
            code = []
            while i < n and not FENCE_RE.match(lines[i]):
                code.append(lines[i])
                i += 1
            i += 1
            out.append(dict(k="pre", raw="\n".join(code), html=code_block("\n".join(code))))
            continue
        if is_table_start(lines, i):
            rows = []
            while i < n and lines[i].lstrip().startswith("|"):
                rows.append(lines[i])
                i += 1
            out.append(dict(k="table", raw=rows, html=render_table(rows)))
            continue
        if line.startswith(">"):
            paras, cur = [], []
            while i < n and lines[i].startswith(">"):
                t = re.sub(r"^>\s?", "", lines[i])
                if t.strip():
                    cur.append(t.strip())
                elif cur:
                    paras.append(" ".join(cur))
                    cur = []
                i += 1
            if cur:
                paras.append(" ".join(cur))
            out.append(dict(k="quote", raw=paras, html=""))
            continue
        if LIST_RE.match(line):
            items, i = build_list(lines, i)
            out.append(dict(k="list", raw=items, html=""))
            continue
        buf = []
        while i < n:
            l = lines[i]
            if (not l.strip() or re.match(r"^#{1,4}\s", l) or re.match(r"^---+\s*$", l) or FENCE_RE.match(l)
                    or is_table_start(lines, i) or l.startswith(">") or LIST_RE.match(l)):
                break
            if buf and BOLD_ONLY_RE.match(l.strip()):
                break
            buf.append(l.strip())
            i += 1
            if BOLD_ONLY_RE.match(buf[-1]) and len(buf) == 1:
                break
        txt = " ".join(buf)
        out.append(dict(k="q" if BOLD_ONLY_RE.match(txt) else "p", raw=txt, html=""))
    return out


def render_block(b, extra=""):
    k = b["k"]
    if k == "p":
        return f"<p>{inline(b['raw'])}</p>"
    if k == "q":
        return f'<p class="q">{inline(b["raw"])}</p>'
    if k in ("table", "pre"):
        return b["html"]
    if k == "hr":
        return ""
    if k == "list":
        return render_items(b["raw"], extra)
    if k == "quote":
        paras = b["raw"]
        tag = "Read aloud"
        cls = "say"
        if paras and paras[0].startswith("**Say:**"):
            paras = [paras[0][len("**Say:**"):].strip()] + paras[1:]
            tag = "Say it"
            cls = "say one"
        return (f'<blockquote class="{cls}"><span class="saytag">{tag}</span>'
                + "".join(f"<p>{inline(p)}</p>" for p in paras) + "</blockquote>")
    if k in ("h3", "h4"):
        return f"<{k}>{inline(b['raw'])}</{k}>"
    return ""


def render_blocks(blocks):
    """Render a list of blocks, merging 'Notes for you' with its list."""
    out, i = [], 0
    while i < len(blocks):
        b = blocks[i]
        if b["k"] == "p" and b["raw"].startswith("Notes for you") and i + 1 < len(blocks) and blocks[i + 1]["k"] == "list":
            out.append('<div class="notes"><span class="notetag">Notes for you, not spoken</span>'
                       + render_block(b) + render_block(blocks[i + 1]) + "</div>")
            i += 2
            continue
        out.append(render_block(b))
        i += 1
    return "\n".join(out)


# ---------------------------------------------------------------- diagrams (hand drawn SVG and HTML)
def svg_open(w, h, title):
    return (f'<svg viewBox="0 0 {w} {h}" role="img" aria-label="{html.escape(title)}" class="dg" xmlns="http://www.w3.org/2000/svg">'
            '<defs><marker id="ah" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse">'
            '<path d="M0 0 L10 5 L0 10 z" class="ar"/></marker></defs>')


def txt(x, y, s, cls="t", anchor="middle"):
    return f'<text x="{x}" y="{y}" text-anchor="{anchor}" class="{cls}">{html.escape(s)}</text>'


def box(x, y, w, h, title, sub=(), cls="c-blue"):
    g = [f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="12" class="bx {cls}"/>']
    g.append(txt(x + w / 2, y + 24, title, "bt2"))
    for i, s in enumerate(sub):
        g.append(txt(x + w / 2, y + 44 + i * 17, s, "ts"))
    return "".join(g)


def arrow(x1, y1, x2, y2, label=None, dashed=False, lx=None, ly=None):
    d = ' stroke-dasharray="6 5"' if dashed else ""
    g = [f'<line x1="{x1}" y1="{y1}" x2="{x2}" y2="{y2}" class="ln"{d} marker-end="url(#ah)"/>']
    if label:
        g.append(txt(lx if lx is not None else (x1 + x2) / 2, ly if ly is not None else (y1 + y2) / 2 - 6, label, "al"))
    return "".join(g)


def diagram_sequence():
    W, H = 900, 700
    xs = [90, 270, 450, 630, 810]
    names = [("Parent", "the person"), ("Screen", "app or website"), ("Server", "Laravel on Render"),
             ("Database", "TiDB Cloud"), ("Gmail API", "sends emails")]
    cols = ["c-orange", "c-blue", "c-navy", "c-green", "c-red"]
    g = [svg_open(W, H, "Sequence of a parent signing up: screen, server, database and email")]
    for x, (n, s), c in zip(xs, names, cols):
        g.append(f'<line x1="{x}" y1="70" x2="{x}" y2="{H-20}" class="life"/>')
        g.append(f'<rect x="{x-68}" y="14" width="136" height="52" rx="12" class="bx {c}"/>')
        g.append(txt(x, 38, n, "bt2"))
        g.append(txt(x, 56, s, "ts"))
    y = 112
    steps = [
        (0, 1, "Fills the form and ticks the consent box", False),
        (1, 2, "Sends it over HTTPS (website: a page, app: JSON)", False),
        ("note", None, ["The server checks the form:", "names are letters only, password 8 or more,", "the email domain can receive mail, consent is ticked"], None),
        (2, 3, "Saves the account: password hashed, consent recorded", False),
        (2, 4, "Sends the confirmation link (signed, valid 60 minutes)", False),
        (4, 0, "The email arrives in the parent's inbox", True),
        (0, 2, "Parent taps the link: email marked confirmed", False),
        (2, 1, "Signs in: the website gets a cookie, the app a token", False),
        (1, 0, "Home screen shows. Next step: add a child", True),
    ]
    n = 0
    for a, b, label, dashed in steps:
        if a == "note":
            g.append(f'<rect x="{xs[2]-170}" y="{y-6}" width="340" height="68" rx="10" class="note"/>')
            for i, line in enumerate(label):
                g.append(txt(xs[2], y + 14 + i * 17, line, "tn" if i else "tnb"))
            y += 88
            n += 1
            g.append(f'<circle cx="{xs[2]-190}" cy="{y-54}" r="12" class="num"/>' + txt(xs[2] - 190, y - 49, str(n), "numt"))
            continue
        n += 1
        x1, x2 = xs[a], xs[b]
        off = 10 if x2 > x1 else -10
        g.append(arrow(x1 + off, y, x2 - off, y, None, dashed))
        mid = (x1 + x2) / 2
        g.append(txt(mid, y - 9, label, "ml"))
        g.append(f'<circle cx="{x1 + (off*2.2)}" cy="{y}" r="11" class="num"/>' + txt(x1 + off * 2.2, y + 4, str(n), "numt"))
        y += 62
    g.append("</svg>")
    return ('<figure class="fig"><figcaption><strong>Figure.</strong> What happens when a parent signs up: person, screen, server, database, email. '
            'Read it top to bottom. Dashed arrows are replies.</figcaption>' + "".join(g) + "</figure>")


def diagram_network():
    W, H = 900, 580
    g = [svg_open(W, H, "Network model: clients, the Laravel server on Render, the database and the three helper services")]
    g.append(box(40, 16, 380, 90, "Phone app", ["Expo / React Native, Android", "learner, teacher, parent", "signs in with a token"], "c-orange"))
    g.append(box(480, 16, 380, 90, "Browser", ["any device", "learner, teacher, parent, Admin", "signs in with a cookie session"], "c-orange"))
    g.append(arrow(230, 108, 330, 170, "HTTPS, JSON", lx=215, ly=146))
    g.append(arrow(670, 108, 570, 170, "HTTPS, pages", lx=690, ly=146))
    g.append(box(200, 172, 500, 122, "Render web service: tarabasa-ai",
                 ["Laravel 13 | PHP 8.4 | Docker | Singapore", "website pages + JSON API",
                  "our rules: levels, alerts, patterns, fit", "background workers: scoring and writing"], "c-navy"))
    g.append(box(740, 172, 140, 122, "GitHub", ["the code lives here", "push to the live", "branch and Render", "rebuilds the server"], "c-gray"))
    g.append(arrow(740, 233, 702, 233, "deploys", lx=721, ly=224))
    bottom = [
        (24, 150, "TiDB Cloud", ["MySQL compatible", "Singapore", "all the data"], "c-green", 262, 99, "SQL over TLS"),
        (200, 160, "Reading service", ["Vosk speech", "recognition", "scores a recording"], "c-blue", 340, 280, "HTTPS"),
        (380, 160, "Activity writer", ["Gemini language", "model drafts", "activities"], "c-blue", 430, 460, "HTTPS"),
        (560, 140, "Recommender", ["formulas that", "name the next", "skill to practise"], "c-blue", 540, 630, "HTTPS"),
        (730, 150, "Gmail API", ["sends the email:", "confirm link,", "notices"], "c-red", 640, 805, "HTTPS (OAuth)"),
    ]
    for x, w, t, sub, cls, ax, bx, lab in bottom:
        g.append(box(x, 400, w, 108, t, sub, cls))
        g.append(arrow(ax, 294, bx, 400, lab, lx=(ax + bx) / 2 + (0 if x != 24 else -10), ly=(294 + 400) / 2 - 4))
    g.append('<rect x="190" y="380" width="520" height="146" rx="14" class="grp"/>')
    g.append(txt(450, 548, "These three run on the teammate's Render account, free plan: they sleep after 15 idle minutes", "ts"))
    g.append(txt(450, 566, "GitHub Actions has a Keep TaraBasa awake button that pings all four addresses", "ts"))
    g.append("</svg>")
    return ('<figure class="fig"><figcaption><strong>Figure.</strong> The network model. Draw this from memory: clients, one server, one database, three helper services, Gmail, GitHub.</figcaption>'
            + "".join(g) + "</figure>")


def diagram_pipeline():
    steps = [
        ("Child records", "phone or browser"),
        ("Upload", "a ticket number comes back at once"),
        ("Queue", "a worker takes the job"),
        ("Reading service", "Vosk writes down the words"),
        ("Our rules", "line up, accept same sounds, leave out Not sure"),
        ("Save", "points, streak, badges, word bank, alerts"),
        ("Result", "the page asks every 2 seconds, then shows it"),
    ]
    h = ['<div class="pipe" role="list">']
    for i, (a, b) in enumerate(steps):
        h.append(f'<div class="ps" role="listitem"><span class="pn">{i+1}</span><strong>{html.escape(a)}</strong><small>{html.escape(b)}</small></div>')
        if i < len(steps) - 1:
            h.append('<span class="pa" aria-hidden="true">&rarr;</span>')
    h.append("</div>")
    return '<figure class="fig"><figcaption><strong>Figure.</strong> A normal reading on the website, at a glance.</figcaption>' + "".join(h) + "</figure>"


def diagram_ladder():
    cols = ["Letters", "Words", "Short sentences", "Sentences", "Short stories", "Stories", "Longer stories"]
    h = ['<figure class="fig"><figcaption><strong>Figure.</strong> One ladder, four views of it. The first check uses the 7 rungs; the child sees 4 steps; adults see Phil-IRI words; the engine stores 3 levels.</figcaption><div class="ladder">']
    h.append('<div class="lrow-label">Rung (first check)</div>')
    for i, c in enumerate(cols):
        h.append(f'<div class="lc rung" style="grid-column:{i+2}"><span>{i}</span>{c}</div>')

    def row(label, cells, cls):
        h.append(f'<div class="lrow-label">{label}</div>')
        col = 2
        for name, span, extra in cells:
            h.append(f'<div class="lc {cls} {extra}" style="grid-column:{col} / span {span}">{name}</div>')
            col += span

    row("Child sees (4 steps)", [("Letter Explorer", 1, "s1"), ("Word Builder", 1, "s2"), ("Sentence Reader", 2, "s3"), ("Story Reader", 3, "s4")], "step")
    row("Adults see (Phil-IRI word)", [("Non-reader", 1, "p0"), ("Frustration", 2, "p1"), ("Instructional", 2, "p2"), ("Independent", 2, "p3")], "phil")
    row("Stored level (engine)", [("Beginning", 3, "l1"), ("Developing", 2, "l2"), ("Proficient", 2, "l3")], "lvl")
    h.append('</div><p class="figsmall">The mapping from rung to Phil-IRI word is the team\'s own choice. DepEd\'s bands (97 and 90) apply to one reading of one passage.</p></figure>')
    return "".join(h)


def diagram_moving():
    return ('<figure class="fig"><figcaption><strong>Figure.</strong> The rule that moves a child. One reading never moves anyone.</figcaption>'
            '<div class="rule3">'
            '<div class="rc up"><b>Up one step</b><span>3 readings of 90 percent or more</span><span>from at least 2 different activities</span><span>among the last 5 since the last move</span><span>each long enough (4 to 45 words, by step)</span></div>'
            '<div class="rc stay"><b>Stay</b><span>everything else</span><span>one good or one bad reading changes nothing</span><span>free practice and games never count</span></div>'
            '<div class="rc down"><b>Down one step</b><span>2 readings in a row under 70 percent</span><span>on texts that were not too long for the child</span><span>never announced to the child</span></div>'
            '</div></figure>')


def diagram_breakeven():
    W, H = 740, 380
    x0, y0, x1, y1 = 70, 330, 560, 40  # plot area (room on the right for the line labels)
    xmax, ymax = 6, 9000

    def X(v): return x0 + (x1 - x0) * v / xmax
    def Y(v): return y0 - (y0 - y1) * v / ymax
    g = [svg_open(W, H, "Break-even chart: monthly revenue by number of schools against a flat cost of 3000 pesos")]
    for v in (0, 3000, 6000, 9000):
        g.append(f'<line x1="{x0}" y1="{Y(v)}" x2="{x1}" y2="{Y(v)}" class="grid"/>')
        g.append(txt(x0 - 8, Y(v) + 4, f"{v:,}", "ts", "end"))
    for s in range(0, 7):
        g.append(txt(X(s), y0 + 20, str(s), "ts"))
    g.append(txt((x0 + x1) / 2, y0 + 44, "Number of paying schools", "ts"))
    g.append(f'<text x="14" y="{(y0+y1)/2}" class="ts" text-anchor="middle" transform="rotate(-90 14 {(y0+y1)/2})">PHP per month</text>')
    g.append(f'<line x1="{X(0)}" y1="{Y(3000)}" x2="{X(6)}" y2="{Y(3000)}" class="cost" stroke-dasharray="7 5"/>')
    g.append(txt(X(0) + 8, Y(3000) - 9, "Cash cost about 3,000 a month (mostly fixed)", "al", "start"))
    for price, cls, lab, dy in ((1500, "r1", "PHP 1,500 per school", 4), (1000, "r2", "PHP 1,000", 4), (500, "r3", "PHP 500", 22)):
        g.append(f'<line x1="{X(0)}" y1="{Y(0)}" x2="{X(6)}" y2="{Y(price*6)}" class="rev {cls}"/>')
        g.append(txt(X(6) + 8, Y(price * 6) + dy, lab, "ts", "start"))
        be = 3000 / price
        g.append(f'<circle cx="{X(be)}" cy="{Y(3000)}" r="6" class="dot {cls}"/>')
    for s in (2, 3, 6):
        g.append(txt(X(s), Y(3000) + 30, f"{s} schools", "al"))
    g.append("</svg>")
    return ('<figure class="fig"><figcaption><strong>Figure.</strong> Break-even. Where a revenue line crosses the dashed cost line the project pays for itself: 2 schools at PHP 1,500, 3 at PHP 1,000, 6 at PHP 500. '
            'The prices are assumptions to test, not results.</figcaption>' + "".join(g) + "</figure>")


def journey_strip():
    scenes = ["Parent signs up", "Teacher verified", "Child logs in", "First check", "Home path",
              "A real reading", "Teacher sees", "AI writes", "Parent follows", "Admin keeps healthy"]
    h = ['<div class="strip" role="list">']
    for i, s in enumerate(scenes):
        h.append(f'<a class="st" role="listitem" href="#script--scene-{i+1}"><span>{i+1}</span>{html.escape(s)}</a>')
    h.append("</div>")
    return "".join(h)


def bmc_grid(table_rows):
    data = {}
    for r in table_rows[2:]:
        c = split_row(r)
        if len(c) >= 2:
            data[plain(c[0]).lower()] = (plain(c[0]), c[1], c[2] if len(c) > 2 else "")
    order = [
        ("key partners", "kp"), ("key activities", "ka"), ("key resources", "kr"), ("value propositions", "vp"),
        ("customer relationships", "cr"), ("channels", "ch"), ("customer segments", "cs"),
        ("cost structure", "co"), ("revenue streams", "re"),
    ]
    h = ['<div class="bmc">']
    for key, area in order:
        if key not in data:
            continue
        name, text, why = data[key]
        h.append(f'<div class="bc a-{area}"><h4>{html.escape(name)}</h4><p>{inline(text)}</p></div>')
    h.append("</div>")
    return "".join(h)


VISUALS = {
    ("script", "Part C."): lambda: journey_strip() + diagram_sequence(),
    ("flow", "2. The network model"): diagram_network,
    ("flow", "3. The main flows"): None,
    ("basis", "4. The child's four steps"): diagram_ladder,
    ("basis", "7. Moving a child"): diagram_moving,
    ("money", "4. Profit and break-even"): diagram_breakeven,
}
AFTER_H3 = {
    ("flow", "Flow D."): diagram_pipeline,
}


# ---------------------------------------------------------------- tab builder
def build_tab(tab, text, used, tabs_meta):
    blocks = parse_blocks(text)
    pid = tab["id"]
    # sections by h2
    intro, sections = [], []
    cur = None
    for b in blocks:
        if b["k"] == "h1":
            continue
        if b["k"] == "h2":
            cur = dict(title=b["raw"], blocks=[])
            sections.append(cur)
        elif cur is None:
            intro.append(b)
        else:
            cur["blocks"].append(b)

    menu = []  # (level, title, id)
    cards = []
    if pid == "start":
        cards.append(overview_cards(tabs_meta))
        sections = [s for s in sections if not s["title"].startswith("The files, in the order")]
    intro_html = render_blocks([b for b in intro if b["k"] != "hr"])
    if intro_html.strip():
        cards.append(f'<section class="card">{intro_html}</section>')

    for si, sec in enumerate(sections):
        sid = slug(sec["title"], used, pid)
        menu.append((1, sec["title"], sid))
        accordion = tab.get("accordion") or any(sec["title"].startswith(p) for p in tab.get("accordion_sections", ()))
        body = []
        # visuals
        for (tid, prefix), fn in VISUALS.items():
            if tid == pid and fn and sec["title"].startswith(prefix):
                body.append(fn())
        bl = [b for b in sec["blocks"] if b["k"] != "hr"]
        # BMC special case: replace first table whose header starts with Block
        if pid == "money" and sec["title"].startswith("1. The Business Model Canvas"):
            for b in bl:
                if b["k"] == "table" and split_row(b["raw"][0])[0].strip().lower() == "block":
                    b["html"] = bmc_grid(b["raw"]) + '<details class="more"><summary>The same canvas as a table, with the reason for each block</summary>' + b["html"] + "</details>"
        # network ASCII: put the text drawing under a disclosure
        if pid == "flow" and sec["title"].startswith("2. The network model"):
            for b in bl:
                if b["k"] == "pre":
                    b["html"] = '<details class="more"><summary>Text version of the drawing</summary>' + b["html"] + "</details>"
        # sentences and rules
        extra = ""
        if sec["title"].startswith("The 12 sentences"):
            extra = "sentences"
        if accordion:
            body.append(render_accordion(bl, pid, used))
        else:
            # group by h3
            groups = [dict(h=None, blocks=[])]
            for b in bl:
                if b["k"] == "h3":
                    groups.append(dict(h=b["raw"], blocks=[]))
                else:
                    groups[-1]["blocks"].append(b)
            for gi, grp in enumerate(groups):
                if grp["h"] is None:
                    if grp["blocks"]:
                        body.append(render_blocks_extra(grp["blocks"], extra))
                    continue
                gid = slug(grp["h"], used, pid)
                scene = re.match(r"^Scene (\d+)\.", grp["h"])
                if scene:
                    gid = f"script--scene-{scene.group(1)}"
                    used.add(gid)
                if tab.get("sub_h3"):
                    menu.append((2, grp["h"], gid))
                chip = f'<span class="chip">{scene.group(1)}</span>' if scene else ""
                title = re.sub(r"^Scene \d+\.\s*", "", grp["h"]) if scene else grp["h"]
                inner = render_blocks_extra(grp["blocks"], extra)
                for (tid, prefix), fn in AFTER_H3.items():
                    if tid == pid and grp["h"].startswith(prefix):
                        inner += fn()
                body.append(f'<div class="sub{" scene" if scene else ""}" id="{gid}" data-spy><h3>{chip}{inline(title)}</h3>{inner}</div>')
        num = re.match(r"^(\d+)\.\s+(.*)$", sec["title"])
        title_html = (f'<span class="chip">{num.group(1)}</span>{inline(num.group(2))}' if num else inline(sec["title"]))
        cards.append(f'<section class="card" id="{sid}" data-spy><h2>{title_html}</h2>{"".join(body)}</section>')
    return dict(menu=menu, cards="\n".join(cards))


def render_blocks_extra(blocks, extra):
    if not extra:
        return render_blocks(blocks)
    out = []
    for b in blocks:
        out.append(render_block(b, extra) if b["k"] == "list" else render_block(b))
    return "\n".join(out)


def render_accordion(blocks, pid, used):
    out, i, n = [], 0, len(blocks)
    while i < n:
        b = blocks[i]
        summary = None
        rest = []
        if b["k"] in ("h3", "q"):
            summary = b["raw"]
            i += 1
            while i < n and blocks[i]["k"] not in ("h3", "q"):
                rest.append(blocks[i])
                i += 1
        elif b["k"] == "p" and re.match(r"^\*\*[^*]+\?\*\*\s+\S", b["raw"]):
            m = re.match(r"^\*\*([^*]+\?)\*\*\s+(.*)$", b["raw"])
            summary = m.group(1)
            rest = [dict(k="p", raw=m.group(2), html="")]
            i += 1
        if summary is not None:
            m = re.match(r"^(\*\*)?([A-K]\d+)\.\s*(.*?)(\*\*)?$", summary.strip())
            tagtxt = m.group(2) if m else ""
            sumtxt = m.group(3) if m else summary
            sid = slug(summary, used, pid)
            qn = f'<span class="qn">{html.escape(tagtxt)}</span>' if tagtxt else ""
            out.append(f'<details class="qa" id="{sid}"><summary>{qn}<span class="qt">{inline(sumtxt.strip("*"))}</span></summary>'
                       f'<div class="ans">{render_blocks(rest)}</div></details>')
            continue
        out.append(render_block(b))
        i += 1
    return "\n".join(out)


def overview_cards(tabs_meta):
    h = ['<section class="card"><h2><span class="chip">&rarr;</span>Your study path: open the tabs in this order</h2><div class="pathgrid">']
    for t in tabs_meta[1:]:
        h.append(
            f'<a class="pc" href="#{t["id"]}"><span class="pcn">{t["num"]}</span>'
            f'<span class="pct">{html.escape(t["label"])}</span>'
            f'<span class="pcd">{html.escape(t["tag"])}</span>'
            f'<span class="pcm">about {t["mins"]} minutes</span></a>'
        )
    h.append("</div></section>")
    return "".join(h)


# ---------------------------------------------------------------- page
def font_face():
    def b64(name):
        p = ROOT / "public" / "fonts" / name
        return base64.b64encode(p.read_bytes()).decode("ascii") if p.exists() else None

    faces = []
    for fam, wt, f in (("Barlow Semi Condensed", "600", "barlow-semi-condensed-latin-600.woff2"),
                       ("Barlow Semi Condensed", "700", "barlow-semi-condensed-latin-700.woff2")):
        d = b64(f)
        if d:
            faces.append(f"@font-face{{font-family:'{fam}';font-style:normal;font-weight:{wt};font-display:swap;src:url(data:font/woff2;base64,{d}) format('woff2')}}")
    d = b64("inter-latin-400-800.woff2")
    if d:
        faces.append(f"@font-face{{font-family:'Inter';font-style:normal;font-weight:400 800;font-display:swap;src:url(data:font/woff2;base64,{d}) format('woff2')}}")
    return "\n".join(faces)


CSS = r"""
:root{--bg:#eef1f6;--card:#fff;--fg:#14202e;--muted:#5b6b7c;--line:#dfe5ec;--soft:#f4f6fa;--navy:#12263a;--orange:#e07a10;--orange2:#fff3e3;--green:#2f9e44;--green2:#e8f6ec;--blue:#1f5fa8;--blue2:#e8f0fa;--red:#d92d20;--red2:#fdeceb;--code:#eef1f5;--shadow:0 1px 2px rgba(18,38,58,.06),0 6px 18px rgba(18,38,58,.05);--tabs-h:64px}
@media (prefers-color-scheme:dark){:root{--bg:#0e141b;--card:#16202a;--fg:#e8edf2;--muted:#9fb0c0;--line:#2a3846;--soft:#1b2733;--navy:#e8edf2;--orange:#f1913f;--orange2:#2b2013;--green:#5ccf80;--green2:#12261a;--blue:#7db4ee;--blue2:#14222f;--red:#ff7a70;--red2:#2c1715;--code:#1c2833;--shadow:none;color-scheme:dark}}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{margin:0;background:var(--bg);color:var(--fg);font:16px/1.65 'Inter',"Segoe UI",system-ui,sans-serif}
h1,h2,h3,h4,.pill,.pcn,.pct,.chip,.bt2,.bt{font-family:'Barlow Semi Condensed','Segoe UI',system-ui,sans-serif}
.top{max-width:1240px;margin:0 auto;padding:22px clamp(16px,3vw,32px) 8px}
.top h1{margin:0;font-size:clamp(28px,4vw,40px);line-height:1.1;font-weight:700;letter-spacing:-.01em}
.top p{margin:8px 0 0;color:var(--muted);max-width:70ch}
.legend{display:flex;flex-wrap:wrap;gap:6px 8px;margin-top:10px;align-items:center;font-size:13px;color:var(--muted)}
nav.tabs{position:sticky;top:0;z-index:20;background:var(--bg);border-bottom:1px solid var(--line);padding:4px 0}
nav.tabs .in{max-width:1240px;margin:0 auto;padding:10px clamp(16px,3vw,32px);display:flex;gap:10px;overflow-x:auto;scrollbar-width:thin}
a.pill{flex:none;display:inline-flex;align-items:center;gap:7px;padding:8px 13px;border-radius:999px;background:var(--card);border:1px solid var(--line);color:var(--fg);text-decoration:none;font-weight:700;font-size:16px;white-space:nowrap}
a.pill b{display:inline-grid;place-items:center;width:22px;height:22px;border-radius:50%;background:var(--soft);font-size:13px;color:var(--muted)}
a.pill:hover{border-color:var(--orange)}
a.pill[aria-selected=true]{background:var(--navy);color:var(--bg);box-shadow:0 0 0 3px var(--bg),0 0 0 5px var(--orange)}
a.pill[aria-selected=true] b{background:var(--orange);color:#fff}
main{max-width:1240px;margin:0 auto;padding:18px clamp(16px,3vw,32px) 80px}
.panel[hidden]{display:none}
.phead{display:flex;gap:16px;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;margin:6px 0 16px}
.phead h2{margin:0;font-size:clamp(24px,3vw,32px);line-height:1.15}
.phead p{margin:6px 0 0;color:var(--muted);max-width:70ch}
.meta{display:flex;gap:8px;flex-wrap:wrap}
.meta span{background:var(--card);border:1px solid var(--line);border-radius:999px;padding:3px 12px;font-size:13px;color:var(--muted);font-weight:600}
.layout{display:grid;grid-template-columns:250px minmax(0,1fr);gap:22px;align-items:start}
.sidemenu{position:sticky;top:calc(var(--tabs-h) + 14px);max-height:calc(100vh - var(--tabs-h) - 30px);overflow:auto;background:var(--card);border:1px solid var(--line);border-radius:14px;padding:12px;box-shadow:var(--shadow)}
.sidemenu h5{margin:2px 6px 8px;font:700 12px 'Barlow Semi Condensed',sans-serif;text-transform:uppercase;letter-spacing:.08em;color:var(--muted)}
.sidemenu a{display:block;padding:6px 10px;border-radius:8px;color:var(--fg);text-decoration:none;font-size:14px;line-height:1.35}
.sidemenu a.l2{padding-left:22px;font-size:13px;color:var(--muted)}
.sidemenu a:hover{background:var(--soft)}
.sidemenu a.on{background:var(--orange2);color:var(--fg);box-shadow:inset 3px 0 0 var(--orange);font-weight:600}
.content{min-width:0}
.card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:22px clamp(16px,2.4vw,28px);margin:0 0 18px;box-shadow:var(--shadow);scroll-margin-top:calc(var(--tabs-h) + 16px)}
.card>h2{margin:0 0 12px;font-size:clamp(22px,2.4vw,27px);line-height:1.2;display:flex;gap:10px;align-items:center;text-wrap:balance}
.chip{display:inline-grid;place-items:center;min-width:34px;height:34px;padding:0 8px;border-radius:999px;background:var(--orange);color:#fff;font-weight:700;font-size:17px;flex:none}
.sub{margin:22px 0 0;padding-top:16px;border-top:1px dashed var(--line);scroll-margin-top:calc(var(--tabs-h) + 16px)}
.sub:first-of-type{border-top:0;padding-top:0;margin-top:8px}
.sub>h3,h3{margin:0 0 8px;font-size:21px;line-height:1.25;display:flex;gap:10px;align-items:center;color:var(--blue);text-wrap:balance}
.sub.scene>h3{color:var(--fg)}
.sub.scene .chip{background:var(--navy);color:var(--bg)}
h4{font-size:18px;margin:16px 0 6px}
p{margin:0 0 12px}
p.q{margin:16px 0 4px;font-weight:700}
ul,ol{margin:0 0 14px;padding-left:1.35em}
li{margin:4px 0}
li>ul,li>ol{margin:6px 0 4px}
ol:not(.sentences){list-style:none;counter-reset:n;padding-left:0}
ol:not(.sentences)>li{counter-increment:n;position:relative;padding-left:38px;margin:10px 0}
ol:not(.sentences)>li::before{content:counter(n);position:absolute;left:0;top:1px;width:27px;height:27px;border-radius:50%;background:var(--navy);color:var(--bg);display:grid;place-items:center;font:700 14px 'Barlow Semi Condensed',sans-serif}
ol.sentences{list-style:none;counter-reset:s;padding:0;display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:10px}
ol.sentences>li{counter-increment:s;background:var(--soft);border:1px solid var(--line);border-radius:12px;padding:10px 14px 10px 46px;position:relative;margin:0}
ol.sentences>li::before{content:counter(s);position:absolute;left:12px;top:10px;width:26px;height:26px;border-radius:50%;background:var(--orange);color:#fff;display:grid;place-items:center;font:700 14px 'Barlow Semi Condensed',sans-serif}
ul.beats{list-style:none;padding:0;display:grid;gap:10px;margin:10px 0 14px}
li.beat{display:grid;grid-template-columns:128px minmax(0,1fr);gap:14px;padding:12px 14px;border-radius:12px;border:1px solid var(--line);border-left-width:6px;margin:0;background:var(--soft)}
li.beat .bt{font-weight:700;font-size:15px;letter-spacing:.02em;text-transform:uppercase}
li.b-screen{border-left-color:var(--blue)}li.b-screen .bt{color:var(--blue)}
li.b-behind{border-left-color:var(--navy)}li.b-behind .bt{color:var(--fg)}
li.b-why{border-left-color:var(--orange)}li.b-why .bt{color:var(--orange)}
li.b-ends{border-left-color:var(--green)}li.b-ends .bt{color:var(--green)}
ul.check{list-style:none;padding-left:0}
label.chk{display:flex;gap:10px;align-items:flex-start;cursor:pointer}
label.chk input{width:20px;height:20px;margin-top:3px;accent-color:var(--green);flex:none}
label.chk input:checked+span{color:var(--muted);text-decoration:line-through}
hr{border:0;border-top:1px solid var(--line);margin:22px 0}
code{font:13.5px/1.4 Consolas,"Cascadia Mono",Menlo,monospace;background:var(--code);padding:1px 5px;border-radius:5px;overflow-wrap:anywhere}
.code{position:relative;margin:8px 0 14px}
.code pre{background:var(--code);border:1px solid var(--line);border-radius:10px;padding:12px 14px;padding-right:70px;overflow:auto;margin:0}
.code pre code{background:none;padding:0;white-space:pre}
button.copy{position:absolute;top:8px;right:8px;border:1px solid var(--line);background:var(--card);color:var(--fg);border-radius:8px;font:600 12px 'Inter',sans-serif;padding:3px 10px;cursor:pointer}
button.copy:hover{border-color:var(--orange)}
blockquote.say{margin:14px 0;padding:34px 20px 10px;background:var(--orange2);border:1px solid var(--line);border-left:6px solid var(--orange);border-radius:12px;position:relative}
blockquote.say p{margin:0 0 10px;font-size:17px}
blockquote.say .saytag{position:absolute;top:8px;left:18px;font:700 12px 'Barlow Semi Condensed',sans-serif;text-transform:uppercase;letter-spacing:.1em;color:var(--orange)}
blockquote.say.one{background:var(--green2);border-left-color:var(--green)}
blockquote.say.one .saytag{color:var(--green)}
.notes{background:var(--soft);border:1px dashed var(--line);border-radius:12px;padding:30px 18px 6px;position:relative;margin:12px 0 16px;font-size:15px}
.notes .notetag{position:absolute;top:8px;left:18px;font:700 12px 'Barlow Semi Condensed',sans-serif;text-transform:uppercase;letter-spacing:.1em;color:var(--muted)}
.tw{overflow-x:auto;margin:10px 0 16px;border:1px solid var(--line);border-radius:12px;background:var(--card)}
table{border-collapse:collapse;width:100%;font-size:14.5px}
th,td{padding:8px 12px;text-align:left;vertical-align:top;border-bottom:1px solid var(--line)}
th{background:var(--soft);font:700 14px 'Barlow Semi Condensed',sans-serif;letter-spacing:.03em}
tr:last-child td{border-bottom:0}
tbody tr:hover td{background:var(--soft)}
.lab{display:inline-block;font-size:11.5px;font-weight:700;padding:0 6px;border-radius:5px;border:1px solid var(--line);background:var(--soft);white-space:nowrap}
.lab.long{display:inline;white-space:normal;padding:0 4px;font-weight:600}
.lab-curriculum,.lab-verified{color:var(--green);border-color:var(--green)}
.lab-research,.lab-guideline,.lab-listed{color:var(--blue);border-color:var(--blue)}
.lab-team,.lab-assumption{color:var(--orange);border-color:var(--orange)}
.lab-fill,.lab-confirm,.lab-validate,.lab-recalled{color:var(--red);border-color:var(--red)}
a{color:var(--blue)}
details.more{margin:10px 0 16px;border:1px solid var(--line);border-radius:12px;padding:0 14px;background:var(--soft)}
details.more>summary{cursor:pointer;padding:10px 0;font-weight:700}
details.more[open]>summary{border-bottom:1px solid var(--line);margin-bottom:10px}
details.qa{border:1px solid var(--line);border-radius:12px;margin:8px 0;background:var(--card);overflow:hidden}
details.qa>summary{list-style:none;cursor:pointer;padding:12px 14px;display:flex;gap:12px;align-items:flex-start;font-weight:600}
details.qa>summary::-webkit-details-marker{display:none}
details.qa>summary::after{content:"+";margin-left:auto;font-size:22px;line-height:1;color:var(--orange);flex:none}
details.qa[open]>summary::after{content:"\2212"}
details.qa[open]>summary{background:var(--soft);border-bottom:1px solid var(--line)}
.qn{flex:none;min-width:34px;text-align:center;background:var(--navy);color:var(--bg);border-radius:8px;padding:0 6px;font:700 14px 'Barlow Semi Condensed',sans-serif;margin-top:2px}
.qt{min-width:0}
.ans{padding:12px 16px 4px}
.tools{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:0 0 14px}
.tools input[type=search]{flex:1 1 260px;min-width:0;padding:10px 14px;border:1px solid var(--line);border-radius:999px;background:var(--card);color:var(--fg);font:16px 'Inter',sans-serif}
.tools button{border:1px solid var(--line);background:var(--card);color:var(--fg);border-radius:999px;padding:9px 16px;font:700 15px 'Barlow Semi Condensed',sans-serif;cursor:pointer}
.tools button:hover{border-color:var(--orange)}
.tools .count{color:var(--muted);font-size:14px}
.pathgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:12px}
a.pc{display:flex;flex-direction:column;gap:4px;padding:14px 16px;border:1px solid var(--line);border-radius:14px;background:var(--soft);text-decoration:none;color:var(--fg);position:relative;padding-left:62px;min-height:118px}
a.pc:hover{border-color:var(--orange);background:var(--orange2)}
.pcn{position:absolute;left:14px;top:14px;width:38px;height:38px;border-radius:50%;background:var(--navy);color:var(--bg);display:grid;place-items:center;font-weight:700;font-size:20px}
.pct{font-weight:700;font-size:20px;line-height:1.1}
.pcd{font-size:13.5px;color:var(--muted);line-height:1.4}
.pcm{font-size:12.5px;font-weight:700;color:var(--orange);margin-top:auto}
.strip{display:flex;gap:8px;overflow-x:auto;padding:4px 2px 12px;margin:0 0 6px}
a.st{flex:none;display:flex;align-items:center;gap:8px;padding:7px 14px 7px 8px;border:1px solid var(--line);background:var(--soft);border-radius:999px;color:var(--fg);text-decoration:none;font:700 15px 'Barlow Semi Condensed',sans-serif;white-space:nowrap}
a.st span{display:grid;place-items:center;width:24px;height:24px;border-radius:50%;background:var(--navy);color:var(--bg);font-size:13px}
a.st:hover{border-color:var(--orange)}
figure.fig{margin:14px 0 18px;border:1px solid var(--line);border-radius:14px;background:var(--card);padding:12px 14px}
figure.fig figcaption{font-size:14px;color:var(--muted);margin:0 0 8px}
.figsmall{font-size:13px;color:var(--muted);margin:8px 0 0}
svg.dg{width:100%;height:auto;display:block;max-width:920px;margin:0 auto}
svg.dg .t,svg.dg .bt2,svg.dg .ts,svg.dg .ml,svg.dg .al,svg.dg .tn,svg.dg .tnb,svg.dg .numt{fill:var(--fg)}
svg.dg .bt2{font:700 16px 'Barlow Semi Condensed',sans-serif}
svg.dg .ts{font:12.5px 'Inter',sans-serif;fill:var(--muted)}
svg.dg .ml,svg.dg .al{font:600 13px 'Inter',sans-serif;paint-order:stroke;stroke:var(--card);stroke-width:6px;stroke-linejoin:round}
svg.dg .al{font-size:12px;fill:var(--muted)}
svg.dg .tn{font:13px 'Inter',sans-serif}
svg.dg .tnb{font:700 14px 'Inter',sans-serif}
svg.dg .numt{font:700 12px 'Barlow Semi Condensed',sans-serif;fill:#fff;text-anchor:middle}
svg.dg .num{fill:var(--orange)}
svg.dg .ln{stroke:var(--muted);stroke-width:2;fill:none}
svg.dg .ar{fill:var(--muted)}
svg.dg .life{stroke:var(--line);stroke-width:2;stroke-dasharray:4 6}
svg.dg .note{fill:var(--orange2);stroke:var(--orange);stroke-width:1.5}
svg.dg .grp{fill:none;stroke:var(--muted);stroke-width:1.5;stroke-dasharray:8 6}
svg.dg .bx{stroke-width:2}
svg.dg .c-blue{fill:var(--blue2);stroke:var(--blue)}
svg.dg .c-orange{fill:var(--orange2);stroke:var(--orange)}
svg.dg .c-green{fill:var(--green2);stroke:var(--green)}
svg.dg .c-red{fill:var(--red2);stroke:var(--red)}
svg.dg .c-navy{fill:var(--soft);stroke:var(--fg);stroke-width:2.5}
svg.dg .c-gray{fill:var(--soft);stroke:var(--muted)}
svg.dg .grid{stroke:var(--line);stroke-width:1}
svg.dg .cost{stroke:var(--red);stroke-width:2.5}
svg.dg .rev{stroke-width:3;fill:none}
svg.dg .r1{stroke:var(--orange)}svg.dg .r2{stroke:var(--blue)}svg.dg .r3{stroke:var(--muted)}
svg.dg .dot{stroke:var(--card);stroke-width:2}
svg.dg .dot.r1{fill:var(--orange)}svg.dg .dot.r2{fill:var(--blue)}svg.dg .dot.r3{fill:var(--muted)}
.pipe{display:flex;flex-wrap:wrap;gap:8px;align-items:stretch}
.ps{display:flex;flex-direction:column;gap:2px;padding:10px 12px;border:1px solid var(--line);border-radius:12px;background:var(--soft);flex:1 1 130px;max-width:190px;position:relative}
.ps strong{font:700 16px 'Barlow Semi Condensed',sans-serif}
.ps small{color:var(--muted);font-size:12.5px;line-height:1.35}
.pn{position:absolute;top:-9px;left:10px;background:var(--orange);color:#fff;border-radius:999px;padding:0 8px;font:700 12px 'Barlow Semi Condensed',sans-serif}
.pa{align-self:center;color:var(--muted);font-size:20px}
.ladder{display:grid;grid-template-columns:150px repeat(7,minmax(0,1fr));gap:6px;align-items:stretch;overflow-x:auto;min-width:0}
.lrow-label{font:700 13px 'Barlow Semi Condensed',sans-serif;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;align-self:center;grid-column:1}
.lc{border-radius:10px;padding:8px 6px;text-align:center;font-weight:600;font-size:13.5px;line-height:1.25;display:flex;align-items:center;justify-content:center;gap:4px;flex-direction:column;border:1px solid var(--line);min-height:46px}
.lc.rung{background:var(--soft)}.lc.rung span{background:var(--navy);color:var(--bg);border-radius:50%;width:20px;height:20px;display:grid;place-items:center;font-size:12px}
.lc.step{background:var(--orange2);border-color:var(--orange)}
.lc.phil{background:var(--blue2);border-color:var(--blue)}
.lc.lvl{background:var(--green2);border-color:var(--green)}
.rule3{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}
.rc{border-radius:14px;padding:14px 16px;border:1px solid var(--line);display:flex;flex-direction:column;gap:4px;font-size:14.5px}
.rc b{font:700 20px 'Barlow Semi Condensed',sans-serif}
.rc.up{background:var(--green2);border-color:var(--green)}.rc.up b{color:var(--green)}
.rc.stay{background:var(--soft)}
.rc.down{background:var(--red2);border-color:var(--red)}.rc.down b{color:var(--red)}
.bmc{display:grid;grid-template-columns:repeat(10,minmax(0,1fr));grid-template-areas:"kp kp ka ka vp vp cr cr cs cs" "kp kp kr kr vp vp ch ch cs cs" "co co co co co re re re re re";gap:8px;margin:10px 0 14px}
.bc{border:1px solid var(--line);border-radius:12px;padding:10px 12px;background:var(--soft);font-size:13px;line-height:1.45;min-width:0}
.bc h4{margin:0 0 4px;font-size:16px;color:var(--orange)}
.bc p{margin:0}
.a-kp{grid-area:kp}.a-ka{grid-area:ka}.a-kr{grid-area:kr}.a-vp{grid-area:vp;background:var(--orange2);border-color:var(--orange)}.a-cr{grid-area:cr}.a-ch{grid-area:ch}.a-cs{grid-area:cs}.a-co{grid-area:co}.a-re{grid-area:re;background:var(--green2);border-color:var(--green)}.a-re h4{color:var(--green)}
.pager{display:flex;justify-content:space-between;gap:10px;margin:22px 0 0;flex-wrap:wrap}
.pager a{display:inline-flex;gap:8px;align-items:center;padding:10px 18px;border:1px solid var(--line);border-radius:999px;background:var(--card);text-decoration:none;color:var(--fg);font:700 17px 'Barlow Semi Condensed',sans-serif}
.pager a:hover{border-color:var(--orange)}
.foot{margin-top:30px;color:var(--muted);font-size:13px}
.hide{display:none!important}
@media (max-width:1000px){
 .layout{display:block}
 .sidemenu{position:sticky;top:var(--tabs-h);z-index:10;max-height:none;border-radius:0 0 12px 12px;display:flex;gap:6px;overflow-x:auto;padding:8px;margin:0 0 14px}
 .sidemenu h5{display:none}
 .sidemenu a{flex:none;white-space:nowrap;border:1px solid var(--line);border-radius:999px;padding:5px 12px}
 .sidemenu a.l2{padding-left:12px}
 .bmc{grid-template-columns:1fr;grid-template-areas:"vp" "cs" "ch" "cr" "kp" "ka" "kr" "co" "re"}
 li.beat{grid-template-columns:1fr;gap:4px}
 .ladder{grid-template-columns:110px repeat(7,minmax(70px,1fr));min-width:640px}
 figure.fig{overflow-x:auto}
}
@media print{
 @page{size:A4;margin:14mm}
 body{background:#fff;color:#000;font-size:10.5pt}
 nav.tabs,.sidemenu,.pager,.tools,button.copy,.legend{display:none!important}
 .panel[hidden]{display:block!important}
 .panel{break-before:page}
 .panel:first-of-type{break-before:auto}
 .layout{display:block}
 .card{box-shadow:none;break-inside:auto}
 details.qa .ans{display:block}
 h2,h3{break-after:avoid}
 tr,figure{break-inside:avoid}
 a{color:#000;text-decoration:none}
}
"""

JS = r"""
(function(){
  var tabs=[].slice.call(document.querySelectorAll('a.pill'));
  var panels=[].slice.call(document.querySelectorAll('.panel'));
  var spy=null;
  function store(k,v){try{localStorage.setItem(k,v);}catch(e){}}
  function load(k){try{return localStorage.getItem(k);}catch(e){return null;}}
  function show(id,fromUser){
    var found=false;
    panels.forEach(function(p){var on=p.dataset.id===id;p.hidden=!on;if(on)found=true;});
    if(!found){id='start';panels[0].hidden=false;}
    tabs.forEach(function(t){t.setAttribute('aria-selected',t.dataset.tab===id?'true':'false');});
    store('tb-kit-tab',id);
    if(fromUser)window.scrollTo(0,0);
    var active=tabs.filter(function(t){return t.dataset.tab===id})[0];
    var bar=document.querySelector('nav.tabs .in');
    if(active&&bar){bar.scrollLeft=active.offsetLeft-(bar.clientWidth-active.offsetWidth)/2;}
    setupSpy(id);
  }
  function setupSpy(id){
    if(spy)spy.disconnect();
    if(!('IntersectionObserver' in window))return;
    var panel=document.querySelector('.panel[data-id="'+id+'"]');
    var links=panel.querySelectorAll('.sidemenu a');
    var map={};links.forEach(function(a){map[a.getAttribute('href').slice(1)]=a;});
    spy=new IntersectionObserver(function(es){
      es.forEach(function(e){
        if(e.isIntersecting&&map[e.target.id]){
          links.forEach(function(a){a.classList.remove('on');});
          map[e.target.id].classList.add('on');
          var sm=panel.querySelector('.sidemenu');
          var a=map[e.target.id];
          if(sm&&window.innerWidth>1000&&sm.scrollHeight>sm.clientHeight){sm.scrollTop=a.offsetTop-sm.clientHeight/3;}
          else if(sm&&window.innerWidth<=1000){sm.scrollLeft=a.offsetLeft-sm.clientWidth/3;}
        }
      });
    },{rootMargin:'-120px 0px -70% 0px'});
    panel.querySelectorAll('[data-spy]').forEach(function(el){spy.observe(el);});
  }
  function route(fromUser){
    var h=decodeURIComponent(location.hash.replace(/^#/,''));
    if(!h){show(load('tb-kit-tab')||'start',false);return;}
    if(tabs.some(function(t){return t.dataset.tab===h;})){show(h,fromUser);return;}
    var el=document.getElementById(h);
    if(el){var p=el.closest('.panel');if(p){show(p.dataset.id,false);if(el.tagName==='DETAILS')el.open=true;setTimeout(function(){el.scrollIntoView({behavior:'smooth',block:'start'});},30);}}
  }
  window.addEventListener('hashchange',function(){route(true);});
  try{history.scrollRestoration='manual';}catch(e){}
  route(false);
  if(!document.getElementById(decodeURIComponent(location.hash.replace(/^#/,'')))||location.hash.replace(/^#/,'')===''){window.scrollTo(0,0);}

  // copy buttons
  document.addEventListener('click',function(e){
    var b=e.target.closest('button.copy');if(!b)return;
    var t=b.parentNode.querySelector('pre').innerText;
    function done(){var o=b.textContent;b.textContent='Copied';setTimeout(function(){b.textContent=o;},1200);}
    function fallback(){var ta=document.createElement('textarea');ta.value=t;document.body.appendChild(ta);ta.select();try{document.execCommand('copy');done();}catch(x){}document.body.removeChild(ta);}
    if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(t).then(done,fallback);}else{fallback();}
  });

  // saved checklist
  var boxes=[].slice.call(document.querySelectorAll('label.chk input'));
  boxes.forEach(function(b,i){var k='tb-kit-chk-'+i;if(load(k)==='1')b.checked=true;b.addEventListener('change',function(){store(k,b.checked?'1':'0');});});

  // search and open/close
  [].slice.call(document.querySelectorAll('.tools')).forEach(function(tools){
    var panel=tools.closest('.panel');
    var input=tools.querySelector('input');
    var count=tools.querySelector('.count');
    var kind=tools.dataset.kind;
    function run(){
      var q=(input.value||'').toLowerCase().trim();var shown=0;
      if(kind==='questions'){
        panel.querySelectorAll('details.qa').forEach(function(d){var ok=!q||d.textContent.toLowerCase().indexOf(q)>-1;d.classList.toggle('hide',!ok);if(ok)shown++;if(q&&ok)d.open=true;});
        panel.querySelectorAll('section.card').forEach(function(c){var any=c.querySelector('details.qa:not(.hide)');c.classList.toggle('hide',!!q&&!any);});
        count.textContent=shown+' question'+(shown===1?'':'s')+(q?' match':'');
      }else{
        panel.querySelectorAll('tbody tr').forEach(function(r){var ok=!q||r.textContent.toLowerCase().indexOf(q)>-1;r.classList.toggle('hide',!ok);if(ok)shown++;});
        panel.querySelectorAll('.tw').forEach(function(w){var any=w.querySelector('tbody tr:not(.hide)');w.classList.toggle('hide',!!q&&!any);});
        count.textContent=shown+' row'+(shown===1?'':'s')+(q?' match':'');
      }
    }
    input.addEventListener('input',run);run();
    var o=tools.querySelector('[data-open]'),c=tools.querySelector('[data-close]');
    if(o)o.addEventListener('click',function(){panel.querySelectorAll('details.qa:not(.hide)').forEach(function(d){d.open=true;});});
    if(c)c.addEventListener('click',function(){panel.querySelectorAll('details.qa').forEach(function(d){d.open=false;});});
  });
  window.addEventListener('beforeprint',function(){document.querySelectorAll('details').forEach(function(d){d.open=true;});});
})();
"""


def main():
    used = set(t["id"] for t in TABS)
    meta = [dict(t) for t in TABS]
    pills, panels = [], []
    for idx, t in enumerate(TABS):
        f = HERE / t["file"]
        if not f.exists():
            print(f"Missing {t['file']}", file=sys.stderr)
            sys.exit(1)
        built = build_tab(t, f.read_text(encoding="utf-8"), used, meta)
        pills.append(f'<a class="pill" role="tab" href="#{t["id"]}" data-tab="{t["id"]}" aria-selected="false"><b>{t["num"]}</b>{html.escape(t["label"])}</a>')
        menu_html = ['<h5>On this tab</h5>']
        for level, title, sid in built["menu"]:
            label = re.sub(r"^Scene (\d+)\.\s*", r"Scene \1: ", plain(title))
            menu_html.append(f'<a class="l{level}" href="#{sid}">{html.escape(label)}</a>')
        tools = ""
        if t.get("search"):
            kind = t["search"]
            tools = (f'<div class="tools" data-kind="{kind}"><input type="search" placeholder="Search {kind}...">'
                     + ('<button type="button" data-open>Open all</button><button type="button" data-close>Close all</button>' if kind == "questions" else "")
                     + '<span class="count"></span></div>')
        prev_t = TABS[idx - 1] if idx > 0 else None
        next_t = TABS[idx + 1] if idx < len(TABS) - 1 else None
        pager = '<div class="pager">' + (f'<a href="#{prev_t["id"]}">&larr; {html.escape(prev_t["label"])}</a>' if prev_t else "<span></span>") \
            + (f'<a href="#{next_t["id"]}">{html.escape(next_t["label"])} &rarr;</a>' if next_t else "<span></span>") + "</div>"
        side = f'<aside class="sidemenu" aria-label="On this tab">{"".join(menu_html)}</aside>' if len(built["menu"]) > 1 else ""
        layout_cls = "layout" if side else "layout single"
        panels.append(
            f'<div class="panel" role="tabpanel" data-id="{t["id"]}" id="p-{t["id"]}" hidden>'
            f'<div class="phead"><div><h2>{html.escape(t["title"])}</h2><p>{html.escape(t["tag"])}</p></div>'
            f'<div class="meta"><span>Tab {t["num"]} of {len(TABS)-1}</span><span>about {t["mins"]} minutes</span></div></div>'
            f'<div class="{layout_cls}">{side}<div class="content">{tools}{built["cards"]}{pager}</div></div></div>'
        )
    today = date.today().strftime("%d %B %Y")
    legend = ('<div class="legend">Basis labels: '
              '<span class="lab lab-curriculum">[CURRICULUM]</span> from a DepEd document '
              '<span class="lab lab-research">[RESEARCH]</span> from a study '
              '<span class="lab lab-team">[TEAM CHOICE]</span> our own setting '
              '<span class="lab lab-fill">[FILL]</span> put your real number '
              '<span class="lab lab-confirm">[CONFIRM]</span> check the source</div>')
    page = f"""<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>TaraBasa AI Defense Study Kit</title>
<style>
{font_face()}
{CSS}
.layout.single{{display:block}}
</style>
</head>
<body>
<header class="top">
<h1>TaraBasa AI: defense study kit</h1>
<p>Understand it, do not memorize it. Nine tabs, one for each part of the defense. Start with tab 0, then go in order.</p>
{legend}
</header>
<nav class="tabs" role="tablist" aria-label="Study kit tabs"><div class="in">{"".join(pills)}</div></nav>
<main>
{"".join(panels)}
<p class="foot">Built {today} from the markdown files in docs/defense. Edit those files, then run build_kit_html.py again. To save as PDF: Ctrl + P, then Save as PDF (every tab prints in order).</p>
</main>
<script>{JS}</script>
</body>
</html>
"""
    OUT.write_text(page, encoding="utf-8")
    print(f"Wrote {OUT.name}: {len(TABS)} tabs, {OUT.stat().st_size // 1024} KB")


if __name__ == "__main__":
    main()
