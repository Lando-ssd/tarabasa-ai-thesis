from kit import *

OUTDIR = "../out/figures"

T = {
    "users": ["id PK", "first_name, last_name", "email", "user_type (Teacher, Parent, Admin)", "status", "email_verified_at", "password (hashed)"],
    "teachers": ["id PK", "user_id FK users", "school_name", "employee_id", "status", "free_generation_credits", "grades_handled", "grades_requested"],
    "parents": ["id PK", "user_id FK users"],
    "classes": ["id PK", "teacher_id FK teachers", "name, section", "grade_level (one grade)", "school_year", "group_tag"],
    "learners": ["id PK", "learner_code (check digit)", "class_id FK classes", "name, grade_level", "pin (hashed)", "mastery_level, reading_rung", "points, streak", "competency_states (json)", "reading_stage, answers"],
    "parent_learners": ["id PK", "parent_id FK parents", "learner_id FK learners", "relationship", "is_creator"],
    "promotion_records": ["id PK", "learner_id FK learners", "released_by_teacher_id FK", "claimed_by_teacher_id FK", "next_grade, status"],
    "learner_badges": ["id PK", "learner_id FK learners", "badge_code", "earned_at"],
    "game_plays": ["id PK", "learner_id FK learners", "game", "level_reached", "played_at"],
    "personal_word_bank": ["id PK", "learner_id FK learners", "session_id FK sessions", "word", "mastery_status", "times_drilled"],
    "reading_sessions": ["id PK", "learner_id FK learners", "activity_id FK activities", "accuracy_percent, wcpm", "comprehension_score", "level_before, level_after", "rung_before, rung_after", "word_feedback (json)", "session_type, initiated_by"],
    "activities": ["id PK", "created_by_teacher_id FK", "grade_level", "competency, activity_type", "difficulty_tier (Teacher)", "ai_difficulty_tier", "title, passage_text", "status, purpose", "curriculum_code"],
    "activity_assignments": ["id PK", "activity_id FK activities", "learner_id FK (or)", "class_id FK (or)", "group_tag, reading_band", "assigned_by_teacher_id FK"],
    "open_repository_listings": ["id PK", "activity_id FK activities", "teacher_id FK teachers", "price_type, price", "listed_at"],
    "repository_unlocks": ["id PK", "listing_id FK listings", "parent_id FK parents", "learner_id FK learners", "amount_paid (simulated)"],
    "repository_ratings": ["id PK", "listing_id FK listings", "parent_id FK parents", "learner_id FK learners", "rating (1 to 5)", "comment"],
    "notifications": ["id PK", "recipient_user_id FK users", "learner_id FK learners", "type", "message, is_read"],
    "teacher_alert_actions": ["id PK", "teacher_id FK teachers", "learner_id FK learners", "kind", "handled_at"],
    "learner_pin_changes": ["id PK", "learner_id FK learners", "user_id FK users", "method", "changed_at"],
    "admin_actions": ["id PK", "admin_user_id FK users", "target_user_id FK users", "action", "detail, note"],
    "activity_generations": ["id PK", "teacher_id FK teachers", "class_id", "status", "levels (json)", "created_count"],
    "service_failures": ["id PK", "service", "status", "what", "created_at"],
    "data_erasures": ["id PK", "kind, role", "requested_by", "children_erased", "created_at (no names kept)"],
}
TONE = {"users": "blue", "teachers": "teal", "parents": "blue", "classes": "teal", "learners": "violet", "parent_learners": "blue",
        "promotion_records": "teal", "learner_badges": "amber", "game_plays": "amber", "personal_word_bank": "violet", "reading_sessions": "violet",
        "activities": "teal", "activity_assignments": "teal", "open_repository_listings": "orange", "repository_unlocks": "orange", "repository_ratings": "orange"}


def h_of(name):
    return 22 + 13 * len(T[name]) + 10


