from kit import *


def build():
    f = Flow("F01_system_flow", "TaraBasa AI: system flow chart",
             "One story from sign up to a learner reading, being scored, and the adults seeing the result. Read top to bottom.",
             [("p", "Parent", 1.0, "blue"), ("t", "Teacher and Admin", 1.15, "teal"),
              ("l", "Learner", 1.0, "violet"), ("s", "TaraBasa system and AI services", 1.4, "orange")],
             rows=14, row_h=70)
    N, E = f.N, f.E
    N("start", "term", "p", 0, "Start", w=70, h=26)
    N("p1", "proc", "p", 1, "Parent signs up and confirms email")
    N("s1", "ext", "s", 1, "Send confirmation link (Gmail API); create account")
    N("p2", "proc", "p", 2, "Add child: reading stage, 3 questions, PIN")
    N("s2", "proc", "s", 2, "Create learner, Learner Code and PIN (hashed)")
    N("t1", "proc", "t", 3, "Teacher signs up and confirms email")
    N("s3", "proc", "s", 3, "Create teacher account (status Pending)")
    N("t2", "proc", "t", 4, "Admin checks school and ID; approves", tone="amber")
    N("s4", "proc", "s", 4, "Teacher becomes Active; grades confirmed")
    N("t3", "proc", "t", 5, "Create class (one grade); add child by code or QR")
    N("s5", "proc", "s", 5, "Check grade and reading level; tell the parent")
    N("t4", "proc", "t", 6, "Ask AI to write activities; review; set level")
    N("s6", "ext", "s", 6, "AI activity writer (Gemini) saves drafts in the background")
    N("t5", "proc", "t", 7, "Assign to learner, class or reading group")
    N("s7", "proc", "s", 7, "Check that the child can read it; save assignment")
    N("l1", "proc", "l", 8, "Scan card or type code; enter PIN")
    N("s8", "proc", "s", 8, "Check code and PIN; first reading check if not done")
    N("l2", "io", "l", 9, "Read aloud and record")
    N("s9", "ext", "s", 9, "Reading service (Vosk) scores the recording")
    N("s10", "proc", "s", 10, "Rules: accuracy, step moves slowly, word bank, badges")
    N("l3", "proc", "l", 11, "See results, points and badges")
    N("s11", "ext", "s", 11, "Adaptive recommender updates profile and suggests next")
    N("s12", "proc", "s", 12, "Make alerts; update dashboards")
    N("p3", "proc", "p", 13, "See progress and tips")
    N("t6", "proc", "t", 13, "See alerts and analytics; assign next")

    E("start", "p1")
    E("p1", "s1", "r", "l")
    E("s1", "p2", "b", "t")
    E("p2", "s2", "r", "l")
    E("s2", "t1", "b", "t")
    E("t1", "s3", "r", "l")
    E("s3", "t2", "b", "t")
    E("t2", "s4", "r", "l")
    E("s4", "t3", "b", "t")
    E("t3", "s5", "r", "l")
    E("s5", "t4", "b", "t")
    E("t4", "s6", "r", "l")
    E("s6", "t5", "b", "t")
    E("t5", "s7", "r", "l")
    E("s7", "l1", "b", "t")
    E("l1", "s8", "r", "l")
    E("s8", "l2", "b", "t")
    E("l2", "s9", "r", "l")
    E("s9", "s10", "b", "t")
    E("s10", "l3", "b", "t")
    E("l3", "s11", "r", "l")
    E("s11", "s12", "b", "t")
    E("s12", "t6", "b", "t")
    E("s12", "p3", "b", "t")
    # loops back
    d = f.d
    tl = f.lane_x["t"][0] + 5
    E("t6", "t4", "l", "l", "next activity", via=[(tl, d.nodes["t6"]["y"] + d.nodes["t6"]["h"] / 2), (tl, d.nodes["t4"]["y"] + d.nodes["t4"]["h"] / 2)], dashed=True, lx=tl + 38, ly=d.nodes["t6"]["y"] - 30)
    ll = f.lane_x["l"][0] + 5
    E("l3", "l2", "l", "l", "read again", via=[(ll, d.nodes["l3"]["y"] + d.nodes["l3"]["h"] / 2), (ll, d.nodes["l2"]["y"] + d.nodes["l2"]["h"] / 2)], dashed=True, lx=ll + 30, ly=d.nodes["l3"]["y"] - 18)
    f.legend([("blue", "Step"), ("orange", "Outside service"), ("teal", "Read aloud"), ("amber", "Admin step")])
    return f.save()


if __name__ == "__main__":
    print(build())
