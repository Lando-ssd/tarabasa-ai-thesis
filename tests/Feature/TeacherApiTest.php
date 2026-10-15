<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityAssignment;
use App\Models\Learner;
use App\Models\Notification;
use App\Models\ParentAccount;
use App\Models\ReadingSession;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use App\Support\LearnerCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * The phone app's Teacher screens (/api/teacher/*): the same rules as the website's class pages, over JSON. They check what a
 * Teacher sees (home, classes, one class, one learner), what an Active Teacher may change (a class, who is in it) and what a
 * Pending Teacher, another Teacher's class, a Parent or a child may not.
 */
class TeacherApiTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    /**
     * A request as the person who owns this token. The test app keeps the signed in person from one request to the next, so a test that
     * changes a teacher's data (credits, status) or switches to another token starts the next request from a clean slate, like a real one.
     */
    private function api(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function teacher(string $status = 'Active', array $grades = ['Grade 1', 'Grade 2']): array
    {
        $n = ++$this->seq;
        $user = User::create(['first_name' => 'Ana', 'last_name' => "Reyes{$n}", 'email' => "tapi{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        $user->forceFill(['email_verified_at' => now()])->save();
        $teacher = Teacher::create(['user_id' => $user->id, 'school_name' => 'Rizal ES', 'employee_id' => "TA{$n}", 'status' => $status, 'free_generation_credits_remaining' => 2, 'grades_handled' => $grades]);

        return [$user, $teacher, $user->createToken('mobile')->plainTextToken];
    }

    private function schoolClass(Teacher $teacher, string $grade = 'Grade 1', ?string $year = null, string $name = 'Sampaguita'): SchoolClass
    {
        return SchoolClass::create(['teacher_id' => $teacher->id, 'name' => $name, 'grade_level' => $grade, 'section' => 'A', 'school_year' => $year ?? SchoolClass::currentSchoolYear()]);
    }

    private function child(?SchoolClass $class, string $first = 'Mia', string $grade = 'Grade 1', ?int $rung = 2): Learner
    {
        return Learner::create([
            'learner_code' => LearnerCode::generate(), 'class_id' => $class?->id, 'first_name' => $first, 'last_name' => 'Cruz', 'grade_level' => $grade,
            // The stored level follows the step (0 to 2 Beginning, 3 and 4 Developing, 5 and 6 Proficient), as the app keeps them.
            'pin' => '1234', 'avatar_id' => 'A', 'mastery_level' => $rung === null || $rung <= 2 ? 'Beginning' : ($rung <= 4 ? 'Developing' : 'Proficient'), 'reading_rung' => $rung,
        ]);
    }

    private function activity(Teacher $teacher, string $title = 'Plain words'): Activity
    {
        return Activity::create([
            'created_by_teacher_id' => $teacher->id, 'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'competency_label' => 'Foundational Reading',
            'activity_type' => 'word_reading', 'title' => $title, 'instructions' => 'Read.', 'passage_text' => 'cat dog', 'reference_text' => 'cat dog', 'word_count' => 2,
            'status' => 'Approved', 'difficulty_tier' => 'Easy',
        ]);
    }

    /** The last five characters of a code, the way a Teacher types it. */
    private function tail(Learner $learner): string
    {
        return substr(str_replace('-', '', $learner->learner_code), -5);
    }

    // ------------------------------------------------------------------ who may come in

    public function test_only_a_teacher_token_opens_the_teacher_screens(): void
    {
        $this->getJson('/api/teacher/home')->assertStatus(401);

        $parent = User::create(['first_name' => 'Pam', 'last_name' => 'Dee', 'email' => 'pam.tapi@example.com', 'password' => 'Passw0rd!', 'user_type' => 'Parent']);
        $parent->forceFill(['email_verified_at' => now()])->save();
        ParentAccount::create(['user_id' => $parent->id]);

        foreach ([$parent->createToken('m')->plainTextToken, $this->child(null)->createToken('m')->plainTextToken] as $token) {
            $this->api($token)->getJson('/api/teacher/home')->assertStatus(403)->assertJsonPath('error', 'wrong_account_type');
            $this->api($token)->postJson('/api/teacher/classes', [])->assertStatus(403);
        }
    }

    // ------------------------------------------------------------------ home

    public function test_home_gives_the_numbers_and_who_needs_support(): void
    {
        [, $t, $token] = $this->teacher();
        $class = $this->schoolClass($t);
        $kid = $this->child($class, 'Leo', 'Grade 1', 3);
        $this->child($class, 'Mia');
        $this->activity($t);
        Activity::create(['created_by_teacher_id' => $t->id, 'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'competency_label' => 'Foundational Reading', 'activity_type' => 'word_reading', 'title' => 'Draft one', 'instructions' => 'Read.', 'passage_text' => 'a b', 'status' => 'Draft', 'difficulty_tier' => 'Easy']);

        // A reading under 70 percent, flagged: the same thing the website's Home banner counts.
        $activity = $this->activity($t, 'Hard words');
        $row = ReadingSession::create(['learner_id' => $kid->id, 'activity_id' => $activity->id, 'session_type' => 'Practice', 'initiated_by' => 'Teacher', 'accuracy_percent' => 25, 'flagged_needs_attention' => true]);
        $row->timestamp = now()->subDay();
        $row->save();

        $r = $this->api($token)->getJson('/api/teacher/home')->assertOk();

        $r->assertJsonPath('counts.classes', 1)->assertJsonPath('counts.learners', 2)->assertJsonPath('counts.activities', 3)
            ->assertJsonPath('counts.approved', 2)->assertJsonPath('counts.drafts', 1)->assertJsonPath('counts.credits', 2)
            ->assertJsonPath('needsSupportCount', 1)->assertJsonPath('needsSupport.0.name', 'Leo Cruz');
        $this->assertGreaterThanOrEqual(1, $r->json('counts.alerts'));
    }

    // ------------------------------------------------------------------ classes

    public function test_classes_lists_this_years_own_classes_and_marks_a_past_year_read_only(): void
    {
        [, $t, $token] = $this->teacher();
        [, $other] = $this->teacher();
        $mine = $this->schoolClass($t);
        $this->child($mine, 'Mia');
        $this->child($mine, 'Ben');
        $this->schoolClass($other, 'Grade 1', null, 'Rose');
        $this->schoolClass($t, 'Grade 2', '2019-2020', 'Old');
        ActivityAssignment::create(['activity_id' => $this->activity($t)->id, 'class_id' => $mine->id, 'assigned_by_teacher_id' => $t->id]);

        $r = $this->api($token)->getJson('/api/teacher/classes')->assertOk();
        $this->assertSame(['Sampaguita'], collect($r->json('classes'))->pluck('name')->all());
        $r->assertJsonPath('classes.0.learnerCount', 2)->assertJsonPath('classes.0.activityCount', 1)->assertJsonPath('readOnly', false);
        $this->assertSame(['Grade 1', 'Grade 2'], $r->json('grades'));
        $this->assertSame(['support', 'instructional', 'independent'], collect($r->json('classes.0.groups'))->pluck('key')->all());

        $old = $this->api($token)->getJson('/api/teacher/classes?school_year=2019-2020')->assertOk();
        $old->assertJsonPath('readOnly', true)->assertJsonPath('classes.0.name', 'Old')->assertJsonPath('classes.0.readOnly', true);
    }

    public function test_one_class_shows_its_learners_with_their_level_step_and_tag_and_what_it_was_given(): void
    {
        [, $t, $token] = $this->teacher();
        $class = $this->schoolClass($t);
        $leo = $this->child($class, 'Leo', 'Grade 1', 3);
        $this->child($class, 'Ana', 'Grade 1', 0);
        $this->child($class, 'Rosa', 'Grade 1', 5);
        ActivityAssignment::create(['activity_id' => $this->activity($t, 'Animal words')->id, 'class_id' => $class->id, 'assigned_by_teacher_id' => $t->id]);

        $row = ReadingSession::create(['learner_id' => $leo->id, 'activity_id' => $this->activity($t, 'Hard words')->id, 'session_type' => 'Practice', 'initiated_by' => 'Teacher', 'accuracy_percent' => 20, 'flagged_needs_attention' => true]);
        $row->timestamp = now()->subDay();
        $row->save();

        $r = $this->api($token)->getJson("/api/teacher/classes/{$class->id}")->assertOk();

        $this->assertSame(['Ana Cruz', 'Leo Cruz', 'Rosa Cruz'], collect($r->json('learners'))->pluck('name')->all());
        $by = collect($r->json('learners'))->keyBy('firstName');
        $this->assertSame('Non-reader', $by['Ana']['level']);
        $this->assertSame('Letters', $by['Ana']['step']);
        $this->assertSame('Starting to read', $by['Ana']['tag']['label']);
        $this->assertSame('Instructional', $by['Leo']['level']);
        $this->assertSame('Needs support', $by['Leo']['tag']['label']);
        $this->assertSame('Independent', $by['Rosa']['level']);
        $this->assertTrue($r->json('canAdd'));
        $this->assertSame('Animal words', $r->json('activities.0.title'));
        $this->assertSame('class', $r->json('activities.0.via'));
        $this->assertNotEmpty($r->json('generalLevel.label'));
    }

    public function test_another_teachers_class_looks_like_it_does_not_exist(): void
    {
        [, , $token] = $this->teacher();
        [, $other] = $this->teacher();
        $theirs = $this->schoolClass($other);
        $kid = $this->child($theirs);

        $this->api($token)->getJson("/api/teacher/classes/{$theirs->id}")->assertNotFound();
        $this->api($token)->getJson("/api/teacher/classes/{$theirs->id}/learners/{$kid->id}")->assertNotFound();
        $this->api($token)->putJson("/api/teacher/classes/{$theirs->id}", ['name' => 'Mine now', 'grade_level' => 'Grade 1', 'section' => 'A'])->assertNotFound();
        $this->api($token)->postJson("/api/teacher/classes/{$theirs->id}/learners", ['learner_code' => $this->tail($this->child(null))])->assertNotFound();
    }

    // ------------------------------------------------------------------ making and changing a class

    public function test_an_active_teacher_makes_a_class_and_the_grade_must_be_one_they_handle(): void
    {
        [, $t, $token] = $this->teacher('Active', ['Grade 1']);
        $year = SchoolClass::currentSchoolYear();

        $this->api($token)->postJson('/api/teacher/classes', ['name' => 'Dahlia', 'grade_level' => 'Grade 1', 'section' => 'B', 'school_year' => $year])
            ->assertCreated()->assertJsonPath('class.name', 'Dahlia')->assertJsonPath('class.grade', 'Grade 1')->assertJsonPath('class.learnerCount', 0);
        $this->assertSame(1, SchoolClass::where('teacher_id', $t->id)->count());

        // A forged grade gets the website's own sentence.
        $this->api($token)->postJson('/api/teacher/classes', ['name' => 'Oak', 'grade_level' => 'Grade 3', 'section' => 'C', 'school_year' => $year])
            ->assertStatus(422)->assertJsonValidationErrors('grade_level')
            ->assertJsonPath('errors.grade_level.0', 'You handle Grade 1. Change the grades you handle in Profile to open a class for another grade.');
        $this->assertSame(1, SchoolClass::where('teacher_id', $t->id)->count());

        $this->api($token)->postJson('/api/teacher/classes', [])->assertStatus(422)->assertJsonValidationErrors(['name', 'grade_level', 'section', 'school_year']);
    }

    public function test_a_pending_teacher_can_look_but_not_change_anything(): void
    {
        [, $t, $token] = $this->teacher('Pending');
        $class = $this->schoolClass($t);
        $kid = $this->child(null);

        $this->api($token)->getJson('/api/teacher/home')->assertOk();
        $this->api($token)->getJson("/api/teacher/classes/{$class->id}")->assertOk()->assertJsonPath('canAdd', false);

        $this->api($token)->postJson('/api/teacher/classes', ['name' => 'X', 'grade_level' => 'Grade 1', 'section' => 'A', 'school_year' => SchoolClass::currentSchoolYear()])
            ->assertStatus(403)->assertJsonPath('message', 'Locked until your school verification is approved.');
        $this->api($token)->putJson("/api/teacher/classes/{$class->id}", ['name' => 'Y', 'grade_level' => 'Grade 1', 'section' => 'A'])->assertStatus(403);
        $this->api($token)->postJson("/api/teacher/classes/{$class->id}/learners", ['learner_code' => $this->tail($kid)])->assertStatus(403);
        $this->assertNull($kid->fresh()->class_id);
    }

    public function test_changing_a_class_keeps_one_grade_per_class_and_a_past_year_is_read_only(): void
    {
        [, $t, $token] = $this->teacher();
        $class = $this->schoolClass($t);
        $this->child($class, 'Mia', 'Grade 1');
        $old = $this->schoolClass($t, 'Grade 1', '2019-2020', 'Old');

        $this->api($token)->putJson("/api/teacher/classes/{$class->id}", ['name' => 'Sampaguita 2', 'grade_level' => 'Grade 1', 'section' => 'B', 'group_tag' => 'phonics'])
            ->assertOk()->assertJsonPath('class.name', 'Sampaguita 2')->assertJsonPath('class.groupTag', 'phonics');

        // Mia is in Grade 1, so the class cannot become a Grade 2 class under her.
        $this->api($token)->putJson("/api/teacher/classes/{$class->id}", ['name' => 'Sampaguita 2', 'grade_level' => 'Grade 2', 'section' => 'B'])
            ->assertStatus(422)->assertJsonValidationErrors('grade_level');
        $this->assertSame('Grade 1', $class->fresh()->grade_level);

        $this->api($token)->putJson("/api/teacher/classes/{$old->id}", ['name' => 'Renamed', 'grade_level' => 'Grade 1', 'section' => 'A'])->assertStatus(403);
        $this->api($token)->postJson("/api/teacher/classes/{$old->id}/learners", ['learner_code' => $this->tail($this->child(null))])->assertStatus(403);
    }

    // ------------------------------------------------------------------ adding a learner

    public function test_a_learner_joins_with_the_last_five_characters_or_the_whole_code_and_the_parent_is_told(): void
    {
        [, $t, $token] = $this->teacher();
        $class = $this->schoolClass($t);
        $first = $this->child(null, 'Mia');
        $second = $this->child(null, 'Ben');

        $parent = User::create(['first_name' => 'Pam', 'last_name' => 'Cruz', 'email' => 'pam.join@example.com', 'password' => 'Passw0rd!', 'user_type' => 'Parent']);
        ParentAccount::create(['user_id' => $parent->id])->learners()->attach($first->id, ['relationship' => 'Mother', 'is_creator' => true]);

        $this->api($token)->postJson("/api/teacher/classes/{$class->id}/learners", ['learner_code' => $this->tail($first)])
            ->assertCreated()->assertJsonPath('ok', true)->assertJsonPath('learner.name', 'Mia Cruz')->assertJsonPath('learner.code', $first->learner_code);
        $this->assertSame($class->id, $first->fresh()->class_id);
        $this->assertSame(1, Notification::where('recipient_user_id', $parent->id)->where('type', Notification::TYPE_CLASS_JOINED)->count());

        $this->api($token)->postJson("/api/teacher/classes/{$class->id}/learners", ['learner_code' => strtolower($second->learner_code)])
            ->assertCreated()->assertJsonPath('learner.name', 'Ben Cruz');
        $this->assertSame($class->id, $second->fresh()->class_id);
    }

    public function test_a_learner_who_cannot_join_gets_the_websites_reason(): void
    {
        [, $t, $token] = $this->teacher();
        $class = $this->schoolClass($t, 'Grade 1');
        $enrolled = $this->child($this->schoolClass($t, 'Grade 1', null, 'Other'), 'Zoe');
        $second = $this->child(null, 'Nia', 'Grade 2');
        $join = fn (string $code) => $this->api($token)->postJson("/api/teacher/classes/{$class->id}/learners", ['learner_code' => $code]);

        $join($this->tail($enrolled))->assertStatus(422)->assertJsonPath('ok', false)->assertJsonPath('message', 'This learner is already enrolled in a class.');
        $join($this->tail($second))->assertStatus(422)->assertJsonPath('ok', false);
        $this->assertStringContainsString('A class holds one grade only', $join($this->tail($second))->json('message'));
        $this->assertNull($second->fresh()->class_id);

        $join('zz')->assertStatus(422)->assertJsonPath('message', 'Type the last 5 characters of the Learner Code, like 48293.');
        $join('00000')->assertStatus(422)->assertJsonPath('message', 'No learner found with that code. Check it and try again.');
        $this->api($token)->postJson("/api/teacher/classes/{$class->id}/learners", [])->assertStatus(422)->assertJsonValidationErrors('learner_code');
    }

    public function test_too_many_wrong_codes_make_the_teacher_wait(): void
    {
        [, $t, $token] = $this->teacher();
        $class = $this->schoolClass($t);
        RateLimiter::clear('join-learner|teacher:'.$t->id);

        for ($i = 0; $i < 10; $i++) {
            $this->api($token)->postJson("/api/teacher/classes/{$class->id}/learners", ['learner_code' => '00000'])->assertStatus(422);
        }

        $this->api($token)->postJson("/api/teacher/classes/{$class->id}/learners", ['learner_code' => $this->tail($this->child(null))])
            ->assertStatus(422)->assertJsonPath('ok', false);
        $this->assertStringContainsString('Too many codes that did not match', $this->api($token)->postJson("/api/teacher/classes/{$class->id}/learners", ['learner_code' => '00000'])->json('message'));
    }

    // ------------------------------------------------------------------ one learner

    public function test_a_learners_page_has_what_the_parent_shared_and_where_the_child_reads(): void
    {
        [, $t, $token] = $this->teacher();
        $class = $this->schoolClass($t);
        $kid = $this->child($class, 'Mia', 'Grade 1', 2);
        $kid->update(['reading_stage' => 'blending', 'home_language' => 'Tagalog', 'interests' => ['animals']]);
        $elsewhere = $this->child($this->schoolClass($t, 'Grade 1', null, 'Other'), 'Ben');

        $r = $this->api($token)->getJson("/api/teacher/classes/{$class->id}/learners/{$kid->id}")->assertOk();

        $r->assertJsonPath('learner.name', 'Mia Cruz')->assertJsonPath('profile.stage', 'Reads simple words')->assertJsonPath('profile.language', 'Tagalog');
        $this->assertSame(['Animals'], $r->json('profile.interests'));
        foreach (['check', 'bestFit', 'teach', 'progress'] as $key) {
            $this->assertArrayHasKey($key, $r->json('profile'), $key);
        }

        // A child who is in another of this teacher's classes is not on this class's page.
        $this->api($token)->getJson("/api/teacher/classes/{$class->id}/learners/{$elsewhere->id}")->assertNotFound();
    }
}
