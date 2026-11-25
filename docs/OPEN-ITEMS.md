# TaraBasa AI: everything still unresolved (written 2026-11-21)

One list for later clean-up. It adds to `docs/FINISH-CHECKLIST.md` (written 2026-10-23, older) and `docs/KEEP-IT-ALIVE.md`; where they disagree, this page is newer.
"Who" says who can do it: **Owner**, **Team**, **Adviser**, **Claude** (can be done in a session when asked).
Tick a box when it is really done. Nothing here was assumed done unless it says so.

## A. Live site and hosting (do these first)

- [ ] **Check Render's Environment tab.** Was `PAYMENTS_MODE=demo` saved and deployed? (Owner. It was typed but nobody confirmed it was saved.) Also confirm the two placeholder rows `PAYMONGO_SECRET_KEY` (`sk_test_...`) and `PAYMONGO_WEBHOOK_SECRET` (`whsk_...`) are **not** saved, because their values were the example text and not real secrets.
- [ ] **After that deploy, look at the live Parent Activities page.** It should say "Demo mode" and show "Unlock for ... (demo)". (Owner, screenshot. Claude cannot log in as a parent on the live site.)
- [ ] **The first Render deploy of `9832cc6` failed after 15 min 39 s and the cause was never seen.** A manual retry went Live in 131 s. If it happens again: Deploys, open the failed row, copy the last 30 lines of the log. (Owner)
- [ ] **Render Metrics, Memory chart** (512 MB limit, three queue workers) and a **7 day search of Render Logs** for ERROR or Exception. (Owner)
- [ ] **Teammate services** (BldZeuz): Reading-api has no password; ask for a `levels` option on the generator; the three services belong to one person (add a second owner or fork); keep the shared key in a team password manager. (Owner and BldZeuz)
- [ ] **Admin, System health, "Check services now"**: all four cards should be green, including the Adaptive recommender (its URL is empty on the local copy only). (Owner)
- [ ] **TiDB**: free plan has 50M request units a month and no automatic backups. Export the database every few months and watch "Capacity used this month". (Owner)
- [ ] **Gmail sending**: the token can expire after about 6 months unused. If emails stop: Admin, System health, Reconnect Gmail. (Owner)

## B. The paper (manuscript, 18 Nov 2026 PDF)

Differences between the paper and the real system, with the places to fix:

- [ ] **Software specification paragraph, printed pages 144 and 145** (and XAMPP again on page 142): still lists Kotlin, XAMPP, Bootstrap, Google Cloud Speech-to-Text and a Philippine SMS gateway. The system uses React Native, Laravel, TiDB and Vosk, and has no SMS. (Team: writer)
- [ ] **"Bundled with Vite and Tailwind CSS 4"** (technology paragraph after Table 34.1): the pages use plain custom CSS. (Team)
- [ ] **"Four reading games"** (abstract and conclusion): the system has five (Sound Hunt, added 19 Nov). (Team)
- [ ] **Survey numbers**: the abstract and Appendices D and E say 24 teachers and 86 parents, but Chapter 3 quotes 90.9%, 84.7%, 36.4% and 63.6%, which cannot come from those totals. Reconcile, or explain that they come from an earlier survey. (Team)
- [ ] **Pilot school**: Chapter 3 plans Suba Elementary School in Liloan, Cebu; the scope says a selected school in Cebu City. (Team)
- [ ] **Reference list** (checked against Crossref on 21 Nov): Alrawashdeh et al. (2024) DOI should be `10.1016/j.edurev.2023.100587`; Wang, X. et al. (2024) DOI should be `10.1177/07356331241240459`; **Abocejo (2022)** could not be found and its DOI is an unrelated item, so remove or replace it; **Chu (2021)** has Li as first author in the journal record. (Team)
- [ ] **Earlier review items** (`docs/manuscript-review-v33`, `docs/manuscript-update`, the 2026-11-19 review): the revenue table contradicts the Refund Policy and Terms (no real money is charged); acceptance testing record is not signed; some storyboard text was mixed up; check that no screenshot in the final PDF shows real personal data. Re-check each against the 18 Nov PDF, because some may already be fixed. (Team)
- [ ] **Testing records**: Recommendation 4 itself says the unit, alpha and acceptance testing records still need real results from real users. (Team)
- [ ] **Team roles**: the paper names only Tyron Pulga as Project Manager. Decide each person's role, because the schedule asks one question per role. (Team)
- [ ] **The payment step**: the paper says "simulated payment"; the system now has a demo checkout page. Add one sentence describing it. (Team)

## C. The defense (deck and demo)

