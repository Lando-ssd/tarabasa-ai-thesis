<?php

namespace Tests\Feature;

// Saves the legal pages and the screens that carry the new consent boxes, rendered from the real templates, as HTML files for
// the legal preview. It is NOT part of the test suite (it lives under tools/). Run it from the project folder:
//   LEGAL_SHOTS_DIR=<an empty folder> php vendor/bin/phpunit tools/legal-preview/CaptureLegalPreviewTest.php
// then see README.md in this folder for the next steps. The people in it (Carla Domingo, Maria Perez, Elena Reyes) are made up.

use App\Models\Learner;
use App\Models\ParentAccount;
use App\Models\Teacher;
use App\Models\User;
use App\Support\LearnerCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CaptureLegalPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function dir(): string
    {
        return getenv('LEGAL_SHOTS_DIR') ?: sys_get_temp_dir().'/legal-shots';
    }

    private function shot(string $id, string $url): void
    {
        $response = $this->get($url);
        $response->assertOk();
        @mkdir($this->dir(), 0777, true);
        file_put_contents($this->dir().'/'.$id.'.html', $response->getContent());
    }

    public function test_capture(): void
    {
        // Before signing in: the new pages, the home page, the sign up forms and the two sign in pages.
        foreach ([
            'privacy' => '/privacy', 'terms' => '/terms', 'cookies' => '/cookies', 'refunds' => '/refunds', 'consent' => '/parent-consent',
            'landing' => '/', 'signup-parent' => '/register/parent', 'signup-teacher' => '/register/teacher',
            'login' => '/login', 'learner-login' => '/learner/login',
        ] as $id => $url) {
            $this->shot($id, $url);
        }

        // A parent with one child.
        $pu = User::create(['first_name' => 'Carla', 'last_name' => 'Domingo', 'email' => 'carla.domingo@example.com', 'password' => 'Passw0rd!', 'user_type' => 'Parent']);
        $pu->forceFill(['email_verified_at' => now()])->save();
        ParentAccount::create(['user_id' => $pu->id]);
        $maria = Learner::create([
            'learner_code' => LearnerCode::generate(), 'first_name' => 'Maria', 'last_name' => 'Perez', 'grade_level' => 'Grade 1',
            'pin' => Hash::make('2580'), 'avatar_id' => 'M', 'mastery_level' => 'Beginning',
        ]);
        $pu->parentProfile->learners()->attach($maria->id, ['relationship' => 'Mother', 'is_creator' => true, 'linked_at' => now()]);

        $this->actingAs($pu);
        $this->shot('add-child', '/parent/children/create');
        $this->shot('link-child', '/parent/children/link');
        $this->shot('children', '/parent/children');
        $this->shot('profile-parent', '/parent/profile');

        // A teacher who is already approved.
        $tu = User::create(['first_name' => 'Elena', 'last_name' => 'Reyes', 'email' => 'elena.reyes@example.com', 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        $tu->forceFill(['email_verified_at' => now()])->save();
        Teacher::create(['user_id' => $tu->id, 'school_name' => 'Sampaguita Elementary', 'employee_id' => 'T-1001', 'status' => 'Active', 'grades_handled' => ['Grade 1']]);

        $this->actingAs($tu);
        $this->shot('profile-teacher', '/teacher/profile');
        $this->shot('teacher-activities', '/teacher/activities');
    }
}
