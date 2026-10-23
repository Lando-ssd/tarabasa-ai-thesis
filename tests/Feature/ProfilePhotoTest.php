<?php

namespace Tests\Feature;

use App\Models\Learner;
use App\Models\ParentAccount;
use App\Models\Teacher;
use App\Models\User;
use App\Models\UserPhoto;
use App\Services\DataErasureService;
use App\Support\LearnerCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A Teacher's or Parent's own profile photo, on the website and in the phone app.
 *
 * It is kept in the database (so it survives a restart of the free hosting) and ONLY the person it belongs to can ever open it:
 * not a child, not another teacher or parent, not the Administrator, not a visitor with the address. Everyone else gets "not found".
 */
class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function png(string $name = 'me.png'): UploadedFile
    {
        // a real 1 by 1 picture, so the test needs no image library
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
    }

    private function person(string $type): User
    {
        $n = ++$this->seq;
        $user = User::create(['first_name' => 'Pat', 'last_name' => "Photo{$n}", 'email' => "photo{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => $type]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $type === 'Teacher'
            ? Teacher::create(['user_id' => $user->id, 'school_name' => 'Rizal ES', 'employee_id' => "PH{$n}", 'status' => 'Active', 'free_generation_credits_remaining' => 2, 'grades_handled' => ['Grade 1']])
            : ParentAccount::create(['user_id' => $user->id]);

        return $user;
    }

    private function api(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    // ------------------------------------------------------------------ saving, replacing, removing

    public function test_a_teacher_and_a_parent_can_add_change_and_remove_their_own_photo_on_the_website(): void
    {
        foreach (['Teacher', 'Parent'] as $type) {
            $user = $this->person($type);
            $this->assertNull($user->photoUrl());

            $this->actingAs($user)->post(route('profile.photo'), ['photo' => $this->png()])->assertRedirect()->assertSessionHas('status', 'Photo saved.');
            $user->refresh();
            $first = $user->photo_version;
            $this->assertNotNull($first);
            $this->assertSame(1, UserPhoto::where('user_id', $user->id)->count());
            $this->assertSame('image/png', UserPhoto::where('user_id', $user->id)->value('mime'));

            // a new picture gets a new address, so a browser never shows the old one
            $this->actingAs($user)->post(route('profile.photo'), ['photo' => $this->png('again.png')])->assertRedirect();
            $this->assertGreaterThan($first, $user->refresh()->photo_version);
            $this->assertSame(1, UserPhoto::where('user_id', $user->id)->count(), 'one row per person');

            $this->actingAs($user)->delete(route('profile.photo.remove'))->assertRedirect()->assertSessionHas('status', 'Photo removed.');
            $this->assertNull($user->refresh()->photo_version);
            $this->assertSame(0, UserPhoto::where('user_id', $user->id)->count(), 'nothing is left in the database');
        }
    }

    public function test_the_profile_page_and_the_avatar_circles_show_the_photo(): void
    {
        $teacher = $this->person('Teacher');
        $parent = $this->person('Parent');
        $this->actingAs($teacher)->post(route('profile.photo'), ['photo' => $this->png()]);
        $this->actingAs($parent)->post(route('profile.photo'), ['photo' => $this->png()]);

        $teacherPage = $this->actingAs($teacher->refresh())->get(route('teacher.profile.edit'))->assertOk()
            ->assertSee('Profile photo')->assertSee('Remove photo')->assertSee('Change photo');
        $this->assertSame(1, substr_count($teacherPage->getContent(), 'src="'.$teacher->photoUrl().'"') >= 3 ? 1 : 0, 'the sidebar, the top bar and the profile page all use it');

        $this->actingAs($parent->refresh())->get(route('parent.profile.edit'))->assertOk()->assertSee('Remove photo')->assertSee($parent->photoUrl(), false);
        $this->actingAs($parent)->get(route('parent.dashboard'))->assertOk()->assertSee($parent->photoUrl(), false);

        // a person with no photo is offered one, and sees no picture
        $other = $this->person('Parent');
        $this->actingAs($other)->get(route('parent.profile.edit'))->assertOk()->assertSee('Choose a photo')->assertDontSee('Remove photo');
    }

    public function test_only_a_real_small_picture_is_kept(): void
    {
        $user = $this->person('Parent');

        // Real temporary files, not Laravel's fake upload: the fake one reports its type from the file NAME, so a text file called
        // x.png would pass as a picture. A browser's upload is judged by what is INSIDE the file, and so is this.
        $real = function (string $name, string $bytes): UploadedFile {
            $path = tempnam(sys_get_temp_dir(), 'tbphoto');
            file_put_contents($path, $bytes);

            return new UploadedFile($path, $name, null, null, true);
        };
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $tries = [
            'nothing sent' => [],
            'text named .png' => ['photo' => $real('x.png', 'this is not a picture')],
            'a drawing file with a script in it' => ['photo' => $real('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')],
            'a drawing file named .png' => ['photo' => $real('y.png', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')],
            'a picture over 1 MB' => ['photo' => $real('big.png', $png.str_repeat("\0", 1100 * 1024))],
        ];
        foreach ($tries as $what => $body) {
            $response = $this->actingAs($user)->post(route('profile.photo'), $body);
            try {
                $response->assertSessionHasErrors('photo');
            } catch (\PHPUnit\Framework\AssertionFailedError $e) {
                $this->fail("{$what} was not refused (status {$response->getStatusCode()})");
            }
        }

        $this->assertNull($user->refresh()->photo_version);
        $this->assertSame(0, UserPhoto::count());
    }

    public function test_an_administrator_and_a_child_cannot_add_one(): void
    {
        $admin = $this->person('Admin');
        $this->actingAs($admin)->post(route('profile.photo'), ['photo' => $this->png()])->assertForbidden();
        $this->assertNull($admin->refresh()->photo_version);

        // a child's session (the route audit signs a child in on the same guard) is turned away too, never a crash
        $kid = Learner::create(['learner_code' => LearnerCode::generate(), 'first_name' => 'Kid', 'last_name' => 'Cruz', 'grade_level' => 'Grade 1', 'pin' => Hash::make('1234'), 'avatar_id' => 'A', 'mastery_level' => 'Beginning']);
        $this->app['auth']->forgetGuards();
        $this->actingAs($kid)->post(route('profile.photo'), ['photo' => $this->png()])->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->actingAs($kid)->delete(route('profile.photo.remove'))->assertForbidden();

        // a visitor who is not signed in is sent to the sign in page
        $this->app['auth']->forgetGuards();
        $this->post(route('profile.photo'), ['photo' => $this->png()])->assertRedirect(route('login'));
    }

    // ------------------------------------------------------------------ who may see it

    public function test_only_the_owner_can_open_the_photo_and_everyone_else_gets_not_found(): void
    {
        $owner = $this->person('Teacher');
        $this->actingAs($owner)->post(route('profile.photo'), ['photo' => $this->png()]);
        $owner->refresh();
        $address = route('photos.user', ['user' => $owner->id]);

        // the owner, on the website: the picture, with a short private cache and an ETag
        $r = $this->actingAs($owner)->get($address)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringContainsString('private', (string) $r->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('no-store', (string) $r->headers->get('Cache-Control'), 'a photo may be kept for an hour by the browser');
        $etag = $r->headers->get('ETag');
        $this->actingAs($owner)->get($address, ['If-None-Match' => $etag])->assertStatus(304);

        // nobody else: a visitor, another teacher, a parent, an Administrator, a child
        $this->app['auth']->forgetGuards();
        $this->get($address)->assertNotFound();
        foreach (['Teacher', 'Parent', 'Admin'] as $type) {
            $this->actingAs($this->person($type))->get($address)->assertNotFound();
        }
        $kid = Learner::create(['learner_code' => LearnerCode::generate(), 'first_name' => 'Kid', 'last_name' => 'Cruz', 'grade_level' => 'Grade 1', 'pin' => Hash::make('1234'), 'avatar_id' => 'A', 'mastery_level' => 'Beginning']);
        $this->app['auth']->forgetGuards();
        $this->actingAs($kid, 'learner')->get($address)->assertNotFound();

        // a person with no photo, and an address that does not exist, look exactly alike
        $nobody = $this->person('Parent');
        $this->actingAs($nobody)->get(route('photos.user', ['user' => $nobody->id]))->assertNotFound();
        $this->actingAs($nobody)->get(route('photos.user', ['user' => 999999]))->assertNotFound();
    }

    public function test_a_deactivated_owner_sees_nothing(): void
    {
        $owner = $this->person('Parent');
        $this->actingAs($owner)->post(route('profile.photo'), ['photo' => $this->png()]);
        $owner->refresh()->forceFill(['status' => 'Inactive'])->save();

        $this->actingAs($owner)->get(route('photos.user', ['user' => $owner->id]))->assertNotFound();
    }

    // ------------------------------------------------------------------ the phone app

    public function test_the_phone_can_add_see_and_remove_the_photo_and_the_account_carries_its_address(): void
    {
        $user = $this->person('Parent');
        $token = $user->createToken('mobile')->plainTextToken;

        $this->api($token)->getJson('/api/auth/me')->assertOk()->assertJsonPath('account.photoUrl', null);

        $r = $this->api($token)->post('/api/auth/profile/photo', ['photo' => $this->png()], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('message', 'Photo saved.');
        $url = $r->json('account.photoUrl');
        $this->assertStringContainsString('/photos/user/'.$user->id, (string) $url);
        $this->api($token)->getJson('/api/auth/me')->assertJsonPath('account.photoUrl', $url);

        // the phone opens it with its own token, at its own API address (no id in it) or at the printed address
        $this->api($token)->get('/api/auth/profile/photo')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->api($token)->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
        // another person's token does not: the printed address says "not found", and their own address has no photo to give
        $thief = $this->person('Teacher')->createToken('mobile')->plainTextToken;
        $this->api($thief)->get($url)->assertNotFound();
        $this->api($thief)->get('/api/auth/profile/photo')->assertNotFound();
        // a child's token and a visitor never reach it
        $kidToken = Learner::create(['learner_code' => LearnerCode::generate(), 'first_name' => 'Kid', 'last_name' => 'Cruz', 'grade_level' => 'Grade 1', 'pin' => Hash::make('1234'), 'avatar_id' => 'A', 'mastery_level' => 'Beginning'])->createToken('mobile')->plainTextToken;
        $this->api($kidToken)->get('/api/auth/profile/photo')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withoutToken()->getJson('/api/auth/profile/photo')->assertUnauthorized();

        $this->api($token)->postJson('/api/auth/profile/photo', [])->assertStatus(422)->assertJsonValidationErrors('photo');
        $this->api($token)->deleteJson('/api/auth/profile/photo')->assertOk()->assertJsonPath('account.photoUrl', null);
        $this->assertSame(0, UserPhoto::count());
    }

    public function test_a_childs_phone_token_and_an_admin_cannot_use_the_adult_photo_routes(): void
    {
        $kid = Learner::create(['learner_code' => LearnerCode::generate(), 'first_name' => 'Kid', 'last_name' => 'Cruz', 'grade_level' => 'Grade 1', 'pin' => Hash::make('1234'), 'avatar_id' => 'A', 'mastery_level' => 'Beginning']);
        $childToken = $kid->createToken('mobile')->plainTextToken;
        $adminToken = $this->person('Admin')->createToken('mobile')->plainTextToken;

        foreach ([$childToken, $adminToken] as $token) {
            $this->api($token)->post('/api/auth/profile/photo', ['photo' => $this->png()], ['Accept' => 'application/json'])->assertStatus(403);
        }
        $this->assertSame(0, UserPhoto::count());
    }

    // ------------------------------------------------------------------ deleting the account, and the words we promised

    public function test_deleting_the_account_deletes_the_photo(): void
    {
        $user = $this->person('Parent');
        $this->actingAs($user)->post(route('profile.photo'), ['photo' => $this->png()]);
        $this->assertSame(1, DB::table('user_photos')->where('user_id', $user->id)->count());

        app(DataErasureService::class)->deleteOwnAccount($user, 'Passw0rd!');

        $this->assertNull(User::find($user->id));
        $this->assertSame(0, DB::table('user_photos')->count());
    }

    public function test_the_privacy_page_says_so_and_the_version_moved(): void
    {
        $this->assertSame('2026-10-24', config('legal.version'));

        $this->get('/privacy')->assertOk()->assertSee('Your own profile photo', false)->assertSee('Remove photo', false)->assertSee('Use a photo of the code', false)
            ->assertSee('2026-10-24', false)->assertSee('2026-10-23', false);
        $this->get('/terms')->assertOk()->assertSee('profile photo', false);
        $this->get('/cookies')->assertOk()->assertSee('profile photo', false);
    }
}
