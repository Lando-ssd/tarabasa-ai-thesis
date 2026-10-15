<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Learner;
use App\Models\Notification;
use App\Models\OpenRepositoryListing;
use App\Models\ParentAccount;
use App\Models\ReadingSession;
use App\Models\RepositoryRating;
use App\Models\RepositoryUnlock;
use App\Models\Teacher;
use App\Models\User;
use App\Support\LearnerCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * The phone app's Parent screens (/api/parent/...): the same classes and rules as the website's Parent pages, over JSON.
 */
class ParentApiTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    /** A request as the owner of this token, from a clean slate (the test app keeps the signed in person between requests). */
    private function api(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    /** @return array{0: User, 1: ParentAccount, 2: string} */
    private function parent(): array
    {
        $n = ++$this->seq;
        $user = User::create(['first_name' => 'Carla', 'last_name' => "Domingo{$n}", 'email' => "papi{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Parent']);
        $user->forceFill(['email_verified_at' => now()])->save();
        $parent = ParentAccount::create(['user_id' => $user->id]);

        return [$user, $parent, $user->createToken('mobile')->plainTextToken];
    }

    private function child(ParentAccount $parent, string $first = 'Mia', int $rung = 2, string $grade = 'Grade 1'): Learner
    {
        $kid = Learner::create([
            'learner_code' => LearnerCode::generate(), 'first_name' => $first, 'last_name' => 'Cruz', 'grade_level' => $grade,
            'pin' => Hash::make('1234'), 'avatar_id' => '🦁', 'mastery_level' => $rung <= 2 ? 'Beginning' : ($rung <= 4 ? 'Developing' : 'Proficient'), 'reading_rung' => $rung,
        ]);
        $parent->learners()->attach($kid->id, ['relationship' => 'Mother', 'is_creator' => true, 'linked_at' => now()]);

        return $kid;
    }

    private function teacher(): Teacher
    {
        $n = ++$this->seq;
        $u = User::create(['first_name' => 'Ana', 'last_name' => "Reyes{$n}", 'email' => "papit{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);

        return Teacher::create(['user_id' => $u->id, 'school_name' => 'Rizal ES', 'employee_id' => "PA{$n}", 'status' => 'Active', 'free_generation_credits_remaining' => 2, 'grades_handled' => ['Grade 1']]);
    }

    private function activity(Teacher $t, string $title = 'Easy words', string $tier = 'Easy'): Activity
    {
        return Activity::create([
            'created_by_teacher_id' => $t->id, 'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'competency_label' => 'Foundational Reading',
            'activity_type' => 'word_reading', 'title' => $title, 'instructions' => 'Read.', 'passage_text' => 'cat dog pig hen cow bat', 'reference_text' => 'cat dog pig hen cow bat',
            'word_count' => 6, 'status' => 'Approved', 'difficulty_tier' => $tier,
        ]);
    }

    private function reading(Learner $kid, Activity $a, float $accuracy, int $daysAgo = 1, ?array $feedback = null, string $by = 'Teacher'): ReadingSession
    {
        $row = ReadingSession::create([
            'learner_id' => $kid->id, 'activity_id' => $a->id, 'session_type' => 'Practice', 'initiated_by' => $by, 'accuracy_percent' => $accuracy,
            'flagged_needs_attention' => $accuracy < 70, 'word_feedback' => $feedback,
        ]);
        $row->timestamp = now()->subDays($daysAgo);
        $row->save();

        return $row;
    }

    // ------------------------------------------------------------------ home

    public function test_a_parent_with_no_children_gets_empty_screens_not_errors(): void
    {
        [, , $token] = $this->parent();

        $this->api($token)->getJson('/api/parent/home')->assertOk()->assertJsonPath('children', [])->assertJsonPath('child', null)->assertJsonPath('selected', null)->assertJsonPath('unread', 0);
        $this->api($token)->getJson('/api/parent/progress')->assertOk()->assertJsonPath('child', null);
        $this->api($token)->getJson('/api/parent/repository')->assertOk()->assertJsonPath('listings', []);
        $this->api($token)->getJson('/api/parent/alerts')->assertOk()->assertJsonPath('alerts', []);
    }

    public function test_home_shows_the_children_one_childs_week_and_flags_the_one_who_needs_a_look(): void
    {
        [, $p, $token] = $this->parent();
        $mia = $this->child($p, 'Mia');
        $leo = $this->child($p, 'Leo');
        $t = $this->teacher();
        $act = $this->activity($t, 'Sounds at the Farm');
        $this->reading($mia, $act, 92, 3);
        $this->reading($leo, $act, 40, 1);

        $r = $this->api($token)->getJson('/api/parent/home')->assertOk();

        $this->assertSame(['Leo', 'Mia'], collect($r->json('children'))->pluck('firstName')->all(), 'in name order');
        $this->assertSame([true, false], collect($r->json('children'))->pluck('flagged')->all(), 'Leo needs a look');
        // The first child is the one shown until another is asked for.
        $r->assertJsonPath('selected', $leo->id)->assertJsonPath('child.firstName', 'Leo')->assertJsonPath('child.recent.0.title', 'Sounds at the Farm')->assertJsonPath('child.recent.0.accuracy', 40)->assertJsonPath('child.recent.0.flagged', true);
        $this->assertSame(1, $r->json('child.sources.teacher.count'));
        $this->assertSame(0, $r->json('child.sources.parent.count'));
        $this->assertArrayHasKey('weeklyTarget', $r->json('child.summary'));
        $this->assertSame('🦁', $r->json('child.avatar'));

        $second = $this->api($token)->getJson("/api/parent/home?learner_id={$mia->id}")->assertOk();
        $second->assertJsonPath('child.firstName', 'Mia')->assertJsonPath('child.recent.0.accuracy', 92)->assertJsonPath('child.recent.0.flagged', false);
    }

    public function test_a_child_who_is_not_theirs_is_never_shown_and_never_an_error(): void
    {
        [, $p, $token] = $this->parent();
        $mine = $this->child($p, 'Mia');
        [, $other] = $this->parent();
        $theirs = $this->child($other, 'Zed');

        $this->api($token)->getJson("/api/parent/home?learner_id={$theirs->id}")->assertOk()->assertJsonPath('child.firstName', 'Mia');
        $this->api($token)->getJson("/api/parent/progress?learner_id={$theirs->id}")->assertOk()->assertJsonPath('child.firstName', 'Mia');
        $this->api($token)->getJson("/api/parent/children/{$theirs->id}/card")->assertNotFound();
        $this->api($token)->getJson("/api/parent/children/{$mine->id}/card")->assertOk()->assertJsonPath('code', $mine->learner_code)->assertJsonPath('firstName', 'Mia');
    }

    public function test_the_card_carries_the_code_in_a_qr_but_never_the_pin(): void
    {
        [, $p, $token] = $this->parent();
        $mia = $this->child($p, 'Mia');

        $r = $this->api($token)->getJson("/api/parent/children/{$mia->id}/card")->assertOk();

        $this->assertStringStartsWith('<svg', ltrim((string) $r->json('qrSvg')));
        $this->assertStringNotContainsString('1234', json_encode($r->json()), 'the PIN is never sent');
    }

    // ------------------------------------------------------------------ children

    public function test_the_children_list_says_who_each_child_is(): void
    {
        [, $p, $token] = $this->parent();
        $this->child($p, 'Mia');

        $r = $this->api($token)->getJson('/api/parent/children')->assertOk();

        $r->assertJsonPath('children.0.firstName', 'Mia')->assertJsonPath('children.0.relationship', 'Mother')->assertJsonPath('children.0.creator', true)->assertJsonPath('children.0.grade', 'Grade 1');
        $this->assertArrayHasKey('dayStreak', $r->json('children.0'));
    }

    public function test_the_wizard_options_are_the_websites_own_lists(): void
    {
        [, , $token] = $this->parent();

        $r = $this->api($token)->getJson('/api/parent/children/options')->assertOk();

        $this->assertSame(['Grade 1', 'Grade 2', 'Grade 3'], $r->json('grades'));
        $this->assertCount(3, $r->json('placementQuestions.Grade 1'));
        $this->assertSame('starting', $r->json('readingStages.0.key'));
        $this->assertContains('Filipino', $r->json('homeLanguages'));
        $this->assertSame('read_aloud', $r->json('supports.0.key'));
    }

    private function newChild(array $over = []): array
    {
        return array_merge([
            'first_name' => 'Ben', 'last_name' => 'Santos', 'grade_level' => 'Grade 1', 'avatar_id' => '🐰', 'reading_stage' => 'blending',
            'q1' => 'yes', 'q2' => 'yes', 'q3' => 'no', 'pin' => '4321', 'pin_confirmation' => '4321', 'home_language' => 'Filipino', 'supports' => ['games'], 'interests' => ['animals'],
        ], $over);
    }

    public function test_adding_a_child_sets_the_level_from_the_answers_and_links_the_parent_as_the_creator(): void
    {
        [, $p, $token] = $this->parent();

        $r = $this->api($token)->postJson('/api/parent/children', $this->newChild())->assertCreated();

        $kid = Learner::findOrFail($r->json('child.id'));
        $this->assertTrue(LearnerCode::looksLikeCode($r->json('child.code')));
        $this->assertSame('Developing', $kid->mastery_level, 'two yes answers of three');
        $this->assertSame('blending', $kid->reading_stage);
        $this->assertSame(['q1' => 'yes', 'q2' => 'yes', 'q3' => 'no'], $kid->placement_answers);
        $this->assertSame(['animals'], $kid->interests);
        $this->assertTrue(Hash::check('4321', $kid->pin), 'the PIN is stored hashed');
        $this->assertNotSame('4321', $kid->pin);
        $this->assertTrue((bool) $p->learners()->whereKey($kid->id)->first()->pivot->is_creator);
        $this->assertStringNotContainsString('4321', json_encode($r->json()), 'the PIN is never sent back');
    }

    public function test_every_answer_not_sure_starts_at_the_neutral_level_and_three_yes_at_the_top(): void
    {
        [, , $token] = $this->parent();

        $none = $this->api($token)->postJson('/api/parent/children', $this->newChild(['q1' => 'unsure', 'q2' => 'unsure', 'q3' => 'unsure']))->assertCreated();
        $all = $this->api($token)->postJson('/api/parent/children', $this->newChild(['first_name' => 'Cy', 'q1' => 'yes', 'q2' => 'yes', 'q3' => 'yes']))->assertCreated();

        $this->assertSame('Developing', Learner::find($none->json('child.id'))->mastery_level);
        $this->assertSame('Proficient', Learner::find($all->json('child.id'))->mastery_level);
    }

    public function test_adding_a_child_is_refused_with_the_websites_messages(): void
    {
        [, $p, $token] = $this->parent();

        $r = $this->api($token)->postJson('/api/parent/children', $this->newChild(['first_name' => 'Ben2', 'pin' => '12', 'pin_confirmation' => '99', 'avatar_id' => 'x', 'grade_level' => 'Grade 4']))->assertStatus(422);

        $this->assertStringContainsString('no numbers', $r->json('errors.first_name.0'));
        $this->assertArrayHasKey('pin', $r->json('errors'));
        $this->assertArrayHasKey('avatar_id', $r->json('errors'));
        $this->assertArrayHasKey('grade_level', $r->json('errors'));
        $this->assertSame(0, $p->learners()->count());
    }

    public function test_a_second_guardian_links_with_the_code_and_the_pin_and_the_first_is_told(): void
    {
        [$u1, $p1] = $this->parent();
        $kid = $this->child($p1, 'Mia');
        [, $p2, $token2] = $this->parent();

        $r = $this->api($token2)->postJson('/api/parent/children/link', ['learner_code' => $kid->learner_code, 'pin' => '1234', 'relationship' => 'Father'])->assertOk();

        $r->assertJsonPath('child.firstName', 'Mia');
        $this->assertSame('Father', $p2->learners()->whereKey($kid->id)->first()->pivot->relationship);
        $this->assertFalse((bool) $p2->learners()->whereKey($kid->id)->first()->pivot->is_creator);
        $this->assertSame(1, Notification::where('recipient_user_id', $u1->id)->where('type', Notification::TYPE_GUARDIAN_LINKED)->count(), 'the first guardian is told');
    }

    public function test_linking_gives_one_answer_for_a_wrong_pin_and_an_unknown_code_and_stops_a_guesser(): void
    {
        [, $p1] = $this->parent();
        $kid = $this->child($p1, 'Mia');
        [, $p2, $token2] = $this->parent();
        RateLimiter::clear('link-child|parent:'.$p2->id);
        RateLimiter::clear('link-child|code:'.$kid->learner_code);

        $wrong = $this->api($token2)->postJson('/api/parent/children/link', ['learner_code' => $kid->learner_code, 'pin' => '9999', 'relationship' => 'Mother'])->assertStatus(422);
        $unknown = $this->api($token2)->postJson('/api/parent/children/link', ['learner_code' => 'TB26-00000', 'pin' => '1234', 'relationship' => 'Mother'])->assertStatus(422);
        $this->assertSame($wrong->json('errors.learner_code.0'), $unknown->json('errors.learner_code.0'), 'it never says which was wrong');
        $this->assertSame(0, $p2->learners()->count());

        // Five wrong tries in a row and even the right PIN is refused for a while.
        for ($i = 0; $i < 4; $i++) {
            $this->api($token2)->postJson('/api/parent/children/link', ['learner_code' => $kid->learner_code, 'pin' => '9999', 'relationship' => 'Mother']);
        }
        $locked = $this->api($token2)->postJson('/api/parent/children/link', ['learner_code' => $kid->learner_code, 'pin' => '1234', 'relationship' => 'Mother'])->assertStatus(422);
        $this->assertStringContainsString('Too many tries', $locked->json('errors.learner_code.0'));
        $this->assertSame(0, $p2->learners()->count());
    }

    public function test_linking_a_child_twice_is_refused(): void
    {
        [, $p, $token] = $this->parent();
        $kid = $this->child($p, 'Mia');

        $r = $this->api($token)->postJson('/api/parent/children/link', ['learner_code' => $kid->learner_code, 'pin' => '1234', 'relationship' => 'Mother'])->assertStatus(422);

        $this->assertStringContainsString('already linked', $r->json('errors.learner_code.0'));
    }

    // ------------------------------------------------------------------ progress

    public function test_progress_says_the_level_in_plain_words_and_shows_the_history_and_the_trend(): void
    {
        [, $p, $token] = $this->parent();
        $mia = $this->child($p, 'Mia', 3);
        $act = $this->activity($this->teacher(), 'Farm words');
        $this->reading($mia, $act, 80, 5);
        $this->reading($mia, $act, 91, 3);
        $this->reading($mia, $act, 95, 1, null, 'Parent');
        // The first reading check is not practice, so it is not in the history.
        $check = $this->reading($mia, $act, 60, 6);
        $check->update(['session_type' => 'Diagnostic']);

        $r = $this->api($token)->getJson('/api/parent/progress')->assertOk();

        $r->assertJsonPath('child.readings', 3)->assertJsonPath('child.level.band', ReadingLevelBand::for($mia));
        $this->assertStringContainsString('Mia', (string) $r->json('child.level.plain'), 'the plain sentence about the level is there, in words');
        $this->assertNotEmpty($r->json('child.level.label'));
        $this->assertSame([80, 91, 95], $r->json('child.trend'), 'oldest first, for the small chart');
        $this->assertSame([95, 91, 80], collect($r->json('child.history'))->pluck('accuracy')->all(), 'newest first');
        $this->assertSame('Parent', $r->json('child.history.0.source'));
        $this->assertSame(1, $r->json('child.sources.parent.count'));
        $this->assertNull($r->json('child.home'), 'a few readings are not a pattern');
    }

    public function test_progress_tells_a_parent_what_to_practise_once_a_kind_of_mistake_repeats(): void
    {
        [, $p, $token] = $this->parent();
        $mia = $this->child($p, 'Mia', 3);
        $act = $this->activity($this->teacher(), 'Farm words');
        // Three readings where the same word ending is missed: a clear pattern.
        $miss = [
            ['reference' => 'cats', 'spoken' => 'cat', 'status' => 'substitution'], ['reference' => 'dogs', 'spoken' => 'dog', 'status' => 'substitution'],
            ['reference' => 'hens', 'spoken' => 'hen', 'status' => 'substitution'], ['reference' => 'pig', 'spoken' => 'pig', 'status' => 'correct'],
        ];
        foreach ([5, 3, 1] as $days) {
            $this->reading($mia, $act, 70, $days, $miss);
        }

        $home = $this->api($token)->getJson('/api/parent/progress')->assertOk()->json('child.home');

        $this->assertNotNull($home);
        $this->assertNotSame('', $home['say']);
        $this->assertNotSame('', $home['tip']);
        $this->assertNotEmpty($home['words']);
    }

    // ------------------------------------------------------------------ the Open Repository

    private function listed(Teacher $t, string $title, string $tier = 'Easy', string $price = 'Free', float $amount = 0): OpenRepositoryListing
    {
        return OpenRepositoryListing::create(['activity_id' => $this->activity($t, $title, $tier)->id, 'teacher_id' => $t->id, 'price_type' => $price, 'price' => $amount]);
    }

    public function test_the_repository_puts_the_activities_that_suit_the_child_first_and_filters(): void
    {
        [, $p, $token] = $this->parent();
        $mia = $this->child($p, 'Mia', 2); // a Beginning reader: Easy activities suit
        $t = $this->teacher();
        $hard = $this->listed($t, 'Hard one', 'Hard');
        $easy = $this->listed($t, 'Easy one', 'Easy');
        $paid = $this->listed($t, 'Paid easy', 'Easy', 'Paid', 25);
        $this->reading($mia, $this->activity($t, 'seed'), 60, 2);

        $r = $this->api($token)->getJson('/api/parent/repository')->assertOk();

        $titles = collect($r->json('listings'))->pluck('title')->all();
        $this->assertSame('Hard one', end($titles), 'what does not suit comes last');
        $this->assertTrue($r->json('hasMatching'));
        $first = collect($r->json('listings'))->firstWhere('title', 'Easy one');
        $this->assertTrue($first['matchesLevel']);
        $this->assertSame('Free', $first['price']);
        $this->assertSame('₱25', collect($r->json('listings'))->firstWhere('title', 'Paid easy')['price']);

        $this->assertSame(['Paid easy'], collect($this->api($token)->getJson('/api/parent/repository?filter=paid')->json('listings'))->pluck('title')->all());
        $only = $this->api($token)->getJson('/api/parent/repository?best=1')->json('listings');
        $this->assertNotContains('Hard one', collect($only)->pluck('title')->all(), 'only the matches');
    }

    public function test_unlocking_records_one_unlock_for_one_child_and_a_second_press_is_refused(): void
    {
        [, $p, $token] = $this->parent();
        $mia = $this->child($p, 'Mia');
        $leo = $this->child($p, 'Leo');
        $listing = $this->listed($this->teacher(), 'Shared one', 'Easy', 'Paid', 25);

        $this->api($token)->postJson("/api/parent/repository/{$listing->id}/unlock", ['learner_id' => $mia->id])->assertOk()->assertJsonPath('message', 'Unlocked "Shared one" for Mia.');
        $this->assertSame(1, RepositoryUnlock::where('listing_id', $listing->id)->count());
        $this->assertEquals(25, RepositoryUnlock::first()->amount_paid, 'the simulated price is recorded');

        $dup = $this->api($token)->postJson("/api/parent/repository/{$listing->id}/unlock", ['learner_id' => $mia->id])->assertStatus(422);
        $this->assertSame('Already unlocked for Mia.', $dup->json('errors.unlock.0'));
        $this->assertSame(1, RepositoryUnlock::count());

        // It is per child: Leo has not got it, and the list says so for each.
        $this->assertTrue($this->api($token)->getJson("/api/parent/repository?learner_id={$mia->id}")->json('listings.0.unlocked'));
        $this->assertFalse($this->api($token)->getJson("/api/parent/repository?learner_id={$leo->id}")->json('listings.0.unlocked'));
    }

    public function test_a_child_who_is_not_theirs_cannot_be_unlocked_for(): void
    {
        [, , $token] = $this->parent();
        [, $other] = $this->parent();
        $theirs = $this->child($other, 'Zed');
        $listing = $this->listed($this->teacher(), 'Shared one');

        $this->api($token)->postJson("/api/parent/repository/{$listing->id}/unlock", ['learner_id' => $theirs->id])->assertStatus(403);
        $this->assertSame(0, RepositoryUnlock::count());
    }

    public function test_only_a_parent_who_unlocked_something_can_rate_it_and_rating_again_replaces_the_rating(): void
    {
        [, $p, $token] = $this->parent();
        $mia = $this->child($p, 'Mia');
        $listing = $this->listed($this->teacher(), 'Shared one');

        $this->api($token)->postJson("/api/parent/repository/{$listing->id}/rate", ['learner_id' => $mia->id, 'rating' => 5])->assertStatus(403);
        $this->assertSame(0, RepositoryRating::count());

        $this->api($token)->postJson("/api/parent/repository/{$listing->id}/unlock", ['learner_id' => $mia->id])->assertOk();
        $this->api($token)->postJson("/api/parent/repository/{$listing->id}/rate", ['learner_id' => $mia->id, 'rating' => 4, 'comment' => 'Nice'])->assertOk();
        $this->api($token)->postJson("/api/parent/repository/{$listing->id}/rate", ['learner_id' => $mia->id, 'rating' => 2])->assertOk();

        $this->assertSame(1, RepositoryRating::count(), 'one rating for each parent and listing');
        $this->assertSame(2, (int) RepositoryRating::first()->rating);
        $mine = $this->api($token)->getJson('/api/parent/repository')->json('listings.0');
        $this->assertSame(2, $mine['mine']['rating']);
        $this->assertSame(1, $mine['ratings']);
        $this->api($token)->postJson("/api/parent/repository/{$listing->id}/rate", ['learner_id' => $mia->id, 'rating' => 6])->assertStatus(422);
    }

    // ------------------------------------------------------------------ alerts

    private function note(User $u, ?Learner $kid, string $type, string $message, bool $read = false, ?\DateTimeInterface $at = null): Notification
    {
        $n = Notification::create(['recipient_user_id' => $u->id, 'learner_id' => $kid?->id, 'type' => $type, 'message' => $message, 'is_read' => $read]);
        $n->timestamp = $at ?? now()->subMinutes(5);
        $n->save();

        return $n;
    }

    public function test_alerts_come_newest_first_with_the_day_and_the_unread_count_and_filter_by_kind(): void
    {
        [$u, $p, $token] = $this->parent();
        $mia = $this->child($p, 'Mia');
        $this->note($u, $mia, Notification::TYPE_SESSION_SUMMARY, 'Mia read Farm words.', true, now()->subDays(10));
        $this->note($u, $mia, Notification::TYPE_NEEDS_ATTENTION, 'Mia needs attention.');
        $this->note($u, $mia, Notification::TYPE_SESSION_SUMMARY, 'Mia read Pond words.', false, now()->subMinutes(2));

        $r = $this->api($token)->getJson('/api/parent/alerts')->assertOk();

        $this->assertSame(2, $r->json('unread'));
        $this->assertSame(['Mia read Pond words.', 'Mia needs attention.', 'Mia read Farm words.'], collect($r->json('alerts'))->pluck('message')->all());
        $this->assertSame(['Today', 'Today', 'Earlier'], collect($r->json('alerts'))->pluck('day')->all());
        $this->assertSame('Mia', $r->json('alerts.0.child'));
        $this->assertTrue($r->json('alerts.1.attention'));

        $attention = $this->api($token)->getJson('/api/parent/alerts?filter=attention')->json('alerts');
        $this->assertCount(1, $attention);
        $this->assertCount(2, $this->api($token)->getJson('/api/parent/alerts?filter=routine')->json('alerts'));
    }

    public function test_marking_alerts_read_one_by_one_or_all_and_never_someone_elses(): void
    {
        [$u, $p, $token] = $this->parent();
        [$u2] = $this->parent();
        $mia = $this->child($p, 'Mia');
        $a = $this->note($u, $mia, Notification::TYPE_SESSION_SUMMARY, 'One');
        $b = $this->note($u, $mia, Notification::TYPE_SESSION_SUMMARY, 'Two');
        $theirs = $this->note($u2, null, Notification::TYPE_SESSION_SUMMARY, 'Not mine');

        $this->api($token)->postJson("/api/parent/alerts/{$a->id}/read")->assertOk()->assertJsonPath('unread', 1);
        $this->assertTrue($a->fresh()->is_read);
        $this->api($token)->postJson("/api/parent/alerts/{$theirs->id}/read")->assertNotFound();
        $this->assertFalse($theirs->fresh()->is_read);

        $this->api($token)->postJson('/api/parent/alerts/read-all')->assertOk()->assertJsonPath('unread', 0);
        $this->assertTrue($b->fresh()->is_read);
        $this->assertFalse($theirs->fresh()->is_read, 'someone else\'s alerts are left alone');
    }

    // ------------------------------------------------------------------ the token fence

    public function test_only_a_parent_token_opens_the_parent_screens(): void
    {
        [, , $parentToken] = $this->parent();
        $n = ++$this->seq;
        $tu = User::create(['first_name' => 'T', 'last_name' => 'T', 'email' => "papif{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        $tu->forceFill(['email_verified_at' => now()])->save();
        Teacher::create(['user_id' => $tu->id, 'school_name' => 'X', 'employee_id' => 'FX', 'status' => 'Active', 'free_generation_credits_remaining' => 2, 'grades_handled' => ['Grade 1']]);

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/parent/home')->assertUnauthorized();
        $this->api($tu->createToken('m')->plainTextToken)->getJson('/api/parent/home')->assertForbidden();
        $this->api($parentToken)->getJson('/api/parent/home')->assertOk();
    }
}

/** The Phil-IRI band the test expects for a child, so the test does not repeat the rule it is checking. */
final class ReadingLevelBand
{
    public static function for(Learner $kid): string
    {
        return \App\Support\ReadingLevel::forAdult($kid)['band'];
    }
}
