{{--
  The Terms of Use (public). Plain words. Written for a student capstone project that real parents, teachers and children
  may use: it says so honestly, says what the app can and cannot do, and does not promise results. It must match what the
  app does (payments are simulated, AI drafts are reviewed by a teacher, speech scoring can be wrong, a class holds one
  grade, accounts can be deleted by their owner). Change config('legal.version') and the "Changes" table when its meaning
  changes. It is NOT a substitute for a lawyer's review; see docs/legal/LEGAL-RISK-REGISTER.md.
--}}
@extends('layouts.legal', [
    'doc' => 'terms',
    'title' => 'Terms of Use',
    'description' => 'The rules for using TaraBasa AI: who may use it, what parents and teachers are responsible for, what the app can and cannot do, and how an account ends.',
    'sections' => [
        'about' => 'About TaraBasa AI and these terms',
        'who' => 'Who may use it',
        'account' => 'Your account',
        'parents' => 'Parents and guardians',
        'teachers' => 'Teachers',
        'does' => 'What the app does and does not do',
        'rules' => 'Rules for using it',
        'content' => 'Content and licences',
        'payments' => 'Payments',
        'availability' => 'Availability and changes',
        'ending' => 'Ending your use, or ours',
        'liability' => 'Disclaimers and limits',
        'law' => 'Governing law',
        'changes' => 'Changes to these terms',
        'contact' => 'Contact',
    ],
])

@section('summary')
  <ul>
    <li>TaraBasa AI is a free reading practice tool made by a student team for a capstone project. It is a prototype, not a finished commercial product.</li>
    <li>Parents and legal guardians (18 or older) create accounts for their children. Teachers need to be teaching at a school. Children never sign up alone.</li>
    <li>Speech scoring and suggestions are aids for a teacher or parent. They can be wrong, and they are not a test, a diagnosis, or a promise that a child will improve.</li>
    <li>There are no real payments. You can delete a child or your account yourself at any time.</li>
    <li>Be honest, keep your account safe, and use children's information only for teaching reading.</li>
  </ul>
@endsection

@section('body')

<section id="about">
  <h2>About TaraBasa AI and these terms</h2>
  <p>TaraBasa AI is a reading practice website and phone app for Grade 1 to 3 learners, run by {{ config('legal.operator') }}@if (config('legal.operator_detail')) ({{ config('legal.operator_detail') }})@endif ("we", "us"). It was built as a capstone project. We offer it free, as it is, and we may change it.</p>
  <p>These terms are an agreement between you and us about using TaraBasa AI. You accept them when you tick the box at sign up, or when you use the website or the app. If you do not agree, please do not use it. Please also read the <a href="{{ route('privacy') }}">Privacy Policy</a>, the <a href="{{ route('cookies') }}">Cookie Policy</a> and the <a href="{{ route('consent') }}">Parent and Guardian Consent</a> notice, which are part of how we deal with you.</p>
  <p>TaraBasa AI is <b>not made, approved or endorsed by the Department of Education</b>. It uses the names of DepEd documents (such as MATATAG and Phil-IRI) only to say which curriculum or reading levels a feature refers to.</p>
</section>

<section id="who">
  <h2>Who may use it</h2>
  <ul>
    <li><b>Parents and legal guardians</b> must be 18 or older. You create the account and add your child's profile, and you accept these terms for yourself and for your child.</li>
    <li><b>Teachers</b> must be teaching at a school. You give your school name and employee ID, and the site administrator checks them. Until you are approved you can try the tools, but you cannot join real students to a class.</li>
    <li><b>Children</b> use TaraBasa AI only through a profile a parent made, signing in with a learner code and a 4 digit PIN, and should be supervised by a parent or teacher. A child does not create an account and does not accept these terms.</li>
    <li><b>Administrators</b> run the service and check accounts.</li>
  </ul>
</section>

<section id="account">
  <h2>Your account</h2>
  <ul>
    <li>Give true information, and keep it up to date.</li>
    <li>Keep your password, and your child's PIN and learner code card, safe. Do not share them beyond the family and the child's teacher. Tell us if you think someone else has them.</li>
    <li>You are responsible for what happens under your account.</li>
    <li>One person, one account. We may require you to confirm your email address before you can sign in.</li>
  </ul>
</section>

<section id="parents">
  <h2>Parents and guardians</h2>
  <ul>
    <li>Add only a child you are the parent or legal guardian of.</li>
    <li>Give true information about your child, and read the <a href="{{ route('consent') }}">Parent and Guardian Consent</a> notice before ticking the box.</li>
    <li>Upload a photo only if it is a photo of your own child and you have the right to share it. The photo is optional.</li>
    <li>If you link yourself to a child who already has a profile, you need the child's learner code and PIN, and you confirm that you are the child's parent or guardian.</li>
  </ul>
</section>

<section id="teachers">
  <h2>Teachers</h2>
  <ul>
    <li>Join a child to your class only when the child's parent has agreed to the school using TaraBasa AI. The parent is told in the app each time you do.</li>
    <li>Use what you see about a child only to teach that child, keep it confidential, and follow your school's rules and the Department of Education's child protection rules.</li>
    <li><b>Read and approve what the AI writes.</b> Activities written by the AI activity writer are drafts. You decide whether a child sees them. Check them for mistakes and for anything unsuitable for a child.</li>
    <li>Do not type a child's name or other personal details in the topic or notes boxes of the activity writer.</li>
    <li>A class holds one grade only. If you teach more than one grade, make one class for each. Adding a grade to your account is checked by the administrator.</li>
    <li>Do not share a child's learner code or your login with anyone else.</li>
  </ul>
</section>

