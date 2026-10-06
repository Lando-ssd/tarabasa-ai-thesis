<?php

namespace Tests\Feature;

use App\Http\Controllers\LearnerController;
use App\Jobs\GenerateActivitiesJob;
use App\Models\Activity;
use App\Models\ActivityGeneration;
use App\Models\Learner;
use App\Models\LearnerBadge;
use App\Models\OpenRepositoryListing;
use App\Models\ParentAccount;
use App\Models\PromotionRecord;
use App\Models\ReadingSession;
use App\Models\RepositoryUnlock;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ActivityAiClient;
use App\Services\BadgeService;
use App\Support\LearnerCode;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * "What if two things happen at the same moment?" A test cannot truly run two requests in parallel,
 * so these check the two things that make the answer safe: the second of two identical requests is
 * refused cleanly, and the database itself refuses a duplicate even if the check was skipped.
 */
class ConcurrencySafetyTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function teacher(int $credits = 2): array
    {
        $n = ++$this->seq;
        $u = User::create(['first_name' => 'T', 'last_name' => "N{$n}", 'email' => "c{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        $u->forceFill(['email_verified_at' => now()])->save();

        return [$u, Teacher::create(['user_id' => $u->id, 'school_name' => 'S', 'employee_id' => "E{$n}", 'status' => 'Active', 'free_generation_credits_remaining' => $credits])];
    }

    private function activity(Teacher $t, array $o = []): Activity
    {
        return Activity::create($o + [
            'created_by_teacher_id' => $t->id, 'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'competency_label' => 'Foundational Reading',
            'activity_type' => 'word_reading', 'difficulty_tier' => 'Easy', 'title' => 'Words', 'instructions' => 'Read.', 'passage_text' => 'cat dog', 'status' => 'Approved',
        ]);
    }

    public function test_sharing_twice_lists_it_once_and_pays_the_credits_once(): void
    {
        [$u, $t] = $this->teacher(2);
        $a = $this->activity($t);
        $this->actingAs($u);

        $this->post(route('teacher.activities.share', $a), ['price_type' => 'Free'])->assertRedirect();
        $this->post(route('teacher.activities.share', $a), ['price_type' => 'Free'])->assertForbidden();

        $this->assertSame(1, OpenRepositoryListing::where('activity_id', $a->id)->count());
        $this->assertSame(4, $t->fresh()->free_generation_credits_remaining, '2 to start with and 2 earned, not 6');

        // And the database itself refuses a second listing even if a check were skipped.
        $this->expectException(UniqueConstraintViolationException::class);
        OpenRepositoryListing::create(['activity_id' => $a->id, 'teacher_id' => $t->id, 'price_type' => 'Free', 'price' => 0]);
    }

    public function test_only_one_teacher_wins_a_claim(): void
    {
        [$uA, $tA] = $this->teacher();
        [$uB, $tB] = $this->teacher();
        $cA = SchoolClass::create(['teacher_id' => $tA->id, 'name' => 'A2', 'grade_level' => 'Grade 2', 'section' => 'A', 'school_year' => SchoolClass::currentSchoolYear()]);
        $cB = SchoolClass::create(['teacher_id' => $tB->id, 'name' => 'B2', 'grade_level' => 'Grade 2', 'section' => 'A', 'school_year' => SchoolClass::currentSchoolYear()]);
        $kid = Learner::create(['learner_code' => LearnerCode::generate(), 'first_name' => 'K', 'last_name' => 'L', 'grade_level' => 'Grade 1', 'pin' => '1234', 'avatar_id' => 'A']);
        $record = PromotionRecord::create(['learner_id' => $kid->id, 'released_by_teacher_id' => $tA->id, 'next_grade' => 'Grade 2', 'status' => 'Pending']);

        $this->actingAs($uA)->post(route('teacher.promotions.claim', $record), ['class_id' => $cA->id])->assertRedirect();
        $this->actingAs($uB)->post(route('teacher.promotions.claim', $record), ['class_id' => $cB->id])->assertForbidden();

        $kid->refresh();
        $this->assertSame($cA->id, $kid->class_id, 'the second teacher must not take the child from the first');
        $this->assertSame('Grade 2', $kid->grade_level);
        $this->assertSame($tA->id, $record->fresh()->claimed_by_teacher_id);
    }

    public function test_a_generation_never_takes_a_credit_that_is_not_there_and_saves_nothing_then(): void
    {
        [, $t] = $this->teacher(1);
        $gen = ActivityGeneration::create(['teacher_id' => $t->id, 'status' => ActivityGeneration::QUEUED, 'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'activity_type' => 'word_reading', 'levels' => ['Easy' => 1, 'Medium' => 0, 'Hard' => 0]]);

        $variant = ['variant_label' => 'A', 'title' => 'T', 'instructions' => 'i', 'display_text' => 'cat dog', 'reference_text' => 'cat dog', 'word_count' => 2, 'reading_features' => ['f']];
        $bundle = ['generation_id' => 'g', 'bundle_title' => 'B', 'competency_label' => 'Foundational Reading', 'levels' => ['easy' => [$variant], 'medium' => [$variant], 'hard' => [$variant]]];

        // The credit is spent by something else while the AI is still working (the check at the top is out of date).
        $this->app->instance(ActivityAiClient::class, new class($bundle, $t) extends ActivityAiClient {
            public function __construct(private array $bundle, private Teacher $teacher)
            {
            }

            public function generateBundle(array $payload, int $timeout = 150, int $attempts = 1): array
            {
                Teacher::whereKey($this->teacher->id)->update(['free_generation_credits_remaining' => 0]);

                return $this->bundle;
            }
        });

        (new GenerateActivitiesJob($gen->id))->handle(app(ActivityAiClient::class));

        $this->assertSame(0, $t->fresh()->free_generation_credits_remaining, 'never below zero');
        $this->assertSame(0, Activity::where('created_by_teacher_id', $t->id)->count(), 'nothing is saved without a credit');
        $this->assertSame(ActivityGeneration::FAILED, $gen->fresh()->status);
        $this->assertStringContainsString('Nothing was charged', $gen->fresh()->message);
    }

    public function test_two_generation_requests_in_a_row_make_only_one(): void
    {
        [$u, $t] = $this->teacher(2);
        config(['queue.default' => 'database']); // queued, not run: the request stays "being written"
        $this->actingAs($u);
        $payload = ['grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'activity_type' => 'word_reading', 'levels' => ['Easy' => 1, 'Medium' => 0, 'Hard' => 0]];

        $this->post(route('teacher.activities.generate'), $payload);
        $this->post(route('teacher.activities.generate'), $payload)->assertSessionHasErrors('generate');

        $this->assertSame(1, ActivityGeneration::where('teacher_id', $t->id)->count());
    }

    public function test_a_badge_asked_for_twice_is_recorded_once_and_never_crashes(): void
    {
        [, $t] = $this->teacher();
        $kid = Learner::create(['learner_code' => LearnerCode::generate(), 'first_name' => 'K', 'last_name' => 'L', 'grade_level' => 'Grade 1', 'pin' => '1234', 'avatar_id' => 'A']);
        $act = $this->activity($t);
        ReadingSession::create(['learner_id' => $kid->id, 'activity_id' => $act->id, 'session_type' => 'Practice', 'initiated_by' => 'Teacher', 'accuracy_percent' => 90]);

        $service = app(BadgeService::class);
        $first = $service->sync($kid->fresh());
        $second = $service->sync($kid->fresh());

        $this->assertGreaterThan(0, LearnerBadge::where('learner_id', $kid->id)->count());
        $this->assertSame([], $second, 'a badge is celebrated once');
        $this->assertSame(LearnerBadge::where('learner_id', $kid->id)->count(), LearnerBadge::where('learner_id', $kid->id)->distinct('badge_code')->count('badge_code'));

        // The raw insert path: a duplicate is ignored, not an error.
        $row = LearnerBadge::where('learner_id', $kid->id)->first();
        $this->assertSame(0, LearnerBadge::insertOrIgnore([['learner_id' => $kid->id, 'badge_code' => $row->badge_code, 'earned_at' => now()]]));
    }

    public function test_unlocking_twice_is_a_clear_message_and_one_unlock(): void
    {
        [, $t] = $this->teacher();
        $u = User::create(['first_name' => 'P', 'last_name' => 'P', 'email' => 'pp@example.com', 'password' => 'Passw0rd!', 'user_type' => 'Parent']);
        $parent = ParentAccount::create(['user_id' => $u->id]);
        $kid = Learner::create(['learner_code' => LearnerCode::generate(), 'first_name' => 'K', 'last_name' => 'L', 'grade_level' => 'Grade 1', 'pin' => '1234', 'avatar_id' => 'A']);
        $parent->learners()->attach($kid->id, ['relationship' => 'Mother', 'is_creator' => true]);
        $listing = OpenRepositoryListing::create(['activity_id' => $this->activity($t)->id, 'teacher_id' => $t->id, 'price_type' => 'Free', 'price' => 0]);
        $this->actingAs($u);

        $this->post(route('parent.repository.unlock', $listing), ['learner_id' => $kid->id])->assertRedirect();
        $this->post(route('parent.repository.unlock', $listing), ['learner_id' => $kid->id])->assertSessionHasErrors('unlock');
        $this->assertSame(1, RepositoryUnlock::where('listing_id', $listing->id)->count());

        $this->expectException(UniqueConstraintViolationException::class);
        RepositoryUnlock::create(['listing_id' => $listing->id, 'parent_id' => $parent->id, 'learner_id' => $kid->id, 'amount_paid' => 0]);
    }

    public function test_a_learner_code_that_clashes_is_drawn_again(): void
    {
        $tries = 0;
        $controller = new LearnerController();
        $method = new \ReflectionMethod($controller, 'withFreshCode');

        $kid = $method->invoke($controller, function () use (&$tries) {
            $tries++;
            if ($tries < 3) {
                throw new UniqueConstraintViolationException('sqlite', 'insert into learners', [], new \Exception('UNIQUE constraint failed: learners.learner_code'));
            }

            return Learner::create(['learner_code' => LearnerCode::generate(), 'first_name' => 'K', 'last_name' => 'L', 'grade_level' => 'Grade 1', 'pin' => '1234', 'avatar_id' => 'A']);
        });

        $this->assertSame(3, $tries);
        $this->assertNotNull($kid->id);

        // A different clash (not the code) is not hidden.
        $this->expectException(UniqueConstraintViolationException::class);
        $method->invoke($controller, fn () => throw new UniqueConstraintViolationException('sqlite', 'insert', [], new \Exception('UNIQUE constraint failed: parent_learners.parent_id')));
    }

    public function test_the_clean_up_migration_merges_listings_and_keeps_what_parents_unlocked(): void
    {
        [, $t] = $this->teacher();
        $a = $this->activity($t);

        Schema::table('open_repository_listings', fn ($table) => $table->dropUnique(['activity_id']));
        $keep = OpenRepositoryListing::create(['activity_id' => $a->id, 'teacher_id' => $t->id, 'price_type' => 'Free', 'price' => 0]);
        $dup = OpenRepositoryListing::create(['activity_id' => $a->id, 'teacher_id' => $t->id, 'price_type' => 'Free', 'price' => 0]);

        $u = User::create(['first_name' => 'P', 'last_name' => 'P', 'email' => 'mm@example.com', 'password' => 'Passw0rd!', 'user_type' => 'Parent']);
        $parent = ParentAccount::create(['user_id' => $u->id]);
        $kid = Learner::create(['learner_code' => LearnerCode::generate(), 'first_name' => 'K', 'last_name' => 'L', 'grade_level' => 'Grade 1', 'pin' => '1234', 'avatar_id' => 'A']);
        // The parent unlocked it through the second (duplicate) listing.
        RepositoryUnlock::create(['listing_id' => $dup->id, 'parent_id' => $parent->id, 'learner_id' => $kid->id, 'amount_paid' => 0]);

        (require base_path('database/migrations/2026_10_06_000300_one_listing_per_activity.php'))->up();

        $this->assertSame([$keep->id], OpenRepositoryListing::where('activity_id', $a->id)->pluck('id')->all());
        $this->assertSame($keep->id, RepositoryUnlock::where('learner_id', $kid->id)->value('listing_id'), 'the unlock moved to the kept listing');
    }
}
