<?php

namespace Tests\Feature;

// Builds made-up accounts in an in-memory database and saves the real Admin pages, rendered from the real templates, as HTML
// files for the Admin phone preview. It is NOT part of the test suite (it lives under tools/). Run it from the project folder:
//   ADMIN_SHOTS_DIR=<an empty folder> php vendor/bin/phpunit tools/admin-phone-preview/CaptureAdminPhoneTest.php
// then see README.md in this folder for the next steps.

use App\Models\AdminAction;
use App\Models\ParentAccount;
use App\Models\ServiceFailure;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaptureAdminPhoneTest extends TestCase
{
    use RefreshDatabase;

    private function dir(): string
    {
        return getenv('ADMIN_SHOTS_DIR') ?: sys_get_temp_dir().'/admin-shots';
    }

    private function save(string $id, string $html): void
    {
        @mkdir($this->dir(), 0777, true);
        file_put_contents($this->dir().'/'.$id.'.html', $html);
    }

    private function user(string $first, string $last, string $email, string $type, bool $verified = true, string $status = 'Active', int $daysAgo = 3): User
    {
        $u = User::create(['first_name' => $first, 'last_name' => $last, 'email' => $email, 'password' => 'Passw0rd!', 'user_type' => $type, 'contact_number' => '0917 555 01'.random_int(10, 99)]);
        $u->forceFill(['email_verified_at' => $verified ? now()->subDays($daysAgo) : null, 'status' => $status, 'created_at' => now()->subDays($daysAgo), 'updated_at' => now()->subDays($daysAgo)])->save();

        return $u;
    }

    private function teacher(User $u, string $school, string $status, array $grades, ?string $reason = null): Teacher
    {
        return Teacher::create(['user_id' => $u->id, 'school_name' => $school, 'employee_id' => 'T-'.random_int(1000, 9999), 'status' => $status, 'rejection_reason' => $reason, 'grades_handled' => $grades]);
    }

    private function logRow(User $admin, string $action, User $target, ?string $detail, ?string $note, int $hoursAgo): void
    {
        $row = AdminAction::create(['admin_user_id' => $admin->id, 'admin_name' => $admin->first_name.' '.$admin->last_name, 'action' => $action, 'target_user_id' => $target->id,
            'target_name' => $target->first_name.' '.$target->last_name, 'target_role' => $target->user_type, 'detail' => $detail, 'note' => $note]);
        $row->forceFill(['created_at' => now()->subHours($hoursAgo)])->save();
    }

    public function test_capture(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-14 02:30:00', 'UTC')); // Wednesday 10:30 in Manila

        $admin = $this->user('Ada', 'Admin', 'ada.admin@example.com', 'Admin', true, 'Active', 60);

        // Teachers waiting for approval (one has not confirmed the email yet), one rejected, two active.
        $this->teacher($this->user('Marites', 'Gomez', 'marites.gomez@example.com', 'Teacher', true, 'Active', 1), 'Rizal Elementary School', 'Pending', ['Grade 1']);
        $this->teacher($this->user('Joel', 'Dizon', 'joel.dizon@example.com', 'Teacher', false, 'Active', 2), 'San Roque Elementary School', 'Pending', ['Grade 2', 'Grade 3']);
        $this->teacher($this->user('Liza', 'Ramos', 'liza.ramos@example.com', 'Teacher', true, 'Active', 2), 'Sampaguita Elementary School', 'Pending', ['Grade 1', 'Grade 2']);
        $ben = $this->teacher($this->user('Ben', 'Cruz', 'ben.cruz@example.com', 'Teacher', true, 'Active', 9), 'Unknown Learning Center', 'Rejected', ['Grade 1'], 'The school could not be confirmed');
        $elena = $this->teacher($this->user('Elena', 'Reyes', 'elena.reyes@example.com', 'Teacher', true, 'Active', 30), 'Sampaguita Elementary School', 'Active', ['Grade 1', 'Grade 2']);
        $ramon = $this->teacher($this->user('Ramon', 'Santos', 'ramon.santos@example.com', 'Teacher', true, 'Active', 21), 'Rizal Elementary School', 'Active', ['Grade 1']);

        // Parents, one of them closed by the Admin.
        $carla = $this->user('Carla', 'Domingo', 'carla.domingo@example.com', 'Parent', true, 'Active', 28);
        $pedro = $this->user('Pedro', 'Ramos', 'pedro.ramos@example.com', 'Parent', true, 'Active', 14);
        $joy = $this->user('Joy', 'Lim', 'joy.lim@example.com', 'Parent', false, 'Active', 1);
        $mila = $this->user('Mila', 'Tan', 'mila.tan@example.com', 'Parent', true, 'Inactive', 40);
        foreach ([$carla, $pedro, $joy, $mila] as $p) {
            ParentAccount::create(['user_id' => $p->id]);
        }

        // What the Admin did lately.
        $this->logRow($admin, 'activated', $elena->user, 'Sampaguita Elementary School', null, 700);
        $this->logRow($admin, 'activated', $ramon->user, 'Rizal Elementary School', null, 500);
        $this->logRow($admin, 'rejected', $ben->user, 'Unknown Learning Center', 'The school could not be confirmed', 200);
        $this->logRow($admin, 'deactivated', $mila, null, 'Asked to close the account', 72);
        $this->logRow($admin, 'resent', $joy, null, null, 20);
        $this->logRow($admin, 'confirmed_email', $pedro, null, 'I spoke with them', 6);

        // What the platform's services said at the last check, and a failed call.
        $details = ['problems' => 0, 'checks' => [
            ['key' => 'reader', 'who' => 'Reading checker', 'what' => 'Health page', 'state' => 'ok', 'http' => 200, 'ms' => 1400, 'said' => 'ok'],
            ['key' => 'reader', 'who' => 'Reading checker', 'what' => 'A short silent recording', 'state' => 'ok', 'http' => 422, 'ms' => 2100, 'said' => 'Audio is silent'],
            ['key' => 'generator', 'who' => 'Activity generator', 'what' => 'Health page', 'state' => 'asleep', 'http' => 429, 'ms' => 300, 'said' => 'Too Many Requests'],
            ['key' => 'recommender', 'who' => 'Adaptive recommender', 'what' => 'Health page', 'state' => 'ok', 'http' => 200, 'ms' => 900, 'said' => 'ok'],
        ]];
        $check = ServiceFailure::create(['service' => 'check', 'status' => 0, 'what' => 'Service check', 'details' => json_encode($details)]);
        $check->forceFill(['created_at' => now()->subMinutes(12)])->save();
        $failed = ServiceFailure::create(['service' => 'generator', 'status' => 503, 'trail' => '429, 429, 503', 'content_type' => 'text/plain', 'what' => 'No answer from the generator after 3 tries', 'body' => 'Service Unavailable']);
        $failed->forceFill(['created_at' => now()->subHours(5)])->save();

        $this->actingAs($admin);
        $shots = [
            'overview' => route('admin.dashboard'),
            'approvals' => route('admin.approvals'),
            'approvals-rejected' => route('admin.approvals', ['tab' => 'rejected']),
            'accounts' => route('admin.accounts'),
            'accounts-teachers' => route('admin.accounts', ['filter' => 'teachers']),
            'accounts-search' => route('admin.accounts', ['q' => 'ramos']),
            'health' => route('admin.health'),
            'log' => route('admin.log'),
        ];
        foreach ($shots as $id => $url) {
            $r = $this->get($url);
            $r->assertOk();
            $this->save($id, $r->getContent());
        }

        auth()->logout();
        $this->flushSession();
        $r = $this->get(route('login', ['role' => 'admin']));
        $r->assertOk();
        $this->save('login', $r->getContent());

        $this->assertTrue(true);
    }
}
