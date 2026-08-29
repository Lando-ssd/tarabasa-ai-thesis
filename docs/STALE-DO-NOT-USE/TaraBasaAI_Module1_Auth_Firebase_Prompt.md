# TaraBasa AI — MODULE 1 ONLY: ACCOUNT MANAGEMENT / AUTHENTICATION
### Complete, standalone prompt — Firebase-connected implementation

Build ONLY this module right now: registration, login, and account
status logic for all 4 actors (Admin, Teacher, Parent, Learner).
Nothing else yet — no Activities, no Classes, no Reading Sessions. This
module is the foundation every other module will build on, so it needs
to be fully correct and fully tested before moving on.

---

## 1. TECH STACK FOR THIS MODULE

- **Frontend:** the HTML/CSS/JS already built in this account — plug
  Firebase directly into it, don't rebuild the visual design.
- **Auth + Database:** **Firebase** — specifically:
  - **Firebase Authentication** for Admin, Teacher, and Parent
    (email + password accounts).
  - **Cloud Firestore** as the database for every profile field Firebase
    Auth doesn't store natively, plus the Learner records.
  - **Cloud Functions** (Node.js) for any logic that must run
    server-side and never be trusted to the client — PIN verification,
    rate limiting, and status checks all belong here, not in
    client-side JS.
- This is the practical implementation choice for what this account can
  actually generate and run (client-callable Firebase SDK, no separate
  server to host). The system's official documented production stack
  (PHP/Laravel + Cloud SQL) stays as the long-term target described
  elsewhere — Firebase here is a fully legitimate, real implementation
  of the exact same data model and business rules, not a simplified
  stand-in.

---

## 2. FIRESTORE COLLECTIONS FOR THIS MODULE

```
users/{uid}
  firstName, lastName, email, userType ("Teacher"|"Parent"|"Admin"),
  contactNumber (nullable), status ("Active"|"Inactive"), createdAt
  — {uid} is the Firebase Auth UID for this user. Firebase Auth itself
    already stores the email + hashed password; this document holds
    everything else.

teachers/{uid}
  schoolName, employeeId, status ("Pending"|"Active"|"Rejected"),
  freeGenerationCreditsRemaining (default 2)
  — same {uid} as the matching users/{uid} document (1:1).

parents/{uid}
  (no extra fields needed beyond the users/{uid} document — this
  collection can simply mirror which UIDs are Parents, or you can skip
  a separate parents collection entirely and rely on users/{uid}.userType
  == "Parent" — either is fine, pick one and be consistent.)

learners/{learnerId}
  learnerCode (unique), classId (nullable — always null for now, Class
  doesn't exist yet in this module), firstName, middleName (nullable),
  lastName, gradeLevel, pin (4-digit string), avatarId, masteryLevel
  (nullable), learningStyle (nullable), points (0), streak (0),
  status ("Active"), createdAt, authUid (the Firebase Auth UID of the
  "shadow" account created for this Learner — see Section 4)
  — {learnerId} is its own generated ID, separate from any Auth UID,
    since a Learner is not a User subtype.

parentLearners/{id}
  parentId (the Parent's UID), learnerId, relationship, isCreator (bool),
  linkedAt
```

---

## 3. ADULT AUTHENTICATION (Admin, Teacher, Parent)

### Registration
- **Teacher:** collect First Name, Last Name, Email, Password (8+
  chars), School Name, Employee ID, Contact Number (optional). Create
  the Firebase Auth account with `createUserWithEmailAndPassword`,
  then write the `users/{uid}` and `teachers/{uid}` documents via a
  Cloud Function (never write these documents directly from client JS
  — always go through a Cloud Function so the initial `status:
  "Pending"` and `freeGenerationCreditsRemaining: 2` can't be tampered
  with by a modified client request). Confirmation screen: *"Your
  account is pending Admin approval. You can log in now — some
  features will be limited until you're approved."*
- **Parent:** same pattern, no School Name/Employee ID fields, `users/
  {uid}` document gets `status: "Active"` immediately (still written
  via a Cloud Function for consistency, even though there's no
  approval gate to protect here).
- **Admin:** no registration flow anywhere. Seed exactly one Admin
  account once, manually, during setup (Firebase Auth account +
  `users/{uid}` document with `userType: "Admin"`).

### Login
- Use Firebase Auth's `signInWithEmailAndPassword` for the actual
  credential check — this gives you bcrypt-equivalent password
  security for free, no custom hashing code needed.
