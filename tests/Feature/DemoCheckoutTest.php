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
use App\Services\DataErasureService;
use App\Services\PaymentService;
use App\Support\LearnerCode;
use App\Support\PaymongoSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * TaraBasa's own DEMO checkout (PAYMENTS_MODE=demo, config/payments.php, the approved preview "A demo checkout inside TaraBasa"): the same Payment
 * record, the same unlock rule and the same history as the PayMongo test path, but on a page of TaraBasa itself, with pretend money, no account, no key
 * and nothing sent to anyone. These pin: the setting (off by default, a typo means off); that nothing leaves the site; that there is no input box for a
 * card or wallet number; that only an approved answer unlocks, once, with the amount checked; that a declined, cancelled or expired payment unlocks nothing;
 * and that one parent never reaches another's payment.
 */
class DemoCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config(['payments.mode' => 'demo', 'payments.enabled' => false, 'payments.paymongo.secret_key' => null, 'payments.paymongo.webhook_secret' => null]);
        Http::fake();
    }

    // ------------------------------------------------------------------------------------------------------------- a small world

    /** @return array{0: User, 1: ParentAccount, 2: Learner, 3: OpenRepositoryListing} */
    private function world(float $price = 50): array
    {
        [$user, $parent] = $this->parent();

        return [$user, $parent, $this->child($parent), $this->listing('The Market Day Story', $price)];
    }

    /** @return array{0: User, 1: ParentAccount} */
    private function parent(): array
    {
        $n = ++$this->seq;
        $user = User::create(['first_name' => 'Carla', 'last_name' => "Domingo{$n}", 'email' => "demo{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Parent']);
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
        $teacherUser = User::create(['first_name' => 'Ana', 'last_name' => "Reyes{$n}", 'email' => "demot{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        $teacher = Teacher::create(['user_id' => $teacherUser->id, 'school_name' => 'Rizal ES', 'employee_id' => "DT{$n}", 'status' => 'Active', 'free_generation_credits_remaining' => 2, 'grades_handled' => ['Grade 1']]);
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

    /** Press "Go to demo checkout": the record is made and the parent is sent to TaraBasa's own page. */
    private function started(User $user, OpenRepositoryListing $listing, Learner $kid): Payment
    {
        $this->asParent($user)->post(route('parent.payments.start', $listing), ['learner_id' => $kid->id])->assertRedirect();

        return Payment::latest('id')->firstOrFail();
    }

    private function answer(User $user, Payment $payment, string $method = 'gcash', string $outcome = 'approve')
    {
        return $this->asParent($user)->post(route('parent.payments.demo.pay', $payment->reference), ['method' => $method, 'outcome' => $outcome]);
    }

    // ============================================================================================================= the setting

    public function test_the_mode_is_off_unless_a_person_sets_it_and_a_typo_means_off(): void
    {
        $defaults = require base_path('config/payments.php');
        $this->assertArrayHasKey('mode', $defaults);
        $this->assertNull($defaults['mode'], 'PAYMENTS_MODE has no value unless a person sets one');

        foreach ([[null, false, 'off'], ['', false, 'off'], [null, true, 'paymongo'], ['demo', false, 'demo'], [' DEMO ', false, 'demo'], ['paymongo', false, 'paymongo'],
            ['live', true, 'off'], ['demoo', false, 'off'], ['off', true, 'off']] as [$mode, $enabled, $expected]) {
            config(['payments.mode' => $mode, 'payments.enabled' => $enabled]);
            $this->assertSame($expected, PaymentService::mode(), 'mode '.var_export($mode, true).' with the old switch '.var_export($enabled, true));
            $this->assertSame($expected !== 'off', PaymentService::enabled());
        }
    }

    public function test_the_demo_needs_no_key_and_paymongo_still_needs_a_test_key(): void
    {
        config(['payments.paymongo.secret_key' => null]);
        $this->assertTrue(PaymentService::ready(), 'the demo runs with no key at all');

        config(['payments.mode' => 'paymongo']);
        $this->assertFalse(PaymentService::ready());
        config(['payments.paymongo.secret_key' => 'sk_live_abc']);
        $this->assertFalse(PaymentService::ready(), 'a live key is never accepted');
        config(['payments.paymongo.secret_key' => 'sk_test_abc']);
        $this->assertTrue(PaymentService::ready());

        config(['payments.mode' => 'off']);
        $this->assertFalse(PaymentService::ready());
    }

    public function test_the_activities_page_offers_the_demo_checkout_and_says_where_it_goes(): void
    {
        [$user, , $kid] = $this->world();

        $page = $this->asParent($user)->get(route('parent.repository.index', ['learner_id' => $kid->id]))->assertOk();
        $page->assertSee('Demo mode. Payments here use pretend money.', false)
            ->assertSee('Unlock for &#8369;50 (demo)', false)
            ->assertSee('Go to demo checkout', false)
            ->assertSee('It runs inside TaraBasa', false)
            ->assertSee('nothing is sent to any other company', false)
            ->assertDontSee('Go to test payment', false)
            ->assertDontSee('payment provider', false);
    }

    // ============================================================================================================= starting

    public function test_pressing_go_to_demo_checkout_sends_nothing_to_anyone_and_opens_taraBasas_own_page(): void
    {
        [$user, $parent, $kid, $listing] = $this->world();

        $this->asParent($user)->post(route('parent.payments.start', $listing), ['learner_id' => $kid->id])->assertRedirect();

        $payment = Payment::firstOrFail();
        $this->assertSame('demo', $payment->provider);
        $this->assertSame('pending', $payment->status);
        $this->assertSame(5000, $payment->amount_centavos);
        $this->assertSame($parent->id, $payment->parent_id);
        $this->assertStringStartsWith('TB-D-', $payment->reference);
        $this->assertStringStartsWith('demo_', (string) $payment->provider_session_id);
        $this->assertSame(route('parent.payments.demo', $payment->reference), $payment->checkout_url);
        $this->assertTrue($payment->isDemo());
        Http::assertNothingSent();
    }

    public function test_pressing_twice_uses_the_same_open_demo_checkout(): void
    {
        [$user, , $kid, $listing] = $this->world();

        $first = $this->started($user, $listing, $kid);
        $second = $this->started($user, $listing, $kid);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Payment::count());
    }

    public function test_a_free_activity_and_a_child_that_is_not_theirs_cannot_be_paid_for_in_the_demo(): void
    {
        [$user, , $kid] = $this->world();

        $free = $this->listing('A free one', 0);
        $this->asParent($user)->post(route('parent.payments.start', $free), ['learner_id' => $kid->id])->assertSessionHasErrors('unlock');

        $other = $this->child($this->parent()[1], 'Leo');
        $this->asParent($user)->post(route('parent.payments.start', $this->listing('Another', 20)), ['learner_id' => $other->id])->assertForbidden();

        $this->assertSame(0, Payment::count());
    }

    public function test_a_paid_activity_cannot_be_unlocked_without_the_demo_checkout_on_the_website_or_the_phone(): void
    {
        [$user, , $kid, $listing] = $this->world();

        $this->asParent($user)->post(route('parent.repository.unlock', $listing), ['learner_id' => $kid->id])->assertSessionHasErrors('unlock');
        $this->assertSame(0, RepositoryUnlock::count());

        $token = $user->createToken('mobile')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $api = $this->withToken($token)->postJson("/api/parent/repository/{$listing->id}/unlock", ['learner_id' => $kid->id])->assertStatus(422);
        $this->assertSame('This is a paid activity. Paid activities are unlocked on the website, with a demo payment.', $api->json('errors.unlock.0'));
        $this->assertSame(0, RepositoryUnlock::count());
    }

    // ============================================================================================================= the pages

    public function test_the_checkout_page_shows_three_ways_to_pay_and_the_demo_picker_but_no_box_to_type_in(): void
    {
        [$user, , $kid, $listing] = $this->world();
        $payment = $this->started($user, $listing, $kid);

        $html = $this->asParent($user)->get(route('parent.payments.demo', $payment->reference))->assertOk()
            ->assertSee('TaraBasa demo checkout', false)->assertSee('Demo mode. Pretend money only.', false)
            ->assertSee('The Market Day Story', false)->assertSee('&#8369;50.00', false)->assertSee($payment->reference, false)
            ->assertSee('Card', false)->assertSee('GCash', false)->assertSee('Maya', false)
            ->assertSee('Demo only: what should happen?', false)->assertSee('Approved', false)->assertSee('Declined', false)->assertSee('Cancelled', false)
            ->assertSee('Cancel and go back', false)->getContent();

        $this->assertNoTypingBoxes($html);
        $this->assertDontSeeProvider($html);
    }

    public function test_the_pretend_step_for_a_card_is_read_only_and_for_a_wallet_is_one_approve_button(): void
    {
        [$user, , $kid, $listing] = $this->world();
        $payment = $this->started($user, $listing, $kid);

        $card = $this->asParent($user)->get(route('parent.payments.demo.step', ['reference' => $payment->reference, 'method' => 'card', 'outcome' => 'approve']))->assertOk()
            ->assertSee('Pay with Card', false)->assertSee('Pretend card number (read only)', false)->assertSee('This page cannot take a real card number.', false)
            ->assertSee('Pay &#8369;50.00 (pretend)', false)->getContent();
        $this->assertNoTypingBoxes($card);

        $wallet = $this->asParent($user)->get(route('parent.payments.demo.step', ['reference' => $payment->reference, 'method' => 'gcash', 'outcome' => 'decline']))->assertOk()
            ->assertSee('Pay with GCash', false)->assertSee('Pretend GCash number (read only)', false)->assertSee('no real wallet is touched', false)
            ->assertSee('Demo ending: <b>Declined</b>', false)->getContent();
        $this->assertNoTypingBoxes($wallet);

        $maya = $this->asParent($user)->get(route('parent.payments.demo.step', ['reference' => $payment->reference, 'method' => 'maya', 'outcome' => 'approve']))->assertOk()
            ->assertSee('Pay with Maya', false)->getContent();
        $this->assertDontSeeProvider($maya);
    }

    public function test_a_way_to_pay_or_an_ending_that_is_not_on_the_list_is_refused(): void
    {
        [$user, , $kid, $listing] = $this->world();
        $payment = $this->started($user, $listing, $kid);

        $this->asParent($user)->get(route('parent.payments.demo.step', ['reference' => $payment->reference, 'method' => 'bitcoin', 'outcome' => 'approve']))->assertSessionHasErrors('method');
        $this->asParent($user)->get(route('parent.payments.demo.step', ['reference' => $payment->reference, 'method' => 'card', 'outcome' => 'surprise']))->assertSessionHasErrors('outcome');
        $this->answer($user, $payment, 'bitcoin')->assertSessionHasErrors('method');
        $this->answer($user, $payment, 'card', 'surprise')->assertSessionHasErrors('outcome');
        $this->answer($user, $payment, '', 'approve')->assertSessionHasErrors('method');

        $this->assertSame('pending', $payment->refresh()->status);
        $this->assertSame(0, RepositoryUnlock::count());
    }

    // ============================================================================================================= the endings

    public function test_an_approved_demo_payment_unlocks_the_activity_once_and_says_so(): void
    {
        [$user, $parent, $kid, $listing] = $this->world();
        $payment = $this->started($user, $listing, $kid);

        $this->assertSame(0, RepositoryUnlock::count(), 'going to the checkout unlocks nothing');
        $this->answer($user, $payment, 'gcash')->assertRedirect(route('parent.payments.back', $payment->reference));

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertSame('gcash', $payment->method);
        $this->assertNotNull($payment->paid_at);
        $unlock = RepositoryUnlock::firstOrFail();
        $this->assertSame([$listing->id, $parent->id, $kid->id], [$unlock->listing_id, $unlock->parent_id, $unlock->learner_id]);
        $this->assertSame(50.0, (float) $unlock->amount_paid);

        $this->asParent($user)->get(route('parent.payments.back', $payment->reference))->assertOk()
            ->assertSee('Unlocked for Mia', false)->assertSee('This was a demo payment, so no real money was charged.', false)
            ->assertSee('GCash', false)->assertSee('&#8369;50.00 (demo)', false)->assertSee('Demo mode. Pretend money only.', false)
            ->assertDontSee('Checking your payment', false);

        // The checkout page of a payment that is over goes to the result, and a second press changes nothing.
        $this->asParent($user)->get(route('parent.payments.demo', $payment->reference))->assertRedirect(route('parent.payments.back', $payment->reference));
        $this->answer($user, $payment, 'card')->assertRedirect();
        $this->assertSame(1, RepositoryUnlock::count());
        $this->assertSame('gcash', $payment->refresh()->method, 'the first answer stands');
        Http::assertNothingSent();
    }

    public function test_a_declined_demo_payment_unlocks_nothing_and_offers_to_try_again(): void
    {
        [$user, , $kid, $listing] = $this->world();
        $payment = $this->started($user, $listing, $kid);

        $this->answer($user, $payment, 'card', 'decline')->assertRedirect();

        $payment->refresh();
        $this->assertSame('failed', $payment->status);
        $this->assertSame('declined', $payment->failure_reason);
        $this->assertTrue($payment->isDeclined());
        $this->assertSame('Declined', $payment->statusLabel());
        $this->assertSame(0, RepositoryUnlock::count());

        $this->asParent($user)->get(route('parent.payments.back', $payment->reference))->assertOk()
            ->assertSee('The payment was declined', false)->assertSee('Nothing was unlocked and nothing was charged. You can try again.', false)
            ->assertSee('Try again', false)->assertSee('Declined', false);

        // Trying again makes a new payment with a new reference, and that one can go through.
        $again = $this->started($user, $listing, $kid);
        $this->assertNotSame($payment->reference, $again->reference);
        $this->answer($user, $again, 'maya')->assertRedirect();
        $this->assertSame('paid', $again->refresh()->status);
        $this->assertSame(1, RepositoryUnlock::count());
    }

    public function test_cancelling_in_the_demo_unlocks_nothing_by_the_picker_and_by_the_cancel_link(): void
    {
        [$user, , $kid, $listing] = $this->world();

        $first = $this->started($user, $listing, $kid);
        $this->answer($user, $first, 'gcash', 'cancel')->assertRedirect();
        $this->assertSame('cancelled', $first->refresh()->status);

        $second = $this->started($user, $listing, $kid);
        $this->asParent($user)->get(route('parent.payments.cancelled', $second->reference))->assertOk()->assertSee('You cancelled', false);
        $this->assertSame('cancelled', $second->refresh()->status);

        $this->assertSame(0, RepositoryUnlock::count());
        Http::assertNothingSent();
    }

    public function test_a_demo_checkout_that_ran_out_of_time_cannot_be_approved(): void
    {
        [$user, , $kid, $listing] = $this->world();
        $payment = $this->started($user, $listing, $kid);
        $payment->update(['expires_at' => now()->subMinute()]);

        $this->answer($user, $payment, 'gcash')->assertRedirect(route('parent.payments.back', $payment->reference));

        $this->assertSame('expired', $payment->refresh()->status);
        $this->assertSame(0, RepositoryUnlock::count());
        $this->asParent($user)->get(route('parent.payments.back', $payment->reference))->assertOk()->assertSee('The payment request ran out of time', false);
        Http::assertNothingSent();
    }

    public function test_coming_back_to_an_unfinished_demo_payment_says_so_and_does_not_unlock_or_wait_on_anyone(): void
    {
        [$user, , $kid, $listing] = $this->world();
        $payment = $this->started($user, $listing, $kid);

        $html = $this->asParent($user)->get(route('parent.payments.back', $payment->reference))->assertOk()
            ->assertSee('Your demo payment is not finished', false)->assertSee('Continue to demo checkout', false)
            ->assertDontSee('Checking your payment', false)->assertDontSee('payment provider', false)->getContent();

        $this->assertStringNotContainsString('payments.status', $html);
        $this->assertStringNotContainsString('/status', $html, 'no quiet checking: there is nobody to ask');
        $this->assertSame('pending', $payment->refresh()->status);
        $this->assertSame(0, RepositoryUnlock::count());
        Http::assertNothingSent();
    }

    public function test_the_history_lists_demo_attempts_with_their_status_and_the_word_demo(): void
    {
        [$user, , $kid, $listing] = $this->world();

        $declined = $this->started($user, $listing, $kid);
        $this->answer($user, $declined, 'card', 'decline');
        $paid = $this->started($user, $listing, $kid);
        $this->answer($user, $paid, 'gcash');

        $this->asParent($user)->get(route('parent.payments.index'))->assertOk()
            ->assertSee($declined->reference, false)->assertSee($paid->reference, false)
            ->assertSee('Declined', false)->assertSee('Paid', false)->assertSee('&#8369;50.00 (demo)', false)
            ->assertSee('Demo payments for shared activities', false);
    }

    // ============================================================================================================= who may reach what

    public function test_one_parent_never_reaches_another_parents_demo_payment(): void
    {
        [$user, , $kid, $listing] = $this->world();
        $payment = $this->started($user, $listing, $kid);
        [$stranger] = $this->parent();

        $this->asParent($stranger)->get(route('parent.payments.demo', $payment->reference))->assertNotFound();
        $this->asParent($stranger)->get(route('parent.payments.demo.step', ['reference' => $payment->reference, 'method' => 'card', 'outcome' => 'approve']))->assertNotFound();
        $this->answer($stranger, $payment)->assertNotFound();

        $this->assertSame('pending', $payment->refresh()->status);
        $this->assertSame(0, RepositoryUnlock::count());

        // Not signed in.
        $this->app['auth']->forgetGuards();
        auth()->logout();
        $this->get(route('parent.payments.demo', $payment->reference))->assertRedirect();
        $this->post(route('parent.payments.demo.pay', $payment->reference), ['method' => 'card', 'outcome' => 'approve'])->assertRedirect();
        $this->assertSame(0, RepositoryUnlock::count());
    }

    public function test_the_demo_pages_are_not_found_unless_the_demo_is_the_mode_that_runs(): void
    {
        [$user, , $kid, $listing] = $this->world();
        $payment = $this->started($user, $listing, $kid);

        foreach (['off', 'paymongo'] as $mode) {
            config(['payments.mode' => $mode, 'payments.paymongo.secret_key' => 'sk_test_abc']);
            $this->asParent($user)->get(route('parent.payments.demo', $payment->reference))->assertNotFound();
            $this->answer($user, $payment)->assertNotFound();
        }

        $this->assertSame('pending', $payment->refresh()->status);
        $this->assertSame(0, RepositoryUnlock::count());
    }

    public function test_a_paymongo_payment_cannot_be_approved_on_the_demo_page_even_while_the_demo_runs(): void
    {
        [$user, $parent, $kid, $listing] = $this->world();
        $payment = Payment::create([
            'reference' => 'TB-T-REALISH1', 'parent_id' => $parent->id, 'learner_id' => $kid->id, 'listing_id' => $listing->id, 'provider' => 'paymongo',
            'provider_session_id' => 'cs_test_1', 'checkout_url' => 'https://checkout.example.test/cs_test_1', 'amount_centavos' => 5000, 'currency' => 'PHP',
            'status' => Payment::PENDING, 'is_test' => true, 'expires_at' => now()->addMinutes(30),
        ]);

        $this->asParent($user)->get(route('parent.payments.demo', $payment->reference))->assertNotFound();
        $this->answer($user, $payment)->assertNotFound();
        $this->assertSame('pending', $payment->refresh()->status);
        $this->assertSame(0, RepositoryUnlock::count());
    }

    public function test_a_signed_message_from_outside_cannot_decide_a_demo_payment(): void
    {
        [$user, , $kid, $listing] = $this->world();
        $payment = $this->started($user, $listing, $kid);
        config(['payments.paymongo.webhook_secret' => 'whsk_test_secret_for_the_tests']);

        $body = (string) json_encode(['data' => ['id' => 'evt_1', 'type' => 'event', 'attributes' => ['type' => 'checkout_session.payment.paid', 'livemode' => false, 'data' => [
            'id' => $payment->provider_session_id, 'type' => 'checkout_session', 'attributes' => ['reference_number' => $payment->reference, 'status' => 'active', 'payments' => [
                ['id' => 'pay_1', 'type' => 'payment', 'attributes' => ['amount' => 5000, 'currency' => 'PHP', 'status' => 'paid', 'source' => ['type' => 'gcash']]],
            ]],
        ]]]]);
        $t = '1700000000';
        $header = 't='.$t.',te='.PaymongoSignature::sign($t, $body, 'whsk_test_secret_for_the_tests').',li=';

        $this->call('POST', '/api/webhooks/paymongo', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_PAYMONGO_SIGNATURE' => $header, 'HTTP_ACCEPT' => 'application/json'], $body)
            ->assertOk()->assertJson(['outcome' => 'unknown_payment']);

        $this->assertSame('pending', $payment->refresh()->status);
        $this->assertSame(0, RepositoryUnlock::count());
    }

    public function test_answering_the_demo_checkout_over_and_over_is_slowed_down(): void
    {
        [$user, , $kid, $listing] = $this->world();
        $payment = $this->started($user, $listing, $kid);
        $this->answer($user, $payment, 'gcash', 'decline');

        $statuses = [];
        foreach (range(1, 22) as $ignored) {
            $statuses[] = $this->answer($user, $payment, 'gcash')->getStatusCode();
        }

        $this->assertSame(429, end($statuses), 'a parent can answer 20 times a minute, then is asked to wait');
        $this->assertNotContains(429, array_slice($statuses, 0, 15));
        $this->assertSame(0, RepositoryUnlock::count());
    }

    public function test_the_demo_pages_follow_the_copy_rules_no_emoji_and_no_dashes(): void
    {
        [$user, , $kid, $listing] = $this->world();
        $payment = $this->started($user, $listing, $kid);

        $pages = [
            $this->asParent($user)->get(route('parent.repository.index', ['learner_id' => $kid->id]))->getContent(),
            $this->asParent($user)->get(route('parent.payments.demo', $payment->reference))->getContent(),
            $this->asParent($user)->get(route('parent.payments.demo.step', ['reference' => $payment->reference, 'method' => 'card', 'outcome' => 'approve']))->getContent(),
            $this->asParent($user)->get(route('parent.payments.demo.step', ['reference' => $payment->reference, 'method' => 'gcash', 'outcome' => 'approve']))->getContent(),
            $this->asParent($user)->get(route('parent.payments.back', $payment->reference))->getContent(),
            $this->asParent($user)->get(route('parent.payments.index'))->getContent(),
        ];
        $this->answer($user, $payment, 'card', 'decline');
        $pages[] = $this->asParent($user)->get(route('parent.payments.back', $payment->reference))->getContent();
        $paid = $this->started($user, $listing, $kid);
        $this->answer($user, $paid);
        $pages[] = $this->asParent($user)->get(route('parent.payments.back', $paid->reference))->getContent();

        foreach ($pages as $html) {
            $text = strip_tags((string) preg_replace('/<(style|script)\b.*?<\/\1>/is', '', (string) $html));
            $this->assertStringNotContainsString("\u{2014}", $text, 'no em dash in the copy');
            $this->assertStringNotContainsString("\u{2013}", $text, 'no en dash in the copy');
            $this->assertSame(0, preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $text), 'no emoji in the copy');
        }
    }

    public function test_deleting_the_child_removes_the_demo_payment_records_with_it(): void
    {
        [$user, $parent, $kid, $listing] = $this->world();
        $payment = $this->started($user, $listing, $kid);
        $this->answer($user, $payment);
        $this->assertSame(1, Payment::where('parent_id', $parent->id)->where('provider', 'demo')->count());

        $result = app(DataErasureService::class)->removeChild($user, $kid, 'Passw0rd!');

        $this->assertTrue($result['erased']);
        $this->assertSame(0, Payment::where('learner_id', $kid->id)->count(), 'a demo payment is erased like any other payment record');
        $this->assertSame(0, RepositoryUnlock::where('learner_id', $kid->id)->count());
    }

    // ------------------------------------------------------------------------------------------------------------- helpers

    /** A page that takes a card or wallet number would have a text, tel, number, email, password or search box, or a text area. Radios and hidden fields only. */
    private function assertNoTypingBoxes(string $html): void
    {
        preg_match_all('/<input\b[^>]*>/i', $html, $inputs);
        foreach ($inputs[0] as $input) {
            $this->assertMatchesRegularExpression('/type="(radio|hidden)"/i', $input, 'only radio and hidden fields: '.$input);
        }
        $this->assertStringNotContainsString('<textarea', $html);
        $this->assertStringNotContainsString('<select', $html);
        $this->assertDoesNotMatchRegularExpression('/autocomplete="cc-/i', $html);
    }

    /** The demo sends the parent to no other company, so no page of it names one or links to one. */
    private function assertDontSeeProvider(string $html): void
    {
        $this->assertDoesNotMatchRegularExpression('/paymongo/i', $html);
        $this->assertDoesNotMatchRegularExpression('/checkout\.example\.test|https?:\/\/(?!localhost)[^"\'\s]*\.(com|ph|net)\b/i', preg_replace('/<link\b[^>]*>|<script\b[^>]*src=[^>]*>/i', '', $html) ?? $html);
    }
}
