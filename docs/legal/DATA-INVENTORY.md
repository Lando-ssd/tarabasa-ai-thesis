# Data inventory

Written 21 October 2026 from the code, the migrations and the live configuration. It is the factual basis of the Privacy Policy,
and the raw material for a Privacy Impact Assessment, a National Privacy Commission registration and the Google Play "Data safety"
form. If the app starts keeping or sending something new, update this file in the same commit as `resources/views/privacy.blade.php`.

## 1. What is stored, where, and who sees it

| Data | Table or place | Who it is about | Who can see it | Kept until |
|---|---|---|---|---|
| First, middle initial, last name, email, password (bcrypt hash), contact number (optional) | `users` | Parents, teachers, administrators | The person; the administrator (name, email, status) | The account is deleted |
| Relationship to a child (Mother, Father, Guardian) | `parent_learners` | A guardian who links to a child someone else added (the creating parent has none) | Guardians of that child | The child or the link is deleted |
| School name, employee ID, grades handled, status, free credits | `teachers` | Teachers | The teacher; the administrator | The account is deleted |
| First and last name, optional middle name, grade, avatar, learner code, PIN (hash), theme colour, and a marker that a photo exists (`photo_version`) | `learners` | Children | Guardians; the class teacher after joining |
| **Optional photo**, cropped to 480 by 480 pixels by the browser, JPEG, PNG or WebP, base64 text | `learner_photos` (one row per child, deleted with the child by cascade and by the erasure service). Shown only through `/photos/learner/{id}` to the child, a linked guardian and the class teacher; everyone else, including an Admin, gets "not found" | Children | The child; guardians; the class teacher after joining | The child is deleted |
| Reading stage, three answers about how the child reads, home language, what helps (supports), topics the child likes | `learners` (`reading_stage`, `placement_answers`, `home_language`, `supports`, `interests`) | Children | Guardians; the class teacher | The child is deleted |
| Reading level and step, points, streak, adaptive state | `learners` | Children | Guardians; the class teacher | The child is deleted |
| Each reading: date, scores, speed, **the words the speech recognizer heard**, word by word result, which words were missed | `reading_sessions` (`word_feedback`) | Children | Guardians; the class teacher | The child is deleted |
| Missed words list, badges, practice games finished (game and level only), alerts | `personal_word_bank`, `learner_badges`, `game_plays`, `notifications` | Children, adults | Guardians; teacher (alerts); the recipient | The child or recipient is deleted |
| Classes, class lists, assignments, written activities, shared activities and ratings | `classes`, `activity_assignments`, `activities`, `open_repository_listings`, `repository_*` | Teachers, children (class lists) | The teacher; parents (shared activities) | The teacher or child is deleted (activities stay without the teacher's name) |
| What an adult agreed to: user, child, kind, **version of the pages**, channel, date | `consent_records` | Adults | Nobody in the app; the team on request | The adult or child is deleted |
| Sign in session: **IP address, browser details**, session payload | `sessions` | Everyone signed in | The team (database) | About 120 minutes after last use (routine clean up) |
| Wrong sign in counters (by IP and email or learner code) | cache (`cache` table) | Everyone | The team | A few minutes (15 for a child's PIN) |
| Error diary: kind of error, the service, the answer (no recipient, no child data by design) | `service_failures` | Nobody in particular | The administrator | 14 days |
| Who did what, with a reason (no name of the person deleted) | `admin_actions`, `data_erasures` | Administrators | The administrator | Kept |
| Sent emails (confirmation, password reset): address and text | The TaraBasa Gmail account ("Sent") | Adults | The team who hold the mailbox | Until someone deletes them. **Open item.** |
| Server logs | Render | Visitors | The team; Render | Render's rules |

**Not stored:** the voice recording (neither by the app nor, by its code, by the scoring service); precise location; address; phone number of a child; birth date; contacts; device identifiers; advertising IDs.

## 2. What leaves the system

| Service | Run by | Region | What it receives | Why |
|---|---|---|---|---|
| Render | Render Services, Inc. | Singapore (the web service) | Everything sent to the website, plus server logs | Hosting |
| TiDB Cloud | PingCAP | AWS Singapore | All stored data | Database |
| Reading scoring service (Vosk) | A team member, on Render | Set by Render (not fixed by us) | The recording, the activity text, curriculum code, difficulty, activity number | Scores a reading |
| Activity recommendation service | The same team member, on Render | As above | Scores and an internal child number | Suggests what to read next |
| AI activity writer (Gemini) | The same team member, on Render, calling Google's Gemini API | As above | Teacher typed grade, skill, topic, notes; for "For a class", an unnamed class summary | Writes draft activities |
| Gmail API | Google | Google data centres | Email address and text of the account emails | Sends confirmation and reset emails |
| Google sign in (optional) | Google | Google | The person leaves for Google; we receive name and email | Convenience for adults |
| The visitor's own browser | n/a | n/a | One bare health request to each of the scoring and recommendation services (and the activity writer for teachers), no cookie, no recording, no child data | Wakes sleeping free-tier services |

Nothing else: no analytics, no advertising network, no font or script host, no social plugin, no map or video.

## 3. Cookies and similar storage (website)

| Name | Purpose | Lifetime | Flags |
|---|---|---|---|
| `tarabasa-ai-session` (`config('session.cookie')`) | Keeps a person signed in; holds one time messages | `SESSION_LIFETIME` minutes (120) after last use | HttpOnly, SameSite=Lax, Secure on https |
| `XSRF-TOKEN` | Form forgery protection | Same | SameSite=Lax, Secure on https |
| sessionStorage `tb-wake:<service>` | When a service was last woken, at most every 2 minutes | Until the tab closes | Never sent to us |
| sessionStorage `tb_warm_<learner code>` | That the microphone check was done | Until the tab closes | Never sent to us |

Phone app: no cookies. Secure storage holds the sign in token and the person's first name; both are removed at log out.

## 4. Consents recorded

| Kind (`consent_records.kind`) | Asked where | Required |
|---|---|---|
| `terms_privacy` | Parent sign up, Teacher sign up (website; phone from build 0.5.5) | Yes |
| `child_data` | Last step of "Add your child" (website and phone) | Yes |
| `child_photo` | Same step, only when a photo is chosen (website only; the phone cannot upload photos) | Yes, for the photo |
| `child_link` | Linking to an existing child (website and phone) | Yes |

## 5. Draft answers for the Google Play "Data safety" form

Verify every answer against the form's current wording before submitting; Google can ask for the evidence.

| Question | Draft answer |
|---|---|
| Does the app collect or share user data? | Collects. Does not share with third parties for their own purposes; service providers process it for us. |
| Personal info | Name, email address, user IDs (learner code), other info (school name, employee ID, optional contact number). |
| Audio | Voice or sound recordings: **collected** (sent off the phone for scoring), processed only to produce a score, **not stored**. Consider declaring it as collected even though processing is brief. |
| Photos and videos | Not collected by the phone app. (The camera only scans a QR code; the picture is not kept or sent.) |
| App activity | App interactions (readings, scores, badges, games). |
| Location, contacts, calendar, files, health, financial info, web browsing, device or other IDs, advertising ID | Not collected. |
| App info and performance | No crash logs, diagnostics or analytics are collected. |
| Is all data encrypted in transit? | Yes (https). |
| Can users request that data be deleted? | Yes: in the app (delete a child, delete my account) and at `https://tarabasa-ai.onrender.com/privacy#delete`. |
| Purpose of collection | App functionality, account management. Not advertising, not analytics, not personalization for ads. |
| Target audience and content | Children (Grade 1 to 3, about 6 to 9 years old). Falls under the Families Policy: no ads, privacy policy that matches the app, parent created accounts. |
| Privacy policy URL | `https://tarabasa-ai.onrender.com/privacy` |
