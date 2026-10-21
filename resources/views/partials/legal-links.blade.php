{{--
  A small row of links to the legal pages (Privacy, Terms, Cookies, Refunds). Used under the sign in and sign up
  cards and on the profile pages, so a person can always reach what they are agreeing to. Self contained: it carries its
  own tiny style (once per page) so it works inside any of the standalone layouts. Pass 'align' => 'left' to left align.
--}}
@once
<style>
  .legal-links{ display:flex; flex-wrap:wrap; justify-content:center; gap:2px 16px; margin:18px 0 0; padding:0; font:500 13px/1.4 'Inter',system-ui,sans-serif; }
  .legal-links.left{ justify-content:flex-start; }
  .legal-links a{ color:inherit; opacity:.8; text-decoration:underline; text-underline-offset:3px; display:inline-flex; align-items:center; min-height:36px; padding:0 2px; }
  .legal-links a:hover{ opacity:1; }
</style>
@endonce
<nav class="legal-links {{ ($align ?? '') === 'left' ? 'left' : '' }}" aria-label="Legal">
  <a href="{{ route('privacy') }}">Privacy</a>
  <a href="{{ route('terms') }}">Terms</a>
  <a href="{{ route('cookies') }}">Cookies</a>
  <a href="{{ route('refunds') }}">Refunds</a>
</nav>
