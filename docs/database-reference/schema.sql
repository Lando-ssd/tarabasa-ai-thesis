-- TaraBasa AI — Database Schema
-- SQLite dialect for local dev/testing in this sandbox.
-- Field types/constraints map 1:1 to the MySQL/Cloud SQL version described
-- in the Backend Implementation Prompt; SQLite syntax differs only in
-- AUTOINCREMENT/ENUM handling (SQLite has no native ENUM, so we use
-- TEXT + CHECK constraints to enforce the same allowed values).

PRAGMA foreign_keys = ON;

-- ============================================================
-- 1. users — shared identity row for Teacher/Parent/Admin
-- ============================================================
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    first_name TEXT NOT NULL,
    last_name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    user_type TEXT NOT NULL CHECK (user_type IN ('Teacher','Parent','Admin')),
    contact_number TEXT,
    status TEXT NOT NULL DEFAULT 'Active' CHECK (status IN ('Active','Inactive')),
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ============================================================
-- 2. teachers
-- ============================================================
CREATE TABLE teachers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL UNIQUE REFERENCES users(id),
    school_name TEXT NOT NULL,
    employee_id TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'Pending' CHECK (status IN ('Pending','Active','Rejected')),
    free_generation_credits_remaining INTEGER NOT NULL DEFAULT 2
);

-- ============================================================
-- 3. parents
-- ============================================================
CREATE TABLE parents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL UNIQUE REFERENCES users(id)
);

-- ============================================================
-- 4. learners
-- ============================================================
CREATE TABLE learners (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    learner_code TEXT NOT NULL UNIQUE,
    class_id INTEGER REFERENCES classes(id),
    first_name TEXT NOT NULL,
    middle_name TEXT,
    last_name TEXT NOT NULL,
    grade_level TEXT NOT NULL CHECK (grade_level IN ('Grade 1','Grade 2','Grade 3')),
    pin TEXT NOT NULL,
    avatar_id TEXT NOT NULL,
    mastery_level TEXT CHECK (mastery_level IN ('Beginning','Developing','Proficient') OR mastery_level IS NULL),
    learning_style TEXT CHECK (learning_style IN ('Visual','Listening','Hands-on') OR learning_style IS NULL),
    points INTEGER NOT NULL DEFAULT 0,
    streak INTEGER NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'Active',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ============================================================
-- 5. parent_learners
-- ============================================================
CREATE TABLE parent_learners (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    parent_id INTEGER NOT NULL REFERENCES parents(id),
    learner_id INTEGER NOT NULL REFERENCES learners(id),
    relationship TEXT,
    is_creator INTEGER NOT NULL DEFAULT 0 CHECK (is_creator IN (0,1)),
    linked_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(parent_id, learner_id)
);

-- ============================================================
-- 6. classes  (PATCH: school_year added — SchoolYear_Addition.txt)
-- ============================================================
CREATE TABLE classes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    teacher_id INTEGER NOT NULL REFERENCES teachers(id),
    name TEXT NOT NULL,
    grade_level TEXT NOT NULL CHECK (grade_level IN ('Grade 1','Grade 2','Grade 3')),
    section TEXT NOT NULL,
    group_tag TEXT,
    school_year TEXT NOT NULL  -- e.g. "2025-2026" — REQUIRED, never overwritten across years
);

-- ============================================================
-- 7. promotion_records
-- ============================================================
CREATE TABLE promotion_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    learner_id INTEGER NOT NULL REFERENCES learners(id),
    released_by_teacher_id INTEGER NOT NULL REFERENCES teachers(id),
    claimed_by_teacher_id INTEGER REFERENCES teachers(id),
    next_grade TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'Pending' CHECK (status IN ('Pending','Claimed')),
    released_at TEXT NOT NULL DEFAULT (datetime('now')),
    claimed_at TEXT,
    -- PATCH: added on top of the confirmed 17-table schema, specifically to
    -- support the rich roster history line from SchoolYear_Addition.txt Part 4
    -- ("Grade 1 - CCS (SY 2025-2026, Teacher: Jenny Reyes) -> Grade 2 - CCS ...").
    -- Without these, only grade-number + date can be reconstructed for each
    -- historical step, since learner.class_id gets overwritten on every promotion.
    released_from_class_id INTEGER REFERENCES classes(id),
    claimed_into_class_id INTEGER REFERENCES classes(id)
);

