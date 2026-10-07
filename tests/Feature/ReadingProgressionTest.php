<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Learner;
use App\Models\ReadingSession;
use App\Services\ReadingProgression;
use App\Support\LearnerCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A child who read six words perfectly, once, became a "Sentence Reader" on the spot: one reading moved a whole level, and a
 * level change put them on the lowest step of the next level. These pin down the replacement: a move needs repeated strong
 * readings of real length from different activities, goes ONE step at a time, and one weak reading never moves a child down.
 */
class ReadingProgressionTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function kid(int $rung, array $o = []): Learner
    {
        return Learner::create($o + [
            'learner_code' => LearnerCode::generate(), 'first_name' => 'Maria', 'last_name' => 'Cruz', 'grade_level' => 'Grade 1', 'pin' => '1234', 'avatar_id' => 'A',
            'mastery_level' => ReadingProgression::levelForRung($rung), 'reading_rung' => $rung, 'rung_changed_at' => now()->subDays(2),
        ]);
    }

    private function activity(int $words = 6, string $type = 'word_reading'): Activity
    {
        return Activity::create([
            'created_by_teacher_id' => null, 'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'competency_label' => 'Foundational Reading', 'activity_type' => $type,
            'difficulty_tier' => 'Easy', 'title' => 'Words '.(++$this->seq), 'instructions' => 'Read', 'passage_text' => trim(str_repeat('cat ', $words)), 'word_count' => $words, 'status' => 'Approved',
        ]);
    }

    private function past(Learner $kid, Activity $a, float $accuracy, int $minutesAgo = 5): void
    {
        $row = ReadingSession::create(['learner_id' => $kid->id, 'activity_id' => $a->id, 'session_type' => 'Practice', 'initiated_by' => 'Teacher', 'accuracy_percent' => $accuracy, 'level_before' => $kid->mastery_level, 'level_after' => $kid->mastery_level]);
        $row->timestamp = now()->subMinutes($minutesAgo);
        $row->save();
    }

    public function test_one_perfect_reading_of_six_words_does_not_make_a_word_reader_a_sentence_reader(): void
    {
        $kid = $this->kid(1);
        $result = app(ReadingProgression::class)->evaluate($kid, $this->activity(6), 100.0);

        $this->assertNull($result['moved']);
        $this->assertSame(1, $result['rungAfter']);
        $this->assertSame('Beginning', $result['levelAfter']);
        $this->assertSame('Word Builder', $result['stepAfter']);
        $this->assertSame(1, $result['have'], 'it counts: one of three strong readings');
        $this->assertSame(3, $result['need']);
    }

    public function test_three_strong_readings_of_different_activities_move_a_child_up_one_step_only(): void
    {
        $kid = $this->kid(1);
        $this->past($kid, $this->activity(6), 100, 30);
        $this->past($kid, $this->activity(7), 95, 20);

        $result = app(ReadingProgression::class)->evaluate($kid, $this->activity(6), 92.0);

        $this->assertSame('up', $result['moved']);
        $this->assertSame(2, $result['rungAfter'], 'one step, never straight to the lowest step of the next level');
        $this->assertSame('Sentence Reader', $result['stepAfter']);
        $this->assertTrue($result['stepChanged']);
        $this->assertSame('Beginning', $result['levelAfter'], 'short sentences still sit inside Beginning');
        $this->assertSame(0, $result['have'], 'the count starts again after a move');
    }

    public function test_the_stored_level_changes_only_when_a_step_crosses_into_the_next_level(): void
    {
        $kid = $this->kid(2);
        $this->past($kid, $this->activity(12), 95, 30);
        $this->past($kid, $this->activity(14), 97, 20);

        $result = app(ReadingProgression::class)->evaluate($kid, $this->activity(11), 93.0);

        $this->assertSame(3, $result['rungAfter']);
        $this->assertSame('Beginning', $result['levelBefore']);
        $this->assertSame('Developing', $result['levelAfter']);
    }

    public function test_the_same_activity_read_three_times_is_not_enough(): void
    {
        $kid = $this->kid(1);
        $same = $this->activity(6);
        $this->past($kid, $same, 100, 30);
        $this->past($kid, $same, 100, 20);

        $result = app(ReadingProgression::class)->evaluate($kid, $same, 100.0);

        $this->assertNull($result['moved'], 'memorising one text is not mastering the step');
        $this->assertSame(3, $result['have']);
    }

    public function test_a_reading_too_short_to_show_the_step_does_not_count(): void
    {
        $kid = $this->kid(1); // a word reader needs at least 6 words to show it
        $this->past($kid, $this->activity(3), 100, 30);
        $this->past($kid, $this->activity(4), 100, 20);

        $result = app(ReadingProgression::class)->evaluate($kid, $this->activity(5), 100.0);

        $this->assertNull($result['moved']);
        $this->assertSame(0, $result['have']);
    }

    public function test_readings_before_the_last_move_do_not_count_again(): void
    {
        $kid = $this->kid(1, ['rung_changed_at' => now()->subMinutes(10)]);
        $this->past($kid, $this->activity(6), 100, 60); // before the move
        $this->past($kid, $this->activity(7), 100, 50); // before the move
        $this->past($kid, $this->activity(8), 100, 5);

        $result = app(ReadingProgression::class)->evaluate($kid, $this->activity(6), 100.0);

        $this->assertNull($result['moved']);
        $this->assertSame(2, $result['have'], 'only the two readings after the move count');
    }

    public function test_one_weak_reading_never_moves_a_child_down_but_two_in_a_row_do(): void
    {
        $kid = $this->kid(3);
        $weak = app(ReadingProgression::class)->evaluate($kid, $this->activity(14), 40.0);
        $this->assertNull($weak['moved'], 'one bad reading is not the child, it may be the day');

        $this->past($kid, $this->activity(15), 45, 10);
        $second = app(ReadingProgression::class)->evaluate($kid, $this->activity(14), 50.0);

        $this->assertSame('down', $second['moved']);
        $this->assertSame(2, $second['rungAfter']);
        $this->assertSame('Beginning', $second['levelAfter']);
    }

    public function test_a_text_far_too_long_for_the_child_failing_says_nothing_about_the_step(): void
    {
        $kid = $this->kid(1); // a word reader: 69 words is far too long
        $this->past($kid, $this->activity(69, 'timed_reading'), 20, 10);

        $result = app(ReadingProgression::class)->evaluate($kid, $this->activity(69, 'timed_reading'), 25.0);

        $this->assertNull($result['moved']);
        $this->assertSame(1, $result['rungAfter']);
    }

    public function test_a_good_reading_between_weak_ones_breaks_the_down_count(): void
    {
        $kid = $this->kid(3);
        $this->past($kid, $this->activity(14), 40, 20);
        $this->past($kid, $this->activity(15), 92, 10);

        $result = app(ReadingProgression::class)->evaluate($kid, $this->activity(14), 45.0);

        $this->assertNull($result['moved'], 'weak, good, weak is not two weak readings in a row');
    }

    public function test_the_ladder_has_a_floor_and_a_ceiling(): void
    {
        $bottom = $this->kid(0);
        $this->past($bottom, $this->activity(5), 30, 10);
        $this->assertSame(0, app(ReadingProgression::class)->evaluate($bottom, $this->activity(5), 20.0)['rungAfter']);

        $top = $this->kid(6);
        $this->past($top, $this->activity(140, 'passage_reading'), 99, 30);
        $this->past($top, $this->activity(150, 'passage_reading'), 99, 20);
        $result = app(ReadingProgression::class)->evaluate($top, $this->activity(160, 'passage_reading'), 99.0);
        $this->assertNull($result['moved']);
        $this->assertNull($result['nextStep']);
    }

    public function test_progress_tells_the_teacher_how_far_a_child_is_from_the_next_step(): void
    {
        $kid = $this->kid(1);
        $this->past($kid, $this->activity(6), 100, 30);
        $this->past($kid, $this->activity(7), 55, 20);

        $progress = app(ReadingProgression::class)->progress($kid);

        $this->assertSame('Word Builder', $progress['step']);
        $this->assertSame('Words', $progress['rungLabel']);
        $this->assertSame('Short sentences', $progress['nextRungLabel']);
        $this->assertSame(1, $progress['have']);
        $this->assertSame(3, $progress['need']);
    }
}
