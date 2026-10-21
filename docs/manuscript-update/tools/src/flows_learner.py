from kit import *


def l1():
    """Learner workflow part 1: sign in and the first reading check."""
    f = Flow("F05_learner_workflow_1", "Learner workflow (1 of 3): sign in and first reading check",
             "Replaces Figure 3.1. The first check starts where the Parent's profile says the child is.",
             [("l", "Learner", 1.0, "violet"), ("s", "TaraBasa system", 1.25, "orange")], rows=11, row_h=84)
    N, E = f.N, f.E
    d = f.d
    N("st", "term", "l", 0, "Start", w=70, h=26)
    N("a", "proc", "l", 1, "Scan the learner card, or type the Learner Code")
    N("b", "proc", "l", 2, "Enter the 4 digit PIN")
    N("c", "dec", "s", 3, "Code and PIN correct?", w=250, h=56)
    N("e", "proc", "l", 3, "See a short message. After 5 wrong tries wait 15 minutes; the parent is told")
    N("f", "dec", "s", 4, "First reading check done?", w=260, h=56)
    N("g", "proc", "l", 4, "Hear the welcome; one word microphone warm-up")
    N("h", "proc", "s", 5, "Start at the step the Parent's profile suggests (letters up to stories)")
    N("i", "io", "l", 6, "Read the item aloud and record")
    N("j", "ext", "s", 6, "Reading service (Vosk) scores the recording")
    N("k", "dec", "s", 7, "Score 90 or more, below 70, or between?", w=250, h=74)
    N("m", "proc", "s", 8, "Go up one rung, down one rung, or stop here (at most 3 items)")
    N("n", "proc", "l", 9, "See a friendly result: no score, only a reading step")
    N("o", "proc", "s", 9, "Save the step and level; start the adaptive profile")
    N("dash", "term", "s", 10, "Dashboard", w=110, h=28)
    E("st", "a")
    E("a", "b")
    E("b", "c", "r", "t")
    E("c", "e", "l", "r", "No")
    f.C("e", "a", "left", "try again", dashed=True, lane="l")
    E("c", "f", "b", "t", "Yes", lx=f.cx("s") + 16, ly=d.nodes["c"]["y"] + d.nodes["c"]["h"] + 12)
    E("f", "g", "l", "r", "No")
    f.C("f", "dash", "right", "Yes", lane="s", lbl_dy=-14)
    E("g", "h", "b", "t")
    E("h", "i", "b", "t")
    E("i", "j", "r", "l")
    E("j", "k", "b", "t")
    E("k", "m", "b", "t")
    E("m", "i", "l", "b", "Next item", dashed=True, lx=f.lane_x["l"][0] + f.lane_x["l"][1] * 0.5, ly=d.nodes["m"]["y"] + d.nodes["m"]["h"] / 2 - 10)
    E("m", "n", "b", "t", "Finished", lx=f.cx("s") - 60, ly=d.nodes["m"]["y"] + d.nodes["m"]["h"] + 14)
    E("n", "o", "r", "l")
    E("o", "dash", "b", "t")
    f.legend([("blue", "Step"), ("orange", "Outside service"), ("amber", "Decision"), ("teal", "Read aloud")])
    return f.save()