- **Immediately after that succeeds, call a Cloud Function** that:
  1. Checks `users/{uid}.status` — if `"Inactive"`, reject with *"This
     account has been deactivated. Please contact your Admin."* even
     though Firebase Auth itself said the password was correct.
  2. If `userType === "Teacher"`, checks `teachers/{uid}.status` — if
     `"Rejected"`, reject with the rejection message. **`"Pending"`
     does NOT block login** — this is intentional (see Section 5).
  3. If both checks pass, mint/return a Firebase **session cookie**
     (via the Admin SDK's `createSessionCookie`, httpOnly + secure) —
     do not rely on the client SDK's default token storage alone for
     anything requiring real security; explicitly issue a proper
     httpOnly session cookie the same way the rest of this system's
     security model already requires.
- **Rate limiting:** before even attempting the Firebase Auth sign-in,
  check a Firestore document tracking failed attempts for that email
  (e.g. `loginAttempts/{email}` with `count` and `lockedUntil`). If
  currently locked out, reject immediately without even calling
  Firebase Auth. On a failed Firebase Auth attempt, increment the
  counter; lock out for 15 minutes after 5 failures. Clear the counter
  on a successful login. This logic belongs in a Cloud Function, never
  purely client-side, since a modified client could otherwise just skip
  the check.
- **Generic error messages** — whether the email doesn't exist or the
  password is wrong, the user-facing message is always "Invalid email
  or password." Never reveal which one it was.

---

## 4. LEARNER AUTHENTICATION (no password, ever)

Firebase Authentication's normal email/password model does not fit a
Learner at all — so Learners get a deliberately different path, built
around a Cloud Function, not the Firebase client SDK's normal sign-in
methods:

1. Every Learner still gets one real Firebase Auth account behind the
   scenes ("shadow account") purely so Firestore Security Rules and
   session handling work the same consistent way for every actor — but
   this account has no email/password a human ever uses, and no
   Learner-facing screen ever asks for one.
2. **PIN login flow (Cloud Function, callable from the client):**
   - Input: either `learnerId` (if reached via avatar-tap, already
     knowing which Learner) or `learnerCode` (if reached via code
     entry), plus the 4-digit `pin`.
   - The Cloud Function looks up the Learner document, checks the same
     kind of rate-limit counter as adult login (keyed by learnerId this
     time, since a 4-digit PIN is far weaker than a real password —
     10,000 possible combinations, so this matters even more here).
   - If the PIN matches: mint a **Firebase custom token** for that
     Learner's shadow Auth UID (`admin.auth().createCustomToken(...)`)
     and return it to the client.
   - The client then calls `signInWithCustomToken` with that token —
     this gives the Learner a real, secure Firebase Auth session
     without ever touching email/password anywhere in the flow.
3. This keeps the security model consistent: every actor, including
   Learner, ends up with a genuine Firebase Auth session at the end,
   just reached through different front doors appropriate to each
   actor.

---

## 5. WHY PENDING TEACHERS CAN STILL LOG IN (build the UI around this)

A Pending Teacher's login succeeds, but their dashboard is locked down
to lesson generation only (capped at their 2 free credits) — nothing
that touches real Learner data. The actual safety boundary is "touching
real children," not "logged in at all." Since Class Management,
joining real Learners, and Promotions don't exist yet in this module,
this mostly just means: **make sure the login/status-check logic above
never blocks a Pending Teacher outright** — the actual feature-locking
happens in later modules, but the foundation (allowing the login) has
to be right here first.

---

## 6. VALIDATION & EDGE CASES FOR THIS MODULE

- Duplicate email at registration → Firebase Auth itself will reject
  this (`auth/email-already-in-use`) — catch that error and show a
  clear message, don't let a raw Firebase error code reach the user.
- Password under 8 characters → validate before even calling Firebase
  Auth, clear inline message.
- Wrong PIN → generic "Incorrect PIN," same reasoning as adult login.
- 5 wrong PIN attempts → 15-minute lockout, clear message with roughly
  how long until they can try again.
- Attempting to log in as a Rejected Teacher → clear rejection message,
  distinct from the generic "Invalid email or password" (this one DOES
  confirm the account exists, since rejection is a known, final state
  the person should understand, not something to obscure).
- Every form needs loading and error states — no silent failures, no
  raw Firebase error objects shown directly to the user.

---

## 7. WHAT'S DELIBERATELY OUT OF SCOPE HERE

Do not build in this pass: Class Management, Activity Generation,
Reading Sessions, Open Repository, Analytics, Notifications, or the
Pending-Teacher locked dashboard's actual content (the login logic that
*allows* a Pending Teacher in is in scope; the dashboard UI showing
only "Generate Activity" while locking everything else is a later
module, once those other modules exist to lock). This module ends at:
a person can register, log in, and the system correctly knows who they
are and what status they're in — nothing more yet.
