# 4. Business model, revenue and profit

The panel may ask: "Is this only a system, or can it sustain itself? Where does the money come from, and is there profit?"
This file gives you the Business Model Canvas (BMC), the revenue streams, the costs, a break-even calculation you can redo on paper, and the honest limits.

**Read this first.** TaraBasa AI has **no paying customer yet**, no price has been tested with a school, and the payment feature in the app is **simulated** (no real money moves).
So the numbers below are a **plan with stated assumptions**, not results. Say that sentence before the panel asks. A panel respects a clear plan with honest assumptions;
it does not respect an invented market size.

Labels used in this file: [FACT] = checked on a provider's page on 23 October 2026 | [ASSUMPTION] = the team's guess, change it | [TO VALIDATE] = needs an interview or a quotation before you rely on it.

---

## 1. The Business Model Canvas (aligned with the updated manuscript, block C3)

The canvas in version 29 of the manuscript names tools the system does not use (Google Speech-to-Text, Semaphore SMS, Firebase, an advertisement stream).
The update pack replaces them. This is the canvas as it should be defended:

| Block | What goes in it (the updated version) | Why |
|---|---|---|
| **Customer segments** | **Learners** (Grades 1 to 3, the users), **teachers** (the daily operators), **parents and guardians** (the home partners), and **schools** (the buyer, because the teacher and the parent rarely hold the budget). | The user and the payer are different people. That is normal in education software, and you should name both. |
| **Value propositions** | **For the learner:** read aloud, get friendly feedback, see a path, play reading games. **For the teacher:** an AI-assisted oral reading check, a first reading check, alerts and suggested activities that show their evidence, class reports. **For the parent:** a progress page, a plain sentence about the child's level, a "practise at home" tip, in-app alerts. | The core promise is time: a teacher cannot listen to every child often; the system listens and says who needs help first. |
| **Channels** | The **Android app** (learner, teacher, parent), the **website** (all four roles), and **school deployment** (the teacher introduces it to the class and sends the child's card home). | The website needs no install; the app works on a phone a family already owns. |
| **Customer relationships** | Automatic in-app alerts to teachers and parents after each reading; a weekly reading goal and a progress page that keep parents involved; the Admin confirms each teacher with the school. | Low-touch by design: a small team cannot give personal support to every school. |
| **Revenue streams** | **1. School subscription (school license).** **2. Sale of shared activities in the Open Repository** (a teacher lists an approved activity free or at a price; a parent unlocks it). **No advertisements.** | Ads were removed because the users are children and the Privacy Policy says none are shown. |
| **Key resources** | The **Vosk speech recognition service** (Reading-api), the **written rules** for adaptive support, the **activity writer** (Gemini) and **recommender** services, **Render** hosting, **TiDB Cloud** database, the **Gmail API** for account emails, the **curated first-check texts** and **teacher-approved activities** coded to MATATAG, and the team's code (Laravel 13 website, React Native with Expo app). | Most of the intelligence is rules we wrote and content we coded to the curriculum. |
| **Key activities** | Writing and tuning the adaptive rules; connecting the speech, activity and recommender services; designing the interfaces; coding content to the curriculum; confirming teachers; supporting pilots. (Version 29 said "develop AI models": nothing was trained, so the update says "connect a speech recognition service and write the rules".) | Honest wording avoids a claim the panel can disprove. |
| **Key partners** | Teachers; parents; the partner school; DepEd curriculum references (MATATAG, Phil-IRI, Hamon); service providers (Render, TiDB Cloud, Google for Gmail and Gemini); the teammate who maintains the three AI services. | Name the dependency on the teammate's services as a partner and a risk (section 6). |
| **Cost structure** | Hosting and database plans, AI service usage per request, a domain name, the Google Play developer fee, and the team's time. Free plans were used during development. | Section 3 puts numbers on it. |

**The line to say:** "Our canvas has two customers who use it, the teacher and the parent, and one customer who pays, the school. We removed advertising and SMS because children use the system and because SMS is not part of what we built."

---

## 2. Revenue streams, one by one

### Stream 1: School subscription (the main stream)
- **What it is.** A school (or a school division, a local government unit, or a private school) pays a flat fee per month or per school year for its teachers, classes and learners to use the system.
- **Why a flat fee per school.** One decision-maker, one invoice, no payment from poor families, and the cost to us hardly changes with the number of learners at the size of a school. [ASSUMPTION]
- **Why not charge families.** Many families cannot pay, the learners are children, and charging parents for a learning tool in a public school raises fairness problems.
- **What a fee could look like** (examples to calculate with, not a price list):
  | Option | Example | When it fits |
  |---|---|---|
  | Flat per school, per month | PHP 1,500 | A small school, simplest to sell |
  | Per class, per month | PHP 400 per class | A school that starts with two or three classes |
  | Per learner, per year | PHP 150 | A division or a private school that wants to pay by head count |
  Pick **one** to present. The numbers are [ASSUMPTION]; no school has been asked.
- **Who can actually pay.** [TO VALIDATE] Private schools can. Public schools run on limited budgets: a school's Maintenance and Other Operating Expenses (MOOE) and a local government's Special Education Fund (SEF) are the usual places a public school or its division could fund a learning tool. **Do not claim a public school can legally buy a software subscription from these funds until you have asked a school head or a DepEd division officer and received it in writing.** Other possible payers: a partner company's corporate social responsibility budget, an education NGO, or a donor-funded pilot.

### Stream 2: Sale of shared activities (the Open Repository)
- **What it is.** An approved teacher lists an activity to the repository as **free or paid**. A parent unlocks it for a child. The platform could keep a **commission** and the teacher keeps the rest. Sharing an approved activity also earns a teacher 2 extra writing credits.
- **What exists today.** The listing, the unlock, the rating and the "best match for your child first" order all work. **The payment is simulated: no real money moves, no payment provider is connected, and no commission is taken.**
- **Why it is a supporting stream and not the main one.** A paid activity costs a parent a few pesos; even a lively repository brings little (section 4 shows the arithmetic). Its bigger job is to **reward teachers for sharing** and to build a library, which makes the school subscription worth paying for.
- **What must be built before real money moves** [TO VALIDATE]: a payment provider (for example a local e-wallet or card gateway), the gateway's fees, the rules for a refund (the Refund page says what we do today), tax and business registration, and the Terms for sellers. Do not promise a date.

### What we do not do
- **No advertisements.** The Privacy Policy and the Terms say so. Removing the ads stream is a deliberate trade: less revenue, more trust.
- **No selling of data.** Never. Say it plainly if asked.
- **No SMS.** It was in version 29; it is not in the system and the update removes it.

---

## 3. What it costs to run

All prices are USD per month unless written otherwise. [FACT] means the number was read from the provider's page on 23 October 2026; confirm again before quoting, because prices change.

| Cost | Today (development) | For steady classroom use | Source |
|---|---|---|---|
| **Website hosting (Render)** | Free web service. It sleeps after 15 idle minutes and wakes in about a minute. The free tier gives 750 instance hours per month per account. | **Starter $7** (512 MB, 0.5 CPU) or **Standard $25** (2 GB, 1 CPU). Our server runs four web workers and three background workers, so **Standard is the safer choice** for a real class. | [FACT] Render pricing page |
| **Database (TiDB Cloud Starter)** | Free allowance: 5 GiB storage and **50 million request units per month** for a MySQL compatible instance. | Pay only beyond the free allowance: from **$0.10 per million request units** and from **$0.20 per GiB of storage per month**. A small school stays inside or near the free allowance. | [FACT] TiDB Cloud pricing page |
| **The three AI services** (speech checker, activity writer, recommender) | Run free on the teammate's Render account; they sleep when idle (the keep-awake button wakes them). | To stop the sleeping and to be independent, host our own: roughly **$7 each** on Starter, about **$21** for three. The speech checker holds a model in memory and may need a larger plan. | [ASSUMPTION], from Render's Starter price; test the speech checker's memory before you rely on it |
| **AI language model (Gemini)** | Used through the teammate's service. | Billed per token. At the cheapest Flash-Lite rates ($0.10 per million input tokens, $0.40 per million output tokens) writing one set of activities costs **well under one US cent**. Even 1,000 requests a month is a few dollars. | [FACT] Google's pricing page for the cheapest model; the model the service really uses is unconfirmed |
| **Email (Gmail API)** | Free. | Free, with daily sending limits on a normal Gmail account. A school with thousands of accounts would need a proper email service. | [TO VALIDATE] |
| **Domain name** | None (the address ends in onrender.com). | About **$1 per month** ($10 to $15 per year). | [ASSUMPTION] |
| **Google Play developer fee** | Not paid. | A **one-time** registration fee (about $25) and a testing requirement before public release. | [TO VALIDATE] confirm on Play Console |
| **People** | The team's own time (unpaid). | **The largest real cost.** Someone must fix bugs, answer teachers, update curriculum references and keep the services running. | |

### A realistic monthly cash cost for ONE steady school pilot
| Item | USD |
|---|---|
| Website (Render Standard) | 25 |
| Three AI services on Starter | 21 |
| Database (inside or near the free allowance) | 0 to 5 |
| Gemini usage | 1 to 3 |
| Domain | 1 |
| **Total** | **about 48 to 55** |

At an exchange rate of **PHP 58 per USD** [ASSUMPTION: replace with today's rate], that is about **PHP 2,800 to 3,200 per month**. This is cash only; it leaves out the team's time.

If the school is happy with the free-plan behaviour (one minute of waiting after a quiet period), the cash cost of a pilot is **close to zero**; the keep-awake button and the cron workflow exist for exactly that. That is a real strength for a first pilot, and a real weakness for a business, because free plans can change or end.

---

## 4. Profit and break-even, with the arithmetic

**Profit = revenue minus costs.** **Break-even** is the point where they are equal.
All figures are PHP per month and use the assumptions above. Redo them with your own price.

### 4.1 How many schools does it take to cover the cash cost?
Cost for the steady setup: about PHP 3,000 per month.

| Price per school per month | Schools needed to break even (3,000 divided by price, rounded up) | Profit with 5 schools |
|---|---|---|
| PHP 500 | 6 | 5 x 500 = 2,500 minus 3,000 = **minus 500** |
| PHP 1,000 | 3 | 5,000 minus 3,000 = **2,000** |
| PHP 1,500 | 2 | 7,500 minus 3,000 = **4,500** |
| PHP 3,000 | 1 | 15,000 minus 3,000 = **12,000** |

Because most of the cost is fixed (the same servers serve one school or ten), **each extra school adds almost pure margin** until the servers need to grow. That is the point to make about this model.

### 4.2 When do the servers need to grow?
Do not guess. Look at three numbers on the dashboards: Render **Memory** and **CPU** graphs, and TiDB **request units**. If the request units approach 50 million a month, or memory touches the limit, move up one plan.
A rough guide from our own load test: with the background-scoring design, ten children recording together did not block the website's health check. Thirty at once is **untested on Render**.

### 4.3 Cost per learner
If one school has 300 learners and the cash cost is PHP 3,000 per month, that is **PHP 10 per learner per month**. If you price per learner per year at PHP 150, that is PHP 12.50 per month per learner, so the margin per learner is small and only works at volume. A flat school fee is simpler and safer.

### 4.4 What the Open Repository can add
Suppose 20 parents each buy 5 activities a month at PHP 20 each: 20 x 5 x 20 = PHP 2,000 of sales. If the platform keeps a 20 percent commission [ASSUMPTION; no commission exists in the app], that is **PHP 400**, before the payment gateway's own fee. Useful, but about the same as one cheap school subscription. Say "supporting income".

### 4.5 Putting it together: three sentences for the panel
1. "Our cash cost for a steady school is about PHP 3,000 a month, and most of it is fixed."
2. "If a school pays PHP 1,500 a month, two schools cover the cost and each additional school is mostly profit."
3. "These are our assumptions. We have not asked a school to pay, so the next step is a pilot with a price discussion, and we will not claim demand we have not tested."

---

## 5. How the system could sustain itself over time (the plan, in stages)

| Stage | What happens | Money |
|---|---|---|
| **1. Capstone and defense (now)** | Free plans, keep-awake button, team time. | Cash cost near zero. |
| **2. Pilot** | One or two classes in the partner school, real children, real consent forms. Measure: do teachers use alerts, do children finish readings, do scores move. | Free for the school, or a small token fee to learn about willingness to pay. Grant or sponsor covers a paid Render plan if needed. |
| **3. First paying schools** | Fix what the pilot found, then offer the school subscription. Ask the school how it can lawfully pay. | A few schools cover the cash cost (section 4.1). |
| **4. Growth** | A division or a local government buys for many schools; the repository grows; own the three AI services; a real payment provider. | Margin grows because costs are mostly fixed; people costs also grow. |

---

## 6. Risks to the business (say them before the panel does)

| Risk | Why it matters | What you would do |
|---|---|---|
| **No tested demand** | No school has been asked to pay. | Pilot first, listen, then set a price. |
| **Public school budgets** | A public school may not be able to buy a subscription. | Ask a school head and a division officer what funding route is lawful; consider a private school, a division license, a local government fund, a sponsor or a grant. |
| **Dependence on the teammate's services** | The speech checker, activity writer and recommender run on his hosting; the speech checker has no password. | The rebuild plan is in `docs/KEEP-IT-ALIVE.md` section 7; offline copies are in the backup. |
| **Free plans can change** | Render or TiDB can limit or end a free tier. | The paid plans in section 3 are the exit; the cost is small. |
| **Real children untested** | Speech recognition is weaker on children; our numbers were set with computer voices. | Pilot with consent, then tune the settings. |
| **Privacy duties grow with money** | Once a school pays, the school expects written agreements and a breach plan. | Complete the items in `docs/legal/LEGAL-RISK-REGISTER.md` before charging. |
| **Competition** | Reading programmes and apps exist (for example international reading apps and school made tools). | See section 7. |
| **Team time** | A business needs maintenance people. | A support agreement or a school fee that pays someone; or hand over to the school's ICT office. |

---

## 7. Why a school would choose this and not something else

Do not invent competitor names or figures. Say the **kind** of alternatives and what is different here:
- **Teacher listening only** (the Phil-IRI done by hand, once or twice a year): free, but slow and infrequent. TaraBasa adds weekly reading and an alert between tests.
- **General reading apps** made for other countries: often in another accent, not coded to MATATAG, with data stored abroad and often with ads or subscriptions per child. TaraBasa is coded to the Philippine curriculum and shows Phil-IRI words teachers already know.
- **What is truly different:** one system for the teacher, the parent and the child; every adaptive decision shows its reason; no ads; a teacher approves every AI draft; and it works on a phone.
- **What is not different:** speech recognition accuracy, content volume, years of research. Do not claim these.

---

## 8. Likely business questions and short answers

**Q. Where is your profit?**
A. "We have no revenue yet; the system is a prototype with simulated payments. The plan is a school subscription. Our cash cost for a steady school is about PHP 3,000 a month, mostly fixed, so two schools at PHP 1,500 would cover it and more schools are mostly margin. These prices are assumptions we will test in a pilot."

**Q. Who pays?**
A. "The school or its sponsor, not the family. The learner, the teacher and the parent are users. We still need to confirm with a school head how a public school can lawfully pay."

**Q. Why no ads?**
A. "Children use it, and the Privacy Policy says none are shown. We accept less revenue to keep trust."

**Q. Why is the repository not the main income?**
A. "A paid activity costs a parent a few pesos, so it adds little. Its job is to reward teachers for sharing and to build a library."

**Q. What if a free plan ends?**
A. "We move to the paid plans in our cost table. The extra cash cost is about PHP 3,000 a month."

**Q. Can you run it for free forever?**
A. "For a pilot, yes, with the keep-awake button, but a child waits about a minute after a quiet period. For steady use, a paid plan removes the waiting."

**Q. How much does one learner cost you?**
A. "About PHP 10 a month at 300 learners per school, because the cost is mostly fixed."

**Q. Is the payment real?**
A. "No. It is simulated and the Refund page says so. A real payment provider, its fees, taxes and registration come before any real money."

**Q. Is it a business or a thesis?**
A. "It is a thesis first. We included the business model because a tool teachers cannot afford to keep running does not help children. The canvas shows how it could keep running."

---

## 9. What to do before you can honestly claim profit

1. Ask the partner school head: would the school pay, how much, from which fund, and what do they need to see in writing.
2. Interview one private school and one division officer for the same questions.
3. Write the price you will present (one option from section 2) and the cost table with today's prices and exchange rate.
4. Decide whether the Open Repository will keep a commission, and the percentage.
5. Complete the privacy items in the risk register that a paying school will ask for.
6. Keep a simple spreadsheet: month, costs, revenue, profit. Show it in the defense if you have one, even with zeros.
