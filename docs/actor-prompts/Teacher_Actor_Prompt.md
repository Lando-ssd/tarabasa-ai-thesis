# TaraBasa AI — TEACHER ACTOR — Complete Standalone System Flow (Updated)

Build the Teacher actor in isolation. This version supersedes the
earlier Teacher prompt — every patch discussed since then (structured
activity content, adaptive generation, school year, learner history
display, the Assign fix) is merged in below. Where this references
Admin, Parent, or Learner actions, assume that data already exists.

---

## 1. WHO THE TEACHER IS, AND WHAT THEY TOUCH

A Teacher creates AI-assisted reading activities, organizes learners
into classes, and monitors reading progress. **A Teacher never creates
a Learner account** — Learners already exist (created by a Parent)
before a Teacher ever interacts with one.

**Entities touched:** Teacher (own record), Class (owns/creates,
including `schoolYear`), Activity (creates, reviews, edits, assigns,
shares — including structured `content`), ActivityAssignment (creates),
PromotionRecord (releases and claims), CurriculumGuide (reads only —
searches/matches, including optional `sampleVocabulary`),
OpenRepositoryListing (creates when sharing), ReadingSession (reads
only, for analytics), PersonalWordBank (reads only, to inform adaptive
generation), Notification (reads own, marks read), Learner (reads/links
via code — never creates).

---

## 2. THE FULL TEACHER JOURNEY

### Step 1 — Registration
First Name, Last Name, Email, Password (8+ chars), School Name, DepEd
Employee ID, Contact Number (optional). Creates the User + Teacher
record, `status: "Pending"`, `freeGenerationCreditsRemaining: 2`.
Confirmation screen: *"Your account is pending Admin approval. You can
log in now — some features will be limited until you're approved."*

### Step 2 — Login while Pending (intentional — see reasoning)
Login succeeds immediately even while Pending. Only `"Rejected"` blocks
login outright. **Why:** the safety boundary is "touching real
children," not "logged in at all" — see the Admin prompt for the full
reasoning if needed. Generating a Draft lesson while Pending touches no
real child whatsoever.

### Step 3 — Pending Dashboard (locked-down)
Banner: *"Your account is awaiting Admin approval. You can try lesson
generation now (2 free credits) — other features unlock once
approved."* Only **Generate Activity** is usable. Everything else is
hidden or shown visibly locked ("🔒 Unlocks once approved"). **Every
locked route must reject a Pending Teacher on the backend**, not just
hide the button — this is the real security boundary.

### Step 4 — Admin approves
Same already-logged-in session unlocks full access on next load — no
re-login needed.

### Step 5 — Full (Active) Dashboard
Stat row: Classes, Activities, Approved, Credits. Nav grid: Class
Management, Generate Activity, My Activities, Promotions, Analytics,
Repository. (Layout already prototyped and working — keep the
structure, just ensure every card's destination is fully built per the
steps below.)

### Step 6 — Class Management
- **Create a Class:** Name, Grade Level, Section, optional Group Tag,
  and **School Year** (e.g. "2025-2026," defaulted to the current year
  but editable). **A Class is never edited into "becoming" next year's
  class** — a new school year always means a new Class row; last
  year's stays as a permanent, read-only historical record. By
  default, only show the CURRENT school year's classes; provide a
  year switcher to view past years (past-year classes are read-only —
  no Add Learner/Edit actions on them).
- **Join a Learner:** input `learnerCode` + target Class. Validate the
  code exists and the Learner isn't already in a class. This is the
  ONLY way a Teacher connects to a Learner.
- **On each Learner's card/row, show two extra lines of context:**
  1. *Promotion/school-year history* — pulled from `PromotionRecord`:
     if none exist, "New to the system — no prior grade history." If
     one or more Claimed records exist, show the most recent as a
     headline (e.g. "Promoted from a previous class — now in Grade 2
     since June 2026"), with the full chain (including each class's
     school year and teacher, e.g. "Grade 1 - CCS (SY 2025-2026,
     Teacher: Jenny Reyes) → Grade 2 - CCS (SY 2026-2027, Teacher:
     Maria Santos)") available on expand.
  2. *Proficiency trajectory* — pulled from the Learner's ENTIRE
     lifetime `ReadingSession` history (not just this class/year):
     compare the very first session's `levelBefore` to the most recent
     session's `levelAfter`. Show as "Proficiency: started at
     [level] → currently [level]" using only the real three tiers
     (Beginning/Developing/Proficient) — never a subjective "good/bad"
     label. If zero sessions exist yet, show "No reading sessions
     recorded yet. Starting level: [masteryLevel]."
  Keep both lines small/muted — supporting context, not competing with
  the Learner's name for visual attention.

### Step 7 — Generate Activity
Form: Curriculum Topic, Grade Level, optional Skill Focus, optional
target Learner.

On submit:
1. Block if `freeGenerationCreditsRemaining <= 0`, with a message
   explaining how to get more (Pending: wait for approval; Active:
   share something to the repository).
2. Search `CurriculumGuide` for a matching topic + gradeLevel entry.
   Show which entry matched (or "no match found") directly on the
   resulting Draft — this traceability must be visible.
