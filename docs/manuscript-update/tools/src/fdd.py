import os
from kit import *
from model import MODULES, STATUS_TONE, STATUS_WORD, counts, total_functions

ROOT_TEXT = "TaraBasa AI: AI-Powered Adaptive Reading Intervention System with Gamified Learning for Grade 1-3 Learners"
FDD_OUT = "../out/fdd"
UC_OUT = "../out/usecase"


def fdd_overview(variant="review"):
    """Left to right tree: root on the left, 11 modules on the right."""
    W = 760
    n = len(MODULES)
    pitch = 52
    H = 120 + n * pitch + 70
    review = variant == "review"
    d = Diagram(f"FDD_00_overview_{variant}", "Functional Decomposition Diagram: overview (version 2)",
                W, H, "Eleven modules. Modules 10 and 11 are new; every other module has changes." if review else "", show_title=True)
    d.node("root", "box", 14, 120 + (n * pitch) / 2 - 56, 156, 112, "TaraBasa AI\nAI-Powered Adaptive Reading Intervention System with Gamified Learning for Grade 1-3 Learners", size=11, tone="blue", bold=True)
    trunk_x = 196
    ys = []
    for i, m in enumerate(MODULES):
        y = 120 + i * pitch
        tone = {"NEW": "green", "UPDATED": "amber"}[m["change"]] if review else "blue"
        d.node(f"m{m['no']}", "box", 224, y, 262, 38, f"{m['no']}. {m['name']}", size=12, tone=tone, bold=True)
        ys.append(y + 19)
        d.line([(trunk_x, y + 19), (224, y + 19)])
        if review:
            c = counts(m)
            bits = []
            if c["new"]:
                bits.append(f"{c['new']} new")
            if c["upd"]:
                bits.append(f"{c['upd']} updated")
            if c["same"]:
                bits.append(f"{c['same']} unchanged")
            d.text(498, y + 24, f"{total_functions(m)} functions: " + ", ".join(bits), size=11, color="muted")
        else:
            d.text(498, y + 24, f"{total_functions(m)} functions", size=11, color="muted")
    d.line([(170, 120 + (n * pitch) / 2), (trunk_x, 120 + (n * pitch) / 2)])
    d.line([(trunk_x, ys[0]), (trunk_x, ys[-1])])
    if review:
        d.legend([("green", "New module"), ("amber", "Module with changes")], 24, H - 16)
    return d.save(FDD_OUT, formats=("svg", "drawio", "png"))


def fdd_module(mod, variant="review"):
    review = variant == "review"
    W = 760
    actors = mod["actors"]
    n = len(actors)
    gap = 8
    cw = min(340, (W - 28 - gap * (n - 1)) / n)
    total_w = cw * n + gap * (n - 1)
    x0 = (W - total_w) / 2
    size = 11
    fw = cw - 34
    # heights per function
    hts = []
    for _a, fns in actors:
        hs = []
        for (name, st) in fns:
            lines = wrap(name + (f" ({STATUS_WORD[st].lower()})" if review and st != "same" else ""), fw - 14, size)
            hs.append(max(30, len(lines) * size * 1.25 + 14))
        hts.append(hs)
    tallest = max(sum(h) + 10 * (len(h) - 1) for h in hts)
    top = 74
    actor_y = top + 78 + 38 + 46
    H = actor_y + 38 + 22 + tallest + (70 if review else 44)
    d = Diagram(f"FDD_{mod['no']:02d}_{mod['name'].lower().replace(' ', '_')}_{variant}", f"Module {mod['no']}: {mod['name']}", W, H,
                (f"{mod['change']} in version 2. {mod['why']}" if review else ""), show_title=review)
    if not review:
        top = 14
        actor_y = top + 78 + 38 + 46
        H = actor_y + 38 + 22 + tallest + 40
        d = Diagram(f"FDD_{mod['no']:02d}_{mod['name'].lower().replace(' ', '_')}_{variant}", f"Module {mod['no']}: {mod['name']}", W, H, "", show_title=False)
    tone_mod = {"NEW": "green", "UPDATED": "amber"}[mod["change"]] if review else "blue"
    d.node("root", "box", 40, top, W - 80, 40, ROOT_TEXT, size=11, tone="blue", bold=False)
    d.node("mod", "box", W / 2 - 150, top + 78, 300, 38, f"{mod['name']}", size=13, tone=tone_mod, bold=True)
    d.edge("root", "mod", "b", "t", arrow=False)
    busy = top + 78 + 38 + 22
    for i, (actor, fns) in enumerate(actors):
        cx = x0 + i * (cw + gap)
        ax = cx + cw / 2
        d.node(f"a{i}", "box", cx + 6, actor_y, cw - 12, 38, actor, size=12, tone="gray", bold=True)
        if n == 1:
            d.line([(W / 2, top + 78 + 38), (W / 2, actor_y)])
        else:
            d.line([(W / 2, top + 78 + 38), (W / 2, busy), (ax, busy), (ax, actor_y)])
        y = actor_y + 38 + 22
        fx = cx + 28
        first_y = y
        last_mid = y
        for (name, st), h in zip(fns, hts[i]):
            tone = STATUS_TONE[st] if review else "blue"
            label = name + (f" ({STATUS_WORD[st].lower()})" if review and st != "same" else "")
            d.node(f"f{i}_{y:.0f}", "fn", fx, y, fw, h, label, size=size, tone=tone)
            d.line([(cx + 12, y + h / 2), (fx, y + h / 2)])
            last_mid = y + h / 2
            y += h + 10
        d.line([(cx + 12, actor_y + 38), (cx + 12, last_mid)])
    if review:
        d.legend([("green", "NEW"), ("amber", "UPDATED"), ("blue", "UNCHANGED")], 24, H - 16)
    return d.save(FDD_OUT, formats=("svg", "drawio", "png"))


