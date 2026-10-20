<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Learner;
use App\Models\ReadingSession;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ClassActivityOutcomes;
use App\Services\ReadingProgression;
use App\Support\LearnerCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * "Did the class activities help?": activities written for a class against the teacher's general ones, for the same
 * children. The rules that matter: only practice readings count, a comparison is only given once enough children read
 * BOTH kinds, a child is compared with themselves, and the number never claims more than it can show.
 */
class ClassActivityOutcomesTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    /** @return array{0: User, 1: Teacher, 2: SchoolClass} */
    private function setUpClass(string $name = 'Sampaguita'): array
    {
        $n = ++$this->seq;
        $user = User::create(['first_name' => 'Ana', 'last_name' => "Reyes{$n}", 'email' => "oc{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        $user->forceFill(['email_verified_at' => now()])->save();
        $teacher = Teacher::create(['user_id' => $user->id, 'school_name' => 'Rizal ES', 'employee_id' => "OC{$n}", 'status' => 'Active', 'free_generation_credits_remaining' => 2, 'grades_handled' => ['Grade 1']]);
        $class = SchoolClass::create(['teacher_id' => $teacher->id, 'name' => $name, 'grade_level' => 'Grade 1', 'section' => 'A', 'school_year' => SchoolClass::currentSchoolYear()]);

        return [$user, $teacher, $class];
    }

    private function kid(SchoolClass $class): Learner
    {
        $n = ++$this->seq;

        return Learner::create([
            'learner_code' => LearnerCode::generate(), 'class_id' => $class->id, 'first_name' => "Kid{$n}", 'last_name' => 'Cruz', 'grade_level' => 'Grade 1',
            'pin' => '1234', 'avatar_id' => 'A', 'mastery_level' => 'Beginning', 'reading_rung' => 1,
        ]);
    }

    private function activity(Teacher $t, ?array $basis, string $title = 'Words'): Activity
    {
        return Activity::create([
            'created_by_teacher_id' => $t->id, 'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'competency_label' => 'Foundational Reading',
            'activity_type' => 'word_reading', 'difficulty_tier' => 'Easy', 'title' => $title, 'instructions' => 'Read.', 'passage_text' => 'cat dog pig hen cow bat', 'word_count' => 6,
            'status' => 'Approved', 'generation_basis' => $basis,
        ]);
    }

    private function read(Learner $kid, Activity $a, float $accuracy, string $type = 'Practice'): void
    {
        ReadingSession::create([
            'learner_id' => $kid->id, 'activity_id' => $a->id, 'session_type' => $type, 'initiated_by' => 'Teacher', 'accuracy_percent' => $accuracy,
            'level_before' => 'Beginning', 'level_after' => 'Beginning', 'rung_before' => 1, 'rung_after' => 1,
        ]);
    }

    private function outcome(Teacher $t, SchoolClass $c): ?array
    {
        return app(ClassActivityOutcomes::class)->forClasses($t, collect([$c]))->get($c->id);
    }

    private function basis(SchoolClass $c): array
    {
        return ['className' => $c->name, 'classId' => $c->id, 'lines' => ['Most of the class reads short words.']];
    }

    public function test_a_class_with_nothing_written_for_it_has_no_entry(): void
    {
        [, $t, $c] = $this->setUpClass();
        $this->activity($t, null);

        $this->assertNull($this->outcome($t, $c));
    }

    public function test_written_but_not_read_yet_says_so(): void
    {
        [, $t, $c] = $this->setUpClass();
        $this->activity($t, $this->basis($c));
        $this->kid($c);

        $r = $this->outcome($t, $c);

        $this->assertSame('not_read_yet', $r['state']);
        $this->assertNull($r['difference']);
        $this->assertStringContainsString('none read yet', $r['headline']);
    }

    public function test_with_too_few_children_who_read_both_it_gives_no_number_and_says_what_is_missing(): void
    {
        [, $t, $c] = $this->setUpClass();
        $mine = $this->activity($t, $this->basis($c));
        $general = $this->activity($t, null, 'General');
        [$a, $b, $d] = [$this->kid($c), $this->kid($c), $this->kid($c)];

        // Three children read the class activity, but only two of them also read a general one.
        foreach ([$a, $b, $d] as $kid) {
            $this->read($kid, $mine, 90);
        }
        $this->read($a, $general, 70);
        $this->read($b, $general, 70);

        $r = $this->outcome($t, $c);

        $this->assertSame('early', $r['state']);
        $this->assertNull($r['difference']);
        $this->assertNull($r['verdict']);
        $this->assertSame(2, $r['paired']);
        $this->assertStringContainsString('at least 3 children who read both kinds', $r['detail']);
        $this->assertStringContainsString('so far 2 have', $r['detail']);
    }

    public function test_it_compares_each_child_with_themselves_and_calls_a_clear_gain_higher(): void
    {
        [, $t, $c] = $this->setUpClass();
        $mine = $this->activity($t, $this->basis($c));
        $general = $this->activity($t, null, 'General');

        // Two strong readers and two weaker ones: each does 10 points better on the class activity, so the pooled averages would
        // mislead but the paired difference is exactly 10.
        foreach ([[95, 85], [90, 80], [70, 60], [60, 50]] as [$withClass, $withGeneral]) {
            $kid = $this->kid($c);
            $this->read($kid, $mine, $withClass);
            $this->read($kid, $general, $withGeneral);
        }

        $r = $this->outcome($t, $c);

        $this->assertSame('enough', $r['state']);
        $this->assertSame(10.0, $r['difference']);
        $this->assertSame('higher', $r['verdict']);
        $this->assertSame(4, $r['paired']);
        $this->assertStringContainsString('about 10 points higher on activities written for Sampaguita', $r['headline']);
        $this->assertStringContainsString('not proof', $r['detail']);
        $this->assertSame(79, $r['for']['avg']);
        $this->assertSame(69, $r['general']['avg']);
    }

    public function test_a_small_difference_is_about_the_same_and_a_drop_is_lower(): void
    {
        [, $t, $c] = $this->setUpClass();
        $mine = $this->activity($t, $this->basis($c));
        $general = $this->activity($t, null, 'General');
        foreach ([[80, 79], [70, 71], [90, 90]] as [$x, $y]) {
            $kid = $this->kid($c);
            $this->read($kid, $mine, $x);
            $this->read($kid, $general, $y);
        }

        $this->assertSame('similar', $this->outcome($t, $c)['verdict']);

        [, $t2, $c2] = $this->setUpClass('Rose');
        $mine2 = $this->activity($t2, $this->basis($c2));
        $general2 = $this->activity($t2, null, 'General');
        foreach ([[60, 80], [50, 70], [70, 85]] as [$x, $y]) {
            $kid = $this->kid($c2);
            $this->read($kid, $mine2, $x);
            $this->read($kid, $general2, $y);
        }

        $r = $this->outcome($t2, $c2);
        $this->assertSame('lower', $r['verdict']);
        $this->assertStringContainsString('points lower', $r['headline']);
    }

    public function test_only_practice_readings_of_this_teachers_own_activities_count(): void
    {
        [, $t, $c] = $this->setUpClass();
        [, $other, $otherClass] = $this->setUpClass('Dahlia');
        $mine = $this->activity($t, $this->basis($c));
        $general = $this->activity($t, null, 'General');
        $forOtherClass = $this->activity($t, $this->basis($otherClass), 'For another class');
        $someoneElses = $this->activity($other, null, 'Not mine');

        $kid = $this->kid($c);
        $this->read($kid, $mine, 80);
        $this->read($kid, $mine, 100, 'Diagnostic');      // the first reading check never counts
        $this->read($kid, $general, 60);
        $this->read($kid, $forOtherClass, 10);            // written for another class: neither kind
        $this->read($kid, $someoneElses, 10);             // not this teacher's activity

        $r = $this->outcome($t, $c);

        $this->assertSame(1, $r['for']['readings']);
        $this->assertSame(1, $r['general']['readings']);
        $this->assertSame(80, $r['for']['avg']);
        $this->assertSame(60, $r['general']['avg']);
    }

    public function test_an_activity_saved_before_class_ids_were_kept_is_matched_by_the_class_name(): void
    {
        [, $t, $c] = $this->setUpClass();
        $this->activity($t, ['className' => 'Sampaguita', 'lines' => ['x']]);

        $this->assertNotNull($this->outcome($t, $c));
        $this->assertSame(1, $this->outcome($t, $c)['written']);
    }

    public function test_the_analytics_page_and_the_phone_api_show_it_only_when_it_exists(): void
    {
        [$user, $t, $c] = $this->setUpClass();
        $this->kid($c);

        $this->actingAs($user)->get(route('teacher.analytics.index'))->assertOk()->assertDontSee('Did the class activities help?');

        $mine = $this->activity($t, $this->basis($c));
        $general = $this->activity($t, null, 'General');
        foreach ([[90, 80], [85, 75], [80, 70]] as [$x, $y]) {
            $kid = $this->kid($c);
            $this->read($kid, $mine, $x);
            $this->read($kid, $general, $y);
        }

        $html = $this->actingAs($user)->get(route('teacher.analytics.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Did the class activities help?', $html);
        $this->assertStringContainsString('about 10 points higher on activities written for Sampaguita', $html);

        Sanctum::actingAs($user);
        $json = $this->getJson('/api/teacher/analytics')->assertOk()->json('outcomes');
        $this->assertCount(1, $json);
        $this->assertSame('higher', $json[0]['verdict']);
        // Counts and averages only: nothing about a single child leaves the service.
        $this->assertArrayNotHasKey('perLearner', $json[0]['for']);
    }
}
