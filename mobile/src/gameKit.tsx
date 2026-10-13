import { useEffect, useRef, useState, type ReactNode } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { createAudioPlayer, setAudioModeAsync, type AudioPlayer } from 'expo-audio';
import * as api from './api';
import { Feedback } from './Feedback';
import { Animation, Icon, IconBtn } from './ui';
import { colors, fonts, size } from './theme';
import { s } from './scale';

// The pieces every Practice Game shares: the three short sounds, the memory that lets a child leave a game and come back to
// the same board, the header, the "level up" overlay and the "all done" screen that reports the game and shows new badges.
// Every game is a read-aloud game.
// Games are free play: no points, no streak, no level. The one thing a finished game reports is a row, so the Games badges
// can be earned.

// ------------------------------------------------------------------ sounds (Mixkit free licence, the same three files as the website)

export type Sfx = 'correct' | 'wrong' | 'level';

const SOURCES: Record<Sfx, number> = {
  correct: require('../assets/sounds/correct.mp3'),
  wrong: require('../assets/sounds/wrong.mp3'),
  level: require('../assets/sounds/level-complete.mp3'),
};
const players: Partial<Record<Sfx, AudioPlayer>> = {};

/** Switch the phone from recording back to playing, so sounds and the read-aloud voice come out of the speaker. */
export async function prepareSounds() {
  try {
    await setAudioModeAsync({ allowsRecording: false, playsInSilentMode: true });
  } catch {
    /* sounds are a bonus: the game works without them */
  }
}

/** A short sound. A wrong match is played quietly on purpose: it must never sound scary to a six year old. */
export function playSfx(name: Sfx, volume = 0.5) {
  try {
    const player = (players[name] ??= createAudioPlayer(SOURCES[name]));
    player.volume = volume;
    void player.seekTo(0);
    player.play();
  } catch {
    /* a sound that cannot play never stops a game */
  }
}

// ------------------------------------------------------------------ letters

/** A letter's NAME as it is said ("ay", "bee"): reading the bare letter would give a word like "a" or "I". */
export const LETTER_NAMES: Record<string, string> = {
  a: 'ay', b: 'bee', c: 'see', d: 'dee', e: 'ee', f: 'eff', g: 'jee', h: 'aitch', i: 'eye', j: 'jay',
  k: 'kay', l: 'el', m: 'em', n: 'en', o: 'oh', p: 'pee', q: 'cue', r: 'are', s: 'ess', t: 'tee',
  u: 'you', v: 'vee', w: 'double you', x: 'ex', y: 'why', z: 'zee',
};

// ------------------------------------------------------------------ memory

// Leaving a game (the Games tab, another tab, the phone's back button) keeps the board, so a child who comes back finds it
// as they left it, like the website. It lives only while the app is open: a game is about a minute long.
const memory = new Map<string, unknown>();
export const gameMemory = {
  get<T>(key: string): T | null {
    return (memory.get(key) as T | undefined) ?? null;
  },
  set(key: string, value: unknown) {
    memory.set(key, value);
  },
  clear(key: string) {
    memory.delete(key);
  },
};

// ------------------------------------------------------------------ pieces

/** Fisher-Yates shuffle, so a replay is never the same order twice. */
export function shuffle<T>(items: T[]): T[] {
  const a = items.slice();
  for (let i = a.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [a[i], a[j]] = [a[j], a[i]];
  }
  return a;
}

/**
 * The words for a round: the child's own missed words at this level first, topped up with the words that practise their
 * kind of mistake, then the level's list, each group in a fresh random order. Run fresh for every round, so a child held at a
 * level gets a different set, and their own missed words get a fresh chance to come back.
 */
export function pickWords(data: api.WordLevels['levels'][string], count: number): string[] {
  let picked = shuffle(data.struggling).slice(0, count);
  if (picked.length < count) {
    const free = (w: string) => !picked.includes(w);
    const favoured = shuffle(data.favoured.filter(free));
    const rest = shuffle(data.fallback.filter((w) => free(w) && !favoured.includes(w)));
    picked = picked.concat(favoured.concat(rest).slice(0, count - picked.length));
  }
  return shuffle(picked);
}

const LEVEL_ICON: Record<number, string> = { 1: 'plant', 2: 'leaf', 3: 'tree' };

