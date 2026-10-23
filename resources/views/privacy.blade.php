{{--
  The Privacy Policy (public, no sign in). Plain words, because parents and teachers read it, and it says only what the
  app really does. It was checked against the code on 2026-10-23 (and the phone app part against mobile/src): recordings
  are kept only while they are scored (the website's scoring waits on a temporary file that is deleted when the job ends,
  App\Services\BackgroundScoring; the phone deletes its own temporary copy, see mobile/src/recordingFiles.ts), nothing is sold,
  there are no analytics or advertising cookies and no outside font or script service, and every outside service is
  listed with what it receives. The phone app's permissions (microphone, camera) are in mobile/app.json; if one is added,
  say what it is for here. The address Google asks for to publish the Gmail sender is this page (/privacy), and the
  "Delete" section is the page an app store asks for under "how to request deletion" (/privacy#delete): keep both.
  If the app starts doing something new with data (a new outside service, storing recordings, a new cookie), change this
  page in the same commit, bump config('legal.version') and add a row to the "Changes" table. tests/Feature/LegalPagesTest.php
  and PrivacyPageTest.php pin the claims that matter.
--}}
@extends('layouts.legal', [
    'doc' => 'privacy',
    'title' => 'Privacy Policy',
    'description' => 'What TaraBasa AI collects about parents, teachers and children, why, who sees it, who helps us, and how to ask for it to be removed.',
    'sections' => [
        'who' => 'Who is responsible',
        'collect' => 'What we collect',
        'voice' => 'Voice recordings',
        'why' => 'Why we use it',
        'children' => 'Children',
        'sees' => 'Who can see it',
        'services' => 'Services that help us',
        'phone' => 'The phone app',
        'abroad' => 'Where it is kept',
        'keep' => 'How long we keep it',
        'rights' => 'Your rights',
        'delete' => 'Deleting your information',
        'security' => 'Keeping it safe',
        'cookies' => 'Cookies',
        'changes' => 'Changes to this policy',
        'contact' => 'Contact and complaints',
    ],
])

@section('summary')
  <ul>
    <li>TaraBasa AI is a reading practice website and phone app for Grade 1 to 3 learners, built by a student team as a capstone project.</li>
    <li>We keep only what is needed to teach reading: names, grade, and reading results. A child's parent and class teacher can see the child's progress.</li>
    <li>A child's voice is recorded only when the child taps the microphone. <b>The app does not keep the recording.</b> It is deleted as soon as it has been scored. We keep the scores and the words the speech recognizer heard, not the sound.</li>
    <li>We do not sell information, show advertising, or use analytics or tracking. The only cookies are the ones that keep a person signed in. We do not load fonts or scripts from other companies.</li>
    <li>A parent decides what to enter about a child, and can delete the child, or the whole account, at any time. Nobody has to approve it.</li>
    <li>A few outside services do part of the work (hosting, a database, speech scoring, email). Section 7 lists each one and what it receives.</li>
  </ul>
@endsection

@section('body')

<section id="who">
  <h2>Who is responsible</h2>
  <p>TaraBasa AI is run by {{ config('legal.operator') }}@if (config('legal.operator_detail')) ({{ config('legal.operator_detail') }})@endif. It is a capstone project, not a company. Under the Data Privacy Act of 2012 (Republic Act No. 10173), the team is the <b>personal information controller</b> for the information described on this page.</p>
  @if (config('legal.accountable_person'))
    <p>The person accountable for how we follow the Act is {{ config('legal.accountable_person') }}.</p>
  @endif
  <p>To ask a question, use a right described in section 11, or report a problem, write to @include('partials.legal-contact').</p>
</section>

<section id="collect">
  <h2>What we collect</h2>
  <div class="lg-scroll"><table class="lg-table">
    <thead><tr><th>Whose</th><th>What</th><th>Where it comes from</th></tr></thead>
    <tbody>
      <tr><td><b>Parents</b></td><td>First name, middle initial, last name, email address, a password (kept scrambled, never readable), and, for a guardian who links to a child someone else added, how you are related to the child (for example mother). A record of when you agreed to the Terms and Privacy Policy, and which version.</td><td>You, when you sign up and add a child.</td></tr>
      <tr><td><b>Teachers</b></td><td>The same name, email and password details, plus school name, employee ID, the grades you handle, and an optional contact number. The class lists you create, the activities you write or share, and which alerts you marked as handled.</td><td>You. The site administrator checks the school details.</td></tr>
      <tr><td><b>Children</b></td><td>First name, optional middle name, last name, grade, an avatar or an optional photo, a learner code and a 4 digit PIN (kept scrambled). The parent also tells us how the child reads now (a stage and three short questions) and may add the home language, what helps the child most, and topics the child likes.</td><td>A parent adds each child. A child cannot sign up alone.</td></tr>
      <tr><td><b>What a child does</b></td><td>For each reading: the date, the scores, the speed, which words were missed, and the words the speech recognizer heard. The child's reading step and level, points, streak, badges, which practice games were finished (game and level only), a list of words the child keeps missing, and alerts about the child.</td><td>The child, while reading.</td></tr>
      <tr><td><b>Technical</b></td><td>While you are signed in, the website keeps a session record that includes your internet address (IP address) and browser details. Counters of wrong sign in tries (by address and email or learner code) for a few minutes. Error records, which hold the kind of error and the service that failed, for 14 days. Our hosting company also keeps ordinary server logs.</td><td>Your device, automatically.</td></tr>
      <tr><td><b>Email</b></td><td>Confirmation and password reset emails are sent from a TaraBasa AI mailbox. That mailbox keeps a copy of what it sent, which includes your email address.</td><td>Created when you sign up or ask for a reset.</td></tr>
    </tbody>
  </table></div>
  <div class="lg-note">
    <p><b>Sensitive information.</b> Under the Data Privacy Act, a person's age and information about their education are <b>sensitive personal information</b>. A child's grade and reading results therefore get extra care: we collect them only with a parent's consent, keep them small, show them only to the people in section 6, and delete them when asked.</p>
  </div>
  <p>We do not collect a child's address, phone number, birth date, location, contacts, or anything from the phone's files or photos.</p>
</section>

<section id="voice">
  <h2>Voice recordings</h2>
  <p>A child's voice is recorded, in the browser or in the phone app, only when the child taps the microphone. The recording is sent over an encrypted connection to a speech scoring service, which works out which words were said and returns the scores. <b>The app does not keep the recording.</b> We keep the result (the scores and the words that were heard), not the audio.</p>
  <ul>
    <li>On the website, the recording waits for a few seconds in a temporary file on our own server while a background worker scores it. The file is deleted the moment the scoring ends, whether it worked or not. A file left behind by a crash is removed within minutes, and the recording of a child who is deleted goes with the child. Nobody listens to it.</li>
    <li>The scoring service handles each recording in a temporary folder that is removed when the request ends. By its program code, it does not store the audio or the words. We read that code to check this; we have not had it audited by anyone else.</li>
    <li>The phone checks, on the phone itself, that a voice was heard. If nothing was heard, nothing is sent.</li>
    <li>The short microphone check at the start of the first reading only listens, and keeps and sends nothing.</li>
    <li>We do not use recordings or the words heard to train a speech model or any other AI model.</li>
  </ul>
</section>

<section id="why">
  <h2>Why we use it</h2>
  <p>To give you what you asked for:</p>
  <ul>
    <li>score a reading, choose what to read next, and show progress to a child's parent and teacher;</li>
    <li>let teachers make classes, write and approve activities, and see how their class is doing;</li>
    <li>send account emails (confirming an email address, resetting a password);</li>
    <li>keep the service safe: stop repeated wrong sign in tries, find and fix errors, and let the administrator manage accounts.</li>
  </ul>
  <p>The Data Privacy Act asks us to have a lawful basis for this. For a child's information, our basis is the <b>consent of the child's parent or guardian</b>, given when the parent adds the child (see the <a href="{{ route('consent') }}">Parent and Guardian Consent</a> notice). For adults, it is your consent when you sign up and what is needed to provide the service you asked for. For keeping the service secure, it is our legitimate interest in doing so. We do not use information for advertising, we do not sell it, and we do not use it for anything a parent or teacher would not expect from a reading practice app.</p>
  <p><b>Suggestions are made by written rules.</b> The app suggests what a child might read next, and which children may need help, using written rules and fixed formulas, not a model trained on children's data. A teacher or parent decides what happens. <a href="{{ route('how-it-works') }}">How TaraBasa decides</a> lists every rule and number.</p>
</section>

<section id="children">
  <h2>Children</h2>
  <p>A child cannot sign up alone. A parent or legal guardian creates the child's profile, chooses what to enter, and gives consent for the child. The photo is optional. Children signing in use a learner code and a 4 digit PIN, with no email or password.</p>
  <ul>
    <li>A child's screens have no advertising, no chat, no public profile, no links to other websites, and no way to contact other users.</li>
    <li>A child sees only their own progress.</li>
    <li>A parent can reset the child's PIN, and other guardians are told when that happens.</li>
  </ul>
  <p>Parents: before you add a child, please read the <a href="{{ route('consent') }}">Parent and Guardian Consent</a> notice, which says what you agree to and what is optional.</p>
</section>

<section id="sees">
  <h2>Who can see it</h2>
  <ul>
    <li><b>A child's parent or parents</b> (every guardian linked to the child): everything about that child in the app.</li>
    <li><b>The teacher of a class the child has joined.</b> A teacher joins a child to a class with the learner code. The child's parents are told in the app when this happens. The teacher then sees the child's name, grade, reading level and step, readings and scores, words missed, the kinds of mistakes that repeat, and what the parent shared when adding the child (how the child reads, home language, what helps, topics). A teacher can also see a short summary of a class (for example the most common mistakes), without names.</li>
    <li><b>The site administrator</b> manages accounts, checks teachers' school details, reads the activity log and the system health page, and can delete an account. The administrator does not read children's readings in the normal course of the work.</li>
    <li><b>The people who run the service.</b> Team members who keep the website working can reach the database. They use that only to run, fix and secure the service.</li>
    <li><b>Teachers who share activities.</b> A parent who leaves a star rating or comment on a shared activity is seen by the teacher only as part of an average.</li>
  </ul>
  <p>We do not sell information, show advertising, or use analytics or advertising cookies. We do not share information with anyone else except the services in section 7 and when the law requires it.</p>
</section>

<section id="services">
  <h2>Services that help us</h2>
  <p>These companies and services do part of the work. We chose them for the job and send each one only what it needs.</p>
  <div class="lg-scroll"><table class="lg-table">
    <thead><tr><th>Service</th><th>What it does</th><th>What it receives</th></tr></thead>
    <tbody>
      <tr><td><b>Render</b> (website host)</td><td>Runs the website and the phone app's server, in its Singapore region.</td><td>Everything you send to the website, because it passes through the server. Render also keeps ordinary server logs.</td></tr>
      <tr><td><b>TiDB Cloud</b> (database)</td><td>Stores the information in section 2, in its Singapore region.</td><td>All the stored information.</td></tr>
      <tr><td><b>Reading scoring service</b></td><td>Scores a reading.</td><td>The recording, the words of the activity, and the curriculum code and difficulty of the activity. No name and no learner code. It is run by a member of the project team and hosted on Render.</td></tr>
      <tr><td><b>Activity recommendation service</b></td><td>Suggests what to read next from fixed formulas.</td><td>Scores and an internal number for the child. Not the child's name. Run by the same team member, hosted on Render.</td></tr>
      <tr><td><b>AI activity writer</b></td><td>Writes draft reading activities for teachers to review.</td><td>What a teacher types (grade, skill, topic, notes). When a teacher picks "For a class", also a short summary of that class made from its readings: the reading step most children are on, mistakes several of them make, words several of them missed, and topics their parents said they like. It never receives a name, a learner code or any one child's details. It sends the request to a Google AI model (Gemini). <b>Teachers: please do not type a child's name or any personal detail in the topic or notes boxes.</b> Google's own terms and privacy policy apply to what that model receives.</td></tr>
      <tr><td><b>Gmail</b> (Google)</td><td>Sends account emails.</td><td>The email address and the text of the email.</td></tr>
      <tr><td><b>Google sign in</b> (optional, adults only)</td><td>Lets an adult with an existing account sign in with Google.</td><td>When you choose it you leave our site for Google. We receive the name and email address of the Google account.</td></tr>
    </tbody>
  </table></div>
  <p><b>Wake up requests.</b> The scoring and recommendation services sleep when nobody has used them for a while. So that a child does not wait, some pages (the reading screens, the games, and the teacher's activity writer) ask each service's health page, once, from your own device, as soon as the page opens. That request sends no cookie, no recording and nothing about a child, but the service can see your internet address, as any website does when you visit it.</p>
  <p><b>Not used:</b> advertising networks, analytics, social media buttons, embedded video or maps, and font or script services. Our typefaces are served from this website, so no font company sees your address. The device's own voice, which reads words aloud, works on the device and sends nothing anywhere.</p>
</section>

<section id="phone">
  <h2>The phone app</h2>
  <p>The phone app (Android) is for learners, teachers and parents. Children sign in on the phone with the learner code and the 4 digit PIN, and a child's profile is created by a parent. What the phone app does, and does not do:</p>
  <ul>
    <li><b>Microphone:</b> used only while a child is reading or saying a word. The phone first checks, on the phone itself, that a voice was heard. If nothing was heard, nothing is sent.</li>
    <li><b>The phone's copy of a recording</b> is removed as soon as our server has answered. If sending fails, the copy stays so the child can send it again, and it is removed the next time the app opens or the child logs out.</li>
    <li><b>Camera:</b> used only to scan the QR code on a learner card. The picture is read on the phone and is not saved or sent anywhere. Only the code printed on the card is used to fill in the sign in box. A child can type the code instead.</li>
    <li><b>Kept on the phone:</b> the sign in token and the person's first name, in the phone's secure storage. Both are removed when the person logs out.</li>
    <li><b>Voice that reads aloud:</b> the phone's own voice. Nothing is sent anywhere for that.</li>
    <li><b>Not used:</b> advertising, analytics, location, contacts, files or photos on the phone, and the phone's advertising ID.</li>
  </ul>
</section>

<section id="abroad">
  <h2>Where it is kept</h2>
  <p>The website, its database and the phone app's server are in Singapore. The scoring and recommendation services are hosted on Render, and Google's services (Gmail, Gemini, Google sign in) run in Google's own data centres, so some information is handled outside the Philippines. The Data Privacy Act keeps us responsible for information that we send to a service we use, and we send only what that service needs.</p>
  <p><b>Photos</b> are kept in the database together with the rest of the child's profile, not as separate files, so restarting or redeploying the website does not remove them. A photo is shown only to a signed in parent or guardian of the child, to the teacher of the class the child is in, and to the child. We make no backups of our own; a photo is covered only by whatever the database provider keeps for the database as a whole.</p>
</section>

<section id="keep">
  <h2>How long we keep it</h2>
  <div class="lg-scroll"><table class="lg-table">
    <thead><tr><th>Information</th><th>How long</th></tr></thead>
    <tbody>
      <tr><td>Account and profile details, a child's profile, readings, badges, words, alerts, and the record of your consent</td><td>Until you delete the child or the account (section 12). We do not delete them on a schedule while the account is in use.</td></tr>
      <tr><td>Sign in session (with address and browser details)</td><td>While you are signed in, and about {{ config('session.lifetime') }} minutes after you last use the site. Expired sessions are removed by routine clean up.</td></tr>
      <tr><td>Counters of wrong sign in tries</td><td>A few minutes, up to 15 minutes for a child's PIN.</td></tr>
      <tr><td>Error records</td><td>14 days.</td></tr>
      <tr><td>The line that says a deletion happened (what kind and when, with no name)</td><td>Kept, because it holds no information about a person.</td></tr>
      <tr><td>The administrator's activity log</td><td>Kept. It names the administrator and the reason, not the person who was deleted.</td></tr>
      <tr><td>Copies held by our hosting companies</td><td>Their backups and logs follow their own rules, which we do not control.</td></tr>
    </tbody>
  </table></div>
</section>

<section id="rights">
  <h2>Your rights</h2>
  <p>The Data Privacy Act (Sections 16 and 18) gives you these rights. A parent or guardian can use them for a child.</p>
  <ul>
    <li><b>To be informed</b> about what we do with information, which is what this page is for.</li>
    <li><b>To see</b> what we hold about you or your child.</li>
    <li><b>To correct</b> what is wrong. A parent or teacher can change their own name, contact number and password in Profile. For anything you cannot change yourself, such as a child's details, ask us.</li>
    <li><b>To object, or to withdraw consent</b>, at any time. A parent can do this by deleting the child or the account (section 12).</li>
    <li><b>To have information removed or blocked</b> when it is wrong, unlawfully obtained, no longer needed, or used without permission.</li>
    <li><b>To get a copy</b> of your information in a usable electronic format. This is done by request, not from a button.</li>
    <li><b>To claim damages</b> if you were harmed by inaccurate, incomplete, outdated, false or unlawfully obtained information.</li>
    <li><b>To complain</b> to the National Privacy Commission (see section 16).</li>
  </ul>
  <p>Write to @include('partials.legal-contact'). We answer within {{ config('legal.reply_days') }} days and do not charge for it. We may ask you to prove who you are first, so that we never give a child's information to the wrong person.</p>
</section>

<section id="delete">
  <h2>Deleting your information</h2>
  <p>Information stays while the account is in use. You can delete it yourself at any time, on the website or in the phone app. Nobody has to approve it. This follows the Data Privacy Act of 2012 (Republic Act 10173), which lets a person ask for their personal data to be removed or destroyed.</p>
  <ul>
    <li><b>A child's profile:</b> a parent opens <b>My children</b> (<b>Children</b> in the phone app), taps <b>Remove</b> on the child and types their password. If the parent is the child's only guardian, the profile and every reading record, badge, word and alert are deleted, and so is the photo if there was one. If another guardian is linked, only the parent who asked is removed and the child stays for the other guardian.</li>
    <li><b>An account:</b> a parent or teacher opens <b>Profile</b> (in the phone app: the picture at the top, or <b>More</b>, then <b>My profile</b>) and chooses <b>Delete my account</b>. A parent's account takes with it the children who have no other guardian. A teacher's account takes the classes and the activities shared to the library. The learners stay with their parents. Activities a teacher wrote stay without the teacher's name, because children's reading history is attached to them.</li>
    <li><b>Only the photo:</b> there is no button for this yet. Ask us, or delete the child's profile.</li>
  </ul>
  <p>We keep one line that a deletion happened (what kind, and when) and nothing that says who it was.</p>
  <p>If you cannot sign in, write to @include('partials.legal-contact') and the site administrator can delete it for you.</p>
</section>

<section id="security">
  <h2>Keeping it safe</h2>
  <ul>
    <li>Passwords and PINs are stored scrambled, so not even we can read them.</li>
    <li>The live site and the phone app use an encrypted connection.</li>
    <li>Repeated wrong password or PIN tries are slowed down and locked for a while.</li>
    <li>What a person can see depends on their role, and a parent can reach only their own children.</li>
    <li>We keep the information we hold small.</li>
  </ul>
  <p>No website can promise perfect security. <b>If something goes wrong</b> and sensitive information is, or may have been, taken by someone who should not have it, in a way that is likely to cause real harm, we will tell the National Privacy Commission and the people affected within 72 hours of knowing, as the law asks.</p>
</section>

<section id="cookies">
  <h2>Cookies</h2>
  <p>We set only the cookies that are needed to keep a person signed in and to protect forms. There are no analytics, advertising or tracking cookies. The <a href="{{ route('cookies') }}">Cookie Policy</a> lists each one.</p>
</section>

<section id="changes">
  <h2>Changes to this policy</h2>
  <p>If this changes in a way that matters, we update this page, its date and its version, and the change is listed here. When a change affects what a parent agreed to, we will tell parents by email or in the app and, where the change needs their consent, ask them to agree again.</p>
  <div class="lg-scroll"><table class="lg-table">
    <thead><tr><th>Version</th><th>What changed</th></tr></thead>
    <tbody>
      <tr><td>{{ config('legal.version') }}</td><td>A recording now waits for a few seconds in a temporary file on our server while it is scored in the background, and is deleted as soon as the scoring ends. Before, the website scored it while the page waited and did not keep it at all. Nothing else about voice recordings changed.</td></tr>
      <tr><td>2026-10-21.2</td><td>A child's photo is now kept in the database instead of on the website server's disk, so it is no longer lost when the website restarts. It is shown only to the child's parents or guardians, the child's class teacher and the child.</td></tr>
      <tr><td>2026-10-21</td><td>Rewritten with a summary, a list of every service, retention times and rights. Added the Terms of Use, Cookie Policy, Refund Policy and Parent and Guardian Consent. Fonts are now served from this website, not from Google.</td></tr>
      <tr><td>2026-10-20</td><td>The first public version of the Privacy page.</td></tr>
    </tbody>
  </table></div>
</section>

<section id="contact">
  <h2>Contact and complaints</h2>
  <p>Questions, requests and problems: write to @include('partials.legal-contact').</p>
  <p>If you think we have handled your information wrongly and we have not put it right, you may complain to the <b>National Privacy Commission</b> of the Philippines (<a href="https://privacy.gov.ph" rel="noopener">privacy.gov.ph</a>).</p>
</section>

@endsection
