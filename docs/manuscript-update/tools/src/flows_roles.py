from kit import *


def t1():
    f = Flow("F08_teacher_workflow_1", "Teacher workflow (1 of 4): account, approval and grades",
             "Replaces Figures 4.1. A Pending teacher can sign in and look, but only an Active teacher touches real students.",
             [("t", "Teacher", 1.0, "teal"), ("s", "TaraBasa system and Admin", 1.25, "orange")], rows=10, row_h=82)
    N, E = f.N, f.E
    d = f.d
    N("st", "term", "t", 0, "Start", w=70, h=26)
    N("a", "proc", "t", 1, "Sign up: name, school, employee ID, grades, email")
    N("b", "proc", "s", 1, "Check the form and the email domain; create account (Pending); send confirmation link")
    N("c", "proc", "t", 2, "Open the confirmation link in the email")
    N("dd", "proc", "s", 2, "Mark the email confirmed (a wrong or old link offers a new one)")
    N("e", "proc", "t", 3, "Sign in with email and password")
    N("f", "dec", "s", 4, "Sign in allowed?", w=240, h=56)
    N("g", "proc", "t", 4, "See the reason: wrong password, email not confirmed, rejected (with the reason) or deactivated")
    N("h", "dec", "s", 5, "Approved by the Admin?", w=240, h=56)
    N("i", "proc", "t", 5, "Pending: view only, 2 free AI drafts")
    N("j", "proc", "s", 6, "Active: classes, activities, assigning, sharing")
    N("k", "term", "t", 6, "Dashboard", w=100, h=28)
    N("l", "proc", "t", 8, "Later: ask to add a grade; say who at school can confirm it")
    N("m", "proc", "s", 8, "Admin approves or declines; the teacher is told", tone="amber")
    N("n", "term", "s", 9, "Classes open for that grade", w=190, h=28)
    E("st", "a")
    E("a", "b", "r", "l")
    E("b", "c", "b", "t")
    E("c", "dd", "r", "l")
    E("dd", "e", "b", "t")
    E("e", "f", "r", "t")
    E("f", "g", "l", "r", "No")
    f.C("g", "e", "left", "try again", dashed=True, lane="t")
    E("f", "h", "b", "t", "Yes", lx=f.cx("s") + 16, ly=d.nodes["f"]["y"] + d.nodes["f"]["h"] + 12)
    E("h", "i", "l", "r", "No")
    E("h", "j", "b", "t", "Yes", lx=f.cx("s") + 16, ly=d.nodes["h"]["y"] + d.nodes["h"]["h"] + 12)
    E("j", "k", "l", "r")
    E("i", "k", "b", "t", dashed=True)
    E("k", "l", "b", "t")
    E("l", "m", "r", "l")
    E("m", "n", "b", "t")
    f.legend([("blue", "Step"), ("amber", "Admin step or decision"), ("orange", "Outside service")])
    return f.save()