/** The top of a game: a way out, the level and the round. */
export function GameHeader({ onBack, level, label, icons }: { onBack: () => void; level: number; label: string; icons?: Record<number, string> }) {
  return (
    <View style={styles.header}>
      <IconBtn icon="caret-left" label="Back to Games" onPress={onBack} />
      <View style={styles.headerMid} accessible accessibilityLabel={`Level ${level}. ${label}`}>
        <View style={styles.levelPill}>
          <Icon name={(icons ?? LEVEL_ICON)[level] ?? 'plant'} size={s(20)} color={colors.ok} />
          <Text maxFontSizeMultiplier={1.2} style={styles.levelText}>
            Level {level}
          </Text>
        </View>
        <Text maxFontSizeMultiplier={1.2} style={styles.round}>
          {label}
        </Text>
      </View>
      <View style={{ width: size.touch }} />
    </View>
  );
}

/** A short full-board message ("Nice matching!", "Level up!") that appears for a moment between steps. */
export function Celebrate({ text, big }: { text: string; big?: boolean }) {
  return (
    <View style={styles.celebrate} accessibilityLiveRegion="polite">
      <Animation name="happy" px={s(big ? 130 : 100)} />
      <Text maxFontSizeMultiplier={1.2} style={styles.celebrateText}>
        {text}
      </Text>
    </View>
  );
}

/** Dots that show how far through a game a child is: finished, now, still to come. */
export function Dots({ done, total }: { done: number; total: number }) {
  return (
    <View style={styles.dots} accessible accessibilityLabel={`Set ${done + 1} of ${total}`}>
      {Array.from({ length: total }, (_, i) => (
        <View key={i} style={[styles.dot, i < done && { backgroundColor: colors.ok }, i === done && { backgroundColor: colors.orange }]} />
      ))}
    </View>
  );
}

/**
 * The "all done" screen. It reports the finished game once (so the Games badges can be earned) and shows any badges that
 * came back. The report can never break a game: if it fails the child simply sees no new badge.
 */
export function GameDone({
  game,
  firstName,
  level,
  climbed,
  report,
  onAgain,
  onExit,
  extra,
}: {
  game: api.GameName;
  firstName: string;
  level: number;
  climbed: boolean;
  report: api.GameFinishBody;
  onAgain: () => void;
  onExit: () => void;
  extra?: ReactNode;
}) {
  const [badges, setBadges] = useState<api.NewBadge[]>([]);
  const sent = useRef(false);

  useEffect(() => {
    if (sent.current) return;
    sent.current = true;
    playSfx('level', 0.6);
    api
      .finishGame(game, report)
      .then((r) => setBadges(r.newBadges.map((b) => ({ ...b, description: '' }))))
      .catch(() => {});
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <Feedback
      mood="happy"
      heading={`All done, ${firstName}!`}
      subline={`You finished at Level ${level}. ${climbed ? 'Great climbing!' : 'Great job!'}`}
      badges={badges}
      details={extra}
      buttonLabel="Play Again"
      onButton={onAgain}
      second={{ label: 'Back to Games', onPress: onExit }}
    />
  );
}

const styles = StyleSheet.create({
  header: { flexDirection: 'row', alignItems: 'center', gap: s(10), minHeight: s(52) },
  headerMid: { flex: 1, alignItems: 'center', gap: s(2) },
  levelPill: { flexDirection: 'row', alignItems: 'center', gap: s(6), backgroundColor: colors.mint, borderRadius: s(99), paddingHorizontal: s(12), minHeight: s(34) },
  levelText: { fontFamily: fonts.uiBold, fontSize: s(17), color: colors.ink },
  round: { fontFamily: fonts.ui, fontSize: s(14), color: colors.slate },
  celebrate: { ...StyleSheet.absoluteFill, backgroundColor: 'rgba(255,255,255,0.96)', alignItems: 'center', justifyContent: 'center', gap: s(6), zIndex: 5, borderRadius: s(18) },
  celebrateText: { fontFamily: fonts.display, fontSize: s(28), lineHeight: s(34), color: colors.orange, textAlign: 'center' },
  dots: { flexDirection: 'row', gap: s(8), justifyContent: 'center' },
  dot: { width: s(12), height: s(12), borderRadius: s(6), backgroundColor: colors.sky2 },
});
