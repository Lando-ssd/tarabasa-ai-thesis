<?php

namespace Tests\Feature;

use App\Models\Learner;
use App\Models\ParentAccount;
use App\Models\Teacher;
use App\Models\User;
use App\Support\LearnerCode;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * The phone app's sign in and sign up for Teachers and Parents: the same rules as the website (they share
 * AccountAuthService), plus the fence that keeps one kind of token out of another kind's routes. A Learner's token
 * never opens a Teacher route and a Teacher's token never opens a Learner route; an Admin is never accepted on the phone.
 */
class AccountApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.require_verified_email' => true]);
        RateLimiter::clear('pinky@example.com|127.0.0.1');
    }

    private function account(string $type = 'Teacher', array $o = []): User
    {
        $u = User::create($o + ['first_name' => 'Pinky', 'last_name' => 'Reyes', 'email' => 'pinky@example.com', 'password' => 'Passw0rd!', 'user_type' => $type]);

        if ($type === 'Teacher') {
            Teacher::create(['user_id' => $u->id, 'school_name' => 'Rizal ES', 'employee_id' => 'EMP-1', 'status' => 'Pending', 'free_generation_credits_remaining' => 2, 'grades_handled' => ['Grade 1']]);
        } elseif ($type === 'Parent') {
            ParentAccount::create(['user_id' => $u->id]);
        }

        return $u;
    }

    private function confirmed(string $type = 'Teacher', array $o = []): User
    {
        $u = $this->account($type, $o);
        $u->forceFill(['email_verified_at' => now()])->save();

        return $u;
    }

    private function teacherForm(array $o = []): array
    {
        return $o + [
            'first_name' => 'Pinky', 'last_name' => 'Reyes', 'email' => 'pinky@example.com', 'password' => 'Passw0rd!', 'password_confirmation' => 'Passw0rd!',
            'school_name' => 'Rizal ES', 'employee_id' => 'EMP-1', 'grades_mode' => 'single', 'grades_handled' => ['Grade 2'],
        ];
    }

    private function learner(): Learner
    {
        return Learner::create([
            'learner_code' => LearnerCode::generate(), 'first_name' => 'Kid', 'last_name' => 'Cruz', 'grade_level' => 'Grade 1',
            'pin' => '1234', 'avatar_id' => 'A', 'mastery_level' => 'Developing', 'reading_rung' => 3,
        ]);
    }

    // ------------------------------------------------------------------ sign in

    public function test_a_confirmed_teacher_signs_in_and_gets_a_token_and_who_they_are(): void
    {
        $this->confirmed('Teacher');

        $r = $this->postJson('/api/auth/login', ['email' => 'Pinky@Example.com ', 'password' => 'Passw0rd!']);

        $r->assertOk()->assertJsonPath('account.role', 'teacher')->assertJsonPath('account.teacher.status', 'Pending')
            ->assertJsonPath('account.teacher.freeCredits', 2)->assertJsonPath('account.teacher.grades', ['Grade 1']);
        $this->assertNotEmpty($r->json('token'));
        $this->assertArrayNotHasKey('password', $r->json('account'));

        // The token works for /me.
        $this->withToken($r->json('token'))->getJson('/api/auth/me')->assertOk()->assertJsonPath('account.email', 'pinky@example.com');
    }

    public function test_a_confirmed_parent_signs_in(): void
    {
        $this->confirmed('Parent', ['email' => 'mama@example.com']);

        $this->postJson('/api/auth/login', ['email' => 'mama@example.com', 'password' => 'Passw0rd!'])
            ->assertOk()->assertJsonPath('account.role', 'parent')->assertJsonMissingPath('account.teacher');
    }

    public function test_a_wrong_password_and_an_unknown_address_get_the_same_answer(): void
    {
        $this->confirmed('Teacher');

        $wrong = $this->postJson('/api/auth/login', ['email' => 'pinky@example.com', 'password' => 'nope-nope']);
        $unknown = $this->postJson('/api/auth/login', ['email' => 'nobody@example.com', 'password' => 'nope-nope']);

        $wrong->assertStatus(401)->assertJsonPath('error', 'invalid_credentials');
        $this->assertSame($wrong->json('message'), $unknown->json('message'));
        $unknown->assertStatus(401);
    }

    public function test_five_wrong_tries_lock_the_address_for_a_minute(): void
    {
        $this->confirmed('Teacher');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'pinky@example.com', 'password' => 'wrong-pass']);
        }

        // Even the right password is refused while locked, with the website's wording.
        $this->postJson('/api/auth/login', ['email' => 'pinky@example.com', 'password' => 'Passw0rd!'])
            ->assertStatus(429)->assertJsonPath('error', 'too_many_attempts');
    }

    public function test_an_unconfirmed_email_cannot_sign_in_and_is_offered_the_link_again(): void
    {
        $this->account('Teacher');

        $r = $this->postJson('/api/auth/login', ['email' => 'pinky@example.com', 'password' => 'Passw0rd!']);

        $r->assertStatus(403)->assertJsonPath('error', 'email_not_confirmed')->assertJsonPath('email', 'pinky@example.com');
        $this->assertStringContainsString('confirm your email', $r->json('message'));
    }

    public function test_an_unconfirmed_address_is_only_named_after_the_password_was_right(): void
    {
        $this->account('Teacher');

        // A wrong password does not reveal that the address has an unconfirmed account.
        $this->postJson('/api/auth/login', ['email' => 'pinky@example.com', 'password' => 'wrong-pass'])
            ->assertStatus(401)->assertJsonPath('error', 'invalid_credentials');
    }

    public function test_a_deactivated_and_a_rejected_account_are_refused_with_the_websites_wording(): void
    {
        $this->confirmed('Teacher', ['status' => 'Inactive']);
        $this->postJson('/api/auth/login', ['email' => 'pinky@example.com', 'password' => 'Passw0rd!'])
            ->assertStatus(403)->assertJsonPath('message', 'This account has been deactivated. Contact your school\'s TaraBasa admin.');

        RateLimiter::clear('pat@example.com|127.0.0.1');
        $rejected = $this->confirmed('Teacher', ['email' => 'pat@example.com']);
        $rejected->teacher->update(['status' => 'Rejected', 'rejection_reason' => 'School not found']);
        $this->postJson('/api/auth/login', ['email' => 'pat@example.com', 'password' => 'Passw0rd!'])
            ->assertStatus(403)->assertJsonPath('error', 'not_allowed');
        $this->assertStringContainsString('Reason: School not found', $this->postJson('/api/auth/login', ['email' => 'pat@example.com', 'password' => 'Passw0rd!'])->json('message'));
    }

    public function test_an_admin_is_never_signed_in_on_the_phone(): void
    {
        $this->confirmed('Admin', ['email' => 'ada@example.com']);

        $this->postJson('/api/auth/login', ['email' => 'ada@example.com', 'password' => 'Passw0rd!'])
            ->assertStatus(403)->assertJsonPath('error', 'admin_web_only')->assertJsonMissingPath('token');
        $this->assertSame(0, \Laravel\Sanctum\PersonalAccessToken::count());
    }

    public function test_an_admin_with_a_token_made_some_other_way_is_still_refused_everywhere(): void
    {
        $admin = $this->confirmed('Admin', ['email' => 'ada@example.com']);
        $token = $admin->createToken('x')->plainTextToken;

        $this->withToken($token)->getJson('/api/auth/me')->assertStatus(403)->assertJsonPath('error', 'admin_web_only');
    }

    public function test_the_website_still_lets_the_admin_in(): void
    {
        $this->confirmed('Admin', ['email' => 'ada@example.com']);

        $this->post('/login', ['email' => 'ada@example.com', 'password' => 'Passw0rd!'])->assertRedirect(route('admin.dashboard'));
    }

    // ------------------------------------------------------------------ sign up

    public function test_a_teacher_signs_up_and_must_confirm_the_email_before_any_token(): void
    {
        Notification::fake();

        $r = $this->postJson('/api/auth/register/teacher', $this->teacherForm());

        $r->assertCreated()->assertJsonPath('status', 'confirm_email')->assertJsonPath('role', 'teacher')->assertJsonMissingPath('token');
        $teacher = User::where('email', 'pinky@example.com')->first();
        $this->assertSame('Pending', $teacher->teacher->status);
        $this->assertSame(2, (int) $teacher->teacher->free_generation_credits_remaining);
        $this->assertSame(['Grade 2'], $teacher->teacher->grades_handled);
        Notification::assertSentTo($teacher, VerifyEmail::class);
        $this->assertNull($teacher->email_verified_at);
    }

    public function test_a_parent_signs_up(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register/parent', ['first_name' => 'Pat', 'last_name' => 'Cruz', 'email' => 'Pat@Example.com', 'password' => 'Passw0rd!', 'password_confirmation' => 'Passw0rd!'])
            ->assertCreated()->assertJsonPath('role', 'parent')->assertJsonPath('maskedEmail', 'p***@example.com');

        $this->assertDatabaseHas('users', ['email' => 'pat@example.com', 'user_type' => 'Parent']);
        $this->assertDatabaseHas('parents', ['user_id' => User::where('email', 'pat@example.com')->value('id')]);
    }

    public function test_sign_up_uses_the_websites_email_rules(): void
    {
        Notification::fake();
        $this->account('Teacher', ['email' => 'taken@example.com']);

        $this->postJson('/api/auth/register/teacher', $this->teacherForm(['email' => 'taken@example.com']))
            ->assertStatus(422)->assertJsonValidationErrors('email');
        $this->postJson('/api/auth/register/teacher', $this->teacherForm(['email' => 'pinky@gmial.com']))
            ->assertStatus(422)->assertJsonPath('errors.email.0', 'That address looks mistyped. Did you mean pinky@gmail.com?');
        $this->postJson('/api/auth/register/teacher', $this->teacherForm(['password_confirmation' => 'different']))
            ->assertStatus(422)->assertJsonValidationErrors('password');
        $this->postJson('/api/auth/register/teacher', $this->teacherForm(['grades_mode' => 'single', 'grades_handled' => ['Grade 1', 'Grade 2']]))
            ->assertStatus(422);
        $this->postJson('/api/auth/register/teacher', ['email' => 'x@example.com'])->assertStatus(422)->assertJsonValidationErrors(['first_name', 'school_name', 'employee_id']);
        $this->assertDatabaseMissing('users', ['email' => 'pinky@gmial.com']);
    }

    public function test_an_address_that_cannot_be_sent_to_leaves_no_account_behind(): void
    {
        $this->app->bind(\App\Services\AccountAuthService::class, fn () => new class extends \App\Services\AccountAuthService
        {
            public function sendVerificationEmail(User $user): bool
            {
                return false;
            }
        });

        $this->postJson('/api/auth/register/teacher', $this->teacherForm())->assertStatus(422)->assertJsonValidationErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'pinky@example.com']);
        $this->assertDatabaseCount('teachers', 0);
    }

    public function test_with_confirming_switched_off_sign_up_gives_a_token_straight_away(): void
    {
        config(['auth.require_verified_email' => false]);
        Notification::fake();

        $r = $this->postJson('/api/auth/register/teacher', $this->teacherForm());

        $r->assertCreated()->assertJsonPath('status', 'signed_in');
        $this->withToken($r->json('token'))->getJson('/api/auth/me')->assertOk();
    }

    public function test_resend_says_the_same_thing_for_any_address(): void
    {
        Notification::fake();
        $u = $this->account('Teacher');

        $known = $this->postJson('/api/auth/resend-verification', ['email' => 'pinky@example.com']);
        $unknown = $this->postJson('/api/auth/resend-verification', ['email' => 'nobody@example.com']);

        $this->assertSame($known->json('message'), $unknown->json('message'));
        Notification::assertSentToTimes($u, VerifyEmail::class, 1);

        // Once a minute per account.
        $this->postJson('/api/auth/resend-verification', ['email' => 'pinky@example.com'])->assertOk();
        Notification::assertSentToTimes($u, VerifyEmail::class, 1);
    }

    public function test_forgot_password_never_says_whether_the_address_exists(): void
    {
        Notification::fake();
        $this->confirmed('Teacher');

        $a = $this->postJson('/api/auth/forgot-password', ['email' => 'pinky@example.com']);
        $b = $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com']);

        $a->assertOk();
        $this->assertSame($a->json('message'), $b->json('message'));
    }

    // ------------------------------------------------------------------ logout and the fence between kinds of token

    public function test_logout_deletes_only_this_devices_token(): void
    {
        $u = $this->confirmed('Teacher');
        $one = $u->createToken('mobile')->plainTextToken;
        $u->createToken('mobile');

        $this->withToken($one)->postJson('/api/auth/logout')->assertOk();

        $this->assertSame(1, $u->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->withToken($one)->getJson('/api/auth/me')->assertStatus(401);
    }

    public function test_a_learner_token_cannot_open_the_teacher_and_parent_routes(): void
    {
        $token = $this->learner()->createToken('mobile')->plainTextToken;

        $this->withToken($token)->getJson('/api/auth/me')->assertStatus(403)->assertJsonPath('error', 'wrong_account_type');
    }

    public function test_a_teacher_or_parent_token_cannot_open_the_learner_routes(): void
    {
        foreach (['Teacher', 'Parent'] as $i => $type) {
            $u = $this->confirmed($type, ['email' => strtolower($type).'@example.com']);
            $token = $u->createToken('mobile')->plainTextToken;
            $this->app['auth']->forgetGuards();

            foreach (['/api/learner/dashboard', '/api/learner/activities', '/api/learner/games', '/api/learner/bookshelf'] as $path) {
                $this->withToken($token)->getJson($path)->assertStatus(403)->assertJsonPath('error', 'wrong_account_type');
            }
            $this->withToken($token)->postJson('/api/learner/logout')->assertStatus(403);
            $this->withToken($token)->postJson('/api/learner/reading-preferences/font-step', ['step' => 3])->assertStatus(403);
        }
    }

    public function test_a_deactivated_account_loses_its_tokens_on_the_next_call(): void
    {
        $u = $this->confirmed('Parent', ['email' => 'mama@example.com']);
        $token = $u->createToken('mobile')->plainTextToken;
        $u->update(['status' => 'Inactive']);

        $this->withToken($token)->getJson('/api/auth/me')->assertStatus(401)->assertJsonPath('error', 'deactivated');
        $this->assertSame(0, $u->tokens()->count());
    }

    public function test_the_learner_api_still_works_for_a_learner_token(): void
    {
        $learner = $this->learner();
        $token = $learner->createToken('mobile')->plainTextToken;

        // 403 diagnostic_required is the Learner API answering (not the actor fence): the token is accepted.
        $this->withToken($token)->getJson('/api/learner/dashboard')->assertStatus(403)->assertJsonPath('error', 'diagnostic_required');
    }

    public function test_no_token_means_401_not_a_redirect(): void
    {
        $this->getJson('/api/auth/me')->assertStatus(401);
        $this->getJson('/api/learner/dashboard')->assertStatus(401);
    }
}
