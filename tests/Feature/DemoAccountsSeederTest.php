<?php

namespace Tests\Feature;

use App\Models\Learner;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\DemoAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The practice accounts for trying the apps without a real email address (database/seeders/DemoAccountsSeeder.php).
 */
class DemoAccountsSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        unset($_SERVER['DEMO_ACCOUNTS_PASSWORD'], $_ENV['DEMO_ACCOUNTS_PASSWORD']);

        parent::tearDown();
    }

    public function test_the_demo_teacher_and_parent_exist_already_confirmed_and_sign_in_on_the_phone_api(): void
    {
        $this->seed(DemoAccountsSeeder::class);

        $teacher = User::where('email', 'demo.teacher@example.com')->firstOrFail();
        $this->assertNotNull($teacher->email_verified_at, 'no email is needed to sign in');
        $this->assertSame('Active', $teacher->teacher->status, 'already approved, so every action works');
        $this->assertSame(['Grade 1', 'Grade 2'], $teacher->teacher->grades_handled);

        foreach (['teacher' => 'demo.teacher@example.com', 'parent' => 'demo.parent@example.com'] as $role => $email) {
            $this->app['auth']->forgetGuards();
            $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'Demo1234!', 'role' => $role])
                ->assertOk()->assertJsonPath('account.role', $role);
        }
    }

    public function test_the_demo_child_is_linked_to_the_parent_in_the_teachers_class_and_signs_in_with_pin_1234(): void
    {
        $this->seed(DemoAccountsSeeder::class);

        $parent = User::where('email', 'demo.parent@example.com')->firstOrFail();
        $child = $parent->parentProfile->learners()->firstOrFail();
        $class = SchoolClass::where('teacher_id', User::where('email', 'demo.teacher@example.com')->firstOrFail()->teacher->id)->firstOrFail();

        $this->assertSame($class->id, $child->class_id);
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/learner/login', ['learner_code' => $child->learner_code, 'pin' => '1234'])->assertOk();
    }

    public function test_running_it_again_resets_the_passwords_but_adds_no_second_class_or_child(): void
    {
        $this->seed(DemoAccountsSeeder::class);
        User::where('email', 'demo.teacher@example.com')->update(['password' => 'changed-by-somebody']);

        $this->seed(DemoAccountsSeeder::class);

        $this->assertSame(1, SchoolClass::count());
        $this->assertSame(1, Learner::count());
        $this->assertSame(1, Teacher::count());
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/login', ['email' => 'demo.teacher@example.com', 'password' => 'Demo1234!', 'role' => 'teacher'])->assertOk();
    }

    public function test_on_a_production_server_it_refuses_unless_a_long_password_is_set(): void
    {
        // Calling the seeder directly: `db:seed` itself asks "are you sure?" on a production server, which a test cannot answer.
        $this->app->detectEnvironment(fn () => 'production');

        app(DemoAccountsSeeder::class)->run();
        $this->assertSame(0, User::count(), 'the public default password must never reach a live server');

        $_SERVER['DEMO_ACCOUNTS_PASSWORD'] = 'short';
        app(DemoAccountsSeeder::class)->run();
        $this->assertSame(0, User::count());

        $_SERVER['DEMO_ACCOUNTS_PASSWORD'] = 'a-long-private-password-1';
        app(DemoAccountsSeeder::class)->run();
        $this->assertSame(2, User::count());
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/login', ['email' => 'demo.parent@example.com', 'password' => 'Demo1234!', 'role' => 'parent'])->assertStatus(401);
    }
}
