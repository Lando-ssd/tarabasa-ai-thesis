<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityAssignment;
use App\Models\Learner;
use App\Models\Notification;
use App\Models\PersonalWordBank;
use App\Models\ReadingSession;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\TeacherAlertAction;
use App\Models\User;
use App\Services\TeacherAlerts;
use App\Services\WordBank;
use App\Support\ErrorPatterns;
use App\Support\LearnerCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The revised Teacher Analytics (an overview of the whole class, a downloadable report) and Alerts
 * (who needs support, who is ready to move up, who has gone quiet, each with evidence and a suggested
 * step from the teacher's own approved activities), plus the mistake-pattern analysis and the word
 * bank they rest on.
 */
class TeacherAnalyticsAlertsTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    /**
     * The numbers on these screens are "this week" and "the last 14 days", and the tests seed readings a
     * day or two back. Run on a Monday, "yesterday" is already last week and a test failed. Pin the clock
     * to the middle of a week (Thursday noon) so no result depends on the day the tests happen to run.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->startOfWeek()->addDays(3)->setTime(12, 0));
    }

    private function teacher(string $status = 'Active'): array
    {
        $n = ++$this->seq;
        $user = User::create(['first_name' => 'Ana', 'last_name' => "Reyes{$n}", 'email' => "t{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        $user->forceFill(['email_verified_at' => now()])->save();

        return [$user, Teacher::create(['user_id' => $user->id, 'school_name' => 'Rizal ES', 'employee_id' => "E{$n}", 'status' => $status])];
    }

    private function klass(Teacher $t, array $o = []): SchoolClass
    {
        return SchoolClass::create($o + ['teacher_id' => $t->id, 'name' => 'Rizal', 'grade_level' => 'Grade 1', 'section' => 'A', 'school_year' => SchoolClass::currentSchoolYear()]);
    }

    private function kid(SchoolClass $c, array $o = []): Learner
    {
        $n = ++$this->seq;

        return Learner::create($o + [
            'learner_code' => LearnerCode::generate(), 'class_id' => $c->id, 'first_name' => "Kid{$n}", 'last_name' => 'Cruz',
            'grade_level' => $c->grade_level, 'pin' => '1234', 'avatar_id' => 'A', 'mastery_level' => 'Developing', 'reading_rung' => 3,
        ]);
    }

    private function activity(Teacher $t, string $tier = 'Easy', array $o = []): Activity
    {
        return Activity::create($o + [
            'created_by_teacher_id' => $t->id, 'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'competency_label' => 'Foundational Reading',
            'activity_type' => 'word_reading', 'difficulty_tier' => $tier, 'ai_difficulty_tier' => $tier, 'title' => "{$tier} Farm Words",
            'instructions' => 'Read.', 'passage_text' => 'cat dog pig hen cow', 'reference_text' => 'cat dog pig hen cow', 'word_count' => 5, 'status' => 'Approved',
        ]);
    }

    /** @param  list<array<string, mixed>>|null  $feedback */
    private function read(Learner $kid, Activity $a, float $accuracy, int $daysAgo = 0, ?array $feedback = null, string $source = 'Teacher'): ReadingSession
    {
        $row = ReadingSession::create([
            'learner_id' => $kid->id, 'activity_id' => $a->id, 'session_type' => 'Practice', 'initiated_by' => $source,
            'accuracy_percent' => $accuracy, 'word_feedback' => $feedback,
        ]);
        $row->timestamp = now()->subDays($daysAgo)->subMinutes(5);
        $row->save();

        return $row;
    }

    private function missed(string ...$words): array
    {
        return array_map(fn ($w) => ['reference' => $w, 'spoken' => null, 'status' => 'deletion'], $words);
    }

    // ------------------------------------------------------------- the overview

    public function test_the_overview_shows_the_class_numbers_levels_missed_words_and_what_each_assignment_produced(): void
    {
        [$user, $teacher] = $this->teacher();
        $class = $this->klass($teacher, ['name' => 'Kamunggay']);
        $a = $this->kid($class, ['mastery_level' => 'Beginning', 'reading_rung' => 2]);
        $b = $this->kid($class, ['mastery_level' => 'Proficient', 'reading_rung' => 6]);
        $c = $this->kid($class, ['mastery_level' => null, 'reading_rung' => null]);
        $act = $this->activity($teacher, 'Easy', ['title' => 'Easy Animal Words']);
        $given = ActivityAssignment::create(['activity_id' => $act->id, 'class_id' => $class->id, 'assigned_by_teacher_id' => $teacher->id]);
        // Given a month ago: only readings since an assignment count as what it produced.
        $given->assigned_at = now()->subDays(30);
        $given->save();

        $this->read($a, $act, 70, 1, $this->missed('hen', 'cow'));
        $this->read($b, $act, 94, 1, $this->missed('hen'));
        $this->read($b, $act, 80, 1, null, 'Parent');
        // Last week: lower, so this week is up.
        $this->read($a, $act, 40, 8);

        $this->actingAs($user);
        $page = $this->get(route('teacher.analytics.index', ['period' => 'week']))->assertOk();

        $page->assertSee('Learners')->assertSee('across 1 class')
            ->assertSee('Readings this week')->assertSee('2 assigned · 1 started by parents')
            ->assertSee('Reading levels')->assertSee('1 Frustration')->assertSee('1 Independent')
            ->assertSee('1 learner has not finished the first reading check')
            ->assertSee('Words missed most this week')->assertSee('hen')
            ->assertSee('Assigned activities')->assertSee('Easy Animal Words')->assertSee('Kamunggay')->assertSee('2 of 3');

        // The average (70, 94, 80 = 81) is above last week's (40).
        $page->assertSee('81%')->assertSee('up 41 points on last week');
    }

    public function test_the_overview_never_includes_another_teachers_classes_or_the_first_check(): void
    {
        [$user, $teacher] = $this->teacher();
        [, $other] = $this->teacher();
        $mine = $this->kid($this->klass($teacher, ['name' => 'Mine']));
        $theirClass = $this->klass($other, ['name' => 'Theirs']);
        $theirs = $this->kid($theirClass, ['first_name' => 'Secret']);
        $act = $this->activity($other);
        $this->read($theirs, $act, 33, 0, $this->missed('zebra'));
        // The first reading check is not practice.
        $check = ReadingSession::create(['learner_id' => $mine->id, 'activity_id' => $act->id, 'session_type' => 'Diagnostic', 'initiated_by' => 'Parent', 'accuracy_percent' => 10]);

        $this->actingAs($user);
        $this->get(route('teacher.analytics.index', ['class' => $theirClass->id]))->assertOk()
            ->assertDontSee('zebra')->assertDontSee('Theirs')->assertDontSee('Secret')->assertSee('Mine');

        $page = $this->get(route('teacher.analytics.index'))->assertOk();
        $page->assertSee('No readings')->assertDontSee('10%');
    }

    public function test_the_class_report_is_a_csv_with_only_what_a_report_needs_and_cannot_run_as_a_formula(): void
    {
        [$user, $teacher] = $this->teacher();
        [, $other] = $this->teacher();
        $class = $this->klass($teacher, ['name' => 'Kamunggay']);
        $kid = $this->kid($class, ['first_name' => '=HYPERLINK("http://evil")', 'last_name' => 'Cruz']);
        $this->kid($this->klass($other), ['first_name' => 'OtherTeachersKid']);
        $act = $this->activity($teacher);
        $this->read($kid, $act, 60, 0, $this->missed('+cmd', 'hen'));

        $this->actingAs($user);
        $res = $this->get(route('teacher.analytics.report', ['period' => 'week']))->assertOk();
        $res->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('class-report-', $res->headers->get('content-disposition'));

        $csv = $res->streamedContent();
        $this->assertStringContainsString('Class,Grade,Learner,"Reading level (Phil-IRI)",Readings,"Average score %","Last reading","Words missed most","Needs support"', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv, 'a name that starts with = is neutralised');
        $this->assertStringContainsString("'+cmd", $csv);
        $this->assertStringNotContainsString($kid->learner_code, $csv, 'the report carries no learner code');
        $this->assertStringNotContainsString('OtherTeachersKid', $csv);
    }

    public function test_the_report_and_overview_need_a_teacher(): void
    {
        $this->get(route('teacher.analytics.report'))->assertRedirect();
        $parent = User::create(['first_name' => 'P', 'last_name' => 'Q', 'email' => 'p@example.com', 'password' => 'Passw0rd!', 'user_type' => 'Parent']);
        $this->actingAs($parent)->get(route('teacher.analytics.report'))->assertForbidden();
    }

    // ------------------------------------------------------------- the alerts

    public function test_three_low_readings_in_a_row_make_a_needs_support_alert_with_the_evidence_and_a_suggested_step(): void
    {
        [$user, $teacher] = $this->teacher();
        $class = $this->klass($teacher, ['name' => 'Kamunggay']);
        $jun = $this->kid($class, ['first_name' => 'Jun', 'last_name' => 'Dela Cruz', 'mastery_level' => 'Beginning', 'reading_rung' => 2]);
        $easy = $this->activity($teacher, 'Easy', ['title' => 'Sounds at the Farm', 'activity_type' => 'phonics_reading']);
        $used = $this->activity($teacher, 'Medium', ['title' => 'The one they read']);

        $this->read($jun, $used, 52, 4, $this->missed('hen', 'cow', 'said'));
        $this->read($jun, $used, 61, 2, $this->missed('hen', 'cow', 'said'));
        $this->read($jun, $used, 48, 0, $this->missed('hen', 'cow', 'said', 'they'));

        $this->actingAs($user);
        $page = $this->get(route('teacher.notifications.index'))->assertOk();

        $page->assertSee('Jun Dela Cruz needs support')
            ->assertSee('3 readings under 70% in 4 days')
            ->assertSee("Jun missed 'hen', 'cow' and 'said' in every reading.")
            ->assertSee('Accuracy has been 52%, 61% and 48%.')
            ->assertSee('Frustration level')
            ->assertSee('Try the')->assertSee('Sounds at the Farm')
            ->assertSee('Assign easier activity')->assertSee('Open progress')->assertSee('Mark handled');
        // Only the Teacher's own, approved, matching-level activity is offered.
        $page->assertDontSee('The one they read</b>', false);
    }

    public function test_a_flagged_latest_reading_is_an_alert_at_once_and_a_recovered_or_old_one_is_not(): void
    {
        [$user, $teacher] = $this->teacher();
        $class = $this->klass($teacher);
        $a = $this->activity($teacher);
        $alerts = app(TeacherAlerts::class);

        // A single reading the app flagged (under 70 percent) used to be invisible on the Alerts page until two more
        // bad ones arrived, while the Home banner already said "needs attention".
        $one = $this->kid($class);
        $this->read($one, $a, 25, 0);

        $two = $this->kid($class);
        $this->read($two, $a, 40, 1);
        $this->read($two, $a, 40, 0);

        $recovered = $this->kid($class);
        $this->read($recovered, $a, 40, 2);
        $this->read($recovered, $a, 40, 1);
        $this->read($recovered, $a, 85, 0);

        $stale = $this->kid($class);
        foreach ([40, 41, 42] as $i => $v) {
            $this->read($stale, $a, $v, 30 + $i);
        }

        $support = $alerts->forTeacher($teacher, false)->where('kind', 'support');
        $this->assertEqualsCanonicalizing([$one->id, $two->id], $support->pluck('learner.id')->all(), 'a flagged latest reading alerts; a recovered or an old one does not');
        $this->assertSame('Latest reading 25%, under 70%', $support->firstWhere('learner.id', $one->id)['evidence']);
        $this->assertStringContainsString('scored 25% on "Easy Farm Words"', $support->firstWhere('learner.id', $one->id)['why']);
        // The stale child has not read for a month, so the quiet alert is the one that applies.
        $this->assertTrue($alerts->forTeacher($teacher, false)->where('kind', 'quiet')->pluck('learner.id')->contains($stale->id));
    }

    public function test_the_home_banner_and_the_alerts_page_agree_about_who_needs_attention(): void
    {
        [$user, $teacher] = $this->teacher();
        $class = $this->klass($teacher);
        $kid = $this->kid($class, ['first_name' => 'Sarah']);
        $a = $this->activity($teacher, 'Easy', ['title' => 'Letter M Sound']);
        $this->read($kid, $a, 25, 0);
        // The routine "needs attention" notices (two of them, from two flagged readings) used to be what the banner
        // counted: "2 learners need attention" for one learner, linked to a page that showed nothing.
        Notification::create(['recipient_user_id' => $user->id, 'learner_id' => $kid->id, 'type' => Notification::TYPE_NEEDS_ATTENTION, 'message' => 'Sarah needs attention (1).', 'timestamp' => now()]);
        Notification::create(['recipient_user_id' => $user->id, 'learner_id' => $kid->id, 'type' => Notification::TYPE_NEEDS_ATTENTION, 'message' => 'Sarah needs attention (2).', 'timestamp' => now()]);
        $this->actingAs($user);

        $this->get(route('teacher.dashboard'))->assertOk()->assertSee('1 learner needs attention')->assertDontSee('2 learners need attention')->assertSee('Sarah');
        $this->get(route('teacher.notifications.index', ['filter' => 'support']))->assertOk()->assertSee('Sarah')->assertSee('needs support')->assertSee('Latest reading 25%');
        $this->get(route('teacher.analytics.index'))->assertOk()->assertSee('Need support');
        $this->assertSame(1, app(TeacherAlerts::class)->openCount($teacher));
    }

    public function test_three_readings_at_90_or_above_flag_good_news_with_a_harder_activity(): void
    {
        [$user, $teacher] = $this->teacher();
        $class = $this->klass($teacher, ['name' => 'Sampaguita']);
        $ana = $this->kid($class, ['first_name' => 'Ana', 'last_name' => 'Reyes', 'mastery_level' => 'Developing', 'reading_rung' => 3]);
        $used = $this->activity($teacher, 'Medium', ['title' => 'Medium done']);
        $hard = $this->activity($teacher, 'Hard', ['title' => 'Rain on the Roof']);
        foreach ([[93, 3], [95, 2], [94, 0]] as [$v, $d]) {
            $this->read($ana, $used, $v, $d);
        }

        $this->actingAs($user);
        $this->get(route('teacher.notifications.index'))->assertOk()
            ->assertSee('Ana Reyes is ready to move up')->assertSee('3 readings at 90% or above')
            ->assertSee("Ana's last three readings scored 93%, 95% and 94%")
            ->assertSee('Rain on the Roof')->assertSee('Assign next level');
    }

    public function test_a_learner_who_has_gone_quiet_is_listed_without_a_suggestion(): void
    {
        [$user, $teacher] = $this->teacher();
        $class = $this->klass($teacher);
        $luis = $this->kid($class, ['first_name' => 'Luis', 'last_name' => 'Santos']);
        $a = $this->activity($teacher);
        $this->read($luis, $a, 80, 9);

        $this->actingAs($user);
        $this->get(route('teacher.notifications.index', ['filter' => 'quiet']))->assertOk()
            ->assertSee('Luis Santos has not read in 9 days')->assertSee('The last reading was on')->assertDontSee('Assign next level');
    }

    public function test_marking_handled_hides_an_alert_until_the_learner_reads_again(): void
    {
        [$user, $teacher] = $this->teacher();
        $class = $this->klass($teacher);
        $jun = $this->kid($class, ['first_name' => 'Jun']);
        $a = $this->activity($teacher);
        foreach ([2, 1, 0] as $d) {
            $this->read($jun, $a, 40, $d);
        }
        $this->actingAs($user);

        $this->get(route('teacher.notifications.index'))->assertSee('needs support');
        $this->post(route('teacher.alerts.handled', $jun), ['kind' => 'support'])->assertRedirect();
        $this->get(route('teacher.notifications.index'))->assertDontSee('Jun Cruz needs support');
        $this->assertSame(0, app(TeacherAlerts::class)->openCount($teacher));

        // Another low reading (after the click) changes the evidence, so it comes back.
        $new = $this->read($jun, $a, 35, 0);
        $new->timestamp = now()->addMinute();
        $new->save();
        \Illuminate\Support\Facades\Cache::forget(TeacherAlerts::cacheKey($teacher));
        $this->get(route('teacher.notifications.index'))->assertSee('Jun Cruz needs support');
    }

    public function test_assigning_from_an_alert_gives_that_learner_the_suggested_activity_and_handles_it(): void
    {
        [$user, $teacher] = $this->teacher();
        $class = $this->klass($teacher);
        $jun = $this->kid($class, ['first_name' => 'Jun']);
        $used = $this->activity($teacher, 'Medium');
        $easy = $this->activity($teacher, 'Easy', ['title' => 'Sounds at the Farm']);
        foreach ([2, 1, 0] as $d) {
            $this->read($jun, $used, 40, $d);
        }
        $this->actingAs($user);

        $this->post(route('teacher.alerts.assign', $jun), ['kind' => 'support', 'activity_id' => $easy->id])->assertRedirect();

        $this->assertTrue(ActivityAssignment::where(['activity_id' => $easy->id, 'learner_id' => $jun->id, 'assigned_by_teacher_id' => $teacher->id])->exists());
        $this->assertTrue(TeacherAlertAction::where(['teacher_id' => $teacher->id, 'learner_id' => $jun->id, 'kind' => 'support'])->exists());
        // The child can now see it in their own reading list.
        $this->assertTrue($easy->isAssignedToLearner($jun->fresh()));
        // Asking twice does not give it twice.
        $this->post(route('teacher.alerts.assign', $jun), ['kind' => 'support', 'activity_id' => $easy->id])->assertRedirect();
        $this->assertSame(1, ActivityAssignment::where(['activity_id' => $easy->id, 'learner_id' => $jun->id])->count());
    }

    public function test_alert_actions_are_locked_to_the_teachers_own_learners_and_activities(): void
    {
        [$user, $teacher] = $this->teacher();
        [$otherUser, $other] = $this->teacher();
        $mine = $this->kid($this->klass($teacher));
        $theirs = $this->kid($this->klass($other));
        $myActivity = $this->activity($teacher);
        $theirActivity = $this->activity($other);
        $draft = $this->activity($teacher, 'Easy', ['status' => 'Draft']);
        $this->actingAs($user);

        // Another teacher's learner.
        $this->post(route('teacher.alerts.assign', $theirs), ['kind' => 'support', 'activity_id' => $myActivity->id])->assertForbidden();
        $this->post(route('teacher.alerts.handled', $theirs), ['kind' => 'support'])->assertForbidden();
        // Another teacher's activity, and an activity that is not approved.
        $this->post(route('teacher.alerts.assign', $mine), ['kind' => 'support', 'activity_id' => $theirActivity->id])->assertForbidden();
        $this->post(route('teacher.alerts.assign', $mine), ['kind' => 'support', 'activity_id' => $draft->id])->assertForbidden();
        // Junk.
        $this->post(route('teacher.alerts.assign', $mine), ['kind' => 'nonsense', 'activity_id' => $myActivity->id])->assertSessionHasErrors('kind');
        $this->post(route('teacher.alerts.handled', $mine), ['kind' => ['x']])->assertSessionHasErrors('kind');
        $this->post(route('teacher.alerts.assign', $mine), ['kind' => 'support'])->assertSessionHasErrors('activity_id');

        $this->assertSame(0, ActivityAssignment::count());
    }

    public function test_a_pending_teacher_can_read_alerts_but_not_assign(): void
    {
        [$user, $teacher] = $this->teacher('Pending');
        $kid = $this->kid($this->klass($teacher));
        $act = $this->activity($teacher);
        $this->actingAs($user);

        $this->get(route('teacher.notifications.index'))->assertOk();
        $this->get(route('teacher.analytics.index'))->assertOk();
        // Locked on the server, not just hidden: it bounces back with the explanation and gives nothing.
        $this->post(route('teacher.alerts.assign', $kid), ['kind' => 'support', 'activity_id' => $act->id])->assertSessionHas('classError');
        $this->assertSame(0, ActivityAssignment::count());
    }

    // ------------------------------------------------------------- the mistake patterns

    public function test_each_kind_of_mistake_is_told_apart(): void
    {
        $cases = [
            [['reference' => 'the', 'status' => 'deletion'], 'small_words'],
            [['reference' => 'elephant', 'status' => 'deletion'], 'skipped'],
            [['reference' => 'hat', 'spoken' => 'hit', 'status' => 'substitution'], 'vowels'],
            [['reference' => 'cat', 'spoken' => 'hat', 'status' => 'substitution'], 'beginning'],
            [['reference' => 'pig', 'spoken' => 'pit', 'status' => 'substitution'], 'ending_sound'],
            [['reference' => 'frog', 'spoken' => 'fog', 'status' => 'substitution'], 'blends'],
            [['reference' => 'chicks', 'spoken' => 'chick', 'status' => 'substitution'], 'endings'],
            [['reference' => 'jumped', 'spoken' => 'jumping', 'status' => 'substitution'], 'endings'],
            [['reference' => 'was', 'spoken' => 'saw', 'status' => 'substitution'], 'look_alike'],
            [['reference' => 'bad', 'spoken' => 'dad', 'status' => 'substitution'], 'look_alike'],
            [['reference' => 'cow', 'spoken' => 'hill', 'status' => 'substitution'], 'other'],
            // Not mistakes the child made.
            [['reference' => 'dog', 'spoken' => 'dog', 'status' => 'correct'], null],
            [['reference' => 'hen', 'spoken' => 'hand', 'status' => 'substitution', 'unsure' => true], null],
            [['reference' => null, 'spoken' => 'the', 'status' => 'insertion'], null],
        ];

        foreach ($cases as [$entry, $expected]) {
            $this->assertSame($expected, ErrorPatterns::classify($entry), json_encode($entry));
        }
    }

    public function test_a_pattern_needs_enough_readings_and_misses_and_says_when_it_is_early(): void
    {
        [, $teacher] = $this->teacher();
        $kid = $this->kid($this->klass($teacher));
        $a = $this->activity($teacher);
        $sub = fn (string $r, string $s) => ['reference' => $r, 'spoken' => $s, 'status' => 'substitution'];

        $one = collect([$this->read($kid, $a, 50, 0, [$sub('hat', 'hit'), $sub('pen', 'pin'), $sub('cut', 'cat')])]);
        $this->assertFalse(ErrorPatterns::profile($one)['enough'], 'one reading is not a pattern');

        $three = collect([
            $this->read($kid, $a, 50, 0, [$sub('hat', 'hit'), $sub('pen', 'pin'), $sub('cut', 'cat')]),
            $this->read($kid, $a, 50, 1, [$sub('big', 'bag'), $sub('hot', 'hat')]),
            $this->read($kid, $a, 50, 2, [$sub('sun', 'sin'), $sub('top', 'tip')]),
        ]);
        $p = ErrorPatterns::profile($three);
        $this->assertTrue($p['enough']);
        $this->assertTrue($p['early'], 'three readings is still early');
        $this->assertSame('vowels', $p['top'][0]['key']);
        $this->assertSame(100, $p['top'][0]['share']);
        $this->assertContains('hat as hit', $p['top'][0]['examples']);
    }

    public function test_the_suggested_activity_is_the_one_that_practises_the_pattern(): void
    {
        [$user, $teacher] = $this->teacher();
        $class = $this->klass($teacher);
        $kid = $this->kid($class, ['first_name' => 'Jun', 'mastery_level' => 'Beginning', 'reading_rung' => 1]);
        $used = $this->activity($teacher, 'Medium', ['title' => 'Already read']);
        $plain = $this->activity($teacher, 'Easy', ['title' => 'Plain words', 'passage_text' => 'cat dog sun bus', 'reference_text' => 'cat dog sun bus']);
        $blends = $this->activity($teacher, 'Easy', ['title' => 'Blend words', 'passage_text' => 'frog star snap clap', 'reference_text' => 'frog star snap clap']);
        $sub = fn (string $r, string $s) => ['reference' => $r, 'spoken' => $s, 'status' => 'substitution'];
        foreach ([2, 1, 0] as $d) {
            $this->read($kid, $used, 40, $d, [$sub('frog', 'fog'), $sub('stop', 'sop'), $sub('clap', 'cap')]);
        }

        $this->actingAs($user);
        $this->get(route('teacher.notifications.index'))->assertOk()
            ->assertSee('Blend words')->assertDontSee('<b>Plain words</b>', false)
            ->assertSee('Mostly Letter blends')->assertSee('Slowly blend the two sounds first');
    }

    // ------------------------------------------------------------- the word bank

    public function test_the_word_bank_is_one_row_per_word_and_a_word_is_mastered_by_reading_it_right_three_times(): void
    {
        [, $teacher] = $this->teacher();
        $kid = $this->kid($this->klass($teacher));
        $a = $this->activity($teacher);
        $bank = app(WordBank::class);
        $miss = fn () => [['reference' => 'hen', 'spoken' => 'hand', 'status' => 'substitution'], ['reference' => 'Cow', 'status' => 'deletion']];
        $right = fn () => [['reference' => 'hen', 'spoken' => 'hen', 'status' => 'correct'], ['reference' => 'cow', 'spoken' => 'cow', 'status' => 'correct']];

        // Missed in three readings: still one row per word, and "Cow" and "cow" are the same word.
        foreach ([1, 2, 3] as $i) {
            $bank->record($kid, $this->read($kid, $a, 50, 0), $miss());
        }
        $this->assertSame(2, PersonalWordBank::where('learner_id', $kid->id)->count());
        $this->assertSame(['cow', 'hen'], PersonalWordBank::where('learner_id', $kid->id)->orderBy('word')->pluck('word')->all());

        $bank->record($kid, $this->read($kid, $a, 100, 0), $right());
        $this->assertSame('Improving', PersonalWordBank::where(['learner_id' => $kid->id, 'word' => 'hen'])->value('mastery_status'));
        $bank->record($kid, $this->read($kid, $a, 100, 0), $right());
        $bank->record($kid, $this->read($kid, $a, 100, 0), $right());
        $this->assertSame('Mastered', PersonalWordBank::where(['learner_id' => $kid->id, 'word' => 'hen'])->value('mastery_status'));

        // Missing it again sends it back to practise.
        $bank->record($kid, $this->read($kid, $a, 50, 0), $miss());
        $this->assertSame('Struggling', PersonalWordBank::where(['learner_id' => $kid->id, 'word' => 'hen'])->value('mastery_status'));
        $this->assertSame(0, PersonalWordBank::where(['learner_id' => $kid->id, 'word' => 'hen'])->value('times_drilled'));

        // A word read right that was never missed is not added, and a not-sure word is never added.
        $bank->record($kid, $this->read($kid, $a, 100, 0), [['reference' => 'sun', 'spoken' => 'sun', 'status' => 'correct'], ['reference' => 'pig', 'spoken' => 'peg', 'status' => 'substitution', 'unsure' => true]]);
        $this->assertSame(2, PersonalWordBank::where('learner_id', $kid->id)->count());
    }

    public function test_practice_words_put_struggling_before_improving_and_skip_mastered(): void
    {
        [, $teacher] = $this->teacher();
        $kid = $this->kid($this->klass($teacher));
        foreach (['mastered' => 'Mastered', 'improving' => 'Improving', 'struggling' => 'Struggling'] as $w => $status) {
            PersonalWordBank::create(['learner_id' => $kid->id, 'word' => $w, 'mastery_status' => $status]);
        }

        $this->assertSame(['struggling', 'improving'], app(WordBank::class)->practiceWords($kid)->all());
    }
}
