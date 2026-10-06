<?php

namespace Tests\Feature;

use App\Models\ActivityAssignment;
use App\Models\Learner;
use App\Models\ParentAccount;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use App\Support\LearnerCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * The October 2026 revision round, part one: signing in and signing up. One log in page for
 * everyone, the new learner code with its check digit, the grades a Teacher handles (one grade or
 * multigrade, enforced on the server), and the Parent's add-a-child form (no favourite colour or
 * learning style, "Not sure" counted as no signal).
 */
class AccessRevisionTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function teacher(?array $grades = null): array
    {
        $n = ++$this->seq;
        $user = User::create(['first_name' => 'Ana', 'last_name' => "Reyes{$n}", 'email' => "t{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        $user->forceFill(['email_verified_at' => now()])->save();
        $teacher = Teacher::create(['user_id' => $user->id, 'school_name' => 'Rizal ES', 'employee_id' => "E{$n}", 'status' => 'Active', 'grades_handled' => $grades]);

        return [$user, $teacher];
    }

    private function learner(array $o = []): Learner
    {
        $n = ++$this->seq;

        return Learner::create($o + ['learner_code' => LearnerCode::generate(), 'first_name' => "Kid{$n}", 'last_name' => 'Cruz', 'grade_level' => 'Grade 1', 'pin' => '1234', 'avatar_id' => 'A', 'mastery_level' => 'Developing']);
    }

    // -------------------------------------------------------------- the learner code

    public function test_a_new_code_has_the_right_shape_and_a_check_digit_that_catches_a_typing_mistake(): void
    {
        $code = LearnerCode::generate(2026);

        $this->assertMatchesRegularExpression('/^TB26-\d{5}$/', $code);
        $this->assertTrue(LearnerCode::checkDigitOk($code));

        $digits = substr($code, -5);
        $wrong = substr($code, 0, -2).(((int) $digits[3] + 1) % 10).$digits[4];
        $this->assertFalse(LearnerCode::checkDigitOk($wrong), 'a changed digit must fail the check');
        $this->assertTrue(LearnerCode::looksLikeCode($wrong), 'it still has the shape of a code');

        // Two known values: the check digit is a Luhn digit of the year and four random digits.
        $this->assertSame('3', LearnerCode::checkDigit('264829'));
        $this->assertTrue(LearnerCode::checkDigitOk('TB26-48293'));
        $this->assertFalse(LearnerCode::checkDigitOk('TB26-48294'));
    }

    public function test_codes_carry_no_personal_information_and_are_unique(): void
    {
        // Each code is saved as it is made, the way real children are: the generator only avoids
        // codes that already exist, so 40 unsaved draws from 10,000 could repeat by chance.
        $codes = collect(range(1, 40))->map(function ($i) {
            $code = LearnerCode::generate();
            \App\Models\Learner::create([
                'learner_code' => $code, 'first_name' => 'Kid'.$i, 'last_name' => 'Test', 'grade_level' => 'Grade 1',
                'pin' => '1234', 'avatar_id' => 'A',
            ]);

            return $code;
        });
        $this->assertSame($codes->count(), $codes->unique()->count());
        $this->assertTrue($codes->every(fn ($c) => preg_match('/^TB\d{2}-\d{5}$/', $c) === 1));
    }

    public function test_what_people_type_is_understood_for_new_and_old_codes(): void
    {
        $this->assertSame('TB26-48293', LearnerCode::normalize('tb2648293'));
        $this->assertSame('TB26-48293', LearnerCode::normalize(' TB26 48293 '));
        $this->assertSame('TB26-48293', LearnerCode::normalize('TB26-48293'));
        $this->assertSame('TB-12345', LearnerCode::normalize('tb12345'));
        $this->assertSame('TB-12345', LearnerCode::normalize('TB-12345'));
        $this->assertTrue(LearnerCode::looksLikeCode('tb26 48293'));
        $this->assertTrue(LearnerCode::looksLikeCode('TB-ABCDE'));
        $this->assertFalse(LearnerCode::looksLikeCode('ana@example.com'));
        $this->assertFalse(LearnerCode::looksLikeCode('TB26-4'));
        // An old code has no check digit, so it always passes that check.
        $this->assertTrue(LearnerCode::checkDigitOk('TB-12345'));
    }

    public function test_new_learners_get_the_new_code_and_old_codes_keep_working(): void
    {
        $this->assertMatchesRegularExpression('/^TB\d{2}-\d{5}$/', Learner::generateUniqueCode());

        $old = $this->learner(['learner_code' => 'TB-12345']);
        $new = $this->learner(['learner_code' => 'TB26-48293']);

        $this->post(route('learner.login.submit'), ['learner_code' => 'tb12345', 'pin' => '1234'])->assertRedirect();
        $this->assertAuthenticatedAs($old, 'learner');
        $this->post(route('learner.logout'));
        $this->post(route('learner.login.submit'), ['learner_code' => 'TB2648293', 'pin' => '1234'])->assertRedirect();
        $this->assertAuthenticatedAs($new, 'learner');
    }

    // -------------------------------------------------------------- one log in page

    public function test_one_login_page_serves_adults_and_learners(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Email or learner code')->assertSee('Forgot password?');
        // The child's own page keeps its look (the children holding letters) and gains a speaker.
        $this->get(route('learner.login'))->assertOk()->assertSee('Type your code, then your secret PIN.')->assertSee('animations/tarabasa-learner-login-hero.json', false)->assertSee('data-speak', false);
        $this->get(route('landing'))->assertOk()->assertSee('Sign up')->assertDontSee('Get Started')->assertDontSee("I'm a");
    }

    public function test_a_learner_code_and_pin_typed_in_the_general_form_sign_the_child_straight_in(): void
    {
        $kid = $this->learner(['learner_code' => 'TB26-48293']);
        // A finished first reading check, so the child goes to the dashboard and not the check.
        $activity = \App\Models\Activity::create(['grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'competency_label' => 'Foundational Reading', 'activity_type' => 'word_reading', 'difficulty_tier' => 'Easy', 'title' => 'Check', 'instructions' => 'x', 'passage_text' => 'cat', 'word_count' => 1, 'status' => 'Approved', 'purpose' => 'diagnostic']);
        \App\Models\ReadingSession::create(['learner_id' => $kid->id, 'activity_id' => $activity->id, 'session_type' => 'Diagnostic', 'initiated_by' => 'Teacher', 'accuracy_percent' => 80]);

        $this->post(route('login.submit'), ['email' => 'tb26 48293', 'password' => '1234'])->assertRedirect(route('learner.dashboard'));
        $this->assertAuthenticatedAs($kid, 'learner');

        // Logging out sends the child to their own login page.
        $this->post(route('learner.logout'))->assertRedirect(route('learner.login'));
        $this->assertGuest('learner');

        // A wrong PIN gives the same message as the child's own page; nobody is signed in.
        $this->from(route('login'))->post(route('login.submit'), ['email' => 'TB26-48293', 'password' => '9999'])->assertSessionHasErrors('pin');
        $this->assertSame('Incorrect code or PIN.', session('errors')->first('pin'));
        $this->assertGuest('learner');
        $this->assertGuest();
    }

    public function test_a_wrong_code_and_a_wrong_pin_say_exactly_the_same_thing_and_lock_out_the_same_way(): void
    {
        $kid = $this->learner(['learner_code' => 'TB26-48293']);

        $unknownCode = $this->from(route('learner.login'))->post(route('learner.login.submit'), ['learner_code' => 'TB26-11111', 'pin' => '0000']);
        $wrongPin = $this->from(route('learner.login'))->post(route('learner.login.submit'), ['learner_code' => 'TB26-48293', 'pin' => '0000']);

        $unknownCode->assertSessionHasErrors('pin');
        $wrongPin->assertSessionHasErrors('pin');
        $this->assertSame(session('errors')->first('pin'), 'Incorrect code or PIN.');

        $this->assertGuest('learner');

        // Five wrong tries lock the code out for fifteen minutes, with the correct PIN too.
        RateLimiter::clear('learner-login:TB26-48293|127.0.0.1');
        for ($i = 0; $i < 5; $i++) {
            $this->from(route('learner.login'))->post(route('learner.login.submit'), ['learner_code' => 'TB26-48293', 'pin' => '9999']);
        }
        $this->from(route('learner.login'))->post(route('learner.login.submit'), ['learner_code' => 'TB26-48293', 'pin' => '1234'])
            ->assertSessionHasErrors('pin');
        $this->assertStringContainsString('Too many attempts', session('errors')->first('pin'));
        $this->assertGuest('learner');
        $this->assertNotNull($kid->fresh());
    }

    public function test_adult_login_is_unchanged(): void
    {
        [$user] = $this->teacher();

        $this->post(route('login.submit'), ['email' => $user->email, 'password' => 'Passw0rd!', 'role' => 'teacher'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    // -------------------------------------------------------------- Teacher grades

    private function teacherForm(array $o = []): array
    {
        return $o + [
            'first_name' => 'Pinky', 'last_name' => 'Reyes', 'email' => 'pinky@example.com', 'password' => 'Passw0rd!', 'password_confirmation' => 'Passw0rd!',
            'school_name' => 'Rizal ES', 'employee_id' => 'EMP-1', 'grades_mode' => 'single', 'grades_handled' => ['Grade 2'],
        ];
    }

    public function test_a_teacher_must_say_which_grades_they_handle_and_it_is_saved(): void
    {
        $this->from(route('register.teacher'))->post(route('register.teacher.submit'), $this->teacherForm(['grades_mode' => null, 'grades_handled' => null]))
            ->assertSessionHasErrors(['grades_mode', 'grades_handled']);

        $this->post(route('register.teacher.submit'), $this->teacherForm());
        $this->assertSame(['Grade 2'], Teacher::first()->grades_handled);
    }

    public function test_one_grade_means_exactly_one_and_multigrade_means_two_or_more(): void
    {
        $this->from(route('register.teacher'))->post(route('register.teacher.submit'), $this->teacherForm(['grades_mode' => 'single', 'grades_handled' => ['Grade 1', 'Grade 2']]))
            ->assertSessionHasErrors('grades_handled');
        $this->from(route('register.teacher'))->post(route('register.teacher.submit'), $this->teacherForm(['grades_mode' => 'multi', 'grades_handled' => ['Grade 3']]))
            ->assertSessionHasErrors('grades_handled');
        $this->from(route('register.teacher'))->post(route('register.teacher.submit'), $this->teacherForm(['grades_handled' => ['Grade 9']]))
            ->assertSessionHasErrors('grades_handled.0');
        $this->assertSame(0, Teacher::count());

        $this->post(route('register.teacher.submit'), $this->teacherForm(['email' => 'multi@example.com', 'employee_id' => 'EMP-2', 'grades_mode' => 'multi', 'grades_handled' => ['Grade 1', 'Grade 3']]));
        $this->assertSame(['Grade 1', 'Grade 3'], Teacher::first()->grades_handled);
    }

    public function test_a_single_grade_teacher_cannot_open_a_class_for_another_grade_even_with_a_forged_request(): void
    {
        [$user, $teacher] = $this->teacher(['Grade 2']);
        $this->actingAs($user);
        $year = SchoolClass::currentSchoolYear();
        $payload = fn (string $grade) => ['name' => 'Sampaguita', 'section' => 'A', 'grade_level' => $grade, 'school_year' => $year];

        $this->post(route('teacher.classes.store'), $payload('Grade 2'))->assertRedirect();
        $this->assertSame(1, SchoolClass::count());

        $this->from('/x')->post(route('teacher.classes.store'), $payload('Grade 1'))->assertSessionHasErrors('grade_level');
        $this->from('/x')->post(route('teacher.classes.store'), $payload('Grade 3'))->assertSessionHasErrors('grade_level');
        $this->assertSame(1, SchoolClass::count());

        $window = $this->get(route('teacher.classes.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('<option >Grade 1</option>', $window);
    }

    public function test_a_multigrade_teacher_can_open_classes_for_their_grades_only(): void
    {
        [$user] = $this->teacher(['Grade 1', 'Grade 2']);
        $this->actingAs($user);
        $year = SchoolClass::currentSchoolYear();
        $payload = fn (string $grade) => ['name' => "C {$grade}", 'section' => 'A', 'grade_level' => $grade, 'school_year' => $year];

        $this->post(route('teacher.classes.store'), $payload('Grade 1'))->assertRedirect();
        $this->post(route('teacher.classes.store'), $payload('Grade 2'))->assertRedirect();
        $this->from('/x')->post(route('teacher.classes.store'), $payload('Grade 3'))->assertSessionHasErrors('grade_level');
        $this->assertSame(2, SchoolClass::count());
    }

    public function test_changing_grades_in_profile_limits_new_classes_but_an_old_class_can_still_be_edited(): void
    {
        [$user, $teacher] = $this->teacher(['Grade 1', 'Grade 2']);
        $this->actingAs($user);
        $class = SchoolClass::create(['teacher_id' => $teacher->id, 'name' => 'Old', 'grade_level' => 'Grade 2', 'section' => 'A', 'school_year' => SchoolClass::currentSchoolYear()]);

        $this->put(route('profile.grades.update'), ['grades_mode' => 'single', 'grades_handled' => ['Grade 1']])->assertSessionHas('status');
        $this->assertSame(['Grade 1'], $teacher->fresh()->grades_handled);

        $this->put(route('teacher.classes.update', $class), ['name' => 'Renamed', 'section' => 'A', 'grade_level' => 'Grade 2', 'group_tag' => ''])->assertRedirect();
        $this->assertSame('Renamed', $class->fresh()->name);
        $this->from('/x')->put(route('teacher.classes.update', $class), ['name' => 'Renamed', 'section' => 'A', 'grade_level' => 'Grade 3', 'group_tag' => ''])->assertSessionHasErrors('grade_level');

        $this->from('/x')->put(route('profile.grades.update'), ['grades_mode' => 'single', 'grades_handled' => ['Grade 1', 'Grade 2']])->assertSessionHasErrors('grades_handled');
    }

    public function test_a_teacher_made_before_grades_were_asked_can_open_any_grade(): void
    {
        [$user, $teacher] = $this->teacher(null);

        $this->assertSame(['Grade 1', 'Grade 2', 'Grade 3'], $teacher->gradesAllowed());
    }

    public function test_an_activity_can_be_assigned_to_a_class_that_has_no_learners_yet_and_a_learner_who_joins_later_gets_it(): void
    {
        [$user, $teacher] = $this->teacher();
        $class = SchoolClass::create(['teacher_id' => $teacher->id, 'name' => 'Empty', 'grade_level' => 'Grade 1', 'section' => 'A', 'school_year' => SchoolClass::currentSchoolYear()]);
        $activity = \App\Models\Activity::create(['created_by_teacher_id' => $teacher->id, 'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'competency_label' => 'Foundational Reading', 'activity_type' => 'word_reading', 'difficulty_tier' => 'Easy', 'ai_difficulty_tier' => 'Easy', 'title' => 'Words', 'instructions' => 'Read.', 'passage_text' => 'cat dog', 'word_count' => 2, 'status' => 'Approved']);
        $this->actingAs($user);

        $this->post(route('teacher.classes.assign-activity', $class), ['activity_id' => $activity->id])->assertRedirect();
        $this->assertSame(1, ActivityAssignment::where('class_id', $class->id)->count());

        $kid = $this->learner(['learner_code' => 'TB26-48293']);
        $this->post(route('teacher.classes.join-learner', $class), ['learner_code' => '48293'])->assertRedirect();
        $options = app(\App\Services\LearnerAuthService::class)->findActivityOptions($kid->fresh());
        $this->assertSame([$activity->id], $options->pluck('activity.id')->all());
    }

    public function test_a_teacher_joins_a_learner_with_the_last_five_characters_and_is_asked_for_the_whole_code_when_two_end_alike(): void
    {
        [$user, $teacher] = $this->teacher();
        $class = SchoolClass::create(['teacher_id' => $teacher->id, 'name' => 'Rizal', 'grade_level' => 'Grade 1', 'section' => 'A', 'school_year' => SchoolClass::currentSchoolYear()]);
        $a = $this->learner(['learner_code' => 'TB26-48293']);
        $b = $this->learner(['learner_code' => 'TB-48293']);
        $this->actingAs($user);
        $url = route('teacher.classes.join-learner', $class);

        $this->from('/x')->post($url, ['learner_code' => '48293'])->assertSessionHasErrors(['learner_code' => 'More than one learner ends in those characters. Type the whole code, like TB26-48293.']);
        $this->assertNull($a->fresh()->class_id);
        $this->assertNull($b->fresh()->class_id);

        $this->post($url, ['learner_code' => 'tb2648293'])->assertRedirect();
        $this->assertSame($class->id, $a->fresh()->class_id);
        $this->assertNull($b->fresh()->class_id);
    }

    // -------------------------------------------------------------- Parent: add a child

    private function parent(): User
    {
        $user = User::create(['first_name' => 'Carla', 'last_name' => 'Domingo', 'email' => 'p@example.com', 'password' => 'Passw0rd!', 'user_type' => 'Parent']);
        $user->forceFill(['email_verified_at' => now()])->save();
        ParentAccount::create(['user_id' => $user->id]);

        return $user;
    }

    private function childForm(array $o = []): array
    {
        return $o + [
            'first_name' => 'Maria', 'last_name' => 'Perez', 'grade_level' => 'Grade 1', 'avatar_id' => '🦁', 'reading_stage' => 'letters',
            'q1' => 'yes', 'q2' => 'no', 'q3' => 'no', 'pin' => '1234', 'pin_confirmation' => '1234',
        ];
    }

    public function test_the_add_a_child_form_no_longer_asks_for_a_colour_or_a_learning_style(): void
    {
        $user = $this->parent();
        $this->actingAs($user);

        $page = $this->get(route('parent.children.create'))->assertOk();
        $page->assertSee('Language spoken at home')->assertSee('Cannot yet recognize letters')->assertSee('Hearing the words read aloud')
            ->assertDontSee('Favorite color')->assertDontSee('Learning style')->assertDontSee('Visual')->assertDontSee('Hands-on');

        $this->post(route('parent.children.store'), $this->childForm([
            'home_language' => 'Cebuano', 'supports' => ['read_aloud', 'games'], 'interests' => ['animals'],
        ]))->assertOk()->assertSee('Maria is all set')->assertSee('Check digit');

        $kid = Learner::firstWhere('first_name', 'Maria');
        $this->assertMatchesRegularExpression('/^TB\d{2}-\d{5}$/', $kid->learner_code);
        $this->assertTrue(LearnerCode::checkDigitOk($kid->learner_code));
        $this->assertSame('Cebuano', $kid->home_language);
        $this->assertSame(['read_aloud', 'games'], $kid->supports);
        $this->assertSame(['animals'], $kid->interests);
        $this->assertNull($kid->learning_style);
        $this->assertSame(['q1' => 'yes', 'q2' => 'no', 'q3' => 'no'], $kid->placement_answers);
    }

    public function test_not_sure_is_no_signal_not_a_wrong_answer(): void
    {
        $this->actingAs($this->parent());

        $this->post(route('parent.children.store'), $this->childForm(['first_name' => 'Allunsure', 'q1' => 'unsure', 'q2' => 'unsure', 'q3' => 'unsure', 'reading_stage' => 'unsure']))->assertOk();
        $unsure = Learner::firstWhere('first_name', 'Allunsure');
        $this->assertSame('Developing', $unsure->mastery_level, 'no answers at all: the neutral level, the first check decides');

        $this->post(route('parent.children.store'), $this->childForm(['first_name' => 'Allno', 'q1' => 'no', 'q2' => 'no', 'q3' => 'no']))->assertOk();
        $this->assertSame('Beginning', Learner::firstWhere('first_name', 'Allno')->mastery_level);

        $this->post(route('parent.children.store'), $this->childForm(['first_name' => 'Mixed', 'q1' => 'yes', 'q2' => 'yes', 'q3' => 'unsure']))->assertOk();
        $this->assertSame('Developing', Learner::firstWhere('first_name', 'Mixed')->mastery_level);

        // The first reading check starts nowhere lower than the Parent's own words when every
        // question was "Not sure".
        $this->assertSame('phonics_easy', \App\Support\DiagnosticPlacement::startingRung(Learner::firstWhere('first_name', 'Mixed')->setAttribute('reading_stage', 'letters')));
        $allUnsure = $unsure->setAttribute('reading_stage', 'blending');
        $this->assertSame(config('diagnostic.stage_start.blending'), array_search(\App\Support\DiagnosticPlacement::startingRung($allUnsure), \App\Support\DiagnosticPlacement::ladder()));
    }

    public function test_the_form_rejects_unknown_languages_supports_and_answers(): void
    {
        $this->actingAs($this->parent());

        $this->from('/x')->post(route('parent.children.store'), $this->childForm(['home_language' => 'Klingon']))->assertSessionHasErrors('home_language');
        $this->from('/x')->post(route('parent.children.store'), $this->childForm(['supports' => ['mind_reading']]))->assertSessionHasErrors('supports.0');
        $this->from('/x')->post(route('parent.children.store'), $this->childForm(['q1' => 'maybe']))->assertSessionHasErrors('q1');
        $this->assertSame(0, Learner::count());
    }
}