def t2():
    f = Flow("F09_teacher_workflow_2", "Teacher workflow (2 of 4): classes and learners",
             "New. One class holds one grade only, even for a teacher who handles several grades.",
             [("t", "Teacher", 1.0, "teal"), ("s", "TaraBasa system", 1.25, "orange")], rows=11, row_h=82)
    N, E = f.N, f.E
    d = f.d
    N("st", "term", "t", 0, "Classes", w=90, h=26)
    N("a", "proc", "t", 1, "Create a class: name, one grade, section, school year")
    N("b", "dec", "s", 2, "Grade is one you handle?", w=250, h=56)
    N("c", "proc", "t", 2, "Ask the Admin to add the grade (see Teacher workflow 1)")
    N("d", "proc", "s", 3, "Save the class")
    N("e", "proc", "t", 4, "Add a learner: type the last 5 characters of the code, or scan the QR")
    N("f", "proc", "s", 4, "Check the code as it is typed (check digit); limit wrong tries")
    N("g", "dec", "s", 5, "Same grade, reading level fits, not in another class?", w=270, h=70)
    N("h", "proc", "t", 5, "See the reason; the Add button stays off")
    N("i", "proc", "s", 6, "Add the child to the class; tell the parents")
    N("j", "proc", "t", 7, "Open the roster: reading groups, tags, learner profile")
    N("k", "proc", "s", 7, "Group the class by reading level; show what the parent shared and what suits the child")
    N("l", "proc", "t", 8, "Move a learner to another class of the same grade")
    N("m", "proc", "s", 8, "Check the same rules again")
    N("n", "proc", "t", 9, "Release a learner for promotion; claim a released learner")
    N("o", "proc", "s", 9, "Set grade and class from the class that claims")
    N("p", "proc", "t", 10, "Past school years stay read only")
    E("st", "a")
    E("a", "b", "r", "t")
    E("b", "c", "l", "r", "No")
    E("b", "d", "b", "t", "Yes", lx=f.cx("s") + 16, ly=d.nodes["b"]["y"] + d.nodes["b"]["h"] + 12)
    E("d", "e", "b", "t")
    E("e", "f", "r", "l")
    E("f", "g", "b", "t")
    E("g", "h", "l", "r", "No")
    f.C("h", "e", "left", "", dashed=True, lane="t")
    E("g", "i", "b", "t", "Yes", lx=f.cx("s") + 16, ly=d.nodes["g"]["y"] + d.nodes["g"]["h"] + 12)
    E("i", "j", "b", "t")
    E("j", "k", "r", "l")
    E("k", "l", "b", "t")
    E("l", "m", "r", "l")
    E("m", "n", "b", "t")
    E("n", "o", "r", "l")
    E("o", "p", "b", "t")
    f.legend([("blue", "Step"), ("amber", "Decision")])
    return f.save()


def t3():
    f = Flow("F10_teacher_workflow_3", "Teacher workflow (3 of 4): activities",
             "Replaces the activity part of Figure 4.2. The Teacher decides the level; the AI only suggests it.",
             [("t", "Teacher", 1.0, "teal"), ("s", "TaraBasa system and AI services", 1.3, "orange")], rows=9, row_h=82)
    N, E = f.N, f.E
    d = f.d
    N("st", "term", "t", 0, "Activities", w=90, h=26)
    N("a", "proc", "t", 1, "Ask for activities: grade, skill, kind, how many per level; optionally for a class")
    N("b", "proc", "s", 1, "Check a credit is left; for a class, read its readings and write a short basis")
    N("c", "proc", "t", 2, "Keep working; a notice shows the progress")
    N("dd", "ext", "s", 2, "AI activity writer (Gemini) writes drafts in the background (about 2 minutes)")
    N("e", "proc", "t", 3, "Review each draft: edit, place in Easy, Medium or Hard, or reject")
    N("f2", "proc", "s", 3, "Save the Teacher's level next to the AI's suggestion; show why")
    N("g", "proc", "t", 4, "Assign to a learner, class, reading group or focus group")
    N("h", "dec", "s", 5, "Can these children read it? (grade, length)", w=280, h=70)
    N("i", "proc", "t", 5, "See the reason; choose another activity")
    N("j", "proc", "s", 6, "Save the assignment")
    N("k", "proc", "t", 7, "Share an approved activity to the Open Repository (free or paid)")
    N("l", "proc", "s", 7, "List it once; add 2 free credits to the teacher")
    N("m", "proc", "t", 8, "Later: see which activities helped the class (Analytics)")
    E("st", "a")
    E("a", "b", "r", "l")
    E("b", "c", "b", "t")
    E("c", "dd", "r", "l")
    E("dd", "e", "b", "t")
    E("e", "f2", "r", "l")
    E("f2", "g", "b", "t")
    E("g", "h", "r", "t")
    E("h", "i", "l", "r", "No")
    f.C("i", "g", "left", "", dashed=True, lane="t")
    E("h", "j", "b", "t", "Yes", lx=f.cx("s") + 16, ly=d.nodes["h"]["y"] + d.nodes["h"]["h"] + 12)
    E("j", "k", "b", "t")
    E("k", "l", "r", "l")
    E("l", "m", "b", "t")
    f.legend([("blue", "Step"), ("orange", "Outside service"), ("amber", "Decision")])
    return f.save()


