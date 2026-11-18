<?php

namespace Tests\Feature;

use App\Models\Learner;
use App\Models\ParentAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The PIN a parent sets while adding a child follows the same rule as a PIN made later (App\Support\PinRules):
 * not four of the same number and not a straight run. A weak PIN used to be accepted here and refused only on a reset.
 */
class ChildPinAtCreationTest extends TestCase
{
    use RefreshDatabase;

    private function parentUser(): User
    {
        $u = User::create(['first_name' => 'Pat', 'last_name' => 'Cruz', 'email' => 'pat@example.com', 'password' => 'Passw0rd!', 'user_type' => 'Parent']);
        $u->forceFill(['email_verified_at' => now()])->save();
        ParentAccount::create(['user_id' => $u->id]);

        return $u;
    }

    private function form(string $pin): array
    {
        return [
            'first_name' => 'Ben', 'last_name' => 'Santos', 'grade_level' => 'Grade 1', 'avatar_id' => '🐰', 'reading_stage' => 'blending',
            'q1' => 'yes', 'q2' => 'no', 'q3' => 'no', 'pin' => $pin, 'pin_confirmation' => $pin, 'parent_consent' => '1',
        ];
    }

    /** @return array<string, array{string, string}> */
    public static function weakPins(): array
    {
        return [
            'four of the same' => ['1111', 'four of the same number'],
            'zeros' => ['0000', 'four of the same number'],
            'run up' => ['1234', 'run of numbers'],
            'run down' => ['4321', 'run of numbers'],
            'run in the middle' => ['3456', 'run of numbers'],
            'run down from nine' => ['9876', 'run of numbers'],
        ];
    }

    #[DataProvider('weakPins')]
    public function test_the_phone_api_refuses_an_easy_pin_when_adding_a_child(string $pin, string $words): void
    {
        $token = $this->parentUser()->createToken('t')->plainTextToken;

        $r = $this->withToken($token)->postJson('/api/parent/children', $this->form($pin))->assertStatus(422)->assertJsonValidationErrors('pin');

        $this->assertStringContainsString($words, $r->json('errors.pin.0'));
        $this->assertSame(0, Learner::count(), 'no child is made with an easy PIN');
    }

    #[DataProvider('weakPins')]
    public function test_the_website_refuses_an_easy_pin_when_adding_a_child(string $pin, string $words): void
    {
        $user = $this->parentUser();

        $this->actingAs($user)->from(route('parent.children.create'))->post(route('parent.children.store'), $this->form($pin))
            ->assertRedirect(route('parent.children.create'))
            ->assertSessionHasErrors('pin');

        $this->assertStringContainsString($words, session('errors')->first('pin'));
        $this->assertSame(0, Learner::count());
    }

    public function test_a_normal_pin_is_still_accepted_on_both(): void
    {
        $user = $this->parentUser();
        $token = $user->createToken('t')->plainTextToken;

        $this->withToken($token)->postJson('/api/parent/children', $this->form('2580'))->assertCreated();
        $this->actingAs($user)->post(route('parent.children.store'), $this->form('1357'))->assertOk();

        $this->assertSame(2, Learner::count());
    }

    public function test_a_pin_that_is_not_four_digits_still_gets_the_plain_length_message_only(): void
    {
        $token = $this->parentUser()->createToken('t')->plainTextToken;

        $r = $this->withToken($token)->postJson('/api/parent/children', $this->form('12'))->assertStatus(422);

        $this->assertCount(1, $r->json('errors.pin'), 'one message, not two');
    }
}
