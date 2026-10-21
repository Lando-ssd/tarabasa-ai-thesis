from kit import *

OUTDIR = "../out/figures"


def f02_architecture():
    W = 760
    d = Diagram("F02_architecture_tech_stack", "TaraBasa AI: system architecture and technology stack",
                W, 880, "Replaces the Technology Stack Diagram (Table 23) as a picture. No SMS gateway and no Google Speech-to-Text.")
    d.lane("l1", 14, 74, 732, 104, "1. People and their devices", "violet", header=26, size=12, align="left")
    people = [("Learner", "Android phone or tablet, or a browser"), ("Teacher", "Android phone, or a laptop browser"),
              ("Parent", "Android phone, or a browser"), ("Admin", "Laptop browser (website only)")]
    for i, (a, b) in enumerate(people):
        d.node(f"pe{i}", "proc", 26 + i * 180, 112, 168, 56, f"{a}\n{b}", size=11, tone="violet")
    d.lane("l2", 14, 198, 732, 112, "2. What people open", "blue", header=26, size=12, align="right")
    d.node("app1", "proc", 30, 234, 340, 66, "Phone app (version 0.5.4)\nReact Native with Expo SDK 57, React 19, TypeScript\nAndroid 7 or newer; scans QR cards; records audio", size=11, tone="blue")
    d.node("app2", "proc", 390, 234, 340, 66, "Website\nLaravel Blade pages with plain JavaScript\nChrome, Edge or Firefox; all four roles", size=11, tone="blue")
    d.lane("l3", 14, 332, 732, 112, "3. Application server (Render, Singapore, Docker image, PHP 8.4)", "teal", header=26, size=12, align="right")
    d.node("sv1", "proc", 28, 368, 168, 66, "Laravel 13\nweb pages and forms\nsession sign in", size=11, tone="teal")
    d.node("sv2", "proc", 208, 368, 168, 66, "JSON API for the phone\nSanctum tokens\ntoken fence by role", size=11, tone="teal")
    d.node("sv3", "proc", 388, 368, 168, 66, "Rule engine (services)\nprogression, patterns,\nfit, alerts, word bank", size=11, tone="teal")
    d.node("sv4", "proc", 568, 368, 166, 66, "Background worker\nqueue in the database\nwrites AI drafts", size=11, tone="teal")
    d.lane("l4", 14, 476, 360, 124, "4. Data", "gray", header=26, size=12, align="left")
    d.node("db1", "db", 36, 514, 160, 76, "TiDB Cloud Starter\nMySQL compatible\nAWS Singapore", size=11)
    d.node("db2", "db", 212, 514, 150, 76, "SQLite\nonly on a developer's\ncomputer", size=11, dashed=True)
    d.lane("l5", 388, 476, 358, 368, "5. Outside services (HTTPS)", "orange", header=26, size=12, align="right")
    ext = [("Reading service (Vosk)\nReading-api v3, FastAPI on Render\nscores a recording", 514),
           ("Activity writer\nGemini through a FastAPI service\nwrites draft activities", 590),
           ("Adaptive recommender\nformulas only, FastAPI on Render\nupdates the profile, suggests next", 666),
           ("Gmail API (OAuth)\nsends confirmation and reset emails", 742)]
    for i, (t, y) in enumerate(ext):
        d.node(f"ex{i}", "ext", 402, y, 330, 64, t, size=11)
    d.node("note1", "proc", 20, 616, 354, 106, "Safety: HTTPS, security headers, rate limits, role checks on every route, PINs and passwords hashed, nothing sensitive in the address, no card numbers stored.", size=11, tone="gray")
    d.node("note2", "proc", 20, 736, 354, 108, "Free hosting limits: the server and the three AI services sleep when idle (about a minute to wake). The page wakes them from the browser; the server waits for them.", size=11, tone="amber")
    # people -> apps (the Admin only uses the website)
    d.edge("pe0", "app1", "b", "t")
    d.edge("pe1", "app1", "b", "t")
    d.edge("pe2", "app1", "b", "t")
    d.edge("pe3", "app2", "b", "t")
    d.edge("app1", "sv2", "b", "t", bi=True, eo=0.7, no=0.5)
    d.edge("app2", "sv1", "b", "t", bi=True, eo=0.2, no=0.5)
    d.edge("sv3", "db1", "b", "t", via=[(446.8, 458), (116, 458)], bi=True, eo=0.35)
    d.edge("sv4", "ex0", "b", "t", via=[(651, 458), (517.5, 458)], bi=True, no=0.35)
    d.legend([("orange", "Outside service"), ("teal", "Our server"), ("blue", "What people open"), ("gray", "Data and notes")], 24, 868)
    return d.save(OUTDIR)


