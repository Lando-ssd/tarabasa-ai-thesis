<?php

namespace Tests\Feature;

use App\Jobs\GenerateActivitiesJob;
use App\Models\Activity;
use App\Models\ActivityGeneration;
use App\Models\Learner;
use App\Models\ReadingSession;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ActivityAiClient;
use App\Support\GenerationBasis;
use App\Support\LearnerCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * "Write it for my class": the generator knows nothing about the children, so a request can be based on one
 * class's real readings. These check the facts are worked out from the class's own data, that one child's habit
 * is never called the class's, that the teacher sees the basis and the generator gets it, and that a request can
 * only ever be written for the teacher's own class of the right grade.
 */
class GenerationBasisTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.activity_ai.ready_wait' => 1, 'services.retry_pause' => 0]);
    }

    private function teacher(): array
    {
        $n = ++$this->seq;
        $user = User::create(['first_name' => 'Ana', 'last_name' => "Basis{$n}", 'email' => "basis{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        $user->forceFill(['email_verified_at' => now()])->save();
        $teacher = Teacher::create(['user_id' => $user->id, 'school_name' => 'Rizal ES', 'employee_id' => "BA{$n}", 'status' => 'Active', 'free_generation_credits_remaining' => 3, 'grades_handled' => ['Grade 1', 'Grade 2']]);

        return [$user, $teacher];
    }

    private function schoolClass(Teacher $teacher, string $grade = 'Grade 1', ?string $year = null, string $name = 'Sampaguita'): SchoolClass
    {
        return SchoolClass::create(['teacher_id' => $teacher->id, 'name' => $name, 'grade_level' => $grade, 'section' => 'A', 'school_year' => $year ?? SchoolClass::currentSchoolYear()]);
    }

    private function learner(SchoolClass $class, string $first, int $rung = 2, array $interests = []): Learner
    {
        return Learner::create([
            'learner_code' => LearnerCode::generate(), 'class_id' => $class->id, 'first_name' => $first, 'last_name' => 'Cruz', 'grade_level' => $class->grade_level,
            'pin' => '1234', 'avatar_id' => 'A', 'mastery_level' => 'Developing', 'reading_rung' => $rung, 'interests' => $interests,
        ]);
    }

    /** Three practice readings in which the child drops word endings (dogs read as dog) on the given words. */
    private function endingMistakes(Learner $kid, array $words): void
    {
        $activity = Activity::create(['created_by_teacher_id' => null, 'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'competency_label' => 'Foundational Reading', 'activity_type' => 'word_reading', 'title' => 'Plain', 'instructions' => 'Read the words.', 'passage_text' => 'dogs cats', 'reference_text' => 'dogs cats', 'word_count' => 2, 'status' => 'Approved', 'difficulty_tier' => 'Easy']);
        $feedback = array_map(fn ($w) => ['reference' => $w, 'spoken' => rtrim($w, 's'), 'status' => 'substitution'], $words);

        for ($i = 0; $i < 3; $i++) {
            $row = ReadingSession::create(['learner_id' => $kid->id, 'activity_id' => $activity->id, 'session_type' => 'Practice', 'initiated_by' => 'Teacher', 'accuracy_percent' => 60, 'word_feedback' => $feedback]);
            $row->timestamp = now()->subDays($i + 1);
            $row->save();
        }
    }

    // ------------------------------------------------------------- the facts

    public function test_the_basis_names_the_step_the_shared_mistakes_the_words_and_the_topics(): void
    {
        [, $t] = $this->teacher();
        $class = $this->schoolClass($t);
        $a = $this->learner($class, 'Ana', 2, ['animals', 'food']);
        $b = $this->learner($class, 'Ben', 2, ['animals']);
        $this->learner($class, 'Cy', 3, ['vehicles']);
        $this->endingMistakes($a, ['dogs', 'cats', 'pigs', 'hens']);
        $this->endingMistakes($b, ['dogs', 'cats', 'cows', 'bats']);

        $basis = GenerationBasis::forClass($class, 'word_reading');
        $text = implode(' ', $basis['lines']);

        $this->assertTrue($basis['enough']);
        $this->assertSame(3, $basis['learners']);
        $this->assertSame(2, $basis['withReadings']);
        $this->assertStringContainsString('3 learners in the class, 2 with practice readings', $text);
        $this->assertStringContainsString('Most read at the Short sentences step (2 of 3', $text);
        $this->assertStringContainsString('2 learners leaves off or changes word endings', $text);
        $this->assertStringContainsString('Words several learners missed: dogs, cats', $text);
        $this->assertStringContainsString('Topics they like: animals', $text);
        $this->assertStringContainsString('For this activity type,', $text);

        // The note for the generator comes from the same facts and leaves room for the teacher's own words.
        $this->assertStringContainsString('Short sentences step', $basis['note']);
        $this->assertStringContainsString('dogs, cats', $basis['note']);
        $this->assertLessThanOrEqual(420, strlen($basis['note']));
    }

    public function test_one_childs_habit_is_not_called_the_classs(): void
    {
        [, $t] = $this->teacher();
        $class = $this->schoolClass($t);
        $a = $this->learner($class, 'Ana', 2, ['animals']);
        $this->learner($class, 'Ben', 2, ['food']);
        $this->endingMistakes($a, ['dogs', 'cats', 'pigs', 'hens']);

        $text = implode(' ', GenerationBasis::forClass($class)['lines']);

        $this->assertStringNotContainsString('word endings', $text);
        $this->assertStringNotContainsString('Words several learners missed', $text);
        $this->assertStringNotContainsString('Topics they like', $text);
    }

    public function test_a_class_with_no_learners_says_so_and_sends_nothing(): void
    {
        [, $t] = $this->teacher();
        $basis = GenerationBasis::forClass($this->schoolClass($t));

        $this->assertFalse($basis['enough']);
        $this->assertNull($basis['note']);
        $this->assertStringContainsString('no learners yet', $basis['lines'][0]);
    }

    public function test_the_notes_keep_the_teachers_words_first_and_stay_inside_the_limit(): void
    {
        $notes = GenerationBasis::composeNotes(['Use the sh sound.', null, str_repeat('class fact ', 200), 'late calibration']);

        $this->assertStringStartsWith('Use the sh sound.', $notes);
        $this->assertLessThanOrEqual(1000, strlen($notes));
        $this->assertNull(GenerationBasis::composeNotes([null, '  ']));
    }

    // ------------------------------------------------------ asking for a class

    public function test_a_request_for_a_class_saves_the_basis_and_the_generator_is_told_it(): void
    {
        [$user, $t] = $this->teacher();
        $class = $this->schoolClass($t);
        $a = $this->learner($class, 'Ana', 2);
        $b = $this->learner($class, 'Ben', 2);
        $this->endingMistakes($a, ['dogs', 'cats', 'pigs', 'hens']);
        $this->endingMistakes($b, ['dogs', 'cats', 'cows', 'bats']);

        config(['services.activity_ai.url' => 'https://gen.test', 'services.activity_ai.key' => 'k']);
        Http::fake(['gen.test/*' => Http::response(['generation_id' => 'g1', 'bundle_title' => 'Grade 1 Foundational Reading', 'competency_label' => 'Foundational Reading', 'levels' => [
            'easy' => [['title' => 'Easy A', 'variant_label' => 'A', 'instructions' => 'Read.', 'display_text' => 'dogs cats', 'reference_text' => 'dogs cats', 'word_count' => 2, 'target_skills' => [], 'reading_features' => [], 'follow_up_questions' => []]],
            'medium' => [], 'hard' => [],
        ]])]);
        $this->actingAs($user);

        Queue::fake();
        $this->post(route('teacher.activities.generate'), [
            'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'activity_type' => 'word_reading',
            'levels' => ['Easy' => 1], 'teacher_notes' => 'Use animals.', 'class_id' => $class->id,
        ])->assertRedirect();

        $generation = ActivityGeneration::firstOrFail();
        $this->assertSame($class->id, $generation->class_id);
        $this->assertTrue($generation->basis['enough']);
        $this->assertSame('Sampaguita', $generation->basis['className']);

        (new GenerateActivitiesJob($generation->id))->handle(app(ActivityAiClient::class));

        // Teacher's own words first, then what the class's readings say.
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/generate-bundle')
            && str_starts_with($r['teacher_notes'], 'Use animals.')
            && str_contains($r['teacher_notes'], "From this class's real readings")
            && str_contains($r['teacher_notes'], 'dogs, cats')
            && strlen($r['teacher_notes']) <= 1000);

        // Only the teacher's own words are the activity's notes; the reasons are kept beside them and shown.
        $activity = Activity::latest('id')->first();
        $this->assertSame('Use animals.', $activity->teacher_notes);
        $this->assertSame('Sampaguita', $activity->generation_basis['className']);
        // The class id is kept too, so "Did it work?" can tell two classes with the same name apart.
        $this->assertSame($class->id, $activity->generation_basis['classId']);
        $this->assertNotEmpty($activity->generation_basis['lines']);

        $this->get(route('teacher.activities.window', $activity))
            ->assertOk()
            ->assertSee('Written for Sampaguita')
            ->assertSee('Words several learners missed');
    }

    public function test_a_class_with_nothing_to_say_is_written_as_a_general_activity(): void
    {
        [$user, $t] = $this->teacher();
        $class = $this->schoolClass($t);

        config(['services.activity_ai.url' => 'https://gen.test', 'services.activity_ai.key' => 'k']);
        Http::fake(['gen.test/*' => Http::response(['generation_id' => 'g2', 'bundle_title' => 'x', 'competency_label' => 'Foundational Reading', 'levels' => [
            'easy' => [['title' => 'Easy A', 'variant_label' => 'A', 'instructions' => 'Read.', 'display_text' => 'cat dog', 'reference_text' => 'cat dog', 'word_count' => 2, 'target_skills' => [], 'reading_features' => [], 'follow_up_questions' => []]],
            'medium' => [], 'hard' => [],
        ]])]);
        $this->actingAs($user);

        Queue::fake();
        $this->post(route('teacher.activities.generate'), [
            'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'activity_type' => 'word_reading', 'levels' => ['Easy' => 1], 'class_id' => $class->id,
        ])->assertRedirect();

        $generation = ActivityGeneration::firstOrFail();
        (new GenerateActivitiesJob($generation->id))->handle(app(ActivityAiClient::class));

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/generate-bundle') && ! str_contains((string) ($r['teacher_notes'] ?? ''), 'real readings'));
        $this->assertNull(Activity::latest('id')->first()->generation_basis);
    }

    public function test_a_request_cannot_be_written_for_someone_elses_class_a_past_year_or_another_grade(): void
    {
        [$user, $t] = $this->teacher();
        [, $other] = $this->teacher();
        $mine = $this->schoolClass($t, 'Grade 1');
        $theirs = $this->schoolClass($other, 'Grade 1', null, 'Rose');
        $past = $this->schoolClass($t, 'Grade 1', '2019-2020', 'Old');
        $grade2 = $this->schoolClass($t, 'Grade 2', null, 'Dahlia');
        $this->actingAs($user);
        Queue::fake();

        $ask = fn (int $classId, string $grade = 'Grade 1') => $this->post(route('teacher.activities.generate'), [
            'grade_level' => $grade, 'competency' => 'foundational_reading', 'activity_type' => 'word_reading', 'levels' => ['Easy' => 1], 'class_id' => $classId,
        ]);

        $ask($theirs->id)->assertSessionHasErrors('class_id');
        $ask($past->id)->assertSessionHasErrors('class_id');
        $ask($grade2->id)->assertSessionHasErrors('class_id');   // a Grade 2 class for a Grade 1 request
        $ask(999999)->assertSessionHasErrors('class_id');

        $this->assertSame(0, ActivityGeneration::count());
        $this->assertSame(3, $t->fresh()->free_generation_credits_remaining);

        $ask($mine->id)->assertSessionDoesntHaveErrors();
        $this->assertSame(1, ActivityGeneration::count());
    }

    // ------------------------------------------------------- what the window shows

    public function test_the_window_gets_the_lines_before_generate_and_only_for_the_teachers_own_class(): void
    {
        [$user, $t] = $this->teacher();
        [, $other] = $this->teacher();
        $class = $this->schoolClass($t);
        $this->learner($class, 'Ana', 2);
        $theirs = $this->schoolClass($other, 'Grade 1', null, 'Rose');
        $this->actingAs($user);

        $this->getJson(route('teacher.activities.basis', ['class_id' => $class->id, 'grade_level' => 'Grade 1', 'activity_type' => 'word_reading']))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('enough', true)
            ->assertJsonFragment(['1 learner in the class, none with a practice reading yet.']);

        $this->getJson(route('teacher.activities.basis', ['class_id' => $theirs->id, 'grade_level' => 'Grade 1']))->assertStatus(422)->assertJsonPath('ok', false);
        $this->getJson(route('teacher.activities.basis', ['class_id' => $class->id, 'grade_level' => 'Grade 2']))->assertStatus(422);

        $this->get(route('teacher.activities.index', ['generate' => 1]))
            ->assertOk()
            ->assertSee('For a class')
            ->assertSee('Sampaguita (Grade 1)')
            ->assertDontSee('Rose (Grade 1)');
    }
}
