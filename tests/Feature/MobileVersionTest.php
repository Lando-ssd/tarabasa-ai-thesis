<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The phone app shows its own version in the technical note of a failed send (so a tester's screenshot says which build
 * it came from). That number lives in TypeScript, which cannot read app.json, so this test keeps the two equal, and
 * keeps the safe retry from quietly turning into something that could score a reading twice.
 */
class MobileVersionTest extends TestCase
{
    public function test_the_version_shown_in_the_app_is_the_one_in_app_json(): void
    {
        $json = json_decode(file_get_contents(base_path('mobile/app.json')), true);
        $config = file_get_contents(base_path('mobile/src/config.ts'));

        $this->assertSame(1, preg_match("/APP_VERSION = '([^']+)'/", $config, $found), 'config.ts must define APP_VERSION.');
        $this->assertSame($json['expo']['version'], $found[1], 'APP_VERSION in mobile/src/config.ts must equal "version" in mobile/app.json.');
    }

    public function test_a_failed_upload_is_only_retried_when_it_never_reached_the_server(): void
    {
        $api = file_get_contents(base_path('mobile/src/api.ts'));
        $config = file_get_contents(base_path('mobile/src/config.ts'));

        // Only a quick network failure is repeated by itself; a timeout or a server answer never is.
        $this->assertStringContainsString("e.kind === 'network' && e.seconds <= UPLOAD_QUICK_FAILURE_SECONDS", $api);
        $this->assertStringNotContainsString("e.kind === 'timeout' && tries", $api);
        // At most two extra tries, and only within a few seconds.
        $this->assertMatchesRegularExpression('/UPLOAD_RETRY_PAUSES_MS = \[\s*3_000,\s*6_000\s*\]/', $config);
        $this->assertMatchesRegularExpression('/UPLOAD_QUICK_FAILURE_SECONDS = (\d|10)\b/', $config);
    }

    /**
     * A tester's phone failed instantly ("could not reach TaraBasa" within half a second of tapping done) although the
     * same phone had just loaded the check from the server: the file-address upload was the suspect. So the recording is
     * now read on the phone and sent as plain bytes first, and the old way stays as the backup.
     */
    public function test_a_recording_is_sent_as_bytes_first_with_the_old_way_as_backup(): void
    {
        $api = file_get_contents(base_path('mobile/src/api.ts'));
        $multipart = file_get_contents(base_path('mobile/src/multipart.ts'));

        $this->assertStringContainsString('buildMultipart(parts, boundary)', $api, 'Method A (bytes) must be the first way to send.');
        $this->assertStringContainsString('body: await buildForm()', $api, 'Method B (the file address) must stay as the backup.');
        // Only a connection that never got an answer falls through to the backup; a timeout must never send a second copy.
        $this->assertStringContainsString("if (a.kind !== 'network') throw a;", $api);
        // A recording that is gone is said plainly, not blamed on the internet.
        $this->assertStringContainsString('the recording file was not found', $api);
        // The technical note carries what the phone really said, so a screenshot is enough to find the cause.
        $this->assertStringContainsString("notes.push(`A: ", $api);
        $this->assertStringContainsString("notes.push(`B: ", $api);
        // The builder must stay plain code that any computer can test.
        $this->assertStringNotContainsString("from 'react-native'", $multipart);
        $this->assertStringContainsString('export function buildMultipart', $multipart);
    }

    /**
     * "Use a photo of the code" (website parity): the phone can read a learner card from a photo in the gallery, for a teacher
     * adding learners and for a child signing in. The image picker's config plugin BLOCKS the microphone permission when told
     * microphonePermission is false, which would stop every recording, so that option must never be set.
     */
    public function test_a_card_can_be_read_from_a_photo_and_the_microphone_stays_allowed(): void
    {
        $json = json_decode(file_get_contents(base_path('mobile/app.json')), true);
        $picker = collect($json['expo']['plugins'])->first(fn ($p) => is_array($p) && $p[0] === 'expo-image-picker');

        $this->assertNotNull($picker, 'the image picker plugin must be listed in app.json');
        $this->assertArrayNotHasKey('microphonePermission', $picker[1] ?? [], 'never block the microphone: every reading needs it');
        $this->assertNotContains('android.permission.RECORD_AUDIO', $json['expo']['android']['blockedPermissions']);

        $photo = file_get_contents(base_path('mobile/src/cardPhoto.ts'));
        $this->assertStringContainsString('isGoodCard(item.data)', $photo, 'only a real TaraBasa card (shape and check digit) counts, as with the live camera');
        $this->assertStringContainsString("scanFromURLAsync(asset.uri, ['qr'])", $photo);

        $sheet = file_get_contents(base_path('mobile/src/teacher/AddLearner.tsx'));
        foreach (['Scan QR codes', 'Use a photo of the code', 'OR TYPE THE CODE', 'One class, one grade'] as $text) {
            $this->assertStringContainsString($text, $sheet, "the Add a learner sheet must say: {$text}");
        }
        $this->assertStringContainsString('Use a photo of my card', file_get_contents(base_path('mobile/src/CardScanner.tsx')));
    }
}