def f03_network_model():
    W = 760
    d = Diagram("F03_network_model", "TaraBasa AI: network model",
                W, 740, "Replaces Figure 42. Everything goes through the Internet over HTTPS; there is no SMS gateway.")
    for i, (a, tone) in enumerate([("Learner", "violet"), ("Teacher", "teal"), ("Parent", "blue"), ("Admin", "amber")]):
        d.node(f"u{i}", "proc", 30 + i * 180, 84, 150, 46, a, size=13, tone=tone, bold=True)
    d.node("isp", "box", 30, 190, 700, 42, "School or home network, ISP router, mobile data  >  the Internet (HTTPS with TLS)", size=13, tone="gray")
    for i in range(4):
        d.edge(f"u{i}", "isp", "b", "t", bi=True, no=0.12 + 0.25 * i)
    d.node("srv", "proc", 250, 290, 260, 70, "Application server\nLaravel 13 on Render (Singapore)\nwebsite, API, rules, worker", size=12, tone="teal", bold=True)
    d.edge("isp", "srv", "b", "t", bi=True)
    d.node("db", "db", 24, 448, 200, 96, "TiDB Cloud database\nMySQL compatible\nusers, learners, readings,\nactivities, alerts", size=11)
    d.edge("srv", "db", "b", "t", via=[(328, 404), (124, 404)], bi=True, label="SQL over TLS", lx=226, ly=392, eo=0.3)
    d.lane("ext", 256, 428, 480, 276, "Outside services (called by our server only)", "orange", header=26, size=12, align="left")
    d.node("pill", "pill", 280, 464, 432, 24, "HTTPS calls from the server (keys stay on the server)", size=11, tone="orange")
    d.edge("srv", "pill", "b", "t", via=[(440, 404), (440, 464)], bi=True, eo=0.7) if False else None
    d.edge("srv", "pill", "b", "t", via=[(450.2, 410), (450.2, 464)], bi=True, eo=0.77, no=0.395)
    svc = [("Reading service (Vosk)\nscores each recording", 276, 504), ("Activity writer (Gemini)\nwrites draft activities", 506, 504),
           ("Adaptive recommender\nupdates profile, suggests next", 276, 574), ("Gmail API\nconfirmation and reset emails", 506, 574),
           ("Google sign in\n(website, optional)", 276, 644)]
    for i, (t, x, y) in enumerate(svc):
        d.node(f"x{i}", "ext", x, y, 218, 54, t, size=11)
    d.edge("pill", "x0", "b", "t", arrow=False, dashed=True) if False else None
    d.node("n1", "note", 24, 560, 220, 120, "The browser may send one harmless\n'wake up' request to the three AI\nservices when a page opens,\nbecause free hosting lets them\nsleep. No key is on a device.", size=11)
    d.nodes["n1"]["tcol"] = C["muted"]
    d.legend([("teal", "Our server"), ("orange", "Outside service"), ("gray", "Network")], 24, 728)
    return d.save(OUTDIR)