# ------------------------------------------------------------------------- use cases
UC_FIGS = [
    dict(name="UC_1_account", title="Use case diagram 1: Account Management and Administration", mods=[1, 11],
         left=["Learner", "Teacher"], right=["Parent", "Admin"]),
    dict(name="UC_2_learner_class", title="Use case diagram 2: Learner and Class Management", mods=[2, 3], left=["Teacher"], right=["Parent"]),
    dict(name="UC_3_activities", title="Use case diagram 3: Reading Activities and Open Repository", mods=[4, 5], left=["Teacher"], right=["Parent"]),
    dict(name="UC_4_reading", title="Use case diagram 4: Reading Assessment and Gamified Learning", mods=[6, 7], left=["Learner"], right=[]),
    dict(name="UC_5_dashboard", title="Use case diagram 5: Dashboards and Alerts", mods=[8, 9], left=["Teacher"], right=["Parent", "System"]),
    dict(name="UC_6_adaptive", title="Use case diagram 6: Adaptive Reading Support", mods=[10], left=["System"], right=["Teacher", "Parent", "Learner"]),
]


def usecase(fig):
    # collect unique use cases and which actors use them
    ucs = []
    index = {}
    for m in MODULES:
        if m["no"] not in fig["mods"]:
            continue
        for actor, fns in m["actors"]:
            for name, st in fns:
                if name not in index:
                    index[name] = len(ucs)
                    ucs.append(dict(name=name, actors=set(), st=st, mod=m["no"]))
                ucs[index[name]]["actors"].add(actor)
                if st != "same":
                    ucs[index[name]]["st"] = st
    # module 10 is a system module: show Teacher/Parent/Learner as users of its outputs
    if fig["mods"] == [10]:
        users = {"Place learner on the reading ladder": ["Learner"], "Score oral reading (Vosk service)": ["Learner"],
                 "Move reading step gradually": ["Learner", "Teacher"], "Update profile, recommend next (service)": ["Learner"],
                 "Find mistake patterns": ["Teacher", "Parent"], "Keep personal word bank": ["Learner"],
                 "Check 'can they read it?'": ["Teacher"], "Suggest activities for a group": ["Teacher"]}
        for u in ucs:
            for a in users.get(u["name"], []):
                u["actors"].add(a)
    W = 760
    pitch = 40
    n = len(ucs)
    boundary_x, boundary_w = 190, 380
    top = 76
    H = top + 40 + n * pitch + 70
    d = Diagram(f"{fig['name']}", fig["title"], W, H, "Use cases are the same functions as the FDD, one list for both.")
    d.node("sysbox", "note", boundary_x, top, boundary_w, 0, "")
    d.lane("sys", boundary_x, top, boundary_w, 30 + n * pitch + 10, "TaraBasa AI", "gray", header=26, size=12)
    uy0 = top + 40
    for i, u in enumerate(ucs):
        tone = STATUS_TONE[u["st"]]
        d.node(f"u{i}", "uc", boundary_x + 40, uy0 + i * pitch, boundary_w - 80, 32, u["name"], size=11, tone=tone)
    # actors
    def place(actors, left):
        k = len(actors)
        for j, a in enumerate(actors):
            ay = top + 50 + j * ((n * pitch - 60) / max(1, k)) + (n * pitch - 60) / max(1, k) / 2 - 40 if k else 0
            ax = 70 if left else W - 70
            d.node(f"act_{a}", "actor", ax - 40, ay, 80, 90, a, size=12)
            for i, u in enumerate(ucs):
                if a in u["actors"]:
                    nid = f"u{i}"
                    nn = d.nodes[nid]
                    ty = nn["y"] + nn["h"] / 2
                    if left:
                        d.line([(ax + 16, ay + 27), (nn["x"], ty)], color="muted", width=1.0)
                    else:
                        d.line([(ax - 16, ay + 27), (nn["x"] + nn["w"], ty)], color="muted", width=1.0)
    place(fig["left"], True)
    place(fig["right"], False)
    d.legend([("green", "NEW"), ("amber", "UPDATED"), ("blue", "UNCHANGED")], 24, H - 16)
    return d.save(UC_OUT)


def build_all():
    out = []
    out.append(fdd_overview("review"))
    out.append(fdd_overview("clean"))
    for m in MODULES:
        out.append(fdd_module(m, "review"))
        out.append(fdd_module(m, "clean"))
    for f in UC_FIGS:
        out.append(usecase(f))
    return out


if __name__ == "__main__":
    print(build_all())
