# Phone test checklist: Teacher and Parent (for whoever tests version 0.5.3 on a real Android phone)

Version 0.5.3 has the Teacher and Parent screens next to the Learner screens (0.5.0 was the first build with them; 0.5.1 added moving a learner and the school year picker; 0.5.3 added a new PIN for a child who forgot theirs; 0.5.3 adds removing a child, deleting your own account, a check on the code before Add, and asking the Admin for another grade). It is a TEST build.
Nobody has run these screens on a real phone yet, so you are the first. Please be honest about what fails: a failure found
now is cheap, the same failure found on demo day is not.

Use only 0.5.3. Older builds are missing screens.

## Before you start
- The owner has already made test accounts on the live website (a Teacher, a Parent and one child). Their logins are sent to you privately.
- Never type a real child's name or photo anywhere. Use made-up names only.

## Install
Same as the Learner checklist: copy the APK to the phone, allow installs from that app, open **TaraBasa AI**.

## Test as the Teacher
Write down for each one: **worked / did not work / odd**, and what the screen said.

1. **Sign in.** On the first screen tap **Log in with email** (the small link under the code box). Sign in. You should land on Home.
2. **Home.** Does it say who needs support, the four numbers (Classes, Learners, Approved, Credits) and what is waiting?
   Is anything cut off or too small to read?
3. **Make a class.** Classes, **New class**: a name, a section, a grade. A class holds one grade. Does it open the class?
4. **Add a learner.** Open the class. Try **Scan learner cards** and scan a child's QR card (print one from the Parent screens
   or show the Parent app's child card on another phone). Does the camera open, does a green result appear, does it keep scanning?
   Then try **Add with a code** and type the last 5 characters of a code.
5. **A child of the wrong grade (new in 0.5.3).** In a Grade 1 class, open **Add with a code** and type the last 5 characters of a Grade 2 child's code.
   After about a second a RED message must appear that names both grades, and the **Add to this class** button must stay grey (it cannot be pressed). Then type a Grade 1 child's
   code: the message must turn GREEN ("This code can be added...") and the button must turn on. While you are still typing (fewer than 5 characters) it must say how many more
   are needed. Write down anything that looks wrong, and how long the check took.
5b. **Move a learner.** Tap a learner's row in a class and scroll to the end of their page. If the teacher has another class of the same grade you
    should see **Move to another Grade 1 class**. Pick a class, press the button that names it, and check the app goes back to the class and says
    where the child went. A class of another grade must never be offered. (If that card is missing, write it down: it needs the newest server.)
5c. **School years.** On Classes, if the teacher has classes in more than one school year, chips appear at the top. A past year must say it
    can only be read and must have no New class button. (With only one year there are no chips: that is correct.)
6. **Write activities.** Activities, **Write new activities**. Pick a grade, a skill, a kind of reading and ONE level.
   Pick a class under **For a class** and read the box it shows. Press **Write**. It takes about two minutes (the free server may sleep
   first). Leave the screen, use another tab, come back: the blue notice should still be there with a timer. When it is done it turns
   green. Note the real time. If it failed, what did it say? (A failed request costs nothing.)
7. **Review.** Put a draft in a level with the three buttons. Reject one, then tap **Undo**.
8. **An activity.** Open one. Does it say why it is in its level? Try **Edit the words and approve**.
9. **Give it to a class.** Open an approved activity, **Give to a class or learner**. A long activity must be refused for a class of
   non-readers ("Too long"), with the button greyed out.
9b. **Ask the Admin for another grade (new in 0.5.3).** More, **My profile**, **Grades you handle**. Tick a grade you do not handle yet (for example Grade 2).
    A box must appear asking who at your school can confirm it. Leave it empty and press **Ask the Admin**: it must refuse and say why. Then type a short note (for example
    "Mrs. Cruz, the school head, gave me Grade 2") and press the button again: the app must say the request was sent, the list **Handled now** must NOT change, and a
    "Waiting for the Admin" box must appear with a **Cancel this request** button. Try Cancel. Then ask again, and tell the owner: the owner approves it on the website
    (Admin, Approvals, Grade requests). Reopen **My profile**: the Admin's answer must show, and **Handled now** must now include the new grade. Taking a grade away
    (untick it) must save at once without asking the Admin.
