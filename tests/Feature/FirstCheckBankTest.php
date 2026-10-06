<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Learner;
use App\Models\ReadingSession;
use App\Services\DiagnosticBank;
use App\Services\LearnerDiagnosticService;
use App\Support\DiagnosticPlacement;
use App\Support\LearnerCode;
use App\Support\ReadingLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The first-login reading check now comes from a curated, curriculum-coded bank, not from the
 * AI generator: fixed texts three per rung, no waiting, and the rung the child lands on is saved.
 */
class FirstCheckBankTest extends TestCase
{
    use RefreshDatabase;

    /** The words a rung's texts must have (the lengths the revision preview promised). */
    private const WORDS = [
        'phonics_easy' => [5, 7],
        'phonics_medium' => [8, 10],
        'phonics_hard' => [11, 14],
        'passage_easy' => [28, 36],
        'passage_medium' => [52, 66],
        'passage_hard' => [90, 105],
    ];

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.reading_ai.url' => 'https://reading.test',
            // Nothing in this file may reach a real service.
            'services.adaptive_recommender.url' => null,
            'services.adaptive_recommender.key' => null,
            'services.activity_ai.url' => null,
        ]);
    }

    private function learner(array $o = []): Learner
    {
        $n = ++$this->seq;

        return Learner::create($o + [
            'learner_code' => LearnerCode::generate(), 'first_name' => "Kid{$n}", 'last_name' => 'Cruz', 'grade_level' => 'Grade 2',
            'pin' => '1234', 'avatar_id' => 'A', 'mastery_level' => null, 'reading_stage' => 'sentences',
            'placement_answers' => ['q1' => 'yes', 'q2' => 'yes', 'q3' => 'yes'],
        ]);
    }

    private function analysis(float $accuracy): array
    {
        $words = ['one', 'two', 'three'];

        return [
            'accuracy' => [
                'accuracy_score' => $accuracy, 'spoken_word_count' => 3, 'substitutions' => 0, 'deletions' => 0, 'insertions' => 0,
                'word_feedback' => array_map(fn ($w) => ['reference' => $w, 'spoken' => $w, 'status' => 'correct'], $words),
            ],
            'speed' => ['wcpm' => 80, 'speed_score' => 70],
            'prosody' => ['prosody_score' => 60],
            'word_timestamps' => array_map(fn ($w, $i) => ['word' => $w, 'start' => $i, 'end' => $i + .5, 'confidence' => 0.95], $words, array_keys($words)),
        ];
    }

    // ------------------------------------------------------------- the bank itself

    public function test_every_rung_has_three_texts_of_the_right_length_and_a_real_curriculum_code(): void
    {
        $ladder = config('diagnostic.ladder');
        $this->assertSame(
            ['letters', 'phonics_easy', 'phonics_medium', 'phonics_hard', 'passage_easy', 'passage_medium', 'passage_hard'],
            array_keys($ladder)
        );
        $this->assertCount(3, config('diagnostic.letters.sets'));

        foreach (self::WORDS as $rung => [$min, $max]) {
            $items = config("diagnostic_bank.{$rung}.items");
            $this->assertCount(3, $items, "{$rung} needs three variants");

            foreach ($items as $item) {
                $words = Activity::countWords($item['text']);
                $this->assertGreaterThanOrEqual($min, $words, "{$item['title']} is too short for {$rung}");
                $this->assertLessThanOrEqual($max, $words, "{$item['title']} is too long for {$rung}");
                $this->assertNotSame('', trim($item['title']));
            }

            // Three different texts, so a retake or a second child does not read the same thing.
            $this->assertCount(3, array_unique(array_column($items, 'text')));
        }

        foreach ($ladder as $rung => $def) {
            $this->assertNotEmpty($def['codes'], "{$rung} names no curriculum code");
            foreach ($def['codes'] as $code => $text) {
                $this->assertMatchesRegularExpression('/^(RL1|EN2|EN3)[A-Z]+-[IVX]+-\d+$/', $code);
                $this->assertNotSame('', $text);
            }
            $this->assertContains($def['mastery'], ['Beginning', 'Developing', 'Proficient']);
        }
    }

    public function test_each_rung_is_one_of_the_texts_a_child_can_actually_read_aloud(): void
    {
        // No lists of digits or symbols, nothing that needs explaining before reading.
        foreach (self::WORDS as $rung => $range) {
            foreach (config("diagnostic_bank.{$rung}.items") as $item) {
                $this->assertDoesNotMatchRegularExpression('/[0-9@#%&*_=<>\\\\\/]/', $item['text'], $item['title']);
            }
        }
    }

    public function test_the_bank_makes_its_rows_once_and_describes_them_honestly(): void
    {
        $bank = app(DiagnosticBank::class);
        $first = $bank->idsByRung();
        $second = $bank->idsByRung();

        $this->assertSame($first, $second, 'a second look must find the same rows, not make more');
        $this->assertSame(21, Activity::where('purpose', 'diagnostic')->count());
        $this->assertSame(21, array_sum(array_map('count', $first)));

        $a = Activity::find($first['passage_medium'][0]);
        $this->assertSame('passage_medium', $a->check_rung);
        $this->assertSame('EN3CAT-I-1', $a->curriculum_code);
        $this->assertNull($a->created_by_teacher_id, 'a check item belongs to no teacher, so it never shows in a teacher list');
        $this->assertSame('Medium', $a->difficulty_tier);
        $this->assertSame(Activity::countWords($a->passage_text), $a->word_count);
        $this->assertSame('passage_medium', DiagnosticPlacement::rungOf($a));
        $this->assertStringContainsString('EN3CAT-I-2 Comprehend stories.', implode(' ', $a->target_skills));

        $letters = Activity::find($first['letters'][2]);
        $this->assertTrue($letters->isLetterCheck());
        $this->assertSame('h r e f l k', $letters->reference_text);
        $this->assertSame('EN2PWS-I-2', $letters->curriculum_code);
    }

    public function test_editing_a_text_makes_a_new_row_and_leaves_the_old_reading_history_alone(): void
    {
        $bank = app(DiagnosticBank::class);
        $before = $bank->idsByRung();

        $original = config('diagnostic_bank.phonics_easy.items');
        $edited = $original;
        $edited[0]['text'] = 'cat dog pig bus bed red';
        config(['diagnostic_bank.phonics_easy.items' => $edited]);

        $after = $bank->idsByRung();

        $this->assertNotSame($before['phonics_easy'][0], $after['phonics_easy'][0]);
        $this->assertSame(array_slice($before['phonics_easy'], 1), array_slice($after['phonics_easy'], 1));
        $this->assertNotNull(Activity::find($before['phonics_easy'][0]), 'the old row must stay, readings may point at it');
    }

    public function test_the_scorer_is_told_the_items_own_curriculum_code_without_asking_the_generator(): void
    {
        Http::fake();
        $ids = app(DiagnosticBank::class)->idsByRung();
        $resolver = app(\App\Services\MatatagAlignmentResolver::class);

        $word = Activity::find($ids['phonics_easy'][0]);
        $this->assertSame(
            ['grade' => 1, 'subdomain' => 'Phonics and Word Study', 'competency_code' => 'RL1PWS-I-5'],
            $resolver->contextFor($word)
        );
        $story = Activity::find($ids['passage_hard'][1]);
        $this->assertSame(['grade' => 3, 'subdomain' => 'Phonics and Word Study', 'competency_code' => 'EN3CAT-I-1'], $resolver->contextFor($story));
        $this->assertSame('EN3CAT-I-1', $resolver->cachedCodeFor($story));

        Http::assertNothingSent();
    }

    // ------------------------------------------------------------- the check itself

    public function test_starting_the_check_never_calls_the_generator_and_is_instant(): void
    {
        Http::fake();
        $kid = $this->learner();

        $state = app(LearnerDiagnosticService::class)->ensureBundleGenerated($kid);

        Http::assertNothingSent();
        $this->assertSame('phonics_hard', $state['current_tier'], 'a child described as reading simple sentences starts there');
        $this->assertCount(21, collect($state['activity_ids'])->flatten());
        $this->assertSame(0, ReadingSession::count());

        $activity = Activity::findOrFail(app(LearnerDiagnosticService::class)->currentActivityId($state));
        $this->assertSame('phonics_hard', $activity->check_rung);

        // And through the page a child actually opens.
        $this->actingAs($this->learner(), 'learner')->get(route('learner.diagnostic.passage'))->assertOk()->assertSee('Read the sentences out loud.');
        Http::assertNothingSent();
    }

    public function test_children_do_not_all_get_the_same_first_text_on_a_rung(): void
    {
        $service = app(LearnerDiagnosticService::class);
        $firsts = [];
        foreach (range(1, 3) as $i) {
            $state = $service->ensureBundleGenerated($this->learner());
            $firsts[] = $state['activity_ids']['phonics_hard'][0];
        }

        $this->assertCount(3, array_unique($firsts), 'three children in a row each start on a different one of the three texts');
    }

    public function test_the_staircase_climbs_revisits_with_a_new_text_and_saves_where_the_child_landed(): void
    {
        $kid = $this->learner(['reading_stage' => 'independent', 'placement_answers' => null, 'grade_level' => 'Grade 3']);
        $this->actingAs($kid, 'learner');
        $service = app(LearnerDiagnosticService::class);

        $this->get(route('learner.diagnostic.passage'))->assertOk();
        $state = $service->diagnosticState($kid);
        $this->assertSame('passage_medium', $state['current_tier']);
        $first = $service->currentActivityId($state);

        $sent = [];
        Http::fake(['reading.test/analyze' => function ($request) use (&$sent) {
            $sent[] = $request->body();
            // down, then back up, then (cap) whatever
            return Http::response($this->analysis([40, 95, 50][count($sent) - 1] ?? 50));
        }]);

        // 40%: down one rung to passage_easy.
        $this->post(route('learner.diagnostic.record'), ['audio' => UploadedFile::fake()->create('r.webm', 30, 'audio/webm')])
            ->assertOk()->assertSee('Keep Going');
        $state = $service->diagnosticState($kid);
        $this->assertSame('passage_easy', $state['current_tier']);
        $this->assertSame(0, $state['variant_used']['passage_easy'], 'a rung not read yet starts with its first text');

        // 95%: up again to passage_medium, which must be a DIFFERENT text from the first time.
        $this->post(route('learner.diagnostic.record'), ['audio' => UploadedFile::fake()->create('r.webm', 30, 'audio/webm')])->assertOk();
        $state = $service->diagnosticState($kid);
        $this->assertSame('passage_medium', $state['current_tier']);
        $this->assertNotSame($first, $service->currentActivityId($state));

        // The third item ends the check whatever it scores, and the landing rung is saved.
        $this->post(route('learner.diagnostic.record'), ['audio' => UploadedFile::fake()->create('r.webm', 30, 'audio/webm')])
            ->assertOk()->assertSee('Start Exploring');

        $kid->refresh();
        $this->assertNull($service->diagnosticState($kid));
        $this->assertSame('Proficient', $kid->mastery_level);
        $this->assertSame(5, $kid->reading_rung);
        $this->assertSame('Story Reader', ReadingLevel::stepName($kid));
        $this->assertSame(3, ReadingSession::where('learner_id', $kid->id)->where('session_type', 'Diagnostic')->count());

        // Every reading was scored against that item's own curriculum code.
        $this->assertCount(3, $sent);
        $this->assertStringContainsString('EN3CAT-I-1', $sent[0]);
        $this->assertStringContainsString('name="competency_code"', $sent[0]);
    }

    public function test_a_child_who_cannot_read_yet_finishes_on_letters_after_one_item(): void
    {
        $kid = $this->learner(['reading_stage' => 'starting', 'placement_answers' => null]);
        $this->actingAs($kid, 'learner');
        $this->get(route('learner.diagnostic.passage'))->assertOk()->assertSee('Say the name of each letter.');

        Http::fake(['reading.test/analyze' => Http::response($this->analysis(10))]);
        $this->post(route('learner.diagnostic.record'), ['audio' => UploadedFile::fake()->create('r.webm', 30, 'audio/webm')])
            ->assertOk()->assertSee('Start Exploring');

        $kid->refresh();
        $this->assertSame('Beginning', $kid->mastery_level);
        $this->assertSame(0, $kid->reading_rung);
        $this->assertSame('Letter Explorer', ReadingLevel::stepName($kid));
        $this->assertSame('non', ReadingLevel::band($kid), 'only a child whose check never got past letters is a Non-reader');
        $this->assertSame(1, ReadingSession::where('learner_id', $kid->id)->count());
    }

    // ------------------------------------------------------------- what production looks like before this ships

    public function test_letter_rows_made_before_the_bank_are_reused_and_filled_in_not_duplicated(): void
    {
        // Exactly how the old code made them: no rung, no code, the old title.
        $old = collect(['A' => 's a t p i n', 'B' => 'm d o g c b'])->map(fn ($ref, $label) => Activity::create([
            'created_by_teacher_id' => null, 'purpose' => 'diagnostic', 'activity_type' => 'phonics', 'grade_level' => 'Grade 1',
            'variant_label' => $label, 'reference_text' => $ref, 'competency' => 'foundational_reading',
            'competency_label' => 'Foundational Reading', 'difficulty_tier' => 'Easy', 'bundle_title' => 'Letter Check',
            'title' => 'Letter Check '.$label, 'instructions' => 'Say the name of each letter.', 'passage_text' => strtoupper($ref),
            'word_count' => 6, 'status' => 'Draft',
        ]));

        $ids = app(DiagnosticBank::class)->idsByRung();

        $this->assertSame([$old['A']->id, $old['B']->id], array_slice($ids['letters'], 0, 2), 'the rows already in the database are the ones used');
        $this->assertCount(3, $ids['letters'], 'only the new third set was added');
        $this->assertSame(3, Activity::where('purpose', 'diagnostic')->where('bundle_title', 'Letter Check')->count());
        $this->assertSame('letters', $old['A']->fresh()->check_rung);
        $this->assertSame('EN2PWS-I-2', $old['B']->fresh()->curriculum_code);
    }

    public function test_a_check_that_was_already_running_on_generated_items_still_finishes(): void
    {
        $kid = $this->learner(['reading_stage' => 'independent', 'placement_answers' => null]);
        $this->actingAs($kid, 'learner');
        $service = app(LearnerDiagnosticService::class);

        // What the generator-based version left in the cache: items of its own, no codes of their own.
        $generated = fn (string $tier) => Activity::create([
            'created_by_teacher_id' => null, 'purpose' => 'diagnostic', 'grade_level' => 'Grade 2', 'competency' => 'reading_fluency',
            'competency_label' => 'Reading Fluency', 'activity_type' => 'passage_reading', 'difficulty_tier' => $tier,
            'title' => 'Old '.$tier, 'instructions' => 'Read.', 'passage_text' => 'one two three', 'reference_text' => 'one two three',
            'word_count' => 3, 'status' => 'Draft',
        ])->id;
        $ladder = DiagnosticPlacement::ladder();
        $ids = array_fill_keys($ladder, []);
        $ids['passage_medium'] = [$generated('Medium'), $generated('Medium')];
        \Illuminate\Support\Facades\Cache::put("diagnostic_state:{$kid->id}", [
            'version' => 2, 'rungs' => $ladder, 'activity_ids' => $ids, 'variant_used' => array_fill_keys($ladder, 0),
            'current_tier' => 'passage_medium', 'passages_done' => 0, 'level_before' => null, 'accuracy_history' => [],
            'is_first_ever_reading' => true,
        ], now()->addHours(6));

        Http::fake(['reading.test/analyze' => Http::response($this->analysis(80))]);
        $this->post(route('learner.diagnostic.record'), ['audio' => UploadedFile::fake()->create('r.webm', 30, 'audio/webm')])
            ->assertOk()->assertSee('Start Exploring');

        $kid->refresh();
        $this->assertSame('Proficient', $kid->mastery_level);
        $this->assertSame(5, $kid->reading_rung);
        $this->assertNull($service->diagnosticState($kid));
    }

    public function test_a_check_from_before_the_ladder_existed_finishes_on_its_old_three_tiers(): void
    {
        $kid = $this->learner(['reading_stage' => null, 'placement_answers' => null]);
        $this->actingAs($kid, 'learner');
        $activity = Activity::create([
            'created_by_teacher_id' => null, 'purpose' => 'diagnostic', 'grade_level' => 'Grade 2', 'competency' => 'reading_fluency',
            'competency_label' => 'Reading Fluency', 'activity_type' => 'passage_reading', 'difficulty_tier' => 'Medium',
            'title' => 'Old', 'instructions' => 'Read.', 'passage_text' => 'one two three', 'reference_text' => 'one two three',
            'word_count' => 3, 'status' => 'Draft',
        ]);
        // No `rungs` at all: the three-tier state of the very first version.
        \Illuminate\Support\Facades\Cache::put("diagnostic_state:{$kid->id}", [
            'activity_ids' => ['easy' => [$activity->id], 'medium' => [$activity->id, $activity->id], 'hard' => [$activity->id]],
            'variant_used' => ['easy' => 0, 'medium' => 0, 'hard' => 0], 'current_tier' => 'medium', 'passages_done' => 0,
            'level_before' => null, 'accuracy_history' => [], 'is_first_ever_reading' => true,
        ], now()->addHours(6));

        Http::fake(['reading.test/analyze' => Http::response($this->analysis(80))]);
        $this->post(route('learner.diagnostic.record'), ['audio' => UploadedFile::fake()->create('r.webm', 30, 'audio/webm')])
            ->assertOk()->assertSee('Start Exploring');

        $kid->refresh();
        $this->assertSame('Developing', $kid->mastery_level);
        $this->assertNull($kid->reading_rung, 'there is no rung to save for the old tiers; the step is worked out from the level');
        $this->assertSame('Sentence Reader', ReadingLevel::stepName($kid));
    }

    // ------------------------------------------------------------- the path step follows later level changes

    public function test_a_level_change_keeps_the_saved_reading_path_step_in_line(): void
    {
        $kid = $this->learner(['mastery_level' => 'Beginning', 'reading_rung' => 1]);

        $this->assertSame(3, ReadingLevel::rungAfterLevelChange($kid, 'Developing'), 'up lands on the lowest rung of the new level');
        $this->assertSame(1, ReadingLevel::rungAfterLevelChange($kid, 'Beginning'), 'same level keeps the rung');

        $kid->reading_rung = 4;
        $this->assertSame(2, ReadingLevel::rungAfterLevelChange($kid, 'Beginning'), 'down lands on the highest rung of the new level');
        $this->assertSame(5, ReadingLevel::rungAfterLevelChange($kid, 'Proficient'));

        $old = $this->learner(['mastery_level' => 'Developing', 'reading_rung' => null]);
        $this->assertNull(ReadingLevel::rungAfterLevelChange($old, 'Proficient'), 'a child checked before the ladder keeps working from the level alone');
    }
}
