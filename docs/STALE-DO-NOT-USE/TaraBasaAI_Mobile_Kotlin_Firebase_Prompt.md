# TaraBasa AI — MOBILE APPLICATION (Kotlin/Android) — Complete Standalone Prompt

Build the native Android mobile app. This is NOT a separate system — it
is a second frontend calling the exact same Firebase backend (Auth,
Firestore, Cloud Functions, Storage) that the Next.js web app already
uses. If you find yourself writing a Cloud Function here, stop — that
belongs in the backend account's codebase, not duplicated here. Mobile
only ever CALLS what already exists.

---

## 1. TECH STACK

- Kotlin
- Android SDK, Android Studio
- Android Material Design (adapted to the project's established color
  palette and warmth — not stock Material defaults; see Section 6)
- Firebase Authentication SDK (Android)
- Cloud Firestore SDK (Android)
- Firebase Cloud Messaging SDK (push notifications)
- Firebase Storage SDK (uploading recorded reading audio)
- RESTful/callable Cloud Functions (Firebase's `httpsCallable`, calling
  the same functions the backend account builds and deploys)

**The one rule that matters most in this whole document:** the exact
same Firebase project, the exact same Firestore collections, the exact
same Cloud Functions as web. A Teacher's account, a Parent's linked
Learners, a Class's roster — all of it is the same data whether viewed
from the website or the app. There is no mobile-only backend logic
anywhere in this build.

---

## 2. WHICH ACTORS GET A MOBILE SCREEN, AND WHY

- **Parent — primary mobile user.** This is genuinely the actor who
  benefits most from mobile: checking on their child, unlocking
  Repository content, reading notifications, all realistically happen
  on a phone at home, not a desktop.
- **Teacher — primary mobile user.** Checking notifications, reviewing
  a flagged Learner, quick analytics checks between classes — real
  use cases for a phone.
- **Learner — accessed THROUGH the Parent's phone, never their own
  device.** This matches both the system's existing design (avatar-tap
  requires an adult already logged in) and DepEd's actual current
  policy (DepEd Order No. 6, s. 2026 / ESMLE — prohibits personal
  portable devices for learners during instructional hours, with
  narrow academic/emergency exemptions). Build the Learner flow as a
  screen reached FROM inside the Parent's own logged-in session on
  their phone — never a standalone "Learner app" a child would carry
  or operate independently outside a parent-supervised context.
- **Admin — lower priority, optional for this build pass.** Admin's
  entire job (Teacher verification, account status) is infrequent and
  works fine on the website. Build this last, if at all, in mobile v1.

---

## 3. AUTHENTICATION ON MOBILE (identical mechanism to web, different SDK)

- **Adults (Teacher/Parent):** Firebase Auth's
  `signInWithEmailAndPassword`, same as web. After success, call the
  same backend Cloud Function web uses to check `status` (Active/
  Inactive, and Teacher Pending/Active/Rejected) before granting
  access — this logic must not be reimplemented natively in Kotlin; it
  already exists as a Cloud Function, call it.
- **Learner (PIN, no password):** same custom-token flow as web —
  call the existing Cloud Function with `learnerCode`/`learnerId` +
  `pin`, receive a custom token back, then `signInWithCustomToken`.
  Same rate-limiting protection already built server-side applies
  automatically, since it's the same function.
- Store the Firebase Auth session using Firebase's own secure Android
  persistence — never write your own token storage, never put
  anything auth-related in unencrypted `SharedPreferences`.

---

## 4. FIRESTORE ACCESS PATTERNS

Read/write the same collections already defined in the backend spec
(`users`, `teachers`, `parents`, `learners`, `parentLearners`,
`classes`, `activities`, `activityAssignments`, `readingSessions`,
`personalWordBank`, `notifications`, etc.) — same field names, same
shapes, same Security Rules already enforce who can read/write what.
Do not create a parallel or mobile-specific collection for anything
that already has a place in the existing schema.

