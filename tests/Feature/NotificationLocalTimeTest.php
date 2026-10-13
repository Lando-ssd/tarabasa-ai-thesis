<?php

namespace Tests\Feature;

use App\Models\Learner;
use App\Models\Notification;
use App\Models\ParentAccount;
use App\Models\User;
use App\Support\LearnerCode;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Alerts are shown in the family's own time (the Philippines), not the server's UTC. A reading at 10:30 in the morning there
 * is 02:30 UTC, and "Today" must follow the local day, not the UTC one.
 */
class NotificationLocalTimeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // the frozen clock must not leak into the next test
        parent::tearDown();
    }

    private function parentWithAlert(Carbon $storedAtUtc): User
    {
        $u = User::create(['first_name' => 'Carla', 'last_name' => 'Domingo', 'email' => 'carla@example.com', 'password' => 'Passw0rd!', 'user_type' => 'Parent']);
        $u->forceFill(['email_verified_at' => now()])->save();
        $parent = ParentAccount::create(['user_id' => $u->id]);
        $kid = Learner::create(['learner_code' => LearnerCode::generate(), 'first_name' => 'Maria', 'last_name' => 'Domingo', 'grade_level' => 'Grade 1', 'pin' => '1234', 'avatar_id' => 'A', 'mastery_level' => 'Developing']);
        $parent->learners()->attach($kid->id, ['relationship' => 'Mother', 'is_creator' => true]);

        $n = Notification::create(['recipient_user_id' => $u->id, 'learner_id' => $kid->id, 'type' => Notification::TYPE_SESSION_SUMMARY, 'message' => 'Maria read "Sounds at the Farm" with 88% accuracy.']);
        $n->timestamp = $storedAtUtc;
        $n->save();

        return $u;
    }

    public function test_an_alert_from_this_morning_in_manila_says_today_and_shows_the_local_hour(): void
    {
        // 10:30 on 14 October in Manila is 02:30 UTC. The alert came at 02:30 Manila time, which is 18:30 UTC the day before.
        Carbon::setTestNow(Carbon::parse('2026-10-14 02:30:00', 'UTC'));
        $parent = $this->parentWithAlert(Carbon::parse('2026-10-13 18:30:00', 'UTC'));

        $page = $this->actingAs($parent)->get(route('parent.notifications.index'))->assertOk();

        $page->assertSee('Today')->assertSee('2:30 AM')->assertDontSee('Yesterday')->assertDontSee('6:30 PM');
    }

    public function test_an_alert_from_yesterday_in_manila_says_yesterday(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-14 02:30:00', 'UTC'));
        // 23:30 on 13 October in Manila is 15:30 UTC the same day.
        $parent = $this->parentWithAlert(Carbon::parse('2026-10-13 15:30:00', 'UTC'));

        $this->actingAs($parent)->get(route('parent.notifications.index'))->assertOk()->assertSee('Yesterday')->assertSee('11:30 PM');
    }
}
