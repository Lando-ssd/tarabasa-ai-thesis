<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * What a visitor sees when something goes wrong, and the headers every response carries.
 */
class ErrorPagesAndHeadersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Production hides the technical page; the friendly one is what people see.
        config(['app.debug' => false]);
    }

    private function user(string $type): User
    {
        $u = User::create(['first_name' => 'T', 'last_name' => 'U', 'email' => strtolower($type).'@example.com', 'password' => 'Passw0rd!', 'user_type' => $type]);
        $u->forceFill(['email_verified_at' => now()])->save();

        return $u;
    }

    public function test_a_missing_page_says_so_in_plain_words_and_offers_a_way_out(): void
    {
        $this->get('/no/such/page')->assertNotFound()
            ->assertSee('We could not find that page')->assertSee('Go to the home page')->assertSee('TaraBasa', false)
            ->assertDontSee('NotFoundHttpException');
    }

    public function test_the_wrong_kind_of_account_gets_a_clear_refusal_not_a_blank_error(): void
    {
        $this->actingAs($this->user('Parent'))->get(route('teacher.dashboard'))->assertForbidden()
            ->assertSee('This page is not for this account');
    }

    public function test_a_list_in_an_address_is_refused_politely(): void
    {
        $this->get('/login?role[]=x')->assertStatus(400)->assertSee('That address is not valid');
    }

    public function test_a_crash_shows_a_friendly_page_and_never_the_technical_message(): void
    {
        Route::get('/_test/boom', fn () => throw new \RuntimeException('SECRET internal detail: db password is hunter2'));

        $res = $this->get('/_test/boom')->assertStatus(500);
        $res->assertSee('Something went wrong on our side')->assertDontSee('hunter2')->assertDontSee('RuntimeException');
    }

    public function test_the_page_timed_out_and_too_many_tries_pages_exist(): void
    {
        foreach ([419 => 'This page timed out', 429 => 'Too many tries', 503 => 'We are getting things ready'] as $status => $words) {
            $html = view("errors.{$status}")->render();
            $this->assertStringContainsString($words, $html);
        }

        Route::get('/_test/slow', fn () => abort(429))->name('slow');
        $this->get('/_test/slow')->assertStatus(429)->assertSee('Too many tries');
    }

    // ------------------------------------------------------------- headers

    public function test_every_response_carries_the_protective_headers(): void
    {
        $res = $this->get('/login');
        $res->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('microphone=(self)', $res->headers->get('Permissions-Policy'));
        // The two login pages may use the camera (a child's "Scan my card"); a page with no scanner may not.
        $this->assertStringContainsString('camera=(self)', $res->headers->get('Permissions-Policy'));
        $this->assertStringContainsString('camera=()', $this->get('/')->headers->get('Permissions-Policy'));
        $this->assertStringContainsString("frame-ancestors 'self'", $res->headers->get('Content-Security-Policy'));
        $this->assertNull($res->headers->get('Strict-Transport-Security'), 'HSTS only over https');

        $this->get('/no/such/page')->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $this->getJson('/api/learner/dashboard')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_https_gets_hsts_and_a_secure_session_cookie_but_http_does_not(): void
    {
        $secure = $this->get('https://localhost/login');
        $this->assertStringContainsString('max-age=', $secure->headers->get('Strict-Transport-Security'));
        $cookie = collect($secure->headers->getCookies())->first(fn ($c) => str_contains($c->getName(), 'session'));
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isSecure(), 'the session cookie must be Secure over https');
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());

        config(['session.secure' => null]);
        $plain = $this->get('http://localhost/login');
        $plainCookie = collect($plain->headers->getCookies())->first(fn ($c) => str_contains($c->getName(), 'session'));
        $this->assertFalse($plainCookie->isSecure(), 'over plain http (a local run) the cookie must still work');
    }

    public function test_a_signed_in_page_is_never_stored_for_the_back_button(): void
    {
        // A page for someone who is not signed in is not forced to no-store.
        $this->assertStringNotContainsString('no-store', (string) $this->get('/login')->headers->get('Cache-Control'));
        $res = $this->actingAs($this->user('Parent'))->get(route('parent.dashboard'))->assertOk();
        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));
    }
}
