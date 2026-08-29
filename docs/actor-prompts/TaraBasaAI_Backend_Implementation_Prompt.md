# TaraBasa AI — BACKEND IMPLEMENTATION PROMPT (Start Here for Code)

This is the consolidated spec for building the actual backend — the
database, the API, and the business logic underneath every screen
already designed across the Admin, Teacher, Parent, and Learner
prompts. Read this alongside those four documents; this file gives the
API contract and data structure, those four give the detailed
per-actor logic and edge cases.

Tech stack: PHP (Laravel) + Cloud SQL (MySQL) + Firebase (Cloud
Messaging + Storage) + Google Cloud Speech-to-Text v2 (chirp_3,
en-PH). See the Updated Technology Stack document for the full
reasoning on each piece.

---

## 1. UI NAVIGATION PATTERN (confirmed direction, affects route structure)

Teacher, Parent, and Admin all use a **persistent left sidebar** (one
item per major section, current section highlighted, collapses to a
hamburger/bottom bar on mobile) with the dashboard's stat cards living
in the main content area as the default "Home" view. **Learner has no
sidebar** — full-screen, single primary action, zero navigation
chrome. Structure your frontend routes so each sidebar item maps to
one clear URL (e.g. `/teacher/activities`, `/teacher/analytics`), since
the backend API below is organized the same way.

---

## 2. DATABASE SCHEMA (17 tables, final field list including every patch)

users
  id, first_name, last_name, email (unique), password (hashed),
  user_type (enum: Teacher/Parent/Admin), contact_number (nullable),
  status (enum: Active/Inactive), created_at

teachers
  id, user_id (FK), school_name, employee_id, status (enum:
  Pending/Active/Rejected), free_generation_credits_remaining (int,
  default 2)

parents
  id, user_id (FK)

learners
  id, learner_code (unique), class_id (FK, NULLABLE), first_name,
  middle_name (nullable), last_name, grade_level (enum: Grade 1/2/3),
  pin (4-char string), avatar_id, mastery_level (nullable enum:
  Beginning/Developing/Proficient), learning_style (nullable enum:
  Visual/Listening/Hands-on), points (int, default 0), streak (int,
  default 0), status (Active), created_at

parent_learners
  id, parent_id (FK), learner_id (FK), relationship, is_creator
  (bool), linked_at

classes
  id, teacher_id (FK), name, grade_level, section, group_tag
  (nullable), school_year (string, e.g. "2025-2026")

promotion_records
  id, learner_id (FK), released_by_teacher_id (FK),
  claimed_by_teacher_id (FK, nullable), next_grade, status (enum:
  Pending/Claimed), released_at, claimed_at (nullable)

curriculum_guides
  id, topic, grade_level, skill_focus, sample_vocabulary (JSON array,
  nullable), created_at
  — SEEDED DATA ONLY. No create/update/delete endpoint exists for this
  table anywhere in the API. It is populated once via a database seeder
  during setup, exactly like any other fixed reference table.

activities
  id, created_by_teacher_id (FK), curriculum_guide_id (FK, nullable),
  topic, grade_level, skill_focus, game_type (string, open/extensible
  — see the Game Templates addition for the fixed set used so far),
  difficulty_tier (enum: Easy/Medium/Hard), status (enum: Draft/
  Approved/Rejected), shared_to_repository (bool), passage_text (text),
  content (JSON — shape depends on game_type, see Game Templates
  addition for the exact structure per type), created_at

activity_assignments
  id, activity_id (FK), learner_id (FK, nullable), class_id (FK,
  nullable), group_tag (nullable), assigned_by_teacher_id (FK),
  assigned_at
  — exactly one of learner_id/class_id/group_tag is set per row,
  enforced at the API layer, not just the database.

open_repository_listings
  id, activity_id (FK), teacher_id (FK), price_type (enum:
  Free/Paid), price (decimal, 0 if Free), listed_at

repository_unlocks
  id, listing_id (FK), parent_id (FK), learner_id (FK), amount_paid,
  unlocked_at

reading_sessions
  id, learner_id (FK), activity_id (FK), accuracy_percent, wcpm,
  pronunciation_score, fluency_score, mispronunciation_count,
  skipped_word_count, substitution_count, repetition_count,
  insertion_count, level_before (nullable enum), level_after
  (nullable enum), flagged_needs_attention (bool), session_type (enum:
  Diagnostic/Practice/Assessment), initiated_by (enum: Teacher/Parent
  — always derived server-side, never trusted from the client),
  timestamp

personal_word_bank
  id, learner_id (FK), session_id (FK), word, skill_type,
  mastery_status (enum: Struggling/Improving/Mastered), times_drilled
  (int), last_reviewed (nullable), created_at

badges
  id, name, description, point_threshold

learner_badges
  id, learner_id (FK), badge_id (FK), date_earned

notifications
  id, recipient_user_id (FK to users — works for both Teacher and
  Parent), learner_id (FK), type, message, is_read (bool), timestamp

---

## 3. AUTHENTICATION ARCHITECTURE

- **Adults (Teacher/Parent/Admin):** Laravel Sanctum or session-based
  auth, password hashed with Laravel's `Hash::make()` (bcrypt under the
  hood). httpOnly cookie or Sanctum SPA token — never store the token
  in localStorage/sessionStorage. Rate limit login attempts (5 failed
  attempts → 15 minute lockout), keyed per email. Generic "Invalid
  email or password" error, never revealing which part was wrong.
- **Learner:** completely separate, much simpler mechanism — no
  password field exists at all. A short-lived session/token created
  after correct `learner_code` (or avatar selection) + PIN match.
  Same rate-limiting principle applies, keyed per learner_id, since a
  4-digit PIN is far weaker than a real password.
