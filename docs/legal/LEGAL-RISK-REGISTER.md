# Legal and privacy risk register

Written 21 October 2026, from the code as built that day. **This is not legal advice, and nobody on the team is a lawyer.**
It lists what was fixed, what is still open, and who can close each item. No document can make a project "lawsuit proof".
What reduces the risk is: saying only what is true, collecting as little as possible, getting real consent, keeping the
promises made on the legal pages, and answering people quickly when they ask. The pages and the app now do the first four;
the "open" list below is mostly things only the team (not code) can do.

How the facts were checked: every claim on the legal pages is read from the code, the configuration or the live service, and
`tests/Feature/LegalPagesTest.php` and `ConsentCaptureTest.php` fail when the app drifts from them. The Philippine law points
were checked against the text of Republic Act 10173 on lawphil.net and against law firm summaries; the National Privacy
Commission's own site returned an error to the build machine, so anything marked **verify** must be read on privacy.gov.ph.

Legend: **FIXED** = done in this change. **TEAM** = only the team can do it. **LAWYER** = needs someone qualified.
**WATCH** = fine today, becomes a problem if something changes.

---

## 1. What was found and fixed

| # | Finding | Why it mattered | Status |
|---|---|---|---|
| F1 | 21 pages loaded fonts from Google (`fonts.googleapis.com`, `fonts.gstatic.com`). | Every visitor's IP address, including parents and children, went to Google on every page. A German court (LG Munich, 20 Jan 2022, case 3 O 17493/20) awarded EUR 100 for exactly this under the GDPR, and demand letters followed. It was also a third party the Privacy page had to disclose. | **FIXED.** Fonts are served from `/fonts` (five families, Latin subset, SIL Open Font License). The browser is told, with `font-src 'self'`, to refuse any other font host. A test fails if any page loads anything from another company. |
| F2 | No consent was asked anywhere: not at parent sign up, teacher sign up, adding a child, uploading a photo, or linking to a child. | The Data Privacy Act treats age and education information as sensitive personal information (Section 3(l)), and a child cannot consent alone. Without a recorded consent the app has no defensible basis for holding a child's data. | **FIXED.** Required tick boxes with links to the full text, on the website and (build 0.5.5) in the phone app. Each tick is stored with who, which child, what, which version of the pages, and when (`consent_records`). The photo has its own separate, optional box. Records are deleted with the child or the account. |
| F3 | The Privacy page was short and missed real facts: teachers also see what the parent shared; sessions store IP address and browser details; the wake-up requests from a visitor's browser to the scoring services; the Google AI model; retention; rights; breach notice; complaint route. | An incomplete privacy notice is itself a breach of the right to be informed (Section 16). | **FIXED.** Rewritten, 16 numbered sections, every service listed with what it receives. |
| F4 | Home page claims the app could not back up: "AI-powered, teacher-verified", "checking every word as they go, right there in the moment", "every activity a child sees is checked by an actual teacher", an outcome promise ("instead of getting stuck or bored"), "set it up in a few minutes". | Misleading claims about a product used by children are a consumer protection risk. Scoring happens after the recording is sent, not live; the first reading check and the games are written by the team, not by a teacher; teachers also have to be approved first. | **FIXED.** Reworded to what is true, with a line that the project is a student capstone project, not made or endorsed by the Department of Education. No reviews, ratings, testimonials, statistics or logos were ever on the site. A test blocks the old phrases and common claim words. |
| F5 | No Terms of Use, Cookie Policy, Refund Policy or consent notice existed. | Terms are where a project says what it does not promise (accuracy of scoring, results). A consent notice is what a parent actually agrees to. | **FIXED.** Added, in one layout with a list of documents, a table of contents and a plain words summary on each. Footer, sign in pages, profile pages and the phone app link to them. |
| F6 | A teacher could type a child's name into the activity writer's Topic or Notes box, and that text goes to a Google AI model. | Personal data reaching a third party the parent never agreed to. | **FIXED in wording** (a warning on the form, on the phone, in the Privacy Policy and the Terms). **WATCH:** there is no software filter for names. |
| F7 | An unused default Laravel page (`welcome.blade.php`) still linked to outside sites and fonts. | Dead code with third party links. | **FIXED.** Removed. |
| F8 | Small inaccuracies in the old text (for example, "how the child learns best" is no longer asked, and the creating parent has no recorded relationship to the child). | A policy that names data that is not collected, or leaves out data that is, is inaccurate. | **FIXED.** |

### Checked and found clean