3. **Generate structured content, not just a text blob.** Every
   Activity always has a top-level `passageText` (used for the core
   AI-scored read-aloud moment — every game type below still ends in
   this same step). Beyond that, `content` is shaped by `gameType`:
   - *Read Aloud:* `{ passageText }`
   - *Letter-Sound Match:* `{ passageText, letterSoundPairs: [{ letter, correctSound, distractorSounds[] }] }`
   - *Word Builder:* `{ passageText, targetWords: [{ word, scrambledLetters[], imageHint }] }`
   - *Trace-and-Write:* `{ passageText, traceWords: [] }`
   - *Sentence Scramble:* `{ passageText, scrambledSentences: [{ correct, scrambled[] }] }`
   - *Picture-Word Match:* `{ passageText, wordImagePairs: [{ word, imageHint }] }`
   Minimum 5 distinct words/pairs per list — a 1-2 item activity isn't
   real content. Distractors must be plausible wrong answers, not
   nonsense.
4. **Grounding:** if the matched Curriculum Guide has
   `sampleVocabulary`, use those words as the primary content source.
   If not, the AI chooses age-appropriate vocabulary itself, but the
   prompt sent to it must still explicitly state topic + skillFocus.
5. **Adaptivity:** if a target Learner was selected, look up their
   `PersonalWordBank` entries where `masteryStatus === "Struggling"`
   and deliberately include 1-3 of those exact words in the new
   content, alongside new vocabulary — this re-serves words THIS
   specific child already got wrong, not just "the right difficulty in
   the abstract." Skip silently if they have no struggling words yet.
6. Set `difficultyTier` from the target Learner's `masteryLevel` if
   selected, else default `"Medium"`. Save as `status: "Draft"`,
   decrement credits by 1. Show a real "AI is writing your lesson..."
   loading state, not an instant swap.

### Step 8 — My Activities
Three sections: Drafts Awaiting Review, Approved, Rejected.

**On a Draft, three actions:**
- **Approve** — as-is, status → `"Approved"`.
- **Edit** — opens the structured content for editing, not just the
  topic/skillFocus text fields: `passageText` as free text, and every
  word/pair list (whichever apply to this `gameType`) as an editable
  list — each item's fields editable inline, an "Add" button for new
  items, a remove control on existing ones. Teachers can add words the
  AI didn't generate, remove ones that don't fit, or fix a wrong image
  hint. Saving moves status → `"Approved"` in the same action.
- **Reject** — status → `"Rejected"`.

No Activity reaches `"Approved"` any other way.

**On an Approved card, TWO actions must both be present (this was
previously missing "Assign" — make sure it's actually there):**
- **Assign** — choose exactly ONE of: a specific Learner (from this
  Teacher's roster), a whole Class, or a Group (distinct `groupTag`
  values across this Teacher's classes). Reject if zero or multiple
  targets are selected.
- **Share to Repository** — Free or Paid (with price if Paid). On
  success: credits +2, card shows "✓ Shared to Repository." Can only
  be shared once per Activity.

### Step 9 — Promotions
**Release:** learners in this Teacher's classes, each with a "Release
→ [next grade]" button (no button for Grade 3 — "No next grade, final
year" instead). Clears `classId`, Learner stays fully Active.

**Claim:** a shared, platform-wide queue of every Pending
PromotionRecord — never a name search, never limited to ones this
Teacher released. Only this Teacher's own classes matching the
required `nextGrade` show as valid claim targets. On claim: `classId`
and `gradeLevel` both set from the claimed class — `gradeLevel` is
never manually typed anywhere in this flow.

### Step 10 — Analytics
Toggle between **By Learner** (mastery trend + full session history +
Teacher-Assigned/Parent-Initiated counts shown as two separate
numbers, never combined) and **By Group** (every learner in a
`groupTag`, their current level + session count, same split
aggregated).

### Step 11 — Notifications
List, newest first, type/message/timestamp, "Mark read" action.
"Needs Attention" notifications visually distinct (e.g. red-tinted)
from routine "Session Summary" ones.

### Step 12 — Logout

---

## 3. VALIDATION & EDGE CASES

- Duplicate email at registration → clear error, no duplicate account.
- Any locked action attempted while Pending (Class creation, joining a
  Learner, assigning, claiming a Promotion) → rejected on the backend
  with a clear "must be Active" message, regardless of UI state.
- Generating at 0 credits → blocked, with the correct explanation for
  their current status.
- Assigning a non-Approved Activity → rejected.
- Assigning to zero or multiple targets → rejected, "choose exactly
  one."
- Joining a Learner already in a class → rejected, no silent
  overwrite.
- Claiming into a grade-mismatched class → rejected with the specific
  mismatch explained.
- Creating a class without a School Year → block; it's required, not
  optional.
- Editing a Draft's content and adding fewer than the practical minimum
  (5) words to a list-based game → allow it (the Teacher may have a
  good reason), but consider a soft warning rather than a hard block.
- Every list view needs an explicit empty state with a friendly
  message + relevant next action.

---

## 4. UI NOTES

Minimalism direction from the main Design System: clean cards, one
consistent accent color for primary actions, soft rounded corners,
consistent status badge colors used everywhere a status appears
(Pending/Active/Draft/Approved/Rejected). Keep the existing dashboard's
stat-card + nav-card grid structure — it already works well; refine
spacing/consistency rather than replacing it.
