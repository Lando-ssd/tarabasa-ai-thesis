<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ReadingAiClient;
use App\Support\SpeechNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The speech check must not call a child wrong for reading a word right, and must never call a real
 * mistake right. These check both directions, with answers shaped exactly like the scoring service's.
 */
class SpeechAccuracyTest extends TestCase
{
    use RefreshDatabase;

    /** A scoring-service answer from (reference, spoken, status) triples. */
    private function answer(array $rows): array
    {
        $feedback = [];
        $correct = $reference = $subs = $ins = $del = 0;
        foreach ($rows as [$ref, $spoken, $status]) {
            $feedback[] = ['reference' => $ref, 'spoken' => $spoken, 'status' => $status];
            if ($status !== 'insertion') {
                $reference++;
            }
            $correct += $status === 'correct' ? 1 : 0;
            $subs += $status === 'substitution' ? 1 : 0;
            $ins += $status === 'insertion' ? 1 : 0;
            $del += $status === 'deletion' ? 1 : 0;
        }

        return ['accuracy' => [
            'accuracy_score' => round($correct / max(1, $reference) * 100, 2), 'spoken_word_count' => count($rows),
            'word_feedback' => $feedback, 'substitutions' => $subs, 'insertions' => $ins, 'deletions' => $del,
        ]];
    }

    public function test_a_word_that_sounds_exactly_alike_is_not_a_mistake(): void
    {
        $out = SpeechNormalizer::apply($this->answer([['the', 'the', 'correct'], ['sun', 'son', 'substitution'], ['is', 'is', 'correct'], ['hot', 'hot', 'correct']]));

        $this->assertSame(100.0, (float) $out['accuracy']['accuracy_score']);
        $this->assertSame(75.0, (float) $out['accuracy']['accuracy_score_service'], 'the service number is kept, nothing is hidden');
        $row = $out['accuracy']['word_feedback'][1];
        $this->assertSame('correct', $row['status']);
        $this->assertSame('homophone', $row['accepted']);
        $this->assertSame('son', $row['heard'], 'what was heard is kept');
        $this->assertSame('sun', $row['spoken'], 'the screens show the right word');
        $this->assertSame(0, $out['accuracy']['substitutions']);
        $this->assertSame(1, $out['accuracy']['accepted_count']);
    }

    public function test_spelling_variants_numbers_and_apostrophes_are_accepted(): void
    {
        foreach ([['mangoes', 'mangos', 'variant'], ['mom', 'mum', 'variant'], ['2', 'two', 'number'], ['dogs', 'dogs', null], ["don't", 'dont', 'apostrophe']] as [$ref, $spoken, $why]) {
            $this->assertSame($why === null ? 'same' : $why, SpeechNormalizer::sameWord($ref, $spoken), "{$ref} / {$spoken}");
        }

        $out = SpeechNormalizer::apply($this->answer([['i', 'i', 'correct'], ['ate', 'eight', 'substitution'], ['2', 'two', 'substitution'], ['mangoes', 'mangos', 'substitution']]));
        $this->assertSame(100.0, (float) $out['accuracy']['accuracy_score']);
        $this->assertSame(3, $out['accuracy']['accepted_count']);
    }

    public function test_a_word_written_as_two_is_one_word_read_right_whichever_order(): void
    {
        // "sandcastle" heard as "sand" then "castle": the substitution first, the extra word after.
        $after = SpeechNormalizer::apply($this->answer([['a', 'a', 'correct'], ['sandcastle', 'sand', 'substitution'], [null, 'castle', 'insertion']]));
        $this->assertSame(100.0, (float) $after['accuracy']['accuracy_score']);
        $this->assertSame('split', $after['accuracy']['word_feedback'][1]['accepted']);
        $this->assertTrue($after['accuracy']['word_feedback'][2]['merged'], 'the extra word is marked merged so it is not shown as "also said"');

        // The extra word first, then the rest.
        $before = SpeechNormalizer::apply($this->answer([['a', 'a', 'correct'], [null, 'sand', 'insertion'], ['sandcastle', 'castle', 'substitution']]));
        $this->assertSame(100.0, (float) $before['accuracy']['accuracy_score']);
        $this->assertTrue($before['accuracy']['word_feedback'][1]['merged']);
    }

