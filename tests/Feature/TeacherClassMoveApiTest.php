<?php

namespace Tests\Feature;

use App\Models\Learner;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use App\Support\LearnerCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Moving a learner to another class, and the list of school years, on the phone (/api/teacher/classes/...). The rule itself lives in
 * TeacherClassService::move and is the one the website's Move button calls.
 */
class TeacherClassMoveApiTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function api(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    /** @return array{0: User, 1: Teacher, 2: string} */
    private function teacher(string $status = 'Active'): array
    {
        $n = ++$this->seq;
        $user = User::create(['first_name' => 'Ana', 'last_name' => "Reyes{$n}", 'email' => "tmove{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        $user->forceFill(['email_verified_at' => now()])->save();
        $teacher = Teacher::create(['user_id' => $user->id, 'school_name' => 'Rizal ES', 'employee_id' => "TM{$n}", 'status' => $status, 'free_generation_credits_remaining' => 2, 'grades_handled' => ['Grade 1', 'Grade 2']]);

        return [$user, $teacher, $user->createToken('mobile')->plainTextToken];
    }

    private function schoolClass(Teacher $teacher, string $name, string $grade = 'Grade 1', ?string $year = null): SchoolClass
    {
        return SchoolClass::create(['teacher_id' => $teacher->id, 'name' => $name, 'grade_level' => $grade, 'section' => 'A', 'school_year' => $year ?? SchoolClass::currentSchoolYear()]);
    }

    private function child(SchoolClass $class, string $first = 'Mia', string $grade = 'Grade 1'): Learner
    {
        return Learner::create([
            'learner_code' => LearnerCode::generate(), 'class_id' => $class->id, 'first_name' => $first, 'last_name' => 'Cruz', 'grade_level' => $grade,
            'pin' => '1234', 'avatar_id' => 'A', 'mastery_level' => 'Beginning', 'reading_rung' => 2,
        ]);
    }

    public function test_the_learner_page_offers_only_this_teachers_other_classes_of_the_childs_grade(): void
    {
        [, $t, $token] = $this->teacher();
        $from = $this->schoolClass($t, 'Sampaguita');
        $this->schoolClass($t, 'Rose');                              // another Grade 1 class: offered
        $this->schoolClass($t, 'Dahlia', 'Grade 2');                 // another grade: not offered
        $this->schoolClass($t, 'Last year', 'Grade 1', '2020-2021'); // another school year: not offered
        [, $other] = $this->teacher();
        $this->schoolClass($other, 'Not mine');                      // someone else's class: not offered
        $mia = $this->child($from);

        $r = $this->api($token)->getJson("/api/teacher/classes/{$from->id}/learners/{$mia->id}")->assertOk();

        $r->assertJsonPath('canMove', true);
        $this->assertSame(['Rose'], collect($r->json('moveTargets'))->pluck('name')->all());
    }

    public function test_a_learner_is_moved_and_keeps_their_own_grade(): void
    {
        [, $t, $token] = $this->teacher();
        $from = $this->schoolClass($t, 'Sampaguita');
        $to = $this->schoolClass($t, 'Rose');
        $mia = $this->child($from);

        $this->api($token)->postJson("/api/teacher/classes/{$from->id}/learners/{$mia->id}/move", ['to_class_id' => $to->id])
            ->assertOk()->assertJsonPath('message', 'Mia Cruz moved to "Rose".')->assertJsonPath('class.id', $to->id);

        $this->assertSame($to->id, $mia->fresh()->class_id);
        $this->assertSame('Grade 1', $mia->fresh()->grade_level, 'a move is a teaching decision, not a promotion');
    }

    public function test_a_class_of_another_grade_or_someone_elses_or_the_same_one_is_refused_and_nobody_moves(): void
    {
        [, $t, $token] = $this->teacher();
        $from = $this->schoolClass($t, 'Sampaguita');
        $grade2 = $this->schoolClass($t, 'Dahlia', 'Grade 2');
        [, $other] = $this->teacher();
        $theirs = $this->schoolClass($other, 'Not mine');
        $mia = $this->child($from);
        $url = "/api/teacher/classes/{$from->id}/learners/{$mia->id}/move";

        $wrongGrade = $this->api($token)->postJson($url, ['to_class_id' => $grade2->id])->assertStatus(422);
        $this->assertStringContainsString('Grade', (string) $wrongGrade->json('errors.to_class_id.0'));

        $this->assertSame('Choose one of your other classes.', $this->api($token)->postJson($url, ['to_class_id' => $theirs->id])->assertStatus(422)->json('errors.to_class_id.0'));
        $this->assertSame('Choose one of your other classes.', $this->api($token)->postJson($url, ['to_class_id' => $from->id])->assertStatus(422)->json('errors.to_class_id.0'));
        $this->assertSame('Choose the class to move to.', $this->api($token)->postJson($url, [])->assertStatus(422)->json('errors.to_class_id.0'));

        $this->assertSame($from->id, $mia->fresh()->class_id, 'nothing moved');
    }

    public function test_a_past_year_another_teachers_class_a_stranger_and_a_pending_teacher_cannot_move_anyone(): void
    {
        [, $t, $token] = $this->teacher();
        $past = $this->schoolClass($t, 'Old A', 'Grade 1', '2020-2021');
        $pastTo = $this->schoolClass($t, 'Old B', 'Grade 1', '2020-2021');
        $old = $this->child($past, 'Oli');
        $this->api($token)->postJson("/api/teacher/classes/{$past->id}/learners/{$old->id}/move", ['to_class_id' => $pastTo->id])->assertStatus(403);
        $this->assertSame($past->id, $old->fresh()->class_id);

        $from = $this->schoolClass($t, 'Sampaguita');
        $to = $this->schoolClass($t, 'Rose');
        $mia = $this->child($from);
        $elsewhere = $this->child($to, 'Ben');

        [, , $otherToken] = $this->teacher();
        $this->api($otherToken)->postJson("/api/teacher/classes/{$from->id}/learners/{$mia->id}/move", ['to_class_id' => $to->id])->assertNotFound();
        // A learner who is not in the class named in the address.
        $this->api($token)->postJson("/api/teacher/classes/{$from->id}/learners/{$elsewhere->id}/move", ['to_class_id' => $to->id])->assertStatus(404);

        [, $p, $pToken] = $this->teacher('Pending');
        $pFrom = $this->schoolClass($p, 'Pending A');
        $pTo = $this->schoolClass($p, 'Pending B');
        $kid = $this->child($pFrom, 'Pia');
        $this->api($pToken)->postJson("/api/teacher/classes/{$pFrom->id}/learners/{$kid->id}/move", ['to_class_id' => $pTo->id])->assertStatus(403);
        // Looking is open, but it says moving is not available yet and offers no choices.
        $this->api($pToken)->getJson("/api/teacher/classes/{$pFrom->id}/learners/{$kid->id}")->assertOk()->assertJsonPath('canMove', false)->assertJsonPath('moveTargets', []);
    }

    public function test_the_class_list_names_every_school_year_so_a_past_one_can_be_opened(): void
    {
        [, $t, $token] = $this->teacher();
        $this->schoolClass($t, 'Now');
        $this->schoolClass($t, 'Then', 'Grade 1', '2020-2021');
        $current = SchoolClass::currentSchoolYear();

        $r = $this->api($token)->getJson('/api/teacher/classes')->assertOk();
        $this->assertSame([$current, '2020-2021'], $r->json('years'), 'newest first');
        $r->assertJsonPath('readOnly', false);

        $past = $this->api($token)->getJson('/api/teacher/classes?school_year=2020-2021')->assertOk();
        $past->assertJsonPath('readOnly', true)->assertJsonPath('classes.0.name', 'Then');
        $this->assertSame([$current, '2020-2021'], $past->json('years'));

        // A teacher with no class this year still gets this year in the list.
        [, , $emptyToken] = $this->teacher();
        $this->assertSame([$current], $this->api($emptyToken)->getJson('/api/teacher/classes')->json('years'));
    }
}