Deck, PDF and notes are in `docs/defense/deck/` (`DECK-NOTES.md` has the slide map and timing).

- [ ] **Concept / Explainer video** for slide 2 (the old Canva deck used a classroom video labelled FH Philippines; credit it or replace it). (Team)
- [ ] **Confirm the roles on slide 1** (Hustler, Hacker, Hipster, Writer labels come from the old team slide). (Team)
- [ ] **Rehearse with a timer**; decide who answers which question. (Team)
- [ ] **Demo setup**: demo accounts for Admin, Teacher, Parent and Learner; `PAYMENTS_MODE=demo` in Render; wake the services 5 minutes before (the GitHub "Keep TaraBasa awake" action; the workflow file is on `main`, which is correct); a real Android phone for the reading step; a recorded backup video; the PDF as backup. (Team)
- [ ] **Decide about the deck files**: they contain team photos and the repo is public, so keep them out of Git unless the team agrees. (Team)

## D. Legal and privacy (nobody on the team is a lawyer)

- [ ] **Read the new wording** (version `2026-11-20.2`): Refund Policy, Terms, Privacy, Cookies. The adviser should read it; a lawyer should read the Terms. (Owner, Adviser)
- [ ] Items still open in `docs/legal/LEGAL-RISK-REGISTER.md`: NPC registration check, a short Privacy Impact Assessment, a breach plan, written agreements with the hosts, a retention rule, a lawyer's reading of the Terms, and whether parents who consented earlier must be told that recordings now wait briefly in a temporary file. (Owner, Adviser)
- [ ] **O15**: read Microsoft's terms for generated audio before any Sound Hunt voice clip ships. (Owner)
- [ ] **PayMongo** (only if that path is ever used): read PayMongo's terms, make the account, put the TEST key and webhook secret only in Render, one real test. Unconfirmed details: the retrieve call, the Maya method name, the real webhook body, the lowest amount. Real money is a separate decision. (Owner)

## E. Things only a real test shows

- [ ] **A real child, a real microphone, a real phone.** The scoring has only been tested with computer voices (for example "bat" is heard as "that"). (Team, with consent)
- [ ] **Phone app 0.6.3**: built, never sent or tried on a real phone. The result of the test of 0.6.2 is awaited from the teammate. (Owner)
- [ ] **A consented classroom pilot** (Recommendation 1 of the paper). (Team, Adviser)
- [ ] **Sound Hunt voice**: listen with `tools/voice-check.html` (which spelling of the letter A sounds right), run `python tools/make-voice-clips.py --sample` with your own Azure key and listen (A and I first). No clip has been made yet. (Owner)
- [ ] **A Grade 1 teacher** reads the Sound Hunt word lists and the first reading check texts and game sentences. (Adviser or a teacher)
- [ ] The numbers that move a learner (move up after 3 strong readings, down after 2 weak ones, "not sure" thresholds, alert lines) were set without recordings of real children and need tuning from the pilot. (Team)

## F. Accounts, keys and risk

- [ ] **Credential-looking files in the Downloads folder on the owner's computer**: a Google client secret `.json` file and an AWS access key list `.csv` file. Delete them or deactivate those keys in Google Cloud and AWS. Never paste or commit them. (Owner)
- [ ] **One person owns everything**: add a second owner to GitHub, Render, TiDB and the Google account; keep every login in a shared password manager. (Owner)
- [ ] **C: drive** is nearly full (97% on 18 Nov). (Owner)
- [ ] **Packages**: update about every 3 months (`composer update`, `composer audit`, `composer why-not php 8.4.99` must print "no installed package depending on php", full tests, then push). Last done 18 Nov. (Claude)

## G. Repository clean-up (untracked clutter and old notes)

- [ ] **Untracked in the repo**: `out.txt` (32 KB), `preview/` (9.7 MB), `docs/manuscript-review-v33/` (1.3 MB), `docs/manuscript-roadmap/` (1.5 MB), `docs/manuscript-userguide/` (9.8 MB). Decide for each: commit, move out of the repo, or delete. (Owner)
- [ ] **The other folder** (`D:\GitHub\tarabasa-ai-thesis`, branch `main`) has `CLAUDE.md` modified and an untracked `docs/external-apis/`. Check whether that holds work you want. (Owner, Claude)
- [ ] **Extra worktrees** `admin-dashboard-approvals-62dcd0` (branch `claude/other-website-build-a8e4bf`) and `affectionate-mcclintock-6650cb`: check for unpushed work, then remove. (Claude)
- [ ] **Branch names**: `main` is the old August state and the live branch has a long auto-generated name; Render deploys only `claude/admin-dashboard-approvals-62dcd0`. Decide whether to rename or repoint. (Owner)
- [ ] **Stale notes** (Claude can fix in one pass): `CLAUDE.md` still says "883 tests" and its "State on 2026-11-18" block predates the demo checkout, the merge to live and the defense deck; the `docs/HISTORY.md` section "Demo checkout" still says "BUILT LOCALLY, not committed, not on the live site" (it is committed and live); the defense kit (`docs/defense`) still quotes older test counts.
- [ ] **Pint code style** (138 files, cosmetic): proposed, recommended to skip. (Owner)

