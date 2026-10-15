<?php

namespace Tests\Feature;

use App\Jobs\GenerateActivitiesJob;
use App\Models\Activity;
use App\Models\ActivityAssignment;
use App\Models\ActivityGeneration;
use App\Models\Learner;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use App\Support\LearnerCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The phone app's Activities screens (/api/teacher/activities...): the review list, one activity, putting it in a level, giving it to
 * learners, sharing it, and asking for new ones. The same rules as the website (they share TeacherActivityService); these check the
 * JSON, the Pending Teacher's lock, and that another teacher's activity is out of reach.
 */
class TeacherActivityApiTest extends TestCase
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

    private function teacher(string $status = 'Active', int $credits = 2): array
    {
        $n = ++$this->seq;
        $user = User::create(['first_name' => 'Ana', 'last_name' => "Reyes{$n}", 'email' => "tact{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        $user->forceFill(['email_verified_at' => now()])->save();
        $teacher = Teacher::create(['user_id' => $user->id, 'school_name' => 'Rizal ES', 'employee_id' => "TC{$n}", 'status' => $status, 'free_generation_credits_remaining' => $credits, 'grades_handled' => ['Grade 1', 'Grade 2']]);

        return [$user, $teacher, $user->createToken('mobile')->plainTextToken];
    }

    private function activity(Teacher $teacher, array $o = []): Activity
    {
        return Activity::create($o + [
            'created_by_teacher_id' => $teacher->id, 'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'competency_label' => 'Foundational Reading',
            'activity_type' => 'word_reading', 'title' => 'Farm words', 'instructions' => 'Read the words.', 'passage_text' => 'cat dog pig hen cow bat', 'reference_text' => 'cat dog pig hen cow bat',
            'word_count' => 6, 'status' => 'Draft', 'difficulty_tier' => 'Medium', 'ai_difficulty_tier' => 'Medium',
        ]);
    }

    private function schoolClass(Teacher $teacher, string $name = 'Sampaguita'): SchoolClass
    {
        return SchoolClass::create(['teacher_id' => $teacher->id, 'name' => $name, 'grade_level' => 'Grade 1', 'section' => 'A', 'school_year' => SchoolClass::currentSchoolYear()]);
    }

    private function child(SchoolClass $class, string $first = 'Mia', int $rung = 3): Learner
    {
        return Learner::create([
            'learner_code' => LearnerCode::generate(), 'class_id' => $class->id, 'first_name' => $first, 'last_name' => 'Cruz', 'grade_level' => 'Grade 1',
            'pin' => '1234', 'avatar_id' => 'A', 'mastery_level' => $rung <= 2 ? 'Beginning' : 'Developing', 'reading_rung' => $rung,
        ]);
    }

    // ------------------------------------------------------------------ the list

    public function test_the_list_starts_on_drafts_and_follows_the_status_level_and_search(): void
    {
        [, $t, $token] = $this->teacher();
        $this->activity($t, ['title' => 'Draft one']);
        $this->activity($t, ['title' => 'Easy approved', 'status' => 'Approved', 'difficulty_tier' => 'Easy']);
        $this->activity($t, ['title' => 'Hard approved', 'status' => 'Approved', 'difficulty_tier' => 'Hard']);
        $this->activity($t, ['title' => 'Turned down', 'status' => 'Rejected']);
        [, $other] = $this->teacher();
        $this->activity($other, ['title' => 'Not mine']);

        $r = $this->api($token)->getJson('/api/teacher/activities')->assertOk();
        $r->assertJsonPath('status', 'Draft')->assertJsonPath('counts.Draft', 1)->assertJsonPath('counts.Approved', 2)->assertJsonPath('counts.Rejected', 1)
            ->assertJsonPath('locked', false)->assertJsonPath('credits', 2)->assertJsonPath('generation', null);
        $this->assertSame(['Draft one'], collect($r->json('activities'))->pluck('title')->all());
        $this->assertSame('cat dog pig hen cow bat', $r->json('activities.0.passage'), 'a draft carries its text so it can be read before it is placed');

        $approved = $this->api($token)->getJson('/api/teacher/activities?status=Approved')->assertOk();
        $this->assertEqualsCanonicalizing(['Easy approved', 'Hard approved'], collect($approved->json('activities'))->pluck('title')->all());
        $this->assertNull($approved->json('activities.0.passage'));

        $this->assertSame(['Hard approved'], collect($this->api($token)->getJson('/api/teacher/activities?status=Approved&tier=Hard')->json('activities'))->pluck('title')->all());
        $this->assertSame(['Easy approved'], collect($this->api($token)->getJson('/api/teacher/activities?status=Approved&q=easy')->json('activities'))->pluck('title')->all());
        $this->assertSame(['Turned down'], collect($this->api($token)->getJson('/api/teacher/activities?status=Rejected')->json('activities'))->pluck('title')->all());
    }

    public function test_with_no_drafts_the_list_opens_on_approved(): void
    {
        [, $t, $token] = $this->teacher();
        $this->activity($t, ['status' => 'Approved']);

        $this->api($token)->getJson('/api/teacher/activities')->assertOk()->assertJsonPath('status', 'Approved');
    }

    public function test_one_activity_tells_why_it_is_in_its_level_and_where_it_can_be_given(): void
    {
        [, $t, $token] = $this->teacher();
        $class = $this->schoolClass($t);
        $kid = $this->child($class);
        $a = $this->activity($t, ['status' => 'Approved', 'difficulty_tier' => 'Medium']);
        ActivityAssignment::create(['activity_id' => $a->id, 'learner_id' => $kid->id, 'assigned_by_teacher_id' => $t->id]);

        $r = $this->api($token)->getJson("/api/teacher/activities/{$a->id}")->assertOk();

        $r->assertJsonPath('title', 'Farm words')->assertJsonPath('instructions', 'Read the words.')->assertJsonPath('competency', 'Foundational Reading')
            ->assertJsonPath('givenTo.0', 'Mia Cruz')->assertJsonPath('targets.classes.0.name', 'Sampaguita')->assertJsonPath('targets.learners.0.name', 'Mia Cruz');
        $this->assertSame(['ok', 'caution', 'blocked', 'unknown'], collect(['ok', 'caution', 'blocked', 'unknown'])->all());
        $this->assertContains($r->json('targets.classes.0.fit.verdict'), ['ok', 'caution', 'blocked', 'unknown']);
        $this->assertNotEmpty($r->json('why'));
        $this->assertArrayHasKey('band', $r->json());
    }

    public function test_another_teachers_activity_looks_like_it_does_not_exist(): void
    {
        [, , $token] = $this->teacher();
        [, $other] = $this->teacher();
        $theirs = $this->activity($other);

        $this->api($token)->getJson("/api/teacher/activities/{$theirs->id}")->assertNotFound();
        $this->api($token)->postJson("/api/teacher/activities/{$theirs->id}/place", ['level' => 'Easy'])->assertNotFound();
        $this->api($token)->postJson("/api/teacher/activities/{$theirs->id}/assign", ['class_id' => 1])->assertNotFound();
        $this->api($token)->postJson("/api/teacher/activities/{$theirs->id}/share", ['price_type' => 'Free'])->assertNotFound();
        $this->assertSame('Draft', $theirs->fresh()->status);
    }

    // ------------------------------------------------------------------ levels

    public function test_a_draft_placed_in_a_level_is_approved_there_and_a_reject_can_be_taken_back(): void
    {
        [, $t, $token] = $this->teacher();
        $draft = $this->activity($t);

        $this->api($token)->postJson("/api/teacher/activities/{$draft->id}/place", ['level' => 'Hard'])
            ->assertOk()->assertJsonPath('activity.status', 'Approved')->assertJsonPath('activity.level', 'Hard')->assertJsonPath('activity.aiLevel', 'Medium')->assertJsonPath('activity.moved', true)
            ->assertJsonPath('undo', false);
        $this->assertStringContainsString('The AI suggested Medium', $this->api($token)->postJson("/api/teacher/activities/{$this->activity($t)->id}/place", ['level' => 'Hard'])->json('message'));

        // Re-leveling an approved one.
        $this->api($token)->postJson("/api/teacher/activities/{$draft->id}/place", ['level' => 'Easy'])->assertOk()->assertJsonPath('message', 'Moved to Easy.');

        $other = $this->activity($t);
        $this->api($token)->postJson("/api/teacher/activities/{$other->id}/place", ['level' => 'reject'])->assertOk()->assertJsonPath('undo', true)->assertJsonPath('activity.status', 'Rejected');
        $this->api($token)->postJson("/api/teacher/activities/{$other->id}/place", ['level' => 'Easy'])->assertStatus(403);
        $this->api($token)->postJson("/api/teacher/activities/{$other->id}/restore")->assertOk()->assertJsonPath('activity.status', 'Draft');

        // Only a draft can be rejected, and only a rejected one restored.
        $this->api($token)->postJson("/api/teacher/activities/{$draft->id}/place", ['level' => 'reject'])->assertStatus(403);
        $this->api($token)->postJson("/api/teacher/activities/{$draft->id}/restore")->assertStatus(403);
        $this->api($token)->postJson("/api/teacher/activities/{$draft->id}/place", ['level' => 'Nope'])->assertStatus(422)->assertJsonValidationErrors('level');
    }

    public function test_editing_a_draft_approves_it_and_keeps_the_scored_text_in_step(): void
    {
        [, $t, $token] = $this->teacher();
        $draft = $this->activity($t);

        $this->api($token)->putJson("/api/teacher/activities/{$draft->id}", ['title' => 'Better words', 'instructions' => 'Say each word.', 'passage_text' => "ant   bee\ncat", 'level' => 'Easy'])
            ->assertOk()->assertJsonPath('activity.title', 'Better words')->assertJsonPath('activity.status', 'Approved')->assertJsonPath('activity.words', 3);

        $fresh = $draft->fresh();
        $this->assertSame('ant bee cat', $fresh->reference_text);
        $this->assertSame('Easy', $fresh->difficulty_tier);

        $this->api($token)->putJson("/api/teacher/activities/{$draft->id}", ['title' => 'x', 'instructions' => 'y', 'passage_text' => 'z'])->assertStatus(403);
        $this->api($token)->putJson("/api/teacher/activities/{$this->activity($t)->id}", ['title' => '', 'instructions' => '', 'passage_text' => ''])->assertStatus(422)->assertJsonValidationErrors(['title', 'instructions', 'passage_text']);
    }

    // ------------------------------------------------------------------ giving it out

    public function test_an_activity_can_be_given_to_exactly_one_place_and_one_that_is_too_long_is_refused(): void
    {
        [, $t, $token] = $this->teacher();
        $class = $this->schoolClass($t, 'Sampaguita');
        $kid = $this->child($class, 'Mia', 3);
        $good = $this->activity($t, ['status' => 'Approved']);

        $this->api($token)->postJson("/api/teacher/activities/{$good->id}/assign", ['class_id' => $class->id])->assertOk()->assertJsonPath('message', '"Farm words" assigned.');
        $this->assertSame(1, ActivityAssignment::where('activity_id', $good->id)->where('class_id', $class->id)->count());
        $this->api($token)->postJson("/api/teacher/activities/{$good->id}/assign", ['learner_id' => $kid->id])->assertOk();
        $this->api($token)->postJson("/api/teacher/activities/{$good->id}/assign", [])->assertStatus(422)->assertJsonValidationErrors('assign_target');
        $this->api($token)->postJson("/api/teacher/activities/{$good->id}/assign", ['class_id' => $class->id, 'learner_id' => $kid->id])->assertStatus(422);

        // A draft cannot be given out, nor to someone else's learner.
        $this->api($token)->postJson("/api/teacher/activities/{$this->activity($t)->id}/assign", ['class_id' => $class->id])->assertStatus(403);
        [, $other] = $this->teacher();
        $theirs = $this->child($this->schoolClass($other, 'Rose'), 'Zed');
        $this->api($token)->postJson("/api/teacher/activities/{$good->id}/assign", ['learner_id' => $theirs->id])->assertStatus(403);

        // Far too long for children who are only starting to read: not given, with the website's reason.
        $starters = $this->schoolClass($t, 'Starters');
        $this->child($starters, 'Ana', 0);
        $long = $this->activity($t, ['status' => 'Approved', 'title' => 'The Big Story', 'word_count' => 69, 'passage_text' => str_repeat('word ', 69)]);
        $r = $this->api($token)->postJson("/api/teacher/activities/{$long->id}/assign", ['class_id' => $starters->id])->assertStatus(422);
        $this->assertStringContainsString('too long', $r->json('errors.assign_target.0'));
        $this->assertSame(0, ActivityAssignment::where('activity_id', $long->id)->count());
    }

    public function test_an_activity_can_be_given_to_a_reading_group_of_a_class_but_not_twice(): void
    {
        [, $t, $token] = $this->teacher();
        $class = $this->schoolClass($t);
        $this->child($class, 'Mia', 3);
        $a = $this->activity($t, ['status' => 'Approved']);

        $this->api($token)->postJson("/api/teacher/classes/{$class->id}/assign", ['activity_id' => $a->id, 'reading_band' => 'instructional'])
            ->assertOk()->assertJsonPath('message', '"Farm words" assigned to the instructional group in Sampaguita.');
        $this->assertSame('instructional', ActivityAssignment::where('activity_id', $a->id)->first()->reading_band);

        $this->api($token)->postJson("/api/teacher/classes/{$class->id}/assign", ['activity_id' => $a->id, 'reading_band' => 'instructional'])
            ->assertStatus(422)->assertJsonValidationErrors('activity_id');
        $this->api($token)->postJson("/api/teacher/classes/{$class->id}/assign", ['activity_id' => $a->id, 'reading_band' => 'wrong'])->assertStatus(422);
        $this->api($token)->postJson("/api/teacher/classes/{$class->id}/assign", ['activity_id' => $a->id])->assertOk();
    }

    public function test_sharing_earns_two_credits_once_and_a_pending_teacher_is_locked_out_of_changes(): void
    {
        [, $t, $token] = $this->teacher('Active', 0);
        $a = $this->activity($t, ['status' => 'Approved']);

        $this->api($token)->postJson("/api/teacher/activities/{$a->id}/share", ['price_type' => 'Paid'])->assertStatus(422)->assertJsonValidationErrors('price');
        $this->api($token)->postJson("/api/teacher/activities/{$a->id}/share", ['price_type' => 'Paid', 'price' => 25])
            ->assertOk()->assertJsonPath('credits', 2);
        $this->assertSame(2, $t->fresh()->free_generation_credits_remaining);
        $this->assertSame('Paid', $a->fresh()->repositoryListing->price_type);
        $this->api($token)->postJson("/api/teacher/activities/{$a->id}/share", ['price_type' => 'Free'])->assertStatus(403);
        $this->assertSame(2, $t->fresh()->free_generation_credits_remaining);

        // Pending: can look, cannot change.
        [, $p, $pToken] = $this->teacher('Pending');
        $draft = $this->activity($p);
        $this->api($pToken)->getJson('/api/teacher/activities')->assertOk()->assertJsonPath('locked', true);
        $this->api($pToken)->getJson("/api/teacher/activities/{$draft->id}")->assertOk();
        foreach ([['post', 'place', ['level' => 'Easy']], ['post', 'restore', []], ['post', 'assign', ['class_id' => 1]], ['post', 'share', ['price_type' => 'Free']]] as [$m, $path, $body]) {
            $this->api($pToken)->postJson("/api/teacher/activities/{$draft->id}/{$path}", $body)->assertStatus(403)->assertJsonPath('message', 'Locked until your school verification is approved.');
        }
        $this->api($pToken)->putJson("/api/teacher/activities/{$draft->id}", ['title' => 'x', 'instructions' => 'y', 'passage_text' => 'z'])->assertStatus(403);
        $this->assertSame('Draft', $draft->fresh()->status);
    }

    // ------------------------------------------------------------------ asking for new ones

    public function test_generate_options_give_the_screen_everything_it_needs(): void
    {
        [, $t, $token] = $this->teacher();
        $this->schoolClass($t);
        $this->activity($t, ['status' => 'Approved', 'difficulty_tier' => 'Easy']);

        $r = $this->api($token)->getJson('/api/teacher/activities/generate-options')->assertOk();

        $r->assertJsonPath('credits', 2)->assertJsonPath('maxPerLevel', 5)->assertJsonPath('classes.0.name', 'Sampaguita')->assertJsonPath('generation', null);
        $this->assertSame(['Grade 1', 'Grade 2'], $r->json('grades'));
        $this->assertNotEmpty($r->json('competencies.0.types'));
        $this->assertSame([1, 0], $r->json('have.1.word_reading.Easy'));
        $this->assertArrayHasKey('word_reading', $r->json('bands'));
    }

    public function test_asking_for_activities_saves_the_request_and_answers_at_once(): void
    {
        Queue::fake();
        [, $t, $token] = $this->teacher();
        $class = $this->schoolClass($t);
        $body = ['grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'activity_type' => 'word_reading', 'levels' => ['Easy' => 0, 'Medium' => 2, 'Hard' => 0], 'topic' => 'Animals', 'class_id' => $class->id];

        $r = $this->api($token)->postJson('/api/teacher/activities/generate', $body)->assertStatus(202);
        $r->assertJsonPath('generation.status', ActivityGeneration::QUEUED)->assertJsonPath('generation.total', 2);
        $generation = ActivityGeneration::firstOrFail();
        $this->assertSame($class->id, $generation->class_id);
        Queue::assertPushed(GenerateActivitiesJob::class, fn ($job) => $job->generationId === $generation->id);
        $this->assertSame(2, $t->fresh()->free_generation_credits_remaining, 'nothing is charged until the activities are saved');

        // The list follows the running request, and a second one waits for it.
        $this->api($token)->getJson('/api/teacher/activities')->assertOk()->assertJsonPath('generation.id', $generation->id)->assertJsonPath('generation.active', true);
        $this->api($token)->postJson('/api/teacher/activities/generate', $body)->assertStatus(422)->assertJsonValidationErrors('generate');
        $this->api($token)->getJson("/api/teacher/activities/generations/{$generation->id}")->assertOk()->assertJsonPath('status', ActivityGeneration::QUEUED);

        $generation->update(['status' => ActivityGeneration::DONE, 'message' => '2 drafts added to To review: 2 Medium.', 'finished_at' => now()]);
        $this->api($token)->postJson("/api/teacher/activities/generations/{$generation->id}/dismiss")->assertOk();
        $this->api($token)->getJson('/api/teacher/activities')->assertJsonPath('generation', null);
    }

    public function test_a_bad_request_for_activities_is_refused_with_the_reason_and_costs_nothing(): void
    {
        Queue::fake();
        [, $t, $token] = $this->teacher('Active', 0);
        $ask = fn (array $o = []) => $this->api($token)->postJson('/api/teacher/activities/generate', $o + ['grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'activity_type' => 'word_reading', 'levels' => ['Medium' => 1]]);

        $ask()->assertStatus(422)->assertJsonValidationErrors('credits');
        $t->update(['free_generation_credits_remaining' => 2]);
        $ask(['levels' => ['Easy' => 0]])->assertStatus(422)->assertJsonValidationErrors('levels');
        $ask(['activity_type' => 'nope'])->assertStatus(422)->assertJsonValidationErrors('activity_type');
        $ask(['class_id' => 9999])->assertStatus(422)->assertJsonValidationErrors('class_id');
        $this->assertSame(0, ActivityGeneration::count());

        // A Pending teacher may use the free credits (Teacher Actor Prompt Step 3).
        [, $p, $pToken] = $this->teacher('Pending');
        $this->api($pToken)->postJson('/api/teacher/activities/generate', ['grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'activity_type' => 'word_reading', 'levels' => ['Medium' => 1]])->assertStatus(202);
    }
}
