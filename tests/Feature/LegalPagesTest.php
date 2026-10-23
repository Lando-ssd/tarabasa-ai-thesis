<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The legal pages (Privacy Policy, Terms of Use, Cookie Policy, Refund Policy, Parent and Guardian Consent) must say only
 * what the app really does. This pins the facts they rely on, so that changing the app without changing the pages fails here:
 * no request to another company's server, only the two cookies the Cookie Policy lists, no payment library, no
 * invented reviews or results on the home page. Each test says which page it protects.
 */
class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES = ['privacy' => '/privacy', 'terms' => '/terms', 'cookies' => '/cookies', 'refunds' => '/refunds', 'consent' => '/parent-consent'];

    public function test_every_legal_page_opens_without_signing_in_and_shows_its_date_and_version(): void
    {
        foreach (self::PAGES as $name => $path) {
            $this->get($path)->assertOk()->assertSee('Last updated', false)->assertSee(config('legal.version'), false)->assertSee('The short version', false);
        }
    }

    public function test_every_legal_page_links_to_all_the_others(): void
    {
        foreach (self::PAGES as $path) {
            $page = $this->get($path)->assertOk();

            foreach (self::PAGES as $other) {
                $page->assertSee('href="'.url($other).'"', false);
            }
        }
    }

    public function test_the_contact_follows_the_setting_on_every_page(): void
    {
        foreach (self::PAGES as $path) {
            config(['legal.contact_email' => null]);
            $this->get($path)->assertDontSee('mailto:', false);

            config(['legal.contact_email' => 'help@example.com']);
            $this->get($path)->assertSee('mailto:help@example.com', false);
        }
    }

    public function test_the_numbers_one_section_uses_to_point_at_another_are_still_right(): void
    {
        // The Privacy Policy says "section 7", "section 11", "section 12" and so on. Sections are numbered by their order, so moving
        // one silently breaks those pointers. This fails if the order changes without the text being updated.
        preg_match_all('/<li><a href="#([a-z]+)">/', $this->get('/privacy')->getContent(), $m);
        $order = $m[1];

        $this->assertSame(1, array_search('who', $order) + 1);
        $this->assertSame(2, array_search('collect', $order) + 1);
        $this->assertSame(6, array_search('sees', $order) + 1);
        $this->assertSame(7, array_search('services', $order) + 1);
        $this->assertSame(11, array_search('rights', $order) + 1);
        $this->assertSame(12, array_search('delete', $order) + 1);
        $this->assertSame(16, array_search('contact', $order) + 1);
        $this->get('/privacy')->assertSee('id="delete"', false);
    }

    public function test_no_page_or_script_loads_anything_from_another_company(): void
    {
        // The Privacy and Cookie Policies say nothing is loaded from other companies (no fonts, scripts, analytics, embeds).
        $bad = [];
        $roots = [resource_path('views'), public_path('js'), public_path('css')];

        foreach ($roots as $root) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if (! in_array($file->getExtension(), ['php', 'js', 'css'], true)) {
                    continue;
                }

                $code = file_get_contents($file->getPathname());
                // Things a page LOADS: script src, stylesheet/preload href, image or media src, iframes, @import, url(...). A plain link a person
                // clicks (an anchor) is not a load, and the SVG namespace is not a request.
                $patterns = [
                    '/<script[^>]+src=["\']https?:\/\/(?!localhost)/i',
                    '/<link[^>]+href=["\']https?:\/\/(?!localhost)/i',
                    '/<(img|source|video|audio|iframe|embed|object)[^>]+(src|data)=["\']https?:\/\/(?!localhost)/i',
                    '/@import\s+(url\()?["\']?https?:\/\//i',
                    '/url\(\s*["\']?https?:\/\/(?!localhost)/i',
                ];

                foreach ($patterns as $p) {
                    if (preg_match($p, $code, $hit)) {
                        $bad[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname()).' => '.trim($hit[0]);
                    }
                }

                foreach (['googletagmanager', 'google-analytics', 'gtag(', 'fbq(', 'facebook.net', 'hotjar', 'clarity.ms', 'doubleclick', 'adsbygoogle', 'fonts.googleapis', 'fonts.gstatic', 'cdnjs.cloudflare', 'cdn.jsdelivr', 'unpkg.com'] as $needle) {
                    if (stripos($code, $needle) !== false) {
                        $bad[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname())." mentions {$needle}";
                    }
                }
            }
        }

        $this->assertSame([], $bad, "A page now loads something from another company. Remove it, or change the Privacy and Cookie Policies first.\n".implode("\n", $bad));
    }

    public function test_the_fonts_are_served_from_this_site_and_the_browser_is_told_to_keep_it_that_way(): void
    {
        $css = file_get_contents(public_path('css/fonts.css'));
        $this->assertStringNotContainsString('http', $css, 'fonts.css must point at local files only');

        preg_match_all("/url\('\.\.\/fonts\/([^']+)'\)/", $css, $m);
        $this->assertNotEmpty($m[1]);
        foreach ($m[1] as $file) {
            $this->assertFileExists(public_path('fonts/'.$file));
        }

        $this->get('/')->assertHeader('Content-Security-Policy')->assertSee('css/fonts.css', false);
        $this->assertStringContainsString("font-src 'self'", $this->get('/')->headers->get('Content-Security-Policy'));
    }

    public function test_only_the_cookies_the_cookie_policy_lists_are_ever_set(): void
    {
        $listed = [config('session.cookie'), 'XSRF-TOKEN'];

        foreach (['/', '/login', '/learner/login', '/register/parent', '/register/teacher', '/privacy', '/terms'] as $path) {
            $names = array_map(fn ($c) => $c->getName(), $this->get($path)->headers->getCookies());

            $this->assertSame([], array_values(array_diff($names, $listed)), "{$path} sets a cookie the Cookie Policy does not list.");
        }

        $policy = $this->get('/cookies')->assertOk();
        foreach ($listed as $name) {
            $policy->assertSee($name, false);
        }
    }

    public function test_the_cookies_are_marked_the_way_the_cookie_policy_says(): void
    {
        // Left unset, the setting follows the connection: Secure on https (the live site), not on plain http.
        config(['session.secure' => null]);
        $cookies = collect($this->get('https://localhost/login')->headers->getCookies())->keyBy(fn ($c) => $c->getName());

        $session = $cookies[config('session.cookie')];
        $this->assertTrue($session->isHttpOnly(), 'the session cookie is hidden from page scripts');
        $this->assertTrue($session->isSecure(), 'the session cookie is Secure on https');
        $this->assertSame('lax', strtolower((string) $session->getSameSite()));
        $this->assertTrue($cookies['XSRF-TOKEN']->isSecure(), 'the XSRF-TOKEN cookie is Secure on https');
        $this->assertSame('lax', strtolower((string) $cookies['XSRF-TOKEN']->getSameSite()));

        // No "remember me": nothing on the sign in pages offers it, so no long lived cookie exists.
        foreach (['/login', '/register/parent'] as $path) {
            $this->get($path)->assertDontSee('name="remember"', false);
        }
    }

    public function test_there_is_no_payment_library_so_the_refund_policy_is_still_true(): void
    {
        $composer = strtolower(file_get_contents(base_path('composer.json')));

        foreach (['stripe', 'paypal', 'paymongo', 'xendit', 'gcash', 'paymaya', 'maya', 'braintree', 'mollie', 'cashier', 'dragonpay'] as $gateway) {
            $this->assertStringNotContainsString($gateway, $composer, "A payment package ({$gateway}) was added: replace the Refund Policy and the Terms first.");
        }

        $this->get('/refunds')->assertOk()->assertSee('no money is charged', false)->assertSee('simulated payment', false);
        $this->assertStringContainsString('simulated payment', strtolower(file_get_contents(resource_path('views/parent/repository.blade.php'))));
    }

    public function test_the_home_page_makes_no_claim_it_cannot_back_up(): void
    {
        // Claims removed in the 2026-10-21 review. They were stronger than what the app does: "checking every word as they go" (it scores
        // after the recording is sent), "every activity is checked by an actual teacher" (the first check and the games are written by the
        // team), an outcome promise, and a made up time to set up. No reviews, ratings or statistics are shown, and none may be added without a real source.
        $html = $this->get('/')->assertOk()->getContent();
        // Only what a visitor can read (not the style rules and comments in the page source).
        $text = html_entity_decode(strip_tags(preg_replace(['/<(style|script).*?<\/>/is', '/<!--.*?-->/s'], '', $html)));

        foreach (['AI-powered', 'AI powered', 'teacher-verified', 'teacher verified', 'checking every word', 'Backed by real teachers', 'in a few minutes', 'instead of getting stuck or bored', 'not dreading it',
            'testimonial', 'Trusted by', 'five-star', '5-star', 'rated', 'guarantee', 'guaranteed', 'proven', 'clinically', 'DepEd-approved', 'DepEd approved', 'certified'] as $phrase) {
            $this->assertSame(0, preg_match('/'.preg_quote($phrase, '/').'/i', $text), "The home page says \"{$phrase}\", which the app cannot back up.");
        }

        $this->assertStringContainsString('not made or endorsed by the Department of Education', $text);
    }

    public function test_the_pages_keep_the_house_copy_rules(): void
    {
        foreach (self::PAGES as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $text = html_entity_decode(strip_tags(preg_replace('/<(style|script)\b.*?<\/\1>/is', '', $html)));

            $this->assertStringNotContainsString("\u{2014}", $text, "{$path}: no em dash in the copy");
            $this->assertStringNotContainsString("\u{2013}", $text, "{$path}: no en dash in the copy");
            $this->assertSame(0, preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $text), "{$path}: no emoji in the copy");
        }
    }

    public function test_the_wake_up_requests_the_privacy_policy_admits_to_are_the_only_ones_to_other_hosts(): void
    {
        // The only request a page makes to another host is the bare health request in this partial; the Privacy Policy and the Cookie Policy
        // describe it. If another fetch to a different host appears, this fails.
        $partial = file_get_contents(resource_path('views/partials/wake-from-browser.blade.php'));
        $this->assertStringContainsString("credentials: 'omit'", $partial);
        $this->assertStringContainsString("referrerPolicy: 'no-referrer'", $partial);
        $this->get('/privacy')->assertSee('Wake up requests.', false);
    }

    public function test_the_consent_page_matches_the_boxes_and_the_records(): void
    {
        $page = $this->get('/parent-consent')->assertOk();

        foreach (['Required', 'Optional', 'separate tick box', 'The recording itself is not kept', 'Changing your mind', 'If you do not agree'] as $line) {
            $page->assertSee($line, false);
        }

        // The kinds of consent the page describes are the kinds the app records.
        $this->assertSame(['terms_privacy', 'child_data', 'child_photo', 'child_link'], [
            \App\Services\ConsentService::TERMS_PRIVACY, \App\Services\ConsentService::CHILD_DATA, \App\Services\ConsentService::CHILD_PHOTO, \App\Services\ConsentService::CHILD_LINK,
        ]);
    }
}
