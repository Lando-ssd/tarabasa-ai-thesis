"""The system as one model: modules, actors and functions.
status: 'same' = already in manuscript v29 and still true; 'upd' = in v29 but the wording or behaviour changed;
        'new' = not in v29 at all.
This one list drives the FDD pictures, the use case pictures and the List of Modules table, so they cannot disagree.
"""

ACTORS_ORDER = ["Learner", "Teacher", "Parent", "Admin", "System"]

MODULES = [
    dict(no=1, name="Account Management", v29="Module 1: Account Management", change="UPDATED",
         why="Email must be confirmed before sign in; learners sign in by card or code; people can delete their own account. Admin work moved to Module 11.",
         actors=[
             ("Admin", [("Log in", "same"), ("Log out", "same")]),
             ("Teacher", [("Register account", "upd"), ("Confirm email by link", "upd"), ("Send a new confirmation link", "new"),
                          ("Log in (email, or Google)", "upd"), ("Reset forgotten password", "same"), ("View and edit profile", "same"),
                          ("Change password", "same"), ("Request a grade change", "new"), ("Delete own account", "new"), ("Log out", "same")]),
             ("Parent", [("Register account", "upd"), ("Confirm email by link", "upd"), ("Send a new confirmation link", "new"),
                         ("Log in (email, or Google)", "upd"), ("Reset forgotten password", "same"), ("View and edit profile", "same"),
                         ("Change password", "same"), ("Delete own account", "new"), ("Log out", "same")]),
             ("Learner", [("Scan learner card or type code", "upd"), ("Enter PIN", "upd"), ("View own profile details", "same"),
                          ("Access learner dashboard", "same"), ("Log out", "same")]),
         ]),
    dict(no=2, name="Learner Management", v29="Module 2: Learner Management", change="UPDATED",
         why="Learner Code has a check digit and a printed QR card; a Parent can reset a forgotten PIN and remove a child; more profile details are kept.",
         actors=[
             ("Parent", [("View learners", "same"), ("Add learner and generate learner code", "upd"), ("Set reading stage and answer 3 questions", "upd"),
                         ("Set learning style", "same"), ("Set home language, supports and interests", "new"), ("Set learner PIN", "same"),
                         ("Reset forgotten PIN", "new"), ("Link existing learner (code and PIN)", "upd"),
                         ("Print learner card with QR", "new"), ("Remove learner profile", "new")]),
         ]),
    dict(no=3, name="Class Management", v29="Module 3: Class Management", change="UPDATED",
         why="One grade per class; adding by the last 5 characters or a QR scan with a check before Add; reading groups; moving a learner; learner profile for the teacher.",
         actors=[
             ("Teacher", [("View classes by school year", "upd"), ("Create class (one grade only)", "upd"), ("Edit class", "same"),
                          ("Add learner by code or QR scan", "upd"), ("View roster and reading groups", "upd"),
                          ("View learner profile", "new"), ("Move learner to another class", "new"),
                          ("View class history", "same"), ("Manage learner promotion", "same")]),
         ]),
    dict(no=4, name="Reading Activity Management", v29="Module 4: Reading Activity", change="UPDATED",
         why="The Teacher chooses how many drafts per level, can write for a class, places each draft in a level, and assigning checks that the children can read it.",
         actors=[
             ("Teacher", [("View activities", "same"), ("Generate activity with AI (levels, background)", "upd"), ("Write for a class", "new"),
                          ("View generation credits", "same"), ("Edit activity", "same"), ("Place in a level, or reject", "upd"),
                          ("Assign activity (can they read it?)", "upd")]),
         ]),
    dict(no=5, name="Open Repository Management", v29="Module 5: Open Repository", change="UPDATED",
         why="Browse shows best matches first; paying is simulated (no real money); only the Parent browses, the Teacher only shares.",
         actors=[
             ("Teacher", [("Share activity", "same"), ("Set activity price", "same")]),
             ("Parent", [("Browse repository (best match first)", "upd"), ("Unlock activity", "same"), ("Rate activity", "same"),
                         ("Make payment (simulated)", "upd")]),
         ]),
    dict(no=6, name="Learner Reading Assessment", v29="Module 6: Learner Reading Assessment", change="UPDATED",
         why="Placement and diagnostic are one first reading check; practice with free tries and a microphone warm-up were added.",
         actors=[
             ("Learner", [("Do microphone warm-up", "new"), ("Take first reading check", "upd"), ("View adaptive recommendation", "upd"),
                          ("Start recommended or other activity", "upd"), ("Practise with free tries", "new"), ("Record reading", "same"),
                          ("Answer comprehension quiz", "same"), ("View result and word review", "upd"),
                          ("View reading path and progress", "upd")]),
         ]),
    dict(no=7, name="Gamified Learning", v29="Module 7: Gamified Learning", change="UPDATED",
         why="Four read-aloud practice games; 100 badges; day streak and weekly goal; text size.",
         actors=[
             ("Learner", [("View points and 100 badges", "upd"), ("View day streak and weekly goal", "upd"),
                          ("Play practice games (4)", "upd"), ("Practise difficult words", "same"), ("View bookshelf", "same"),
                          ("Re-read activity for free", "same"), ("Change text size", "new")]),
         ]),
    dict(no=8, name="Dashboard Analytics", v29="Module 8: Dashboard Analytics", change="UPDATED",
         why="Teacher overview, 'Did it work?' and a class report; Parent sees the child's reading step (the same four steps the child sees) in plain words, and tips to practise at home.",
         actors=[
             ("Teacher", [("View dashboard", "same"), ("Select learner or group", "same"), ("View analytics overview", "upd"),
                          ("View 'Did it work?'", "new"), ("Download class report (CSV)", "new"), ("View reading history", "same")]),
             ("Parent", [("View dashboard", "same"), ("Select learner", "same"), ("View progress and tips", "upd"),
                         ("View reading history", "same")]),
         ]),
    dict(no=9, name="Notification Management", v29="Module 9: Notification Management", change="UPDATED",
         why="Teacher alerts are worked out from readings and can be acted on; emails are sent for confirmation and reset only (no SMS).",
         actors=[
             ("Teacher", [("View alerts", "upd"), ("Give suggested activity from an alert", "new"), ("Mark alert handled", "new"),
                          ("Read notification", "same")]),
             ("Parent", [("View notifications", "same"), ("Read notification", "same"), ("Mark all as read", "new")]),
             ("System", [("Send confirmation and reset emails", "new")]),
         ]),
    dict(no=10, name="Adaptive Reading Support", v29="(not in v29)", change="NEW",
         why="The rule-based 'AI expert' was missing from the FDD: scoring clean-up, gradual reading steps, recommender, mistake patterns, word bank and 'can they read it' checks.",
         actors=[
             ("System", [("Place learner on the reading ladder", "new"), ("Score oral reading (Vosk service)", "new"),
                         ("Accept same sound words", "new"), ("Mark faint words 'not sure'", "new"),
                         ("Move reading step gradually", "new"), ("Update profile, recommend next (service)", "new"),
                         ("Find mistake patterns", "new"), ("Keep personal word bank", "new"),
                         ("Check 'can they read it?'", "new"), ("Suggest activities for a group", "new")]),
         ]),
    dict(no=11, name="System Administration and Privacy", v29="(Admin part of Module 1)", change="NEW",
         why="The Admin screens (approvals, accounts, system health, activity log, grade requests, deletion) are a module of their own.",
         actors=[
             ("Admin", [("View overview and waiting items", "new"), ("Approve or reject teacher (with reason)", "upd"),
                        ("Reopen rejected teacher", "new"), ("Approve or decline grade request", "new"),
                        ("Confirm email by hand", "new"), ("Deactivate or reactivate account", "upd"),
                        ("Delete account", "new"), ("View system health and check services", "new"),
                        ("Reconnect email sending", "new"), ("View activity log", "new")]),
         ]),
]

STATUS_TONE = {"new": "green", "upd": "amber", "same": "blue"}
STATUS_WORD = {"new": "NEW", "upd": "UPDATED", "same": "UNCHANGED"}


def counts(mod):
    c = {"new": 0, "upd": 0, "same": 0}
    for _a, fns in mod["actors"]:
        for _n, s in fns:
            c[s] += 1
    return c


def total_functions(mod):
    return sum(len(f) for _a, f in mod["actors"])


if __name__ == "__main__":
    tot = {"new": 0, "upd": 0, "same": 0}
    for m in MODULES:
        c = counts(m)
        for k in tot:
            tot[k] += c[k]
        print(m["no"], m["name"], total_functions(m), c)
    print(tot, sum(tot.values()))
