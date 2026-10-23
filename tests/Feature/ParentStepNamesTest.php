<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Learner;
use App\Models\Notification;
use App\Models\ParentAccount;
use App\Models\ReadingSession;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use App\Services\LearnerDiagnosticService;
use App\Services\LearnerReadingService;
use App\Support\LearnerCode;
use App\Support\ParentInsights;
use App\Support\ReadingLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * What a parent is told about how their child reads.
 *
 * A parent sees the child's OWN four steps (Letter Explorer, Word Builder, Sentence Reader, Story Reader), the same names the child
 * sees on their Home screen. A teacher sees the school's Phil-IRI words (Independent, Instructional, Frustration). The stored
 * Beginning / Developing / Proficient is the system's own level and is shown to nobody.
 */
class ParentStepNamesTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function parent(): array
    {
        $n = ++$this->seq;
        $user = User::create(['first_name' => 'Carla', 'last_name' => "Step{$n}", 'email' => "pstep{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Parent']);
        $user->forceFill(['email_verified_at' => now()])->save();
        $parent = ParentAccount::create(['user_id' => $user->id]);

        return [$user, $parent, $user->createToken('mobile')->plainTextToken];
    }

    private function teacher(): Teacher
    {
        $n = ++$this->seq;
        $u = User::create(['first_name' => 'Ana', 'last_name' => "Teach{$n}", 'email' => "pstept{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);

        return Teacher::create(['user_id' => $u->id, 'school_name' => 'Rizal ES', 'employee_id' => "PS{$n}", 'status' => 'Active', 'free_generation_credits_remaining' => 2, 'grades_handled' => ['Grade 1']]);
    }

    /** A child at a rung, linked to the parent. $checked = the first reading check has really been done. */
    private function child(ParentAccount $parent, string $first, int $rung, bool $checked = true, ?SchoolClass $class = null): Learner
    {
        $level = $rung <= 2 ? 'Beginning' : ($rung <= 4 ? 'Developing' : 'Proficient');
        $kid = Learner::create([
            'learner_code' => LearnerCode::generate(), 'class_id' => $class?->id, 'first_name' => $first, 'last_name' => 'Cruz', 'grade_level' => 'Grade 1',
            'pin' => Hash::make('1234'), 'avatar_id' => '🦁', 'mastery_level' => $level, 'reading_rung' => $rung,
        ]);
        $parent->learners()->attach($kid->id, ['relationship' => 'Mother', 'is_creator' => true, 'linked_at' => now()]);
        if ($checked) {
            ReadingSession::create(['learner_id' => $kid->id, 'activity_id' => $this->activity($this->teacher())->id, 'session_type' => 'Diagnostic', 'initiated_by' => 'Teacher', 'accuracy_percent' => 80]);
        }

        return $kid;
    }

    private function activity(Teacher $t): Activity
    {
        return Activity::create([
            'created_by_teacher_id' => $t->id, 'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'competency_label' => 'Foundational Reading',
            'activity_type' => 'word_reading', 'title' => 'Farm words', 'instructions' => 'Read.', 'passage_text' => 'cat dog', 'reference_text' => 'cat dog',
            'word_count' => 2, 'status' => 'Approved', 'difficulty_tier' => 'Easy',
        ]);
    }

    private function api(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    // ------------------------------------------------------------------ the rule itself

    public function test_each_rung_maps_to_the_step_the_child_sees(): void
    {
        [, $p] = $this->parent();
        $expect = [0 => 'Letter Explorer', 1 => 'Word Builder', 2 => 'Sentence Reader', 3 => 'Sentence Reader', 4 => 'Story Reader', 5 => 'Story Reader', 6 => 'Story Reader'];

        foreach ($expect as $rung => $name) {
            $kid = $this->child($p, "Kid{$rung}", $rung);
            $this->assertSame($name, ParentInsights::step($kid)['name'], "rung {$rung}");
            // exactly what the child's own Home shows
            $this->assertSame($name, ReadingLevel::stepName($kid), "rung {$rung}: parent and child agree");
        }
    }

    public function test_a_child_who_has_not_done_the_first_check_is_not_given_a_guessed_step(): void
    {
        [, $p] = $this->parent();
        // The wizard stores a guessed level from the parent's own answers, so mastery_level and reading_rung may be set before any check.
        $kid = $this->child($p, 'Ben', 1, checked: false);

        $step = ParentInsights::step($kid);
        $this->assertFalse($step['checked']);
        $this->assertSame('First check not done', $step['pill']);
        $this->assertNull($step['number']);
        $this->assertStringContainsString('has not finished the first reading check yet', ParentInsights::level($kid)['plain']);
    }

    public function test_a_teacher_still_gets_the_school_words(): void
    {
        [, $p] = $this->parent();
        $kid = $this->child($p, 'Mara', 3);

        $this->assertSame('Instructional', ReadingLevel::forAdult($kid)['short'], 'the teacher side is untouched');
        $this->assertSame('Sentence Reader', ParentInsights::step($kid)['name']);
    }

    // ------------------------------------------------------------------ the website

    public function test_the_children_page_shows_the_step_name_not_the_stored_level(): void
    {
        [$user, $p] = $this->parent();
        $this->child($p, 'Hapi', 5);   // stored Proficient
        $this->child($p, 'Ben', 1);    // stored Beginning
        $this->child($p, 'Sarah', 3);  // stored Developing
        $this->child($p, 'Newcomer', 0, checked: false);

        $html = $this->actingAs($user)->get(route('parent.children.index'))->assertOk()->getContent();

        foreach (['Story Reader', 'Word Builder', 'Sentence Reader', 'First check not done'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        foreach (['Proficient', 'Beginning', 'Developing', 'Instructional', 'Independent', 'Frustration'] as $internal) {
            $this->assertStringNotContainsString(">{$internal}<", $html, "{$internal} must not be shown to a parent");
        }
    }

    public function test_the_home_page_shows_the_step_and_says_a_step_change_in_step_names(): void
    {
        [$user, $p] = $this->parent();
        $kid = $this->child($p, 'Mara', 3);
        $row = ReadingSession::create([
            'learner_id' => $kid->id, 'activity_id' => $this->activity($this->teacher())->id, 'session_type' => 'Practice', 'initiated_by' => 'Teacher',
            'accuracy_percent' => 95, 'level_before' => 'Beginning', 'level_after' => 'Developing', 'rung_before' => 1, 'rung_after' => 2,
        ]);
        $row->timestamp = now()->subHour();
        $row->save();

        $this->actingAs($user)->get(route('parent.dashboard'))->assertOk()
            ->assertSee('Sentence Reader')
            ->assertSee('95% accuracy, moved up to Sentence Reader')
            ->assertDontSee('moved to Developing');
    }

    // ------------------------------------------------------------------ the phone API

    public function test_the_api_carries_the_step_and_keeps_the_older_keys_for_older_app_builds(): void
    {
        [, $p, $token] = $this->parent();
        $this->child($p, 'Mia', 1);
        $this->child($p, 'Pending', 3, checked: false);

        $rows = collect($this->api($token)->getJson('/api/parent/children')->assertOk()->json('children'))->keyBy('firstName');

        $mia = $rows['Mia']['level'];
        $this->assertSame(2, $mia['stepNumber']);
        $this->assertSame('Word Builder', $mia['name']);
        $this->assertSame('Sounding Out Words', $mia['skill']);
        $this->assertSame('Step 2 of 4: Sounding Out Words', $mia['step']);
        $this->assertStringContainsString('Mia is learning to sound out and read words', $mia['plain']);
        // older builds draw label and short: they must carry the step name, never a school word
        $this->assertSame('Word Builder', $mia['label']);
        $this->assertSame('Word Builder', $mia['short']);

        $new = $rows['Pending']['level'];
        $this->assertNull($new['stepNumber']);
        $this->assertSame('First check not done', $new['label']);
        $this->assertSame('unchecked', $new['band']);

        $this->assertStringNotContainsString('Instructional', json_encode($rows));
        $this->assertStringNotContainsString('Frustration', json_encode($rows));
    }

    public function test_the_api_says_a_move_in_step_names(): void
    {
        [, $p, $token] = $this->parent();
        $kid = $this->child($p, 'Mia', 3);
        $a = $this->activity($this->teacher());
        $up = ReadingSession::create(['learner_id' => $kid->id, 'activity_id' => $a->id, 'session_type' => 'Practice', 'initiated_by' => 'Teacher', 'accuracy_percent' => 95, 'rung_before' => 3, 'rung_after' => 4]);
        $same = ReadingSession::create(['learner_id' => $kid->id, 'activity_id' => $a->id, 'session_type' => 'Practice', 'initiated_by' => 'Teacher', 'accuracy_percent' => 91, 'rung_before' => 2, 'rung_after' => 3]);
        $old = ReadingSession::create(['learner_id' => $kid->id, 'activity_id' => $a->id, 'session_type' => 'Practice', 'initiated_by' => 'Teacher', 'accuracy_percent' => 90, 'level_before' => 'Beginning', 'level_after' => 'Developing']);

        $recent = collect($this->api($token)->getJson('/api/parent/home')->assertOk()->json('child.recent'))->keyBy('id');

        $this->assertSame('Story Reader', $recent[$up->id]['moved']);
        $this->assertSame('moved up to Story Reader', $recent[$up->id]['movedText']);
        $this->assertNull($recent[$same->id]['moved'], 'a rung inside the same step is not a move of step');
        $this->assertNull($recent[$old->id]['moved'], 'a reading from before steps were recorded claims nothing');
    }

    // ------------------------------------------------------------------ alerts

    public function test_the_alert_after_a_reading_uses_step_names_for_the_parent_and_school_words_for_the_teacher(): void
    {
        $t = $this->teacher();
        $class = SchoolClass::create(['teacher_id' => $t->id, 'name' => 'Rizal', 'grade_level' => 'Grade 1', 'section' => 'A', 'school_year' => SchoolClass::currentSchoolYear()]);
        [$parentUser, $p] = $this->parent();
        $kid = $this->child($p, 'Mary', 3, true, $class);
        $a = $this->activity($t);
        $session = ReadingSession::create(['learner_id' => $kid->id, 'activity_id' => $a->id, 'session_type' => 'Practice', 'initiated_by' => 'Teacher', 'accuracy_percent' => 95]);

        $notify = new \ReflectionMethod(LearnerReadingService::class, 'notifyForSession');
        // Sentences (rung 3) to Short stories (rung 4): the step changes (Sentence Reader to Story Reader) and so does the school's level.
        $notify->invoke(app(LearnerReadingService::class), $kid, $a, $session, ['moved' => 'up', 'rungBefore' => 3, 'rungAfter' => 4, 'rungLabelAfter' => 'Short stories', 'stepAfter' => 'Story Reader']);

        $parentText = Notification::where('recipient_user_id', $parentUser->id)->value('message');
        $teacherText = Notification::where('recipient_user_id', $t->user_id)->value('message');

        $this->assertStringContainsString('moved up to Story Reader.', $parentText);
        $this->assertStringNotContainsString('Instructional', $parentText);
        $this->assertStringNotContainsString('level', $parentText);
        $this->assertStringContainsString('moved up to Short stories (Story Reader)', $teacherText, 'the teacher keeps the rung wording');
    }

    public function test_a_move_inside_the_same_step_says_nothing_about_a_step_to_the_parent(): void
    {
        $t = $this->teacher();
        $class = SchoolClass::create(['teacher_id' => $t->id, 'name' => 'Rizal', 'grade_level' => 'Grade 1', 'section' => 'A', 'school_year' => SchoolClass::currentSchoolYear()]);
        [$parentUser, $p] = $this->parent();
        $kid = $this->child($p, 'Mary', 3, true, $class);
        $a = $this->activity($t);
        $session = ReadingSession::create(['learner_id' => $kid->id, 'activity_id' => $a->id, 'session_type' => 'Practice', 'initiated_by' => 'Teacher', 'accuracy_percent' => 92]);

        (new \ReflectionMethod(LearnerReadingService::class, 'notifyForSession'))
            ->invoke(app(LearnerReadingService::class), $kid, $a, $session, ['moved' => 'up', 'rungBefore' => 2, 'rungAfter' => 3, 'rungLabelAfter' => 'Sentences', 'stepAfter' => 'Sentence Reader']);

        $this->assertSame('Mary read "Farm words" with 92% accuracy.', Notification::where('recipient_user_id', $parentUser->id)->value('message'));
    }

    public function test_the_level_confirmed_alert_after_the_first_check_names_the_step(): void
    {
        [$parentUser, $p] = $this->parent();
        $kid = $this->child($p, 'Rosa', 0, false);

        $finish = new \ReflectionMethod(LearnerDiagnosticService::class, 'finishDiagnostic');
        $tiers = \App\Support\DiagnosticPlacement::ladder();
        $finish->invoke(app(LearnerDiagnosticService::class), $kid, $tiers[1], 75.0, true);

        $text = Notification::where('recipient_user_id', $parentUser->id)->where('type', Notification::TYPE_LEVEL_CONFIRMED)->value('message');
        $this->assertSame("Rosa's first reading check is done. Rosa starts on the Word Builder step.", $text);
        $this->assertStringNotContainsString('Beginning', $text);
    }

    public function test_old_alerts_to_a_parent_lose_the_school_words_but_a_teacher_keeps_them(): void
    {
        $t = $this->teacher();
        [$parentUser, $p] = $this->parent();
        $kid = $this->child($p, 'Rosa', 3);
        $make = fn (int $userId, string $type, string $message) => Notification::create(['recipient_user_id' => $userId, 'learner_id' => $kid->id, 'type' => $type, 'message' => $message]);

        $confirmed = $make($parentUser->id, Notification::TYPE_LEVEL_CONFIRMED, "Rosa's starting reading level has been confirmed: Developing.");
        $summary = $make($parentUser->id, Notification::TYPE_SESSION_SUMMARY, 'Rosa read "Farm words" with 95% accuracy, and moved up to Sentences (Sentence Reader), now at the Instructional level.');
        $plain = $make($parentUser->id, Notification::TYPE_SESSION_SUMMARY, 'Rosa read "Farm words" with 80% accuracy.');
        $teachers = $make($t->user_id, Notification::TYPE_SESSION_SUMMARY, 'Rosa read "Farm words" with 95% accuracy, and moved up to Sentences (Sentence Reader), now at the Instructional level.');

        (require base_path('database/migrations/2026_10_23_000200_reword_old_parent_alerts.php'))->up();

        $this->assertSame("Rosa's first reading check is done.", $confirmed->fresh()->message);
        $this->assertSame('Rosa read "Farm words" with 95% accuracy, and moved up to Sentences (Sentence Reader).', $summary->fresh()->message);
        $this->assertSame('Rosa read "Farm words" with 80% accuracy.', $plain->fresh()->message);
        $this->assertStringContainsString('now at the Instructional level', $teachers->fresh()->message, 'a teacher keeps the school words');
    }
}
