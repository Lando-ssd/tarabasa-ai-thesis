# TaraBasa AI — ADMIN ACTOR — Complete Standalone System Flow (Corrected Final)

This version supersedes the earlier Admin Actor Prompt. Corrected:
Curriculum Guide is now built-in system data, not Admin-managed.
Restored: account deactivation was missing from a recent summary —
it is still very much in scope.

---

## 1. WHO ADMIN IS, AND THE ONE RULE THAT SHAPES EVERYTHING HERE

Admin exists for exactly **two jobs**: verifying Teachers are real
school staff, and toggling account status when needed. Nothing else.
Admin never manages content (including the Curriculum Guide — that is
seeded system reference data, not an Admin feature), never sees
learner performance reports, and never touches Classes, Activities, or
Sessions. A smaller, more auditable Admin surface is a deliberate
safety choice for the most sensitive account in the system, not a
limitation to build around.

There is exactly **one seeded Admin account** — no self-registration
flow exists for this role.

---

## 2. WHY TEACHER APPROVAL EXISTS (build the UI around this reasoning)

Once Active, a Teacher gets real access to real children — class
rosters, real names, reading performance data, and the ability to
assign content directly to kids. Employee ID + School Name
verification confirms the person registering is genuinely school
staff, not someone impersonating one.

**Important nuance the UI must reflect correctly:** a Pending Teacher
can already log in to a locked-down dashboard (AI lesson generation
only, capped at 2 credits, touching zero real children). The boundary
Admin approval protects is "touching real students," not "logging in
at all." Admin's approval unlocks Class Management, joining real
Learners, and Promotions — not basic account access.

---

## 3. THE FULL ADMIN JOURNEY

### Step 1 — Login
Plain email + password. No registration link — only the one seeded
account exists.

### Step 2 — Admin Dashboard
Top stat row (counts only, tied directly to Admin's actual job —
nothing learner/content-related):
- **Pending Teachers** (count awaiting review)
- **Active Teachers** (count)
- **Total Accounts** (Teachers + Parents combined, or shown separately
  — either is fine, just keep it account-level, not content-level)

Two sections below:

**Pending Teacher Approvals** — list every Teacher with
`status: "Pending"`. Each row: First Name, Last Name, School Name,
Employee ID, Email. Two actions:
- **Activate** — sets `status: "Active"`. Takes effect immediately on
  their already-existing session if they're currently logged in — no
  re-login required on their end.
- **Reject** — sets `status: "Rejected"`. Future login attempts show a
  clear rejection message.

**All Accounts** — list every non-Admin User (Teachers and Parents),
showing First Name, Last Name, User Type, current Status. One action
per row: **Deactivate** (sets `User.status: "Inactive"`, which must
genuinely block login on the backend, not just visually flag the row)
or **Activate** again if previously deactivated. This is a real,
separate responsibility from Teacher verification — it applies to
Parent accounts too, not only Teachers.

**No Curriculum Guide section on this dashboard at all.** That data is
seeded into the system directly (see Section 5) and is never
created, edited, or even necessarily viewed through any Admin screen.

### Step 3 — Logout
Destroys the session, returns to login/role-select.

---

## 4. NON-NEGOTIABLE RULES SPECIFIC TO THIS ACTOR

1. **Admin never appears in its own "All Accounts" list** — cannot
   deactivate itself, cannot even see itself as a manageable row.
2. Admin cannot create, edit, view, or delete: Curriculum Guide
   entries, Activities, Classes, Reading Sessions, Notifications, or
   any Learner data. If a screen for any of those shows up under
   Admin, it's out of scope — remove it.
3. Account deactivation must be enforced at login time on the
   backend — an Inactive account is rejected with a clear message
   regardless of what the frontend shows.
4. Both Teacher verification AND account deactivation are real,
   separate Admin responsibilities — neither one is optional or a
   subset of the other.

---

## 5. WHERE THE CURRICULUM GUIDE ACTUALLY COMES FROM

The Curriculum Guide is **built into the system as seeded reference
data** from the start of development — the same way any other core
reference table would be populated during setup. The AI reads/matches
against it exactly as specified in the Teacher and AI Integration
prompts. No Admin screen creates, edits, or manages it. If a future
version of the system ever needs a way to expand this content, that
would be a deliberate, separate decision made later — not something
this version of Admin exposes.

---

## 6. EDGE CASES

- Re-activating an already-Active Teacher, or re-rejecting an
  already-Rejected one — allow status to be changed freely between
  Pending/Active/Rejected at any time by Admin (real-world corrections
  happen), applied consistently.
- Empty states: zero pending Teachers, zero accounts — each needs a
  friendly "nothing here yet" message, not a blank area.
- Attempting to deactivate the seeded Admin account itself — this
  should be structurally impossible, not just discouraged, since Admin
  never appears in its own manageable list at all (Rule 1 above).

---

## 7. UI NOTES

Follow the Minimalism direction from the main Design System — this is
the most utilitarian actor in the whole system, deliberately. Plain
tables/lists, minimal color, clear status badges (Pending/Active/
Rejected/Inactive, one consistent color each, used everywhere).
Admin's job is fast, accurate verification — resist making this screen
more decorative or feature-rich than the other three actors.
