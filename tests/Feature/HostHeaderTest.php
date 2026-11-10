<?php

namespace Tests\Feature;

use App\Models\ParentAccount;
use App\Models\User;
use App\Support\PublicUrl;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * A stranger must not be able to choose the address inside our emails.
 *
 * Found on 2026-11-09 by a read-only request to the live site: a forged "X-Forwarded-Host" header made every link on a page
 * point at the forged host. The password reset and email confirmation messages are built from the same address, so someone could
 * ask for a reset for another person's account and the real email would carry a link (with the secret token) to the stranger's site.
 */
class HostHeaderTest extends TestCase
{
    use RefreshDatabase;

    private const EVIL = 'forged.example.invalid';

    protected function tearDown(): void
    {
        PublicUrl::reset();

        parent::tearDown();
    }

    private function parentAccount(bool $confirmed): User
    {
        $u = User::create(['first_name' => 'Pat', 'last_name' => 'Cruz', 'email' => 'pat@example.com', 'password' => 'Passw0rd!', 'user_type' => 'Parent']);
        ParentAccount::create(['user_id' => $u->id]);
        if ($confirmed) {
            $u->forceFill(['email_verified_at' => now()])->save();
        }

        return $u;
    }

    public function test_a_forged_forwarded_host_does_not_change_the_links_on_a_page(): void
    {
        $page = $this->withHeader('X-Forwarded-Host', self::EVIL)->get('/login')->assertOk()->getContent();

        $this->assertStringNotContainsString(self::EVIL, $page, 'the page must not use a host the visitor made up');
        $this->assertStringContainsString('http://localhost', $page);
    }

    public function test_the_password_reset_link_in_the_email_points_at_our_own_address_even_with_a_forged_header(): void
    {
        Notification::fake();
        $user = $this->parentAccount(true);

        $this->withHeader('X-Forwarded-Host', self::EVIL)->post('/forgot-password', ['email' => $user->email])->assertRedirect();

        Notification::assertSentTo($user, ResetPassword::class);
        $url = (string) Notification::sent($user, ResetPassword::class)->first()->toMail($user)->actionUrl;

        $this->assertStringNotContainsString(self::EVIL, $url, 'the emailed reset link must not lead to a host the requester made up');
        $this->assertStringStartsWith(rtrim((string) config('app.url'), '/').'/reset-password/', $url);
    }

    public function test_the_confirmation_link_in_the_email_points_at_our_own_address_even_with_a_forged_header(): void
    {
        Notification::fake();
        $user = $this->parentAccount(false);

        $this->withHeader('X-Forwarded-Host', self::EVIL)->post('/email/resend', ['email' => $user->email]);

        Notification::assertSentTo($user, VerifyEmail::class);
        $url = (string) Notification::sent($user, VerifyEmail::class)->first()->toMail($user)->actionUrl;

        $this->assertStringNotContainsString(self::EVIL, $url, 'the emailed confirmation link must not lead to a host the requester made up');
        $this->assertStringStartsWith(rtrim((string) config('app.url'), '/').'/email/verify/', $url);
    }

    public function test_the_public_address_is_pinned_when_it_is_an_https_address(): void
    {
        $this->assertTrue(PublicUrl::pin('https://tarabasa.example.test/'));

        $this->assertSame('https://tarabasa.example.test/privacy', url('/privacy'));
        $this->assertStringStartsWith('https://tarabasa.example.test/', route('login'));
        $this->assertStringStartsWith('https://tarabasa.example.test/', URL::temporarySignedRoute('verification.verify', now()->addMinutes(5), ['id' => 1, 'hash' => 'x']));
    }

    public function test_the_address_is_left_alone_when_the_setting_is_missing_plain_http_or_not_an_address(): void
    {
        foreach ([null, '', 'http://localhost:8000', 'localhost', 'https://', 'javascript:alert(1)'] as $bad) {
            $this->assertFalse(PublicUrl::pin($bad), 'must not pin: '.var_export($bad, true));
        }

        $this->assertNull(PublicUrl::host());
        $this->assertStringStartsWith('http://localhost', url('/'), 'nothing was pinned');
    }

    /** On the live site (an https APP_URL) a forged Host or X-Forwarded-Host changes nothing: the page, its links and the emails use our address. */
    public function test_with_the_live_setting_a_forged_host_or_forwarded_host_is_ignored_everywhere(): void
    {
        PublicUrl::pin('https://tarabasa.example.test');

        $forwarded = $this->withHeader('X-Forwarded-Host', self::EVIL)->get('https://tarabasa.example.test/login')->assertOk()->getContent();
        $this->assertStringNotContainsString(self::EVIL, $forwarded);
        $this->assertStringContainsString('https://tarabasa.example.test/privacy', $forwarded);

        $forgedHost = $this->get('https://'.self::EVIL.'/login')->assertOk()->getContent();
        $this->assertStringNotContainsString(self::EVIL, $forgedHost, 'even a forged Host header must not reach the page');
        $this->assertStringContainsString('https://tarabasa.example.test/privacy', $forgedHost);
    }

    /** A signed confirmation link must stay valid when it is opened through a proxy that shows a different host to the app. */
    public function test_a_signed_link_stays_valid_whatever_host_the_proxy_shows_to_the_app(): void
    {
        PublicUrl::pin('https://tarabasa.example.test');
        $user = $this->parentAccount(false);
        $link = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]);
        $this->assertStringStartsWith('https://tarabasa.example.test/email/verify/', $link);

        // the app is shown some other host (an internal address), as a proxy might do
        $path = substr($link, strlen('https://tarabasa.example.test'));
        $this->get('https://internal.proxy.invalid'.$path)->assertOk()->assertSee('Email confirmed');

        $this->assertNotNull($user->fresh()->email_verified_at);
    }
}
