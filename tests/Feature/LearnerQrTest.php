<?php

namespace Tests\Feature;

use App\Models\Learner;
use App\Models\ParentAccount;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use App\Support\LearnerCode;
use App\Support\LearnerQr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Each learner has a QR code that holds the Learner Code and nothing else (never the PIN), made on our own server.
 * A parent can show and print it; a teacher's scanner adds a learner through the same route as the typed code.
 */
class LearnerQrTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function user(string $type, string $email): User
    {
        $u = User::create(['first_name' => 'Pat', 'last_name' => 'Person'.(++$this->seq), 'email' => $email, 'password' => 'Passw0rd!', 'user_type' => $type]);
        $u->forceFill(['email_verified_at' => now()])->save();

        return $u;
    }

    private function child(array $o = []): Learner
    {
        return Learner::create($o + ['learner_code' => LearnerCode::generate(), 'first_name' => 'Maria', 'last_name' => 'Cruz', 'grade_level' => 'Grade 1', 'pin' => '4321', 'avatar_id' => 'A', 'mastery_level' => 'Beginning']);
    }

    public function test_the_qr_is_an_inline_svg_that_holds_only_the_code(): void
    {
        $svg = LearnerQr::svg('TB26-48293', 240);

        $this->assertStringStartsWith('<svg ', $svg);
        $this->assertStringNotContainsString('<?xml', $svg);
        $this->assertStringContainsString('QR code for learner code TB26-48293', $svg);
        $this->assertNotSame($svg, LearnerQr::svg('TB26-48294', 240), 'a different code is a different picture');
        $this->assertSame('TB26-48293', LearnerQr::payload('tb26 48293'), 'what it holds is the stored form of the code, nothing else');
        $this->assertSame('TB-12345', LearnerQr::payload('TB12345'), 'an old style code works too');

        $this->expectException(\InvalidArgumentException::class);
        LearnerQr::svg('anything else');
    }

    public function test_a_parent_sees_the_qr_on_my_children_and_can_open_the_printable_card_without_the_pin(): void
    {
        $parent = $this->user('Parent', 'p@example.com');
        $profile = ParentAccount::create(['user_id' => $parent->id]);
        $kid = $this->child();
        $profile->learners()->attach($kid->id, ['relationship' => null, 'is_creator' => true, 'linked_at' => now()]);
        $this->actingAs($parent);

        $this->get(route('parent.children.index'))->assertOk()->assertSee('QR code for learner code '.$kid->learner_code, false)->assertSee('data-qr-open', false);

        $card = $this->get(route('parent.children.card', $kid))->assertOk();
        $card->assertSee('Maria Cruz')->assertSee($kid->learner_code)->assertSee('QR code for learner code '.$kid->learner_code, false)->assertSee('The PIN is not on this card');
        $this->assertStringNotContainsString('4321', $card->getContent(), 'the PIN is never printed');
    }

    public function test_right_after_a_profile_is_made_the_parent_sees_the_qr_and_a_link_to_print_the_card(): void
    {
        $parent = $this->user('Parent', 'new@example.com');
        ParentAccount::create(['user_id' => $parent->id]);
        $this->actingAs($parent);

        $page = $this->post(route('parent.children.store'), [
            'first_name' => 'Maria', 'last_name' => 'Perez', 'grade_level' => 'Grade 1', 'avatar_id' => '🦁', 'reading_stage' => 'letters',
            'q1' => 'yes', 'q2' => 'no', 'q3' => 'no', 'pin' => '1234', 'pin_confirmation' => '1234',
        ])->assertOk();

        $kid = Learner::where('first_name', 'Maria')->firstOrFail();
        $page->assertSee('QR code for learner code '.$kid->learner_code, false)->assertSee('Print Maria', false)->assertSee(route('parent.children.card', $kid), false);
        $this->assertStringNotContainsString('1234', preg_replace('/TB\d{2}-\d{5}/', '', strip_tags(\App\Support\LearnerQr::svg($kid->learner_code))), 'the QR holds the code only');
    }

    public function test_only_a_linked_parent_can_open_a_childs_card(): void
    {
        $stranger = $this->user('Parent', 's@example.com');
        ParentAccount::create(['user_id' => $stranger->id]);
        $teacher = $this->user('Teacher', 't@example.com');
        $kid = $this->child();

        $this->actingAs($stranger)->get(route('parent.children.card', $kid))->assertForbidden();
        $this->actingAs($teacher)->get(route('parent.children.card', $kid))->assertForbidden();
    }

    public function test_the_scanner_adds_a_learner_through_the_same_route_and_answers_in_json(): void
    {
        $user = $this->user('Teacher', 'tt@example.com');
        $teacher = Teacher::create(['user_id' => $user->id, 'school_name' => 'Rizal ES', 'employee_id' => 'E1', 'status' => 'Active']);
        $class = SchoolClass::create(['teacher_id' => $teacher->id, 'name' => 'Sampaguita', 'grade_level' => 'Grade 1', 'section' => 'A', 'school_year' => SchoolClass::currentSchoolYear()]);
        $kid = $this->child();
        $this->actingAs($user);

        $this->postJson(route('teacher.classes.join-learner', $class), ['learner_code' => $kid->learner_code])
            ->assertOk()->assertJson(['ok' => true, 'name' => 'Maria Cruz', 'code' => $kid->learner_code]);
        $this->assertSame($class->id, $kid->fresh()->class_id);

        // scanning the same card again, or a code nobody has, is refused with a plain message (no redirect)
        $this->postJson(route('teacher.classes.join-learner', $class), ['learner_code' => $kid->learner_code])
            ->assertStatus(422)->assertJson(['ok' => false, 'message' => 'This learner is already enrolled in a class.']);
        $this->postJson(route('teacher.classes.join-learner', $class), ['learner_code' => 'TB26-00000'])
            ->assertStatus(422)->assertJsonPath('ok', false);
    }

    public function test_the_camera_is_allowed_on_the_class_screens_only(): void
    {
        $user = $this->user('Teacher', 'tc@example.com');
        Teacher::create(['user_id' => $user->id, 'school_name' => 'Rizal ES', 'employee_id' => 'E2', 'status' => 'Active']);
        $this->actingAs($user);

        $this->assertStringContainsString('camera=(self)', $this->get(route('teacher.classes.index'))->headers->get('Permissions-Policy'));
        $this->assertStringContainsString('camera=()', $this->get(route('teacher.dashboard'))->headers->get('Permissions-Policy'));
    }

    public function test_the_childs_login_has_a_scan_my_card_button_and_still_lets_the_code_be_typed(): void
    {
        $page = $this->get(route('learner.login'))->assertOk();

        $page->assertSee('Scan my card')->assertSee('Scan your card, then type your secret PIN.')->assertSee('or type your code')
            ->assertSee('data-card-scan-open', false)->assertSee('id="csRoot"', false)->assertSee('data-target="#learner_code"', false)->assertSee('data-focus="#pinInput"', false)
            ->assertSee('vendor/jsQR-1.4.0.js', false)->assertSee('name="learner_code"', false)->assertSee('Got it!')->assertSee('I cannot see your card');
        $this->assertStringNotContainsString('autofocus', $page->getContent(), 'the typing box no longer pops the keyboard up over the scan button');
    }

    public function test_the_general_sign_in_has_a_small_scan_link_that_fills_the_same_box(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Scan my learner card')->assertSee('data-target="#email"', false)->assertSee('data-focus="#password"', false)->assertSee('name="email"', false);
    }

    public function test_the_camera_is_allowed_on_the_two_login_pages_and_nowhere_else_public(): void
    {
        $this->assertStringContainsString('camera=(self)', $this->get(route('learner.login'))->headers->get('Permissions-Policy'));
        $this->assertStringContainsString('camera=(self)', $this->get(route('login'))->headers->get('Permissions-Policy'));
        $this->assertStringContainsString('camera=()', $this->get(route('landing'))->headers->get('Permissions-Policy'));
    }
}
