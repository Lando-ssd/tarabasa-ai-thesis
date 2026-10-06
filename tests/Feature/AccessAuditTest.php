<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityAssignment;
use App\Models\ActivityGeneration;
use App\Models\Learner;
use App\Models\Notification;
use App\Models\OpenRepositoryListing;
use App\Models\ParentAccount;
use App\Models\PromotionRecord;
use App\Models\ReadingSession;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use App\Services\DiagnosticBank;
use App\Support\LearnerCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The system audit, as a test that keeps running. It tries EVERY route as every kind of visitor and
 * checks four things the app must always be true to:
 *
 *  1. Nothing crashes: no route answers 500, whoever asks and whatever they send.
 *  2. Each area is only for its own role (teacher screens for teachers, and so on); everyone else is
 *     sent to the login or refused.
 *  3. Nobody can read or change another person's records (another teacher's classes and activities,
 *     another parent's children, another child's readings), whatever ids they send.
 *  4. Every link and form on the main screens goes to a page that exists.
 *
 * When a new route is added, it is picked up here automatically.
 */
class AccessAuditTest extends TestCase
{
    use RefreshDatabase;

    private array $f = [];

    private string $defaultGuard = 'web';

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        ini_set('memory_limit', '1G'); // hundreds of requests with large bodies
        $this->defaultGuard = config('auth.defaults.guard');

        // Nothing here may reach a real service: any outbound call answers with an empty 200.
        Http::fake();
        config(['services.reading_ai.url' => 'https://reading.test', 'services.activity_ai.url' => 'https://activity.test', 'services.activity_ai.key' => 'k',
            'services.adaptive_recommender.url' => null, 'services.adaptive_recommender.key' => null]);

