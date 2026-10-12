import { useCallback, useEffect, useRef, useState } from 'react';
import { Animated, Pressable, StyleSheet, Text, View, useWindowDimensions } from 'react-native';
import * as Speech from 'expo-speech';
import * as api from './api';
import { Celebrate, Dots, GameDone, GameHeader, gameMemory, pickWords, playSfx, prepareSounds, shuffle } from './gameKit';
import { SayWord, type SayResult } from './SayWord';
import { useSession } from './session';
import { Btn, Busy, Icon, IconBtn, Notice, Screen, Small } from './ui';
import { colors, fonts, size } from './theme';

// Word Builder, the same rules as the website: put scrambled letters in order to spell a word, five words a round, three levels (3
// letter words, 4 letter words, 5 and 6 letter words). A round is one attempt at a level; finishing it with three or fewer wrong
// taps climbs a level, and a game is up to three rounds, or one round once a child is on the top level. The words are the child's
// own missed words first, then the level's list. Free play: no points, no streak, no level.

const MAX_ATTEMPTS = 3;
const WRONG_THRESHOLD = 3;
const WORDS_PER_ROUND = 5;
const MEMORY_KEY = (learnerId: number) => `word-builder:${learnerId}`;

type Snapshot = {
  level: number;
  attempt: number;
  climbed: boolean;
  topCleared: boolean;
  perfect: boolean;
  roundWords: string[];
  wordIndex: number;
  letters: string[];
  used: boolean[];
  spelled: string;
  wrong: number;
};
type Finished = { level: number; climbed: boolean; report: api.GameFinishBody };

const buildRoundWords = (data: api.WordLevels['levels'][string]) => pickWords(data, WORDS_PER_ROUND);

function scramble(word: string): string[] {
  let letters = shuffle(word.split(''));
  if (letters.join('') === word && word.length > 1) letters = shuffle(letters); // never hand over the word already spelled
  return letters;
}

export function WordBuilder({ onExit }: { onExit: () => void }) {
  const { learner } = useSession();
  const [data, setData] = useState<api.WordLevels | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);
    try {
      setData(await api.gameWords());
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Something went wrong. Please try again.');
    }
  }, []);

  useEffect(() => {
    load();
    prepareSounds();
    return () => void Speech.stop();
  }, [load]);

  if (error) {
    return (
      <Screen footer={<Btn label="Back to Games" tone="ghost" onPress={onExit} />}>
        <View style={{ flex: 1, justifyContent: 'center', gap: 12 }}>
          <Notice text={error} />
          <Btn label="Try Again" onPress={load} />
        </View>
      </Screen>
    );
  }
  if (!data) {
    return (
      <Screen scroll={false}>
        <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center' }}>
          <Busy text="Getting your words ready" />
        </View>
      </Screen>
    );
  }
  return <Round key={learner.id} data={data} firstName={learner.firstName} memoryKey={MEMORY_KEY(learner.id)} onExit={onExit} />;
}