def t4():
    cols = [
        dict(title="Analytics", tone="teal", steps=[
            ("a1", "proc", "Open Analytics"),
            ("a2", "proc", "See class numbers: readings this week, levels, words missed most"),
            ("a3", "proc", "See each assigned activity and what it produced"),
            ("a4", "proc", "See 'Did it work?': class activities against general ones"),
            ("a5", "proc", "Download the class report (CSV)"),
        ]),
        dict(title="Alerts", tone="amber", steps=[
            ("b1", "proc", "Open Alerts"),
            ("b2", "proc", "System makes alerts from real readings: needs support, ready to move up, starting to read, gone quiet, moved up"),
            ("b3", "proc", "Read the evidence and the suggested activity"),
            ("b5", "proc", "Act: give the suggested activity in one tap, or Mark handled"),
            ("b6", "proc", "A new reading opens the alert again"),
        ]),
        dict(title="Learner profile", tone="violet", steps=[
            ("c1", "proc", "Open a learner in a class"),
            ("c2", "proc", "See what the parent shared: language, interests, answers"),
            ("c3", "proc", "See where the child reads now: Phil-IRI level and step"),
            ("c4", "proc", "See what suits the child: teaching moves and main mistake pattern"),
        ]),
    ]
    c = Cols("F11_teacher_workflow_4", "Teacher workflow (4 of 4): monitoring",
             "New. Everything here is worked out by rules from the children's real readings (no trained model).",
             "Teacher dashboard", cols, "Back to dashboard")
    return c.save()


def p1():
    f = Flow("F12_parent_workflow_1", "Parent workflow (1 of 2): account and child profile",
             "Replaces Figures 5.1 and part of 5.2. A Parent cannot start a child's reading: the child signs in with the PIN.",
             [("p", "Parent", 1.0, "blue"), ("s", "TaraBasa system", 1.25, "orange")], rows=12, row_h=80)
    N, E = f.N, f.E
    d = f.d
    N("st", "term", "p", 0, "Start", w=70, h=26)
    N("a", "proc", "p", 1, "Sign up: name, email, password")
    N("b", "proc", "s", 1, "Check the form and email domain; send confirmation link")
    N("c", "proc", "p", 2, "Confirm the email, then sign in")
    N("dd", "proc", "s", 2, "Check the password; limit wrong tries")
    N("e", "proc", "p", 3, "Add a child: name, grade, character, reading stage, home language, interests")
    N("f", "proc", "s", 3, "Keep the profile; guess the starting step")
    N("g", "proc", "p", 4, "Answer 3 questions for the grade; set the 4 digit PIN")
    N("h", "proc", "s", 4, "Create the learner and Learner Code (with check digit); keep the PIN hashed")
    N("i", "proc", "p", 5, "Print or show the learner card with QR code")
    N("j", "proc", "s", 5, "Draw the QR here; it holds only the code, never the PIN")
    N("k", "proc", "p", 6, "Link an existing child: code and the child's PIN")
    N("l", "proc", "s", 6, "Check the PIN; limit wrong tries; tell the other guardians")
    N("m", "proc", "p", 7, "Child forgot the PIN: make a new one (password needed)")
    N("n", "proc", "s", 7, "Apply the rules (max 3 a day); end the child's sign ins; tell the other guardians")
    N("o", "proc", "p", 8, "Remove a child's profile (password needed)")
    N("q", "dec", "s", 9, "Another guardian linked?", w=240, h=56)
    N("r", "proc", "p", 9, "Only you are unlinked; the child stays")
    N("s", "proc", "s", 10, "Delete the child and every record")
    E("st", "a")
    E("a", "b", "r", "l")
    E("b", "c", "b", "t")
    E("c", "dd", "r", "l")
    E("dd", "e", "b", "t")
    E("e", "f", "r", "l")
    E("f", "g", "b", "t")
    E("g", "h", "r", "l")
    E("h", "i", "b", "t")
    E("i", "j", "r", "l")
    E("j", "k", "b", "t")
    E("k", "l", "r", "l")
    E("l", "m", "b", "t")
    E("m", "n", "r", "l")
    E("n", "o", "b", "t")
    E("o", "q", "r", "t")
    E("q", "r", "l", "r", "Yes")
    E("q", "s", "b", "t", "No", lx=f.cx("s") + 16, ly=d.nodes["q"]["y"] + d.nodes["q"]["h"] + 12)
    f.legend([("blue", "Step"), ("amber", "Decision")])
    return f.save()


