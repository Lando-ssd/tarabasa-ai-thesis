{{--
  The Parent and Guardian Consent notice (public). It explains, in one place, what a parent agrees to when they tick the
  boxes in the "Add your child" steps (required: the child's account; optional and separate: the photo), what is optional,
  how to withdraw, and what happens if they say no. The boxes themselves are in resources/views/parent/children/create.blade.php
  (and link.blade.php, and the phone app), and what a parent ticks is written to consent_records with config('legal.version').
  Keep this page, those boxes and ConsentService::KINDS saying the same thing.
--}}
@extends('layouts.legal', [
    'doc' => 'consent',
    'title' => 'Parent and Guardian Consent',
    'description' => 'What a parent or guardian agrees to when adding a child to TaraBasa AI, what is optional, and how to withdraw consent.',
    'sections' => [
        'what' => 'What this notice is',
        'who' => 'Who can give consent',
        'agree' => 'What you agree to',
        'not' => 'What we will not do',
        'teachers' => 'Teachers and classes',
        'withdraw' => 'Changing your mind',
        'refuse' => 'If you do not agree',
        'record' => 'The record of your consent',
    ],
])

@section('summary')
  <ul>
    <li>A child's information can only be added by a parent or legal guardian, who must agree first.</li>
    <li>To make a child's profile you agree to us keeping the child's name, grade and reading results, and to the child's voice being sent to be scored. <b>The recording is not kept: it is deleted as soon as it has been scored.</b></li>
    <li>The child's photo is optional and has its own separate tick box. Extra details (home language, what helps, topics) are optional too.</li>
    <li>You can withdraw consent at any time by deleting the child. Saying no costs you nothing except that the child cannot use TaraBasa AI.</li>
  </ul>
@endsection

@section('body')

<section id="what">
  <h2>What this notice is</h2>
  <p>The Data Privacy Act of 2012 treats a person's age and information about their education as <b>sensitive personal information</b>, and a child cannot give consent alone. So before we keep anything about a child, a parent or legal guardian has to agree. This page says what you are agreeing to when you tick the box in the "Add your child" steps. It is read together with the <a href="{{ route('privacy') }}">Privacy Policy</a>.</p>
  <p>You can print this page for your records.</p>
</section>

<section id="who">
  <h2>Who can give consent</h2>
  <p>Only the child's <b>parent or legal guardian</b>, who is 18 or older, can add a child or link to a child. If a child has two guardians, each can link to the child with the learner code and the child's PIN, and each confirms they are the child's parent or guardian. If you are not the child's parent or guardian, please do not add the child.</p>
</section>

<section id="agree">
  <h2>What you agree to</h2>
  <div class="lg-scroll"><table class="lg-table">
    <thead><tr><th>You agree that we</th><th>Required?</th><th>Why</th></tr></thead>
    <tbody>
      <tr><td>Keep your child's first name, last name (and middle name if you enter one), grade, a 4 digit PIN (scrambled) and a learner code.</td><td>Required</td><td>To make the child's profile and let the child sign in.</td></tr>
      <tr><td>Keep how your child reads now (a stage and three short questions you answer).</td><td>Required</td><td>So the first reading check starts at the right place, not too hard or too easy.</td></tr>
      <tr><td>Record your child's voice when the child taps the microphone, send it to a scoring service, and keep the result (scores and the words heard). <b>The recording itself is not kept: it is deleted as soon as it has been scored.</b></td><td>Required</td><td>To score each reading and show progress.</td></tr>
      <tr><td>Keep your child's reading results: scores, words missed, reading level, points, badges and alerts.</td><td>Required</td><td>To choose what to read next, and to tell you and the teacher how your child is doing.</td></tr>
      <tr><td>Show this to you and any other guardian you link, to the teacher of a class your child joins, and, for running the service, to the site administrator.</td><td>Required</td><td>That is the purpose of the app. See "Who can see it" in the Privacy Policy.</td></tr>
      <tr><td>Use the outside services listed in the Privacy Policy (hosting, database, speech scoring, recommendation, email), sending each one only what it needs.</td><td>Required</td><td>They do part of the work.</td></tr>
      <tr><td>Keep a photo of your child.</td><td><b>Optional</b> (separate tick box)</td><td>So the child's picture shows in the app. Without it we show an avatar or the child's initial.</td></tr>
      <tr><td>Keep home language, what helps your child learn, topics your child likes.</td><td><b>Optional</b></td><td>To help teachers and to suggest activities.</td></tr>
    </tbody>
  </table></div>
</section>

<section id="not">
  <h2>What we will not do</h2>
  <ul>
    <li>Sell your child's information, or use it for advertising or marketing.</li>
    <li>Use your child's information, recordings or the words heard to train AI models.</li>
    <li>Show your child's name, photo or progress to the public, to other children, or to other parents.</li>
    <li>Collect your child's location, address, phone number, birth date or contacts.</li>
    <li>Keep the child's voice recording.</li>
  </ul>
</section>

<section id="teachers">
  <h2>Teachers and classes</h2>
  <p>A teacher can add your child to a class with the child's learner code. You are told in the app each time this happens, with the teacher's name and school. The teacher then sees what is listed under "Who can see it" in the Privacy Policy. If you did not expect it, or you do not agree, write to @include('partials.legal-contact') or talk to the teacher, and you can delete the child's profile at any time.</p>
</section>

<section id="withdraw">
  <h2>Changing your mind</h2>
  <ul>
    <li><b>Withdraw your consent for your child:</b> open <b>My children</b> (<b>Children</b> in the phone app), tap <b>Remove</b> on the child and type your password. The profile and every reading record are deleted straight away (if you are the only guardian), and nobody has to approve it.</li>
    <li><b>Withdraw only the photo:</b> there is no button for this yet. Write to @include('partials.legal-contact'), or delete the child's profile.</li>
    <li><b>Ask to see, correct or get a copy</b> of your child's information: write to @include('partials.legal-contact'). See "Your rights" in the Privacy Policy.</li>
  </ul>
  <p>Withdrawing consent does not affect what we did with the information before you withdrew.</p>
</section>

<section id="refuse">
  <h2>If you do not agree</h2>
  <p>You do not have to. If you do not tick the box, your child's profile is not created and nothing about your child is kept. There is no penalty, and nobody will think less of you. The child simply cannot use TaraBasa AI.</p>
</section>

<section id="record">
  <h2>The record of your consent</h2>
  <p>When you tick the box, we keep a short record: which parent, which child, what you agreed to, which version of these documents (currently {{ config('legal.version') }}), and when. We keep it to be able to show that you agreed, and we delete it when the child or your account is deleted.</p>
</section>

@endsection