def erd():
    W = 800
    cw, gap = 170, 34
    xs = [9 + i * (cw + gap) for i in range(4)]
    grid = {
        "users": (0, 0), "teachers": (1, 0), "classes": (2, 0), "promotion_records": (3, 0),
        "parents": (0, 1), "parent_learners": (1, 1), "learners": (2, 1),
        "learner_badges": (0, 2), "game_plays": (1, 2), "personal_word_bank": (2, 2), "reading_sessions": (3, 2),
        "activity_assignments": (2, 3), "activities": (3, 3),
        "repository_unlocks": (2, 4), "open_repository_listings": (3, 4),
        "repository_ratings": (3, 5),
    }
    rows = {}
    for n, (c, r) in grid.items():
        rows.setdefault(r, []).append(n)
    ytop = {}
    y = 78
    for r in sorted(rows):
        ytop[r] = y
        y += max(h_of(n) for n in rows[r]) + 46
    core_bottom = y - 46
    strip_top = core_bottom + 56
    strip = ["notifications", "teacher_alert_actions", "learner_pin_changes", "admin_actions", "activity_generations", "service_failures", "data_erasures"]
    srows = [strip[:4], strip[4:]]
    sh = [max(h_of(n) for n in r) for r in srows]
    H = int(strip_top + 24 + sh[0] + 22 + sh[1] + 60)
    d = Diagram("F16_erd_version2", "Entity Relationship Diagram (version 2, from the real database)", W, H,
                "Replaces Figure 41. 23 tables made by the app: 14 of the 16 tables in version 29 plus 9 new ones (Curriculum Guide and Badge are not tables). The framework's own tables are left out.")
    pos = {}
    for n, (c, r) in grid.items():
        x, yy = xs[c], ytop[r]
        d.node(n, "ent", x, yy, cw, h_of(n), n, size=11, tone=TONE.get(n, "blue"), lines=T[n])
        pos[n] = (x, yy, cw, h_of(n))

    def mid(n, side):
        x, y, w, h = pos[n]
        return {"t": (x + w / 2, y), "b": (x + w / 2, y + h), "l": (x, y + h / 2), "r": (x + w, y + h / 2)}[side]

    def rel(a, b, ea, eb, la="1", lb="N", via=None):
        eo = 40 / pos[a][3] if ea in "lr" else 0.5
        no = 40 / pos[b][3] if eb in "lr" else 0.5
        d.edge(a, b, ea, eb, via=via, arrow=False, eo=eo, no=no)
        pa, pb = mid(a, ea), mid(b, eb)
        if ea in "lr":
            pa = (pa[0], pos[a][1] + 40)
        if eb in "lr":
            pb = (pb[0], pos[b][1] + 40)
        off = {"t": (8, -6), "b": (8, 14), "l": (-14, -5), "r": (6, -5)}
        d.text(pa[0] + off[ea][0], pa[1] + off[ea][1], la, 11, True, "red")
        d.text(pb[0] + off[eb][0], pb[1] + off[eb][1], lb, 11, True, "red")

    rel("users", "teachers", "r", "l", "1", "1")
    rel("users", "parents", "b", "t", "1", "1")
    rel("teachers", "classes", "r", "l", "1", "N")
    rel("classes", "learners", "b", "t", "1", "N")
    rel("parents", "parent_learners", "r", "l", "1", "N")
    rel("learners", "parent_learners", "l", "r", "1", "N")
    # learners -> promotion_records: right then up
    lx, ly, lw, lh = pos["learners"]
    px, py, pw, ph = pos["promotion_records"]
    d.edge("learners", "promotion_records", "r", "b", via=[(px + pw / 2, ly + lh / 2)], arrow=False)
    d.text(lx + lw + 6, ly + lh / 2 - 5, "1", 11, True, "red")
    d.text(px + pw / 2 + 8, py + ph + 14, "N", 11, True, "red")
    # learners -> children row 2 via bus
    busy = ytop[1] + max(h_of(n) for n in rows[1]) + 22
    for child in ("learner_badges", "game_plays", "personal_word_bank", "reading_sessions"):
        cx, cy, cwid, chh = pos[child]
        if child == "personal_word_bank":
            d.edge("learners", child, "b", "t", arrow=False)
        else:
            d.edge("learners", child, "b", "t", via=[(lx + lw / 2, busy), (cx + cwid / 2, busy)], arrow=False)
        d.text(cx + cwid / 2 + 8, cy - 6, "N", 11, True, "red")
    d.text(lx + lw / 2 + 8, ly + lh + 14, "1", 11, True, "red")
    rel("reading_sessions", "activities", "b", "t", "N", "1")
    rel("activities", "activity_assignments", "l", "r", "1", "N")
    rel("activities", "open_repository_listings", "b", "t", "1", "1")
    rel("open_repository_listings", "repository_unlocks", "l", "r", "1", "N")
    rel("open_repository_listings", "repository_ratings", "b", "t", "1", "N")
    # note about the lines that are not drawn
    nx, ny = xs[0], ytop[3]
    d.node("n1", "note", nx, ny, 2 * cw + gap, 150,
           "Reading the picture: PK is the primary key; red FK lines name the table they point to.\nLines show the main links only. Other links are written as FK in the table, for example classes, activities and\nassignments also point to the teacher who owns them, and unlocks and ratings also point to the parent and learner.\nThe 100 badge definitions live in a settings file (config/badges.php), not in a table. There is no curriculum_guides\ntable: the MATATAG codes are kept in settings and on each activity (curriculum_code).", size=10)
    d.nodes["n1"]["tcol"] = C["muted"]
    d.text(14, strip_top - 8, "Supporting tables (no lines drawn; their FK columns name the table they point to)", 12, True, "ink")
    yy = strip_top + 6
    for r, names in enumerate(srows):
        for i, n in enumerate(names):
            d.node(n, "ent", xs[i], yy, cw, h_of(n), n, size=11, tone="gray", lines=T[n])
        yy += sh[r] + 22
    d.legend([("blue", "Accounts"), ("teal", "Classes and activities"), ("violet", "Learner and readings"), ("orange", "Repository"), ("amber", "Rewards"), ("gray", "Supporting")], 24, H - 16)
    return d.save(OUTDIR)


if __name__ == "__main__":
    print(erd())
