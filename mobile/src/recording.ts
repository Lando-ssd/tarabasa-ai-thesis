// What the phone decides about a recording BEFORE it is sent, so a child never waits a minute to hear "we did not hear
// anything". The same idea as the website (it listens with the browser's Web Audio and sends nothing when the microphone
// heard no voice at all), now with the level the phone's recorder reports.
//
// The level is in dB relative to full scale: 0 is the loudest the microphone can record, about -20 is a normal voice
// held close, about -60 and lower is a quiet room. Android reports the loudest moment since the last reading, the
// browser and iOS report an average, so the numbers below are chosen to be safe for both: far above a quiet room and
// far below speech. They were set from how these values behave, NOT from a real child on a real phone, so they are
// the first thing to adjust if the phone test finds a false "we did not hear anything".

/** A recording shorter than this was most likely an accidental tap. */
export const MIN_SECONDS = 1;

/** If the loudest moment of the whole recording never reached this, nothing was said. */
export const SILENT_DB = -45;

/** A level above this counts as a voice. The warm-up needs a few of these in a row of readings. */
export const VOICE_DB = -35;
export const WARM_VOICE_SAMPLES = 3;
export const WARM_MAX_SECONDS = 4;

/** A game that records a row or a sentence stops by itself once the voice has been quiet this long (and a voice was heard). */
export const PAUSE_MS = 1700;
export const PAUSE_MIN_VOICE = 5;

/** How often the level is read while recording, in milliseconds. */
export const METER_MS = 100;

export type Judgement = 'ok' | 'silent' | 'short';

/**
 * Judge a finished recording. `peakDb` is the loudest level seen while recording, or null when the phone gave no level
 * at all. With no level information the recording is allowed through, because the server's own check still catches
 * silence: a missing meter must never stop a child from reading.
 */
export function judgeRecording(seconds: number, peakDb: number | null): Judgement {
  if (seconds < MIN_SECONDS) return 'short';
  if (peakDb === null) return 'ok';
  return peakDb < SILENT_DB ? 'silent' : 'ok';
}

/** Where a level sits on the voice meter, from 0 (quiet room) to 1 (loud voice). */
export function levelShare(db: number | null | undefined): number {
  if (typeof db !== 'number' || !Number.isFinite(db)) return 0;
  const low = -60;
  const high = -15;
  return Math.min(1, Math.max(0, (db - low) / (high - low)));
}

/** The words shown when a recording is stopped before it is sent. */
export const HINTS: Record<Exclude<Judgement, 'ok'>, string> = {
  silent: 'We did not hear anything. Hold the phone a little closer, say the words out loud, then tap the check mark.',
  short: 'That was very short. Tap the mic, read the words, then tap the check mark when you finish.',
};
