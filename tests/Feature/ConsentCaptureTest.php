<?php

namespace Tests\Feature;

use App\Models\ConsentRecord;
use App\Models\Learner;
use App\Models\ParentAccount;
use App\Models\User;
use App\Services\ConsentService;
use App\Support\LearnerCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * What a person agrees to is asked for, required where it must be, written down with the version of the legal pages, and deleted
 * with the person or the child. The words the boxes carry are on resources/views/legal/consent.blade.php; these check the behaviour.
 */
class ConsentCaptureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Storage::fake('public');
        Notification::fake();
    }

    private function parentUser(string $email = 'carla@example.com'): User
    {
        $user = User::create(['first_name' => 'Carla', 'last_name' => 'Domingo', 'email' => $email, 'password' => 'Passw0rd!', 'user_type' => 'Parent']);
        $user->forceFill(['email_verified_at' => now()])->save();
        ParentAccount::create(['user_id' => $user->id]);

        return $user;
    }

    /** A real 1 by 1 PNG, so the test needs no GD extension (UploadedFile::fake()->image() does). */
    private function photo(): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent('maria.png', $png);
    }

    private function childForm(array $o = []): array
    {
        return $o + [
            'first_name' => 'Maria', 'last_name' => 'Perez', 'grade_level' => 'Grade 1', 'avatar_id' => '🦁', 'reading_stage' => 'letters',
            'q1' => 'yes', 'q2' => 'no', 'q3' => 'no', 'pin' => '1234', 'pin_confirmation' => '1234',
        ];
    }

    private function existingChild(User $guardian): Learner
    {
        $kid = Learner::create([
            'learner_code' => LearnerCode::generate(), 'first_name' => 'Mia', 'last_name' => 'Cruz', 'grade_level' => 'Grade 1',
            'pin' => Hash::make('2580'), 'avatar_id' => 'A', 'mastery_level' => 'Beginning',
        ]);
        $guardian->parentProfile->learners()->attach($kid->id, ['relationship' => 'Mother', 'is_creator' => true, 'linked_at' => now()]);

        return $kid;
    }

    // ------------------------------------------------------------ adults signing up

    public function test_a_parent_cannot_sign_up_without_ticking_the_box_and_nothing_is_made(): void
    {
        $form = ['first_name' => 'Pat', 'last_name' => 'Cruz', 'email' => 'pat@example.com', 'password' => 'Passw0rd!', 'password_confirmation' => 'Passw0rd!'];

        $this->from(route('register.parent'))->post(route('register.parent.submit'), $form)
            ->assertSessionHasErrors(['accepts_terms' => 'Please tick the box to agree, or we cannot make your account.']);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('consent_records', 0);
    }

    public function test_a_parent_who_ticks_the_box_leaves_a_record_with_the_version_of_the_pages(): void
    {
        $this->post(route('register.parent.submit'), ['first_name' => 'Pat', 'last_name' => 'Cruz', 'email' => 'pat@example.com', 'password' => 'Passw0rd!', 'password_confirmation' => 'Passw0rd!', 'accepts_terms' => '1'])->assertOk();

        $record = ConsentRecord::firstOrFail();
        $this->assertSame(User::where('email', 'pat@example.com')->value('id'), $record->user_id);
        $this->assertNull($record->learner_id);
        $this->assertSame(ConsentService::TERMS_PRIVACY, $record->kind);
        $this->assertSame(config('legal.version'), $record->policy_version);
        $this->assertSame('web', $record->channel);
        $this->assertNotNull($record->accepted_at);
    }

    public function test_a_teacher_cannot_sign_up_without_ticking_the_box_either(): void
    {
        $form = [
            'first_name' => 'Pinky', 'last_name' => 'Reyes', 'email' => 'pinky@example.com', 'password' => 'Passw0rd!', 'password_confirmation' => 'Passw0rd!',
            'school_name' => 'Rizal ES', 'employee_id' => 'EMP-1', 'grades_mode' => 'single', 'grades_handled' => ['Grade 2'],
        ];

        $this->from(route('register.teacher'))->post(route('register.teacher.submit'), $form)->assertSessionHasErrors('accepts_terms');
        $this->assertDatabaseCount('users', 0);

        $this->post(route('register.teacher.submit'), $form + ['accepts_terms' => '1'])->assertOk();
        $this->assertSame(1, ConsentRecord::where('kind', ConsentService::TERMS_PRIVACY)->count());
    }

    public function test_the_sign_up_forms_show_the_box_with_links_to_what_is_agreed(): void
    {
        foreach (['register.parent', 'register.teacher'] as $route) {
            $this->get(route($route))->assertOk()
                ->assertSee('name="accepts_terms"', false)->assertSee(route('terms'), false)->assertSee(route('privacy'), false)
                ->assertDontSee('name="accepts_terms" value="1" checked', false);
        }
    }

    // ------------------------------------------------------------ a parent adding a child

    public function test_a_child_is_not_added_without_the_parents_consent_and_nothing_is_kept(): void
    {
        $user = $this->parentUser();

        $this->actingAs($user)->from(route('parent.children.create'))->post(route('parent.children.store'), $this->childForm())
            ->assertSessionHasErrors('parent_consent');

        $this->assertSame(0, Learner::count());
        $this->assertSame(0, ConsentRecord::count());
    }

    public function test_consent_for_a_child_is_recorded_against_that_child(): void
    {
        $user = $this->parentUser();

        $this->actingAs($user)->post(route('parent.children.store'), $this->childForm(['parent_consent' => '1']))->assertOk();

        $kid = Learner::firstOrFail();
        $record = ConsentRecord::where('learner_id', $kid->id)->firstOrFail();
        $this->assertSame($user->id, $record->user_id);
        $this->assertSame(ConsentService::CHILD_DATA, $record->kind);
        $this->assertSame(config('legal.version'), $record->policy_version);
        $this->assertSame(0, ConsentRecord::where('kind', ConsentService::CHILD_PHOTO)->count(), 'no photo, so no photo consent');
    }

    public function test_a_photo_needs_its_own_separate_consent(): void
    {
        $user = $this->parentUser();
        $photo = $this->photo();

        $this->actingAs($user)->from(route('parent.children.create'))->post(route('parent.children.store'), $this->childForm(['parent_consent' => '1', 'avatar_photo' => $photo]))
            ->assertSessionHasErrors('photo_consent');
        $this->assertSame(0, Learner::count(), 'the child is not made, and no photo stored, without the photo box');
        $this->assertSame([], Storage::disk('public')->allFiles());

        $this->actingAs($user)->post(route('parent.children.store'), $this->childForm(['parent_consent' => '1', 'photo_consent' => '1', 'avatar_photo' => $this->photo()]))->assertOk();

        $kid = Learner::firstOrFail();
        $this->assertNotNull($kid->photo_version, 'the photo is kept, in the database');
        $this->assertSame(1, \App\Models\LearnerPhoto::where('learner_id', $kid->id)->count());
        $this->assertSame([], Storage::disk('public')->allFiles(), 'and no file is written to the server disk, which the free host wipes');
        $this->assertSame(['child_data', 'child_photo'], ConsentRecord::where('learner_id', $kid->id)->orderBy('id')->pluck('kind')->all());
    }

    public function test_the_wizard_page_carries_the_boxes_and_the_notice(): void
    {
        $this->actingAs($this->parentUser())->get(route('parent.children.create'))->assertOk()
            ->assertSee('name="parent_consent"', false)->assertSee('name="photo_consent"', false)->assertSee(route('consent'), false)
            ->assertSee('is deleted as soon as it has been scored', false);
    }

    // ------------------------------------------------------------ a second guardian linking

    public function test_linking_to_a_child_needs_the_guardian_to_confirm_and_is_recorded(): void
    {
        $first = $this->parentUser('first@example.com');
        $kid = $this->existingChild($first);
        $second = $this->parentUser('second@example.com');
        $body = ['learner_code' => $kid->learner_code, 'pin' => '2580', 'relationship' => 'Father'];

        $this->actingAs($second)->from(route('parent.children.link'))->post(route('parent.children.link.submit'), $body)->assertSessionHasErrors('guardian_confirm');
        $this->assertSame(0, $second->parentProfile->learners()->count());

        $this->actingAs($second)->post(route('parent.children.link.submit'), $body + ['guardian_confirm' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(1, $second->parentProfile->learners()->count());

        $record = ConsentRecord::where('user_id', $second->id)->firstOrFail();
        $this->assertSame(ConsentService::CHILD_LINK, $record->kind);
        $this->assertSame($kid->id, $record->learner_id);
    }

    // ------------------------------------------------------------ the phone app

    public function test_the_phone_can_send_the_boxes_and_it_is_recorded_as_from_the_app(): void
    {
        $r = $this->postJson('/api/auth/register/parent', ['first_name' => 'Pat', 'last_name' => 'Cruz', 'email' => 'pat@example.com', 'password' => 'Passw0rd!', 'password_confirmation' => 'Passw0rd!', 'accepts_terms' => true]);
        $r->assertStatus(201);

        $this->assertSame('app', ConsentRecord::firstOrFail()->channel);
    }

    public function test_until_every_phone_has_the_boxes_the_server_still_accepts_a_sign_up_without_them(): void
    {
        config(['legal.api_consent_required' => false]);

        $this->postJson('/api/auth/register/parent', ['first_name' => 'Pat', 'last_name' => 'Cruz', 'email' => 'old@example.com', 'password' => 'Passw0rd!', 'password_confirmation' => 'Passw0rd!'])->assertStatus(201);
        $this->assertSame(0, ConsentRecord::count(), 'nothing is recorded that was not ticked');
    }

    public function test_once_the_setting_is_on_the_phone_must_send_the_boxes_too(): void
    {
        config(['legal.api_consent_required' => true]);
        $form = ['first_name' => 'Pat', 'last_name' => 'Cruz', 'email' => 'pat@example.com', 'password' => 'Passw0rd!', 'password_confirmation' => 'Passw0rd!'];

        $this->postJson('/api/auth/register/parent', $form)->assertStatus(422)->assertJsonValidationErrors('accepts_terms');
        $this->postJson('/api/auth/register/parent', $form + ['accepts_terms' => true])->assertStatus(201);

        $user = $this->parentUser('p2@example.com');
        $token = $user->createToken('mobile')->plainTextToken;
        $child = ['first_name' => 'Ben', 'last_name' => 'Santos', 'grade_level' => 'Grade 1', 'avatar_id' => '🐰', 'reading_stage' => 'blending', 'q1' => 'yes', 'q2' => 'yes', 'q3' => 'no', 'pin' => '4321', 'pin_confirmation' => '4321'];

        $this->withToken($token)->postJson('/api/parent/children', $child)->assertStatus(422)->assertJsonValidationErrors('parent_consent');
        $this->withToken($token)->postJson('/api/parent/children', $child + ['parent_consent' => true])->assertCreated();

        $record = ConsentRecord::where('kind', ConsentService::CHILD_DATA)->firstOrFail();
        $this->assertSame('app', $record->channel);
        $this->assertSame(Learner::where('first_name', 'Ben')->value('id'), $record->learner_id);
    }

    // ------------------------------------------------------------ deleting

    public function test_deleting_a_child_deletes_the_consent_records_about_that_child(): void
    {
        $user = $this->parentUser();
        $this->actingAs($user)->post(route('parent.children.store'), $this->childForm(['parent_consent' => '1']))->assertOk();
        $kid = Learner::firstOrFail();
        $this->assertSame(1, ConsentRecord::where('learner_id', $kid->id)->count());

        $this->app['auth']->forgetGuards();
        $this->actingAs($user)->post(route('parent.children.remove', $kid), ['password' => 'Passw0rd!', 'confirm' => '1'])->assertRedirect();

        $this->assertSame(0, Learner::count());
        $this->assertSame(0, ConsentRecord::count(), 'no record of what was agreed about a deleted child is kept');
    }

    public function test_deleting_an_account_deletes_that_persons_records_but_not_the_other_guardians(): void
    {
        $first = $this->parentUser('first@example.com');
        $kid = $this->existingChild($first);
        $second = $this->parentUser('second@example.com');
        $second->parentProfile->learners()->attach($kid->id, ['relationship' => 'Father', 'is_creator' => false, 'linked_at' => now()]);

        foreach ([$first, $second] as $u) {
            ConsentService::record($u, ConsentService::TERMS_PRIVACY, true);
            ConsentService::record($u, ConsentService::CHILD_DATA, true, $kid);
        }

        $this->app['auth']->forgetGuards();
        $this->actingAs($second)->post(route('profile.delete'), ['password' => 'Passw0rd!', 'confirm' => '1'])->assertRedirect();

        $this->assertSame(0, ConsentRecord::where('user_id', $second->id)->count());
        $this->assertSame(2, ConsentRecord::where('user_id', $first->id)->count(), 'the guardian who stays keeps their own record');
    }

    public function test_a_parent_who_leaves_a_child_with_another_guardian_loses_only_their_own_record_about_that_child(): void
    {
        $first = $this->parentUser('first@example.com');
        $kid = $this->existingChild($first);
        $second = $this->parentUser('second@example.com');
        $second->parentProfile->learners()->attach($kid->id, ['relationship' => 'Father', 'is_creator' => false, 'linked_at' => now()]);
        ConsentService::record($first, ConsentService::CHILD_DATA, true, $kid);
        ConsentService::record($second, ConsentService::CHILD_LINK, true, $kid);
        ConsentService::record($second, ConsentService::TERMS_PRIVACY, true);

        $this->app['auth']->forgetGuards();
        $this->actingAs($second)->post(route('parent.children.remove', $kid), ['password' => 'Passw0rd!', 'confirm' => '1'])->assertRedirect();

        $this->assertSame(1, Learner::count(), 'the child stays for the other guardian');
        $this->assertSame(0, ConsentRecord::where('user_id', $second->id)->where('learner_id', $kid->id)->count());
        $this->assertSame(1, ConsentRecord::where('user_id', $second->id)->whereNull('learner_id')->count(), 'their own account record stays while the account exists');
        $this->assertSame(1, ConsentRecord::where('user_id', $first->id)->count());
    }

    public function test_the_consent_table_holds_no_address_or_browser_details(): void
    {
        $columns = DB::getSchemaBuilder()->getColumnListing('consent_records');

        $this->assertSame(['id', 'user_id', 'learner_id', 'kind', 'policy_version', 'channel', 'accepted_at', 'withdrawn_at'], $columns);
    }
}
