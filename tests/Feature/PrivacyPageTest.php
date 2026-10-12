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

    public function test_it_describes_the_phone_app(): void
    {
        $page = $this->get('/privacy')->assertOk();

        $page->assertSee('The phone app', false);
        $page->assertSee('Microphone:', false);
        $page->assertSee('Camera:', false);
        $page->assertSee('QR code on a learner card', false);
        $page->assertSee('removed as soon as our server has answered', false);
        $page->assertSee('advertising ID', false);
    }

    public function test_the_phone_app_asks_only_for_the_permissions_the_page_explains(): void
    {
        // The page explains the microphone and the camera. If another sensitive permission is ever added to the
        // phone app (location, contacts, storage...), this fails and the page must say what it is for first.
        $app = json_decode(file_get_contents(base_path('mobile/app.json')), true);
        $blocked = $app['expo']['android']['blockedPermissions'] ?? [];

        foreach (['SYSTEM_ALERT_WINDOW', 'USE_BIOMETRIC', 'USE_FINGERPRINT', 'READ_EXTERNAL_STORAGE', 'WRITE_EXTERNAL_STORAGE'] as $unused) {
            $this->assertContains("android.permission.$unused", $blocked, "$unused is not used by the phone app and must stay blocked.");
        }

        $plugins = json_encode($app['expo']['plugins']);
        $this->assertStringContainsString('microphonePermission', $plugins);
        $this->assertStringContainsString('cameraPermission', $plugins);
        $this->assertStringNotContainsString('locationPermission', $plugins);
        $this->assertStringNotContainsString('expo-location', $plugins);
        $this->assertStringNotContainsString('expo-contacts', $plugins);
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
