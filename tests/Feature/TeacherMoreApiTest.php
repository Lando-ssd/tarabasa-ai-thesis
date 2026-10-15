<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityAssignment;
use App\Models\Learner;
use App\Models\ParentAccount;
use App\Models\PromotionRecord;
use App\Models\ReadingSession;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use App\Support\LearnerCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The phone app's Alerts, Analytics, Promotions and Profile (/api/teacher/... and /api/auth/profile...): the same classes and rules as the
 * website's pages, over JSON.
 */
class TeacherMoreApiTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    /** A request as the owner of this token, from a clean slate (the test app keeps the signed in person between requests). */
    private function api(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function teacher(string $status = 'Active', array $grades = ['Grade 1', 'Grade 2']): array
    {
        $n = ++$this->seq;
        $user = User::create(['first_name' => 'Ana', 'last_name' => "Reyes{$n}", 'email' => "tmore{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        $user->forceFill(['email_verified_at' => now()])->save();
        $teacher = Teacher::create(['user_id' => $user->id, 'school_name' => 'Rizal ES', 'employee_id' => "TM{$n}", 'status' => $status, 'free_generation_credits_remaining' => 2, 'grades_handled' => $grades]);

        return [$user, $teacher, $user->createToken('mobile')->plainTextToken];
    }

    private function schoolClass(Teacher $teacher, string $grade = 'Grade 1', string $name = 'Sampaguita'): SchoolClass
    {
        return SchoolClass::create(['teacher_id' => $teacher->id, 'name' => $name, 'grade_level' => $grade, 'section' => 'A', 'school_year' => SchoolClass::currentSchoolYear()]);
    }

    private function child(?SchoolClass $class, string $first = 'Mia', int $rung = 2, string $grade = 'Grade 1'): Learner
    {
        return Learner::create([
            'learner_code' => LearnerCode::generate(), 'class_id' => $class?->id, 'first_name' => $first, 'last_name' => 'Cruz', 'grade_level' => $grade,
            'pin' => '1234', 'avatar_id' => 'A', 'mastery_level' => $rung <= 2 ? 'Beginning' : ($rung <= 4 ? 'Developing' : 'Proficient'), 'reading_rung' => $rung,
        ]);
    }

    private function activity(Teacher $teacher, string $title = 'Easy words', int $words = 6, string $tier = 'Easy'): Activity
    {
        return Activity::create([
            'created_by_teacher_id' => $teacher->id, 'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'competency_label' => 'Foundational Reading',
            'activity_type' => 'word_reading', 'title' => $title, 'instructions' => 'Read.', 'passage_text' => 'cat dog pig hen cow bat', 'reference_text' => 'cat dog pig hen cow bat',
            'word_count' => $words, 'status' => 'Approved', 'difficulty_tier' => $tier,
        ]);
    }

    private function reading(Learner $kid, Activity $activity, float $accuracy, int $daysAgo = 1, ?array $feedback = null): ReadingSession
    {
        $row = ReadingSession::create([
            'learner_id' => $kid->id, 'activity_id' => $activity->id, 'session_type' => 'Practice', 'initiated_by' => 'Teacher', 'accuracy_percent' => $accuracy,
            'flagged_needs_attention' => $accuracy < 70, 'word_feedback' => $feedback,
        ]);
        // "Today" is a few minutes ago: a reading made in the same second as the request would not be counted yet.
        $row->timestamp = $daysAgo === 0 ? now()->subMinutes(5) : now()->subDays($daysAgo);
        $row->save();

        return $row;
    }

    // ------------------------------------------------------------------ alerts

    public function test_alerts_say_who_needs_what_with_the_reason_and_one_suggested_activity(): void
    {
        [, $t, $token] = $this->teacher();
        $class = $this->schoolClass($t);
        $leo = $this->child($class, 'Leo', 2);
        $ana = $this->child($class, 'Ana', 0);
        $easy = $this->activity($t, 'Sounds at the Farm');
        $this->reading($leo, $easy, 25);

        $r = $this->api($token)->getJson('/api/teacher/alerts')->assertOk();

        $r->assertJsonPath('counts.all', 2)->assertJsonPath('counts.support', 1)->assertJsonPath('counts.start', 1)->assertJsonPath('filter', 'all');
        $this->assertSame(['support', 'start'], collect($r->json('alerts'))->pluck('kind')->all(), 'support comes first');
        $support = $r->json('alerts.0');
        $this->assertSame('Leo Cruz', $support['name']);
        $this->assertSame('Sampaguita', $support['className']);
        $this->assertStringContainsString('needs support', $support['title']);
        $this->assertSame('Sounds at the Farm', $support['suggestion']['activity']['title']);
        $this->assertSame('Easy', $support['suggestion']['level']);
        $this->assertStringContainsString('kind', json_encode($r->json('alerts.1')) ? 'kind' : '');

        $only = $this->api($token)->getJson('/api/teacher/alerts?filter=start')->assertOk();
        $this->assertSame(['start'], collect($only->json('alerts'))->pluck('kind')->all());
        $this->assertSame('Ana Cruz', $only->json('alerts.0.name'));
    }

    public function test_marking_an_alert_handled_hides_it_and_a_suggested_activity_can_be_given_with_one_tap(): void
    {
        [, $t, $token] = $this->teacher();
        $class = $this->schoolClass($t);
        $leo = $this->child($class, 'Leo', 2);
        $easy = $this->activity($t, 'Sounds at the Farm');
        $this->reading($leo, $easy, 25);

        $this->api($token)->postJson("/api/teacher/alerts/{$leo->id}/assign", ['kind' => 'support', 'activity_id' => $easy->id])
            ->assertOk()->assertJsonPath('message', '"Sounds at the Farm" assigned to Leo.');
        $this->assertSame(1, ActivityAssignment::where('learner_id', $leo->id)->where('activity_id', $easy->id)->count());
        // Giving the suggestion also marks that alert handled.
        $this->api($token)->getJson('/api/teacher/alerts')->assertJsonPath('counts.support', 0);

        // Handled by itself, for an alert that has no suggestion.
        $ana = $this->child($class, 'Ana', 0);
        $this->api($token)->getJson('/api/teacher/alerts')->assertJsonPath('counts.start', 1);
        $this->api($token)->postJson("/api/teacher/alerts/{$ana->id}/handled", ['kind' => 'start'])->assertOk()->assertJsonPath('message', 'Marked handled for Ana.');
        $this->api($token)->getJson('/api/teacher/alerts')->assertJsonPath('counts.all', 0);

        // Only the teacher's own learners, and only their own approved activities.
        [, $other, $otherToken] = $this->teacher();
        $this->api($otherToken)->postJson("/api/teacher/alerts/{$leo->id}/handled", ['kind' => 'support'])->assertNotFound();
        $draft = $this->activity($t, 'A draft');
        $draft->update(['status' => 'Draft']);
        $this->api($token)->postJson("/api/teacher/alerts/{$leo->id}/assign", ['kind' => 'support', 'activity_id' => $draft->id])->assertStatus(403);
        $this->api($token)->postJson("/api/teacher/alerts/{$leo->id}/handled", ['kind' => 'nonsense'])->assertStatus(422);
    }

    public function test_an_alert_will_not_give_a_child_an_activity_that_is_far_too_long_and_a_pending_teacher_cannot_give_one(): void
    {
        [, $t, $token] = $this->teacher();
        $class = $this->schoolClass($t);
        $ana = $this->child($class, 'Ana', 0);
        $long = $this->activity($t, 'The Big Story', 69, 'Hard');

        $r = $this->api($token)->postJson("/api/teacher/alerts/{$ana->id}/assign", ['kind' => 'start', 'activity_id' => $long->id])->assertStatus(422);
        $this->assertStringContainsString('too long', $r->json('errors.activity_id.0'));
        $this->assertSame(0, ActivityAssignment::count());

        [, $p, $pToken] = $this->teacher('Pending');
        $pc = $this->schoolClass($p, 'Grade 1', 'Pendings');
        $kid = $this->child($pc, 'Pia', 0);
        $this->api($pToken)->getJson('/api/teacher/alerts')->assertOk()->assertJsonPath('counts.start', 1);
        $this->api($pToken)->postJson("/api/teacher/alerts/{$kid->id}/assign", ['kind' => 'start', 'activity_id' => $this->activity($p)->id])->assertStatus(403);
    }

    // ------------------------------------------------------------------ analytics

    public function test_analytics_gives_the_overview_in_the_departments_reading_levels(): void
    {
        [, $t, $token] = $this->teacher();
        $class = $this->schoolClass($t);
        $a = $this->child($class, 'Ana', 3);
        $b = $this->child($class, 'Ben', 5);
        $this->child($class, 'Cy', 0);
        $act = $this->activity($t, 'Farm words');
        $miss = [['reference' => 'hen', 'spoken' => 'hand', 'status' => 'substitution'], ['reference' => 'cow', 'spoken' => 'cow', 'status' => 'correct']];
        $this->reading($a, $act, 80, 0, $miss);
        $this->reading($b, $act, 100, 0, $miss);
        // Given a week ago: an assignment counts the readings made since it was given.
        $given = ActivityAssignment::create(['activity_id' => $act->id, 'class_id' => $class->id, 'assigned_by_teacher_id' => $t->id]);
        $given->assigned_at = now()->subWeek();
        $given->save();

        $r = $this->api($token)->getJson('/api/teacher/analytics?period=month')->assertOk();

        $r->assertJsonPath('period', 'month')->assertJsonPath('learners', 3)->assertJsonPath('scope', 'All your classes')->assertJsonPath('average', 90);
        $this->assertSame(2, $r->json('readings'));
        $levels = collect($r->json('levels'))->keyBy('key');
        $this->assertSame(1, $levels['non']['count']);
        $this->assertSame(1, $levels['instructional']['count']);
        $this->assertSame(1, $levels['independent']['count']);
        $this->assertSame('Non-reader', $levels['non']['label']);
        $this->assertSame(2, $r->json('missedWords.hen'));
        $this->assertSame('Farm words', $r->json('assignments.0.title'));
        $this->assertSame(2, $r->json('assignments.0.done'));

        // One class, by id; a stranger's class id falls back to all of this teacher's own.
        $this->api($token)->getJson("/api/teacher/analytics?class={$class->id}")->assertOk()->assertJsonPath('scope', 'Sampaguita');
        [, $other] = $this->teacher();
        $theirs = $this->schoolClass($other, 'Grade 1', 'Rose');
        $this->api($token)->getJson("/api/teacher/analytics?class={$theirs->id}")->assertOk()->assertJsonPath('scope', 'All your classes');
    }

    // ------------------------------------------------------------------ promotions

    public function test_a_grade_1_learner_is_released_and_another_teacher_with_a_grade_2_class_claims_them(): void
    {
        [, $t, $token] = $this->teacher();
        $class = $this->schoolClass($t, 'Grade 1');
        $mia = $this->child($class, 'Mia');
        $g3 = $this->child($this->schoolClass($t, 'Grade 2', 'Dahlia'), 'Gus', 4, 'Grade 2');

        $list = $this->api($token)->getJson('/api/teacher/promotions')->assertOk();
        $sampaguita = collect($list->json('release'))->firstWhere('name', 'Sampaguita');
        $this->assertSame('Grade 2', collect($sampaguita['learners'])->firstWhere('name', 'Mia Cruz')['nextGrade']);

        $this->api($token)->postJson("/api/teacher/promotions/{$mia->id}/release")->assertOk()->assertJsonPath('message', 'Mia released for promotion to Grade 2.');
        $this->assertNull($mia->fresh()->class_id);
        $record = PromotionRecord::firstOrFail();
        $this->assertSame('Pending', $record->status);

        // Released, so no longer in the class and cannot be released twice.
        $this->api($token)->postJson("/api/teacher/promotions/{$mia->id}/release")->assertStatus(403);

        // Another teacher sees the queue and can claim with a matching Grade 2 class of their own.
        [, $other, $otherToken] = $this->teacher();
        $g2 = $this->schoolClass($other, 'Grade 2', 'Orchid');
        $queue = $this->api($otherToken)->getJson('/api/teacher/promotions')->assertOk();
        $queue->assertJsonPath('claim.0.name', 'Mia Cruz')->assertJsonPath('claim.0.nextGrade', 'Grade 2')->assertJsonPath('claim.0.classes.0.name', 'Orchid')->assertJsonPath('claimable', 1);

        // A class that is not theirs, or the wrong grade, is refused.
        $this->api($otherToken)->postJson("/api/teacher/promotions/{$record->id}/claim", ['class_id' => $class->id])->assertStatus(403);
        $this->api($otherToken)->postJson("/api/teacher/promotions/{$record->id}/claim", ['class_id' => $g2->id])->assertOk()->assertJsonPath('message', 'Mia claimed into "Orchid".');
        $this->assertSame($g2->id, $mia->fresh()->class_id);
        $this->assertSame('Grade 2', $mia->fresh()->grade_level);
        $this->api($otherToken)->postJson("/api/teacher/promotions/{$record->id}/claim", ['class_id' => $g2->id])->assertStatus(403);

        // Grade 3 has no next grade.
        $third = $this->child($this->schoolClass($t, 'Grade 2', 'Third'), 'Tia', 5, 'Grade 3');
        $this->api($token)->postJson("/api/teacher/promotions/{$third->id}/release")->assertStatus(403);
        $this->assertNotNull($g3);
    }

    public function test_a_pending_teacher_can_see_promotions_but_not_release_or_claim(): void
    {
        [, $t, $token] = $this->teacher('Pending');
        $class = $this->schoolClass($t, 'Grade 1');
        $kid = $this->child($class);

        $this->api($token)->getJson('/api/teacher/promotions')->assertOk();
        $this->api($token)->postJson("/api/teacher/promotions/{$kid->id}/release")->assertStatus(403)->assertJsonPath('message', 'Locked until your school verification is approved.');
        $this->assertSame($class->id, $kid->fresh()->class_id);
    }

    // ------------------------------------------------------------------ profile (Teacher and Parent)

    public function test_a_teacher_changes_their_name_password_and_grades_but_not_their_school(): void
    {
        [$user, $t, $token] = $this->teacher();
        $class = $this->schoolClass($t, 'Grade 2', 'Dahlia');

        $this->api($token)->putJson('/api/auth/profile', ['first_name' => 'Elena', 'middle_initial' => 'M', 'last_name' => "D'Souza-Reyes", 'contact_number' => '0917 555 1234', 'school_name' => 'Hacked'])
            ->assertOk()->assertJsonPath('message', 'Profile updated.')->assertJsonPath('account.firstName', 'Elena')->assertJsonPath('account.contactNumber', '0917 555 1234');
        $this->assertSame('Rizal ES', $t->fresh()->school_name, 'the school the Admin checked cannot be changed here');
        $this->api($token)->putJson('/api/auth/profile', ['first_name' => 'Juan123', 'last_name' => 'Cruz'])->assertStatus(422)
            ->assertJsonPath('errors.first_name.0', 'First name may only contain letters, spaces, hyphens, and apostrophes, no numbers.');

        $this->api($token)->putJson('/api/auth/profile/password', ['current_password' => 'wrong', 'password' => 'NewPassw0rd!', 'password_confirmation' => 'NewPassw0rd!'])
            ->assertStatus(422)->assertJsonPath('errors.current_password.0', 'That current password is incorrect.');
        $this->api($token)->putJson('/api/auth/profile/password', ['current_password' => 'Passw0rd!', 'password' => 'short', 'password_confirmation' => 'short'])->assertStatus(422)->assertJsonValidationErrors('password');
        $this->api($token)->putJson('/api/auth/profile/password', ['current_password' => 'Passw0rd!', 'password' => 'NewPassw0rd!', 'password_confirmation' => 'NewPassw0rd!'])->assertOk()->assertJsonPath('message', 'Password changed.');
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('NewPassw0rd!', $user->fresh()->password));

        // Narrowing the grades leaves an existing class alone but says it cannot take new learners.
        $r = $this->api($token)->putJson('/api/auth/profile/grades', ['grades_mode' => 'single', 'grades_handled' => ['Grade 1']])->assertOk();
        $this->assertSame(['Grade 1'], $r->json('account.teacher.grades'));
        $this->assertStringContainsString('Dahlia (Grade 2)', $r->json('message'));
        $this->assertSame('Grade 2', $class->fresh()->grade_level);
        $this->api($token)->putJson('/api/auth/profile/grades', ['grades_mode' => 'multi', 'grades_handled' => ['Grade 1']])->assertStatus(422)->assertJsonValidationErrors('grades_handled');
    }

    public function test_a_parent_changes_their_own_details_and_cannot_change_grades(): void
    {
        $user = User::create(['first_name' => 'Pam', 'last_name' => 'Dee', 'email' => 'pam.prof@example.com', 'password' => 'Passw0rd!', 'user_type' => 'Parent']);
        $user->forceFill(['email_verified_at' => now()])->save();
        ParentAccount::create(['user_id' => $user->id]);
        $token = $user->createToken('m')->plainTextToken;

        $this->api($token)->putJson('/api/auth/profile', ['first_name' => 'Pamela', 'last_name' => 'Dee'])->assertOk()->assertJsonPath('account.firstName', 'Pamela')->assertJsonPath('account.role', 'parent');
        $this->api($token)->putJson('/api/auth/profile/grades', ['grades_mode' => 'single', 'grades_handled' => ['Grade 1']])->assertStatus(403);
        $this->api($token)->putJson('/api/auth/profile', ['first_name' => '', 'last_name' => ''])->assertStatus(422)->assertJsonValidationErrors(['first_name', 'last_name']);
        $this->getJson('/api/auth/profile')->assertStatus(405);
    }
}