10. **Alerts.** Does the red number on the bell match the alerts? Open one: "Why this is flagged", then **Give it to ...** and **Mark handled**.
11. **More.** Analytics (change This week / This month, pick a class), Promotions (the confirm step: press Cancel, do NOT release a real child),
    My profile (change the contact number and save), then **Log out**.
12. **Pending teacher** (if you made one): sign in. It should say it is waiting for the Admin, and buttons that touch students should
    explain why they are locked.

## Test as the Parent
1. **Sign in** the same way, with the Parent account. You should land on Home.
2. **Add a child.** Children, **Add a child**, five steps. Try pressing Next with nothing typed: each step should say what is missing.
   At the end it shows the child's Learner Code. Write it down. Then **Show the card**: a big code and a QR code.
3. **The child signs in.** On the first screen use that code and the PIN you set (Learner side). Does it work?
4. **Home.** After the child has read something: days in a row, points, words a minute, this week's goal, badges, recent readings.
   With two children, tap the chips at the top to switch.
5. **Link a child.** On a second Parent account, Children, **Link an existing child**: a wrong PIN must be refused with one message
   (it must not say whether the code or the PIN was wrong). The right code and PIN should link the child, and the first Parent should
   get an alert.
5b. **Forgot the PIN.** Children, tap the child, **New PIN**. (a) Pick **Make one for me**, type the parent password, press **Make a new PIN**: big numbers appear ONCE.
    Write them down, press Done, and check the child can sign in with them (and the old PIN no longer works). (b) Try **I'll choose one** with 1111 or 1234: it must be refused with a
    sentence. (c) Try a wrong password: it must say so. (d) After 3 new PINs in a day the 4th must be refused. (e) On the child sign in screen tap **Forgot my PIN?**: it should say to ask the
    grown-up. After 5 wrong PINs the child is locked and the help should open by itself, and the parent should get a red alert ("PIN Locked").
6. **Progress.** The level in plain words, "Practise at home" (only after several readings), the chart, the history.
7. **Activities.** Needs a Teacher to have shared an activity to the Repository first. Unlock a free one in one tap, a paid one with
   the confirm step (nothing is charged). Rate it with the stars. Then check that the child sees it in their own app under extra practice.
8. **Alerts.** Tap an alert: it should turn plain and the number should go down. Try **Mark all as read**.
9. **Profile and Log out.** Tap the picture at the top of Home.

10. **Delete (use a throwaway account and a made-up child only; new in 0.5.3).** (a) Children, tap a child, scroll to the end, **Remove**: it must say whether it
    deletes the child for good (you are the only guardian) or only removes you (another guardian is linked). A wrong password must be refused; the right password and
    the tick must remove the child from the list. (b) Profile, **Delete my account**: it first lists exactly what would be deleted, then asks for the password. After
    deleting, the app returns to the sign in screen and says the account was deleted, and signing in with that email must no longer work.
    (Never do this on the account you still need.)

## Things that only a real phone can show (please note them even if everything "works")
- The camera: scanning a QR card on both the Teacher screens and the sign in screen, in a dim room and in sunlight.
- The keyboard: does it cover a field you are typing in? Does Next/Done work?
- Reading size: set the phone's text size to Large and check nothing is cut off.
- The back button on the phone: it should go one step back, then to Home, then leave the app.
- How long each screen takes on mobile data. The free servers sleep after a quiet while, so the first request can take a minute.

## When something fails
Write the screen, what you pressed, what you expected and what happened, and take a screenshot. Never include a real child's name.
