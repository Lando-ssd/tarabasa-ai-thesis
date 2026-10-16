<?php

namespace Database\Seeders;

use App\Models\ParentAccount;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ParentChildService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Practice accounts for trying the website and the phone app WITHOUT a real email address.
 *
 * Why this exists: a new Teacher or Parent must confirm their email before signing in, and on a computer that only writes mail to
 * its log (MAIL_MAILER=log), or when Gmail sending is not set up, that confirmation never arrives. These accounts are created
 * already confirmed (and the Teacher already approved), so they can sign in straight away.
 *
 *   Teacher   demo.teacher@example.com   (Active, 2 free generation credits, handles Grade 1 and Grade 2, has a class)
 *   Parent    demo.parent@example.com    (one child, "Maria Demo", already linked)
 *   Password  Demo1234!                  (both accounts)
 *   Child     the code is printed when the seeder makes the child (or open Children in the Parent account); PIN 1234
 *
 * Run it with:  php artisan db:seed --class=DemoAccountsSeeder
 * It is safe to run again: it resets these two accounts to the values above and only adds a class or a child when there is none.
 *
 * It is NOT part of DatabaseSeeder on purpose, and it refuses to run on a production server unless DEMO_ACCOUNTS_PASSWORD (12 or
 * more characters) is set, because this file is in a public repository and the default password above is public.
 */
class DemoAccountsSeeder extends Seeder
{
    private const LOCAL_PASSWORD = 'Demo1234!';

    public function run(): void
    {
        $password = (string) env('DEMO_ACCOUNTS_PASSWORD', '');

        if (app()->environment('production')) {
            if (strlen($password) < 12) {
                $this->command?->warn('This is a production server and DEMO_ACCOUNTS_PASSWORD (12 or more characters) is not set, so no demo accounts were made.');

                return;
            }
        } elseif ($password === '') {
            $password = self::LOCAL_PASSWORD;
        }

        $teacherUser = $this->user('demo.teacher@example.com', 'Elena', 'Reyes', 'Teacher', $password);
        $teacher = Teacher::firstOrNew(['user_id' => $teacherUser->id]);
        $teacher->fill(['school_name' => $teacher->school_name ?? 'Demo Elementary School', 'employee_id' => $teacher->employee_id ?? 'DEMO-0001']);
        $teacher->status = 'Active';
        $teacher->grades_handled = $teacher->grades_handled ?: ['Grade 1', 'Grade 2'];
        $teacher->free_generation_credits_remaining = $teacher->exists ? $teacher->free_generation_credits_remaining : 2;
        $teacher->save();

        $class = SchoolClass::where('teacher_id', $teacher->id)->where('school_year', SchoolClass::currentSchoolYear())->first()
            ?? SchoolClass::create([
                'teacher_id' => $teacher->id,
                'name' => 'Demo Class',
                'grade_level' => 'Grade 1',
                'section' => 'A',
                'school_year' => SchoolClass::currentSchoolYear(),
            ]);

        $parentUser = $this->user('demo.parent@example.com', 'Carla', 'Domingo', 'Parent', $password);
        $parent = ParentAccount::firstOrCreate(['user_id' => $parentUser->id]);

        if ($parent->learners()->count() === 0) {
            $child = app(ParentChildService::class)->create($parentUser->fresh(), [
                'first_name' => 'Maria', 'middle_name' => null, 'last_name' => 'Demo', 'grade_level' => 'Grade 1',
                'avatar_id' => '🦁', 'theme_color' => 'blue', 'reading_stage' => 'letters',
                'q1' => 'unsure', 'q2' => 'unsure', 'q3' => 'unsure',
                'pin' => '1234',
            ]);
            $child->update(['class_id' => $class->id]);

            $this->command?->info("Demo child made: {$child->first_name} {$child->last_name}, code {$child->learner_code}, PIN 1234.");
        }

        $this->command?->info('Demo accounts ready: demo.teacher@example.com and demo.parent@example.com (already confirmed).');
    }

    private function user(string $email, string $first, string $last, string $type, string $password): User
    {
        $user = User::firstOrNew(['email' => $email]);

        if (! $user->exists) {
            $user->first_name = $first;
            $user->last_name = $last;
        }

        $user->user_type = $type;
        $user->status = 'Active';
        $user->password = Hash::make($password);
        $user->email_verified_at = $user->email_verified_at ?? now();
        $user->save();

        return $user;
    }
}