def f04_network_topology():
    W = 760
    d = Diagram("F04_network_topology", "TaraBasa AI: network topology",
                W, 860, "Replaces Figure 43. Links as they are used today: school WiFi, home or mobile data, and the cloud.")
    d.lane("sch", 14, 74, 360, 196, "At the partner school (local area network)", "teal", header=26, size=12, align="left")
    d.node("lt", "proc", 28, 110, 108, 56, "Teacher laptop\nor phone", size=11, tone="teal")
    d.node("tb", "proc", 146, 110, 112, 56, "Learner Android\ntablets", size=11, tone="violet")
    d.node("ph", "proc", 268, 110, 96, 56, "Learner or\nteacher phone", size=11, tone="violet")
    d.node("r1", "proc", 118, 202, 150, 44, "School WiFi router", size=12, tone="gray")
    d.edge("lt", "r1", "b", "t", no=0.2)
    d.edge("tb", "r1", "b", "t")
    d.edge("ph", "r1", "b", "t", no=0.8)
    d.lane("home", 386, 74, 360, 196, "At home or in an office", "blue", header=26, size=12, align="left")
    d.node("pp", "proc", 398, 110, 108, 56, "Parent phone\nor laptop", size=11, tone="blue")
    d.node("cd", "proc", 516, 110, 112, 56, "Child's phone\nor tablet", size=11, tone="violet")
    d.node("ad", "proc", 638, 110, 98, 56, "Admin laptop\n(website only)", size=11, tone="amber")
    d.node("r2", "proc", 490, 202, 150, 44, "Home WiFi or mobile data", size=12, tone="gray")
    d.edge("pp", "r2", "b", "t", no=0.2)
    d.edge("cd", "r2", "b", "t")
    d.edge("ad", "r2", "b", "t", no=0.8)
    d.node("isp", "box", 150, 316, 460, 44, "Internet service providers and the public Internet", size=13, tone="gray")
    d.edge("r1", "isp", "b", "t", via=[(193, 288), (380, 288)], bi=True)
    d.edge("r2", "isp", "b", "t", via=[(565, 288), (380, 288)], bi=True)
    d.lane("cloud", 14, 394, 732, 424, "Cloud (data centres, mainly Singapore)", "orange", header=26, size=12, align="left")
    d.node("srv", "proc", 250, 440, 260, 64, "Render web service (Docker)\nLaravel 13: website, API, rules, queue worker", size=11, tone="teal", bold=True)
    d.edge("isp", "srv", "b", "t", bi=True)
    d.node("db", "db", 34, 570, 196, 84, "TiDB Cloud (AWS Singapore)\nMySQL compatible\n(SQLite on a developer PC)", size=11)
    d.node("e1", "ext", 267, 570, 226, 56, "Reading service (Vosk)\nRender free service", size=11)
    d.node("e2", "ext", 267, 640, 226, 56, "Activity writer (Gemini)\nRender free service", size=11)
    d.node("e3", "ext", 267, 710, 226, 56, "Adaptive recommender\nRender free service", size=11)
    d.node("e4", "ext", 516, 570, 214, 56, "Gmail API\n(emails to adults)", size=11)
    d.node("e5", "ext", 516, 640, 214, 56, "Google sign in\n(OAuth, optional)", size=11)
    d.edge("srv", "db", "b", "t", via=[(328, 540), (132, 540)], bi=True, eo=0.3)
    d.edge("srv", "e1", "b", "t", bi=True, eo=0.7, no=0.7, via=[(432, 540), (432, 570)]) if False else None
    d.edge("srv", "e1", "b", "t", via=[(375, 540), (375, 556)], bi=True) if False else None
    d.edge("srv", "e1", "b", "t", via=[(380, 540), (375, 540)], bi=True) if False else None
    d.edge("srv", "e1", "b", "t", bi=True)
    d.edge("srv", "e4", "b", "t", via=[(445, 540), (623, 540)], bi=True, eo=0.75)
    d.node("n1", "note", 30, 782, 700, 30, "The Admin works on the website only. There is no SMS channel: adults see updates inside the app and the website.", size=11)
    d.nodes["n1"]["tcol"] = C["muted"]
    d.legend([("teal", "Our server"), ("orange", "Outside service"), ("gray", "Network or data")], 24, 848)
    return d.save(OUTDIR)


