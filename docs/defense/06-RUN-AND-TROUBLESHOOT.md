# 6. Run it on your computer, and what to do when something breaks

Two halves: **Part 1** runs the website and the phone app on your own computer (for practice and the demo). **Part 2** is where to look on the live system (Render, TiDB, GitHub, the helper services) when something is wrong.

Everything here uses **PowerShell**. Open it from the Start menu: type `PowerShell`, press Enter. A black or blue window opens. Each command goes on its own line; press Enter to run it.
**Never paste a password, a token or a key into a chat or a document.** The commands below do not need you to.

---

## Part 1. Run it on your computer

### 1.1 What must be installed (already installed on this computer)
| Tool | What it does | Check it with |
|---|---|---|
| PHP 8.4 or newer | Runs the website | `php -v` |
| Composer | Installs the website's PHP packages | `composer -V` |
| Node.js 22 | Runs the phone app preview | `node -v` |
| Git | Gets the code from GitHub | `git --version` |

On this computer `php -v` showed PHP 8.5.9 and `node -v` showed v22.22.2 on 23 October 2026.

### 1.2 Where the project is
The project folder (the working copy used in this work) is:
```
D:\GitHub\tarabasa-ai-thesis\.claude\worktrees\sleepy-rhodes-2f3ecb
```
The main folder is `D:\GitHub\tarabasa-ai-thesis`. A backup of the code, the phone builds and the helper services is in `D:\TaraBasa-BACKUP-2026-10-23` (open `READ-ME-FIRST.txt` there).

Go into the project folder first, **every time you open a new PowerShell window**:
```powershell
cd D:\GitHub\tarabasa-ai-thesis\.claude\worktrees\sleepy-rhodes-2f3ecb
```

### 1.3 Start the website (window 1)
1. Check the packages are installed (they are; this only repeats if something is missing):
   ```powershell
   composer install
   ```
2. Check the settings file exists. It is called `.env` and holds the settings and secrets. It is **not** in GitHub on purpose. A copy is in the backup folder. If it is missing, copy `.env.example` to `.env`, then run:
   ```powershell
   php artisan key:generate
   ```
3. Make the database tables (the local database is a file, `database\database.sqlite`; this is safe to repeat):
   ```powershell
   php artisan migrate
   ```
4. Start the server on port 8123 (the phone app preview is set to this port):
   ```powershell
   php artisan serve --port=8123
   ```
5. Open the browser at **http://localhost:8123**. Leave this window open. To stop the website press **Ctrl + C**.

No `npm install` or build step is needed for the website. The pages use plain files in the `public` folder.

### 1.4 Start the background worker (window 2)
This is what scores recordings and writes activities in the background. Open a **second** PowerShell window, `cd` into the folder again, then:
```powershell
php artisan queue:work --queue=scoring,default
```
Leave it open. If you forget it, the page that is waiting scores the recording itself after about 12 seconds, so nothing breaks; it is just slower.

### 1.5 Accounts to sign in with
- **Demo teacher and demo parent** (already confirmed, no email needed): run once
  ```powershell
  php artisan db:seed --class=DemoAccountsSeeder
  ```
  The accounts are `demo.teacher@example.com` and `demo.parent@example.com`. **The password is written at the top of the file `database\seeders\DemoAccountsSeeder.php`**; open the file to read it. The seeder creates one class and one child (Maria Demo, PIN 1234). It refuses to run on a live server unless a password is set.
- **Administrator**: the Administrator password is never in the code. Set it for this window only, then create or update the account:
  ```powershell
  $env:ADMIN_PASSWORD = "type-a-12-or-more-character-password-here"
  php artisan admin:sync
  ```
  Sign in at **http://localhost:8123/login?role=admin**.
- **A new account made on localhost**: the confirmation email is **not sent**. It is written to the log file instead. Open `storage\logs\laravel.log`, find the newest line with `email/verify`, copy the full link into the browser. If the link shows "403" or "invalid", the port in the link does not match the one you serve on: set `APP_URL=http://localhost:8123` in `.env` and restart the server.
- **Child sign in**: `http://localhost:8123/learner/login`, with the child's Learner Code and PIN (or Maria Demo and 1234).

### 1.6 Run the phone app preview in a browser (window 3)
The phone app is the folder `mobile`. Its preview runs in the browser, drawn inside a phone.
1. Go into the folder:
   ```powershell
   cd D:\GitHub\tarabasa-ai-thesis\.claude\worktrees\sleepy-rhodes-2f3ecb\mobile
   ```
