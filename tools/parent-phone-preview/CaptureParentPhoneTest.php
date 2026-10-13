<?php

namespace Tests\Feature;

// Builds a made-up family in an in-memory database and saves the real Parent pages, rendered from the real templates, as HTML
// files for the Parent phone preview. It is NOT part of the test suite (it lives under tools/). Run it from the project folder:
//   PARENT_SHOTS_DIR=<an empty folder> php vendor/bin/phpunit tools/parent-phone-preview/CaptureParentPhoneTest.php
// then see README.md in this folder for the next steps.

use App\Http\Controllers\LearnerController;
use App\Models\Activity;
use App\Models\Learner;
use App\Models\Notification;
use App\Models\OpenRepositoryListing;
use App\Models\ParentAccount;
use App\Models\PersonalWordBank;
use App\Models\ReadingSession;
use App\Models\RepositoryRating;
use App\Models\RepositoryUnlock;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use App\Services\BadgeService;
use App\Support\LearnerCode;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaptureParentPhoneTest extends TestCase
{
    use RefreshDatabase;

    private function dir(): string
    {
        return getenv('PARENT_SHOTS_DIR') ?: sys_get_temp_dir().'/parent-shots';
    }

    private function save(string $id, string $html): void
    {
        @mkdir($this->dir(), 0777, true);
        file_put_contents($this->dir().'/'.$id.'.html', $html);
    }

    private function activity(Teacher $t, string $title, string $text, string $tier, string $competency, string $label, string $type, ?array $quiz = null): Activity
    {
        return Activity::create([
            'created_by_teacher_id' => $t->id, 'grade_level' => 'Grade 1', 'competency' => $competency, 'competency_label' => $label,
            'activity_type' => $type, 'difficulty_tier' => $tier, 'title' => $title, 'instructions' => 'Read it out loud.',
            'passage_text' => $text, 'reference_text' => $text, 'word_count' => str_word_count($text), 'status' => 'Approved',
        ]);
    }

    private function reading(Learner $kid, Activity $a, float $accuracy, int $daysAgo, string $by = 'Teacher', int $wcpm = 48, ?array $feedback = null, bool $flag = false): void
    {
        $row = ReadingSession::create([
            'learner_id' => $kid->id, 'activity_id' => $a->id, 'session_type' => 'Practice', 'initiated_by' => $by,
            'accuracy_percent' => $accuracy, 'wcpm' => $wcpm, 'level_before' => $kid->mastery_level, 'level_after' => $kid->mastery_level,
            'flagged_needs_attention' => $flag, 'word_feedback' => $feedback,
        ]);
        $row->timestamp = now()->subDays($daysAgo)->subHours(1);
        $row->save();
    }

    private function endings(): array
    {
        $fb = [];
        foreach (['dogs', 'cats', 'pigs', 'hens', 'cows'] as $w) {
            $fb[] = ['reference' => $w, 'spoken' => rtrim($w, 's'), 'status' => 'substitution'];
        }
        foreach (['sun', 'hat', 'big'] as $w) {
            $fb[] = ['reference' => $w, 'spoken' => $w, 'status' => 'correct'];
        }

        return $fb;
    }

    public function test_capture(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-14 02:30:00', 'UTC')); // Wednesday 10:30 in Manila

        // A teacher, a class, and activities she shared to the Open Repository.
        $tu = User::create(['first_name' => 'Elena', 'last_name' => 'Reyes', 'email' => 'elena.reyes@example.com', 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        $teacher = Teacher::create(['user_id' => $tu->id, 'school_name' => 'Sampaguita Elementary', 'employee_id' => 'T-1001', 'status' => 'Active']);
        $class = SchoolClass::create(['teacher_id' => $teacher->id, 'name' => 'Sampaguita', 'grade_level' => 'Grade 1', 'section' => 'A', 'school_year' => SchoolClass::currentSchoolYear()]);

        $farm = $this->activity($teacher, 'Sounds at the Farm', 'cat dog pig hen cow bat rat fox', 'Easy', 'foundational_reading', 'Foundational Reading', 'word_reading');
        $pets = $this->activity($teacher, 'My Pet Cat', 'My cat is on the mat. She is soft and she is fat. She naps in the sun.', 'Easy', 'reading_fluency', 'Reading Fluency', 'sentence_reading');
        $rain = $this->activity($teacher, 'The Big Rain', 'Rain fell on the roof all day. We sat by the window and watched it run down the glass.', 'Medium', 'reading_fluency', 'Reading Fluency', 'passage_reading');
        $reef = $this->activity($teacher, 'A Day at the Coral Reef', 'Mia and Dad went to the coral reef. They saw a tiny green turtle and a school of bright fish.', 'Medium', 'reading_comprehension', 'Reading Comprehension', 'passage_reading');
        $words = $this->activity($teacher, 'Words That End in S', 'dogs cats pigs hens cows bugs rugs mugs', 'Easy', 'foundational_reading', 'Foundational Reading', 'word_reading');
        $blend = $this->activity($teacher, 'Blend It: Frog and Crab', 'frog crab drum trip slim flag grin plum', 'Medium', 'foundational_reading', 'Foundational Reading', 'word_reading');

        $l1 = OpenRepositoryListing::create(['activity_id' => $words->id, 'teacher_id' => $teacher->id, 'price_type' => 'Free', 'price' => 0]);
        $l2 = OpenRepositoryListing::create(['activity_id' => $blend->id, 'teacher_id' => $teacher->id, 'price_type' => 'Paid', 'price' => 25]);
        $l3 = OpenRepositoryListing::create(['activity_id' => $rain->id, 'teacher_id' => $teacher->id, 'price_type' => 'Free', 'price' => 0]);
        $l4 = OpenRepositoryListing::create(['activity_id' => $reef->id, 'teacher_id' => $teacher->id, 'price_type' => 'Paid', 'price' => 40]);

        // The parent and her children.
        $pu = User::create(['first_name' => 'Carla', 'middle_initial' => 'M', 'last_name' => 'Domingo', 'email' => 'carla.domingo@example.com', 'password' => 'Passw0rd!', 'user_type' => 'Parent', 'contact_number' => '0917 555 0142']);
        $pu->forceFill(['email_verified_at' => now()])->save();
        $parent = ParentAccount::create(['user_id' => $pu->id]);

        $av = LearnerController::AVATARS;
        $maria = Learner::create([
            'learner_code' => LearnerCode::generate(), 'class_id' => $class->id, 'first_name' => 'Maria', 'last_name' => 'Domingo', 'grade_level' => 'Grade 1',
            'pin' => '1234', 'avatar_id' => $av[1], 'mastery_level' => 'Developing', 'reading_rung' => 2, 'points' => 142, 'streak' => 9, 'theme_color' => 'pink',
        ]);
        $miguel = Learner::create([
            'learner_code' => LearnerCode::generate(), 'class_id' => null, 'first_name' => 'Miguel', 'last_name' => 'Domingo', 'grade_level' => 'Grade 2',
            'pin' => '1234', 'avatar_id' => $av[0], 'mastery_level' => 'Proficient', 'reading_rung' => 5, 'points' => 310, 'streak' => 14, 'theme_color' => 'blue',
        ]);
        foreach ([$maria, $miguel] as $k) {
            $parent->learners()->attach($k->id, ['relationship' => 'Mother', 'is_creator' => true]);
        }

        // Maria: a first check, then readings on 3 days in a row this week, a tricky one, and a kind of mistake that repeats.
        ReadingSession::create(['learner_id' => $maria->id, 'activity_id' => $farm->id, 'session_type' => 'Diagnostic', 'initiated_by' => 'Teacher', 'accuracy_percent' => 82]);
        $this->reading($maria, $farm, 88, 0, 'Teacher', 52);
        $this->reading($maria, $words, 64, 1, 'Parent', 40, $this->endings(), true);
        $this->reading($maria, $pets, 91, 2, 'Teacher', 56);
        $this->reading($maria, $words, 66, 4, 'Parent', 38, $this->endings());
        $this->reading($maria, $farm, 79, 5, 'Teacher', 46);
        $this->reading($maria, $pets, 84, 7, 'Teacher', 50);
        $this->reading($maria, $words, 61, 8, 'Parent', 36, $this->endings());
        $this->reading($maria, $farm, 72, 9, 'Teacher', 44);
        foreach ([['dogs', 'Struggling'], ['cats', 'Struggling'], ['pigs', 'Improving'], ['hens', 'Struggling'], ['bat', 'Mastered']] as [$w, $st]) {
            PersonalWordBank::create(['learner_id' => $maria->id, 'word' => $w, 'skill_type' => 'Decoding', 'mastery_status' => $st, 'times_drilled' => 2]);
        }
        // Miguel: steady, stronger readings.
        $this->reading($miguel, $rain, 94, 0, 'Teacher', 88);
        $this->reading($miguel, $reef, 92, 2, 'Teacher', 82);
        $this->reading($miguel, $rain, 90, 3, 'Parent', 86);
        $this->reading($miguel, $reef, 87, 6, 'Teacher', 78);

        // The learning path the adaptive engine keeps for each child (the same shape the engine returns).
        $maria->forceFill([
            'subdomain_states' => [
                'Phonics and Word Study' => ['proficiency' => 54.0, 'difficulty' => 'medium', 'confidence' => 0.76, 'attempt_count' => 6],
                'Vocabulary and Word Knowledge' => ['proficiency' => 63.5, 'difficulty' => 'medium', 'confidence' => 0.68, 'attempt_count' => 2],
            ],
            'next_recommended_subdomain' => 'Phonics and Word Study', 'next_recommended_difficulty' => 'medium',
        ])->save();
        $miguel->forceFill([
            'subdomain_states' => [
                'Phonics and Word Study' => ['proficiency' => 88.0, 'difficulty' => 'hard', 'confidence' => 0.84, 'attempt_count' => 5],
                'Comprehending and Analyzing Text' => ['proficiency' => 79.0, 'difficulty' => 'medium', 'confidence' => 0.68, 'attempt_count' => 2],
            ],
            'next_recommended_subdomain' => 'Comprehending and Analyzing Text', 'next_recommended_difficulty' => 'medium',
        ])->save();

        // What the child's Unlock and rating look like on the Activities page.
        RepositoryUnlock::create(['listing_id' => $l1->id, 'parent_id' => $parent->id, 'learner_id' => $maria->id, 'amount_paid' => 0]);
        RepositoryRating::create(['listing_id' => $l1->id, 'parent_id' => $parent->id, 'learner_id' => $maria->id, 'rating' => 5, 'comment' => 'Maria loved the animal words.']);

        // Alerts, written the way the app writes them.
        $mk = function (Learner $k, string $type, string $msg, bool $read, int $hoursAgo) use ($pu) {
            $n = Notification::create(['recipient_user_id' => $pu->id, 'learner_id' => $k->id, 'type' => $type, 'message' => $msg, 'is_read' => $read]);
            $n->timestamp = now()->subHours($hoursAgo);
            $n->save();
        };
        $mk($maria, Notification::TYPE_SESSION_SUMMARY, 'Maria read "Sounds at the Farm" with 88% accuracy.', false, 1);
        $mk($maria, Notification::TYPE_NEEDS_ATTENTION, 'Maria needs attention. Their session on "Words That End in S" scored 64% and was flagged for extra support.', false, 26);
        $mk($maria, Notification::TYPE_SESSION_SUMMARY, 'Maria read "Words That End in S" with 64% accuracy.', false, 26);
        $mk($miguel, Notification::TYPE_SESSION_SUMMARY, 'Miguel read "The Big Rain" with 94% accuracy.', false, 3);
        $mk($maria, Notification::TYPE_SESSION_SUMMARY, 'Maria read "My Pet Cat" with 91% accuracy.', true, 50);
        $mk($miguel, Notification::TYPE_LEVEL_CONFIRMED, "Miguel finished the first reading check and starts as a Story Reader.", true, 24 * 12);

        app(BadgeService::class)->sync($maria);
        app(BadgeService::class)->sync($miguel);

        // ---- the pages, as the parent
        $this->actingAs($pu);
        $shots = [
            'home-maria' => route('parent.dashboard', ['learner_id' => $maria->id]),
            'home-miguel' => route('parent.dashboard', ['learner_id' => $miguel->id]),
            'children' => route('parent.children.index'),
            'progress-maria' => route('parent.progress', ['learner_id' => $maria->id]),
            'progress-miguel' => route('parent.progress', ['learner_id' => $miguel->id]),
            'activities-maria' => route('parent.repository.index', ['learner_id' => $maria->id]),
            'activities-miguel' => route('parent.repository.index', ['learner_id' => $miguel->id]),
            'alerts' => route('parent.notifications.index'),
            'profile' => route('parent.profile.edit'),
            'add-child' => route('parent.children.create'),
            'link-child' => route('parent.children.link'),
        ];
        foreach ($shots as $id => $url) {
            $r = $this->get($url);
            $r->assertOk();
            $this->save($id, $r->getContent());
        }

        // The "all set" page, by really adding a child (it is also the empty-state child for the other screens).
        $r = $this->post(route('parent.children.store'), [
            'first_name' => 'Ana', 'last_name' => 'Domingo', 'grade_level' => 'Grade 1', 'avatar_id' => $av[2], 'theme_color' => 'blue',
            'reading_stage' => 'letters', 'home_language' => 'Filipino', 'q1' => 'yes', 'q2' => 'no', 'q3' => 'unsure', 'pin' => '4321', 'pin_confirmation' => '4321',
        ]);
        $r->assertOk();
        $this->save('all-set', $r->getContent());
        $ana = Learner::where('first_name', 'Ana')->first();
        foreach (['home-ana' => route('parent.dashboard', ['learner_id' => $ana->id]), 'progress-ana' => route('parent.progress', ['learner_id' => $ana->id]), 'activities-ana' => route('parent.repository.index', ['learner_id' => $ana->id]), 'children-after' => route('parent.children.index')] as $id => $url) {
            $r = $this->get($url);
            $r->assertOk();
            $this->save($id, $r->getContent());
        }

        // ---- the pages a visitor sees
        auth()->logout();
        $this->flushSession();
        foreach (['signup' => route('register.parent'), 'login' => route('login', ['role' => 'parent'])] as $id => $url) {
            $r = $this->get($url);
            $r->assertOk();
            $this->save($id, $r->getContent());
        }

        $this->assertTrue(true);
    }
}