function Round({ data, firstName, memoryKey, onExit }: { data: api.WordLevels; firstName: string; memoryKey: string; onExit: () => void }) {
  const saved = useRef(gameMemory.get<Snapshot>(memoryKey));
  const start = saved.current;
  const first = useRef<string[] | null>(null);
  if (!start && !first.current) first.current = buildRoundWords(data.levels[String(data.startLevel)]);

  const [level, setLevel] = useState(start?.level ?? data.startLevel);
  const [attempt, setAttempt] = useState(start?.attempt ?? 0);
  const [climbed, setClimbed] = useState(start?.climbed ?? false);
  const [topCleared, setTopCleared] = useState(start?.topCleared ?? false);
  const [perfect, setPerfect] = useState(start?.perfect ?? false);
  const [roundWords, setRoundWords] = useState<string[]>(start?.roundWords ?? first.current ?? []);
  const [wordIndex, setWordIndex] = useState(start?.wordIndex ?? 0);
  const [letters, setLetters] = useState<string[]>(start?.letters ?? scramble((start?.roundWords ?? first.current ?? [''])[0]));
  const [used, setUsed] = useState<boolean[]>(start?.used ?? new Array((first.current ?? [''])[0].length).fill(false));
  const [spelled, setSpelled] = useState(start?.spelled ?? '');
  const [wrong, setWrong] = useState(start?.wrong ?? 0);
  const [wrongTile, setWrongTile] = useState<number | null>(null);
  const [step, setStep] = useState<'spell' | 'say'>('spell');
  const [message, setMessage] = useState<string | null>(null);
  const [sayNote, setSayNote] = useState('Now say it out loud.');
  const [sayTries, setSayTries] = useState(0);
  const [micOff, setMicOff] = useState(false);
  const [finished, setFinished] = useState<Finished | null>(null);
  const active = useRef(false); // the child has tapped something, so the word can be said on its own
  const timers = useRef<ReturnType<typeof setTimeout>[]>([]);
  const later = (ms: number, work: () => void) => {
    timers.current.push(setTimeout(work, ms));
  };
  useEffect(() => () => timers.current.forEach(clearTimeout), []);

  const word = roundWords[wordIndex] ?? '';

  const snapshot = (over: Partial<Snapshot> = {}) =>
    gameMemory.set(memoryKey, { level, attempt, climbed, topCleared, perfect, roundWords, wordIndex, letters, used, spelled, wrong, ...over } satisfies Snapshot);

  const say = (w: string) => {
    Speech.stop();
    Speech.speak(w, { language: 'en-US', rate: 0.7 });
  };

  function newWord(words: string[], index: number, over: Partial<Snapshot> = {}) {
    const w = words[index];
    const next = scramble(w);
    const fresh = new Array(w.length).fill(false);
    setWordIndex(index);
    setLetters(next);
    setUsed(fresh);
    setSpelled('');
    setStep('spell');
    snapshot({ roundWords: words, wordIndex: index, letters: next, used: fresh, spelled: '', ...over });
    if (active.current) say(w);
  }

  function tap(i: number) {
    if (used[i] || step !== 'spell') return;
    active.current = true;
    const expected = word[spelled.length];
    if (letters[i].toLowerCase() === expected) {
      const nextUsed = used.map((u, n) => (n === i ? true : u));
      const nextSpelled = spelled + letters[i];
      setUsed(nextUsed);
      setSpelled(nextSpelled);
      playSfx('correct', 0.55);
      if (nextSpelled.length === word.length) later(350, afterSpelled);
      else snapshot({ used: nextUsed, spelled: nextSpelled });
    } else {
      const wrongNow = wrong + 1;
      setWrong(wrongNow);
      setWrongTile(i);
      later(400, () => setWrongTile(null));
      playSfx('wrong', 0.3);
      snapshot({ wrong: wrongNow });
    }
  }

  // After the last letter: ask the child to say the word out loud (optional), then celebrate.
  function afterSpelled() {
    if (micOff) {
      wordComplete(false);
      return;
    }
    setSayTries(0);
    setSayNote('Now say it out loud.');
    setStep('say');
    say(word);
  }

  function sayResult(result: SayResult) {
    if (result === 'unavailable') {
      setMicOff(true);
      wordComplete(false);
    } else if (result === 'heard') {
      wordComplete(true);
    } else {
      const tries = sayTries + 1;
      setSayTries(tries);
      if (tries >= 2) {
        // There is no wrong answer here: ask once more, then move on kindly.
        setSayNote('Good try! On we go.');
        later(900, () => wordComplete(false));
      } else {
        setSayNote('Tara is not sure. Try once more!');
      }
    }
  }

  function wordComplete(saidIt: boolean) {
    Speech.stop();
    setStep('spell');
    playSfx('level', 0.5);
    setMessage(`${saidIt ? 'You said' : 'You spelled'} "${word.toUpperCase()}"!`);
    later(1300, () => {
      setMessage(null);
      const nextIndex = wordIndex + 1;
      if (nextIndex >= roundWords.length) roundDone();
      else newWord(roundWords, nextIndex);
    });
  }

  function roundDone() {
    const atCeiling = level >= 3;
    const top = topCleared || atCeiling;
    const perfectNow = perfect || wrong === 0;
    const up = wrong <= WRONG_THRESHOLD && level < 3;
    const climbedNow = climbed || up;
    const attemptNow = attempt + 1;
    const nextLevel = up ? level + 1 : level;
    setTopCleared(top);
    setPerfect(perfectNow);
    setClimbed(climbedNow);

    if (attemptNow >= MAX_ATTEMPTS || atCeiling) {
      gameMemory.clear(memoryKey);
      setFinished({
        level: nextLevel,
        climbed: climbedNow,
        report: { level_reached: nextLevel, top_level_cleared: top, had_perfect_round: perfectNow, rounds: Math.max(1, Math.min(3, attemptNow)) },
      });
      return;
    }

    setAttempt(attemptNow);
    setLevel(nextLevel);
    setWrong(0);
    const words = buildRoundWords(data.levels[String(nextLevel)]);
    setRoundWords(words);
    const carry = { level: nextLevel, attempt: attemptNow, wrong: 0, climbed: climbedNow, topCleared: top, perfect: perfectNow };
    if (up) {
      playSfx('level', 0.6);
      setMessage('Level up!');
      later(1400, () => {
        setMessage(null);
        newWord(words, 0, carry);
      });
    } else {
      newWord(words, 0, carry);
    }
  }

  function playAgain() {
    gameMemory.clear(memoryKey);
    setFinished(null);
    setLevel(data.startLevel);
    setAttempt(0);
    setClimbed(false);
    setTopCleared(false);
    setPerfect(false);
    setWrong(0);
    const words = buildRoundWords(data.levels[String(data.startLevel)]);
    setRoundWords(words);
    newWord(words, 0, { level: data.startLevel, attempt: 0, wrong: 0, climbed: false, topCleared: false, perfect: false });
  }

  if (finished) {
    return (
      <GameDone game="word-builder" firstName={firstName} level={finished.level} climbed={finished.climbed} report={finished.report} onAgain={playAgain} onExit={onExit} />
    );
  }

  return (
    <Screen>
      <GameHeader onBack={onExit} level={level} label={`Round ${attempt + 1} of ${MAX_ATTEMPTS}`} />
      <View style={{ marginTop: 4, gap: 4 }}>
        <Small style={{ textAlign: 'center' }}>Word {wordIndex + 1} of {roundWords.length}</Small>
        <Dots done={wordIndex} total={roundWords.length} />
      </View>

      <View style={styles.stage}>
        {step === 'spell' ? (
          <>
            <Small style={{ textAlign: 'center' }}>Tap the letters in order to spell the word.</Small>
            <View style={styles.listen}>
              <Btn label="Hear the word" icon="speaker-high" tone="ghost" compact onPress={() => { active.current = true; say(word); }} />
            </View>
            <Slots word={word} spelled={spelled} />
            <Tiles letters={letters} used={used} wrongTile={wrongTile} onTap={tap} />
          </>
        ) : (
          <View style={styles.say}>
            <Text maxFontSizeMultiplier={1.2} style={styles.sayWord}>
              You spelled "{word.toUpperCase()}"!
            </Text>
            <Text maxFontSizeMultiplier={1.2} style={styles.sayNote} accessibilityLiveRegion="polite">
              {sayNote}
            </Text>
            {sayTries < 2 ? <SayWord word={word} onResult={sayResult} /> : null}
            <IconBtn icon="speaker-high" label="Hear the word again" onPress={() => say(word)} />
            <Btn label="Skip This Part" tone="ghost" compact onPress={() => { Speech.stop(); wordComplete(false); }} />
          </View>
        )}
        {message ? <Celebrate text={message} big={message === 'Level up!'} /> : null}
      </View>
    </Screen>
  );
}

