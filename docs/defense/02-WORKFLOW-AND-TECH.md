# 2. What happens behind the screens: workflow, tech stack, web versus app, network

Use this file to answer: "How is it connected?", "Where does the data go?", "Why this technology?" Each flow is written the same way: **what the user does, then what the system does, step by step.**

## 1. The big picture in one paragraph

A user opens the **website** or the **phone app**. Both talk over **HTTPS** to one **Laravel** server (PHP 8.4, in a Docker container on **Render**, Singapore). The server stores everything in one
**TiDB Cloud** database (MySQL compatible, Singapore). For the three jobs it cannot do alone, it calls three small services that your teammate built and hosts on Render: the **reading service** (Vosk speech
recognition), the **activity writer** (Google's Gemini language model) and the **recommender** (formulas). Account emails go out through the **Gmail API**. The code lives on **GitHub**, and every push to one branch rebuilds the
server on Render automatically.

## 2. The network model (draw this from memory)

```
  PHONE APP (Expo / React Native, Android)              BROWSER (any device)
     learner, teacher, parent                              learner, teacher, parent, ADMIN
        |  HTTPS, JSON                                        |  HTTPS, web pages
        |  login token (Sanctum)                              |  login cookie (session)
        +---------------------------+-------------------------+
                                    |
                                    v
                 +-------------------------------------------+
                 |  RENDER web service "tarabasa-ai"          |
                 |  Laravel 13, PHP 8.4, Docker, Singapore    |
                 |  - website pages (Blade) + JSON API        |
                 |  - rules: levels, alerts, patterns, fit    |
                 |  - background workers (scoring, writing)   |
                 +--+----------+-----------+----------+-------+
                    |          |           |          |
        SQL (TLS)   |   HTTPS  |   HTTPS   |  HTTPS   |  HTTPS (OAuth)
                    v          v           v          v
             +-----------+ +---------+ +----------+ +-------------+  +-----------+
             | TiDB Cloud| | READING | | ACTIVITY | | RECOMMENDER |  | Gmail API |
             | database  | | SERVICE | | WRITER   | | (formulas)  |  | (emails)  |
             | Singapore | | Vosk    | | Gemini   | |             |  +-----------+
             +-----------+ +---------+ +----------+ +-------------+
                           these three: teammate's Render account, free plan (they sleep)

  GitHub (code)  --push to branch-->  Render builds the Docker image and restarts the server
  GitHub Actions: the "Keep TaraBasa awake" button that pings the four addresses
```

Say it in one breath: **"Clients talk to one Laravel server. The server owns the data in TiDB and asks three specialist services for speech scoring, activity drafts and recommendations, and uses Gmail for emails."**

## 3. The main flows, step by step

### Flow A. Sign up, confirm the email, sign in (parent or teacher)

1. The user fills the form on the website, or in the app, which sends the same request to the JSON interface (`/api/auth/register/...`). Both go through **one shared service** on the server, so the rules cannot differ between web and app.
2. The server validates: names are letters only, the password is at least 8 characters, the two passwords match, the email's domain can receive mail (a typo like `gmial.com` gets a "Did you mean gmail.com?"), the consent box is ticked.
3. It writes the account to the database: the **password is hashed** (a one-way scramble; nobody, including us, can read it), a parent or teacher profile row, and a consent record (which policy version was shown, the time, web or app). A teacher starts as Pending.
4. It sends the confirmation email through the **Gmail API** (HTTPS with an OAuth token, from the project's own Gmail account). The link is **signed** (it cannot be forged) and expires in 60 minutes. If the email cannot be sent, the account is removed again so nothing unusable is left.
5. The user clicks the link; the server checks the signature, marks the email as confirmed, and shows "Email confirmed".
6. Sign in: on the **website** the server creates a session, stored in the database, and gives the browser a cookie. In the **app** it issues a **Sanctum token** that the app keeps on the phone and sends with every request.
   A wrong password five times locks sign-in for a minute. A teacher who is not approved yet can sign in but cannot touch any real child.

Why the email step exists: it proves the address is real, and it protects against someone creating an account for another person.

### Flow B. A child logs in

1. The child scans the QR card (the camera is read **on the phone**; only the code leaves) or types the code, then the PIN.
2. The server checks the code's check digit first (so a typo is caught without a database search), then finds the child and compares the **hashed PIN**.
3. Wrong PIN five times: locked for 15 minutes, and every linked guardian gets an alert with what to do. The error for a wrong code and a wrong PIN looks the same, so nobody can find out which codes exist.
4. A child's session is separate from an adult's, so a parent and a child can be signed in on the same device.

### Flow C. The first reading check

1. On first login the server notes the child has no check yet and sends the child to the check.
2. It chooses the first text from a **curated bank** (21 texts, coded to MATATAG, stored in the app) by the parent's answers, not by grade. This is instant; no AI is called.
3. The child records. The app or page records audio, the microphone is first tested **on the device** (silence is caught before anything is sent).
4. The recording is uploaded to the server over HTTPS.
5. The server sends it to the **reading service**. The service converts it to 16 kHz audio (ffmpeg), runs **Vosk** speech recognition, and returns the heard words with timing and confidence, and a few scores (words per minute, a speed score, a prosody score).
6. Our server **cleans and compares**: it lines the heard words up against the text, accepts words that sound the same, leaves faintly heard words out ("Not sure"), and for letters repairs known recognizer habits. It computes the accuracy.
7. It applies the staircase (90 up, under 70 down, 70 to 89 stop; letters 80 up), picks the next text or finishes, saves the child's step and the first badge, and tells the parent.
8. The recording is deleted.

### Flow D. A normal reading (what the child does every day)

1. The child opens a story (assigned by a teacher, unlocked by a parent, or recommended) and records.
2. **Website**: the page uploads the recording in the background and gets a ticket number. A **queue worker** takes the job, calls the reading service, cleans the result, and saves it; the page asks "done yet?" every 2 seconds and shows the result.
   This design keeps the web server free; before it, one slow recording could block every other user.
   **Phone app**: the scoring runs inside the upload request itself, with at most two at a time. If the server is busy the app says so and offers Send Again. (This difference is known and listed in the limits.)
3. After scoring, the server saves the reading, works out points (about half the accuracy percent), the streak, the weekly goal and the badges, adds missed words to the **word bank**, checks the **progression rule** (3 strong readings to move up, 2 weak to move down), creates the teacher's and parents' **notifications**, and calls the **recommender**.
4. The **recommender** receives the child's scores and returns an updated skill score and the next skill to practise. It keeps nothing; our database stores its answer.
5. The recording file is deleted when the job ends.

### Flow E. The teacher writes activities

1. The teacher opens Generate, picks grade, skill, type, how many of each level, optional topic and class. If a class is chosen, the server builds a short note from the class's real readings (step, mistakes, missed words, liked topics) and shows it first.
2. Pressing Write saves a request and returns at once. A **background worker** calls the **activity writer**, which calls **Gemini**. A request takes about two minutes. One free credit is used only when the activities are saved; a failure costs nothing. A new teacher has 2 credits; sharing an approved activity earns 2.
3. The drafts appear. The teacher places each one in Easy, Medium or Hard, edits or rejects. Only an **approved** activity can be assigned.
4. Assigning checks two rules on the server: **the grade rule** (an activity is only for classes and learners of its grade) and **"can this child read it"** (word count against the child's step).

### Flow F. Alerts and analytics

1. There is no separate alert job. When the teacher opens Alerts, Analytics or Home, the server **reads the readings from the database and applies the written rules** (latest reading under 70, last 3 at 90 or more, no reading for 7 days, and so on) and shows the evidence.
2. A suggested activity is picked from the teacher's own approved activities, only if no child in the group would find it too long.

### Flow G. Open Repository (the shared library)

1. A teacher shares an approved activity, free or at a price (one time; the teacher earns 2 more credits).
2. A parent browses it, with the best match for their child first, unlocks it for a child, and may rate it. The paid unlock is **simulated**: the screen says so and no real payment gateway exists.

### Flow H. Deleting data

1. A parent can delete a child. If another guardian is linked, it only removes this parent's link; otherwise it deletes everything about the child: readings, word bank, badges, alerts, consent records, photo and phone sign-ins.
2. A teacher or parent can delete their own account with their password. The Admin can do it for someone who cannot sign in.
3. A small log records that a deletion happened, with no name, email or code.

## 4. The technology stack and why

| Part | What we used | Why (the reason you can say) | What we gave up |
|---|---|---|---|
| Server language and framework | **PHP 8.4 with Laravel 13** | A mature framework with accounts, validation, background jobs, database migrations and testing built in, so the team could spend time on the reading rules. Very well documented. | Not the newest trend; needs a PHP host. |
| Web pages | **Blade templates** (the server draws each page) | Fast on low-end phones and slow internet, no heavy page scripts, one place for the rules. | Less interactive than a single-page app. |
| Database | **TiDB Cloud Starter** (MySQL compatible), Singapore | Reading data is relational (users, classes, children, readings), so a SQL database fits. MySQL compatible means Laravel works unchanged. A free tier (5 GiB, 50 million request units a month) is enough for a pilot. Same region as the server. | Free-plan limits; no automatic backups on the free plan. |
| Hosting | **Render** (Docker container, Singapore), auto-deploy from GitHub | Free to start, HTTPS included, rebuilds on every push, and Docker means the same environment on every machine. | The free plan sleeps after 15 idle minutes (the first request takes up to a minute) and has 512 MB of memory. |
| Phone app | **React Native with Expo** (TypeScript) | One codebase, JavaScript skills, direct access to the microphone and camera, quick to build and test, and it can also reach iPhones later. It only draws screens and calls the same server. | The manuscript's first plan was Kotlin. Native Kotlin is Android only and slower to build for a small team. |
| App login | **Laravel Sanctum** tokens | A simple, standard way to sign a phone in without cookies, with the token stored on the phone and revocable. | |
| Speech recognition | **Vosk** (open source, built on Kaldi), small English model | Free, no per-request fee, runs on our own service so **children's voices are not sent to a large vendor**, and returns word timing and confidence. | A small model built for adult speech: less accurate on children, so we made the scoring forgiving. Google Speech-to-Text (the manuscript's first plan) is more accurate but costs per request and sends audio to Google. |
| Activity writing | **Google Gemini** language model, through a small service | A language model can write many leveled texts quickly. A teacher approves every draft, so errors do not reach a child. | AI text can be wrong or too hard; that is why a human approves. |
| Recommendations | **Formulas** (exponential moving average of skill scores, a confidence value), no machine learning | Explainable and testable; no data to train on. | It does not "learn". |
| Email | **Gmail API** over HTTPS | The free host blocks the usual email ports, and an API call works anywhere. | Depends on the Gmail account's token. |
| Code and tests | **GitHub**, **PHPUnit** (775 automated tests), TypeScript type checking | Every rule is tested, so a change cannot silently break a number on the "how it decides" page. | |

## 5. Web versus phone app: the difference

| | Website | Phone app |
|---|---|---|
| Who uses it | Learner, teacher, parent **and Admin** | Learner, teacher, parent. **No Admin** (Admin is web only, on purpose) |
| Built with | Laravel Blade pages | React Native screens |
| How it signs in | Session cookie | Token stored on the phone |
| Same accounts and same database? | **Yes**: a reading done on one is visible on the other. | |
| Scanning a card or class QR | Browser camera | Phone camera (on the device) |
| Recording | Browser microphone, sent in the background | Phone microphone (m4a), sent in the request |
| Scoring | Background worker; the page waits for the result | Inside the upload request, two at a time |
| Strength | Nothing to install; Admin tools; big screens for teachers | Fits a child's hands; camera and microphone are native; the phone is what a child actually has |
| Weakness | Needs a browser and a microphone permission each time | Must be installed (a test file, not the Play Store yet); no Admin |

Why both? "Teachers and parents often prefer a bigger screen and need Admin tools, while a child reads on a phone or tablet. Both use the same server, so the rules are written once."
**One honest line:** "The phone app has never been tested by a real child on a real phone for a long time; the first real-phone tests have just started." (It is true. Say it.)

## 6. How the code is built, tested and deployed

1. A developer changes the code and runs the tests (`php artisan test`: 775 pass) and the app's type check.
2. The change is pushed to GitHub. Render watches the branch `claude/admin-dashboard-approvals-62dcd0` and rebuilds the Docker image.
3. On start the container migrates the database (adds new tables), refreshes the Admin account, and starts the web server and the background workers.
4. The phone app is built separately into an Android installer (APK) and sent to testers. The website needs no installer.
Honest limit to say if asked: the server runs on a single free instance; for classroom use it needs a paid plan.

## 7. What each outside service receives

| Service | Receives | Never receives |
|---|---|---|
| Reading service (Vosk) | The recording and the activity's words and grade | The child's name, code or account |
| Activity writer (Gemini) | A grade, a skill, a topic and a short note (for a class: a summary like "most are on the Words step") | Any name, learner code or one child's details |
| Recommender | Skill scores | Names |
| Gmail | The email address and the message | Children's data |
| TiDB | Everything we store | |

## 8. The data we keep (the main tables)

Accounts (users, parents, teachers); children (learners, with the hashed PIN, the step, points); classes; activities and who they were assigned to; readings (reading sessions: accuracy, words, which words were wrong, level before and after);
the word bank; badges earned; game plays; notifications; consent records; the Open Repository (listings, unlocks, ratings); promotion records; generation requests; an Admin activity log; and a small log of deletions with no personal details.
**Not stored**: recordings (deleted after scoring), full names of children in logs, advertising or tracking data (there is none).
