# Keeping TaraBasa AI alive (written 2026-10-23)

The system can stay alive through 2027 on the free setup. "Alive" does not mean "no waiting": the free hosts put
services to sleep, and you wake them with the keep-awake button (see section 5). What can kill the system is mostly
outside the code: accounts, keys and other people's services. This file lists them, most dangerous first.

Read this together with `docs/FINISH-CHECKLIST.md` (what is left to do) and `CLAUDE.md` (every decision and limit).

## 1. What runs where

| Part | Where | Who owns the account | Free limit that matters |
|---|---|---|---|
| Website + phone API | Render, service `tarabasa-ai` (free), https://tarabasa-ai.onrender.com | Lando-ssd (Render "My Workspace") | sleeps after 15 quiet minutes; 750 free hours a month per account; no persistent disk |
| Database | TiDB Cloud Starter, cluster `tarabasa`, Singapore | "Lando-ssd's Org" | 50M request units a month; 5 GiB; no automatic backups mentioned for the free plan |
| Reading checker (Reading-api) | Render, https://reading-api-v5fp.onrender.com | teammate BldZeuz (not verified) | same Render free rules; has NO password |
| Activity writer | Render, https://acitvity-api.onrender.com (the spelling is real) | teammate BldZeuz (not verified) | same; uses a Google Gemini key |
| Recommender | Render, https://adaptive-recommendator.onrender.com | teammate BldZeuz (not verified) | same |
| Email sending | Gmail account `tarabasaai.noreply@gmail.com` through Google's Gmail API | team | the token can expire (see section 2, item 2) |
| Code | GitHub `Lando-ssd/tarabasa-ai-thesis` (public) | Lando-ssd | none; branches `claude/admin-dashboard-approvals-62dcd0` (deployed), `mobile-app`, `main` |

Render deploys ONLY the branch `claude/admin-dashboard-approvals-62dcd0`. Every push to it rebuilds and restarts the site (about a minute).

## 2. What can break it, most dangerous first

1. **The three helper services belong to your teammate.** If he deletes them, they lapse, or the Gemini key or model changes, then
   scoring, activity writing and recommendations stop, with no change on our side.
   Ask him to: keep them running; add a second owner or fork them into a team account; write the shared key (`ACTIVITY_AI_KEY`, also used for the
   recommender) in the team's password manager; and put a password on the Reading-api service.
   Their code is on his GitHub (`BldZeuz/Reading-api`, `gemini_activity_gen`, `Adaptive_Recommendator`), so they can be redeployed if the code stays public.
2. **Email sending.** Needs the token in `GMAIL_SEND_REFRESH_TOKEN`. Google can expire a token that has not been used for about 6 months, or if the
   Gmail account's password or security settings change. Do not change that account's password. If emails stop: Admin > System health > Reconnect Gmail,
   then paste the new token into Render > Environment and redeploy. The Email card on System health shows whether it works.
3. **One person owns everything.** Add a second owner to GitHub, Render, TiDB and the Google account. Keep every login and key in a shared
   password manager. Without the Google account you lose email sending; without the Render account you lose the site.
4. **Free-tier rules change.** Read emails from Render and TiDB (use an address you read). Render's docs say terms can change.
5. **Data.** TiDB's free plan lists no automatic backups: export the database every few months (TiDB console > Data > Export) and keep it with the
   backup. Before real children use the system, work through `docs/legal/LEGAL-RISK-REGISTER.md`, including a rule for deleting old accounts.
6. **Updates.** The server (PHP, Laravel) needs a security update session about every 3 months (`composer audit`, update, run the tests, deploy), or you
   accept that risk for a thesis demo. The phone app APK does not expire.
7. **Secrets.** Never change `APP_KEY` (it breaks sessions). `ADMIN_PASSWORD` in Render sets the Admin login. Copies of the settings are in the
   backup folder (the `.env.render` copy is from 6 October and older than Render's current values: Render's Environment tab is the truth).

## 3. The free limits, measured on 2026-10-23

- Database: 3.25 million of 50 million request units used this month. The background workers ask for work every 2 seconds, which costs about
  **25 request units per second while the website is awake** (about 2.2 million a day), even when nobody is reading. A sleeping site costs nothing.
  So the free plan lasts about 3 weeks if the site is kept awake 24/7. That is why the keep-awake button is for hours, not days.
- Render: a service that is asleep uses no free hours. One service awake all month is about 744 of the 750 hours. If the teammate's three services
  share one account, keeping all of them awake 24/7 would use about 2,200 hours and Render would suspend them until the 1st.
