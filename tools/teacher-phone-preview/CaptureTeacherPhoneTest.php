<?php

namespace Tests\Feature;

// Builds a made-up class in an in-memory database and saves the real Teacher pages, rendered from the real templates, as HTML
// files for the Teacher phone preview. It is NOT part of the test suite (it lives under tools/). Run it from the project folder:
//   TEACHER_SHOTS_DIR=<an empty folder> php vendor/bin/phpunit tools/teacher-phone-preview/CaptureTeacherPhoneTest.php
// then see README.md in this folder for the next steps.

use App\Models\Activity;
use App\Models\ActivityAssignment;
use App\Models\Learner;
use App\Models\Notification;
use App\Models\OpenRepositoryListing;
use App\Models\PersonalWordBank;
use App\Models\PromotionRecord;
use App\Models\ReadingSession;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use App\Support\LearnerCode;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaptureTeacherPhoneTest extends TestCase
{
    use RefreshDatabase;

    private function dir(): string
    {
        return getenv('TEACHER_SHOTS_DIR') ?: sys_get_temp_dir().'/teacher-shots';
    }

    private function save(string $id, string $html): void
    {
        @mkdir($this->dir(), 0777, true);
        file_put_contents($this->dir().'/'.$id.'.html', $html);
    }

    private function teacher(string $first, string $last, string $email, string $status, array $grades): array
    {
        $u = User::create(['first_name' => $first, 'last_name' => $last, 'email' => $email, 'password' => 'Passw0rd!', 'user_type' => 'Teacher', 'contact_number' => '0917 555 0188']);
        $u->forceFill(['email_verified_at' => now()])->save();
        $t = Teacher::create(['user_id' => $u->id, 'school_name' => 'Sampaguita Elementary School', 'employee_id' => 'T-'.random_int(1000, 9999), 'status' => $status, 'grades_handled' => $grades]);

        return [$u, $t];
    }

    private function activity(Teacher $t, string $title, string $text, string $tier, string $competency, string $label, string $type, string $status = 'Approved', string $grade = 'Grade 1'): Activity
    {
        return Activity::create([
            'created_by_teacher_id' => $t->id, 'grade_level' => $grade, 'competency' => $competency, 'competency_label' => $label,
            'activity_type' => $type, 'difficulty_tier' => $tier, 'ai_difficulty_tier' => $tier, 'title' => $title, 'instructions' => 'Read it out loud.',
            'passage_text' => $text, 'reference_text' => $text, 'word_count' => str_word_count($text), 'status' => $status,
            'target_skills' => ['Read common words'], 'reading_features' => ['short words'],
        ]);
    }

    private function kid(SchoolClass $c, string $first, int $rung, string $level, array $o = []): Learner
    {
        return Learner::create($o + [
            'learner_code' => LearnerCode::generate(), 'class_id' => $c->id, 'first_name' => $first, 'last_name' => 'Domingo', 'grade_level' => $c->grade_level,
            'pin' => '1234', 'avatar_id' => 'A', 'mastery_level' => $level, 'reading_rung' => $rung, 'points' => random_int(40, 300),
        ]);
    }

    private function reading(Learner $kid, Activity $a, float $accuracy, int $daysAgo, string $by = 'Teacher', ?array $feedback = null, ?int $before = null, ?int $after = null): void
    {
        $row = ReadingSession::create([
            'learner_id' => $kid->id, 'activity_id' => $a->id, 'session_type' => 'Practice', 'initiated_by' => $by,
            'accuracy_percent' => $accuracy, 'wcpm' => 48, 'level_before' => $kid->mastery_level, 'level_after' => $kid->mastery_level,
            'flagged_needs_attention' => $accuracy < 70, 'word_feedback' => $feedback, 'rung_before' => $before, 'rung_after' => $after,
        ]);
        $row->timestamp = now()->subDays($daysAgo)->subHours(1);
        $row->save();
    }

    private function missed(string ...$words): array
    {
        return array_map(fn ($w) => ['reference' => $w, 'spoken' => null, 'status' => 'deletion'], $words);
    }

    public function test_capture(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-14 02:30:00', 'UTC')); // Wednesday 10:30 in Manila

        // ---- Ms Elena Reyes, an active teacher with two classes
        [$tu, $teacher] = $this->teacher('Elena', 'Reyes', 'elena.reyes@example.com', 'Active', ['Grade 1', 'Grade 2']);
        $teacher->forceFill(['free_generation_credits_remaining' => 1])->save();
        $year = SchoolClass::currentSchoolYear();
        $g1 = SchoolClass::create(['teacher_id' => $teacher->id, 'name' => 'Sampaguita', 'grade_level' => 'Grade 1', 'section' => 'A', 'group_tag' => 'phonics-focus', 'school_year' => $year]);
        $g2 = SchoolClass::create(['teacher_id' => $teacher->id, 'name' => 'Dahlia', 'grade_level' => 'Grade 2', 'section' => 'B', 'school_year' => $year]);

        // Her activities: some waiting for review, some placed in a level, one rejected, two shared.
        $farm = $this->activity($teacher, 'Sounds at the Farm', 'cat dog pig hen cow bat rat fox', 'Easy', 'foundational_reading', 'Foundational Reading', 'word_reading');
        $ends = $this->activity($teacher, 'Words That End in S', 'dogs cats pigs hens cows bugs rugs mugs', 'Easy', 'foundational_reading', 'Foundational Reading', 'word_reading');
        $cat = $this->activity($teacher, 'My Pet Cat', 'My cat is on the mat. She is soft and she is fat. She naps in the sun.', 'Easy', 'reading_fluency', 'Reading Fluency', 'sentence_reading');
        $rain = $this->activity($teacher, 'The Big Rain', 'Rain fell on the roof all day. We sat by the window and watched it run down the glass.', 'Medium', 'reading_fluency', 'Reading Fluency', 'passage_reading');
        $blend = $this->activity($teacher, 'Blend It: Frog and Crab', 'frog crab drum trip slim flag grin plum', 'Medium', 'foundational_reading', 'Foundational Reading', 'word_reading');
        $market = $this->activity($teacher, 'A Busy Day at the Market', 'Nina and Lola walk to the market early. They buy ripe mangoes, fresh fish and a bunch of green beans. A boy sells bright flowers by the gate, and Nina picks a yellow one for her desk.', 'Hard', 'reading_fluency', 'Reading Fluency', 'passage_reading');
        $reef = $this->activity($teacher, 'A Day at the Coral Reef', 'Mia and Dad went to the coral reef. They saw a tiny green turtle and a school of bright fish.', 'Medium', 'reading_comprehension', 'Reading Comprehension', 'passage_reading');
        $this->activity($teacher, 'Hop, Skip, Jump', 'hop skip jump run sit fall', 'Easy', 'foundational_reading', 'Foundational Reading', 'word_reading', 'Rejected');
        foreach ([['The Mango Tree', 'Medium', 'reading_fluency', 'Reading Fluency', 'passage_reading', 'Lito climbs the old mango tree. He picks a ripe one and shares it with his sister.'],
                  ['Short Vowel Fun', 'Easy', 'foundational_reading', 'Foundational Reading', 'word_reading', 'bag big bug box bed bin bus bat'],
                  ['Rain on the Roof', 'Hard', 'reading_comprehension', 'Reading Comprehension', 'passage_reading', 'The rain began early. Dad put the pots under the leaks. Soon the roof sang a plinking song.'],
                  ['Sam and the Ant', 'Easy', 'reading_fluency', 'Reading Fluency', 'sentence_reading', 'Sam sat on a log. An ant ran up his leg. Sam gave it a nut.']] as [$title, $tier, $comp, $label, $type, $text]) {
            $this->activity($teacher, $title, $text, $tier, $comp, $label, $type, 'Draft');
        }
        OpenRepositoryListing::create(['activity_id' => $ends->id, 'teacher_id' => $teacher->id, 'price_type' => 'Free', 'price' => 0]);
        OpenRepositoryListing::create(['activity_id' => $blend->id, 'teacher_id' => $teacher->id, 'price_type' => 'Paid', 'price' => 25]);

        // Sampaguita, Grade 1: one learner for each kind of alert.
        $maria = $this->kid($g1, 'Maria', 3, 'Developing');
        $ana = $this->kid($g1, 'Ana', 0, 'Beginning');
        $leo = $this->kid($g1, 'Leo', 2, 'Beginning');
        $rosa = $this->kid($g1, 'Rosa', 4, 'Developing');
        $nico = $this->kid($g1, 'Nico', 3, 'Developing');
        $jun = $this->kid($g1, 'Jun', 3, 'Developing');
        $miguel = $this->kid($g2, 'Miguel', 5, 'Proficient', ['grade_level' => 'Grade 2']);
        $paolo = $this->kid($g2, 'Paolo', 4, 'Developing', ['grade_level' => 'Grade 2']);

        ReadingSession::create(['learner_id' => $ana->id, 'activity_id' => $farm->id, 'session_type' => 'Diagnostic', 'initiated_by' => 'Teacher', 'accuracy_percent' => 40]);
        foreach ([[88, 0], [91, 2], [84, 4], [79, 6]] as [$acc, $d]) { $this->reading($maria, $farm, $acc, $d); }
        foreach ([[55, 5], [62, 3], [58, 1]] as [$acc, $d]) { $this->reading($leo, $ends, $acc, $d, 'Teacher', $this->missed('dogs', 'cats', 'pigs', 'hens', 'cows')); }
        foreach ([[94, 6], [92, 4], [96, 2]] as [$acc, $d]) { $this->reading($rosa, $cat, $acc, $d); }
        $this->reading($nico, $farm, 82, 12);
        $this->reading($nico, $cat, 78, 15);
        $this->reading($jun, $farm, 90, 9);
        $this->reading($jun, $cat, 93, 6);
        $this->reading($jun, $rain, 95, 3, 'Teacher', null, 2, 3);
        foreach ([[94, 0], [92, 2], [90, 3]] as [$acc, $d]) { $this->reading($miguel, $rain, $acc, $d); }
        $this->reading($paolo, $rain, 85, 1);
        $this->reading($paolo, $reef, 80, 4, 'Parent');
        foreach ([['dogs', 'Struggling'], ['cats', 'Struggling'], ['hens', 'Improving']] as [$w, $st]) {
            PersonalWordBank::create(['learner_id' => $leo->id, 'word' => $w, 'skill_type' => 'Decoding', 'mastery_status' => $st, 'times_drilled' => 2]);
        }

        // What she has given out.
        ActivityAssignment::create(['activity_id' => $farm->id, 'class_id' => $g1->id, 'assigned_by_teacher_id' => $teacher->id])->forceFill(['assigned_at' => now()->subDays(10)])->save();
        ActivityAssignment::create(['activity_id' => $cat->id, 'class_id' => $g1->id, 'reading_band' => 'support', 'assigned_by_teacher_id' => $teacher->id])->forceFill(['assigned_at' => now()->subDays(6)])->save();
        ActivityAssignment::create(['activity_id' => $rain->id, 'class_id' => $g2->id, 'assigned_by_teacher_id' => $teacher->id])->forceFill(['assigned_at' => now()->subDays(4)])->save();

        // Another teacher has released a learner to Grade 2: Ms Reyes can claim him.
        [$su, $santos] = $this->teacher('Ramon', 'Santos', 'ramon.santos@example.com', 'Active', ['Grade 1']);
        $rosal = SchoolClass::create(['teacher_id' => $santos->id, 'name' => 'Rosal', 'grade_level' => 'Grade 1', 'section' => 'C', 'school_year' => $year]);
        $jose = $this->kid($rosal, 'Jose', 4, 'Developing', ['class_id' => null, 'first_name' => 'Jose']);
        PromotionRecord::create(['learner_id' => $jose->id, 'released_by_teacher_id' => $santos->id, 'next_grade' => 'Grade 2', 'status' => 'Pending', 'released_from_class_id' => $rosal->id]);

        // Her routine updates (the Alerts page lists them under the alerts that need her).
        foreach ([[$maria, 'Maria read "Sounds at the Farm" with 88% accuracy.', 1], [$rosa, 'Rosa read "My Pet Cat" with 96% accuracy.', 5], [$leo, 'Leo read "Words That End in S" with 58% accuracy.', 27], [$miguel, 'Miguel read "The Big Rain" with 94% accuracy.', 3]] as [$k, $msg, $h]) {
            $n = Notification::create(['recipient_user_id' => $tu->id, 'learner_id' => $k->id, 'type' => Notification::TYPE_SESSION_SUMMARY, 'message' => $msg, 'is_read' => $h > 20]);
            $n->timestamp = now()->subHours($h);
            $n->save();
        }

        // ---- the pages, as the active teacher
        $this->actingAs($tu);
        $shots = [
            'home' => route('teacher.dashboard'),
            'classes' => route('teacher.classes.index'),
            'class-learners' => route('teacher.classes.index', ['open' => $g1->id, 'tab' => 'learners']),
            'class-activities' => route('teacher.classes.index', ['open' => $g1->id, 'tab' => 'acts']),
            'class-learner' => route('teacher.classes.index', ['open' => $g1->id, 'tab' => 'learner-'.$leo->id]),
            'activities-review' => route('teacher.activities.index', ['tab' => 'review']),
            'activities-mine' => route('teacher.activities.index', ['tab' => 'mine']),
            'activities-generate' => route('teacher.activities.index', ['generate' => 1]),
            'analytics' => route('teacher.analytics.index'),
            'analytics-learner' => route('teacher.analytics.index', ['mode' => 'learner', 'learner_id' => $maria->id]),
            'analytics-group' => route('teacher.analytics.index', ['mode' => 'group']),
            'alerts' => route('teacher.notifications.index'),
            'promotions' => route('teacher.promotions.index'),
            'promotions-claim' => route('teacher.promotions.index', ['tab' => 'claim']),
            'profile' => route('teacher.profile.edit'),
        ];
        foreach ($shots as $id => $url) {
            $r = $this->get($url);
            $r->assertOk();
            $this->save($id, $r->getContent());
        }
        // The window of every activity (the page fetches one when a card opens).
        foreach (Activity::where('created_by_teacher_id', $teacher->id)->get() as $a) {
            $r = $this->get(route('teacher.activities.window', $a), ['X-Requested-With' => 'XMLHttpRequest']);
            $r->assertOk();
            $this->save('window-'.$a->id, $r->getContent());
        }

        // ---- a teacher still waiting for approval (empty screens with their notes)
        [$pu] = $this->teacher('Mara', 'Cruz', 'mara.cruz@example.com', 'Pending', ['Grade 1']);
        $this->actingAs($pu);
        foreach (['home-pending' => route('teacher.dashboard'), 'classes-pending' => route('teacher.classes.index'), 'activities-pending' => route('teacher.activities.index'), 'analytics-pending' => route('teacher.analytics.index'), 'alerts-pending' => route('teacher.notifications.index')] as $id => $url) {
            $r = $this->get($url);
            $r->assertOk();
            $this->save($id, $r->getContent());
        }

        // ---- the pages a visitor sees
        auth()->logout();
        $this->flushSession();
        foreach (['signup' => route('register.teacher'), 'login' => route('login', ['role' => 'teacher'])] as $id => $url) {
            $r = $this->get($url);
            $r->assertOk();
            $this->save($id, $r->getContent());
        }

        @file_put_contents($this->dir().'/ids.json', json_encode(['g1' => $g1->id, 'g2' => $g2->id, 'maria' => $maria->id, 'leo' => $leo->id, 'ana' => $ana->id, 'rosa' => $rosa->id, 'nico' => $nico->id, 'jun' => $jun->id, 'miguel' => $miguel->id, 'paolo' => $paolo->id, 'farm' => $farm->id, 'ends' => $ends->id]));
        $this->assertTrue(true);
    }
}
