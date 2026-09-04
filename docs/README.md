# /docs — Reference Material for Claude Code

Everything in here is read-only reference material, not application code.
It's organized by how trustworthy/current each thing is, exactly like the
"current vs. stale" distinction established for this project from the start.

## manuscript/ — HIGHEST AUTHORITY

`TARABASA-AI-Final-Manuscript23.pdf` is the actual graded thesis manuscript.
If anything else in this project ever conflicts with it, the manuscript
wins — flag the conflict, don't silently pick a side.

The two MATATAG curriculum guide PDFs are DepEd's official curriculum
reference — relevant to the AI content-matching / curriculum guide
features (later modules), not needed for Sprint 3 (Account Management).

## actor-prompts/ — CURRENT, per-role detailed behavior specs

These describe exactly how each actor (Teacher/Parent/Admin/Learner)
should behave — UX flows, business rules, edge cases. Must match the
manuscript, not override it. Before building ANY new user-facing screen,
check the relevant actor prompt for the specific step being built —
don't improvise flow behavior.

Note: only the corrected/updated versions are included here. Older
duplicate drafts of these same files exist in Project Knowledge on
claude.ai but were deliberately left out of this folder to avoid
confusion — these versions here are the ones to trust.

## patches-and-additions/ — CURRENT, override whatever they patch

These are later additions/corrections layered on top of the manuscript
for specific features (school year handling, activity content rules,
learner history, repository ratings, diagnostic corrections, etc.).
They take precedence over the base manuscript wherever they overlap.

Note: `TaraBasaAI_Master_Plan_Consolidated.txt` was originally filed
here by mistake — it describes an abandoned Firebase/Firestore
architecture that directly conflicts with the confirmed Laravel +
MySQL/SQLite stack, so it was never actually a valid patch. It has
been moved to `STALE-DO-NOT-USE/` below.

## database-reference/ — CURRENT, matches the Data Dictionary

`schema.sql` is the full 20-table relational design. The actual Laravel
migrations built so far (users, teachers, parents, personal_access_tokens)
were translated from this file — future tables (classes, activities,
open_repository_listings, etc.) should be translated the same way when
those features get built.

## design-reference-html/ — LOCKED design system, current

Static HTML/CSS prototypes for every screen across the whole app —
including many screens not built yet (Admin dashboard, Class management,
Teacher dashboard, Open Repository, etc.). These define the exact visual
language (Baloo 2 + Inter fonts, specific color tokens, no emoji,
restrained motion) that every real Blade view must match. When building
a new screen, find its matching prototype here first and enhance from it
— don't design from scratch, and don't deviate from the token system.

## old-backend-reference-DO-NOT-RUN/ — SUPERSEDED, logic reference only

This was the original plain-PHP prototype backend, built before the
project restarted in real Laravel. The architecture itself is abandoned
— don't run this code, don't copy files from it directly into the
Laravel app. But the business logic inside it (validation rules, field
names, what each test script checks) is often correct and worth reading
before building the equivalent feature in Laravel, since it reflects an
earlier working implementation of the same actor-prompt rules.

## STALE-DO-NOT-USE/ — explicitly abandoned, kept only as a warning

Firebase-based drafts from before the architecture was finalized as
Laravel + MySQL/Cloud SQL. Do not build toward anything in this folder.
It's kept only so nobody accidentally resurrects it by mistake.
Includes `TaraBasaAI_Master_Plan_Consolidated.txt` (moved here from
`patches-and-additions/` — see the note there), which describes a
Firebase Auth + Cloud Firestore + Cloud Functions architecture that was
considered and abandoned before this project settled on Laravel.