- Optional saving (not done): ask every 5 seconds instead of 2 in `docker/entrypoint.sh` (`--sleep=2` on the default worker) to roughly halve the idle cost.

## 4. A 10-minute monthly routine

1. Admin > System health > **Check services now**. All four cards should be green.
2. Render > `tarabasa-ai` > Events: look for "Instance failed".
3. TiDB cluster Overview > "Capacity used this month": keep it under about 40M.
4. Render billing: free hours well under 750.
5. Press the keep-awake button (section 5) and watch that all four lines say 200.

## 5. The keep-awake button (before a test or the presentation)

The website and the three helper services sleep after 15 quiet minutes, and the first reading after a pause can fail with "error 429" while the
reading checker wakes (it takes 25 to 45 seconds).

GitHub > this repository > **Actions** > "Keep TaraBasa awake" > **Run workflow** > choose 1 to 5 hours > Run workflow.
Open the running job and its step "Ask all four addresses every 4 minutes": when all four lines say 200, everything is awake. Press it about
5 minutes before you start, for at least as many hours as the presentation lasts. To stop early, open the job and press Cancel workflow.
The file is `.github/workflows/keep-awake.yml` and must stay on the `main` branch for the button to show. It is not scheduled on purpose.

If you need guaranteed zero waiting for the defense month, Render's paid plan has no sleep. I believe it is roughly 7 US dollars a month per
service, but I did not check the current price. The three helper services would have to be upgraded on your teammate's account.

## 6. If you lose access to GitHub

A full offline copy of the code history (all branches) was saved on 2026-10-23 as
`D:\TaraBasa-BACKUP-2026-10-23\code-history\tarabasa-ai-thesis-all-branches.bundle`. To get a working copy back:
`git clone tarabasa-ai-thesis-all-branches.bundle tarabasa-copy`, then `cd tarabasa-copy` and `git branch -a`.
This is the same code as the public repository: it contains no passwords. Keep this copy on a USB drive or cloud folder too.

## 7. If the teammate's three services ever disappear

The app cannot score a reading, write an activity or recommend a story without them (section 1). Their code is public on GitHub, and a full copy of
all three was saved on 2026-10-23 in `D:\TaraBasa-BACKUP-2026-10-23\teammate-services\` (with the same recipe in its `READ-ME-FIRST.txt`):

| Service | Repository | Copied at commit | What it needs to run |
|---|---|---|---|
| Reading-api | `BldZeuz/Reading-api` | `fa4905b` (6 Oct 2026) | a Docker build; it downloads the free Vosk model `vosk-model-small-en-us-0.15` from alphacephei.com; no password |
| Activity writer | `BldZeuz/gemini_activity_gen` | `c5cc308` (22 Sept 2026) | `GEMINI_API_KEY` (your own, free from Google AI Studio), `APP_API_KEY` (a secret you invent), `GEMINI_MODEL`, has `render.yaml` |
| Recommender | `BldZeuz/Adaptive_Recommendator` | `f7a801e` (6 Oct 2026) | `APP_API_KEY` (a secret you invent), has `render.yaml` |

How to rebuild (a plan, NOT tested):
1. Put each service in a repository on your team's own GitHub. There is no license file, so ask the author before publishing a copy.
2. On your own Render account: New > Web Service > that repository > the FREE instance. Choose "Docker" for Reading-api (its first build takes several minutes).
3. Type the settings from the table above in each new service. Check that the model named in `GEMINI_MODEL` still exists at Google; models get retired.
4. In this website's Render service (`tarabasa-ai`) > Environment, replace `READING_AI_URL`, `ACTIVITY_AI_URL`, `ADAPTIVE_RECOMMENDER_URL`,
   `ACTIVITY_AI_KEY` (the writer's `APP_API_KEY`) and `ADAPTIVE_RECOMMENDER_KEY` (the recommender's `APP_API_KEY`), then Save, rebuild, and deploy.
5. Check: Admin > System health > Check services now. All four cards should be green; then try a reading and an activity.
6. Update the three addresses in `.github/workflows/keep-awake.yml` (it pings them) and in section 1 of this file.

The copies are the latest on GitHub, which may not be exactly the version running on Render today (the app copes with the recommender in
version 1 or 2).

## Sources checked on 2026-10-23

- Render free tier terms: https://render.com/docs/free (15 minute spin down, 750 free hours per user or team a month, no persistent disk on free services)
- TiDB Cloud Starter limits: https://docs.pingcap.com/tidbcloud/serverless-limitations/ (5 GiB, 50M request units a month, new connections refused when the
  quota is used up; the page does not mention inactivity or automatic backups for the free plan, which is not a promise that none exist)
