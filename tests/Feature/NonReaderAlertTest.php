<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Learner;
use App\Models\ReadingSession;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ReadingProgression;
use App\Services\TeacherAlerts;
use App\Support\LearnerCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A child still on the Letters step is at the Phil-IRI Non-reader level (DepEd Order No. 14, s. 2018). The teacher gets one calm
 * "just starting to read" alert with the basis and a first teaching move, only from a MEASURED level, and it never says the child failed.
 */
class NonReaderAlertTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function teacher(): array
    {
        $n = ++$this->seq;
        $user = User::create(['first_name' => 'Ana', 'last_name' => "Reyes{$n}", 'email' => "nr{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        $user->forceFill(['email_verified_at' => now()])->save();
        $teacher = Teacher::create(['user_id' => $user->id, 'school_name' => 'Rizal ES', 'employee_id' => "N{$n}", 'status' => 'Active', 'free_generation_credits_remaining' => 2, 'grades_handled' => ['Grade 1', 'Grade 2', 'Grade 3']]);

        return [$user, $teacher];
    }

    private function kid(SchoolClass $c, ?int $rung, array $o = []): Learner
    {
        $n = ++$this->seq;

        return Learner::create($o + [
            'learner_code' => LearnerCode::generate(), 'class_id' => $c->id, 'first_name' => "Kid{$n}", 'last_name' => 'Cruz', 'grade_level' => $c->grade_level,
            'pin' => '1234', 'avatar_id' => 'A', 'mastery_level' => $rung === null ? null : ReadingProgression::levelForRung($rung), 'reading_rung' => $rung,
            'rung_changed_at' => $rung === null ? null : now()->subDays(2),
        ]);
    }

    private function read(Learner $kid, Teacher $t, float $accuracy, int $daysAgo = 0): void
    {
        $a = Activity::create([
            'created_by_teacher_id' => $t->id, 'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'competency_label' => 'Foundational Reading',
            'activity_type' => 'phonics', 'difficulty_tier' => 'Easy', 'title' => 'Letters '.(++$this->seq), 'instructions' => 'Say the letters.', 'passage_text' => 's a t p i n', 'word_count' => 6, 'status' => 'Approved',
        ]);
        $row = ReadingSession::create(['learner_id' => $kid->id, 'activity_id' => $a->id, 'session_type' => 'Practice', 'initiated_by' => 'Teacher', 'accuracy_percent' => $accuracy]);
        $row->timestamp = now()->subDays($daysAgo)->subMinutes(5);
        $row->save();
    }

    public function test_a_child_on_the_letters_step_gets_one_calm_alert_with_the_basis_and_a_first_teaching_move(): void
    {
        [$user, $t] = $this->teacher();
        $class = SchoolClass::create(['teacher_id' => $t->id, 'name' => 'Sampaguita', 'grade_level' => 'Grade 1', 'section' => 'A', 'school_year' => SchoolClass::currentSchoolYear()]);
        $mary = $this->kid($class, 0, ['first_name' => 'Mary', 'last_name' => 'Rose']);

        $alerts = app(TeacherAlerts::class)->forTeacher($t);
        $this->assertCount(1, $alerts);
        $this->assertSame(['start', 'nonreader', 'non'], [$alerts[0]['kind'], $alerts[0]['variant'], $alerts[0]['band']]);

        $this->actingAs($user)->get(route('teacher.notifications.index'))->assertOk()
            ->assertSee('Mary Rose is just starting to read')
            ->assertSee('Letters step, Non-reader level')
            ->assertSee('where reading instruction begins, not a mark against Mary', false)
            ->assertSee('Phil-IRI (DepEd Order No. 14, s. 2018)', false)
            ->assertSee('EN2PWS-I-2')
            ->assertSee('Teach a few letters at a time')
            ->assertSee('Starting to read')
            ->assertDontSee('is in Grade 1, so plan', false);
        $this->assertSame(1, app(TeacherAlerts::class)->openCount($t));
    }

    public function test_an_older_grade_child_gets_one_extra_plain_line_and_the_filter_shows_only_these(): void
    {
        [$user, $t] = $this->teacher();
        $class = SchoolClass::create(['teacher_id' => $t->id, 'name' => 'Rosal', 'grade_level' => 'Grade 3', 'section' => 'A', 'school_year' => SchoolClass::currentSchoolYear()]);
        $this->kid($class, 0, ['first_name' => 'Tomas', 'last_name' => 'Lim']);
        $this->kid($class, 1, ['first_name' => 'Pia', 'last_name' => 'Ong']);

        $this->actingAs($user)->get(route('teacher.notifications.index', ['filter' => 'start']))->assertOk()
            ->assertSee('Tomas Lim is just starting to read')
            ->assertSee('Tomas is in Grade 3, so plan a few minutes of letter practice every day')
            ->assertDontSee('Pia Ong');
    }

    public function test_only_a_measured_level_raises_it_never_what_the_parent_said(): void
    {
        [, $t] = $this->teacher();
        $class = SchoolClass::create(['teacher_id' => $t->id, 'name' => 'Sampaguita', 'grade_level' => 'Grade 1', 'section' => 'A', 'school_year' => SchoolClass::currentSchoolYear()]);
        $this->kid($class, null, ['reading_stage' => 'starting', 'mastery_level' => null]);   // the parent said "just starting", nothing measured yet
        $this->kid($class, 1);                                                                 // already on Words

        $this->assertCount(0, app(TeacherAlerts::class)->forTeacher($t));
    }

    public function test_low_scores_on_the_letters_step_do_not_add_a_second_needs_support_card(): void
    {
        [, $t] = $this->teacher();
        $class = SchoolClass::create(['teacher_id' => $t->id, 'name' => 'Sampaguita', 'grade_level' => 'Grade 1', 'section' => 'A', 'school_year' => SchoolClass::currentSchoolYear()]);
        $kid = $this->kid($class, 0);
        foreach ([30, 25, 40] as $i => $acc) {
            $this->read($kid, $t, $acc, 3 - $i);
        }

        $alerts = app(TeacherAlerts::class)->forTeacher($t);

        $this->assertCount(1, $alerts);
        $this->assertSame('start', $alerts[0]['kind']);
    }

    public function test_mark_handled_hides_it_until_the_child_reads_again_and_it_ends_when_the_child_moves_up(): void
    {
        [$user, $t] = $this->teacher();
        $class = SchoolClass::create(['teacher_id' => $t->id, 'name' => 'Sampaguita', 'grade_level' => 'Grade 1', 'section' => 'A', 'school_year' => SchoolClass::currentSchoolYear()]);
        $kid = $this->kid($class, 0);
        $this->actingAs($user);

        $this->post(route('teacher.alerts.handled', $kid), ['kind' => 'start'])->assertRedirect();
        $this->assertCount(0, app(TeacherAlerts::class)->forTeacher($t));

        $this->travel(2)->hours();
        $this->read($kid, $t, 50, 0);
        $this->assertCount(1, app(TeacherAlerts::class)->forTeacher($t), 'a new reading opens it again');

        $kid->update(['reading_rung' => 1, 'mastery_level' => 'Beginning']);
        $this->assertCount(0, app(TeacherAlerts::class)->forTeacher($t)->where('kind', 'start'), 'it ends when the child reaches the Words step');
    }

    public function test_the_alert_can_hand_out_a_short_approved_activity_and_the_kind_is_accepted(): void
    {
        [$user, $t] = $this->teacher();
        $class = SchoolClass::create(['teacher_id' => $t->id, 'name' => 'Sampaguita', 'grade_level' => 'Grade 1', 'section' => 'A', 'school_year' => SchoolClass::currentSchoolYear()]);
        $kid = $this->kid($class, 0);
        $short = Activity::create([
            'created_by_teacher_id' => $t->id, 'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'competency_label' => 'Foundational Reading',
            'activity_type' => 'phonics', 'difficulty_tier' => 'Easy', 'title' => 'Six letters', 'instructions' => 'Say the letters.', 'passage_text' => 's a t p i n', 'word_count' => 6, 'status' => 'Approved',
        ]);
        $this->actingAs($user);

        $page = $this->get(route('teacher.notifications.index'))->assertOk();
        $page->assertSee('Six letters')->assertSee('Assign a short activity');

        $this->post(route('teacher.alerts.assign', $kid), ['kind' => 'start', 'activity_id' => $short->id])->assertRedirect();
        $this->assertDatabaseHas('activity_assignments', ['activity_id' => $short->id, 'learner_id' => $kid->id]);
    }

    public function test_the_deped_basis_is_kept_in_one_place_and_agrees_with_the_levels_the_app_uses(): void
    {
        // The Phil-IRI bands the app names a single reading by (DepEd Order No. 14, s. 2018).
        $this->assertSame('independent', \App\Support\ReadingLevel::bandForAccuracy(97.0));
        $this->assertSame('instructional', \App\Support\ReadingLevel::bandForAccuracy(96.0));
        $this->assertSame('instructional', \App\Support\ReadingLevel::bandForAccuracy(90.0));
        $this->assertSame('frustration', \App\Support\ReadingLevel::bandForAccuracy(89.0));

        $deped = config('teaching_path.deped');
        $this->assertStringContainsString('97 percent or more', $deped['phil_iri']);
        $this->assertStringContainsString('90 to 96', $deped['phil_iri']);
        $this->assertStringContainsString('Non-reader', $deped['phil_iri']);
        $this->assertStringContainsString('Memorandum No. 173, s. 2019', $deped['3bs']);
        $this->assertStringContainsString('one class for each grade', $deped['multigrade']);

        // Every step of the ladder names the DepEd element of reading it teaches (the six of the 3Bs initiative).
        foreach (range(0, 6) as $rung) {
            $this->assertNotEmpty(config("teaching_path.rungs.{$rung}.element"), "rung {$rung} element");
        }
        $this->assertStringContainsString('phonics', strtolower(config('teaching_path.rungs.0.element')));
    }
}
