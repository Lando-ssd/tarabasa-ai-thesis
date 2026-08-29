# TaraBasa AI — PARENT ACTOR — Complete Standalone System Flow

Build the Parent actor in isolation. Where this references Teacher or
Learner actions taken by other actors, assume that data already
exists.

---

## 1. WHO THE PARENT IS, AND WHAT THEY TOUCH

A Parent is the ONLY actor who can create a Learner account — this is
the single most important rule in the entire system, and it never
changes regardless of what other actors can do. A Parent's own account
carries zero special verification, since registering for yourself
carries no child-safety risk; all the care in this system goes into
protecting the *Learner* accounts a Parent creates, not gatekeeping the
Parent account itself.

**Entities touched:** Parent (own record), Learner (creates, via a
wizard — never edited by a Teacher), ParentLearner (creates, including
linking a second guardian to an existing Learner), OpenRepositoryListing
(reads, browses), RepositoryUnlock (creates), ReadingSession (reads,
for analytics), Notification (reads own, marks read).

---

## 2. THE FULL PARENT JOURNEY

### Step 1 — Registration
First Name, Last Name, Email, Password (8+ chars), Contact Number
(optional). Creates the User + Parent record, `status: "Active"`
immediately — no approval gate, no waiting. Redirects straight to
login (or auto-logs-in, either is fine).

### Step 2 — Login
Standard email + password.

### Step 3 — Parent Dashboard (Child Selector)
If zero Learners linked yet: friendly empty state with two options —
**"Add Your Child"** (Step 4 below) or **"Link an existing child's
account"** (Step 5 below, for a second guardian joining an
already-created Learner).

If one or more Learners linked: show each as a card — avatar, first
name, grade level, current mastery level (or "Not yet assessed"),
points, streak, **learning style** (Visual/Listening/Hands-on, if
set), learner code, and whether they're currently in a class or not
("Not enrolled in a class yet" if `classId` is null). Below the grid:
"+ Add another child" and "Link an existing child's account."

Header nav: Browse Repository, Progress, Notifications, Logout.

### Step 4 — Add a Learner (the account-creation wizard)
Multi-step:
1. **Basic info:** child's first name, grade level (Grade 1/2/3).
2. **Reading stage self-report:** one of "Just starting" / "Knows
   letters and sounds" / "Blending sounds into words" / "Reading
   simple sentences" / "Reading independently but needs confidence" /
   "Not sure" — framed clearly as just an initial estimate, refined
   next.
3. **Learning style:** Visual / Listening / Hands-on — framed as
   helping the AI tailor how activities are presented later.
4. **Placement check:** 3 quick yes/no questions (e.g. "Does [name]
   know the sound each letter makes?", "Can [name] blend sounds into a
   short word?", "Can [name] read a full simple sentence aloud?").
   Score: 0-1 "yes" → `masteryLevel: "Beginning"`; 2 → `"Developing"`;
   3 → `"Proficient"`. This score, not the self-report from step 2, is
   what actually sets the starting level.
5. **Set a 4-digit PIN** for the child.

On completion: generate a unique `learnerCode` (e.g. "TB-48213"),
assign a random avatar, create the Learner record
(`classId: null, status: "Active"` immediately — no Teacher/Class
required), and create the ParentLearner link (`isCreator: true`).
Success screen shows the `learnerCode` prominently (*"share this with
a Teacher later, if you want"*) and the resulting starting level.

### Step 5 — Link an Existing Child's Account (second guardian)
A separate short form: `learnerCode` + relationship (Mother/Father/
Guardian). Validates the code exists and this Parent isn't already
linked to that Learner, then creates a second ParentLearner row
(`isCreator: false`). This is how a second guardian joins a Learner
account someone else already created — **the Learner account itself
never changes**, only who's allowed to see/manage it.

### Step 6 — Browse & Unlock the Open Repository
Platform-wide listing grid (NOT limited to the Parent's own child's
Teacher — every Teacher's shared content shows here), each card:
topic, grade level, game type, skill focus, price badge (Free / ₱X).

If more than one Learner is linked, a selector at the top: "Unlocking
for: [dropdown]."

- **Free** items unlock instantly on tap.
- **Paid** items show a confirm step ("Unlock this for ₱X? (simulated
  payment)") before completing — real money, even simulated, shouldn't
  be one accidental tap.
- Unlocking is always per-item, per-specific-Learner — never a flat
  platform subscription. Attempting to unlock something already
  unlocked for that Learner → rejected, clear message.

### Step 7 — Progress (Analytics)
Child Selector at the top if more than one Learner. For the selected
child: current mastery level, points, streak, an accuracy-over-time
trend visual, full session history, and **Teacher-Assigned vs.
Parent-Initiated session counts shown as two separate numbers, never
blended into one total** — same split Teacher sees, same rule applies
here.

### Step 8 — Notifications
List of notifications where this Parent is the recipient, **grouped
into a clearly separate section per Learner** if more than one child is
linked — never one blended feed mixing both kids' updates together.
Newest first within each group, "Mark read" per notification.
"Needs Attention" type visually distinct from routine "Session
Summary" ones — noticeable, but not alarmist/scary in tone, since this
is a worried parent reading it.

### Step 9 — Logout

---

## 3. VALIDATION & EDGE CASES

- Duplicate email at registration → clear error.
- Adding a Learner with an invalid PIN (not exactly 4 digits) →
  rejected before submission.
- Linking a Learner via code that doesn't exist → clear "no learner
  found with that code" message.
- Linking a Learner already linked to this same Parent → rejected,
  "already linked to your account."
- Unlocking an already-unlocked repository item → rejected.
- Zero Learners linked → the empty state in Step 3, never a broken/
  blank dashboard.
- Zero repository listings, zero notifications, zero session history →
  each needs its own friendly empty state.
- A Learner with `classId: null` should display clearly as
  "not enrolled" rather than looking broken or incomplete — this is a
  fully valid, fully supported state, not an error condition.

---

## 4. UI NOTES

Minimalism direction, same as Teacher — but **warmer in tone**: this is
a parent checking on their own child, so avoid anything that reads as
clinical or alarming. Soften the "Needs Attention" flag visually (clear
and noticeable, not a scary red siren). The Child Selector pattern
(cards with avatar + key stats) should feel reassuring at a glance —
the Parent should be able to tell "is my child okay?" within about
three seconds of landing on the dashboard.