<section id="does">
  <h2>What the app does and does not do</h2>
  <div class="lg-warn">
    <p><b>TaraBasa AI is a practice and support tool. It is not an assessment, a diagnosis, or a guarantee.</b></p>
  </div>
  <ul>
    <li><b>Speech scoring can be wrong.</b> The speech recognizer is built for adult speech and can mishear children, accents, names and noisy rooms. A score is an estimate. A word can be marked wrong when it was read right, and the other way round.</li>
    <li><b>Suggestions are aids.</b> Reading steps, levels (such as Independent, Instructional or Frustration), alerts, and what to read next come from written rules and fixed formulas, with numbers we chose ourselves. They are not a clinical, psychological or special education assessment, and they do not replace a teacher's judgment or a professional's advice. <a href="{{ route('how-it-works') }}">How TaraBasa decides</a> lists every rule.</li>
    <li><b>AI drafts need a person.</b> Activities written by an AI are checked by a teacher before children use them. We cannot promise that an AI draft is free of errors.</li>
    <li><b>No promise of results.</b> We do not promise that using TaraBasa AI will improve a child's reading, test scores or motivation.</li>
    <li>A teacher or parent stays responsible for decisions about a child.</li>
  </ul>
</section>

<section id="rules">
  <h2>Rules for using it</h2>
  <p>Please do not:</p>
  <ul>
    <li>break the law, or use TaraBasa AI to harm, bully, scare or mislead anyone, especially a child;</li>
    <li>pretend to be someone else, or use someone else's account;</li>
    <li>try to get into accounts, data or systems that are not yours, to test our security without our written permission, to overload the service, or to copy it in bulk;</li>
    <li>upload or write anything unlawful, offensive, or that belongs to someone else without permission;</li>
    <li>use a child's information, or the learner codes, for anything other than teaching and supporting that child's reading;</li>
    <li>record, share or publish a child's progress, screens or voice outside the family and the school.</li>
  </ul>
</section>

<section id="content">
  <h2>Content and licences</h2>
  <ul>
    <li><b>What you write is yours.</b> That includes activities a teacher writes. You allow us to store and show it as the service needs.</li>
    <li><b>Sharing to the library.</b> When a teacher shares an approved activity to the Open Repository, the teacher allows TaraBasa AI and the parents who unlock it to show and use it for reading practice. Share only what you wrote or have the right to share. To have an activity taken down, write to @include('partials.legal-contact').</li>
    <li><b>Our work.</b> The TaraBasa AI program, design and texts belong to the team, who keep all rights not given here. Some parts use open source libraries, fonts and icons under their own licences.</li>
    <li><b>Feedback.</b> If you send us ideas, we may use them without owing you anything.</li>
  </ul>
</section>

<section id="payments">
  <h2>Payments</h2>
  <p>TaraBasa AI is free. It does not take payments. Prices shown on shared activities are examples, and the "payment" step is a simulation that never charges anyone. The <a href="{{ route('refunds') }}">Refund Policy</a> explains this.</p>
</section>

<section id="availability">
  <h2>Availability and changes</h2>
  <p>TaraBasa AI runs on free hosting, and some of its parts sleep when they are not used. The first action after a quiet period can take a minute, and the service can be slow, change, or be unavailable. We may change or stop any feature, or the whole service, and we will try to give notice when we can. Please do not rely on it for anything urgent, and keep your own copy of anything you cannot lose.</p>
</section>

<section id="ending">
  <h2>Ending your use, or ours</h2>
  <ul>
    <li><b>You</b> can stop at any time. You can delete a child's profile, or your whole account, yourself (see the <a href="{{ route('privacy') }}#delete">Privacy Policy</a>).</li>
    <li><b>We</b> may suspend or close an account that breaks these terms, puts a child or the service at risk, or is used by someone it does not belong to. When we can, we will say why. A teacher's account can also be closed if the school details cannot be confirmed.</li>
  </ul>
</section>

<section id="liability">
  <h2>Disclaimers and limits</h2>
  <p>To the extent the law allows, TaraBasa AI is provided <b>"as is" and "as available"</b>, without promises that it will be error free, always available, or fit for a particular purpose.</p>
  <p>To the extent the law allows, we are not responsible for losses that follow from relying on a score, a suggestion or an AI draft; for indirect or knock on losses; or for events outside our control, such as the failure of a hosting or internet provider.</p>
  <div class="lg-note">
    <p>Nothing in these terms takes away rights you have under Philippine law, including the Data Privacy Act of 2012 and consumer protection laws, or limits responsibility that the law does not allow to be limited, such as for fraud or deliberate harm.</p>
  </div>
</section>

<section id="law">
  <h2>Governing law</h2>
  <p>These terms follow the laws of the Republic of the Philippines. If we have a disagreement, please write to us first so we can try to settle it. If it cannot be settled, it is for the proper courts of the Philippines.</p>
</section>

<section id="changes">
  <h2>Changes to these terms</h2>
  <p>If we change these terms in a way that matters, we update this page, its date and its version, and list the change here. When a change affects what you agreed to, we will tell you by email or in the app and, where the change needs your agreement, ask you to agree again. If you keep using TaraBasa AI after a small change, such as a correction, that means you accept it.</p>
  <div class="lg-scroll"><table class="lg-table">
    <thead><tr><th>Version</th><th>What changed</th></tr></thead>
    <tbody>
      <tr><td>{{ config('legal.version') }}</td><td>No change to these terms. The Privacy Policy changed: where a child's photo is kept and who sees it.</td></tr>
      <tr><td>2026-10-21</td><td>The first version of the Terms of Use.</td></tr>
    </tbody>
  </table></div>
</section>

<section id="contact">
  <h2>Contact</h2>
  <p>Questions about these terms: write to @include('partials.legal-contact').</p>
</section>

@endsection