/** One slot for each letter of the word, filling in green as the child spells it. */
function Slots({ word, spelled }: { word: string; spelled: string }) {
  const { width } = useWindowDimensions();
  const board = Math.min(size.maxWidth, width) - size.gutter * 2;
  const gap = 8;
  const slot = Math.min(66, (board - gap * (word.length - 1)) / word.length);
  return (
    <View style={[styles.slots, { gap }]} accessible accessibilityLabel={`The word so far: ${spelled || 'nothing yet'}, ${word.length} letters`}>
      {word.split('').map((_, i) => (
        <View key={i} style={[styles.slot, { width: slot, height: slot * 1.15 }, i < spelled.length && styles.slotOn]}>
          {i < spelled.length ? (
            <Text maxFontSizeMultiplier={1.1} style={[styles.slotText, { fontSize: slot * 0.62 }]}>
              {spelled[i]}
            </Text>
          ) : null}
        </View>
      ))}
    </View>
  );
}

/** The scrambled letters. Each tile is its own tap (by position, not by letter), so a word with a repeated letter works. */
function Tiles({ letters, used, wrongTile, onTap }: { letters: string[]; used: boolean[]; wrongTile: number | null; onTap: (i: number) => void }) {
  return (
    <View style={styles.tiles}>
      {letters.map((letter, i) => (
        <Tile key={`${letter}-${i}`} letter={letter} used={used[i]} wrong={wrongTile === i} onPress={() => onTap(i)} />
      ))}
    </View>
  );
}