        $this->buildFixtures();
    }

    // ------------------------------------------------------------------ fixtures

    private function user(string $type, string $email): User
    {
        $u = User::create(['first_name' => ucfirst(strtolower($type)), 'last_name' => 'Tester'.(++$this->seq), 'email' => $email, 'password' => 'Passw0rd!', 'user_type' => $type]);
        $u->forceFill(['email_verified_at' => now()])->save();

        return $u;
    }

    private function teacher(string $email, string $status): array
    {
        $u = $this->user('Teacher', $email);

        return [$u, Teacher::create(['user_id' => $u->id, 'school_name' => 'Rizal ES', 'employee_id' => 'E'.(++$this->seq), 'status' => $status, 'free_generation_credits_remaining' => 2])];
    }

    private function kid(?SchoolClass $c, string $name, array $o = []): Learner
    {
        return Learner::create($o + [
            'learner_code' => LearnerCode::generate(), 'class_id' => $c?->id, 'first_name' => $name, 'last_name' => 'Cruz', 'grade_level' => 'Grade 1',
            'pin' => '1234', 'avatar_id' => 'A', 'mastery_level' => 'Developing', 'reading_rung' => 3,
        ]);
    }

    private function activity(Teacher $t, string $title, string $status = 'Approved'): Activity
    {
        return Activity::create([
            'created_by_teacher_id' => $t->id, 'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'competency_label' => 'Foundational Reading',
            'activity_type' => 'word_reading', 'difficulty_tier' => 'Easy', 'ai_difficulty_tier' => 'Easy', 'title' => $title, 'instructions' => 'Read.',
            'passage_text' => 'cat dog pig hen cow', 'reference_text' => 'cat dog pig hen cow', 'word_count' => 5, 'status' => $status,
        ]);
    }

    private function buildFixtures(): void
    {
        $f = &$this->f;

        $f['admin'] = $this->user('Admin', 'admin@example.com');
        [$f['uA'], $f['tA']] = $this->teacher('ta@example.com', 'Active');
        [$f['uB'], $f['tB']] = $this->teacher('tb@example.com', 'Active');
        [$f['uP'], $f['tP']] = $this->teacher('tp@example.com', 'Pending');

        $f['cA'] = SchoolClass::create(['teacher_id' => $f['tA']->id, 'name' => 'AlphaClass', 'grade_level' => 'Grade 1', 'section' => 'A', 'school_year' => SchoolClass::currentSchoolYear()]);
        $f['cB'] = SchoolClass::create(['teacher_id' => $f['tB']->id, 'name' => 'BravoClass', 'grade_level' => 'Grade 1', 'section' => 'A', 'school_year' => SchoolClass::currentSchoolYear()]);

        $f['lA'] = $this->kid($f['cA'], 'Alma');           // teacher A's learner, parent A's child
        $f['lB'] = $this->kid($f['cB'], 'Bobby');          // teacher B's learner, parent B's child
        $f['lFree'] = $this->kid(null, 'Fresh', ['mastery_level' => null, 'reading_rung' => null]); // has not done the first check

        $f['uPA'] = $this->user('Parent', 'pa@example.com');
        $f['uPB'] = $this->user('Parent', 'pb@example.com');
        $f['pA'] = ParentAccount::create(['user_id' => $f['uPA']->id]);
        $f['pB'] = ParentAccount::create(['user_id' => $f['uPB']->id]);
        $f['pA']->learners()->attach($f['lA']->id, ['relationship' => 'Mother', 'is_creator' => true]);
        $f['pB']->learners()->attach($f['lB']->id, ['relationship' => 'Father', 'is_creator' => true]);

        $f['actA'] = $this->activity($f['tA'], 'A approved');
        $f['actADraft'] = $this->activity($f['tA'], 'A draft', 'Draft');
        $f['actB'] = $this->activity($f['tB'], 'B approved');
        ActivityAssignment::create(['activity_id' => $f['actA']->id, 'class_id' => $f['cA']->id, 'assigned_by_teacher_id' => $f['tA']->id]);
        ActivityAssignment::create(['activity_id' => $f['actB']->id, 'class_id' => $f['cB']->id, 'assigned_by_teacher_id' => $f['tB']->id]);

        $f['listing'] = OpenRepositoryListing::create(['activity_id' => $f['actB']->id, 'teacher_id' => $f['tB']->id, 'price_type' => 'Free', 'price' => 0]);
        $f['gen'] = ActivityGeneration::create(['teacher_id' => $f['tA']->id, 'status' => ActivityGeneration::DONE, 'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'activity_type' => 'word_reading', 'levels' => ['Easy' => 1, 'Medium' => 0, 'Hard' => 0]]);
        $f['notifA'] = Notification::create(['recipient_user_id' => $f['uA']->id, 'learner_id' => $f['lA']->id, 'type' => Notification::TYPE_SESSION_SUMMARY, 'message' => 'A message for teacher A.']);
        $f['notifPA'] = Notification::create(['recipient_user_id' => $f['uPA']->id, 'learner_id' => $f['lA']->id, 'type' => Notification::TYPE_SESSION_SUMMARY, 'message' => 'A message for parent A.']);
        $f['promo'] = PromotionRecord::create(['learner_id' => $f['lB']->id, 'released_by_teacher_id' => $f['tB']->id, 'next_grade' => 'Grade 2', 'status' => 'Pending', 'released_from_class_id' => $f['cB']->id]);

        // Learner A and B have finished the first check; the "fresh" one has not.
        $diag = app(DiagnosticBank::class)->idsByRung()['letters'][0];
        foreach ([$f['lA'], $f['lB']] as $l) {
            ReadingSession::create(['learner_id' => $l->id, 'activity_id' => $diag, 'session_type' => 'Diagnostic', 'initiated_by' => 'Parent', 'accuracy_percent' => 80]);
            ReadingSession::create(['learner_id' => $l->id, 'activity_id' => $f['actA']->id, 'session_type' => 'Practice', 'initiated_by' => 'Teacher', 'accuracy_percent' => 60,
                'word_feedback' => [['reference' => 'hen', 'spoken' => null, 'status' => 'deletion']]]);
        }
    }

    /** Signs in as one kind of visitor (and signs everyone else out). */
    private function as(?string $who): static
    {
        $this->app['auth']->forgetGuards();
        // actingAs(.., 'learner') also makes the learner login the DEFAULT one, which would make
        // the next visitor (say an admin) be signed in as a learner. Put it back.
        $this->app['auth']->shouldUse($this->defaultGuard);
        $this->flushSession();

        $map = ['admin' => 'admin', 'teacherA' => 'uA', 'teacherB' => 'uB', 'teacherPending' => 'uP', 'parentA' => 'uPA', 'parentB' => 'uPB'];

        if ($who === null) {
            return $this;
        }
        if (isset($map[$who])) {
            return $this->actingAs($this->f[$map[$who]]);
        }
        if ($who === 'learnerA') {
            return $this->actingAs($this->f['lA'], 'learner');
        }
        if ($who === 'learnerB') {
            return $this->actingAs($this->f['lB'], 'learner');
        }
        if ($who === 'learnerFresh') {
            return $this->actingAs($this->f['lFree'], 'learner');
        }

        throw new \InvalidArgumentException($who);
    }

    private const ROLES = [null, 'admin', 'teacherA', 'teacherB', 'teacherPending', 'parentA', 'parentB', 'learnerA', 'learnerFresh'];

    /** @return list<LaravelRoute> every route of the app itself (not the framework's own) */
    private function appRoutes(): array
    {
        $skip = '#^(storage/|sanctum/|up$|_ignition|api/|auth/google/|email/verify/)#';

        return array_values(array_filter(Route::getRoutes()->getRoutes(), fn (LaravelRoute $r) => ! preg_match($skip, $r->uri())));
    }

    private function url(LaravelRoute $route): string
    {
        $values = [
            'activity' => $this->f['actA']->id, 'class' => $this->f['cA']->id, 'learner' => $this->f['lA']->id, 'notification' => $this->f['notifA']->id,
            'generation' => $this->f['gen']->id, 'listing' => $this->f['listing']->id, 'record' => $this->f['promo']->id, 'teacher' => $this->f['tA']->id,
            'user' => $this->f['uA']->id, 'game' => 'word-builder', 'token' => 'sometoken', 'id' => 1, 'hash' => 'abc',
        ];

        return '/'.preg_replace_callback('/\{(\w+)\??\}/', fn ($m) => $values[$m[1]] ?? '1', $route->uri());
    }

    /** Which roles may use a route, from where it lives. null means anyone, including a visitor who is not logged in. */
    private function allowedRoles(LaravelRoute $route): ?array
    {
        $uri = $route->uri();

        return match (true) {
            str_starts_with($uri, 'admin/'), str_starts_with($uri, 'internal/') => ['admin'],
            str_starts_with($uri, 'teacher/') => ['teacherA', 'teacherB', 'teacherPending'],
            str_starts_with($uri, 'parent/') => ['parentA', 'parentB'],
            in_array($uri, ['learner/login', 'learner/logout'], true) => null,
            str_starts_with($uri, 'learner/') => ['learnerA', 'learnerFresh'],
            $uri === 'logout' => null,
            default => null,
        };
    }

    // ------------------------------------------------------------------ 1 and 2: nothing crashes, each area is for its own role

    public function test_no_route_crashes_for_any_visitor_and_each_area_only_admits_its_own_role(): void
    {
        $problems = [];

        foreach ($this->appRoutes() as $route) {
            $allowed = $this->allowedRoles($route);
            $methods = array_values(array_diff($route->methods(), ['HEAD']));
            $method = $methods[0];

            foreach (self::ROLES as $role) {
                $this->as($role);
                $res = $this->call($method, $this->url($route), $method === 'GET' ? [] : ['_token' => csrf_token()]);
                $status = $res->getStatusCode();
                $where = "{$method} {$route->uri()} as ".($role ?? 'guest');

                if ($status >= 500) {
                    $problems[] = "{$where} CRASHED ({$status}): ".substr(strip_tags((string) ($res->exception?->getMessage() ?? '')), 0, 160);

                    continue;
                }

                $isAllowed = $allowed === null || in_array($role, $allowed, true);
                if (! $isAllowed) {
                    $location = (string) $res->headers->get('Location');
                    $refused = $status === 403 || $status === 401 || ($status === 302 && str_contains($location, 'login'));
                    if (! $refused) {
                        $problems[] = "{$where} should have been refused but answered {$status}".($location ? " to {$location}" : '');
                    }
                }
            }
        }

        $this->assertSame([], $problems, "Route audit found:\n".implode("\n", $problems));
    }

    // ------------------------------------------------------------------ 3: nobody can touch another person's records

    public function test_a_teacher_cannot_read_or_change_another_teachers_records(): void
    {
        $f = $this->f;
        $before = $this->snapshot();
        $this->as('teacherA');

        // Teacher B's things, asked for by teacher A, by every route that takes an id.
        $readings = [
            "/teacher/activities/{$f['actB']->id}/window",
            "/teacher/analytics?class={$f['cB']->id}",
            "/teacher/analytics?mode=learner&learner_id={$f['lB']->id}",
        ];
        foreach ($readings as $url) {
            $res = $this->get($url);
            $this->assertContains($res->getStatusCode(), [200, 403, 404], $url);
            if ($res->getStatusCode() === 200) {
                $res->assertDontSee('Bobby')->assertDontSee('BravoClass')->assertDontSee('B approved');
            } else {
                $this->assertNotSame(500, $res->getStatusCode());
            }
        }

        $writes = [
            ['POST', "/teacher/activities/{$f['actB']->id}/approve", []],
            ['PUT', "/teacher/activities/{$f['actB']->id}", ['title' => 'Hacked', 'passage_text' => 'hacked']],
            ['POST', "/teacher/activities/{$f['actB']->id}/reject", []],
            ['POST', "/teacher/activities/{$f['actB']->id}/place", ['level' => 'Hard']],
            ['POST', "/teacher/activities/{$f['actB']->id}/restore", []],
            ['POST', "/teacher/activities/{$f['actB']->id}/share", ['price_type' => 'Free']],
            ['POST', "/teacher/activities/{$f['actB']->id}/assign", ['assign_learner_id' => $f['lA']->id]],
            ['POST', "/teacher/activities/{$f['actA']->id}/assign", ['assign_learner_id' => $f['lB']->id]],
            ['POST', "/teacher/activities/{$f['actA']->id}/assign", ['assign_class_id' => $f['cB']->id]],
            ['PUT', "/teacher/classes/{$f['cB']->id}", ['name' => 'Hacked', 'grade_level' => 'Grade 1', 'section' => 'Z']],
            ['POST', "/teacher/classes/{$f['cB']->id}/assign-activity", ['activity_id' => $f['actA']->id]],
            ['POST', "/teacher/classes/{$f['cB']->id}/join-learner", ['learner_code' => substr($f['lA']->learner_code, -5)]],
            ['POST', "/teacher/classes/{$f['cA']->id}/learners/{$f['lB']->id}/move", ['to_class_id' => $f['cA']->id]],
            ['POST', "/teacher/alerts/{$f['lB']->id}/handled", ['kind' => 'support']],
            ['POST', "/teacher/alerts/{$f['lB']->id}/assign", ['kind' => 'support', 'activity_id' => $f['actA']->id]],
            ['POST', "/teacher/alerts/{$f['lA']->id}/assign", ['kind' => 'support', 'activity_id' => $f['actB']->id]],
            ['POST', "/teacher/promotions/{$f['lB']->id}/release", []],
            ['POST', "/notifications/{$f['notifPA']->id}/read", []],
        ];
        foreach ($writes as [$method, $url, $body]) {
            $res = $this->call($method, $url, $body + ['_token' => csrf_token()]);
            $this->assertNotSame(500, $res->getStatusCode(), "{$method} {$url} crashed");
            $this->assertNotContains($res->getStatusCode(), [200, 201, 204], "{$method} {$url} should not succeed");
        }

        $this->assertSame($before, $this->snapshot(), 'a refused request must change nothing');

        // Another teacher's generation request.
        $this->as('teacherB');
        $this->assertContains($this->get("/teacher/activities/generations/{$f['gen']->id}")->getStatusCode(), [403, 404]);
        $this->assertContains($this->post("/teacher/activities/generations/{$f['gen']->id}/dismiss")->getStatusCode(), [403, 404]);
    }

    public function test_a_parent_cannot_see_or_unlock_for_another_parents_child(): void
    {
        $f = $this->f;
        $before = $this->snapshot();
        $this->as('parentA');

        // Unlock and rate on behalf of a child that is not theirs.
        $res = $this->post("/parent/repository/{$f['listing']->id}/unlock", ['learner_id' => $f['lB']->id]);
        $this->assertNotSame(500, $res->getStatusCode());
        $this->assertSame($before, $this->snapshot(), 'unlocking for someone else\'s child changed data');

        // Their own pages never show the other child.
        foreach (['/parent/dashboard', '/parent/children', '/parent/progress', '/parent/repository', '/parent/notifications'] as $url) {
            $this->get($url)->assertOk()->assertDontSee('Bobby');
        }
        $this->get("/parent/progress?learner_id={$f['lB']->id}")->assertOk()->assertDontSee('Bobby');
        $this->get("/parent/dashboard?learner_id={$f['lB']->id}")->assertOk()->assertDontSee('Bobby');
        $this->get("/parent/repository?learner_id={$f['lB']->id}")->assertOk()->assertDontSee('Bobby');

        // A message that belongs to another user.
        $this->post("/notifications/{$f['notifA']->id}/read")->assertForbidden();
        $this->assertFalse((bool) $f['notifA']->fresh()->is_read);
    }

    public function test_a_child_cannot_open_or_record_against_another_childs_activities(): void
    {
        $f = $this->f;
        $before = $this->snapshot();
        $this->as('learnerB');

        // Activity A was given to class A only; child B is in class B.
        $this->get("/learner/activity/{$f['actA']->id}")->assertForbidden();
        $this->post("/learner/activity/{$f['actA']->id}/record", ['audio' => UploadedFile::fake()->create('r.webm', 20, 'audio/webm')])->assertForbidden();
        $this->post("/learner/activity/{$f['actA']->id}/practice", ['audio' => UploadedFile::fake()->create('r.webm', 20, 'audio/webm')])->assertForbidden();
        // Re-reading is only for something this child has already read for real.
        $this->get("/learner/bookshelf/{$f['actB']->id}/reread")->assertForbidden();
        // A draft is never readable by a child.
        $this->get("/learner/activity/{$f['actADraft']->id}")->assertForbidden();

        $this->assertSame($before, $this->snapshot());
    }

    public function test_a_child_who_has_not_done_the_first_check_is_held_there_on_every_learner_screen(): void
    {
        $this->as('learnerFresh');
        foreach (['/learner/dashboard', '/learner/games', '/learner/badges', '/learner/bookshelf', '/learner/activity/start', '/learner/games/balloon-pop'] as $url) {
            $this->get($url)->assertRedirect(route('learner.diagnostic.show'));
        }
    }

    public function test_a_pending_teacher_is_locked_out_of_every_action_that_touches_real_learners(): void
    {
        $f = $this->f;
        $this->as('teacherPending');
        $before = $this->snapshot();

        $actions = [
            ['POST', '/teacher/classes', ['name' => 'X', 'grade_level' => 'Grade 1', 'section' => 'A']],
            ['POST', "/teacher/classes/{$f['cA']->id}/join-learner", ['learner_code' => '12345']],
            ['POST', "/teacher/activities/{$f['actA']->id}/assign", ['assign_learner_id' => $f['lA']->id]],
            ['POST', "/teacher/activities/{$f['actA']->id}/share", ['price_type' => 'Free']],
            ['POST', "/teacher/alerts/{$f['lA']->id}/assign", ['kind' => 'support', 'activity_id' => $f['actA']->id]],
            ['POST', "/teacher/promotions/{$f['lA']->id}/release", []],
        ];
        foreach ($actions as [$method, $url, $body]) {
            $res = $this->call($method, $url, $body + ['_token' => csrf_token()]);
            $this->assertNotSame(500, $res->getStatusCode(), $url);
            $this->assertNotContains($res->getStatusCode(), [200, 201], "{$method} {$url} worked for a pending teacher");
        }
        $this->assertSame($before, $this->snapshot());
    }

    public function test_private_files_and_setup_pages_are_not_reachable_without_a_login_or_a_signature(): void
    {
        $this->as(null);

        // The framework's own file route for the private disk: unsigned means refused.
        $this->assertContains($this->get('/storage/anything.txt')->getStatusCode(), [403, 404]);
        $this->assertContains($this->put('/storage/anything.txt')->getStatusCode(), [403, 404, 405]);

        // The one-time mail setup pages: nothing for anyone who is not the Admin.
        foreach (['/internal/gmail-authorize', '/internal/gmail-authorize/callback?error=%3Cscript%3Ealert(1)%3C/script%3E'] as $url) {
            $res = $this->get($url);
            $this->assertSame(302, $res->getStatusCode(), $url);
            $this->assertStringContainsString('login', (string) $res->headers->get('Location'));
        }
        $this->as('teacherA');
        $this->get('/internal/gmail-authorize/callback?error=%3Cscript%3Ealert(1)%3C/script%3E')->assertForbidden();

        // Even the Admin never gets a page that echoes a script back as HTML.
        $this->as('admin');
        $res = $this->get('/internal/gmail-authorize/callback?error=%3Cscript%3Ealert(1)%3C/script%3E');
        $this->assertStringStartsWith('text/plain', (string) $res->headers->get('Content-Type'));
    }

    // ------------------------------------------------------------------ the API

    public function test_the_mobile_api_needs_a_token_and_never_crashes_on_junk(): void
    {
        foreach ([['GET', '/api/learner/dashboard'], ['GET', '/api/learner/activities'], ['GET', "/api/learner/activities/{$this->f['actA']->id}"],
            ['POST', "/api/learner/activities/{$this->f['actA']->id}/record"], ['GET', '/api/learner/diagnostic/passage'],
            ['POST', '/api/learner/diagnostic/record'], ['POST', '/api/learner/logout'], ['POST', '/api/learner/reading-preferences/font-step']] as [$method, $url]) {
            $res = $this->json($method, $url);
            $this->assertSame(401, $res->getStatusCode(), "{$method} {$url} should need a token");
        }

        foreach ([[], ['learner_code' => ['x'], 'pin' => ['y']], ['learner_code' => str_repeat('A', 5000), 'pin' => '1234'], ['learner_code' => 'TB-00000', 'pin' => '0000'], ['learner_code' => "' OR 1=1 --", 'pin' => '1']] as $body) {
            $res = $this->postJson('/api/learner/login', $body);
            $this->assertLessThan(500, $res->getStatusCode(), json_encode($body));
            $this->assertNotSame(200, $res->getStatusCode());
        }

        // The login never says which half was wrong.
        $wrongCode = $this->postJson('/api/learner/login', ['learner_code' => 'TB26-00000', 'pin' => '1234']);
        $wrongPin = $this->postJson('/api/learner/login', ['learner_code' => $this->f['lA']->learner_code, 'pin' => '9999']);
        $this->assertSame($wrongCode->getStatusCode(), $wrongPin->getStatusCode());
        $this->assertSame($wrongCode->json('message'), $wrongPin->json('message'));
    }

    // ------------------------------------------------------------------ garbage in

    public function test_every_form_survives_garbage(): void
    {
        $junk = [
            'empty' => '',
            'long' => str_repeat('A', 30000),
            'script' => '<script>alert(1)</script>',
            'sql' => "'; DROP TABLE users; --",
            'array' => ['a' => ['b' => 'c']],
            'negative' => -1,
            'huge' => 99999999999999999999,
            'emoji' => "👩‍🏫 \u{0000} \u{202E}",
            'path' => '../../../../etc/passwd',
        ];
        $fields = ['first_name', 'last_name', 'middle_initial', 'middle_name', 'email', 'password', 'password_confirmation', 'current_password', 'contact_number', 'school_name', 'employee_id',
            'name', 'grade_level', 'section', 'group_tag', 'school_year', 'learner_code', 'pin', 'avatar_id', 'reading_stage', 'placement_answers', 'q1', 'q2', 'q3', 'home_language',
            'supports', 'interests', 'relationship', 'activity_id', 'class_id', 'learner_id', 'reading_band', 'levels', 'counts', 'topic', 'grade', 'competency', 'activity_type',
            'price_type', 'price', 'rating', 'comment', 'kind', 'level', 'word', 'token', 'code', 'role', 'status', 'title', 'passage_text', 'instructions', 'assign_learner_id',
            'assign_class_id', 'assign_group_tag', 'short_code', 'target_class_id', 'view', 'form', 'return', 'grades_handled', 'tier', 'to', 'to_class_id', 'level_reached',
            'top_level_cleared', 'had_perfect_round', 'rounds', 'font_step', 'step', 'theme_color', 'confirm'];

        $roleFor = fn (string $uri) => match (true) {
            str_starts_with($uri, 'admin/'), str_starts_with($uri, 'internal/') => 'admin',
            str_starts_with($uri, 'teacher/') => 'teacherA',
            str_starts_with($uri, 'parent/') => 'parentA',
            str_starts_with($uri, 'learner/') && ! in_array($uri, ['learner/login'], true) => 'learnerA',
            str_starts_with($uri, 'notifications/') => 'teacherA',
            str_starts_with($uri, 'profile') => 'teacherA',
            default => null,
        };

        $crashes = [];
        foreach ($this->appRoutes() as $route) {
            $methods = array_values(array_diff($route->methods(), ['HEAD', 'GET']));
            if ($methods === []) {
                continue;
            }
            $method = $methods[0];
            foreach ($junk as $label => $value) {
                $this->as($roleFor($route->uri()));
                $payload = array_fill_keys($fields, $value) + ['_token' => csrf_token()];
                $res = $this->call($method, $this->url($route), $payload);
                if ($res->getStatusCode() >= 500) {
                    $crashes[] = "{$method} {$route->uri()} with {$label}: ".substr((string) ($res->exception?->getMessage() ?? $res->getStatusCode()), 0, 140);
                }
            }
        }

        $this->assertSame([], $crashes, "Forms that crashed on garbage:\n".implode("\n", $crashes));
    }

    public function test_every_screen_survives_junk_in_the_address(): void
    {
        $names = ['class', 'period', 'mode', 'filter', 'learner_id', 'group_tag', 'q', 'code', 'view', 'tab', 'status', 'all', 'fragment', 'stage', 'role', 'generate',
            'summaries', 'page', 'sort', 'level', 'grade', 'type', 'show', 'year', 'school_year', 'child', 'for', 'free', 'rated', 'id'];
        $junk = ['list' => ['a' => ['b']], 'script' => '<script>alert(1)</script>', 'sql' => "' OR 1=1 --", 'long' => str_repeat('Z', 5000), 'negative' => '-1', 'huge' => '99999999999999999999', 'nul' => "a b"];

        $crashes = [];
        foreach ($this->appRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }
            $role = match (true) {
                str_starts_with($route->uri(), 'admin/') || str_starts_with($route->uri(), 'internal/') => 'admin',
                str_starts_with($route->uri(), 'teacher/') => 'teacherA',
                str_starts_with($route->uri(), 'parent/') => 'parentA',
                str_starts_with($route->uri(), 'learner/') && $route->uri() !== 'learner/login' => 'learnerA',
                default => null,
            };
            foreach ($junk as $label => $value) {
                $this->as($role);
                $res = $this->call('GET', $this->url($route), array_fill_keys($names, $value));
                if ($res->getStatusCode() >= 500) {
                    $crashes[] = "GET {$route->uri()} with {$label} in the address: ".substr((string) ($res->exception?->getMessage() ?? $res->getStatusCode()), 0, 140);
                }
            }
        }

        $this->assertSame([], $crashes, "Screens that crashed on junk in the address:
".implode("
", $crashes));
    }

    public function test_accounts_missing_their_profile_never_crash_a_screen(): void
    {
        // A Parent or Teacher user whose profile row is missing (an interrupted sign up, a bad import).
        $ghostParent = $this->user('Parent', 'ghostparent@example.com');
        $ghostTeacher = $this->user('Teacher', 'ghostteacher@example.com');

        $crashes = [];
        foreach ($this->appRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }
            foreach ([['parent/', $ghostParent], ['teacher/', $ghostTeacher]] as [$prefix, $user]) {
                if (! str_starts_with($route->uri(), $prefix)) {
                    continue;
                }
                $this->as(null);
                $res = $this->actingAs($user)->get($this->url($route));
                if ($res->getStatusCode() >= 500) {
                    $crashes[] = "GET {$route->uri()} for an account with no profile: ".substr((string) ($res->exception?->getMessage() ?? ''), 0, 120);
                }
            }
        }

        $this->assertSame([], $crashes, implode("
", $crashes));
    }

    // ------------------------------------------------------------------ 4: no dead links

    public function test_every_link_and_form_on_the_main_screens_goes_to_a_page_that_exists(): void
    {
        $pages = [
            [null, ['/', '/login', '/register/teacher', '/register/parent', '/forgot-password', '/learner/login']],
            ['admin', ['/admin/dashboard']],
            ['teacherA', ['/teacher/dashboard', '/teacher/classes', '/teacher/activities', '/teacher/analytics', '/teacher/analytics?mode=learner', '/teacher/analytics?mode=group', '/teacher/promotions', '/teacher/notifications', '/teacher/profile']],
            ['teacherPending', ['/teacher/dashboard', '/teacher/classes', '/teacher/activities', '/teacher/notifications']],
            ['parentA', ['/parent/dashboard', '/parent/children', '/parent/children/create', '/parent/children/link', '/parent/progress', '/parent/repository', '/parent/notifications', '/parent/profile']],
            ['learnerA', ['/learner/dashboard', '/learner/games', '/learner/games/word-builder', '/learner/games/letter-match', '/learner/games/balloon-pop', '/learner/badges', '/learner/bookshelf', '/learner/activity/start']],
            ['learnerFresh', ['/learner/diagnostic', '/learner/diagnostic/passage']],
        ];

        $dead = [];
        $checked = [];
        foreach ($pages as [$role, $urls]) {
            foreach ($urls as $url) {
                $this->as($role);
                $res = $this->get($url);
                if ($res->getStatusCode() !== 200) {
                    $dead[] = "page {$url} as ".($role ?? 'guest').' answered '.$res->getStatusCode();

                    continue;
                }

                $html = $res->getContent();
                preg_match_all('/<a\s[^>]*href="([^"#][^"]*)"/i', $html, $links);
                foreach (array_unique($links[1]) as $href) {
                    $path = $this->localPath($href);
                    if ($path === null || isset($checked[$role.'|'.$path])) {
                        continue;
                    }
                    $checked[$role.'|'.$path] = true;

                    $this->as($role);
                    $r = $this->get($path);
                    if ($r->getStatusCode() >= 400) {
                        $dead[] = "link {$path} (on {$url}, as ".($role ?? 'guest').') answered '.$r->getStatusCode();
                    }
                }

                // Every form must point at a route that exists for its method.
                preg_match_all('/<form\s[^>]*>/i', $html, $forms);
                foreach ($forms[0] as $tag) {
                    preg_match('/action="([^"]*)"/i', $tag, $a);
                    preg_match('/method="([^"]*)"/i', $tag, $m);
                    $path = isset($a[1]) ? $this->localPath(html_entity_decode($a[1])) : null;
                    if ($path === null || $path === '') {
                        continue;
                    }
                    $method = strtoupper($m[1] ?? 'GET');
                    // A form posted as PUT/DELETE says so in a hidden _method field further in.
                    if ($method === 'POST' && preg_match('/name="_method"\s+value="(PUT|PATCH|DELETE)"/i', substr($html, strpos($html, $tag), 1500), $spoof)) {
                        $method = strtoupper($spoof[1]);
                    }
                    try {
                        Route::getRoutes()->match(\Illuminate\Http\Request::create($path, $method));
                    } catch (\Throwable $e) {
                        $dead[] = "form {$method} {$path} (on {$url}) has no matching route";
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($dead)), "Dead links or forms:\n".implode("\n", array_unique($dead)));
    }

    /** The path and query of an in-app link, or null for anything that leaves the app or is not a page. */
    private function localPath(string $href): ?string
    {
        $href = html_entity_decode($href);
        $base = rtrim(config('app.url'), '/');
        if (str_starts_with($href, 'http://localhost') || str_starts_with($href, $base)) {
            $href = preg_replace('#^https?://[^/]+#', '', $href);
        }
        if (! str_starts_with($href, '/') || str_starts_with($href, '//')) {
            return null;
        }
        // Files and things that are not pages of the app.
        if (preg_match('#^/(css|js|images|icons|animations|sounds|storage|fonts|auth/google|logout)#', $href) || preg_match('#\.(css|js|png|jpg|svg|json|mp3|ico|csv)(\?|$)#', $href)) {
            return null;
        }

        return $href;
    }

    /** The rows that matter, counted, so a request that should have been refused can be shown to have changed nothing. */
    private function snapshot(): array
    {
        return [
            'activities' => DB::table('activities')->orderBy('id')->get(['id', 'title', 'status', 'difficulty_tier', 'shared_to_repository'])->map(fn ($r) => (array) $r)->all(),
            'assignments' => DB::table('activity_assignments')->count(),
            'classes' => DB::table('classes')->orderBy('id')->get(['id', 'name', 'section', 'grade_level'])->map(fn ($r) => (array) $r)->all(),
            'learners' => DB::table('learners')->orderBy('id')->get(['id', 'class_id', 'first_name', 'mastery_level'])->map(fn ($r) => (array) $r)->all(),
            'unlocks' => DB::table('repository_unlocks')->count(),
            'ratings' => DB::table('repository_ratings')->count(),
            'notifications_read' => DB::table('notifications')->where('is_read', true)->count(),
            'promotions' => DB::table('promotion_records')->orderBy('id')->get(['id', 'status', 'claimed_by_teacher_id'])->map(fn ($r) => (array) $r)->all(),
            'alert_actions' => DB::table('teacher_alert_actions')->count(),
            'sessions' => DB::table('reading_sessions')->count(),
            'teachers' => DB::table('teachers')->orderBy('id')->get(['id', 'status', 'free_generation_credits_remaining'])->map(fn ($r) => (array) $r)->all(),
        ];
    }
}