def l2():
    """Learner workflow part 2: read an activity, scoring, adaptive update."""
    f = Flow("F06_learner_workflow_2", "Learner workflow (2 of 3): read an activity and get scored",
             "Replaces Figures 3.1 and 3.2. A reading step moves slowly: one strong or weak reading does not change it.",
             [("l", "Learner", 1.0, "violet"), ("s", "TaraBasa system and AI services", 1.35, "orange")], rows=16, row_h=80)
    N, E = f.N, f.E
    d = f.d
    N("st", "term", "l", 0, "Dashboard", w=100, h=28)
    N("a", "proc", "l", 1, "Tap Read to see the activities")
    N("b", "proc", "s", 1, "Gather assigned, unlocked and recommended activities; 'Picked just for you' goes first")
    N("c", "proc", "l", 2, "Choose an activity")
    N("d", "proc", "l", 3, "Listen to Tara read it, then practise (2 free tries)")
    N("e", "proc", "s", 3, "Score each try for feedback only; nothing is saved")
    N("f", "io", "l", 4, "Read for real and record")
    N("g", "dec", "s", 4, "Audio clear enough?", w=240, h=56)
    N("h", "proc", "s", 5, "Try again (up to 3 times), then ask the Teacher or Parent")
    N("i", "ext", "s", 6, "Reading service (Vosk): words, speed, voice")
    N("j", "proc", "s", 7, "Accept same sound words; leave faint words 'not sure'")
    N("k", "dec", "s", 8, "Story with questions?", w=240, h=56)
    N("l", "proc", "l", 8, "Answer the comprehension questions")
    N("n", "proc", "s", 9, "Mark any answers; save the reading; add to word bank; give points")
    N("o", "dec", "s", 10, "3 strong readings or 2 weak in a row?", w=290, h=62)
    N("p", "proc", "s", 11, "Move one reading step up or down")
    N("q", "ext", "s", 12, "Adaptive recommender updates the profile and picks what is next")
    N("r", "proc", "s", 13, "Award badges; make alerts for the Teacher and Parent")
    N("s", "proc", "l", 14, "See the result: words read, points, badges, word review")
    N("end", "term", "l", 15, "Back to dashboard", w=130, h=28)
    E("st", "a")
    E("a", "b", "r", "l")
    E("b", "c", "b", "t")
    E("c", "d")
    E("d", "e", "r", "l")
    E("e", "f", "b", "t")
    E("f", "g", "r", "l")
    E("g", "h", "b", "t", "No", lx=f.cx("s") + 16, ly=d.nodes["g"]["y"] + d.nodes["g"]["h"] + 12)
    E("h", "f", "l", "b", dashed=True)
    f.C("g", "i", "right", "Yes", lane="s")
    E("i", "j", "b", "t")
    E("j", "k", "b", "t")
    E("k", "l", "l", "r", "Yes")
    E("k", "n", "b", "t", "No", lx=f.cx("s") + 16, ly=d.nodes["k"]["y"] + d.nodes["k"]["h"] + 12)
    E("l", "n", "b", "l")
    E("n", "o", "b", "t")
    E("o", "p", "b", "t", "Yes", lx=f.cx("s") + 16, ly=d.nodes["o"]["y"] + d.nodes["o"]["h"] + 12)
    f.C("o", "q", "right", "No", lane="s")
    E("p", "q", "b", "t")
    E("q", "r", "b", "t")
    E("r", "s", "l", "t")
    E("s", "end")
    f.legend([("blue", "Step"), ("orange", "Outside service"), ("teal", "Read aloud"), ("amber", "Decision")])
    return f.save()


def l3():
    """Learner workflow part 3: practice games, bookshelf, rewards."""
    cols = [
        dict(title="Practice Games", tone="violet", steps=[
            ("g1", "proc", "Choose Balloon Pop, Word Train, Sentence Stars or Syllable Steps"),
            ("g2", "proc", "System offers the child's own missed words first; the start level follows the reading step"),
            ("g3", "io", "Read the word, row or sentence aloud"),
            ("g4", "ext", "Reading service checks it: heard, or try again (never 'wrong')"),
            ("g5", "proc", "2 tries each; 3 things a round; up to 3 rounds"),
            ("g6", "proc", "Save one game play (for badges only)"),
        ]),
        dict(title="My Bookshelf", tone="teal", steps=[
            ("b1", "proc", "Pick a story already read for real"),
            ("b2", "proc", "System allows only stories this child really finished"),
            ("b3", "io", "Read it aloud again, free"),
            ("b4", "ext", "Reading service checks it"),
            ("b5", "proc", "Show the word by word review"),
            ("b6", "proc", "Save nothing: no points, streak or level"),
        ]),
        dict(title="Rewards", tone="amber", steps=[
            ("r1", "proc", "Open My Badges, goal and reading path"),
            ("r2", "proc", "System works out which of the 100 badges are earned, from real readings and game plays"),
            ("r3", "proc", "Show the weekly goal (5 readings) and growth chart"),
            ("r4", "proc", "Show the day streak: days with a real reading in a row"),
            ("r5", "proc", "Show the reading path: the child's step of four"),
        ]),
    ]
    c = Cols("F07_learner_workflow_3", "Learner workflow (3 of 3): games, bookshelf and rewards",
             "New figure. Games and free re-reads never change points, streak or reading step.",
             "Dashboard", cols, "Back to dashboard")
    return c.save()


if __name__ == "__main__":
    for fn in (l1, l2, l3):
        print(fn())
