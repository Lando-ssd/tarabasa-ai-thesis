# TaraBasa AI — Project Context for Claude Code

This file is read automatically by Claude Code at the start of every session
in this folder. It exists because Claude Code has NO access to the chat
history where these decisions were made — this file is the only way it
knows any of this.

## What this project is

TaraBasa AI — an AI-powered adaptive reading intervention platform for
Grade 1–3 Filipino learners. Capstone/thesis project, 4-person team.
Four actor types: Learner, Teacher, Parent, Admin.

## Tech stack (confirmed, do not change without asking the user)

- **Backend:** PHP 8.5 + Laravel 13, plain PHP router replaced with real
  Laravel (full framework rebuild happened after this project started
  as a lighter custom-PHP app — that old version is abandoned).
- **Database:** SQLite for local dev (`database/database.sqlite`),
  MySQL/Cloud SQL for eventual production. Same relational schema
  either way — this is a config swap later, not a rewrite.
- **Auth:** Laravel Sanctum installed (for future API/mobile use).
  Current web forms use plain session-based `Auth::login()`, not
  Sanctum tokens yet.
- **Email:** Real Gmail SMTP (`tarabasaai.noreply@gmail.com` + a Google
  App Password), configured in `.env` as `MAIL_MAILER=smtp`. NOT Brevo
  — a Brevo account was tried first but got suspended before use;
  abandoned entirely, don't suggest going back to it.
- **Frontend:** Blade templates using a LOCKED design system — Baloo 2
  (headings) + Inter (body) fonts, specific color tokens (see any
  `layouts/auth.blade.php` file's `:root` CSS variables), zero emoji,
  restrained motion. Match this exactly on every new screen; don't
  introduce a different visual style.
- **Version control:** GitHub repo `Lando-ssd/tarabasa-ai-thesis`
  (public). The user is not experienced with git — explain commands
  plainly if you run them, and confirm before pushing.

## Important naming gotcha

The Parent model is named `ParentAccount`, NOT `Parent` — `Parent` is a
PHP reserved word (used for `parent::method()` calls) and causes a
syntax error if used as a class name. It maps to the real `parents`
database table via `protected $table = 'parents';`. Don't rename it
back to `Parent`.

## Current status (Sprint 3 — Account Management)

Done and tested by the user:
- Database: `users`, `teachers`, `parents` tables (migrations exist)
- Teacher registration (`/register/teacher`) — full stack, working,
  sends real verification email
- Parent registration (`/register/parent`) — same, working
- Login (`/login`) — shared form for Teacher/Parent/Admin, role-aware
  via `?role=` query param, generic "Invalid email or password"
  message (never reveal which field was wrong — this is an explicit
  requirement from the Admin actor prompt), rate-limited (5 attempts/
  60s cooldown via Laravel's `RateLimiter` facade)
- Homepage/role-select (`/`) — real landing page, not Laravel's default
  welcome page
- Real logo wired in (`public/images/logo.png`) across all auth screens

Not started yet (rest of Sprint 3):
- Admin approval screen (view pending Teachers, Activate/Reject)
- Class management (create/edit classes, add learners)
- Learner account creation flow (Parent-driven)
- Learner login (avatar → PIN, no password — different mechanism
  entirely from adult login)

## Design/UX rules to follow on every new screen

1. **All reference material lives in `/docs` — read `/docs/README.md`
   first.** It explains which subfolder is the manuscript (highest
   authority), which are current actor prompts, which folder has the
   locked design-system HTML prototypes for screens not built yet, and
   which folders are explicitly stale/superseded and must never be
   built toward. Quote the actual actor-prompt step (in
   `/docs/actor-prompts/`) before building any user-facing flow — don't
   improvise login/registration/approval behavior. Find the matching
   prototype in `/docs/design-reference-html/` before styling any new
   screen — don't design from scratch.
2. A Pending Teacher can log in immediately with limited access (2 free
   AI-generation credits, no real students) — Admin approval only gates
   "touching real students," not login itself.
3. Admin has no self-registration — exactly one seeded account, ever.
4. Build in small, complete, vertical slices — one full-stack feature
   at a time (migration → model → controller → route → Blade view),
   not partial layers across many features at once. Tell the user
   clearly what's done vs. deferred after each slice.

## Open, unresolved decision (don't act on this without asking)

A teammate ("BldZeuz") independently built two working, deployed Python
services on Render:
- `Reading-api` — purpose not yet compared against this project's plan
- `gemini_activity_gen` — AI activity generation via Gemini

Whether to integrate with these (Laravel calls them over HTTP) instead
of building equivalent PHP logic ourselves is UNDECIDED — flagged for
when Sprint 4/5 (Reading Assessment / Activity Generation) actually
starts. Don't build either integration or a from-scratch replacement
without the user weighing in first.

## The user's working style

- Limited hands-on coding experience — explain what you're doing and
  why in plain language, not just diffs.
- Prefers being walked through GUI steps (File Explorer, PowerShell,
  GitHub Desktop) explicitly — don't assume familiarity with terminal
  conventions.
- Wants real, working features tested end-to-end before moving to the
  next piece — not multiple half-built things at once.