    public function test_a_real_mistake_is_never_called_right(): void
    {
        // Close in sound is not the same sound: ripe is not right, hit is not hat, and a skipped word stays skipped.
        $rows = [['right', 'ripe', 'substitution'], ['hat', 'hit', 'substitution'], ['pen', 'pin', 'substitution'], ['top', null, 'deletion'], ['cat', 'cat', 'correct']];
        $this->assertNull(SpeechNormalizer::sameWord('right', 'ripe'));
        $this->assertNull(SpeechNormalizer::sameWord('hat', 'hit'));

        $in = $this->answer($rows);
        $out = SpeechNormalizer::apply($in);

        $this->assertSame($in, $out, 'nothing changes when nothing is a same-word case');
        $this->assertSame(20.0, (float) $out['accuracy']['accuracy_score']);

        // An extra word that does not join to the word beside it is a real extra word.
        $extra = SpeechNormalizer::apply($this->answer([['sandcastle', 'sand', 'substitution'], [null, 'banana', 'insertion']]));
        $this->assertSame('substitution', $extra['accuracy']['word_feedback'][0]['status']);
        $this->assertArrayNotHasKey('merged', $extra['accuracy']['word_feedback'][1]);
    }

    public function test_empty_or_odd_answers_never_crash_it(): void
    {
        foreach ([[], ['accuracy' => []], ['accuracy' => ['word_feedback' => []]], ['accuracy' => ['word_feedback' => 'nope']], ['accuracy' => ['word_feedback' => [['status' => 'substitution']]]], ['accuracy' => ['word_feedback' => [['reference' => '', 'spoken' => 'x', 'status' => 'substitution']]]]] as $odd) {
            $this->assertIsArray(SpeechNormalizer::apply($odd));
        }
    }

    public function test_capital_letters_and_spaces_do_not_hide_a_match(): void
    {
        $out = SpeechNormalizer::apply($this->answer([['Sun', ' SON ', 'substitution']]));
        $this->assertSame(100.0, (float) $out['accuracy']['accuracy_score']);
    }

    public function test_every_listed_group_has_at_least_two_words_and_none_is_a_near_miss_pair(): void
    {
        // A group of one does nothing; and the pairs that were tried and rejected must not creep in.
        foreach (['homophones', 'variants'] as $set) {
            foreach (config("reading_words.{$set}") as $group) {
                $this->assertGreaterThanOrEqual(2, count($group), json_encode($group));
            }
        }
        foreach ([['ripe', 'right'], ['hit', 'hat'], ['pin', 'pen'], ['bean', 'been']] as [$a, $b]) {
            $this->assertNull(SpeechNormalizer::sameWord($a, $b), "{$a} / {$b} must stay a mistake");
        }
    }

    public function test_the_correction_is_applied_where_the_service_answer_comes_back(): void
    {
        config(['services.reading_ai.url' => 'https://reader.test']);
        $teacherUser = User::create(['first_name' => 'T', 'last_name' => 'N', 'email' => 't@example.com', 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        $teacher = Teacher::create(['user_id' => $teacherUser->id, 'school_name' => 'S', 'employee_id' => 'E1', 'status' => 'Active', 'free_generation_credits_remaining' => 1]);
        $activity = Activity::create([
            'created_by_teacher_id' => $teacher->id, 'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'competency_label' => 'Foundational Reading',
            'activity_type' => 'word_reading', 'difficulty_tier' => 'Easy', 'title' => 'Sun', 'instructions' => 'Read.', 'passage_text' => 'the sun is hot', 'reference_text' => 'the sun is hot', 'status' => 'Approved',
        ]);

        Http::fake(['reader.test/analyze' => Http::response($this->answer([['the', 'the', 'correct'], ['sun', 'son', 'substitution'], ['is', 'is', 'correct'], ['hot', 'hot', 'correct']]))]);

        $outcome = app(ReadingAiClient::class)->analyze(UploadedFile::fake()->create('recording.webm', 20, 'audio/webm'), $activity);

        $this->assertFalse($outcome['unclear']);
        $this->assertSame(100.0, (float) $outcome['result']['accuracy']['accuracy_score'], 'a child who read it right scores 100, not 75');
        $this->assertSame(75.0, (float) $outcome['result']['accuracy']['accuracy_score_service']);
    }
}
