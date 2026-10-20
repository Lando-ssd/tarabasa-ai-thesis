<?php

namespace Tests\Feature;

use App\Models\Learner;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ReadingProgression;
use App\Support\LearnerCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The Add learner window asks the server as a code is typed, and keeps its Add button off until the code can really be added to the class.
 * Only the answer is tested here (the window's script and the phone screen only show it); the rules are the same ones join() applies, so a
 * Grade 2 child's code in a Grade 1 class is refused with the reason, before anyone presses Add, on the website and on the phone.
 */
class LearnerCodeCheckTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    /** @return array{0: User, 1: Teacher} */
    private function teacher(array $grades, string $status = 'Active'): array
    {
        $n = ++$this->seq;
        $user = User::create(['first_name' => 'Ana', 'last_name' => "Reyes{$n}", 'email' => "lc{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        $user->forceFill(['email_verified_at' => now()])->save();
        $teacher = Teacher::create(['user_id' => $user->id, 'school_name' => 'Rizal ES', 'employee_id' => "L{$n}", 'status' => $status, 'free_generation_credits_remaining' => 2, 'grades_handled' => $grades]);

        return [$user, $teacher];
    }

    private function klass(Teacher $t, string $grade, string $name = 'Sampaguita'): SchoolClass
    {
        return SchoolClass::create(['teacher_id' => $t->id, 'name' => $name, 'grade_level' => $grade, 'section' => 'A', 'group_tag' => null, 'school_year' => SchoolClass::currentSchoolYear()]);
    }

    private function kid(?SchoolClass $c, string $grade, string $first = 'Ben', int $rung = 1): Learner
    {
        return Learner::create([
            'learner_code' => LearnerCode::generate(), 'class_id' => $c?->id, 'first_name' => $first, 'last_name' => 'Cruz'.(++$this->seq), 'grade_level' => $grade,
            'pin' => '1234', 'avatar_id' => 'A', 'mastery_level' => ReadingProgression::levelForRung($rung), 'reading_rung' => $rung,
        ]);
    }

    private function tail(Learner $l): string
    {
        return substr($l->learner_code, -5);
    }

    public function test_a_code_that_can_be_added_says_yes_and_never_names_the_child(): void
    {
        [$user, $t] = $this->teacher(['Grade 1']);
        $class = $this->klass($t, 'Grade 1');
        $kid = $this->kid(null, 'Grade 1', 'Lito');

        $json = $this->actingAs($user)->postJson(route('teacher.classes.check-learner', $class), ['learner_code' => $this->tail($kid)])->assertOk()->json();

        $this->assertTrue($json['ok']);
        $this->assertStringContainsString('can be added to Sampaguita', $json['message']);
        $this->assertStringNotContainsString('Lito', json_encode($json));
        $this->assertStringNotContainsString($kid->learner_code, json_encode($json));
        // Asking changes nothing: the child is not in the class yet.
        $this->assertNull($kid->fresh()->class_id);
    }

    public function test_a_child_of_another_grade_is_refused_before_add_is_pressed_for_a_teacher_who_handles_one_grade(): void
    {
        [$user, $t] = $this->teacher(['Grade 1']);
        $class = $this->klass($t, 'Grade 1');
        $kid = $this->kid(null, 'Grade 2', 'Ben');

        $json = $this->actingAs($user)->postJson(route('teacher.classes.check-learner', $class), ['learner_code' => $this->tail($kid)])->assertOk()->json();

        $this->assertFalse($json['ok']);
        $this->assertSame('grade', $json['reason']);
        $this->assertStringContainsString('Ben is in Grade 2, and Sampaguita is a Grade 1 class', $json['message']);
        $this->assertStringContainsString('You do not handle Grade 2', $json['message']);
        $this->assertNull($kid->fresh()->class_id);
    }

    public function test_for_a_teacher_who_handles_both_grades_it_still_says_no_and_points_to_the_right_class(): void
    {
        [$user, $t] = $this->teacher(['Grade 1', 'Grade 2']);
        $class = $this->klass($t, 'Grade 1');
        $kid = $this->kid(null, 'Grade 2', 'Ben');

        $json = $this->actingAs($user)->postJson(route('teacher.classes.check-learner', $class), ['learner_code' => $kid->learner_code])->assertOk()->json();

        $this->assertFalse($json['ok']);
        $this->assertSame('grade', $json['reason']);
        $this->assertStringContainsString('classes of different grades cannot be merged', $json['message']);
        $this->assertStringContainsString('open a new Grade 2 class', $json['message']);
    }

    public function test_the_same_code_is_still_refused_by_add_even_if_the_page_skipped_the_check(): void
    {
        [$user, $t] = $this->teacher(['Grade 1']);
        $class = $this->klass($t, 'Grade 1');
        $kid = $this->kid(null, 'Grade 2');

        $this->actingAs($user)->postJson(route('teacher.classes.join-learner', $class), ['learner_code' => $this->tail($kid)])->assertStatus(422)->assertJson(['ok' => false]);
        $this->assertNull($kid->fresh()->class_id);
    }

    public function test_unknown_malformed_enrolled_and_too_high_a_level_each_get_their_own_reason(): void
    {
        [$user, $t] = $this->teacher(['Grade 2']);
        $class = $this->klass($t, 'Grade 2', 'Dahlia');
        $other = $this->klass($t, 'Grade 2', 'Rose');
        $this->actingAs($user);
        $url = route('teacher.classes.check-learner', $class);

        $this->postJson($url, ['learner_code' => 'ab'])->assertOk()->assertJson(['ok' => false, 'reason' => 'format']);
        $this->postJson($url, ['learner_code' => '00000'])->assertOk()->assertJson(['ok' => false, 'reason' => 'unknown']);

        $enrolled = $this->kid($other, 'Grade 2', 'Mia');
        $this->postJson($url, ['learner_code' => $this->tail($enrolled)])->assertOk()->assertJson(['ok' => false, 'reason' => 'enrolled']);

        // A Grade 1 class is taught at the Instructional level, so a child who already reads at the Independent level is refused.
        [$user1, $t1] = $this->teacher(['Grade 1']);
        $g1 = $this->klass($t1, 'Grade 1', 'Sampaguita');
        $strong = $this->kid(null, 'Grade 1', 'Ria', 5);
        $this->actingAs($user1)->postJson(route('teacher.classes.check-learner', $g1), ['learner_code' => $this->tail($strong)])->assertOk()->assertJson(['ok' => false, 'reason' => 'level']);
    }

    public function test_it_cannot_be_used_to_walk_through_codes_reading_children_names(): void
    {
        [$user, $t] = $this->teacher(['Grade 1']);
        $class = $this->klass($t, 'Grade 1');
        $this->actingAs($user);
        $url = route('teacher.classes.check-learner', $class);

        // Twenty refusals that name a child (wrong grade) are allowed in an hour; the next one is turned away without looking anyone up.
        for ($i = 0; $i < 20; $i++) {
            $kid = $this->kid(null, 'Grade 2', 'Kid'.$i);
            $this->postJson($url, ['learner_code' => $this->tail($kid)])->assertOk()->assertJson(['ok' => false, 'reason' => 'grade']);
        }

        $extra = $this->kid(null, 'Grade 2', 'Extra');
        $json = $this->postJson($url, ['learner_code' => $this->tail($extra)])->assertOk()->json();
        $this->assertSame('limit', $json['reason']);
        $this->assertStringNotContainsString('Extra', $json['message']);
    }

    public function test_only_the_class_owner_who_is_active_can_ask(): void
    {
        [$owner, $t] = $this->teacher(['Grade 1']);
        $class = $this->klass($t, 'Grade 1');
        [$stranger] = $this->teacher(['Grade 1']);
        [$pending, $pendingTeacher] = $this->teacher(['Grade 1'], 'Pending');
        $pendingClass = $this->klass($pendingTeacher, 'Grade 1');
        $kid = $this->kid(null, 'Grade 1');

        $this->actingAs($stranger)->postJson(route('teacher.classes.check-learner', $class), ['learner_code' => $this->tail($kid)])->assertForbidden();
        $this->actingAs($pending)->postJson(route('teacher.classes.check-learner', $pendingClass), ['learner_code' => $this->tail($kid)])->assertForbidden();
        $this->actingAs($owner)->postJson(route('teacher.classes.check-learner', $class), [])->assertStatus(422);
    }

    public function test_the_phone_api_gives_the_same_answers(): void
    {
        [$user, $t] = $this->teacher(['Grade 1']);
        $class = $this->klass($t, 'Grade 1');
        $good = $this->kid(null, 'Grade 1');
        $bad = $this->kid(null, 'Grade 2', 'Ben');
        Sanctum::actingAs($user);
        $url = "/api/teacher/classes/{$class->id}/learners/check";

        $this->postJson($url, ['learner_code' => $this->tail($good)])->assertOk()->assertJson(['ok' => true]);

        $refused = $this->postJson($url, ['learner_code' => $this->tail($bad)])->assertOk()->assertJson(['ok' => false, 'reason' => 'grade'])->json();
        $this->assertStringContainsString('Ben is in Grade 2, and Sampaguita is a Grade 1 class', $refused['message']);
    }

    public function test_the_window_starts_with_add_off_and_only_cancel_usable(): void
    {
        [$user, $t] = $this->teacher(['Grade 1']);
        $this->klass($t, 'Grade 1');

        $html = $this->actingAs($user)->get(route('teacher.classes.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<button[^>]*data-add-btn[^>]*disabled[^>]*>Add to class<\/button>/', $html);
        $this->assertStringContainsString('data-check-url=', $html);
        $this->assertStringContainsString('data-check-note', $html);
        $this->assertStringContainsString('data-goto="learners">Cancel</button>', $html);
    }
}