def f15_rule_chain():
    W = 760
    d = Diagram("F15_adaptive_rule_chain", "Adaptive reading support: from a recording to a decision",
                W, 1060, "New figure. Every step is a written rule or a formula; there is no trained model. Numbers are the team's own settings.")
    steps = [
        ("s1", "io", "Recording from the phone or browser", "Audio file, up to 15 MB. A try shorter than 1 second or too quiet is not sent."),
        ("s2", "ext", "Reading service (Vosk) scores it", "Words heard with time and confidence, accuracy by word comparison, words a minute, voice score. The teammate's service."),
        ("s3", "proc", "Accept same sound words", "sun and son, mangoes and mangos, 2 and two, sand + castle. Near misses (hit and hat) stay mistakes."),
        ("s4", "proc", "Leave faint words 'not sure'", "Heard below 0.50 confidence and not matching: left out of the score and the word bank. At most 25 percent of the words."),
        ("s5", "proc", "Save the reading and word by word feedback", "Correct, skipped, a different word, mispronounced, said twice. Missed words go to the word bank."),
        ("s6", "dec", "Move the reading step?", "Up one step: 3 strong readings (90 percent or more, enough words) of at least 2 activities. Down one: 2 weak readings (under 70) in a row. Only readings after the last move count."),
        ("s7", "ext", "Adaptive recommender updates the profile", "Proficiency (moving average, 0.30), confidence (+0.08 per reading); picks the next competency and level."),
        ("s8", "proc", "Find the main mistake pattern", "Last 8 readings; 10 kinds (endings, vowels, first or last sounds, blends, small words, skipped words, longer words in pieces...). Needs 3 readings and 6 misses."),
    ]
    y = 76
    for i, (sid, kind, title, note) in enumerate(steps):
        h = 64
        d.node(sid, kind, 20, y, 330, h, title, size=12, bold=True)
        d.node(sid + "n", "note", 372, y + 2, 372, h, note, size=11)
        d.nodes[sid + "n"]["tcol"] = C["muted"]
        d.line([(350, y + h / 2), (368, y + h / 2)], color="gray_s", dashed=True)
        if i:
            d.edge(steps[i - 1][0], sid, "b", "t")
        y += 94
    last_bottom = 76 + 7 * 94 + 64
    bus = last_bottom + 44
    d.node("o_title", "note", 372, last_bottom + 6, 372, 24, "What the decision changes: every screen reads the same result", size=11, bold=True)
    d.nodes["o_title"]["tcol"] = C["ink"]
    outs = [("Learner", "Result, points, badges; 'Picked just for you'; games use missed words first", "violet"),
            ("Teacher", "Alerts, analytics, suggested activities, 'Did it work?', learner profile", "teal"),
            ("Parent", "Plain level, 'Practise at home', alerts", "blue"),
            ("Activity writer", "A short class basis: step, shared mistakes, words, interests", "orange")]
    for i, (a, b, tone) in enumerate(outs):
        d.node(f"o{i}", "proc", 20 + i * 184, bus + 24, 174, 100, f"{a}\n{b}", size=11, tone=tone)
        d.line([(20 + i * 184 + 87, bus), (20 + i * 184 + 87, bus + 24)], color="line")
    d.line([(185, last_bottom), (185, bus), (107 + 3 * 184, bus)], color="line")
    d.line([(107, bus), (185, bus)], color="line")
    d.h = bus + 24 + 100 + 52
    d.legend([("blue", "Rule in TaraBasa"), ("orange", "Outside service"), ("amber", "Decision")], 24, d.h - 14)
    return d.save(OUTDIR)


if __name__ == "__main__":
    for fn in (f02_architecture, f03_network_model, f04_network_topology, f15_rule_chain):
        print(fn.__name__, fn())
