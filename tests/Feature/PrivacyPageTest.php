<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The privacy page is public (Google needs its address to publish the Gmail sender, and parents need
 * to read it before signing a child up). It must say only true things, so the claims that matter are
 * pinned here: if the app ever starts saving recordings or adds an outside service, these tests are the
 * reminder to change the page in the same commit.
 */
class PrivacyPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_privacy_page_opens_without_signing_in(): void
    {
        $this->get('/privacy')
            ->assertOk()
            ->assertSee('Privacy')
            ->assertSee('Last updated');
    }

    public function test_it_says_what_matters_to_a_parent(): void
    {
        $page = $this->get('/privacy')->assertOk();

        $page->assertSee('The app does not save the recording.', false);
        $page->assertSee('We do not sell information', false);
        $page->assertSee('Gmail', false);
        $page->assertSee('Google Fonts', false);
        $page->assertSee('A child cannot sign up alone.', false);
    }

    public function test_the_recording_claim_is_still_true_the_app_stores_no_audio(): void
    {
        // The only file the app writes with ->store() is the optional avatar photo. If a recording is
        // ever stored, this fails and the page must be changed first.
        $stored = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path())) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $code = file_get_contents($file->getPathname());
                if (preg_match_all("/->(store|storeAs|storePublicly)\\(\\s*'([^']+)'/", $code, $m)) {
                    foreach ($m[2] as $folder) {
                        $stored[] = $folder;
                    }
                }
            }
        }

        $this->assertSame(['avatars'], array_values(array_unique($stored)), 'The app now stores a new kind of file. Update the privacy page.');
    }

    public function test_the_contact_line_follows_the_setting(): void
    {
        config(['app.privacy_contact' => null]);
        $this->get('/privacy')->assertSee('tell the school administrator', false);

        config(['app.privacy_contact' => 'help@example.com']);
        $this->get('/privacy')->assertSee('mailto:help@example.com', false);
    }

    public function test_the_home_page_links_to_it(): void
    {
        $this->get('/')->assertOk()->assertSee(route('privacy'), false);
    }
}
