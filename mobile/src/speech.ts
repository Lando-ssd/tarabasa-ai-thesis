import { createAudioPlayer, type AudioPlayer } from 'expo-audio';
import * as Speech from 'expo-speech';

// Tara's voice on the phone. Two ways to say something:
//   1. Recorded clips (Sound Hunt): the server lists the clips that exist (key => address) and a line is played from clips only when EVERY clip it
//      needs exists, so one line never mixes two voices. The clips are made once and sound the same on every phone.
//   2. The phone's own read-aloud voice (expo-speech). A phone has several voices and some are robotic, so the English voices are ranked by what
//      their names and languages say: Philippine English first, then enhanced and natural ones, then women's names, and known men's names last.
//      A voice does not say whether it is a woman's or a man's, so this is a best guess; it is the same ranking the website uses
//      (resources/views/partials/speak.blade.php).
// Nothing here ever throws: a phone with no voice simply stays quiet.

let chosen: string | undefined;
let found = false;

const FEMALE = /aria|jenny|\bava\b|emma|sonia|libby|samantha|zira|susan|allison|karen|tessa|moira|serena|victoria|google us english|female/;
const MALE = /david|\bmark\b|\bguy\b|ryan|james|george|\bmale\b|alex|fred|daniel|\btom\b|eric|brian|christopher|davis|tony/;

function norm(lang: string | undefined) {
  return (lang ?? '').replace('_', '-');
}

export function rankVoice(v: Pick<Speech.Voice, 'identifier' | 'name' | 'language' | 'quality'>): number {
  const n = `${v.name ?? ''} ${v.identifier ?? ''}`.toLowerCase();
  const lang = norm(v.language);
  let score = 0;
  if (/^en/i.test(lang)) score += 10;
  if (/^en-ph/i.test(lang) || n.includes('philippines')) score += 14;
  if (/^en-us/i.test(lang)) score += 2;
  if (v.quality === Speech.VoiceQuality.Enhanced) score += 4;
  if (/natural|online|neural|network/.test(n)) score += 6;
  if (/\brosa\b/.test(n)) score += 6;
  if (FEMALE.test(n)) score += 6;
  if (MALE.test(n)) score -= 8;
  return score;
}

/** Look at the phone's voices once and remember the best English one. Safe to call again: it stops looking once it has found one. */
export async function prepareVoice(): Promise<void> {
  if (found) return;
  try {
    const voices = (await Speech.getAvailableVoicesAsync()).filter((v) => /^en/i.test(norm(v.language)));
    if (!voices.length) return;
    voices.sort((a, b) => rankVoice(b) - rankVoice(a));
    chosen = voices[0].identifier;
    found = true;
  } catch {
    /* no voice list: the phone's default voice is used */
  }
}
void prepareVoice();

/** Say a line with the phone's own voice, slowly, stopping whatever was being said. `onDone` runs when it ends or is stopped. */
export function say(text: string, rate = 0.8, onDone?: () => void) {
  if (!found) void prepareVoice();
  try {
    void Speech.stop();
    Speech.speak(text, {
      ...(chosen ? { voice: chosen } : { language: 'en-US' }),
      rate,
      ...(onDone ? { onDone, onStopped: onDone, onError: onDone } : {}),
    });
  } catch {
    onDone?.();
  }
}

export function stopSaying() {
  try {
    void Speech.stop();
  } catch {
    /* nothing to stop */
  }
}

// ------------------------------------------------------------------ recorded clips

export type Line = { parts: string[]; text: string };

let player: AudioPlayer | null = null;
let token = 0;

/** Stop a clip that is playing, and anything queued behind it. */
export function stopClips() {
  token++;
  try {
    player?.pause();
    player?.remove();
  } catch {
    /* already gone */
  }
  player = null;
}

/** Stop everything Tara is saying, clip or voice. */
export function stopAll() {
  stopClips();
  stopSaying();
}

/**
 * Say a line: from the clips when every clip it needs exists (`clips` is key => address), otherwise with the phone's own voice, the whole line.
 * `onDone` runs when the line has been said (or could not be).
 */
export function playLine(line: Line, clips: Record<string, string>, rate = 0.8, onDone?: () => void) {
  stopAll();
  const mine = token;
  const fallback = () => {
    if (mine === token) say(line.text, rate, onDone);
  };

  if (!line.parts.length || !line.parts.every((key) => clips[key])) {
    fallback();
    return;
  }

  let i = 0;
  const next = () => {
    if (mine !== token) return;
    if (i >= line.parts.length) {
      onDone?.();
      return;
    }
    try {
      const p = createAudioPlayer(clips[line.parts[i++]]);
      player = p;
      let moved = false;
      const advance = () => {
        if (moved) return;
        moved = true;
        try {
          p.remove();
        } catch {
          /* released */
        }
        if (mine === token) setTimeout(next, 90);
      };
      p.addListener('playbackStatusUpdate', (status) => {
        if (status.didJustFinish) advance();
      });
      p.play();
      // A clip that never reports it finished (a lost connection) must not hold the game: move on after a generous wait.
      setTimeout(advance, 6000);
    } catch {
      fallback();
    }
  };
  next();
}
