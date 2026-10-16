<?php

namespace Tests\Feature;

use App\Models\Learner;
use App\Models\LearnerPinChange;
use App\Models\Notification;
use App\Models\ParentAccount;
use App\Models\Teacher;
use App\Models\User;
use App\Services\LearnerPinService;
use App\Support\LearnerCode;
use App\Support\PinRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * A child forgot their PIN: a linked guardian makes a new one (website and phone), with limits. The rules live in
 * LearnerPinService and PinRules; these check every one of them, and that nothing ever stores or shows a PIN it should not.
 */
class ChildPinResetTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        RateLimiter::clear('x');
    }

    /** @return array{0: User, 1: ParentAccount} */
    private function parent(string $first = 'Carla'): array
    {
        $n = ++$this->seq;
        $user = User::create(['first_name' => $first, 'last_name' => "Domingo{$n}", 'email' => "pin{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Parent']);
        $user->forceFill(['email_verified_at' => now()])->save();

        return [$user, ParentAccount::create(['user_id' => $user->id])];
    }

    private function child(ParentAccount $parent, string $pin = '2580'): Learner
    {
        $kid = Learner::create([
            'learner_code' => LearnerCode::generate(), 'first_name' => 'Mia', 'last_name' => 'Cruz', 'grade_level' => 'Grade 1',
            'pin' => Hash::make($pin), 'avatar_id' => '🦁', 'mastery_level' => 'Beginning', 'reading_rung' => 1,
        ]);
        $parent->learners()->attach($kid->id, ['relationship' => 'Mother', 'is_creator' => true, 'linked_at' => now()]);

        return $kid;
    }

    private function form(array $over = []): array
    {
        return $over + ['mode' => 'generate', 'password' => 'Passw0rd!'];
    }

    private function reset(User $user, Learner $kid, array $over = [])
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user)->post(route('parent.children.pin', $kid), $this->form($over));
    }

    // ------------------------------------------------------------------ the rules for a PIN

    public function test_a_pin_may_not_be_four_of_the_same_number_a_straight_run_or_not_four_digits(): void
    {
        foreach (['1111', '0000', '1234', '2345', '0123', '4321', '9876', '3210'] as $weak) {
            $this->assertNotNull(PinRules::refusal($weak), "{$weak} should be refused");
        }
        foreach (['12a4', '123', '12345', ''] as $bad) {
            $this->assertSame('The PIN must be exactly 4 numbers.', PinRules::refusal($bad));
        }
        foreach (['2580', '1357', '0709', '4827', '1212'] as $fine) {
            $this->assertNull(PinRules::refusal($fine), "{$fine} should be allowed");
        }
        $this->assertStringContainsString('already the PIN', (string) PinRules::refusal('2580', Hash::make('2580')));
    }

    public function test_a_made_up_pin_is_always_four_digits_and_never_a_weak_one(): void
    {
        $current = Hash::make('2580');

        for ($i = 0; $i < 400; $i++) {
            $pin = PinRules::generate($current);
            $this->assertMatchesRegularExpression('/^\d{4}$/', $pin);
            $this->assertNull(PinRules::refusal($pin, $current), "{$pin} broke a rule");
        }
    }

    // ------------------------------------------------------------------ the website

    public function test_a_guardian_can_have_a_new_pin_made_which_works_at_once_is_shown_once_and_the_old_one_stops(): void
    {
        [$user, $parent] = $this->parent();
        $kid = $this->child($parent);

        $response = $this->reset($user, $kid);

        $response->assertRedirect(route('parent.children.index'));
        $shown = session('new_pin');
        $this->assertSame($kid->id, $shown['learner_id']);
        $this->assertMatchesRegularExpression('/^\d{4}$/', $shown['pin']);

        $fresh = $kid->fresh();
        $this->assertTrue(Hash::check($shown['pin'], $fresh->pin));
        $this->assertFalse(Hash::check('2580', $fresh->pin), 'the old PIN must stop working');

        $this->assertSame(1, LearnerPinChange::where('learner_id', $kid->id)->where('user_id', $user->id)->where('method', 'generated')->count());
        // Nothing readable keeps the PIN: not the record of the change, not an alert.
        $this->assertStringNotContainsString($shown['pin'], json_encode(LearnerPinChange::all()->toArray()));
        $this->assertStringNotContainsString($shown['pin'], json_encode(Notification::all()->pluck('message')->all()));
    }

    public function test_a_guardian_can_choose_the_new_pin_and_it_is_not_shown_back(): void
    {
        [$user, $parent] = $this->parent();
        $kid = $this->child($parent);

        $this->reset($user, $kid, ['mode' => 'choose', 'pin' => '2468', 'pin_confirmation' => '2468'])->assertRedirect(route('parent.children.index'));

        $this->assertNull(session('new_pin'), 'a PIN the guardian typed is not echoed back');
        $this->assertTrue(Hash::check('2468', $kid->fresh()->pin));
        $this->assertSame('chosen', LearnerPinChange::first()->method);
    }

    public function test_a_wrong_password_changes_nothing_and_five_wrong_ones_lock_the_form(): void
    {
        [$user, $parent] = $this->parent();
        $kid = $this->child($parent);

        for ($i = 0; $i < 5; $i++) {
            $this->reset($user, $kid, ['password' => 'not-it'])->assertSessionHasErrors(['password' => 'That password is not right.']);
        }
        // Now even the right password is turned away for a while.
        $this->reset($user, $kid)->assertSessionHasErrors('password');
        $this->assertStringContainsString('Too many tries', session('errors')->first('password'));

        $this->assertTrue(Hash::check('2580', $kid->fresh()->pin), 'nothing changed');
        $this->assertSame(0, LearnerPinChange::count());
    }

    public function test_a_weak_or_unchanged_or_mismatched_pin_is_refused_and_the_old_one_stays(): void
    {
        [$user, $parent] = $this->parent();
        $kid = $this->child($parent);

        foreach (['1111', '1234', '4321', '2580'] as $bad) {
            $this->reset($user, $kid, ['mode' => 'choose', 'pin' => $bad, 'pin_confirmation' => $bad])->assertSessionHasErrors('pin');
        }
        $this->reset($user, $kid, ['mode' => 'choose', 'pin' => '12a4', 'pin_confirmation' => '12a4'])->assertSessionHasErrors('pin');
        $this->reset($user, $kid, ['mode' => 'choose', 'pin' => '2468', 'pin_confirmation' => '2469'])->assertSessionHasErrors('pin_confirmation');
        $this->reset($user, $kid, ['mode' => 'choose'])->assertSessionHasErrors('pin');
        $this->reset($user, $kid, ['mode' => 'nonsense'])->assertSessionHasErrors('mode');

        $this->assertTrue(Hash::check('2580', $kid->fresh()->pin));
        $this->assertSame(0, LearnerPinChange::count());
    }

    public function test_a_pin_that_was_typed_wrongly_is_not_written_back_into_the_form(): void
    {
        [$user, $parent] = $this->parent();
        $kid = $this->child($parent);

        $this->reset($user, $kid, ['mode' => 'choose', 'pin' => '1111', 'pin_confirmation' => '1111', 'pin_form' => (string) $kid->id]);

        $this->assertNull(session()->getOldInput('pin'));
        $this->assertNull(session()->getOldInput('pin_confirmation'));
        $this->assertNull(session()->getOldInput('password'));
    }

    public function test_a_pin_can_be_changed_three_times_a_day_and_again_the_next_day(): void
    {
        [$user, $parent] = $this->parent();
        $kid = $this->child($parent);

        for ($i = 0; $i < PinRules::CHANGES_PER_DAY; $i++) {
            $this->reset($user, $kid)->assertSessionHasNoErrors();
        }

        $this->reset($user, $kid)->assertSessionHasErrors('pin');
        $this->assertStringContainsString('3 times today', session('errors')->first('pin'));
        $this->assertSame(3, LearnerPinChange::count());

        $this->travel(25)->hours();
        $this->reset($user, $kid)->assertSessionHasNoErrors();
        $this->assertSame(4, LearnerPinChange::count());
    }

    public function test_only_a_guardian_linked_to_the_child_can_do_it(): void
    {
        [$owner, $ownerParent] = $this->parent();
        $kid = $this->child($ownerParent);
        [$stranger] = $this->parent('Rosa');

        $this->reset($stranger, $kid)->assertNotFound();
        $this->assertTrue(Hash::check('2580', $kid->fresh()->pin));

        // A teacher or an admin signed in on the website is not a Parent at all.
        $tUser = User::create(['first_name' => 'Ana', 'last_name' => 'Reyes', 'email' => 'pin-t@example.com', 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        Teacher::create(['user_id' => $tUser->id, 'school_name' => 'Rizal ES', 'employee_id' => 'PIN1', 'status' => 'Active', 'free_generation_credits_remaining' => 2, 'grades_handled' => ['Grade 1']]);
        $this->app['auth']->forgetGuards();
        $this->actingAs($tUser)->post(route('parent.children.pin', $kid), $this->form())->assertForbidden();
        $this->assertTrue(Hash::check('2580', $kid->fresh()->pin));
    }

    public function test_the_other_guardians_are_told_but_not_the_one_who_did_it_or_the_teacher(): void
    {
        [$user, $parent] = $this->parent('Carla');
        [$other, $otherParent] = $this->parent('Rosa');
        $kid = $this->child($parent);
        $otherParent->learners()->attach($kid->id, ['relationship' => 'Father', 'is_creator' => false, 'linked_at' => now()]);

        $this->reset($user, $kid);

        $told = Notification::where('type', Notification::TYPE_PIN_CHANGED)->get();
        $this->assertCount(1, $told);
        $this->assertSame($other->id, $told->first()->recipient_user_id);
        $this->assertStringContainsString('Carla', $told->first()->message);
        $this->assertSame(0, Notification::where('recipient_user_id', $user->id)->count(), 'the person who did it is not told about it');
    }

    public function test_a_phone_sign_in_of_the_child_ends_when_the_pin_changes(): void
    {
        [$user, $parent] = $this->parent();
        $kid = $this->child($parent);
        $token = $kid->createToken('mobile')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/learner/dashboard')->assertStatus(403); // signed in (the first reading check is still due), not 401

        $this->reset($user, $kid);

        $this->assertSame(0, $kid->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/learner/dashboard')->assertStatus(401);
    }

    public function test_a_website_sign_in_of_the_child_ends_at_its_next_page_when_the_pin_changes(): void
    {
        [$user, $parent] = $this->parent();
        $kid = $this->child($parent);
        $oldStamp = LearnerPinService::stamp($kid);

        // Signed in with the old PIN: the page opens.
        $this->app['auth']->forgetGuards();
        $this->actingAs($kid, 'learner')->withSession(['learner_pin_stamp' => $oldStamp])->get(route('learner.diagnostic.show'))->assertSuccessful();

        $this->reset($user, $kid);

        $this->app['auth']->forgetGuards();
        $this->actingAs($kid->fresh(), 'learner')->withSession(['learner_pin_stamp' => $oldStamp])->get(route('learner.diagnostic.show'))
            ->assertRedirect(route('learner.login'))
            ->assertSessionHas('status', 'Your PIN was changed. Please sign in with the new PIN.');

        // A sign in made before this existed has no fingerprint and is simply given one: nobody is signed out by the update.
        $this->app['auth']->forgetGuards();
        $this->actingAs($kid->fresh(), 'learner')->get(route('learner.diagnostic.show'))->assertSuccessful();
    }

    // ------------------------------------------------------------------ the locked child

    public function test_five_wrong_pins_lock_the_child_and_tell_the_guardians_once_and_a_new_pin_unlocks_at_once(): void
    {
        [$user, $parent] = $this->parent();
        [$other, $otherParent] = $this->parent('Rosa');
        $kid = $this->child($parent);
        $otherParent->learners()->attach($kid->id, ['relationship' => 'Father', 'is_creator' => false, 'linked_at' => now()]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/learner/login', ['learner_code' => $kid->learner_code, 'pin' => '9999'])->assertStatus(422);
        }
        // Locked, even for the right PIN, and the message says who can help.
        $locked = $this->postJson('/api/learner/login', ['learner_code' => $kid->learner_code, 'pin' => '2580'])->assertStatus(422);
        $this->assertStringContainsString('Ask your grown-up for a new PIN', $locked->json('errors.pin.0'));
        $this->postJson('/api/learner/login', ['learner_code' => $kid->learner_code, 'pin' => '2580'])->assertStatus(422);

        // Each guardian heard about it exactly once, in words that say what to do.
        foreach ([$user, $other] as $guardian) {
            $alerts = Notification::where('recipient_user_id', $guardian->id)->where('type', Notification::TYPE_PIN_LOCKED)->get();
            $this->assertCount(1, $alerts);
            $this->assertStringContainsString('New PIN', $alerts->first()->message);
        }

        // The guardian makes a new PIN: the child gets in at once, from the same device that was locked.
        $this->reset($user, $kid);
        $newPin = session('new_pin')['pin'];
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/learner/login', ['learner_code' => $kid->learner_code, 'pin' => $newPin])->assertOk();
        // And the old PIN is just wrong now, not "locked".
        $this->assertSame('Incorrect code or PIN.', $this->postJson('/api/learner/login', ['learner_code' => $kid->learner_code, 'pin' => '2580'])->json('errors.pin.0'));
    }

    public function test_a_code_that_does_not_exist_gets_the_same_answer_and_tells_nobody(): void
    {
        $known = $this->postJson('/api/learner/login', ['learner_code' => 'TB26-00000', 'pin' => '9999'])->assertStatus(422);
        $this->assertSame('Incorrect code or PIN.', $known->json('errors.pin.0'));

        for ($i = 0; $i < 6; $i++) {
            $last = $this->postJson('/api/learner/login', ['learner_code' => 'TB26-00000', 'pin' => '9999'])->assertStatus(422);
        }
        $this->assertStringContainsString('Too many attempts', $last->json('errors.pin.0'));
        $this->assertSame(0, Notification::count());
    }

    // ------------------------------------------------------------------ the phone API

    public function test_the_phone_api_makes_a_pin_returns_it_once_and_follows_the_same_rules(): void
    {
        [$user, $parent] = $this->parent();
        $kid = $this->child($parent);
        $token = $user->createToken('mobile')->plainTextToken;

        $this->app['auth']->forgetGuards();
        $made = $this->withToken($token)->postJson("/api/parent/children/{$kid->id}/pin", $this->form())->assertOk();
        $this->assertMatchesRegularExpression('/^\d{4}$/', $made->json('pin'));
        $this->assertStringContainsString('old PIN no longer works', $made->json('message'));
        $this->assertTrue(Hash::check($made->json('pin'), $kid->fresh()->pin));

        $this->app['auth']->forgetGuards();
        $typed = $this->withToken($token)->postJson("/api/parent/children/{$kid->id}/pin", $this->form(['mode' => 'choose', 'pin' => '2468', 'pin_confirmation' => '2468']))->assertOk();
        $this->assertNull($typed->json('pin'));

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson("/api/parent/children/{$kid->id}/pin", $this->form(['mode' => 'choose', 'pin' => '1234', 'pin_confirmation' => '1234']))->assertStatus(422)->assertJsonValidationErrors('pin');
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson("/api/parent/children/{$kid->id}/pin", $this->form(['password' => 'nope']))->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_the_phone_api_gives_a_stranger_and_a_teacher_nothing(): void
    {
        [, $ownerParent] = $this->parent();
        $kid = $this->child($ownerParent);
        [$stranger] = $this->parent('Rosa');

        $this->app['auth']->forgetGuards();
        $this->withToken($stranger->createToken('mobile')->plainTextToken)->postJson("/api/parent/children/{$kid->id}/pin", $this->form())->assertNotFound();

        $tUser = User::create(['first_name' => 'Ana', 'last_name' => 'Reyes', 'email' => 'pin-api-t@example.com', 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        Teacher::create(['user_id' => $tUser->id, 'school_name' => 'Rizal ES', 'employee_id' => 'PIN2', 'status' => 'Active', 'free_generation_credits_remaining' => 2, 'grades_handled' => ['Grade 1']]);
        $this->app['auth']->forgetGuards();
        $this->withToken($tUser->createToken('mobile')->plainTextToken)->postJson("/api/parent/children/{$kid->id}/pin", $this->form())->assertForbidden();

        $this->assertTrue(Hash::check('2580', $kid->fresh()->pin));
    }

    public function test_a_locked_child_alert_counts_as_needing_attention_and_a_changed_pin_alert_as_an_update(): void
    {
        [$user, $parent] = $this->parent();
        $kid = $this->child($parent);
        $token = $user->createToken('mobile')->plainTextToken;
        Notification::create(['recipient_user_id' => $user->id, 'learner_id' => $kid->id, 'type' => Notification::TYPE_PIN_LOCKED, 'message' => 'Mia tried the wrong PIN 5 times.']);
        Notification::create(['recipient_user_id' => $user->id, 'learner_id' => $kid->id, 'type' => Notification::TYPE_PIN_CHANGED, 'message' => 'Rosa set a new PIN for Mia.']);

        $this->app['auth']->forgetGuards();
        $attention = $this->withToken($token)->getJson('/api/parent/alerts?filter=attention')->assertOk();
        $this->assertSame(['PIN Locked'], collect($attention->json('alerts'))->pluck('type')->all());
        $this->assertTrue($attention->json('alerts.0.attention'));

        $this->app['auth']->forgetGuards();
        $routine = $this->withToken($token)->getJson('/api/parent/alerts?filter=routine')->assertOk();
        $this->assertSame(['PIN Changed'], collect($routine->json('alerts'))->pluck('type')->all());

        // On the website the same two alerts show, with their own titles.
        $this->app['auth']->forgetGuards();
        $this->actingAs($user)->get(route('parent.notifications.index'))->assertOk()->assertSee('PIN Locked')->assertSee('PIN Changed');
    }

    public function test_the_childs_sign_in_page_says_who_can_make_a_new_pin_and_shows_the_message_after_a_change(): void
    {
        $this->get(route('learner.login'))->assertOk()->assertSee('Forgot my PIN?')->assertSee('Ask your grown-up!')->assertSee('Tap <b>New PIN</b>.', false);

        $this->withSession(['status' => 'Your PIN was changed. Please sign in with the new PIN.'])->get(route('learner.login'))
            ->assertOk()->assertSee('Your PIN was changed. Please sign in with the new PIN.');
    }

    public function test_signing_in_as_another_child_in_the_same_browser_never_counts_as_a_changed_pin(): void
    {
        [, $parent] = $this->parent();
        $first = $this->child($parent, '2580');
        $second = $this->child($parent, '1357');

        $this->app['auth']->forgetGuards();
        $this->actingAs($second, 'learner')->withSession(['learner_pin_stamp' => LearnerPinService::stamp($first)])->get(route('learner.diagnostic.show'))->assertSuccessful();
    }
}
