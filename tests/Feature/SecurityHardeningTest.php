<?php

namespace Tests\Feature;

use App\Models\Learner;
use App\Models\Notification;
use App\Models\ParentAccount;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use App\Support\LearnerCode;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Fixes that came out of the system audit. Each one closes a way for a stranger, or a user with an
 * ordinary account, to see or reach something they should not.
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function parent(string $email): array
    {
        $u = User::create(['first_name' => 'Pat', 'last_name' => 'Parent'.(++$this->seq), 'email' => $email, 'password' => 'Passw0rd!', 'user_type' => 'Parent']);
        $u->forceFill(['email_verified_at' => now()])->save();

        return [$u, ParentAccount::create(['user_id' => $u->id])];
    }

    private function child(array $o = []): Learner
    {
        return Learner::create($o + [
            'learner_code' => LearnerCode::generate(), 'first_name' => 'Secretname', 'last_name' => 'Cruz'.(++$this->seq), 'grade_level' => 'Grade 1',
            'pin' => '4321', 'avatar_id' => 'A', 'mastery_level' => 'Developing',
        ]);
    }

    // ------------------------------------------------------------- linking an existing child

    public function test_the_link_page_shows_nothing_about_a_child_from_a_code_alone(): void
    {
        [$stranger] = $this->parent('stranger@example.com');
        $kid = $this->child();

        $this->actingAs($stranger)->get(route('parent.children.link', ['code' => $kid->learner_code]))
            ->assertOk()->assertDontSee('Secretname')->assertDontSee($kid->last_name)->assertSee("Child's PIN", false);
    }

    public function test_a_code_without_the_pin_links_nothing_and_a_wrong_pin_looks_like_an_unknown_code(): void
    {
        [$stranger, $strangerProfile] = $this->parent('stranger@example.com');
        $kid = $this->child();
        $this->actingAs($stranger);

        $this->post(route('parent.children.link.submit'), ['learner_code' => $kid->learner_code, 'relationship' => 'Guardian'])
            ->assertSessionHasErrors('pin');

        $wrongPin = $this->post(route('parent.children.link.submit'), ['learner_code' => $kid->learner_code, 'pin' => '0000', 'relationship' => 'Guardian']);
        $noSuchCode = $this->post(route('parent.children.link.submit'), ['learner_code' => 'TB26-00000', 'pin' => '0000', 'relationship' => 'Guardian']);
        $wrongPin->assertSessionHasErrors('learner_code');
        $noSuchCode->assertSessionHasErrors('learner_code');
        $this->assertSame(session('errors')->first('learner_code'), $wrongPin->baseResponse->getSession()->get('errors')->first('learner_code'));
        $this->assertSame(
            $wrongPin->baseResponse->getSession()->get('errors')->first('learner_code'),
            $noSuchCode->baseResponse->getSession()->get('errors')->first('learner_code'),
            'the message must not say whether the code or the PIN was the wrong half'
        );

        $this->assertSame(0, $strangerProfile->learners()->count());
    }

    public function test_a_parent_who_keeps_guessing_is_locked_out_even_for_the_right_pin(): void
    {
        [$stranger, $profile] = $this->parent('stranger@example.com');
        $kid = $this->child();
        $this->actingAs($stranger);

        foreach (range(1, 5) as $i) {
            $this->post(route('parent.children.link.submit'), ['learner_code' => $kid->learner_code, 'pin' => sprintf('%04d', $i), 'relationship' => 'Guardian']);
        }

        // The sixth try has the right PIN and is still refused: guessing is what is being stopped.
        $this->post(route('parent.children.link.submit'), ['learner_code' => $kid->learner_code, 'pin' => '4321', 'relationship' => 'Guardian'])
            ->assertSessionHasErrors('learner_code');
        $this->assertStringContainsString('Too many tries', session('errors')->first('learner_code'));
        $this->assertSame(0, $profile->learners()->count());
    }

    public function test_guessing_across_many_parent_accounts_is_stopped_per_code(): void
    {
        $kid = $this->child();
        foreach (range(1, 5) as $i) {
            [$u] = $this->parent("guesser{$i}@example.com");
            $this->actingAs($u)->post(route('parent.children.link.submit'), ['learner_code' => $kid->learner_code, 'pin' => sprintf('%04d', $i), 'relationship' => 'Guardian']);
        }

        // A brand new account, with the right PIN, is still refused while the code is being attacked.
        [$late, $profile] = $this->parent('late@example.com');
        $this->actingAs($late)->post(route('parent.children.link.submit'), ['learner_code' => $kid->learner_code, 'pin' => '4321', 'relationship' => 'Guardian'])
            ->assertSessionHasErrors('learner_code');
        $this->assertSame(0, $profile->learners()->count());
    }

    public function test_the_right_code_and_pin_link_the_child_and_tell_the_guardian_who_was_already_there(): void
    {
        [$first, $firstProfile] = $this->parent('first@example.com');
        [$second, $secondProfile] = $this->parent('second@example.com');
        $kid = $this->child();
        $firstProfile->learners()->attach($kid->id, ['relationship' => 'Mother', 'is_creator' => true]);

        $this->actingAs($second)->post(route('parent.children.link.submit'), ['learner_code' => strtolower($kid->learner_code), 'pin' => '4321', 'relationship' => 'Father'])
            ->assertRedirect(route('parent.children.index'));

        $this->assertSame(1, $secondProfile->learners()->count());
        $note = Notification::where('recipient_user_id', $first->id)->where('type', Notification::TYPE_GUARDIAN_LINKED)->first();
        $this->assertNotNull($note, 'the first guardian must be told');
        $this->assertStringContainsString($second->first_name, $note->message);
        $this->assertSame(0, Notification::where('recipient_user_id', $second->id)->count(), 'the new guardian is not told about themself');

        // And the parent's alerts screen can show that message.
        $this->actingAs($first)->get(route('parent.notifications.index'))->assertOk()->assertSee('Guardian Linked');
    }

    public function test_a_new_format_code_fits_in_the_box(): void
    {
        [$parent] = $this->parent('p@example.com');
        $html = $this->actingAs($parent)->get(route('parent.children.link'))->getContent();
        preg_match('/id="learner_code"[^>]*maxlength="(\d+)"/', $html, $m);
        $this->assertGreaterThanOrEqual(strlen('TB26-48293'), (int) ($m[1] ?? 0), 'the box used to stop at 8 characters, so a new code could not be typed');
    }

    // ------------------------------------------------------------- a teacher joining a learner by code

    private function activeTeacher(): array
    {
        $u = User::create(['first_name' => 'Tess', 'last_name' => 'Teacher', 'email' => 'tt@example.com', 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        $u->forceFill(['email_verified_at' => now()])->save();
        $t = Teacher::create(['user_id' => $u->id, 'school_name' => 'Rizal ES', 'employee_id' => 'E1', 'status' => 'Active']);
        $c = SchoolClass::create(['teacher_id' => $t->id, 'name' => 'Kamunggay', 'grade_level' => 'Grade 1', 'section' => 'A', 'school_year' => SchoolClass::currentSchoolYear()]);

        return [$u, $t, $c];
    }

    public function test_joining_a_learner_tells_the_childs_guardians(): void
    {
        [$u, $t, $c] = $this->activeTeacher();
        [$parentUser, $profile] = $this->parent('mom@example.com');
        $kid = $this->child();
        $profile->learners()->attach($kid->id, ['relationship' => 'Mother', 'is_creator' => true]);

        $this->actingAs($u)->post(route('teacher.classes.join-learner', $c), ['learner_code' => substr($kid->learner_code, -5)])->assertRedirect();

        $this->assertSame($c->id, $kid->fresh()->class_id);
        $note = Notification::where('recipient_user_id', $parentUser->id)->where('type', Notification::TYPE_CLASS_JOINED)->first();
        $this->assertNotNull($note);
        $this->assertStringContainsString('Kamunggay', $note->message);
        $this->assertStringContainsString('Rizal ES', $note->message);
    }

    public function test_a_teacher_who_keeps_trying_codes_that_do_not_match_is_slowed_down(): void
    {
        [$u, $t, $c] = $this->activeTeacher();
        RateLimiter::clear('join-learner|teacher:'.$t->id);
        $this->actingAs($u);

        foreach (range(10000, 10009) as $n) {
            $this->post(route('teacher.classes.join-learner', $c), ['learner_code' => (string) $n]);
        }

        $kid = $this->child();
        $this->post(route('teacher.classes.join-learner', $c), ['learner_code' => substr($kid->learner_code, -5)])
            ->assertSessionHasErrors('learner_code');
        $this->assertStringContainsString('Too many codes', session('errors')->first('learner_code'));
        $this->assertNull($kid->fresh()->class_id, 'even the right code is refused while guesses are being made');
    }

    // ------------------------------------------------------------- the Admin account

    public function test_no_admin_password_is_written_in_the_code_and_nothing_is_created_without_one(): void
    {
        putenv('ADMIN_PASSWORD'); // unset
        unset($_ENV['ADMIN_PASSWORD'], $_SERVER['ADMIN_PASSWORD']);

        (new AdminSeeder())->run();
        $this->assertSame(0, User::where('user_type', 'Admin')->count(), 'without ADMIN_PASSWORD the seeder makes no Admin');

        $this->assertStringNotContainsString('AdminPass123!', file_get_contents(base_path('database/seeders/AdminSeeder.php')));
        $this->assertSame(0, Artisan::call('admin:sync'), 'with no password set the command leaves things as they are');
        $this->assertSame(0, User::where('user_type', 'Admin')->count());
    }

    public function test_admin_sync_sets_the_admin_from_the_setting_and_refuses_weak_or_published_passwords(): void
    {
        $set = function (?string $password) {
            foreach (['putenv', '_ENV', '_SERVER'] as $_) {
            }
            putenv($password === null ? 'ADMIN_PASSWORD' : "ADMIN_PASSWORD={$password}");
            $_ENV['ADMIN_PASSWORD'] = $_SERVER['ADMIN_PASSWORD'] = $password;
        };

        $set('AdminPass123!');
        $this->assertSame(1, Artisan::call('admin:sync'), 'the published password is refused');
        $set('short');
        $this->assertSame(1, Artisan::call('admin:sync'), 'a short password is refused');
        $this->assertSame(0, User::where('user_type', 'Admin')->count());

        $set('a-long-private-password-1');
        $this->assertSame(0, Artisan::call('admin:sync'));
        $admin = User::where('user_type', 'Admin')->first();
        $this->assertNotNull($admin);
        $this->assertTrue(Hash::check('a-long-private-password-1', $admin->password));

        // Changing the setting changes the password (and never makes a second Admin).
        $set('another-long-private-password-2');
        Artisan::call('admin:sync');
        $this->assertSame(1, User::where('user_type', 'Admin')->count());
        $this->assertTrue(Hash::check('another-long-private-password-2', User::where('user_type', 'Admin')->first()->password));

        $set(null);
    }

    public function test_the_admin_dashboard_warns_loudly_while_the_published_password_is_still_in_use(): void
    {
        $admin = User::create(['first_name' => 'T', 'last_name' => 'A', 'email' => 'a@example.com', 'password' => 'AdminPass123!', 'user_type' => 'Admin']);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('still uses the password that was written in the code')->assertSee('ADMIN_PASSWORD');

        $admin->forceFill(['password' => 'a-private-and-long-one-9'])->save();
        $this->get(route('admin.dashboard'))->assertOk()->assertDontSee('still uses the password');
    }

    public function test_the_admin_accounts_screen_lists_a_page_at_a_time_and_can_search_for_the_rest(): void
    {
        $admin = User::create(['first_name' => 'T', 'last_name' => 'A', 'email' => 'a@example.com', 'password' => 'x-long-private-password', 'user_type' => 'Admin']);
        foreach (range(1, 130) as $i) {
            User::create(['first_name' => "Person{$i}", 'last_name' => 'Lister', 'email' => "p{$i}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Parent']);
        }

        $this->actingAs($admin);
        $page = $this->get(route('admin.accounts'))->assertOk();
        $page->assertSee('Showing 1 to 25 of 130')->assertSee('Search by name or email');
        $this->assertSame(25, substr_count($page->getContent(), '<tr class='), 'a page shows 25 accounts, never every account');
        $this->get(route('admin.accounts', ['page' => 6]))->assertOk()->assertSee('Showing 126 to 130 of 130');

        $this->get(route('admin.accounts', ['q' => 'Person7@']))->assertOk(); // junk-ish search does not crash
        $this->get(route('admin.accounts', ['q' => 'p7@example']))->assertOk()->assertSee('Person7');
        $this->get(route('admin.accounts', ['q' => ['x']]))->assertStatus(400); // a list in the address is refused everywhere
    }

    public function test_a_visitor_who_is_not_signed_in_cannot_tell_which_activity_numbers_exist(): void
    {
        [, $teacher] = $this->activeTeacher();
        $activity = \App\Models\Activity::create([
            'created_by_teacher_id' => $teacher->id, 'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'competency_label' => 'Foundational Reading',
            'activity_type' => 'word_reading', 'difficulty_tier' => 'Easy', 'ai_difficulty_tier' => 'Easy', 'title' => 'Words', 'instructions' => 'Read.',
            'passage_text' => 'cat dog', 'word_count' => 2, 'status' => 'Approved',
        ]);

        foreach (['/learner/activity/%s', '/learner/bookshelf/%s/reread'] as $pattern) {
            // a real number and a number nobody has must answer the same way: send them to the child sign in
            $this->get(sprintf($pattern, $activity->id))->assertRedirect(route('learner.login'));
            $this->get(sprintf($pattern, 99999999))->assertRedirect(route('learner.login'));
        }
    }
}
