<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityAssignment;
use App\Models\Learner;
use App\Models\Notification;
use App\Models\PromotionRecord;
use App\Models\ReadingSession;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The redesigned Teacher end: every screen renders, and the logic behind the windows works
 * (placing a draft in a level, restoring, generating only the levels asked for, assigning to a
 * class, joining a learner by the last 5 characters of the code) and stays locked for a
 * teacher who is not Active yet or who does not own the record.
 */
class TeacherRedesignTest extends TestCase
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
            'instructions' => 'Read the sentences out loud.', 'passage_text' => 'Nanay goes to the market every Saturday. She buys fresh fish, ripe mangoes, and rice for our lunch.',
            'word_count' => 18, 'status' => 'Draft', 'reading_features' => ['Grade level words'],
        ]);
    }

    private function bundle(): array
    {
        $variant = fn (string $tier, string $label) => [
            'difficulty' => strtolower($tier), 'variant_label' => $label, 'title' => "{$tier} Animals {$label}", 'instructions' => 'Read the words.',
            'display_text' => 'cat dog pig hen cow bat rat', 'reference_text' => 'cat dog pig hen cow bat rat', 'word_count' => 7,
            'target_skills' => ['Reading words'], 'reading_features' => ['Familiar words'], 'follow_up_questions' => [],
        ];

        return [
            'generation_id' => 'gen-1', 'bundle_title' => 'Grade 2 Foundational Reading', 'competency_label' => 'Foundational Reading', 'total_activities' => 9,
            'levels' => [
                'easy' => [$variant('Easy', 'A'), $variant('Easy', 'B'), $variant('Easy', 'C')],
                'medium' => [$variant('Medium', 'A'), $variant('Medium', 'B'), $variant('Medium', 'C')],
                'hard' => [$variant('Hard', 'A'), $variant('Hard', 'B'), $variant('Hard', 'C')],
            ],
        ];
    }

    // ---------------------------------------------------------------- screens

    public function test_every_teacher_screen_renders_for_an_active_teacher(): void
    {
        [$user, $teacher] = $this->teacher();
        $class = $this->klass($teacher, ['group_tag' => 'phonics-focus']);
        $kid = $this->learner($class);
        $draft = $this->activity($teacher, ['title' => 'Draft One']);
        $approved = $this->activity($teacher, ['title' => 'Approved One', 'status' => 'Approved', 'difficulty_tier' => 'Hard', 'ai_difficulty_tier' => 'Medium']);
        ActivityAssignment::create(['activity_id' => $approved->id, 'class_id' => $class->id, 'assigned_by_teacher_id' => $teacher->id]);
        ReadingSession::create(['learner_id' => $kid->id, 'activity_id' => $approved->id, 'session_type' => 'Practice', 'initiated_by' => 'Teacher', 'accuracy_percent' => 82, 'wcpm' => 60, 'level_before' => 'Beginning', 'level_after' => 'Developing', 'timestamp' => now()]);
        Notification::create(['recipient_user_id' => $user->id, 'learner_id' => $kid->id, 'type' => Notification::TYPE_SESSION_SUMMARY, 'message' => 'Kid read it.', 'timestamp' => now()]);
        $this->actingAs($user);

        $this->get(route('teacher.dashboard'))->assertOk()->assertSee('Welcome back')->assertSee('Rizal')->assertSee('1 draft to review');
        $this->get(route('teacher.classes.index'))->assertOk()->assertSee('1 activity assigned')->assertSee('Add learner')->assertSee('Assign an activity')->assertSee($kid->learner_code);
        $this->get(route('teacher.activities.index'))->assertOk()->assertSee('To review')->assertSee('Draft One')->assertSee('Moved from Medium')->assertSee('Generate activities');
        $this->get(route('teacher.activities.index', ['view' => 'list', 'status' => 'Approved']))->assertOk()->assertSee('Approved One');
        $this->get(route('teacher.activities.index', ['fragment' => 1]))->assertOk()->assertSee('class="board"', false)->assertDontSee('<html', false);
        $this->get(route('teacher.activities.create'))->assertRedirect(route('teacher.activities.index', ['generate' => 1]));
        $this->get(route('teacher.activities.window', $draft))->assertOk()->assertSee('Why Medium')->assertSee('Approve in level');
        $this->get(route('teacher.activities.window', $approved))->assertOk()->assertSee('Assigned to')->assertSee('The AI suggested');
        $this->get(route('teacher.analytics.index'))->assertOk()->assertSee('Days in a row')->assertSee('Recent sessions');
        $this->get(route('teacher.analytics.index', ['mode' => 'group']))->assertOk()->assertSee('phonics-focus')->assertSee('Who needs help first');
        $this->get(route('teacher.promotions.index'))->assertOk()->assertSee('To Grade 3');
        $this->get(route('teacher.promotions.index', ['tab' => 'claim']))->assertOk();
        $this->get(route('teacher.notifications.index'))->assertOk()->assertSee('Session summary');
        $this->get(route('teacher.profile.edit'))->assertOk()->assertSee('Change password');
    }

    public function test_a_pending_teacher_can_look_but_every_action_is_locked(): void
    {
        [$user, $teacher] = $this->teacher('Pending');
        $draft = $this->activity($teacher);
        $this->actingAs($user);

        foreach (['teacher.dashboard', 'teacher.classes.index', 'teacher.activities.index', 'teacher.analytics.index', 'teacher.promotions.index', 'teacher.notifications.index', 'teacher.profile.edit'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('teacher.classes.index'))->assertSee('Class Management is locked');

        $this->postJson(route('teacher.activities.place', $draft), ['level' => 'Hard'])->assertStatus(403);
        $this->post(route('teacher.activities.approve', $draft))->assertSessionHas('classError');
        $this->assertSame('Draft', $draft->fresh()->status);
    }

    // ------------------------------------------------------------ placing cards

    public function test_dropping_a_draft_in_a_level_approves_it_there_and_remembers_the_ai_suggestion(): void
    {
        [$user, $teacher] = $this->teacher();
        $draft = $this->activity($teacher, ['difficulty_tier' => 'Hard', 'ai_difficulty_tier' => 'Hard']);
        $this->actingAs($user);

        $this->postJson(route('teacher.activities.place', $draft), ['level' => 'Medium'])
            ->assertOk()->assertJsonPath('message', 'Approved in Medium. The AI suggested Hard. Your choice is saved.');

        $draft->refresh();
        $this->assertSame('Approved', $draft->status);
        $this->assertSame('Medium', $draft->difficulty_tier);
        $this->assertSame('Hard', $draft->ai_difficulty_tier);
        $this->assertTrue($draft->movedByTeacher());

        // An approved activity can be re-leveled, and the AI's suggestion is never overwritten.
        $this->postJson(route('teacher.activities.place', $draft), ['level' => 'Easy'])->assertOk()->assertJsonPath('message', 'Moved to Easy.');
        $this->assertSame(['Easy', 'Hard'], [$draft->fresh()->difficulty_tier, $draft->fresh()->ai_difficulty_tier]);
    }

    public function test_reject_can_be_undone_and_only_a_draft_can_be_rejected(): void
    {
        [$user, $teacher] = $this->teacher();
        $draft = $this->activity($teacher);
        $this->actingAs($user);

        $res = $this->postJson(route('teacher.activities.place', $draft), ['level' => 'reject'])->assertOk();
        $this->assertSame('Rejected', $draft->fresh()->status);
        $this->postJson($res->json('undo.url'))->assertOk();
        $this->assertSame('Draft', $draft->fresh()->status);

        $draft->update(['status' => 'Approved']);
        $this->postJson(route('teacher.activities.place', $draft), ['level' => 'reject'])->assertStatus(403);
        $this->postJson(route('teacher.activities.restore', $draft))->assertStatus(403);
        $this->postJson(route('teacher.activities.place', $draft), ['level' => 'Nope'])->assertStatus(422);

        $draft->update(['status' => 'Rejected']);
        $this->postJson(route('teacher.activities.place', $draft), ['level' => 'Easy'])->assertStatus(403);
    }

    public function test_approve_uses_the_chosen_level_and_edit_recomputes_the_word_count(): void
    {
        [$user, $teacher] = $this->teacher();
        $a = $this->activity($teacher);
        $b = $this->activity($teacher, ['title' => 'Second']);
        $this->actingAs($user);

        $this->post(route('teacher.activities.approve', $a), ['level' => 'Easy'])->assertRedirect();
        $this->assertSame(['Approved', 'Easy'], [$a->fresh()->status, $a->fresh()->difficulty_tier]);

        $this->put(route('teacher.activities.update', $b), ['title' => 'Second edited', 'instructions' => 'Read it.', 'passage_text' => "The cat sat.\nThe dog ran fast.", 'level' => 'Hard'])->assertRedirect();
        $b->refresh();
        $this->assertSame(['Approved', 'Hard', 7, 'The cat sat. The dog ran fast.'], [$b->status, $b->difficulty_tier, $b->word_count, $b->reference_text]);
    }

    public function test_another_teachers_activity_cannot_be_touched(): void
    {
        [$user] = $this->teacher();
        [, $other] = $this->teacher();
        $theirs = $this->activity($other);
        $this->actingAs($user);

        $this->postJson(route('teacher.activities.place', $theirs), ['level' => 'Easy'])->assertStatus(403);
        $this->get(route('teacher.activities.window', $theirs))->assertStatus(403);
        $this->assertSame('Draft', $theirs->fresh()->status);
    }

    // -------------------------------------------------------------- generating

    public function test_generate_keeps_only_the_levels_asked_for_and_uses_one_credit(): void
    {
        config(['services.activity_ai.url' => 'https://gen.test', 'services.activity_ai.key' => 'k']);
        Http::fake(['gen.test/*' => Http::response($this->bundle())]);
        [$user, $teacher] = $this->teacher();
        $this->actingAs($user);

        $this->post(route('teacher.activities.generate'), [
            'grade_level' => 'Grade 2', 'competency' => 'foundational_reading', 'activity_type' => 'word_reading',
            'levels' => ['Easy' => 0, 'Medium' => 2, 'Hard' => 1], 'topic' => 'Animals',
        ])->assertRedirect(route('teacher.activities.index', ['view' => 'board']))->assertSessionHas('status', '3 drafts added to To review: 2 Medium, 1 Hard.');

        $this->assertSame(3, Activity::count());
        $this->assertSame(2, Activity::where('difficulty_tier', 'Medium')->count());
        $this->assertSame(1, Activity::where('difficulty_tier', 'Hard')->count());
        $this->assertSame(0, Activity::where('difficulty_tier', 'Easy')->count());
        $this->assertSame(['Draft'], Activity::pluck('status')->unique()->values()->all());
        $this->assertSame(Activity::pluck('difficulty_tier')->all(), Activity::pluck('ai_difficulty_tier')->all());
        $this->assertSame(1, $teacher->fresh()->free_generation_credits_remaining);

        // The service was asked for the largest number wanted, on one call.
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r['variants_per_level'] === 2 && $r['grade'] === 2 && $r['competency'] === 'foundational_reading');
    }

    public function test_generate_needs_a_level_and_a_credit_and_a_failure_costs_nothing(): void
    {
        config(['services.activity_ai.url' => 'https://gen.test', 'services.activity_ai.key' => 'k']);
        [$user, $teacher] = $this->teacher();
        $this->actingAs($user);
        $base = ['grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'activity_type' => 'word_reading'];

        Http::fake(['gen.test/*' => Http::response(['detail' => 'boom'], 500)]);
        $this->post(route('teacher.activities.generate'), $base + ['levels' => ['Easy' => 0, 'Medium' => 0, 'Hard' => 0]])->assertSessionHasErrors('levels');
        $this->post(route('teacher.activities.generate'), $base + ['levels' => ['Easy' => 9]])->assertSessionHasErrors('levels.Easy');
        $this->post(route('teacher.activities.generate'), ['activity_type' => 'passage_reading'] + $base + ['levels' => ['Easy' => 1]])->assertSessionHasErrors('activity_type');
        Http::assertNothingSent();

        $this->post(route('teacher.activities.generate'), $base + ['levels' => ['Easy' => 1]])->assertSessionHasErrors('generate');
        $this->assertSame(2, $teacher->fresh()->free_generation_credits_remaining);
        $this->assertSame(0, Activity::count());

        $teacher->update(['free_generation_credits_remaining' => 0]);
        $user->unsetRelation('teacher'); // a real request loads the teacher fresh
        $this->post(route('teacher.activities.generate'), $base + ['levels' => ['Easy' => 1]])->assertSessionHasErrors('credits');
    }

    public function test_opening_the_generate_window_wakes_the_generator_without_ever_failing(): void
    {
        config(['services.activity_ai.url' => 'https://gen.test', 'services.activity_ai.key' => 'k']);
        Http::fake(['gen.test/health' => Http::response(['status' => 'ok'])]);
        [$user] = $this->teacher();
        $this->actingAs($user);

        $this->get(route('teacher.activities.warm'))->assertNoContent();
        Http::assertSent(fn ($r) => $r->url() === 'https://gen.test/health');
        $this->get(route('teacher.activities.index'))->assertSee('data-warm-url', false);

        // An unreachable or unconfigured service must never break the page.
        Http::fake(['gen.test/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('asleep')]);
        $this->get(route('teacher.activities.warm'))->assertNoContent();
        config(['services.activity_ai.url' => null]);
        $this->get(route('teacher.activities.warm'))->assertNoContent();
    }

    public function test_what_the_teacher_moved_before_is_told_to_the_generator_in_its_notes(): void
    {
        config(['services.activity_ai.url' => 'https://gen.test', 'services.activity_ai.key' => 'k']);
        Http::fake(['gen.test/*' => Http::response($this->bundle())]);
        [$user, $teacher] = $this->teacher();
        $this->activity($teacher, ['status' => 'Approved', 'activity_type' => 'word_reading', 'difficulty_tier' => 'Medium', 'ai_difficulty_tier' => 'Hard']);
        $this->activity($teacher, ['status' => 'Approved', 'activity_type' => 'word_reading', 'difficulty_tier' => 'Medium', 'ai_difficulty_tier' => 'Hard']);
        $this->actingAs($user);

        $this->post(route('teacher.activities.generate'), [
            'grade_level' => 'Grade 2', 'competency' => 'foundational_reading', 'activity_type' => 'word_reading',
            'levels' => ['Medium' => 1], 'teacher_notes' => 'Use animals.',
        ])->assertSessionHasNoErrors();

        Http::assertSent(fn ($r) => str_starts_with($r['teacher_notes'], 'Use animals.')
            && str_contains($r['teacher_notes'], '2 texts the AI called Hard placed in Medium by the teacher')
            && strlen($r['teacher_notes']) <= 1000);
        // Only the teacher's own words are saved on the activity.
        $this->assertSame('Use animals.', Activity::latest('id')->first()->teacher_notes);
        // A different grade or type has no such note.
        $this->assertNull(Activity::calibrationNote($teacher->id, 'Grade 1', 'word_reading'));
        $this->assertNull(Activity::calibrationNote($teacher->id, 'Grade 2', 'passage_reading'));
    }

    // ------------------------------------------------------------------ classes

    public function test_an_approved_activity_can_be_assigned_to_a_class_and_shows_in_it(): void
    {
        [$user, $teacher] = $this->teacher();
        $class = $this->klass($teacher, ['group_tag' => 'needs-fluency']);
        $sameTag = $this->klass($teacher, ['name' => 'Mabini', 'group_tag' => 'needs-fluency']);
        $direct = $this->activity($teacher, ['status' => 'Approved', 'title' => 'Direct One']);
        $viaGroup = $this->activity($teacher, ['status' => 'Approved', 'title' => 'Group One']);
        $draft = $this->activity($teacher, ['title' => 'Still a draft']);
        ActivityAssignment::create(['activity_id' => $viaGroup->id, 'group_tag' => 'needs-fluency', 'assigned_by_teacher_id' => $teacher->id]);
        $this->actingAs($user);

        $this->post(route('teacher.classes.assign-activity', $class), ['activity_id' => $direct->id])
            ->assertRedirect(route('teacher.classes.index', ['school_year' => $class->school_year, 'open' => $class->id, 'tab' => 'acts']))
            ->assertSessionHas('status', '"Direct One" assigned to Rizal.');
        $this->assertTrue(ActivityAssignment::where(['activity_id' => $direct->id, 'class_id' => $class->id])->exists());

        // Twice is refused, a draft is refused, and nothing is created for either.
        $this->from('/x')->post(route('teacher.classes.assign-activity', $class), ['activity_id' => $direct->id])->assertSessionHasErrors('activity_id');
        $this->from('/x')->post(route('teacher.classes.assign-activity', $class), ['activity_id' => $draft->id])->assertSessionHasErrors('activity_id');
        $this->from('/x')->post(route('teacher.classes.assign-activity', $class), [])->assertSessionHasErrors('activity_id');
        $this->assertSame(2, ActivityAssignment::count());

        // The class page lists both what the class was given directly and what came through its group tag.
        $page = $this->get(route('teacher.classes.index', ['open' => $class->id, 'tab' => 'acts']))->assertOk();
        $page->assertSee('Given to this class')->assertSee('Through group needs-fluency')->assertSee('Direct One')->assertSee('Group One')->assertSee('data-autoview="acts"', false);
        // The class counts both; another class with the same tag gets the group activity too.
        $page->assertSee('2 activities assigned')->assertSee('1 activity assigned')->assertSee($sameTag->name);
    }

    public function test_assigning_is_limited_to_your_own_current_classes(): void
    {
        [$user, $teacher] = $this->teacher();
        [, $other] = $this->teacher();
        $mine = $this->klass($teacher);
        $theirs = $this->klass($other);
        $past = $this->klass($teacher, ['school_year' => '2019-2020']);
        $a = $this->activity($teacher, ['status' => 'Approved']);
        $this->actingAs($user);

        $this->post(route('teacher.classes.assign-activity', $theirs), ['activity_id' => $a->id])->assertStatus(403);
        $this->post(route('teacher.classes.assign-activity', $past), ['activity_id' => $a->id])->assertStatus(403);
        // Someone else's approved activity is not offered to me either.
        $foreign = $this->activity($other, ['status' => 'Approved', 'title' => 'Not mine']);
        $this->from('/x')->post(route('teacher.classes.assign-activity', $mine), ['activity_id' => $foreign->id])->assertSessionHasErrors('activity_id');
        $this->assertSame(0, ActivityAssignment::count());
    }

    public function test_a_learner_joins_by_the_last_five_characters_or_the_whole_code(): void
    {
        [$user, $teacher] = $this->teacher();
        $class = $this->klass($teacher);
        $a = $this->learner(null, ['learner_code' => 'TB-12345', 'first_name' => 'Rosa']);
        $b = $this->learner(null, ['learner_code' => 'TB-67890', 'first_name' => 'Lito']);
        $taken = $this->learner($this->klass($teacher, ['name' => 'Other']), ['learner_code' => 'TB-55555']);
        $this->actingAs($user);
        $url = route('teacher.classes.join-learner', $class);

        $this->post($url, ['learner_code' => '12345'])->assertRedirect()->assertSessionHas('status', 'Rosa Cruz added to "Rizal".');
        $this->assertSame($class->id, $a->fresh()->class_id);
        $this->post($url, ['learner_code' => ' tb-67890 '])->assertRedirect();
        $this->assertSame($class->id, $b->fresh()->class_id);

        $this->from('/x')->post($url, ['learner_code' => '123'])->assertSessionHasErrors(['learner_code' => 'The Learner Code ends in 5 characters, like TB-12345.']);
        $this->from('/x')->post($url, ['learner_code' => '00000'])->assertSessionHasErrors(['learner_code' => 'No learner found with that code. Check it and try again.']);
        $this->from('/x')->post($url, ['learner_code' => '55555'])->assertSessionHasErrors(['learner_code' => 'This learner is already enrolled in a class.']);
        $this->from('/x')->post($url, ['learner_code' => ''])->assertSessionHasErrors('learner_code');
    }

    public function test_creating_and_editing_a_class_and_the_past_year_lock(): void
    {
        [$user, $teacher] = $this->teacher();
        $this->actingAs($user);
        $year = SchoolClass::currentSchoolYear();

        $this->post(route('teacher.classes.store'), ['name' => 'Sampaguita', 'section' => 'Section A', 'grade_level' => 'Grade 1', 'school_year' => $year, 'group_tag' => 'phonics-focus'])
            ->assertRedirect(route('teacher.classes.index', ['school_year' => $year]));
        $class = SchoolClass::firstWhere('name', 'Sampaguita');
        $this->assertSame('phonics-focus', $class->group_tag);

        $this->from('/x')->post(route('teacher.classes.store'), ['name' => '', 'section' => 'x', 'grade_level' => 'Grade 1', 'school_year' => $year])->assertSessionHasErrors('name');
        $this->put(route('teacher.classes.update', $class), ['name' => 'Sampaguita 2', 'section' => 'Section A', 'grade_level' => 'Grade 1', 'group_tag' => ''])->assertRedirect();
        $this->assertSame('Sampaguita 2', $class->fresh()->name);

        $past = $this->klass($teacher, ['school_year' => '2019-2020']);
        $this->put(route('teacher.classes.update', $past), ['name' => 'Nope', 'section' => 'x', 'grade_level' => 'Grade 1'])->assertStatus(403);
        $this->get(route('teacher.classes.index', ['school_year' => '2019-2020']))->assertOk()->assertSee('A past school year is read only')->assertDontSee('data-open="newClassDlg"', false);
    }

    // ------------------------------------------------------------- promotions

    public function test_release_and_claim_still_work_from_the_new_windows(): void
    {
        [$user, $teacher] = $this->teacher();
        $g1 = $this->klass($teacher, ['name' => 'One', 'grade_level' => 'Grade 1']);
        $g2 = $this->klass($teacher, ['name' => 'Two', 'grade_level' => 'Grade 2']);
        $kid = $this->learner($g1, ['grade_level' => 'Grade 1']);
        $final = $this->learner($this->klass($teacher, ['name' => 'Three', 'grade_level' => 'Grade 3']), ['grade_level' => 'Grade 3']);
        $this->actingAs($user);

        $this->get(route('teacher.promotions.index'))->assertSee('Final year')->assertSee('data-next="Grade 2"', false);
        $this->post(route('teacher.promotions.release', $kid))->assertRedirect();
        $this->assertNull($kid->fresh()->class_id);
        $this->post(route('teacher.promotions.release', $final))->assertStatus(403);

        $record = PromotionRecord::firstWhere('learner_id', $kid->id);
        $this->assertSame('Grade 2', $record->next_grade);
        $this->get(route('teacher.promotions.index', ['tab' => 'claim']))->assertOk()->assertSee('data-classes', false)->assertSee('needs Grade 2');
        $this->post(route('teacher.promotions.claim', $record), ['class_id' => $g2->id])->assertRedirect();
        $this->assertSame([$g2->id, 'Grade 2'], [$kid->fresh()->class_id, $kid->fresh()->grade_level]);
    }

    public function test_the_menu_bar_counts_unread_alerts_and_claimable_learners(): void
    {
        [$user, $teacher] = $this->teacher();
        $g2 = $this->klass($teacher);
        $mover = $this->learner(null, ['grade_level' => 'Grade 1']);
        PromotionRecord::create(['learner_id' => $mover->id, 'released_by_teacher_id' => $teacher->id, 'next_grade' => 'Grade 2', 'status' => 'Pending', 'released_from_class_id' => $g2->id]);
        Notification::create(['recipient_user_id' => $user->id, 'learner_id' => $mover->id, 'type' => Notification::TYPE_NEEDS_ATTENTION, 'message' => 'Needs help.', 'timestamp' => now()]);
        $this->actingAs($user);

        $html = $this->get(route('teacher.dashboard'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/Promotions<\/span>\s*<span class="nav-count">1</', $html);
        $this->assertMatchesRegularExpression('/Alerts<\/span>\s*<span class="nav-count">1</', $html);
        $this->assertSame(['unread' => 1, 'claim' => 1], \App\Support\TeacherNav::counts($user));
    }

    public function test_other_roles_cannot_open_teacher_screens(): void
    {
        $parent = User::create(['first_name' => 'P', 'last_name' => 'Q', 'email' => 'p@example.com', 'password' => 'Passw0rd!', 'user_type' => 'Parent']);
        $this->actingAs($parent)->get(route('teacher.activities.index'))->assertStatus(403);
        auth()->logout();
        $this->get(route('teacher.activities.index'))->assertRedirect();
    }
}
