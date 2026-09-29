<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityAssignment;
use App\Models\ActivityBundle;
use App\Models\Learner;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use App\Services\LearnerAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Activity Bundles — the instructor's relayed request: create a named bundle ("Bundle 1"), drop
 * Approved activities into it, then assign the whole bundle to a class. An activity dropped into a
 * bundle that is already assigned to a class must reach that class immediately, with no separate
 * assign step — the real point of this feature, proven end to end below (test_*_reaches_the_class_
 * immediately_once_the_bundle_is_already_assigned).
 */
class ActivityBundleTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function teacher(string $status = 'Active'): array
    {
        $n = ++$this->seq;
        $user = User::create(['first_name' => 'Ana', 'last_name' => "Reyes{$n}", 'email' => "t{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        $user->forceFill(['email_verified_at' => now()])->save();
        $teacher = Teacher::create(['user_id' => $user->id, 'school_name' => 'Rizal ES', 'employee_id' => "E{$n}", 'status' => $status, 'free_generation_credits_remaining' => 2]);

        return [$user, $teacher];
    }

    private function klass(Teacher $t, array $o = []): SchoolClass
    {
        return SchoolClass::create($o + ['teacher_id' => $t->id, 'name' => 'Rizal', 'grade_level' => 'Grade 2', 'section' => 'Section B', 'group_tag' => null, 'school_year' => SchoolClass::currentSchoolYear()]);
    }

    private function learner(?SchoolClass $c = null, array $o = []): Learner
    {
        $n = ++$this->seq;

        return Learner::create($o + [
            'learner_code' => 'TB-'.(10000 + $n), 'class_id' => $c?->id, 'first_name' => "Kid{$n}", 'last_name' => 'Cruz',
            'grade_level' => $c?->grade_level ?? 'Grade 2', 'pin' => '1234', 'avatar_id' => 'A', 'mastery_level' => 'Developing',
        ]);
    }

    private function activity(Teacher $t, array $o = []): Activity
    {
        $tier = $o['difficulty_tier'] ?? 'Medium';

        return Activity::create($o + [
            'created_by_teacher_id' => $t->id, 'grade_level' => 'Grade 2', 'competency' => 'reading_fluency', 'competency_label' => 'Reading Fluency',
            'activity_type' => 'sentence_reading', 'difficulty_tier' => $tier, 'ai_difficulty_tier' => $tier, 'title' => 'Medium Market Sentences',
            'instructions' => 'Read the sentences out loud.', 'passage_text' => 'Nanay goes to the market every Saturday.',
            'word_count' => 8, 'status' => 'Draft', 'reading_features' => ['Grade level words'],
        ]);
    }

    // ---------------------------------------------------------------- creating and dropping in

    public function test_a_teacher_creates_a_bundle_and_drops_an_approved_activity_into_it(): void
    {
        [$user, $teacher] = $this->teacher();
        $approved = $this->activity($teacher, ['title' => 'Easy Animal Words', 'status' => 'Approved']);
        $this->actingAs($user);

        $this->post(route('teacher.bundles.store'), ['name' => 'Bundle 1'])
            ->assertRedirect(route('teacher.activities.index'))
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'Bundle "Bundle 1" created'));

        $bundle = ActivityBundle::where('teacher_id', $teacher->id)->firstOrFail();
        $this->assertSame('Bundle 1', $bundle->name);

        $this->postJson(route('teacher.bundles.activities.add', $bundle), ['activity_id' => $approved->id])
            ->assertOk()
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Easy Animal Words') && str_contains($m, 'Assign the bundle'));

        $this->assertTrue($bundle->fresh()->activities->contains('id', $approved->id));

        $this->get(route('teacher.activities.index'))->assertOk()->assertSee('Bundle 1')->assertSee('1 activity');
        $this->get(route('teacher.bundles.window', $bundle))->assertOk()->assertSee('Easy Animal Words')->assertSee('Not assigned to a class yet');
    }

    public function test_only_an_approved_activity_can_join_a_bundle(): void
    {
        [$user, $teacher] = $this->teacher();
        $draft = $this->activity($teacher, ['status' => 'Draft']);
        $bundle = ActivityBundle::create(['teacher_id' => $teacher->id, 'name' => 'Bundle 1']);
        $this->actingAs($user);

        $this->post(route('teacher.bundles.activities.add', $bundle), ['activity_id' => $draft->id])->assertForbidden();
        $this->assertFalse($bundle->fresh()->activities->contains('id', $draft->id));
    }

    public function test_a_teacher_cannot_add_another_teachers_activity_or_touch_another_teachers_bundle(): void
    {
        [$userA, $teacherA] = $this->teacher();
        [$userB, $teacherB] = $this->teacher();
        $othersApproved = $this->activity($teacherB, ['status' => 'Approved']);
        $myBundle = ActivityBundle::create(['teacher_id' => $teacherA->id, 'name' => 'Bundle 1']);
        $othersBundle = ActivityBundle::create(['teacher_id' => $teacherB->id, 'name' => "B's Bundle"]);
        $this->actingAs($userA);

        $this->post(route('teacher.bundles.activities.add', $myBundle), ['activity_id' => $othersApproved->id])->assertForbidden();
        $this->get(route('teacher.bundles.window', $othersBundle))->assertForbidden();
        $this->post(route('teacher.bundles.destroy', $othersBundle))->assertForbidden();
        $this->assertNotNull($othersBundle->fresh());
    }

    public function test_adding_from_the_activitys_own_window_reaches_the_same_pivot_row(): void
    {
        [$user, $teacher] = $this->teacher();
        $approved = $this->activity($teacher, ['status' => 'Approved']);
        $bundle = ActivityBundle::create(['teacher_id' => $teacher->id, 'name' => 'Bundle 1']);
        $this->actingAs($user);

        $this->get(route('teacher.activities.window', $approved))->assertOk()->assertSee('Bundles')->assertSee('Not in a bundle yet');

        $this->post(route('teacher.activities.bundles.add', $approved), ['bundle_id' => $bundle->id])
            ->assertRedirect();

        $this->assertTrue($bundle->fresh()->activities->contains('id', $approved->id));
        $this->get(route('teacher.activities.window', $approved))->assertOk()->assertSee('Bundle 1');
    }

    // ---------------------------------------------------------------- assigning to a class

    public function test_a_teacher_assigns_a_bundle_to_their_own_class(): void
    {
        [$user, $teacher] = $this->teacher();
        $class = $this->klass($teacher);
        $bundle = ActivityBundle::create(['teacher_id' => $teacher->id, 'name' => 'Bundle 1']);
        $this->actingAs($user);

        $this->post(route('teacher.classes.bundles.assign', $class), ['bundle_id' => $bundle->id])
            ->assertRedirect(route('teacher.classes.index', ['school_year' => $class->school_year, 'open' => $class->id, 'tab' => 'bundles']));

        $this->assertTrue($class->bundles()->where('activity_bundle_id', $bundle->id)->exists());
        $this->get(route('teacher.classes.index'))->assertOk()->assertSee('Bundle 1');
    }

    public function test_a_teacher_cannot_assign_a_bundle_to_another_teachers_class_or_a_past_year_class(): void
    {
        [$userA, $teacherA] = $this->teacher();
        [$userB, $teacherB] = $this->teacher();
        $othersClass = $this->klass($teacherB);
        $bundle = ActivityBundle::create(['teacher_id' => $teacherA->id, 'name' => 'Bundle 1']);
        $pastClass = $this->klass($teacherA, ['school_year' => '2019-2020']);
        $this->actingAs($userA);

        $this->post(route('teacher.classes.bundles.assign', $othersClass), ['bundle_id' => $bundle->id])->assertForbidden();
        $this->post(route('teacher.classes.bundles.assign', $pastClass), ['bundle_id' => $bundle->id])->assertForbidden();
    }

    public function test_a_pending_teacher_cannot_mutate_bundles(): void
    {
        [$user, $teacher] = $this->teacher('Pending');
        $approved = $this->activity($teacher, ['status' => 'Approved']);
        $bundle = ActivityBundle::create(['teacher_id' => $teacher->id, 'name' => 'Bundle 1']);
        $this->actingAs($user);

        // EnsureTeacherIsActive answers a background (JSON) call with a real 403, and a plain
        // request with a redirect back and a flash message — same split every other 'teacher.active'
        // route already uses (see TeacherRedesignTest).
        $this->postJson(route('teacher.bundles.store'), ['name' => 'Another'])->assertStatus(403);
        $this->postJson(route('teacher.bundles.activities.add', $bundle), ['activity_id' => $approved->id])->assertStatus(403);
        $this->post(route('teacher.bundles.store'), ['name' => 'Another'])->assertSessionHas('classError');
        $this->assertSame(1, ActivityBundle::where('teacher_id', $teacher->id)->count());

        // Viewing stays open, same "approval gates touching real students, not visibility" rule
        // as every other Teacher screen (the activity window itself has no 'teacher.active' guard
        // either) — a Pending teacher can still look.
        $this->get(route('teacher.bundles.window', $bundle))->assertOk()->assertSee('Bundle 1');
    }

    public function test_unassigning_a_bundle_and_removing_an_activity(): void
    {
        [$user, $teacher] = $this->teacher();
        $class = $this->klass($teacher);
        $approved = $this->activity($teacher, ['status' => 'Approved']);
        $bundle = ActivityBundle::create(['teacher_id' => $teacher->id, 'name' => 'Bundle 1']);
        $bundle->activities()->attach($approved->id, ['added_at' => now()]);
        $bundle->classes()->attach($class->id, ['assigned_at' => now()]);
        $this->actingAs($user);

        $this->post(route('teacher.bundles.classes.remove', [$bundle, $class]))->assertRedirect();
        $this->assertFalse($bundle->fresh()->classes->contains('id', $class->id));

        $this->post(route('teacher.bundles.activities.remove', [$bundle, $approved]))->assertRedirect();
        $this->assertFalse($bundle->fresh()->activities->contains('id', $approved->id));

        $this->post(route('teacher.classes.bundles.assign', $class), ['bundle_id' => $bundle->id]);
        $this->post(route('teacher.classes.bundles.remove', [$class, $bundle]))->assertRedirect();
        $this->assertFalse($bundle->fresh()->classes->contains('id', $class->id));
    }

    public function test_deleting_a_bundle_leaves_the_activity_approved(): void
    {
        [$user, $teacher] = $this->teacher();
        $approved = $this->activity($teacher, ['status' => 'Approved']);
        $bundle = ActivityBundle::create(['teacher_id' => $teacher->id, 'name' => 'Bundle 1']);
        $bundle->activities()->attach($approved->id, ['added_at' => now()]);
        $this->actingAs($user);

        $this->post(route('teacher.bundles.destroy', $bundle))->assertRedirect(route('teacher.activities.index'));

        $this->assertNull(ActivityBundle::find($bundle->id));
        $this->assertSame('Approved', $approved->fresh()->status);
    }

    // ---------------------------------------------------------------- the Learner-side point of the feature

    public function test_an_activity_in_an_already_assigned_bundle_reaches_the_learner_immediately(): void
    {
        [$user, $teacher] = $this->teacher();
        $class = $this->klass($teacher);
        $kid = $this->learner($class);
        $bundle = ActivityBundle::create(['teacher_id' => $teacher->id, 'name' => 'Bundle 1']);
        $bundle->classes()->attach($class->id, ['assigned_at' => now()]);

        // Assigned to the class BEFORE the bundle has anything in it yet — the Learner correctly
        // sees nothing from it.
        $service = app(LearnerAuthService::class);
        $this->assertCount(0, $service->findActivityOptions($kid->fresh()));

        // A second Approved activity is dropped into the bundle afterward, with no further step.
        $laterActivity = $this->activity($teacher, ['title' => 'Dropped In Later', 'status' => 'Approved']);
        $this->actingAs($user);
        $this->postJson(route('teacher.bundles.activities.add', $bundle), ['activity_id' => $laterActivity->id])
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'already reaching'));

        $options = $service->findActivityOptions($kid->fresh());
        $this->assertCount(1, $options);
        $this->assertSame('Dropped In Later', $options->first()['activity']->title);
        $this->assertSame('Assigned by your Teacher', $options->first()['source']);
    }

    public function test_a_direct_class_assignment_and_a_bundle_assignment_are_merged_not_one_overriding_the_other(): void
    {
        [$user, $teacher] = $this->teacher();
        $class = $this->klass($teacher);
        $kid = $this->learner($class);
        $direct = $this->activity($teacher, ['title' => 'Direct To Class', 'status' => 'Approved']);
        ActivityAssignment::create(['activity_id' => $direct->id, 'class_id' => $class->id, 'assigned_by_teacher_id' => $teacher->id]);

        $bundle = ActivityBundle::create(['teacher_id' => $teacher->id, 'name' => 'Bundle 1']);
        $viaBundle = $this->activity($teacher, ['title' => 'Via Bundle', 'status' => 'Approved']);
        $bundle->activities()->attach($viaBundle->id, ['added_at' => now()]);
        $bundle->classes()->attach($class->id, ['assigned_at' => now()]);

        $options = app(LearnerAuthService::class)->findActivityOptions($kid->fresh());
        $titles = $options->pluck('activity.title')->sort()->values()->all();
        $this->assertSame(['Direct To Class', 'Via Bundle'], $titles);
    }

    public function test_a_direct_to_learner_assignment_still_wins_over_a_bundle(): void
    {
        [$user, $teacher] = $this->teacher();
        $class = $this->klass($teacher);
        $kid = $this->learner($class);
        $directToLearner = $this->activity($teacher, ['title' => 'Just For Kid', 'status' => 'Approved']);
        ActivityAssignment::create(['activity_id' => $directToLearner->id, 'learner_id' => $kid->id, 'assigned_by_teacher_id' => $teacher->id]);

        $bundle = ActivityBundle::create(['teacher_id' => $teacher->id, 'name' => 'Bundle 1']);
        $viaBundle = $this->activity($teacher, ['title' => 'Via Bundle', 'status' => 'Approved']);
        $bundle->activities()->attach($viaBundle->id, ['added_at' => now()]);
        $bundle->classes()->attach($class->id, ['assigned_at' => now()]);

        $options = app(LearnerAuthService::class)->findActivityOptions($kid->fresh());
        $this->assertCount(1, $options);
        $this->assertSame('Just For Kid', $options->first()['activity']->title);
    }

    public function test_a_rejected_bundle_activity_never_reaches_the_learner(): void
    {
        [$user, $teacher] = $this->teacher();
        $class = $this->klass($teacher);
        $kid = $this->learner($class);
        $bundle = ActivityBundle::create(['teacher_id' => $teacher->id, 'name' => 'Bundle 1']);
        $bundle->classes()->attach($class->id, ['assigned_at' => now()]);

        $rejected = $this->activity($teacher, ['title' => 'Once Approved, Now Rejected', 'status' => 'Approved']);
        $bundle->activities()->attach($rejected->id, ['added_at' => now()]);
        $rejected->update(['status' => 'Rejected']);

        $options = app(LearnerAuthService::class)->findActivityOptions($kid->fresh());
        $this->assertCount(0, $options);
    }

    /**
     * A real bug caught live in the browser, not by the tests above: 'on' for a bundle-sourced
     * row was a raw string from the pivot (activity_bundle_classes.assigned_at isn't cast), so
     * Blade's ->format('M j') call threw. Also checks the class card's own count and the row's
     * label, both of which need the class's own eager-loaded 'bundles.activities'.
     */
    public function test_the_classes_screen_renders_a_bundle_sourced_activity_without_error(): void
    {
        [$user, $teacher] = $this->teacher();
        $class = $this->klass($teacher);
        $this->learner($class);
        $approved = $this->activity($teacher, ['title' => 'Via Bundle Row', 'status' => 'Approved']);
        $bundle = ActivityBundle::create(['teacher_id' => $teacher->id, 'name' => 'Bundle 1']);
        $bundle->activities()->attach($approved->id, ['added_at' => now()]);
        $bundle->classes()->attach($class->id, ['assigned_at' => now()]);
        $this->actingAs($user);

        $this->get(route('teacher.classes.index'))
            ->assertOk()
            ->assertSee('1 activity assigned');

        $this->get(route('teacher.classes.index', ['open' => $class->id, 'tab' => 'acts']))
            ->assertOk()
            ->assertSee('Via Bundle Row')
            ->assertSee('Through bundle Bundle 1');
    }

    public function test_unassigning_the_bundle_from_the_class_removes_it_from_the_learners_picker(): void
    {
        [$user, $teacher] = $this->teacher();
        $class = $this->klass($teacher);
        $kid = $this->learner($class);
        $bundle = ActivityBundle::create(['teacher_id' => $teacher->id, 'name' => 'Bundle 1']);
        $approved = $this->activity($teacher, ['status' => 'Approved']);
        $bundle->activities()->attach($approved->id, ['added_at' => now()]);
        $bundle->classes()->attach($class->id, ['assigned_at' => now()]);

        $this->assertCount(1, app(LearnerAuthService::class)->findActivityOptions($kid->fresh()));

        $bundle->classes()->detach($class->id);

        $this->assertCount(0, app(LearnerAuthService::class)->findActivityOptions($kid->fresh()));
    }
}