def p2():
    cols = [
        dict(title="Progress", tone="blue", steps=[
            ("a1", "proc", "Open Progress; pick a child"),
            ("a2", "proc", "See the reading level in plain words, days in a row, weekly goal, badges"),
            ("a3", "proc", "See recent readings and the accuracy chart"),
            ("a4", "proc", "See 'Practise at home': tips from the main mistake pattern"),
        ]),
        dict(title="Activities", tone="teal", steps=[
            ("b1", "proc", "Open Activities; pick a child"),
            ("b2", "proc", "See the best matches first (level and mistakes)"),
            ("b4", "proc", "Free: unlock in one tap. Paid: confirm the price (payment is simulated)"),
            ("b5", "proc", "Rate 1 to 5 stars"),
            ("b6", "proc", "It appears in the child's list as extra practice"),
        ]),
        dict(title="Alerts and account", tone="amber", steps=[
            ("c1", "proc", "Open Alerts: summaries, needs attention, level confirmed, PIN changed"),
            ("c2", "proc", "Tap to mark read, or mark all read"),
            ("c3", "proc", "Profile: name, contact, password"),
            ("c4", "proc", "Delete my account (password needed): removes children only you guard"),
        ]),
    ]
    c = Cols("F13_parent_workflow_2", "Parent workflow (2 of 2): progress, activities and alerts",
             "Replaces the rest of Figure 5.2. The old 'Practice Reading' branch is removed: it is not in the system.",
             "Parent dashboard", cols, "Back to dashboard")
    return c.save()


def a1():
    cols = [
        dict(title="Approvals", tone="amber", steps=[
            ("a1", "proc", "Open Approvals: Waiting, Rejected, Grade requests"),
            ("a2", "proc", "See school, employee ID, grades and whether the email is confirmed"),
            ("a4", "proc", "Check the email is confirmed and the details are right; then Approve, or Reject with a reason the teacher will see"),
            ("a5", "proc", "Reopen a rejected teacher; approve or decline a grade request"),
        ]),
        dict(title="Accounts", tone="teal", steps=[
            ("b1", "proc", "Open Accounts; search or filter"),
            ("b2", "proc", "Deactivate or reactivate an account"),
            ("b3", "proc", "Confirm an email by hand (with a reason)"),
            ("b4", "proc", "Delete an account (with a reason; data privacy)"),
        ]),
        dict(title="System health and log", tone="orange", steps=[
            ("c1", "proc", "Open System health"),
            ("c2", "proc", "Check now: wake the three services, then test them"),
            ("c3", "proc", "See Working, Asleep or Needs you for each service and email"),
            ("c4", "proc", "Reconnect email sending if needed"),
            ("c5", "proc", "Open the Activity log: who did what, and why"),
        ]),
    ]
    c = Cols("F14_admin_workflow", "Admin workflow",
             "Replaces Figure 6.1. The Admin works on the website only; there is no profile page for the Admin.",
             "Admin sign in", cols, "Log out")
    return c.save()


if __name__ == "__main__":
    for fn in (t1, t2, t3, t4, p1, p2, a1):
        print(fn.__name__, fn())