* **Analytics and tracking:** none. No Google Analytics, tag managers, advertising or marketing pixels, session replay, crash reporter or A/B tool in the website or in the phone app (`mobile/package.json` has none).
* **Embeds:** none. No video, map, chat widget or social button.
* **Cookies:** exactly two, both first party and strictly necessary (the session cookie and `XSRF-TOKEN`). There is no "remember me". A test lists them and checks their flags (HttpOnly, Secure on https, SameSite Lax).
* **Why there is no cookie banner:** a banner is needed for cookies that are not strictly necessary (analytics, advertising). There are none. The Philippines has no cookie specific law; the Data Privacy Act still applies to any personal data. In the EU and UK, strictly necessary cookies are exempt from consent. **WATCH:** adding any analytics, embed or non-essential cookie means building a consent banner first. **LAWYER:** confirm if the project is ever offered outside the Philippines.
* **Payments:** none. The "paid" unlock in the Open Repository is a simulation that charges nothing. The Refund Policy says so, and a test fails if a payment package is added to `composer.json`.
* **Recordings:** not stored by the app (a test pins that the app writes no file to the server disk at all: a child's photo is kept in the database). The scoring service's code processes audio in a temporary folder that is removed when the request ends, and does not log it (read on 21 Oct 2026; not independently audited).

---

## 2. Still open

### Needs the team (nobody else can do it)

| # | Item | Why | Do this |
|---|---|---|---|
| O1 | **Name the responsible party.** The pages say "the TaraBasa AI student team". | The Act asks a controller's notice to give the controller's identity and contact (Section 16(b)). A school or university name and an address look much stronger than "a student team". | Decide what may be printed, then set `LEGAL_OPERATOR_DETAIL` (for example the school and a postal address) on Render. |
| O2 | **The privacy mailbox.** `PRIVACY_CONTACT_EMAIL` is not set, so every contact line says "the school administrator or the TaraBasa AI team who gave you access". | People need a way to use their rights; Google Play and Google's OAuth review also ask for one. | Create the mailbox, set the variable on Render, check `/privacy#contact`. Someone must actually read it. |
| O3 | **Name an accountable person.** | Section 21(b) of the Act: the controller designates an individual accountable for compliance, whose identity is told to anyone who asks. | Pick one team member and an adviser contact; set `LEGAL_ACCOUNTABLE_PERSON`. |
| O4 | **National Privacy Commission registration.** **verify** | NPC Circular 2022-04 (secondary sources) exempts organisations under 250 staff unless the processing is likely to risk people's rights, is **not occasional**, or involves sensitive personal information of 1,000 or more people. TaraBasa processes minors' sensitive information continuously, so it may need to register its data processing system and its DPO. | Ask the school's data protection officer, or the NPC (privacy.gov.ph), whether the project must register. Do it before real classes are onboarded. |
| O5 | **Privacy Impact Assessment.** | Recommended by the NPC for processing children's data; a short written one also helps with Google Play. | A 2 to 4 page note: what is collected, why, risks, measures. This register and `DATA-INVENTORY.md` are the raw material. |
| O6 | **Agreements with the services that handle children's data.** | The Act keeps the controller responsible for what its processors do. Render, TiDB Cloud and Google have terms of service, but the scoring, recommendation and activity services are run by a team member with no written agreement, the scoring service has no password, and it receives children's voices. | A one page written agreement among the team about who runs each service, deletes nothing it should not keep, and tells the others of any incident. Ask the teammate to add an API key to the scoring service. |
| O7 | **Breach plan.** The Privacy Policy promises to tell the NPC and affected people within 72 hours (Section 20(f) of the Act; NPC Circular 16-03). | A promise with no procedure fails on the first bad day. | One page: who decides, who writes to the NPC, who writes to parents, where the contact list lives. |
| O8 | **A retention rule for inactive accounts.** | Data stays until someone deletes it. Keeping children's data forever "just in case" is hard to defend. | Decide a rule (for example, inactive for 24 months), say it in section 10 of the Privacy Policy, build or run the cleanup. |
| O9 | **Gemini data terms.** | Google's terms for the free tier of the Gemini API (as reported) let Google use inputs to improve its products and let reviewers read them; the paid tier does not. Only teacher typed text and an unnamed class summary are sent. | Check the billing tier of the activity writer's key. If it is free tier, either switch to paid or keep warning teachers (already done). |
| O10 | **Re-consent.** `consent_records` stores the version of the pages, but nothing makes a parent agree again when the meaning changes. | If a change affects what parents agreed to, the Act expects fresh consent. | Build a "please review the changes" gate when the first material change happens; until then notify parents by email. |
| O11 | **Photos.** **Partly fixed on 21 Oct 2026 (version 2026-10-21.2):** a photo is now kept in the database, not on the server disk, so it is no longer lost on every restart, and it is shown only to the child, a linked guardian and the class teacher (it used to be a public file address). **Still open:** there is no way to remove only a child's photo (delete the child, or write to us), and the photo has no protection of its own beyond the database provider's. | A child's photo is high risk for little benefit. | Add a "remove photo" button, or drop photo upload before a public launch. **Added 24 Oct 2026 (version 2026-10-24):** a Teacher or Parent can add a photo of themselves (website and phone app). It is low risk by design: it is an adult's own choice, it is shown only to that person (every other account, including the Admin, gets "not found"), it has a Remove photo button, it is deleted with the account, and the phone only ever opens the gallery (never the camera). Privacy page, Terms and Cookie Policy changes tables were updated in the same commit. A picture of a learner card chosen in the phone app is read on the phone and is not stored or sent. |
| O12 | **Requests are handled by hand.** Access, correction and "give me a copy" (Section 18, portability) have no screen. | The Privacy Policy says so honestly and promises an answer within 30 days. | Decide who answers, and write a one page how-to (what to check, what to send). |
| O13 | **A parent cannot take their child out of a class.** Teachers join children by code and the parent is told in the app, but the only ways out are deleting the profile or asking the teacher. | A parent who does not agree has few options. | Consider a "remove from class" button for parents. |
| O14 | **The temporary recording on the server (23 Oct 2026, version 2026-10-23).** Scoring moved to a background worker, so a website recording now waits for seconds in a temporary file on the server disk before it is scored and deleted. The Privacy Policy, the Parent Consent page and the add-a-child step were changed to say so ("deleted as soon as it has been scored"). | The old wording said the app does not save the recording. The new wording is true, but it is a change in how the data is handled, so decide whether parents who already consented need to be told (O10). Check the Play Data safety answer for Audio still reads "processed only to produce a score". A crash can leave a file for a few minutes; the sweep removes it. |

### Needs a lawyer or the school's legal office (LAWYER)

| # | Item |
|---|---|
| L1 | Read the five documents once. They were written in plain language from the Data Privacy Act and the code, not by a lawyer. |
| L2 | **Terms of Use:** the limitation of liability, the governing law and venue clause ("the proper courts of the Philippines", no named city) and the "as is" wording. Limits on liability do not hold against consumer rights or against gross negligence. |
| L3 | **Parental consent method.** Ticking a box proves nothing about who ticked it. The Act does not prescribe a method for minors; the NPC expects reasonable means. Decide if a signed school form or an email confirmation to the parent is wanted before real classes join. |
| L4 | **Use outside the Philippines.** The website is public. If a child in the United States signs up, COPPA applies (verifiable parental consent for under 13s); in the EU or UK, GDPR (child consent age 13 to 16). The pages are written for the Philippines only. Consider limiting the service to Philippine users, or get advice. |
| L5 | **The DepEd and school side.** DepEd's rules on child protection and on using apps with learners. A school may need a memorandum or a data sharing agreement before it puts a class on TaraBasa. |
| L6 | **Google Play.** An app aimed at young children falls under Google Play's Families Policy (no personalized advertising, a privacy policy that matches what the app really does, an accurate Data safety form). `DATA-INVENTORY.md` has a draft of the Data safety answers. |

### Watch list (true today, breaks if things change)

* A new outside service, a new cookie, saved recordings, analytics, a payment gateway, a chat or comment feature: change the pages first (`config/legal.php` explains how, and the tests fail until you do).
* The scoring service's code is public and could change; the claim that it keeps no audio was true on 21 October 2026.
* The phone app builds before 0.5.5 have no consent boxes. Sign up from those builds is still accepted until `API_CONSENT_REQUIRED=true` is set on Render. **Set it as soon as every tester has 0.5.5.**
* Time zone, ages and grades: the service is for Grade 1 to 3. If older or younger children sign up, the notices still apply but the curriculum text does not.

---

## 3. If someone asks "are you compliant?"

Say what is true: *"We built privacy in from the start. A parent consents before any child's information is kept, we keep little, recordings are deleted as soon as they are scored, there is no tracking or advertising, parents can delete everything themselves, and we publish exactly what each outside service receives. We have a written list of what is still open (registration with the National Privacy Commission, a named data protection contact, agreements with our service providers) and we are working through it."* Do not say "fully compliant" until items O1 to O7 and L1 are closed.