## H. Leftovers on this computer (local only, harmless)

- [ ] A **demo Admin account** was created in the local database (`demo.admin@example.com`); extra demo readings, points and drafts remain from testing (you asked not to reset them). Tell Claude if you want a clean slate.
- [ ] The local copy has no recommender URL, so Admin, System health shows "Not set up" **locally only**. It does not affect the live site.
- [ ] Temporary tools and build files are in this session's scratch folder (the deck builder, assets, an npm install). They can be deleted; the deck itself is in `docs/defense/deck/`.

## I. Known small problems

- [ ] **A time-of-day test**: `ParentApiTest::test_alerts_come_newest_first_with_the_day_and_the_unread_count_and_filter_by_kind` fails if it runs within 5 minutes after midnight Manila time (it creates a notification "5 minutes ago"). The code is fine; the test needs a fixed clock. (Claude)
- [ ] **Do not call `/api/learner/diagnostic/passage` in a test sweep**: it starts the child's reading check and blocks every other learner screen until it is finished.
- [ ] The **demo checkout** proves TaraBasa's own screens and rules only. It does not prove that PayMongo or a bank would accept a payment. Say "demo checkout with pretend money", never "payments work".

## J. Optional, later

- [ ] Google Play listing (a personal account needs 12 testers for 14 days; the upload key is not in the repo).
- [ ] Always-on hosting (removes the wake-up delay; about 7 US dollars a month per service, price not checked).
- [ ] iOS, Bisaya voice recognition, a speech model trained on children's voices, real payments, low-connectivity mode (all in the paper's recommendations and roadmap).

## K. Pre-defense check, 25 November 2026

Run on this computer against the LIVE speech service, activity writer and recommender service (computer voices only).

- **Found and fixed (committed and pushed to `mobile-app` on 25 November 2026, NOT yet on the live branch):** a child who had finished the first reading check could start a second one just by asking for a passage again (the Android back button, a stale screen). It locked her out of every other screen until she read a new check and could place her again. Both `GET /api/learner/diagnostic/passage` (now 409 `diagnostic_done`) and the website's `/learner/diagnostic/passage` (now redirects home) refuse it. Three new tests, `FirstCheckNotRestartedTest`; two of them fail without the fix. **To reach the live site it must be merged into the live branch (needs the owner's word).** If it is not live by the defense, do not press back after the first check in the demo.
- [ ] **Render, Environment: is `ADAPTIVE_RECOMMENDER_URL` and its key set?** Without them the app still works (the recommender is only an enhancement, reading never depends on it) but the slide's "a rule-based recommender picks the next activity" would not be true on the live site. See Admin, System health, the Adaptive recommender card. Not checked from here: the key belongs to the host, not to this session.
- [ ] **Phone app 0.6.3** matches the current phone source and its checksum is right, but it has never run on a real phone. The phone shows the new 409 as a message on the first-check intro screen; it does not yet move itself home (that needs a new build).
- Checked and working: 912 tests (915 with the new ones), `tsc` clean, production web bundle of the phone app builds, `composer audit` clean; live site pages 2 s, the three services awake in 23 to 34 s; the phone API for learner, teacher and parent; first reading check (including a 3-item climb); three strong readings from two activities move a child up one step, two weak readings in a row move her down; the move-up counter ignores weak readings; a text too long for a child cannot be assigned; a class with a child of another grade refuses a class-wide assign with a clear reason; games' word and sentence checks, silence, and finishing a game changes no points or level; website scoring in the background (accepted in 0.3 s, done in 9 s); no-credit message.
- Seen, not changed: one activity generation failed once with a Gemini 502 (nothing charged, clear message) and worked on the second try in 13 s, so keep an approved activity ready for the demo. Very short word lists read fast can award several speed badges at once (a computer voice read 8 words at 102 words per minute and won four): a design question, not a crash.
- Local demo data changed by this check: Ana (TB26-68648) and Magda (TB26-37171) finished their first checks and Ana has about ten readings; the demo teacher has 0 free credits; three activities (84, 85, 86) were approved and two assigned to Ana.
