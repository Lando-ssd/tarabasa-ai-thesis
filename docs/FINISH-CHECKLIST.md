# TaraBasa AI: what is left to do (written 2026-10-23)

This is the list of things that only a person can do. The code, the tests and the notes are finished
as far as they can go without real users. Nothing here is "ready" until a real child, a real microphone
and a real teacher have used it: the app was only ever tested with computer voices.

Tick the boxes as you go. Where it says "Render" it means https://dashboard.render.com, service `tarabasa-ai`.

Where the details live (read these before asking anyone for help):
- `CLAUDE.md`: every decision, rule and known limit in the project, newest at the bottom.
- `mobile/HANDOVER.md`: how to build the phone app and what each version contains.
- `docs/legal/LEGAL-RISK-REGISTER.md` and `docs/legal/DATA-INVENTORY.md`: the legal list and the facts behind it.
- `docs/manuscript-update/00_README_START_HERE.docx`: the manuscript update pack.

---

## 1. Today (Claude Code access ends today, so test first)

Anything that might need a code fix has to be found today.

- [ ] **Back up what is not in GitHub.** Copy `D:\tools\apk-out` (the APKs and the tester pack zip) and your
      local `.env.render` file to a safe place (a password manager or a drive). Nobody can rebuild these for you.
- [ ] **Check the Render deploy log.** Render > Logs > the newest deploy.
      - The text `Unable to respect the PHP_CLI_SERVER_WORKERS` must NOT appear. If it does, the server is back to
        one worker and the 502 errors will come back.
      - You should see the three queue workers start (one default, two scoring).
- [ ] **Check Render memory.** Render > Metrics > Memory. The free server has 512 MB. If it is close to the limit,
      Environment > set `SCORING_WORKERS` to `1` > Save (no code change needed).
- [ ] **Do one real reading on the live site with your own voice.** This is the most important test, because it
      has never been done for real:
      1. Sign up a test Teacher and a test Parent (use addresses you control; the Admin can press "Confirm email myself").
      2. Approve the Teacher as Admin, make a class, make a child as the Parent, join the child to the class.
      3. Sign in as the child (code and PIN), do the first reading check, then read one assigned activity.
      4. Watch that the page says it is working, then moves to the result by itself, and that points go up ONCE.
      - If anything fails, write down the time and the exact words on the screen (or a screenshot).
- [ ] **Open TiDB's usage page once** and write down the number of request units used. The background workers ask
      for work every 2 seconds all day; look again tomorrow to see how fast it grows (the free limit is 50M a month).
      If it grows too fast, set `SCORING_WORKERS` to `1`.

## 2. This week

- [ ] **Make the privacy mailbox** (the team planned `tarabasa.privacy@gmail.com`) and set these in Render > Environment:
      `PRIVACY_CONTACT_EMAIL`, `LEGAL_OPERATOR_DETAIL` (who runs the service, one line) and
      `LEGAL_ACCOUNTABLE_PERSON` (the named person responsible for privacy questions). The legal pages have gaps without them.
- [ ] **Send the phone test.** Give your teammate `TaraBasa-0.5.5-tester-pack.zip` (in `D:\tools\apk-out`) and the
      test logins you made above. They follow `1-LEARNER-checklist.md` and `2-TEACHER-AND-PARENT-checklist.md`
      on a real Android phone and send back what broke.
- [ ] **After EVERY tester has version 0.5.5**, set `API_CONSENT_REQUIRED` to `true` in Render. (Older phone builds
      cannot show the consent boxes, so turning it on too early locks them out of sign up.)
- [ ] **Ask BldZeuz (the teammate who owns the other services) for two things:**
      a password on the Reading-api service (it has none), and a `levels` option on the activity generator
      (so it stops writing levels nobody asked for).
- [ ] **Watch the free limits weekly until the end of November.** Render > Billing > free usage (750 instance
      hours a month; running out stops all free services until the 1st). TiDB usage page (50M request units a month;
      running out refuses new connections until the 1st).
- [ ] Keep Render's **Health Check Path empty** until Render's Events tab has been quiet for a few days.
      After that you may put `/up` back.

## 3. Before the defense

- [ ] **Teachers review.** One Grade 1 and one Grade 2 teacher read the first check texts
      (`config/diagnostic_bank.php`, the letter sets in `config/diagnostic.php`), the game sentences
      (`config/practice_sentences.php`) and the syllable words (`config/syllable_method.php`). The team agrees on the
      numbers on the public page `/how-it-works` (when a child moves up, alert thresholds, how long is too long).
      I chose these numbers; the adviser should accept them or change them in the config files.
- [ ] **Real children.** If at all possible, have a few real children read with a real microphone, ideally two or
      three at the same time. Write down what the app marked wrong that was right (children's voices differ from the
      computer voices used so far). Wrongly heard letter names can be added to `config/reading_words.php`.
- [ ] **Update the manuscript.** A teammate works through `docs/manuscript-update` in the order its README says.
      The big items: the speech engine is Vosk (not Google Speech-to-Text), the phone app is React Native/Expo (not Kotlin),
      the stack is Laravel on Render with TiDB. Re-read the DepEd orders and the references marked as unchecked
      (the pack says which) before you quote them.
- [ ] **Legal list** in `docs/legal/LEGAL-RISK-REGISTER.md`. The main items:
      check whether the National Privacy Commission needs you to register (Circular 2022-04);
      write a short Privacy Impact Assessment and a breach plan (the policy promises 72 hours);
      check the Gemini key is not on the free tier (Google can use free tier inputs);
      decide whether parents who already agreed must be told that recordings now wait a few seconds in a temporary
      file on our server while they are scored;
      ideally have a lawyer read the Terms. Nobody on the team is a lawyer and no document makes the project safe by itself.
- [ ] **Demo day.** The free servers sleep after about 15 quiet minutes and take about a minute to wake (the
      reading checker, the activity writer and the recommender too). About 5 minutes before you start, open the website,
      sign in as a child and open a reading screen so everything is awake.

## 4. Optional

- [ ] **Google Play.** Needs a signing key made by the account owner (see `mobile/PLAY-STORE-PLAN.md`) and a closed test with
      12 testers for 14 days, so it cannot happen before early November. The Data safety answers are drafted in
      `docs/legal/DATA-INVENTORY.md`.
- [ ] **Rotate the Google OAuth client secret.** Earlier in the project a Google client secret file was pasted into a chat.
      It was never used or saved in the project, but you can create a new secret in Google Cloud Console to be safe. If you
      do, put the new value in Render too, or the verification emails stop.
- [ ] The `preview/` folder in the project is an untracked local file. Do not commit it.

---

## If something goes wrong

- **The site shows 502 or is very slow.** Render > Events: if it says "health check failed" the server was too busy;
  wait a few minutes. Then read the newest deploy log (section 1). Do not turn the Health Check Path back on in a hurry.
- **A new deploy broke something.** Render keeps older deploys: Deploys tab, pick the previous good one, Rollback.
  (The live version before the background scoring was commit `ef85bcc`; the newest is `aba98e3`.) A rollback does not undo
  database changes, but the only database change in this release is one added table (`scoring_jobs`), which older code ignores.
- **A child's recording says it failed.** The child taps Send Again. That is safe: a recording is never counted twice.
- **Emails do not arrive.** Admin > System health shows whether the Gmail connection works. The Admin can also press
  "Confirm email myself" for a person who never got the link.
- **Admin password.** It is the value of `ADMIN_PASSWORD` in Render > Environment. Changing it there and redeploying changes the login.