Use Firestore's real-time listeners (`addSnapshotListener`) for things
that benefit from it on mobile specifically — notifications updating
live, a Teacher's pending-approval count updating without a manual
refresh — rather than polling on a timer.

---

## 5. THE TWO MOBILE-SPECIFIC FIREBASE FEATURES

### Firebase Cloud Messaging (push notifications)
- Register the device's FCM token, save it to that User's document
  (e.g. `users/{uid}.fcmToken`) via a small Cloud Function call, so the
  backend knows where to deliver a push.
- When the backend's Notification-creation logic runs (a session
  completes, a Learner is flagged, a Teacher gets approved), the
  backend sends an FCM push to any registered device for that
  recipient — this is backend logic, already covered under the
  Notification module, not something to reimplement here. Mobile's job
  is only: register the token, and handle the incoming notification
  (show it, deep-link into the right screen on tap).

### Firebase Storage (recorded reading audio)
- When a Learner finishes reading aloud (Reading Session flow), record
  audio using Android's `MediaRecorder`, upload it to Firebase
  Storage, then call the existing backend Cloud Function (the same one
  web's flow would call, once real audio capture exists there too)
  with the Storage file reference. The backend Cloud Function handles
  the actual Speech-to-Text call and 7-criteria scoring — mobile's job
  ends at "record, upload, hand off the reference."
- Request microphone permission properly (runtime permission on modern
  Android), with a clear, friendly explanation of why it's needed,
  shown before the system permission dialog — a young child or a
  non-technical parent should understand why the app wants microphone
  access.

---

## 6. UI DIRECTION FOR MOBILE

Same design system as web, translated into Material Design components
rather than replaced by stock Material defaults:
- **Teacher/Parent/Admin screens:** Minimalism — clean Material cards,
  the project's established accent color (not Android's default blue
  unless that happens to already be the chosen accent), soft rounded
  corners, real light/dark theme support (Android's system dark mode
  should be respected, not ignored).
- **Learner screens (reached through the Parent's session):** soft
  Claymorphic direction — bigger touch targets than Material's normal
  48dp minimum where reasonable for young children, bright warm
  colors, playful micro-interactions on correct actions. This should
  feel like the same product as the web Learner experience, not a
  visually different app that happens to share a backend.

---

## 7. VALIDATION & EDGE CASES SPECIFIC TO MOBILE

- No network connection → clear offline state, never a silent hang.
  Firestore's offline persistence can help here for reads of
  already-cached data, but writes (like submitting a reading session)
  need an explicit "waiting to reconnect" state, not a false success.
- Microphone permission denied → clear explanation + a way to open
  Android's app settings to grant it, not just a dead end.
- App backgrounded mid-recording (a phone call comes in, etc.) →
  handle gracefully, don't lose/corrupt the in-progress recording
  silently.
- FCM token changes (app reinstalled, device changed) → make sure the
  stored token on the User's document gets updated, or notifications
  silently stop reaching that device.
- Same session-expiry handling as web: if the Firebase Auth session
  becomes invalid, redirect to login with a clear message, never a
  frozen or broken screen.

---

## 8. BUILD ORDER

1. Firebase project connection (Android app registered in the same
   Firebase project as web — `google-services.json` from the same
   project, not a new one).
2. Auth (adult + Learner PIN flow) — reuses backend Cloud Functions
   already built for web; this should be the fastest module to stand
   up on mobile precisely because the hard logic already exists.
3. Parent's core flows (Child Selector, Add/Link a Learner, Browse
   Repository).
4. Teacher's core flows (Dashboard, Notifications, Analytics —
   whichever are highest-value on-the-go).
5. Learner's reading session flow, including real audio recording +
   Storage upload (this is the one genuinely new technical piece
   mobile adds that web's browser-based version approaches
   differently).
6. FCM push notifications.
7. Admin, only if time allows.
