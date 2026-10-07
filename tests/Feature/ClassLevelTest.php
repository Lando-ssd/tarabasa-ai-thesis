<?php

namespace Tests\Feature;

use App\Models\Learner;
use App\Models\ReadingSession;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use App\Support\ClassLevel;
use App\Support\LearnerCode;
use App\Support\LearnerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A teacher's own instruction: a class is taught at one general level, and a learner who already reads at the Independent
 * level cannot be added to a class taught lower, even in the same grade. These pin down that rule (typed code, scanned
 * code and moving between classes), what the teacher is told, and the teaching notes and progress shown on a learner's page.
 */
class ClassLevelTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function teacher(): array
    {
        $n = ++$this->seq;
        $user = User::create(['first_name' => 'Ana', 'last_name' => "Reyes{$n}", 'email' => "cl{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        $user->forceFill(['email_verified_at' => now()])->save();
        $teacher = Teacher::create(['user_id' => $user->id, 'school_name' => 'Rizal ES', 'employee_id' => "C{$n}", 'status' => 'Active', 'free_generation_credits_remaining' => 2]);

        return [$user, $teacher];
    }

    private function klass(Teacher $t, string $grade, string $name = 'Class'): SchoolClass
    {
        return SchoolClass::create(['teacher_id' => $t->id, 'name' => $name, 'grade_level' => $grade, 'section' => 'A', 'group_tag' => null, 'school_year' => SchoolClass::currentSchoolYear()]);
    }

    private function kid(?SchoolClass $c, int $rung, array $o = []): Learner
    {
        $n = ++$this->seq;

        return Learner::create($o + [
            'learner_code' => LearnerCode::generate(), 'class_id' => $c?->id, 'first_name' => "Kid{$n}", 'last_name' => 'Cruz',
            'grade_level' => $c?->grade_level ?? 'Grade 1', 'pin' => '1234', 'avatar_id' => 'A',
            'mastery_level' => \App\Services\ReadingProgression::levelForRung($rung), 'reading_rung' => $rung,
        ]);
    }

    public function test_a_class_with_few_learners_is_taught_at_the_usual_level_for_its_grade(): void
    {
        [, $t] = $this->teacher();

        $this->assertSame('instructional', ClassLevel::general($this->klass($t, 'Grade 1'))['band']);
        $this->assertSame('grade', ClassLevel::general($this->klass($t, 'Grade 1'))['source']);
        $this->assertSame('independent', ClassLevel::general($this->klass($t, 'Grade 2'))['band']);
    }

    public function test_a_class_with_three_or_more_levelled_learners_takes_the_middle_level_of_them(): void
    {
        [, $t] = $this->teacher();
        $class = $this->klass($t, 'Grade 2');
        foreach ([0, 1, 4] as $rung) {
            $this->kid($class, $rung);
        }

        $general = ClassLevel::general($class->fresh('learners'));

        $this->assertSame('frustration', $general['band'], 'non-reader, frustration, instructional: the middle one');
        $this->assertSame('learners', $general['source']);
    }

    public function test_an_independent_reader_cannot_be_added_to_a_lower_level_class_even_in_the_same_grade(): void
    {
        [$user, $t] = $this->teacher();
        $class = $this->klass($t, 'Grade 1', 'Sampaguita');
        $strong = $this->kid(null, 6, ['first_name' => 'Rosa', 'grade_level' => 'Grade 1']);
        $this->actingAs($user);

        $this->from('/x')->post(route('teacher.classes.join-learner', $class), ['learner_code' => $strong->learner_code])
            ->assertSessionHasErrors('learner_code');

        $this->assertNull($strong->fresh()->class_id, 'not enrolled');
        $this->assertStringContainsString('Independent level', session('errors')->first('learner_code'));
        $this->assertStringContainsString('even in the same grade', session('errors')->first('learner_code'));
    }

    public function test_the_scanner_gets_the_same_refusal_as_json(): void
    {
        [$user, $t] = $this->teacher();
        $class = $this->klass($t, 'Grade 1');
        $strong = $this->kid(null, 5);
        $this->actingAs($user);

        $this->postJson(route('teacher.classes.join-learner', $class), ['learner_code' => $strong->learner_code])
            ->assertStatus(422)->assertJson(['ok' => false])->assertJsonPath('message', fn ($m) => str_contains($m, 'Independent level'));
        $this->assertNull($strong->fresh()->class_id);
    }

    public function test_an_independent_reader_joins_a_class_taught_at_that_level_and_others_join_any_class(): void
    {
        [$user, $t] = $this->teacher();
        $high = $this->klass($t, 'Grade 2', 'Higher');
        $low = $this->klass($t, 'Grade 1', 'Lower');
        $strong = $this->kid(null, 6, ['grade_level' => 'Grade 2']);
        $middle = $this->kid(null, 3);
        $starter = $this->kid(null, 0);
        $this->actingAs($user);

        $this->post(route('teacher.classes.join-learner', $high), ['learner_code' => $strong->learner_code])->assertRedirect();
        $this->assertSame($high->id, $strong->fresh()->class_id);

        $this->post(route('teacher.classes.join-learner', $low), ['learner_code' => $middle->learner_code])->assertRedirect();
        $this->post(route('teacher.classes.join-learner', $low), ['learner_code' => $starter->learner_code])->assertRedirect();
        $this->assertSame($low->id, $middle->fresh()->class_id);
        $this->assertSame($low->id, $starter->fresh()->class_id, 'a child who needs more support is always welcome');
    }

    public function test_a_child_who_has_not_been_measured_is_never_turned_away(): void
    {
        [$user, $t] = $this->teacher();
        $class = $this->klass($t, 'Grade 1');
        // The parent said "reads independently" but nothing has been measured yet: that is a starting point, not a level.
        $unchecked = $this->kid(null, 6, ['reading_rung' => null, 'mastery_level' => null, 'reading_stage' => 'independent']);
        $this->actingAs($user);

        $this->post(route('teacher.classes.join-learner', $class), ['learner_code' => $unchecked->learner_code])->assertRedirect();
        $this->assertSame($class->id, $unchecked->fresh()->class_id);
    }

    public function test_moving_an_independent_reader_into_a_lower_class_is_refused_too(): void
    {
        [$user, $t] = $this->teacher();
        $from = $this->klass($t, 'Grade 2', 'From');
        $to = $this->klass($t, 'Grade 1', 'To');
        $strong = $this->kid($from, 6);
        $this->actingAs($user);

        $this->from('/x')->post(route('teacher.classes.move-learner', [$from, $strong]), ['to_class_id' => $to->id])
            ->assertSessionHasErrors('to_class_id');
        $this->assertSame($from->id, $strong->fresh()->class_id);
    }

    public function test_the_class_window_says_learners_shows_the_general_level_and_the_learners_picture(): void
    {
        [$user, $t] = $this->teacher();
        $class = $this->klass($t, 'Grade 1', 'Sampaguita');
        $withPhoto = $this->kid($class, 3, ['first_name' => 'Pia', 'last_name' => 'Lim', 'avatar_photo_path' => 'avatars/pia.jpg']);
        $withGlyph = $this->kid($class, 3, ['first_name' => 'Tomas', 'avatar_id' => "\u{1F98A}"]);
        $plain = $this->kid($class, 3, ['first_name' => 'Noel', 'last_name' => 'Ong', 'avatar_id' => 'A']);

        $html = $this->actingAs($user)->get(route('teacher.classes.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Search learners', $html);
        $this->assertStringNotContainsString('Search this class', $html);
        $this->assertStringContainsString('storage/avatars/pia.jpg', $html, 'the uploaded photo is shown');
        $this->assertStringContainsString('onerror="this.style.display=', $html, 'a missing file falls back to the picture or the initials');
        $this->assertStringContainsString("\u{1F98A}", $html, 'the picture the child chose');
        $this->assertMatchesRegularExpression('/class="av init c\d" aria-hidden="true">NO<\/span>/', $html, 'initials when there is neither a photo nor a picture');
        $this->assertStringContainsString('This class is taught at the Instructional level', $html);
        $this->assertStringContainsString('cannot be added to a class taught at a lower level', $html);
    }

    public function test_the_learner_page_teaches_the_step_and_shows_progress_toward_the_next_one(): void
    {
        [$user, $t] = $this->teacher();
        $class = $this->klass($t, 'Grade 1', 'Sampaguita');
        $kid = $this->kid($class, 1, ['first_name' => 'Pia']);
        ReadingSession::create(['learner_id' => $kid->id, 'activity_id' => \App\Models\Activity::create([
            'created_by_teacher_id' => $t->id, 'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'competency_label' => 'Foundational Reading', 'activity_type' => 'word_reading',
            'difficulty_tier' => 'Easy', 'title' => 'Farm Words', 'instructions' => 'Read.', 'passage_text' => 'cat dog pig hen cow bat', 'word_count' => 6, 'status' => 'Approved',
        ])->id, 'session_type' => 'Practice', 'initiated_by' => 'Teacher', 'accuracy_percent' => 100]);

        $profile = LearnerProfile::forTeacher($kid->fresh());
        $this->assertSame(1, $profile['progress']['have']);
        $this->assertSame(3, $profile['progress']['need']);
        $this->assertStringContainsString('6 words or more at 90 percent', $profile['teach']['ready'], 'written from the numbers the app counts');
        $this->assertNotEmpty($profile['teach']['moves']);

        $html = $this->actingAs($user)->get(route('teacher.classes.index'))->assertOk()->getContent();
        $this->assertStringContainsString('How to teach this step', $html);
        $this->assertStringContainsString('Toward Short sentences', $html);
        $this->assertStringContainsString('1 of 3 strong readings so far at Words', $html);
        $this->assertStringContainsString('National Reading Panel', $html);
    }

    public function test_the_learner_page_names_the_pattern_in_the_mistakes_only_when_there_is_enough_evidence(): void
    {
        [$user, $t] = $this->teacher();
        $class = $this->klass($t, 'Grade 1', 'Sampaguita');
        $kid = $this->kid($class, 1, ['first_name' => 'Pia']);
        $activity = \App\Models\Activity::create([
            'created_by_teacher_id' => $t->id, 'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'competency_label' => 'Foundational Reading', 'activity_type' => 'word_reading',
            'difficulty_tier' => 'Easy', 'title' => 'Hat words', 'instructions' => 'Read.', 'passage_text' => 'hat pot sun', 'word_count' => 3, 'status' => 'Approved',
        ]);
        $reading = fn () => ReadingSession::create([
            'learner_id' => $kid->id, 'activity_id' => $activity->id, 'session_type' => 'Practice', 'initiated_by' => 'Teacher', 'accuracy_percent' => 33,
            'word_feedback' => [
                ['reference' => 'hat', 'spoken' => 'hit', 'status' => 'substitution'],
                ['reference' => 'pot', 'spoken' => 'pet', 'status' => 'substitution'],
                ['reference' => 'sun', 'spoken' => 'sun', 'status' => 'correct'],
            ],
        ]);

        $reading();
        $this->assertNull(LearnerProfile::forTeacher($kid->fresh())['pattern'], 'one reading is not a pattern');

        $reading();
        $reading();
        $pattern = LearnerProfile::forTeacher($kid->fresh())['pattern'];
        $this->assertNotNull($pattern);
        $this->assertStringContainsString('vowel', $pattern['says']);
        $this->assertSame('RL1PWS-I-4', $pattern['code']);
        $this->assertTrue($pattern['early'], 'three readings is an early sign, and it says so');

        $html = $this->actingAs($user)->get(route('teacher.classes.index'))->assertOk()->getContent();
        $this->assertStringContainsString('What the readings show', $html);
        $this->assertStringContainsString('Over the last 3 readings, Pia mixes up the vowel', $html);
        $this->assertStringContainsString('This is an early sign', $html);
        $this->assertStringContainsString('Change just the middle sound', $html);
    }

    public function test_every_rung_has_teaching_notes_and_the_ready_line_matches_the_progression_config(): void
    {
        $this->assertCount(7, config('teaching_path.rungs'));
        foreach (range(0, 6) as $rung) {
            $path = config("teaching_path.rungs.{$rung}");
            $this->assertNotEmpty($path['focus'], "rung {$rung} focus");
            $this->assertGreaterThanOrEqual(3, count($path['moves']), "rung {$rung} moves");
            $this->assertNotEmpty($path['watch'], "rung {$rung} watch");
            // Rungs 0 to 5 are written from config/progression.php; only the top rung has its own wording.
            $this->assertSame($rung === 6, isset($path['ready']), "rung {$rung} ready line");
            $this->assertSame($rung < 6, config("progression.min_words.{$rung}") !== null);
        }
    }

    public function test_the_teaching_notes_name_codes_that_exist_in_the_reading_check(): void
    {
        $known = collect(config('diagnostic.ladder'))->flatMap(fn ($r) => array_keys($r['codes']))->unique()->all();
        preg_match_all('/\b(?:RL|EN)\d[A-Z]{2,4}-[IVX]+-\d+\b/', json_encode(config('teaching_path.rungs')), $found);

        $this->assertNotEmpty($found[0]);
        foreach (array_unique($found[0]) as $code) {
            $this->assertContains($code, $known, "{$code} is not a code the first reading check uses");
        }
    }
}
