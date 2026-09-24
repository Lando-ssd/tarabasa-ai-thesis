<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Learner;
use App\Models\ParentAccount;
use App\Models\ReadingSession;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Teacher redesign touched two things other roles use: the day streak (Learner) and the way
 * activities are created from the generator's bundle (also used by the first-login check). These
 * make sure the Parent screens and that creation path still behave exactly as before.
 */
class SharedCodeStillWorksTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_day_streak_is_the_same_whether_or_not_the_sessions_were_preloaded(): void
    {
        $kid = Learner::create(['learner_code' => 'TB-11111', 'first_name' => 'Kid', 'last_name' => 'One', 'grade_level' => 'Grade 2', 'pin' => '1234', 'avatar_id' => 'A']);
        $user = User::create(['first_name' => 'T', 'last_name' => 'T', 'email' => 't@example.com', 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        $teacher = Teacher::create(['user_id' => $user->id, 'school_name' => 'S', 'employee_id' => '1', 'status' => 'Active']);
        $activity = Activity::create(['created_by_teacher_id' => $teacher->id, 'grade_level' => 'Grade 2', 'competency' => 'reading_fluency', 'competency_label' => 'Reading Fluency', 'activity_type' => 'sentence_reading', 'difficulty_tier' => 'Easy', 'title' => 'A', 'instructions' => 'x', 'passage_text' => 'x y', 'status' => 'Approved']);

        foreach ([0, 1, 2] as $daysAgo) {
            $s = ReadingSession::create(['learner_id' => $kid->id, 'activity_id' => $activity->id, 'session_type' => 'Practice', 'initiated_by' => 'Teacher', 'accuracy_percent' => 80]);
            $s->timestamp = now()->subDays($daysAgo);
            $s->save();
        }
        // A diagnostic never counts toward the streak.
        $d = ReadingSession::create(['learner_id' => $kid->id, 'activity_id' => $activity->id, 'session_type' => 'Diagnostic', 'initiated_by' => 'Teacher', 'accuracy_percent' => 50]);
        $d->timestamp = now()->subDays(3);
        $d->save();

        $fresh = Learner::find($kid->id);
        $preloaded = Learner::with('readingSessions')->find($kid->id);

        $this->assertSame(3, $fresh->readingDayStreak());
        $this->assertSame(3, $preloaded->readingDayStreak());
        $this->assertSame($fresh->practiceReadingDays()->all(), $preloaded->practiceReadingDays()->all());
    }

    public function test_the_parent_screens_still_render(): void
    {
        $user = User::create(['first_name' => 'Carla', 'last_name' => 'Domingo', 'email' => 'p@example.com', 'password' => 'Passw0rd!', 'user_type' => 'Parent']);
        $user->forceFill(['email_verified_at' => now()])->save();
        $parent = ParentAccount::create(['user_id' => $user->id]);
        $kid = Learner::create(['learner_code' => 'TB-22222', 'first_name' => 'Miguel', 'last_name' => 'Domingo', 'grade_level' => 'Grade 1', 'pin' => '1234', 'avatar_id' => 'A', 'mastery_level' => 'Developing']);
        $parent->learners()->attach($kid->id, ['relationship' => 'Mother', 'is_creator' => true]);
        $this->actingAs($user);

        foreach (['parent.dashboard', 'parent.children.index', 'parent.progress', 'parent.repository.index', 'parent.notifications.index', 'parent.profile.edit'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('parent.dashboard'))->assertSee('Days in a row');
    }

    public function test_activities_made_from_a_bundle_keep_every_variant_unless_told_otherwise(): void
    {
        $user = User::create(['first_name' => 'T', 'last_name' => 'T', 'email' => 't2@example.com', 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        $teacher = Teacher::create(['user_id' => $user->id, 'school_name' => 'S', 'employee_id' => '2', 'status' => 'Active']);
        $variant = fn (string $l) => ['variant_label' => $l, 'title' => "T{$l}", 'instructions' => 'i', 'display_text' => 'cat dog', 'word_count' => 2, 'reading_features' => ['f']];
        $bundle = ['generation_id' => 'g', 'bundle_title' => 'B', 'levels' => ['easy' => [$variant('A'), $variant('B')], 'medium' => [$variant('A'), $variant('B')], 'hard' => [$variant('A'), $variant('B')]]];
        $base = ['created_by_teacher_id' => $teacher->id, 'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'competency_label' => 'Foundational Reading', 'activity_type' => 'word_reading'];

        // The first-login check and any caller that passes no counts: everything is kept, as before.
        $all = Activity::createManyFromBundle($bundle, $base);
        $this->assertSame(6, $all->count());
        $this->assertSame(['Easy', 'Easy', 'Medium', 'Medium', 'Hard', 'Hard'], $all->pluck('difficulty_tier')->all());
        $this->assertSame($all->pluck('difficulty_tier')->all(), $all->pluck('ai_difficulty_tier')->all());

        // Asking for fewer of a level, or none of it, keeps the first ones of what was asked for.
        $some = Activity::createManyFromBundle($bundle, $base, ['Easy' => 0, 'Medium' => 1, 'Hard' => 2]);
        $this->assertSame(['Medium', 'Hard', 'Hard'], $some->pluck('difficulty_tier')->all());
        $this->assertSame(['TA', 'TA', 'TB'], $some->pluck('title')->all());
    }
}