-- ============================================================
-- 8. curriculum_guides (PATCH: sample_vocabulary added — ActivityContent_Addition.txt)
--    SEEDED DATA ONLY. No create/update/delete endpoint exists for this
--    table anywhere in the API (confirmed conflict #1).
-- ============================================================
CREATE TABLE curriculum_guides (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    topic TEXT NOT NULL,
    grade_level TEXT NOT NULL CHECK (grade_level IN ('Grade 1','Grade 2','Grade 3')),
    skill_focus TEXT NOT NULL,
    sample_vocabulary TEXT, -- JSON array, nullable, e.g. ["cat","dog","sun"]
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ============================================================
-- 9. activities (PATCH: content added — ActivityContent_Addition.txt)
-- ============================================================
CREATE TABLE activities (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    -- Nullable: diagnostic passages (see Adaptive Diagnostic Correction) are
    -- system-generated with no authoring Teacher — everything else always has one.
    created_by_teacher_id INTEGER REFERENCES teachers(id),
    curriculum_guide_id INTEGER REFERENCES curriculum_guides(id),
    topic TEXT NOT NULL,
    grade_level TEXT NOT NULL CHECK (grade_level IN ('Grade 1','Grade 2','Grade 3')),
    skill_focus TEXT,
    game_type TEXT NOT NULL, -- open/extensible; 6 defined shapes per Activity Content addition
    difficulty_tier TEXT NOT NULL CHECK (difficulty_tier IN ('Easy','Medium','Hard')),
    status TEXT NOT NULL DEFAULT 'Draft' CHECK (status IN ('Draft','Approved','Rejected')),
    shared_to_repository INTEGER NOT NULL DEFAULT 0 CHECK (shared_to_repository IN (0,1)),
    passage_text TEXT NOT NULL,
    content TEXT NOT NULL, -- JSON, shape depends on game_type
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ============================================================
-- 10. activity_assignments
-- ============================================================
CREATE TABLE activity_assignments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    activity_id INTEGER NOT NULL REFERENCES activities(id),
    learner_id INTEGER REFERENCES learners(id),
    class_id INTEGER REFERENCES classes(id),
    group_tag TEXT,
    assigned_by_teacher_id INTEGER NOT NULL REFERENCES teachers(id),
    assigned_at TEXT NOT NULL DEFAULT (datetime('now'))
    -- Enforced at API layer: exactly one of learner_id/class_id/group_tag set
);

-- ============================================================
-- 11. open_repository_listings
-- ============================================================
CREATE TABLE open_repository_listings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    activity_id INTEGER NOT NULL REFERENCES activities(id),
    teacher_id INTEGER NOT NULL REFERENCES teachers(id),
    price_type TEXT NOT NULL CHECK (price_type IN ('Free','Paid')),
    price REAL NOT NULL DEFAULT 0,
    listed_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ============================================================
-- 12. repository_unlocks
-- ============================================================
CREATE TABLE repository_unlocks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    listing_id INTEGER NOT NULL REFERENCES open_repository_listings(id),
    parent_id INTEGER NOT NULL REFERENCES parents(id),
    learner_id INTEGER NOT NULL REFERENCES learners(id),
    amount_paid REAL NOT NULL DEFAULT 0,
    unlocked_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(listing_id, learner_id) -- per-item, per-specific-learner, never duplicate
);

-- ============================================================
-- 13. repository_ratings (NEW TABLE — RepositoryRatings_Addition.txt)
-- ============================================================
CREATE TABLE repository_ratings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    listing_id INTEGER NOT NULL REFERENCES open_repository_listings(id),
    parent_id INTEGER NOT NULL REFERENCES parents(id),
    learner_id INTEGER NOT NULL REFERENCES learners(id),
    rating INTEGER NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    rated_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(listing_id, parent_id) -- one rating per parent per listing; re-rate = UPDATE
);