function Tile({ letter, used, wrong, onPress }: { letter: string; used: boolean; wrong: boolean; onPress: () => void }) {
  const shake = useRef(new Animated.Value(0)).current;
  useEffect(() => {
    if (!wrong) return;
    Animated.sequence([
      Animated.timing(shake, { toValue: 1, duration: 60, useNativeDriver: true }),
      Animated.timing(shake, { toValue: -1, duration: 60, useNativeDriver: true }),
      Animated.timing(shake, { toValue: 1, duration: 60, useNativeDriver: true }),
      Animated.timing(shake, { toValue: 0, duration: 60, useNativeDriver: true }),
    ]).start();
  }, [wrong, shake]);
  return (
    <Animated.View style={{ transform: [{ translateX: shake.interpolate({ inputRange: [-1, 1], outputRange: [-6, 6] }) }] }}>
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={`Letter ${letter}`}
        accessibilityState={{ disabled: used }}
        disabled={used}
        onPress={onPress}
        style={({ pressed }) => [styles.tile, used && styles.tileUsed, wrong && styles.tileWrong, pressed && { transform: [{ translateY: 3 }], borderBottomWidth: 2 }]}
      >
        <Text maxFontSizeMultiplier={1.1} style={[styles.tileText, used && { color: colors.slate }]}>
          {letter}
        </Text>
      </Pressable>
    </Animated.View>
  );
}

const styles = StyleSheet.create({
  stage: { flexGrow: 1, marginTop: 12, gap: 16, alignItems: 'center', justifyContent: 'space-evenly', paddingBottom: 12 },
  listen: { alignSelf: 'center' },
  slots: { flexDirection: 'row', justifyContent: 'center', flexWrap: 'wrap' },
  slot: { borderRadius: 14, backgroundColor: colors.sky, borderBottomWidth: 4, borderBottomColor: colors.sky2, alignItems: 'center', justifyContent: 'center' },
  slotOn: { backgroundColor: colors.mint, borderBottomColor: '#b9dfc9' },
  slotText: { fontFamily: fonts.display, color: colors.ok },
  tiles: { flexDirection: 'row', flexWrap: 'wrap', gap: 14, justifyContent: 'center' },
  tile: { width: 80, height: 88, borderRadius: 20, backgroundColor: colors.white, borderWidth: 2, borderColor: colors.sky2, borderBottomWidth: 6, alignItems: 'center', justifyContent: 'center' },
  tileUsed: { backgroundColor: colors.unsureSoft, borderColor: colors.line, opacity: 0.55 },
  tileWrong: { borderColor: '#ff8a80', backgroundColor: colors.badSoft },
  tileText: { fontFamily: fonts.display, fontSize: 46, lineHeight: 58, color: colors.orange },
  say: { alignItems: 'center', gap: 12, alignSelf: 'stretch' },
  sayWord: { fontFamily: fonts.display, fontSize: 28, lineHeight: 34, color: colors.orange, textAlign: 'center' },
  sayNote: { fontFamily: fonts.uiBold, fontSize: 20, color: colors.ink, textAlign: 'center' },
});