- Every protected API route re-checks role AND status (e.g. Teacher
  must be Active for anything beyond lesson generation) server-side,
  regardless of what the frontend shows or hides. This is the actual
  security boundary — treat every frontend restriction as convenience
  only.

---

## 4. API ENDPOINTS BY MODULE

*(Full business logic, validation rules, and edge cases for each of
these live in the corresponding actor prompt — this list is the
contract, not the full spec.)*

### Auth
- `POST /api/auth/register-teacher`
- `POST /api/auth/register-parent`
- `POST /api/auth/login` (Admin/Teacher/Parent)
- `POST /api/auth/logout`
- `GET /api/auth/me`
- `POST /api/learner/login-pin`
- `GET /api/learner/me`
- `POST /api/learner/logout`

### Admin (see Admin Actor Prompt — Corrected)
- `GET /api/admin/pending-teachers`
- `POST /api/admin/teacher-status` (Activate/Reject)
- `GET /api/admin/all-users`
- `POST /api/admin/toggle-user-status`
- No Curriculum Guide endpoints under Admin at all.

### Learner accounts & linking (see Parent Actor Prompt)
- `POST /api/learner/create` (Parent only — the wizard)
- `GET /api/learner/my-learners` (Parent's linked children)
- `POST /api/learner/link-parent` (second guardian linking)
- `POST /api/learner/join-class` (Teacher only)

### Class Management (see Teacher Actor Prompt)
- `POST /api/class/create` (requires school_year)
- `GET /api/class/my-classes`
- `GET /api/class/roster` (includes each learner's promotion history +
  proficiency trajectory, per the Learner History addition)

### Activity Generation & Review (see Teacher Actor Prompt + AI
Integration Prompt + Game Templates/Activity Content additions)
- `POST /api/activity/generate` (real Google Speech-to-Text/LLM calls,
  or documented fallback — never fake final scores even when stubbed)
- `GET /api/activity/my-activities`
- `POST /api/activity/decision` (Approve/Edit/Reject — Edit accepts a
  structured `content` payload, not just topic/skillFocus strings)
- `POST /api/activity/assign` (exactly one of learner_id/class_id/
  group_tag)
- `POST /api/activity/share`

### Promotions (see Teacher Actor Prompt)
- `POST /api/promotion/release`
- `GET /api/promotion/pending` (platform-wide queue)
- `POST /api/promotion/claim`

### Open Repository (see Parent + Teacher Actor Prompts)
- `GET /api/repository/listings` (platform-wide)
- `POST /api/repository/unlock`

### Reading Sessions (see Learner Actor Prompt — this is the core AI
Examiner flow)
- `GET /api/session/available-activities` (assigned + unlocked,
  Learner session)
- `POST /api/session/submit` (real Speech-to-Text call, 7-criteria
  scoring, level adjustment, PersonalWordBank side-effects, badge
  checks, notifications — `initiated_by` derived server-side from
  actual assignment/unlock records, never trusted from the client)
- First-login diagnostic uses this same endpoint with
  `session_type: "Diagnostic"` and the adaptive staircase logic from
  the Adaptive Diagnostic Correction document — no separate endpoint
  needed.

### Analytics (see Teacher + Parent Actor Prompts)
- `GET /api/analytics/learner?learnerId=X` (role-checked: Parent must
  be linked, Teacher must own the learner's class)
- `GET /api/analytics/group?groupTag=X` (Teacher only)

### Notifications (see all actor prompts — shared module)
- `GET /api/notifications/list` (grouped per-learner for Parents with
  multiple children)
- `POST /api/notifications/mark-read`

---

## 5. NON-NEGOTIABLE RULES — QUICK REFERENCE

1. Parent creates Learners, never Teacher.
2. Learner accounts are Active immediately, no Teacher/Class required.
3. Learner never has a password.
4. Multiple Parents per Learner, multiple Learners per Parent supported.
5. `initiated_by` always derived server-side, never blank, never
   trusted from client input.
6. No Activity reaches Approved without passing through Teacher
   review — no silent auto-publish.
7. Admin's scope is verification + status toggling ONLY — no content,
   no Curriculum Guide management, no reports.
8. Class Management lives under Teacher, not a separate top-level area.
9. Promotion is release → claim between two Teachers, grade always
   inferred from the claimed Class, never typed manually.
10. Open Repository unlocks are per-item, never a platform subscription.
11. Teacher's free credits replenish only via sharing, applies
    identically whether Pending or Active.
12. Notifications are one shared module for Teacher and Parent.
13. Pending Teachers can log in to a locked dashboard (generation
    only) — every other route rejects them server-side.
14. Curriculum Guide is seeded, read-only reference data — no
    create/update/delete endpoint exists for it anywhere.

---

## 6. SUGGESTED BUILD ORDER

1. Database migrations for all 17 tables + seeders (Admin account,
   Curriculum Guide entries, a couple of demo Teachers/Parents/Learners
   per the Seed Data spec in the main Build Prompt).
2. Auth (both adult and Learner mechanisms) — nothing else works
   without this.
3. Account Management (Admin approval flow, Class Management, Learner
   creation/linking).
4. Activity Generation + Review + Assignment.
5. Reading Sessions (the AI Examiner flow) — this is the system's core
   value, get it working with real or documented-fallback AI early
   rather than last.
6. Open Repository.
7. Analytics.
8. Notifications.
9. Wire up Firebase Cloud Messaging for real push notifications on
   top of the already-working in-app Notification list.

---

## 7. TESTING EXPECTATION

Every endpoint above should be verified with real requests (not just
"it compiles") before considering it done — confirm the happy path,
at least one rejection/edge case, and that access control actually
blocks who it's supposed to block. This mirrors how the working
reference prototype for this project was built and verified, module by
module, rather than all at once.