2. Check that the file `mobile\.env.local` has this one line (it does):
   ```
   EXPO_PUBLIC_API_URL=http://localhost:8123
   ```
   That makes the app talk to your own website instead of the live one. To test against the **live** site instead, change it to the live address and restart (and remember a live test child must be made by you).
3. Start it:
   ```powershell
   npx expo start --web --port 8081
   ```
4. Open **http://localhost:8081**. A wide window shows the app inside a phone frame.
5. Sign in as a child (Learner Code and PIN) or choose "Teacher or Parent? Log in with email" and use the demo accounts.
6. Handy extra pages: `http://localhost:8081/#gallery=results` (screens with sample data) and `#gallery=crash` (the crash screen).

**The preview is the same app code, not a real phone.** It cannot use a real phone's microphone, camera or speech voice the same way. Say so if asked.

### 1.7 Run the tests
In the project folder (not `mobile`):
```powershell
php -d memory_limit=1G artisan test
```
It takes a few minutes. At the last check **788 tests passed**. A single test file:
```powershell
php artisan test tests/Feature/LetterNamesTest.php
```
The phone app's type check:
```powershell
cd mobile
npx tsc --noEmit
```
No output means no errors.

### 1.8 Look inside the database (carefully)
Use the interactive console, not one long command with quotes (PowerShell mangles quotes):
```powershell
php artisan tinker
```
Then type PHP at the prompt, for example `App\Models\Learner::count()` and press Enter. Leave with `exit`. **Do not run this against the live database.** Your `.env` points at the local file; keep it that way unless you mean otherwise.

### 1.9 Build the Android file (APK)
Long and exact; follow `mobile\HANDOVER.md`, section "Build an Android APK (Windows)". The short version: build from the short-path copy `D:\tb` (Windows long paths break the build), with Java 17 from `D:\tools\jdk-17`; the finished files are in `D:\tools\apk-out`. The latest is `TaraBasa-AI-0.5.7-test17.apk` with a tester pack zip. Close other programs first: the build needs a lot of memory, and it failed once with "out of memory" until memory was freed.

