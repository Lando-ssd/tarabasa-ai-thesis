<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Learner;
use App\Models\OpenRepositoryListing;
use App\Models\ParentAccount;
use App\Models\Payment;
use App\Models\RepositoryUnlock;
use App\Models\Teacher;
use App\Models\User;
use App\Support\LearnerCode;
use App\Support\PaymongoSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Test mode payments for a paid activity (config/payments.php, App\Services\PaymentService), against a PRETEND provider (Http::fake): no key, no network.
 * They pin what the approved preview promised: the switch is off by default and then nothing changes; the provider is sent only the price, the activity
 * title and a reference; the unlock comes ONLY from a signed message (or from asking the provider), never from the page the parent comes back to; the
 * amount must match; a repeated message or a second press changes nothing; a live key and a live message are refused; and one parent never sees
 * another's payments.
 *
 * What these cannot show: the real provider's pages and messages. They were not readable in full (see docs/HISTORY.md, "Test payments"), so the first
 * real test with a test key is the proof.
 */
class PaymentsTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsk_test_secret_for_the_tests';

    private int $seq = 0;

    // What the pretend provider answers.
    private array $createAnswer = [];

    private int $createStatus = 200;

    private array $retrieveAnswer = [];

    private int $retrieveStatus = 200;

    private int $expireStatus = 200;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.enabled' => true,
            'payments.paymongo.secret_key' => 'sk_test_abc123',
            'payments.paymongo.webhook_secret' => self::SECRET,
            'payments.session_minutes' => 30,
        ]);

        $this->createAnswer = ['data' => ['id' => 'cs_test_1', 'attributes' => ['checkout_url' => 'https://checkout.example.test/cs_test_1']]];
        $this->retrieveAnswer = ['data' => ['id' => 'cs_test_1', 'attributes' => ['status' => 'active', 'payments' => []]]];

        Http::fake(function ($request) {
            if ($request->method() === 'POST' && str_ends_with($request->url(), '/expire')) {
                return Http::response([], $this->expireStatus);
            }
            if ($request->method() === 'POST') {
                return Http::response($this->createAnswer, $this->createStatus);
            }

            return Http::response($this->retrieveAnswer, $this->retrieveStatus);
        });
    }

    // ------------------------------------------------------------------------------------------------------------- a small world

    /** @return array{0: User, 1: ParentAccount, 2: Learner, 3: OpenRepositoryListing} */
    private function world(float $price = 50, string $title = 'The Market Day Story'): array
    {
        [$user, $parent] = $this->parent();
        $kid = $this->child($parent);

        return [$user, $parent, $kid, $this->listing($title, $price)];
    }

    /** @return array{0: User, 1: ParentAccount} */
    private function parent(): array
    {
        $n = ++$this->seq;
        $user = User::create(['first_name' => 'Carla', 'last_name' => "Domingo{$n}", 'email' => "pay{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Parent']);
        $user->forceFill(['email_verified_at' => now()])->save();

        return [$user, ParentAccount::create(['user_id' => $user->id])];
    }

    private function child(ParentAccount $parent, string $first = 'Mia'): Learner
    {
        $kid = Learner::create([
            'learner_code' => LearnerCode::generate(), 'first_name' => $first, 'last_name' => 'Cruz', 'grade_level' => 'Grade 1',
            'pin' => Hash::make('1234'), 'avatar_id' => 'A', 'mastery_level' => 'Beginning', 'reading_rung' => 2,
        ]);
        $parent->learners()->attach($kid->id, ['relationship' => 'Mother', 'is_creator' => true, 'linked_at' => now()]);

        return $kid;
    }

    private function listing(string $title, float $price): OpenRepositoryListing
    {
        $n = ++$this->seq;
        $teacherUser = User::create(['first_name' => 'Ana', 'last_name' => "Reyes{$n}", 'email' => "payt{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        $teacher = Teacher::create(['user_id' => $teacherUser->id, 'school_name' => 'Rizal ES', 'employee_id' => "PT{$n}", 'status' => 'Active', 'free_generation_credits_remaining' => 2, 'grades_handled' => ['Grade 1']]);
        $activity = Activity::create([
            'created_by_teacher_id' => $teacher->id, 'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'competency_label' => 'Foundational Reading',
            'activity_type' => 'word_reading', 'title' => $title, 'instructions' => 'Read.', 'passage_text' => 'cat dog pig', 'reference_text' => 'cat dog pig',
            'word_count' => 3, 'status' => 'Approved', 'difficulty_tier' => 'Easy',
        ]);

        return OpenRepositoryListing::create(['activity_id' => $activity->id, 'teacher_id' => $teacher->id, 'price_type' => $price > 0 ? 'Paid' : 'Free', 'price' => $price]);
    }

    private function asParent(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user);
    }

    private function pay(User $user, OpenRepositoryListing $listing, Learner $kid)
    {
        return $this->asParent($user)->post(route('parent.payments.start', $listing), ['learner_id' => $kid->id]);
    }

    /** A payment that has been started: the row exists with its checkout. */
    private function started(User $user, OpenRepositoryListing $listing, Learner $kid): Payment
    {
        $this->pay($user, $listing, $kid)->assertRedirect('https://checkout.example.test/cs_test_1');

        return Payment::firstOrFail();
    }

    // ------------------------------------------------------------------------------------------------------------- the provider's message

    /** The body of the message that says a checkout was paid, in either of the two shapes the provider's documentation shows. */
    private function paidBody(Payment $p, array $o = []): string
    {
        $o += ['amount' => $p->amount_centavos, 'live' => false, 'method' => 'gcash', 'type' => 'checkout_session.payment.paid', 'session' => $p->provider_session_id, 'reference' => $p->reference, 'shape' => 'a'];

        $session = ['id' => $o['session'], 'type' => 'checkout_session', 'attributes' => [
            'reference_number' => $o['reference'], 'status' => 'active',
            'payments' => [['id' => 'pay_1', 'type' => 'payment', 'attributes' => ['amount' => $o['amount'], 'currency' => 'PHP', 'status' => 'paid', 'source' => ['type' => $o['method']]]]],
        ]];

        $event = $o['shape'] === 'a'
            ? ['data' => ['id' => 'evt_1', 'type' => 'event', 'attributes' => ['type' => $o['type'], 'livemode' => $o['live'], 'data' => $session]]]
            : ['data' => ['type' => $o['type'], 'resource' => 'checkout_session', 'livemode' => $o['live'], 'data' => $session]];

        return (string) json_encode($event);
    }

    private function message(string $body, ?string $header = null, string $secret = self::SECRET)
    {
        $t = '1700000000';
        $header ??= 't='.$t.',te='.PaymongoSignature::sign($t, $body, $secret).',li=';

        return $this->call('POST', '/api/webhooks/paymongo', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_PAYMONGO_SIGNATURE' => $header, 'HTTP_ACCEPT' => 'application/json'], $body);
    }

    // ============================================================================================================= the switch

    public function test_the_switch_is_off_by_default(): void
    {
        $defaults = require base_path('config/payments.php');
        $this->assertFalse($defaults['enabled'], 'PAYMENTS_ENABLED must be false unless a person turns it on');
        $this->assertSame('PHP', $defaults['currency']);
    }

    public function test_with_the_switch_off_a_paid_unlock_is_the_old_simulated_step_and_nothing_leaves_the_site(): void
    {
        config(['payments.enabled' => false]);
        [$user, , $kid, $listing] = $this->world();

        $page = $this->asParent($user)->get(route('parent.repository.index', ['learner_id' => $kid->id]))->assertOk();
        $page->assertSee('This is a simulated payment, so nothing is charged.', false)->assertDontSee('Go to test payment', false)->assertDontSee('Test mode.', false);

        $this->asParent($user)->post(route('parent.repository.unlock', $listing), ['learner_id' => $kid->id])->assertRedirect();
        $this->assertSame(50.0, (float) RepositoryUnlock::where('learner_id', $kid->id)->value('amount_paid'));
        Http::assertNothingSent();
        $this->assertSame(0, Payment::count());
    }

    public function test_with_the_switch_on_a_paid_activity_cannot_be_unlocked_without_paying_on_the_website_or_the_phone(): void
    {
        [$user, , $kid, $listing] = $this->world();

        $this->asParent($user)->post(route('parent.repository.unlock', $listing), ['learner_id' => $kid->id])->assertSessionHasErrors('unlock');
        $this->assertSame(0, RepositoryUnlock::count());

        $token = $user->createToken('mobile')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $api = $this->withToken($token)->postJson("/api/parent/repository/{$listing->id}/unlock", ['learner_id' => $kid->id])->assertStatus(422);
        $this->assertSame('This is a paid activity. Paid activities are unlocked on the website, with a test payment.', $api->json('errors.unlock.0'));
        $this->assertSame(0, RepositoryUnlock::count());

        // A free activity is unchanged.
        $free = $this->listing('A free one', 0);
        $this->asParent($user)->post(route('parent.repository.unlock', $free), ['learner_id' => $kid->id])->assertRedirect();
        $this->assertSame(1, RepositoryUnlock::count());
    }

    public function test_the_page_says_test_mode_and_offers_the_test_payment_step(): void
    {
        [$user, , $kid] = $this->world();

        $page = $this->asParent($user)->get(route('parent.repository.index', ['learner_id' => $kid->id]))->assertOk();
        $page->assertSee('Test mode. Payments here use pretend money. Nothing real is charged.', false)
            ->assertSee('Unlock for &#8369;50 (test)', false)
            ->assertSee('Go to test payment', false)
            ->assertSee("We do not send Mia's name, your name or your email.", false)
            ->assertDontSee('simulated payment', false);
    }

    public function test_a_switch_that_is_on_without_a_key_says_so_and_pays_nothing(): void
    {
        config(['payments.paymongo.secret_key' => null]);
        [$user, , $kid, $listing] = $this->world();

        $this->asParent($user)->get(route('parent.repository.index', ['learner_id' => $kid->id]))->assertOk()->assertSee('switched on but not set up yet', false);
        $this->pay($user, $listing, $kid)->assertSessionHasErrors('unlock');
        Http::assertNothingSent();
        $this->assertSame(0, Payment::count());
    }

    public function test_a_live_key_is_never_used(): void
    {
        config(['payments.paymongo.secret_key' => 'sk_live_abc123']);
        [$user, , $kid, $listing] = $this->world();

        $this->pay($user, $listing, $kid)->assertSessionHasErrors('unlock');
        Http::assertNothingSent();
        $this->assertSame(0, Payment::count());
    }

    // ============================================================================================================= starting a payment

    public function test_pressing_go_to_test_payment_sends_the_provider_only_the_price_the_title_and_a_reference(): void
    {
        [$user, , $kid, $listing] = $this->world(50, 'The Market Day Story');

        $payment = $this->started($user, $listing, $kid);

        $this->assertSame(['pending', 5000, 'PHP', true, 'cs_test_1'], [$payment->status, $payment->amount_centavos, $payment->currency, $payment->is_test, $payment->provider_session_id]);
        $this->assertStringStartsWith('TB-T-', $payment->reference);
        $this->assertTrue($payment->expires_at->isFuture());

        $sent = null;
        Http::assertSent(function ($request) use (&$sent) {
            $sent = $request;

            return true;
        });
        $this->assertSame('https://api.paymongo.com/v1/checkout_sessions', $sent->url());
        $this->assertTrue($sent->hasHeader('Authorization', 'Basic '.base64_encode('sk_test_abc123:')), 'basic authentication with the key as the user name and no password');

        $attributes = $sent->data()['data']['attributes'];
        $this->assertSame([['name' => 'The Market Day Story', 'amount' => 5000, 'currency' => 'PHP', 'quantity' => 1]], $attributes['line_items']);
        $this->assertSame($payment->reference, $attributes['reference_number']);
        $this->assertSame(route('parent.payments.back', $payment->reference), $attributes['success_url']);
        $this->assertSame(route('parent.payments.cancelled', $payment->reference), $attributes['cancel_url']);
        $this->assertSame(['card', 'gcash', 'paymaya'], $attributes['payment_method_types'], 'QR Ph is left out on purpose: its test codes are real');

        // Nothing about the people: no names, no email, no learner code, no ids of ours.
        $body = (string) json_encode($sent->data());
        foreach (['Mia', 'Cruz', 'Carla', 'Domingo', 'example.com', $kid->learner_code] as $private) {
            $this->assertStringNotContainsString($private, $body, "{$private} must not be sent to the provider");
        }
        $this->assertArrayNotHasKey('metadata', $attributes);
        $this->assertArrayNotHasKey('billing', $attributes);
    }

    public function test_the_same_press_twice_uses_the_same_open_checkout(): void
    {
        [$user, , $kid, $listing] = $this->world();

        $this->started($user, $listing, $kid);
        $this->pay($user, $listing, $kid)->assertRedirect('https://checkout.example.test/cs_test_1');

        $this->assertSame(1, Payment::count());
        Http::assertSentCount(1);
    }

    public function test_a_free_activity_an_unlocked_one_and_a_child_that_is_not_theirs_cannot_be_paid_for(): void
    {
        [$user, $parent, $kid, $listing] = $this->world();

        $free = $this->listing('Free one', 0);
        $this->pay($user, $free, $kid)->assertSessionHasErrors('unlock');

        $other = $this->child($this->parent()[1], 'Leo');
        $this->pay($user, $listing, $other)->assertForbidden();

        RepositoryUnlock::create(['listing_id' => $listing->id, 'parent_id' => $parent->id, 'learner_id' => $kid->id, 'amount_paid' => 50]);
        $this->pay($user, $listing, $kid)->assertSessionHasErrors('unlock');

        $this->assertSame(0, Payment::count());
        Http::assertNothingSent();
    }

    public function test_a_provider_that_refuses_or_cannot_be_reached_leaves_nothing_unlocked_and_says_so(): void
    {
        [$user, , $kid, $listing] = $this->world();

        $this->createStatus = 400;
        $this->createAnswer = ['errors' => [['code' => 'parameter_invalid', 'detail' => 'The amount is below what is accepted.']]];
        $this->pay($user, $listing, $kid)->assertSessionHasErrors(['unlock' => 'The test payment could not be started right now. Nothing was charged. Please try again in a moment.']);

        $payment = Payment::firstOrFail();
        $this->assertSame('failed', $payment->status);
        $this->assertStringContainsString('The amount is below what is accepted.', (string) $payment->failure_reason, 'the provider\'s own words are kept for the team');
        $this->assertSame(0, RepositoryUnlock::count());

        // Unreachable.
        Http::fake(fn () => throw new ConnectionException('down'));
        $this->pay($user, $listing, $kid)->assertSessionHasErrors('unlock');
        $this->assertSame(2, Payment::count());
        $this->assertSame(0, RepositoryUnlock::count());
    }

    public function test_a_checkout_id_that_is_already_in_use_is_refused_with_a_message_not_a_crash(): void
    {
        [$user, , $kid, $listing] = $this->world();
        $this->started($user, $listing, $kid);
        $other = $this->listing('Another paid one', 40);

        // The pretend provider answers with the same checkout id again. The real one never does.
        $this->pay($user, $other, $kid)->assertSessionHasErrors('unlock');

        $second = Payment::latest('id')->firstOrFail();
        $this->assertSame('failed', $second->status);
        $this->assertNull($second->provider_session_id);
        $this->assertSame(0, RepositoryUnlock::count());
    }

    // ============================================================================================================= the signed message

    public function test_a_signed_paid_message_unlocks_the_activity_once_with_the_amount_and_the_way_paid(): void
    {
        [$user, $parent, $kid, $listing] = $this->world();
        $payment = $this->started($user, $listing, $kid);

        $this->message($this->paidBody($payment))->assertOk()->assertJson(['received' => true, 'outcome' => 'paid']);

        $payment->refresh();
        $this->assertSame(['paid', 'gcash', 'pay_1'], [$payment->status, $payment->method, $payment->provider_payment_id]);
        $this->assertNotNull($payment->paid_at);
        $unlock = RepositoryUnlock::firstOrFail();
        $this->assertSame([$listing->id, $parent->id, $kid->id, 50.0], [$unlock->listing_id, $unlock->parent_id, $unlock->learner_id, (float) $unlock->amount_paid]);

        // The same message again, or the provider trying again: nothing changes.
        $this->message($this->paidBody($payment))->assertOk();
        $this->assertSame(1, RepositoryUnlock::count());
    }

    public function test_the_other_shape_of_the_message_in_the_providers_documentation_works_too(): void
    {
        [$user, , $kid, $listing] = $this->world();
        $payment = $this->started($user, $listing, $kid);

        $this->message($this->paidBody($payment, ['shape' => 'b', 'method' => 'paymaya']))->assertOk()->assertJson(['outcome' => 'paid']);

        $this->assertSame('maya', $payment->refresh()->method);
        $this->assertSame(1, RepositoryUnlock::count());
    }

    public function test_a_message_with_a_wrong_or_missing_signature_changes_nothing(): void
    {
        [$user, , $kid, $listing] = $this->world();
        $payment = $this->started($user, $listing, $kid);
        $body = $this->paidBody($payment);

        $this->message($body, null, 'whsk_some_other_secret')->assertStatus(401)->assertJson(['outcome' => 'bad_signature']);
        $this->message($body, 't=1700000000,te=,li=')->assertStatus(401);
        $this->message($body, 'nonsense')->assertStatus(401);
        // A body changed after it was signed.
        $t = '1700000000';
        $this->message(str_replace('5000', '1', $body), 't='.$t.',te='.PaymongoSignature::sign($t, $body, self::SECRET).',li=')->assertStatus(401);
        // The LIVE signature field is never accepted in this version.
        $this->message($body, 't='.$t.',te=,li='.PaymongoSignature::sign($t, $body, self::SECRET))->assertStatus(401);

        $this->assertSame('pending', $payment->refresh()->status);
        $this->assertSame(0, RepositoryUnlock::count());
    }

    public function test_a_message_is_not_answered_when_the_switch_is_off_or_the_secret_is_missing(): void
    {
        [$user, , $kid, $listing] = $this->world();
        $payment = $this->started($user, $listing, $kid);

        config(['payments.paymongo.webhook_secret' => null]);
        $this->message($this->paidBody($payment))->assertStatus(503);

        config(['payments.paymongo.webhook_secret' => self::SECRET, 'payments.enabled' => false]);
        $this->message($this->paidBody($payment))->assertStatus(503);

        $this->assertSame(0, RepositoryUnlock::count());
    }

    public function test_the_amount_must_be_exactly_what_was_asked_for(): void
    {
        [$user, , $kid, $listing] = $this->world();
        $payment = $this->started($user, $listing, $kid);

        $this->message($this->paidBody($payment, ['amount' => 100]))->assertOk()->assertJson(['outcome' => 'amount_mismatch']);

        $this->assertSame('pending', $payment->refresh()->status);
        $this->assertSame(0, RepositoryUnlock::count());
    }

    public function test_a_live_mode_message_and_other_events_and_strangers_are_ignored(): void
    {
        [$user, , $kid, $listing] = $this->world();
        $payment = $this->started($user, $listing, $kid);

        $this->message($this->paidBody($payment, ['live' => true]))->assertOk()->assertJson(['outcome' => 'live_mode_ignored']);
        $this->message($this->paidBody($payment, ['type' => 'payment.failed']))->assertOk()->assertJson(['outcome' => 'ignored']);
        $this->message($this->paidBody($payment, ['reference' => 'TB-T-NOSUCH', 'session' => 'cs_other']))->assertOk()->assertJson(['outcome' => 'unknown_payment']);
        $this->message($this->paidBody($payment, ['session' => 'cs_a_different_checkout']))->assertOk()->assertJson(['outcome' => 'session_mismatch']);

        $this->assertSame('pending', $payment->refresh()->status);
        $this->assertSame(0, RepositoryUnlock::count());
    }

    public function test_a_payment_that_was_cancelled_or_expired_but_really_paid_is_unlocked(): void
    {
        [$user, , $kid, $listing] = $this->world();
        $payment = $this->started($user, $listing, $kid);
        $payment->update(['status' => 'expired']);

        $this->message($this->paidBody($payment))->assertOk()->assertJson(['outcome' => 'paid']);

        $this->assertSame('paid', $payment->refresh()->status);
        $this->assertSame(1, RepositoryUnlock::count());
    }

    // ============================================================================================================= the page the parent comes back to

    public function test_coming_back_does_not_unlock_anything_by_itself(): void
    {
        [$user, , $kid, $listing] = $this->world();
        $payment = $this->started($user, $listing, $kid);

        // The provider says nothing has been paid.
        $page = $this->asParent($user)->get(route('parent.payments.back', $payment->reference))->assertOk();
        $page->assertSee('Checking your payment', false)->assertSee('Nothing is unlocked yet.', false)->assertDontSee('Unlocked for Mia', false);

        $this->assertSame('pending', $payment->refresh()->status);
        $this->assertSame(0, RepositoryUnlock::count());
    }

    public function test_coming_back_asks_the_provider_and_shows_unlocked_only_if_it_was_paid(): void
    {
        [$user, , $kid, $listing] = $this->world();
        $payment = $this->started($user, $listing, $kid);

        $this->retrieveAnswer = ['data' => ['id' => 'cs_test_1', 'attributes' => ['status' => 'active', 'payments' => [
            ['id' => 'pay_9', 'type' => 'payment', 'attributes' => ['amount' => 5000, 'currency' => 'PHP', 'status' => 'paid', 'source' => ['type' => 'card']]],
        ]]]];

        $this->asParent($user)->get(route('parent.payments.back', $payment->reference))->assertOk()
            ->assertSee('Unlocked for Mia', false)->assertSee('Card', false)->assertSee($payment->reference, false);

        $this->assertSame(['paid', 'card'], [$payment->refresh()->status, $payment->method]);
        $this->assertSame(1, RepositoryUnlock::count());

        // A paid answer for the wrong amount unlocks nothing.
        Payment::query()->delete();
        RepositoryUnlock::query()->delete();
        $other = $this->started($user, $listing, $kid);
        $this->retrieveAnswer['data']['attributes']['payments'][0]['attributes']['amount'] = 1;
        $this->asParent($user)->get(route('parent.payments.back', $other->reference))->assertOk()->assertSee('Checking your payment', false);
        $this->assertSame(0, RepositoryUnlock::count());
    }

    public function test_a_provider_that_cannot_be_asked_leaves_the_payment_pending_without_a_crash(): void
    {
        [$user, , $kid, $listing] = $this->world();
        $payment = $this->started($user, $listing, $kid);
        $this->retrieveStatus = 404;

        $this->asParent($user)->get(route('parent.payments.back', $payment->reference))->assertOk()->assertSee('Checking your payment', false);
        $this->assertSame('pending', $payment->refresh()->status);
    }

    public function test_cancelling_closes_the_checkout_and_unlocks_nothing(): void
    {
        [$user, , $kid, $listing] = $this->world();
        $payment = $this->started($user, $listing, $kid);

        $this->asParent($user)->get(route('parent.payments.cancelled', $payment->reference))->assertOk()
            ->assertSee('You cancelled', false)->assertSee('Nothing was unlocked and nothing was charged.', false);

        $this->assertSame('cancelled', $payment->refresh()->status);
        $this->assertSame(0, RepositoryUnlock::count());
        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request->url() === 'https://api.paymongo.com/v1/checkout_sessions/cs_test_1/expire');
    }

    public function test_a_checkout_that_ran_out_of_time_is_closed_and_shown_as_expired(): void
    {
        [$user, , $kid, $listing] = $this->world();
        $payment = $this->started($user, $listing, $kid);
        $payment->update(['expires_at' => now()->subMinute()]);

        $this->asParent($user)->get(route('parent.payments.back', $payment->reference))->assertOk()
            ->assertSee('The payment request ran out of time', false);

        $this->assertSame('expired', $payment->refresh()->status);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/cs_test_1/expire'));
        $this->assertSame(0, RepositoryUnlock::count());
    }

    public function test_the_waiting_page_status_answers_pending_then_paid(): void
    {
        [$user, , $kid, $listing] = $this->world();
        $payment = $this->started($user, $listing, $kid);

        $this->asParent($user)->getJson(route('parent.payments.status', $payment->reference))->assertOk()->assertExactJson(['status' => 'pending']);

        $this->message($this->paidBody($payment))->assertOk();
        $this->asParent($user)->getJson(route('parent.payments.status', $payment->reference))->assertOk()->assertExactJson(['status' => 'paid']);
    }

    // ============================================================================================================= who may see what

    public function test_one_parent_never_sees_another_parents_payment(): void
    {
        [$user, , $kid, $listing] = $this->world();
        $payment = $this->started($user, $listing, $kid);
        [$stranger] = $this->parent();

        foreach (['back', 'cancelled', 'status'] as $route) {
            $this->asParent($stranger)->get(route("parent.payments.{$route}", $payment->reference))->assertNotFound();
        }
        $this->asParent($stranger)->get(route('parent.payments.index'))->assertOk()->assertSee('No payments yet', false)->assertDontSee($payment->reference, false);

        $this->asParent($user)->get(route('parent.payments.index'))->assertOk()->assertSee($payment->reference, false)->assertSee('The Market Day Story', false);

        // Not signed in, and not a parent.
        $this->app['auth']->forgetGuards();
        auth()->logout();
        $this->get(route('parent.payments.index'))->assertRedirect();
        $this->post(route('parent.payments.start', $listing), ['learner_id' => $kid->id])->assertRedirect();
    }

    public function test_the_history_lists_every_attempt_with_its_status(): void
    {
        [$user, , $kid, $listing] = $this->world();
        $first = $this->started($user, $listing, $kid);
        $this->asParent($user)->get(route('parent.payments.cancelled', $first->reference))->assertOk();
        // The provider makes a new checkout for the new payment (the pretend one has to say so too: a session id is unique).
        $this->createAnswer = ['data' => ['id' => 'cs_test_2', 'attributes' => ['checkout_url' => 'https://checkout.example.test/cs_test_2']]];
        $this->pay($user, $listing, $kid)->assertRedirect('https://checkout.example.test/cs_test_2');
        $second = Payment::latest('id')->firstOrFail();
        $this->assertNotSame($first->reference, $second->reference, 'after a cancel a new press makes a new payment');

        $page = $this->asParent($user)->get(route('parent.payments.index'))->assertOk();
        $page->assertSee($first->reference, false)->assertSee($second->reference, false)->assertSee('Cancelled', false)->assertSee('Pending', false)
            ->assertSee('&#8369;50.00 (test)', false);
    }

    public function test_the_new_pages_follow_the_copy_rules_no_emoji_and_no_dashes(): void
    {
        [$user, , $kid, $listing] = $this->world();
        $payment = $this->started($user, $listing, $kid);

        $pages = [
            $this->asParent($user)->get(route('parent.repository.index', ['learner_id' => $kid->id]))->getContent(),
            $this->asParent($user)->get(route('parent.payments.back', $payment->reference))->getContent(),
            $this->asParent($user)->get(route('parent.payments.index'))->getContent(),
            $this->asParent($user)->get(route('parent.payments.cancelled', $payment->reference))->getContent(),
        ];
        $this->retrieveStatus = 404;
        foreach ($pages as $html) {
            $text = strip_tags((string) preg_replace('/<(style|script)\b.*?<\/\1>/is', '', $html));
            $this->assertStringNotContainsString("\u{2014}", $text, 'no em dash in the copy');
            $this->assertStringNotContainsString("\u{2013}", $text, 'no en dash in the copy');
            $this->assertSame(0, preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $text), 'no emoji in the copy');
        }
    }

    public function test_the_signature_check_is_the_one_the_providers_page_describes(): void
    {
        $t = '1496734173';
        $body = '{"data":{"id":"evt_x"}}';
        $good = hash_hmac('sha256', $t.'.'.$body, 'whsk_abc');

        $this->assertSame(['t' => $t, 'te' => $good, 'li' => ''], PaymongoSignature::parts("t={$t},te={$good},li="));
        $this->assertTrue(PaymongoSignature::validTest($body, "t={$t},te={$good},li=", 'whsk_abc'));
        $this->assertFalse(PaymongoSignature::validTest($body, "t={$t},te={$good},li=", 'whsk_other'));
        $this->assertFalse(PaymongoSignature::validTest($body.' ', "t={$t},te={$good},li=", 'whsk_abc'), 'the raw body is signed, so one extra byte breaks it');
        $this->assertFalse(PaymongoSignature::validTest($body, null, 'whsk_abc'));
        $this->assertFalse(PaymongoSignature::validTest($body, "t={$t},te={$good},li=", ''));
    }
}
