# TaraBasa AI — LEARNER ACTOR — Complete Standalone System Flow

Build the Learner actor in isolation. Where this references Activities
created by a Teacher or content unlocked by a Parent, assume that data
already exists. This is the ONLY actor where the AI Examiner (Google
Speech-to-Text) actually runs — everything here should be built with
that as the centerpiece moment, not a footnote.

---

## 1. WHO THE LEARNER IS, AND THE ONE RULE THAT SHAPES EVERYTHING HERE

A Learner is a Grade 1–3 child. **A Learner never has a password, never
types an email, and never sees a registration form of any kind.** Every
interaction is designed for a 6–8 year-old operating mostly
independently: big tap targets (48px minimum), simple language, no
walls of text, immediate visual/audio feedback on every action.

**Entities touched:** Learner (own record, read + updated after
sessions), ActivityAssignment (read, to find assigned content),
RepositoryUnlock (read, to find extra unlocked content), Activity
(read), ReadingSession (creates), PersonalWordBank (created as a side
effect, never directly by the Learner), LearnerBadge (created as a side
effect).

---

## 2. THE FULL LEARNER JOURNEY

### Step 1 — Access (no password, ever)
Two methods only:
- **Avatar tap:** if a Parent is already logged in on this device, show
  their linked Learners as tappable avatars. Tapping one asks only for
  the 4-digit PIN.
- **Code entry (stand-in for QR scan):** if no adult is logged in on
  this device, show a field to enter the `learnerCode` directly
  (representing what scanning a QR code would have captured), then the
  same PIN entry.

**Rate limiting:** lock out after 5 wrong PIN attempts for 15 minutes —
apply this even more carefully here than for adult passwords, since a
4-digit PIN has only 10,000 possible combinations.

After a correct PIN: land on the Learner's own dedicated dashboard —
explicitly the Learner's OWN space. Never label anything "child view"
or "profile" in the UI copy — it's "your dashboard," addressed directly
to the child.

### Step 2 — Learner Dashboard
Big friendly avatar, first name greeting ("Hi, [name]!"), grade level,
and three stat tiles: current Level (Beginning/Developing/Proficient,
or "New" if not yet assessed), Points, Streak (with a fire emoji or
similar). One large, prominent button: **"Start Reading Activity."**
"Not you? Switch learner" link for logout.

### Step 3 — Finding what to read
On tapping Start:
1. Resolve the Teacher-assigned Activity (if any) using this priority:
   direct-to-this-Learner assignment first, then their Class, then
   their Class's Group tag — first match wins.
2. Also gather any Activities this Learner has unlocked via the Open
   Repository (a Parent unlocked them).
