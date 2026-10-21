{{--
  The Cookie Policy (public). Every cookie the website sets is listed here, with its name and lifetime read from the
  real configuration (config/session.php), so the page cannot drift from the app. There is NO cookie banner on purpose:
  the app sets only strictly necessary cookies, no analytics, advertising or social cookies, and loads nothing from other
  companies. If a cookie that is not strictly necessary (analytics, a chat widget, an embed...) is ever added, this page
  must change and a consent banner must be built first. tests/Feature/LegalPagesTest.php fails if a new cookie appears.
--}}
@extends('layouts.legal', [
    'doc' => 'cookies',
    'title' => 'Cookie Policy',
    'description' => 'The cookies TaraBasa AI sets, what each one is for, and why there is no cookie banner: only the cookies needed to keep you signed in.',
    'sections' => [
        'what' => 'What cookies are',
        'ours' => 'The cookies we set',
        'similar' => 'Similar technologies',
        'not' => 'What we do not use',
        'banner' => 'Why there is no cookie banner',
        'choices' => 'Your choices',
        'others' => 'Other sites',
        'changes' => 'Changes and contact',
    ],
])

@section('summary')
  <ul>
    <li>We set <b>two small cookies</b>, both needed for the site to work: one keeps you signed in, the other protects forms.</li>
    <li>We use <b>no analytics, advertising, social media or tracking cookies</b>, and we load nothing from other companies, not even fonts.</li>
    <li>Because we set only necessary cookies, there is no cookie banner to click. If that ever changes, we will ask first.</li>
    <li>The phone app does not use cookies.</li>
  </ul>
@endsection

@section('body')

<section id="what">
  <h2>What cookies are</h2>
  <p>A cookie is a small piece of text that a website asks your browser to keep, and that the browser sends back on your next visit to that website. Websites use them to remember that you are signed in, for example.</p>
</section>

<section id="ours">
  <h2>The cookies we set</h2>
  <p>These are all of them. They are first party cookies, which means only TaraBasa AI sets and reads them.</p>
  <div class="lg-scroll"><table class="lg-table">
    <thead><tr><th>Name</th><th>What it is for</th><th>How long it lasts</th><th>Type</th></tr></thead>
    <tbody>
      <tr>
        <td><code>{{ config('session.cookie') }}</code></td>
        <td>Keeps you signed in as a parent, teacher, administrator or learner, and holds one time messages such as "Profile updated". It holds a random code, not your name or email.</td>
        <td>{{ config('session.lifetime') }} minutes after you last use the site (it renews each time you do).</td>
        <td>Strictly necessary</td>
      </tr>
      <tr>
        <td><code>XSRF-TOKEN</code></td>
        <td>Checks that a form really came from one of our pages, so another website cannot send a form for you.</td>
        <td>{{ config('session.lifetime') }} minutes.</td>
        <td>Strictly necessary</td>
      </tr>
    </tbody>
  </table></div>
  <p>Both are sent only to this website, with the "SameSite=Lax" setting. The first one is hidden from page scripts (HttpOnly), and both are marked Secure on the live site, so browsers send them only over an encrypted connection. We do not use the "remember me" cookie that keeps a person signed in for weeks.</p>
</section>

<section id="similar">
  <h2>Similar technologies</h2>
  <ul>
    <li><b>Browser session storage.</b> A few pages keep a note in your browser's session storage, which is not a cookie and is cleared when you close the tab: <code>tb-wake:</code> followed by a service name (the time we last woke a service, so we do it at most every two minutes), and <code>tb_warm_</code> followed by a learner code (that the microphone check was done). Nothing in session storage is sent to us.</li>
    <li><b>The phone app</b> uses no cookies. It keeps the sign in token and the person's first name in the phone's secure storage, and removes both when the person logs out.</li>
  </ul>
</section>

<section id="not">
  <h2>What we do not use</h2>
  <ul>
    <li>No analytics (such as Google Analytics) and no advertising or marketing pixels.</li>
    <li>No social media buttons or embedded videos, maps or chat boxes.</li>
    <li>No font, script or style files from other companies: our typefaces are served from this website, so no outside company sees your address when a page opens.</li>
    <li>No fingerprinting of your device.</li>
  </ul>
  <p>The one request that leaves our website without you clicking is explained in the <a href="{{ route('privacy') }}#services">Privacy Policy</a>: some pages ask the scoring and recommendation services' health pages, once, to wake them. That request carries no cookie.</p>
</section>

<section id="banner">
  <h2>Why there is no cookie banner</h2>
  <p>Cookie banners ask for consent before a website uses cookies that are not needed for it to work, such as analytics or advertising. We set only the two necessary cookies above, which cannot be switched off without breaking the site, so there is nothing to agree to or refuse. If we ever add any other cookie, we will update this page and ask you first.</p>
  <p>The Data Privacy Act of 2012 still applies to any personal information, and our <a href="{{ route('privacy') }}">Privacy Policy</a> covers all of it.</p>
</section>

<section id="choices">
  <h2>Your choices</h2>
  <p>You can see, block and delete cookies in your browser's settings. If you block the two cookies above, you will not be able to sign in. Logging out ends your session. On a shared computer, log out when you finish, and close the tab.</p>
</section>

<section id="others">
  <h2>Other sites</h2>
  <p>If you choose "Continue with Google" to sign in, you leave TaraBasa AI for Google, and Google's own cookies and policies apply there.</p>
</section>

<section id="changes">
  <h2>Changes and contact</h2>
  <p>If we add or remove a cookie we change this page, its date and its version. Questions: write to @include('partials.legal-contact').</p>
  <div class="lg-scroll"><table class="lg-table">
    <thead><tr><th>Version</th><th>What changed</th></tr></thead>
    <tbody>
      <tr><td>{{ config('legal.version') }}</td><td>The first version of the Cookie Policy.</td></tr>
    </tbody>
  </table></div>
</section>

@endsection
