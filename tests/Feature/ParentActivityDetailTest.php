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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * An activity in the Open Repository opens (website window and phone sheet), shows its words only once unlocked for that child, shows this
 * parent's own receipt, and the phone app pays with the same demo checkout as the website (pretend money, nothing sent to anyone, only an
 * approved answer unlocks, one parent never reaches another's payment).
 */
class ParentActivityDetailTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payments.mode' => 'demo', 'payments.enabled' => false]);
        Http::fake();
    }

    /** @return array{0: User, 1: ParentAccount, 2: Learner} */
    private function family(): array
    {
        $n = ++$this->seq;
        $user = User::create(['first_name' => 'Carla', 'last_name' => "Cruz{$n}", 'email' => "ra{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Parent']);
        $user->forceFill(['email_verified_at' => now()])->save();
        $parent = ParentAccount::create(['user_id' => $user->id]);
        $kid = Learner::create(['learner_code' => LearnerCode::generate(), 'first_name' => 'Mia', 'last_name' => 'Cruz', 'grade_level' => 'Grade 1',
            'pin' => Hash::make('1234'), 'avatar_id' => 'A', 'mastery_level' => 'Beginning', 'reading_rung' => 2]);
        $parent->learners()->attach($kid->id, ['relationship' => 'Mother', 'is_creator' => true, 'linked_at' => now()]);

        return [$user, $parent, $kid];
    }

    private function listing(float $price, string $words = 'sunny puppy rabbit'): OpenRepositoryListing
    {
        $n = ++$this->seq;
        $teacherUser = User::create(['first_name' => 'Ana', 'last_name' => "Reyes{$n}", 'email' => "rat{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        $teacher = Teacher::create(['user_id' => $teacherUser->id, 'school_name' => 'Rizal ES', 'employee_id' => "RA{$n}", 'status' => 'Active', 'free_generation_credits_remaining' => 2, 'grades_handled' => ['Grade 1']]);
        $activity = Activity::create(['created_by_teacher_id' => $teacher->id, 'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'competency_label' => 'Foundational Reading',
            'activity_type' => 'word_reading', 'title' => "Words {$n}", 'instructions' => 'Read.', 'passage_text' => $words, 'reference_text' => $words, 'word_count' => 3, 'status' => 'Approved', 'difficulty_tier' => 'Easy']);

        return OpenRepositoryListing::create(['activity_id' => $activity->id, 'teacher_id' => $teacher->id, 'price_type' => $price > 0 ? 'Paid' : 'Free', 'price' => $price]);
    }

    private function phone(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('mobile')->plainTextToken);
    }

    private function web(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user);
    }

    public function test_every_activity_card_opens_a_window_and_a_locked_one_does_not_give_the_words_away(): void
    {
        [$user, , $kid] = $this->family();
        $listing = $this->listing(25);

        $this->web($user)->get(route('parent.repository.index', ['learner_id' => $kid->id]))->assertOk()
            ->assertSee('id="ra-'.$listing->id.'"', false)->assertSee('data-ra-open="ra-'.$listing->id.'"', false)
            ->assertSee('View details', false)->assertSee('Go to demo checkout', false)
            ->assertDontSee('sunny puppy rabbit', false);
    }

    public function test_after_a_demo_payment_the_window_shows_the_words_and_this_parents_receipt(): void
    {
        [$user, , $kid] = $this->family();
        $listing = $this->listing(25);
        $this->web($user)->post(route('parent.payments.start', $listing), ['learner_id' => $kid->id]);
        $payment = Payment::firstOrFail();
        $this->web($user)->post(route('parent.payments.demo.pay', $payment->reference), ['method' => 'gcash', 'outcome' => 'approve']);

        $this->web($user)->get(route('parent.repository.index', ['learner_id' => $kid->id]))->assertOk()
            ->assertSee('Ready to read &middot; Open', false)->assertSee('sunny puppy rabbit', false)
            ->assertSee($payment->reference, false)->assertSee('GCash', false)->assertSee('(demo)', false);
    }

    public function test_the_phone_pays_with_the_demo_checkout_and_only_an_approved_answer_unlocks(): void
    {
        [$user, , $kid] = $this->family();
        $listing = $this->listing(25);

        $list = $this->phone($user)->getJson("/api/parent/repository?learner_id={$kid->id}")->assertOk();
        $this->assertSame(['mode' => 'demo', 'ready' => true], $list->json('payments'));
        $this->assertNull($list->json('listings.0.passage'));
        $this->assertSame('Word Reading', $list->json('listings.0.type'));

        $start = $this->phone($user)->postJson("/api/parent/repository/{$listing->id}/pay", ['learner_id' => $kid->id])->assertOk();
        $this->assertSame(['card', 'gcash', 'maya'], array_column($start->json('methods'), 'key'));
        $reference = $start->json('payment.reference');
        $this->assertSame('pending', $start->json('payment.status'));
        $this->assertSame('₱25.00', $start->json('payment.amount'));
        $this->assertSame(0, RepositoryUnlock::count(), 'starting never unlocks');

        $paid = $this->phone($user)->postJson("/api/parent/payments/{$reference}/demo/pay", ['method' => 'maya', 'outcome' => 'approve'])->assertOk();
        $this->assertSame('paid', $paid->json('payment.status'));
        $this->assertSame('Maya', $paid->json('payment.method'));
        $this->assertSame(1, RepositoryUnlock::count());

        $after = $this->phone($user)->getJson("/api/parent/repository?learner_id={$kid->id}")->assertOk();
        $this->assertTrue($after->json('listings.0.unlocked'));
        $this->assertSame('sunny puppy rabbit', $after->json('listings.0.passage'));
        $this->assertSame($reference, $after->json('listings.0.receipt.reference'));

        $this->assertSame($reference, $this->phone($user)->getJson('/api/parent/payments')->assertOk()->json('payments.0.reference'));
        Http::assertNothingSent();
    }

    public function test_declined_and_cancelled_unlock_nothing_and_a_wrong_answer_is_refused(): void
    {
        [$user, , $kid] = $this->family();
        $listing = $this->listing(25);

        $ref = $this->phone($user)->postJson("/api/parent/repository/{$listing->id}/pay", ['learner_id' => $kid->id])->json('payment.reference');
        $this->phone($user)->postJson("/api/parent/payments/{$ref}/demo/pay", ['method' => 'bitcoin', 'outcome' => 'approve'])->assertStatus(422);
        $this->phone($user)->postJson("/api/parent/payments/{$ref}/demo/pay", ['method' => 'card', 'outcome' => 'free'])->assertStatus(422);
        $declined = $this->phone($user)->postJson("/api/parent/payments/{$ref}/demo/pay", ['method' => 'card', 'outcome' => 'decline'])->assertOk();
        $this->assertTrue($declined->json('payment.declined'));
        $this->assertSame('Declined', $declined->json('payment.statusLabel'));

        $again = $this->phone($user)->postJson("/api/parent/repository/{$listing->id}/pay", ['learner_id' => $kid->id])->json('payment.reference');
        $this->assertNotSame($ref, $again);
        $this->assertSame('cancelled', $this->phone($user)->postJson("/api/parent/payments/{$again}/demo/pay", ['method' => 'card', 'outcome' => 'cancel'])->json('payment.status'));
        // A decided payment cannot be turned into a paid one afterwards.
        $this->assertSame('cancelled', $this->phone($user)->postJson("/api/parent/payments/{$again}/demo/pay", ['method' => 'card', 'outcome' => 'approve'])->json('payment.status'));
        $this->assertSame(0, RepositoryUnlock::count());
    }

    public function test_nobody_else_can_use_a_parents_payment_or_see_their_receipt(): void
    {
        [$user, , $kid] = $this->family();
        [$other, $otherParent] = $this->family();
        $listing = $this->listing(25);
        // No sign in at all (asked first: a token used later would stay on the next requests of this test).
        $this->postJson("/api/parent/repository/{$listing->id}/pay", ['learner_id' => $kid->id])->assertUnauthorized();
        $ref = $this->phone($user)->postJson("/api/parent/repository/{$listing->id}/pay", ['learner_id' => $kid->id])->json('payment.reference');

        $this->phone($other)->postJson("/api/parent/payments/{$ref}/demo/pay", ['method' => 'card', 'outcome' => 'approve'])->assertNotFound();
        $this->phone($other)->postJson("/api/parent/repository/{$listing->id}/pay", ['learner_id' => $kid->id])->assertForbidden();
        $this->assertSame([], $this->phone($other)->getJson('/api/parent/payments')->json('payments'));

        // A second guardian of the same child sees the unlock but not the first parent's receipt.
        $otherParent->learners()->attach($kid->id, ['relationship' => 'Father', 'is_creator' => false, 'linked_at' => now()]);
        $this->phone($user)->postJson("/api/parent/payments/{$ref}/demo/pay", ['method' => 'card', 'outcome' => 'approve'])->assertOk();
        $seen = $this->phone($other)->getJson("/api/parent/repository?learner_id={$kid->id}")->assertOk();
        $this->assertTrue($seen->json('listings.0.unlocked'));
        $this->assertNull($seen->json('listings.0.receipt'));
    }

    public function test_without_the_demo_the_phone_checkout_is_refused(): void
    {
        [$user, , $kid] = $this->family();
        $listing = $this->listing(25);

        foreach (['off' => 'There is no demo checkout switched on right now.', 'paymongo' => 'This is a paid activity. Paid activities are unlocked on the website, with a test payment.'] as $mode => $words) {
            config(['payments.mode' => $mode]);
            $answer = $this->phone($user)->postJson("/api/parent/repository/{$listing->id}/pay", ['learner_id' => $kid->id])->assertStatus(422);
            $this->assertSame($words, $answer->json('errors.unlock.0'));
        }
        $this->assertSame(0, Payment::count());
    }
}