### 1.10 Common local problems
| What you see | Likely reason | Fix |
|---|---|---|
| "Address already in use" or the site does not start | Another server is on port 8123 | Close the other PowerShell window, or use `--port=8124` (then change `mobile\.env.local`) |
| "No application encryption key has been specified" | `.env` is missing the key | `php artisan key:generate` |
| "Base table or view not found" | Tables not made | `php artisan migrate` |
| "could not find driver" (SQLite) | A PHP extension is off | In `php.ini` remove the `;` before `extension=pdo_sqlite` and `extension=sqlite3` |
| Reading or activity writing says "waking up" or fails | The helper services (on the teammate's hosting) are asleep | Wait about a minute and try again, or run the keep-awake button (Part 2, section 2.5) |
| "cURL error 60: unable to get local issuer certificate" | PHP has no certificate bundle | Point `curl.cainfo` and `openssl.cafile` in `php.ini` to `F:\php\extras\ssl\cacert.pem` (never turn off verification) |
| The phone preview shows "We could not reach TaraBasa" | The website (window 1) is not running, or the port differs from `.env.local` | Start the website on 8123, restart `npx expo start` after changing `.env.local` |
| The sign-in link from a new account says 403 | `APP_URL` does not match the port | Set `APP_URL` to the address you open in the browser |
| `php artisan` is not recognised | You are not in the project folder | `cd` into it (section 1.2) |
| A page shows old styles | Browser cache | Hold **Ctrl + F5** |
| Tests fail with GD missing | PHP's image extension is off | Enable `extension=gd` in `php.ini` (the photo tests no longer need it) |

---

## Part 2. When something breaks on the live system

### 2.1 The first minute: find out which part is broken
Do these in order. Each takes seconds.

1. **Is the website up?** Open `https://tarabasa-ai.onrender.com/up`. A short "up" page means the server answers. If it takes about a minute, the free service was asleep; that is normal, not a fault.
2. **Is it one person or everyone?** Ask someone else. Try another browser. Try your phone's mobile data.
3. **Which of the four services?** Open each in the browser:
   - Website: `https://tarabasa-ai.onrender.com/up`
   - Reading checker: `https://reading-api-v5fp.onrender.com/health`
   - Activity writer: `https://acitvity-api.onrender.com/health`
   - Recommender: `https://adaptive-recommendator.onrender.com/health`
   (The activity writer's address really is spelled `acitvity`; that is how it was created.) A page with `ok` or a short JSON answer means awake; a wait of up to a minute then an answer means it was asleep.
4. **Ask the system.** Sign in as Administrator, open **System health**, press **Check now**. It wakes the services from your browser, tests them, and shows Working / Asleep / Needs you, with the reason, for the reading checker, the activity writer, the recommender and email sending.
   - **Asleep** is not a fault. Press the button again after a minute.
   - **Needs you** is a real problem; read the message under the card.

### 2.2 Symptom to likely cause to what to do
| Symptom | Likely cause | What to do |
|---|---|---|
| The whole website shows **502** or "Bad Gateway" | The server was restarted, ran out of memory, or the host stopped sending traffic because the health check failed | Render, open service `tarabasa-ai`, **Events**. Look for "health check failed", "Out of memory" or a deploy. Wait 2 to 5 minutes; if it does not recover, press **Manual Deploy**, then **Deploy latest commit** |
| First load takes about a minute | The free service was asleep | Normal. Use the keep-awake button before a test or the defense |
| A child's recording ends in "We could not reach TaraBasa" | Slow or dropped internet, or the reading checker was asleep | Press **Send Again** (the recording is kept). If it repeats, open `reading-api.../health` and wait for it to answer, then try again |
| "The reading checker is waking up" | Normal wake-up wait | Wait up to a minute; it is shown on purpose |
| Reading says "Didn't quite catch that" three times | The child was silent or the microphone is blocked | Check the microphone permission in the browser or phone settings |
| Writing activities stays on "Writing..." for a long time | The activity writer was asleep, or a job is stuck | A request writes in about 2 minutes (longer if asleep). After 20 minutes it closes as failed and charges nothing. Check System health; try again |
| No confirmation email arrives | Gmail connection expired, spam folder, or a wrong address | System health, "Email sending" card. As Administrator, use **Confirm email myself** with a reason. To reconnect Gmail see section 2.6 |
| Can't sign in: "email not confirmed" | The link was never opened | Press **Send me a new confirmation link** on the sign-in page, or the Admin confirms it by hand |
| Teacher sees "pending" and cannot touch students | Not approved yet | Administrator, **Approvals**, Approve (the teacher's email must be confirmed first) |
| Child locked out | Five wrong PINs | Wait 15 minutes, or a guardian makes a new PIN: **Children**, **New PIN** |
| App shows "TaraBasa did not answer in time" | The server is slow or asleep | Open `/up`, wait, retry |
| Everything was fine and now database errors appear | TiDB quota or connection problem | Section 2.4 |
| A page is blank after a deploy | A bad deploy | Roll back (section 2.7) |

### 2.3 Render (the website host): where to look
Sign in at **dashboard.render.com**, open the service **tarabasa-ai**. Tabs on the left:
- **Events**: a timeline. Look for **"Deploy live"** (a deploy finished), **"Deploy failed"**, **"Instance failed ... health check failed"**, **"Out of memory"**, **"Service recovered"**. This tells you whether the host restarted the service.
- **Logs**: the server's own messages. Use the search box. Useful words:
  | Search for | Meaning |
  |---|---|
  | `Recording: scoring started` / `Recording: scoring finished` | One line each time a recording is scored; the finished line shows the seconds and the memory used. Many "started" without "finished" means the speech service is slow |
  | `Unable to respect the PHP_CLI_SERVER_WORKERS` | **Must NOT appear.** If it does, the server is back to one worker |
  | `Maximum execution time` | Something waited longer than allowed (usually a sleeping service) |
  | `Out of memory` or `Killed` | The 512 MB limit was hit |
  | `SQLSTATE` | A database problem (read the words after it) |
  | `cURL error 60` | A certificate problem on an outside call |
  | `429` | An outside service said "too many requests" (usually asleep) |
  | `queue:work` or `scoring` | Background workers starting |
- **Metrics**: **Memory** and **CPU** graphs. A memory line touching the limit means the plan is too small (see file 04).
- **Environment**: the settings. **Never copy values into chats.** Names to know: `APP_KEY`, `APP_DEBUG` (must be `false`), `DB_*` and `MYSQL_ATTR_SSL_CA` (database), `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`, `QUEUE_POP_LOCK=plain`, `MAIL_MAILER=gmail-api` with `GMAIL_*`, `READING_AI_URL`, `ACTIVITY_AI_URL` and its key, `ADAPTIVE_RECOMMENDER_URL` and its key, `ADMIN_PASSWORD`, `SCORING_WORKERS`, `API_CONSENT_REQUIRED`, `PRIVACY_CONTACT_EMAIL`, `LEGAL_OPERATOR_DETAIL`, `LEGAL_ACCOUNTABLE_PERSON`. Changing a setting restarts the service.
- **Settings**: the **Health Check Path** is cleared on purpose (a busy server could fail the check and be cut off). Leave it unless you know why.
- **Billing** (account menu): the free plan gives 750 instance hours a month. A sleeping service uses none; running out suspends the free services until the 1st.

### 2.4 TiDB Cloud (the database): where to look
Sign in at **tidbcloud.com**, open the cluster `tarabasa`.
- **Overview / Usage**: shows **request units used this month** against the free **50 million**. If it reaches the limit, new connections are refused until the 1st. Check weekly until the end of November. Expect a few million.
- **Monitoring**: queries per second and latency. A spike means something is hammering the database.
- **Backup**: the free plan lists no automatic backup. Take your own export before the defense if you can (see `docs/KEEP-IT-ALIVE.md`).
- **Settings, Networking**: the connection details. Do not post them.
- If the site shows database errors but TiDB looks fine, it may be the server restarting; check Render Events first.

### 2.5 The keep-awake button (before a test and before the defense)
Why: the website and the three helper services sleep after about 15 idle minutes; a test would start with a one-minute wait.
1. Go to GitHub: `github.com/Lando-ssd/tarabasa-ai-thesis`, tab **Actions**, workflow **Keep TaraBasa awake**.
2. Press **Run workflow**, choose how many hours (1 to 5), press the green button.
3. It pings all four services every 4 minutes for that long. Start it 15 minutes before you need it.
4. If the workflow is missing on the page, it lives on the `main` branch; check the branch selector.
(`docs/KEEP-IT-ALIVE.md` section 5 has the details, and a way to do the same from this computer.)

### 2.6 Email and Gmail
- **Where to see the state:** Administrator, **System health**, the "Email sending" card. A test checks, without sending, that the stored Gmail token still gives access.
- **If the token expired** (Google's consent screen in "Testing" expires tokens after 7 days; ours is set to "In production"): sign in as Administrator, open `/internal/gmail-authorize`, sign in as the sender Gmail account, copy the printed token into the `GMAIL_SEND_REFRESH_TOKEN` setting on Render, and let it restart. A person has to paste it; the app cannot write host settings.
- **Meanwhile:** the Administrator can confirm any teacher's or parent's email by hand (**Confirm email myself**), with a reason that is logged.

### 2.7 Undo a bad change
1. **Render roll back:** service, **Events** or **Deploys**, find the last good deploy, **Rollback** (or redeploy that commit).
2. **Git undo:** on the live branch, `git revert <bad-commit>` then push. Do not use `--force`. If you are unsure, ask before pushing; a push to the live branch restarts the website.
3. **Branches:** the live site deploys from `claude/admin-dashboard-approvals-62dcd0`. The branch `mobile-app` does not deploy. The branch `main` is old (about 112 commits behind) and is not used for the live site.

### 2.8 If a teammate's service is down
The reading checker, the activity writer and the recommender run on the teammate's hosting. If one is gone, sign in and the rest still work, but readings or activity writing fail with a friendly message. Offline copies and a rebuild plan are in `docs/KEEP-IT-ALIVE.md` section 7 and the backup folder. Ask the teammate first.

### 2.9 If the phone app says it cannot reach TaraBasa
1. Is the website up? (`/up` in the phone's browser.)
2. Is the phone online? Try mobile data, then Wi-Fi.
3. The app retries a quick failure by itself twice. If it still fails it shows a **Technical note** line (what failed, how many tries, the app version). Take a screenshot of that line; it is how we tell a network problem from a server problem.
4. Is the app version old? Builds 0.5.4 and older cannot sign up because consent boxes are now required; install **0.5.7**.
5. A reading that was too quiet is not sent; the app says so.

### 2.10 Five minutes before the defense: a checklist
- [ ] The keep-awake workflow has been running for 15 minutes or more (Actions tab shows it green).
- [ ] `https://tarabasa-ai.onrender.com/up` answers fast; the three `/health` addresses answer.
- [ ] Administrator: System health shows Working on all four cards.
- [ ] You can sign in as the demo teacher, the demo parent and as a child (Maria Demo) on the **live** site or on localhost, whichever you demo. Make a test child on the live site yourself beforehand; do not create accounts during the defense.
- [ ] A child recording has been scored once today (so the reading checker is awake and the microphone permission is already granted).
- [ ] Laptop plugged in, notifications off, phone volume up, browser zoom 100 percent.
- [ ] A second device (the phone) is signed in as a child on mobile data, in case the room Wi-Fi fails.
- [ ] Screenshots of each main screen are saved in a folder, in case the internet fails.
- [ ] This kit is open on the other screen.

**If the live demo fails:** say one calm sentence ("The free hosting is waking up; let me show you the localhost version"), switch to localhost (Part 1) or to your screenshots, and carry on with the story. A panel judges how you recover, not only whether it works.