-- ============================================================
-- 14. reading_sessions
-- ============================================================
CREATE TABLE reading_sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    learner_id INTEGER NOT NULL REFERENCES learners(id),
    activity_id INTEGER NOT NULL REFERENCES activities(id),
    accuracy_percent REAL,
    wcpm REAL,
    pronunciation_score REAL,
    fluency_score REAL,
    mispronunciation_count INTEGER DEFAULT 0,
    skipped_word_count INTEGER DEFAULT 0,
    substitution_count INTEGER DEFAULT 0,
    repetition_count INTEGER DEFAULT 0,
    insertion_count INTEGER DEFAULT 0,
    level_before TEXT CHECK (level_before IN ('Beginning','Developing','Proficient') OR level_before IS NULL),
    level_after TEXT CHECK (level_after IN ('Beginning','Developing','Proficient') OR level_after IS NULL),
    flagged_needs_attention INTEGER NOT NULL DEFAULT 0 CHECK (flagged_needs_attention IN (0,1)),
    session_type TEXT NOT NULL CHECK (session_type IN ('Diagnostic','Practice','Assessment')),
    initiated_by TEXT NOT NULL CHECK (initiated_by IN ('Teacher','Parent')), -- always server-derived
    timestamp TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ============================================================
-- 15. personal_word_bank
-- ============================================================
CREATE TABLE personal_word_bank (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    learner_id INTEGER NOT NULL REFERENCES learners(id),
    session_id INTEGER REFERENCES reading_sessions(id),
    word TEXT NOT NULL,
    skill_type TEXT,
    mastery_status TEXT NOT NULL CHECK (mastery_status IN ('Struggling','Improving','Mastered')),
    times_drilled INTEGER NOT NULL DEFAULT 0,
    last_reviewed TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ============================================================
-- 16. badges + learner_badges
-- ============================================================
CREATE TABLE badges (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT,
    point_threshold INTEGER NOT NULL
);

CREATE TABLE learner_badges (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    learner_id INTEGER NOT NULL REFERENCES learners(id),
    badge_id INTEGER NOT NULL REFERENCES badges(id),
    date_earned TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(learner_id, badge_id)
);

-- ============================================================
-- 17. notifications — shared module for Teacher + Parent
-- ============================================================
CREATE TABLE notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    recipient_user_id INTEGER NOT NULL REFERENCES users(id),
    learner_id INTEGER REFERENCES learners(id),
    type TEXT NOT NULL,
    message TEXT NOT NULL,
    is_read INTEGER NOT NULL DEFAULT 0 CHECK (is_read IN (0,1)),
    timestamp TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ============================================================
-- AUTH SUPPORT TABLES (implementation plumbing, NOT part of the
-- business 17 — needed to actually implement Sanctum-style tokens
-- and the 5-attempts/15-min rate limiting rule for both adult and
-- Learner login, exactly as the Backend Implementation Prompt
-- Section 3 describes but without specifying storage mechanics)
-- ============================================================
CREATE TABLE auth_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    token TEXT NOT NULL UNIQUE,
    actor_type TEXT NOT NULL CHECK (actor_type IN ('user','learner')),
    actor_id INTEGER NOT NULL, -- users.id or learners.id depending on actor_type
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    expires_at TEXT
);

CREATE TABLE login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    identifier_type TEXT NOT NULL CHECK (identifier_type IN ('email','learner_id')),
    identifier TEXT NOT NULL,
    failed_count INTEGER NOT NULL DEFAULT 0,
    locked_until TEXT,
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(identifier_type, identifier)
);
