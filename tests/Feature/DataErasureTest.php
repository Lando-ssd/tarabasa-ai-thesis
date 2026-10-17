<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\AdminAction;
use App\Models\DataErasure;
use App\Models\Learner;
use App\Models\Notification;
use App\Models\OpenRepositoryListing;
use App\Models\ParentAccount;
use App\Models\PersonalWordBank;
use App\Models\PromotionRecord;
use App\Models\ReadingSession;
use App\Models\RepositoryRating;
use App\Models\RepositoryUnlock;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use App\Services\DataErasureService;
use App\Support\LearnerCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Deleting a child's profile or a whole account on request. DataErasureService holds every rule; these check each one, on the
 * website, the phone API and the Admin screen, and that what is deleted is really gone while what must stay (the children of someone
 * else, the activities a teacher wrote) stays.
 */
class DataErasureTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Storage::fake('public');
    }

    /** @return array{0: User, 1: ParentAccount} */
    private function parent(string $first = 'Carla'): array
    {
        $n = ++$this->seq;
        $user = User::create(['first_name' => $first, 'last_name' => "Domingo{$n}", 'email' => "erase{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Parent']);
        $user->forceFill(['email_verified_at' => now()])->save();

        return [$user, ParentAccount::create(['user_id' => $user->id])];
    }

    /** @return array{0: User, 1: Teacher} */
    private function teacher(): array
    {
        $n = ++$this->seq;
        $user = User::create(['first_name' => 'Elena', 'last_name' => "Reyes{$n}", 'email' => "teach{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        $user->forceFill(['email_verified_at' => now()])->save();

        return [$user, Teacher::create(['user_id' => $user->id, 'school_name' => 'Rizal ES', 'employee_id' => "E{$n}", 'status' => 'Active', 'free_generation_credits_remaining' => 2, 'grades_handled' => ['Grade 1']])];
    }

    private function admin(): User
    {
        $a = User::create(['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'ada'.++$this->seq.'@example.com', 'password' => 'Passw0rd!', 'user_type' => 'Admin', 'status' => 'Active']);
        $a->forceFill(['email_verified_at' => now()])->save();

        return $a;
    }

    private function child(ParentAccount $parent, string $name = 'Mia', ?SchoolClass $class = null): Learner
    {
        $kid = Learner::create([
            'learner_code' => LearnerCode::generate(), 'class_id' => $class?->id, 'first_name' => $name, 'last_name' => 'Cruz', 'grade_level' => 'Grade 1',
            'pin' => Hash::make('2580'), 'avatar_id' => 'A', 'mastery_level' => 'Beginning', 'reading_rung' => 1,
        ]);
        $parent->learners()->attach($kid->id, ['relationship' => 'Mother', 'is_creator' => true, 'linked_at' => now()]);

        return $kid;
    }

    private function activity(?Teacher $t, array $o = []): Activity
    {
        return Activity::create($o + [
            'created_by_teacher_id' => $t?->id, 'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'competency_label' => 'Foundational Reading',
            'activity_type' => 'word_reading', 'difficulty_tier' => 'Easy', 'title' => 'Words', 'instructions' => 'Read.', 'passage_text' => 'cat dog', 'status' => 'Approved',
        ]);
    }

    /** Rows about a child in every table that can hold any, so a test can say "nothing is left". */
    private function fillChild(Learner $kid, User $guardian): void
    {
        $activity = $this->activity(null);
        $session = ReadingSession::create(['learner_id' => $kid->id, 'activity_id' => $activity->id, 'session_type' => 'Practice', 'initiated_by' => 'Parent', 'accuracy_percent' => 80]);
        PersonalWordBank::create(['learner_id' => $kid->id, 'word' => 'hen', 'mastery_status' => 'Struggling', 'session_id' => $session->id]);
        DB::table('learner_badges')->insert(['learner_id' => $kid->id, 'badge_code' => 'first_reading', 'earned_at' => now()]);
        DB::table('game_plays')->insert(['learner_id' => $kid->id, 'game' => 'balloon-pop', 'level_reached' => 1, 'top_level_cleared' => false, 'had_perfect_round' => false, 'rounds' => 1, 'played_at' => now()]);
        Notification::create(['recipient_user_id' => $guardian->id, 'learner_id' => $kid->id, 'type' => 'Session Summary', 'message' => 'Mia read.']);
        DB::table('learner_pin_changes')->insert(['learner_id' => $kid->id, 'user_id' => $guardian->id, 'method' => 'generated', 'changed_at' => now()]);
        $owner = Teacher::create(['user_id' => User::create(['first_name' => 'Tess', 'last_name' => 'Owner', 'email' => 'owner'.++$this->seq.'@example.com', 'password' => 'Passw0rd!', 'user_type' => 'Teacher'])->id, 'school_name' => 'S', 'employee_id' => 'O'.$this->seq, 'status' => 'Active']);
        $listing = OpenRepositoryListing::create(['activity_id' => $this->activity($owner)->id, 'teacher_id' => $owner->id, 'price_type' => 'Free', 'price' => 0]);
        RepositoryRating::create(['listing_id' => $listing->id, 'parent_id' => ParentAccount::where('user_id', $guardian->id)->value('id'), 'learner_id' => $kid->id, 'rating' => 5, 'comment' => 'Mia loved it']);
        $kid->createToken('mobile');
    }

    private function everyRowAbout(Learner $kid): int
    {
        $total = 0;

        foreach (['notifications', 'personal_word_bank', 'learner_badges', 'game_plays', 'repository_unlocks', 'repository_ratings', 'teacher_alert_actions', 'learner_pin_changes', 'promotion_records', 'activity_assignments', 'reading_sessions', 'parent_learners'] as $table) {
            $total += DB::table($table)->where('learner_id', $kid->id)->count();
        }

        return $total + DB::table('learners')->where('id', $kid->id)->count()
            + DB::table('personal_access_tokens')->where('tokenable_type', Learner::class)->where('tokenable_id', $kid->id)->count();
    }

    private function removeChild(User $user, Learner $kid, array $over = [])
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user)->post(route('parent.children.remove', $kid), $over + ['password' => 'Passw0rd!', 'confirm' => '1']);
    }

    // ------------------------------------------------------------------ removing a child

    public function test_the_only_guardian_deletes_a_child_and_everything_about_the_child_goes(): void
    {
        [$user, $parent] = $this->parent();
        $kid = $this->child($parent);
        $this->fillChild($kid, $user);
        Storage::disk('public')->put('avatars/mia.png', 'x');
        $kid->update(['avatar_photo_path' => 'avatars/mia.png']);
        $this->assertGreaterThan(8, $this->everyRowAbout($kid), 'the test child must have rows in every table to start with');

        $this->removeChild($user, $kid)->assertRedirect(route('parent.children.index'))->assertSessionHas('status', "Mia's profile and every reading record were deleted.");

        $this->assertSame(0, $this->everyRowAbout($kid), 'nothing about the child may be left, in any table');
        Storage::disk('public')->assertMissing('avatars/mia.png');
        $this->assertNotNull(User::find($user->id), 'the parent keeps their own account');
    }

    public function test_a_deletion_leaves_a_record_that_holds_nothing_about_the_person(): void
    {
        [$user, $parent] = $this->parent();
        $kid = $this->child($parent);

        $this->removeChild($user, $kid);

        $row = DataErasure::first();
        $this->assertSame(['child', 'Learner', 'self', 0], [$row->kind, $row->role, $row->requested_by, $row->children_erased]);
        $this->assertSame(['id', 'kind', 'role', 'requested_by', 'children_erased', 'created_at'], array_keys($row->getAttributes()));
    }

    public function test_a_wrong_password_or_no_tick_deletes_nothing_and_five_wrong_passwords_lock_it(): void
    {
        [$user, $parent] = $this->parent();
        $kid = $this->child($parent);

        $this->removeChild($user, $kid, ['password' => 'nope'])->assertSessionHasErrors('password');
        $this->removeChild($user, $kid, ['confirm' => ''])->assertSessionHasErrors('confirm');
        $this->assertNotNull(Learner::find($kid->id));

        for ($i = 0; $i < 4; $i++) {
            $this->removeChild($user, $kid, ['password' => 'nope']);
        }
        $locked = $this->removeChild($user, $kid, ['password' => 'Passw0rd!']);
        $locked->assertSessionHasErrors('password');
        $this->assertStringContainsString('Too many tries', session('errors')->first('password'));
        $this->assertNotNull(Learner::find($kid->id), 'even the right password does nothing while it is locked');
    }

    public function test_someone_who_is_not_a_guardian_of_the_child_gets_not_found_and_nothing_is_deleted(): void
    {
        [, $parent] = $this->parent();
        [$stranger] = $this->parent('Rosa');
        $kid = $this->child($parent);

        $this->removeChild($stranger, $kid)->assertNotFound();
        $this->assertNotNull(Learner::find($kid->id));

        [$teacher] = $this->teacher();
        $this->app['auth']->forgetGuards();
        $this->actingAs($teacher)->post(route('parent.children.remove', $kid), ['password' => 'Passw0rd!', 'confirm' => '1'])->assertForbidden();
        $this->assertNotNull(Learner::find($kid->id));
    }

    public function test_with_another_guardian_linked_removing_only_unlinks_and_the_child_stays(): void
    {
        [$user, $parent] = $this->parent();
        [$other, $otherParent] = $this->parent('Rosa');
        $kid = $this->child($parent);
        $otherParent->learners()->attach($kid->id, ['relationship' => 'Father', 'is_creator' => false, 'linked_at' => now()]);
        $this->fillChild($kid, $user);
        Notification::create(['recipient_user_id' => $other->id, 'learner_id' => $kid->id, 'type' => 'Session Summary', 'message' => 'For Rosa.']);

        $teacher = $this->teacher();
        $listing = OpenRepositoryListing::create(['activity_id' => $this->activity($teacher[1])->id, 'teacher_id' => $teacher[1]->id, 'price_type' => 'Free', 'price' => 0]);
        RepositoryUnlock::create(['listing_id' => $listing->id, 'parent_id' => $parent->id, 'learner_id' => $kid->id, 'amount_paid' => 0]);

        $this->removeChild($user, $kid)->assertSessionHas('status', "You are no longer a guardian of Mia. Mia's profile stays for the other guardian.");

        $this->assertNotNull(Learner::find($kid->id), 'the other guardian still has the child');
        $this->assertSame([$otherParent->id], $kid->parents()->pluck('parents.id')->all());
        $this->assertSame(1, ReadingSession::where('learner_id', $kid->id)->count(), 'the reading history stays');
        $this->assertSame(0, Notification::where('recipient_user_id', $user->id)->where('learner_id', $kid->id)->count(), 'the guardian who left keeps no alerts about the child');
        $this->assertSame(1, Notification::where('recipient_user_id', $other->id)->count(), 'the other guardian keeps theirs');
        $this->assertSame($otherParent->id, RepositoryUnlock::first()->parent_id, 'an unlock paid for the child moves to the guardian who stays');
        $this->assertSame('unlinked', DataErasure::first()->kind);
    }

    public function test_the_phone_api_removes_a_child_the_same_way(): void
    {
        [$user, $parent] = $this->parent();
        $kid = $this->child($parent);
        $token = $user->createToken('mobile')->plainTextToken;

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson("/api/parent/children/{$kid->id}/remove", ['password' => 'wrong'])->assertStatus(422)->assertJsonValidationErrors('password');
        $this->assertNotNull(Learner::find($kid->id));

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson("/api/parent/children/{$kid->id}/remove", ['password' => 'Passw0rd!'])
            ->assertOk()->assertJson(['erased' => true]);
        $this->assertNull(Learner::find($kid->id));

        [$other] = $this->parent('Rosa');
        $mine = $this->child(ParentAccount::where('user_id', $user->id)->first(), 'Ben');
        $this->app['auth']->forgetGuards();
        $this->withToken($other->createToken('mobile')->plainTextToken)->postJson("/api/parent/children/{$mine->id}/remove", ['password' => 'Passw0rd!'])->assertNotFound();
        $this->assertNotNull(Learner::find($mine->id));
    }

    // ------------------------------------------------------------------ deleting a Parent account

    public function test_what_deleting_a_parent_account_would_do_is_told_first_in_plain_words(): void
    {
        [$user, $parent] = $this->parent();
        [, $otherParent] = $this->parent('Rosa');
        $alone = $this->child($parent, 'Mia');
        $shared = $this->child($parent, 'Ben');
        $otherParent->learners()->attach($shared->id, ['relationship' => 'Father', 'is_creator' => false, 'linked_at' => now()]);

        $impact = app(DataErasureService::class)->accountImpact($user);

        $this->assertSame(['Mia'], $impact['erasedChildren']);
        $this->assertSame(['Ben'], $impact['keptChildren']);
        $this->assertNull($impact['blocked']);
        $this->assertStringContainsString('Mia will be deleted for good', $impact['lines'][0]);
        $this->assertStringContainsString('Ben stays for the other guardian', $impact['lines'][1]);
        $this->assertNotNull(Learner::find($alone->id), 'asking what would happen deletes nothing');
    }

    public function test_a_parent_deletes_their_account_the_sole_guardian_children_go_and_the_shared_one_stays(): void
    {
        [$user, $parent] = $this->parent();
        [$other, $otherParent] = $this->parent('Rosa');
        $alone = $this->child($parent, 'Mia');
        $shared = $this->child($parent, 'Ben');
        $otherParent->learners()->attach($shared->id, ['relationship' => 'Father', 'is_creator' => false, 'linked_at' => now()]);
        $this->fillChild($alone, $user);
        $user->createToken('mobile');
        DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => 'x', 'created_at' => now()]);

        $teacher = $this->teacher();
        $listing = OpenRepositoryListing::create(['activity_id' => $this->activity($teacher[1])->id, 'teacher_id' => $teacher[1]->id, 'price_type' => 'Free', 'price' => 0]);
        RepositoryUnlock::create(['listing_id' => $listing->id, 'parent_id' => $parent->id, 'learner_id' => $shared->id, 'amount_paid' => 0]);
        RepositoryRating::create(['listing_id' => $listing->id, 'parent_id' => $parent->id, 'learner_id' => $shared->id, 'rating' => 4]);

        $this->app['auth']->forgetGuards();
        $this->actingAs($user)->post(route('profile.delete'), ['password' => 'Passw0rd!', 'confirm' => '1'])->assertRedirect('/')->assertSessionHas('status', 'Your account was deleted.');

        $this->assertNull(User::find($user->id));
        $this->assertNull(ParentAccount::find($parent->id));
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_type', User::class)->where('tokenable_id', $user->id)->count());
        $this->assertSame(0, DB::table('password_reset_tokens')->where('email', $user->email)->count());
        $this->assertSame(0, $this->everyRowAbout($alone), 'the child with no other guardian is deleted with the account');
        $this->assertNotNull(Learner::find($shared->id), 'the child with another guardian stays');
        $this->assertSame([$otherParent->id], $shared->parents()->pluck('parents.id')->all());
        $this->assertSame($otherParent->id, RepositoryUnlock::first()->parent_id);
        $this->assertSame(0, RepositoryRating::count(), 'ratings the parent gave go with them');
        $this->assertNotNull(User::find($other->id));

        $row = DataErasure::first();
        $this->assertSame(['account', 'Parent', 'self', 1], [$row->kind, $row->role, $row->requested_by, $row->children_erased]);
        $this->assertGuest();
    }

    public function test_a_wrong_password_does_not_delete_the_account(): void
    {
        [$user] = $this->parent();

        $this->app['auth']->forgetGuards();
        $this->actingAs($user)->post(route('profile.delete'), ['password' => 'nope', 'confirm' => '1'])->assertSessionHasErrors('password', null, 'erase');
        $this->actingAs($user)->post(route('profile.delete'), ['password' => 'Passw0rd!'])->assertSessionHasErrors('confirm', null, 'erase');

        $this->assertNotNull(User::find($user->id));
        $this->assertSame(0, DataErasure::count());
    }

    public function test_an_admin_account_can_never_be_deleted_this_way(): void
    {
        $admin = $this->admin();

        $this->app['auth']->forgetGuards();
        $this->actingAs($admin)->post(route('profile.delete'), ['password' => 'Passw0rd!', 'confirm' => '1'])->assertForbidden();
        $this->assertNotNull(User::find($admin->id));

        $this->app['auth']->forgetGuards();
        $this->withToken($admin->createToken('x')->plainTextToken)->postJson('/api/auth/profile/delete', ['password' => 'Passw0rd!'])->assertForbidden();
        $this->assertNotNull(User::find($admin->id));
    }

    // ------------------------------------------------------------------ deleting a Teacher account

    private function teacherWithClassroom(): array
    {
        [$user, $teacher] = $this->teacher();
        $class = SchoolClass::create(['teacher_id' => $teacher->id, 'name' => 'Sampaguita', 'grade_level' => 'Grade 1', 'section' => 'A', 'school_year' => SchoolClass::currentSchoolYear()]);
        [$pUser, $parent] = $this->parent();
        $kid = $this->child($parent, 'Mia', $class);
        $activity = $this->activity($teacher, ['teacher_notes' => 'For Sampaguita', 'generation_basis' => ['class' => 'Sampaguita', 'lines' => ['Most of the class reads short words.']]]);
        DB::table('activity_assignments')->insert(['activity_id' => $activity->id, 'assigned_by_teacher_id' => $teacher->id, 'class_id' => $class->id, 'assigned_at' => now()]);
        $listing = OpenRepositoryListing::create(['activity_id' => $activity->id, 'teacher_id' => $teacher->id, 'price_type' => 'Free', 'price' => 0]);
        RepositoryUnlock::create(['listing_id' => $listing->id, 'parent_id' => $parent->id, 'learner_id' => $kid->id, 'amount_paid' => 0]);
        ReadingSession::create(['learner_id' => $kid->id, 'activity_id' => $activity->id, 'session_type' => 'Practice', 'initiated_by' => 'Teacher', 'accuracy_percent' => 90]);

        return [$user, $teacher, $class, $kid, $activity, $listing];
    }

    public function test_a_teacher_deletes_their_account_classes_go_learners_stay_and_activities_lose_their_name(): void
    {
        [$user, $teacher, $class, $kid, $activity, $listing] = $this->teacherWithClassroom();
        $teacher->update(['free_generation_credits_remaining' => 2]);

        $impact = app(DataErasureService::class)->accountImpact($user);
        $this->assertNull($impact['blocked']);
        $this->assertStringContainsString('Your 1 class is deleted. The 1 learner stays with their parents', $impact['lines'][0]);
        $this->assertStringContainsString('shared to the Repository', implode(' ', $impact['lines']));

        $this->app['auth']->forgetGuards();
        $this->actingAs($user)->post(route('profile.delete'), ['password' => 'Passw0rd!', 'confirm' => '1'])->assertRedirect('/');

        $this->assertNull(User::find($user->id));
        $this->assertNull(Teacher::find($teacher->id));
        $this->assertNull(SchoolClass::find($class->id));
        $this->assertNull(OpenRepositoryListing::find($listing->id));
        $this->assertSame(0, RepositoryUnlock::count());
        $this->assertSame(0, DB::table('activity_assignments')->count());

        $this->assertNotNull(Learner::find($kid->id), 'the child belongs to the parent, not the teacher');
        $this->assertNull($kid->fresh()->class_id);
        $this->assertSame(1, ReadingSession::where('learner_id', $kid->id)->count(), "the child's reading history stays");

        $kept = $activity->fresh();
        $this->assertNotNull($kept, 'the activity stays: reading history points at it');
        $this->assertNull($kept->created_by_teacher_id);
        $this->assertNull($kept->teacher_notes);
        $this->assertNull($kept->generation_basis, 'class details written into the activity are taken off it');

        $row = DataErasure::first();
        $this->assertSame(['account', 'Teacher', 'self', 0], [$row->kind, $row->role, $row->requested_by, $row->children_erased]);
    }

    public function test_a_teacher_with_learners_nobody_has_claimed_yet_cannot_delete_until_they_are_claimed(): void
    {
        [$user, $teacher, , $kid] = $this->teacherWithClassroom();
        $record = PromotionRecord::create(['learner_id' => $kid->id, 'released_by_teacher_id' => $teacher->id, 'next_grade' => 'Grade 2', 'status' => 'Pending']);

        $impact = app(DataErasureService::class)->accountImpact($user);
        $this->assertStringContainsString('released 1 learner for promotion', (string) $impact['blocked']);

        $this->app['auth']->forgetGuards();
        $this->actingAs($user)->post(route('profile.delete'), ['password' => 'Passw0rd!', 'confirm' => '1'])->assertSessionHasErrors('account', null, 'erase');
        $this->assertNotNull(User::find($user->id));

        $record->update(['status' => 'Claimed']);
        $this->app['auth']->forgetGuards();
        $this->actingAs($user)->post(route('profile.delete'), ['password' => 'Passw0rd!', 'confirm' => '1'])->assertRedirect('/');
        $this->assertNull(User::find($user->id));
    }

    public function test_the_phone_api_tells_what_would_happen_then_deletes_the_account_and_its_tokens(): void
    {
        [$user, $parent] = $this->parent();
        $kid = $this->child($parent);
        $token = $user->createToken('mobile')->plainTextToken;

        $this->app['auth']->forgetGuards();
        $info = $this->withToken($token)->getJson('/api/auth/account/erase-info')->assertOk();
        $this->assertNull($info->json('blocked'));
        $this->assertStringContainsString('Mia will be deleted for good', $info->json('lines.0'));

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/auth/profile/delete', ['password' => 'wrong'])->assertStatus(422)->assertJsonValidationErrors('password');
        $this->assertNotNull(User::find($user->id));

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/auth/profile/delete', ['password' => 'Passw0rd!'])->assertOk()->assertJson(['message' => 'Your account was deleted.']);

        $this->assertNull(User::find($user->id));
        $this->assertNull(Learner::find($kid->id));
        $this->assertSame(0, DB::table('personal_access_tokens')->count());

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_a_child_cannot_use_the_account_deletion_routes(): void
    {
        [, $parent] = $this->parent();
        $kid = $this->child($parent);

        $this->app['auth']->forgetGuards();
        $this->withToken($kid->createToken('mobile')->plainTextToken)->postJson('/api/auth/profile/delete', ['password' => '2580'])->assertForbidden();
        $this->assertNotNull(Learner::find($kid->id));
    }

    // ------------------------------------------------------------------ the Admin

    public function test_the_admin_deletes_an_account_on_request_with_a_reason_and_the_log_keeps_no_name(): void
    {
        $admin = $this->admin();
        [$user, $parent] = $this->parent('Carla');
        $kid = $this->child($parent);
        AdminAction::record($admin, 'confirmed_email', $user, 'I spoke with them');

        $this->app['auth']->forgetGuards();
        $this->actingAs($admin)->post(route('admin.users.delete', $user), [])->assertSessionHasErrors('reason');
        $this->actingAs($admin)->post(route('admin.users.delete', $user), ['reason' => 'Other'])->assertSessionHasErrors('note');
        $this->assertNotNull(User::find($user->id));

        $this->actingAs($admin)->post(route('admin.users.delete', $user), ['reason' => 'The person asked us to', 'note' => 'Asked at school'])
            ->assertSessionHas('status', 'The Parent account was deleted with 1 child.');

        $this->assertNull(User::find($user->id));
        $this->assertNull(Learner::find($kid->id));

        $log = AdminAction::where('action', 'deleted')->first();
        $this->assertSame('a Parent account', $log->target_name);
        $this->assertSame('The person asked us to', $log->detail);
        $this->assertSame('Asked at school', $log->note);
        $this->assertNull($log->target_user_id);
        $this->assertStringNotContainsString('Carla', json_encode(AdminAction::all()->toArray()), 'no earlier log line may still name the person');
        $this->assertSame('admin', DataErasure::first()->requested_by);

        $this->actingAs($admin)->get(route('admin.log'))->assertOk()->assertSee('deleted')->assertSee('a Parent account');
    }

    public function test_an_admin_account_cannot_be_deleted_and_only_an_admin_can_use_the_route(): void
    {
        $admin = $this->admin();
        [$other] = $this->parent();
        [$teacher] = $this->teacher();

        $this->app['auth']->forgetGuards();
        $this->actingAs($admin)->post(route('admin.users.delete', $admin), ['reason' => 'The person asked us to'])->assertForbidden();
        $this->actingAs($teacher)->post(route('admin.users.delete', $other), ['reason' => 'The person asked us to'])->assertForbidden();
        $this->assertNotNull(User::find($admin->id));
        $this->assertNotNull(User::find($other->id));
    }

    public function test_the_admin_cannot_delete_a_teacher_whose_released_learners_are_still_unclaimed(): void
    {
        $admin = $this->admin();
        [$user, $teacher, , $kid] = $this->teacherWithClassroom();
        PromotionRecord::create(['learner_id' => $kid->id, 'released_by_teacher_id' => $teacher->id, 'next_grade' => 'Grade 2', 'status' => 'Pending']);

        $this->app['auth']->forgetGuards();
        $this->actingAs($admin)->post(route('admin.users.delete', $user), ['reason' => 'The person asked us to'])->assertSessionHasErrors('account');
        $this->assertNotNull(User::find($user->id));
    }

    // ------------------------------------------------------------------ the screens

    public function test_the_screens_offer_the_deletion_with_the_warning(): void
    {
        [$user, $parent] = $this->parent();
        $this->child($parent);
        [$teacher] = $this->teacher();
        $admin = $this->admin();

        $this->app['auth']->forgetGuards();
        $this->actingAs($user)->get(route('parent.children.index'))->assertOk()->assertSee('Remove Mia')->assertSee('cannot be undone');
        $this->actingAs($user)->get(route('parent.profile.edit'))->assertOk()->assertSee('Delete my account')->assertSee('Mia will be deleted for good');

        $this->app['auth']->forgetGuards();
        $this->actingAs($teacher)->get(route('teacher.profile.edit'))->assertOk()->assertSee('Delete my account');

        $this->app['auth']->forgetGuards();
        $this->actingAs($admin)->get(route('admin.accounts'))->assertOk()->assertSee('Delete account');
    }

    public function test_the_privacy_page_describes_the_real_way_to_ask(): void
    {
        $this->get('/privacy')->assertOk()
            ->assertSee('Delete my account')
            ->assertSee('Remove')
            ->assertSee('id="delete"', false);
    }
}
