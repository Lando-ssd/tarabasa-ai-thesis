<?php

namespace Tests\Feature;

use App\Http\Controllers\GameController;
use App\Models\Activity;
use App\Models\ActivityAssignment;
use App\Models\Learner;
use App\Models\ReadingSession;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use App\Services\LearnerAuthService;
use App\Support\ErrorPatterns;
use App\Support\LearnerCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What the child sees follows what their own readings show: the story list and the games lean towards
 * the kind of mistake they keep making, say what is being practised in kind words, and stay out of the
 * way when there is no clear pattern.
 */
class AdaptivePatternTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function teacher(): Teacher
    {
        $n = ++$this->seq;
        $u = User::create(['first_name' => 'T', 'last_name' => "N{$n}", 'email' => "ap{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);

        return Teacher::create(['user_id' => $u->id, 'school_name' => 'S', 'employee_id' => "E{$n}", 'status' => 'Active']);
    }

    private function activity(Teacher $t, string $title, string $text): Activity
    {
        return Activity::create([
            'created_by_teacher_id' => $t->id, 'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'competency_label' => 'Foundational Reading',
            'activity_type' => 'word_reading', 'difficulty_tier' => 'Easy', 'title' => $title, 'instructions' => 'Read.',
            'passage_text' => $text, 'reference_text' => $text, 'word_count' => str_word_count($text), 'status' => 'Approved',
        ]);
    }

    /** A child in a class that has been given the activities. */
    private function childWith(Teacher $t, Activity ...$activities): Learner
    {
        $class = SchoolClass::create(['teacher_id' => $t->id, 'name' => 'Rizal', 'grade_level' => 'Grade 1', 'section' => 'A', 'school_year' => SchoolClass::currentSchoolYear()]);
        $kid = Learner::create([
            'learner_code' => LearnerCode::generate(), 'class_id' => $class->id, 'first_name' => 'Kid', 'last_name' => 'Cruz', 'grade_level' => 'Grade 1',
            'pin' => '1234', 'avatar_id' => 'A', 'mastery_level' => 'Developing', 'reading_rung' => 3,
        ]);

        foreach ($activities as $a) {
            ActivityAssignment::create(['activity_id' => $a->id, 'class_id' => $class->id, 'assigned_by_teacher_id' => $t->id]);
        }

        return $kid;
    }

    /** Readings whose misses are all one kind: an ending left off (dogs read as dog). */
    private function endingMistakes(Learner $kid, Activity $a, int $readings = 3): void
    {
        $feedback = [];
        foreach (['dogs', 'cats', 'pigs', 'hens'] as $word) {
            $feedback[] = ['reference' => $word, 'spoken' => rtrim($word, 's'), 'status' => 'substitution'];
        }

        for ($i = 0; $i < $readings; $i++) {
            $row = ReadingSession::create([
                'learner_id' => $kid->id, 'activity_id' => $a->id, 'session_type' => 'Practice', 'initiated_by' => 'Teacher',
                'accuracy_percent' => 60, 'word_feedback' => $feedback,
            ]);
            $row->timestamp = now()->subDays($i + 1);
            $row->save();
        }
    }

    public function test_a_clear_pattern_moves_the_best_fitting_story_up_and_says_what_it_practises_kindly(): void
    {
        $t = $this->teacher();
        $plain = $this->activity($t, 'Plain Words', 'cat dog pig hen cow');
        $endings = $this->activity($t, 'Animals Running', 'dogs running cats jumping pigs digging hens walking');
        $kid = $this->childWith($t, $plain, $endings);
        $this->endingMistakes($kid, $plain);

        $options = app(LearnerAuthService::class)->findActivityOptions($kid);

        $this->assertSame('Animals Running', $options->first()['activity']->title, 'the story that practises word endings comes first');
        $this->assertSame('Practice word endings', $options->first()['practice']);
        $this->assertArrayNotHasKey('practice', $options->last(), 'only the story that fits is tagged');
        $this->assertSame(2, $options->count(), 'nothing the teacher gave is hidden');
        $this->assertStringNotContainsStringIgnoringCase('mistake', $options->first()['practice']);
    }

    public function test_with_no_clear_pattern_the_list_is_left_exactly_as_it_was(): void
    {
        $t = $this->teacher();
        $a = $this->activity($t, 'A Words', 'cat dog pig hen cow');
        $b = $this->activity($t, 'B Words', 'dogs running cats jumping');
        $kid = $this->childWith($t, $a, $b);

        // New child: no readings at all.
        $none = app(LearnerAuthService::class)->findActivityOptions($kid);
        $this->assertTrue($none->every(fn ($o) => ! isset($o['practice'])));

        // Two readings with plenty of misses is still too little evidence for a pattern.
        $this->endingMistakes($kid, $a, 2);
        $few = app(LearnerAuthService::class)->findActivityOptions($kid);
        $this->assertTrue($few->every(fn ($o) => ! isset($o['practice'])), 'a pattern needs at least three readings');
    }

    public function test_the_recommenders_own_pick_keeps_its_place_and_label(): void
    {
        $t = $this->teacher();
        $picked = $this->activity($t, 'Picked One', 'cat dog pig hen cow');
        $plain = $this->activity($t, 'Plain Words', 'sun map bed fox');
        $endings = $this->activity($t, 'Animals Running', 'dogs running cats jumping pigs digging hens walking');
        $kid = $this->childWith($t, $picked, $plain, $endings);
        $this->endingMistakes($kid, $plain);

        $method = new \ReflectionMethod(LearnerAuthService::class, 'applyPatternPractice');
        $options = collect([
            ['activity' => $picked, 'source' => 'Picked just for you'],
            ['activity' => $plain, 'source' => 'Assigned by your Teacher'],
            ['activity' => $endings, 'source' => 'Assigned by your Teacher'],
        ]);

        $out = $method->invoke(app(LearnerAuthService::class), $kid, $options);

        $this->assertSame(['Picked One', 'Animals Running', 'Plain Words'], $out->map(fn ($o) => $o['activity']->title)->all());
        $this->assertSame('Picked just for you', $out->first()['source']);
        $this->assertArrayNotHasKey('practice', $out->first());
    }

    public function test_a_single_option_is_never_reshuffled_or_tagged(): void
    {
        $t = $this->teacher();
        $only = $this->activity($t, 'Only One', 'dogs running cats jumping');
        $kid = $this->childWith($t, $only);
        $this->endingMistakes($kid, $only);

        $options = app(LearnerAuthService::class)->findActivityOptions($kid);

        $this->assertCount(1, $options);
        $this->assertArrayNotHasKey('practice', $options->first());
    }

    public function test_the_picker_page_and_the_mobile_list_show_what_is_practised(): void
    {
        $t = $this->teacher();
        $plain = $this->activity($t, 'Plain Words', 'cat dog pig hen cow');
        $endings = $this->activity($t, 'Animals Running', 'dogs running cats jumping pigs digging hens walking');
        $kid = $this->childWith($t, $plain, $endings);
        $this->endingMistakes($kid, $plain);
        // Past the first reading check, so the picker is reachable.
        ReadingSession::create(['learner_id' => $kid->id, 'activity_id' => $plain->id, 'session_type' => 'Diagnostic', 'initiated_by' => 'Teacher', 'accuracy_percent' => 80]);

        $this->actingAs($kid, 'learner');
        $this->get(route('learner.activity.find'))->assertOk()->assertSee('Practice word endings')->assertSee('Animals Running');

        $token = $kid->createToken('test')->plainTextToken;
        $json = $this->withToken($token)->getJson('/api/learner/activities')->assertOk()->json('options');
        $this->assertSame('Practice word endings', $json[0]['practice']);
        $this->assertNull($json[1]['practice']);
    }

    // ------------------------------------------------------------- the parent's view

    private function parentOf(Learner $kid): User
    {
        $u = User::create(['first_name' => 'Pat', 'last_name' => 'Cruz', 'email' => 'pp'.(++$this->seq).'@example.com', 'password' => 'Passw0rd!', 'user_type' => 'Parent']);
        $u->forceFill(['email_verified_at' => now()])->save();
        $parent = \App\Models\ParentAccount::create(['user_id' => $u->id]);
        $parent->learners()->attach($kid->id, ['relationship' => 'Mother', 'is_creator' => true]);

        return $u;
    }

    public function test_a_parent_sees_the_level_in_plain_words_and_one_thing_to_try_at_home(): void
    {
        $t = $this->teacher();
        $a = $this->activity($t, 'Plain', 'cat dog pig hen cow');
        $kid = $this->childWith($t, $a);
        $kid->forceFill(['mastery_level' => 'Developing', 'first_name' => 'Mara'])->save();
        $this->endingMistakes($kid, $a, 4);

        $this->actingAs($this->parentOf($kid))
            ->get(route('parent.progress'))->assertOk()
            ->assertSee('Reading level')->assertSee('Instructional level')->assertSee('Mara reads well with some help')
            ->assertSee('Phil-IRI')
            ->assertSee('Practise at home')->assertSee('Mara leaves off or changes the ends of words')
            ->assertSee('Try this:')->assertSee('Point to the last letters')
            ->assertSee('EN2PWS-II-2')->assertSee('Words to practise together')
            ->assertDontSee('Frustration')->assertDontSee('mistake');
    }

    public function test_the_home_card_says_so_when_there_is_no_pattern_instead_of_inventing_one(): void
    {
        $t = $this->teacher();
        $a = $this->activity($t, 'Plain', 'cat dog pig hen cow');
        $kid = $this->childWith($t, $a);
        $kid->forceFill(['first_name' => 'Mara'])->save();

        $this->actingAs($this->parentOf($kid))
            ->get(route('parent.progress'))->assertOk()
            ->assertSee('Nothing specific to practise yet')->assertDontSee('Try this:');
    }

    public function test_a_parent_only_sees_their_own_childs_pattern(): void
    {
        $t = $this->teacher();
        $a = $this->activity($t, 'Plain', 'cat dog pig hen cow');
        $mine = $this->childWith($t, $a);
        $other = $this->childWith($t, $a);
        $other->forceFill(['first_name' => 'Zoe'])->save();
        $this->endingMistakes($other, $a, 4);

        $this->actingAs($this->parentOf($mine))
            ->get(route('parent.progress', ['learner_id' => $other->id]))->assertOk()
            ->assertDontSee('Zoe')->assertSee('Nothing specific to practise yet');
    }

    // ------------------------------------------------------------- the parent's activity browser

    private function listed(Teacher $t, Activity $a): \App\Models\OpenRepositoryListing
    {
        return \App\Models\OpenRepositoryListing::create(['activity_id' => $a->id, 'teacher_id' => $t->id, 'price_type' => 'Free', 'price' => 0]);
    }

    public function test_the_activity_browser_puts_the_best_matches_for_the_child_first_and_says_why(): void
    {
        $t = $this->teacher();
        $own = $this->activity($t, 'Plain', 'cat dog pig hen cow');
        $kid = $this->childWith($t, $own);
        $kid->forceFill(['mastery_level' => 'Developing', 'first_name' => 'Mara'])->save(); // wants Medium
        $this->endingMistakes($kid, $own, 4);

        $hard = $this->activity($t, 'Hard Stuff', 'dogs running cats jumping');
        $hard->update(['difficulty_tier' => 'Hard']);
        $medium = $this->activity($t, 'Medium Animals', 'dogs running cats jumping pigs digging');
        $medium->update(['difficulty_tier' => 'Medium']);
        $easyPlain = $this->activity($t, 'Easy Plain', 'sun map bed fox');
        // Oldest listing is the best match, so it is not first just for being newest.
        $this->listed($t, $medium);
        $this->listed($t, $hard);
        $this->listed($t, $easyPlain);

        $page = $this->actingAs($this->parentOf($kid))->get(route('parent.repository.index'))->assertOk();
        $page->assertSeeInOrder(['Medium Animals', 'Hard Stuff'])
            ->assertSee("Matches Mara's level", false)->assertSee('Practises word endings')
            ->assertSee('Activities that suit Mara are shown first')->assertSee('Only matches for Mara');

        // "Only matches": the plain easy one is left out, the others stay.
        $only = $this->get(route('parent.repository.index', ['best' => '1']))->assertOk();
        $only->assertSee('Medium Animals')->assertDontSee('Easy Plain')->assertSee('Showing only matches for Mara');
    }

    public function test_a_child_who_has_not_been_checked_gets_the_plain_list_with_no_claims(): void
    {
        $t = $this->teacher();
        $own = $this->activity($t, 'Plain', 'cat dog pig hen cow');
        $kid = $this->childWith($t, $own);
        $kid->forceFill(['mastery_level' => null, 'reading_rung' => null, 'first_name' => 'Mara'])->save();
        $this->listed($t, $this->activity($t, 'Some Activity', 'sun map bed fox'));

        $this->actingAs($this->parentOf($kid))->get(route('parent.repository.index'))->assertOk()
            ->assertSee('Some Activity')->assertDontSee('Matches Mara')->assertDontSee('Only matches for')->assertDontSee('shown first');
    }

    // ------------------------------------------------------------- the games

    public function test_games_favour_words_that_show_the_childs_pattern_when_filling_a_round(): void
    {
        $t = $this->teacher();
        $a = $this->activity($t, 'Plain', 'cat dog pig hen cow');
        $kid = $this->childWith($t, $a);

        // Letters dropped from a blend (frog read as fog).
        $feedback = array_map(fn ($w) => ['reference' => $w[0], 'spoken' => $w[1], 'status' => 'substitution'], [['frog', 'fog'], ['drum', 'dum'], ['flag', 'fag'], ['stop', 'sop'], ['swim', 'sim'], ['spin', 'sin']]);
        for ($i = 0; $i < 3; $i++) {
            $row = ReadingSession::create(['learner_id' => $kid->id, 'activity_id' => $a->id, 'session_type' => 'Practice', 'initiated_by' => 'Teacher', 'accuracy_percent' => 50, 'word_feedback' => $feedback]);
            $row->timestamp = now()->subDays($i + 1);
            $row->save();
        }
        $this->assertSame('blends', ErrorPatterns::mainPattern(ErrorPatterns::forLearner($kid)));

        $levels = (new \ReflectionMethod(GameController::class, 'wordLevels'))->invoke(app(GameController::class), $kid);

        foreach ([1, 2, 3] as $level) {
            $this->assertArrayHasKey('favoured', $levels[$level]);
            foreach ($levels[$level]['favoured'] as $word) {
                $this->assertContains($word, $levels[$level]['fallback'], 'favoured words come from the level list');
                $this->assertTrue(ErrorPatterns::practisesPattern($word, 'blends'), $word);
            }
        }
        $this->assertContains('frog', $levels[2]['favoured']);
        $this->assertNotContains('cat', $levels[2]['favoured']);
        $this->assertSame([], $levels[1]['favoured'], 'level 1 is plain three letter words: no blends to favour');
        // The child's own missed words come first whatever the pattern: that is the word bank, separate from this.
        $this->assertArrayHasKey('struggling', $levels[2]);
    }

    public function test_a_pattern_the_lists_cannot_show_leaves_the_round_as_it_was(): void
    {
        $t = $this->teacher();
        $a = $this->activity($t, 'Plain', 'cat dog pig hen cow');
        $kid = $this->childWith($t, $a);
        $this->endingMistakes($kid, $a);

        $this->assertSame('endings', ErrorPatterns::mainPattern(ErrorPatterns::forLearner($kid)));
        $levels = (new \ReflectionMethod(GameController::class, 'wordLevels'))->invoke(app(GameController::class), $kid);

        // The game lists are plain single words with no endings to favour: nothing is pretended.
        foreach ([1, 2, 3] as $level) {
            $this->assertSame([], $levels[$level]['favoured']);
        }
    }

    public function test_without_a_pattern_or_for_a_pattern_with_no_spelling_feature_no_words_are_singled_out(): void
    {
        $t = $this->teacher();
        $a = $this->activity($t, 'Plain', 'cat dog pig hen cow');
        $kid = $this->childWith($t, $a);
        $method = new \ReflectionMethod(GameController::class, 'wordLevels');

        $none = $method->invoke(app(GameController::class), $kid);
        $this->assertSame([], $none[1]['favoured']);

        // Middle-sound mistakes (hat read as hit) have no spelling feature to match on.
        $feedback = array_map(fn ($w) => ['reference' => $w[0], 'spoken' => $w[1], 'status' => 'substitution'], [['hat', 'hit'], ['pen', 'pin'], ['top', 'tip'], ['bag', 'big'], ['cut', 'cot'], ['sad', 'sod']]);
        for ($i = 0; $i < 3; $i++) {
            $row = ReadingSession::create(['learner_id' => $kid->id, 'activity_id' => $a->id, 'session_type' => 'Practice', 'initiated_by' => 'Teacher', 'accuracy_percent' => 50, 'word_feedback' => $feedback]);
            $row->timestamp = now()->subDays($i + 1);
            $row->save();
        }

        $this->assertSame('vowels', ErrorPatterns::mainPattern(ErrorPatterns::forLearner($kid)));
        $vowels = $method->invoke(app(GameController::class), $kid);
        $this->assertSame([], $vowels[1]['favoured']);
        $this->assertSame([], $vowels[3]['favoured']);
    }

    public function test_the_first_check_is_not_counted_as_practice_when_finding_a_pattern(): void
    {
        $t = $this->teacher();
        $a = $this->activity($t, 'Plain', 'cat dog pig hen cow');
        $kid = $this->childWith($t, $a);

        $feedback = array_map(fn ($w) => ['reference' => $w, 'spoken' => rtrim($w, 's'), 'status' => 'substitution'], ['dogs', 'cats', 'pigs', 'hens']);
        for ($i = 0; $i < 4; $i++) {
            ReadingSession::create(['learner_id' => $kid->id, 'activity_id' => $a->id, 'session_type' => 'Diagnostic', 'initiated_by' => 'Teacher', 'accuracy_percent' => 40, 'word_feedback' => $feedback]);
        }

        $this->assertNull(ErrorPatterns::mainPattern(ErrorPatterns::forLearner($kid)), 'placement items are not ongoing practice');
    }

    public function test_practisesPattern_knows_blends_endings_and_small_words_and_nothing_else(): void
    {
        $this->assertTrue(ErrorPatterns::practisesPattern('frog', 'blends'));
        $this->assertFalse(ErrorPatterns::practisesPattern('cat', 'blends'));
        $this->assertTrue(ErrorPatterns::practisesPattern('jumping', 'endings'));
        $this->assertFalse(ErrorPatterns::practisesPattern('cat', 'endings'));
        $this->assertTrue(ErrorPatterns::practisesPattern('the', 'small_words'));
        $this->assertFalse(ErrorPatterns::practisesPattern('elephant', 'small_words'));
        $this->assertFalse(ErrorPatterns::practisesPattern('frog', 'vowels'));
        $this->assertFalse(ErrorPatterns::practisesPattern('frog', null));
        $this->assertSame(['frog', 'ship', 'cat'], ErrorPatterns::orderWords(['cat', 'frog', 'ship'], 'blends'));
        $this->assertSame(['cat', 'frog'], ErrorPatterns::orderWords(['cat', 'frog'], null), 'no pattern, no reordering');
    }
}
