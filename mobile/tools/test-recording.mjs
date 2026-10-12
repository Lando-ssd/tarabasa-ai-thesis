// A small check of the rules in src/recording.ts. Run from mobile/:  node --experimental-strip-types tools/test-recording.mjs
import assert from 'node:assert/strict';
import { judgeRecording, levelShare, MIN_SECONDS, SILENT_DB } from '../src/recording.ts';

// An accidental tap is "short", whatever the level.
assert.equal(judgeRecording(0.3, -10), 'short');
assert.equal(judgeRecording(MIN_SECONDS - 0.01, null), 'short');

// A recording whose loudest moment was below the floor is silent.
assert.equal(judgeRecording(4, -160), 'silent'); // Android reports -160 for pure silence
assert.equal(judgeRecording(4, -55), 'silent'); // a quiet room
assert.equal(judgeRecording(4, SILENT_DB - 0.1), 'silent');

// A voice, even a quiet one, goes through.
assert.equal(judgeRecording(4, SILENT_DB), 'ok');
assert.equal(judgeRecording(4, -30), 'ok');
assert.equal(judgeRecording(4, -12), 'ok');

// No level at all (no meter): never block a child.
assert.equal(judgeRecording(4, null), 'ok');

// The voice meter stays between 0 and 1.
assert.equal(levelShare(null), 0);
assert.equal(levelShare(undefined), 0);
assert.equal(levelShare(-160), 0);
assert.equal(levelShare(0), 1);
assert.ok(levelShare(-35) > 0.3 && levelShare(-35) < 0.7);

console.log('recording rules: all checks passed');
