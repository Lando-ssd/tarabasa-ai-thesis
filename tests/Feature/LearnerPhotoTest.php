<?php

namespace Tests\Feature;

use App\Http\Controllers\LearnerController;
use App\Models\Learner;
use App\Models\LearnerPhoto;
use App\Models\ParentAccount;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use App\Support\LearnerCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A child's photo is kept in the database (not on the server's disk, which the free host wipes on every restart or redeploy),
 * is shown only to the people allowed to see that child, and goes with the child when the child is deleted.
 */
class LearnerPhotoTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    /** A real 1 by 1 PNG, so no GD extension is needed. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Notification::fake();
    }

    private function png(): string
    {
        return base64_decode(self::PNG);
    }

    private function upload(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('maria.png', $this->png());
    }

    private function adult(string $type): array
    {
        $n = ++$this->seq;
        $user = User::create(['first_name' => 'Pat', 'last_name' => "Cruz{$n}", 'email' => "ph{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => $type]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $profile = $type === 'Parent'
            ? ParentAccount::create(['user_id' => $user->id])
            : Teacher::create(['user_id' => $user->id, 'school_name' => 'Rizal ES', 'employee_id' => "P{$n}", 'status' => 'Active']);

        return [$user, $profile];
    }

    private function child(?SchoolClass $class = null, ?ParentAccount $parent = null, bool $withPhoto = true): Learner
    {
        $n = ++$this->seq;
        $kid = Learner::create([
            'learner_code' => LearnerCode::generate(), 'class_id' => $class?->id, 'first_name' => "Mia{$n}", 'last_name' => 'Cruz', 'grade_level' => 'Grade 1',
            'pin' => Hash::make('2580'), 'avatar_id' => 'A', 'mastery_level' => 'Beginning',
        ]);
        $parent?->learners()->attach($kid->id, ['relationship' => 'Mother', 'is_creator' => true, 'linked_at' => now()]);

        if ($withPhoto) {
            LearnerPhoto::put($kid, $this->upload());
            $kid->refresh();
        }

        return $kid;
    }

    private function klass(Teacher $t): SchoolClass
    {
        return SchoolClass::create(['teacher_id' => $t->id, 'name' => 'Sampaguita', 'grade_level' => 'Grade 1', 'section' => 'A', 'group_tag' => null, 'school_year' => SchoolClass::currentSchoolYear()]);
    }

    private function see(Learner $kid)
    {
        return $this->get(route('photos.learner', ['learner' => $kid->id, 'v' => $kid->photo_version]));
    }

    // ------------------------------------------------------------------ kept in the database

    public function test_a_photo_added_in_the_wizard_is_kept_in_the_database_and_no_file_is_written(): void
    {
        [$user] = $this->adult('Parent');

        $this->actingAs($user)->post(route('parent.children.store'), [
            'parent_consent' => '1', 'photo_consent' => '1', 'first_name' => 'Maria', 'last_name' => 'Perez', 'grade_level' => 'Grade 1', 'avatar_id' => LearnerController::AVATARS[0],
            'reading_stage' => 'letters', 'q1' => 'yes', 'q2' => 'no', 'q3' => 'no', 'pin' => '2580', 'pin_confirmation' => '2580', 'avatar_photo' => $this->upload(),
        ])->assertOk();

        $kid = Learner::firstOrFail();
        $this->assertNotNull($kid->photo_version);
        $this->assertSame('image/png', LearnerPhoto::where('learner_id', $kid->id)->value('mime'));
        $this->assertSame([], Storage::disk('public')->allFiles(), 'nothing on the disk, so a wiped disk loses nothing');

        $shown = $this->see($kid)->assertOk();
        $this->assertSame('image/png', $shown->headers->get('Content-Type'));
        $this->assertSame($this->png(), $shown->getContent(), 'the very same picture comes back');
    }

    public function test_the_photo_still_shows_after_the_whole_server_disk_is_wiped(): void
    {
        [$user, $parent] = $this->adult('Parent');
        $kid = $this->child(null, $parent);

        Storage::disk('public')->deleteDirectory('');
        $this->assertSame([], Storage::disk('public')->allFiles());

        $this->actingAs($user);
        $this->see($kid)->assertOk();
        $this->get(route('parent.children.index'))->assertOk()->assertSee('/photos/learner/'.$kid->id.'?v='.$kid->photo_version, false);
    }

    public function test_a_replaced_photo_gets_a_new_address_and_there_is_still_one_row(): void
    {
        $kid = $this->child();
        $first = $kid->photo_version;

        LearnerPhoto::put($kid, $this->upload());

        $this->assertGreaterThan($first, $kid->fresh()->photo_version, 'a new version, so a browser never shows the old picture');
        $this->assertSame(1, LearnerPhoto::where('learner_id', $kid->id)->count());
    }

    public function test_only_jpeg_png_and_webp_are_kept(): void
    {
        $kid = $this->child(null, null, false);

        $this->expectException(\InvalidArgumentException::class);
        LearnerPhoto::put($kid, UploadedFile::fake()->createWithContent('x.gif', 'GIF89a'.str_repeat("\0", 20)));
    }

    public function test_a_photo_over_the_size_ceiling_is_refused_with_a_plain_message(): void
    {
        [$user] = $this->adult('Parent');

        $this->actingAs($user)->from(route('parent.children.create'))->post(route('parent.children.store'), [
            'parent_consent' => '1', 'photo_consent' => '1', 'first_name' => 'Maria', 'last_name' => 'Perez', 'grade_level' => 'Grade 1', 'avatar_id' => LearnerController::AVATARS[0],
            'reading_stage' => 'letters', 'q1' => 'yes', 'q2' => 'no', 'q3' => 'no', 'pin' => '2580', 'pin_confirmation' => '2580',
            'avatar_photo' => UploadedFile::fake()->createWithContent('big.png', $this->png().str_repeat("\0", 1200 * 1024)),
        ])->assertSessionHasErrors('avatar_photo');

        $this->assertSame(0, Learner::count(), 'and no child is made');
    }

    // ------------------------------------------------------------------ who may see it

    public function test_a_parent_and_a_second_guardian_see_the_photo_but_another_parent_does_not(): void
    {
        [$mother, $motherProfile] = $this->adult('Parent');
        [$father, $fatherProfile] = $this->adult('Parent');
        [$stranger] = $this->adult('Parent');
        $kid = $this->child(null, $motherProfile);
        $fatherProfile->learners()->attach($kid->id, ['relationship' => 'Father', 'is_creator' => false, 'linked_at' => now()]);

        $this->actingAs($mother);
        $this->see($kid)->assertOk();

        $this->app['auth']->forgetGuards();
        $this->actingAs($father);
        $this->see($kid)->assertOk();

        $this->app['auth']->forgetGuards();
        $this->actingAs($stranger);
        $this->see($kid)->assertNotFound();
    }

    public function test_the_class_teacher_sees_the_photo_and_another_teacher_does_not(): void
    {
        [$teacher, $teacherProfile] = $this->adult('Teacher');
        [$other] = $this->adult('Teacher');
        $kid = $this->child($this->klass($teacherProfile));

        $this->actingAs($teacher);
        $this->see($kid)->assertOk();

        $this->app['auth']->forgetGuards();
        $this->actingAs($other);
        $this->see($kid)->assertNotFound();
    }

    public function test_a_teacher_does_not_see_the_photo_of_a_child_who_is_in_no_class(): void
    {
        [$teacher] = $this->adult('Teacher');
        $kid = $this->child();

        $this->actingAs($teacher);
        $this->see($kid)->assertNotFound();
    }

    public function test_the_child_sees_their_own_photo_and_not_another_childs(): void
    {
        $mine = $this->child();
        $theirs = $this->child();

        $this->actingAs($mine, 'learner');

        $this->see($mine)->assertOk();
        $this->see($theirs)->assertNotFound();
    }

    public function test_a_guest_and_an_admin_get_not_found_never_forbidden(): void
    {
        $kid = $this->child();
        $this->see($kid)->assertNotFound();

        $admin = User::create(['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'ada@example.com', 'password' => 'Passw0rd!', 'user_type' => 'Admin']);
        $this->actingAs($admin);
        $this->see($kid)->assertNotFound();
    }

    public function test_a_child_without_a_photo_and_an_unknown_child_are_both_not_found(): void
    {
        [$user, $parent] = $this->adult('Parent');
        $kid = $this->child(null, $parent, false);
        $this->actingAs($user);

        $this->get(route('photos.learner', ['learner' => $kid->id]))->assertNotFound();
        $this->get(route('photos.learner', ['learner' => 999999]))->assertNotFound();
    }

    public function test_a_phone_token_works_for_the_child_and_a_deactivated_adult_is_refused(): void
    {
        [$parentUser, $parent] = $this->adult('Parent');
        $kid = $this->child(null, $parent);
        $other = $this->child();

        $childToken = $kid->createToken('phone')->plainTextToken;
        $this->withToken($childToken)->get(route('photos.learner', ['learner' => $kid->id, 'v' => $kid->photo_version]))->assertOk();
        $this->withToken($childToken)->get(route('photos.learner', ['learner' => $other->id, 'v' => $other->photo_version]))->assertNotFound();

        $parentToken = $parentUser->createToken('phone')->plainTextToken;
        $this->withToken($parentToken)->get(route('photos.learner', ['learner' => $kid->id]))->assertOk();

        $parentUser->forceFill(['status' => 'Inactive'])->save();
        $this->app['auth']->forgetGuards();
        $this->withToken($parentToken)->get(route('photos.learner', ['learner' => $kid->id]))->assertNotFound();
    }

    // ------------------------------------------------------------------ caching

    public function test_the_photo_may_be_kept_by_the_browser_for_an_hour_and_is_not_marked_no_store(): void
    {
        [$user, $parent] = $this->adult('Parent');
        $kid = $this->child(null, $parent);
        $this->actingAs($user);

        $response = $this->see($kid)->assertOk();
        $cache = (string) $response->headers->get('Cache-Control');

        $this->assertStringContainsString('private', $cache, 'never shared');
        $this->assertStringContainsString('max-age=3600', $cache);
        $this->assertStringNotContainsString('no-store', $cache, 'a list of children must not download every photo again on every page');
        $this->assertNotEmpty($response->headers->get('ETag'));
    }

    public function test_a_browser_that_already_has_the_picture_gets_a_304_and_a_stranger_cannot_use_the_tag(): void
    {
        [$user, $parent] = $this->adult('Parent');
        [$stranger] = $this->adult('Parent');
        $kid = $this->child(null, $parent);

        $this->actingAs($user);
        $etag = $this->see($kid)->assertOk()->headers->get('ETag');

        $again = $this->withHeader('If-None-Match', $etag)->get(route('photos.learner', ['learner' => $kid->id, 'v' => $kid->photo_version]));
        $again->assertStatus(304);
        $this->assertSame('', $again->getContent());
        $this->assertStringNotContainsString('no-store', (string) $again->headers->get('Cache-Control'));

        $this->app['auth']->forgetGuards();
        $this->actingAs($stranger);
        $this->withHeader('If-None-Match', $etag)->get(route('photos.learner', ['learner' => $kid->id, 'v' => $kid->photo_version]))
            ->assertNotFound();
    }

    // ------------------------------------------------------------------ the children's pages and the phone

    public function test_the_phone_payload_points_at_the_private_address_and_is_null_without_a_photo(): void
    {
        $with = $this->child();
        $without = $this->child(null, null, false);

        $this->assertSame(route('photos.learner', ['learner' => $with->id, 'v' => $with->photo_version]), $with->photoUrl());
        $this->assertNull($without->photoUrl());
    }

    // ------------------------------------------------------------------ erasure and the move of old files

    public function test_deleting_the_child_deletes_the_photo_too(): void
    {
        [$user, $parent] = $this->adult('Parent');
        $kid = $this->child(null, $parent);
        $this->assertSame(1, LearnerPhoto::count());

        $this->actingAs($user)->post(route('parent.children.remove', $kid), ['password' => 'Passw0rd!', 'confirm' => '1'])->assertRedirect(route('parent.children.index'));

        $this->assertSame(0, LearnerPhoto::count(), 'the picture of a deleted child does not stay behind');
        $this->assertSame(0, Learner::count());
    }

    public function test_a_photo_row_cannot_outlive_its_child_even_without_the_service(): void
    {
        $kid = $this->child();

        DB::table('learners')->where('id', $kid->id)->delete();

        $this->assertSame(0, LearnerPhoto::count(), 'the database itself removes it (cascade)');
    }

    public function test_old_photo_files_are_moved_into_the_database_and_dead_pointers_are_cleared(): void
    {
        $alive = $this->child(null, null, false);
        $gone = $this->child(null, null, false);
        Storage::disk('public')->put('avatars/alive.png', $this->png());
        $alive->update(['avatar_photo_path' => 'avatars/alive.png']);
        $gone->update(['avatar_photo_path' => 'avatars/gone.png']); // the file was wiped with the old disk

        $migration = require base_path('database/migrations/2026_10_21_000200_create_learner_photos_table.php');
        $move = new \ReflectionMethod($migration, 'moveOldPhotoFiles');
        $move->invoke($migration);

        $alive->refresh();
        $gone->refresh();
        $this->assertNotNull($alive->photo_version, 'a file that was still there is kept');
        $this->assertNull($alive->avatar_photo_path);
        Storage::disk('public')->assertMissing('avatars/alive.png');
        $this->assertSame($this->png(), base64_decode(LearnerPhoto::where('learner_id', $alive->id)->value('data')));

        $this->assertNull($gone->photo_version, 'nothing to keep');
        $this->assertNull($gone->avatar_photo_path, 'and the dead pointer is cleared');
        $this->assertSame(0, LearnerPhoto::where('learner_id', $gone->id)->count());
    }
}