3. If there's exactly one option total, go straight into it — no extra
   click needed. If there's more than one, show a simple picker: each
   option as a card with its topic and a small label ("Assigned by your
   Teacher" vs. "Extra Practice"). If there are zero options, show a
   friendly "No activity yet — ask your teacher, or check back soon!"
   message with a way back to the dashboard.

### Step 4 — The activity itself (game-type-driven, always ending in a read-aloud)
Every Activity has a `gameType`, and the interaction shown depends on
it — but **every game type still ends in the same core moment: reading
the `passageText` aloud, scored by AI.** The game is a warm-up/practice
step before that moment, not a replacement for it (this mirrors real
literacy instruction — pre-teaching vocabulary before reading a passage
is a genuinely evidence-based approach, not just "more fun"):

- **Read Aloud:** go straight to the passage.
- **Letter-Sound Match:** show each letter with 2-3 tappable sound
  options (one correct, others plausible distractors) before the
  passage.
- **Word Builder:** show scrambled letters the Learner arranges into
  the target word (with an image hint), for each word, before the
  passage.
- **Trace-and-Write:** the Learner traces each target word on-screen
  before the passage.
- **Sentence Scramble:** the Learner reorders scrambled words into the
  correct sentence before reading it aloud.
- **Picture-Word Match:** the Learner matches each word to the correct
  picture before the passage.

After the pre-game (or immediately, for Read Aloud): show the full
passage text clearly, large friendly type, with a prompt like "Read
this out loud, then tap the button below," and a single button:
**"I'm done reading!"**

### Step 5 — AI scoring (the core moment)
On tapping done: send the recorded audio to Google Speech-to-Text
(v2, `chirp_3` model — see the AI Integration prompt for the exact
technical wiring), get back a transcript with word-level timing and
confidence, then compute all 7 criteria by comparing that transcript to
`passageText`:
- Word Accuracy (% correct via word-diff alignment)
- Pronunciation Score (avg. word confidence)
- Fluency Score (derived from pacing consistency between word
  timestamps)
- Reading Speed / WCPM (correct words ÷ elapsed minutes)
- Errors: mispronunciation, skipped word, substitution, repetition,
  insertion counts (from the diff alignment)
- Vocabulary Mastery (which specific words were missed)
- Reading Level (the resulting threshold check below)

**"Unclear" handling:** if the audio genuinely couldn't be processed
(simulate this ~15% of the time if using the documented fallback), show
"Didn't quite catch that — try again!" and let them re-record, up to 3
total attempts. On the 3rd unclear attempt, show a graceful "Please ask
your Teacher or Parent for help" message and return to the dashboard —
**no ReadingSession record gets created for an attempt that was never
actually scored.**

### Step 6 — Level adjustment and rewards (once a real score comes back)
- Accuracy ≥90% → `masteryLevel` increases one tier (capped at
  Proficient).
- 70–89% → level stays the same; note this means the NEXT activity
  should be different content at the same level, not a repeat.
- <70% → level decreases one tier (floored at Beginning) AND
  `flaggedNeedsAttention = true`.
- Points awarded ≈ accuracy ÷ 2, rounded. Streak increments by 1.
- Check `Badge` thresholds against the Learner's new total points;
  award any newly earned ones.
- On accuracy <80%, auto-create `PersonalWordBank` entries for 1-2 of
  the actually-missed words (from the diff alignment, or from the
  passage if using the documented fallback) — **no manual action by
  the Learner triggers this**, it just happens.
- `initiatedBy` on the new ReadingSession is determined by HOW this
  activity was reached, not guessed: `"Teacher"` if it came from an
  assignment, `"Parent"` if it came from an unlocked repository item —
  verified server-side against real assignment/unlock records, never
  trusted from the client.
- Trigger notifications to the Learner's Teacher (if in a class) and
  every linked Parent with the session summary, plus an additional
  urgent one to both if flagged.

### Step 7 — Results screen
Celebratory but honest — an emoji/icon reflecting the tier (e.g. 🌟 for
≥90%, 👍 for 70-89%, 💪 for <70%, never a sad/discouraging visual even
on a low score), the accuracy % and WCPM, a level-change callout if it
happened, a new-badge callout if one was earned, points earned this
session, and current streak. One button: "Done," back to the
dashboard.

### Step 8 — Switch learner / logout
Destroys the Learner session specifically (separate from any Parent
session that might still be active on the same device), returns to the
access screen from Step 1.

---

## 3. VALIDATION & EDGE CASES

- Wrong PIN → generic "Incorrect PIN" message (don't reveal whether the
  code itself was valid).
- 5 wrong PINs in a row → locked out 15 minutes, clear message.
- Attempting to submit a session for an Activity this Learner has no
  real access to (not assigned, not unlocked) → rejected outright by
  the backend — this must be independently verified server-side, never
  assumed safe just because the frontend only shows valid options.
- Slow AI response (Speech-to-Text taking a few seconds) → a visible,
  friendly "Checking..." state, never a frozen-looking screen for a
  child.
- Network failure mid-submission → a retry option, not a silent dead
  end.
- A brand-new Learner with zero session history yet → dashboard shows
  "New" for level rather than blank/broken.

---

## 4. UI NOTES

**This is the one place soft Claymorphism belongs** — puffy rounded
buttons and cards with soft dual-tone shadows (light highlight + soft
dark shadow, giving a gentle pressable 3D look), bright but not harsh
pastel colors, generous 48px+ tap targets, satisfying small
micro-interactions on correct actions (a gentle bounce/glow on earning
a badge or finishing a session). This is the one screen in the whole
system where genuine playfulness is not just allowed but expected —
everywhere else stays calm and minimal, but this is the fun layer kids
actually interact with.
